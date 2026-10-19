<?php
/**
 * "Add your phone" card for accounts created with Google / Telegram.
 *
 * Shown once, dismissible, and only to a signed-in user whose account has no phone yet.
 *
 * @package Ashna
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="ashna-sso-phone" id="ashna-sso-phone" role="dialog" aria-labelledby="ashna-sso-phone-title" aria-live="polite">
	<button type="button" class="ashna-sso-phone-close" aria-label="بستن">&times;</button>

	<div class="ashna-sso-phone-body" data-step="intro">
		<div class="ashna-sso-phone-icon" aria-hidden="true">
			<svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="5" y="2" width="14" height="20" rx="2" ry="2"/><line x1="12" y1="18" x2="12.01" y2="18"/></svg>
		</div>
		<div>
			<strong id="ashna-sso-phone-title">شمارهٔ موبایلت را ثبت کن</strong>
			<p>اگر روزی به حساب گوگل/تلگرام دسترسی نداشتی، با کد پیامکی هم می‌توانی وارد شوی.</p>
			<div class="ashna-sso-phone-actions">
				<button type="button" class="ashna-btn ashna-btn-primary" data-ashna-sso="start">ثبت شماره</button>
				<button type="button" class="ashna-btn-link" data-ashna-sso="dismiss">بعداً</button>
			</div>
		</div>
	</div>

	<div class="ashna-sso-phone-body" data-step="phone" hidden>
		<div>
			<strong>شمارهٔ موبایل</strong>
			<p>مثل <span dir="ltr">09123456789</span></p>
			<input type="tel" inputmode="numeric" class="ashna-sso-phone-input" dir="ltr" autocomplete="tel" placeholder="09xxxxxxxxx" maxlength="13" />
			<div class="ashna-sso-phone-actions">
				<button type="button" class="ashna-btn ashna-btn-primary" data-ashna-sso="send">ارسال کد</button>
				<button type="button" class="ashna-btn-link" data-ashna-sso="back">بازگشت</button>
			</div>
		</div>
	</div>

	<div class="ashna-sso-phone-body" data-step="code" hidden>
		<div>
			<strong>کد تأیید</strong>
			<p class="ashna-sso-phone-sent">کد پیامک‌شده را وارد کن.</p>
			<input type="text" inputmode="numeric" class="ashna-sso-code-input" dir="ltr" autocomplete="one-time-code" placeholder="- - - - -" maxlength="8" />
			<div class="ashna-sso-phone-actions">
				<button type="button" class="ashna-btn ashna-btn-primary" data-ashna-sso="verify">تأیید و ثبت</button>
				<button type="button" class="ashna-btn-link" data-ashna-sso="back">تغییر شماره</button>
			</div>
		</div>
	</div>

	<div class="ashna-sso-phone-msg" role="status" aria-live="polite"></div>
</div>
