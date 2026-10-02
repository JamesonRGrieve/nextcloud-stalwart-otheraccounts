<?php

// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace OCA\OtherAccounts\Tests\Unit\Mail;

use OCA\OtherAccounts\Mail\MailProtocolException;
use OCA\OtherAccounts\Mail\MailProver;
use OCA\OtherAccounts\Tests\Support\ScriptedChannel;
use PHPUnit\Framework\TestCase;

final class MailProverTest extends TestCase
{
    public function testImapPasswordLoginQuotesCredentials(): void
    {
        $server = new ScriptedChannel(['* OK ready', '* CAPABILITY IMAP4rev1', 'a1 OK logged in']);
        (new MailProver($server))->imapPassword('imap.shaw.ca', 993, 'jrg09', 'p"a\\ss');

        self::assertSame('a1 LOGIN "jrg09" "p\\"a\\\\ss"', $server->written[0]);
        self::assertSame(['host' => 'imap.shaw.ca', 'port' => 993, 'implicitTls' => true], $server->opened[0]);
        self::assertTrue($server->closed);
    }

    public function testImapRejectedLoginThrows(): void
    {
        $server = new ScriptedChannel(['* OK ready', 'a1 NO [AUTHENTICATIONFAILED] invalid']);

        $this->expectException(MailProtocolException::class);
        (new MailProver($server))->imapPassword('imap.shaw.ca', 993, 'u', 'p');
    }

    public function testXoauth2SendsTheSaslBlobAndAnswersAChallenge(): void
    {
        $server = new ScriptedChannel(['* OK Gimap ready', '+ eyJzdGF0dXMiOiI0MDAifQ==', 'a1 NO [AUTHENTICATIONFAILED]']);

        try {
            (new MailProver($server))->imapXoauth2('imap.gmail.com', 993, 'me@gmail.com', 'tok');
            self::fail('expected MailProtocolException');
        } catch (MailProtocolException) {
            self::assertSame('a1 AUTHENTICATE XOAUTH2 ' . base64_encode("user=me@gmail.com\x01auth=Bearer tok\x01\x01"), $server->written[0]);
            self::assertSame('', $server->written[1], 'an empty line answers the error challenge');
        }
    }

    public function testSmtpStartTlsFlowWithMultilineReplies(): void
    {
        $server = new ScriptedChannel([
            '220 smtp.example ESMTP',
            '250-smtp.example', '250-STARTTLS', '250 AUTH PLAIN LOGIN',
            '220 go ahead',
            '250-smtp.example', '250 AUTH PLAIN LOGIN',
            '235 authenticated',
        ]);
        (new MailProver($server))->smtpPassword('smtp.example', 587, 'u', 'p');

        self::assertTrue($server->tlsStarted);
        self::assertSame(['EHLO otheraccounts.local', 'STARTTLS', 'EHLO otheraccounts.local', 'AUTH PLAIN ' . base64_encode("\0u\0p"), 'QUIT'], $server->written);
        self::assertFalse($server->opened[0]['implicitTls']);
    }

    public function testSmtpImplicitTlsSkipsStartTls(): void
    {
        $server = new ScriptedChannel(['220 hi', '250 ok', '235 ok']);
        (new MailProver($server))->smtpPassword('smtp.example', 465, 'u', 'p');

        self::assertFalse($server->tlsStarted);
        self::assertTrue($server->opened[0]['implicitTls']);
    }

    public function testSmtpRejectedAuthThrows(): void
    {
        $server = new ScriptedChannel(['220 hi', '250 ok', '535 5.7.8 bad credentials']);

        $this->expectException(MailProtocolException::class);
        (new MailProver($server))->smtpPassword('smtp.example', 465, 'u', 'p');
    }
}
