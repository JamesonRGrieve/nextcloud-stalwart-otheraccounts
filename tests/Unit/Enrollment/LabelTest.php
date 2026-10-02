<?php

// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace OCA\OtherAccounts\Tests\Unit\Enrollment;

use OCA\OtherAccounts\Enrollment\EmailAddress;
use OCA\OtherAccounts\Enrollment\Label;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class LabelTest extends TestCase
{
    /** @return iterable<string, array{string, string}> the labels already enrolled in production */
    public static function liveLabels(): iterable
    {
        yield 'gmail' => ['jamesonrgrieve@gmail.com', 'jamesonrgrieve-gmail'];
        yield 'dotted gmail' => ['JamesonRGrieve.Personal@gmail.com', 'jamesonrgrieve-personal-gmail'];
        yield 'workspace' => ['jrg09@telus.net', 'jrg09-telus'];
        yield 'shaw' => ['smithfamilyha@shaw.ca', 'smithfamilyha-shaw'];
        yield 'plus-addressing' => ['a.b+tag@mail.example.co.uk', 'a-b-tag-mail'];
    }

    #[DataProvider('liveLabels')]
    public function testDerivesTheProductionLabelShape(string $address, string $label): void
    {
        $derived = Label::forAddress(EmailAddress::fromString($address));

        self::assertSame($label, $derived->value);
        self::assertSame("external_{$label}", $derived->secretName());
    }

    public function testRejectsAnythingThatIsNotASlug(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        Label::fromString('../escape');
    }

    public function testEmailAddressValidatesAndSplits(): void
    {
        $address = EmailAddress::fromString('  Brad@FloridaRailCon.com ');

        self::assertSame('brad@floridarailcon.com', $address->value);
        self::assertSame('brad', $address->localPart());
        self::assertSame('floridarailcon.com', $address->domain());
        self::assertTrue($address->equals(EmailAddress::fromString('brad@floridarailcon.com')));
    }

    public function testEmailAddressRejectsGarbage(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        EmailAddress::fromString('not an address');
    }
}
