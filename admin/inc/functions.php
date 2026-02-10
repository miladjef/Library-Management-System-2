<?php
include "jdf.php";

// JUST EDIT THIS PART
$host = 'localhost';
$user = 'root';
$pass = '';
$db = 'lib';
$site_URL = "http://localhost/lib";
// JUST EDIT THIS PART

// اتصال با PDO به جای mysqli
try {
    $pdo = new PDO("mysql:host=$host;dbname=$db;charset=utf8mb4", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    die("خطا در اتصال به دیتابیس: " . $e->getMessage());
}

// نگهداری mysqli برای سازگاری با کدهای قدیمی
$conn = mysqli_connect($host, $user, $pass, $db);
if (!$conn) {
    die("خطا در اتصال: " . mysqli_connect_error());
}
mysqli_set_charset($conn, 'utf8mb4');

session_start();

const IMG_PATH = "../assets/img/books/";

function siteurl() {
    global $site_URL;
    return $site_URL;
}

/**
 * تابع امنیت‌سازی خروجی HTML
 */
function e($string) {
    return htmlspecialchars($string ?? '', ENT_QUOTES, 'UTF-8');
}

/**
 * تابع امنیت‌سازی ورودی
 */
function sanitize_input($data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data);
    return $data;
}

/**
 * بررسی ورود کاربر
 */
function is_logged_in() {
    return isset($_SESSION['userid']) && !empty($_SESSION['userid']);
}

/**
 * بررسی نقش ادمین
 */
function is_admin($userid = null) {
    global $pdo;

    if ($userid === null && isset($_SESSION['userid'])) {
        $userid = $_SESSION['userid'];
    }

    if (!$userid) {
        return false;
    }

    $stmt = $pdo->prepare("SELECT role FROM members WHERE mid = ?");
    $stmt->execute([$userid]);
    $user = $stmt->fetch();

    return $user && $user['role'] == 2;
}

/**
 * خروج از سیستم
 */
function logout() {
    session_unset();
    session_destroy();
    header("Location: " . siteurl());
    exit;
}

/**
 * دریافت لیست کتاب‌ها
 */
function get_books() {
    global $pdo, $books;
    $stmt = $pdo->query("SELECT * FROM books ORDER BY date DESC");
    $books = $stmt->fetchAll();
    return $books;
}

/**
 * دریافت کتاب‌های موجود
 */
function get_available_books() {
    global $pdo, $books;
    $stmt = $pdo->query("SELECT * FROM books WHERE count > 0 ORDER BY date DESC");
    $books = $stmt->fetchAll();
    return $books;
}

/**
 * دریافت دسته‌بندی‌ها
 */
function get_categories() {
    global $pdo, $cats;
    $stmt = $pdo->query("SELECT * FROM categories ORDER BY date DESC");
    $cats = $stmt->fetchAll();
    return $cats;
}

/**
 * دریافت نام دسته‌بندی
 */
function get_category_name($cat_id) {
    global $pdo;
    $stmt = $pdo->prepare("SELECT cat_name FROM categories WHERE cat_id = ?");
    $stmt->execute([$cat_id]);
    $category = $stmt->fetch();
    return $category ? $category['cat_name'] : false;
}

/**
 * افزودن کتاب با پشتیبانی از ISBN و API
 */
function add_book() {
    global $pdo;

    if (!isset($_POST['add_book'])) {
        return false;
    }

    try {
        // آپلود تصویر
        $book_image = 'default.jpg';
        if (isset($_FILES['book_img']) && $_FILES['book_img']['error'] == 0) {
            $target_dir = "../assets/img/books/";
            $rand = rand(1, 99999) . '-';
            $file_ext = strtolower(pathinfo($_FILES["book_img"]["name"], PATHINFO_EXTENSION));
            $allowed_ext = ['jpg', 'jpeg', 'png', 'gif'];

            if (in_array($file_ext, $allowed_ext)) {
                $new_filename = $rand . basename($_FILES["book_img"]["name"]);
                $target_file = $target_dir . $new_filename;

                if (move_uploaded_file($_FILES["book_img"]["tmp_name"], $target_file)) {
                    $book_image = $new_filename;
                }
            }
        }

        // دریافت داده‌ها
        $book_name = sanitize_input($_POST['book_name']);
        $author = sanitize_input($_POST['author']);
        $publisher = sanitize_input($_POST['publisher'] ?? '');
        $publish_year = intval($_POST['publish_year'] ?? 0);
        $category = intval($_POST['category']);
        $count = intval($_POST['count']);
        $description = sanitize_input($_POST['description'] ?? '');
        $isbn = sanitize_input($_POST['isbn'] ?? '');
        $pages = intval($_POST['pages'] ?? 0);
        $language = sanitize_input($_POST['language'] ?? 'فارسی');

        // اعتبارسنجی
        if (empty($book_name) || empty($author) || $count < 1) {
            throw new Exception("فیلدهای ضروری خالی است");
        }

        // درج در دیتابیس
        $sql = "INSERT INTO books (
            book_name, author, publisher, publish_year,
            category_id, count, description, book_img,
            isbn, pages, language, date
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())";

        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            $book_name, $author, $publisher, $publish_year,
            $category, $count, $description, $book_image,
            $isbn, $pages, $language
        ]);

        return true;

    } catch (Exception $e) {
        error_log("Error adding book: " . $e->getMessage());
        return false;
    }
}

/**
 * دریافت اطلاعات کتاب
 */
function get_book_data($book_id) {
    global $pdo;
    $stmt = $pdo->prepare("SELECT * FROM books WHERE bid = ?");
    $stmt->execute([$book_id]);
    return $stmt->fetch();
}

/**
 * ویرایش کتاب
 */
function edit_book() {
    global $pdo;

    if (!isset($_POST['edit_book'])) {
        return false;
    }

    try {
        $book_id = intval($_POST['bid']);
        $book_image = $_POST['current_image'] ?? 'default.jpg';

        // آپلود تصویر جدید
        if (isset($_FILES['book_img']) && $_FILES['book_img']['error'] == 0) {
            $target_dir = "../assets/img/books/";
            $rand = rand(1, 99999) . '-';
            $file_ext = strtolower(pathinfo($_FILES["book_img"]["name"], PATHINFO_EXTENSION));
            $allowed_ext = ['jpg', 'jpeg', 'png', 'gif'];

            if (in_array($file_ext, $allowed_ext)) {
                $new_filename = $rand . basename($_FILES["book_img"]["name"]);
                $target_file = $target_dir . $new_filename;

                if (move_uploaded_file($_FILES["book_img"]["tmp_name"], $target_file)) {
                    // حذف تصویر قدیمی
                    if ($book_image != 'default.jpg' && file_exists($target_dir . $book_image)) {
                        unlink($target_dir . $book_image);
                    }
                    $book_image = $new_filename;
                }
            }
        }

        // دریافت داده‌ها
        $book_name = sanitize_input($_POST['book_name']);
        $author = sanitize_input($_POST['author']);
        $publisher = sanitize_input($_POST['publisher'] ?? '');
        $publish_year = intval($_POST['publish_year'] ?? 0);
        $category = intval($_POST['category']);
        $count = intval($_POST['count']);
        $description = sanitize_input($_POST['description'] ?? '');
        $isbn = sanitize_input($_POST['isbn'] ?? '');
        $pages = intval($_POST['pages'] ?? 0);
        $language = sanitize_input($_POST['language'] ?? 'فارسی');

        // به‌روزرسانی
        $sql = "UPDATE books SET
            book_name = ?, author = ?, publisher = ?, publish_year = ?,
            category_id = ?, count = ?, description = ?, book_img = ?,
            isbn = ?, pages = ?, language = ?
            WHERE bid = ?";

        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            $book_name, $author, $publisher, $publish_year,
            $category, $count, $description, $book_image,
            $isbn, $pages, $language, $book_id
        ]);

        return true;

    } catch (Exception $e) {
        error_log("Error editing book: " . $e->getMessage());
        return false;
    }
}

/**
 * حذف کتاب
 */
function delete_book($book_id) {
    global $pdo;

    try {
        // دریافت اطلاعات کتاب برای حذف تصویر
        $book = get_book_data($book_id);

        // حذف از دیتابیس
        $stmt = $pdo->prepare("DELETE FROM books WHERE bid = ?");
        $stmt->execute([$book_id]);

        // حذف تصویر
        if ($book && $book['book_img'] != 'default.jpg') {
            $image_path = "../assets/img/books/" . $book['book_img'];
            if (file_exists($image_path)) {
                unlink($image_path);
            }
        }

        return true;

    } catch (Exception $e) {
        error_log("Error deleting book: " . $e->getMessage());
        return false;
    }
}

/**
 * افزودن دسته‌بندی
 */
function add_category() {
    global $pdo;

    if (!isset($_POST['add_category'])) {
        return false;
    }

    try {
        $cat_name = sanitize_input($_POST['cat_name']);

        $stmt = $pdo->prepare("INSERT INTO categories (cat_name, date) VALUES (?, NOW())");
        $stmt->execute([$cat_name]);

        return true;

    } catch (Exception $e) {
        error_log("Error adding category: " . $e->getMessage());
        return false;
    }
}

/**
 * ویرایش دسته‌بندی
 */
function update_category() {
    global $pdo;

    if (!isset($_POST['edit_category'])) {
        return false;
    }

    try {
        $cat_id = intval($_POST['cat_id']);
        $cat_name = sanitize_input($_POST['cat_name']);

        $stmt = $pdo->prepare("UPDATE categories SET cat_name = ? WHERE cat_id = ?");
        $stmt->execute([$cat_name, $cat_id]);

        return true;

    } catch (Exception $e) {
        error_log("Error updating category: " . $e->getMessage());
        return false;
    }
}

/**
 * حذف دسته‌بندی
 */
function delete_category($cat_id) {
    global $pdo;

    try {
        // بررسی وجود کتاب در این دسته
        $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM books WHERE category_id = ?");
        $stmt->execute([$cat_id]);
        $result = $stmt->fetch();

        if ($result['count'] > 0) {
            throw new Exception("این دسته دارای کتاب است و نمی‌توان حذف کرد");
        }

        $stmt = $pdo->prepare("DELETE FROM categories WHERE cat_id = ?");
        $stmt->execute([$cat_id]);

        return true;

    } catch (Exception $e) {
        error_log("Error deleting category: " . $e->getMessage());
        return false;
    }
}

/**
 * دریافت نام کاربر
 */
function get_user_name($user_id) {
    global $pdo;
    $stmt = $pdo->prepare("SELECT name, surname FROM members WHERE mid = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch();

    if ($user) {
        return $user['name'] . " " . $user['surname'];
    }
    return "نامشخص";
}

/**
 * تابع کمکی برای محاسبه زمان گذشته
 */
function time_elapsed_string($datetime, $full = false) {
    $now = new DateTime;
    $ago = new DateTime($datetime);
    $diff = $now->diff($ago);

    $diff->w = floor($diff->d / 7);
    $diff->d -= $diff->w * 7;

    $string = array(
        'y' => 'سال',
        'm' => 'ماه',
        'w' => 'هفته',
        'd' => 'روز',
        'h' => 'ساعت',
        'i' => 'دقیقه',
        's' => 'ثانیه',
    );

    foreach ($string as $k => &$v) {
        if ($diff->$k) {
            $v = $diff->$k . ' ' . $v;
        } else {
            unset($string[$k]);
        }
    }

    if (!$full) {
        $string = array_slice($string, 0, 1);
    }

    return $string ? implode(', ', $string) . ' پیش' : 'هم اکنون';
}

// باقی توابع قدیمی را نگه می‌داریم برای سازگاری
// (سایر توابع موجود...)
