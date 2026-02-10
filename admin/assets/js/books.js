// مدیریت صفحه لیست کتاب‌ها

// تأیید حذف کتاب
function confirmDelete(bookId, bookName) {
    if (confirm(`آیا مطمئن هستید که می‌خواهید کتاب "${bookName}" را حذف کنید؟\n\nتوجه: این عملیات غیرقابل بازگشت است.`)) {
        window.location.href = `books.php?delete=1&id=${bookId}`;
    }
    return false;
}

// نمایش تصویر در Modal
function showImageModal(imageSrc) {
    const modal = document.getElementById('imageModal');
    const modalImg = document.getElementById('modalImage');

    modal.style.display = 'block';
    modalImg.src = imageSrc;
}

// بستن Modal تصویر
function closeImageModal() {
    document.getElementById('imageModal').style.display = 'none';
}

// بستن Modal با کلید Escape
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        closeImageModal();
    }
});

// بستن هشدارها
function closeAlert(id) {
    const alert = document.getElementById(id);
    if (alert) {
        alert.style.display = 'none';
    }
}

// خودکار بستن پیام‌های موفقیت بعد از 5 ثانیه
document.addEventListener('DOMContentLoaded', function() {
    const successAlert = document.getElementById('successAlert');
    if (successAlert) {
        setTimeout(() => {
            successAlert.style.display = 'none';
        }, 5000);
    }
});
