<?php

// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace OCA\OtherAccounts\Controller;

use OCA\OtherAccounts\AppInfo\Application;
use OCA\OtherAccounts\BackgroundJob\ConvergeJob;
use OCA\OtherAccounts\Converge\ConvergeOutcome;
use OCA\OtherAccounts\Converge\ConvergeScope;
use OCA\OtherAccounts\Converge\ConvergeStatusStore;
use OCA\OtherAccounts\Enrollment\ConnectedAccount;
use OCA\OtherAccounts\Enrollment\EmailAddress;
use OCA\OtherAccounts\Enrollment\Enrollment;
use OCA\OtherAccounts\Enrollment\EnrollmentException;
use OCA\OtherAccounts\Enrollment\ImapCredential;
use OCA\OtherAccounts\Mail\MailProtocolException;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\JSONResponse;
use OCP\BackgroundJob\IJobList;
use OCP\IRequest;
use Psr\Log\LoggerInterface;

/** @phpstan-type Json JSONResponse<Http::STATUS_*, array<string, mixed>, array<string, mixed>> */
final class AccountsController extends Controller
{
    public function __construct(
        IRequest $request,
        private readonly Enrollment $enrollment,
        private readonly CurrentOwner $owner,
        private readonly ConvergeStatusStore $statuses,
        private readonly IJobList $jobs,
        private readonly LoggerInterface $logger,
    ) {
        parent::__construct(Application::APP_ID, $request);
    }

    /** @return Json */
    #[NoAdminRequired]
    public function index(): JSONResponse
    {
        return $this->guard(function (): JSONResponse {
            $owner = $this->owner->address();
            $accounts = array_map(fn(ConnectedAccount $a): array => [
                ...$a->jsonSerialize(),
                'status' => $this->statuses->latest(ConvergeScope::connect($a->label, $owner)),
            ], $this->enrollment->connected($owner));

            return new JSONResponse(['mailbox' => $owner->value, 'accounts' => $accounts]);
        });
    }

    /** @return Json */
    #[NoAdminRequired]
    public function connectImap(string $email, string $imapHost, int $imapPort, string $smtpHost, int $smtpPort, string $username, string $password): JSONResponse
    {
        return $this->guard(function () use ($email, $imapHost, $imapPort, $smtpHost, $smtpPort, $username, $password): JSONResponse {
            $credential = new ImapCredential($imapHost, $imapPort, $smtpHost, $smtpPort, $username, $password);
            $scope = $this->enrollment->connectImap($this->owner->address(), EmailAddress::fromString($email), $credential);

            return $this->queue($scope);
        });
    }

    /** @return Json */
    #[NoAdminRequired]
    public function disconnect(string $email): JSONResponse
    {
        return $this->guard(fn(): JSONResponse => $this->queue($this->enrollment->disconnect($this->owner->address(), EmailAddress::fromString($email))));
    }

    /** @return Json */
    private function queue(ConvergeScope $scope): JSONResponse
    {
        $this->statuses->record($scope, ConvergeOutcome::queued());
        $this->jobs->add(ConvergeJob::class, ConvergeJob::arguments($scope));

        return new JSONResponse(['label' => $scope->label->value, 'status' => ConvergeOutcome::queued()]);
    }

    /**
     * @param \Closure(): Json $action
     * @return Json
     */
    private function guard(\Closure $action): JSONResponse
    {
        try {
            return $action();
        } catch (EnrollmentException | MailProtocolException | \InvalidArgumentException $e) {
            return new JSONResponse(['error' => $e->getMessage()], Http::STATUS_BAD_REQUEST);
        } catch (\RuntimeException $e) {
            $this->logger->error('Other Accounts request failed', ['exception' => $e]);

            return new JSONResponse(['error' => 'Something went wrong; an administrator can find details in the server log.'], Http::STATUS_BAD_GATEWAY);
        }
    }
}
