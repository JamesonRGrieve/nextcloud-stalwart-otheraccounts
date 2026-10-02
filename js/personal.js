// SPDX-License-Identifier: AGPL-3.0-or-later
// Other Accounts personal settings. All DOM is built with textContent (no HTML injection).
(function () {
	'use strict';

	const base = OC.generateUrl('/apps/otheraccounts');
	const STATE_TEXT = {
		queued: 'Queued',
		running: 'Applying',
		applied: 'Active',
		refused: 'Held for an administrator',
		failed: 'Failed',
		timed_out: 'Waiting on the pipeline',
	};

	function el(tag, text, className) {
		const node = document.createElement(tag);
		if (text !== undefined) {
			node.textContent = text;
		}
		if (className) {
			node.className = className;
		}
		return node;
	}

	function flash(message, isError) {
		const node = document.getElementById('otheraccounts-flash');
		node.textContent = message;
		node.className = isError ? 'otheraccounts-error' : 'otheraccounts-ok';
	}

	async function call(method, path, body) {
		const response = await fetch(base + path, {
			method,
			headers: { 'Content-Type': 'application/json', requesttoken: OC.requestToken },
			body: body === undefined ? undefined : JSON.stringify(body),
		});
		const data = await response.json();
		if (!response.ok) {
			throw new Error(data.error || 'Request failed.');
		}
		return data;
	}

	function targetMailbox() {
		return document.getElementById('otheraccounts-mailbox').value;
	}

	async function disconnect(mailbox, address) {
		if (!window.confirm('Disconnect ' + address + ' from ' + mailbox + '? Sync and send-as for it will stop.')) {
			return;
		}
		try {
			await call('POST', '/accounts/disconnect', { mailbox, email: address });
			flash('Disconnecting ' + address + '.', false);
			await render();
		} catch (e) {
			flash(e.message, true);
		}
	}

	function accountRow(mailbox, account) {
		const item = el('li', undefined, 'otheraccounts-row');
		item.appendChild(el('span', account.address, 'otheraccounts-address'));
		const kind = account.kind === 'google' ? 'Google' : account.kind === 'imap' ? 'IMAP' : 'Needs reconnecting';
		item.appendChild(el('span', kind, 'otheraccounts-kind'));
		const status = account.status;
		item.appendChild(el('span', status ? (STATE_TEXT[status.state] || status.state) + ' — ' + status.detail : '', 'otheraccounts-status'));
		if (account.kind === 'google' || account.kind === 'missing') {
			const reconnect = el('button', 'Reconnect');
			reconnect.type = 'button';
			reconnect.setAttribute('aria-label', 'Reconnect ' + account.address);
			reconnect.addEventListener('click', () => startGoogle(mailbox, account.address));
			item.appendChild(reconnect);
		}
		const remove = el('button', 'Disconnect');
		remove.type = 'button';
		remove.setAttribute('aria-label', 'Disconnect ' + account.address + ' from ' + mailbox);
		remove.addEventListener('click', () => disconnect(mailbox, account.address));
		item.appendChild(remove);
		return item;
	}

	function mailboxSection(entry) {
		const section = el('section', undefined, 'otheraccounts-mailbox');
		section.appendChild(el('h4', entry.mailbox + (entry.own ? ' (your mailbox)' : ' (shared with you)')));
		if (entry.accounts.length === 0) {
			section.appendChild(el('p', 'No accounts linked.', 'otheraccounts-status'));
		} else {
			const list = el('ul');
			list.replaceChildren(...entry.accounts.map((a) => accountRow(entry.mailbox, a)));
			section.appendChild(list);
		}
		return section;
	}

	async function render() {
		const list = document.getElementById('otheraccounts-list');
		const picker = document.getElementById('otheraccounts-mailbox');
		try {
			const data = await call('GET', '/accounts');
			list.replaceChildren(...data.mailboxes.map(mailboxSection));
			const selected = picker.value;
			picker.replaceChildren(...data.mailboxes.map((m) => {
				const option = el('option', m.mailbox + (m.own ? ' (your mailbox)' : ' (shared)'));
				option.value = m.mailbox;
				return option;
			}));
			if (selected && data.mailboxes.some((m) => m.mailbox === selected)) {
				picker.value = selected;
			}
		} catch (e) {
			flash(e.message, true);
		}
	}

	async function startGoogle(mailbox, email) {
		try {
			const data = await call('POST', '/google/start', { mailbox, email });
			window.location.assign(data.url);
		} catch (e) {
			flash(e.message, true);
		}
	}

	document.addEventListener('DOMContentLoaded', () => {
		const params = new URLSearchParams(window.location.search);
		if (params.has('connected')) {
			flash('Connected ' + params.get('connected') + '. Sync starts once the change is applied.', false);
		} else if (params.has('error')) {
			flash(params.get('error'), true);
		}

		document.getElementById('otheraccounts-google').addEventListener('submit', (event) => {
			event.preventDefault();
			startGoogle(targetMailbox(), new FormData(event.target).get('email'));
		});

		document.getElementById('otheraccounts-imap').addEventListener('submit', async (event) => {
			event.preventDefault();
			const form = new FormData(event.target);
			const email = form.get('email');
			try {
				await call('POST', '/accounts/imap', {
					mailbox: targetMailbox(),
					email,
					username: form.get('username') || email,
					password: form.get('password'),
					imapHost: form.get('imapHost'),
					imapPort: Number(form.get('imapPort')),
					smtpHost: form.get('smtpHost'),
					smtpPort: Number(form.get('smtpPort')),
				});
				event.target.reset();
				flash('Connected ' + email + '. Sync starts once the change is applied.', false);
				await render();
			} catch (e) {
				flash(e.message, true);
			}
		});

		render();
	});
})();
