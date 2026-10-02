<?php

// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace OCA\OtherAccounts\Enrollment;

use OCA\OtherAccounts\Converge\ConvergeScope;
use OCA\OtherAccounts\Mail\MailProver;
use OCA\OtherAccounts\Service\GoogleGrant;
use OCA\OtherAccounts\Service\Mailbox;
use OCA\OtherAccounts\Service\NetBoxMailboxes;
use OCA\OtherAccounts\Service\OpenBao;

/**
 * Writes the SoT the Stalwart converge reads for one external mailbox: the credential at
 * `<services>/external_<label>` in OpenBao and the address in the owning mailbox's
 * `send_as_addresses` in NetBox. A credential is proven before anything is stored, and an address
 * already owned by another mailbox is refused.
 */
final readonly class Enrollment
{
    public const string GOOGLE_IMAP_HOST = 'imap.gmail.com';
    public const int IMAPS_PORT = 993;

    public function __construct(
        private OpenBao $bao,
        private string $servicesPath,
        private NetBoxMailboxes $mailboxes,
        private MailProver $prover,
    ) {}

    public function connectGoogle(EmailAddress $owner, EmailAddress $external, GoogleGrant $grant): ConvergeScope
    {
        $mailbox = $this->claim($owner, $external);
        $this->prover->imapXoauth2(self::GOOGLE_IMAP_HOST, self::IMAPS_PORT, $external->value, $grant->accessToken);
        $label = Label::forAddress($external);
        $this->bao->write($this->path($label), ['email' => $external->value, 'refresh_token' => $grant->refreshToken]);
        $this->mailboxes->setSendAs($mailbox, $mailbox->withSendAs($external));

        return ConvergeScope::connect($label, $owner);
    }

    public function connectImap(EmailAddress $owner, EmailAddress $external, ImapCredential $credential): ConvergeScope
    {
        $mailbox = $this->claim($owner, $external);
        $this->prover->imapPassword($credential->imapHost, $credential->imapPort, $credential->username, $credential->password);
        $this->prover->smtpPassword($credential->smtpHost, $credential->smtpPort, $credential->username, $credential->password);
        $label = Label::forAddress($external);
        $this->bao->write($this->path($label), ['email' => $external->value, ...$credential->toSecret()]);
        $this->mailboxes->setSendAs($mailbox, $mailbox->withSendAs($external));

        return ConvergeScope::connect($label, $owner);
    }

    public function disconnect(EmailAddress $owner, EmailAddress $external): ConvergeScope
    {
        $mailbox = $this->ownMailbox($owner);
        if (!$mailbox->owns($external)) {
            throw new EnrollmentException('That account is not connected to your mailbox.');
        }
        $label = Label::forAddress($external);
        $this->mailboxes->setSendAs($mailbox, $mailbox->withoutSendAs($external));
        $this->bao->destroy($this->path($label));

        return ConvergeScope::disconnect($label, $owner);
    }

    /** @return list<ConnectedAccount> */
    public function connected(EmailAddress $owner): array
    {
        $mailbox = $this->mailboxes->find($owner);
        if ($mailbox === null) {
            return [];
        }
        $accounts = [];
        foreach ($mailbox->sendAs as $address) {
            $external = EmailAddress::fromString($address);
            $label = Label::forAddress($external);
            $secret = $this->bao->read($this->path($label));
            $accounts[] = new ConnectedAccount($external, $label, ConnectionKind::fromSecret($secret));
        }

        return $accounts;
    }

    /** The user's own mailbox, refusing an address that another mailbox already owns. */
    private function claim(EmailAddress $owner, EmailAddress $external): Mailbox
    {
        if ($external->equals($owner)) {
            throw new EnrollmentException('That is your own mailbox address.');
        }
        $mailbox = $this->ownMailbox($owner);
        $current = $this->mailboxes->ownerOf($external);
        if ($current !== null && !$current->address->equals($owner)) {
            throw new EnrollmentException('That account is already connected to another mailbox.');
        }

        return $mailbox;
    }

    private function ownMailbox(EmailAddress $owner): Mailbox
    {
        return $this->mailboxes->find($owner) ?? throw new EnrollmentException('Your Nextcloud email address has no mailbox.');
    }

    private function path(Label $label): string
    {
        return "{$this->servicesPath}/{$label->secretName()}";
    }
}
