<?php

// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace OCA\OtherAccounts\Service;

use OCA\OtherAccounts\Enrollment\EmailAddress;
use OCA\OtherAccounts\Http\HttpClient;
use OCA\OtherAccounts\Http\UpstreamException;

/**
 * The Google authorization-code flow with PKCE, requesting exactly the scopes the Stalwart
 * mailsync, the outbound XOAUTH2 relay and the calendar/contacts sync need. Offline access with
 * forced consent so Google always returns a refresh token; a partially granted consent
 * (granular consent lets the user untick scopes) is rejected.
 */
final readonly class GoogleOAuth
{
    public const string AUTH_URL = 'https://accounts.google.com/o/oauth2/v2/auth';
    public const string TOKEN_URL = 'https://oauth2.googleapis.com/token';
    public const array SCOPES = [
        'https://mail.google.com/',
        'https://www.googleapis.com/auth/calendar',
        'https://www.googleapis.com/auth/carddav',
    ];

    public function __construct(private HttpClient $http, private string $clientId, private string $clientSecret) {}

    public function authorizationUrl(EmailAddress $loginHint, string $redirectUri, string $state, Pkce $pkce): string
    {
        return self::AUTH_URL . '?' . http_build_query([
            'client_id' => $this->clientId,
            'redirect_uri' => $redirectUri,
            'response_type' => 'code',
            'scope' => implode(' ', self::SCOPES),
            'access_type' => 'offline',
            'prompt' => 'consent',
            'login_hint' => $loginHint->value,
            'state' => $state,
            'code_challenge' => $pkce->challenge(),
            'code_challenge_method' => 'S256',
        ], encoding_type: PHP_QUERY_RFC3986);
    }

    public function exchange(string $code, string $redirectUri, Pkce $pkce): GoogleGrant
    {
        $body = http_build_query([
            'code' => $code,
            'client_id' => $this->clientId,
            'client_secret' => $this->clientSecret,
            'redirect_uri' => $redirectUri,
            'grant_type' => 'authorization_code',
            'code_verifier' => $pkce->verifier,
        ], encoding_type: PHP_QUERY_RFC3986);
        $response = $this->http->request('POST', self::TOKEN_URL, ['Content-Type' => 'application/x-www-form-urlencoded'], $body);
        if (!$response->ok()) {
            throw UpstreamException::from('Google', 'token exchange', $response);
        }
        /** @var array{access_token?: string, refresh_token?: string, scope?: string} $token */
        $token = $response->json();
        $granted = explode(' ', $token['scope'] ?? '');
        $missing = array_values(array_diff(self::SCOPES, $granted));
        if ($missing !== []) {
            throw new IncompleteConsentException($missing);
        }
        if (!isset($token['refresh_token'], $token['access_token'])) {
            throw new \UnexpectedValueException('Google did not return an offline refresh token.');
        }

        return new GoogleGrant($token['access_token'], $token['refresh_token']);
    }
}
