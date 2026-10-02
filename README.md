<!-- SPDX-License-Identifier: AGPL-3.0-or-later -->
# Other Accounts (Nextcloud app `otheraccounts`)

Self-service enrollment of external mailboxes into a user's Stalwart mailbox. In **Personal
settings → Other Accounts**, a user connects a Gmail / Google Workspace account (Google consent,
PKCE, offline access) or any IMAP + SMTP account (login proven first). The account is then two-way
synced into "Other Accounts/<label>" in their mailbox, and they can send as it.

## How it works

The app writes the same source of truth the pipeline reads, then runs the pipeline:

1. **Prove** the credential: IMAP XOAUTH2 for Google, IMAP LOGIN + SMTP AUTH for password providers.
2. **OpenBao**: `<services_path>/external_<label>` (`email` + `refresh_token`, or the IMAP/SMTP fields).
3. **NetBox**: the address is appended to the user's mailbox `send_as_addresses` (netbox-email). An
   address another mailbox owns is refused.
4. **Converge**: a background job starts a Semaphore run of the zephyrex template, `-target`ed at that
   label's resources (mailsync account/owner files, mbsyncrc, `stalwart_relay.external`, the route and
   send-as expressions, the Nextcloud alias). It confirms only if every planned action is one of those
   targets, nothing is replaced, and nothing is destroyed except the label's own account file and
   relay when disconnecting. Any other plan is **rejected**, never left parked holding the runner, and
   the reason is shown to the user.

Labels are `<localpart>-<provider>` (`jamesonrgrieve.personal@gmail.com` → `jamesonrgrieve-personal-gmail`),
identical to `enroll_mailbox.py`, so existing enrollments are reconnected in place.

## Configuration

`config.php` → `'otheraccounts' => [...]`, written by the pipeline:

| Key | Meaning |
|---|---|
| `openbao_address`, `openbao_mount` | OpenBao API and KV-v2 mount |
| `approle_role_id`, `approle_secret_id` | the app's own AppRole (policy: read the OAuth client + tokens, write `external_*` only) |
| `services_path` | Stalwart services path holding `external_*` and `google_oauth_client` |
| `netbox_url`, `netbox_token_path` | NetBox API and the bao path of a token allowed to change netbox-email mailboxes |
| `semaphore_url`, `semaphore_token_path`, `semaphore_project`, `semaphore_template` | the runner and template |

No token or OAuth secret is stored in Nextcloud. Everything except the AppRole credential is read
from OpenBao at runtime. The Google OAuth client must list
`https://<cloud>/apps/otheraccounts/google/callback` as an authorized redirect URI.

## Development

No host PHP needed: `scripts/dev.sh install`, then `scripts/dev.sh run lint|cs:check|stan|test`.
`composer install` wires `.githooks/pre-commit`, which runs all four.
