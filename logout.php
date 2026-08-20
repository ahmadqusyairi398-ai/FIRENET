<?php
session_start();

$target = isset($_GET['redirect']) ? strtolower($_GET['redirect']) : (isset($_GET['type']) ? strtolower($_GET['type']) : (isset($_SESSION['dashboard_type']) ? strtolower($_SESSION['dashboard_type']) : ''));

if ($target === 'indoor') {
    unset($_SESSION['login_indoor'], $_SESSION['indoor_username'], $_SESSION['indoor_user_id'], $_SESSION['indoor_role'], $_SESSION['indoor_login_time']);
    if (isset($_SESSION['dashboard_type']) && $_SESSION['dashboard_type'] === 'indoor') {
        unset($_SESSION['dashboard_type']);
    }
    header("Location: login.php?redirect=indoor");
    exit();
} elseif ($target === 'outdoor') {
    unset($_SESSION['login_outdoor'], $_SESSION['outdoor_username'], $_SESSION['outdoor_user_id'], $_SESSION['outdoor_role'], $_SESSION['outdoor_login_time']);
    if (isset($_SESSION['dashboard_type']) && $_SESSION['dashboard_type'] === 'outdoor') {
        unset($_SESSION['dashboard_type']);
    }
    header("Location: login.php?redirect=outdoor");
    exit();
} else {
    session_unset();
    session_destroy();
    header("Location: home.php");
    exit();
}
?>