// admin/assets/js/members.js
document.addEventListener('DOMContentLoaded', function() {

    // جستجوی زنده
    const searchInput = document.getElementById('search-member');
    if (searchInput) {
        let searchTimeout;
        searchInput.addEventListener('input', function() {
            clearTimeout(searchTimeout);
            searchTimeout = setTimeout(() => {
                document.getElementById('filter-form').submit();
            }, 500);
        });
    }

    // فیلترهای خودکار
    const statusFilter = document.getElementById('status-filter');
    const penaltyFilter = document.getElementById('penalty-filter');

    if (statusFilter) {
        statusFilter.addEventListener('change', function() {
            document.getElementById('filter-form').submit();
        });
    }

    if (penaltyFilter) {
        penaltyFilter.addEventListener('change', function() {
            document.getElementById('filter-form').submit();
        });
    }
});

// تایید غیرفعال کردن عضو
function confirmDeactivate(memberId, memberName) {
    if (confirm(`آیا از غیرفعال کردن عضو "${memberName}" اطمینان دارید؟\n\nتوجه: امانت‌های فعال این عضو باید قبلاً بسته شده باشند.`)) {
        window.location.href = `?action=delete&id=${memberId}`;
    }
}

// تایید فعال کردن عضو
function confirmActivate(memberId, memberName) {
    if (confirm(`آیا از فعال کردن مجدد عضو "${memberName}" اطمینان دارید؟`)) {
        window.location.href = `?action=activate&id=${memberId}`;
    }
}
