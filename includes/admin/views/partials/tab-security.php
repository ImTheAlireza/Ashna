<?php
/**
 * Admin Settings Partial: tab-security.php
 *
 * Layout: one full-width "shield" card (the rate limits that actually stop SMS bombing), then a
 * split row (captcha + firewall lists | real-IP diagnostics), then the two live monitors.
 *
 * Every field keeps its original name and id, and the JS hooks (attack banner, captcha note
 * toggles, unlock / trust-proxy / fetch-Arvan buttons) are unchanged.
 *
 * @package Ashna
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$ashna_attack_state = Ashna_Rate_Limiter::attack_mode_state();
$ashna_block_stats  = Ashna_Rate_Limiter::block_stats();
$ashna_ip_diag      = Ashna_IP::diagnostics();
$ashna_proxy_hint   = Ashna_IP::untrusted_proxy_hint();
$ashna_cap_type     = isset( $settings['captcha_type'] ) ? $settings['captcha_type'] : 'none';
$ashna_needs_key    = in_array( $ashna_cap_type, array( 'arcaptcha', 'recaptcha_v3', 'turnstile' ), true );

$ashna_reason_fa = array(
	'ashna_cooldown'                 => 'فاصله‌ی ارسال مجدد',
	'ashna_rate_limit_recipient'     => 'سقف ساعتی شماره',
	'ashna_rate_limit_recipient_day' => 'سقف روزانه‌ی شماره',
	'ashna_rate_limit_ip_minute'     => 'سقف دقیقه‌ای آی‌پی',
	'ashna_rate_limit_ip'            => 'سقف ساعتی آی‌پی',
	'ashna_rate_limit_subnet'        => 'سقف شبکه',
	'ashna_daily_cap'                => 'سقف روزانه‌ی سایت',
	'ashna_honeypot'                 => 'فیلد تله (ربات)',
	'ashna_captcha_empty'            => 'کپچای خالی',
	'ashna_captcha_wrong'            => 'کپچای اشتباه',
	'ashna_captcha_used'             => 'کپچای تکراری',
	'ashna_captcha_expired'          => 'کپچای منقضی',
	'ashna_captcha_missing'          => 'کپچای خالی',
	'ashna_captcha_failed'           => 'کپچای ناموفق',
	'ashna_arcaptcha_failed'         => 'کپچای ناموفق',
	'ashna_locked_out'               => 'قفل موقت',
	'ashna_blocked_ip'               => 'آی‌پی مسدود',
	'ashna_blocked_recipient'        => 'شماره‌ی مسدود',
);

$ashna_limits = array(
	array(
		'id'    => 'max_requests_per_hour',
		'title' => 'هر شماره',
		'unit'  => 'در ساعت',
		'hint'  => 'فاصله‌ی ارسال مجدد (پیش‌فرض ۶۰ ثانیه) هم یعنی حداکثر ۱ کد در دقیقه.',
	),
	array(
		'id'    => 'max_requests_per_day',
		'title' => 'هر شماره',
		'unit'  => 'در ۲۴ ساعت',
		'hint'  => 'جلوی مزاحمت طولانی برای یک شماره را می‌گیرد.',
	),
	array(
		'id'    => 'max_ip_requests_per_minute',
		'title' => 'هر آی‌پی',
		'unit'  => 'در دقیقه',
		'hint'  => 'ارسال رگباری به شماره‌های مختلف از یک دستگاه.',
	),
	array(
		'id'    => 'max_ip_requests_per_hour',
		'title' => 'هر آی‌پی',
		'unit'  => 'در ساعت',
		'hint'  => 'جلوگیری از تست شماره‌های متعدد توسط یک دستگاه.',
	),
);

$ashna_advanced = array(
	array(
		'id'    => 'max_subnet_requests_per_minute',
		'title' => 'سقف هر شبکه‌ی آی‌پی',
		'unit'  => 'در دقیقه',
		'hint'  => 'آی‌پی‌های یک رنج (‎/24 و در IPv6 ‎/64) با هم شمرده می‌شوند.',
	),
	array(
		'id'    => 'daily_send_cap',
		'title' => 'سقف ارسال کل سایت',
		'unit'  => 'در روز',
		'hint'  => 'محافظ شارژ پنل؛ با رسیدن به سقف ارسال تا فردا متوقف و به مدیر ایمیل می‌شود.',
	),
	array(
		'id'    => 'attack_mode_threshold',
		'title' => 'آستانه‌ی «حالت حمله»',
		'unit'  => 'درخواست/دقیقه',
		'hint'  => 'با عبور از این عدد، کپچا برای همه اجباری و به مدیر ایمیل می‌شود.',
	),
	array(
		'id'    => 'attack_mode_minutes',
		'title' => 'مدت «حالت حمله»',
		'unit'  => 'دقیقه',
		'hint'  => 'بعد از این مدت، فرم خودکار به حالت عادی برمی‌گردد.',
	),
	array(
		'id'    => 'max_verify_attempts',
		'title' => 'تعداد مجاز خطا در کد',
		'unit'  => 'بار',
		'hint'  => 'بعد از این تعداد اشتباه، شماره موقتاً قفل می‌شود.',
	),
	array(
		'id'    => 'lockout_duration',
		'title' => 'مدت قفل موقت',
		'unit'  => 'ثانیه',
		'hint'  => 'پیش‌فرض ۹۰۰ ثانیه (۱۵ دقیقه).',
	),
);

$ashna_zero_note = '۰ = بدون محدودیت.';
?>
				<section class="ashna-panel" id="ashna-tab-security_firewall">

					<!-- ============================================================
					     1. THE SHIELD — rate limits that stop SMS bombing & panel credit drain
					     ============================================================ -->
					<div class="ashna-card ashna-sec-hero" id="ashna-rate-limit-card">
						<div class="ashna-sec-hero-head">
							<div class="ashna-sec-hero-title">
								<span class="ashna-sec-hero-icon">
									<svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/><polyline points="8 12 11 15 16 9"/></svg>
								</span>
								<div>
									<h2>محافظت از شارژ پنل و ضد پیامک‌بمبر</h2>
									<p>هر درخواست قبل از تماس با درگاه سهمیه‌اش را رزرو می‌کند؛ حتی ده‌ها درخواست هم‌زمان برای یک شماره فقط یک پیامک می‌فرستند.</p>
								</div>
							</div>
							<div class="ashna-sec-hero-status">
								<span class="ashna-pill <?php echo $ashna_attack_state ? 'is-err' : 'is-ok'; ?>">
									<?php echo $ashna_attack_state ? 'حالت حمله فعال' : 'محافظت خودکار'; ?>
								</span>
							</div>
						</div>

						<?php if ( $ashna_attack_state ) : ?>
							<div class="ashna-info-note is-danger" id="ashna-attack-mode-note" style="margin:0 0 18px;">
								<span>
									<strong>حالت حمله فعال است:</strong>
									<?php
									echo esc_html(
										sprintf(
											'%s درخواست در یک دقیقه رسید؛ تا ساعت %s پاسخ‌دادن به کپچا برای همه اجباری است.',
											number_format_i18n( (int) $ashna_attack_state['peak'] ),
											wp_date( 'H:i', (int) $ashna_attack_state['until'] )
										)
									);
									?>
								</span>
								<button type="button" class="ashna-btn-secondary ashna-end-attack-btn">پایان حالت حمله</button>
							</div>
						<?php endif; ?>

						<!-- The four numbers that do most of the work, as a row of tiles -->
						<div class="ashna-sec-limit-row">
							<?php foreach ( $ashna_limits as $ashna_limit ) : ?>
								<label class="ashna-sec-limit" for="<?php echo esc_attr( $ashna_limit['id'] ); ?>">
									<span class="ashna-sec-limit-head">
										<span><?php echo esc_html( $ashna_limit['title'] ); ?></span>
										<em><?php echo esc_html( $ashna_limit['unit'] ); ?></em>
									</span>
									<input
										type="text"
										inputmode="numeric"
										class="ashna-sec-limit-input"
										name="ashna[<?php echo esc_attr( $ashna_limit['id'] ); ?>]"
										id="<?php echo esc_attr( $ashna_limit['id'] ); ?>"
										value="<?php echo esc_attr( (string) $settings[ $ashna_limit['id'] ] ); ?>"
										dir="ltr"
									/>
									<small><?php echo esc_html( $ashna_limit['hint'] . ' ' . $ashna_zero_note ); ?></small>
								</label>
							<?php endforeach; ?>
						</div>

						<details class="ashna-sec-more">
							<summary>
								<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 12 15 18 9"/></svg>
								تنظیمات پیشرفته‌ی محافظت
							</summary>
							<div class="ashna-sec-advanced-grid">
								<?php foreach ( $ashna_advanced as $ashna_field ) : ?>
									<div class="ashna-field">
										<label for="<?php echo esc_attr( $ashna_field['id'] ); ?>">
											<?php echo esc_html( $ashna_field['title'] ); ?>
											<span class="ashna-sec-unit">(<?php echo esc_html( $ashna_field['unit'] ); ?>)</span>
										</label>
										<input
											type="text"
											inputmode="numeric"
											name="ashna[<?php echo esc_attr( $ashna_field['id'] ); ?>]"
											id="<?php echo esc_attr( $ashna_field['id'] ); ?>"
											value="<?php echo esc_attr( (string) $settings[ $ashna_field['id'] ] ); ?>"
											dir="ltr"
										/>
										<small><?php echo esc_html( $ashna_field['hint'] ); ?></small>
									</div>
								<?php endforeach; ?>
							</div>
						</details>
					</div>

					<!-- ============================================================
					     2. SPLIT ROW — captcha + firewall lists (left) | real IP (right)
					     ============================================================ -->
					<div class="ashna-sec-split">

						<div class="ashna-sec-stack">

							<!-- 2a. Captcha -->
							<div class="ashna-card" id="ashna-captcha-card">
								<div class="ashna-card-head">
									<div class="ashna-card-head-title">
										<span class="ashna-card-icon is-purple">
											<svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.1" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="11" width="18" height="10" rx="2"/><circle cx="12" cy="5" r="2"/><path d="M12 7v4"/><line x1="8" y1="16" x2="8.01" y2="16"/><line x1="16" y1="16" x2="16.01" y2="16"/></svg>
										</span>
										<div>
											<h2>سپر ضد ربات (کپچا)</h2>
											<p>جلوگیری از حملات ربات‌های پیامک‌بمبر روی فرم ورود</p>
										</div>
									</div>
								</div>

								<div class="ashna-field">
									<label for="captcha_type">نوع کپچای فرم ورود</label>
									<select name="ashna[captcha_type]" id="captcha_type">
										<option value="none" <?php selected( $ashna_cap_type, 'none' ); ?>>غیرفعال (بدون کپچا — تجربه راحت‌تر کاربر)</option>
										<option value="math" <?php selected( $ashna_cap_type, 'math' ); ?>>کپچای ریاضی داخلی (آماده و بدون نیاز به کلید)</option>
										<option value="arcaptcha" <?php selected( $ashna_cap_type, 'arcaptcha' ); ?>>آرکپچا — Arcaptcha.ir (بومی و ضد تحریم)</option>
										<option value="recaptcha_v3" <?php selected( $ashna_cap_type, 'recaptcha_v3' ); ?>>گوگل کپچا — Google reCAPTCHA v3 (نامرئی)</option>
										<option value="turnstile" <?php selected( $ashna_cap_type, 'turnstile' ); ?>>کلودفلر — Cloudflare Turnstile</option>
									</select>
								</div>

								<div id="ashna-captcha-keys-wrap" style="margin-top:14px; <?php echo $ashna_needs_key ? '' : 'display:none;'; ?>">
									<div class="ashna-field">
										<label for="captcha_site_key">کلید عمومی سایت (Site Key)</label>
										<input type="text" name="ashna[captcha_site_key]" id="captcha_site_key" value="<?php echo esc_attr( $settings['captcha_site_key'] ); ?>" dir="ltr" placeholder="Site Key" />
									</div>
									<div class="ashna-field" style="margin-top:14px;">
										<label for="captcha_secret_key">کلید خصوصی (Secret Key)</label>
										<input type="password" name="ashna[captcha_secret_key]" id="captcha_secret_key" value="<?php echo esc_attr( $settings['captcha_secret_key'] ); ?>" dir="ltr" placeholder="Secret Key" />
									</div>
								</div>

								<div id="ashna-captcha-math-note" class="ashna-info-note" style="<?php echo 'math' === $ashna_cap_type ? '' : 'display:none;'; ?>">
									<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#10b981" stroke-width="2" style="flex-shrink:0;"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
									<span><strong>کپچای ریاضی داخلی</strong> بلافاصله فعال است و به هیچ کلید یا سرویس خارجی نیاز ندارد.</span>
								</div>

								<div id="ashna-captcha-none-note" class="ashna-info-note" style="<?php echo 'none' === $ashna_cap_type ? '' : 'display:none;'; ?>">
									<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#3b82f6" stroke-width="2" style="flex-shrink:0;"><circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg>
									<span>در حال حاضر محدودساز هوشمند نرخ ارسال (Rate Limit) بدون ایجاد مزاحمت کپچا از فرم شما محافظت می‌کند.</span>
								</div>
							</div>

							<!-- 2b. Firewall lists -->
							<div class="ashna-card" id="ashna-firewall-card">
								<div class="ashna-card-head">
									<div class="ashna-card-head-title">
										<span class="ashna-card-icon is-amber">
											<svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.1" stroke-linecap="round" stroke-linejoin="round"><path d="M8.5 14.5A2.5 2.5 0 0 0 11 12c0-1.38-.5-2-1-3-1.072-2.143-.224-4.054 2-6 .5 2.5 2 4.9 4 6.5 2 1.6 3 3.5 3 5.5a7 7 0 1 1-14 0c0-1.153.433-2.294 1-3a2.5 2.5 0 0 0 2.5 2.5z"/></svg>
										</span>
										<div>
											<h2>لیست سیاه و سفید</h2>
											<p>پشتیبانی از الگوی ستاره <code>*</code> در هر خط (مثلاً <code>0919000*</code> یا <code>185.10.*</code>)</p>
										</div>
									</div>
								</div>

								<div class="ashna-field">
									<label for="blocked_phones">شماره‌های مسدود (Blacklist)</label>
									<textarea name="ashna[blocked_phones]" id="blocked_phones" rows="3" dir="ltr" placeholder="09120000000&#10;0939111*"><?php echo esc_textarea( $settings['blocked_phones'] ); ?></textarea>
								</div>
								<div class="ashna-field" style="margin-top:12px;">
									<label for="blocked_ips">آدرس‌های IP مسدود (IP Block)</label>
									<textarea name="ashna[blocked_ips]" id="blocked_ips" rows="3" dir="ltr" placeholder="192.168.1.50&#10;185.10.*"><?php echo esc_textarea( $settings['blocked_ips'] ); ?></textarea>
								</div>
								<div class="ashna-field" style="margin-top:12px;">
									<label for="whitelisted_identifiers">لیست سفید معاف از محدودیت (Whitelist)</label>
									<textarea name="ashna[whitelisted_identifiers]" id="whitelisted_identifiers" rows="2" dir="ltr" placeholder="09121234567&#10;127.0.0.1"><?php echo esc_textarea( $settings['whitelisted_identifiers'] ); ?></textarea>
									<small>شماره‌ها یا آی‌پی‌های مدیران؛ این‌ها هیچ‌وقت به سقف نمی‌خورند.</small>
								</div>
							</div>
						</div>

						<!-- 2c. Real visitor IP -->
						<div class="ashna-card ashna-sec-ip" id="ashna-real-ip-card">
							<div class="ashna-card-head">
								<div class="ashna-card-head-title">
									<span class="ashna-card-icon is-cyan">
										<svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.1" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="2" y1="12" x2="22" y2="12"/><path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"/></svg>
									</span>
									<div>
										<h2>تشخیص آی‌پی واقعی (CDN و پراکسی)</h2>
										<p>هدر جعلی آی‌پی، محدودیت‌ها را دور نمی‌زند</p>
									</div>
								</div>
								<span class="ashna-pill <?php echo $ashna_ip_diag['trusted'] ? 'is-ok' : 'is-muted'; ?>">
									<?php echo $ashna_ip_diag['trusted'] ? 'پراکسی مورد اعتماد' : 'اتصال مستقیم'; ?>
								</span>
							</div>

							<div class="ashna-switch-row">
								<div class="ashna-switch-text">
									<strong>خواندن آی‌پی بازدیدکننده از هدر CDN/پراکسی</strong>
									<p>فقط از پراکسی‌های مورد اعتماد پذیرفته می‌شود: کلودفلر (خودکار)، شبکه‌ی داخلی سرور و فهرست زیر.</p>
								</div>
								<label class="ashna-switch">
									<input type="checkbox" name="ashna[trust_proxy_headers]" value="1" <?php checked( ! empty( $settings['trust_proxy_headers'] ), true ); ?> />
									<span class="ashna-slider"></span>
								</label>
							</div>

							<div class="ashna-field" style="margin-top:14px;">
								<label for="trusted_proxies">پراکسی‌ها و CDNهای مورد اعتماد (IP یا CIDR، هر خط یکی)</label>
								<textarea name="ashna[trusted_proxies]" id="trusted_proxies" rows="3" dir="ltr" placeholder="185.143.232.0/22"><?php echo esc_textarea( $settings['trusted_proxies'] ); ?></textarea>
								<small>اگر سایت پشت ابرآروان است، رنج‌های رسمی آن را اضافه کنید (<code dir="ltr">arvancloud.ir/fa/ips.txt</code>).</small>
								<div style="display:flex;gap:8px;flex-wrap:wrap;margin-top:8px;">
									<button type="button" class="ashna-btn-secondary ashna-fetch-arvan-btn">دریافت خودکار رنج‌های ابرآروان</button>
								</div>
							</div>

							<?php if ( $ashna_proxy_hint ) : ?>
								<div class="ashna-info-note is-warning" id="ashna-proxy-hint" style="margin-top:14px;">
									<span>
										<strong>احتمالاً سایت پشت CDN/پراکسی است که در فهرست نیست.</strong>
										درخواست‌ها با هدر <code dir="ltr"><?php echo esc_html( implode( ', ', (array) $ashna_proxy_hint['headers'] ) ); ?></code> از آی‌پی <code dir="ltr"><?php echo esc_html( $ashna_proxy_hint['remote'] ); ?></code> رسیده‌اند. تا وقتی این آی‌پی مورد اعتماد نباشد، همه‌ی بازدیدکننده‌هایی که از آن عبور می‌کنند یک آی‌پی حساب می‌شوند و زودتر به سقف می‌رسند.
										<?php if ( ! empty( $ashna_proxy_hint['arvan'] ) ) : ?>
											هدر <code dir="ltr">AR-Real-IP</code> نشان می‌دهد سایت پشت ابرآروان است.
										<?php endif; ?>
									</span>
									<button type="button" class="ashna-btn-secondary ashna-trust-proxy-btn" data-ip="<?php echo esc_attr( $ashna_proxy_hint['remote'] ); ?>">اعتماد به این آی‌پی</button>
								</div>
							<?php endif; ?>

							<table class="ashna-modern-table ashna-sec-ip-table">
								<tbody>
									<tr>
										<td>آی‌پی اتصال (REMOTE_ADDR)</td>
										<td><code dir="ltr"><?php echo esc_html( $ashna_ip_diag['remote'] ); ?></code></td>
									</tr>
									<tr>
										<td>هدرهای پراکسی همین درخواست</td>
										<td dir="ltr" style="font-size:12px;"><?php echo empty( $ashna_ip_diag['headers'] ) ? '—' : esc_html( implode( ' | ', array_map( static function ( $k, $v ) { return $k . ': ' . $v; }, array_keys( $ashna_ip_diag['headers'] ), $ashna_ip_diag['headers'] ) ) ); ?></td>
									</tr>
									<tr>
										<td>آی‌پی شما از نگاه آشنا</td>
										<td><code dir="ltr"><?php echo esc_html( $ashna_ip_diag['resolved'] ); ?></code></td>
									</tr>
								</tbody>
							</table>

							<div class="ashna-info-note">
								<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#3b82f6" stroke-width="2" style="flex-shrink:0;"><circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg>
								<span>هدرهای CDN فقط از شبکه‌های شناخته‌شده (کلودفلر، ابرآروان یا فهرست بالا) خوانده می‌شوند؛ یک بازدیدکننده نمی‌تواند با جعل هدر، سقف را دور بزند.</span>
							</div>
						</div>
					</div>

					<!-- ============================================================
					     3. BOTTOM ROW — live lockouts | refused requests today
					     ============================================================ -->
					<div class="ashna-sec-split ashna-sec-split-bottom">

						<!-- 3a. Active lockouts -->
						<div class="ashna-card" id="ashna-lockouts-card">
							<div class="ashna-card-head">
								<div class="ashna-card-head-title">
									<span class="ashna-card-icon <?php echo empty( $active_lockouts ) ? 'is-green' : 'is-red'; ?>">
										<svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.1" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
									</span>
									<div>
										<h2>قفل‌های امنیتی فعال (Active Lockouts)</h2>
										<p>پایش لحظه‌ای شماره‌ها و IPهای قفل‌شده به دلیل تلاش غیرمجاز</p>
									</div>
								</div>
								<span class="ashna-pill <?php echo empty( $active_lockouts ) ? 'is-ok' : 'is-err'; ?>">
									<?php echo empty( $active_lockouts ) ? 'وضعیت امن' : sprintf( '%d قفل فعال', count( $active_lockouts ) ); ?>
								</span>
							</div>

							<?php if ( empty( $active_lockouts ) ) : ?>
								<div class="ashna-empty-state-box">
									<div class="ashna-empty-state-icon">
										<svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/><path d="m9 12 2 2 4-4"/></svg>
									</div>
									<strong>هیچ شماره یا آی‌پی مسدود شده‌ای وجود ندارد</strong>
									<p>سپر ضد Brute-Force فعال است و در صورت تلاش مشکوک، مهاجم را خودکار قفل می‌کند.</p>
								</div>
							<?php else : ?>
								<table class="ashna-modern-table">
									<thead>
										<tr>
											<th>شناسه / شماره</th>
											<th>آدرس IP</th>
											<th>باقی‌مانده</th>
											<th>عملیات</th>
										</tr>
									</thead>
									<tbody>
										<?php foreach ( $active_lockouts as $lock_key => $lock_info ) : ?>
											<tr>
												<td><?php if ( 0 === strpos( (string) $lock_info['target'], 'user:' ) ) : ?><strong>حساب کاربری <span dir="ltr"><?php echo esc_html( substr( $lock_info['target'], 5 ) ); ?></span></strong><?php else : ?><strong dir="ltr"><?php echo esc_html( $lock_info['target'] ); ?></strong><?php endif; ?></td>
												<td><code dir="ltr"><?php echo esc_html( $lock_info['ip'] ); ?></code></td>
												<td><?php echo esc_html( (string) ceil( $lock_info['remaining_sec'] / 60 ) ); ?> دقیقه</td>
												<td>
													<button type="button" class="ashna-btn-secondary ashna-unlock-btn" data-target="<?php echo esc_attr( $lock_key ); ?>">
														رفع مسدودی
													</button>
												</td>
											</tr>
										<?php endforeach; ?>
									</tbody>
								</table>
							<?php endif; ?>
						</div>

						<!-- 3b. Refused requests today -->
						<div class="ashna-card" id="ashna-blocked-card">
							<div class="ashna-card-head">
								<div class="ashna-card-head-title">
									<span class="ashna-card-icon <?php echo $ashna_block_stats['total'] > 0 ? 'is-amber' : 'is-green'; ?>">
										<svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.1" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="4.93" y1="4.93" x2="19.07" y2="19.07"/></svg>
									</span>
									<div>
										<h2>درخواست‌های ردشده‌ی امروز</h2>
										<p>هر درخواستی که به سقف خورد یا کپچا/تله را رد نکرد، بدون ارسال پیامک</p>
									</div>
								</div>
								<span class="ashna-pill <?php echo $ashna_block_stats['total'] > 0 ? 'is-warn' : 'is-ok'; ?>"><?php echo esc_html( sprintf( '%s مورد', number_format_i18n( (int) $ashna_block_stats['total'] ) ) ); ?></span>
							</div>

							<?php if ( empty( $ashna_block_stats['total'] ) ) : ?>
								<div class="ashna-empty-state-box">
									<div class="ashna-empty-state-icon">
										<svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
									</div>
									<strong>امروز هیچ درخواستی رد نشده است</strong>
									<p>اگر کسی تلاش به ارسال رگباری کند، این‌جا دیده می‌شود.</p>
								</div>
							<?php else : ?>
								<div class="ashna-sec-reason-chips">
									<?php foreach ( $ashna_block_stats['counts'] as $reason => $count ) : ?>
										<span class="ashna-pill is-muted"><?php echo esc_html( ( isset( $ashna_reason_fa[ $reason ] ) ? $ashna_reason_fa[ $reason ] : $reason ) . ': ' . number_format_i18n( (int) $count ) ); ?></span>
									<?php endforeach; ?>
								</div>
								<table class="ashna-modern-table">
									<thead>
										<tr>
											<th>زمان</th>
											<th>دلیل</th>
											<th>شماره</th>
											<th>آی‌پی</th>
										</tr>
									</thead>
									<tbody>
										<?php foreach ( array_slice( $ashna_block_stats['recent'], 0, 8 ) as $event ) : ?>
											<tr>
												<td dir="ltr" style="font-size:12px;"><?php echo esc_html( substr( (string) $event['time'], 11, 8 ) ); ?></td>
												<td><?php echo esc_html( isset( $ashna_reason_fa[ $event['reason'] ] ) ? $ashna_reason_fa[ $event['reason'] ] : $event['reason'] ); ?></td>
												<td dir="ltr"><?php echo esc_html( '' !== $event['recipient'] ? $event['recipient'] : '—' ); ?></td>
												<td><code dir="ltr"><?php echo esc_html( $event['ip'] ); ?></code></td>
											</tr>
										<?php endforeach; ?>
									</tbody>
								</table>
							<?php endif; ?>
						</div>
					</div>
				</section>
