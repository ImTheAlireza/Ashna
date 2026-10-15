<?php
/**
 * Ashna - Modern Logs & Analytics Table View
 *
 * @package Ashna
 * @var array  $logs_data
 * @var array  $stats
 * @var int    $paged
 * @var int    $per_page
 * @var string $search
 * @var string $status
 * @var string $channel
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$items       = $logs_data['items'];
$total_items = $logs_data['total'];
$total_pages = ceil( $total_items / $per_page );

$export_csv_url = wp_nonce_url(
	add_query_arg(
		array(
			'page'                  => 'ashna-otp-logs',
			'ashna_export_logs_csv' => '1',
		),
		admin_url( 'admin.php' )
	),
	'ashna_export_csv_action',
	'nonce'
);
?>
<div class="ashna-app-shell" id="ashna-app-shell" dir="rtl">
	<style>
		#ashna-app-shell .ashna-theme-linear-icon { display: inline-flex !important; align-items: center !important; justify-content: center !important; width: 20px !important; height: 20px !important; line-height: 0 !important; }
		#ashna-app-shell .ashna-icon-moon { display: block !important; width: 20px !important; height: 20px !important; flex-shrink: 0 !important; }
		#ashna-app-shell .ashna-icon-sun { display: none !important; width: 20px !important; height: 20px !important; flex-shrink: 0 !important; }
		#ashna-app-shell.is-dark .ashna-icon-moon { display: none !important; }
		#ashna-app-shell.is-dark .ashna-icon-sun { display: block !important; }
	</style>
	<header class="ashna-topbar">
		<div class="ashna-topbar-brand">
			<div class="ashna-brand-logo">
				<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="width:24px;height:24px;flex-shrink:0;display:block;"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/><line x1="10" y1="9" x2="8" y2="9"/></svg>
			</div>
			<div>
				<div class="ashna-brand-title-row">
					<h1>گزارش کدهای ارسالی (آشنا)</h1>
					<span class="ashna-badge-ver"><?php echo esc_html( number_format_i18n( $total_items ) ); ?> رکورد</span>
				</div>
				<p>مشاهده وضعیت ارسال پیامک‌ها، بله، ایمیل‌ها و ورودهای بیومتریک</p>
			</div>
		</div>

		<div class="ashna-topbar-actions">
			<button type="button" id="ashna-theme-toggle" class="ashna-icon-btn" title="تغییر حالت روشن / تاریک" style="padding:0 !important;width:40px !important;height:40px !important;display:inline-flex !important;align-items:center !important;justify-content:center !important;">
				<span class="ashna-theme-linear-icon">
					<svg class="ashna-icon-moon" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/></svg>
					<svg class="ashna-icon-sun" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="4"/><path d="M12 2v2"/><path d="M12 20v2"/><path d="m4.93 4.93 1.41 1.41"/><path d="m17.66 17.66 1.41 1.41"/><path d="M2 12h2"/><path d="M20 12h2"/><path d="m6.34 17.66-1.41 1.41"/><path d="m19.07 4.93-1.41 1.41"/></svg>
				</span>
			</button>

			<a href="<?php echo esc_url( $export_csv_url ); ?>" class="ashna-btn-secondary">
				<svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="width:17px;height:17px;flex-shrink:0;display:block;"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
				<span>خروجی اکسل (CSV)</span>
			</a>

			<a href="<?php echo esc_url( admin_url( 'admin.php?page=ashna-otp' ) ); ?>" class="ashna-btn-save" style="text-decoration:none;">
				<svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="width:17px;height:17px;flex-shrink:0;display:block;"><line x1="19" y1="12" x2="5" y2="12"/><polyline points="12 19 5 12 12 5"/></svg>
				<span>بازگشت به تنظیمات</span>
			</a>
		</div>
	</header>

	<div style="padding:24px;">
		<!-- KPI Summary -->
		<div class="ashna-kpi-grid" style="margin-bottom:20px;">
			<div class="ashna-kpi-card">
				<div class="ashna-kpi-main">
					<div class="ashna-kpi-icon is-blue"><svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m22 2-7 20-4-9-9-4Z"/><path d="M22 2 11 13"/></svg></div>
					<div class="ashna-kpi-info">
						<span>کل کدهای ثبت‌شده</span>
						<small>مجموع لاگ‌ها</small>
					</div>
				</div>
				<div class="ashna-kpi-number-box is-blue">
					<strong><?php echo esc_html( number_format_i18n( $stats['total'] ) ); ?></strong>
				</div>
			</div>
			<div class="ashna-kpi-card">
				<div class="ashna-kpi-main">
					<div class="ashna-kpi-icon is-green"><svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg></div>
					<div class="ashna-kpi-info">
						<span>تایید و وارد شده</span>
						<small>ورودهای موفق</small>
					</div>
				</div>
				<div class="ashna-kpi-number-box is-green">
					<strong><?php echo esc_html( number_format_i18n( $stats['verified'] ) ); ?></strong>
				</div>
			</div>
			<div class="ashna-kpi-card">
				<div class="ashna-kpi-main">
					<div class="ashna-kpi-icon is-purple"><svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg></div>
					<div class="ashna-kpi-info">
						<span>ارسالی امروز</span>
						<small>۲۴ ساعت اخیر</small>
					</div>
				</div>
				<div class="ashna-kpi-number-box is-purple">
					<strong><?php echo esc_html( number_format_i18n( $stats['today'] ) ); ?></strong>
				</div>
			</div>
			<div class="ashna-kpi-card">
				<div class="ashna-kpi-main">
					<div class="ashna-kpi-icon is-red"><svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg></div>
					<div class="ashna-kpi-info">
						<span>خطا در ارسال</span>
						<small>ارسال ناموفق</small>
					</div>
				</div>
				<div class="ashna-kpi-number-box is-red">
					<strong><?php echo esc_html( number_format_i18n( $stats['failed'] ) ); ?></strong>
				</div>
			</div>
		</div>

		<!-- Filter & Cleanup Card -->
		<div class="ashna-card" style="margin-bottom:20px;padding:16px 22px;">
			<div class="ashna-logs-toolbar">
				<form method="get" action="<?php echo esc_url( admin_url( 'admin.php' ) ); ?>" class="ashna-logs-filter-form">
					<input type="hidden" name="page" value="ashna-otp-logs" />
					<input type="search" name="s" value="<?php echo esc_attr( $search ); ?>" placeholder="جستجوی شماره، ایمیل یا IP..." style="min-width:220px;" />
					<select name="status">
						<option value="">همه وضعیت‌ها</option>
						<option value="sent" <?php selected( $status, 'sent' ); ?>>ارسال شده (فعال)</option>
						<option value="verified" <?php selected( $status, 'verified' ); ?>>تایید شده</option>
						<option value="failed" <?php selected( $status, 'failed' ); ?>>ناموفق</option>
						<option value="expired" <?php selected( $status, 'expired' ); ?>>منقضی شده</option>
					</select>
					<select name="channel">
						<option value="">همه کانال‌ها</option>
						<option value="sms" <?php selected( $channel, 'sms' ); ?>>پیامک (SMS)</option>
						<option value="bale" <?php selected( $channel, 'bale' ); ?>>پیام‌رسان بله</option>
						<option value="email" <?php selected( $channel, 'email' ); ?>>ایمیل</option>
						<option value="passkey" <?php selected( $channel, 'passkey' ); ?>>بیومتریک (Passkey)</option>
					</select>
					<button type="submit" class="ashna-btn-secondary">اعمال فیلتر</button>
					<?php if ( ! empty( $search ) || ! empty( $status ) || ! empty( $channel ) ) : ?>
						<a href="<?php echo esc_url( admin_url( 'admin.php?page=ashna-otp-logs' ) ); ?>" class="ashna-btn-secondary">حذف فیلتر</a>
					<?php endif; ?>
				</form>

				<form method="post" action="" onsubmit="return confirm('آیا از پاکسازی لاگ‌ها اطمینان دارید؟');" style="display:flex;gap:8px;align-items:center;">
					<?php wp_nonce_field( 'ashna_clear_logs_action', 'ashna_logs_nonce' ); ?>
					<select name="clear_type">
						<option value="old">حذف لاگ‌های قدیمی</option>
						<option value="all">پاک کردن تمام لاگ‌ها</option>
					</select>
					<button type="submit" name="ashna_clear_logs" value="1" class="ashna-btn-danger">پاکسازی</button>
				</form>
			</div>
		</div>

		<!-- Table Card -->
		<div class="ashna-card" style="padding:0;overflow:hidden;">
			<table class="ashna-modern-table">
				<thead>
					<tr>
						<th style="width:65px;">شناسه</th>
						<th>گیرنده (موبایل / ایمیل)</th>
						<th>کانال / درگاه</th>
						<th>کد OTP</th>
						<th>وضعیت</th>
						<th>تلاش‌ها</th>
						<th>پاسخ سرور / درگاه</th>
						<th>آدرس IP</th>
						<th>زمان ایجاد</th>
					</tr>
				</thead>
				<tbody>
					<?php if ( empty( $items ) ) : ?>
						<tr>
							<td colspan="9" style="text-align:center;padding:36px;color:#64748b;">
								هیچ لاگی مطابق با جستجوی شما یافت نشد.
							</td>
						</tr>
					<?php else : ?>
						<?php foreach ( $items as $row ) : ?>
							<tr>
								<td>#<?php echo esc_html( (string) $row->id ); ?></td>
								<td><strong dir="ltr"><?php echo esc_html( $row->recipient ); ?></strong></td>
								<td>
									<span class="ashna-pill is-muted"><?php echo esc_html( strtoupper( $row->channel ) . ' / ' . $row->gateway ); ?></span>
								</td>
								<td>
									<code class="ashna-otp-code-pill" dir="ltr"><?php echo esc_html( $row->otp_code ); ?></code>
								</td>
								<td>
									<?php if ( 'verified' === $row->status ) : ?>
										<span class="ashna-pill is-ok">تایید و وارد شده</span>
									<?php elseif ( 'failed' === $row->status ) : ?>
										<span class="ashna-pill is-err">خطا در ارسال</span>
									<?php elseif ( 'sent' === $row->status ) : ?>
										<span class="ashna-pill is-info">ارسال شده (فعال)</span>
									<?php else : ?>
										<span class="ashna-pill is-muted">منقضی شده</span>
									<?php endif; ?>
								</td>
								<td><?php echo esc_html( (string) $row->attempts ); ?></td>
								<td style="max-width:260px;font-size:12px;"><?php echo esc_html( $row->response_message ); ?></td>
								<td><code dir="ltr"><?php echo esc_html( $row->ip_address ); ?></code></td>
								<td dir="ltr" style="font-size:12px;"><?php echo esc_html( $row->created_at ); ?></td>
							</tr>
						<?php endforeach; ?>
					<?php endif; ?>
				</tbody>
			</table>
		</div>

		<?php if ( $total_pages > 1 ) : ?>
			<div class="tablenav bottom" style="margin-top:16px;">
				<div class="tablenav-pages">
					<?php
					// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
					echo paginate_links(
						array(
							'base'      => add_query_arg( 'paged', '%#%' ),
							'format'    => '',
							'prev_text' => '&laquo; قبلی',
							'next_text' => 'بعدی &raquo;',
							'total'     => $total_pages,
							'current'   => $paged,
						)
					);
					?>
				</div>
			</div>
		<?php endif; ?>
	</div>
</div>
