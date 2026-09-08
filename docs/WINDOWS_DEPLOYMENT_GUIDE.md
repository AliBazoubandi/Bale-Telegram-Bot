# راهنمای جامع استقرار و اجرای سامانه با XAMPP و ویندوز

این راهنما مراحل کامل راه‌اندازی، اتصال به دیتابیس، تبدیل Apache و MySQL به **سرویس‌های خودکار ویندوز (۲۴/۷)** و ثبت وبهوک‌ها با **XAMPP** را توضیح می‌دهد.

---

## ۱. پیش‌نیازها و تنظیم فایل `.env`

در فایل `.env` اطلاعات اتصال دیتابیس XAMPP و توکن‌ها را تنظیم کنید:

```ini
# --- MySQL در XAMPP ---
DB_HOST=127.0.0.1
DB_PORT=3306
DB_NAME=notifier
DB_USER=root
DB_PASS=

# --- پورت وب‌سرور داخلی (در صورت عدم استفاده از آپاچی) ---
APP_PORT=8080

# --- توکن‌های ربات‌ها (از BotFather در تلگرام و بله) ---
TELEGRAM_BOT_TOKEN=your_telegram_bot_token
BALE_BOT_TOKEN=your_bale_bot_token

# --- امنیت وبهوک‌ها ---
WEBHOOK_SECRET=your_secret_key_here
```

---

## ۲. بررسی سلامت سیستم و ایجاد دیتابیس

1. **بررسی پیش‌نیازها (`scripts/check_env.bat`):**
   - روی `scripts/check_env.bat` (یا دستور `php bin/check_requirements.php`) دوبار کلیک کنید تا اکستنشن‌های PHP و ارتباط با دیتابیس بررسی شود.
2. **ساخت خودکار دیتابیس و جداول (`scripts/setup_database.bat`):**
   - در کنترل پنل XAMPP سرویس **MySQL** را Start کنید.
   - روی `scripts/setup_database.bat` (یا دستور `php bin/setup_db.php`) دوبار کلیک کنید تا دیتابیس `notifier` و تمامی جداول ساخته شوند.
   - سپس با اجرای دستور زیر حساب مدیر سامانه را بسازید:
     ```bash
     php bin/create_admin.php <username> <password> "نام مدیر"
     ```

---

## ۳. روش اصلی: میزبانی مستقیم با وب‌سرور Apache در XAMPP

در این روش، وب‌سرور قدرتمند **Apache در XAMPP** مستقیماً سایت را میزبانی می‌کند و امنیت آن توسط فایل [`.htaccess`](file:///e:/php%20projects/SendMessageBots/.htaccess) تضمین می‌شود.

### گام اول: فعال‌سازی VirtualHost در XAMPP
فایل `C:\xampp\apache\conf\extra\httpd-vhosts.conf` را باز کرده و در انتهای آن کد زیر را اضافه کنید:

```apache
<VirtualHost *:80>
    DocumentRoot "E:/php projects/SendMessageBots"
    ServerName localhost
    # اگر دامنه دارید نام دامنه را بنویسید، مثلا: ServerName bot.mydomain.com

    <Directory "E:/php projects/SendMessageBots">
        Options Indexes FollowSymLinks
        AllowOverride All
        Require all granted
    </Directory>
</VirtualHost>
```

> [!IMPORTANT]
> در فایل `C:\xampp\apache\conf\httpd.conf` مطمئن شوید دو خط زیر فعال (بدون `#`) هستند:
> 1. `LoadModule rewrite_module modules/mod_rewrite.so` (برای فعال بودن فایروال .htaccess)
> 2. `Include conf/extra/httpd-vhosts.conf`

### گام دوم: تبدیل Apache و MySQL به سرویس خودکار ویندوز (۲۴/۷)
برای اینکه با هر بار ری‌استارت شدن سرور ویندوز، آپاچی و دیتابیس خودکار بالا بیایند:
1. **کنترل پنل XAMPP** را باز کنید (Run as administrator).
2. آپاچی و MySQL را Stop کنید.
3. روی علامت ضربدر قرمز **`[X]`** کنار **Apache** و **MySQL** کلیک کنید تا تبدیل به تیک سبز شوند (نصب به عنوان Windows Service).
4. هر دو را Start کنید.
حالا آپاچی و دیتابیس حتی بدون لاگین کاربر همیشه در پس‌زمینه فعال هستند.

---

## ۴. روش جایگزین: اجرای به عنوان سرویس مستقل PHP (بدون آپاچی)

اگر ترجیح می‌دهید آپاچی دست‌نخورده بماند و پروژه روی پورت اختصاصی خودش (مثلاً `8080` یا پورت تنظیم شده در `APP_PORT`) کار کند:

1. روی **`scripts/service_install.bat`** راست‌کلیک کرده و **Run as administrator** را بزنید.
2. گزینه **[2] (Windows Task Scheduler)** یا گزینه **[1] (NSSM)** را انتخاب کنید.
3. برنامه با روتر امنیتی اختصاصی [`router.php`](file:///e:/php%20projects/SendMessageBots/router.php) به عنوان سرویس ۲۴ ساعته فعال می‌شود.
4. مدیریت، راه‌اندازی مجدد و مشاهده لاگ‌ها با **`scripts/service_control.bat`** انجام می‌شود.

---

## ۵. اتصال دامنه و ثبت وبهوک‌ها (Webhooks)

### الف) اتصال دامنه اینترنتی:
- **با استفاده از Cloudflare (پیشنهادی):**
  رکورد `A` دامنه خود (مثلاً `bot.mydomain.com`) را به IP سرور خود متصل کنید و SSL را فعال نمایید.
- **یا با استفاده از تونل ngrok / Cloudflare:**
  ```cmd
  cloudflared tunnel --url http://localhost:80
  ```

### ب) ثبت خودکار وبهوک‌ها با `scripts/set_webhooks.bat`:
1. فایل **`scripts/set_webhooks.bat`** (یا دستور `php bin/register_webhooks.php "https://yourdomain.com"`) را اجرا کنید.
2. آدرس دامنه یا تونل HTTPS خود را وارد کنید (مثال: `https://bot.mydomain.com` یا `https://xxx.ngrok-free.dev`).
3. اسکریپت به صورت خودکار:
   - وبهوک تلگرام را با توکن و Secret Token ثبت می‌کند.
   - وبهوک بله را با پارامتر secret ثبت می‌کند.
   - منوی دستورات ربات را برای هر دو پلتفرم آپدیت می‌کند.

---

## ۶. دستورات تکمیلی و مدیریت

- **ورود به پنل وب:**
  `http://localhost/admin/` (اگر از آپاچی پورت ۸۰ استفاده می‌کنید) یا `http://localhost:8080/admin/`
  کاربر: `admin` | رمز عبور: تنظیم شده هنگام ایجاد حساب کاربری مدیر

- **ساخت مدیر جدید در خط فرمان:**
  ```cmd
  php bin/create_admin.php <username> <password> "نام مدیر"
  ```

- **ثبت برنامه ارسال‌کننده جدید (تولید app_key):**
  ```cmd
  php bin/register_app.php "نام سیستم فرستنده"
  ```

- **تست ارسال پیام:**
  ```cmd
  php tools/example_send.php
  ```
