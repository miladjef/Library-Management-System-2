<?php
// inc/header.php
require_once 'classes/Database.php';
require_once 'classes/Member.php';
require_once 'inc/functions.php';

// بررسی لاگین کاربر
$user_logged_in = false;
$current_user = null;

if (isset($_SESSION['userid'])) {
    $db = Database::getInstance();
    $member = new Member($db);
    $current_user = $member->getById($_SESSION['userid']);
    $user_logged_in = true;
}
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo  isset($title) ? $title . ' - ' : '' ?>کتابخانه مجازی</title>

    <!-- Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Vazir:wght@300;400;500;600;700&display=swap" rel="stylesheet">

    <!-- CSS -->
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="assets/css/user.css">

    <!-- Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">

    <!-- jQuery -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar">
        <div class="nav-container">
            <!-- Logo -->
            <div class="nav-logo">
                <a href="<?php echo  siteurl() ?>">
                    <i class="fas fa-book"></i>
                    <span>کتابخانه مجازی</span>
                </a>
            </div>

            <!-- Menu Items -->
            <div class="nav-menu" id="nav-menu">
                <a href="<?php echo  siteurl() ?>" class="nav-link">
                    <i class="fas fa-home"></i>
                    خانه
                </a>
                <a href="<?php echo  siteurl() ?>/books.php" class="nav-link">
                    <i class="fas fa-book-open"></i>
                    کتاب‌ها
                </a>
                <a href="<?php echo  siteurl() ?>/categories.php" class="nav-link">
                    <i class="fas fa-list"></i>
                    دسته‌بندی‌ها
                </a>

                <?php if ($user_logged_in): ?>
                    <a href="<?php echo  siteurl() ?>/profile.php" class="nav-link">
                        <i class="fas fa-user"></i>
                        پروفایل من
                    </a>
                    <a href="<?php echo  siteurl() ?>/my-reservations.php" class="nav-link">
                        <i class="fas fa-history"></i>
                        امانت‌های من
                    </a>
                    <a href="<?php echo  siteurl() ?>/tickets.php" class="nav-link">
                        <i class="fas fa-support"></i>
                        پشتیبانی
                    </a>
                <?php endif; ?>
            </div>

            <!-- User Actions -->
            <div class="nav-actions">
                <?php if ($user_logged_in): ?>
                    <div class="user-dropdown">
                        <button class="user-btn" onclick="toggleUserMenu()">
                            <i class="fas fa-user-circle"></i>
                            <span><?php echo  $current_user['name'] . ' ' . $current_user['surname'] ?></span>
                            <i class="fas fa-chevron-down"></i>
                        </button>
                        <div class="user-menu" id="user-menu">
                            <a href="profile.php" class="user-menu-item">
                                <i class="fas fa-user"></i>
                                پروفایل
                            </a>
                            <a href="my-reservations.php" class="user-menu-item">
                                <i class="fas fa-book"></i>
                                امانت‌های من
                            </a>
                            <a href="tickets.php" class="user-menu-item">
                                <i class="fas fa-support"></i>
                                تیکت‌ها
                            </a>
                            <div class="user-menu-divider"></div>
                            <a href="logout.php" class="user-menu-item text-danger">
                                <i class="fas fa-sign-out-alt"></i>
                                خروج
                            </a>
                        </div>
                    </div>
                <?php else: ?>
                    <a href="login.php" class="btn btn-outline">
                        <i class="fas fa-sign-in-alt"></i>
                        ورود
                    </a>
                    <a href="register.php" class="btn btn-primary">
                        <i class="fas fa-user-plus"></i>
                        ثبت نام
                    </a>
                <?php endif; ?>
            </div>

            <!-- Mobile Menu Toggle -->
            <div class="nav-toggle" onclick="toggleMobileMenu()">
                <span class="bar"></span>
                <span class="bar"></span>
                <span class="bar"></span>
            </div>
        </div>
    </nav>

    <!-- Main Content -->
    <main class="main-content">
