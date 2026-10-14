<?php
/**
 * Admin Settings Partial: tab-security.php (2x2 Side-by-Side Bento Grid + Vector Icons)
 *
 * @package Ashna_OTP
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
				<section class="ashna-panel" id="ashna-tab-security_firewall">
					<!-- TOP BENTO ROW: Rate Limiting (Col 7) + Captcha Anti-Bot (Col 5) -->
					<div class="ashna-bento-row">
						<!-- Box 1: Rate Limiting & Brute-Force Protection -->
						<div class="ashna-card ashna-col-7">
							<div class="ashna-card-head">
								<div class="ashna-card-head-title">
									<span class="ashna-card-icon is-blue">
										<svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.1" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/><polyline points="8 12 11 15 16 9"/></svg>
									</span>
									<div>
										<h2>محافظت از شارژ پنل و ضد اسپم</h2>
										<p>جلوگیری خودکار از درخواست‌های رگباری و قفل موقت مهاجمان</p>
									</div>
								</div>
								<span class="ashna-pill is-ok">محافظت خودکار</span>
							</div>

							<div class="ashna-fields-grid ashna-cols-2">
								<div class="ashna-field">
									<label for="max_requests_per_hour">سقف پیامک هر شماره (در ساعت)</label>
									<input type="text" inputmode="numeric" name="ashna[max_requests_per_hour]" id="max_requests_per_hour" value="<?php echo esc_attr( (string) $settings['max_requests_per_hour'] ); ?>" dir="ltr" />
									<small>هر شماره موبایل چند بار در ساعت اجازه دریافت کد دارد؟</small>
								</div>
								<div class="ashna-field">
									<label for="max_ip_requests_per_hour">سقف درخواست هر آی‌پی (در ساعت)</label>
									<input type="text" inputmode="numeric" name="ashna[max_ip_requests_per_hour]" id="max_ip_requests_per_hour" value="<?php echo esc_attr( (string) $settings['max_ip_requests_per_hour'] ); ?>" dir="ltr" />
									<small>جلوگیری از تست شماره‌های متعدد توسط یک دستگاه</small>
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

							<div class="ashna-switch-row" style="margin-top:18px;">
								<div class="ashna-switch-text">
									<strong>تشخیص آی‌پی واقعی پشت کلودفلر و ابرآروان</strong>
									<p>اگر سایت شما روی CDN است فعال کنید تا آی‌پی اصلی کاربر شناسایی شود.</p>
								</div>
								<label class="ashna-switch">
									<input type="checkbox" name="ashna[trust_proxy_headers]" value="1" <?php checked( ! empty( $settings['trust_proxy_headers'] ), true ); ?> />
									<span class="ashna-slider"></span>
								</label>
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
