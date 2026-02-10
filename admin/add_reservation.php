<?php
require_once 'inc/functions.php';

if (!is_logged_in() || !is_admin($_SESSION['userid'])) {
    header('Location: login.php');
    exit;
}

$title = "امانت دادن دستی کتاب";
include "inc/header.php"; ?>
<?php include "inc/add_reservation.php"; ?>
<?php include "inc/footer.php" ?>