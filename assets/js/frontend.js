/**
 * Ashna Frontend Controller
 */
(function ($) {
	'use strict';

	function toEnglishDigits(str) {
		if (!str) {
			return '';
		}
		var persian = [/۰/g, /۱/g, /۲/g, /۳/g, /۴/g, /۵/g, /۶/g, /۷/g, /۸/g, /۹/g];
		var arabic = [/٠/g, /١/g, /٢/g, /٣/g, /٤/g, /٥/g, /٦/g, /٧/g, /٨/g, /٩/g];
		var result = String(str);
		for (var i = 0; i < 10; i++) {
			result = result.replace(persian[i], i).replace(arabic[i], i);
		}
		return result;
	}

	/**
	 * +98 (Iran flag) mode: the national number is entered without its leading 0.
	 * Strips a pasted/typed +98, 0098 or 98 country prefix and any leading zeros.
	 * Emails (anything containing @ or letters) are returned untouched.
	 */
	function normalizeIrFlagInput(str) {
		var s = String(str || '').trim();
		if (s.indexOf('@') !== -1 || !/^[0-9+\s\-]*$/.test(s)) {
			return s;
		}
		var d = s.replace(/[\s\-]/g, '');
		if (/^(\+98|0098|98)\d{10}$/.test(d)) {
			d = d.replace(/^(\+98|0098|98)/, '');
		}
		return d.replace(/\D/g, '').replace(/^0+/, '');
	}

	function formatCountdown(seconds) {
		var s = Math.max(0, parseInt(seconds, 10) || 0);
		var mins = Math.floor(s / 60);
		var secs = s % 60;
		return (mins < 10 ? '0' + mins : mins) + ':' + (secs < 10 ? '0' + secs : secs);
	}

	function base64urlToUint8Array(base64url) {
		var padding = '='.repeat((4 - (base64url.length % 4)) % 4);
		var base64 = (base64url + padding).replace(/-/g, '+').replace(/_/g, '/');
		var rawData = window.atob(base64);
		var outputArray = new Uint8Array(rawData.length);
		for (var i = 0; i < rawData.length; ++i) {
			outputArray[i] = rawData.charCodeAt(i);
		}
		return outputArray;
	}

	function bufferToBase64url(buffer) {
		var bytes = new Uint8Array(buffer);
		var str = '';
		for (var i = 0; i < bytes.byteLength; i++) {
			str += String.fromCharCode(bytes[i]);
		}
		return window.btoa(str).replace(/\+/g, '-').replace(/\//g, '_').replace(/=+$/, '');
	}

	function isWebAuthnSupported() {
		return typeof window !== 'undefined' && typeof window.PublicKeyCredential !== 'undefined' && navigator.credentials;
	}

	/* ------------------------------------------------------------------
	 * Session-aware AJAX. Every request carries the newest nonce. A stale
	 * nonce (page cache, sign-in/out in another tab, expired session) is
	 * answered by the server with a fresh one and the request is retried
	 * once, so a re-login never ends in "-1" / 403. After a sign-in the
	 * server returns the nonce of the *new* session (e.g. for registering
	 * a passkey right away).
	 * ---------------------------------------------------------------- */
	var P = window.ashnaOtpParams || {};
	var sessionRefresh = null;
	var renderedLoggedIn = P.loggedIn === true || P.loggedIn === 1 || P.loggedIn === '1';

	function isTruthy(v) {
		return v === true || v === 1 || v === '1';
	}

	function applySessionState(data) {
		if (!data) {
			return;
		}
		if (data.nonce) {
			P.nonce = data.nonce;
		}
		if (typeof data.logged_in !== 'undefined') {
			P.loggedIn = isTruthy(data.logged_in) ? '1' : '';
		}
		if (data.captcha && data.captcha.token) {
			// Each form gets its own single-use question.
			var list = data.captchas && data.captchas.length ? data.captchas : [data.captcha];
			$('.ashna-captcha-box.is-math').each(function (i) {
				var $box = $(this);
				var c = list[i] || null;
				if (c && c.token) {
					$box.find('.ashna-captcha-question').text(c.question);
					$box.find('.ashna-captcha-token').val(c.token);
					$box.find('.ashna-captcha-answer').val('');
					$box.addClass('is-active').show();
				}
			});
		}
	}

	function refreshSession() {
		if (!sessionRefresh) {
			sessionRefresh = $.ajax({
				url: P.ajaxUrl,
				type: 'POST',
				dataType: 'json',
				cache: false,
				data: { action: 'ashna_refresh_session' }
			})
				.done(function (res) {
					if (res && res.success) {
						applySessionState(res.data);
					}
				})
				.always(function () {
					sessionRefresh = null;
				});
		}
		return sessionRefresh;
	}

	function isStaleNonce(xhr) {
		if (!xhr || xhr.status !== 403) {
			return false;
		}
		var json = xhr.responseJSON;
		if (json && json.data && json.data.code === 'ashna_nonce_expired') {
			return true;
		}
		var text = String(xhr.responseText || '').trim();
		return text === '-1' || text === '0';
	}

	function ashnaRequest(data, options) {
		var deferred = $.Deferred();
		var send = function (isRetry) {
			$.ajax(
				$.extend({ url: P.ajaxUrl, type: 'POST', dataType: 'json' }, options || {}, {
					data: $.extend({}, data, { nonce: P.nonce })
				})
			)
				.done(function (res) {
					if (res && res.success && res.data) {
						applySessionState({ nonce: res.data.nonce, logged_in: res.data.logged_in });
					}
					deferred.resolve(res);
				})
				.fail(function (xhr, textStatus, errorThrown) {
					if (!isRetry && isStaleNonce(xhr)) {
						var json = xhr.responseJSON;
						if (json && json.data && json.data.nonce) {
							applySessionState(json.data);
							send(true);
						} else {
							refreshSession().always(function () {
								send(true);
							});
						}
						return;
					}
					deferred.reject(xhr, textStatus, errorThrown);
				});
		};
		send(false);
		return deferred.promise();
	}

	function initOtpWrapper($wrapper) {
		if ($wrapper.data('ashna-initialized')) {
			return;
		}
		$wrapper.data('ashna-initialized', true);

		var $stepRequest = $wrapper.find('.ashna-step-request');
		var $stepVerify = $wrapper.find('.ashna-step-verify');
		var $stepPassword = $wrapper.find('.ashna-step-password');
		var $identifierInput = $wrapper.find('.ashna-identifier-input');
		var $digitBoxes = $wrapper.find('.ashna-digit-box');
		var $hiddenCode = $wrapper.find('.ashna-otp-hidden-code');
		var $alert = $wrapper.find('.ashna-otp-alert');
		var $recipientDisplay = $wrapper.find('.ashna-recipient-display');
		var $timerWrap = $wrapper.find('.ashna-timer-wrap');
		var $timerCountdown = $wrapper.find('.ashna-timer-countdown');
		var $resendBtn = $wrapper.find('.ashna-resend-btn');
		var $changeBtn = $wrapper.find('.ashna-change-identifier');
		var $newUserBox = $wrapper.find('.ashna-new-user-fields');
		var $regNameGroup = $wrapper.find('.ashna-reg-name-group');
		var $regEmailGroup = $wrapper.find('.ashna-reg-email-group');

		var otpLength = parseInt($wrapper.attr('data-otp-length'), 10) || 5;
		var redirectTo = $wrapper.attr('data-redirect') || '';
		var currentIdentifier = '';
		var resendToken = '';
		var $mathBox = $stepRequest.find('.ashna-captcha-box.is-math');

		// Single-use math captcha: every server answer may carry the next question. During an
		// attack the server can also ask for a captcha on a form that normally has none.
		function applyCaptcha(captcha, reveal) {
			if (!captcha || !captcha.token || !$mathBox.length) {
				return;
			}
			$mathBox.find('.ashna-captcha-question').text(captcha.question);
			$mathBox.find('.ashna-captcha-token').val(captcha.token);
			$mathBox.find('.ashna-captcha-answer').val('');
			if (reveal && !$mathBox.hasClass('is-active')) {
				$mathBox.addClass('is-active').slideDown(160);
			}
		}
		var hasRequiredExtraFields = false;
		var timerInterval = null;

		function showAlert(message, type) {
			$alert
				.removeClass('is-error is-success is-info')
				.addClass('is-' + (type || 'info'))
				.html(message)
				.slideDown(180);
		}

		function hideAlert() {
			$alert.slideUp(150);
		}

		function setLoading($btn, isLoading) {
			$btn.prop('disabled', isLoading);
			if (isLoading) {
				$btn.find('.ashna-spinner').show();
			} else {
				$btn.find('.ashna-spinner').hide();
			}
		}

		function startCountdown(seconds) {
			if (timerInterval) {
				clearInterval(timerInterval);
			}
			var totalSecs = Math.max(1, parseInt(seconds, 10) || 60);
			var remaining = totalSecs;
			var $progressFill = $timerWrap.find('.ashna-timer-progress-fill');
			var $ringFg = $timerWrap.find('.ashna-ring-fg');
			var circumference = 56.55;

			$resendBtn.hide();
			$timerWrap.show();
			$timerCountdown.text(formatCountdown(remaining));
			if ($progressFill.length) {
				$progressFill.css('width', '100%');
			}
			if ($ringFg.length) {
				$ringFg.css('stroke-dashoffset', '0');
			}

			timerInterval = setInterval(function () {
				remaining--;
				if (remaining <= 0) {
					clearInterval(timerInterval);
					$timerWrap.hide();
					$resendBtn.fadeIn(150);
				} else {
					$timerCountdown.text(formatCountdown(remaining));
					var ratio = Math.max(0, Math.min(1, remaining / totalSecs));
					if ($progressFill.length) {
						$progressFill.css('width', Math.round(ratio * 100) + '%');
					}
					if ($ringFg.length) {
						$ringFg.css('stroke-dashoffset', String((1 - ratio) * circumference));
					}
				}
			}, 1000);
		}

		function syncDigitsToHidden() {
			var code = '';
			$digitBoxes.each(function () {
				var v = toEnglishDigits($(this).val());
				$(this).toggleClass('is-filled', v.length > 0);
				code += v;
			});
			$hiddenCode.val(code);
			return code;
		}

		function clearDigits() {
			$digitBoxes.val('').removeClass('is-filled');
			$hiddenCode.val('');
		}

		$identifierInput.on('input', function () {
			var converted = toEnglishDigits($(this).val());
			if ($wrapper.hasClass('ashna-addon-ir_flag')) {
				converted = normalizeIrFlagInput(converted);
			}
			if (converted !== $(this).val()) {
				$(this).val(converted);
			}
		});

		$digitBoxes.on('input', function () {
			var $this = $(this);
			var val = toEnglishDigits($this.val()).replace(/[^0-9]/g, '');
			$this.val(val.slice(-1));

			var fullCode = syncDigitsToHidden();
			var idx = parseInt($this.attr('data-index'), 10);

			if (val && idx < otpLength - 1) {
				$digitBoxes.eq(idx + 1).trigger('focus').trigger('select');
			}

			// Auto-submit when all digits entered (unless disabled or required new user name/email is still empty)
			if (fullCode.length === otpLength && !hasRequiredExtraFields && $wrapper.attr('data-auto-submit') !== '0') {
				$stepVerify.trigger('submit');
			}
		});

		$digitBoxes.on('keydown', function (e) {
			var $this = $(this);
			var idx = parseInt($this.attr('data-index'), 10);

			if (e.key === 'Backspace' && !$this.val() && idx > 0) {
				$digitBoxes.eq(idx - 1).val('').trigger('focus');
				syncDigitsToHidden();
			} else if (e.key === 'ArrowLeft' && idx > 0) {
				$digitBoxes.eq(idx - 1).trigger('focus');
			} else if (e.key === 'ArrowRight' && idx < otpLength - 1) {
				$digitBoxes.eq(idx + 1).trigger('focus');
			}
		});

		$digitBoxes.on('paste', function (e) {
			var clipboardData = (e.originalEvent || e).clipboardData;
			if (!clipboardData) {
				return;
			}
			var pasted = toEnglishDigits(clipboardData.getData('text')).replace(/[^0-9]/g, '');
			if (pasted.length > 1) {
				e.preventDefault();
				for (var i = 0; i < otpLength; i++) {
					$digitBoxes.eq(i).val(pasted.charAt(i) || '');
				}
				var code = syncDigitsToHidden();
				if (code.length === otpLength && !hasRequiredExtraFields) {
					$stepVerify.trigger('submit');
				}
			}
		});

		function listenForWebOTP() {
			if ('OTPCredential' in window && navigator.credentials) {
				var ac = new AbortController();
				navigator.credentials
					.get({
						otp: { transport: ['sms'] },
						signal: ac.signal
					})
					.then(function (otp) {
						if (otp && otp.code) {
							var clean = toEnglishDigits(otp.code).replace(/[^0-9]/g, '');
							for (var i = 0; i < otpLength; i++) {
								$digitBoxes.eq(i).val(clean.charAt(i) || '');
							}
							if (syncDigitsToHidden().length === otpLength && !hasRequiredExtraFields) {
								$stepVerify.trigger('submit');
							}
						}
					})
					.catch(function () {});
			}
		}

		// Step 1: Request OTP
		function requestOtp(identifier) {
			var $submitBtn = $stepRequest.find('.ashna-submit-request');
			hideAlert();
			setLoading($submitBtn, true);

			var payload = {
				action: 'ashna_request_otp',
				identifier: identifier,
				ashna_website: $stepRequest.find('.ashna-hp-input').val() || ''
			};
			if (resendToken && identifier === currentIdentifier) {
				payload.resend_token = resendToken;
			}

			var $arcaptchaTok = $stepRequest.find('[name="arcaptcha-token"]');
			var $turnstileTok = $stepRequest.find('[name="cf-turnstile-response"]');

			if ($mathBox.hasClass('is-active')) {
				payload.captcha_answer = toEnglishDigits($mathBox.find('.ashna-captcha-answer').val());
				payload.captcha_token = $mathBox.find('.ashna-captcha-token').val();
			} else if ($arcaptchaTok.length) {
				payload.captcha_token = $arcaptchaTok.val();
			} else if ($turnstileTok.length) {
				payload.captcha_token = $turnstileTok.val();
			}

			if (
				typeof ashnaOtpParams !== 'undefined' &&
				ashnaOtpParams.captchaType === 'recaptcha_v3' &&
				ashnaOtpParams.captchaSiteKey &&
				typeof window.grecaptcha !== 'undefined' &&
				typeof window.grecaptcha.execute === 'function'
			) {
				window.grecaptcha.ready(function () {
					window.grecaptcha
						.execute(ashnaOtpParams.captchaSiteKey, { action: 'ashna_otp_request' })
						.then(function (token) {
							payload.captcha_token = token;
							dispatchOtpRequest(payload, $submitBtn);
						})
						.catch(function () {
							dispatchOtpRequest(payload, $submitBtn);
						});
				});
				return;
			}

			dispatchOtpRequest(payload, $submitBtn);
		}

		function dispatchOtpRequest(payload, $submitBtn) {
			// Progressive status: the visitor sees what is happening instead of a frozen
			// button, including when the server is switching to a backup gateway.
			var i18n = ashnaOtpParams.i18n || {};
			var hintTimers = [];
			if (i18n.slowSending) {
				hintTimers.push(setTimeout(function () {
					showAlert(i18n.slowSending, 'info');
				}, parseInt(ashnaOtpParams.slowHintMs, 10) || 2500));
			}
			if (ashnaOtpParams.hasFallback && i18n.failoverSending) {
				hintTimers.push(setTimeout(function () {
					showAlert(i18n.failoverSending, 'info');
				}, parseInt(ashnaOtpParams.failoverHintMs, 10) || 5500));
			}

			ashnaRequest(payload, { timeout: parseInt(ashnaOtpParams.sendTimeoutMs, 10) || 30000 })
				.done(function (res) {
					if (res && res.data && res.data.new_captcha) {
						applyCaptcha(res.data.new_captcha, !res.success);
					}
					if (res && res.success) {
						currentIdentifier = res.data.identifier;
						resendToken = res.data.resend_token || '';
						$recipientDisplay.text(res.data.masked || currentIdentifier);
						$stepRequest.hide();
						$stepPassword.hide();

						// Configure extra registration fields if user is new
						hasRequiredExtraFields = false;
						$regNameGroup.hide();
						$regEmailGroup.hide();
						$newUserBox.hide();

						if (res.data.is_new_user) {
							var showBox = false;
							if (res.data.require_name && res.data.require_name !== 'disabled') {
								showBox = true;
								$regNameGroup.show();
								$regNameGroup.find('.ashna-req-badge').text(res.data.require_name === 'required' ? '(الزامی)' : '(اختیاری)');
								if (res.data.require_name === 'required') {
									hasRequiredExtraFields = true;
								}
							}
							if (res.data.require_email && res.data.require_email !== 'disabled') {
								showBox = true;
								$regEmailGroup.show();
								$regEmailGroup.find('.ashna-req-badge').text(res.data.require_email === 'required' ? '(الزامی)' : '(اختیاری)');
								if (res.data.require_email === 'required') {
									hasRequiredExtraFields = true;
								}
							}
							if (showBox) {
								$newUserBox.show();
							}
						}

						$stepVerify.fadeIn(200);
						$wrapper.find('.ashna-step-badge').text('گام ۲ از ۲ • تایید کد یکبارمصرف');
						clearDigits();

						var msg = res.data.message;
						if (res.data.debug_code) {
							msg += '<br><strong>کد آزمایشی (Sandbox): <code dir="ltr">' + res.data.debug_code + '</code></strong>';
						}
						showAlert(msg, 'success');
						startCountdown(res.data.cooldown || 60);
						setTimeout(function () {
							if (hasRequiredExtraFields && $regNameGroup.is(':visible')) {
								$regNameGroup.find('input').trigger('focus');
							} else {
								$digitBoxes.eq(0).trigger('focus');
							}
						}, 220);
						listenForWebOTP();
					} else {
						// A captcha is now needed while the visitor is on the code step (e.g. a resend
						// from a new network): bring the first step back with the question.
						if (res && res.data && res.data.new_captcha && !$stepRequest.is(':visible')) {
							$stepVerify.hide();
							$stepPassword.hide();
							$identifierInput.val(currentIdentifier);
							$stepRequest.fadeIn(180);
						}
						if (window.arcaptcha && typeof window.arcaptcha.reset === 'function') {
							try {
								window.arcaptcha.reset();
							} catch (e) {}
						}
						var errMsg = res && res.data && res.data.message ? res.data.message : ashnaOtpParams.i18n.networkError;
						showAlert(errMsg, 'error');
					}
				})
				.fail(function (xhr, textStatus) {
					showAlert(textStatus === 'timeout' && i18n.sendTimeout ? i18n.sendTimeout : i18n.networkError, 'error');
				})
				.always(function () {
					for (var t = 0; t < hintTimers.length; t++) {
						clearTimeout(hintTimers[t]);
					}
					setLoading($submitBtn, false);
				});
		}

		$stepRequest.on('submit', function (e) {
			e.preventDefault();
			var val = toEnglishDigits($identifierInput.val()).trim();
			if ($wrapper.hasClass('ashna-addon-ir_flag')) {
				val = normalizeIrFlagInput(val);
			}
			if (!val) {
				showAlert('لطفاً شماره موبایل یا ایمیل خود را وارد کنید.', 'error');
				$identifierInput.trigger('focus');
				return;
			}
			requestOtp(val);
		});

		$changeBtn.on('click', function () {
			if (timerInterval) {
				clearInterval(timerInterval);
			}
			hideAlert();
			$stepVerify.hide();
			$stepPassword.hide();
			$wrapper.find('.ashna-step-badge').text('گام ۱ از ۲ • احراز هویت سریع');
			$stepRequest.fadeIn(180);
			$identifierInput.trigger('focus').trigger('select');
		});

		$resendBtn.on('click', function () {
			if (currentIdentifier) {
				requestOtp(currentIdentifier);
			}
		});

		// Step 2: Verify OTP
		$stepVerify.on('submit', function (e) {
			e.preventDefault();
			var code = syncDigitsToHidden();
			if (code.length < otpLength) {
				showAlert('لطفاً کد تایید را به صورت کامل وارد کنید.', 'error');
				return;
			}

			var $verifyBtn = $stepVerify.find('.ashna-submit-verify');
			hideAlert();
			setLoading($verifyBtn, true);

			ashnaRequest({
				action: 'ashna_verify_otp',
				identifier: currentIdentifier,
				code: code,
				full_name: $stepVerify.find('.ashna-reg-fullname').val() || '',
				user_email: $stepVerify.find('.ashna-reg-email').val() || '',
				redirect_to: redirectTo
			})
				.done(function (res) {
					if (res && res.success) {
						showAlert(res.data.message, 'success');
						var finalUrl = res.data.redirect_to || window.location.href;
						var $enrollStep = $wrapper.find('.ashna-step-passkey-enroll');
						var shouldPromptPasskey =
							$wrapper.attr('data-passkey-prompt') === '1' &&
							isWebAuthnSupported() &&
							$enrollStep.length > 0 &&
							window.localStorage.getItem('ashna_pk_enrolled') !== '1';

						if (shouldPromptPasskey) {
							$stepVerify.hide();
							$enrollStep.attr('data-final-url', finalUrl).fadeIn(200);
							setLoading($verifyBtn, false);
						} else {
							setTimeout(function () {
								if (res.data.redirect_to) {
									window.location.href = res.data.redirect_to;
								} else {
									window.location.reload();
								}
							}, 600);
						}
					} else {
						var errMsg = res && res.data && res.data.message ? res.data.message : ashnaOtpParams.i18n.networkError;
						showAlert(errMsg, 'error');
						setLoading($verifyBtn, false);
						if ($wrapper.attr('data-error-shake') !== '0') {
							var $digitsWrap = $stepVerify.find('.ashna-otp-digits');
							$digitsWrap.addClass('ashna-shake');
							setTimeout(function () {
								$digitsWrap.removeClass('ashna-shake');
							}, 480);
						}
					}
				})
				.fail(function () {
					showAlert(ashnaOtpParams.i18n.networkError, 'error');
					setLoading($verifyBtn, false);
				});
		});

		// Password Fallback Toggle & Submit
		$wrapper.find('.ashna-switch-to-password').on('click', function () {
			hideAlert();
			$stepRequest.hide();
			$stepVerify.hide();
			var currentVal = $identifierInput.val();
			if (currentVal) {
				$stepPassword.find('.ashna-pw-identifier').val(currentVal);
			}
			$stepPassword.fadeIn(180);
		});

		$wrapper.find('.ashna-switch-to-otp').on('click', function () {
			hideAlert();
			$stepPassword.hide();
			$stepRequest.fadeIn(180);
		});

		$stepPassword.on('submit', function (e) {
			e.preventDefault();
			var idVal = toEnglishDigits($stepPassword.find('.ashna-pw-identifier').val()).trim();
			var pwVal = $stepPassword.find('.ashna-pw-input').val();
			if (!idVal || !pwVal) {
				showAlert('لطفاً شناسه کاربری و رمز عبور خود را وارد کنید.', 'error');
				return;
			}

			var $pwBtn = $stepPassword.find('.ashna-submit-password');
			hideAlert();
			setLoading($pwBtn, true);

			ashnaRequest({
				action: 'ashna_password_login',
				identifier: idVal,
				password: pwVal,
				redirect_to: redirectTo
			})
				.done(function (res) {
					if (res && res.success) {
						showAlert(res.data.message, 'success');
						setTimeout(function () {
							if (res.data.redirect_to) {
								window.location.href = res.data.redirect_to;
							} else {
								window.location.reload();
							}
						}, 600);
					} else {
						var err = res && res.data && res.data.message ? res.data.message : ashnaOtpParams.i18n.networkError;
						showAlert(err, 'error');
						setLoading($pwBtn, false);
					}
				})
				.fail(function () {
					showAlert(ashnaOtpParams.i18n.networkError, 'error');
					setLoading($pwBtn, false);
				});
		});

		// Biometric Passkey (WebAuthn) Login & Post-OTP Enrollment
		if (isWebAuthnSupported()) {
			$wrapper.find('.ashna-passkey-login-wrap').show();
		}

		$wrapper.find('.ashna-trigger-passkey-login').on('click', function () {
			if (!isWebAuthnSupported()) {
				showAlert('مرورگر یا دستگاه شما از ورود بیومتریک (Passkey) پشتیبانی نمی‌کند.', 'error');
				return;
			}
			var $pkBtn = $(this);
			hideAlert();
			setLoading($pkBtn, true);

			ashnaRequest({
				action: 'ashna_passkey_login_options',
				identifier: toEnglishDigits($identifierInput.val()).trim()
			})
				.done(function (res) {
					if (!res || !res.success || !res.data) {
						showAlert((res && res.data && res.data.message) || 'خطا در شروع ورود بیومتریک.', 'error');
						setLoading($pkBtn, false);
						return;
					}
					var opts = res.data;
					var publicKey = {
						challenge: base64urlToUint8Array(opts.challenge),
						rpId: opts.rpId,
						timeout: opts.timeout || 60000,
						userVerification: opts.userVerification || 'preferred'
					};
					if (opts.allowCredentials && opts.allowCredentials.length) {
						publicKey.allowCredentials = opts.allowCredentials.map(function (c) {
							return {
								type: 'public-key',
								id: base64urlToUint8Array(c.id)
							};
						});
					}

					navigator.credentials
						.get({ publicKey: publicKey })
						.then(function (assertion) {
							return ashnaRequest({
								action: 'ashna_passkey_login_verify',
								credential_id: bufferToBase64url(assertion.rawId),
								client_data_json: bufferToBase64url(assertion.response.clientDataJSON),
								authenticator_data: bufferToBase64url(assertion.response.authenticatorData),
								signature: bufferToBase64url(assertion.response.signature),
								redirect_to: redirectTo
							});
						})
						.then(function (verifyRes) {
							if (verifyRes && verifyRes.success) {
								try {
									window.localStorage.setItem('ashna_pk_enrolled', '1');
								} catch (e) {}
								showAlert(verifyRes.data.message || 'ورود بیومتریک موفق!', 'success');
								setTimeout(function () {
									window.location.href = verifyRes.data.redirect_to || verifyRes.data.redirect || window.location.href;
								}, 500);
							} else {
								showAlert((verifyRes && verifyRes.data && verifyRes.data.message) || 'تایید بیومتریک ناموفق بود.', 'error');
								setLoading($pkBtn, false);
							}
						})
						.catch(function (err) {
							setLoading($pkBtn, false);
							if (err && err.name !== 'NotAllowedError') {
								showAlert('خطا در خواندن کلید بیومتریک دستگاه.', 'error');
							}
						});
				})
				.fail(function () {
					showAlert(ashnaOtpParams.i18n.networkError, 'error');
					setLoading($pkBtn, false);
				});
		});

		$wrapper.find('.ashna-skip-passkey-enroll').on('click', function () {
			var finalUrl = $wrapper.find('.ashna-step-passkey-enroll').attr('data-final-url');
			window.location.href = finalUrl || window.location.href;
		});

		$wrapper.find('.ashna-enroll-passkey-now').on('click', function () {
			var $btn = $(this);
			var finalUrl = $wrapper.find('.ashna-step-passkey-enroll').attr('data-final-url');
			setLoading($btn, true);
			enrollPasskeyDevice(
				function (msg) {
					showAlert(msg || 'ورود بیومتریک فعال شد!', 'success');
					setTimeout(function () {
						window.location.href = finalUrl || window.location.href;
					}, 700);
				},
				function (errMsg) {
					setLoading($btn, false);
					showAlert(errMsg || 'امکان ثبت کلید بیومتریک وجود نداشت.', 'error');
				}
			);
		});
	}

	function enrollPasskeyDevice(onSuccess, onError) {
		if (!isWebAuthnSupported()) {
			onError('مرورگر شما از استاندارد بیومتریک WebAuthn پشتیبانی نمی‌کند.');
			return;
		}
		ashnaRequest({
			action: 'ashna_passkey_register_options'
		})
			.done(function (res) {
				if (!res || !res.success || !res.data) {
					onError((res && res.data && res.data.message) || 'خطا در دریافت تنظیمات Passkey.');
					return;
				}
				var opts = res.data;
				var publicKey = {
					rp: opts.rp,
					user: {
						id: base64urlToUint8Array(opts.user.id),
						name: opts.user.name,
						displayName: opts.user.displayName
					},
					challenge: base64urlToUint8Array(opts.challenge),
					pubKeyCredParams: opts.pubKeyCredParams,
					timeout: opts.timeout || 60000,
					authenticatorSelection: opts.authenticatorSelection,
					attestation: opts.attestation || 'none'
				};
				if (opts.excludeCredentials && opts.excludeCredentials.length) {
					publicKey.excludeCredentials = opts.excludeCredentials.map(function (c) {
						return {
							type: 'public-key',
							id: base64urlToUint8Array(c.id)
						};
					});
				}

				navigator.credentials
					.create({ publicKey: publicKey })
					.then(function (cred) {
						var spkiB64 = '';
						var alg = -7;
						if (cred.response && typeof cred.response.getPublicKey === 'function') {
							var spkiBuf = cred.response.getPublicKey();
							if (spkiBuf) {
								spkiB64 = bufferToBase64url(spkiBuf);
							}
						}
						if (cred.response && typeof cred.response.getPublicKeyAlgorithm === 'function') {
							alg = cred.response.getPublicKeyAlgorithm();
						}
						var authDataB64 = '';
						if (cred.response && typeof cred.response.getAuthenticatorData === 'function') {
							authDataB64 = bufferToBase64url(cred.response.getAuthenticatorData());
						}

						return ashnaRequest({
							action: 'ashna_passkey_register_verify',
							credential_id: bufferToBase64url(cred.rawId),
							client_data_json: bufferToBase64url(cred.response.clientDataJSON),
							authenticator_data: authDataB64,
							public_key_spki: spkiB64,
							public_key_alg: alg
						});
					})
					.then(function (verifyRes) {
						if (verifyRes && verifyRes.success) {
							try {
								window.localStorage.setItem('ashna_pk_enrolled', '1');
							} catch (e) {}
							onSuccess(verifyRes.data.message);
						} else {
							onError((verifyRes && verifyRes.data && verifyRes.data.message) || 'خطا در ذخیره کلید بیومتریک.');
						}
					})
					.catch(function (err) {
						if (err && err.name === 'InvalidStateError') {
							onError('این دستگاه قبلاً برای حساب شما ثبت شده است.');
						} else if (err && err.name === 'NotAllowedError') {
							onError('عملیات تایید بیومتریک توسط شما لغو شد.');
						} else {
							onError('خطا در ثبت بیومتریک دستگاه.');
						}
					});
			})
			.fail(function () {
				onError(ashnaOtpParams.i18n.networkError);
			});
	}

	// Smart Automatic Dark/Light Surface & Image Luminance Contrast Detection on Frontend (v2.7.4)
	function autoDetectFormContrast($wrapper) {
		var $card = $wrapper.find('.ashna-otp-card').first();
		if (!$card.length) {
			return;
		}
		var parseRgbLum = function (rgbStr) {
			var m = String(rgbStr || '').match(/rgba?\(\s*(\d+)\s*,\s*(\d+)\s*,\s*(\d+)(?:\s*,\s*([\d.]+))?\s*\)/i);
			if (!m) {
				return null;
			}
			var r = parseInt(m[1], 10);
			var g = parseInt(m[2], 10);
			var b = parseInt(m[3], 10);
			var a = m[4] !== undefined ? parseFloat(m[4]) : 1;
			return {
				lum: (0.299 * r + 0.587 * g + 0.114 * b) / 255,
				alpha: a
			};
		};

		var applyContrast = function (isDark) {
			var targetText = isDark ? '#f8fafc' : '#111827';
			$wrapper[0].style.setProperty('--ashna-text', targetText);
			$card.css('color', targetText);
			$wrapper.toggleClass('ashna-theme-dark', isDark).toggleClass('ashna-theme-light', !isDark);
		};

		var cardBgParsed = parseRgbLum(window.getComputedStyle($card[0]).backgroundColor);
		var isGlass = $wrapper.hasClass('ashna-glassmorphism');
		var canvasImgUrl = ($wrapper.attr('data-canvas-img') || '').trim();

		if (canvasImgUrl && isGlass) {
			var img = new Image();
			img.crossOrigin = 'anonymous';
			img.onload = function () {
				try {
					var cv = document.createElement('canvas');
					cv.width = 20;
					cv.height = 20;
					var ctx = cv.getContext('2d');
					ctx.drawImage(img, 0, 0, 20, 20);
					var d = ctx.getImageData(0, 0, 20, 20).data;
					var sum = 0;
					for (var i = 0; i < d.length; i += 4) {
						sum += (0.299 * d[i] + 0.587 * d[i + 1] + 0.114 * d[i + 2]) / 255;
					}
					var imgLum = (sum / (d.length / 4)) * 0.55;
					applyContrast(imgLum < 0.48);
				} catch (e) {
					applyContrast(true);
				}
			};
			img.src = canvasImgUrl;
		} else if (cardBgParsed && cardBgParsed.alpha > 0.15) {
			applyContrast(cardBgParsed.lum < 0.48);
		}
	}

	$(function () {
		$('.ashna-otp-wrapper').each(function () {
			var $w = $(this);
			initOtpWrapper($w);
			autoDetectFormContrast($w);
		});

		// A page served from a cache can be hours old: renew the nonce (and math captcha)
		// before the visitor starts typing instead of failing on submit.
		var renderedAt = parseInt(P.renderedAt, 10) || 0;
		if (P.ajaxUrl && $('.ashna-otp-wrapper').length && renderedAt && Math.abs(Date.now() / 1000 - renderedAt) > 600) {
			refreshSession();
		}

		// Back/forward cache: the visitor may have signed in or out since this page was shown.
		window.addEventListener('pageshow', function (e) {
			if (!e.persisted || !P.ajaxUrl) {
				return;
			}
			refreshSession().done(function (res) {
				if (res && res.success && res.data && isTruthy(res.data.logged_in) !== renderedLoggedIn) {
					window.location.reload();
				}
			});
		});

		var $modal = $('#ashna-otp-modal');
		$(document).on('click', '.ashna-open-modal, a[href="#ashna-login-modal"]', function (e) {
			if ($modal.length) {
				e.preventDefault();
				$modal.fadeIn(180);
				$modal.find('.ashna-identifier-input').trigger('focus');
			}
		});

		$(document).on('click', '.ashna-modal-close', function () {
			$modal.fadeOut(150);
		});

		$modal.on('click', function (e) {
			if ($(e.target).is('.ashna-modal-overlay')) {
				$modal.fadeOut(150);
			}
		});

		$(document).on('keydown', function (e) {
			if (e.key === 'Escape' && $modal.is(':visible')) {
				$modal.fadeOut(150);
			}
		});

		$(document).on('click', '.ashna-toggle-checkout-otp', function () {
			$('.ashna-wc-checkout-form-holder').slideToggle(200);
		});

		// WooCommerce My Account / [ashna_passkey_manager] Register & Delete Buttons
		$(document).on('click', '.ashna-btn-register-passkey', function () {
			var $btn = $(this);
			var $card = $btn.closest('.ashna-passkey-account-card');
			var $msg = $card.find('.ashna-pk-msg');
			$btn.prop('disabled', true);
			enrollPasskeyDevice(
				function (successMsg) {
					$msg.css({ color: '#059669', marginTop: '12px', fontWeight: '700' }).text('✅ ' + successMsg).slideDown(150);
					setTimeout(function () {
						window.location.reload();
					}, 900);
				},
				function (errMsg) {
					$btn.prop('disabled', false);
					$msg.css({ color: '#dc2626', marginTop: '12px', fontWeight: '700' }).text('❌ ' + errMsg).slideDown(150);
				}
			);
		});

		$(document).on('click', '.ashna-pk-delete-btn', function () {
			var $btn = $(this);
			var credId = $btn.attr('data-cred-id');
			$btn.prop('disabled', true).text('...');
			ashnaRequest({
				action: 'ashna_passkey_delete',
				credential_id: credId
			}).done(function (res) {
				if (res && res.success) {
					$btn.closest('.ashna-pk-item').fadeOut(180, function () {
						$(this).remove();
					});
				} else {
					$btn.prop('disabled', false).text('حذف');
				}
			});
		});
	});
})(jQuery);
