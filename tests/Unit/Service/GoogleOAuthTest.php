<?php

// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace OCA\OtherAccounts\Tests\Unit\Service;

use OCA\OtherAccounts\Enrollment\EmailAddress;
use OCA\OtherAccounts\Http\UpstreamException;
use OCA\OtherAccounts\Service\GoogleOAuth;
use OCA\OtherAccounts\Service\IncompleteConsentException;
use OCA\OtherAccounts\Service\Pkce;
use OCA\OtherAccounts\Tests\Support\ScriptedHttpClient;
use PHPUnit\Framework\TestCase;

final class GoogleOAuthTest extends TestCase
{
    private const string REDIRECT = 'https://cloud.example/apps/otheraccounts/google/callback';

    public function testPkceMatchesTheRfc7636Vector(): void
    {
        self::assertSame('E9Melhoa2OwvFrEMTJguCHaoeK1t8URWbuGJSstw-cM', Pkce::fromVerifier('dBjftJeZ4CVP-mB92K27uhbUJU1p1r_wW1gFWFOEjXk')->challenge());
        self::assertMatchesRegularExpression('/^[A-Za-z0-9_-]{64}$/', Pkce::generate()->verifier);
    }

    public function testAuthorizationUrlRequestsOfflineConsentForEveryScope(): void
    {
        $oauth = new GoogleOAuth(new ScriptedHttpClient([]), 'client-id', 'client-secret');
        $pkce = Pkce::fromVerifier('dBjftJeZ4CVP-mB92K27uhbUJU1p1r_wW1gFWFOEjXk');

        $url = $oauth->authorizationUrl(EmailAddress::fromString('me@gmail.com'), self::REDIRECT, 'state-1', $pkce);
        parse_str((string) parse_url($url, PHP_URL_QUERY), $query);

        self::assertStringStartsWith(GoogleOAuth::AUTH_URL . '?', $url);
        self::assertSame('offline', $query['access_type']);
        self::assertSame('consent', $query['prompt']);
        self::assertSame('S256', $query['code_challenge_method']);
        self::assertSame($pkce->challenge(), $query['code_challenge']);
        self::assertSame(implode(' ', GoogleOAuth::SCOPES), $query['scope']);
        self::assertSame('me@gmail.com', $query['login_hint']);
        self::assertSame(self::REDIRECT, $query['redirect_uri']);
        self::assertSame('state-1', $query['state']);
    }

    public function testExchangeReturnsTheGrantAndSendsTheVerifier(): void
    {
        $http = new ScriptedHttpClient(['POST oauth2.googleapis.com/token' => ScriptedHttpClient::json(200, [
            'access_token' => 'at', 'refresh_token' => 'rt', 'scope' => implode(' ', GoogleOAuth::SCOPES),
        ])]);
        $grant = (new GoogleOAuth($http, 'cid', 'csecret'))->exchange('the-code', self::REDIRECT, Pkce::fromVerifier('v'));

        self::assertSame('rt', $grant->refreshToken);
        self::assertSame('at', $grant->accessToken);
        parse_str((string) $http->requests[0]['body'], $sent);
        self::assertSame(['the-code', 'v', 'authorization_code'], [$sent['code'], $sent['code_verifier'], $sent['grant_type']]);
    }

    public function testExchangeRefusesAPartialConsent(): void
    {
        $http = new ScriptedHttpClient(['POST oauth2.googleapis.com/token' => ScriptedHttpClient::json(200, [
            'access_token' => 'at', 'refresh_token' => 'rt', 'scope' => GoogleOAuth::SCOPES[0],
        ])]);

        try {
            (new GoogleOAuth($http, 'cid', 'csecret'))->exchange('c', self::REDIRECT, Pkce::fromVerifier('v'));
            self::fail('expected IncompleteConsentException');
        } catch (IncompleteConsentException $e) {
            self::assertSame([GoogleOAuth::SCOPES[1], GoogleOAuth::SCOPES[2]], $e->missingScopes);
        }
    }

    public function testExchangeSurfacesARejectedCode(): void
    {
        $http = new ScriptedHttpClient(['POST oauth2.googleapis.com/token' => ScriptedHttpClient::json(400, ['error' => 'invalid_grant'])]);

        $this->expectException(UpstreamException::class);
        (new GoogleOAuth($http, 'cid', 'csecret'))->exchange('c', self::REDIRECT, Pkce::fromVerifier('v'));
    }
}
