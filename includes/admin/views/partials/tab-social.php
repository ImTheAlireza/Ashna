<?php
/**
 * Admin Settings Partial: tab-social.php — ورود با گوگل و تلگرام
 *
 * @package Ashna
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$ashna_sso_providers = Ashna_SSO::providers();
$ashna_sso_home_ssl  = is_ssl() || 0 === strpos( (string) home_url(), 'https://' );
?>
				<section class="ashna-panel" id="ashna-tab-social" style="display:none;">
					<div class="ashna-card" style="margin-bottom:16px;">
						<div class="ashna-card-head">
							<div class="ashna-card-head-title">
								<span class="ashna-card-icon is-blue">
									<svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.1" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
								</span>
								<div>
									<h2>ورود با گوگل و تلگرام</h2>
									<p>کاربر با یک کلیک وارد می‌شود؛ حساب آشنا، نشست ووکامرس و دسترسی‌ها مثل ورود پیامکی ساخته می‌شوند.</p>
								</div>
							</div>
							<span class="ashna-pill <?php echo Ashna_SSO::enabled_providers() ? 'is-ok' : 'is-warn'; ?>">
								<?php echo Ashna_SSO::enabled_providers() ? 'فعال' : 'غیرفعال'; ?>
							</span>
						</div>

						<div class="ashna-alert" style="margin:0 0 16px;padding:12px 14px;border-radius:10px;background:rgba(59,130,246,.08);border:1px solid rgba(59,130,246,.25);font-size:13px;line-height:1.9;">
							<strong>قبل از فعال‌سازی، این آدرس را عیناً در پنل ارائه‌دهنده ثبت کنید:</strong>
							<div style="display:flex;align-items:center;gap:8px;margin-top:8px;flex-wrap:wrap;">
								<code id="ashna-sso-redirect" style="direction:ltr;user-select:all;padding:6px 8px;border-radius:6px;background:rgba(0,0,0,.06);"><?php echo esc_html( Ashna_SSO::redirect_uri() ); ?></code>
								<button type="button" class="ashna-btn ashna-btn-sm ashna-copy-btn" data-copy="<?php echo esc_attr( Ashna_SSO::redirect_uri() ); ?>">کپی</button>
							</div>
							<p style="margin:8px 0 0;color:var(--s-text-muted);">هر دو سرویس به همین یک آدرس برمی‌گردند؛ اسلش آخر را حذف نکنید. در گوگل این آدرس در <strong>Authorized redirect URIs</strong> و در تلگرام در <strong>BotFather → Bot Settings → Web Login</strong> ثبت می‌شود.</p>
							<?php if ( ! $ashna_sso_home_ssl ) : ?>
								<p style="margin:8px 0 0;color:#b45309;"><strong>توجه:</strong> آدرس سایت HTTPS نیست. گوگل و تلگرام برای دامنهٔ واقعی، آدرس بازگشتی HTTPS می‌خواهند.</p>
							<?php endif; ?>
						</div>

						<div style="display:flex;flex-direction:column;gap:12px;">
							<div class="ashna-switch-row">
								<div class="ashna-switch-text">
									<strong>نمایش دکمه‌های ورود زیر فرم</strong>
									<p>در فرم کد یکبارمصرف، مودال و صفحه‌ی مستقل.</p>
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

					<?php foreach ( $ashna_sso_providers as $ashna_sso_id => $ashna_sso_provider ) : ?>
						<?php
						$ashna_sso_enabled   = ! empty( $settings[ 'sso_' . $ashna_sso_id . '_enabled' ] );
						$ashna_sso_client    = (string) $settings[ 'sso_' . $ashna_sso_id . '_client_id' ];
						$ashna_sso_secret    = (string) $settings[ 'sso_' . $ashna_sso_id . '_client_secret' ];
						$ashna_sso_is_google = 'google' === $ashna_sso_id;
						?>
						<div class="ashna-card" style="margin-bottom:16px;">
							<div class="ashna-card-head">
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
								<label class="ashna-switch">
									<input type="checkbox" name="ashna[sso_<?php echo esc_attr( $ashna_sso_id ); ?>_enabled]" value="1" <?php checked( $ashna_sso_enabled, true ); ?> />
									<span class="ashna-slider"></span>
								</label>
							</div>

							<div class="ashna-fields-grid ashna-cols-2">
								<div class="ashna-field">
									<label>Client ID</label>
									<input type="text" name="ashna[sso_<?php echo esc_attr( $ashna_sso_id ); ?>_client_id]" value="<?php echo esc_attr( $ashna_sso_client ); ?>" dir="ltr" autocomplete="off" placeholder="<?php echo $ashna_sso_is_google ? 'xxxxx.apps.googleusercontent.com' : 'شناسهٔ عددی بات'; ?>" />
								</div>
								<div class="ashna-field">
									<label>Client Secret</label>
									<input type="password" name="ashna[sso_<?php echo esc_attr( $ashna_sso_id ); ?>_client_secret]" value="" dir="ltr" autocomplete="new-password" placeholder="<?php echo $ashna_sso_secret ? 'ذخیره شده — برای تغییر، مقدار جدید را وارد کنید' : 'مقدار Client Secret'; ?>" />
									<?php if ( $ashna_sso_secret ) : ?>
										<p style="margin:6px 0 0;font-size:12px;color:var(--s-text-muted);">این مقدار ذخیره شده و دیگر نمایش داده نمی‌شود.</p>
									<?php endif; ?>
								</div>
							</div>

							<?php if ( $ashna_sso_is_google ) : ?>
								<p style="margin:12px 0 0;font-size:12.5px;line-height:1.9;color:var(--s-text-muted);">
									مسیر: Google Cloud Console → APIs &amp; Services → Credentials → Create OAuth client ID → <strong>Web application</strong>.
									در OAuth consent screen، نام محصول و اسکوپ‌های <code>openid</code>، <code>email</code> و <code>profile</code> را تنظیم کنید.
								</p>
							<?php else : ?>
								<p style="margin:12px 0 0;font-size:12.5px;line-height:1.9;color:var(--s-text-muted);">
									مسیر: در تلگرام به <strong>@BotFather</strong> بروید → بات خود را انتخاب کنید → <code>Bot Settings → Web Login</code> → آدرس بازگشتی بالا را اضافه کنید.
									<strong>Client Secret توکن بات نیست</strong>؛ BotFather یک مقدار جدا برای همین ورود می‌دهد.
								</p>
							<?php endif; ?>
						</div>
					<?php endforeach; ?>
				</section>
