<?php
/**
 * Admin Settings Partial: tab-auth-flow.php (Real Bale Logo + Subtle Glow, Passkey Biometric Login & Human Copy)
 *
 * @package Ashna
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Crisp SVG Icons & Authentic Official Bale Messenger Logo with Subtle Glow
$svg_phone       = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><rect x="5" y="2" width="14" height="20" rx="2"/><line x1="12" y1="18" x2="12.01" y2="18"/></svg>';
$svg_email       = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="4" width="20" height="16" rx="2"/><path d="m22 7-8.97 5.7a1.94 1.94 0 0 1-2.06 0L2 7"/></svg>';
$svg_sms         = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/><path d="M8 9h8"/><path d="M8 13h5"/></svg>';
$svg_bale_real   = Ashna_Helper::get_bale_logo_svg( 22 );
$svg_arrow       = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round"><line x1="19" y1="12" x2="5" y2="12"/><polyline points="12 19 5 12 12 5"/></svg>';
$svg_check       = '<svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>';
$svg_fingerprint = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.1" stroke-linecap="round" stroke-linejoin="round"><path d="M2 12C2 6.5 6.5 2 12 2a10 10 0 0 1 8 4"/><path d="M5 19.5C5.5 18 6 15 6 12c0-.7.12-1.37.34-2"/><path d="M17.29 21.02c.12-.6.43-2.3.5-3.02"/><path d="M12 10a2 2 0 0 0-2 2c0 1.02-.1 2.51-.26 4"/><path d="M8.65 22c.21-.66.45-1.32.57-2"/><path d="M14 13.12c0 2.38 0 6.38-1 8.88"/><path d="M21.8 16c.2-2 .131-5.354 0-6"/><path d="M9 6.8a6 6 0 0 1 9 5.2c0 .47 0 1.17-.02 2"/></svg>';
$svg_faceid      = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.1" stroke-linecap="round" stroke-linejoin="round"><path d="M3 7V5a2 2 0 0 1 2-2h2"/><path d="M17 3h2a2 2 0 0 1 2 2v2"/><path d="M21 17v2a2 2 0 0 1-2 2h-2"/><path d="M7 21H5a2 2 0 0 1-2-2v-2"/><path d="M8 14s1.5 2 4 2 4-2 4-2"/><line x1="9" y1="9" x2="9.01" y2="9"/><line x1="15" y1="9" x2="15.01" y2="9"/></svg>';
?>
				<section class="ashna-panel" id="ashna-tab-auth_flow">
					<!-- TOP BENTO ROW: Identifier Type (Col 5) + Mobile Delivery Strategy (Col 7) -->
					<div class="ashna-bento-row">
						<!-- Top-Right Box (Col 5): 1. Login Identifier Mode -->
						<div class="ashna-card ashna-col-5">
							<div class="ashna-card-head">
								<div class="ashna-card-head-title">
									<span class="ashna-card-icon is-blue">
										<svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.1" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/><path d="m9 12 2 2 4-4"/></svg>
									</span>
									<div>
										<h2>۱. کاربران با چه چیزی وارد شوند؟</h2>
										<p>انتخاب ورودی فرم ورود و عضویت</p>
									</div>
								</div>
							</div>

							<div class="ashna-choice-grid ashna-cols-1">
								<label class="ashna-choice-card <?php echo 'phone_and_email' === $settings['login_mode'] ? 'selected' : ''; ?>">
									<input type="radio" name="ashna[login_mode]" value="phone_and_email" <?php checked( $settings['login_mode'], 'phone_and_email' ); ?> />
									<div class="ashna-choice-card-top">
										<div class="ashna-flow-icons" title="شماره موبایل یا ایمیل">
											<span class="ashna-flow-node is-phone"><?php echo $svg_phone; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
											<span class="ashna-flow-sep">+</span>
											<span class="ashna-flow-node is-email"><?php echo $svg_email; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
										</div>
										<div class="ashna-choice-top-left">
											<span class="ashna-choice-tag is-green">تشخیص خودکار</span>
											<span class="ashna-choice-check"><?php echo $svg_check; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
										</div>
									</div>
									<div class="ashna-choice-body">
										<strong>موبایل یا ایمیل (هر دو)</strong>
										<small>کاربر هر کدام را بنویسد، سیستم خودش تشخیص می‌دهد</small>
									</div>
								</label>

								<label class="ashna-choice-card <?php echo 'phone_only' === $settings['login_mode'] ? 'selected' : ''; ?>">
									<input type="radio" name="ashna[login_mode]" value="phone_only" <?php checked( $settings['login_mode'], 'phone_only' ); ?> />
									<div class="ashna-choice-card-top">
										<div class="ashna-flow-icons" title="فقط شماره موبایل">
											<span class="ashna-flow-node is-phone"><?php echo $svg_phone; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
										</div>
										<div class="ashna-choice-top-left">
											<span class="ashna-choice-tag is-blue">محبوب فروشگاهی</span>
											<span class="ashna-choice-check"><?php echo $svg_check; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
										</div>
									</div>
									<div class="ashna-choice-body">
										<strong>فقط شماره موبایل</strong>
										<small>بهترین گزینه برای فروشگاه‌ها و سایت‌های ایرانی</small>
									</div>
								</label>

								<label class="ashna-choice-card <?php echo 'email_only' === $settings['login_mode'] ? 'selected' : ''; ?>">
									<input type="radio" name="ashna[login_mode]" value="email_only" <?php checked( $settings['login_mode'], 'email_only' ); ?> />
									<div class="ashna-choice-card-top">
										<div class="ashna-flow-icons" title="فقط آدرس ایمیل">
											<span class="ashna-flow-node is-email"><?php echo $svg_email; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
										</div>
										<div class="ashna-choice-top-left">
											<span class="ashna-choice-check"><?php echo $svg_check; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
										</div>
									</div>
									<div class="ashna-choice-body">
										<strong>فقط آدرس ایمیل</strong>
										<small>کد تایید فقط به ایمیل کاربر فرستاده می‌شود</small>
									</div>
								</label>
							</div>
						</div>

						<!-- Top-Left Box (Col 7): 2. Mobile Delivery Strategy with Real Bale Logo & Subtle Glow -->
						<div class="ashna-card ashna-col-7" id="ashna-mobile-strategy-card" style="<?php echo 'email_only' === $settings['login_mode'] ? 'opacity:0.55;' : ''; ?>">
							<div class="ashna-card-head">
								<div class="ashna-card-head-title">
									<span class="ashna-card-icon is-green">
										<svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.1" stroke-linecap="round" stroke-linejoin="round"><polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"/></svg>
									</span>
									<div>
										<h2>۲. کد تایید چطور به موبایل برسد؟</h2>
										<p>ترکیب پیامک و پیام‌رسان بله برای کاهش هزینه و جلوگیری از قطعی</p>
									</div>
								</div>
							</div>

							<div class="ashna-choice-grid ashna-cols-2">
								<!-- 1. SMS -> Fallback Bale (Visual: [SMS Icon] <- [Real Bale Logo with Glow]) -->
								<label class="ashna-choice-card <?php echo 'sms_fallback_bale' === $settings['mobile_delivery_channel'] ? 'selected' : ''; ?>">
									<input type="radio" name="ashna[mobile_delivery_channel]" value="sms_fallback_bale" <?php checked( $settings['mobile_delivery_channel'], 'sms_fallback_bale' ); ?> />
									<div class="ashna-choice-card-top">
										<div class="ashna-flow-icons" title="اول پیامک ← بعد بله">
											<span class="ashna-flow-node is-sms"><?php echo $svg_sms; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
											<span class="ashna-flow-sep"><?php echo $svg_arrow; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
											<span class="ashna-flow-node is-bale"><?php echo $svg_bale_real; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
										</div>
										<div class="ashna-choice-top-left">
											<span class="ashna-choice-tag is-amber">ضد قطعی</span>
											<span class="ashna-choice-check"><?php echo $svg_check; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
										</div>
									</div>
									<div class="ashna-choice-body">
										<strong>اول پیامک، بعد بله</strong>
										<small>اگر پیامک به خطا خورد، خودکار در بله می‌فرستد</small>
									</div>
								</label>

								<!-- 2. Bale -> Fallback SMS (Visual: [Real Bale Logo with Glow] <- [SMS Icon]) -->
								<label class="ashna-choice-card <?php echo 'bale_fallback_sms' === $settings['mobile_delivery_channel'] ? 'selected' : ''; ?>">
									<input type="radio" name="ashna[mobile_delivery_channel]" value="bale_fallback_sms" <?php checked( $settings['mobile_delivery_channel'], 'bale_fallback_sms' ); ?> />
									<div class="ashna-choice-card-top">
										<div class="ashna-flow-icons" title="اول بله ← بعد پیامک">
											<span class="ashna-flow-node is-bale"><?php echo $svg_bale_real; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
											<span class="ashna-flow-sep"><?php echo $svg_arrow; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
											<span class="ashna-flow-node is-sms"><?php echo $svg_sms; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
										</div>
										<div class="ashna-choice-top-left">
											<span class="ashna-choice-tag is-green">کاهش هزینه</span>
											<span class="ashna-choice-check"><?php echo $svg_check; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
										</div>
									</div>
									<div class="ashna-choice-body">
										<strong>اول بله، بعد پیامک</strong>
										<small>اگر کاربر بله نداشت، فوری پیامک می‌شود</small>
									</div>
								</label>

								<!-- 3. SMS Only -->
								<label class="ashna-choice-card <?php echo 'sms' === $settings['mobile_delivery_channel'] ? 'selected' : ''; ?>">
									<input type="radio" name="ashna[mobile_delivery_channel]" value="sms" <?php checked( $settings['mobile_delivery_channel'], 'sms' ); ?> />
									<div class="ashna-choice-card-top">
										<div class="ashna-flow-icons" title="فقط پیامک">
											<span class="ashna-flow-node is-sms"><?php echo $svg_sms; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
										</div>
										<div class="ashna-choice-top-left">
											<span class="ashna-choice-check"><?php echo $svg_check; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
										</div>
									</div>
									<div class="ashna-choice-body">
										<strong>فقط پیامک</strong>
										<small>ارسال مستقیم از پنل پیامک و پشتیبان‌ها</small>
									</div>
								</label>

								<!-- 4. Bale Only -->
								<label class="ashna-choice-card <?php echo 'bale' === $settings['mobile_delivery_channel'] ? 'selected' : ''; ?>">
									<input type="radio" name="ashna[mobile_delivery_channel]" value="bale" <?php checked( $settings['mobile_delivery_channel'], 'bale' ); ?> />
									<div class="ashna-choice-card-top">
										<div class="ashna-flow-icons" title="فقط پیام‌رسان بله">
											<span class="ashna-flow-node is-bale"><?php echo $svg_bale_real; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
										</div>
										<div class="ashna-choice-top-left">
											<span class="ashna-choice-check"><?php echo $svg_check; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
										</div>
									</div>
									<div class="ashna-choice-body">
										<strong>فقط پیام‌رسان بله</strong>
										<small>ارسال کد فقط به حساب کاربری بله</small>
									</div>
								</label>

								<!-- 5. Simultaneous SMS + Bale (Full Width Span 2) -->
								<label class="ashna-choice-card ashna-span-2 <?php echo 'both' === $settings['mobile_delivery_channel'] ? 'selected' : ''; ?>">
									<input type="radio" name="ashna[mobile_delivery_channel]" value="both" <?php checked( $settings['mobile_delivery_channel'], 'both' ); ?> />
									<div class="ashna-choice-card-top">
										<div class="ashna-flow-icons" title="ارسال همزمان در پیامک و بله">
											<span class="ashna-flow-node is-sms"><?php echo $svg_sms; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
											<span class="ashna-flow-sep">+</span>
											<span class="ashna-flow-node is-bale"><?php echo $svg_bale_real; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
										</div>
										<div class="ashna-choice-top-left">
											<span class="ashna-choice-tag is-purple">تحویل فوری در هر دو</span>
											<span class="ashna-choice-check"><?php echo $svg_check; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
										</div>
									</div>
									<div class="ashna-choice-body">
										<strong>پیامک و بله به صورت همزمان</strong>
										<small>کد تایید در یک لحظه هم پیامک می‌شود و هم در پیام‌رسان بله ارسال می‌گردد تا کاربر هر کدام را زودتر دید وارد شود</small>
									</div>
								</label>
							</div>
						</div>
					</div>

					<!-- MIDDLE FULL-WIDTH CARD: Biometric & Passwordless Passkey Login (WebAuthn / FIDO2) -->
					<div class="ashna-card" style="margin-top:20px;">
						<div class="ashna-card-head">
							<div class="ashna-card-head-title">
								<div class="ashna-flow-icons" style="margin-left:4px;">
									<span class="ashna-flow-node is-passkey" title="اثر انگشت (TouchID / Fingerprint)"><?php echo $svg_fingerprint; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
									<span class="ashna-flow-sep">+</span>
									<span class="ashna-flow-node is-phone" title="تشخیص چهره (FaceID / Windows Hello)"><?php echo $svg_faceid; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
								</div>
								<div>
									<h2>۳. ورود بیومتریک و بدون رمز (Passkey / WebAuthn)</h2>
									<p>ورود ۱ ثانیه‌ای کاربران با اثر انگشت (TouchID)، تشخیص چهره (FaceID) و Windows Hello بدون نیاز به صبر کردن برای پیامک</p>
								</div>
							</div>
							<span class="ashna-pill is-ok">فناوری نسل جدید WebAuthn</span>
						</div>

						<div class="ashna-fields-grid ashna-cols-3">
							<div class="ashna-switch-row" style="margin:0;">
								<div class="ashna-switch-text">
									<strong>فعال‌سازی ورود با Passkey</strong>
									<p>نمایش دکمه «ورود با اثر انگشت / چهره» در فرم ورود</p>
								</div>
								<label class="ashna-switch">
									<input type="checkbox" name="ashna[enable_passkey]" id="enable_passkey" value="1" <?php checked( ! empty( $settings['enable_passkey'] ), true ); ?> />
									<span class="ashna-slider"></span>
								</label>
							</div>

							<div class="ashna-switch-row" style="margin:0;">
								<div class="ashna-switch-text">
									<strong>پیشنهاد ثبت پس از ورود با کد</strong>
									<p>بعد از تایید کد، به کاربر پیشنهاد شود دستگاهش را بیومتریک کند</p>
								</div>
								<label class="ashna-switch">
									<input type="checkbox" name="ashna[passkey_prompt_after_otp]" value="1" <?php checked( ! empty( $settings['passkey_prompt_after_otp'] ), true ); ?> />
									<span class="ashna-slider"></span>
								</label>
							</div>

							<div class="ashna-switch-row" style="margin:0;">
								<div class="ashna-switch-text">
									<strong>مدیریت در حساب ووکامرس</strong>
									<p>نمایش لیست دستگاه‌های بیومتریک در پیشخوان حساب کاربری</p>
								</div>
								<label class="ashna-switch">
									<input type="checkbox" name="ashna[passkey_wc_myaccount]" value="1" <?php checked( ! empty( $settings['passkey_wc_myaccount'] ), true ); ?> />
									<span class="ashna-slider"></span>
								</label>
							</div>
						</div>

						<div id="ashna-passkey-extra-wrap" style="margin-top:14px; <?php echo ! empty( $settings['enable_passkey'] ) ? '' : 'display:none;'; ?>">
							<div class="ashna-fields-grid ashna-cols-2">
								<div class="ashna-field">
									<label for="passkey_btn_text">متن دکمه ورود بیومتریک در فرم</label>
									<input type="text" name="ashna[passkey_btn_text]" id="passkey_btn_text" value="<?php echo esc_attr( isset( $settings['passkey_btn_text'] ) ? $settings['passkey_btn_text'] : 'ورود سریع با اثر انگشت / چهره (Passkey)' ); ?>" />
								</div>
								<div class="ashna-field">
									<label>شورت‌کد مدیریت دستگاه‌های بیومتریک کاربر</label>
									<div style="display:flex;gap:8px;align-items:center;">
										<input type="text" value="[ashna_passkey_manager]" dir="ltr" readonly style="flex:1;" />
										<button type="button" class="ashna-btn-secondary ashna-copy-btn" data-copy="[ashna_passkey_manager]">کپی شورت‌کد</button>
									</div>
								</div>
							</div>
						</div>
					</div>

					<!-- BOTTOM BENTO ROW: OTP Code Timing & Registration (Col 6) + Redirect & Password Options (Col 6) -->
					<div class="ashna-bento-row" style="margin-top:20px;">
						<!-- Left Box: Code Settings & New User Signup -->
						<div class="ashna-card ashna-col-6">
							<div class="ashna-card-head">
								<div class="ashna-card-head-title">
									<span class="ashna-card-icon is-purple">
										<svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.1" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
									</span>
									<div>
										<h2>۴. تنظیمات کد و عضویت کاربران جدید</h2>
										<p>تعداد ارقام کد، زمان اعتبار و اطلاعات دریافتی هنگام ثبت‌نام</p>
									</div>
								</div>
							</div>

							<div class="ashna-fields-grid ashna-cols-3">
								<div class="ashna-field">
									<label for="otp_length">تعداد ارقام کد</label>
									<select name="ashna[otp_length]" id="otp_length">
										<?php for ( $i = 4; $i <= 6; $i++ ) : ?>
											<option value="<?php echo esc_attr( (string) $i ); ?>" <?php selected( $settings['otp_length'], $i ); ?>>
												<?php echo esc_html( (string) $i ); ?> رقمی <?php echo 5 === $i ? '(پیشنهادی)' : ''; ?>
											</option>
										<?php endfor; ?>
									</select>
								</div>

								<div class="ashna-field">
									<label for="otp_expiry">اعتبار کد (ثانیه)</label>
									<input type="text" inputmode="numeric" name="ashna[otp_expiry]" id="otp_expiry" value="<?php echo esc_attr( (string) $settings['otp_expiry'] ); ?>" dir="ltr" />
								</div>

								<div class="ashna-field">
									<label for="resend_cooldown">فاصله ارسال مجدد (ثانیه)</label>
									<input type="text" inputmode="numeric" name="ashna[resend_cooldown]" id="resend_cooldown" value="<?php echo esc_attr( (string) $settings['resend_cooldown'] ); ?>" dir="ltr" />
								</div>
							</div>

							<div class="ashna-switch-row" style="margin-top:16px;">
								<div class="ashna-switch-text">
									<strong>عضویت خودکار کاربران جدید</strong>
									<p>اگر شماره یا ایمیل کاربر در سایت نبود، همان لحظه حسابش ساخته شود.</p>
								</div>
								<label class="ashna-switch">
									<input type="checkbox" name="ashna[auto_register]" id="auto_register" value="1" <?php checked( $settings['auto_register'], 1 ); ?> />
									<span class="ashna-slider"></span>
								</label>
							</div>

							<div id="ashna-auto-register-fields" style="margin-top:16px; <?php echo empty( $settings['auto_register'] ) ? 'display:none;' : ''; ?>">
								<div class="ashna-fields-grid ashna-cols-3">
									<div class="ashna-field">
										<label for="default_user_role">نقش کاربر جدید</label>
										<select name="ashna[default_user_role]" id="default_user_role">
											<option value="customer" <?php selected( $settings['default_user_role'], 'customer' ); ?>>مشتری ووکامرس</option>
											<option value="subscriber" <?php selected( $settings['default_user_role'], 'subscriber' ); ?>>مشترک وردپرس</option>
										</select>
									</div>

									<div class="ashna-field">
										<label for="require_name_on_register">دریافت نام در ثبت‌نام</label>
										<select name="ashna[require_name_on_register]" id="require_name_on_register">
											<option value="disabled" <?php selected( $settings['require_name_on_register'], 'disabled' ); ?>>نپرس (سریع‌ترین ورود)</option>
											<option value="optional" <?php selected( $settings['require_name_on_register'], 'optional' ); ?>>اختیاری بپرس</option>
											<option value="required" <?php selected( $settings['require_name_on_register'], 'required' ); ?>>اجباری باشد</option>
										</select>
									</div>

									<div class="ashna-field">
										<label for="require_email_on_register">دریافت ایمیل از کاربر موبایل</label>
										<select name="ashna[require_email_on_register]" id="require_email_on_register">
											<option value="disabled" <?php selected( $settings['require_email_on_register'], 'disabled' ); ?>>نپرس</option>
											<option value="optional" <?php selected( $settings['require_email_on_register'], 'optional' ); ?>>اختیاری بپرس</option>
											<option value="required" <?php selected( $settings['require_email_on_register'], 'required' ); ?>>اجباری باشد</option>
										</select>
									</div>
								</div>
							</div>
						</div>

						<!-- Right Box: Redirect Cards, Password Option & Terms -->
						<div class="ashna-card ashna-col-6">
							<div class="ashna-card-head">
								<div class="ashna-card-head-title">
									<span class="ashna-card-icon is-amber">
										<svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.1" stroke-linecap="round" stroke-linejoin="round"><path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4"/><polyline points="10 17 15 12 10 7"/><line x1="15" y1="12" x2="3" y2="12"/></svg>
									</span>
									<div>
										<h2>۵. پس از ورود، کاربر به کجا برود؟</h2>
										<p>مسیر انتقال کاربر بعد از ورود و گزینه‌های کمکی فرم</p>
									</div>
								</div>
							</div>

							<!-- Visual Redirect Behavior Cards -->
							<div class="ashna-choice-grid ashna-cols-2">
								<label class="ashna-choice-card <?php echo 'auto' === $settings['redirect_behavior'] ? 'selected' : ''; ?>">
									<input type="radio" name="ashna[redirect_behavior]" value="auto" <?php checked( $settings['redirect_behavior'], 'auto' ); ?> />
									<div class="ashna-choice-card-top">
										<span class="ashna-flow-node is-phone">
											<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><polyline points="1 4 1 10 7 10"/><path d="M3.51 15a9 9 0 1 0 2.13-9.36L1 10"/></svg>
										</span>
										<span class="ashna-choice-check"><?php echo $svg_check; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
									</div>
									<div class="ashna-choice-body">
										<strong>همان صفحه فعلی</strong>
										<small>برگشت به صفحه‌ای که کاربر در آن بود</small>
									</div>
								</label>

								<label class="ashna-choice-card <?php echo 'my_account' === $settings['redirect_behavior'] ? 'selected' : ''; ?>">
									<input type="radio" name="ashna[redirect_behavior]" value="my_account" <?php checked( $settings['redirect_behavior'], 'my_account' ); ?> />
									<div class="ashna-choice-card-top">
										<span class="ashna-flow-node is-sms">
											<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
										</span>
										<span class="ashna-choice-check"><?php echo $svg_check; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
									</div>
									<div class="ashna-choice-body">
										<strong>حساب کاربری من</strong>
										<small>انتقال به پنل کاربری ووکامرس یا وردپرس</small>
									</div>
								</label>

								<label class="ashna-choice-card <?php echo 'home' === $settings['redirect_behavior'] ? 'selected' : ''; ?>">
									<input type="radio" name="ashna[redirect_behavior]" value="home" <?php checked( $settings['redirect_behavior'], 'home' ); ?> />
									<div class="ashna-choice-card-top">
										<span class="ashna-flow-node is-passkey">
											<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path d="m3 9 9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>
										</span>
										<span class="ashna-choice-check"><?php echo $svg_check; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
									</div>
									<div class="ashna-choice-body">
										<strong>صفحه اصلی سایت</strong>
										<small>انتقال به صفحه اول وب‌سایت</small>
									</div>
								</label>

								<label class="ashna-choice-card <?php echo 'custom' === $settings['redirect_behavior'] ? 'selected' : ''; ?>">
									<input type="radio" name="ashna[redirect_behavior]" value="custom" <?php checked( $settings['redirect_behavior'], 'custom' ); ?> />
									<div class="ashna-choice-card-top">
										<span class="ashna-flow-node is-email">
											<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71"/><path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71"/></svg>
										</span>
										<span class="ashna-choice-check"><?php echo $svg_check; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
									</div>
									<div class="ashna-choice-body">
										<strong>آدرس دلخواه شما</strong>
										<small>انتقال به یک لینک مشخص (مثلاً داشبورد)</small>
									</div>
								</label>
							</div>

							<!-- Progressive disclosure: only show Custom Redirect URL when 'custom' is selected -->
							<?php
							$custom_redir_val = ! empty( $settings['custom_redirect_url'] ) ? $settings['custom_redirect_url'] : ( isset( $settings['redirect_url'] ) ? $settings['redirect_url'] : '' );
							?>
							<div class="ashna-field" id="ashna-custom-redirect-wrap" style="margin-top:14px; <?php echo 'custom' === $settings['redirect_behavior'] ? '' : 'display:none;'; ?>">
								<label for="custom_redirect_url">آدرس لینک مقصد دلخواه</label>
								<input type="text" name="ashna[custom_redirect_url]" id="custom_redirect_url" value="<?php echo esc_attr( $custom_redir_val ); ?>" dir="ltr" placeholder="https://yoursite.com/dashboard" />
							</div>

							<div style="display:flex;flex-direction:column;gap:12px;margin-top:16px;">
								<div class="ashna-switch-row">
									<div class="ashna-switch-text">
										<strong>امکان ورود با رمز عبور ثابت</strong>
										<p>زیر دکمه دریافت کد، لینک «ورود با رمز عبور» هم نمایش داده شود.</p>
									</div>
									<label class="ashna-switch">
										<input type="checkbox" name="ashna[allow_password_login]" value="1" <?php checked( $settings['allow_password_login'], 1 ); ?> />
										<span class="ashna-slider"></span>
									</label>
								</div>

								<div class="ashna-switch-row">
									<div class="ashna-switch-text">
										<strong>نمایش جمله پذیرش قوانین سایت</strong>
										<p>نمایش متن قوانین و مقررات در پایین فرم ورود.</p>
									</div>
									<label class="ashna-switch">
										<input type="checkbox" name="ashna[show_terms_checkbox]" id="show_terms_checkbox" value="1" <?php checked( $settings['show_terms_checkbox'], 1 ); ?> />
										<span class="ashna-slider"></span>
									</label>
								</div>
							</div>

							<!-- Progressive disclosure: only show Terms fields when switch is enabled -->
							<div class="ashna-fields-grid ashna-cols-2" id="ashna-terms-fields-wrap" style="margin-top:14px; <?php echo ! empty( $settings['show_terms_checkbox'] ) ? '' : 'display:none;'; ?>">
								<div class="ashna-field">
									<label for="terms_text">متن پذیرش قوانین</label>
									<input type="text" name="ashna[terms_text]" id="terms_text" value="<?php echo esc_attr( $settings['terms_text'] ); ?>" />
								</div>
								<div class="ashna-field">
									<label for="terms_url">لینک برگه قوانین (اختیاری)</label>
									<input type="text" name="ashna[terms_url]" id="terms_url" value="<?php echo esc_attr( $settings['terms_url'] ); ?>" dir="ltr" placeholder="https://example.com/terms" />
								</div>
							</div>
						</div>
					</div>
				</section>
