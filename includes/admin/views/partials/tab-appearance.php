<?php
/**
 * Admin Settings Partial: tab-appearance.php
 * Includes Category 1: Split-Screen Layout, Slide-Over Drawer Modal, Standalone Full-Page Canvas & Card Alignment
 *
 * @package Signa_OTP
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$form_layout    = isset( $settings['form_layout'] ) ? $settings['form_layout'] : 'card';
$card_position  = isset( $settings['card_position'] ) ? $settings['card_position'] : 'center';
$modal_style    = isset( $settings['modal_style'] ) ? $settings['modal_style'] : 'center';
$canvas_bg      = isset( $settings['canvas_bg_style'] ) ? $settings['canvas_bg_style'] : 'mesh_light';
$button_bg_mode = isset( $settings['button_bg_mode'] ) ? $settings['button_bg_mode'] : 'solid';
$card_shadow    = isset( $settings['card_shadow'] ) ? $settings['card_shadow'] : 'medium';
$card_border    = isset( $settings['card_border_style'] ) ? $settings['card_border_style'] : 'subtle';
$bg_pattern     = isset( $settings['bg_pattern'] ) ? $settings['bg_pattern'] : 'none';
$font_family    = isset( $settings['font_family'] ) ? $settings['font_family'] : 'vazirmatn';
$input_style    = isset( $settings['input_style'] ) ? $settings['input_style'] : 'filled';
$input_addon    = isset( $settings['input_addon_style'] ) ? $settings['input_addon_style'] : 'icon';
$is_split       = in_array( $form_layout, array( 'split_right', 'split_left' ), true );
$wp_pages       = get_pages( array( 'post_status' => 'publish' ) );
$svg_check_mark = '<svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>';

$split_features_raw  = isset( $settings['split_features'] ) ? $settings['split_features'] : '';
$split_features_list = array_filter( array_map( 'trim', explode( "\n", (string) $split_features_raw ) ) );
?>
				<section class="signa-panel" id="signa-tab-appearance_studio">
					<div class="signa-studio-layout">
						<!-- Studio Controls (Right Column) -->
						<div class="signa-studio-controls">

							<!-- CARD 1: Layout Skeleton (Single Card vs Split-Screen) & Card Position -->
							<div class="signa-card signa-accordion-card" style="margin-bottom:16px;">
								<div class="signa-card-head signa-accordion-trigger">
									<div class="signa-card-head-title">
										<span class="signa-card-icon is-blue">
											<svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.1" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="18" height="18" rx="2"/><line x1="12" y1="3" x2="12" y2="21"/></svg>
										</span>
										<div>
											<h2>۱. چیدمان و اسکلت صفحه ورود (Layout &amp; Alignment)</h2>
											<p>انتخاب حالت کارت تکی یا لی‌اوت دوتایی (Split-Screen) به همراه موقعیت قرارگیری در صفحه</p>
										</div>
									</div>
									<div style="display:flex;align-items:center;gap:10px;">
										<span class="signa-pill is-info">پیش‌نمایش زنده</span>
										<span class="signa-accordion-chevron"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 12 15 18 9"/></svg></span>
									</div>
								</div>
								<div class="signa-accordion-body" style="display:none;">

								<label class="signa-section-label">الف) اسکلت و ساختار فرم ورود</label>
								<div class="signa-choice-grid signa-cols-3">
									<!-- 1. Classic Single Card -->
									<label class="signa-choice-card <?php echo 'card' === $form_layout ? 'selected' : ''; ?>">
										<input type="radio" name="signa[form_layout]" value="card" <?php checked( $form_layout, 'card' ); ?> />
										<div class="signa-mini-video">
											<span class="signa-mini-video-badge">پیشنمایش</span>
											<div class="signa-mv-card">
												<span class="signa-mv-line"></span>
												<span class="signa-mv-input"></span>
												<span class="signa-mv-btn"></span>
											</div>
										</div>
										<div class="signa-choice-card-top">
											<div class="signa-flow-icons" title="کارت تکی کلاسیک">
												<span class="signa-flow-node is-phone">
													<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><rect x="6" y="3" width="12" height="18" rx="2"/><line x1="9" y1="9" x2="15" y2="9"/><line x1="9" y1="13" x2="15" y2="13"/></svg>
												</span>
											</div>
											<div class="signa-choice-top-left">
												<span class="signa-choice-tag is-blue">مینیمال</span>
												<span class="signa-choice-check"><?php echo $svg_check_mark; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
											</div>
										</div>
										<div class="signa-choice-body">
											<strong>کارت تکی کلاسیک</strong>
											<small>نمایش فرم ورود در یک کارت مستقل و جمع‌وجور</small>
										</div>
									</label>

									<!-- 2. Split-Screen: Form Right + Banner Left -->
									<label class="signa-choice-card <?php echo 'split_right' === $form_layout ? 'selected' : ''; ?>">
										<input type="radio" name="signa[form_layout]" value="split_right" <?php checked( $form_layout, 'split_right' ); ?> />
										<div class="signa-mini-video">
											<span class="signa-mini-video-badge">پیشنمایش</span>
											<div class="signa-mv-split">
												<div class="signa-mv-card">
													<span class="signa-mv-line"></span>
													<span class="signa-mv-input"></span>
													<span class="signa-mv-btn"></span>
												</div>
												<div class="signa-mv-banner">
													<span style="width:85%;"></span>
													<span style="width:65%;opacity:0.65;"></span>
													<span style="width:75%;background:#6ee7b7;"></span>
												</div>
											</div>
										</div>
										<div class="signa-choice-card-top">
											<div class="signa-flow-icons" title="فرم راست + بنر چپ">
												<span class="signa-flow-node is-sms">
													<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><rect x="3" y="3" width="18" height="18" rx="2"/><line x1="12" y1="3" x2="12" y2="21"/><path d="M15 9h3"/><path d="M15 13h3"/><circle cx="7.5" cy="12" r="2"/></svg>
												</span>
											</div>
											<div class="signa-choice-top-left">
												<span class="signa-choice-tag is-green">محبوب فروشگاهی</span>
												<span class="signa-choice-check"><?php echo $svg_check_mark; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
											</div>
										</div>
										<div class="signa-choice-body">
											<strong>دوتایی (فرم راست + بنر چپ)</strong>
											<small>فرم در سمت راست و بنر تصویری/برند در سمت چپ</small>
										</div>
									</label>

									<!-- 3. Split-Screen: Form Left + Banner Right -->
									<label class="signa-choice-card <?php echo 'split_left' === $form_layout ? 'selected' : ''; ?>">
										<input type="radio" name="signa[form_layout]" value="split_left" <?php checked( $form_layout, 'split_left' ); ?> />
										<div class="signa-mini-video">
											<span class="signa-mini-video-badge">پیشنمایش</span>
											<div class="signa-mv-split is-reverse">
												<div class="signa-mv-card">
													<span class="signa-mv-line"></span>
													<span class="signa-mv-input"></span>
													<span class="signa-mv-btn"></span>
												</div>
												<div class="signa-mv-banner">
													<span style="width:85%;"></span>
													<span style="width:65%;opacity:0.65;"></span>
													<span style="width:75%;background:#6ee7b7;"></span>
												</div>
											</div>
										</div>
										<div class="signa-choice-card-top">
											<div class="signa-flow-icons" title="فرم چپ + بنر راست">
												<span class="signa-flow-node is-passkey">
													<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><rect x="3" y="3" width="18" height="18" rx="2"/><line x1="12" y1="3" x2="12" y2="21"/><path d="M6 9h3"/><path d="M6 13h3"/><circle cx="16.5" cy="12" r="2"/></svg>
												</span>
											</div>
											<div class="signa-choice-top-left">
												<span class="signa-choice-check"><?php echo $svg_check_mark; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
											</div>
										</div>
										<div class="signa-choice-body">
											<strong>دوتایی (فرم چپ + بنر راست)</strong>
											<small>بنر معرفی برند در سمت راست و فرم در سمت چپ</small>
										</div>
									</label>
								</div>

								<!-- Progressive Disclosure: Split-Screen Side Banner Configuration -->
								<div id="signa-split-banner-settings" style="margin-top:18px;padding:18px;border-radius:14px;background:var(--s-bg-subtle);border:1px solid var(--s-border-input);<?php echo $is_split ? '' : 'display:none;'; ?>">
									<div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:14px;">
										<strong style="font-size:13.5px;color:var(--s-text);">تنظیمات بنر کناری (Split-Screen Side Panel)</strong>
										<span class="signa-pill is-info">نمایش در دسکتاپ و تبلت</span>
									</div>

									<div class="signa-fields-grid signa-cols-2">
										<div class="signa-field">
											<label for="split_bg_color">رنگ پایه پس‌زمینه بنر کناری</label>
											<div class="signa-color-input-wrap">
												<input type="color" name="signa[split_bg_color]" id="split_bg_color" value="<?php echo esc_attr( $settings['split_bg_color'] ); ?>" />
												<span id="split_bg_color_hex"><?php echo esc_html( $settings['split_bg_color'] ); ?></span>
											</div>
										</div>
										<div class="signa-field">
											<label for="split_badge_text">متن بج بالای بنر (اختیاری)</label>
											<input type="text" name="signa[split_badge_text]" id="split_badge_text" value="<?php echo esc_attr( $settings['split_badge_text'] ); ?>" placeholder="احراز هویت سریع و امن" />
										</div>
									</div>

									<div class="signa-field" style="margin-top:12px;">
										<label for="split_image_url">تصویر کاور یا پس‌زمینه بنر کناری (اختیاری)</label>
										<div style="display:flex;gap:8px;">
											<input type="text" name="signa[split_image_url]" id="split_image_url" value="<?php echo esc_attr( $settings['split_image_url'] ); ?>" dir="ltr" placeholder="https://example.com/banner.jpg" style="flex:1;" />
											<button type="button" id="signa_upload_split_img_btn" class="signa-btn-secondary">انتخاب تصویر</button>
										</div>
									</div>

									<div class="signa-fields-grid signa-cols-2" style="margin-top:12px;">
										<div class="signa-field">
											<label for="split_title">تیتر اصلی بنر کناری</label>
											<input type="text" name="signa[split_title]" id="split_title" value="<?php echo esc_attr( $settings['split_title'] ); ?>" />
										</div>
										<div class="signa-field">
											<label for="split_subtitle">زیرعنوان بنر کناری</label>
											<input type="text" name="signa[split_subtitle]" id="split_subtitle" value="<?php echo esc_attr( $settings['split_subtitle'] ); ?>" />
										</div>
									</div>

									<div class="signa-field" style="margin-top:12px;">
										<label for="split_features">ویژگی‌های تیک‌دار پایین بنر (هر خط یک ویژگی)</label>
										<textarea name="signa[split_features]" id="split_features" rows="3"><?php echo esc_textarea( $settings['split_features'] ); ?></textarea>
									</div>
								</div>

								<!-- Card Horizontal Alignment / Position -->
								<label class="signa-section-label" style="margin-top:20px;">ب) موقعیت افقی قرارگیری کارت در صفحه (Card Alignment)</label>
								<div class="signa-choice-grid signa-cols-3">
									<label class="signa-choice-card <?php echo 'right' === $card_position ? 'selected' : ''; ?>">
										<input type="radio" name="signa[card_position]" value="right" <?php checked( $card_position, 'right' ); ?> />
										<div class="signa-mini-video">
											<span class="signa-mini-video-badge">پیشنمایش</span>
											<div class="signa-mv-pos-track is-right">
												<div class="signa-mv-card" style="width:42px;height:44px;">
													<span class="signa-mv-line"></span>
													<span class="signa-mv-input"></span>
													<span class="signa-mv-btn"></span>
												</div>
											</div>
										</div>
										<div class="signa-choice-card-top">
											<span class="signa-flow-node is-phone">
												<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><rect x="3" y="3" width="18" height="18" rx="2"/><rect x="13" y="6" width="6" height="12" rx="1" fill="currentColor" fill-opacity="0.25"/></svg>
											</span>
											<span class="signa-choice-check"><?php echo $svg_check_mark; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
										</div>
										<div class="signa-choice-body">
											<strong>شناور در سمت راست</strong>
											<small>مناسب وقتی تصویر پس‌زمینه در سمت چپ است</small>
										</div>
									</label>

									<label class="signa-choice-card <?php echo 'center' === $card_position ? 'selected' : ''; ?>">
										<input type="radio" name="signa[card_position]" value="center" <?php checked( $card_position, 'center' ); ?> />
										<div class="signa-mini-video">
											<span class="signa-mini-video-badge">پیشنمایش</span>
											<div class="signa-mv-pos-track is-center">
												<div class="signa-mv-card" style="width:42px;height:44px;">
													<span class="signa-mv-line"></span>
													<span class="signa-mv-input"></span>
													<span class="signa-mv-btn"></span>
												</div>
											</div>
										</div>
										<div class="signa-choice-card-top">
											<span class="signa-flow-node is-sms">
												<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><rect x="3" y="3" width="18" height="18" rx="2"/><rect x="8.5" y="6" width="7" height="12" rx="1" fill="currentColor" fill-opacity="0.25"/></svg>
											</span>
											<div class="signa-choice-top-left">
												<span class="signa-choice-tag is-green">پیش‌فرض</span>
												<span class="signa-choice-check"><?php echo $svg_check_mark; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
											</div>
										</div>
										<div class="signa-choice-body">
											<strong>وسط‌چین (مرکز صفحه)</strong>
											<small>قرارگیری متوازن کارت در مرکز صفحه</small>
										</div>
									</label>

									<label class="signa-choice-card <?php echo 'left' === $card_position ? 'selected' : ''; ?>">
										<input type="radio" name="signa[card_position]" value="left" <?php checked( $card_position, 'left' ); ?> />
										<div class="signa-mini-video">
											<span class="signa-mini-video-badge">پیشنمایش</span>
											<div class="signa-mv-pos-track is-left">
												<div class="signa-mv-card" style="width:42px;height:44px;">
													<span class="signa-mv-line"></span>
													<span class="signa-mv-input"></span>
													<span class="signa-mv-btn"></span>
												</div>
											</div>
										</div>
										<div class="signa-choice-card-top">
											<span class="signa-flow-node is-passkey">
												<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><rect x="3" y="3" width="18" height="18" rx="2"/><rect x="5" y="6" width="6" height="12" rx="1" fill="currentColor" fill-opacity="0.25"/></svg>
											</span>
											<span class="signa-choice-check"><?php echo $svg_check_mark; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
										</div>
										<div class="signa-choice-body">
											<strong>شناور در سمت چپ</strong>
											<small>مناسب وقتی تصویر پس‌زمینه در سمت راست است</small>
										</div>
									</label>
								</div>
								</div>
							</div>

							<!-- CARD 2: Slide-Over Drawer Modal & Standalone Full-Page Canvas -->
							<div class="signa-card signa-accordion-card" style="margin-bottom:16px;">
								<div class="signa-card-head signa-accordion-trigger">
									<div class="signa-card-head-title">
										<span class="signa-card-icon is-green">
											<svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.1" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="18" height="18" rx="2"/><path d="M9 3v18"/></svg>
										</span>
										<div>
											<h2>۲. استایل پاپ‌آپ (Drawer / Modal) و قالب تمام‌صفحه اختصاصی</h2>
											<p>تنظیم نحوه باز شدن مودال ورود (با کلیک روی هر گزینه، انیمیشن آن در پیش‌نمایش زنده اجرا می‌شود)</p>
										</div>
									</div>
									<div style="display:flex;align-items:center;gap:10px;">
										<span class="signa-accordion-chevron"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 12 15 18 9"/></svg></span>
									</div>
								</div>
								<div class="signa-accordion-body" style="display:none;">

								<label class="signa-section-label">الف) نحوه باز شدن پنجره پاپ‌آپ سراسری (Global Modal &amp; Slide-Over Drawer)</label>
								<div class="signa-choice-grid signa-cols-2">
									<label class="signa-choice-card <?php echo 'center' === $modal_style ? 'selected' : ''; ?>">
										<input type="radio" name="signa[modal_style]" value="center" <?php checked( $modal_style, 'center' ); ?> />
										<div class="signa-mini-video">
											<span class="signa-mini-video-badge">پیشنمایش انیمیشن</span>
											<div class="signa-mv-screen">
												<div class="signa-mv-screen-bg"><i style="width:45%;"></i><i style="width:85%;"></i><i style="width:65%;"></i></div>
												<div class="signa-mv-modal-center">
													<span class="signa-mv-line"></span>
													<span class="signa-mv-input"></span>
													<span class="signa-mv-btn"></span>
												</div>
											</div>
										</div>
										<div class="signa-choice-card-top">
											<span class="signa-flow-node is-phone">
												<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><rect x="2" y="3" width="20" height="18" rx="2"/><rect x="7" y="7" width="10" height="10" rx="1.5"/></svg>
											</span>
											<div class="signa-choice-top-left">
												<span class="signa-choice-tag is-blue">کلاسیک</span>
												<span class="signa-choice-check"><?php echo $svg_check_mark; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
											</div>
										</div>
										<div class="signa-choice-body">
											<strong>پاپ‌آپ وسط صفحه (Center Modal)</strong>
											<small>باز شدن پنجره در مرکز تصویر با افکت تار شدن پس‌زمینه</small>
										</div>
									</label>

									<label class="signa-choice-card <?php echo 'drawer_left' === $modal_style ? 'selected' : ''; ?>">
										<input type="radio" name="signa[modal_style]" value="drawer_left" <?php checked( $modal_style, 'drawer_left' ); ?> />
										<div class="signa-mini-video">
											<span class="signa-mini-video-badge">پیشنمایش انیمیشن</span>
											<div class="signa-mv-screen">
												<div class="signa-mv-screen-bg"><i style="width:55%;"></i><i style="width:90%;"></i><i style="width:70%;"></i></div>
												<div class="signa-mv-drawer-left">
													<span class="signa-mv-line"></span>
													<span class="signa-mv-input"></span>
													<span class="signa-mv-btn"></span>
												</div>
											</div>
										</div>
										<div class="signa-choice-card-top">
											<span class="signa-flow-node is-sms">
												<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><rect x="3" y="3" width="18" height="18" rx="2"/><line x1="10" y1="3" x2="10" y2="21"/><path d="m15 10-3 2 3 2"/></svg>
											</span>
											<div class="signa-choice-top-left">
												<span class="signa-choice-tag is-green">مدرن</span>
												<span class="signa-choice-check"><?php echo $svg_check_mark; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
											</div>
										</div>
										<div class="signa-choice-body">
											<strong>دراور کشویی از چپ (Slide-Over Left)</strong>
											<small>باز شدن پنل تمام‌قد به صورت کشویی از لبه چپ صفحه</small>
										</div>
									</label>

									<label class="signa-choice-card <?php echo 'drawer_right' === $modal_style ? 'selected' : ''; ?>">
										<input type="radio" name="signa[modal_style]" value="drawer_right" <?php checked( $modal_style, 'drawer_right' ); ?> />
										<div class="signa-mini-video">
											<span class="signa-mini-video-badge">پیشنمایش انیمیشن</span>
											<div class="signa-mv-screen">
												<div class="signa-mv-screen-bg"><i style="width:55%;"></i><i style="width:90%;"></i><i style="width:70%;"></i></div>
												<div class="signa-mv-drawer-right">
													<span class="signa-mv-line"></span>
													<span class="signa-mv-input"></span>
													<span class="signa-mv-btn"></span>
												</div>
											</div>
										</div>
										<div class="signa-choice-card-top">
											<span class="signa-flow-node is-passkey">
												<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><rect x="3" y="3" width="18" height="18" rx="2"/><line x1="14" y1="3" x2="14" y2="21"/><path d="m9 10 3 2-3 2"/></svg>
											</span>
											<span class="signa-choice-check"><?php echo $svg_check_mark; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
										</div>
										<div class="signa-choice-body">
											<strong>دراور کشویی از راست (Slide-Over Right)</strong>
											<small>باز شدن پنل تمام‌قد به صورت کشویی از لبه راست صفحه</small>
										</div>
									</label>

									<label class="signa-choice-card <?php echo 'bottom_sheet' === $modal_style ? 'selected' : ''; ?>">
										<input type="radio" name="signa[modal_style]" value="bottom_sheet" <?php checked( $modal_style, 'bottom_sheet' ); ?> />
										<div class="signa-mini-video">
											<span class="signa-mini-video-badge">پیشنمایش انیمیشن</span>
											<div class="signa-mv-screen">
												<div class="signa-mv-screen-bg"><i style="width:55%;"></i><i style="width:90%;"></i></div>
												<div class="signa-mv-bottom-sheet">
													<span style="width:18px;height:2.5px;border-radius:99px;background:#cbd5e1;margin:0 auto 2px auto;display:block;"></span>
													<span class="signa-mv-input"></span>
													<span class="signa-mv-btn"></span>
												</div>
											</div>
										</div>
										<div class="signa-choice-card-top">
											<span class="signa-flow-node is-email">
												<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><rect x="3" y="3" width="18" height="18" rx="2"/><line x1="3" y1="14" x2="21" y2="14"/><line x1="10" y1="17" x2="14" y2="17"/></svg>
											</span>
											<span class="signa-choice-check"><?php echo $svg_check_mark; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
										</div>
										<div class="signa-choice-body">
											<strong>شیت کشویی از پایین (Bottom Sheet)</strong>
											<small>باز شدن از پایین صفحه به سبک اپلیکیشن‌های موبایل</small>
										</div>
									</label>
								</div>

								<div class="signa-switch-row" style="margin-top:14px;">
									<div class="signa-switch-text">
										<strong>حالت شیت کشویی پایین (Bottom Sheet) خودکار در موبایل</strong>
										<p>در صفحه‌نمایش موبایل، پاپ‌آپ همیشه از پایین صفحه باز شود تا تایپ کد با یک دست راحت باشد.</p>
									</div>
									<label class="signa-switch">
										<input type="checkbox" name="signa[modal_mobile_sheet]" value="1" <?php checked( ! empty( $settings['modal_mobile_sheet'] ), true ); ?> />
										<span class="signa-slider"></span>
									</label>
								</div>

								<!-- Standalone Full-Page Canvas Settings -->
								<div style="margin-top:20px;padding-top:18px;border-top:1px solid var(--s-border);">
									<label class="signa-section-label">ب) قالب تمام‌صفحه اختصاصی بدون هدر و فوتر قالب (Standalone Full-Page Canvas)</label>
									<div class="signa-fields-grid signa-cols-2">
										<div class="signa-field">
											<label for="standalone_page_id">برگه ورود تمام‌صفحه (حذف خودکار هدر و فوتر قالب)</label>
											<select name="signa[standalone_page_id]" id="standalone_page_id">
												<option value="0" <?php selected( (int) $settings['standalone_page_id'], 0 ); ?>>غیرفعال (فقط روی wp-login.php اعمال شود)</option>
												<?php if ( ! empty( $wp_pages ) ) : ?>
													<?php foreach ( $wp_pages as $p_obj ) : ?>
														<option value="<?php echo esc_attr( (string) $p_obj->ID ); ?>" <?php selected( (int) $settings['standalone_page_id'], (int) $p_obj->ID ); ?>>
															<?php echo esc_html( $p_obj->post_title . ' (ID: ' . $p_obj->ID . ')' ); ?>
														</option>
													<?php endforeach; ?>
												<?php endif; ?>
											</select>
											<small>برگه انتخابی بدون منو، هدر و فوتر قالب به صورت صفحه ورود تمام‌صفحه نمایش داده می‌شود.</small>
										</div>

										<div class="signa-field">
											<label for="canvas_bg_style">استایل پس‌زمینه محیط تمام‌صفحه</label>
											<select name="signa[canvas_bg_style]" id="canvas_bg_style">
												<option value="mesh_light" <?php selected( $canvas_bg, 'mesh_light' ); ?>>گرادینت نوری روشن (Mesh Light — پیش‌فرض)</option>
												<option value="mesh_dark" <?php selected( $canvas_bg, 'mesh_dark' ); ?>>کهکشانی تیره و لوکس (Dark Aurora)</option>
												<option value="brand_gradient" <?php selected( $canvas_bg, 'brand_gradient' ); ?>>گرادینت هماهنگ با رنگ اصلی برند</option>
												<option value="solid" <?php selected( $canvas_bg, 'solid' ); ?>>رنگ یکدست سفارشی</option>
												<option value="custom_image" <?php selected( $canvas_bg, 'custom_image' ); ?>>تصویر پس‌زمینه تمام‌صفحه (Custom Image)</option>
											</select>
										</div>
									</div>

									<div class="signa-fields-grid signa-cols-2" style="margin-top:12px;">
										<div class="signa-field" id="signa-canvas-color-wrap" style="<?php echo in_array( $canvas_bg, array( 'solid', 'mesh_light' ), true ) ? '' : 'display:none;'; ?>">
											<label for="canvas_bg_color">رنگ پس‌زمینه صفحه</label>
											<div class="signa-color-input-wrap">
												<input type="color" name="signa[canvas_bg_color]" id="canvas_bg_color" value="<?php echo esc_attr( $settings['canvas_bg_color'] ); ?>" />
												<span id="canvas_bg_color_hex"><?php echo esc_html( $settings['canvas_bg_color'] ); ?></span>
											</div>
										</div>

										<div class="signa-field" id="signa-canvas-image-wrap" style="<?php echo 'custom_image' === $canvas_bg ? '' : 'display:none;'; ?>grid-column:span 2;">
											<label for="canvas_bg_image">تصویر پس‌زمینه تمام‌صفحه</label>
											<div style="display:flex;gap:8px;">
												<input type="text" name="signa[canvas_bg_image]" id="canvas_bg_image" value="<?php echo esc_attr( $settings['canvas_bg_image'] ); ?>" dir="ltr" placeholder="https://example.com/fullpage-bg.jpg" style="flex:1;" />
												<button type="button" id="signa_upload_canvas_bg_btn" class="signa-btn-secondary">انتخاب از رسانه</button>
											</div>
										</div>
									</div>

									<div class="signa-switch-row" style="margin-top:12px;">
										<div class="signa-switch-text">
											<strong>نمایش دکمه شناور «بازگشت به صفحه اصلی سایت»</strong>
											<p>در صفحه ورود تمام‌صفحه، لینک بازگشت سریع به صفحه اول سایت نمایش داده شود.</p>
										</div>
										<label class="signa-switch">
											<input type="checkbox" name="signa[canvas_show_back_link]" value="1" <?php checked( ! empty( $settings['canvas_show_back_link'] ), true ); ?> />
											<span class="signa-slider"></span>
										</label>
									</div>
								</div>
								</div>
							</div>

							<!-- CARD 2.5 (CATEGORY 2): Glassmorphism, Shadows, Borders, Gradients & SVG Background Patterns -->
							<div class="signa-card signa-accordion-card" style="margin-bottom:16px;">
								<div class="signa-card-head signa-accordion-trigger">
									<div class="signa-card-head-title">
										<span class="signa-card-icon is-amber">
											<svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.1" stroke-linecap="round" stroke-linejoin="round"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg>
										</span>
										<div>
											<h2>۳. استایل کارت، افکت شیشه‌ای (Glassmorphism) و سایه‌ها</h2>
											<p>شخصی‌سازی افکت شیشه‌ای مات، عمق سایه، کادر دور کارت، گرادینت دکمه و پترن گرافیکی پس‌زمینه</p>
										</div>
									</div>
									<div style="display:flex;align-items:center;gap:10px;">
										<span class="signa-pill is-ok">افکت‌های مدرن</span>
										<span class="signa-accordion-chevron"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 12 15 18 9"/></svg></span>
									</div>
								</div>
								<div class="signa-accordion-body" style="display:none;">

								<!-- 1. Glassmorphism Switch & Sliders -->
								<div class="signa-switch-row">
									<div class="signa-switch-text">
										<strong>افکت شیشه‌ای مات (Glassmorphism / Frosted Glass)</strong>
										<p>شفاف شدن پس‌زمینه کارت به همراه تار شدن نوری تصویر یا گرادینت پشت کارت (Backdrop Blur).</p>
									</div>
									<label class="signa-switch">
										<input type="checkbox" name="signa[glassmorphism]" id="glassmorphism" value="1" <?php checked( ! empty( $settings['glassmorphism'] ), true ); ?> />
										<span class="signa-slider"></span>
									</label>
								</div>

								<div id="signa-glassmorphism-controls" style="margin-top:12px;padding:16px;border-radius:12px;background:var(--s-bg-subtle);border:1px solid var(--s-border-input);<?php echo ! empty( $settings['glassmorphism'] ) ? '' : 'display:none;'; ?>">
									<div class="signa-fields-grid signa-cols-2">
										<div class="signa-field">
											<label for="card_bg_opacity">شفافیت پس‌زمینه کارت (Opacity): <strong id="opacity_val_label"><?php echo esc_html( (string) $settings['card_bg_opacity'] ); ?>%</strong></label>
											<input type="range" name="signa[card_bg_opacity]" id="card_bg_opacity" min="25" max="100" value="<?php echo esc_attr( (string) $settings['card_bg_opacity'] ); ?>" />
										</div>
										<div class="signa-field">
											<label for="backdrop_blur">شدت ماتی شیشه (Backdrop Blur): <strong id="blur_val_label"><?php echo esc_html( (string) $settings['backdrop_blur'] ); ?>px</strong></label>
											<input type="range" name="signa[backdrop_blur]" id="backdrop_blur" min="0" max="32" value="<?php echo esc_attr( (string) $settings['backdrop_blur'] ); ?>" />
										</div>
									</div>
								</div>

								<!-- 2. Card Shadow, Border Style & Inner Padding -->
								<div class="signa-fields-grid signa-cols-3" style="margin-top:18px;">
									<div class="signa-field">
										<label for="card_shadow">عمق سایه کارت (Elevation)</label>
										<select name="signa[card_shadow]" id="card_shadow">
											<option value="none" <?php selected( $card_shadow, 'none' ); ?>>بدون سایه (Flat)</option>
											<option value="soft" <?php selected( $card_shadow, 'soft' ); ?>>سایه ملایم و ظریف (Soft)</option>
											<option value="medium" <?php selected( $card_shadow, 'medium' ); ?>>سایه استاندارد مدرن (Medium)</option>
											<option value="deep" <?php selected( $card_shadow, 'deep' ); ?>>سایه عمیق و معلق (Deep 3D)</option>
											<option value="glow" <?php selected( $card_shadow, 'glow' ); ?>>هاله نوری همرنگ برند (Brand Glow)</option>
										</select>
									</div>

									<div class="signa-field">
										<label for="card_border_style">استایل کادر دور کارت</label>
										<select name="signa[card_border_style]" id="card_border_style">
											<option value="subtle" <?php selected( $card_border, 'subtle' ); ?>>کادر ظریف استاندارد (1px)</option>
											<option value="none" <?php selected( $card_border, 'none' ); ?>>بدون کادر (Borderless)</option>
											<option value="top_accent" <?php selected( $card_border, 'top_accent' ); ?>>نوار رنگی برجسته بالای کارت (Top Bar)</option>
											<option value="glow" <?php selected( $card_border, 'glow' ); ?>>کادر درخشان همرنگ برند (Glowing)</option>
										</select>
									</div>

									<div class="signa-field">
										<label for="card_padding">فاصله داخلی کارت (Padding): <strong id="padding_val_label"><?php echo esc_html( (string) $settings['card_padding'] ); ?>px</strong></label>
										<input type="range" name="signa[card_padding]" id="card_padding" min="20" max="48" value="<?php echo esc_attr( (string) $settings['card_padding'] ); ?>" />
									</div>
								</div>

								<!-- 3. Dual-Tone Button Gradient & Secondary Brand Color -->
								<div style="margin-top:20px;padding-top:18px;border-top:1px solid var(--s-border);">
									<label class="signa-section-label">استایل رنگ دکمه‌ها و هدر کارت (تک‌رنگ یا گرادینت دو رنگ)</label>
									<div class="signa-fields-grid signa-cols-2">
										<div class="signa-field">
											<label for="button_bg_mode">حالت رنگ دکمه اصلی</label>
											<select name="signa[button_bg_mode]" id="button_bg_mode">
												<option value="solid" <?php selected( $button_bg_mode, 'solid' ); ?>>تک‌رنگ یکدست کلاسیک (Solid Color)</option>
												<option value="gradient" <?php selected( $button_bg_mode, 'gradient' ); ?>>گرادینت دو رنگ مدرن (Linear Gradient 135°)</option>
											</select>
										</div>

										<div class="signa-field" id="signa-secondary-color-wrap" style="<?php echo 'gradient' === $button_bg_mode ? '' : 'opacity:0.65;'; ?>">
											<label for="secondary_color">رنگ دوم گرادینت دکمه و المان‌ها</label>
											<div class="signa-color-input-wrap">
												<input type="color" name="signa[secondary_color]" id="secondary_color" value="<?php echo esc_attr( $settings['secondary_color'] ); ?>" />
												<span id="secondary_color_hex"><?php echo esc_html( $settings['secondary_color'] ); ?></span>
											</div>
										</div>
									</div>
								</div>

								<!-- 4. SVG Background Patterns -->
								<div style="margin-top:20px;padding-top:18px;border-top:1px solid var(--s-border);">
									<label class="signa-section-label">پترن و بافت گرافیکی پس‌زمینه (SVG Background Pattern)</label>
									<div class="signa-choice-grid signa-cols-3">
										<label class="signa-choice-card <?php echo 'none' === $bg_pattern ? 'selected' : ''; ?>">
											<input type="radio" name="signa[bg_pattern]" value="none" <?php checked( $bg_pattern, 'none' ); ?> />
											<div class="signa-mini-video" style="height:56px;background:radial-gradient(circle at top right, #1e293b, #0f172a);">
												<span class="signa-mini-video-badge">پیشنمایش</span>
											</div>
											<div class="signa-choice-card-top">
												<span class="signa-flow-node is-phone">
													<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><rect x="3" y="3" width="18" height="18" rx="2"/></svg>
												</span>
												<span class="signa-choice-check"><?php echo $svg_check_mark; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
											</div>
											<div class="signa-choice-body">
												<strong>ساده (بدون پترن)</strong>
												<small>پس‌زمینه صاف و یکدست بدون بافت</small>
											</div>
										</label>

										<label class="signa-choice-card <?php echo 'dots' === $bg_pattern ? 'selected' : ''; ?>">
											<input type="radio" name="signa[bg_pattern]" value="dots" <?php checked( $bg_pattern, 'dots' ); ?> />
											<div class="signa-mini-video" style="height:56px;background:radial-gradient(rgba(96,165,250,0.45) 1.5px, transparent 1.5px), #0f172a;background-size:12px 12px, auto;">
												<span class="signa-mini-video-badge">پیشنمایش</span>
											</div>
											<div class="signa-choice-card-top">
												<span class="signa-flow-node is-sms">
													<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="6" cy="6" r="1"/><circle cx="12" cy="6" r="1"/><circle cx="18" cy="6" r="1"/><circle cx="6" cy="12" r="1"/><circle cx="12" cy="12" r="1"/><circle cx="18" cy="12" r="1"/><circle cx="6" cy="18" r="1"/><circle cx="12" cy="18" r="1"/><circle cx="18" cy="18" r="1"/></svg>
												</span>
												<div class="signa-choice-top-left">
													<span class="signa-choice-tag is-blue">مدرن</span>
													<span class="signa-choice-check"><?php echo $svg_check_mark; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
												</div>
											</div>
											<div class="signa-choice-body">
												<strong>ماتریس نقطه‌ای (Dots)</strong>
												<small>نقطه‌های ظریف و منظم به سبک پنل‌های SaaS</small>
											</div>
										</label>

										<label class="signa-choice-card <?php echo 'grid' === $bg_pattern ? 'selected' : ''; ?>">
											<input type="radio" name="signa[bg_pattern]" value="grid" <?php checked( $bg_pattern, 'grid' ); ?> />
											<div class="signa-mini-video" style="height:56px;background:linear-gradient(to right, rgba(148,163,184,0.25) 1px, transparent 1px), linear-gradient(to bottom, rgba(148,163,184,0.25) 1px, transparent 1px), #0f172a;background-size:14px 14px, 14px 14px, auto;">
												<span class="signa-mini-video-badge">پیشنمایش</span>
											</div>
											<div class="signa-choice-card-top">
												<span class="signa-flow-node is-passkey">
													<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="18" height="18" rx="2"/><line x1="3" y1="9" x2="21" y2="9"/><line x1="3" y1="15" x2="21" y2="15"/><line x1="9" y1="3" x2="9" y2="21"/><line x1="15" y1="3" x2="15" y2="21"/></svg>
												</span>
												<span class="signa-choice-check"><?php echo $svg_check_mark; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
											</div>
											<div class="signa-choice-body">
												<strong>شبکه‌ای مهندسی (Micro-Grid)</strong>
												<small>خطوط شطرنجی بسیار ظریف و مدرن</small>
											</div>
										</label>

										<label class="signa-choice-card <?php echo 'waves' === $bg_pattern ? 'selected' : ''; ?>">
											<input type="radio" name="signa[bg_pattern]" value="waves" <?php checked( $bg_pattern, 'waves' ); ?> />
											<div class="signa-mini-video" style="height:56px;background:repeating-radial-gradient(circle at 0 0, transparent 0, rgba(96,165,250,0.18) 10px, transparent 20px), #0f172a;">
												<span class="signa-mini-video-badge">پیشنمایش</span>
											</div>
											<div class="signa-choice-card-top">
												<span class="signa-flow-node is-email">
													<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path d="M2 6c.6.5 1.2 1 2.5 1C7 7 7 5 9.5 5c2.6 0 2.4 2 5 2 2.5 0 2.5-2 5-2 1.3 0 1.9.5 2.5 1"/><path d="M2 12c.6.5 1.2 1 2.5 1 2.5 0 2.5-2 5-2 2.6 0 2.4 2 5 2 2.5 0 2.5-2 5-2 1.3 0 1.9.5 2.5 1"/><path d="M2 18c.6.5 1.2 1 2.5 1 2.5 0 2.5-2 5-2 2.6 0 2.4 2 5 2 2.5 0 2.5-2 5-2 1.3 0 1.9.5 2.5 1"/></svg>
												</span>
												<span class="signa-choice-check"><?php echo $svg_check_mark; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
											</div>
											<div class="signa-choice-body">
												<strong>موج‌های موازی (Waves)</strong>
												<small>بافت خطوط منحنی و موجی در پس‌زمینه</small>
											</div>
										</label>

										<label class="signa-choice-card <?php echo 'geometric' === $bg_pattern ? 'selected' : ''; ?>">
											<input type="radio" name="signa[bg_pattern]" value="geometric" <?php checked( $bg_pattern, 'geometric' ); ?> />
											<div class="signa-mini-video" style="height:56px;background:linear-gradient(30deg, rgba(96,165,250,0.22) 12%, transparent 12.5%, transparent 87%, rgba(96,165,250,0.22) 87.5%), #0f172a;background-size:20px 34px, auto;">
												<span class="signa-mini-video-badge">پیشنمایش</span>
											</div>
											<div class="signa-choice-card-top">
												<span class="signa-flow-node is-phone">
													<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><polygon points="12 2 2 7 12 12 22 7 12 2"/><polyline points="2 17 12 22 22 17"/><polyline points="2 12 12 17 22 12"/></svg>
												</span>
												<span class="signa-choice-check"><?php echo $svg_check_mark; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
											</div>
											<div class="signa-choice-body">
												<strong>هندسی الماسی (Geometric)</strong>
												<small>الگوی چندضلعی ظریف روی محیط پس‌زمینه</small>
											</div>
										</label>
									</div>
								</div>
								</div>
							</div>

							<!-- CARD 2.8 (CATEGORY 3): Typography, Fonts & Input Field Styles -->
							<div class="signa-card signa-accordion-card" style="margin-bottom:16px;">
								<div class="signa-card-head signa-accordion-trigger">
									<div class="signa-card-head-title">
										<span class="signa-card-icon is-cyan">
											<svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.1" stroke-linecap="round" stroke-linejoin="round"><polyline points="4 7 4 4 20 4 20 7"/><line x1="9" y1="20" x2="15" y2="20"/><line x1="12" y1="4" x2="12" y2="20"/></svg>
										</span>
										<div>
											<h2>۴. تایپوگرافی، فونت فارسی و استایل فیلدهای ورودی</h2>
											<p>انتخاب فونت، اندازه متون، طراحی کادر فیلدهای ورودی و پیش‌شماره/پرچم کشور</p>
										</div>
									</div>
									<div style="display:flex;align-items:center;gap:10px;">
										<span class="signa-pill is-info">تایپوگرافی و فیلدها</span>
										<span class="signa-accordion-chevron"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 12 15 18 9"/></svg></span>
									</div>
								</div>
								<div class="signa-accordion-body" style="display:none;">

								<!-- 1. Font Family & Text Size Sliders -->
								<div class="signa-fields-grid signa-cols-2">
									<div class="signa-field">
										<label for="font_family">خانواده فونت فرم ورود (Font Family)</label>
										<select name="signa[font_family]" id="font_family">
											<option value="vazirmatn" <?php selected( $font_family, 'vazirmatn' ); ?>>وزیرمتن (Vazirmatn — پیش‌فرض استاندارد وب)</option>
											<option value="iransans" <?php selected( $font_family, 'iransans' ); ?>>ایران‌سنس (IRANSans / IRANSansX)</option>
											<option value="yekanbakh" <?php selected( $font_family, 'yekanbakh' ); ?>>یکان‌بخ / ایران‌یکان (YekanBakh / IRANYekan)</option>
											<option value="dana" <?php selected( $font_family, 'dana' ); ?>>دانا / انجمن (Dana / Anjoman)</option>
											<option value="estedad" <?php selected( $font_family, 'estedad' ); ?>>استعداد / شبنم (Estedad / Shabnam)</option>
											<option value="theme_inherit" <?php selected( $font_family, 'theme_inherit' ); ?>>ارث‌بری خودکار از فونت قالب وردپرس (Inherit)</option>
										</select>
									</div>

									<div class="signa-field">
										<label for="input_addon_style">نمایش آیکون یا پیش‌شماره کنار فیلد موبایل</label>
										<select name="signa[input_addon_style]" id="input_addon_style">
											<option value="icon" <?php selected( $input_addon, 'icon' ); ?>>آیکون هوشمند موبایل / ایمیل (پیش‌فرض)</option>
											<option value="ir_flag" <?php selected( $input_addon, 'ir_flag' ); ?>>پرچم ایران 🇮🇷 و پیش‌شماره (+98)</option>
											<option value="none" <?php selected( $input_addon, 'none' ); ?>>ساده و بدون آیکون داخل فیلد</option>
										</select>
									</div>
								</div>

								<div class="signa-fields-grid signa-cols-3" style="margin-top:16px;">
									<div class="signa-field">
										<label for="title_font_size">اندازه تیتر اصلی: <strong id="title_size_val_label"><?php echo esc_html( (string) $settings['title_font_size'] ); ?>px</strong></label>
										<input type="range" name="signa[title_font_size]" id="title_font_size" min="16" max="28" value="<?php echo esc_attr( (string) $settings['title_font_size'] ); ?>" />
									</div>

									<div class="signa-field">
										<label for="subtitle_font_size">اندازه زیرعنوان: <strong id="subtitle_size_val_label"><?php echo esc_html( (string) $settings['subtitle_font_size'] ); ?>px</strong></label>
										<input type="range" name="signa[subtitle_font_size]" id="subtitle_font_size" min="12" max="17" value="<?php echo esc_attr( (string) $settings['subtitle_font_size'] ); ?>" />
									</div>

									<div class="signa-field">
										<label for="btn_font_size">اندازه متن دکمه‌ها: <strong id="btn_size_val_label"><?php echo esc_html( (string) $settings['btn_font_size'] ); ?>px</strong></label>
										<input type="range" name="signa[btn_font_size]" id="btn_font_size" min="13" max="18" value="<?php echo esc_attr( (string) $settings['btn_font_size'] ); ?>" />
									</div>
								</div>

								<!-- 2. Input Field Visual Styles (with Mini-Video Previews) -->
								<div style="margin-top:20px;padding-top:18px;border-top:1px solid var(--s-border);">
									<label class="signa-section-label">استایل ظاهری کادر فیلدهای ورودی (Input Field Style)</label>
									<div class="signa-choice-grid signa-cols-2">
										<label class="signa-choice-card <?php echo 'filled' === $input_style ? 'selected' : ''; ?>">
											<input type="radio" name="signa[input_style]" value="filled" <?php checked( $input_style, 'filled' ); ?> />
											<div class="signa-mini-video" style="height:62px;">
												<span class="signa-mini-video-badge">پیشنمایش</span>
												<div style="width:130px;height:28px;border-radius:7px;background:#f8fafc;border:1.5px solid #3b82f6;display:flex;align-items:center;padding:0 8px;">
													<span style="width:65%;height:5px;border-radius:3px;background:#64748b;"></span>
												</div>
											</div>
											<div class="signa-choice-card-top">
												<span class="signa-flow-node is-phone">
													<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><rect x="3" y="6" width="18" height="12" rx="3" fill="currentColor" fill-opacity="0.18"/></svg>
												</span>
												<div class="signa-choice-top-left">
													<span class="signa-choice-tag is-blue">پیش‌فرض</span>
													<span class="signa-choice-check"><?php echo $svg_check_mark; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
												</div>
											</div>
											<div class="signa-choice-body">
												<strong>کادر توپر مدرن (Filled Box)</strong>
												<small>پس‌زمینه ملایم به همراه کادر دور استاندارد</small>
											</div>
										</label>

										<label class="signa-choice-card <?php echo 'outlined' === $input_style ? 'selected' : ''; ?>">
											<input type="radio" name="signa[input_style]" value="outlined" <?php checked( $input_style, 'outlined' ); ?> />
											<div class="signa-mini-video" style="height:62px;">
												<span class="signa-mini-video-badge">پیشنمایش</span>
												<div style="width:130px;height:28px;border-radius:7px;background:transparent;border:1.5px solid #60a5fa;display:flex;align-items:center;padding:0 8px;">
													<span style="width:65%;height:5px;border-radius:3px;background:#93c5fd;"></span>
												</div>
											</div>
											<div class="signa-choice-card-top">
												<span class="signa-flow-node is-sms">
													<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><rect x="3" y="6" width="18" height="12" rx="3"/></svg>
												</span>
												<span class="signa-choice-check"><?php echo $svg_check_mark; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
											</div>
											<div class="signa-choice-body">
												<strong>کادر خطی شفاف (Outlined Clean)</strong>
												<small>پس‌زمینه شفاف همرنگ کارت با حاشیه ظریف</small>
											</div>
										</label>

										<label class="signa-choice-card <?php echo 'underlined' === $input_style ? 'selected' : ''; ?>">
											<input type="radio" name="signa[input_style]" value="underlined" <?php checked( $input_style, 'underlined' ); ?> />
											<div class="signa-mini-video" style="height:62px;">
												<span class="signa-mini-video-badge">پیشنمایش</span>
												<div style="width:130px;height:28px;border-bottom:2.5px solid #38bdf8;display:flex;align-items:center;padding:0 4px;">
													<span style="width:65%;height:5px;border-radius:3px;background:#cbd5e1;"></span>
												</div>
											</div>
											<div class="signa-choice-card-top">
												<span class="signa-flow-node is-passkey">
													<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><line x1="3" y1="18" x2="21" y2="18"/><line x1="6" y1="11" x2="14" y2="11"/></svg>
												</span>
												<span class="signa-choice-check"><?php echo $svg_check_mark; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
											</div>
											<div class="signa-choice-body">
												<strong>خط زیرین مینیمال (Underlined)</strong>
												<small>فقط خط رنگی در پایین فیلد به سبک متریال</small>
											</div>
										</label>

										<label class="signa-choice-card <?php echo 'soft_pill' === $input_style ? 'selected' : ''; ?>">
											<input type="radio" name="signa[input_style]" value="soft_pill" <?php checked( $input_style, 'soft_pill' ); ?> />
											<div class="signa-mini-video" style="height:62px;">
												<span class="signa-mini-video-badge">پیشنمایش</span>
												<div style="width:130px;height:28px;border-radius:99px;background:rgba(241,245,249,0.95);display:flex;align-items:center;padding:0 12px;">
													<span style="width:65%;height:5px;border-radius:99px;background:#64748b;"></span>
												</div>
											</div>
											<div class="signa-choice-card-top">
												<span class="signa-flow-node is-email">
													<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><rect x="2" y="6" width="20" height="12" rx="6"/></svg>
												</span>
												<span class="signa-choice-check"><?php echo $svg_check_mark; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
											</div>
											<div class="signa-choice-body">
												<strong>کپسولی گرد (Soft Pill)</strong>
												<small>فیلد و دکمه‌های کاملاً گرد و مدرن</small>
											</div>
										</label>
									</div>

									<div class="signa-fields-grid signa-cols-3" style="margin-top:16px;">
										<div class="signa-field">
											<label for="input_height">ارتفاع فیلدها و دکمه: <strong id="input_height_val_label"><?php echo esc_html( (string) $settings['input_height'] ); ?>px</strong></label>
											<input type="range" name="signa[input_height]" id="input_height" min="42" max="58" value="<?php echo esc_attr( (string) $settings['input_height'] ); ?>" />
										</div>

										<div class="signa-field">
											<label for="input_bg_color">رنگ پس‌زمینه فیلد ورودی</label>
											<div class="signa-color-input-wrap">
												<input type="color" name="signa[input_bg_color]" id="input_bg_color" value="<?php echo esc_attr( $settings['input_bg_color'] ); ?>" />
												<span id="input_bg_color_hex"><?php echo esc_html( $settings['input_bg_color'] ); ?></span>
											</div>
										</div>

										<div class="signa-field">
											<label for="input_border_color">رنگ کادر دور فیلد ورودی</label>
											<div class="signa-color-input-wrap">
												<input type="color" name="signa[input_border_color]" id="input_border_color" value="<?php echo esc_attr( $settings['input_border_color'] ); ?>" />
												<span id="input_border_color_hex"><?php echo esc_html( $settings['input_border_color'] ); ?></span>
											</div>
										</div>
									</div>
								</div>
								</div>
							</div>

							<!-- CARD 2.9 (CATEGORY 4): OTP Digit Boxes, Countdown Timer Styles & Micro-Animations -->
							<?php
							$digit_box_style    = isset( $settings['digit_box_style'] ) ? $settings['digit_box_style'] : 'box';
							$digit_box_size     = isset( $settings['digit_box_size'] ) ? absint( $settings['digit_box_size'] ) : 48;
							$digit_box_gap      = isset( $settings['digit_box_gap'] ) ? absint( $settings['digit_box_gap'] ) : 8;
							$timer_style        = isset( $settings['timer_style'] ) ? $settings['timer_style'] : 'progress_bar';
							$form_animation     = isset( $settings['form_animation'] ) ? $settings['form_animation'] : 'fade_up';
							$otp_auto_submit    = ! empty( $settings['otp_auto_submit'] );
							$error_shake_effect = ! empty( $settings['error_shake_effect'] );
							?>
							<div class="signa-card signa-accordion-card" style="margin-bottom:16px;">
								<div class="signa-card-head signa-accordion-trigger">
									<div class="signa-card-head-title">
										<span class="signa-card-icon is-green">
											<svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.1" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="6" width="5" height="12" rx="1.5"/><rect x="9.5" y="6" width="5" height="12" rx="1.5"/><rect x="17" y="6" width="5" height="12" rx="1.5"/></svg>
										</span>
										<div>
											<h2>۵. باکس‌های کد تایید (OTP)، تایمر شمارش معکوس و انیمیشن‌ها</h2>
											<p>طراحی خانه‌های ورود کد یکبارمصرف، استایل گرافیکی تایمر ارسال مجدد و انیمیشن‌های تعاملی فرم</p>
										</div>
									</div>
									<div style="display:flex;align-items:center;gap:10px;">
										<span class="signa-pill is-ok">مرحله ۲ و انیمیشن</span>
										<span class="signa-accordion-chevron"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 12 15 18 9"/></svg></span>
									</div>
								</div>
								<div class="signa-accordion-body" style="display:none;">

									<!-- 1. OTP Digit Box Styles with Animated Mini-Video Previews -->
									<label class="signa-section-label">الف) استایل ظاهری خانه‌های کد تایید (OTP Digit Boxes)</label>
									<div class="signa-choice-grid signa-cols-3">
										<label class="signa-choice-card <?php echo 'box' === $digit_box_style ? 'selected' : ''; ?>">
											<input type="radio" name="signa[digit_box_style]" class="signa-cat4-control" value="box" <?php checked( $digit_box_style, 'box' ); ?> />
											<div class="signa-mini-video">
												<span class="signa-mini-video-badge">پیشنمایش</span>
												<div style="display:flex;gap:5px;direction:ltr;">
													<span style="width:22px;height:26px;border-radius:6px;background:#f8fafc;border:1.5px solid #cbd5e1;color:#0f172a;font-size:11px;font-weight:800;display:inline-flex;align-items:center;justify-content:center;">5</span>
													<span style="width:22px;height:26px;border-radius:6px;background:#f8fafc;border:1.5px solid #cbd5e1;color:#0f172a;font-size:11px;font-weight:800;display:inline-flex;align-items:center;justify-content:center;">8</span>
													<span class="signa-mini-digit-active" style="width:22px;height:26px;border-radius:6px;background:#ffffff;border:2px solid #38bdf8;color:#0f172a;font-size:11px;font-weight:800;display:inline-flex;align-items:center;justify-content:center;">2</span>
													<span style="width:22px;height:26px;border-radius:6px;background:rgba(248,250,252,0.2);border:1.5px solid #64748b;"></span>
												</div>
											</div>
											<div class="signa-choice-body">
												<strong>مربعی مدرن (Modern Box)</strong>
												<small>خانه‌های مجزا با گوشه‌های گرد استاندارد</small>
											</div>
										</label>

										<label class="signa-choice-card <?php echo 'underline' === $digit_box_style ? 'selected' : ''; ?>">
											<input type="radio" name="signa[digit_box_style]" class="signa-cat4-control" value="underline" <?php checked( $digit_box_style, 'underline' ); ?> />
											<div class="signa-mini-video">
												<span class="signa-mini-video-badge">پیشنمایش</span>
												<div style="display:flex;gap:6px;direction:ltr;">
													<span style="width:20px;height:26px;border-bottom:2.5px solid #38bdf8;color:#f8fafc;font-size:12px;font-weight:800;display:inline-flex;align-items:center;justify-content:center;">5</span>
													<span style="width:20px;height:26px;border-bottom:2.5px solid #38bdf8;color:#f8fafc;font-size:12px;font-weight:800;display:inline-flex;align-items:center;justify-content:center;">8</span>
													<span class="signa-mini-digit-active" style="width:20px;height:26px;border-bottom:2.5px solid #10b981;color:#38bdf8;font-size:12px;font-weight:800;display:inline-flex;align-items:center;justify-content:center;">2</span>
													<span style="width:20px;height:26px;border-bottom:2px solid #64748b;"></span>
												</div>
											</div>
											<div class="signa-choice-body">
												<strong>خط تیره پایین (Underline)</strong>
												<small>مینیمال با خط شاخص در پایین هر رقم</small>
											</div>
										</label>

										<label class="signa-choice-card <?php echo 'pill' === $digit_box_style ? 'selected' : ''; ?>">
											<input type="radio" name="signa[digit_box_style]" class="signa-cat4-control" value="pill" <?php checked( $digit_box_style, 'pill' ); ?> />
											<div class="signa-mini-video">
												<span class="signa-mini-video-badge">پیشنمایش</span>
												<div style="display:flex;gap:5px;direction:ltr;">
													<span style="width:22px;height:26px;border-radius:99px;background:#f8fafc;border:1.5px solid #cbd5e1;color:#0f172a;font-size:11px;font-weight:800;display:inline-flex;align-items:center;justify-content:center;">5</span>
													<span style="width:22px;height:26px;border-radius:99px;background:#f8fafc;border:1.5px solid #cbd5e1;color:#0f172a;font-size:11px;font-weight:800;display:inline-flex;align-items:center;justify-content:center;">8</span>
													<span class="signa-mini-digit-active" style="width:22px;height:26px;border-radius:99px;background:#ffffff;border:2px solid #38bdf8;color:#0f172a;font-size:11px;font-weight:800;display:inline-flex;align-items:center;justify-content:center;">2</span>
													<span style="width:22px;height:26px;border-radius:99px;background:rgba(248,250,252,0.2);border:1.5px solid #64748b;"></span>
												</div>
											</div>
											<div class="signa-choice-body">
												<strong>کپسولی گرد (Soft Pill)</strong>
												<small>کادرهای کاملاً گرد و منحنی</small>
											</div>
										</label>

										<label class="signa-choice-card <?php echo 'separated_glow' === $digit_box_style ? 'selected' : ''; ?>">
											<input type="radio" name="signa[digit_box_style]" class="signa-cat4-control" value="separated_glow" <?php checked( $digit_box_style, 'separated_glow' ); ?> />
											<div class="signa-mini-video">
												<span class="signa-mini-video-badge">پیشنمایش</span>
												<div style="display:flex;gap:5px;direction:ltr;">
													<span style="width:22px;height:26px;border-radius:7px;background:rgba(56,189,248,0.15);border:1.5px solid #38bdf8;box-shadow:0 0 10px rgba(56,189,248,0.45);color:#f8fafc;font-size:11px;font-weight:800;display:inline-flex;align-items:center;justify-content:center;">5</span>
													<span style="width:22px;height:26px;border-radius:7px;background:rgba(56,189,248,0.15);border:1.5px solid #38bdf8;box-shadow:0 0 10px rgba(56,189,248,0.45);color:#f8fafc;font-size:11px;font-weight:800;display:inline-flex;align-items:center;justify-content:center;">8</span>
													<span class="signa-mini-digit-active" style="width:22px;height:26px;border-radius:7px;background:rgba(16,185,129,0.2);border:1.5px solid #10b981;box-shadow:0 0 12px rgba(16,185,129,0.6);color:#6ee7b7;font-size:11px;font-weight:800;display:inline-flex;align-items:center;justify-content:center;">2</span>
													<span style="width:22px;height:26px;border-radius:7px;background:rgba(15,23,42,0.5);border:1.5px solid #475569;"></span>
												</div>
											</div>
											<div class="signa-choice-body">
												<strong>نئونی درخشان (Glow Box)</strong>
												<small>درخشش نوری رنگ برند دور خانه‌های کد</small>
											</div>
										</label>

										<label class="signa-choice-card <?php echo 'connected' === $digit_box_style ? 'selected' : ''; ?>">
											<input type="radio" name="signa[digit_box_style]" class="signa-cat4-control" value="connected" <?php checked( $digit_box_style, 'connected' ); ?> />
											<div class="signa-mini-video">
												<span class="signa-mini-video-badge">پیشنمایش</span>
												<div style="display:inline-flex;border-radius:8px;overflow:hidden;border:1.5px solid #94a3b8;background:#f8fafc;direction:ltr;">
													<span style="width:22px;height:26px;border-right:1px solid #cbd5e1;color:#0f172a;font-size:11px;font-weight:800;display:inline-flex;align-items:center;justify-content:center;">5</span>
													<span style="width:22px;height:26px;border-right:1px solid #cbd5e1;color:#0f172a;font-size:11px;font-weight:800;display:inline-flex;align-items:center;justify-content:center;">8</span>
													<span class="signa-mini-digit-active" style="width:22px;height:26px;border-right:1px solid #cbd5e1;background:#eff6ff;color:#2563eb;font-size:11px;font-weight:800;display:inline-flex;align-items:center;justify-content:center;">2</span>
													<span style="width:22px;height:26px;color:#94a3b8;font-size:11px;display:inline-flex;align-items:center;justify-content:center;">•</span>
												</div>
											</div>
											<div class="signa-choice-body">
												<strong>پیوسته یکپارچه (Connected)</strong>
												<small>نوار یکپارچه با جداکننده عمودی ظریف</small>
											</div>
										</label>
									</div>

									<!-- 2. Digit Box Size & Gap Sliders -->
									<div class="signa-fields-grid signa-cols-2" style="margin-top:18px;">
										<div class="signa-field">
											<label for="digit_box_size">اندازه و ارتفاع هر خانه کد: <strong id="digit_size_val_label"><?php echo esc_html( (string) $digit_box_size ); ?>px</strong></label>
											<input type="range" name="signa[digit_box_size]" id="digit_box_size" class="signa-cat4-control" min="38" max="58" value="<?php echo esc_attr( (string) $digit_box_size ); ?>" />
										</div>
										<div class="signa-field">
											<label for="digit_box_gap">فاصله افقی بین خانه‌های کد: <strong id="digit_gap_val_label"><?php echo esc_html( (string) $digit_box_gap ); ?>px</strong></label>
											<input type="range" name="signa[digit_box_gap]" id="digit_box_gap" class="signa-cat4-control" min="4" max="14" value="<?php echo esc_attr( (string) $digit_box_gap ); ?>" />
										</div>
									</div>

									<!-- 3. Countdown Timer Style with Animated Mini-Video Previews -->
									<div style="margin-top:22px;padding-top:18px;border-top:1px solid var(--s-border);">
										<label class="signa-section-label">ب) استایل تایمر شمارش معکوس ارسال مجدد کد (Countdown Timer)</label>
										<div class="signa-choice-grid signa-cols-2">
											<label class="signa-choice-card <?php echo 'progress_bar' === $timer_style ? 'selected' : ''; ?>">
												<input type="radio" name="signa[timer_style]" class="signa-cat4-control" value="progress_bar" <?php checked( $timer_style, 'progress_bar' ); ?> />
												<div class="signa-mini-video">
													<span class="signa-mini-video-badge">پیشنمایش</span>
													<div style="width:120px;display:flex;flex-direction:column;gap:5px;align-items:center;">
														<span style="font-size:10px;color:#e2e8f0;font-weight:700;">۰۱:۴۵ تا ارسال مجدد</span>
														<div style="width:100%;height:5px;border-radius:99px;background:rgba(148,163,184,0.25);overflow:hidden;">
															<div class="signa-mini-progress-fill" style="width:68%;height:100%;border-radius:99px;background:linear-gradient(90deg,#38bdf8,#2563eb);"></div>
														</div>
													</div>
												</div>
												<div class="signa-choice-body">
													<strong>نوار پیشرفت افقی (Progress Bar)</strong>
													<small>نوار گرادینت متحرک زیر زمان باقی‌مانده</small>
												</div>
											</label>

											<label class="signa-choice-card <?php echo 'circular_ring' === $timer_style ? 'selected' : ''; ?>">
												<input type="radio" name="signa[timer_style]" class="signa-cat4-control" value="circular_ring" <?php checked( $timer_style, 'circular_ring' ); ?> />
												<div class="signa-mini-video">
													<span class="signa-mini-video-badge">پیشنمایش</span>
													<div style="display:inline-flex;align-items:center;gap:8px;background:rgba(15,23,42,0.55);padding:5px 12px;border-radius:99px;border:1px solid rgba(56,189,248,0.3);">
														<svg width="22" height="22" viewBox="0 0 24 24" style="transform:rotate(-90deg);">
															<circle cx="12" cy="12" r="9" fill="none" stroke="rgba(148,163,184,0.25)" stroke-width="2.5"/>
															<circle class="signa-mini-ring-circle" cx="12" cy="12" r="9" fill="none" stroke="#38bdf8" stroke-width="2.5" stroke-dasharray="56.5" stroke-dashoffset="16" stroke-linecap="round"/>
														</svg>
														<span style="font-size:11px;color:#f8fafc;font-weight:800;" dir="ltr">01:45</span>
													</div>
												</div>
												<div class="signa-choice-body">
													<strong>حلقه گرافیکی دایره‌ای (Circular SVG Ring)</strong>
													<small>حلقه پروگرس دایره‌ای مدرن کنار ثانیه‌شمار</small>
												</div>
											</label>

											<label class="signa-choice-card <?php echo 'minimal_badge' === $timer_style ? 'selected' : ''; ?>">
												<input type="radio" name="signa[timer_style]" class="signa-cat4-control" value="minimal_badge" <?php checked( $timer_style, 'minimal_badge' ); ?> />
												<div class="signa-mini-video">
													<span class="signa-mini-video-badge">پیشنمایش</span>
													<div class="signa-mini-clock-tick" style="display:inline-flex;align-items:center;gap:6px;padding:5px 12px;border-radius:99px;background:rgba(59,130,246,0.18);border:1px solid rgba(59,130,246,0.4);color:#93c5fd;font-size:10.5px;font-weight:700;">
														<svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
														<span>ارسال مجدد: ۰۱:۴۵</span>
													</div>
												</div>
												<div class="signa-choice-body">
													<strong>بج کپسولی مینیمال (Minimal Pill Badge)</strong>
													<small>نمایش زمان داخل تگ کپسولی به همراه آیکون ساعت</small>
												</div>
											</label>

											<label class="signa-choice-card <?php echo 'simple_text' === $timer_style ? 'selected' : ''; ?>">
												<input type="radio" name="signa[timer_style]" class="signa-cat4-control" value="simple_text" <?php checked( $timer_style, 'simple_text' ); ?> />
												<div class="signa-mini-video">
													<span class="signa-mini-video-badge">پیشنمایش</span>
													<span style="font-size:11px;color:#cbd5e1;font-weight:600;">ارسال مجدد کد تا <strong style="color:#38bdf8;">۰۱:۴۵</strong> دیگر</span>
												</div>
												<div class="signa-choice-body">
													<strong>متن ساده کلاسیک (Simple Text)</strong>
													<small>نمایش متنی ساده بدون المان گرافیکی اضافه</small>
												</div>
											</label>
										</div>
									</div>

									<!-- 4. Form Entrance Animation & Interactive Micro-Interactions -->
									<div style="margin-top:22px;padding-top:18px;border-top:1px solid var(--s-border);">
										<label class="signa-section-label">ج) انیمیشن ورود فرم و تغییر مراحل (Form Entrance & Step Choreography)</label>
										<div class="signa-choice-grid signa-cols-2" style="margin-bottom:14px;">
											<label class="signa-choice-card <?php echo 'fade_up' === $form_animation ? 'selected' : ''; ?>">
												<input type="radio" name="signa[form_animation]" class="signa-anim-radio" value="fade_up" <?php checked( $form_animation, 'fade_up' ); ?> />
												<div class="signa-mini-video">
													<span class="signa-mini-video-badge">پیشنمایش</span>
													<div class="signa-mv-card-anim is-fade-up">
														<span class="signa-mv-line w-60"></span>
														<span class="signa-mv-line w-90"></span>
														<span class="signa-mv-btn"></span>
													</div>
												</div>
												<div class="signa-choice-body">
													<strong>ظهور نرم از پایین (Smooth Fade Up)</strong>
													<small>ورود لایه‌به‌لایه و ابریشمی کارت از پایین به بالا</small>
												</div>
											</label>

											<label class="signa-choice-card <?php echo 'zoom_spring' === $form_animation ? 'selected' : ''; ?>">
												<input type="radio" name="signa[form_animation]" class="signa-anim-radio" value="zoom_spring" <?php checked( $form_animation, 'zoom_spring' ); ?> />
												<div class="signa-mini-video">
													<span class="signa-mini-video-badge">پیشنمایش</span>
													<div class="signa-mv-card-anim is-zoom-spring">
														<span class="signa-mv-line w-60"></span>
														<span class="signa-mv-line w-90"></span>
														<span class="signa-mv-btn"></span>
													</div>
												</div>
												<div class="signa-choice-body">
													<strong>بزرگ‌نمایی فنری مدرن (Spring Scale)</strong>
													<small>بزرگ‌نمایی الاستیک با فیزیک فنری نرم به سبک iOS</small>
												</div>
											</label>

											<label class="signa-choice-card <?php echo 'slide_rtl' === $form_animation ? 'selected' : ''; ?>">
												<input type="radio" name="signa[form_animation]" class="signa-anim-radio" value="slide_rtl" <?php checked( $form_animation, 'slide_rtl' ); ?> />
												<div class="signa-mini-video">
													<span class="signa-mini-video-badge">پیشنمایش</span>
													<div class="signa-mv-card-anim is-slide-rtl">
														<span class="signa-mv-line w-60"></span>
														<span class="signa-mv-line w-90"></span>
														<span class="signa-mv-btn"></span>
													</div>
												</div>
												<div class="signa-choice-body">
													<strong>حرکت کشویی افقی (Slide Horizontal)</strong>
													<small>ورود کشویی سریع در جهت راست‌به‌چپ همراه با محو شدن</small>
												</div>
											</label>

											<label class="signa-choice-card <?php echo 'none' === $form_animation ? 'selected' : ''; ?>">
												<input type="radio" name="signa[form_animation]" class="signa-anim-radio" value="none" <?php checked( $form_animation, 'none' ); ?> />
												<div class="signa-mini-video">
													<span class="signa-mini-video-badge">پیشنمایش</span>
													<div class="signa-mv-card-anim">
														<span class="signa-mv-line w-60"></span>
														<span class="signa-mv-line w-90"></span>
														<span class="signa-mv-btn"></span>
													</div>
												</div>
												<div class="signa-choice-body">
													<strong>بدون انیمیشن (Instant)</strong>
													<small>نمایش فوری فرم بدون افکت حرکتی</small>
												</div>
											</label>
										</div>

										<div class="signa-fields-grid signa-cols-2" style="margin-bottom:16px;">
											<button type="button" id="signa-replay-entrance-btn" class="signa-btn-secondary" style="height:42px;justify-content:center;gap:8px;width:100%;">
												<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><polyline points="1 4 1 10 7 10"/><path d="M3.51 15a9 9 0 1 0 2.13-9.36L1 10"/></svg>
												<span>پخش مجدد انیمیشن ورود فرم در پیش‌نمایش</span>
											</button>
											<button type="button" id="signa-test-shake-btn" class="signa-btn-secondary" style="height:42px;justify-content:center;gap:8px;width:100%;border-color:rgba(239,68,68,0.45);color:#ef4444;background:rgba(239,68,68,0.06);">
												<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
												<span>تست زنده افکت لرزش و هشدار قرمز خطا</span>
											</button>
										</div>

										<div class="signa-switch-row" style="margin-bottom:10px;">
											<div class="signa-switch-text">
												<strong>ارسال خودکار به محض تکمیل آخرین رقم کد (Auto-Submit OTP)</strong>
												<p>به محض وارد کردن آخرین رقم کد تایید (یا خواندن خودکار پیامک توسط مرورگر)، فرم بدون نیاز به کلیک روی دکمه بررسی می‌شود.</p>
											</div>
											<label class="signa-switch">
												<input type="checkbox" name="signa[otp_auto_submit]" id="otp_auto_submit" value="1" <?php checked( $otp_auto_submit, true ); ?> />
												<span class="signa-slider"></span>
											</label>
										</div>

										<div class="signa-switch-row" style="align-items:center;gap:14px;">
											<div class="signa-mini-video" style="width:155px;height:64px;flex-shrink:0;margin:0;">
												<div class="signa-mv-error-shake-demo" style="display:flex;flex-direction:column;align-items:center;gap:5px;direction:ltr;">
													<span class="signa-mv-err-pill">کد نادرست است!</span>
													<div style="display:flex;gap:4px;">
														<span class="signa-mv-err-box">5</span>
														<span class="signa-mv-err-box">8</span>
														<span class="signa-mv-err-box">2</span>
														<span class="signa-mv-err-box">9</span>
													</div>
												</div>
											</div>
											<div class="signa-switch-text" style="flex:1;">
												<strong>افکت لرزش الاستیک و نئون قرمز هنگام کد اشتباه (Error Shake & Crimson Glow)</strong>
												<p>در صورت وارد کردن کد نادرست، خانه‌های کد با انیمیشن لرزش فنری، موج نوری قرمز و بنر هشدار متحرک به کاربر بازخورد بصری می‌دهند.</p>
											</div>
											<label class="signa-switch">
												<input type="checkbox" name="signa[error_shake_effect]" id="error_shake_effect" value="1" <?php checked( $error_shake_effect, true ); ?> />
												<span class="signa-slider"></span>
											</label>
										</div>
									</div>

								</div>
							</div>

							<!-- CARD 3: Color Presets, Card Colors & Dimensions -->
							<div class="signa-card signa-accordion-card" style="margin-bottom:16px;">
								<div class="signa-card-head signa-accordion-trigger">
									<div class="signa-card-head-title">
										<span class="signa-card-icon is-purple">
											<svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.1" stroke-linecap="round" stroke-linejoin="round"><circle cx="13.5" cy="6.5" r=".5" fill="currentColor"/><circle cx="17.5" cy="10.5" r=".5" fill="currentColor"/><circle cx="8.5" cy="7.5" r=".5" fill="currentColor"/><circle cx="6.5" cy="12.5" r=".5" fill="currentColor"/><path d="M12 2C6.5 2 2 6.5 2 12s4.5 10 10 10c.926 0 1.648-.746 1.648-1.688 0-.437-.18-.835-.437-1.125-.29-.289-.438-.652-.438-1.125a1.64 1.64 0 0 1 1.668-1.668h1.996c3.051 0 5.555-2.503 5.555-5.554C21.965 6.012 17.461 2 12 2z"/></svg>
										</span>
										<div>
											<h2>۶. پالت‌های رنگی و تم‌های آماده (Presets)</h2>
											<p>با یک کلیک استایل کلی فرم را تغییر دهید یا رنگ‌ها را سفارشی کنید (با تشخیص خودکار کنتراست تیره/روشن)</p>
										</div>
									</div>
									<div style="display:flex;align-items:center;gap:10px;">
										<span class="signa-pill is-ok">کنتراست هوشمند</span>
										<span class="signa-accordion-chevron"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 12 15 18 9"/></svg></span>
									</div>
								</div>
								<div class="signa-accordion-body" style="display:none;">

								<div class="signa-preset-grid">
									<button type="button" class="signa-preset-btn" data-primary="#2563eb" data-bg="#ffffff" data-text="#111827" data-radius="16">
										<span class="signa-swatch" style="background:#2563eb;"></span>
										آبی مدرن (پیش‌فرض)
									</button>
									<button type="button" class="signa-preset-btn" data-primary="#ef4444" data-bg="#ffffff" data-text="#111827" data-radius="12">
										<span class="signa-swatch" style="background:#ef4444;"></span>
										قرمز فروشگاهی
									</button>
									<button type="button" class="signa-preset-btn" data-primary="#10b981" data-bg="#ffffff" data-text="#0f172a" data-radius="20">
										<span class="signa-swatch" style="background:#10b981;"></span>
										سبز زمردی
									</button>
									<button type="button" class="signa-preset-btn" data-primary="#6366f1" data-bg="#1e293b" data-text="#f8fafc" data-radius="18">
										<span class="signa-swatch" style="background:#1e293b;border:2px solid #6366f1;"></span>
										تیره لوکس (Dark)
									</button>
								</div>

								<div class="signa-fields-grid signa-cols-3" style="margin-top:18px;">
									<div class="signa-field">
										<label for="primary_color">رنگ اصلی برند و دکمه</label>
										<div class="signa-color-input-wrap">
											<input type="color" name="signa[primary_color]" id="primary_color" value="<?php echo esc_attr( $settings['primary_color'] ); ?>" />
											<span id="primary_color_hex"><?php echo esc_html( $settings['primary_color'] ); ?></span>
										</div>
									</div>
									<div class="signa-field">
										<label for="card_bg_color">رنگ پس‌زمینه کارت</label>
										<div class="signa-color-input-wrap">
											<input type="color" name="signa[card_bg_color]" id="card_bg_color" value="<?php echo esc_attr( $settings['card_bg_color'] ); ?>" />
											<span id="card_bg_color_hex"><?php echo esc_html( $settings['card_bg_color'] ); ?></span>
										</div>
									</div>
									<div class="signa-field">
										<label for="text_color">رنگ متون اصلی <span id="signa-auto-contrast-badge" style="font-size:10px;font-weight:700;padding:1px 6px;border-radius:99px;background:rgba(37,99,235,0.12);color:#2563eb;margin-right:4px;">تشخیص خودکار</span></label>
										<div class="signa-color-input-wrap">
											<input type="color" name="signa[text_color]" id="text_color" value="<?php echo esc_attr( $settings['text_color'] ); ?>" />
											<span id="text_color_hex"><?php echo esc_html( $settings['text_color'] ); ?></span>
										</div>
									</div>
								</div>

								<div class="signa-fields-grid signa-cols-2" style="margin-top:16px;">
									<div class="signa-field">
										<label for="border_radius">گردی گوشه‌ها: <strong id="radius_val_label"><?php echo esc_html( (string) $settings['border_radius'] ); ?>px</strong></label>
										<input type="range" name="signa[border_radius]" id="border_radius" min="0" max="28" value="<?php echo esc_attr( (string) $settings['border_radius'] ); ?>" />
									</div>
									<div class="signa-field">
										<label for="form_max_width">حداکثر عرض کارت در سایت (px)</label>
										<input type="text" inputmode="numeric" name="signa[form_max_width]" id="form_max_width" value="<?php echo esc_attr( (string) $settings['form_max_width'] ); ?>" dir="ltr" />
									</div>
								</div>

								<div class="signa-field" style="margin-top:16px;">
									<label for="logo_url">تصویر لوگوی بالای فرم (اختیاری)</label>
									<div style="display:flex;gap:8px;">
										<input type="text" name="signa[logo_url]" id="logo_url" value="<?php echo esc_attr( $settings['logo_url'] ); ?>" dir="ltr" placeholder="https://example.com/logo.png" style="flex:1;" />
										<button type="button" id="signa_upload_logo_btn" class="signa-btn-secondary">انتخاب از رسانه</button>
									</div>
								</div>
								</div>
							</div>

							<!-- CARD 4: Form Texts & Custom CSS -->
							<div class="signa-card signa-accordion-card" style="margin-bottom:16px;">
								<div class="signa-card-head signa-accordion-trigger">
									<div class="signa-card-head-title">
										<span class="signa-card-icon is-blue">
											<svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.1" stroke-linecap="round" stroke-linejoin="round"><polyline points="4 7 4 4 20 4 20 7"/><line x1="9" y1="20" x2="15" y2="20"/><line x1="12" y1="4" x2="12" y2="20"/></svg>
										</span>
										<div>
											<h2>۷. متن‌ها و برچسب‌های فرم</h2>
											<p>عنوان‌ها و متن دکمه‌ها را متناسب با لحن برند خود تغییر دهید</p>
										</div>
									</div>
									<div style="display:flex;align-items:center;gap:10px;">
										<span class="signa-accordion-chevron"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 12 15 18 9"/></svg></span>
									</div>
								</div>
								<div class="signa-accordion-body" style="display:none;">
								<div class="signa-fields-grid signa-cols-2">
									<div class="signa-field">
										<label for="form_title">عنوان اصلی فرم</label>
										<input type="text" name="signa[form_title]" id="form_title" value="<?php echo esc_attr( $settings['form_title'] ); ?>" />
									</div>
									<div class="signa-field">
										<label for="button_text">متن دکمه مرحله اول</label>
										<input type="text" name="signa[button_text]" id="button_text" value="<?php echo esc_attr( $settings['button_text'] ); ?>" />
									</div>
									<div class="signa-field">
										<label for="form_subtitle">زیرعنوان (توضیح کوتاه)</label>
										<input type="text" name="signa[form_subtitle]" id="form_subtitle" value="<?php echo esc_attr( $settings['form_subtitle'] ); ?>" />
									</div>
									<div class="signa-field">
										<label for="verify_button_text">متن دکمه تایید کد</label>
										<input type="text" name="signa[verify_button_text]" id="verify_button_text" value="<?php echo esc_attr( $settings['verify_button_text'] ); ?>" />
									</div>
								</div>
								<div class="signa-field" style="margin-top:14px;">
									<label for="custom_css">کدهای CSS سفارشی (Custom CSS)</label>
									<textarea name="signa[custom_css]" id="custom_css" rows="3" dir="ltr" placeholder=".signa-otp-card { ... }"><?php echo esc_textarea( $settings['custom_css'] ); ?></textarea>
								</div>
								</div>
							</div>
						</div>

						<!-- Interactive Live Preview Stage (Left Sticky Column) -->
						<div class="signa-studio-preview-col">
							<div class="signa-preview-box">
								<div class="signa-preview-toolbar">
									<span style="display:inline-flex;align-items:center;gap:6px;"><svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg> پیش‌نمایش زنده پویا</span>
									<div class="signa-preview-step-btns">
										<button type="button" class="signa-prev-step-btn active" data-step="1">مرحله ۱: شماره</button>
										<button type="button" class="signa-prev-step-btn" data-step="2">مرحله ۲: کد تایید</button>
									</div>
								</div>

								<!-- Dynamic Preview Mode Sub-Bar (Page Layout vs Modal/Drawer Animation + Wide Zoom) -->
								<div class="signa-preview-subbar">
									<div class="signa-preview-mode-tabs">
										<button type="button" class="signa-prev-mode-btn active" data-mode="page">نمای صفحه ورود</button>
										<button type="button" class="signa-prev-mode-btn" data-mode="modal">تست پاپ‌آپ / کشویی</button>
									</div>
									<button type="button" class="signa-prev-expand-btn" id="signa-prev-expand-btn" title="بزرگ‌نمایی ستون پیش‌نمایش">
										<svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><polyline points="15 3 21 3 21 9"/><polyline points="9 21 3 21 3 15"/><line x1="21" y1="3" x2="14" y2="10"/><line x1="3" y1="21" x2="10" y2="14"/></svg>
										<span>بزرگ‌نمایی</span>
									</button>
								</div>

								<div class="signa-preview-canvas" id="signa-preview-canvas" style="display:flex;flex-direction:column;align-items:<?php echo 'right' === $card_position ? 'flex-start' : ( 'left' === $card_position ? 'flex-end' : 'center' ); ?>;transition:background 0.4s ease, align-items 0.25s ease;">

									<!-- Ambient Decorative Glass Orbs (Makes Backdrop-Filter Blur Slider Visibly Blur Background Shapes in Real Time) -->
									<div id="signa-prev-glass-orbs" class="signa-prev-glass-orbs" aria-hidden="true">
										<span class="signa-glass-orb orb-1"></span>
										<span class="signa-glass-orb orb-2"></span>
										<span class="signa-glass-orb orb-3"></span>
									</div>

									<!-- Simulated Website Skeleton & Backdrop (Shown in Modal/Drawer Preview Mode) -->
									<div class="signa-prev-site-skeleton" aria-hidden="true">
										<div style="height:28px;border-radius:8px;background:rgba(148,163,184,0.25);display:flex;align-items:center;justify-content:space-between;padding:0 12px;">
											<span style="width:65px;height:10px;border-radius:4px;background:rgba(59,130,246,0.45);"></span>
											<span style="width:120px;height:8px;border-radius:4px;background:rgba(148,163,184,0.35);"></span>
										</div>
										<div style="height:110px;border-radius:12px;background:rgba(148,163,184,0.18);"></div>
										<div style="display:grid;grid-template-columns:repeat(3,1fr);gap:10px;flex:1;">
											<div style="border-radius:10px;background:rgba(148,163,184,0.16);"></div>
											<div style="border-radius:10px;background:rgba(148,163,184,0.16);"></div>
											<div style="border-radius:10px;background:rgba(148,163,184,0.16);"></div>
										</div>
									</div>
									<div class="signa-prev-modal-backdrop" aria-hidden="true"></div>

									<!-- Proportional Viewport Wrapper (Scales 720px Split-Screen smoothly so text never squishes) -->
									<div id="signa-preview-viewport" class="signa-preview-viewport <?php echo $is_split ? 'is-scaled-split' : ''; ?>" style="align-items:<?php echo 'right' === $card_position ? 'flex-start' : ( 'left' === $card_position ? 'flex-end' : 'center' ); ?>;">
										<div id="signa-live-preview-shell" class="signa-prev-shell <?php echo $is_split ? 'is-split' : ''; ?> <?php echo 'split_left' === $form_layout ? 'is-split-left' : ''; ?>" style="width:100%;max-width:<?php echo $is_split ? '720px' : '360px'; ?>;border-radius:<?php echo esc_attr( (string) $settings['border_radius'] ); ?>px;overflow:hidden;box-shadow:0 16px 36px -8px rgba(15,23,42,0.16);display:flex;flex-direction:<?php echo 'split_left' === $form_layout ? 'row-reverse' : 'row'; ?>;transition:all 0.35s ease;">

											<!-- Form Column -->
											<div id="signa-live-preview-card" class="signa-prev-card" style="flex:1;min-width:0;margin:0;box-shadow:none;overflow:hidden;background-clip:padding-box;background:<?php echo esc_attr( $settings['card_bg_color'] ); ?>;color:<?php echo esc_attr( $settings['text_color'] ); ?>;border-radius:<?php echo esc_attr( (string) $settings['border_radius'] ); ?>px;transition:color 0.42s cubic-bezier(0.4, 0, 0.2, 1), background-color 0.42s cubic-bezier(0.4, 0, 0.2, 1), backdrop-filter 0.2s ease, -webkit-backdrop-filter 0.2s ease, box-shadow 0.3s ease, border-color 0.3s ease;">
												<div id="signa-prev-header-block" class="signa-stagger-el" style="text-align:center;margin-bottom:18px;">
													<div id="signa-prev-logo-wrap" style="<?php echo empty( $settings['logo_url'] ) ? 'display:none;' : ''; ?>margin-bottom:12px;">
														<img id="signa-prev-logo-img" src="<?php echo esc_url( $settings['logo_url'] ); ?>" alt="Logo" style="max-height:48px;" />
													</div>
													<div id="signa-prev-badge-icon" style="<?php echo ! empty( $settings['logo_url'] ) ? 'display:none;' : 'display:inline-flex;'; ?>width:48px;height:48px;border-radius:12px;align-items:center;justify-content:center;background:rgba(37,99,235,0.12);color:<?php echo esc_attr( $settings['primary_color'] ); ?>;margin-bottom:10px;">
														<img src="<?php echo esc_url( SIGNA_OTP_URL . 'assets/images/signa-icon.png' ); ?>" width="38" height="38" alt="Signa" style="display:block;object-fit:contain;" />
													</div>
													<h3 id="signa-prev-title" style="margin:0 0 6px 0;font-size:17px;color:inherit;transition:color 0.42s cubic-bezier(0.4, 0, 0.2, 1);"><?php echo esc_html( $settings['form_title'] ); ?></h3>
													<p id="signa-prev-subtitle" style="margin:0;font-size:12.5px;opacity:0.82;line-height:1.6;color:inherit;transition:color 0.42s cubic-bezier(0.4, 0, 0.2, 1);"><?php echo esc_html( $settings['form_subtitle'] ); ?></p>
												</div>

												<!-- Animated Error Banner Toast inside Preview Card -->
												<div id="signa-prev-error-toast" class="signa-prev-error-toast" style="display:none;">
													<span class="signa-prev-error-icon">
														<svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
													</span>
													<span>کد تایید وارد شده نادرست است. مجدداً بررسی کنید.</span>
												</div>

												<!-- Preview Step 1 -->
												<div id="signa-prev-step-1">
													<label id="signa-prev-field-label" class="signa-stagger-el" style="display:block;font-size:12.5px;font-weight:600;margin-bottom:6px;color:inherit;transition:color 0.42s cubic-bezier(0.4, 0, 0.2, 1);">شماره موبایل یا ایمیل</label>
													<div id="signa-prev-input-wrap" class="signa-stagger-el" style="position:relative;margin-bottom:16px;">
														<input type="text" class="signa-prev-input" placeholder="شماره موبایل (0912...) یا ایمیل" dir="rtl" readonly style="margin-bottom:0 !important;transition:background-color 0.38s ease, color 0.38s ease, border-color 0.38s ease;" />
														<span id="signa-prev-input-addon" style="position:absolute;left:12px;top:50%;transform:translateY(-50%);display:inline-flex;align-items:center;gap:4px;font-size:11.5px;font-weight:700;color:#64748b;pointer-events:none;transition:color 0.38s ease;" dir="ltr"></span>
													</div>
													<button type="button" id="signa-prev-btn-1" class="signa-stagger-el" style="width:100%;height:44px;border:none;border-radius:10px;background:<?php echo esc_attr( $settings['primary_color'] ); ?>;color:#fff;font-weight:700;font-size:14px;cursor:pointer;transition:all 0.35s cubic-bezier(0.22, 1, 0.36, 1);">
														<?php echo esc_html( $settings['button_text'] ); ?>
													</button>
												</div>

												<!-- Preview Step 2 -->
												<div id="signa-prev-step-2" style="display:none;">
													<div class="signa-stagger-el" style="display:flex;justify-content:space-between;background:rgba(156,163,175,0.15);padding:8px 12px;border-radius:8px;margin-bottom:14px;font-size:12px;color:inherit;transition:color 0.42s ease;">
														<strong dir="ltr">0912***6789</strong>
														<span id="signa-prev-edit-num" style="color:<?php echo esc_attr( $settings['primary_color'] ); ?>;font-weight:600;cursor:pointer;">ویرایش</span>
													</div>
													<div id="signa-prev-digits" class="signa-stagger-el" style="display:flex;justify-content:center;gap:6px;margin-bottom:14px;" dir="ltr">
														<span class="signa-prev-digit" data-idx="0">5</span>
														<span class="signa-prev-digit" data-idx="1">8</span>
														<span class="signa-prev-digit is-focused-digit" data-idx="2">2</span>
														<span class="signa-prev-digit" data-idx="3">9</span>
														<span class="signa-prev-digit" data-idx="4">1</span>
													</div>
													<div id="signa-prev-timer-box" class="signa-stagger-el" style="margin-bottom:14px;text-align:center;"></div>
													<button type="button" id="signa-prev-btn-2" class="signa-stagger-el" style="width:100%;height:44px;border:none;border-radius:10px;background:<?php echo esc_attr( $settings['primary_color'] ); ?>;color:#fff;font-weight:600;font-size:14px;cursor:pointer;transition:all 0.35s cubic-bezier(0.22, 1, 0.36, 1);">
														<?php echo esc_html( $settings['verify_button_text'] ); ?>
													</button>
												</div>
											</div>

											<!-- Split-Screen Side Banner Preview Column -->
											<div id="signa-prev-split-banner" style="<?php echo $is_split ? 'display:flex;' : 'display:none;'; ?>flex:1;min-width:0;padding:28px 24px;overflow:hidden;background-clip:padding-box;flex-direction:column;justify-content:space-between;color:#ffffff;background-color:<?php echo esc_attr( $settings['split_bg_color'] ); ?>;background-image:<?php echo ! empty( $settings['split_image_url'] ) ? 'linear-gradient(135deg, rgba(15,23,42,0.72), rgba(30,58,138,0.78)), url(' . esc_url( $settings['split_image_url'] ) . ')' : 'radial-gradient(circle at top left, rgba(255,255,255,0.16), transparent 65%)'; ?>;background-size:cover;background-position:center;transition:color 0.42s ease, background 0.42s ease;">
												<div>
													<span id="signa-prev-split-badge" style="display:inline-block;padding:4px 12px;border-radius:99px;font-size:11.5px;font-weight:700;background:rgba(255,255,255,0.18);backdrop-filter:blur(4px);margin-bottom:14px;transition:color 0.42s ease, background 0.42s ease;"><?php echo esc_html( $settings['split_badge_text'] ); ?></span>
													<h4 id="signa-prev-split-title" style="margin:0 0 10px 0;font-size:18px;font-weight:800;color:inherit;line-height:1.45;transition:color 0.42s ease;"><?php echo esc_html( $settings['split_title'] ); ?></h4>
													<p id="signa-prev-split-subtitle" style="margin:0;font-size:13px;color:inherit;opacity:0.9;line-height:1.75;transition:color 0.42s ease;"><?php echo esc_html( $settings['split_subtitle'] ); ?></p>
												</div>
												<ul id="signa-prev-split-features" style="list-style:none;margin:20px 0 0 0;padding:16px 0 0 0;border-top:1px solid rgba(255,255,255,0.16);display:flex;flex-direction:column;gap:8px;font-size:12.5px;color:inherit;opacity:0.95;transition:color 0.42s ease;">
													<?php foreach ( $split_features_list as $feat_line ) : ?>
														<li style="display:flex;align-items:center;gap:8px;margin:0;">
															<span style="display:inline-flex;width:18px;height:18px;border-radius:50%;background:rgba(16,185,129,0.28);color:#6ee7b7;align-items:center;justify-content:center;flex-shrink:0;">✓</span>
															<span><?php echo esc_html( $feat_line ); ?></span>
														</li>
													<?php endforeach; ?>
												</ul>
											</div>

										</div>
									</div>
								</div>
							</div>
						</div>
					</div>
					<script>
					jQuery(function($){
						var currentPreviewMode = 'page';
						var imgLuminanceCache = {};

						// 1. Keep Sticky Preview Box Completely Visible Below Sticky Topbar on Scroll/Resize
						function syncStickyPreviewOffset() {
							var wpBarH = ($('#wpadminbar').length && $('#wpadminbar').is(':visible')) ? ($('#wpadminbar').outerHeight() || 32) : 0;
							var topbarH = $('.signa-topbar').length ? ($('.signa-topbar').outerHeight() || 76) : 76;
							var safeTop = Math.max(118, Math.round(wpBarH + topbarH + 16));
							$('.signa-studio-preview-col').each(function(){
								this.style.setProperty('position', 'sticky', 'important');
								this.style.setProperty('top', safeTop + 'px', 'important');
							});
						}
						$(window).on('scroll resize', syncStickyPreviewOffset);
						setTimeout(syncStickyPreviewOffset, 60);
						syncStickyPreviewOffset();

						// 2. Exclusive Collapsible Accordion Cards (Closed by default; clicking one opens it and closes others)
						$(document).off('click.signaAccordion').on('click.signaAccordion', '.signa-accordion-trigger', function(e){
							e.preventDefault();
							var $card = $(this).closest('.signa-accordion-card');
							var $body = $card.children('.signa-accordion-body');
							var isAlreadyOpen = $card.hasClass('is-open');

							// Close all other open accordion cards first
							$('.signa-accordion-card.is-open').not($card).each(function(){
								$(this).removeClass('is-open').children('.signa-accordion-body').stop(true, true).slideUp(220);
							});

							if (isAlreadyOpen) {
								$card.removeClass('is-open');
								$body.stop(true, true).slideUp(220);
							} else {
								$card.addClass('is-open');
								$body.stop(true, true).slideDown(240, function(){
									syncStickyPreviewOffset();
									var wpBarH = ($('#wpadminbar').length && $('#wpadminbar').is(':visible')) ? ($('#wpadminbar').outerHeight() || 32) : 0;
									var topbarH = $('.signa-topbar').length ? ($('.signa-topbar').outerHeight() || 76) : 76;
									var minVisibleTop = wpBarH + topbarH + 14;
									var cardRect = $card[0].getBoundingClientRect();
									if (cardRect.top < minVisibleTop) {
										window.scrollTo({
											top: window.pageYOffset + cardRect.top - minVisibleTop,
											behavior: 'smooth'
										});
									}
								});
							}
						});

						function parseHexRgb(hex){
							var c = String(hex || '#ffffff').replace('#','').trim();
							if(c.length === 3){ c = c[0]+c[0]+c[1]+c[1]+c[2]+c[2]; }
							return {
								r: isNaN(parseInt(c.substring(0,2),16)) ? 255 : parseInt(c.substring(0,2),16),
								g: isNaN(parseInt(c.substring(2,4),16)) ? 255 : parseInt(c.substring(2,4),16),
								b: isNaN(parseInt(c.substring(4,6),16)) ? 255 : parseInt(c.substring(4,6),16)
							};
						}

						function hexRgba(hex, pct){
							var rgb = parseHexRgb(hex);
							var a = Math.max(0.12, Math.min(1, (parseFloat(pct)||100)/100));
							return 'rgba('+rgb.r+', '+rgb.g+', '+rgb.b+', '+a+')';
						}

						// Relative perceived luminance (0.0 = pitch black, 1.0 = pure white)
						function hexLuminance(hex){
							var rgb = parseHexRgb(hex);
							return (0.299 * rgb.r + 0.587 * rgb.g + 0.114 * rgb.b) / 255;
						}

						// Sample average image luminance asynchronously (with CORS/fallback detection)
						function sampleImageLuminance(url, cb){
							var cleanUrl = String(url || '').trim();
							if (!cleanUrl) { cb(null); return; }
							if (typeof imgLuminanceCache[cleanUrl] === 'number') {
								cb(imgLuminanceCache[cleanUrl]);
								return;
							}
							var img = new Image();
							img.crossOrigin = 'anonymous';
							img.onload = function(){
								try {
									var cv = document.createElement('canvas');
									cv.width = 24;
									cv.height = 24;
									var ctx = cv.getContext('2d');
									ctx.drawImage(img, 0, 0, 24, 24);
									var data = ctx.getImageData(0, 0, 24, 24).data;
									var sum = 0, count = 0;
									for (var i = 0; i < data.length; i += 4) {
										sum += (0.299 * data[i] + 0.587 * data[i+1] + 0.114 * data[i+2]) / 255;
										count++;
									}
									var lum = count > 0 ? (sum / count) : 0.35;
									imgLuminanceCache[cleanUrl] = lum;
									cb(lum);
								} catch (err) {
									// External URL without CORS headers: treat custom image with dark overlay as dark (0.28)
									imgLuminanceCache[cleanUrl] = 0.28;
									cb(0.28);
								}
							};
							img.onerror = function(){
								imgLuminanceCache[cleanUrl] = 0.28;
								cb(0.28);
							};
							img.src = cleanUrl;
						}

						// Smooth stage switching: when the stage type changes, fade the card out,
						// swap the layout while it is hidden, then let the entrance play.
						var lastPreviewKey = null;
						var previewSwitchTimer = null;
						function previewStateKey(){
							return currentPreviewMode === 'modal'
								? 'modal:' + ($('input[name=\"signa[modal_style]\"]:checked').val() || 'center')
								: 'page';
						}
						function requestPreviewSwitch(){
							var key = previewStateKey();
							var $shellEl = $('#signa-live-preview-shell');
							var changed = lastPreviewKey !== null && key !== lastPreviewKey && $shellEl.length;
							lastPreviewKey = key;
							if (!changed) {
								syncStudioCat2();
								return;
							}
							// Apply the new stage in one step. Modal/drawer/sheet entrances (CSS) play once from their start position;
							// page mode gets a short fade. No hidden gap, no second animation.
							syncStudioCat2();
							clearTimeout(previewSwitchTimer);
							$shellEl.removeClass('is-switch-in');
							void $shellEl[0].offsetWidth;
							$shellEl.addClass('is-switch-in');
							previewSwitchTimer = setTimeout(function(){
								$shellEl.removeClass('is-switch-in');
							}, 450);
						}

						function syncStudioCat2(e){
							var triggeredById = (e && e.target && e.target.id) ? e.target.id : '';
							var isWidePreview = $('.signa-studio-layout').hasClass('is-wide-preview');
							var primary = $('#primary_color').val() || '#2563eb';
							var secondary = $('#secondary_color').val() || '#4f46e5';
							var btnBgMode = $('#button_bg_mode').val() || 'solid';
							var bg = $('#card_bg_color').val() || '#ffffff';
							var text = $('#text_color').val() || '#111827';
							var radius = parseFloat($('#border_radius').val() || 16);
							var isGlass = $('#glassmorphism').is(':checked');
							var cardOpacity = parseFloat($('#card_bg_opacity').val() || 85);
							var blurPx = parseFloat($('#backdrop_blur').val() || 16);
							var cardShadow = $('#card_shadow').val() || 'medium';
							var cardBorder = $('#card_border_style').val() || 'subtle';
							var cardPadding = parseFloat($('#card_padding').val() || 32);
							var bgPattern = $('input[name="signa[bg_pattern]"]:checked').val() || 'none';
							var canvasBgStyle = $('#canvas_bg_style').val() || 'mesh_light';
							var canvasBgColor = $('#canvas_bg_color').val() || '#f1f5f9';
							var canvasBgImg = ($('#canvas_bg_image').val() || '').trim();
							var splitBgColor = $('#split_bg_color').val() || '#1e3a8a';
							var splitImgUrl = ($('#split_image_url').val() || '').trim();
							var splitBadge = $('#split_badge_text').val() || '';
							var splitTitle = $('#split_title').val() || '';
							var splitSub = $('#split_subtitle').val() || '';
							var splitFeatures = ($('#split_features').val() || '').split('\n');
							var logoUrl = ($('#logo_url').val() || '').trim();
							var formTitle = $('#form_title').val() || 'ورود / ثبت‌نام';
							var formSubtitle = $('#form_subtitle').val() || 'برای ادامه، شماره موبایل یا ایمیل خود را وارد کنید.';
							var btn1Text = $('#button_text').val() || 'دریافت کد تایید';
							var btn2Text = $('#verify_button_text').val() || 'تایید و ورود به حساب';
							var formLayout = $('input[name="signa[form_layout]"]:checked').val() || 'card';
							var cardPosition = $('input[name="signa[card_position]"]:checked').val() || 'center';
							var modalStyle = $('input[name="signa[modal_style]"]:checked').val() || 'center';

							// Category 3 values
							var fontKey = $('#font_family').val() || 'vazirmatn';
							var titleSize = parseFloat($('#title_font_size').val() || 20);
							var subSize = parseFloat($('#subtitle_font_size').val() || 14);
							var btnSize = parseFloat($('#btn_font_size').val() || 15);
							var inputStyle = $('input[name="signa[input_style]"]:checked').val() || 'filled';
							var inputHeight = parseFloat($('#input_height').val() || 48);
							var inputBg = $('#input_bg_color').val() || '#f8fafc';
							var inputBorder = $('#input_border_color').val() || '#d1d5db';
							var inputAddon = $('#input_addon_style').val() || 'icon';

							// Category 4 values
							var digitStyle = $('input[name="signa[digit_box_style]"]:checked').val() || $('#digit_box_style').val() || 'box';
							var digitSize = parseFloat($('#digit_box_size').val() || 48);
							var digitGap = parseFloat($('#digit_box_gap').val() || 8);
							var timerStyle = $('input[name="signa[timer_style]"]:checked').val() || 'progress_bar';

							var isSplit = (formLayout === 'split_right' || formLayout === 'split_left');
							var isDrawerOrSheet = (currentPreviewMode === 'modal' && (modalStyle === 'drawer_left' || modalStyle === 'drawer_right' || modalStyle === 'bottom_sheet'));
							var showSplitBanner = isSplit && !isDrawerOrSheet;

							// Progressive disclosure for Split-Screen & Canvas background sub-controls
							if (isSplit) {
								$('#signa-split-banner-settings').slideDown(180);
							} else {
								$('#signa-split-banner-settings').slideUp(180);
							}
							$('#signa-canvas-color-wrap').toggle(canvasBgStyle === 'solid' || canvasBgStyle === 'mesh_light');
							$('#signa-canvas-image-wrap').toggle(canvasBgStyle === 'custom_image');

							// Automatically widen preview column slightly when Split-Screen is active so both columns fit inside 100% of the canvas without overflowing
							$('.signa-studio-layout').toggleClass('is-split-preview-active', showSplitBanner);

							// 4. Smart Automatic Dark/Light Theme & Image Contrast Detection
							var cardLum = hexLuminance(bg);
							var canvasLum = 0.14;
							if (canvasBgStyle === 'mesh_dark') {
								canvasLum = 0.08;
							} else if (canvasBgStyle === 'solid') {
								canvasLum = hexLuminance(canvasBgColor);
							} else if (canvasBgStyle === 'custom_image' && canvasBgImg) {
								if (typeof imgLuminanceCache[canvasBgImg] === 'number') {
									canvasLum = imgLuminanceCache[canvasBgImg] * 0.55;
								} else {
									canvasLum = 0.25;
									sampleImageLuminance(canvasBgImg, function(){ syncStudioCat2(); });
								}
							} else if (canvasBgStyle === 'brand_gradient') {
								canvasLum = 0.22;
							} else {
								// Default rich dark slate studio backdrop in Page mode too (as requested)
								canvasLum = 0.14;
							}

							var alpha = isGlass ? Math.max(0.15, Math.min(1, cardOpacity / 100)) : 1;
							var effectiveFormLum = isGlass ? ((cardLum * alpha) + (canvasLum * (1 - alpha))) : cardLum;

							// Card surface is dark when card_bg_color itself is dark (cardLum < 0.48) OR when glass opacity is low enough that effectiveFormLum < 0.48
							var isDarkFormSurface = (cardLum < 0.48) || (isGlass && effectiveFormLum < 0.48);

							// Automatically transition text color between white (#f8fafc) and dark (#111827) unless user is manually dragging #text_color right now
							if (triggeredById !== 'text_color') {
								var targetTextColor = isDarkFormSurface ? '#f8fafc' : '#111827';
								if (text.toLowerCase() !== targetTextColor) {
									text = targetTextColor;
									$('#text_color').val(targetTextColor);
									$('#text_color_hex').text(targetTextColor);
								}
								// Also adapt input background/border for dark vs light surface if not manually editing input colors right now
								if (triggeredById !== 'input_bg_color' && triggeredById !== 'input_border_color') {
									if (isDarkFormSurface && hexLuminance(inputBg) > 0.7) {
										inputBg = '#1e293b';
										inputBorder = '#475569';
										$('#input_bg_color').val(inputBg);
										$('#input_border_color').val(inputBorder);
									} else if (!isDarkFormSurface && inputBg.toLowerCase() === '#1e293b') {
										inputBg = '#f8fafc';
										inputBorder = '#d1d5db';
										$('#input_bg_color').val(inputBg);
										$('#input_border_color').val(inputBorder);
									}
								}
							}

							// Update Smart Contrast Status Badge
							var $contrastBadge = $('#signa-auto-contrast-badge');
							if ($contrastBadge.length) {
								if (isDarkFormSurface) {
									$contrastBadge.text('تم تیره • متن سفید خودکار').css({ background: 'rgba(15, 23, 42, 0.88)', color: '#f8fafc' });
								} else {
									$contrastBadge.text('تم روشن • متن تیره خودکار').css({ background: 'rgba(37, 99, 235, 0.12)', color: '#2563eb' });
								}
							}

							// Also check Split-Screen Banner Image/Background Luminance for automatic text contrast
							var splitLum = hexLuminance(splitBgColor);
							if (splitImgUrl) {
								if (typeof imgLuminanceCache[splitImgUrl] === 'number') {
									splitLum = imgLuminanceCache[splitImgUrl] * 0.55;
								} else {
									splitLum = 0.25;
									sampleImageLuminance(splitImgUrl, function(){ syncStudioCat2(); });
								}
							}
							var splitTextColor = splitLum < 0.52 ? '#ffffff' : '#0f172a';
							$('#signa-prev-split-banner').css('color', splitTextColor);
							$('#signa-prev-split-title, #signa-prev-split-subtitle, #signa-prev-split-features').css('color', splitTextColor);

							$('#primary_color_hex').text(primary);
							$('#secondary_color_hex').text(secondary);
							$('#card_bg_color_hex').text(bg);
							$('#text_color_hex').text(text);
							$('#split_bg_color_hex').text(splitBgColor);
							$('#canvas_bg_color_hex').text(canvasBgColor);
							$('#radius_val_label').text(radius + 'px');
							$('#opacity_val_label').text(cardOpacity + '%');
							$('#blur_val_label').text(blurPx + 'px');
							$('#padding_val_label').text(cardPadding + 'px');
							$('#title_size_val_label').text(titleSize + 'px');
							$('#subtitle_size_val_label').text(subSize + 'px');
							$('#btn_size_val_label').text(btnSize + 'px');
							$('#input_height_val_label').text(inputHeight + 'px');
							$('#input_bg_color_hex').text(inputBg);
							$('#input_border_color_hex').text(inputBorder);
							$('#digit_size_val_label').text(digitSize + 'px');
							$('#digit_gap_val_label').text(digitGap + 'px');

							// Sync Logo, Title, Subtitle, Button Labels, and Split-Screen Banner Content
							$('#signa-prev-badge-icon').css('color', primary);
							$('#signa-prev-title').text(formTitle);
							$('#signa-prev-subtitle').text(formSubtitle);
							$('#signa-prev-btn-1').text(btn1Text);
							$('#signa-prev-btn-2').text(btn2Text);
							if (logoUrl) {
								$('#signa-prev-logo-img').attr('src', logoUrl);
								$('#signa-prev-logo-wrap').show();
								$('#signa-prev-badge-icon').hide();
							} else {
								$('#signa-prev-logo-wrap').hide();
								$('#signa-prev-badge-icon').css('display', 'inline-flex');
							}

							if (showSplitBanner) {
								$('#signa-prev-split-banner').css({
									display: 'flex',
									backgroundColor: splitBgColor,
									backgroundImage: splitImgUrl
										? 'linear-gradient(135deg, rgba(15,23,42,0.72), rgba(30,58,138,0.78)), url(' + splitImgUrl + ')'
										: 'radial-gradient(circle at top left, rgba(255,255,255,0.16), transparent 65%)'
								});
								$('#signa-prev-split-badge').text(splitBadge).toggle(!!splitBadge);
								$('#signa-prev-split-title').text(splitTitle);
								$('#signa-prev-split-subtitle').text(splitSub);
								var featHtml = '';
								for (var f = 0; f < splitFeatures.length; f++) {
									var line = $.trim(splitFeatures[f]);
									if (line) {
										featHtml += '<li style="display:flex;align-items:center;gap:8px;margin:0;"><span style="display:inline-flex;width:18px;height:18px;border-radius:50%;background:rgba(16,185,129,0.28);color:#6ee7b7;align-items:center;justify-content:center;flex-shrink:0;">✓</span><span>' + $('<div>').text(line).html() + '</span></li>';
									}
								}
								$('#signa-prev-split-features').html(featHtml);
							} else {
								$('#signa-prev-split-banner').hide();
							}

							if (isGlass) { $('#signa-glassmorphism-controls').slideDown(180); } else { $('#signa-glassmorphism-controls').slideUp(180); }
							$('#signa-secondary-color-wrap').css('opacity', btnBgMode === 'gradient' ? '1' : '0.65');

							// Show vibrant ambient orbs behind card so Glassmorphism backdrop-filter blur is unmistakably visible
							$('#signa-prev-glass-orbs').toggleClass('is-glass-active', isGlass);
							$('#signa-prev-glass-orbs .orb-1').css('background', primary);
							$('#signa-prev-glass-orbs .orb-2').css('background', secondary);

							// Rich Dark Slate Backdrop in both Page View (نمای صفحه ورود) and Modal View so frosted/light cards pop with high contrast
							var canvasBgCss = '';
							if (canvasBgStyle === 'mesh_dark') {
								canvasBgCss = 'radial-gradient(circle at top right, #1e1b4b 0%, #0f172a 60%, #020617 100%)';
							} else if (canvasBgStyle === 'brand_gradient') {
								canvasBgCss = 'linear-gradient(135deg, #0f172a 0%, #1e293b 55%, #090d16 100%)';
							} else if (canvasBgStyle === 'custom_image' && canvasBgImg) {
								canvasBgCss = 'linear-gradient(rgba(15,23,42,0.58), rgba(15,23,42,0.68)), url(' + canvasBgImg + ') center/cover no-repeat';
							} else if (canvasBgStyle === 'solid') {
								canvasBgCss = (canvasBgColor.toLowerCase() === '#f1f5f9') ? '#1e293b' : canvasBgColor;
							} else {
								canvasBgCss = 'radial-gradient(circle at top right, #334155 0%, #1e293b 52%, #0f172a 100%)';
							}
							var patternLayer = 'radial-gradient(rgba(148, 163, 184, 0.22) 1px, transparent 1px), ', patternSize = '18px 18px, auto';
							if (bgPattern === 'dots') {
								patternLayer = 'radial-gradient(rgba(129, 140, 248, 0.32) 1.35px, transparent 1.35px), ';
								patternSize = '18px 18px, auto';
							} else if (bgPattern === 'grid') {
								patternLayer = 'linear-gradient(to right, rgba(148, 163, 184, 0.16) 1px, transparent 1px), linear-gradient(to bottom, rgba(148, 163, 184, 0.16) 1px, transparent 1px), ';
								patternSize = '22px 22px, 22px 22px, auto';
							} else if (bgPattern === 'waves') {
								patternLayer = 'repeating-radial-gradient(circle at 0 0, transparent 0, rgba(99, 102, 241, 0.12) 12px, transparent 24px), ';
							} else if (bgPattern === 'geometric') {
								patternLayer = 'linear-gradient(30deg, rgba(99, 102, 241, 0.12) 12%, transparent 12.5%, transparent 87%, rgba(99, 102, 241, 0.12) 87.5%), ';
								patternSize = '28px 48px, auto';
							}

							var alignFlex = cardPosition === 'right' ? 'flex-start' : cardPosition === 'left' ? 'flex-end' : 'center';
							var $canvas = $('#signa-preview-canvas');
							var $viewport = $('#signa-preview-viewport');

							$canvas.removeClass('is-modal-mode sim-center sim-drawer_left sim-drawer_right sim-bottom_sheet');
							if (currentPreviewMode === 'modal') {
								$canvas.addClass('is-modal-mode sim-' + modalStyle);
							}

							$canvas.css({
								alignItems: alignFlex,
								background: patternLayer + canvasBgCss,
								backgroundSize: patternSize
							});

							$viewport.toggleClass('is-scaled-split', showSplitBanner);
							$viewport.css('alignItems', alignFlex);

							var shadowCss = '0 20px 44px -10px rgba(2, 6, 23, 0.45)';
							if (cardShadow === 'none') shadowCss = 'none';
							else if (cardShadow === 'soft') shadowCss = '0 8px 24px -4px rgba(2, 6, 23, 0.25)';
							else if (cardShadow === 'deep') shadowCss = '0 28px 64px -10px rgba(2, 6, 23, 0.65), 0 12px 28px -6px rgba(2, 6, 23, 0.35)';
							else if (cardShadow === 'glow') shadowCss = '0 0 38px -2px ' + hexRgba(primary, 55) + ', 0 16px 36px -6px rgba(2, 6, 23, 0.45)';

							var borderCss = '1px solid rgba(148, 163, 184, 0.28)', borderTopCss = borderCss;
							if (cardBorder === 'none') { borderCss = 'none'; borderTopCss = 'none'; }
							else if (cardBorder === 'glow') { borderCss = '1.5px solid ' + hexRgba(primary, 70); borderTopCss = borderCss; }
							else if (cardBorder === 'top_accent') { borderTopCss = '4px solid ' + primary; }

							// Compute exact corner border-radius & hardware clip-path so backdrop-filter blur NEVER bleeds into 90-degree corners
							var radNum = Math.max(0, Math.round(radius));
							var radPx = radNum + 'px';
							var shellRadiusCss = radPx;
							var cardRadiusCss = radPx;
							var cardClipCss = 'inset(0 round ' + radPx + ')';
							var bannerRadiusCss = '0';
							var bannerClipCss = 'none';

							if (currentPreviewMode === 'modal' && modalStyle === 'bottom_sheet') {
								shellRadiusCss = '20px';
								cardRadiusCss = '20px';
								cardClipCss = 'inset(0 round 20px)';
							} else if (currentPreviewMode === 'modal' && modalStyle === 'drawer_left') {
								shellRadiusCss = '0 16px 16px 0';
								cardRadiusCss = '0 16px 16px 0';
								cardClipCss = 'inset(0 round 0 16px 16px 0)';
							} else if (currentPreviewMode === 'modal' && modalStyle === 'drawer_right') {
								shellRadiusCss = '16px 0 0 16px';
								cardRadiusCss = '16px 0 0 16px';
								cardClipCss = 'inset(0 round 16px 0 0 16px)';
							} else if (showSplitBanner) {
								if (formLayout === 'split_left') {
									// In RTL row-reverse: Form Card is on LEFT, Split Banner is on RIGHT
									cardRadiusCss = radPx + ' 0 0 ' + radPx;
									cardClipCss = 'inset(0 round ' + radPx + ' 0 0 ' + radPx + ')';
									bannerRadiusCss = '0 ' + radPx + ' ' + radPx + ' 0';
									bannerClipCss = 'inset(0 round 0 ' + radPx + ' ' + radPx + ' 0)';
								} else {
									// In RTL row: Form Card is on RIGHT, Split Banner is on LEFT
									cardRadiusCss = '0 ' + radPx + ' ' + radPx + ' 0';
									cardClipCss = 'inset(0 round 0 ' + radPx + ' ' + radPx + ' 0)';
									bannerRadiusCss = radPx + ' 0 0 ' + radPx;
									bannerClipCss = 'inset(0 round ' + radPx + ' 0 0 ' + radPx + ')';
								}
							}

							var singleCardMaxW = isWidePreview ? '470px' : '350px';
							var isOverlayPreview = currentPreviewMode === 'modal' && modalStyle !== 'center';
							var $shell = $('#signa-live-preview-shell');
							$shell.toggleClass('is-split', showSplitBanner);
							var shellDom = document.getElementById('signa-live-preview-shell');
							if (shellDom) {
								// Drawers & bottom sheet are sized/positioned by CSS (full stage height, slide transforms).
								if (isOverlayPreview) {
									shellDom.style.removeProperty('width');
									shellDom.style.removeProperty('max-width');
									shellDom.style.removeProperty('transform');
								} else {
									shellDom.style.setProperty('width', '100%', 'important');
									shellDom.style.setProperty('max-width', showSplitBanner ? '100%' : singleCardMaxW, 'important');
								}
								shellDom.style.setProperty('box-sizing', 'border-box', 'important');
								shellDom.style.setProperty('margin', '0', 'important');
								shellDom.style.setProperty('border-radius', shellRadiusCss, 'important');
								shellDom.style.setProperty('flex-direction', formLayout === 'split_left' ? 'row-reverse' : 'row', 'important');
								// In Single-Card mode, put border & shadow directly on cardEl so there is never a double-border mismatch at corners
								shellDom.style.setProperty('box-shadow', showSplitBanner ? shadowCss : 'none', 'important');
								shellDom.style.setProperty('border', showSplitBanner ? borderCss : 'none', 'important');
								shellDom.style.setProperty('border-top', showSplitBanner ? borderTopCss : 'none', 'important');
								shellDom.style.setProperty('background', 'transparent', 'important');
							}

							// 3. Apply Glassmorphism Backdrop Blur directly via native setProperty + clip-path to eliminate corner bleed
							var effectiveGlassOpacity = isGlass ? Math.min(cardOpacity, 88) : 100;
							var cardBgValue = isGlass ? hexRgba(bg, effectiveGlassOpacity) : bg;
							var blurValue = isGlass ? ('blur(' + blurPx + 'px) saturate(160%)') : 'none';
							var previewScaleFactor = isWidePreview ? (showSplitBanner ? 0.88 : 1.0) : (showSplitBanner ? 0.62 : 0.85);

							var cardEl = document.getElementById('signa-live-preview-card');
							if (cardEl) {
								$(cardEl).toggleClass('is-dark-preview-card', isDarkFormSurface);
								cardEl.style.setProperty('background', cardBgValue, 'important');
								cardEl.style.setProperty('backdrop-filter', blurValue, 'important');
								cardEl.style.setProperty('-webkit-backdrop-filter', blurValue, 'important');
								cardEl.style.setProperty('color', text, 'important');
								cardEl.style.setProperty('padding', Math.round(cardPadding * previewScaleFactor) + 'px', 'important');
								cardEl.style.setProperty('border-radius', cardRadiusCss, 'important');
								cardEl.style.setProperty('overflow', 'hidden', 'important');
								cardEl.style.setProperty('background-clip', 'padding-box', 'important');
								cardEl.style.setProperty('clip-path', cardClipCss, 'important');
								cardEl.style.setProperty('-webkit-clip-path', cardClipCss, 'important');
								cardEl.style.setProperty('box-shadow', showSplitBanner ? 'none' : shadowCss, 'important');
								cardEl.style.setProperty('border', showSplitBanner ? 'none' : borderCss, 'important');
								cardEl.style.setProperty('border-top', showSplitBanner ? 'none' : borderTopCss, 'important');
							}

							var splitBannerEl = document.getElementById('signa-prev-split-banner');
							if (splitBannerEl && showSplitBanner) {
								splitBannerEl.style.setProperty('padding', Math.round(cardPadding * previewScaleFactor) + 'px', 'important');
								splitBannerEl.style.setProperty('border-radius', bannerRadiusCss, 'important');
								splitBannerEl.style.setProperty('overflow', 'hidden', 'important');
								splitBannerEl.style.setProperty('clip-path', bannerClipCss, 'important');
								splitBannerEl.style.setProperty('-webkit-clip-path', bannerClipCss, 'important');
							}

							// Ensure title, subtitle, and labels smoothly fade to the detected text color
							$('#signa-prev-title, #signa-prev-subtitle, #signa-prev-field-label').css('color', text);
							$('#signa-prev-edit-num').css('color', primary);

							// Category 3: Apply Typography & Input Field Style in Live Preview
							var fontMap = {
								vazirmatn: "'Vazirmatn', Tahoma, sans-serif",
								iransans: "'IRANSansX', 'IRANSans', 'Vazirmatn', Tahoma, sans-serif",
								yekanbakh: "'YekanBakh', 'IRANYekan', 'Vazirmatn', Tahoma, sans-serif",
								dana: "'Dana', 'Anjoman', 'Vazirmatn', Tahoma, sans-serif",
								estedad: "'Estedad', 'Shabnam', 'Vazirmatn', Tahoma, sans-serif",
								theme_inherit: "inherit"
							};
							var activeFont = fontMap[fontKey] || fontMap.vazirmatn;
							$('#signa-live-preview-shell, #signa-live-preview-shell *').css('font-family', activeFont);
							// Form controls (input/button/select/textarea) carry !important font rules in admin.css, so force the chosen font inline.
							$('#signa-live-preview-shell input, #signa-live-preview-shell button, #signa-live-preview-shell select, #signa-live-preview-shell textarea').each(function () {
								this.style.setProperty('font-family', activeFont, 'important');
							});
							var previewTitleSize = isWidePreview ? (showSplitBanner ? titleSize : Math.round(titleSize * 1.08)) : (showSplitBanner ? Math.max(13, Math.round(titleSize * 0.78)) : titleSize);
							var previewSubSize = isWidePreview ? (showSplitBanner ? subSize : Math.round(subSize * 1.04)) : (showSplitBanner ? Math.max(11, Math.round(subSize * 0.82)) : subSize);
							var previewBtnSize = isWidePreview ? (showSplitBanner ? btnSize : Math.round(btnSize * 1.05)) : (showSplitBanner ? Math.max(12.5, Math.round(btnSize * 0.84)) : btnSize);
							var previewInputH = isWidePreview ? (showSplitBanner ? inputHeight : Math.round(inputHeight * 1.06)) : (showSplitBanner ? Math.max(38, Math.round(inputHeight * 0.84)) : inputHeight);

							$('#signa-prev-title').css('font-size', previewTitleSize + 'px');
							$('#signa-prev-subtitle').css('font-size', previewSubSize + 'px');

							var $prevInput = $('#signa-live-preview-card input.signa-prev-input');
							var inputRad = inputStyle === 'soft_pill' ? '99px' : inputStyle === 'underlined' ? '0' : Math.round(radius * 0.68) + 'px';
							var btnRad = inputStyle === 'soft_pill' ? '99px' : Math.round(radius * 0.68) + 'px';
							var inputFontSize = (showSplitBanner && !isWidePreview) ? '12px' : (isWidePreview ? '14px' : '13px');

							if ($prevInput.length) {
								if (inputStyle === 'underlined') {
									$prevInput[0].style.setProperty('background-color', 'transparent', 'important');
									$prevInput[0].style.setProperty('border', 'none', 'important');
									$prevInput[0].style.setProperty('border-bottom', '2.5px solid ' + primary, 'important');
									$prevInput[0].style.setProperty('border-radius', '0', 'important');
								} else if (inputStyle === 'outlined') {
									$prevInput[0].style.setProperty('background-color', 'transparent', 'important');
									$prevInput[0].style.setProperty('border', '1.5px solid ' + inputBorder, 'important');
									$prevInput[0].style.setProperty('border-radius', inputRad, 'important');
								} else {
									$prevInput[0].style.setProperty('background-color', inputBg, 'important');
									$prevInput[0].style.setProperty('border', '1.5px solid ' + inputBorder, 'important');
									$prevInput[0].style.setProperty('border-radius', inputRad, 'important');
								}
								$prevInput[0].style.setProperty('color', text, 'important');
								$prevInput[0].style.setProperty('height', previewInputH + 'px', 'important');
								$prevInput[0].style.setProperty('font-size', inputFontSize, 'important');
								$prevInput.attr('placeholder', inputAddon === 'ir_flag' ? 'شماره موبایل (912...) یا ایمیل' : 'شماره موبایل (0912...) یا ایمیل');
								$prevInput[0].style.setProperty('padding-right', '14px', 'important');
							}

							var $addon = $('#signa-prev-input-addon');
							if (inputAddon === 'ir_flag') {
								$addon.html('<span style="font-size:13px;">🇮🇷</span><span>+98</span>').show();
								if ($prevInput.length) $prevInput[0].style.setProperty('padding-left', '56px', 'important');
							} else if (inputAddon === 'icon') {
								$addon.html('<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="5" y="2" width="14" height="20" rx="2"/><line x1="12" y1="18" x2="12.01" y2="18"/></svg>').show();
								if ($prevInput.length) $prevInput[0].style.setProperty('padding-left', '38px', 'important');
							} else {
								$addon.hide();
								if ($prevInput.length) $prevInput[0].style.setProperty('padding-left', '14px', 'important');
							}
							$addon.css('color', isDarkFormSurface ? '#cbd5e1' : '#64748b');

							var btnBg = btnBgMode === 'gradient' ? 'linear-gradient(135deg, ' + primary + ', ' + secondary + ')' : primary;
							$('#signa-prev-btn-1, #signa-prev-btn-2').css({
								background: btnBg,
								borderRadius: btnRad,
								height: previewInputH + 'px',
								fontSize: previewBtnSize + 'px'
							});

							// Category 4: Sync OTP Digit Boxes & Countdown Timer in Step 2
							var scaledDigitSize = isWidePreview ? (showSplitBanner ? Math.round(digitSize * 0.92) : digitSize) : (showSplitBanner ? Math.max(30, Math.round(digitSize * 0.72)) : Math.round(digitSize * 0.88));
							var scaledDigitGap = (showSplitBanner && !isWidePreview) ? Math.max(3, Math.round(digitGap * 0.65)) : digitGap;
							var maxSingleDigitW = 'calc((100% - ' + (scaledDigitGap * 4) + 'px) / 5)';
							var $digitsWrap = $('#signa-prev-digits');
							var $digits = $('.signa-prev-digit');
							var activeDigitIdx = parseInt($digitsWrap.attr('data-active-idx') || '2', 10);

							if (digitStyle === 'connected') {
								$digitsWrap.css({
									gap: '0px',
									border: '1.5px solid ' + inputBorder,
									borderRadius: Math.round(radius * 0.55) + 'px',
									overflow: 'hidden',
									background: inputBg,
									display: 'inline-flex',
									width: '100%'
								});
								$digits.each(function(idx){
									var isFocusIdx = (idx === activeDigitIdx);
									$(this).toggleClass('is-focused-digit', isFocusIdx).css({
										flex: '1',
										width: 'auto',
										maxWidth: '20%',
										boxSizing: 'border-box',
										height: scaledDigitSize + 'px',
										lineHeight: scaledDigitSize + 'px',
										border: 'none',
										borderRight: idx < 4 ? ('1px solid ' + inputBorder) : 'none',
										borderRadius: '0',
										background: isFocusIdx ? hexRgba(primary, 16) : 'transparent',
										color: isFocusIdx ? primary : text,
										boxShadow: 'none',
										cursor: 'pointer',
										transition: 'all 0.28s cubic-bezier(0.22, 1, 0.36, 1)',
										fontSize: Math.round(scaledDigitSize * 0.4) + 'px'
									});
								});
							} else {
								$digitsWrap.css({
									gap: scaledDigitGap + 'px',
									border: 'none',
									borderRadius: '0',
									overflow: 'visible',
									background: 'transparent',
									display: 'flex',
									width: '100%'
								});
								$digits.each(function(idx){
									var isFocusIdx = (idx === activeDigitIdx);
									$(this).toggleClass('is-focused-digit', isFocusIdx);
									if (digitStyle === 'underline') {
										$(this).css({
											flex: '1 1 0',
											width: Math.round(scaledDigitSize * 0.86) + 'px',
											maxWidth: maxSingleDigitW,
											boxSizing: 'border-box',
											height: scaledDigitSize + 'px',
											lineHeight: scaledDigitSize + 'px',
											border: 'none',
											borderBottom: (isFocusIdx ? '3px solid ' : '2px solid ') + (isFocusIdx ? primary : inputBorder),
											borderRadius: '0',
											background: 'transparent',
											color: isFocusIdx ? primary : text,
											boxShadow: 'none',
											cursor: 'pointer',
											transition: 'all 0.28s cubic-bezier(0.22, 1, 0.36, 1)',
											fontSize: Math.round(scaledDigitSize * 0.42) + 'px'
										});
									} else if (digitStyle === 'pill') {
										$(this).css({
											flex: '1 1 0',
											width: Math.round(scaledDigitSize * 0.88) + 'px',
											maxWidth: maxSingleDigitW,
											boxSizing: 'border-box',
											height: scaledDigitSize + 'px',
											lineHeight: scaledDigitSize + 'px',
											border: (isFocusIdx ? '2px solid ' : '1.5px solid ') + (isFocusIdx ? primary : inputBorder),
											borderRadius: '99px',
											background: inputBg,
											color: text,
											boxShadow: isFocusIdx ? ('0 0 0 3.5px ' + hexRgba(primary, 22)) : 'none',
											cursor: 'pointer',
											transition: 'all 0.28s cubic-bezier(0.22, 1, 0.36, 1)',
											fontSize: Math.round(scaledDigitSize * 0.4) + 'px'
										});
									} else if (digitStyle === 'separated_glow') {
										$(this).css({
											flex: '1 1 0',
											width: Math.round(scaledDigitSize * 0.88) + 'px',
											maxWidth: maxSingleDigitW,
											boxSizing: 'border-box',
											height: scaledDigitSize + 'px',
											lineHeight: scaledDigitSize + 'px',
											border: (isFocusIdx ? '2px solid ' : '1.5px solid ') + primary,
											borderRadius: Math.round(radius * 0.5) + 'px',
											background: hexRgba(primary, isDarkFormSurface ? (isFocusIdx ? 26 : 16) : (isFocusIdx ? 16 : 9)),
											color: text,
											boxShadow: '0 0 ' + (isFocusIdx ? '16px ' : '10px ') + hexRgba(primary, isFocusIdx ? 60 : 28),
											cursor: 'pointer',
											transition: 'all 0.28s cubic-bezier(0.22, 1, 0.36, 1)',
											fontSize: Math.round(scaledDigitSize * 0.4) + 'px'
										});
									} else {
										$(this).css({
											flex: '1 1 0',
											width: Math.round(scaledDigitSize * 0.88) + 'px',
											maxWidth: maxSingleDigitW,
											boxSizing: 'border-box',
											height: scaledDigitSize + 'px',
											lineHeight: scaledDigitSize + 'px',
											border: (isFocusIdx ? '2px solid ' : '1.5px solid ') + (isFocusIdx ? primary : inputBorder),
											borderRadius: Math.round(radius * 0.5) + 'px',
											background: inputBg,
											color: text,
											boxShadow: isFocusIdx ? ('0 0 0 3.5px ' + hexRgba(primary, 20)) : 'none',
											cursor: 'pointer',
											transition: 'all 0.28s cubic-bezier(0.22, 1, 0.36, 1)',
											fontSize: Math.round(scaledDigitSize * 0.4) + 'px'
										});
									}
								});
							}

							// Render Live Countdown Timer Preview in Step 2
							var $timerBox = $('#signa-prev-timer-box');
							if (timerStyle === 'circular_ring') {
								$timerBox.html(
									'<div style="display:inline-flex;align-items:center;gap:8px;padding:5px 14px;border-radius:99px;background:' + hexRgba(primary, 12) + ';border:1px solid ' + hexRgba(primary, 30) + ';color:' + text + ';font-size:11.5px;font-weight:700;">' +
									'<svg width="20" height="20" viewBox="0 0 24 24" style="transform:rotate(-90deg);flex-shrink:0;"><circle cx="12" cy="12" r="9" fill="none" stroke="rgba(148,163,184,0.28)" stroke-width="2.5"/><circle class="signa-mini-ring-circle" cx="12" cy="12" r="9" fill="none" stroke="' + primary + '" stroke-width="2.5" stroke-dasharray="56.5" stroke-dashoffset="16" stroke-linecap="round"/></svg>' +
									'<span>ارسال مجدد کد: <strong dir="ltr" style="color:' + primary + ';">01:45</strong></span>' +
									'</div>'
								);
							} else if (timerStyle === 'minimal_badge') {
								$timerBox.html(
									'<div class="signa-mini-clock-tick" style="display:inline-flex;align-items:center;gap:6px;padding:5px 14px;border-radius:99px;background:' + hexRgba(primary, 12) + ';color:' + primary + ';font-size:11.5px;font-weight:700;">' +
									'<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>' +
									'<span>ارسال مجدد تا ۰۱:۴۵ دیگر</span>' +
									'</div>'
								);
							} else if (timerStyle === 'simple_text') {
								$timerBox.html(
									'<div style="font-size:11.5px;opacity:0.8;color:' + text + ';font-weight:600;">ارسال مجدد کد تا <strong style="color:' + primary + ';">۰۱:۴۵</strong> دیگر</div>'
								);
							} else {
								$timerBox.html(
									'<div style="display:flex;flex-direction:column;gap:5px;align-items:center;">' +
									'<span style="font-size:11.5px;opacity:0.85;color:' + text + ';font-weight:600;">ارسال مجدد کد تا <strong style="color:' + primary + ';">۰۱:۴۵</strong> دیگر</span>' +
									'<div style="width:100%;height:5px;border-radius:99px;background:rgba(148,163,184,0.22);overflow:hidden;">' +
									'<div class="signa-mini-progress-fill" style="width:68%;height:100%;border-radius:99px;background:' + btnBg + ';"></div>' +
									'</div></div>'
								);
							}
						}

						// Expose unified authoritative Live Preview renderer so admin.js delegates to it without conflict
						window.signaSyncLivePreview = syncStudioCat2;

						// Choreographed Form Entrance & Staggered Elements Animation
						function triggerFormEntranceChoreography(customAnim) {
							var anim = customAnim || $('input[name="signa[form_animation]"]:checked').val() || $('#form_animation').val() || 'fade_up';
							var $card = $('#signa-live-preview-card');
							var $staggerEls = $card.find('.signa-stagger-el:visible');
							$card.removeClass('signa-anim-fade_up signa-anim-zoom_spring signa-anim-slide_rtl');
							$staggerEls.removeClass('signa-stagger-run');
							if (anim !== 'none') {
								void $card[0].offsetWidth;
								$card.addClass('signa-anim-' + anim);
								$staggerEls.each(function(i){
									this.style.animationDelay = (i * 55) + 'ms';
									$(this).addClass('signa-stagger-run');
								});
							}
						}

						// Polished Error Shake & Crimson Neon Wave Choreography
						var errorShakeTimer = null;
						function triggerErrorShakeChoreography() {
							if (errorShakeTimer) {
								clearTimeout(errorShakeTimer);
							}
							$('.signa-prev-step-btn').removeClass('active');
							$('.signa-prev-step-btn[data-step="2"]').addClass('active');
							$('#signa-prev-step-1').hide();
							$('#signa-prev-step-2').show();

							var $card = $('#signa-live-preview-card');
							var $digitsWrap = $('#signa-prev-digits');
							var $digits = $('.signa-prev-digit');
							var $toast = $('#signa-prev-error-toast');
							var $btn2 = $('#signa-prev-btn-2');
							var origBtnText = $('#verify_button_text').val() || 'تایید و ورود به حساب';

							$digitsWrap.removeClass('signa-shake-anim');
							$digits.removeClass('is-error-digit');
							$toast.stop(true, true).slideDown(220).addClass('is-visible');
							void $digitsWrap[0].offsetWidth;
							$digitsWrap.addClass('signa-shake-anim');

							$digits.each(function(idx){
								this.style.animationDelay = (idx * 32) + 'ms';
								$(this).addClass('is-error-digit');
							});

							$btn2.text('کد تایید نامعتبر است').css({
								background: 'linear-gradient(135deg, #ef4444, #dc2626)',
								boxShadow: '0 8px 20px -4px rgba(239, 68, 68, 0.45)'
							});

							errorShakeTimer = setTimeout(function(){
								$digitsWrap.removeClass('signa-shake-anim');
								$digits.removeClass('is-error-digit');
								$toast.removeClass('is-visible').slideUp(240);
								$btn2.text(origBtnText).css('boxShadow', 'none');
								syncStudioCat2();
							}, 1950);
						}

						// Mode Tabs (Page vs Modal Simulation)
						$('.signa-prev-mode-btn').on('click', function(){
							currentPreviewMode = $(this).attr('data-mode') || 'page';
							$('.signa-prev-mode-btn').removeClass('active');
							$(this).addClass('active');
							requestPreviewSwitch();
						});

						// Interactive Step 1 <-> Step 2 buttons & preview buttons
						$('.signa-prev-step-btn').off('click.signaStep').on('click.signaStep', function(){
							var step = $(this).attr('data-step');
							$('.signa-prev-step-btn').removeClass('active');
							$(this).addClass('active');
							if (step === '2') {
								$('#signa-prev-step-1').hide();
								$('#signa-prev-step-2').fadeIn(180);
							} else {
								$('#signa-prev-error-toast').hide();
								$('#signa-prev-step-2').hide();
								$('#signa-prev-step-1').fadeIn(180);
							}
						});

						// Clicking Preview Primary Button 1 switches to Step 2 with micro-animation; clicking Edit switches back
						$('#signa-prev-btn-1').on('click', function(){
							$('.signa-prev-step-btn[data-step="2"]').trigger('click');
						});
						$('#signa-prev-edit-num').on('click', function(){
							$('.signa-prev-step-btn[data-step="1"]').trigger('click');
						});

						// Clicking any OTP digit box in Live Preview focuses it with a spring bounce
						$(document).on('click', '.signa-prev-digit', function(){
							var idx = $(this).attr('data-idx') || '2';
							$('#signa-prev-digits').attr('data-active-idx', idx);
							syncStudioCat2();
						});

						// Auto-switch Live Preview to Modal mode when user selects a Modal/Drawer style
						$('input[name="signa[modal_style]"]').on('change', function(){
							currentPreviewMode = 'modal';
							$('.signa-prev-mode-btn').removeClass('active');
							$('.signa-prev-mode-btn[data-mode="modal"]').addClass('active');
							requestPreviewSwitch();
						});

						// Auto-switch Live Preview to Page mode when user changes Form Layout or Card Position
						$('input[name="signa[form_layout]"], input[name="signa[card_position]"], #canvas_bg_style, input[name="signa[bg_pattern]"]').on('change', function(){
							currentPreviewMode = 'page';
							$('.signa-prev-mode-btn').removeClass('active');
							$('.signa-prev-mode-btn[data-mode="page"]').addClass('active');
							requestPreviewSwitch();
						});

						// Auto-switch Live Preview to Step 2 when user changes any Category 4 OTP/Timer control
						$(document).on('input change', '.signa-cat4-control', function(){
							$('.signa-prev-step-btn').removeClass('active');
							$('.signa-prev-step-btn[data-step="2"]').addClass('active');
							$('#signa-prev-step-1').hide();
							$('#signa-prev-step-2').fadeIn(150);
							syncStudioCat2();
						});

						// Replay Form Entrance Animation in Live Preview when changed or replay button clicked
						$(document).on('change', 'input[name="signa[form_animation]"], #form_animation', function(){
							var anim = $(this).val() || 'fade_up';
							triggerFormEntranceChoreography(anim);
						});
						$('#signa-replay-entrance-btn').on('click', function(){
							triggerFormEntranceChoreography();
						});

						// Live Error Shake Effect Test Button & Switch in Preview
						$('#signa-test-shake-btn').on('click', function(){
							triggerErrorShakeChoreography();
						});
						$('#error_shake_effect').on('change', function(){
							if ($(this).is(':checked')) {
								triggerErrorShakeChoreography();
							}
						});

						// When user adjusts Glassmorphism blur or opacity slider, auto-Lower opacity slightly if it was 100% so blur is immediately visible
						$('#backdrop_blur').on('input change', function(){
							if (!$('#glassmorphism').is(':checked')) {
								$('#glassmorphism').prop('checked', true);
							}
							var curOp = parseFloat($('#card_bg_opacity').val() || 85);
							if (curOp > 82) {
								$('#card_bg_opacity').val(68);
							}
							syncStudioCat2();
						});

						// Toggle Wide / Zoomed Preview Column & Card Size
						$('#signa-prev-expand-btn').off('click').on('click', function(){
							var $btn = $(this);
							$btn.toggleClass('active');
							var isNowWide = $btn.hasClass('active');
							$('.signa-studio-layout').toggleClass('is-wide-preview', isNowWide);
							$btn.find('span').text(isNowWide ? 'کوچک‌نمایی' : 'بزرگ‌نمایی');
							syncStudioCat2();
						});

						// Preset Theme Buttons Sync
						$('.signa-preset-btn').off('click.signaPreset').on('click.signaPreset', function(){
							var $btn = $(this);
							$('#primary_color').val($btn.attr('data-primary'));
							$('#card_bg_color').val($btn.attr('data-bg'));
							$('#text_color').val($btn.attr('data-text'));
							$('#border_radius').val($btn.attr('data-radius'));
							syncStudioCat2();
						});

						$('#signa-tab-appearance_studio').on('input change', 'input, select, textarea', syncStudioCat2);
						syncStudioCat2();
					});
					</script>
				</section>
