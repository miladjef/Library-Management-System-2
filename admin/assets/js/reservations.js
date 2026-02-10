// admin/assets/js/reservations.js
document.addEventListener('DOMContentLoaded', function() {

    // تایید بازگشت کتاب
    window.confirmReturn = function(reservationId, bookTitle) {
        if (confirm(`آیا از بازگشت کتاب "${bookTitle}" اطمینان دارید؟`)) {
            window.location.href = `?action=return&id=${reservationId}`;
        }
    };

    // تایید حذف امانت
    window.confirmDelete = function(reservationId, bookTitle) {
        if (confirm(`آیا از حذف امانت کتاب "${bookTitle}" اطمینان دارید؟\nاین عملیات غیرقابل بازگشت است.`)) {
            window.location.href = `?action=delete&id=${reservationId}`;
        }
    };

    // فیلتر امانت‌ها
    const filterForm = document.getElementById('filter-form');
    if (filterForm) {
        const statusFilter = document.getElementById('status-filter');
        const overdueFilter = document.getElementById('overdue-filter');

        if (statusFilter) {
            statusFilter.addEventListener('change', function() {
                filterForm.submit();
            });
        }

        if (overdueFilter) {
            overdueFilter.addEventListener('change', function() {
                filterForm.submit();
            });
        }
    }

    // جستجوی زنده
    const searchInput = document.getElementById('search-reservation');
    if (searchInput) {
        let searchTimeout;
        searchInput.addEventListener('input', function() {
            clearTimeout(searchTimeout);
            const query = this.value;

            searchTimeout = setTimeout(() => {
                if (query.length >= 2 || query.length === 0) {
                    filterForm.submit();
                }
            }, 500);
        });
    }

    // نمایش جزئیات امانت در مودال
    const detailButtons = document.querySelectorAll('.view-detail-btn');
    detailButtons.forEach(btn => {
        btn.addEventListener('click', function() {
            const reservationId = this.dataset.reservationId;
            showReservationDetail(reservationId);
        });
    });

    function showReservationDetail(reservationId) {
        fetch(`../api/get_reservation_detail.php?id=${reservationId}`)
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    const modal = document.getElementById('detail-modal');
                    const content = document.getElementById('modal-detail-content');

                    let html = '<div class="reservation-detail">';
                    html += `<h3>جزئیات امانت #${data.reservation.id}</h3>`;
                    html += '<div class="detail-section">';
                    html += '<h4>اطلاعات عضو</h4>';
                    html += `<p><strong>نام:</strong> ${data.reservation.member_name}</p>`;
                    html += `<p><strong>کد ملی:</strong> ${data.reservation.national_code}</p>`;
                    html += '</div>';
                    html += '<div class="detail-section">';
                    html += '<h4>اطلاعات کتاب</h4>';
                    html += `<p><strong>عنوان:</strong> ${data.reservation.book_title}</p>`;
                    html += `<p><strong>نویسنده:</strong> ${data.reservation.author}</p>`;
                    html += '</div>';
                    html += '<div class="detail-section">';
                    html += '<h4>اطلاعات امانت</h4>';
                    html += `<p><strong>تاریخ امانت:</strong> ${data.reservation.borrow_date_persian}</p>`;
                    html += `<p><strong>تاریخ برگشت:</strong> ${data.reservation.return_date_persian}</p>`;
                    html += `<p><strong>وضعیت:</strong> ${data.reservation.status_label}</p>`;

                    if (data.reservation.penalty > 0) {
                        html += `<p class="warning"><strong>جریمه:</strong> ${data.reservation.penalty.toLocaleString('fa-IR')} تومان</p>`;
                        html += `<p><strong>وضعیت پرداخت:</strong> ${data.reservation.penalty_paid ? 'پرداخت شده' : 'پرداخت نشده'}</p>`;
                    }

                    html += '</div>';
                    html += '</div>';

                    content.innerHTML = html;
                    modal.style.display = 'block';
                } else {
                    alert(data.message);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('خطا در دریافت اطلاعات');
            });
    }

    // بستن مودال
    const modal = document.getElementById('detail-modal');
    const closeBtn = document.querySelector('.close-modal');
    if (closeBtn) {
        closeBtn.addEventListener('click', function() {
            modal.style.display = 'none';
        });
    }

    window.addEventListener('click', function(event) {
        if (event.target === modal) {
            modal.style.display = 'none';
        }
    });

    // نمایش رنگ‌بندی تاخیرها
    highlightOverdueReservations();
});

function highlightOverdueReservations() {
    const rows = document.querySelectorAll('tr[data-status="active"]');
    rows.forEach(row => {
        const returnDate = row.dataset.returnDate;
        const today = new Date().toISOString().split('T')[0];

        if (returnDate < today) {
            row.classList.add('overdue-row');
        }
    });
}
