// مدیریت فرم ویرایش کتاب

document.addEventListener('DOMContentLoaded', function() {

    const imageInput = document.getElementById('book_image');
    const imagePreview = document.getElementById('imagePreview');
    const removeImageCheckbox = document.querySelector('input[name="remove_image"]');

    // پیش‌نمایش تصویر جدید
    if (imageInput) {
        imageInput.addEventListener('change', function(e) {
            const file = e.target.files[0];
            if (file) {
                // بررسی حجم (5MB)
                if (file.size > 5 * 1024 * 1024) {
                    alert('حجم تصویر نباید بیشتر از 5 مگابایت باشد');
                    imageInput.value = '';
                    return;
                }

                // بررسی فرمت
                if (!['image/jpeg', 'image/jpg', 'image/png'].includes(file.type)) {
                    alert('فقط فرمت‌های JPG و PNG مجاز است');
                    imageInput.value = '';
                    return;
                }

                // نمایش پیش‌نمایش
                const reader = new FileReader();
                reader.onload = function(e) {
                    imagePreview.innerHTML = `
                        <div style="margin-top: 10px;">
                            <p><strong>پیش‌نمایش تصویر جدید:</strong></p>
                            <img src="${e.target.result}" alt="پیش‌نمایش" style="max-width: 200px; border-radius: 5px;">
                            <button type="button" onclick="clearNewImage()" class="btn-small">انصراف</button>
                        </div>
                    `;
                };
                reader.readAsDataURL(file);

                // غیرفعال کردن چک‌باکس حذف تصویر
                if (removeImageCheckbox) {
                    removeImageCheckbox.checked = false;
                    removeImageCheckbox.disabled = true;
                }
            }
        });
    }

    // مدیریت چک‌باکس حذف تصویر
    if (removeImageCheckbox) {
        removeImageCheckbox.addEventListener('change', function() {
            if (this.checked) {
                if (!confirm('آیا مطمئن هستید که می‌خواهید تصویر فعلی را حذف کنید؟')) {
                    this.checked = false;
                }
            }
        });
    }

    // اعتبارسنجی فرم
    const form = document.getElementById('editBookForm');
    if (form) {
        form.addEventListener('submit', function(e) {
            const isbn = document.getElementById('isbn').value.trim();

            if (!/^\d{10}$|^\d{13}$/.test(isbn)) {
                e.preventDefault();
                alert('فرمت ISBN نامعتبر است');
                return false;
            }
        });
    }
});

// حذف پیش‌نمایش تصویر جدید
function clearNewImage() {
    document.getElementById('book_image').value = '';
    document.getElementById('imagePreview').innerHTML = '';

    const removeImageCheckbox = document.querySelector('input[name="remove_image"]');
    if (removeImageCheckbox) {
        removeImageCheckbox.disabled = false;
    }
}

// بستن هشدارها
function closeAlert(id) {
    document.getElementById(id).style.display = 'none';
}
