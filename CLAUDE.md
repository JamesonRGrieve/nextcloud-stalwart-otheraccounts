# nextcloud-stalwart-otheraccounts — Agent Operating Guide

AGPL-3.0 Nextcloud 34 app (`otheraccounts`): self-service enrollment of external mailboxes into a
user's Stalwart mailbox. See README.md for the flow and configuration. Follow the workspace
`/home/jameson/Source/ai-prompts/php.md` standard.

## Rules
- `declare(strict_types=1);` + SPDX header in every PHP file; PHPStan **max** stays clean; PER-CS 2.0
  via PHP-CS-Fixer with zero diff.
- Domain logic (`lib/Enrollment`, `lib/Converge`, `lib/Service`, `lib/Mail`, `lib/Http`) has **no OCP
  dependency** and full unit tests. Test doubles exist only at true external boundaries
  (`ScriptedHttpClient`, `ScriptedChannel`, `InMemoryStatusStore`, `CountingClock`).
- The Nextcloud layer (`lib/AppInfo`, `lib/Controller`, `lib/Settings`, `lib/BackgroundJob`, `lib/Status`)
  stays thin: it wires services and translates HTTP.
- Secrets never reach the browser, logs, the Nextcloud DB or `config.php`, except the AppRole
  credential. Mark secret parameters `#[\SensitiveParameter]`.
- The converge gate is the safety boundary for unattended applies. Any change to `ConvergeScope`
  targets must mirror `svc_stalwart.tf` / `svc_nextcloud.tf` exactly, and must come with gate tests.
- The front end builds DOM with `textContent` only. Every input has a `<label>`; status uses an
  `aria-live` region.

## Commands
`scripts/dev.sh install` · `scripts/dev.sh run lint|cs:check|cs:fix|stan|test` (podman composer image).
