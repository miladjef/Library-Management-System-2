# راهنمای نصب و راه‌اندازی سیستم مدیریت کتابخانه

## پیش‌نیازها

### الزامات سرور
- **PHP**: نسخه 7.4 یا بالاتر (توصیه: PHP 8.0+)
- **MySQL/MariaDB**: نسخه 5.7 یا بالاتر
- **Apache/Nginx**: با پشتیبانی mod_rewrite
- **Extensions مورد نیاز PHP**:
  - PDO و PDO_MySQL
  - mbstring
  - GD یا Imagick
  - zip
  - curl
  - json

### بررسی نسخه PHP
```bash
php -v

### بررسی Extensions
bash
php -m

---

## مراحل نصب

### 1. دانلود و استخراج فایل‌ها

bash
# دانلود از GitHub
git clone https://github.com/yourusername/library-management.git

# یا استخراج فایل ZIP
unzip library-management.zip

### 2. انتقال به سرور

فایل‌ها را در دایرکتوری مناسب قرار دهید:
- **لوکال هاست**: `C:/xampp/htdocs/library/` یا `/var/www/html/library/`
- **هاست اشتراکی**: `public_html/` یا `www/`

### 3. ایجاد دیتابیس

#### روش اول: از phpMyAdmin

1. وارد phpMyAdmin شوید
2. یک دیتابیس جدید با نام `library_db` ایجاد کنید
3. انتخاب کنید: **Collation: `utf8mb4_persian_ci`**
4. از منوی Import، فایل `Database/library_complete.sql` را آپلود کنید

#### روش دوم: از Command Line

bash
# ایجاد دیتابیس
mysql -u root -p -e "CREATE DATABASE library_db CHARACTER SET utf8mb4 COLLATE utf8mb4_persian_ci;"

# وارد کردن اسکریپت SQL
mysql -u root -p library_db < Database/library_complete.sql

### 4. پیکربندی فایل config.php

فایل `config.php` را باز کنید و موارد زیر را ویرایش کنید:

php
// تنظیمات دیتابیس
define('DB_HOST', 'localhost');
define('DB_USER', 'root');           // نام کاربری MySQL
define('DB_PASS', '');               // رمز عبور MySQL
define('DB_NAME', 'library_db');     // نام دیتابیس

// آدرس سایت
define('SITE_URL', 'http://localhost/library');

// در محیط تولید:
// define('DEBUG_MODE', false);

### 5. تنظیم دسترسی‌ها

#### لینوکس/macOS:
bash
# دسترسی نوشتن به پوشه‌ها
chmod -R 755 uploads/
chmod -R 755 logs/
chmod -R 755 cache/
chmod -R 755 backups/

# مالک فایل‌ها
chown -R www-data:www-data /path/to/library/

#### Windows:
- کلیک راست روی پوشه‌های `uploads`, `logs`, `cache`, `backups`
- Properties → Security → Edit
- دسترسی Full Control به IIS_IUSRS بدهید

### 6. پیکربندی Apache Virtual Host (اختیاری)

برای دامنه محلی سفارشی:

apache
<VirtualHost *:80>
ServerName library.local
DocumentRoot "C:/xampp/htdocs/library"

<Directory "C:/xampp/htdocs/library">
Options -Indexes +FollowSymLinks
AllowOverride All
Require all granted
</Directory>

ErrorLog "logs/library-error.log"
CustomLog "logs/library-access.log" common
</VirtualHost>

سپس در فایل `hosts`:

127.0.0.1    library.local

### 7. نصب Composer Dependencies (در صورت استفاده)

bash
cd /path/to/library
composer install --no-dev --optimize-autoloader

### 8. تست نصب

1. در مرورگر وارد شوید: `http://localhost/library/`
2. صفحه اصلی باید بدون خطا نمایش داده شود
3. برای ورود به پنل ادمین: `http://localhost/library/admin/`

**اطلاعات ورود پیش‌فرض:**
- نام کاربری: `admin`
- رمز عبور: `admin123`

⚠️ **هشدار امنیتی**: بلافاصله پس از ورود، رمز عبور را تغییر دهید!

---

## عیب‌یابی مشکلات رایج

### خطای 500 - Internal Server Error

**علت**: مشکل در .htaccess یا تنظیمات PHP

**راه‌حل**:
1. بررسی فایل `logs/error.log`
2. غیرفعال موقت .htaccess (تغییر نام به .htaccess.bak)
3. بررسی نسخه PHP (حداقل 7.4)

### خطای اتصال به دیتابیس

**علت**: اطلاعات نادرست در config.php

**راه‌حل**:
bash
# تست اتصال دیتابیس
mysql -u root -p
USE library_db;
SHOW TABLES;

### خطای 404 برای صفحات

**علت**: mod_rewrite فعال نیست

**راه‌حل Apache**:
bash
# فعال‌سازی mod_rewrite
sudo a2enmod rewrite
sudo systemctl restart apache2

### مشکل آپلود فایل

**علت**: محدودیت حجم یا عدم دسترسی

**راه‌حل**:
1. بررسی `php.ini`:
```ini
   upload_max_filesize = 10M
   post_max_size = 10M
