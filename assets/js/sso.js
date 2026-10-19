/**
 * "Add your phone" card for Google / Telegram accounts.
 *
 * Three small steps, one endpoint each. Every endpoint re-checks the nonce, the session and the
 * rate limits server-side; this file only drives the UI.
 */
(function () {
	'use strict';

	var card = document.getElementById('ashna-sso-phone');
	if (!card || typeof window.ashnaSSO === 'undefined') {
		return;
	}

	var cfg = window.ashnaSSO;
	var bodies = card.querySelectorAll('.ashna-sso-phone-body');
	var msg = card.querySelector('.ashna-sso-phone-msg');
	var phoneInput = card.querySelector('.ashna-sso-phone-input');
	var codeInput = card.querySelector('.ashna-sso-code-input');
	var sentNote = card.querySelector('.ashna-sso-phone-sent');
	var currentPhone = '';

	function step(name) {
		bodies.forEach(function (body) {
			body.hidden = body.getAttribute('data-step') !== name;
		});
		if (name === 'phone') {
			window.setTimeout(function () { phoneInput.focus(); }, 30);
		}
		if (name === 'code') {
			window.setTimeout(function () { codeInput.focus(); }, 30);
		}
	}

	function say(text, isError) {
		msg.textContent = text || '';
		msg.className = 'ashna-sso-phone-msg' + (isError ? ' is-error' : ' is-ok');
	}

	function busy(button, on) {
		if (!button) { return; }
		button.disabled = !!on;
		button.classList.toggle('is-loading', !!on);
	}

	function post(action, data) {
		var body = new URLSearchParams();
		body.set('action', action);
		body.set('nonce', cfg.nonce);
		Object.keys(data || {}).forEach(function (key) {
			body.set(key, data[key]);
		});

		return fetch(cfg.ajaxUrl, {
			method: 'POST',
			credentials: 'same-origin',
			headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
			body: body.toString()
		}).then(function (response) {
			return response.json().catch(function () { return null; });
		});
	}

	function hide() {
		card.classList.add('is-dismissed');
		window.setTimeout(function () { card.remove(); }, 200);
	}

	card.addEventListener('click', function (event) {
		var trigger = event.target.closest('[data-ashna-sso]');
		var action = trigger ? trigger.getAttribute('data-ashna-sso') : '';

		if (event.target.closest('.ashna-sso-phone-close') || action === 'dismiss') {
			post('ashna_sso_phone_dismiss', {});
			hide();
			return;
		}

		if (action === 'start') {
			say('');
			step('phone');
			return;
		}

		if (action === 'back') {
			say('');
			step(currentPhone ? 'phone' : 'intro');
			return;
		}

		if (action === 'send') {
			var phone = (phoneInput.value || '').replace(/[۰-۹]/g, function (d) {
				return String('۰۱۲۳۴۵۶۷۸۹'.indexOf(d));
			}).replace(/\D/g, '');

			if (!phone) {
				say(cfg.i18n.wrong, true);
				return;
			}

			busy(trigger, true);
			say(cfg.i18n.sending);

			post('ashna_sso_phone_start', { phone: phone }).then(function (json) {
				busy(trigger, false);
				if (!json || !json.success) {
					say((json && json.data && json.data.message) || cfg.i18n.error, true);
					return;
				}
				currentPhone = (json.data && json.data.phone) || phone;
				if (sentNote) {
					sentNote.textContent = (json.data && json.data.message) || cfg.i18n.sent;
				}
				say('');
				step('code');
			}).catch(function () {
				busy(trigger, false);
				say(cfg.i18n.error, true);
			});
			return;
		}

		if (action === 'verify') {
			var code = (codeInput.value || '').replace(/[۰-۹]/g, function (d) {
				return String('۰۱۲۳۴۵۶۷۸۹'.indexOf(d));
			}).replace(/\D/g, '');

			if (!code) {
				say(cfg.i18n.wrong, true);
				return;
			}

			busy(trigger, true);
			say(cfg.i18n.verifying);

			post('ashna_sso_phone_verify', { phone: currentPhone, code: code }).then(function (json) {
				busy(trigger, false);
				if (!json || !json.success) {
					say((json && json.data && json.data.message) || cfg.i18n.wrong, true);
					return;
				}
				say((json.data && json.data.message) || cfg.i18n.added);
				window.setTimeout(hide, 1600);
			}).catch(function () {
				busy(trigger, false);
				say(cfg.i18n.error, true);
			});
		}
	});
}());
