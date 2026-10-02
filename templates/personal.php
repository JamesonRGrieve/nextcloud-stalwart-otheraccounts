<?php

// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

?>
<div id="otheraccounts" class="section">
    <p class="settings-hint">Connect an external mailbox and it is two-way synced into "Other Accounts" in your mailbox, and you can send as it.</p>
    <p id="otheraccounts-flash" role="status" aria-live="polite"></p>

    <h1>Connected accounts</h1>
    <div id="otheraccounts-list"></div>

    <h1 id="otheraccounts-link-heading">Link a new account</h1>
    <div class="otheraccounts-link" role="group" aria-labelledby="otheraccounts-link-heading">
    <p class="otheraccounts-target">
        <label for="otheraccounts-mailbox">Link to mailbox</label>
        <select id="otheraccounts-mailbox" name="mailbox" aria-describedby="otheraccounts-target-hint" required></select>
    </p>
    <p id="otheraccounts-target-hint" class="settings-hint">The Google or IMAP account you connect below is linked to <strong id="otheraccounts-target-name"></strong>.</p>

    <div class="otheraccounts-connections">
    <h2>Connect a Google account</h2>
    <form id="otheraccounts-google" aria-describedby="otheraccounts-target-hint">
        <label for="otheraccounts-google-email">Gmail or Google Workspace address</label>
        <input id="otheraccounts-google-email" name="email" type="email" autocomplete="email" required>
        <button type="submit" class="primary">Sign in with Google</button>
    </form>

    <h2>Connect an IMAP account</h2>
    <form id="otheraccounts-imap" aria-describedby="otheraccounts-target-hint">
        <label for="otheraccounts-imap-email">Email address</label>
        <input id="otheraccounts-imap-email" name="email" type="email" autocomplete="email" required>
        <label for="otheraccounts-imap-username">Login (if different from the address)</label>
        <input id="otheraccounts-imap-username" name="username" type="text" autocomplete="username">
        <label for="otheraccounts-imap-password">Password</label>
        <input id="otheraccounts-imap-password" name="password" type="password" autocomplete="current-password" required>
        <label for="otheraccounts-imap-host">IMAP server</label>
        <input id="otheraccounts-imap-host" name="imapHost" type="text" required>
        <label for="otheraccounts-imap-port">IMAP port (TLS)</label>
        <input id="otheraccounts-imap-port" name="imapPort" type="number" value="993" min="1" max="65535" required>
        <label for="otheraccounts-smtp-host">SMTP server</label>
        <input id="otheraccounts-smtp-host" name="smtpHost" type="text" required>
        <label for="otheraccounts-smtp-port">SMTP port</label>
        <input id="otheraccounts-smtp-port" name="smtpPort" type="number" value="587" min="1" max="65535" required>
        <button type="submit" class="primary">Connect</button>
    </form>
    </div>
    </div>
</div>
