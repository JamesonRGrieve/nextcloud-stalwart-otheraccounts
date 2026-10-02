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
 * `<services>/external_<label>` in OpenBao and the address in the target mailbox's
 * `send_as_addresses` in NetBox. The target is a mailbox the user may use (their own, or one
 * whose `shared_with` lists them). A credential is proven before anything is stored, and an
 * address already owned by another mailbox is refused.
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

    public function connectGoogle(EmailAddress $user, EmailAddress $target, EmailAddress $external, GoogleGrant $grant): ConvergeScope
    {
        $mailbox = $this->claim($user, $target, $external);
        $this->prover->imapXoauth2(self::GOOGLE_IMAP_HOST, self::IMAPS_PORT, $external->value, $grant->accessToken);
        $label = Label::forAddress($external);
        $this->bao->write($this->path($label), ['email' => $external->value, 'refresh_token' => $grant->refreshToken]);
        $this->mailboxes->setSendAs($mailbox, $mailbox->withSendAs($external));

        return ConvergeScope::connect($label, $mailbox->address);
    }

    public function connectImap(EmailAddress $user, EmailAddress $target, EmailAddress $external, ImapCredential $credential): ConvergeScope
    {
        $mailbox = $this->claim($user, $target, $external);
        $this->prover->imapPassword($credential->imapHost, $credential->imapPort, $credential->username, $credential->password);
        $this->prover->smtpPassword($credential->smtpHost, $credential->smtpPort, $credential->username, $credential->password);
        $label = Label::forAddress($external);
        $this->bao->write($this->path($label), ['email' => $external->value, ...$credential->toSecret()]);
        $this->mailboxes->setSendAs($mailbox, $mailbox->withSendAs($external));

        return ConvergeScope::connect($label, $mailbox->address);
    }

    public function disconnect(EmailAddress $user, EmailAddress $target, EmailAddress $external): ConvergeScope
    {
        $mailbox = $this->usable($user, $target);
        if (!$mailbox->owns($external)) {
            throw new EnrollmentException('That account is not connected to that mailbox.');
        }
        $label = Label::forAddress($external);
        $this->mailboxes->setSendAs($mailbox, $mailbox->withoutSendAs($external));
        $this->bao->destroy($this->path($label));

        return ConvergeScope::disconnect($label, $mailbox->address);
    }

    /** @return list<MailboxAccounts> every mailbox the user may use, own mailbox first, with its accounts */
    public function connected(EmailAddress $user): array
    {
        return array_map(fn(Mailbox $mailbox): MailboxAccounts => new MailboxAccounts(
            $mailbox->address,
            $mailbox->address->equals($user),
            array_map(fn(string $address): ConnectedAccount => $this->account(EmailAddress::fromString($address)), $mailbox->sendAs),
        ), $this->mailboxes->usableBy($user));
    }

    private function account(EmailAddress $external): ConnectedAccount
    {
        $label = Label::forAddress($external);

        return new ConnectedAccount($external, $label, ConnectionKind::fromSecret($this->bao->read($this->path($label))));
    }

    /** The target mailbox, refusing one the user may not use or an address another mailbox owns. */
    private function claim(EmailAddress $user, EmailAddress $target, EmailAddress $external): Mailbox
    {
        $mailbox = $this->usable($user, $target);
        if ($external->equals($mailbox->address)) {
            throw new EnrollmentException('That is the mailbox address itself.');
        }
        $current = $this->mailboxes->ownerOf($external);
        if ($current !== null && !$current->address->equals($mailbox->address)) {
            throw new EnrollmentException('That account is already connected to another mailbox.');
        }

        return $mailbox;
    }

    private function usable(EmailAddress $user, EmailAddress $target): Mailbox
    {
        $mailbox = $this->mailboxes->find($target);
        if ($mailbox === null || !$mailbox->usableBy($user)) {
            throw new EnrollmentException('You cannot manage accounts for that mailbox.');
        }

        return $mailbox;
    }

    private function path(Label $label): string
    {
        return "{$this->servicesPath}/{$label->secretName()}";
    }
}
