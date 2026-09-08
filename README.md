# SendMessageBots - سامانه یکپارچه ارسال پیام و نوتیفیکیشن

سامانه ارسال خودکار پیام و فایل به کاربران از طریق پیام‌رسان‌های **تلگرام (Telegram)** و **بله (Bale)** همراه با پنل مدیریت، سیستم ثبت شماره تماس مخاطبین، و API اختصاصی برای برنامه‌های فرستنده.

---

## ⚡ راه‌اندازی و استقرار با XAMPP روی سرور ویندوز (روش اصلی)

با توجه به نصب بودن **XAMPP**، پروژه می‌تواند مستقیماً توسط آپاچی و دیتابیس XAMPP میزبانی شود:

### ۱. تنظیم فایل `.env`
```ini
DB_HOST=127.0.0.1
DB_PORT=3306
DB_NAME=notifier
DB_USER=root
DB_PASS=
TELEGRAM_BOT_TOKEN=your_telegram_bot_token
BALE_BOT_TOKEN=your_bale_bot_token
WEBHOOK_SECRET=your_secret_key
```

### ۲. ساخت دیتابیس
- در پنل XAMPP سرویس **MySQL** را استارت کنید.
- روی **`setup_database.bat`** دوبار کلیک کنید تا دیتابیس و جداول خودکار ساخته شوند.

### ۳. میزبانی در Apache (XAMPP VirtualHost)
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
*(مطمئن شوید در `httpd.conf` ماژول `mod_rewrite` و فایل `httpd-vhosts.conf` فعال هستند).*

### ۴. فعال‌سازی سرویس ۲۴/۷ در ویندوز
در **کنترل پنل XAMPP** (در حالت Run as administrator)، روی تیک قرمز **`[X]`** کنار Apache و MySQL کلیک کنید تا به **سرویس رسمی ویندوز (Windows Service)** تبدیل شوند و با هر بار ری‌استارت سرور، خودکار بالا بیایند.

### ۵. ثبت وبهوک‌های تلگرام و بله
- فایل **`set_webhooks.bat`** را اجرا کنید و آدرس دامنه یا تونل HTTPS خود را وارد نمایید.

---

## 🚀 روش جایگزین: اجرای به عنوان سرویس مستقل PHP (بدون نیاز به تنظیم آپاچی)

اگر نمی‌خواهید تنظیمات آپاچی را تغییر دهید:
1. روی **`service_install.bat`** راست‌کلیک کرده و **Run as administrator** را بزنید.
2. گزینه ۲ (Task Scheduler) یا گزینه ۱ (NSSM) را انتخاب کنید تا پروژه روی پورت اختصاصی (تنظیم شده در `APP_PORT` در `.env`) در پس‌زمینه اجرا شود.
3. مدیریت سرویس و مشاهده لاگ‌ها با **`service_control.bat`** انجام می‌شود.

---

## 📖 راهنمای کامل
برای مطالعه راهنمای گام‌به‌گام و جزئیات تکمیلی، فایل **[`WINDOWS_DEPLOYMENT_GUIDE.md`](file:///e:/php%20projects/SendMessageBots/WINDOWS_DEPLOYMENT_GUIDE.md)** را مشاهده کنید.
