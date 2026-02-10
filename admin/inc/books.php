<div class="main">
    <?php if (isset($_GET['add']) && $_GET['add'] == 'ok') {
        ?>
        <div class="success-notification" id="closeAddBook">
            کتاب با موفقیت به کتابخانه افزوده شد.
            <span onclick="closeAddBook()">&times;</span>
        </div>
    <?php }
    if (isset($_GET['edit']) && $_GET['edit'] == 'ok') { ?>
        <div class="success-notification" id="closeEditOk">
            کتاب با موفقیت ویرایش شد.
            <span onclick="closeEditOk()">&times;</span>
        </div>
    <?php }
    if (isset($_POST['delete'])) {
        $book_id = $_POST['bid'];
        if (delete_book($book_id)) { ?>
            <div class="success-notification" id="closeAddBook">
                کتاب با موفقیت از کتابخانه حذف شد.
                <span onclick="closeAddBook()">&times;</span>
            </div>
        <?php }
    } ?>

    <div class="page-title">
        مدیریت کتاب ها
    </div>
    <a href="add_book.php">
        <div class="add-book-btn">
            افزودن کتاب
        </div>
    </a>
    <div class="books-list">
        <table>
            <tr>
                <th>تصویر کتاب</th>
                <th>شناسه کتاب</th>
                <th>نام کتاب</th>
                <th>دسته بندی</th>
                <th>نویسنده</th>
                <th>سال چاپ</th>
                <th>تعداد موجودی</th>
                <th>عملیات</th>
            </tr>
            <?php get_books();
            foreach ($books as $book) { ?>
                <tr>
                    <td><img width="100px" src="<?php echo  IMG_PATH . $book['image'] ?>" alt="<?php echo  $book['book_name'] ?>"></td>
                    <td><?php echo  $book['bid'] ?></td>
                    <td><a href="<?php echo siteurl()?>/book.php?bid=<?php echo  $book['bid'] ?>" target="_blank"><?php echo  $book['book_name'] ?></a></td>
                    <td><a href="<?php echo siteurl()?>/category.php?cat_id=<?php echo $book['category_id']?>" target="_blank"> <?php echo  get_category_name($book['category_id']) ?></a></td>
                    <td><?php echo  $book['author'] ?></td>
                    <td><?php echo  $book['publish_year'] ?></td>
                    <td><?php echo  $book['count'] ?> عدد</td>
                    <td>
                        <form action="edit_book.php" method="POST">
                            <input type="hidden" value="<?php echo  $book['bid'] ?>" name="bid">
                            <button class="edit_delete_btn"><img src="assets/img/edit.svg" alt="ویرایش"></button>
                        </form>

                        <form action="" method="POST" id="delete_book_form" onsubmit="return confirm(`از حذف این کتاب اطمینان دارید؟`)">
                            <input type="hidden" value="<?php echo  $book['bid'] ?>" name="bid">
                            <button class="edit_delete_btn" name="delete"><img src="assets/img/delete.svg" alt="حذف">
                            </button>
                        </form>

                    </td>
                </tr>
            <?php } ?>
        </table>
    </div>
</div>

