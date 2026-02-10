<h1 class="library-title">کتابخانه:</h1>
<div class="library-container">
    <div class="main-content">
        <?php
        get_categories();
        if (isset($_GET['page'])) {
            $page = $_GET['page'];
            get_books_list($page);
        } else {
            get_books_list(1);
        }
        foreach ($books as $book) { ?>
            <div class="book-box">
                <div class="book-box-info">
                    <img alt="" src="assets/img/books/<?php echo  $book['image'] ?>">
                    <div class="infos">
                        <h1><?php echo  $book['book_name'] ?></h1>
                        <h5>دسته بندی: <a style="text-decoration: none;color: #000"
                                          href="category.php?cat_id=<?php echo  $book['category_id'] ?>">
                                <?php echo  get_category_name($book['category_id']) ?>
                            </a></h5>
                        <h5>نویسنده : <?php echo  $book['author'] ?></h5>
                        <h5>سال چاپ : <?php echo  $book['publish_year'] ?></h5>
                        <h5>موجودی کتابخانه: <?php if ($book['count'] > 0) {
                                echo $book['count'] . ' عدد ';
                            } else {
                                echo 'ناموجود';
                            } ?></h5>
                    </div>
                    <div class="more-info-button">
                        <a style="padding: 0.6rem 4rem;" class="more-btn" href="book.php?bid=<?php echo  $book['bid'] ?>">توضیحات
                            بیشتر</a>
                        <!--                        <a class="borrow-btn" href="#">درخواست امانت گرفتن</a>-->
                    </div>
                </div>
            </div>
        <?php } ?>
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

    </div>

    <div class="sidebar">
        <div class="sidebar-search-box">
            <form action="search.php" method="GET">
                <input id="search" name="search" placeholder="نام کتاب مورد نظر خود را وارد کنید ..."
                       type="text">
                <button type="submit"> جستجو</button>
            </form>
        </div>

        <h1>دسته بندی کتابخانه:</h1>

        <div class="sidebar-categories-container">
            <?php foreach ($cats as $cat) { ?>
                <a href="category.php?cat_id=<?php echo  $cat['cat_id'] ?>"
                   class="sidebar-category-box"><?php echo  $cat['cat_name'] ?></a>
            <?php } ?>
        </div>
    </div>
</div>