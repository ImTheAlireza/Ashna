<?php
/**
 * Admin Settings Partial: tab-dashboard.php (Polished Top Cards, Left-Aligned KPI Numbers & Vector Icons)
 *
 * @package Ashna
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$active_backups = Ashna_Gateway_Manager::get_backup_sms_gateways();
$backup_names   = array();
foreach ( $active_backups as $bgw ) {
	$backup_names[] = $bgw->get_id();
}

$captcha_labels = array(
	'none'         => 'غیرفعال',
	'arcaptcha'    => 'آرکپچا (Arcaptcha)',
	'math'         => 'کپچای ریاضی داخلی',
	'recaptcha_v3' => 'Google reCAPTCHA v3',
	'turnstile'    => 'Cloudflare Turnstile',
);
$active_captcha_label = isset( $captcha_labels[ $settings['captcha_type'] ] ) ? $captcha_labels[ $settings['captcha_type'] ] : 'غیرفعال';

$strategy_labels = array(
	'sms'               => 'فقط پیامک (SMS)',
	'bale_fallback_sms' => 'اول بله ← سپس پیامک',
	'sms_fallback_bale' => 'اول پیامک ← سپس بله',
	'bale'              => 'فقط پیام‌رسان بله',
	'both'              => 'ارسال همزمان (پیامک + بله)',
);
$active_strategy_label = isset( $strategy_labels[ $settings['mobile_delivery_channel'] ] )
	? $strategy_labels[ $settings['mobile_delivery_channel'] ]
	: $settings['mobile_delivery_channel'];

$step1_done = ( 'sandbox' !== $settings['active_sms_gateway'] ) || ! empty( $settings['bale_client_id'] ) || ! empty( $settings['bale_bot_token'] );
$step2_done = ! empty( $settings['mobile_delivery_channel'] );
$step3_done = ! empty( $settings['enable_passkey'] );
$step4_done = 'none' !== $settings['captcha_type'];
$step5_done = ! empty( $settings['wc_replace_myaccount'] ) || ! empty( $settings['enable_global_modal'] ) || ! empty( $settings['wp_login_integration'] );

$checklist_steps = array(
	array(
		'done'        => $step1_done,
		'title'       => '۱. اتصال درگاه پیامک یا پیام‌رسان بله',
		'desc'        => $step1_done
			? sprintf( 'درگاه «%s» متصل و آماده ارسال کد به کاربران است.', $active_gw_title )
			: 'درگاه فعلی روی حالت آزمایشی (Sandbox) است؛ کلید API اپراتور پیامک یا سفیر بله را وارد کنید.',
		'status_text' => $step1_done ? 'متصل و فعال' : 'در انتظار اتصال',
		'btn_text'    => 'تنظیم درگاه پیامک',
		'target_tab'  => 'sms_gateways',
	),
	array(
		'done'        => $step2_done,
		'title'       => '۲. انتخاب مسیر ارسال کد و عضویت خودکار',
		'desc'        => sprintf( 'استراتژی فعلی: «%s» با کد %d رقمی.', $active_strategy_label, (int) $settings['otp_length'] ),
		'status_text' => 'پیکربندی شده',
		'btn_text'    => 'سناریوی ورود',
		'target_tab'  => 'auth_flow',
	),
	array(
		'done'        => $step3_done,
		'title'       => '۳. فعال‌سازی ورود بیومتریک بدون رمز (Passkey)',
		'desc'        => $step3_done
			? 'ورود سریع با اثر انگشت و تشخیص چهره (FaceID / TouchID) برای مشتریان فعال است.'
			: 'با فعال‌سازی Passkey، کاربران در مراجعات بعدی بدون نیاز به پیامک با اثر انگشت وارد می‌شوند.',
		'status_text' => $step3_done ? 'فعال شده' : 'پیشنهادی',
		'btn_text'    => 'تنظیم Passkey',
		'target_tab'  => 'auth_flow',
	),
	array(
		'done'        => $step4_done,
		'title'       => '۴. ایمن‌سازی فرم در برابر ربات‌ها (کپچا و فایروال)',
		'desc'        => $step4_done
			? sprintf( 'سپر امنیتی «%s» به همراه محدودیت %d درخواست در ساعت فعال است.', $active_captcha_label, (int) $settings['max_requests_per_hour'] )
			: 'برای جلوگیری از ارسال پیامک‌های جعلی، آرکپچا یا کپچای ریاضی را فعال نمایید.',
		'status_text' => $step4_done ? 'محافظت فعال' : 'پیشنهاد امنیتی',
		'btn_text'    => 'امنیت و فایروال',
		'target_tab'  => 'security_firewall',
	),
	array(
		'done'        => $step5_done,
		'title'       => '۵. نمایش فرم ورود در سایت (ووکامرس / المنتور / شورت‌کد)',
		'desc'        => 'جایگزینی خودکار فرم حساب کاربری ووکامرس، ویجت اختصاصی المنتور یا شورت‌کد [ashna_otp_login].',
		'status_text' => $step5_done ? 'متصل به سایت' : 'آماده جایگذاری',
		'btn_text'    => 'ووکامرس و المنتور',
		'target_tab'  => 'woocommerce',
		'shortcode'   => '[ashna_otp_login]',
	),
);

$checklist_completed = 0;
foreach ( $checklist_steps as $c_step ) {
	if ( ! empty( $c_step['done'] ) ) {
		++$checklist_completed;
	}
}
$checklist_total   = count( $checklist_steps );
$checklist_percent = (int) round( ( $checklist_completed / $checklist_total ) * 100 );
?>
				<section class="ashna-panel active" id="ashna-tab-dashboard">
					<!-- Quick Configuration & Jump Bar -->
					<div class="ashna-quick-setup-grid">
						<div class="ashna-quick-item">
							<div class="ashna-quick-head">
								<span class="ashna-pill <?php echo 'sandbox' === $settings['active_sms_gateway'] ? 'is-warn' : 'is-ok'; ?>">
									<?php echo 'sandbox' === $settings['active_sms_gateway'] ? 'حالت تست (Sandbox)' : 'درگاه عملیاتی فعال'; ?>
								</span>
								<button type="button" class="ashna-jump-tab" data-target-tab="sms_gateways">تنظیم درگاه &larr;</button>
							</div>
							<div class="ashna-quick-body">
								<span class="ashna-quick-icon is-blue">
									<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.1"><rect x="5" y="2" width="14" height="20" rx="2"/><line x1="12" y1="18" x2="12.01" y2="18"/></svg>
								</span>
								<div class="ashna-quick-text">
									<strong><?php echo esc_html( $active_gw_title ); ?></strong>
									<small><?php echo empty( $backup_names ) ? 'بدون درگاه پشتیبان (Failover)' : sprintf( '%d پشتیبان فعال (%s)', count( $backup_names ), implode( '، ', $backup_names ) ); ?></small>
								</div>
							</div>
						</div>

						<div class="ashna-quick-item">
							<div class="ashna-quick-head">
								<span class="ashna-pill is-info">مسیر ارسال کد</span>
								<button type="button" class="ashna-jump-tab" data-target-tab="auth_flow">تغییر مسیر &larr;</button>
							</div>
							<div class="ashna-quick-body">
								<div class="ashna-flow-icons" style="flex-shrink:0;">
									<?php if ( 'bale_fallback_sms' === $settings['mobile_delivery_channel'] ) : ?>
										<span class="ashna-flow-node is-bale" title="بله"><?php echo Ashna_Helper::get_bale_logo_svg( 20 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
										<span class="ashna-flow-sep"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><line x1="19" y1="12" x2="5" y2="12"/><polyline points="12 19 5 12 12 5"/></svg></span>
										<span class="ashna-flow-node is-sms" title="پیامک"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/><path d="M8 10h8"/><path d="M8 14h4"/></svg></span>
									<?php elseif ( 'sms_fallback_bale' === $settings['mobile_delivery_channel'] ) : ?>
										<span class="ashna-flow-node is-sms" title="پیامک"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/><path d="M8 10h8"/><path d="M8 14h4"/></svg></span>
										<span class="ashna-flow-sep"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><line x1="19" y1="12" x2="5" y2="12"/><polyline points="12 19 5 12 12 5"/></svg></span>
										<span class="ashna-flow-node is-bale" title="بله"><?php echo Ashna_Helper::get_bale_logo_svg( 20 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
									<?php elseif ( 'both' === $settings['mobile_delivery_channel'] ) : ?>
										<span class="ashna-flow-node is-sms" title="پیامک"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/><path d="M8 10h8"/><path d="M8 14h4"/></svg></span>
										<span class="ashna-flow-sep is-plus">+</span>
										<span class="ashna-flow-node is-bale" title="بله"><?php echo Ashna_Helper::get_bale_logo_svg( 20 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
									<?php elseif ( 'bale' === $settings['mobile_delivery_channel'] ) : ?>
										<span class="ashna-flow-node is-bale" title="بله"><?php echo Ashna_Helper::get_bale_logo_svg( 20 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
									<?php else : ?>
										<span class="ashna-flow-node is-sms" title="پیامک"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/><path d="M8 10h8"/><path d="M8 14h4"/></svg></span>
									<?php endif; ?>
								</div>
								<div class="ashna-quick-text">
									<strong><?php echo esc_html( $active_strategy_label ); ?></strong>
									<small>ورود بیومتریک Passkey: <?php echo ! empty( $settings['enable_passkey'] ) ? 'فعال' : 'غیرفعال'; ?> • کد <?php echo esc_html( (string) $settings['otp_length'] ); ?> رقمی</small>
								</div>
							</div>
						</div>

						<div class="ashna-quick-item">
							<div class="ashna-quick-head">
								<span class="ashna-pill <?php echo 'none' === $settings['captcha_type'] ? 'is-muted' : 'is-ok'; ?>">
									<?php echo 'none' === $settings['captcha_type'] ? 'بدون کپچا' : 'محافظت فعال'; ?>
								</span>
								<button type="button" class="ashna-jump-tab" data-target-tab="security_firewall">تنظیمات امنیت &larr;</button>
							</div>
							<div class="ashna-quick-body">
								<span class="ashna-quick-icon is-amber">
									<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.1"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
								</span>
								<div class="ashna-quick-text">
									<strong>کپچا: <?php echo esc_html( $active_captcha_label ); ?></strong>
									<small>سقف مجاز: <?php echo esc_html( (string) $settings['max_requests_per_hour'] ); ?> بار در ساعت برای هر شماره</small>
								</div>
							</div>
						</div>

						<div class="ashna-quick-item">
							<div class="ashna-quick-head">
								<span class="ashna-pill is-ok">المنتور و شورت‌کد</span>
								<button type="button" class="ashna-jump-tab" data-target-tab="woocommerce">مشاهده ابزارها &larr;</button>
							</div>
							<div class="ashna-quick-body">
								<span class="ashna-quick-icon is-green">
									<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.1"><polygon points="12 2 2 7 12 12 22 7 12 2"/><polyline points="2 17 12 22 22 17"/><polyline points="2 12 12 17 22 12"/></svg>
								</span>
								<div class="ashna-quick-text">
									<strong>یکپارچگی قالب و صفحه‌ساز</strong>
									<small>۲ ویجت بومی المنتور + سازگار با دیجیتز</small>
								</div>
							</div>
						</div>
					</div>

					<!-- KPI Summary Cards (Numbers cleanly positioned on the LEFT side of each card) -->
					<div class="ashna-kpi-grid">
						<div class="ashna-kpi-card">
							<div class="ashna-kpi-main">
								<div class="ashna-kpi-icon is-blue">
									<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.1" stroke-linecap="round" stroke-linejoin="round"><path d="m22 2-7 20-4-9-9-4Z"/><path d="M22 2 11 13"/></svg>
								</div>
								<div class="ashna-kpi-info">
									<span>کل کدهای ارسال‌شده</span>
									<small><?php echo esc_html( number_format_i18n( $stats['today'] ) ); ?> ارسال در امروز</small>
								</div>
							</div>
							<div class="ashna-kpi-number-box is-blue">
								<strong><?php echo esc_html( number_format_i18n( $stats['total'] ) ); ?></strong>
							</div>
						</div>

						<div class="ashna-kpi-card">
							<div class="ashna-kpi-main">
								<div class="ashna-kpi-icon is-green">
									<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.1" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
								</div>
								<div class="ashna-kpi-info">
									<span>ورودهای موفق (Verified)</span>
									<small>نرخ تبدیل: %<?php echo esc_html( (string) $stats['conversion_rate'] ); ?></small>
								</div>
							</div>
							<div class="ashna-kpi-number-box is-green">
								<strong><?php echo esc_html( number_format_i18n( $stats['verified'] ) ); ?></strong>
							</div>
						</div>

						<div class="ashna-kpi-card">
							<div class="ashna-kpi-main">
								<div class="ashna-kpi-icon is-purple">
									<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.1" stroke-linecap="round" stroke-linejoin="round"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
								</div>
								<div class="ashna-kpi-info">
									<span>کاربران ثبت‌نامی با OTP</span>
									<small>ثبت‌نام خودکار یکپارچه</small>
								</div>
							</div>
							<div class="ashna-kpi-number-box is-purple">
								<strong><?php echo esc_html( number_format_i18n( $stats['otp_users'] ) ); ?></strong>
							</div>
						</div>

						<div class="ashna-kpi-card">
							<div class="ashna-kpi-main">
								<div class="ashna-kpi-icon is-red">
									<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.1" stroke-linecap="round" stroke-linejoin="round"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
								</div>
								<div class="ashna-kpi-info">
									<span>ارسال‌های ناموفق / خطا</span>
									<small><?php echo esc_html( (string) count( $active_lockouts ) ); ?> مسدودی امنیتی فعال</small>
								</div>
							</div>
							<div class="ashna-kpi-number-box is-red">
								<strong><?php echo esc_html( number_format_i18n( $stats['failed'] ) ); ?></strong>
							</div>
						</div>
					</div>

					<!-- Quick Setup Checklist for Customers (چک‌لیست راه‌اندازی سریع) -->
					<div class="ashna-card ashna-setup-checklist-card" style="margin-bottom:20px;">
						<div class="ashna-card-head">
							<div class="ashna-card-head-title">
								<span class="ashna-card-icon is-green">
									<svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.1" stroke-linecap="round" stroke-linejoin="round"><path d="M9 11l3 3L22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/></svg>
								</span>
								<div>
									<h2>چک‌لیست راه‌اندازی سریع افزونه</h2>
									<p>راهنمای گام‌به‌گام برای اتصال درگاه، شخصی‌سازی و فعال‌سازی ورود پیامکی و بیومتریک در سایت</p>
								</div>
							</div>

							<div class="ashna-checklist-progress-wrap">
								<div class="ashna-checklist-progress-meta">
									<span class="ashna-checklist-progress-label"><?php echo esc_html( sprintf( '%d از %d گام تکمیل شده', $checklist_completed, $checklist_total ) ); ?></span>
									<strong class="ashna-checklist-progress-pct">%<?php echo esc_html( (string) $checklist_percent ); ?></strong>
								</div>
								<div class="ashna-checklist-progress-bar">
									<div class="ashna-checklist-progress-fill" style="width:<?php echo esc_attr( (string) $checklist_percent ); ?>%;"></div>
								</div>
							</div>
						</div>

						<div class="ashna-checklist-list">
							<?php foreach ( $checklist_steps as $step_idx => $c_step ) : ?>
								<div class="ashna-checklist-row <?php echo ! empty( $c_step['done'] ) ? 'is-done' : 'is-pending'; ?>">
									<div class="ashna-checklist-main">
										<span class="ashna-checklist-check <?php echo ! empty( $c_step['done'] ) ? 'is-done' : 'is-pending'; ?>">
											<?php if ( ! empty( $c_step['done'] ) ) : ?>
												<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
											<?php else : ?>
												<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
											<?php endif; ?>
										</span>
										<div class="ashna-checklist-text">
											<strong><?php echo esc_html( $c_step['title'] ); ?></strong>
											<p><?php echo esc_html( $c_step['desc'] ); ?></p>
										</div>
									</div>

									<div class="ashna-checklist-actions">
										<?php if ( ! empty( $c_step['shortcode'] ) ) : ?>
											<button type="button" class="ashna-copy-btn" data-copy="<?php echo esc_attr( $c_step['shortcode'] ); ?>" title="کپی شورت‌کد فرم ورود">
												کپی <code><?php echo esc_html( $c_step['shortcode'] ); ?></code>
											</button>
										<?php endif; ?>
										<span class="ashna-pill <?php echo ! empty( $c_step['done'] ) ? 'is-ok' : 'is-warn'; ?>">
											<?php echo esc_html( $c_step['status_text'] ); ?>
										</span>
										<button type="button" class="ashna-checklist-jump ashna-jump-tab" data-target-tab="<?php echo esc_attr( $c_step['target_tab'] ); ?>">
											<span><?php echo esc_html( $c_step['btn_text'] ); ?></span>
											<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><line x1="19" y1="12" x2="5" y2="12"/><polyline points="12 19 5 12 12 5"/></svg>
										</button>
									</div>
								</div>
							<?php endforeach; ?>
						</div>
					</div>

					<div class="ashna-bento-row">
						<!-- 7-Day Visual Bar Chart -->
						<div class="ashna-card ashna-col-8">
							<div class="ashna-card-head">
								<div class="ashna-card-head-title">
									<span class="ashna-card-icon is-blue">
										<svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.1" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="20" x2="18" y2="10"/><line x1="12" y1="20" x2="12" y2="4"/><line x1="6" y1="20" x2="6" y2="14"/></svg>
									</span>
									<div>
										<h2>نمودار ارسال کد در ۷ روز گذشته</h2>
										<p>مقایسه تعداد کل درخواست‌ها و ورودهای موفق روزانه</p>
									</div>
								</div>
								<div class="ashna-chart-legend">
									<span><i style="background:#3b82f6;"></i> کل ارسالی</span>
									<span><i style="background:#10b981;"></i> تایید شده</span>
								</div>
							</div>
							<div class="ashna-bar-chart">
								<?php foreach ( $chart_data as $day ) : ?>
									<div class="ashna-bar-col">
										<div class="ashna-bar-track" title="<?php echo esc_attr( sprintf( '%s: %d ارسال (%d موفق)', $day['label'], $day['total'], $day['verified'] ) ); ?>">
											<div class="ashna-bar-fill-total" style="height:<?php echo esc_attr( (string) $day['height_pct'] ); ?>%;">
												<span class="ashna-bar-num"><?php echo esc_html( (string) $day['total'] ); ?></span>
											</div>
										</div>
										<span class="ashna-bar-label"><?php echo esc_html( $day['label'] ); ?></span>
									</div>
								<?php endforeach; ?>
							</div>
						</div>

						<!-- System Health Check -->
						<div class="ashna-card ashna-col-4">
							<div class="ashna-card-head">
								<div class="ashna-card-head-title">
									<span class="ashna-card-icon is-green">
										<svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.1" stroke-linecap="round" stroke-linejoin="round"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/></svg>
									</span>
									<div>
										<h2>وضعیت سلامت سیستم</h2>
										<p>پیش‌نیازها و یکپارچگی‌ها</p>
									</div>
								</div>
							</div>
							<ul class="ashna-health-list">
								<li>
									<span>ماژول cURL و ارتباط وب‌سرویس</span>
									<span class="ashna-pill <?php echo $curl_active ? 'is-ok' : 'is-err'; ?>"><?php echo $curl_active ? 'فعال' : 'غیرفعال'; ?></span>
								</li>
								<li>
									<span>ماژول رمزنگاری OpenSSL</span>
									<span class="ashna-pill <?php echo $openssl_active ? 'is-ok' : 'is-err'; ?>"><?php echo $openssl_active ? 'فعال' : 'غیرفعال'; ?></span>
								</li>
								<li>
									<span>افزونه فروشگاه‌ساز ووکامرس</span>
									<span class="ashna-pill <?php echo $wc_active ? 'is-ok' : 'is-warn'; ?>"><?php echo $wc_active ? 'نصب و فعال' : 'نصب نیست'; ?></span>
								</li>
								<li>
									<span>ویجت‌های صفحه‌ساز المنتور</span>
									<span class="ashna-pill <?php echo $elementor_active ? 'is-ok' : 'is-muted'; ?>"><?php echo $elementor_active ? 'فعال (۲ ویجت)' : 'المنتور نصب نیست'; ?></span>
								</li>
								<li>
									<span>سازگاری متای دیجیتز (Digits)</span>
									<span class="ashna-pill is-ok">فعال (دوطرفه)</span>
								</li>
							</ul>
						</div>
					</div>

					<!-- Recent Logs Feed -->
					<div class="ashna-card" style="margin-top:20px;">
						<div class="ashna-card-head">
							<div class="ashna-card-head-title">
								<span class="ashna-card-icon is-purple">
									<svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.1" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
								</span>
								<div>
									<h2>آخرین کدهای ارسال‌شده</h2>
									<p>۶ درخواست اخیر ثبت‌شده در سیستم (مفید برای مشاهده سریع کد در حالت تست)</p>
								</div>
							</div>
							<a href="<?php echo esc_url( admin_url( 'admin.php?page=ashna-otp-logs' ) ); ?>" class="ashna-btn-secondary">مشاهده تمام لاگ‌ها</a>
						</div>
						<table class="ashna-modern-table">
							<thead>
								<tr>
									<th>گیرنده</th>
									<th>کانال / درگاه</th>
									<th>کد OTP</th>
									<th>وضعیت</th>
									<th>پاسخ سرور</th>
									<th>زمان</th>
								</tr>
							</thead>
							<tbody>
								<?php if ( empty( $recent_logs ) ) : ?>
									<tr><td colspan="6" style="text-align:center;padding:24px;">هنوز هیچ کد یکبارمصرفی ارسال نشده است.</td></tr>
								<?php else : ?>
									<?php foreach ( $recent_logs as $rlog ) : ?>
										<tr>
											<td><strong dir="ltr"><?php echo esc_html( $rlog->recipient ); ?></strong></td>
											<td><span class="ashna-pill is-muted"><?php echo esc_html( $rlog->channel . ' / ' . $rlog->gateway ); ?></span></td>
											<td><code class="ashna-otp-code-pill" dir="ltr"><?php echo esc_html( $rlog->otp_code ); ?></code></td>
											<td>
												<?php if ( 'verified' === $rlog->status ) : ?>
													<span class="ashna-pill is-ok">تایید شده</span>
												<?php elseif ( 'failed' === $rlog->status ) : ?>
													<span class="ashna-pill is-err">خطا</span>
												<?php elseif ( 'sent' === $rlog->status ) : ?>
													<span class="ashna-pill is-info">ارسال شده</span>
												<?php else : ?>
													<span class="ashna-pill is-muted">منقضی</span>
												<?php endif; ?>
											</td>
											<td><?php echo esc_html( $rlog->response_message ); ?></td>
											<td dir="ltr"><?php echo esc_html( $rlog->created_at ); ?></td>
										</tr>
									<?php endforeach; ?>
								<?php endif; ?>
							</tbody>
						</table>
					</div>
				</section>
