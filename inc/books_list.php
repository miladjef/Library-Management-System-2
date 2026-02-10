<h1 class="latest-books">جدیدترین کتاب ها:</h1>

<div class="books-container">
    <?php
    if (isset($_GET['page'])) {
        $page = $_GET['page'];
        get_books_list($page);
    } else {
        get_books_list(1);
    }
    foreach ($books as $book) { ?>
        <div class="book">
            <a href="book.php?bid=<?php echo  $book['bid'] ?>"> <img alt="PHP book"
                                                             src="assets/img/books/<?php echo  $book['image'] ?>"></a>
            <div class="book-title"><a href="book.php?bid=<?php echo  $book['bid'] ?>"><?php echo  $book['book_name'] ?></a></div>
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
            </div>
            <div class="book-buttons">
                <a class="more-info" href="book.php?bid=<?php echo  $book['bid'] ?>">توضیحات بیشتر </a>
                <!--                <a class="borrow">درخواست امانت گرفتن</a>-->
            </div>
        </div>
    <?php } ?>
</div>
<div class="pagination">
    <?php
    $i = 1;
    while ($i <= book_pages()) { ?>
        <a href="?page=<?php echo  $i ?>">
            <div class="page-number <?php if (isset($_GET['page']) && $_GET['page'] == $i) {
                echo "selected";
            } ?>"><?php echo  $i ?></div>
        </a>
        <?php $i++;
    } ?>


</div>
<?php book_pages() ?>
<!--<div class="more-books">-->
<!--    <a class="library-button" href="library.php">برو به کتابخانه</a>-->
<!--</div>-->