<?php
/**
 * Admin Settings Partial: tab-security.php (2x2 Side-by-Side Bento Grid + Vector Icons)
 *
 * @package Ashna
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
				<section class="ashna-panel" id="ashna-tab-security_firewall">
					<!-- TOP BENTO ROW: Rate Limiting (Col 7) + Captcha Anti-Bot (Col 5) -->
					<div class="ashna-bento-row">
						<!-- Box 1: Rate Limiting & Brute-Force Protection -->
						<?php
						$attack_state = Ashna_Rate_Limiter::attack_mode_state();
						$block_stats  = Ashna_Rate_Limiter::block_stats();
						?>
						<div class="ashna-card ashna-col-7" id="ashna-rate-limit-card">
							<div class="ashna-card-head">
								<div class="ashna-card-head-title">
									<span class="ashna-card-icon is-blue">
										<svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.1" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/><polyline points="8 12 11 15 16 9"/></svg>
									</span>
									<div>
										<h2>محافظت از شارژ پنل و ضد پیامک‌بمبر</h2>
										<p>سقف دقیقه‌ای، ساعتی و روزانه برای هر شماره، هر آی‌پی و هر شبکه</p>
									</div>
								</div>
								<span class="ashna-pill <?php echo $attack_state ? 'is-err' : 'is-ok'; ?>"><?php echo $attack_state ? 'حالت حمله فعال' : 'محافظت خودکار'; ?></span>
							</div>

							<?php if ( $attack_state ) : ?>
								<div class="ashna-info-note is-danger" id="ashna-attack-mode-note" style="margin-bottom:16px;">
									<span>
										<strong>حالت حمله فعال است:</strong>
										<?php
										echo esc_html(
											sprintf(
												'%s درخواست در یک دقیقه رسید؛ تا ساعت %s پاسخ‌دادن به کپچا برای همه اجباری است.',
												number_format_i18n( (int) $attack_state['peak'] ),
												wp_date( 'H:i', (int) $attack_state['until'] )
											)
										);
										?>
									</span>
									<button type="button" class="ashna-btn-secondary ashna-end-attack-btn">پایان حالت حمله</button>
								</div>
							<?php endif; ?>

							<div class="ashna-fields-grid ashna-cols-2">
								<div class="ashna-field">
									<label for="max_requests_per_hour">سقف کد برای هر شماره (در ساعت)</label>
									<input type="text" inputmode="numeric" name="ashna[max_requests_per_hour]" id="max_requests_per_hour" value="<?php echo esc_attr( (string) $settings['max_requests_per_hour'] ); ?>" dir="ltr" />
									<small>فاصله‌ی ارسال مجدد (پیش‌فرض ۶۰ ثانیه) هم یعنی حداکثر ۱ کد در دقیقه؛ در تب «روش و مسیر ورود».</small>
								</div>
								<div class="ashna-field">
									<label for="max_requests_per_day">سقف کد برای هر شماره (در ۲۴ ساعت)</label>
									<input type="text" inputmode="numeric" name="ashna[max_requests_per_day]" id="max_requests_per_day" value="<?php echo esc_attr( (string) $settings['max_requests_per_day'] ); ?>" dir="ltr" />
									<small>جلوی مزاحمت طولانی برای یک شماره را می‌گیرد.</small>
								</div>
								<div class="ashna-field">
									<label for="max_ip_requests_per_minute">سقف درخواست هر آی‌پی (در دقیقه)</label>
									<input type="text" inputmode="numeric" name="ashna[max_ip_requests_per_minute]" id="max_ip_requests_per_minute" value="<?php echo esc_attr( (string) $settings['max_ip_requests_per_minute'] ); ?>" dir="ltr" />
									<small>ارسال رگباری به شماره‌های مختلف از یک دستگاه</small>
								</div>
								<div class="ashna-field">
									<label for="max_ip_requests_per_hour">سقف درخواست هر آی‌پی (در ساعت)</label>
									<input type="text" inputmode="numeric" name="ashna[max_ip_requests_per_hour]" id="max_ip_requests_per_hour" value="<?php echo esc_attr( (string) $settings['max_ip_requests_per_hour'] ); ?>" dir="ltr" />
									<small>جلوگیری از تست شماره‌های متعدد توسط یک دستگاه</small>
								</div>
								<div class="ashna-field">
									<label for="max_subnet_requests_per_minute">سقف هر شبکه‌ی آی‌پی (در دقیقه)</label>
									<input type="text" inputmode="numeric" name="ashna[max_subnet_requests_per_minute]" id="max_subnet_requests_per_minute" value="<?php echo esc_attr( (string) $settings['max_subnet_requests_per_minute'] ); ?>" dir="ltr" />
									<small>آی‌پی‌های یک رنج (‎/24 و در IPv6 ‎/64) با هم شمرده می‌شوند. ۰ = خاموش</small>
								</div>
								<div class="ashna-field">
									<label for="daily_send_cap">سقف ارسال کل سایت در روز</label>
									<input type="text" inputmode="numeric" name="ashna[daily_send_cap]" id="daily_send_cap" value="<?php echo esc_attr( (string) $settings['daily_send_cap'] ); ?>" dir="ltr" />
									<small>محافظ شارژ پنل: با رسیدن به سقف، ارسال تا فردا متوقف و به مدیر ایمیل می‌شود. ۰ = نامحدود</small>
								</div>
								<div class="ashna-field">
									<label for="attack_mode_threshold">آستانه‌ی «حالت حمله» (درخواست در دقیقه، کل سایت)</label>
									<input type="text" inputmode="numeric" name="ashna[attack_mode_threshold]" id="attack_mode_threshold" value="<?php echo esc_attr( (string) $settings['attack_mode_threshold'] ); ?>" dir="ltr" />
									<small>با عبور از این عدد، کپچا برای همه اجباری و به مدیر ایمیل می‌شود (اگر کپچایی تنظیم نشده باشد، کپچای ریاضی). ۰ = خاموش</small>
								</div>
								<div class="ashna-field">
									<label for="attack_mode_minutes">مدت «حالت حمله» (دقیقه)</label>
									<input type="text" inputmode="numeric" name="ashna[attack_mode_minutes]" id="attack_mode_minutes" value="<?php echo esc_attr( (string) $settings['attack_mode_minutes'] ); ?>" dir="ltr" />
									<small>بعد از این مدت، فرم خودکار به حالت عادی برمی‌گردد.</small>
								</div>
								<div class="ashna-field">
									<label for="max_verify_attempts">تعداد مجاز خطا در وارد کردن کد</label>
									<input type="text" inputmode="numeric" name="ashna[max_verify_attempts]" id="max_verify_attempts" value="<?php echo esc_attr( (string) $settings['max_verify_attempts'] ); ?>" dir="ltr" />
									<small>بعد از این تعداد اشتباه، شماره موقتاً قفل می‌شود</small>
								</div>
								<div class="ashna-field">
									<label for="lockout_duration">مدت زمان قفل موقت (ثانیه)</label>
									<input type="text" inputmode="numeric" name="ashna[lockout_duration]" id="lockout_duration" value="<?php echo esc_attr( (string) $settings['lockout_duration'] ); ?>" dir="ltr" />
									<small>پیش‌فرض: ۹۰۰ ثانیه (معادل ۱۵ دقیقه)</small>
								</div>
							</div>

							<div class="ashna-info-note" style="margin-top:16px;">
								<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#3b82f6" stroke-width="2" style="flex-shrink:0;"><circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg>
								<span>هر درخواست قبل از تماس با درگاه، سهمیه‌اش را رزرو می‌کند؛ حتی ده‌ها درخواست هم‌زمان برای یک شماره فقط یک پیامک می‌فرستند. فیلد تله‌ی نامرئی هم ربات‌های ساده را بی‌صدا رد می‌کند.</span>
							</div>
						</div>

						<!-- Box 2: Captcha Anti-Bot Shield -->
						<?php
						$cap_type  = isset( $settings['captcha_type'] ) ? $settings['captcha_type'] : 'none';
						$needs_key = in_array( $cap_type, array( 'arcaptcha', 'recaptcha_v3', 'turnstile' ), true );
						?>
						<div class="ashna-card ashna-col-5">
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
									<option value="none" <?php selected( $cap_type, 'none' ); ?>>غیرفعال (بدون کپچا — تجربه راحت‌تر کاربر)</option>
									<option value="math" <?php selected( $cap_type, 'math' ); ?>>کپچای ریاضی داخلی (آماده و بدون نیاز به کلید)</option>
									<option value="arcaptcha" <?php selected( $cap_type, 'arcaptcha' ); ?>>آرکپچا — Arcaptcha.ir (بومی و ضد تحریم)</option>
									<option value="recaptcha_v3" <?php selected( $cap_type, 'recaptcha_v3' ); ?>>گوگل کپچا — Google reCAPTCHA v3 (نامرئی)</option>
									<option value="turnstile" <?php selected( $cap_type, 'turnstile' ); ?>>کلودفلر — Cloudflare Turnstile</option>
								</select>
							</div>

							<div id="ashna-captcha-keys-wrap" style="margin-top:14px; <?php echo $needs_key ? '' : 'display:none;'; ?>">
								<div class="ashna-field">
									<label for="captcha_site_key">کلید عمومی سایت (Site Key)</label>
									<input type="text" name="ashna[captcha_site_key]" id="captcha_site_key" value="<?php echo esc_attr( $settings['captcha_site_key'] ); ?>" dir="ltr" placeholder="Site Key" />
								</div>

								<div class="ashna-field" style="margin-top:14px;">
									<label for="captcha_secret_key">کلید خصوصی (Secret Key)</label>
									<input type="password" name="ashna[captcha_secret_key]" id="captcha_secret_key" value="<?php echo esc_attr( $settings['captcha_secret_key'] ); ?>" dir="ltr" placeholder="Secret Key" />
								</div>
							</div>

							<div id="ashna-captcha-math-note" class="ashna-info-note" style="<?php echo 'math' === $cap_type ? '' : 'display:none;'; ?>">
								<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#10b981" stroke-width="2" style="flex-shrink:0;"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
								<span><strong>کپچای ریاضی داخلی</strong> بلافاصله فعال است و به هیچ کلید یا سرویس خارجی نیاز ندارد.</span>
							</div>

							<div id="ashna-captcha-none-note" class="ashna-info-note" style="<?php echo 'none' === $cap_type ? '' : 'display:none;'; ?>">
								<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#3b82f6" stroke-width="2" style="flex-shrink:0;"><circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg>
								<span>در حال حاضر محدودساز هوشمند نرخ ارسال (Rate Limit) بدون ایجاد مزاحمت کپچا از فرم شما محافظت می‌کند.</span>
							</div>
						</div>
					</div>

					<!-- MIDDLE BENTO ROW: Real visitor IP behind CDN/proxy (Col 6) + Refused requests today (Col 6) -->
					<?php
					$ip_diag    = Ashna_IP::diagnostics();
					$proxy_hint = Ashna_IP::untrusted_proxy_hint();
					$reason_fa  = array(
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
					?>
					<div class="ashna-bento-row" style="margin-top:20px;">
						<div class="ashna-card ashna-col-6" id="ashna-real-ip-card">
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

							<?php if ( $proxy_hint ) : ?>
								<div class="ashna-info-note is-warning" id="ashna-proxy-hint" style="margin-top:14px;">
									<span>
										<strong>احتمالاً سایت پشت CDN/پراکسی است که در فهرست نیست.</strong>
										درخواست‌ها با هدر <code dir="ltr"><?php echo esc_html( implode( ', ', (array) $proxy_hint['headers'] ) ); ?></code> از آی‌پی <code dir="ltr"><?php echo esc_html( $proxy_hint['remote'] ); ?></code> رسیده‌اند. تا وقتی این آی‌پی مورد اعتماد نباشد، همه‌ی بازدیدکننده‌هایی که از آن عبور می‌کنند یک آی‌پی حساب می‌شوند و زودتر به سقف می‌رسند.
										<?php if ( ! empty( $proxy_hint['arvan'] ) ) : ?>
											هدر <code dir="ltr">AR-Real-IP</code> نشان می‌دهد سایت پشت ابرآروان است.
										<?php endif; ?>
									</span>
									<button type="button" class="ashna-btn-secondary ashna-trust-proxy-btn" data-ip="<?php echo esc_attr( $proxy_hint['remote'] ); ?>">اعتماد به این آی‌پی</button>
								</div>
							<?php endif; ?>

							<table class="ashna-modern-table" style="margin-top:14px;">
								<tbody>
									<tr>
										<td>آی‌پی اتصال (REMOTE_ADDR)</td>
										<td><code dir="ltr"><?php echo esc_html( $ip_diag['remote'] ); ?></code> <?php echo $ip_diag['trusted'] ? '<span class="ashna-pill is-ok">پراکسی مورد اعتماد</span>' : ''; ?></td>
									</tr>
									<tr>
										<td>هدرهای پراکسی همین درخواست</td>
										<td dir="ltr" style="font-size:12px;"><?php echo empty( $ip_diag['headers'] ) ? '—' : esc_html( implode( ' | ', array_map( static function ( $k, $v ) { return $k . ': ' . $v; }, array_keys( $ip_diag['headers'] ), $ip_diag['headers'] ) ) ); ?></td>
									</tr>
									<tr>
										<td>آی‌پی شما از نگاه آشنا</td>
										<td><code dir="ltr"><?php echo esc_html( $ip_diag['resolved'] ); ?></code></td>
									</tr>
								</tbody>
							</table>
						</div>

						<div class="ashna-card ashna-col-6" id="ashna-blocked-card">
							<div class="ashna-card-head">
								<div class="ashna-card-head-title">
									<span class="ashna-card-icon <?php echo $block_stats['total'] > 0 ? 'is-amber' : 'is-green'; ?>">
										<svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.1" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="4.93" y1="4.93" x2="19.07" y2="19.07"/></svg>
									</span>
									<div>
										<h2>درخواست‌های ردشده‌ی امروز</h2>
										<p>هر درخواستی که به سقف خورد یا کپچا/تله را رد نکرد، بدون ارسال پیامک</p>
									</div>
								</div>
								<span class="ashna-pill <?php echo $block_stats['total'] > 0 ? 'is-warn' : 'is-ok'; ?>"><?php echo esc_html( sprintf( '%s مورد', number_format_i18n( (int) $block_stats['total'] ) ) ); ?></span>
							</div>

							<?php if ( empty( $block_stats['total'] ) ) : ?>
								<div class="ashna-empty-state-box">
									<strong>امروز هیچ درخواستی رد نشده است</strong>
									<p>اگر کسی تلاش به ارسال رگباری کند، این‌جا دیده می‌شود.</p>
								</div>
							<?php else : ?>
								<div style="display:flex;flex-wrap:wrap;gap:8px;margin-bottom:12px;">
									<?php foreach ( $block_stats['counts'] as $reason => $count ) : ?>
										<span class="ashna-pill is-muted"><?php echo esc_html( ( isset( $reason_fa[ $reason ] ) ? $reason_fa[ $reason ] : $reason ) . ': ' . number_format_i18n( (int) $count ) ); ?></span>
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
										<?php foreach ( array_slice( $block_stats['recent'], 0, 8 ) as $event ) : ?>
											<tr>
												<td dir="ltr" style="font-size:12px;"><?php echo esc_html( substr( (string) $event['time'], 11, 8 ) ); ?></td>
												<td><?php echo esc_html( isset( $reason_fa[ $event['reason'] ] ) ? $reason_fa[ $event['reason'] ] : $event['reason'] ); ?></td>
												<td dir="ltr"><?php echo esc_html( '' !== $event['recipient'] ? $event['recipient'] : '—' ); ?></td>
												<td><code dir="ltr"><?php echo esc_html( $event['ip'] ); ?></code></td>
											</tr>
										<?php endforeach; ?>
									</tbody>
								</table>
							<?php endif; ?>
						</div>
					</div>

					<!-- BOTTOM BENTO ROW: Firewall Lists (Col 6) + Active Lockouts Monitor (Col 6) -->
					<div class="ashna-bento-row" style="margin-top:20px;">
						<!-- Box 3: Firewall Blacklist & Whitelist -->
						<div class="ashna-card ashna-col-6">
							<div class="ashna-card-head">
								<div class="ashna-card-head-title">
									<span class="ashna-card-icon is-amber">
										<svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.1" stroke-linecap="round" stroke-linejoin="round"><path d="M8.5 14.5A2.5 2.5 0 0 0 11 12c0-1.38-.5-2-1-3-1.072-2.143-.224-4.054 2-6 .5 2.5 2 4.9 4 6.5 2 1.6 3 3.5 3 5.5a7 7 0 1 1-14 0c0-1.153.433-2.294 1-3a2.5 2.5 0 0 0 2.5 2.5z"/></svg>
									</span>
									<div>
										<h2>دیوار آتش: لیست سیاه و سفید (Firewall)</h2>
										<p>پشتیبانی از الگوی ستاره <code>*</code> در هر خط (مثلاً <code>0919000*</code> یا <code>185.10.*</code>)</p>
									</div>
								</div>
							</div>

							<div class="ashna-fields-grid ashna-cols-2">
								<div class="ashna-field">
									<label for="blocked_phones">شماره‌های مسدود (Blacklist)</label>
									<textarea name="ashna[blocked_phones]" id="blocked_phones" rows="3" dir="ltr" placeholder="09120000000&#10;0939111*"><?php echo esc_textarea( $settings['blocked_phones'] ); ?></textarea>
								</div>
								<div class="ashna-field">
									<label for="blocked_ips">آدرس‌های IP مسدود (IP Block)</label>
									<textarea name="ashna[blocked_ips]" id="blocked_ips" rows="3" dir="ltr" placeholder="192.168.1.50&#10;185.10.*"><?php echo esc_textarea( $settings['blocked_ips'] ); ?></textarea>
								</div>
							</div>

							<div class="ashna-field" style="margin-top:14px;">
								<label for="whitelisted_identifiers">لیست سفید معاف از محدودیت (Whitelist — شماره‌ها یا IPهای مدیران)</label>
								<textarea name="ashna[whitelisted_identifiers]" id="whitelisted_identifiers" rows="2" dir="ltr" placeholder="09121234567&#10;127.0.0.1"><?php echo esc_textarea( $settings['whitelisted_identifiers'] ); ?></textarea>
							</div>
						</div>

						<!-- Box 4: Active Lockouts Live Monitor -->
						<div class="ashna-card ashna-col-6">
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
												<td><strong dir="ltr"><?php echo esc_html( $lock_info['target'] ); ?></strong></td>
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
					</div>
				</section>
