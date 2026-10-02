<?php

// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace OCA\OtherAccounts\Converge;

use OCA\OtherAccounts\Enrollment\EmailAddress;
use OCA\OtherAccounts\Enrollment\Label;

/**
 * Exactly the zephyrex-root resources one external mailbox drives (svc_stalwart.tf §1b/§7,
 * svc_nextcloud.tf webmail), as `-target`s for a scoped tmpl1 run, plus which of them a plan may
 * destroy (only the label's own account file and relay, and only when disconnecting).
 */
final readonly class ConvergeScope
{
    private function __construct(public Label $label, public EmailAddress $owner, public bool $disconnect) {}

    public static function connect(Label $label, EmailAddress $owner): self
    {
        return new self($label, $owner, false);
    }

    public static function disconnect(Label $label, EmailAddress $owner): self
    {
        return new self($label, $owner, true);
    }

    /** @return list<string> resource addresses */
    public function targets(): array
    {
        return [
            "host_file.stalwart_mailsync_account[\"{$this->label->value}\"]",
            "host_file.stalwart_mailsync_owner[\"{$this->owner->value}\"]",
            'host_file.stalwart_mailsync_common',
            'host_file.stalwart_mailsync_mbsyncrc',
            "stalwart_relay.external[\"{$this->label->value}\"]",
            'stalwart_mta_expression.outbound_route',
            'stalwart_mta_expression.must_match_sender',
            "nextcloud_mail_aliases.external[\"{$this->owner->value}\"]",
        ];
    }

    /** @return list<string> resource addresses a plan may destroy */
    public function destroyable(): array
    {
        return $this->disconnect ? [
            "host_file.stalwart_mailsync_account[\"{$this->label->value}\"]",
            "stalwart_relay.external[\"{$this->label->value}\"]",
        ] : [];
    }

    /** @return list<string> Semaphore task arguments */
    public function arguments(): array
    {
        return array_map(static fn(string $t): string => "-target={$t}", $this->targets());
    }
}
