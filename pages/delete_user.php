<?php
// pages/delete_user.php
require_once '../config.php';
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') { header("Location: login.php"); exit(); }
$uid = isset($_GET['id']) ? intval($_GET['id']) : 0;
if ($uid <= 0) { header("Location: admin_dashboard.php"); exit(); }
try {
    // Protect admins
    $check = $pdo->prepare("SELECT role_id FROM users WHERE user_id = ?");
    $check->execute([$uid]);
    $u = $check->fetch();
    if ($u && $u['role_id'] == 1) { header("Location: admin_dashboard.php?tab=users"); exit(); }
    $pdo->prepare("DELETE FROM users WHERE user_id = ?")->execute([$uid]);
} catch (PDOException $e) { error_log($e->getMessage()); }
header("Location: admin_dashboard.php?tab=users");
exit();
