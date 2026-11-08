# SendMessageBots - سامانه یکپارچه ارسال پیام و نوتیفیکیشن

سامانه بومی، سبک و امن برای ارسال خودکار پیام، عکس و فایل‌های ضمیمه (PDF، فاکتور و...) به کاربران از طریق پیام‌رسان‌های **تلگرام (Telegram)** و **بله (Bale)** همراه با پنل مدیریت تحت وب، سیستم تعاملی دریافت شماره تماس، ماشین وضعیت مکالمه و API اختصاصی برنامه‌های فرستنده.

توسعه‌یافته با **Plain PHP 8.x + MySQLi**، بدون وابستگی به Composer یا فریم‌ورک‌های سنگین.

---

## ✨ قابلیت‌های کلیدی

- **پشتیبانی همزمان از تلگرام و بله:** ارسال پیام از طریق ساختار ماژولار و رابط یکپارچه [`MessengerClientInterface`](src/Messenger/MessengerClientInterface.php).
- **سیستم مدیریت و ثبت خودکار مخاطبین:** ثبت خودکار مخاطبین از طریق دکمه «اشتراک‌گذاری شماره» در بات تلگرام و بله، به همراه امکان ثبت دستی، ویرایش، حذف، تنظیم پلتفرم ترجیحی و اتصال مستقیم شناسه‌های چت (Chat ID) و نام‌های کاربری در پنل مدیریت.
- **ارسال نوتیفیکیشن از طریق API و متد PHP:** امکان ارسال متن، لینک و فایل ضمیمه (عکس یا PDF) به شماره تلفن مخاطب از طریق [`api_send.php`](api_send.php) یا کلاس [`NotificationSender`](src/NotificationSender.php).
- **مدیریت و تقسیم هوشمند پیام‌های طولانی (Message Chunking):** متون طولانی بر اساس خطوط، خطوط جداکننده و شروع بخش‌ها (فروشگاه‌ها/آیتم‌ها) بدون ناقص شدن محتوا به چند پیام متوالی تقسیم می‌شوند؛ هر بخش با شماره‌گذاری `X/Y` در خط نخست و تکرار هدر اصلی ارسال می‌شود تا عرض پیام‌ها یکنواخت و خوانا باقی بماند. متون همراه با فایل بالای ۱۰۲۴ کاراکتر نیز به صورت تفکیک‌شده ارسال می‌گردند.
- **سیستم پیامک دعوت خودکار (SMS Fallback):** در صورت عدم اتصال شماره مخاطب به تلگرام یا بله، سیستم به صورت هوشمند از طریق پروکسی پیامک، پیامک دعوتی حاوی لینک راه‌اندازی ربات برای مخاطب ارسال می‌کند.
- **امنیت سطح بالا:**
  - فایروال دسترسی وب در [`.htaccess`](.htaccess) و [`router.php`](router.php) برای محافظت از دایرکتوری‌های `src/`، `bin/`، `database/`، `scripts/` و فایل‌های پیکربندی.
  - اعتبارسنجی توکن محرمانه وبهوک‌ها (`WEBHOOK_SECRET`) برای جلوگیری از حملات جعل درخواست.
  - جداسازی محل ذخیره‌سازی فایل‌های ضمیمه در خارج از مسیر وب (`storage/attachments`) و ارائه امن فقط به مدیران احرازشده.
  - سیستم ضدحمله Brute-Force مبتنی بر دیتابیس با جدول `login_throttles` برای مسدودسازی IP در صورت تلاش‌های ناموفق ورود به پنل.
  - حفاظت در برابر حملات CSRF در فرم‌های پنل مدیریت.
  - کوئری‌های کاملاً پارامتریزه (Prepared Statements) در تمام بخش‌ها.
- **پنل مدیریت مدرن:** طراحی واکنش‌گرا و راست‌چین با Tailwind CSS، تقویم شمسی (جلالی)، مدیریت مخاطبین، کانال‌های ارتباطی، برنامه‌های متصل و گزارش لاگ پیام‌ها.

---

## 📁 ساختار مخزن (Repository Structure)

```text
SendMessageBots/
├── admin/                 # پنل مدیریت تحت وب (داشبورد، لاگ‌ها، مخاطبین، برنامه‌ها)
├── assets/                # دارایی‌های عمومی (لوگوی ربات، استایل‌ها)
├── bin/                   # ابزارهای خط فرمان (CLI)
│   ├── check_requirements.php  # بررسی سلامت و نیازمندی‌های سیستم
│   ├── create_admin.php        # ساخت حساب کاربری مدیر
│   ├── register_app.php        # ثبت برنامه فرستنده جدید و دریافت app_key
│   ├── register_webhooks.php   # ثبت خودکار وبهوک‌های تلگرام و بله
│   └── setup_db.php            # راه‌اندازی دیتابیس و اجرای مایگریشن‌ها
├── database/              # اسکریپت‌های SQL
│   ├── schema.sql              # ساختار اولیه دیتابیس
│   ├── migrate_admin.sql       # جدول مدیران
│   └── migrate_attachments.sql  # تغییرات فیلد و جدول ضمیمه‌ها
├── docs/                  # مستندات تکمیلی استقرار
│   └── WINDOWS_DEPLOYMENT_GUIDE.md  # راهنمای جامع استقرار سرور ویندوز
├── scripts/               # اسکریپت‌های اجرایی ویندوز (.bat)
│   ├── check_env.bat           # بررسی وضعیت محیط اجرا
│   ├── compile_tailwind.bat    # کامپایل استایل‌های Tailwind
│   ├── service_control.bat     # کنترل پنل سرویس ویندوز (شروع، توقف، لاگ‌ها)
│   ├── service_install.bat     # نصب سرویس پس‌زمینه در ویندوز (NSSM / Task Scheduler)
│   ├── service_uninstall.bat   # حذف سرویس ویندوز
│   ├── set_webhooks.bat        # اسکریپت ثبت وبهوک‌ها با دریافت آدرس دامنه
│   ├── setup_database.bat      # راه‌اندازی خودکار پایگاه داده
│   └── start_server.bat        # اجرای مستقیم وب‌سرور توسعه/تولید
├── src/                   # کدهای اصلی و کلاس‌های سیستم
│   ├── bootstrap.php           # فایل بارگذاری مرکزی (Single Require Hub)
│   ├── Db.php                  # درایور اتصال امن به پایگاه داده
│   ├── Env.php                 # پارسر فایل تنظیمات محیطی (.env)
│   ├── AttachmentValidator.php # اعتبارسنجی MIME و ذخیره امن فایل‌ها
│   ├── ConversationHandler.php # مدیریت تعاملی پیام‌ها و مکالمات بات
│   ├── NotificationSender.php  # موتور ارسال پیام به کاربران بر اساس شماره
│   ├── PhoneNormalizer.php     # استانداردسازی شماره‌های ایرانی
│   ├── WebhookGuard.php        # گارد امنیتی بررسی توکن محرمانه وبهوک‌ها
│   ├── Jalali.php              # تبدیل تاریخ میلادی به شمسی
│   └── Messenger/              # ماژول ارسال پیام تلگرام و بله
├── tools/                 # ابزارهای کمکی
│   ├── example_send.php        # نمونه کد ارسال نوتیفیکیشن با PHP
│   └── run_hidden.vbs          # اجرای بی‌صدا در پس‌زمینه ویندوز
├── .env.example           # قالب متغیرهای محیطی
├── .htaccess              # قواعد امنیتی وب‌سرور آپاچی
├── docker-compose.yml     # پیکربندی تونل ngrok و محیط Docker
├── Dockerfile             # تصویر رسمی کانتینر
├── index.php              # صفحه فرود و معرفی در ریشه دامنه
├── router.php             # روتر امنیتی وب‌سرور پیش‌فرض PHP
├── api_send.php           # اندپوینت API دریافت درخواست‌های ارسال پیام
├── bale_webhook.php       # اندپوینت دریافت وب‌هوک بله
└── telegram_webhook.php   # اندپوینت دریافت وب‌هوک تلگرام
```

---

## ⚡ راه‌اندازی و استقرار سریع (ویندوز / XAMPP)

### ۱. تنظیم فایل `.env`
فایل `.env.example` را کپی کرده و به `.env` تغییر نام دهید، سپس مقادیر توکن‌ها و دیتابیس را پر کنید:
```ini
DB_HOST=127.0.0.1
DB_PORT=3306
DB_NAME=notifier
DB_USER=root
DB_PASS=

# توکن‌های ربات‌ها (از BotFather در تلگرام و بازوی بات‌ساز در بله)
TELEGRAM_BOT_TOKEN=your_telegram_bot_token
BALE_BOT_TOKEN=your_bale_bot_token

# کلید محرمانه امنیتی برای وبهوک‌ها
WEBHOOK_SECRET=your_strong_secret_token

# توکن ngrok (در صورت استفاده از تونل)
NGROK_AUTHTOKEN=your_ngrok_token
```

### ۲. بررسی سلامت سیستم و ایجاد دیتابیس
1. در پنل XAMPP سرویس **MySQL** را روشن کنید.
2. اسکریپت **`scripts/check_env.bat`** را اجرا کنید تا از فعال بودن اکستنشن‌های PHP اطمینان حاصل شود.
3. روی **`scripts/setup_database.bat`** دوبار کلیک کنید (یا دستور `php bin/setup_db.php` را در خط فرمان اجرا نمایید) تا دیتابیس و تمام جداول به صورت خودکار ایجاد شوند.
4. سپس یک حساب کاربری مدیر اصلی ایجاد کنید:
   ```bash
   php bin/create_admin.php admin your_password "مدیر سیستم"
   ```

### ۳. اجرای وب‌سرور
می‌توانید به یکی از دو روش زیر سرور را اجرا کنید:

#### روش الف) اجرای مستقیم با اسکریپت اختصاصی (بدون نیاز به تنظیم آپاچی):
روی **`scripts/start_server.bat`** دوبار کلیک کنید. سرور روی پورت `8080` (یا پورت تنظیم‌شده در `APP_PORT`) بالا می‌آید:
- پنل ادمین: `http://localhost:8080/admin/`

#### روش ب) میزبانی در Apache (XAMPP VirtualHost):
در فایل `C:\xampp\apache\conf\extra\httpd-vhosts.conf` تنظیم زیر را قرار دهید:
```apache
<VirtualHost *:80>
    DocumentRoot "E:/php projects/SendMessageBots"
    ServerName localhost
    <Directory "E:/php projects/SendMessageBots">
        Options Indexes FollowSymLinks
        AllowOverride All
        Require all granted
    </Directory>
</VirtualHost>
```

---

## 🌐 تنظیم تونل اینترنتی ngrok با Docker

برای تست وب‌هوک‌ها بر روی لوکال‌هاست، سرویس `ngrok` در `docker-compose.yml` پیکربندی شده تا به وب‌سرور میزبان ویندوز (`host.docker.internal:8080`) متصل شود:

```bash
docker compose up -d ngrok
```
سپس آدرس عمومی اختصاص داده شده (مثلاً `https://your-subdomain.ngrok-free.dev`) را در مرحله بعد استفاده کنید. داشبورد وضعیت و لاگ درخواست‌های ngrok در آدرس `http://localhost:4040` در دسترس است.

---

## 🔗 ثبت وبهوک‌ها (Webhooks)

پس از بالا آمدن سرور و دریافت دامنه اینترنتی یا آدرس HTTPS ngrok، اسکریپت ثبت وبهوک را اجرا کنید:
```bash
php bin/register_webhooks.php "https://your-subdomain.ngrok-free.dev"
```
یا فایل **`scripts/set_webhooks.bat`** را اجرا کرده و آدرس HTTPS را وارد نمایید. این ابزار به صورت خودکار:
1. وبهوک تلگرام را همراه با Secret Token تنظیم می‌کند.
2. وبهوک بله را ثبت و تنظیم می‌نماید.
3. منوی دستورات ربات را در هر دو پیام‌رسان بارگذاری می‌کند.

---

## 🚀 اجرای ۲۴/۷ به عنوان سرویس ویندوز (Windows Service)

جهت اجرای مداوم در سرور حتی پس از ری‌استارت و بدون نیاز به لاگین کاربر:
1. روی **`scripts/service_install.bat`** راست‌کلیک کرده و **Run as administrator** را بزنید.
2. گزینه **[2] (Windows Task Scheduler)** یا گزینه **[1] (NSSM)** را انتخاب کنید.
3. وضعیت سرویس و مشاهده خروجی لاگ‌ها از طریق **`scripts/service_control.bat`** مدیریت می‌شود.

---

## 📡 راهنمای ارسال پیام از طریق API ([`api_send.php`](api_send.php))

### ۱. ثبت برنامه فرستنده
برای دریافت کلید دسترسی (`app_key`) برای سیستم یا نرم‌افزار حسابداری خود:
```bash
php bin/register_app.php "سیستم مالی"
```

### ۲. ارسال درخواست POST
```bash
curl -X POST "http://localhost:8080/api_send.php" \
  -H "X-API-Key: YOUR_APP_KEY" \
  -F "phone=09123456789" \
  -F "message=سلام! فاکتور شماره ۱۲۳۴ صادر گردید." \
  -F "link=https://example.com/invoice/1234" \
  -F "attachment=@/path/to/invoice.pdf" \
  -F "attachment_type=document"
```

---

## 📖 مستندات جامع
برای توضیحات تکمیلی مراحل استقرار، عیب‌یابی و نکات ویژه محیط سرور، فایل **[`docs/WINDOWS_DEPLOYMENT_GUIDE.md`](docs/WINDOWS_DEPLOYMENT_GUIDE.md)** را مطالعه نمایید.
