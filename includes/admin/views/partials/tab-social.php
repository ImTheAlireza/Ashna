<?php
/**
 * Admin Settings Partial: tab-social.php — ورود با گوگل و تلگرام
 *
 * Each provider gets its own callback URL, its own console walk-through and its own
 * connection check, so an administrator never has to guess which address belongs to
 * which console. Field names, ids and the save flow are unchanged.
 *
 * @package Ashna
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$ashna_sso_providers = Ashna_SSO::providers();
$ashna_sso_home_ssl  = is_ssl() || 0 === strpos( (string) home_url(), 'https://' );
$ashna_sso_host      = (string) wp_parse_url( home_url( '/' ), PHP_URL_HOST );
$ashna_sso_is_local  = in_array( $ashna_sso_host, array( 'localhost', '127.0.0.1', '::1' ), true ) || '.local' === substr( $ashna_sso_host, -6 );

$ashna_sso_state_labels = array(
	'ready'      => 'آماده',
	'check'      => 'نیاز به بررسی',
	'incomplete' => 'ناقص',
	'off'        => 'خاموش',
);

$ashna_sso_state_classes = array(
	'ready'      => 'is-ok',
	'check'      => 'is-warn',
	'incomplete' => 'is-warn',
	'off'        => 'is-muted',
);
?>
				<section class="ashna-panel" id="ashna-tab-social">

					<div class="ashna-card ashna-sso-hero">
						<div class="ashna-sso-hero-top">
							<div class="ashna-card-head-title">
								<span class="ashna-card-icon is-blue">
									<svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.1" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
								</span>
								<div>
									<h2>ورود با گوگل و تلگرام</h2>
									<p>کاربر با یک کلیک وارد می‌شود؛ حساب آشنا، نشست ووکامرس و دسترسی‌ها دقیقاً مثل ورود پیامکی ساخته می‌شوند.</p>
								</div>
							</div>
							<div class="ashna-sso-hero-pills">
								<?php foreach ( $ashna_sso_providers as $ashna_sso_id => $ashna_sso_provider ) : ?>
									<?php $ashna_sso_state = Ashna_SSO::provider_state( $ashna_sso_id ); ?>
									<span class="ashna-pill <?php echo esc_attr( $ashna_sso_state_classes[ $ashna_sso_state ] ); ?>" data-sso-pill="<?php echo esc_attr( $ashna_sso_id ); ?>">
										<?php echo esc_html( $ashna_sso_provider['label'] ); ?>:
										<span data-sso-pill-text><?php echo esc_html( $ashna_sso_state_labels[ $ashna_sso_state ] ); ?></span>
									</span>
								<?php endforeach; ?>
							</div>
						</div>

						<ol class="ashna-sso-roadmap" aria-hidden="true">
							<li><span class="ashna-sso-roadmap-num">۱</span><span>ساخت اپ در کنسول</span></li>
							<li><span class="ashna-sso-roadmap-num">۲</span><span>ثبت آدرس بازگشتی</span></li>
							<li><span class="ashna-sso-roadmap-num">۳</span><span>وارد کردن کلیدها</span></li>
						</ol>

						<?php if ( ! $ashna_sso_home_ssl && ! $ashna_sso_is_local ) : ?>
							<p class="ashna-sso-hero-note">
								<strong>توجه:</strong> آدرس سایت شما HTTPS نیست. گوگل و تلگرام برای دامنهٔ واقعی، آدرس بازگشتی HTTPS می‌خواهند؛ این تنظیمات روی دامنهٔ بدون SSL کار نمی‌کند.
							</p>
						<?php endif; ?>
					</div>

					<?php foreach ( $ashna_sso_providers as $ashna_sso_id => $ashna_sso_provider ) : ?>
						<?php
						$ashna_sso_is_google   = 'google' === $ashna_sso_id;
						$ashna_sso_enabled     = ! empty( $settings[ 'sso_' . $ashna_sso_id . '_enabled' ] );
						$ashna_sso_client      = (string) $settings[ 'sso_' . $ashna_sso_id . '_client_id' ];
						$ashna_sso_secret      = (string) $settings[ 'sso_' . $ashna_sso_id . '_client_secret' ];
						$ashna_sso_warnings    = Ashna_SSO::credential_warnings( $ashna_sso_id );
						$ashna_sso_state       = Ashna_SSO::provider_state( $ashna_sso_id );
						$ashna_sso_redirect    = Ashna_SSO::redirect_uri( $ashna_sso_id );
						$ashna_sso_field       = 'ashna-sso-' . $ashna_sso_id;
						$ashna_sso_console     = $ashna_sso_is_google
							? 'https://console.cloud.google.com/auth/clients/create'
							: 'https://t.me/BotFather';
						$ashna_sso_console_fa  = $ashna_sso_is_google
							? 'باز کردن Google Auth Platform'
							: 'باز کردن BotFather در تلگرام';
						?>

						<article class="ashna-card ashna-sso-card" data-sso-provider="<?php echo esc_attr( $ashna_sso_id ); ?>" data-sso-has-secret="<?php echo $ashna_sso_secret ? '1' : '0'; ?>">
							<div class="ashna-card-head ashna-sso-head">
								<div class="ashna-card-head-title">
									<span class="ashna-card-icon <?php echo $ashna_sso_is_google ? 'is-blue' : 'is-purple'; ?>">
										<?php if ( $ashna_sso_is_google ) : ?>
											<svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.1" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"/><path d="M2 12h20"/></svg>
										<?php else : ?>
											<svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.1" stroke-linecap="round" stroke-linejoin="round"><line x1="22" y1="2" x2="11" y2="13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/></svg>
										<?php endif; ?>
									</span>
									<div>
										<h2><?php echo esc_html( $ashna_sso_provider['label'] ); ?></h2>
										<p><?php echo $ashna_sso_is_google ? 'ایمیل تأییدشدهٔ گوگل + نام و تصویر پروفایل' : 'شناسهٔ تلگرام، نام، یوزرنیم و در صورت اجازهٔ کاربر، شمارهٔ تأییدشده'; ?></p>
									</div>
								</div>
								<div class="ashna-sso-head-actions">
									<span class="ashna-pill <?php echo esc_attr( $ashna_sso_state_classes[ $ashna_sso_state ] ); ?>" data-sso-pill="<?php echo esc_attr( $ashna_sso_id ); ?>">
										<span data-sso-pill-text><?php echo esc_html( $ashna_sso_state_labels[ $ashna_sso_state ] ); ?></span>
									</span>
									<label class="ashna-switch" title="فعال‌سازی ورود با <?php echo esc_attr( $ashna_sso_provider['label'] ); ?>">
										<input type="checkbox" name="ashna[sso_<?php echo esc_attr( $ashna_sso_id ); ?>_enabled]" value="1" <?php checked( $ashna_sso_enabled, true ); ?> data-sso-toggle="<?php echo esc_attr( $ashna_sso_id ); ?>" />
										<span class="ashna-slider"></span>
									</label>
								</div>
							</div>

							<div class="ashna-sso-columns">
								<div class="ashna-sso-guide">
									<h3 class="ashna-sso-col-title">
										<span class="ashna-sso-col-badge">۱</span>
										<?php echo $ashna_sso_is_google ? 'در Google Cloud Console' : 'در BotFather'; ?>
									</h3>

									<?php if ( $ashna_sso_is_google ) : ?>
										<ol class="ashna-sso-steps">
											<li class="ashna-sso-step">
												<span class="ashna-sso-step-num">۱</span>
												<div class="ashna-sso-step-body">
													<strong>پروژه و صفحهٔ رضایت</strong>
													<p>در همان پروژه‌ای که می‌خواهید استفاده کنید (یا پروژهٔ جدیدی بسازید)، به <code>APIs &amp; Services → OAuth consent screen</code> بروید و <code>Get started</code> را بزنید. نوع کاربر را <strong>External</strong> بگذارید؛ نام اپ و ایمیل پشتیبانی را پر کنید.</p>
												</div>
											</li>
											<li class="ashna-sso-step">
												<span class="ashna-sso-step-num">۲</span>
												<div class="ashna-sso-step-body">
													<strong>دسترسی‌ها و کاربران آزمایشی</strong>
													<p>در <code>Data access</code> سه اسکوپ <code>openid</code>، <code>.../auth/userinfo.email</code> و <code>.../auth/userinfo.profile</code> را اضافه کنید. تا وقتی وضعیت <strong>Testing</strong> است فقط <code>Test users</code> می‌توانند وارد شوند؛ یا از <code>Audience</code> گزینهٔ <strong>Publish app</strong> را بزنید (این سه اسکوپ حساس نیستند و تأیید Google لازم ندارند).</p>
												</div>
											</li>
											<li class="ashna-sso-step">
												<span class="ashna-sso-step-num">۳</span>
												<div class="ashna-sso-step-body">
													<strong>ساخت Client</strong>
													<p>در <code>Clients</code> گزینهٔ <code>Create client</code> را بزنید، نوع <strong>Web application</strong> را انتخاب کنید و آدرس بازگشتی کپی‌شده را در <code>Authorized redirect URIs</code> دقیقاً بچسبانید. سپس <code>Create</code>.</p>
												</div>
											</li>
											<li class="ashna-sso-step">
												<span class="ashna-sso-step-num">۴</span>
												<div class="ashna-sso-step-body">
													<strong>برداشتن کلیدها</strong>
													<p>در پنجرهٔ همان Client، مقدار <code>Client ID</code> (که به <code>.apps.googleusercontent.com</code> ختم می‌شود) و <code>Client Secret</code> (با <code>GOCSPX-</code>) را بردارید. Secret فقط یک‌بار نمایش داده می‌شود.</p>
												</div>
											</li>
										</ol>
									<?php else : ?>
										<ol class="ashna-sso-steps">
											<li class="ashna-sso-step">
												<span class="ashna-sso-step-num">۱</span>
												<div class="ashna-sso-step-body">
													<strong>بات را انتخاب کنید</strong>
													<p>در تلگرام <code>@BotFather</code> را باز کنید (ترجیحاً نسخهٔ Mini App) و بات موجود را انتخاب کنید؛ اگر بات ندارید با <code>/newbot</code> بسازید.</p>
												</div>
											</li>
											<li class="ashna-sso-step">
												<span class="ashna-sso-step-num">۲</span>
												<div class="ashna-sso-step-body">
													<strong>Web Login را روشن کنید</strong>
													<p>در <code>Bot Settings</code> گزینهٔ <strong>Login Widget / Web Login</strong> را باز کنید. اگر تنظیمات OIDC را نمی‌بینید، <code>Switch to OpenID Connect Login</code> را بزنید. اگر این گزینه پیدا نشد، آدرس قبلی را حذف کنید، صفحه را ببندید و دوباره باز کنید.</p>
												</div>
											</li>
											<li class="ashna-sso-step">
												<span class="ashna-sso-step-num">۳</span>
												<div class="ashna-sso-step-body">
													<strong>ثبت آدرس بازگشتی</strong>
													<p><code>Add a Redirect URI</code> را بزنید و آدرس کپی‌شده را عیناً وارد کنید. اگر این آدرس ثبت نشود، تلگرام کد را به‌صورت <code>#tgAuthResult</code> برمی‌گرداند و ورود انجام نمی‌شود.</p>
												</div>
											</li>
											<li class="ashna-sso-step">
												<span class="ashna-sso-step-num">۴</span>
												<div class="ashna-sso-step-body">
													<strong>برداشتن کلیدها</strong>
													<p>همان صفحه <code>Client ID</code> (شناسهٔ عددی بات) و <code>Client Secret</code> را نشان می‌دهد. این Secret <strong>توکن بات نیست</strong>؛ توکن بات شکل <code>12345:AA...</code> دارد و فقط برای ارسال پیام است.</p>
												</div>
											</li>
										</ol>
									<?php endif; ?>

									<a class="ashna-btn-secondary ashna-sso-console-link" href="<?php echo esc_url( $ashna_sso_console ); ?>" target="_blank" rel="noopener noreferrer">
										<?php echo esc_html( $ashna_sso_console_fa ); ?>
										<svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"/><polyline points="15 3 21 3 21 9"/><line x1="10" y1="14" x2="21" y2="3"/></svg>
									</a>
								</div>

								<div class="ashna-sso-config">
									<h3 class="ashna-sso-col-title">
										<span class="ashna-sso-col-badge">۲</span>
										آدرس و کلیدها در آشنا
									</h3>

									<div class="ashna-sso-urlbox">
										<div class="ashna-sso-urlbox-head">
											<span>آدرس بازگشتی مخصوص <?php echo esc_html( $ashna_sso_provider['label'] ); ?></span>
											<span class="ashna-sso-required">الزامی</span>
										</div>
										<div class="ashna-sso-url-row">
											<code id="<?php echo esc_attr( $ashna_sso_field ); ?>-redirect" dir="ltr"><?php echo esc_html( $ashna_sso_redirect ); ?></code>
											<button type="button" class="ashna-sso-copy" data-copy="<?php echo esc_attr( $ashna_sso_redirect ); ?>" aria-label="کپی آدرس بازگشتی <?php echo esc_attr( $ashna_sso_provider['label'] ); ?>">
												<svg class="ashna-sso-copy-icon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="9" y="9" width="13" height="13" rx="2" ry="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/></svg>
												<svg class="ashna-sso-copy-ok" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
											</button>
										</div>
										<p>
											<?php if ( $ashna_sso_is_google ) : ?>
												این را در <code>Authorized redirect URIs</code> همان Client اضافه کنید. آدرس باید کاراکتربه‌کاراکتر یکی باشد؛ <code>https</code> و <code>http</code>، <code>www</code> و اسلش آخر مهم‌اند.
											<?php else : ?>
												این را در <code>Web Login → Allowed URLs / Redirect URI</code> ثبت کنید. آدرس باید کاراکتربه‌کاراکتر یکی باشد و روی دامنهٔ HTTPS باشد.
											<?php endif; ?>
										</p>
									</div>

									<div class="ashna-field">
										<label for="<?php echo esc_attr( $ashna_sso_field ); ?>-id">Client ID</label>
										<input
											id="<?php echo esc_attr( $ashna_sso_field ); ?>-id"
											type="text"
											name="ashna[sso_<?php echo esc_attr( $ashna_sso_id ); ?>_client_id]"
											value="<?php echo esc_attr( $ashna_sso_client ); ?>"
											dir="ltr"
											autocomplete="off"
											data-sso-field="client_id"
											aria-describedby="<?php echo esc_attr( $ashna_sso_field ); ?>-id-hint"
											placeholder="<?php echo $ashna_sso_is_google ? '1234567890-xxxxxxxx.apps.googleusercontent.com' : '8819388185'; ?>"
										/>
										<p class="ashna-sso-hint <?php echo isset( $ashna_sso_warnings['client_id'] ) ? 'is-warn' : ''; ?>" id="<?php echo esc_attr( $ashna_sso_field ); ?>-id-hint" data-sso-hint="client_id">
											<?php
											if ( isset( $ashna_sso_warnings['client_id'] ) ) {
												echo esc_html( $ashna_sso_warnings['client_id'] );
											} elseif ( $ashna_sso_is_google ) {
												echo 'از بخش Clients، همان Client نوع Web application.';
											} else {
												echo 'شناسهٔ عددی بات؛ همان عددی که در Web Login نشان داده می‌شود.';
											}
											?>
										</p>
									</div>

									<div class="ashna-field">
										<label for="<?php echo esc_attr( $ashna_sso_field ); ?>-secret">Client Secret</label>
										<div class="ashna-sso-secret">
											<input
												id="<?php echo esc_attr( $ashna_sso_field ); ?>-secret"
												type="password"
												name="ashna[sso_<?php echo esc_attr( $ashna_sso_id ); ?>_client_secret]"
												value=""
												dir="ltr"
												autocomplete="new-password"
												data-sso-field="client_secret"
												aria-describedby="<?php echo esc_attr( $ashna_sso_field ); ?>-secret-hint"
												placeholder="<?php echo $ashna_sso_secret ? 'ذخیره شده — برای تغییر، مقدار جدید را وارد کنید' : ( $ashna_sso_is_google ? 'GOCSPX-…' : 'Client Secret از Web Login' ); ?>"
											/>
											<button type="button" class="ashna-sso-eye" aria-label="نمایش یا پنهان‌کردن Secret" aria-pressed="false">
												<svg class="ashna-sso-eye-show" width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
												<svg class="ashna-sso-eye-hide" width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94"/><path d="M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19"/><path d="M14.12 14.12a3 3 0 1 1-4.24-4.24"/><line x1="1" y1="1" x2="23" y2="23"/></svg>
											</button>
										</div>
										<p class="ashna-sso-hint <?php echo isset( $ashna_sso_warnings['client_secret'] ) ? 'is-warn' : ''; ?>" id="<?php echo esc_attr( $ashna_sso_field ); ?>-secret-hint" data-sso-hint="client_secret">
											<?php
											if ( isset( $ashna_sso_warnings['client_secret'] ) ) {
												echo esc_html( $ashna_sso_warnings['client_secret'] );
											} elseif ( $ashna_sso_secret ) {
												echo 'ذخیره شده و دیگر نمایش داده نمی‌شود؛ برای تغییر، مقدار جدید را وارد کنید.';
											} elseif ( $ashna_sso_is_google ) {
												echo 'همراه Client ID در همان پنجره؛ فقط یک‌بار نشان داده می‌شود.';
											} else {
												echo 'از Web Login؛ توکن بات نیست و شکل <code>12345:AA…</code> ندارد.';
											}
											?>
										</p>
									</div>

									<div class="ashna-sso-tools">
										<button type="button" class="ashna-btn-secondary ashna-sso-check" data-provider="<?php echo esc_attr( $ashna_sso_id ); ?>">
											<span class="ashna-sso-spinner" aria-hidden="true"></span>
											<span class="ashna-sso-check-label">بررسی اتصال</span>
										</button>
										<span class="ashna-sso-check-hint">با مقادیر ذخیره‌شده؛ چیزی به بیرون فرستاده نمی‌شود.</span>
									</div>
									<div class="ashna-sso-result" data-sso-result="<?php echo esc_attr( $ashna_sso_id ); ?>" aria-live="polite" hidden></div>

									<div class="ashna-sso-scope">
										<span class="ashna-sso-scope-title">وقتی کاربر با <?php echo esc_html( $ashna_sso_provider['label'] ); ?> وارد می‌شود</span>
										<ul>
											<?php if ( $ashna_sso_is_google ) : ?>
												<li>ایمیل <strong>تأییدشده</strong>، نام، تصویر پروفایل و شناسهٔ ثابت گوگل</li>
												<li>ایمیل تأییدنشده پذیرفته نمی‌شود؛ شمارهٔ تلفن هم از گوگل گرفته نمی‌شود</li>
											<?php else : ?>
												<li>شناسهٔ عددی تلگرام، نام و یوزرنیم</li>
												<li>شماره فقط اگر کاربر در صفحهٔ تلگرام اجازه بدهد و شماره تأییدشده باشد</li>
											<?php endif; ?>
											<li>اولین ورود، حساب کاربری می‌سازد؛ ورودهای بعدی به همان حساب وصل می‌شوند</li>
										</ul>
										<p>
											رمز عبور هیچ‌وقت به آشنا نمی‌رسد.
											<?php if ( $ashna_sso_is_google ) : ?>
												اگر این ایمیل از قبل در سایت حساب داشته باشد، به همان حساب وصل می‌شود.
											<?php else : ?>
												اگر شمارهٔ تأییدشده از قبل در سایت حساب داشته باشد، به همان حساب وصل می‌شود.
											<?php endif; ?>
										</p>
									</div>

									<details class="ashna-sso-help">
										<summary>
											<span>مشکلات رایج <?php echo esc_html( $ashna_sso_provider['label'] ); ?></span>
											<svg class="ashna-sso-chevron" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 12 15 18 9"/></svg>
										</summary>
										<ul>
											<?php if ( $ashna_sso_is_google ) : ?>
												<li><strong>redirect_uri_mismatch</strong> — آدرس ثبت‌شده با آدرس بالا یکی نیست: <code>http</code> به‌جای <code>https</code>، <code>www</code> داشتن یا نداشتن، یا اسلش آخر.</li>
												<li><strong>403: access_denied</strong> — اپ در حالت Testing است و ایمیل کاربر در <code>Test users</code> نیست؛ یا اپ را Publish کنید.</li>
												<li><strong>invalid_client</strong> — Secret عوض شده است؛ در Clients یک Secret جدید بسازید و اینجا ذخیره کنید.</li>
											<?php else : ?>
												<li><strong>invalid_client</strong> — Web Login در BotFather فعال نشده، یا به‌جای Client Secret توکن بات وارد شده است.</li>
												<li>بازگشت با <strong>#tgAuthResult</strong> — آدرس بازگشتی در <code>Web Login → Allowed URLs</code> ثبت نشده است.</li>
												<li>دکمهٔ ورود تلگرام کار نمی‌کند — آدرس سایت باید HTTPS باشد و دامنه با آدرس ثبت‌شده در BotFather یکی باشد.</li>
											<?php endif; ?>
										</ul>
									</details>
								</div>
							</div>
						</article>
					<?php endforeach; ?>

					<div class="ashna-card">
						<div class="ashna-card-head">
							<div class="ashna-card-head-title">
								<span class="ashna-card-icon is-cyan">
									<svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.1" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1-2-2 2 2 0 0 1 2-2h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 2-2 2 2 0 0 1 2 2v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 2 2 2 2 0 0 1-2 2h-.09a1.65 1.65 0 0 0-1.51 1z"/></svg>
								</span>
								<div>
									<h2>رفتار دکمه‌ها</h2>
									<p>این دو گزینه روی هر دو ارائه‌دهنده اثر می‌گذارند و جدا از تنظیمات کنسول هستند.</p>
								</div>
							</div>
						</div>

						<div class="ashna-sso-switches">
							<div class="ashna-switch-row">
								<div class="ashna-switch-text">
									<strong>نمایش دکمه‌های ورود زیر فرم</strong>
									<p>در فرم کد یک‌بارمصرف، مودال و صفحهٔ مستقل.</p>
								</div>
								<label class="ashna-switch">
									<input type="checkbox" name="ashna[sso_show_on_forms]" value="1" <?php checked( ! empty( $settings['sso_show_on_forms'] ), true ); ?> />
									<span class="ashna-slider"></span>
								</label>
							</div>
							<div class="ashna-switch-row">
								<div class="ashna-switch-text">
									<strong>پیشنهاد ثبت شماره به حساب‌های بدون شماره</strong>
									<p>اگر کاربر با گوگل وارد شد و شماره ندارد، یک‌بار کارت «ثبت شماره» نشان داده می‌شود تا راه بازیابی حساب داشته باشد.</p>
								</div>
								<label class="ashna-switch">
									<input type="checkbox" name="ashna[sso_ask_phone]" value="1" <?php checked( ! empty( $settings['sso_ask_phone'] ), true ); ?> />
									<span class="ashna-slider"></span>
								</label>
							</div>
						</div>
					</div>
				</section>
