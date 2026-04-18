<?php
// pages/delete_review.php
require_once '../config.php';
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') { header("Location: login.php"); exit(); }
$rid = isset($_GET['id']) ? intval($_GET['id']) : 0;
if ($rid > 0) {
    try { $pdo->prepare("DELETE FROM reviews WHERE review_id = ?")->execute([$rid]); }
    catch (PDOException $e) { error_log($e->getMessage()); }
}
header("Location: admin_dashboard.php?tab=reviews");
exit();
