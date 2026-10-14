<?php
/**
 * Admin Settings Partial: tab-bale-email.php (v2.5.0 Creative Progressive Disclosure + Human Copy)
 *
 * @package Ashna_OTP
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$bale_mode = isset( $settings['bale_mode'] ) ? $settings['bale_mode'] : 'safir';
?>
				<section class="ashna-panel" id="ashna-tab-bale_email">
					<div class="ashna-bento-row">
						<!-- Box 1: Bale Messenger (Safir OTP vs Bot API with Progressive Disclosure) -->
						<div class="ashna-card ashna-col-6">
							<div class="ashna-card-head">
								<div class="ashna-card-head-title">
									<span class="ashna-flow-node is-bale" style="width:44px;height:44px;border-radius:12px;">
										<?php echo Ashna_Helper::get_bale_logo_svg( 26 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
									</span>
									<div>
										<h2>ارسال کد در پیام‌رسان بله</h2>
										<p>کاهش چشمگیر هزینه پیامک با ارسال کد تایید در حساب کاربری بله</p>
									</div>
								</div>
								<span class="ashna-pill is-ok">تعرفه اقتصادی</span>
							</div>

							<label class="ashna-section-label">روش اتصال به پیام‌رسان بله را انتخاب کنید</label>
							<div class="ashna-choice-grid ashna-cols-2">
								<label class="ashna-choice-card <?php echo 'safir' === $bale_mode ? 'selected' : ''; ?>" style="display:flex;flex-direction:column;align-items:stretch;gap:14px;padding:18px 20px;">
									<input type="radio" name="ashna[bale_mode]" value="safir" <?php checked( $bale_mode, 'safir' ); ?> />
									<div class="ashna-choice-card-top" style="display:flex;align-items:center;justify-content:space-between;width:100%;">
										<div class="ashna-flow-icons" style="display:inline-flex;align-items:center;gap:8px;padding:7px 11px;border-radius:14px;">
											<span class="ashna-flow-node is-bale" title="سفیر بله" style="width:40px;height:40px;padding:8px;box-sizing:border-box;">
												<?php echo Ashna_Helper::get_bale_logo_svg( 24 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
											</span>
											<span class="ashna-flow-sep">
												<svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><line x1="19" y1="12" x2="5" y2="12"/><polyline points="12 19 5 12 12 5"/></svg>
											</span>
											<span class="ashna-flow-node is-phone" title="شماره موبایل" style="width:40px;height:40px;padding:8px;box-sizing:border-box;">
												<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><rect x="5" y="2" width="14" height="20" rx="2" ry="2"/><line x1="12" y1="18" x2="12.01" y2="18"/></svg>
											</span>
										</div>
										<div class="ashna-choice-top-left" style="display:inline-flex;align-items:center;gap:8px;">
											<span class="ashna-choice-tag is-green">پیشنهادی</span>
											<span class="ashna-choice-check"><svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg></span>
										</div>
									</div>
									<div class="ashna-choice-body" style="display:flex;flex-direction:column;align-items:flex-start;gap:5px;width:100%;">
										<strong style="display:block;width:100%;font-size:14.5px;line-height:1.45;">سفیر بله (Safir OTP)</strong>
										<small style="display:block;width:100%;font-size:12.5px;line-height:1.65;">ارسال مستقیم کد تایید به شماره موبایل کاربر در بله</small>
									</div>
								</label>

								<label class="ashna-choice-card <?php echo 'bot' === $bale_mode ? 'selected' : ''; ?>" style="display:flex;flex-direction:column;align-items:stretch;gap:14px;padding:18px 20px;">
									<input type="radio" name="ashna[bale_mode]" value="bot" <?php checked( $bale_mode, 'bot' ); ?> />
									<div class="ashna-choice-card-top" style="display:flex;align-items:center;justify-content:space-between;width:100%;">
										<div class="ashna-flow-icons" style="display:inline-flex;align-items:center;gap:8px;padding:7px 11px;border-radius:14px;">
											<span class="ashna-flow-node is-bale" title="پیام‌رسان بله" style="width:40px;height:40px;padding:8px;box-sizing:border-box;">
												<?php echo Ashna_Helper::get_bale_logo_svg( 24 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
											</span>
											<span class="ashna-flow-sep">+</span>
											<span class="ashna-flow-node is-sms" title="ربات بله" style="width:40px;height:40px;padding:8px;box-sizing:border-box;">
												<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="11" width="18" height="10" rx="2"/><circle cx="12" cy="5" r="2"/><path d="M12 7v4"/><line x1="8" y1="16" x2="8.01" y2="16"/><line x1="16" y1="16" x2="16.01" y2="16"/></svg>
											</span>
										</div>
										<div class="ashna-choice-top-left" style="display:inline-flex;align-items:center;gap:8px;">
											<span class="ashna-choice-tag is-blue">بازوی اختصاصی</span>
											<span class="ashna-choice-check"><svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg></span>
										</div>
									</div>
									<div class="ashna-choice-body" style="display:flex;flex-direction:column;align-items:flex-start;gap:5px;width:100%;">
										<strong style="display:block;width:100%;font-size:14.5px;line-height:1.45;">ربات بله (Bot API)</strong>
										<small style="display:block;width:100%;font-size:12.5px;line-height:1.65;">ارسال پیام از طریق توکن بازوی اختصاصی شما در بله</small>
									</div>
								</label>
							</div>

							<!-- Safir Mode Fields -->
							<div id="ashna-bale-safir-box" style="margin-top:18px; <?php echo 'safir' === $bale_mode ? '' : 'display:none;'; ?>">
								<div class="ashna-fields-grid ashna-cols-2">
									<div class="ashna-field">
										<label for="bale_client_id">شناسه کاربری سفیر (Client ID)</label>
										<input type="text" name="ashna[bale_client_id]" id="bale_client_id" value="<?php echo esc_attr( $settings['bale_client_id'] ); ?>" dir="ltr" placeholder="Client ID" />
									</div>
									<div class="ashna-field">
										<label for="bale_client_secret">رمز عبور سفیر (Client Secret)</label>
										<input type="password" name="ashna[bale_client_secret]" id="bale_client_secret" value="<?php echo esc_attr( $settings['bale_client_secret'] ); ?>" dir="ltr" placeholder="Client Secret" />
									</div>
								</div>
								<div class="ashna-info-note" style="margin-top:14px;">
									<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#10b981" stroke-width="2" style="flex-shrink:0;"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
									<span>در سرویس رسمی <code>safir.bale.ai</code> نیازی به قالب متن نیست؛ کد تایید با فرمت استاندارد بله مستقیماً به شماره موبایل کاربر ارسال می‌شود.</span>
								</div>
							</div>

							<!-- Bot Mode Fields -->
							<div id="ashna-bale-bot-box" style="margin-top:18px; <?php echo 'bot' === $bale_mode ? '' : 'display:none;'; ?>">
								<div class="ashna-field">
									<label for="bale_bot_token">توکن بازوی بله (Bot Token)</label>
									<input type="text" name="ashna[bale_bot_token]" id="bale_bot_token" value="<?php echo esc_attr( $settings['bale_bot_token'] ); ?>" dir="ltr" placeholder="123456789:ABCDEF..." />
								</div>

								<div class="ashna-field" style="margin-top:14px;">
									<label for="bale_message_template">متن پیام ارسالی ربات (متغیرها: <code>{code}</code>، <code>{site_name}</code>، <code>{expiry}</code>)</label>
									<textarea name="ashna[bale_message_template]" id="bale_message_template" rows="3"><?php echo esc_textarea( $settings['bale_message_template'] ); ?></textarea>
								</div>
							</div>
						</div>

						<!-- Box 2: Email OTP Configuration -->
						<div class="ashna-card ashna-col-6">
							<div class="ashna-card-head">
								<div class="ashna-card-head-title">
									<span class="ashna-card-icon is-blue">
										<svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.1" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="4" width="20" height="16" rx="2"/><path d="m22 7-8.97 5.7a1.94 1.94 0 0 1-2.06 0L2 7"/></svg>
									</span>
									<div>
										<h2>ارسال کد تایید به ایمیل</h2>
										<p>قالب گرافیکی ایمیل برای کاربرانی که با آدرس ایمیل وارد می‌شوند</p>
									</div>
								</div>
								<span class="ashna-pill is-info">رایگان و فوری</span>
							</div>

							<div class="ashna-fields-grid ashna-cols-2">
								<div class="ashna-field">
									<label for="email_from_name">نام فرستنده</label>
									<input type="text" name="ashna[email_from_name]" id="email_from_name" value="<?php echo esc_attr( $settings['email_from_name'] ); ?>" placeholder="مثلاً: فروشگاه شما" />
								</div>
								<div class="ashna-field">
									<label for="email_from_address">ایمیل فرستنده</label>
									<input type="text" name="ashna[email_from_address]" id="email_from_address" value="<?php echo esc_attr( $settings['email_from_address'] ); ?>" dir="ltr" placeholder="noreply@yoursite.com" />
								</div>
								<div class="ashna-field">
									<label for="email_subject">عنوان ایمیل</label>
									<input type="text" name="ashna[email_subject]" id="email_subject" value="<?php echo esc_attr( $settings['email_subject'] ); ?>" />
								</div>
								<div class="ashna-field">
									<label for="email_heading">تیتر خوش‌آمدگویی داخل ایمیل</label>
									<input type="text" name="ashna[email_heading]" id="email_heading" value="<?php echo esc_attr( $settings['email_heading'] ); ?>" />
								</div>
							</div>

							<div class="ashna-field" style="margin-top:14px;">
								<label for="email_body_text">متن پیام ایمیل (متغیرها: <code>{site_name}</code>، <code>{code}</code>، <code>{expiry}</code>)</label>
								<textarea name="ashna[email_body_text]" id="email_body_text" rows="3"><?php echo esc_textarea( $settings['email_body_text'] ); ?></textarea>
							</div>

							<div class="ashna-info-note">
								<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#3b82f6" stroke-width="2" style="flex-shrink:0;"><circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg>
								<span>رنگ دکمه و کادر کد در ایمیل، خودکار با رنگ برند شما در تب «طراحی ظاهر» هماهنگ می‌شود.</span>
							</div>
						</div>
					</div>
				</section>
