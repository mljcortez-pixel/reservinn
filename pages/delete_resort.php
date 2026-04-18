<?php
// pages/delete_resort.php
require_once '../config.php';
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'owner') { header("Location: login.php"); exit(); }
$resort_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
if ($resort_id <= 0) { header("Location: owner_dashboard.php"); exit(); }
try {
    $stmt = $pdo->prepare("SELECT resort_id, image_path FROM resorts WHERE resort_id = ? AND owner_id = ?");
    $stmt->execute([$resort_id, $_SESSION['user_id']]);
    $r = $stmt->fetch();
    if (!$r) { header("Location: owner_dashboard.php"); exit(); }
    // Remove image file if exists
    if ($r['image_path'] && file_exists('../' . $r['image_path'])) @unlink('../' . $r['image_path']);
    $pdo->prepare("DELETE FROM resorts WHERE resort_id = ? AND owner_id = ?")->execute([$resort_id, $_SESSION['user_id']]);
    $_SESSION['message'] = "Resort deleted successfully.";
} catch (PDOException $e) {
    error_log($e->getMessage());
    $_SESSION['error'] = "Failed to delete resort.";
}
header("Location: owner_dashboard.php");
exit();
