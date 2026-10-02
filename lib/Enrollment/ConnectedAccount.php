<?php

// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace OCA\OtherAccounts\Enrollment;

/** One external mailbox connected to the user's mailbox (no secret material). */
final readonly class ConnectedAccount implements \JsonSerializable
{
    public function __construct(public EmailAddress $address, public Label $label, public ConnectionKind $kind) {}

    /** @return array{address: string, label: string, kind: string} */
    public function jsonSerialize(): array
    {
        return ['address' => $this->address->value, 'label' => $this->label->value, 'kind' => $this->kind->value];
    }
}
