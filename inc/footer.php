<?php
// inc/footer.php
?>
    </main>

    <!-- Footer -->
    <footer class="site-footer">
        <div class="footer-container">
            <!-- بخش اول: درباره -->
            <div class="footer-section">
                <h3>
                    <i class="fas fa-book"></i>
                    کتابخانه مجازی
                </h3>
                <p class="footer-description">
                    سیستم جامع مدیریت کتابخانه با امکانات پیشرفته امانت، رزرو و مدیریت کتاب‌ها
                </p>
                <div class="footer-social">
                    <a href="#" class="social-link" title="تلگرام">
                        <i class="fab fa-telegram"></i>
                    </a>
                    <a href="#" class="social-link" title="اینستاگرام">
                        <i class="fab fa-instagram"></i>
                    </a>
                    <a href="#" class="social-link" title="توییتر">
                        <i class="fab fa-twitter"></i>
                    </a>
                    <a href="#" class="social-link" title="لینکدین">
                        <i class="fab fa-linkedin"></i>
                    </a>
                </div>
            </div>

            <!-- بخش دوم: دسترسی سریع -->
            <div class="footer-section">
                <h4>دسترسی سریع</h4>
                <ul class="footer-links">
                    <li><a href="<?= siteurl() ?>"><i class="fas fa-home"></i> خانه</a></li>
                    <li><a href="<?= siteurl() ?>/books.php"><i class="fas fa-book"></i> کتاب‌ها</a></li>
                    <li><a href="<?= siteurl() ?>/categories.php"><i class="fas fa-list"></i> دسته‌بندی‌ها</a></li>
                    <li><a href="<?= siteurl() ?>/about.php"><i class="fas fa-info-circle"></i> درباره ما</a></li>
                    <li><a href="<?= siteurl() ?>/contact.php"><i class="fas fa-envelope"></i> تماس با ما</a></li>
                </ul>
            </div>

            <!-- بخش سوم: خدمات -->
            <div class="footer-section">
                <h4>خدمات</h4>
                <ul class="footer-links">
                    <?php if ($user_logged_in): ?>
                        <li><a href="<?= siteurl() ?>/profile.php"><i class="fas fa-user"></i> پروفایل من</a></li>
                        <li><a href="<?= siteurl() ?>/my-reservations.php"><i class="fas fa-history"></i> امانت‌های من</a></li>
                        <li><a href="<?= siteurl() ?>/tickets.php"><i class="fas fa-support"></i> پشتیبانی</a></li>
                    <?php else: ?>
                        <li><a href="<?= siteurl() ?>/login.php"><i class="fas fa-sign-in-alt"></i> ورود</a></li>
                        <li><a href="<?= siteurl() ?>/register.php"><i class="fas fa-user-plus"></i> ثبت نام</a></li>
                    <?php endif; ?>
                    <li><a href="<?= siteurl() ?>/rules.php"><i class="fas fa-gavel"></i> قوانین کتابخانه</a></li>
                    <li><a href="<?= siteurl() ?>/faq.php"><i class="fas fa-question-circle"></i> سوالات متداول</a></li>
                </ul>
            </div>

            <!-- بخش چهارم: تماس -->
            <div class="footer-section">
                <h4>تماس با ما</h4>
                <ul class="footer-contact">
                    <li>
                        <i class="fas fa-map-marker-alt"></i>
                        <span>تهران، خیابان ولیعصر، پلاک 123</span>
                    </li>
                    <li>
                        <i class="fas fa-phone"></i>
                        <span dir="ltr">021-12345678</span>
                    </li>
                    <li>
                        <i class="fas fa-envelope"></i>
                        <span>info@library.com</span>
                    </li>
                    <li>
                        <i class="fas fa-clock"></i>
                        <span>شنبه تا پنجشنبه: 8 صبح تا 8 شب</span>
                    </li>
                </ul>
            </div>
        </div>

        <!-- کپی رایت -->
        <div class="footer-bottom">
            <div class="footer-container">
                <div class="copyright">
                    <p>
                        © <?= jdate('Y') ?> کتابخانه مجازی. تمامی حقوق محفوظ است.
                    </p>
                </div>
                <div class="footer-links-bottom">
                    <a href="<?= siteurl() ?>/privacy.php">حریم خصوصی</a>
                    <span class="separator">|</span>
                    <a href="<?= siteurl() ?>/terms.php">شرایط استفاده</a>
                    <span class="separator">|</span>
                    <a href="<?= siteurl() ?>/sitemap.php">نقشه سایت</a>
                </div>
            </div>
        </div>
    </footer>

    <!-- دکمه بازگشت به بالا -->
    <button id="backToTop" class="back-to-top" title="بازگشت به بالا">
        <i class="fas fa-arrow-up"></i>
    </button>

    <!-- اسکریپت‌های عمومی -->
    <script>
    // Toggle Mobile Menu
    function toggleMobileMenu() {
        const navMenu = document.getElementById('nav-menu');
        navMenu.classList.toggle('active');
    }

    // Toggle User Menu
    function toggleUserMenu() {
        const userMenu = document.getElementById('user-menu');
        userMenu.classList.toggle('active');
    }

    // بستن منوها با کلیک خارج از آن‌ها
    document.addEventListener('click', function(event) {
        const userDropdown = document.querySelector('.user-dropdown');
        const navToggle = document.querySelector('.nav-toggle');
        const navMenu = document.getElementById('nav-menu');
        
        // بستن user menu
        if (userDropdown && !userDropdown.contains(event.target)) {
            const userMenu = document.getElementById('user-menu');
            if (userMenu) {
                userMenu.classList.remove('active');
            }
        }
        
        // بستن mobile menu
        if (!navToggle.contains(event.target) && !navMenu.contains(event.target)) {
            navMenu.classList.remove('active');
        }
    });

    // دکمه بازگشت به بالا
    const backToTopButton = document.getElementById('backToTop');

    window.addEventListener('scroll', function() {
        if (window.pageYOffset > 300) {
            backToTopButton.classList.add('visible');
        } else {
            backToTopButton.classList.remove('visible');
        }
    });

    backToTopButton.addEventListener('click', function() {
        window.scrollTo({
            top: 0,
            behavior: 'smooth'
        });
    });

    // نوتیفیکیشن‌ها
    function showNotification(message, type = 'success') {
        const notification = document.createElement('div');
        notification.className = `notification notification-${type}`;
        notification.innerHTML = `
            <i class="fas fa-${type === 'success' ? 'check-circle' : 'exclamation-triangle'}"></i>
            <span>${message}</span>
            <button class="notification-close" onclick="this.parentElement.remove()">
                <i class="fas fa-times"></i>
            </button>
        `;
        
        document.body.appendChild(notification);
        
        setTimeout(() => {
            notification.classList.add('show');
        }, 100);
        
        setTimeout(() => {
            notification.classList.remove('show');
            setTimeout(() => {
                notification.remove();
            }, 300);
        }, 5000);
    }

    // AJAX Helper Function
    function ajaxRequest(url, method = 'GET', data = null) {
        return new Promise((resolve, reject) => {
            const xhr = new XMLHttpRequest();
            xhr.open(method, url);
            xhr.setRequestHeader('Content-Type', 'application/json');
            
            xhr.onload = function() {
                if (xhr.status >= 200 && xhr.status < 300) {
                    try {
                        const response = JSON.parse(xhr.responseText);
                        resolve(response);
                    } catch (e) {
                        resolve(xhr.responseText);
                    }
                } else {
                    reject(xhr.statusText);
                }
            };
            
            xhr.onerror = function() {
                reject(xhr.statusText);
            };
            
            if (data) {
                xhr.send(JSON.stringify(data));
            } else {
                xhr.send();
            }
        });
    }

    // Lazy Loading برای تصاویر
    document.addEventListener('DOMContentLoaded', function() {
        const lazyImages = document.querySelectorAll('img[data-src]');
        
        const imageObserver = new IntersectionObserver(function(entries, observer) {
            entries.forEach(function(entry) {
                if (entry.isIntersecting) {
                    const img = entry.target;
                    img.src = img.dataset.src;
                    img.removeAttribute('data-src');
                    imageObserver.unobserve(img);
                }
            });
        });
        
        lazyImages.forEach(function(img) {
            imageObserver.observe(img);
        });
    });
    </script>

    <?php if (isset($_SESSION['success_message'])): ?>
        <script>
        showNotification('<?= addslashes($_SESSION['success_message']) ?>', 'success');
        </script>
        <?php unset($_SESSION['success_message']); ?>
    <?php endif; ?>

    <?php if (isset($_SESSION['error_message'])): ?>
        <script>
        showNotification('<?= addslashes($_SESSION['error_message']) ?>', 'error');
        </script>
        <?php unset($_SESSION['error_message']); ?>
    <?php endif; ?>
</body>
</html>
