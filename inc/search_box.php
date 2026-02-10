<?php
require_once __DIR__ . '/includes/security.php';
?>
<div class="search-area">
    <form action="search.php" method="GET">
        <input id="search" name="search"
               placeholder="نام کتاب مورد نظر خود را وارد کنید ..."
               type="text"
               value="<?php echo htmlspecialchars($_GET['search'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"
               maxlength="100">
        <button type="submit">جستجوی کتاب</button>
    </form>
</div>
