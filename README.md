# آشنا | پلاگین ورود و ثبت‌نام یکبارمصرف وردپرس (نسخهٔ 3.0.0)

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
  - **فراز اس‌ام‌اس (FarazSMS)** (پشتیبانی از هر دو روش `API Key` و `نام کاربری / رمز عبور`)
  - **ملی‌پیامک (Melipayamak)** (وب‌سرویس خدماتی `BaseServiceNumber`)
  - **کاوه‌نگار (Kavenegar)** (وب‌سرویس اعتبارسنجی `verify/lookup.json`)
  - **آی‌پی‌پنل (IPPanel)** (پشتیبانی از وب‌سرویس جدید `Edge API`، `API Key` و کلاسیک)
  - **زنجیره ۳ درگاه پشتیبان خودکار (Failover Chain):** تعریف تا ۳ درگاه پشتیبان به ترتیب اولویت برای ارسال خودکار در صورت قطعی یا اتمام شارژ درگاه اصلی
- **پیام‌رسان بله (Bale Messenger):**
  - پشتیبانی از **وب‌سرویس رسمی سفیر بله (Safir OTP API)** و **ربات بله (Bale Bot API)**
  - قابلیت **ارسال هوشمند ترکیبی (Fallback)**: اول ارسال در بله و در صورت عدم عضویت کاربر، ارسال خودکار از طریق پیامک
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
│   │   └── class-ashna-security.php               # موتور فایروال، Rate Limit، قفل امنیتی و کپچا
│   ├── services/                                  # لایه سرویس‌های کاربردی (Application Services)
│   │   ├── class-ashna-auth.php                   # سرویس احراز هویت AJAX، ثبت‌نام خودکار و ورود
│   │   ├── class-ashna-frontend.php               # شورت‌کدها، مودال سراسری و جایگزین wp-login.php
│   │   └── class-ashna-woocommerce.php            # یکپارچگی کامل با ووکامرس و پروفایل کاربران
│   ├── gateways/                                  # لایه درایورهای ارسال پیام (Gateway Drivers)
│   │   ├── interface-ashna-gateway.php            # قرارداد استاندارد درگاه‌ها (Interface)
│   │   ├── abstract-ashna-gateway.php             # کلاس پایه انتزاعی درگاه‌ها (Abstract Base Class)
│   │   ├── class-ashna-gateway-manager.php        # مدیر درگاه‌ها و زنجیره ۳ مرحله‌ای Failover
│   │   ├── class-ashna-gateway-sandbox.php        # درایور آزمایشی (Sandbox)
│   │   ├── class-ashna-gateway-smsir.php          # درایور SMS.ir
│   │   ├── class-ashna-gateway-kavenegar.php      # درایور کاوه‌نگار
│   │   ├── class-ashna-gateway-melipayamak.php    # درایور ملی‌پیامک
│   │   ├── class-ashna-gateway-farazsms.php       # درایور فراز اس‌ام‌اس
│   │   ├── class-ashna-gateway-ippanel.php        # درایور آی‌پی‌پنل
│   │   ├── class-ashna-gateway-bale.php           # درایور پیام‌رسان بله (سفیر + ربات)
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
                // ارسال درخواست وب‌سرویس با $this->http_request(...)
                return true;
            }
        }
        Ashna_Gateway_Manager::register_gateway( new My_Custom_SMS_Gateway() );
    }
} );
```
