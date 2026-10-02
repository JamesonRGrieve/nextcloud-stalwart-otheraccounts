<?php

// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace OCA\OtherAccounts\Tests\Unit\Service;

use OCA\OtherAccounts\Http\UpstreamException;
use OCA\OtherAccounts\Service\OpenBao;
use OCA\OtherAccounts\Tests\Support\ScriptedHttpClient;
use PHPUnit\Framework\TestCase;

final class OpenBaoTest extends TestCase
{
    public function testLogsInOnceAndReusesTheToken(): void
    {
        $http = new ScriptedHttpClient([
            'POST /v1/auth/approle/login' => ScriptedHttpClient::json(200, ['auth' => ['client_token' => 't1']]),
            'GET /v1/secret/data/a/b' => ScriptedHttpClient::json(200, ['data' => ['data' => ['k' => 'v']]]),
            'LIST /v1/secret/metadata/a' => ScriptedHttpClient::json(200, ['data' => ['keys' => ['b', 'c']]]),
        ]);
        $bao = new OpenBao($http, 'http://bao', 'secret', 'role', 'sid');

        self::assertSame(['k' => 'v'], $bao->read('a/b'));
        self::assertSame(['b', 'c'], $bao->list('a'));
        self::assertCount(1, $http->requestsTo('POST', 'approle/login'));
        self::assertSame(['role_id' => 'role', 'secret_id' => 'sid'], json_decode((string) $http->requests[0]['body'], true));
    }

    public function testMissingSecretsReadAsNullAndEmpty(): void
    {
        $http = new ScriptedHttpClient([
            'POST /v1/auth/approle/login' => ScriptedHttpClient::json(200, ['auth' => ['client_token' => 't']]),
            'GET /v1/secret/data/' => ScriptedHttpClient::json(404, []),
            'LIST /v1/secret/metadata/' => ScriptedHttpClient::json(404, []),
            'DELETE /v1/secret/metadata/' => ScriptedHttpClient::json(404, []),
        ]);
        $bao = new OpenBao($http, 'http://bao', 'secret', 'role', 'sid');

        self::assertNull($bao->read('x'));
        self::assertSame([], $bao->list('x'));
        $bao->destroy('x');
        self::assertCount(1, $http->requestsTo('DELETE', 'metadata/x'));
    }

    public function testAForbiddenWriteThrows(): void
    {
        $http = new ScriptedHttpClient([
            'POST /v1/auth/approle/login' => ScriptedHttpClient::json(200, ['auth' => ['client_token' => 't']]),
            'POST /v1/secret/data/' => ScriptedHttpClient::json(403, ['errors' => ['permission denied']]),
        ]);

        $this->expectException(UpstreamException::class);
        (new OpenBao($http, 'http://bao', 'secret', 'role', 'sid'))->write('other/path', ['k' => 'v']);
    }
}
