<?php
/**
 * Admin Settings Partial: tab-tools.php (Vector Icons + Side-by-Side Bento Grid)
 *
 * @package Ashna
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
				<section class="ashna-panel" id="ashna-tab-tools_backup">
					<!-- Live Gateway Tester -->
					<div class="ashna-card">
						<div class="ashna-card-head">
							<div class="ashna-card-head-title">
								<span class="ashna-card-icon is-green">
									<svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.1" stroke-linecap="round" stroke-linejoin="round"><polygon points="5 3 19 12 5 21 5 3"/></svg>
								</span>
								<div>
									<h2>آزمایشگاه تست زنده ارسال کد (Live Gateway Tester)</h2>
									<p>ارسال آنی کد آزمایشی برای اطمینان از صحت تنظیمات هر درگاه پیامک، پیام‌رسان بله و ایمیل</p>
								</div>
							</div>
							<span class="ashna-pill is-info">عیب‌یابی آنی</span>
						</div>
						<div class="ashna-fields-grid ashna-cols-3">
							<div class="ashna-field">
								<label for="ashna_test_recipient">شماره موبایل یا ایمیل گیرنده تست</label>
								<input type="text" id="ashna_test_recipient" dir="ltr" placeholder="09123456789 یا email@example.com" />
							</div>
							<div class="ashna-field">
								<label for="ashna_test_channel">کانال ارسال تست</label>
								<select id="ashna_test_channel">
									<option value="sms">پیامک (درگاه انتخابی)</option>
									<option value="bale">پیام‌رسان بله (Bale)</option>
									<option value="email">ایمیل (wp_mail)</option>
								</select>
							</div>
							<div class="ashna-field">
								<label for="ashna_test_gateway_id">درگاه پیامک مشخص (اختیاری)</label>
								<select id="ashna_test_gateway_id">
									<option value="">استفاده از درگاه اصلی فعال</option>
									<?php foreach ( $sms_gateways as $gw_id => $gw_obj ) : ?>
										<option value="<?php echo esc_attr( $gw_id ); ?>"><?php echo esc_html( $gw_obj->get_title() ); ?></option>
									<?php endforeach; ?>
								</select>
							</div>
						</div>
						<div style="margin-top:16px;">
							<button type="button" id="ashna_run_test_btn" class="ashna-btn-save">
								<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.3"><polygon points="5 3 19 12 5 21 5 3"/></svg>
								<span>ارسال کد آزمایشی همین الان</span>
							</button>
						</div>
						<div id="ashna_test_result" style="display:none;margin-top:14px;padding:14px 18px;border-radius:10px;"></div>
					</div>

					<!-- Export / Import / Reset Settings (Side-by-Side Bento Row) -->
					<div class="ashna-bento-row" style="margin-top:20px;">
						<div class="ashna-card ashna-col-6">
							<div class="ashna-card-head">
								<div class="ashna-card-head-title">
									<span class="ashna-card-icon is-blue">
										<svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.1" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
									</span>
									<div>
										<h2>برون‌ریزی و درون‌ریزی تنظیمات (JSON Backup)</h2>
										<p>انتقال سریع تنظیمات بین سایت تستی و سایت اصلی</p>
									</div>
								</div>
							</div>
							<div class="ashna-field">
								<label>کد پشتیبان تنظیمات فعلی (Export JSON)</label>
								<textarea id="ashna_export_json_box" rows="3" dir="ltr" readonly><?php echo esc_textarea( wp_json_encode( $settings ) ); ?></textarea>
								<button type="button" class="ashna-btn-secondary ashna-copy-btn" data-copy="<?php echo esc_attr( wp_json_encode( $settings ) ); ?>" style="margin-top:8px;">
									کپی JSON تنظیمات
								</button>
							</div>
							<div class="ashna-field" style="margin-top:16px;">
								<label for="ashna_import_json_box">درون‌ریزی تنظیمات (Import JSON)</label>
								<textarea id="ashna_import_json_box" rows="3" dir="ltr" placeholder='{"login_mode":"phone_and_email", ...}'></textarea>
								<button type="button" id="ashna_import_settings_btn" class="ashna-btn-secondary" style="margin-top:8px;">
									درون‌ریزی و جایگزینی تنظیمات
								</button>
							</div>
						</div>

						<div class="ashna-card ashna-col-6">
							<div class="ashna-card-head">
								<div class="ashna-card-head-title">
									<span class="ashna-card-icon is-amber">
										<svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.1" stroke-linecap="round" stroke-linejoin="round"><ellipse cx="12" cy="5" rx="9" ry="3"/><path d="M21 12c0 1.66-4 3-9 3s-9-1.34-9-3"/><path d="M3 5v14c0 1.66 4 3 9 3s9-1.34 9-3V5"/></svg>
									</span>
									<div>
										<h2>نگهداری دیتابیس و بازنشانی</h2>
										<p>مدیریت طول عمر لاگ‌ها و بازگشت به تنظیمات کارخانه</p>
									</div>
								</div>
							</div>
							<div class="ashna-field">
								<label for="log_retention_days">مدت زمان نگهداری خودکار لاگ‌ها (روز — پاکسازی با WP-Cron)</label>
								<input type="text" inputmode="numeric" name="ashna[log_retention_days]" id="log_retention_days" value="<?php echo esc_attr( (string) $settings['log_retention_days'] ); ?>" dir="ltr" />
							</div>
							<div class="ashna-switch-row" style="margin-top:14px;">
								<div class="ashna-switch-text">
									<strong>پاکسازی کامل هنگام حذف افزونه</strong>
									<p>حذف جدول لاگ‌ها و تنظیمات از دیتابیس در صورت پاک کردن افزونه.</p>
								</div>
								<label class="ashna-switch">
									<input type="checkbox" name="ashna[delete_data_on_uninstall]" value="1" <?php checked( $settings['delete_data_on_uninstall'], 1 ); ?> />
									<span class="ashna-slider"></span>
								</label>
							</div>
							<div style="margin-top:20px;padding-top:16px;border-top:1px solid rgba(156,163,175,0.2);">
								<button type="button" id="ashna_reset_defaults_btn" class="ashna-btn-danger">
									بازنشانی تمام تنظیمات به حالت پیش‌فرض کارخانه
								</button>
							</div>
						</div>
					</div>
				</section>
