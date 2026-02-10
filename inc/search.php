<?php
require_once __DIR__ . '/includes/security.php';
 if (isset($_GET['search'])) {
    $search = $_GET['search'];
    search($search);
    ?>


    <h1 class="category_title"> جستجو برای : <?php echo  $search ?></h1>
    <div class="books-container">
        <?php
        foreach ($books as $book) { ?>
            <div class="book">
                <img alt="PHP book" src="assets/img/books/<?php echo  $book['image'] ?>">
                <div class="book-title"><?php echo  $book['book_name'] ?></div>
                <div class="book-category">دسته بندی:
                    <a class="cat-link" href="category.php?cat_id=<?php echo  $book['category_id'] ?>">
                         <?php echo  get_category_name($book['category_id']) ?>
                    </a>
                </div>
                <div class="book-count">موجودی کتابخانه: <?php if ($book['count'] > 0) {
                        echo $book['count'] . ' عدد ';
                    } else {
                        echo 'ناموجود';
                    } ?>
                </div>                <div class="book-buttons">
                    <a class="more-info" href="book.php?bid=<?php echo  $book['bid'] ?>">توضیحات بیشتر </a>
                    <!--            <a class="borrow">درخواست امانت گرفتن</a>-->
                </div>
            </div>
        <?php } ?>
    </div>

    <div class="more-books">
        <a class="library-button" href="library.php">برو به کتابخانه</a>
    </div>
<?php } else {
    header('Location:index.php');
} ?>