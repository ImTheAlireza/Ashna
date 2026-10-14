/**
 * Ashna OTP v2.0 - Enterprise SaaS Admin Dashboard Controller
 */
(function ($) {
	'use strict';

	$(function () {
		var $shell = $('#ashna-app-shell');
		var $toast = $('#ashna-toast');
		var toastTimer = null;

		function showToast(msg, isError) {
			if (toastTimer) {
				clearTimeout(toastTimer);
			}
			var cleanMsg = String(msg || '').replace(/^[\u2700-\u27BF\uD83C-\uDBFF\uDC00-\uDFFF\s]+/, '');
			var iconSvg = isError
				? '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>'
				: '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>';
			$toast
				.toggleClass('is-error', !!isError)
				.find('.ashna-toast-icon')
				.html(iconSvg);
			$toast.find('.ashna-toast-text').text(cleanMsg);
			$toast.fadeIn(180);
			toastTimer = setTimeout(function () {
				$toast.fadeOut(220);
			}, 3000);
		}

		// 1. Dark Mode Toggle with localStorage persistence (Linear Stroke SVG Icons controlled by .is-dark)
		function applyDarkMode(isDark) {
			$shell.toggleClass('is-dark', !!isDark);
		}

		try {
			var savedDark = localStorage.getItem('ashna_admin_dark_mode') === '1';
			applyDarkMode(savedDark);
			if (window.location.search && window.location.search.indexOf('settings-updated=true') !== -1) {
				showToast('تنظیمات با موفقیت ذخیره شد!', false);
				// Drop the flag from the URL so a reload does not show the toast again.
				if (window.history && window.history.replaceState) {
					var cleanUrl = window.location.href.replace(/([?&])settings-updated=true(&|$)/, function (m, sep, end) {
						return end ? sep : '';
					}).replace(/[?&]$/, '');
					window.history.replaceState(null, '', cleanUrl);
				}
			}
		} catch (e) {}

		$('#ashna-theme-toggle').on('click', function () {
			var nextDark = !$shell.hasClass('is-dark');
			applyDarkMode(nextDark);
			try {
				localStorage.setItem('ashna_admin_dark_mode', nextDark ? '1' : '0');
			} catch (e) {}
		});

		// 2. Sidebar Navigation Tabs
		var $navItems = $('.ashna-nav-item');
		var $panels = $('.ashna-panel');

		function activateTab(tabId) {
			if (!tabId || !$('#ashna-tab-' + tabId).length) {
				return;
			}
			$navItems.removeClass('active');
			$navItems.filter('[data-tab="' + tabId + '"]').addClass('active');

			$panels.removeClass('active');
			$('#ashna-tab-' + tabId).addClass('active');

			try {
				sessionStorage.setItem('ashna_v2_active_tab', tabId);
			} catch (e) {}
		}

		$navItems.on('click', function () {
			activateTab($(this).attr('data-tab'));
		});

		$(document).on('click', '.ashna-jump-tab', function () {
			var targetTab = $(this).attr('data-target-tab');
			if (targetTab) {
				activateTab(targetTab);
				window.scrollTo({ top: 0, behavior: 'smooth' });
			}
		});

		try {
			var lastTab = sessionStorage.getItem('ashna_v2_active_tab');
			if (lastTab) {
				activateTab(lastTab);
			}
		} catch (e) {}

		// 3. Visual Radio Choice Cards
		$(document).on('change', '.ashna-choice-card input[type="radio"]', function () {
			var name = $(this).attr('name');
			$('input[type="radio"][name="' + name + '"]')
				.closest('.ashna-choice-card')
				.removeClass('selected');
			$(this).closest('.ashna-choice-card').addClass('selected');
		});

		// 4. SMS Gateway Selector Cards & Inspector Pills
		var $gwHiddenInput = $('#active_sms_gateway');
		var $gwCards = $('.ashna-gw-select-card');
		var $gwPills = $('.ashna-gw-pill');
		var $gwBoxes = $('.ashna-gateway-box');

		function showGatewayConfigBox(gwId) {
			$gwPills.removeClass('active');
			$gwPills.filter('[data-gw="' + gwId + '"]').addClass('active');
			$gwBoxes.hide();
			$gwBoxes.filter('[data-gateway="' + gwId + '"]').fadeIn(160);
		}

		if ($gwHiddenInput.length) {
			showGatewayConfigBox($gwHiddenInput.val());
		}

		function refreshFailoverStrip() {
			var $container = $('#ashna-live-route-nodes');
			if (!$container.length) {
				return;
			}
			var primaryTitle = $('.ashna-gw-select-card.selected').attr('data-gw-title') || 'درگاه اصلی';
			var svgSignal = '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="display:inline-block;vertical-align:-2px;margin-left:4px;"><path d="M5 12.55a11 11 0 0 1 14.08 0"/><path d="M8.53 16.11a6 6 0 0 1 6.95 0"/><line x1="12" y1="20" x2="12.01" y2="20"/></svg>';
			var svgShield = '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="display:inline-block;vertical-align:-2px;margin-left:4px;"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>';
			var html = '<span class="ashna-route-pill is-primary">' + svgSignal + 'اصلی: ' + primaryTitle + '</span>';

			var b1 = $('#backup_sms_gateway_1');
			var b2 = $('#backup_sms_gateway_2');
			var b3 = $('#backup_sms_gateway_3');

			if (b1.length && b1.val() && b1.val() !== 'none') {
				html += '<span class="ashna-flow-sep">&larr;</span><span class="ashna-route-pill">' + svgShield + 'پشتیبان ۱: ' + b1.find('option:selected').text().trim() + '</span>';
			}
			if (b2.length && b2.val() && b2.val() !== 'none') {
				html += '<span class="ashna-flow-sep">&larr;</span><span class="ashna-route-pill">' + svgShield + 'پشتیبان ۲: ' + b2.find('option:selected').text().trim() + '</span>';
			}
			if (b3.length && b3.val() && b3.val() !== 'none') {
				html += '<span class="ashna-flow-sep">&larr;</span><span class="ashna-route-pill">' + svgShield + 'پشتیبان ۳: ' + b3.find('option:selected').text().trim() + '</span>';
			}
			$container.html(html);
		}

		$gwCards.on('click', function () {
			var $card = $(this);
			var gwId = $card.attr('data-gw-id');
			var gwTitle = $card.attr('data-gw-title');

			$gwCards.removeClass('selected');
			$card.addClass('selected');
			$gwHiddenInput.val(gwId);
			$('#ashna-topbar-gw-name').text(gwTitle);
			showGatewayConfigBox(gwId);
			refreshFailoverStrip();
		});

		$('#backup_sms_gateway_1, #backup_sms_gateway_2, #backup_sms_gateway_3').on('change', refreshFailoverStrip);
		refreshFailoverStrip();

		$gwPills.on('click', function () {
			showGatewayConfigBox($(this).attr('data-gw'));
		});

		// 4.5. Progressive Disclosure Controls (v2.5.0 Smart Field Visibility)
		$('input[name="ashna[login_mode]"]').on('change', function () {
			var isEmailOnly = $(this).val() === 'email_only';
			$('#ashna-mobile-strategy-card').css('opacity', isEmailOnly ? '0.55' : '1');
		});

		$('#auto_register').on('change', function () {
			if ($(this).is(':checked')) {
				$('#ashna-auto-register-fields').slideDown(180);
			} else {
				$('#ashna-auto-register-fields').slideUp(180);
			}
		});

		$('#show_terms_checkbox').on('change', function () {
			if ($(this).is(':checked')) {
				$('#ashna-terms-fields-wrap').slideDown(180);
			} else {
				$('#ashna-terms-fields-wrap').slideUp(180);
			}
		});

		$('#enable_passkey').on('change', function () {
			if ($(this).is(':checked')) {
				$('#ashna-passkey-extra-wrap').slideDown(180);
			} else {
				$('#ashna-passkey-extra-wrap').slideUp(180);
			}
		});

		$('input[name="ashna[redirect_behavior]"], #redirect_behavior').on('change', function () {
			if ($(this).val() === 'custom') {
				$('#ashna-custom-redirect-wrap').slideDown(180);
			} else {
				$('#ashna-custom-redirect-wrap').slideUp(180);
			}
		});

		$('#farazsms_auth_type').on('change', function () {
			var isUserPass = $(this).val() === 'userpass';
			$('#farazsms-apikey-wrap').toggle(!isUserPass);
			$('#farazsms-userpass-wrap').toggle(isUserPass);
		});

		$('#ippanel_auth_type').on('change', function () {
			var isUserPass = $(this).val() === 'userpass';
			$('#ippanel-apikey-wrap').toggle(!isUserPass);
			$('#ippanel-userpass-wrap').toggle(isUserPass);
		});

		$('input[name="ashna[bale_mode]"]').on('change', function () {
			var mode = $(this).val();
			if (mode === 'bot') {
				$('#ashna-bale-safir-box').hide();
				$('#ashna-bale-bot-box').fadeIn(180);
			} else {
				$('#ashna-bale-bot-box').hide();
				$('#ashna-bale-safir-box').fadeIn(180);
			}
		});

		$('#captcha_type').on('change', function () {
			var ctype = $(this).val();
			var needsKey = ctype === 'arcaptcha' || ctype === 'recaptcha_v3' || ctype === 'turnstile';
			$('#ashna-captcha-keys-wrap').toggle(needsKey);
			$('#ashna-captcha-math-note').toggle(ctype === 'math');
			$('#ashna-captcha-none-note').toggle(ctype === 'none');
		});

		// Inline Quick SMS Test inside Tab 3 (Saves settings first, then dispatches test SMS)
		$('#ashna_quick_sms_test_btn').on('click', function () {
			var phone = ($('#ashna_quick_sms_test_phone').val() || '').trim();
			if (!phone) {
				showToast('لطفاً شماره موبایل مقصد را برای تست وارد کنید.', true);
				$('#ashna_quick_sms_test_phone').trigger('focus');
				return;
			}
			var $btn = $(this);
			var origHtml = $btn.html();
			$btn.prop('disabled', true).html('<span>در حال ذخیره و ارسال...</span>');

			var rawArray = $('#ashna-settings-form').serializeArray();
			var formData = [];
			for (var i = 0; i < rawArray.length; i++) {
				if (rawArray[i].name !== 'ashna_save_settings' && rawArray[i].name !== 'ashna_settings_nonce') {
					formData.push(rawArray[i]);
				}
			}
			formData.push({ name: 'action', value: 'ashna_admin_save_settings' });
			formData.push({ name: 'nonce', value: ashnaAdminParams.nonce });

			$.post(ashnaAdminParams.ajaxUrl, $.param(formData)).always(function () {
				$.post(ashnaAdminParams.ajaxUrl, {
					action: 'ashna_admin_test_gateway',
					nonce: ashnaAdminParams.nonce,
					recipient: phone,
					channel: 'sms',
					gateway_id: $('#active_sms_gateway').val() || ''
				})
					.done(function (res) {
						if (res && res.success) {
							showToast('✅ ' + ((res.data && res.data.message) || 'پیامک تست با موفقیت ارسال شد!'), false);
						} else {
							showToast('❌ ' + ((res && res.data && res.data.message) || 'خطا در ارسال پیامک تست.'), true);
						}
					})
					.fail(function () {
						showToast('❌ خطا در ارتباط با سرور هنگام ارسال تست.', true);
					})
					.always(function () {
						$btn.prop('disabled', false).html(origHtml);
					});
			});
		});

		// Helper: Convert hex color (#ffffff) + opacity (0..100) to rgba()
		function hexToRgbaJs(hex, opacityPct) {
			var c = String(hex || '#ffffff').replace('#', '').trim();
			if (c.length === 3) {
				c = c[0] + c[0] + c[1] + c[1] + c[2] + c[2];
			}
			var r = parseInt(c.substring(0, 2), 16) || 255;
			var g = parseInt(c.substring(2, 4), 16) || 255;
			var b = parseInt(c.substring(4, 6), 16) || 255;
			var a = Math.max(0.2, Math.min(1, (parseFloat(opacityPct) || 100) / 100));
			return 'rgba(' + r + ', ' + g + ', ' + b + ', ' + a + ')';
		}

		// 5. Interactive Appearance Studio & Live Preview (Categories 1–4)
		function refreshLivePreview(e) {
			if (typeof window.ashnaSyncLivePreview === 'function') {
				window.ashnaSyncLivePreview(e);
				return;
			}
			var primary = $('#primary_color').val() || '#2563eb';
			var secondary = $('#secondary_color').val() || '#4f46e5';
			var btnBgMode = $('#button_bg_mode').val() || 'solid';
			var bg = $('#card_bg_color').val() || '#ffffff';
			var text = $('#text_color').val() || '#111827';
			var radius = $('#border_radius').val() || 16;
			var digitStyle = $('#digit_box_style').val() || 'box';
			var logoUrl = ($('#logo_url').val() || '').trim();
			var title = $('#form_title').val() || 'ورود / ثبت‌نام';
			var subtitle = $('#form_subtitle').val() || '';
			var btn1 = $('#button_text').val() || 'دریافت کد تایید';
			var btn2 = $('#verify_button_text').val() || 'تایید و ورود به حساب';

			var isGlass = $('#glassmorphism').is(':checked');
			var cardOpacity = $('#card_bg_opacity').val() || 85;
			var blurPx = $('#backdrop_blur').val() || 16;
			var cardShadow = $('#card_shadow').val() || 'medium';
			var cardBorder = $('#card_border_style').val() || 'subtle';
			var cardPadding = $('#card_padding').val() || 32;
			var bgPattern = $('input[name="ashna[bg_pattern]"]:checked').val() || 'none';

			var formLayout = $('input[name="ashna[form_layout]"]:checked').val() || 'card';
			var cardPosition = $('input[name="ashna[card_position]"]:checked').val() || 'center';
			var splitBg = $('#split_bg_color').val() || '#1e3a8a';
			var splitImg = ($('#split_image_url').val() || '').trim();
			var splitBadge = $('#split_badge_text').val() || '';
			var splitTitle = $('#split_title').val() || '';
			var splitSub = $('#split_subtitle').val() || '';
			var splitFeatures = ($('#split_features').val() || '').split('\n');
			var canvasBgStyle = $('#canvas_bg_style').val() || 'mesh_light';
			var canvasBgColor = $('#canvas_bg_color').val() || '#f1f5f9';
			var canvasBgImg = ($('#canvas_bg_image').val() || '').trim();

			$('#primary_color_hex').text(primary);
			$('#secondary_color_hex').text(secondary);
			$('#card_bg_color_hex').text(bg);
			$('#text_color_hex').text(text);
			$('#split_bg_color_hex').text(splitBg);
			$('#canvas_bg_color_hex').text(canvasBgColor);
			$('#radius_val_label').text(radius + 'px');
			$('#opacity_val_label').text(cardOpacity + '%');
			$('#blur_val_label').text(blurPx + 'px');
			$('#padding_val_label').text(cardPadding + 'px');

			// Progressive disclosure for Split-Screen, Glassmorphism & Button Gradient
			var isSplit = formLayout === 'split_right' || formLayout === 'split_left';
			if (isSplit) {
				$('#ashna-split-banner-settings').slideDown(180);
			} else {
				$('#ashna-split-banner-settings').slideUp(180);
			}

			if (isGlass) {
				$('#ashna-glassmorphism-controls').slideDown(180);
			} else {
				$('#ashna-glassmorphism-controls').slideUp(180);
			}

			$('#ashna-secondary-color-wrap').css('opacity', btnBgMode === 'gradient' ? '1' : '0.65');
			$('#ashna-canvas-color-wrap').toggle(canvasBgStyle === 'solid' || canvasBgStyle === 'mesh_light');
			$('#ashna-canvas-image-wrap').toggle(canvasBgStyle === 'custom_image');

			// Update Canvas Alignment, Background & SVG Pattern in Live Preview
			var $canvas = $('#ashna-preview-canvas');
			var alignFlex = cardPosition === 'right' ? 'flex-start' : cardPosition === 'left' ? 'flex-end' : 'center';
			var canvasBgCss = '';
			if (canvasBgStyle === 'mesh_dark') {
				canvasBgCss = 'radial-gradient(circle at top right, #1e1b4b 0%, #0f172a 60%, #020617 100%)';
			} else if (canvasBgStyle === 'brand_gradient') {
				canvasBgCss = 'linear-gradient(135deg, ' + primary + '26 0%, #f8fafc 60%, ' + secondary + '1f 100%)';
			} else if (canvasBgStyle === 'custom_image' && canvasBgImg) {
				canvasBgCss = 'linear-gradient(rgba(15,23,42,0.45), rgba(15,23,42,0.45)), url(' + canvasBgImg + ') center/cover no-repeat';
			} else if (canvasBgStyle === 'solid') {
				canvasBgCss = canvasBgColor;
			} else {
				canvasBgCss = 'radial-gradient(circle at top right, #e0e7ff 0%, ' + canvasBgColor + ' 65%)';
			}

			var patternLayer = '';
			var patternSize = 'auto';
			if (bgPattern === 'dots') {
				patternLayer = 'radial-gradient(rgba(99, 102, 241, 0.22) 1.25px, transparent 1.25px), ';
				patternSize = '18px 18px, auto';
			} else if (bgPattern === 'grid') {
				patternLayer =
					'linear-gradient(to right, rgba(148, 163, 184, 0.16) 1px, transparent 1px), linear-gradient(to bottom, rgba(148, 163, 184, 0.16) 1px, transparent 1px), ';
				patternSize = '22px 22px, 22px 22px, auto';
			} else if (bgPattern === 'waves') {
				patternLayer = 'repeating-radial-gradient(circle at 0 0, transparent 0, rgba(99, 102, 241, 0.07) 12px, transparent 24px), ';
			} else if (bgPattern === 'geometric') {
				patternLayer =
					'linear-gradient(30deg, rgba(99, 102, 241, 0.08) 12%, transparent 12.5%, transparent 87%, rgba(99, 102, 241, 0.08) 87.5%), ';
				patternSize = '28px 48px, auto';
			}

			$canvas.css({
				alignItems: alignFlex,
				background: patternLayer + canvasBgCss,
				backgroundSize: patternSize
			});

			// Compute Shadow & Border CSS for Shell
			var shadowCss = '0 14px 32px -6px rgba(15, 23, 42, 0.12)';
			if (cardShadow === 'none') {
				shadowCss = 'none';
			} else if (cardShadow === 'soft') {
				shadowCss = '0 4px 16px -2px rgba(15, 23, 42, 0.06)';
			} else if (cardShadow === 'deep') {
				shadowCss = '0 26px 58px -10px rgba(15, 23, 42, 0.28), 0 10px 24px -6px rgba(15, 23, 42, 0.14)';
			} else if (cardShadow === 'glow') {
				shadowCss = '0 0 34px -2px ' + hexToRgbaJs(primary, 45) + ', 0 12px 28px -6px rgba(15, 23, 42, 0.16)';
			}

			var borderCss = '1px solid rgba(156, 163, 175, 0.25)';
			var borderTopCss = borderCss;
			if (cardBorder === 'none') {
				borderCss = 'none';
				borderTopCss = 'none';
			} else if (cardBorder === 'glow') {
				borderCss = '1.5px solid ' + hexToRgbaJs(primary, 65);
				borderTopCss = borderCss;
			} else if (cardBorder === 'top_accent') {
				borderTopCss = '4px solid ' + primary;
			}

			// Update Shell & Split Banner in Live Preview
			var $shell = $('#ashna-live-preview-shell');
			var $banner = $('#ashna-prev-split-banner');
			var $card = $('#ashna-live-preview-card');
			var $viewport = $('#ashna-preview-viewport');
			var modalStyle = $('input[name="ashna[modal_style]"]:checked').val() || 'center';
			var isModalMode = $canvas.hasClass('is-modal-mode');
			var isDrawerOrSheet = isModalMode && (modalStyle === 'drawer_left' || modalStyle === 'drawer_right' || modalStyle === 'bottom_sheet');
			var showSplitBanner = isSplit && !isDrawerOrSheet;

			$viewport.toggleClass('is-scaled-split', showSplitBanner);
			$viewport.css('alignItems', alignFlex);

			$shell.toggleClass('is-split', showSplitBanner).css({
				width: '100%',
				maxWidth: showSplitBanner ? '100%' : '340px',
				boxSizing: 'border-box',
				borderRadius: radius + 'px',
				flexDirection: formLayout === 'split_left' ? 'row-reverse' : 'row',
				boxShadow: shadowCss,
				border: borderCss,
				borderTop: borderTopCss
			});

			var effectiveCardBg = isGlass ? hexToRgbaJs(bg, cardOpacity) : bg;
			var cardDom = document.getElementById('ashna-live-preview-card');
			if (cardDom) {
				cardDom.style.setProperty('background', effectiveCardBg, 'important');
				cardDom.style.setProperty('backdrop-filter', isGlass ? 'blur(' + blurPx + 'px) saturate(160%)' : 'none', 'important');
				cardDom.style.setProperty('-webkit-backdrop-filter', isGlass ? 'blur(' + blurPx + 'px) saturate(160%)' : 'none', 'important');
				cardDom.style.setProperty('color', text, 'important');
				cardDom.style.setProperty('padding', Math.round(cardPadding * 0.85) + 'px', 'important');
				cardDom.style.setProperty('border-radius', '0', 'important');
			}

			if (showSplitBanner) {
				$banner.css({
					display: 'flex',
					backgroundColor: splitBg,
					backgroundImage: splitImg
						? 'linear-gradient(135deg, rgba(15,23,42,0.72), rgba(30,58,138,0.78)), url(' + splitImg + ')'
						: 'radial-gradient(circle at top left, rgba(255,255,255,0.16), transparent 65%)'
				});
				$('#ashna-prev-split-badge').text(splitBadge).toggle(!!splitBadge);
				$('#ashna-prev-split-title').text(splitTitle);
				$('#ashna-prev-split-subtitle').text(splitSub);

				var featHtml = '';
				for (var f = 0; f < splitFeatures.length; f++) {
					var line = $.trim(splitFeatures[f]);
					if (line) {
						featHtml +=
							'<li style="display:flex;align-items:center;gap:8px;margin:0;"><span style="display:inline-flex;width:18px;height:18px;border-radius:50%;background:rgba(16,185,129,0.28);color:#6ee7b7;align-items:center;justify-content:center;flex-shrink:0;">✓</span><span>' +
							$('<div>').text(line).html() +
							'</span></li>';
					}
				}
				$('#ashna-prev-split-features').html(featHtml);
			} else {
				$banner.hide();
			}

			var btnBackground = btnBgMode === 'gradient' ? 'linear-gradient(135deg, ' + primary + ', ' + secondary + ')' : primary;

			$('#ashna-prev-badge-icon').css('color', primary);
			$('#ashna-prev-title').text(title);
			$('#ashna-prev-subtitle').text(subtitle);
			$('#ashna-prev-btn-1').text(btn1).css({
				background: btnBackground,
				borderRadius: Math.round(radius * 0.68) + 'px'
			});
			$('#ashna-prev-btn-2').text(btn2).css({
				background: btnBackground,
				borderRadius: Math.round(radius * 0.68) + 'px'
			});

			if (logoUrl) {
				$('#ashna-prev-logo-img').attr('src', logoUrl);
				$('#ashna-prev-logo-wrap').show();
				$('#ashna-prev-badge-icon').hide();
			} else {
				$('#ashna-prev-logo-wrap').hide();
				$('#ashna-prev-badge-icon').css('display', 'inline-flex');
			}

			var $digits = $('.ashna-prev-digit');
			if (digitStyle === 'underline') {
				$digits.css({
					border: 'none',
					borderBottom: '2.5px solid ' + primary,
					borderRadius: '0',
					background: 'transparent',
					color: text
				});
			} else if (digitStyle === 'pill') {
				$digits.css({
					border: '1.5px solid #cbd5e1',
					borderRadius: '99px',
					background: '#f8fafc',
					color: '#0f172a'
				});
			} else {
				$digits.css({
					border: '1.5px solid #cbd5e1',
					borderRadius: '9px',
					background: '#f8fafc',
					color: '#0f172a'
				});
			}
		}

		$(
			'#primary_color, #secondary_color, #button_bg_mode, #card_bg_color, #text_color, #border_radius, #digit_box_style, #logo_url, #form_title, #form_subtitle, #button_text, #verify_button_text, #glassmorphism, #card_bg_opacity, #backdrop_blur, #card_shadow, #card_border_style, #card_padding, input[name="ashna[bg_pattern]"], input[name="ashna[form_layout]"], input[name="ashna[card_position]"], #split_bg_color, #split_image_url, #split_badge_text, #split_title, #split_subtitle, #split_features, #canvas_bg_style, #canvas_bg_color, #canvas_bg_image'
		).on('input change', refreshLivePreview);
		refreshLivePreview();

		$('.ashna-preset-btn').on('click', function () {
			var $btn = $(this);
			$('#primary_color').val($btn.attr('data-primary'));
			$('#card_bg_color').val($btn.attr('data-bg'));
			$('#text_color').val($btn.attr('data-text'));
			$('#border_radius').val($btn.attr('data-radius'));
			refreshLivePreview();
			$('#card_bg_color').trigger('change');
			showToast('پالت رنگی روی پیش‌نمایش اعمال شد!');
		});

		$('.ashna-prev-step-btn').on('click', function () {
			var step = $(this).attr('data-step');
			$('.ashna-prev-step-btn').removeClass('active');
			$(this).addClass('active');
			if (step === '2') {
				$('#ashna-prev-step-1').hide();
				$('#ashna-prev-step-2').fadeIn(150);
			} else {
				$('#ashna-prev-step-2').hide();
				$('#ashna-prev-step-1').fadeIn(150);
			}
		});

		// WordPress Media Uploader Helper for Logo, Split Banner Image & Canvas Background
		function bindMediaUploader(btnSelector, inputSelector, modalTitle) {
			$(btnSelector).on('click', function (e) {
				e.preventDefault();
				if (typeof wp === 'undefined' || !wp.media) {
					showToast('کتابخانه رسانه وردپرس در دسترس نیست.', true);
					return;
				}
				var frame = wp.media({
					title: modalTitle,
					button: { text: 'استفاده از این تصویر' },
					multiple: false
				});
				frame.on('select', function () {
					var attachment = frame.state().get('selection').first().toJSON();
					if (attachment && attachment.url) {
						$(inputSelector).val(attachment.url).trigger('change');
					}
				});
				frame.open();
			});
		}

		bindMediaUploader('#ashna_upload_logo_btn', '#logo_url', 'انتخاب لوگوی فرم ورود');
		bindMediaUploader('#ashna_upload_split_img_btn', '#split_image_url', 'انتخاب تصویر بنر کناری (Split-Screen)');
		bindMediaUploader('#ashna_upload_canvas_bg_btn', '#canvas_bg_image', 'انتخاب تصویر پس‌زمینه تمام‌صفحه');

		// 6. AJAX Save Settings, Unsaved Changes Indicator & Ctrl+S Shortcut
		var $settingsForm = $('#ashna-settings-form');
		var $saveBtn = $('#ashna-ajax-save-btn');
		var $unsavedDot = $('#ashna-unsaved-dot');

		function markFormDirty() {
			$saveBtn.addClass('has-unsaved');
			$unsavedDot.show();
		}

		function clearFormDirty() {
			$saveBtn.removeClass('has-unsaved');
			$unsavedDot.hide();
		}

		$settingsForm.on('input change', 'input, select, textarea', function () {
			if ($(this).attr('id') === 'ashna_test_recipient' || $(this).attr('id') === 'ashna_import_json_box') {
				return;
			}
			markFormDirty();
		});

		$('.ashna-gw-select-card, .ashna-preset-btn').on('click', function () {
			markFormDirty();
		});

		var isSavingSettings = false;

		function performAjaxSave(evt) {
			if (typeof window.ashnaSaveSettingsNow === 'function') {
				return window.ashnaSaveSettingsNow(evt);
			}
			if (isSavingSettings || !$settingsForm.length) {
				return;
			}
			isSavingSettings = true;

			var rawArray = $settingsForm.serializeArray();
			var formData = [];
			for (var i = 0; i < rawArray.length; i++) {
				if (rawArray[i].name !== 'ashna_save_settings') {
					formData.push(rawArray[i]);
				}
			}
			formData.push({ name: 'action', value: 'ashna_admin_save_settings' });
			if (typeof ashnaAdminParams !== 'undefined' && ashnaAdminParams.nonce) {
				formData.push({ name: 'nonce', value: ashnaAdminParams.nonce });
			}

			var ajaxEndpoint =
				typeof ashnaAdminParams !== 'undefined' && ashnaAdminParams.ajaxUrl
					? ashnaAdminParams.ajaxUrl
					: typeof window.ajaxurl !== 'undefined'
					? window.ajaxurl
					: '';

			$saveBtn.prop('disabled', true);
			$saveBtn.find('.ashna-save-label').text('در حال ذخیره...');

			$.ajax({
				url: ajaxEndpoint,
				type: 'POST',
				dataType: 'text',
				data: $.param(formData)
			})
				.done(function (rawRes) {
					var res = null;
					if (typeof rawRes === 'string') {
						try {
							res = JSON.parse(rawRes.trim());
						} catch (e) {
							var idx = rawRes.indexOf('{"success":');
							if (idx !== -1) {
								try {
									res = JSON.parse(rawRes.substring(idx, rawRes.lastIndexOf('}') + 1));
								} catch (e2) {}
							}
						}
					}
					if (res && res.success) {
						clearFormDirty();
						showToast((res.data && res.data.message) || 'تنظیمات با موفقیت ذخیره شد!', false);
						if (res.data && res.data.settings) {
							$('#ashna_export_json_box').val(JSON.stringify(res.data.settings));
						}
					} else {
						HTMLFormElement.prototype.submit.call($settingsForm[0]);
					}
				})
				.fail(function () {
					HTMLFormElement.prototype.submit.call($settingsForm[0]);
				})
				.always(function () {
					isSavingSettings = false;
					$saveBtn.prop('disabled', false);
					$saveBtn.find('.ashna-save-label').text('ذخیره تغییرات');
				});
		}

		$settingsForm.on('submit', function (e) {
			e.preventDefault();
			performAjaxSave(e);
		});

		$saveBtn.on('click', function (e) {
			e.preventDefault();
			performAjaxSave(e);
		});

		$(document).on('keydown', function (e) {
			if ((e.ctrlKey || e.metaKey) && e.key && e.key.toLowerCase() === 's') {
				if ($settingsForm.length) {
					e.preventDefault();
					performAjaxSave(e);
				}
			}
		});

		// 7. Copy to Clipboard Buttons
		$(document).on('click', '.ashna-copy-btn', function () {
			var text = $(this).attr('data-copy');
			if (navigator.clipboard && text) {
				navigator.clipboard.writeText(text).then(function () {
					showToast('📋 در کلیپ‌بورد کپی شد!');
				});
			}
		});

		// 8. Live Gateway Tester
		var $testBtn = $('#ashna_run_test_btn');
		var $testResult = $('#ashna_test_result');

		$testBtn.on('click', function () {
			var recipient = ($('#ashna_test_recipient').val() || '').trim();
			var channel = $('#ashna_test_channel').val();
			var gatewayId = $('#ashna_test_gateway_id').val();

			if (!recipient) {
				showToast('لطفاً شماره موبایل یا ایمیل گیرنده تست را وارد کنید.', true);
				return;
			}

			$testBtn.prop('disabled', true).text('در حال ارسال کد آزمایشی...');
			$testResult.hide();

			$.ajax({
				url: ashnaAdminParams.ajaxUrl,
				type: 'POST',
				dataType: 'json',
				data: {
					action: 'ashna_admin_test_gateway',
					nonce: ashnaAdminParams.nonce,
					recipient: recipient,
					channel: channel,
					gateway_id: gatewayId
				}
			})
				.done(function (res) {
					if (res && res.success) {
						$testResult
							.css({ background: '#f0fdf4', color: '#15803d', border: '1px solid #bbf7d0' })
							.html('<strong>✅ ' + res.data.message + '</strong>')
							.slideDown(150);
					} else {
						var err = res && res.data && res.data.message ? res.data.message : 'خطای نامشخص';
						$testResult
							.css({ background: '#fef2f2', color: '#b91c1c', border: '1px solid #fecaca' })
							.html('<strong>❌ ' + err + '</strong>')
							.slideDown(150);
					}
				})
				.fail(function () {
					showToast('خطا در برقراری ارتباط با سرور', true);
				})
				.always(function () {
					$testBtn.prop('disabled', false).html('<span class="dashicons dashicons-controls-play"></span> ارسال کد آزمایشی همین الان');
				});
		});

		// 9. Unlock Active Lockout Button
		$(document).on('click', '.ashna-unlock-btn', function () {
			var $btn = $(this);
			var target = $btn.attr('data-target');
			$btn.prop('disabled', true).text('در حال بازگشایی...');

			$.ajax({
				url: ashnaAdminParams.ajaxUrl,
				type: 'POST',
				dataType: 'json',
				data: {
					action: 'ashna_admin_unlock_target',
					nonce: ashnaAdminParams.nonce,
					target: target
				}
			}).done(function (res) {
				if (res && res.success) {
					$btn.closest('tr').fadeOut(200);
					showToast('✅ مسدودی شماره/IP برطرف شد.');
				} else {
					$btn.prop('disabled', false).text('رفع مسدودی آنی (Unlock)');
				}
			});
		});

		// 10. Import JSON & Reset Settings
		$('#ashna_import_settings_btn').on('click', function () {
			var jsonText = ($('#ashna_import_json_box').val() || '').trim();
			if (!jsonText) {
				showToast('لطفاً ابتدا کد JSON تنظیمات را وارد کنید.', true);
				return;
			}
			$.ajax({
				url: ashnaAdminParams.ajaxUrl,
				type: 'POST',
				dataType: 'json',
				data: {
					action: 'ashna_admin_import_settings',
					nonce: ashnaAdminParams.nonce,
					json_data: jsonText
				}
			}).done(function (res) {
				if (res && res.success) {
					showToast('✅ ' + res.data.message);
					setTimeout(function () {
						window.location.reload();
					}, 900);
				} else {
					showToast('❌ ' + (res && res.data ? res.data.message : 'خطا در درون‌ریزی'), true);
				}
			});
		});

		$('#ashna_reset_defaults_btn').on('click', function () {
			if (!window.confirm('آیا مطمئن هستید که می‌خواهید تمام تنظیمات را به حالت پیش‌فرض بازنشانی کنید؟')) {
				return;
			}
			$.ajax({
				url: ashnaAdminParams.ajaxUrl,
				type: 'POST',
				dataType: 'json',
				data: {
					action: 'ashna_admin_reset_settings',
					nonce: ashnaAdminParams.nonce
				}
			}).done(function (res) {
				if (res && res.success) {
					showToast('✅ ' + res.data.message);
					setTimeout(function () {
						window.location.reload();
					}, 800);
				}
			});
		});
	});
})(jQuery);
