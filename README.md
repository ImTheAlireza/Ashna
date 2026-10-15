# آشنا | پلاگین ورود و ثبت‌نام یکبارمصرف وردپرس (نسخهٔ 3.0.0)

> محصولی از **پارسنا** — [parsena.ir/ashna](https://parsena.ir/ashna)

**آشنا (Ashna)** یک افزونه جامع، سازمانی (Enterprise) و کاملاً ماژولار برای **ورود و ثبت‌نام یکپارچه کاربران با کد یکبارمصرف (OTP)** در وردپرس و ووکامرس است.

---

## نیازمندی‌های نسخهٔ لایسنس‌دار فعلی

- وردپرس ۵.۸ یا بالاتر.
- **PHP حداقل 8.2**؛ وردپرس در فعال‌سازی استاندارد، نسخه‌های پایین‌تر را رد می‌کند. این تغییر، PHP هاست را ارتقا نمی‌دهد.
- ionCube Loader فعال در PHP وب و سازگار با نسخهٔ PHP و فایل رمزگذاری‌شدهٔ لایسنس. حداقل PHP به معنی تضمین سازگاری فایل لایسنس با تمام نسخه‌های جدیدتر نیست.
- فایل رسمی `RTL_License_c1595a7238d64f3a.php` با همان هش قبلی `4f106eb02a251f2a6951ca3f530596590081002d`؛ نسخهٔ جایگزین مخصوص PHP 7.4 استفاده نمی‌شود.
- برای بستهٔ قابل نصب، از `deliverables/ashna-otp.zip` استفاده کنید. فایل واقعی لایسنس در مخزن موجود نیست؛ پیش از نصب/جایگزینی، فایل معتبر متناظر را کنار `ashna.php` در بسته قرار دهید. از سایت و فایل لایسنس قبلی نسخهٔ پشتیبان بگیرید.

نسخهٔ جداگانهٔ بدون لایسنس و ابزار عیب‌یابی، نیازمندی‌های مستقل خود را دارند. پیش از تغییر PHP سایت اصلی، سازگاری قالب، پرداخت و افزونه‌ها را روی کپی آزمایشی بررسی کنید.

## امکانات کلیدی

### ۱. پشتیبانی از کانال‌ها و درگاه‌های متنوع + ۳ درگاه پشتیبان (Multi-Gateway Failover)
- **درگاه‌های پیامکی ایرانی (ارسال سریع خدماتی / پترن):**
  - **SMS.ir** (وب‌سرویس جدید REST v1 - `api.sms.ir/v1/send/verify`)
  - **فراز اس‌ام‌اس (FarazSMS)** (وب‌سرویس جدید ایران‌پیامک `api.iranpayamak.com/ws/v1/sms/pattern` با `Api-Key`)
  - **ملی‌پیامک (Melipayamak)** (وب‌سرویس کنسول `console.melipayamak.com` با کلید API؛ نام کاربری و رمز قدیمی فقط به‌عنوان پشتیبان)
  - **کاوه‌نگار (Kavenegar)** (وب‌سرویس اعتبارسنجی `verify/lookup.json`)
  - **آی‌پی‌پنل (IPPanel)** (وب‌سرویس `Edge` در `edge.ippanel.com`)
  - **راه‌پیام (MsgWay)** (پیامک با `api.msgway.com`؛ همان کلید برای پیام‌رسان‌ها هم کار می‌کند)
  - **زنجیره ۳ درگاه پشتیبان خودکار (Failover Chain):** تعریف تا ۳ درگاه پشتیبان به ترتیب اولویت برای ارسال خودکار در صورت قطعی یا اتمام شارژ درگاه اصلی
  - **ضدقطعی سریع (بدون معطلی کاربر):**
    - هر درگاه حداکثر چند ثانیه فرصت پاسخ دارد (پیش‌فرض ۵ ثانیه، قابل تنظیم بین ۲ تا ۱۵) و برقراری اتصال حداکثر ۳ ثانیه؛ قبلاً هر درخواست تا ۱۵ ثانیه منتظر می‌ماند.
    - سقف زمانی کل برای همه پشتیبان‌ها (پیش‌فرض ۱۵ ثانیه).
    - **یادگیری زمان پاسخ:** بعد از ۵ ارسال موفق، مهلت هر درگاه ۴ برابر زمان پاسخ معمول خودش می‌شود (حداقل ۲ ثانیه)؛ یعنی درگاهی که معمولاً ۰٫۴ ثانیه جواب می‌دهد، در قطعی ۲ ثانیه بعد کنار می‌رود، نه ۵ ثانیه.
    - **بررسی خودکار سلامت (هر ۵ دقیقه، در پس‌زمینه):** درگاه‌های مسیر ارسال با یک درخواست HEAD بدون مصرف اعتبار بررسی می‌شوند؛ درگاه قطع‌شده قبل از رسیدن اولین کاربر کنار گذاشته می‌شود.
    - **Circuit Breaker:** درگاهی که قطع، کند، خطای 5xx، کلید نامعتبر یا اعتبار ناکافی دارد، چند دقیقه کنار گذاشته می‌شود و کاربران بعدی بدون تأخیر از پشتیبان کد می‌گیرند. بعد از این مدت، یک امتحان کوتاه (حداکثر ۳ ثانیه) انجام می‌شود و با خطای دوباره، مدت کنار گذاشتن دوبرابر می‌شود (حداکثر ۳۰ دقیقه).
    - خطاهای مربوط به یک گیرنده (مثل «کاربر اکانت بله ندارد») هیچ‌وقت درگاه را برای بقیه از کار نمی‌اندازند؛ شماره‌ی ردشده هم بین درگاه‌های پیامکی دست‌به‌دست نمی‌شود.
    - جدول وضعیت لحظه‌ای درگاه‌ها در پنل، مسیر کامل هر ارسال در لاگ‌ها، و پیام مرحله‌ای در فرم («در حال ارسال کد…» و «در حال ارسال از مسیر پشتیبان…»).
- **پیام‌رسان بله (Bale Messenger):**
  - **سفیر نسخه‌ی ۳** (`safir.bale.ai/api/v3/send_message` با `api-access-key`): هر کد فقط با یک درخواست، از طرف بازوی رسمی «رمز یکبارمصرف» بله با نام بازوی شما و دکمه‌ی کپی؛ کلید `request_id` جلوی ارسال تکراری را می‌گیرد.
  - **ربات بله (Bale Bot API)** برای بازوی اختصاصی
  - قابلیت **ارسال هوشمند ترکیبی (Fallback)**: اول ارسال در بله و در صورت عدم عضویت کاربر، ارسال خودکار از طریق پیامک
- **پیام‌رسان‌های ایرانی با راه‌پیام (MsgWay):** ایتا، روبیکا، بله، گپ و آی‌گپ با یک کلید
  - حالت «ارسال هوشمند» (راه‌پیام بین پیام‌رسان‌های فعال در پنلش مسیر را انتخاب می‌کند)، یا فقط گپ، فقط آی‌گپ، یا پیام‌رسان دیگر با شناسه‌ی provider
  - دو مسیر جدید: «فقط پیام‌رسان‌های ایرانی» و «اول پیام‌رسان‌های ایرانی، بعد پیامک»
- **ایمیل (`wp_mail`):** ارسال کد تایید با قالب HTML فارسی و ریسپانسیو
- **حالت تست / آزمایشی (Sandbox):** تست کامل فرآیند در محیط لوکال بدون نیاز به پنل پیامک

### ۲. داشبورد مدرن SaaS + استودیو طراحی زنده (Live Preview Studio)
- پنل مدیریت مدرن با منوی عمودی، حالت تاریک/روشن (Dark Mode) و ذخیره آنی AJAX (`Ctrl+S`)
- نمودار تحلیلی ۷ روزه، نرخ تبدیل (Conversion Rate) و بررسی سلامت سیستم
- استودیو طراحی ظاهر با پیش‌نمایش زنده ایزوله (Live Preview)، ۴ قالب آماده، انتخابگر رنگ و CSS سفارشی

### ۳. امنیت، فایروال و کپچای بومی و جهانی
- پشتیبانی از **آرکپچا (Arcaptcha.ir)**، **کپچای ریاضی داخلی**، **Cloudflare Turnstile** و **Google reCAPTCHA v3**
- محافظت Brute-Force روی کدهای OTP و رمز عبور ثابت + جدول قفل‌های امنیتی فعال با قابلیت رفع مسدودی آنی (Unlock)
- لیست سیاه (Blacklist) و لیست سفید (Whitelist) با پشتیبانی از الگوی `*` و تشخیص هوشمند IP پشت کلودفلر/ابرآروان
- پاکسازی خودکار روزانه لاگ‌های قدیمی با زمان‌بند استاندارد وردپرس (`WP-Cron`)

---

## معماری ماژولار و توسعه‌پذیر (Clean Architecture)

```text
ashna-otp/
├── index.php                                      # فایل اصلی: سربرگ وردپرس و ثابت‌های افزونه
├── ashna.php                                      # بوت‌استرپ و بررسی لایسنس راست‌چین
├── uninstall.php                                  # پاکسازی کامل دیتابیس و کرون‌ها هنگام حذف
├── includes/
│   ├── class-ashna-autoloader.php                 # بارگذار خودکار کلاس‌ها (SPL Autoloader)
│   ├── class-ashna-plugin.php                     # هسته مرکزی مدیریت چرخه حیات و سرویس‌ها
│   ├── core/                                      # لایه زیرساخت و هسته (Core Layer)
│   │   ├── class-ashna-activator.php              # مدیریت جداول دیتابیس و زمان‌بند WP-Cron
│   │   ├── class-ashna-helper.php                 # مدیریت تنظیمات با کش درون‌حافظه‌ای و نرمال‌سازی
│   │   ├── class-ashna-logger.php                 # ریپازیتوری لاگ‌ها، آمار و نمودار ۷ روزه
│   │   ├── class-ashna-session.php                # نشست بومی وردپرس برای هر سه روش ورود + مسیر بعد از ورود
│   │   └── class-ashna-security.php               # موتور فایروال، Rate Limit، قفل امنیتی و کپچا
│   ├── services/                                  # لایه سرویس‌های کاربردی (Application Services)
│   │   ├── class-ashna-auth.php                   # سرویس احراز هویت AJAX، ثبت‌نام خودکار و ورود
│   │   ├── class-ashna-frontend.php               # شورت‌کدها، مودال سراسری و جایگزین wp-login.php
│   │   └── class-ashna-woocommerce.php            # یکپارچگی کامل با ووکامرس و پروفایل کاربران
│   ├── gateways/                                  # لایه درایورهای ارسال پیام (Gateway Drivers)
│   │   ├── interface-ashna-gateway.php            # قرارداد استاندارد درگاه‌ها (Interface)
│   │   ├── abstract-ashna-gateway.php             # کلاس پایه انتزاعی درگاه‌ها (Abstract Base Class)
│   │   ├── class-ashna-gateway-manager.php        # مدیر درگاه‌ها و زنجیره ۳ مرحله‌ای Failover
│   │   ├── class-ashna-failover.php               # ضدقطعی سریع: timeout، سقف زمانی، Circuit Breaker و سلامت درگاه‌ها
│   │   ├── class-ashna-gateway-sandbox.php        # درایور آزمایشی (Sandbox)
│   │   ├── class-ashna-gateway-smsir.php          # درایور SMS.ir
│   │   ├── class-ashna-gateway-kavenegar.php      # درایور کاوه‌نگار
│   │   ├── class-ashna-gateway-melipayamak.php    # درایور ملی‌پیامک
│   │   ├── class-ashna-gateway-farazsms.php       # درایور فراز اس‌ام‌اس
│   │   ├── class-ashna-gateway-ippanel.php        # درایور آی‌پی‌پنل
│   │   ├── class-ashna-gateway-bale.php           # درایور پیام‌رسان بله (سفیر v3 + ربات)
│   │   ├── class-ashna-gateway-msgway.php         # درایور راه‌پیام (پیامک)
│   │   ├── class-ashna-gateway-msgway-messenger.php # پیام‌رسان‌های ایرانی از طریق راه‌پیام
│   │   └── class-ashna-gateway-email.php          # درایور ایمیل وردپرس
│   └── admin/                                     # لایه پنل مدیریت (Admin UI & Partials)
│       ├── class-ashna-admin.php                  # کنترلر پنل مدیریت، AJAX و خروجی CSV
│       └── views/
│           ├── settings-page.php                  # پوسته اصلی داشبورد مدیریت
│           ├── logs-page.php                      # صفحه گزارش و لاگ کدها
│           └── partials/                          # تب‌های ماژولار پنل تنظیمات
│               ├── tab-dashboard.php
│               ├── tab-auth-flow.php
│               ├── tab-sms-gateways.php
│               ├── tab-bale-email.php
│               ├── tab-appearance.php
│               ├── tab-woocommerce.php
│               ├── tab-security.php
│               └── tab-tools.php
├── assets/
│   ├── css/ (frontend.css, admin.css)
│   └── js/  (frontend.js, admin.js)
└── templates/
    ├── login-form.php                             # قالب فرم ورود (قابل بازنویسی در قالب: yourtheme/ashna/login-form.php)
    ├── wc-myaccount-login.php                     # قالب جایگزین حساب کاربری ووکامرس
    └── email-otp.php                              # قالب HTML ایمیل کد تایید
```

---

## افزودن درگاه پیامک سفارشی توسط توسعه‌دهندگان

به‌واسطه معماری `Ashna_Abstract_Gateway` و متد `Ashna_Gateway_Manager::register_gateway()`، هر توسعه‌دهنده‌ای می‌تواند بدون دستکاری هسته افزونه، درگاه جدیدی را در قالب یا افزونه جانبی ثبت کند:

```php
add_action( 'init', function() {
    if ( class_exists( 'Ashna_Abstract_Gateway' ) ) {
        class My_Custom_SMS_Gateway extends Ashna_Abstract_Gateway {
            public function get_id()    { return 'my_gateway'; }
            public function get_title() { return 'درگاه اختصاصی من'; }
            public function send( $recipient, $code ) {
                // post_json() / remote_post() خودکار محدودیت زمانی ضدقطعی را اعمال می‌کنند.
                $res = $this->post_json( 'https://api.example.com/send', array( 'to' => $recipient, 'code' => $code ) );
                if ( is_wp_error( $res ) ) {
                    return $res;
                }
                if ( 422 === $res['code'] ) {
                    // شماره‌ی نامعتبر: درگاه پیامکی بعدی امتحان نمی‌شود و درگاه کنار گذاشته نمی‌شود.
                    return new WP_Error( 'my_gateway_invalid', 'شماره نامعتبر است.', array( 'ashna_error_type' => 'input' ) );
                }
                return 200 === $res['code'] ? true : new WP_Error( 'my_gateway_error', 'خطای درگاه' );
            }
        }
        Ashna_Gateway_Manager::register_gateway( new My_Custom_SMS_Gateway() );
    }
} );
```

### نوع خطا و هوک‌های ضدقطعی

- درگاه می‌تواند نوع خطا را صریح اعلام کند: `array( 'ashna_error_type' => $type )` در داده‌ی `WP_Error`.
  - `timeout`، `connect`، `network`، `server`، `auth` و `account`: قطعی؛ درگاه موقتاً کنار گذاشته می‌شود.
  - `api` و `config`: فقط همین درخواست به درگاه بعدی می‌رود.
  - `input`: شماره رد شده؛ بقیه‌ی درگاه‌های همان کانال امتحان نمی‌شوند (کانال دیگر، مثلاً بله، همچنان امتحان می‌شود).
- بدون اعلام صریح، نوع خطا از وضعیت شبکه و کد HTTP تشخیص داده می‌شود.
- فیلتر `ashna_otp_failover_budget`: سقف زمان کل ارسال یک کد (ثانیه).
- اکشن `ashna_otp_gateway_failure`: بعد از هر تلاش ناموفق، با آرگومان‌های `$gateway_id, $type, $wp_error, $ms, $sidelined`.

---

## نشست کاربر بعد از ورود (هماهنگ با هسته وردپرس و ووکامرس)

آشنا نشست یا توکن جداگانه نمی‌سازد. ورود با کد، رمز عبور یا Passkey همان نشستی را می‌سازد که `wp-login.php` می‌سازد:

- کوکی‌های استاندارد وردپرس با `WP_Session_Tokens`، تغییر کاربر جاری در همان درخواست و اجرای هوک `wp_login`؛
- انتقال فوری سبد خرید و نشست مهمان ووکامرس به مشتری (مثل `wc_set_customer_auth_cookie()`)؛
- ورود مجدد، نشست قبلیِ همان مرورگر را حذف می‌کند و تعویض حساب تمیز انجام می‌شود؛
- قبل از ساخت نشست، فیلتر `wp_authenticate_user` (افزونه‌های تأیید عضویت، مسدودسازی و محدودکننده‌ی ورود) و در چندسایته `is_user_spammy()` بررسی می‌شود؛
- ورود با رمز از `wp_authenticate()` و کل زنجیره‌ی `authenticate` عبور می‌کند (تأیید دومرحله‌ای رعایت می‌شود)؛
- مسیر بعد از ورود از فیلترهای `login_redirect` و `woocommerce_login_redirect` هم پیروی می‌کند (مثلاً پیشخوان فروشنده‌ی Dokan).

هوک‌های توسعه‌دهنده:

- فیلتر `ashna_otp_can_login( true|WP_Error $allowed, WP_User $user, array $context )`: رد کردن ورود.
- اکشن `ashna_otp_logged_in( WP_User $user, array $context )`: بعد از هر ورود موفق (`$context['method']`: `otp`، `password` یا `passkey`).
- فیلتر `ashna_otp_login_response( array $payload, WP_User $user )`: افزودن داده به پاسخ ورود (مثلاً توکن برای فرانت‌اند هدلس).
- فیلترهای `ashna_otp_login_redirect` و `ashna_otp_pinned_redirect`: تغییر مسیر بعد از ورود.
- فیلتر `ashna_otp_remember_login`: ماندگار بودن نشست.
- فیلترهای `ashna_otp_phone_candidates` و `ashna_otp_is_staff_account`: یافتن حساب بر اساس شماره.
