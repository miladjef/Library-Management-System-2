// assets/js/user.js

// User Menu Toggle
document.addEventListener('DOMContentLoaded', function() {
    const userBtn = document.querySelector('.user-btn');
    const userMenu = document.querySelector('.user-menu');

    if (userBtn && userMenu) {
        userBtn.addEventListener('click', function(e) {
            e.stopPropagation();
            userMenu.classList.toggle('active');
        });

        // بستن منو با کلیک خارج از آن
        document.addEventListener('click', function(e) {
            if (!userMenu.contains(e.target) && !userBtn.contains(e.target)) {
                userMenu.classList.remove('active');
            }
        });
    }
});

// Mobile Navigation
function toggleMobileMenu() {
    const navMenu = document.getElementById('nav-menu');
    const body = document.body;

    navMenu.classList.toggle('active');
    body.classList.toggle('menu-open');
}

// Search Functionality
function initSearchAutocomplete() {
    const searchInput = document.querySelector('.search-input');

    if (searchInput) {
        let debounceTimer;

        searchInput.addEventListener('input', function() {
            clearTimeout(debounceTimer);
            const query = this.value.trim();

            if (query.length >= 2) {
                debounceTimer = setTimeout(() => {
                    fetchSearchSuggestions(query);
                }, 300);
            } else {
                hideSearchSuggestions();
            }
        });
    }
}

function fetchSearchSuggestions(query) {
    fetch(`api/search_suggestions.php?q=${encodeURIComponent(query)}`)
        .then(response => response.json())
        .then(data => {
            if (data.success && data.suggestions.length > 0) {
                displaySearchSuggestions(data.suggestions);
            } else {
                hideSearchSuggestions();
            }
        })
        .catch(error => {
            console.error('Search error:', error);
        });
}

function displaySearchSuggestions(suggestions) {
    let suggestionsBox = document.getElementById('search-suggestions');

    if (!suggestionsBox) {
        suggestionsBox = document.createElement('div');
        suggestionsBox.id = 'search-suggestions';
        suggestionsBox.className = 'search-suggestions';
        document.querySelector('.search-box').appendChild(suggestionsBox);
    }

    suggestionsBox.innerHTML = suggestions.map(item => `
        <a href="book.php?id=${item.bid}" class="suggestion-item">
            <img src="${item.image}" alt="${item.book_name}" onerror="this.src='assets/img/no-image.jpg'">
            <div class="suggestion-info">
                <div class="suggestion-title">${item.book_name}</div>
                <div class="suggestion-author">${item.author}</div>
            </div>
        </a>
    `).join('');

    suggestionsBox.classList.add('active');
}

function hideSearchSuggestions() {
    const suggestionsBox = document.getElementById('search-suggestions');
    if (suggestionsBox) {
        suggestionsBox.classList.remove('active');
    }
}

// Filter Management
function applyFilters() {
    const form = document.querySelector('.filters-form');
    if (form) {
        form.submit();
    }
}

// Book Actions
function addToWishlist(bookId) {
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content;

    fetch('api/add_to_wishlist.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            book_id: bookId,
            csrf_token: csrfToken
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showNotification('کتاب به لیست علاقه‌مندی‌ها اضافه شد', 'success');
        } else {
            showNotification(data.message || 'خطایی رخ داد', 'error');
        }
    })
    .catch(error => {
        console.error('Wishlist error:', error);
        showNotification('خطا در ارتباط با سرور', 'error');
    });
}

// Share Book
function shareBook(title, url) {
    if (navigator.share) {
        navigator.share({
            title: title,
            url: url
        })
        .then(() => console.log('Shared successfully'))
        .catch(error => console.error('Share error:', error));
    } else {
        // Fallback: کپی لینک
        copyToClipboard(url);
        showNotification('لینک کپی شد', 'success');
    }
}

// Copy to Clipboard
function copyToClipboard(text) {
    const textarea = document.createElement('textarea');
    textarea.value = text;
    textarea.style.position = 'fixed';
    textarea.style.opacity = '0';
    document.body.appendChild(textarea);
    textarea.select();
    document.execCommand('copy');
    document.body.removeChild(textarea);
}

// Initialize on load
document.addEventListener('DOMContentLoaded', function() {
    initSearchAutocomplete();
});
