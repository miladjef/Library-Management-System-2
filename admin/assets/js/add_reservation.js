// admin/assets/js/add_reservation.js
document.addEventListener('DOMContentLoaded', function() {
    const memberSelect = document.getElementById('member_id');
    const bookSelect = document.getElementById('book_id');
    const memberInfoDiv = document.getElementById('member-info');
    const bookInfoDiv = document.getElementById('book-info');
    const submitBtn = document.querySelector('button[name="submit_reservation"]');
    const returnDateInput = document.getElementById('return_date');

    // نمایش اطلاعات عضو
    if (memberSelect) {
        memberSelect.addEventListener('change', function() {
            const memberId = this.value;
            if (!memberId) {
                memberInfoDiv.innerHTML = '';
                return;
            }

            fetch(`../api/get_member_info.php?id=${memberId}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        const member = data.member;
                        let html = '<div class="info-box">';
                        html += `<h4>اطلاعات عضو: ${member.name} ${member.surname}</h4>`;
                        html += `<p><strong>کد ملی:</strong> ${member.national_code}</p>`;
                        html += `<p><strong>موبایل:</strong> ${member.mobile}</p>`;
                        html += `<p><strong>امانت‌های فعال:</strong> ${member.active_reservations}</p>`;

                        if (member.unpaid_penalties > 0) {
                            html += `<p class="warning"><strong>⚠️ جریمه معوقه:</strong> ${member.unpaid_penalties.toLocaleString('fa-IR')} تومان</p>`;
                            submitBtn.disabled = true;
                            submitBtn.title = 'عضو دارای جریمه معوقه است';
                        } else if (member.active_reservations >= member.max_active) {
                            html += `<p class="warning"><strong>⚠️ محدودیت:</strong> حداکثر ${member.max_active} امانت فعال مجاز است</p>`;
                            submitBtn.disabled = true;
                            submitBtn.title = 'حداکثر تعداد امانت فعال';
                        } else {
                            html += '<p class="success">✓ عضو مجاز به دریافت امانت است</p>';
                            submitBtn.disabled = false;
                            submitBtn.title = '';
                        }

                        html += '</div>';
                        memberInfoDiv.innerHTML = html;
                    } else {
                        memberInfoDiv.innerHTML = `<p class="error">${data.message}</p>`;
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    memberInfoDiv.innerHTML = '<p class="error">خطا در دریافت اطلاعات عضو</p>';
                });
        });
    }

    // نمایش اطلاعات کتاب
    if (bookSelect) {
        bookSelect.addEventListener('change', function() {
            const bookId = this.value;
            if (!bookId) {
                bookInfoDiv.innerHTML = '';
                return;
            }

            fetch(`../api/get_book_info.php?id=${bookId}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        const book = data.book;
                        let html = '<div class="info-box">';
                        html += `<h4>کتاب: ${book.title}</h4>`;
                        html += `<p><strong>نویسنده:</strong> ${book.author}</p>`;
                        html += `<p><strong>ISBN:</strong> ${book.isbn}</p>`;
                        html += `<p><strong>موجودی کل:</strong> ${book.total_quantity}</p>`;
                        html += `<p><strong>موجودی قابل امانت:</strong> ${book.available_quantity}</p>`;

                        if (book.available_quantity <= 0) {
                            html += '<p class="warning">⚠️ موجودی کافی نیست</p>';
                            submitBtn.disabled = true;
                            submitBtn.title = 'موجودی کتاب کافی نیست';
                        } else {
                            html += '<p class="success">✓ کتاب موجود است</p>';
                            if (memberSelect.value && !submitBtn.disabled) {
                                submitBtn.disabled = false;
                            }
                        }

                        html += '</div>';
                        bookInfoDiv.innerHTML = html;
                    } else {
                        bookInfoDiv.innerHTML = `<p class="error">${data.message}</p>`;
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    bookInfoDiv.innerHTML = '<p class="error">خطا در دریافت اطلاعات کتاب</p>';
                });
        });
    }

    // اعتبارسنجی تاریخ برگشت
    if (returnDateInput) {
        returnDateInput.addEventListener('change', function() {
            const selectedDate = this.value;
            if (!selectedDate) return;

            // ارسال تاریخ به API برای اعتبارسنجی شمسی
            fetch('../api/validate_date.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({ date: selectedDate })
            })
            .then(response => response.json())
            .then(data => {
                if (!data.valid) {
                    alert(data.message || 'تاریخ نامعتبر است');
                    this.value = '';
                }
            });
        });
    }

    // بررسی نهایی قبل از ارسال فرم
    const reservationForm = document.querySelector('form[name="reservation_form"]');
    if (reservationForm) {
        reservationForm.addEventListener('submit', function(e) {
            if (!memberSelect.value || !bookSelect.value || !returnDateInput.value) {
                e.preventDefault();
                alert('لطفاً تمام فیلدها را پر کنید');
                return false;
            }

            if (submitBtn.disabled) {
                e.preventDefault();
                alert('امکان ثبت امانت وجود ندارد. لطفاً هشدارها را بررسی کنید.');
                return false;
            }
        });
    }
});
