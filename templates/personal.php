<?php

// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

?>
<div id="otheraccounts" class="section">
    <h2>Other Accounts</h2>
    <p class="settings-hint">Connect an external mailbox and it is two-way synced into "Other Accounts" in your mailbox, and you can send as it.</p>
    <p id="otheraccounts-flash" role="status" aria-live="polite"></p>

    <h3>Connected accounts</h3>
    <ul id="otheraccounts-list" aria-describedby="otheraccounts-empty"></ul>
    <p id="otheraccounts-empty">No accounts connected yet.</p>

    <h3>Connect a Google account</h3>
    <form id="otheraccounts-google">
        <label for="otheraccounts-google-email">Gmail or Google Workspace address</label>
        <input id="otheraccounts-google-email" name="email" type="email" autocomplete="email" required>
        <button type="submit" class="primary">Sign in with Google</button>
    </form>

    <h3>Connect an IMAP account</h3>
    <form id="otheraccounts-imap">
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
