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

	async function disconnect(address) {
		if (!window.confirm('Disconnect ' + address + '? Sync and send-as for it will stop.')) {
			return;
		}
		try {
			await call('POST', '/accounts/disconnect', { email: address });
			flash('Disconnecting ' + address + '.', false);
			await render();
		} catch (e) {
			flash(e.message, true);
		}
	}

	function accountRow(account) {
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
			reconnect.addEventListener('click', () => startGoogle(account.address));
			item.appendChild(reconnect);
		}
		const remove = el('button', 'Disconnect');
		remove.type = 'button';
		remove.setAttribute('aria-label', 'Disconnect ' + account.address);
		remove.addEventListener('click', () => disconnect(account.address));
		item.appendChild(remove);
		return item;
	}

	async function render() {
		const list = document.getElementById('otheraccounts-list');
		try {
			const data = await call('GET', '/accounts');
			list.replaceChildren(...data.accounts.map(accountRow));
			document.getElementById('otheraccounts-empty').hidden = data.accounts.length > 0;
		} catch (e) {
			flash(e.message, true);
		}
	}

	async function startGoogle(email) {
		try {
			const data = await call('POST', '/google/start', { email });
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
			startGoogle(new FormData(event.target).get('email'));
		});

		document.getElementById('otheraccounts-imap').addEventListener('submit', async (event) => {
			event.preventDefault();
			const form = new FormData(event.target);
			const email = form.get('email');
			try {
				await call('POST', '/accounts/imap', {
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
