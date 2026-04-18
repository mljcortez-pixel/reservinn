<?php
// pages/dashboard.php — role-based redirect
require_once '../config.php';
if (!isset($_SESSION['user_id'])) { header("Location: login.php"); exit(); }
switch ($_SESSION['user_role']) {
    case 'admin': header("Location: admin_dashboard.php"); break;
    case 'owner': header("Location: owner_dashboard.php"); break;
    default:      header("Location: customer_dashboard.php");
}
exit();
