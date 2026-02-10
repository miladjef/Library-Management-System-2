<?php
require_once __DIR__ . '/includes/security.php';

// admin/search_national_library.php

$title = 'جستجو در کتابخانه ملی';
include "inc/header.php";

if (!isset($_SESSION['admin_logged_in'])) {
    header("Location: ../login.php");
    exit;
}
?>

<div class="main">
    <div class="page-title">
        جستجو در کتابخانه ملی ایران
    </div>

    <div class="search-container">
        <div class="search-form">
            <h3>جستجو با شابک (ISBN)</h3>
            <form id="isbnSearchForm">
                <label for="isbn">شابک کتاب:</label>
                <input type="text" id="isbn" name="isbn"
                       placeholder="مثال: 9789648333329"
                       pattern="[0-9X-]{10,17}">
                <button type="submit" class="submit">
                    <img src="assets/img/search.svg" alt="جستجو" width="20">
                    جستجو
                </button>
            </form>
        </div>

        <div class="search-form">
            <h3>جستجو با عنوان</h3>
            <form id="titleSearchForm">
                <label for="bookTitle">عنوان کتاب:</label>
                <input type="text" id="bookTitle" name="title"
                       placeholder="عنوان کتاب را وارد کنید">
                <button type="submit" class="submit">
                    <img src="assets/img/search.svg" alt="جستجو" width="20">
                    جستجو
                </button>
            </form>
        </div>
    </div>

    <div id="searchResults"></div>
    <div id="loadingIndicator" style="display: none;">
        <img src="assets/img/loading.gif" alt="در حال جستجو...">
        <p>در حال جستجو در کتابخانه ملی...</p>
    </div>
</div>

<style>
.search-container {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 30px;
    margin: 30px 0;
}

.search-form {
    background: #fff;
    padding: 25px;
    border-radius: 10px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
}

.search-form h3 {
    margin-bottom: 20px;
    color: #2c3e50;
    border-bottom: 2px solid #3498db;
    padding-bottom: 10px;
}

#searchResults {
    margin-top: 30px;
}

.book-result {
    background: #fff;
    padding: 20px;
    margin: 15px 0;
    border-radius: 10px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
    display: grid;
    grid-template-columns: 150px 1fr auto;
    gap: 20px;
    align-items: start;
}

.book-result img {
    width: 150px;
    height: 200px;
    object-fit: cover;
    border-radius: 5px;
}

.book-info h4 {
    color: #2c3e50;
    margin-bottom: 10px;
}

.book-info p {
    margin: 5px 0;
    color: #7f8c8d;
}

.import-btn {
    background: #27ae60;
    color: white;
    padding: 12px 25px;
    border: none;
    border-radius: 5px;
    cursor: pointer;
    font-family: IRANSans;
    transition: 0.3s;
}

.import-btn:hover {
    background: #229954;
}

#loadingIndicator {
    text-align: center;
    padding: 40px;
}

#loadingIndicator img {
    width: 50px;
}

@media (max-width: 768px) {
    .search-container {
        grid-template-columns: 1fr;
    }

    .book-result {
        grid-template-columns: 1fr;
    }
}
</style>

<script>
// جستجو با شابک
document.getElementById('isbnSearchForm').addEventListener('submit', function(e) {
    e.preventDefault();
    const isbn = document.getElementById('isbn').value;
    searchNationalLibrary('isbn', isbn);
});

// جستجو با عنوان
document.getElementById('titleSearchForm').addEventListener('submit', function(e) {
    e.preventDefault();
    const title = document.getElementById('bookTitle').value;
    searchNationalLibrary('title', title);
});

function searchNationalLibrary(type, value) {
    const resultsDiv = document.getElementById('searchResults');
    const loadingDiv = document.getElementById('loadingIndicator');

    resultsDiv.innerHTML = '';
    loadingDiv.style.display = 'block';

    const formData = new FormData();
    formData.append(type, value);

    fetch('api/national_library_search.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        loadingDiv.style.display = 'none';

        if (data.success) {
            if (Array.isArray(data.data)) {
                displayMultipleResults(data.data);
            } else {
                displaySingleResult(data.data);
            }
        } else {
            resultsDiv.innerHTML = `
                <div class="fail-notification">
                    ${data.message}
                </div>
            `;
        }
    })
    .catch(error => {
        loadingDiv.style.display = 'none';
        resultsDiv.innerHTML = `
            <div class="fail-notification">
                خطا در اتصال به سرویس: ${error.message}
            </div>
        `;
    });
}

function displaySingleResult(book) {
    const resultsDiv = document.getElementById('searchResults');
    resultsDiv.innerHTML = createBookCard(book);
}

function displayMultipleResults(books) {
    const resultsDiv = document.getElementById('searchResults');

    if (books.length === 0) {
        resultsDiv.innerHTML = '<div class="fail-notification">نتیجه‌ای یافت نشد</div>';
        return;
    }

    resultsDiv.innerHTML = '<h3>نتایج جستجو (' + books.length + ' کتاب)</h3>';
    books.forEach(book => {
        resultsDiv.innerHTML += createBookCard(book);
    });
}

function createBookCard(book) {
    return `
        <div class="book-result">
            <img src="${book.cover_image || 'assets/img/no-cover.jpg'}"
                 alt="${book.title}"
                 onerror="this.src='assets/img/no-cover.jpg'">
            <div class="book-info">
                <h4>${book.title}</h4>
                <p><strong>نویسنده:</strong> ${book.author || 'نامشخص'}</p>
                <p><strong>ناشر:</strong> ${book.publisher || 'نامشخص'}</p>
                <p><strong>سال انتشار:</strong> ${book.publish_year || 'نامشخص'}</p>
                <p><strong>شابک:</strong> ${book.isbn || 'ندارد'}</p>
                ${book.description ? '<p><strong>توضیحات:</strong> ' + book.description + '</p>' : ''}
            </div>
            <div>
                <button class="import-btn" onclick='importBook(${JSON.stringify(book)})'>
                    وارد کردن به کتابخانه
                </button>
            </div>
        </div>
    `;
}

function importBook(bookData) {
    if (confirm('آیا مایل به وارد کردن این کتاب به کتابخانه هستید؟')) {
        // انتقال به صفحه افزودن کتاب با داده‌های از پیش پر شده
        const params = new URLSearchParams({
            isbn: bookData.isbn || '',
            title: bookData.title || '',
            author: bookData.author || '',
            publisher: bookData.publisher || '',
            year: bookData.publish_year || '',
            description: bookData.description || '',
            cover_url: bookData.cover_image || ''
        });

        window.location.href = 'add_book.php?' + params.toString();
    }
}
</script>

<?php include "inc/footer.php"; ?>
