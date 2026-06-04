<?php
session_start();
if (!isset($_SESSION['application_id'])) {
    header('Location: login.php');
    exit();
}
header('Location: index.php');
exit();
?>