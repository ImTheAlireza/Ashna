<?php
/**
 * Admin Settings Partial: tab-woocommerce.php (Side-by-Side Bento Grid + Vector Icons)
 *
 * @package Ashna
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
				<section class="ashna-panel" id="ashna-tab-woocommerce">
					<!-- TOP BENTO ROW: WooCommerce & WP Integration (Col 6) + One login page (Col 6) -->
					<div class="ashna-bento-row">
						<!-- Box 1: WooCommerce & WP Core Integration -->
						<div class="ashna-card ashna-col-6">
							<div class="ashna-card-head">
								<div class="ashna-card-head-title">
									<span class="ashna-card-icon is-purple">
										<svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.1" stroke-linecap="round" stroke-linejoin="round"><circle cx="9" cy="21" r="1"/><circle cx="20" cy="21" r="1"/><path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"/></svg>
									</span>
									<div>
										<h2>یکپارچگی با ووکامرس و وردپرس</h2>
										<p>جایگزینی خودکار فرم‌های پیش‌فرض با فرم OTP</p>
									</div>
								</div>
								<span class="ashna-pill <?php echo ! empty( $wc_active ) ? 'is-ok' : 'is-warn'; ?>">
									<?php echo ! empty( $wc_active ) ? 'ووکامرس فعال' : 'هسته وردپرس'; ?>
								</span>
							</div>

							<div style="display:flex;flex-direction:column;gap:12px;">
								<div class="ashna-switch-row">
									<div class="ashna-switch-text">
										<strong>جایگزینی فرم حساب کاربری ووکامرس (My Account)</strong>
										<p>فرم ورود و عضویت پیش‌فرض ووکامرس با فرم OTP جایگزین شود.</p>
									</div>
									<label class="ashna-switch">
										<input type="checkbox" name="ashna[wc_replace_myaccount]" value="1" <?php checked( $settings['wc_replace_myaccount'], 1 ); ?> />
										<span class="ashna-slider"></span>
									</label>
								</div>

								<div class="ashna-switch-row">
									<div class="ashna-switch-text">
										<strong>نوار ورود سریع در تسویه‌حساب (Checkout)</strong>
										<p>نمایش باکس ورود سریع با کد تایید بالای صفحه تسویه‌حساب برای مهمانان.</p>
									</div>
									<label class="ashna-switch">
										<input type="checkbox" name="ashna[wc_checkout_otp_box]" value="1" <?php checked( $settings['wc_checkout_otp_box'], 1 ); ?> />
										<span class="ashna-slider"></span>
									</label>
								</div>

								<div class="ashna-switch-row">
									<div class="ashna-switch-text">
										<strong>مودال پاپ‌آپ سراسری (Global Modal)</strong>
										<p>بارگذاری پنجره پاپ‌آپ ورود در فوتر سایت برای شورت‌کد و ویجت المنتور.</p>
									</div>
									<label class="ashna-switch">
										<input type="checkbox" name="ashna[enable_global_modal]" value="1" <?php checked( $settings['enable_global_modal'], 1 ); ?> />
										<span class="ashna-slider"></span>
									</label>
								</div>

								<div class="ashna-switch-row">
									<div class="ashna-switch-text">
										<strong>جایگزینی کامل صفحه ورود وردپرس (wp-login.php)</strong>
										<p>حذف فرم قدیمی وردپرس در <code>wp-login.php</code> و نمایش صفحه اختصاصی آشنا.</p>
									</div>
									<label class="ashna-switch">
										<input type="checkbox" name="ashna[wp_login_integration]" value="1" <?php checked( $settings['wp_login_integration'], 1 ); ?> />
										<span class="ashna-slider"></span>
									</label>
								</div>
							</div>
						</div>

						<!-- Box 2: One login page + membership login forms -->
						<?php
						$detected_plugins = Ashna_Integrations::detected();
						$login_page_id    = absint( $settings['standalone_page_id'] );
						$login_page_ok    = $login_page_id > 0 && 'publish' === get_post_status( $login_page_id );
						?>
						<div class="ashna-card ashna-col-6" id="ashna-login-page-card">
							<div class="ashna-card-head">
								<div class="ashna-card-head-title">
									<span class="ashna-card-icon is-blue">
										<svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.1" stroke-linecap="round" stroke-linejoin="round"><path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4"/><polyline points="10 17 15 12 10 7"/><line x1="15" y1="12" x2="3" y2="12"/></svg>
									</span>
									<div>
										<h2>یک صفحه‌ی ورود برای همه‌ی افزونه‌ها</h2>
										<p>لینک‌های «ورود» قالب، دیدگاه‌ها و افزونه‌های عضویت به فرم آشنا برسند</p>
									</div>
								</div>
								<span class="ashna-pill <?php echo $login_page_ok ? 'is-ok' : 'is-warn'; ?>"><?php echo $login_page_ok ? 'صفحه‌ی ورود فعال' : 'بدون صفحه‌ی ورود'; ?></span>
							</div>

							<div style="display:flex;flex-direction:column;gap:12px;">
								<div class="ashna-switch-row">
									<div class="ashna-switch-text">
										<strong>هدایت همه‌ی لینک‌های ورود و ثبت‌نام به صفحه‌ی آشنا</strong>
										<p><code>wp_login_url()</code>، لینک ورود Paid Memberships Pro و صفحه‌ی ورود پیشخوان فروشنده‌ی دکان. پیشخوان وردپرس همان <code>wp-login.php</code> را نگه می‌دارد.</p>
									</div>
									<label class="ashna-switch">
										<input type="checkbox" name="ashna[login_url_to_ashna]" value="1" <?php checked( ! empty( $settings['login_url_to_ashna'] ), true ); ?> />
										<span class="ashna-slider"></span>
									</label>
								</div>
								<div class="ashna-switch-row">
									<div class="ashna-switch-text">
										<strong>فرم آشنا به‌جای فرم ورود افزونه‌های عضویت</strong>
										<p>شورت‌کدهای <code>[pmpro_login]</code>، <code>[mepr-login-form]</code> و فرم‌های ورود Ultimate Member برای مهمان‌ها فرم کد یکبارمصرف نشان می‌دهند.</p>
									</div>
									<label class="ashna-switch">
										<input type="checkbox" name="ashna[replace_member_forms]" value="1" <?php checked( ! empty( $settings['replace_member_forms'] ), true ); ?> />
										<span class="ashna-slider"></span>
									</label>
								</div>
							</div>

							<?php if ( $login_page_ok ) : ?>
								<div class="ashna-info-note">
									<span>صفحه‌ی ورود: <a href="<?php echo esc_url( get_permalink( $login_page_id ) ); ?>" target="_blank" rel="noopener"><?php echo esc_html( get_the_title( $login_page_id ) ); ?></a> (از تب «طراحی ظاهر فرم» قابل تغییر است)</span>
								</div>
							<?php else : ?>
								<div class="ashna-info-note is-warning">
									<span>برای هدایت لینک‌ها، ابتدا در تب «طراحی ظاهر فرم» یک «برگه ورود تمام‌صفحه» انتخاب کنید. تا آن موقع لینک‌ها به <code>wp-login.php</code> می‌روند.</span>
								</div>
							<?php endif; ?>
						</div>
					</div>

					<!-- MIDDLE BENTO ROW: Dokan (Col 6) + Membership plugins (Col 6) -->
					<div class="ashna-bento-row" style="margin-top:20px;">
						<div class="ashna-card ashna-col-6" id="ashna-dokan-card">
							<div class="ashna-card-head">
								<div class="ashna-card-head-title">
									<span class="ashna-card-icon is-amber">
										<svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.1" stroke-linecap="round" stroke-linejoin="round"><path d="M3 9l1.5-5h15L21 9"/><path d="M4 9v11h16V9"/><path d="M3 9h18"/><path d="M9 20v-6h6v6"/></svg>
									</span>
									<div>
										<h2>دکان (Dokan) — فروشگاه چندفروشنده</h2>
										<p>ورود و ثبت‌نام فروشنده‌ها با کد یکبارمصرف</p>
									</div>
								</div>
								<span class="ashna-pill <?php echo $detected_plugins['dokan']['active'] ? 'is-ok' : 'is-muted'; ?>"><?php echo $detected_plugins['dokan']['active'] ? 'دکان فعال است' : 'دکان نصب نیست'; ?></span>
							</div>

							<div class="ashna-switch-row">
								<div class="ashna-switch-text">
									<strong>ثبت‌نام فروشنده در فرم کد یکبارمصرف</strong>
									<p>کاربر جدید «فروشنده هستم» را انتخاب می‌کند و نام فروشگاه و آدرس آن را وارد می‌کند. موبایل تأییدشده، شماره‌ی فروشگاه می‌شود و خود دکان فروشنده را می‌سازد.</p>
								</div>
								<label class="ashna-switch">
									<input type="checkbox" name="ashna[dokan_vendor_signup]" value="1" <?php checked( ! empty( $settings['dokan_vendor_signup'] ), true ); ?> />
									<span class="ashna-slider"></span>
								</label>
							</div>

							<ul class="ashna-check-list">
								<li>فروشنده بعد از ورود به <strong>پیشخوان فروشندگی</strong> می‌رود و بعد از ثبت‌نام به <strong>راه‌اندازی فروشگاه</strong> (همان فیلترهای خود دکان).</li>
								<li>تنظیم «فعال‌سازی خودکار فروش» و ایمیل‌های فروشنده‌ی جدید دکان رعایت می‌شود.</li>
								<li>فروشنده‌ای که موبایلش فقط در پروفایل فروشگاه ثبت شده هم با همان شماره وارد می‌شود.</li>
								<li>صفحه‌ی جداگانه‌ی ثبت‌نام فروشنده: <code dir="ltr">[ashna_otp_login vendor="yes"]</code></li>
							</ul>
						</div>

						<div class="ashna-card ashna-col-6" id="ashna-membership-card">
							<div class="ashna-card-head">
								<div class="ashna-card-head-title">
									<span class="ashna-card-icon is-purple">
										<svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.1" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
									</span>
									<div>
										<h2>افزونه‌های عضویت ویژه</h2>
										<p>نشست استاندارد وردپرس؛ سطح عضویت و نقش بلافاصله شناخته می‌شود</p>
									</div>
								</div>
							</div>

							<table class="ashna-modern-table ashna-integrations-table">
								<tbody>
									<tr>
										<td><strong>Ultimate Member</strong> <?php echo $detected_plugins['um']['active'] ? '<span class="ashna-pill is-ok">فعال</span>' : ''; ?></td>
										<td>حساب «در انتظار تأیید»، رد‌شده یا غیرفعال با کد هم وارد نمی‌شود؛ وضعیت کاربر جدید طبق تنظیم نقش؛ مسیر بعد از ورودِ نقش در صفحه‌ی ورود UM.</td>
									</tr>
									<tr>
										<td><strong>Paid Memberships Pro</strong> <?php echo $detected_plugins['pmpro']['active'] ? '<span class="ashna-pill is-ok">فعال</span>' : ''; ?></td>
										<td>عضو به صفحه‌ی حساب عضویت و غیرعضو به صفحه‌ی سطوح می‌رود (فیلتر <code>login_redirect</code>)؛ لینک و فرم ورود PMPro با آشنا.</td>
									</tr>
									<tr>
										<td><strong>MemberPress</strong> <?php echo $detected_plugins['memberpress']['active'] ? '<span class="ashna-pill is-ok">فعال</span>' : ''; ?></td>
										<td>شورت‌کد <code>[mepr-login-form]</code> با فرم آشنا؛ دسترسی به محتوا از روی کاربر وردپرس.</td>
									</tr>
									<tr>
										<td><strong>WooCommerce Memberships</strong> <?php echo $detected_plugins['wcm']['active'] ? '<span class="ashna-pill is-ok">فعال</span>' : ''; ?></td>
										<td>ورود از حساب کاربری ووکامرس و فیلتر <code>woocommerce_login_redirect</code>.</td>
									</tr>
								</tbody>
							</table>
						</div>
					</div>

					<!-- Role-based destinations after sign-in -->
					<div class="ashna-card" style="margin-top:20px;" id="ashna-role-redirect-card">
						<div class="ashna-card-head">
							<div class="ashna-card-head-title">
								<span class="ashna-card-icon is-cyan">
									<svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.1" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><polygon points="16.24 7.76 14.12 14.12 7.76 16.24 9.88 9.88 16.24 7.76"/></svg>
								</span>
								<div>
									<h2>مسیر بعد از ورود بر اساس نقش کاربر</h2>
									<p>خالی یعنی تنظیم کلی «پس از ورود، کاربر به کجا برود؟» (و فیلترهای افزونه‌ها) اعمال شود</p>
								</div>
							</div>
						</div>
						<div class="ashna-fields-grid ashna-cols-3">
							<?php
							$role_map = is_array( $settings['role_redirects'] ) ? $settings['role_redirects'] : array();
							foreach ( wp_roles()->get_names() as $role_key => $role_name ) :
								$is_admin_role = 'administrator' === $role_key;
								?>
								<div class="ashna-field">
									<label for="ashna-role-redirect-<?php echo esc_attr( $role_key ); ?>"><?php echo esc_html( translate_user_role( $role_name ) ); ?> <code dir="ltr"><?php echo esc_html( $role_key ); ?></code></label>
									<input type="text" id="ashna-role-redirect-<?php echo esc_attr( $role_key ); ?>" name="<?php echo esc_attr( $is_admin_role ? 'ashna[admin_redirect_url]' : 'ashna[role_redirects][' . $role_key . ']' ); ?>" value="<?php echo esc_attr( $is_admin_role ? $settings['admin_redirect_url'] : ( isset( $role_map[ $role_key ] ) ? $role_map[ $role_key ] : '' ) ); ?>" dir="ltr" placeholder="https://" />
								</div>
							<?php endforeach; ?>
						</div>
					</div>

					<!-- Elementor -->
					<div class="ashna-bento-row" style="margin-top:20px;">
						<!-- Box 2: Elementor Native Widgets Showcase -->
						<div class="ashna-card ashna-col-12">
							<div class="ashna-card-head">
								<div class="ashna-card-head-title">
									<span class="ashna-card-icon is-blue">
										<svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.1" stroke-linecap="round" stroke-linejoin="round"><polygon points="12 2 2 7 12 12 22 7 12 2"/><polyline points="2 17 12 22 22 17"/><polyline points="2 12 12 17 22 12"/></svg>
									</span>
									<div>
										<h2>ویجت‌های اختصاصی صفحه‌ساز المنتور</h2>
										<p>طراحی Drag &amp; Drop فرم ورود و دکمه مودال در المنتور</p>
									</div>
								</div>
								<span class="ashna-pill <?php echo ! empty( $elementor_active ) ? 'is-ok' : 'is-info'; ?>">
									<?php echo ! empty( $elementor_active ) ? 'المنتور فعال است' : 'پشتیبانی کامل'; ?>
								</span>
							</div>

							<div style="display:flex;flex-direction:column;gap:14px;">
								<div class="ashna-el-widget-card">
									<div class="ashna-el-widget-icon">
										<svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.1"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
									</div>
									<div class="ashna-el-widget-body">
										<div class="ashna-el-widget-title-row">
											<strong>۱. ویجت «فرم ورود و ثبت‌نام یکبارمصرف (آشنا)»</strong>
											<span class="ashna-pill is-info">کارت کامل در برگه</span>
										</div>
										<p>نمایش کارت کامل ورود و ثبت‌نام در هر برگه المنتوری با قابلیت سفارشی‌سازی عنوان، زیرعنوان، رنگ برند، گردی گوشه‌ها و پیش‌نمایش زنده در محیط ادیتور.</p>
									</div>
								</div>

								<div class="ashna-el-widget-card">
									<div class="ashna-el-widget-icon is-purple">
										<svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.1"><rect x="3" y="3" width="18" height="18" rx="2"/><path d="M9 12h6"/><path d="M12 9v6"/></svg>
									</div>
									<div class="ashna-el-widget-body">
										<div class="ashna-el-widget-title-row">
											<strong>۲. ویجت «دکمه پاپ‌آپ ورود سریع (آشنا)»</strong>
											<span class="ashna-pill is-info">ویژه هدرساز المنتور</span>
										</div>
										<p>دکمه هوشمند برای هدر سایت: برای مهمانان مودال پاپ‌آپ ورود را باز می‌کند و برای کاربران واردشده نام کاربر یا لینک «حساب کاربری» را نمایش می‌دهد.</p>
									</div>
								</div>
							</div>
						</div>
					</div>

					<!-- BOTTOM CARD: Ready-to-Copy Shortcodes -->
					<div class="ashna-card" style="margin-top:20px;">
						<div class="ashna-card-head">
							<div class="ashna-card-head-title">
								<span class="ashna-card-icon is-cyan">
									<svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.1" stroke-linecap="round" stroke-linejoin="round"><polyline points="16 18 22 12 16 6"/><polyline points="8 6 2 12 8 18"/></svg>
								</span>
								<div>
									<h2>شورت‌کدها و کلاس‌های آماده (کلیک برای کپی)</h2>
									<p>از این شورت‌کدها در برگه‌های گوتنبرگ، ابزارک‌ها یا منوی هدر قالب استفاده کنید</p>
								</div>
							</div>
						</div>
						<div class="ashna-copy-cards-grid">
							<div class="ashna-copy-card">
								<strong>فرم کامل ورود و ثبت‌نام در برگه</strong>
								<code dir="ltr">[ashna_otp_login]</code>
								<button type="button" class="ashna-copy-btn" data-copy="[ashna_otp_login]">کپی شورت‌کد</button>
							</div>

							<div class="ashna-copy-card">
								<strong>دکمه بازکننده مودال پاپ‌آپ (مناسب هدر)</strong>
								<code dir="ltr">[ashna_otp_button text="ورود / عضویت"]</code>
								<button type="button" class="ashna-copy-btn" data-copy='[ashna_otp_button text="ورود / عضویت"]'>کپی شورت‌کد</button>
							</div>

							<div class="ashna-copy-card">
								<strong>صفحه‌ی ثبت‌نام فروشنده‌ی دکان با کد یکبارمصرف</strong>
								<code dir="ltr">[ashna_otp_login vendor="yes"]</code>
								<button type="button" class="ashna-copy-btn" data-copy='[ashna_otp_login vendor="yes"]'>کپی شورت‌کد</button>
							</div>

							<div class="ashna-copy-card">
								<strong>کلاس CSS بازکننده پاپ‌آپ روی هر دکمه دلخواه</strong>
								<code dir="ltr">ashna-open-modal</code>
								<button type="button" class="ashna-copy-btn" data-copy="ashna-open-modal">کپی کلاس</button>
							</div>
						</div>
					</div>
				</section>
