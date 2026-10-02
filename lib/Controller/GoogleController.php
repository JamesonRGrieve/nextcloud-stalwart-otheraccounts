<?php

// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace OCA\OtherAccounts\Controller;

use OCA\OtherAccounts\AppInfo\Application;
use OCA\OtherAccounts\BackgroundJob\ConvergeJob;
use OCA\OtherAccounts\Converge\ConvergeOutcome;
use OCA\OtherAccounts\Converge\ConvergeStatusStore;
use OCA\OtherAccounts\Enrollment\EmailAddress;
use OCA\OtherAccounts\Enrollment\Enrollment;
use OCA\OtherAccounts\Enrollment\EnrollmentException;
use OCA\OtherAccounts\Mail\MailProtocolException;
use OCA\OtherAccounts\Service\GoogleOAuth;
use OCA\OtherAccounts\Service\IncompleteConsentException;
use OCA\OtherAccounts\Service\Pkce;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\Attribute\NoCSRFRequired;
use OCP\AppFramework\Http\Attribute\UseSession;
use OCP\AppFramework\Http\JSONResponse;
use OCP\AppFramework\Http\RedirectResponse;
use OCP\BackgroundJob\IJobList;
use OCP\IRequest;
use OCP\ISession;
use OCP\IURLGenerator;
use OCP\Security\ISecureRandom;
use Psr\Log\LoggerInterface;

/**
 * The browser leg of Google consent: `start` keeps a one-time state + PKCE verifier in the
 * session and returns Google's URL; Google redirects back to `callback`, which checks the state,
 * exchanges the code, enrolls the account and queues its converge.
 *
 * @phpstan-type Json JSONResponse<Http::STATUS_*, array<string, mixed>, array<string, mixed>>
 * @phpstan-type Redirect RedirectResponse<Http::STATUS_*, array<string, mixed>>
 */
final class GoogleController extends Controller
{
    private const string SESSION_KEY = 'otheraccounts.google';
    private const int STATE_LENGTH = 48;

    public function __construct(
        IRequest $request,
        private readonly GoogleOAuth $google,
        private readonly Enrollment $enrollment,
        private readonly CurrentOwner $owner,
        private readonly ConvergeStatusStore $statuses,
        private readonly IJobList $jobs,
        private readonly ISession $session,
        private readonly ISecureRandom $random,
        private readonly IURLGenerator $urls,
        private readonly LoggerInterface $logger,
    ) {
        parent::__construct(Application::APP_ID, $request);
    }

    /** @return Json */
    #[NoAdminRequired]
    #[UseSession]
    public function start(string $email): JSONResponse
    {
        try {
            $address = EmailAddress::fromString($email);
        } catch (\InvalidArgumentException $e) {
            return new JSONResponse(['error' => $e->getMessage()], Http::STATUS_BAD_REQUEST);
        }
        $state = $this->random->generate(self::STATE_LENGTH, ISecureRandom::CHAR_ALPHANUMERIC);
        $pkce = Pkce::generate();
        $this->session->set(self::SESSION_KEY, ['state' => $state, 'verifier' => $pkce->verifier, 'email' => $address->value]);

        return new JSONResponse(['url' => $this->google->authorizationUrl($address, $this->redirectUri(), $state, $pkce)]);
    }

    /** @return Redirect */
    #[NoAdminRequired]
    #[NoCSRFRequired]
    #[UseSession]
    public function callback(string $state = '', string $code = '', string $error = ''): RedirectResponse
    {
        /** @var array{state: string, verifier: string, email: string}|null $pending */
        $pending = $this->session->get(self::SESSION_KEY);
        $this->session->remove(self::SESSION_KEY);
        if ($pending === null || $state === '' || !hash_equals($pending['state'], $state)) {
            return $this->back('error', 'This sign-in link expired; please try again.');
        }
        if ($error !== '' || $code === '') {
            return $this->back('error', 'Google sign-in was cancelled.');
        }
        try {
            $grant = $this->google->exchange($code, $this->redirectUri(), Pkce::fromVerifier($pending['verifier']));
            $scope = $this->enrollment->connectGoogle($this->owner->address(), EmailAddress::fromString($pending['email']), $grant);
            $this->statuses->record($scope, ConvergeOutcome::queued());
            $this->jobs->add(ConvergeJob::class, ConvergeJob::arguments($scope));

            return $this->back('connected', $pending['email']);
        } catch (IncompleteConsentException | EnrollmentException | MailProtocolException $e) {
            return $this->back('error', $e->getMessage());
        } catch (\RuntimeException $e) {
            $this->logger->error('Other Accounts Google enrollment failed', ['exception' => $e]);

            return $this->back('error', 'Connecting the account failed; an administrator can find details in the server log.');
        }
    }

    private function redirectUri(): string
    {
        return $this->urls->linkToRouteAbsolute(Application::APP_ID . '.google.callback');
    }

    /** @return Redirect */
    private function back(string $kind, string $message): RedirectResponse
    {
        $page = $this->urls->linkToRouteAbsolute('settings.PersonalSettings.index', ['section' => Application::APP_ID]);

        return new RedirectResponse($page . '?' . http_build_query([$kind => $message]));
    }
}
