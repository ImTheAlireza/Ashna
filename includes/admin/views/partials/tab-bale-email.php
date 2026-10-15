<?php
/**
 * Admin Settings Partial: tab-bale-email.php (v2.5.0 Creative Progressive Disclosure + Human Copy)
 *
 * @package Ashna
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

							<!-- Safir v3 Fields -->
							<div id="ashna-bale-safir-box" style="margin-top:18px; <?php echo 'safir' === $bale_mode ? '' : 'display:none;'; ?>">
								<?php if ( empty( $settings['bale_api_key'] ) && ( ! empty( $settings['bale_client_id'] ) || in_array( $settings['mobile_delivery_channel'], array( 'bale', 'bale_fallback_sms', 'sms_fallback_bale', 'both' ), true ) ) ) : ?>
									<div class="ashna-info-note is-warning" style="margin:0 0 14px;">
										<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#f59e0b" stroke-width="2" style="flex-shrink:0;"><path d="M10.29 3.86 1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
										<span>برای ارسال با سفیر نسخه‌ی ۳، کلید دسترسی (api-access-key) و شناسه بازو را از پنل کسب‌وکار بله وارد کنید. Client ID و Client Secret نسخه‌ی قبلی سفیر دیگر کار نمی‌کنند.</span>
									</div>
								<?php endif; ?>
								<div class="ashna-fields-grid ashna-cols-2">
									<div class="ashna-field">
										<label for="bale_api_key">کلید دسترسی سفیر (api-access-key)</label>
										<input type="text" name="ashna[bale_api_key]" id="bale_api_key" value="<?php echo esc_attr( $settings['bale_api_key'] ); ?>" dir="ltr" placeholder="api-access-key" />
									</div>
									<div class="ashna-field">
										<label for="bale_bot_id">شناسه عددی بازو (bot_id)</label>
										<input type="text" inputmode="numeric" name="ashna[bale_bot_id]" id="bale_bot_id" value="<?php echo esc_attr( $settings['bale_bot_id'] ); ?>" dir="ltr" placeholder="123456789" />
									</div>
								</div>
								<div class="ashna-info-note" style="margin-top:14px;">
									<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#10b981" stroke-width="2" style="flex-shrink:0;"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
									<span>سفیر نسخه‌ی ۳: هر کد فقط با یک درخواست ارسال می‌شود. کلید و شناسه‌ی بازو را بعد از ساخت سفیر در <code>business.bale.ai</code> می‌گیرید. کد از طرف بازوی رسمی «رمز یکبارمصرف» بله، با نام بازوی شما و دکمه‌ی کپی ارسال می‌شود.</span>
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

					<?php $msgway_mode = isset( $settings['msgway_messenger_mode'] ) ? $settings['msgway_messenger_mode'] : 'smart'; ?>
					<!-- Rahpayam (MsgWay): Iranian messengers + SMS through one API -->
					<div class="ashna-card" id="ashna-msgway-card" style="margin-top:20px;">
						<div class="ashna-card-head">
							<div class="ashna-card-head-title">
								<span class="ashna-card-icon is-green">
									<svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.1" stroke-linecap="round" stroke-linejoin="round"><path d="M21 11.5a8.38 8.38 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.38 8.38 0 0 1-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 0 1-.9-3.8 8.5 8.5 0 0 1 4.7-7.6 8.38 8.38 0 0 1 3.8-.9h.5a8.48 8.48 0 0 1 8 8v.5z"/></svg>
								</span>
								<div>
									<h2>پیام‌رسان‌های ایرانی با راه‌پیام</h2>
									<p>ارسال کد در ایتا، روبیکا، بله، گپ و آی‌گپ با یک کلید؛ پیامک راه‌پیام هم به‌عنوان درگاه پیامک قابل انتخاب است</p>
								</div>
							</div>
							<span class="ashna-pill is-ok">یک API، پنج پیام‌رسان</span>
						</div>

						<div class="ashna-fields-grid ashna-cols-3">
							<div class="ashna-field">
								<label for="msgway_api_key">کلید API راه‌پیام (apiKey)</label>
								<input type="text" name="ashna[msgway_api_key]" id="msgway_api_key" value="<?php echo esc_attr( $settings['msgway_api_key'] ); ?>" dir="ltr" placeholder="msgway.com ← پروژه‌ها" />
							</div>
							<div class="ashna-field">
								<label for="msgway_messenger_mode">مسیر ارسال در پیام‌رسان‌ها</label>
								<select name="ashna[msgway_messenger_mode]" id="msgway_messenger_mode">
									<option value="smart" <?php selected( $msgway_mode, 'smart' ); ?>>ارسال هوشمند راه‌پیام (پیشنهادی)</option>
									<option value="gap" <?php selected( $msgway_mode, 'gap' ); ?>>فقط پیام‌رسان گپ</option>
									<option value="igap" <?php selected( $msgway_mode, 'igap' ); ?>>فقط پیام‌رسان آی‌گپ</option>
									<option value="custom" <?php selected( $msgway_mode, 'custom' ); ?>>پیام‌رسان دیگر (ایتا، بله، روبیکا) با شناسه</option>
								</select>
								<small>در حالت هوشمند، راه‌پیام از بین پیام‌رسان‌هایی که در پنلش فعال کرده‌اید مسیر را انتخاب می‌کند.</small>
							</div>
							<div class="ashna-field">
								<label for="msgway_messenger_template_id">شناسه الگوی پیام‌رسان (templateID)</label>
								<input type="text" inputmode="numeric" name="ashna[msgway_messenger_template_id]" id="msgway_messenger_template_id" value="<?php echo esc_attr( $settings['msgway_messenger_template_id'] ); ?>" dir="ltr" placeholder="مثلاً 10" />
							</div>
							<div class="ashna-field" id="msgway-provider-wrap" style="<?php echo 'custom' === $msgway_mode ? '' : 'display:none;'; ?>">
								<label for="msgway_messenger_provider">شناسه پیام‌رسان در راه‌پیام (provider)</label>
								<input type="text" inputmode="numeric" name="ashna[msgway_messenger_provider]" id="msgway_messenger_provider" value="<?php echo esc_attr( (string) $settings['msgway_messenger_provider'] ); ?>" dir="ltr" placeholder="از مستندات پنل راه‌پیام" />
							</div>
							<div class="ashna-field">
								<label for="msgway_sms_template_id">شناسه الگوی پیامک راه‌پیام</label>
								<input type="text" inputmode="numeric" name="ashna[msgway_sms_template_id]" id="msgway_sms_template_id" value="<?php echo esc_attr( $settings['msgway_sms_template_id'] ); ?>" dir="ltr" placeholder="مثلاً 3 (الگوی پیش‌فرض کد تایید)" />
								<small>فقط وقتی لازم است که راه‌پیام را به‌عنوان درگاه پیامک هم انتخاب کنید.</small>
							</div>
							<div class="ashna-field">
								<label for="msgway_sms_line">خط ارسال پیامک راه‌پیام</label>
								<select name="ashna[msgway_sms_line]" id="msgway_sms_line">
									<?php
									$msgway_lines = array(
										0 => 'پیش‌فرض راه‌پیام',
										1 => 'سرشماره 3000',
										2 => 'سرشماره 2000',
										3 => 'سرشماره 9000',
										4 => 'سرشماره 5000',
										5 => 'سرشماره 50004',
									);
									foreach ( $msgway_lines as $line_id => $line_label ) :
										?>
										<option value="<?php echo esc_attr( (string) $line_id ); ?>" <?php selected( (int) $settings['msgway_sms_line'], $line_id ); ?>><?php echo esc_html( $line_label ); ?></option>
									<?php endforeach; ?>
								</select>
							</div>
						</div>

						<div class="ashna-info-note" style="margin-top:16px;">
							<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#3b82f6" stroke-width="2" style="flex-shrink:0;"><circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg>
							<span>برای استفاده، در تب «روش و مسیر ورود» یکی از گزینه‌های «پیام‌رسان‌های ایرانی» را انتخاب کنید. الگوها باید در پنل راه‌پیام تأیید شده باشند. کد تایید را خود آشنا می‌سازد و بررسی می‌کند.</span>
						</div>
					</div>
				</section>
