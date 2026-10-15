<?php
/**
 * Admin Settings Partial: tab-sms-gateways.php
 *
 * @package Ashna
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
				<section class="ashna-panel" id="ashna-tab-sms_gateways">
					<?php
					$gw_logos = array(
						'sandbox'     => '<span class="ashna-gw-logo is-sandbox"><svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 3h6m-5 0v5.172a2 2 0 0 1-.586 1.414l-4.828 4.828A2 2 0 0 0 4 15.828V19a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-3.172a2 2 0 0 0-.586-1.414l-4.828-4.828A2 2 0 0 1 14 8.172V3"/></svg></span>',
						'smsir'       => '<span class="ashna-gw-logo is-smsir"><svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2.2"><path d="M21 11.5a8.38 8.38 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.38 8.38 0 0 1-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 0 1-.9-3.8 8.5 8.5 0 0 1 4.7-7.6 8.38 8.38 0 0 1 3.8-.9h.5a8.48 8.48 0 0 1 8 8v.5z"/><path d="M8 11h8m-8 4h5"/></svg></span>',
						'kavenegar'   => '<span class="ashna-gw-logo is-kavenegar"><svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2.2"><path d="m22 2-7 20-4-9-9-4Z"/><path d="M22 2 11 13"/></svg></span>',
						'melipayamak' => '<span class="ashna-gw-logo is-melipayamak"><svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2.2"><rect x="2" y="4" width="20" height="16" rx="3"/><path d="m22 7-8.97 5.7a1.94 1.94 0 0 1-2.06 0L2 7"/></svg></span>',
						'farazsms'    => '<span class="ashna-gw-logo is-farazsms"><svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2.2"><path d="M13 2 3 14h9l-1 8 10-12h-9l1-8z"/></svg></span>',
						'ippanel'     => '<span class="ashna-gw-logo is-ippanel"><svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2.2"><circle cx="12" cy="12" r="10"/><path d="M2 12h20M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"/></svg></span>',
					);
					$b1_selected = ! empty( $settings['backup_sms_gateway_1'] ) && 'none' !== $settings['backup_sms_gateway_1']
						? $settings['backup_sms_gateway_1']
						: $settings['backup_sms_gateway'];
					?>
					<div class="ashna-card">
						<div class="ashna-card-head">
							<div class="ashna-card-head-title">
								<span class="ashna-card-icon is-blue">
									<svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.1" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12.55a11 11 0 0 1 14.08 0"/><path d="M1.42 9a16 16 0 0 1 21.16 0"/><path d="M8.53 16.11a6 6 0 0 1 6.95 0"/><line x1="12" y1="20" x2="12.01" y2="20"/></svg>
								</span>
								<div>
									<h2>کدام سامانه پیامک را دارید؟</h2>
									<p>درگاه اصلی خود را انتخاب کنید و در صورت تمایل تا ۳ درگاه پشتیبان برای مواقع قطعی تعیین نمایید</p>
								</div>
							</div>
						</div>

						<!-- Primary SMS Gateway Visual Selector Cards with Logos -->
						<label class="ashna-section-label">۱. سامانه پیامک اصلی شما</label>
						<input type="hidden" name="ashna[active_sms_gateway]" id="active_sms_gateway" value="<?php echo esc_attr( $settings['active_sms_gateway'] ); ?>" />
						<div class="ashna-gw-selector-grid">
							<?php foreach ( $sms_gateways as $gw_id => $gw_obj ) : ?>
								<button type="button" class="ashna-gw-select-card <?php echo $settings['active_sms_gateway'] === $gw_id ? 'selected' : ''; ?>" data-gw-id="<?php echo esc_attr( $gw_id ); ?>" data-gw-title="<?php echo esc_attr( $gw_obj->get_title() ); ?>">
									<?php
									// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
									echo isset( $gw_logos[ $gw_id ] ) ? $gw_logos[ $gw_id ] : '';
									?>
									<span class="ashna-gw-card-title"><?php echo esc_html( $gw_obj->get_title() ); ?></span>
									<span class="ashna-gw-check-badge">فعال</span>
								</button>
							<?php endforeach; ?>
						</div>

						<!-- 3 Prioritized Backup Gateways (Visual Failover Pipeline) -->
						<label class="ashna-section-label" style="margin-top:24px;">۲. زنجیره نجات پیامک (اگر درگاه اصلی قطع بود، به ترتیب از کدام بفرستد؟)</label>
						<div class="ashna-failover-pipeline">
							<div class="ashna-pipeline-step">
								<div class="ashna-pipeline-step-head">
									<span class="ashna-step-num-badge">
										<span class="ashna-step-num-circle">۱</span>
										<span>پشتیبان اول</span>
									</span>
									<span class="ashna-pill is-info">اولویت اول</span>
								</div>
								<select name="ashna[backup_sms_gateway_1]" id="backup_sms_gateway_1">
									<option value="none" <?php selected( $b1_selected, 'none' ); ?>>غیرفعال (ندارم)</option>
									<?php foreach ( $sms_gateways as $gw_id => $gw_obj ) : ?>
										<?php if ( 'sandbox' !== $gw_id ) : ?>
											<option value="<?php echo esc_attr( $gw_id ); ?>" <?php selected( $b1_selected, $gw_id ); ?>>
												<?php echo esc_html( $gw_obj->get_title() ); ?>
											</option>
										<?php endif; ?>
									<?php endforeach; ?>
								</select>
								<small>به محض خطای درگاه اصلی، از این پنل ارسال می‌شود.</small>
							</div>

							<div class="ashna-pipeline-step">
								<div class="ashna-pipeline-step-head">
									<span class="ashna-step-num-badge">
										<span class="ashna-step-num-circle">۲</span>
										<span>پشتیبان دوم</span>
									</span>
									<span class="ashna-pill is-muted">اولویت دوم</span>
								</div>
								<select name="ashna[backup_sms_gateway_2]" id="backup_sms_gateway_2">
									<option value="none" <?php selected( $settings['backup_sms_gateway_2'], 'none' ); ?>>غیرفعال (ندارم)</option>
									<?php foreach ( $sms_gateways as $gw_id => $gw_obj ) : ?>
										<?php if ( 'sandbox' !== $gw_id ) : ?>
											<option value="<?php echo esc_attr( $gw_id ); ?>" <?php selected( $settings['backup_sms_gateway_2'], $gw_id ); ?>>
												<?php echo esc_html( $gw_obj->get_title() ); ?>
											</option>
										<?php endif; ?>
									<?php endforeach; ?>
								</select>
								<small>اگر پشتیبان اول هم خطا داد، سراغ این پنل می‌رود.</small>
							</div>

							<div class="ashna-pipeline-step">
								<div class="ashna-pipeline-step-head">
									<span class="ashna-step-num-badge">
										<span class="ashna-step-num-circle">۳</span>
										<span>پشتیبان سوم</span>
									</span>
									<span class="ashna-pill is-muted">اولویت سوم</span>
								</div>
								<select name="ashna[backup_sms_gateway_3]" id="backup_sms_gateway_3">
									<option value="none" <?php selected( $settings['backup_sms_gateway_3'], 'none' ); ?>>غیرفعال (ندارم)</option>
									<?php foreach ( $sms_gateways as $gw_id => $gw_obj ) : ?>
										<?php if ( 'sandbox' !== $gw_id ) : ?>
											<option value="<?php echo esc_attr( $gw_id ); ?>" <?php selected( $settings['backup_sms_gateway_3'], $gw_id ); ?>>
												<?php echo esc_html( $gw_obj->get_title() ); ?>
											</option>
										<?php endif; ?>
									<?php endforeach; ?>
								</select>
								<small>آخرین سپر اطمینان برای تحویل ۱۰۰٪ پیامک.</small>
							</div>
						</div>

						<!-- Live Visual Failover Route Strip -->
						<div class="ashna-live-route-strip" id="ashna-live-failover-strip">
							<span style="font-size:12px;font-weight:700;color:var(--s-text-muted);">مسیر فعلی ارسال پیامک:</span>
							<div class="ashna-route-nodes" id="ashna-live-route-nodes">
								<span class="ashna-route-pill is-primary" id="ashna-route-primary-label"><?php echo esc_html( $active_gw_title ); ?></span>
							</div>
						</div>

						<!-- Gateway Switcher Pills with Logos to Inspect/Edit Any Gateway -->
						<div class="ashna-gw-tabs-bar">
							<span class="ashna-section-label" style="margin:0;">۳. تنظیم کلید API و کد پترن هر سامانه:</span>
							<div class="ashna-gw-pills">
								<?php foreach ( $sms_gateways as $gw_id => $gw_obj ) : ?>
									<button type="button" class="ashna-gw-pill" data-gw="<?php echo esc_attr( $gw_id ); ?>">
										<?php
										// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
										echo isset( $gw_logos[ $gw_id ] ) ? $gw_logos[ $gw_id ] : '';
										?>
										<span><?php echo esc_html( $gw_obj->get_title() ); ?></span>
									</button>
								<?php endforeach; ?>
							</div>
						</div>

						<!-- 1. Sandbox Box -->
						<div class="ashna-gateway-box" data-gateway="sandbox">
							<div class="ashna-gw-box-head">
								<h3 style="display:inline-flex;align-items:center;gap:8px;"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 3h6m-5 0v5.172a2 2 0 0 1-.586 1.414l-4.828 4.828A2 2 0 0 0 4 15.828V19a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-3.172a2 2 0 0 0-.586-1.414l-4.828-4.828A2 2 0 0 1 14 8.172V3"/></svg> حالت تست لوکال (Sandbox)</h3>
								<span class="ashna-pill is-warn">بدون ارسال پیامک واقعی</span>
							</div>
							<p class="description">در این حالت پیامکی ارسال نمی‌شود و کد تایید در «لاگ کدها» ثبت می‌گردد تا بدون نیاز به شارژ پنل، فرم ورود را تست کنید.</p>
							<div class="ashna-switch-row" style="margin-top:14px;">
								<div class="ashna-switch-text">
									<strong>نمایش کد آزمایشی بالای فرم ورود</strong>
									<p>کد تایید تولیدشده در کادر پیام بالای فرم به شما نمایش داده شود (در سایت اصلی خاموش کنید).</p>
								</div>
								<label class="ashna-switch">
									<input type="checkbox" name="ashna[show_debug_code_in_toast]" value="1" <?php checked( $settings['show_debug_code_in_toast'], 1 ); ?> />
									<span class="ashna-slider"></span>
								</label>
							</div>
						</div>

						<!-- 2. SMS.ir Box -->
						<div class="ashna-gateway-box" data-gateway="smsir">
							<div class="ashna-gw-box-head">
								<h3>تنظیمات درگاه SMS.ir</h3>
								<span class="ashna-pill is-info">ارسال سریع خدماتی (Verify)</span>
							</div>
							<div class="ashna-fields-grid ashna-cols-3">
								<div class="ashna-field">
									<label for="smsir_api_key">کلید وب‌سرویس (API Key)</label>
									<input type="text" name="ashna[smsir_api_key]" id="smsir_api_key" value="<?php echo esc_attr( $settings['smsir_api_key'] ); ?>" dir="ltr" placeholder="کلید دریافتی از پنل app.sms.ir" />
								</div>
								<div class="ashna-field">
									<label for="smsir_template_id">شناسه قالب (Template ID)</label>
									<input type="text" name="ashna[smsir_template_id]" id="smsir_template_id" value="<?php echo esc_attr( $settings['smsir_template_id'] ); ?>" dir="ltr" placeholder="مثلاً: 100000" />
								</div>
								<div class="ashna-field">
									<label for="smsir_param_name">نام متغیر کد در قالب</label>
									<input type="text" name="ashna[smsir_param_name]" id="smsir_param_name" value="<?php echo esc_attr( $settings['smsir_param_name'] ); ?>" dir="ltr" placeholder="CODE" />
								</div>
							</div>
						</div>

						<!-- 3. Kavenegar Box -->
						<div class="ashna-gateway-box" data-gateway="kavenegar">
							<div class="ashna-gw-box-head">
								<h3>تنظیمات درگاه کاوه‌نگار (Kavenegar)</h3>
								<span class="ashna-pill is-info">ارسال اعتبارسنجی (Lookup)</span>
							</div>
							<div class="ashna-fields-grid ashna-cols-2">
								<div class="ashna-field">
									<label for="kavenegar_api_key">کلید API (API Key)</label>
									<input type="text" name="ashna[kavenegar_api_key]" id="kavenegar_api_key" value="<?php echo esc_attr( $settings['kavenegar_api_key'] ); ?>" dir="ltr" placeholder="کلید API از پنل کاوه‌نگار" />
								</div>
								<div class="ashna-field">
									<label for="kavenegar_template">نام الگوی اعتبارسنجی (Template)</label>
									<input type="text" name="ashna[kavenegar_template]" id="kavenegar_template" value="<?php echo esc_attr( $settings['kavenegar_template'] ); ?>" dir="ltr" placeholder="verify-login" />
									<small>در متن الگوی کاوه‌نگار حتماً از متغیر <code>%token</code> استفاده کنید.</small>
								</div>
							</div>
						</div>

						<!-- 4. Melipayamak Box -->
						<div class="ashna-gateway-box" data-gateway="melipayamak">
							<div class="ashna-gw-box-head">
								<h3>تنظیمات درگاه ملی‌پیامک (Melipayamak)</h3>
								<span class="ashna-pill is-info">وب‌سرویس خط خدماتی ( پترن )</span>
							</div>
							<div class="ashna-fields-grid ashna-cols-3">
								<div class="ashna-field">
									<label for="melipayamak_username">نام کاربری پنل</label>
									<input type="text" name="ashna[melipayamak_username]" id="melipayamak_username" value="<?php echo esc_attr( $settings['melipayamak_username'] ); ?>" dir="ltr" />
								</div>
								<div class="ashna-field">
									<label for="melipayamak_password">رمز عبور یا API Key</label>
									<input type="password" name="ashna[melipayamak_password]" id="melipayamak_password" value="<?php echo esc_attr( $settings['melipayamak_password'] ); ?>" dir="ltr" />
								</div>
								<div class="ashna-field">
									<label for="melipayamak_body_id">کد متن خدماتی (bodyId)</label>
									<input type="text" name="ashna[melipayamak_body_id]" id="melipayamak_body_id" value="<?php echo esc_attr( $settings['melipayamak_body_id'] ); ?>" dir="ltr" placeholder="12345" />
									<small>متن الگو در ملی‌پیامک باید شامل <code>{0}</code> باشد.</small>
								</div>
							</div>
						</div>

						<!-- 5. FarazSMS Box (Progressive Disclosure for Auth Type) -->
						<div class="ashna-gateway-box" data-gateway="farazsms">
							<div class="ashna-gw-box-head">
								<h3>تنظیمات درگاه فراز اس‌ام‌اس (FarazSMS)</h3>
								<span class="ashna-pill is-info">ارسال سریع پترن</span>
							</div>
							<div class="ashna-fields-grid ashna-cols-3">
								<div class="ashna-field">
									<label for="farazsms_auth_type">روش اتصال به پنل</label>
									<select name="ashna[farazsms_auth_type]" id="farazsms_auth_type">
										<option value="apikey" <?php selected( $settings['farazsms_auth_type'], 'apikey' ); ?>>کلید دسترسی (API Key — پیشنهادی)</option>
										<option value="userpass" <?php selected( $settings['farazsms_auth_type'], 'userpass' ); ?>>نام کاربری و رمز عبور</option>
									</select>
								</div>
								<div class="ashna-field" id="farazsms-apikey-wrap" style="<?php echo 'userpass' === $settings['farazsms_auth_type'] ? 'display:none;' : ''; ?>">
									<label for="farazsms_api_key">کلید دسترسی (API Key)</label>
									<input type="text" name="ashna[farazsms_api_key]" id="farazsms_api_key" value="<?php echo esc_attr( $settings['farazsms_api_key'] ); ?>" dir="ltr" />
								</div>
								<div class="ashna-field" id="farazsms-userpass-wrap" style="<?php echo 'userpass' === $settings['farazsms_auth_type'] ? '' : 'display:none;'; ?>">
									<label for="farazsms_username">نام کاربری و رمز پنل</label>
									<div style="display:flex;gap:6px;">
										<input type="text" name="ashna[farazsms_username]" id="farazsms_username" value="<?php echo esc_attr( $settings['farazsms_username'] ); ?>" dir="ltr" placeholder="Username" />
										<input type="password" name="ashna[farazsms_password]" id="farazsms_password" value="<?php echo esc_attr( $settings['farazsms_password'] ); ?>" dir="ltr" placeholder="Password" />
									</div>
								</div>
								<div class="ashna-field">
									<label for="farazsms_from_number">شماره خط فرستنده</label>
									<input type="text" name="ashna[farazsms_from_number]" id="farazsms_from_number" value="<?php echo esc_attr( $settings['farazsms_from_number'] ); ?>" dir="ltr" placeholder="+983000505" />
								</div>
								<div class="ashna-field">
									<label for="farazsms_pattern_code">کد پترن (Pattern Code)</label>
									<input type="text" name="ashna[farazsms_pattern_code]" id="farazsms_pattern_code" value="<?php echo esc_attr( $settings['farazsms_pattern_code'] ); ?>" dir="ltr" placeholder="مثلاً: x8k9p2m" />
								</div>
								<div class="ashna-field">
									<label for="farazsms_param_name">نام متغیر کد در پترن</label>
									<input type="text" name="ashna[farazsms_param_name]" id="farazsms_param_name" value="<?php echo esc_attr( $settings['farazsms_param_name'] ); ?>" dir="ltr" placeholder="verification-code" />
								</div>
							</div>
						</div>

						<!-- 6. IPPanel Box (Progressive Disclosure for Auth Type) -->
						<div class="ashna-gateway-box" data-gateway="ippanel">
							<div class="ashna-gw-box-head">
								<h3>تنظیمات درگاه آی‌پی‌پنل (IPPanel)</h3>
								<span class="ashna-pill is-info">پشتیبانی از Edge و REST</span>
							</div>
							<div class="ashna-fields-grid ashna-cols-3">
								<div class="ashna-field">
									<label for="ippanel_auth_type">نسخه وب‌سرویس</label>
									<select name="ashna[ippanel_auth_type]" id="ippanel_auth_type">
										<option value="edge" <?php selected( $settings['ippanel_auth_type'], 'edge' ); ?>>وب‌سرویس جدید Edge (پیشنهادی)</option>
										<option value="apikey" <?php selected( $settings['ippanel_auth_type'], 'apikey' ); ?>>وب‌سرویس REST با کلید API</option>
										<option value="userpass" <?php selected( $settings['ippanel_auth_type'], 'userpass' ); ?>>نام کاربری و رمز عبور</option>
									</select>
								</div>
								<div class="ashna-field" id="ippanel-apikey-wrap" style="<?php echo 'userpass' === $settings['ippanel_auth_type'] ? 'display:none;' : ''; ?>">
									<label for="ippanel_api_key">کلید API / توکن</label>
									<input type="text" name="ashna[ippanel_api_key]" id="ippanel_api_key" value="<?php echo esc_attr( $settings['ippanel_api_key'] ); ?>" dir="ltr" />
								</div>
								<div class="ashna-field" id="ippanel-userpass-wrap" style="<?php echo 'userpass' === $settings['ippanel_auth_type'] ? '' : 'display:none;'; ?>">
									<label>نام کاربری و رمز پنل</label>
									<div style="display:flex;gap:6px;">
										<input type="text" name="ashna[ippanel_username]" value="<?php echo esc_attr( $settings['ippanel_username'] ); ?>" dir="ltr" placeholder="Username" />
										<input type="password" name="ashna[ippanel_password]" value="<?php echo esc_attr( $settings['ippanel_password'] ); ?>" dir="ltr" placeholder="Password" />
									</div>
								</div>
								<div class="ashna-field">
									<label for="ippanel_from_number">شماره خط فرستنده</label>
									<input type="text" name="ashna[ippanel_from_number]" id="ippanel_from_number" value="<?php echo esc_attr( $settings['ippanel_from_number'] ); ?>" dir="ltr" placeholder="+983000505" />
								</div>
								<div class="ashna-field">
									<label for="ippanel_pattern_code">کد پترن (Pattern Code)</label>
									<input type="text" name="ashna[ippanel_pattern_code]" id="ippanel_pattern_code" value="<?php echo esc_attr( $settings['ippanel_pattern_code'] ); ?>" dir="ltr" />
								</div>
								<div class="ashna-field">
									<label for="ippanel_param_name">نام متغیر کد در پترن</label>
									<input type="text" name="ashna[ippanel_param_name]" id="ippanel_param_name" value="<?php echo esc_attr( $settings['ippanel_param_name'] ); ?>" dir="ltr" placeholder="code" />
								</div>
							</div>
						</div>

						<!-- 4. Inline Quick SMS Test Bar (Relocated right inside SMS Gateways Tab for instant testing!) -->
						<div class="ashna-inline-test-bar">
							<div style="display:flex;align-items:center;gap:12px;">
								<span class="ashna-card-icon is-blue" style="width:38px;height:38px;">
									<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path d="m22 2-7 20-4-9-9-4Z"/><path d="M22 2 11 13"/></svg>
								</span>
								<div>
									<strong style="font-size:13.5px;color:var(--s-text);display:block;">تست فوری ارسال پیامک با همین درگاه</strong>
									<small style="font-size:12px;color:var(--s-text-muted);">بعد از وارد کردن کلید API، شماره موبایل خود را وارد کنید تا اتصال درگاه همین الان تست شود</small>
								</div>
							</div>
							<div class="ashna-inline-test-controls">
								<input type="text" id="ashna_quick_sms_test_phone" placeholder="مثلاً: 09123456789" dir="ltr" style="flex:1;" />
								<button type="button" id="ashna_quick_sms_test_btn" class="ashna-btn-save" style="height:42px;white-space:nowrap;">
									<span>ارسال پیامک تست</span>
								</button>
							</div>
						</div>
					</div>
				</section>
