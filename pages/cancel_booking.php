<?php
// pages/cancel_booking.php
require_once '../config.php';
if (!isset($_SESSION['user_id']))          { header("Location: login.php"); exit(); }
if ($_SESSION['user_role'] !== 'customer') { header("Location: owner_dashboard.php"); exit(); }
$booking_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
if ($booking_id <= 0) { header("Location: my_bookings.php"); exit(); }
try {
    $stmt = $pdo->prepare("SELECT b.*, bs.status_name FROM bookings b JOIN booking_status bs ON b.status_id=bs.status_id WHERE b.booking_id=? AND b.customer_id=?");
    $stmt->execute([$booking_id, $_SESSION['user_id']]);
    $booking = $stmt->fetch();
    if (!$booking) { $_SESSION['error']="Booking not found."; header("Location: my_bookings.php"); exit(); }
    if ($booking['status_name'] !== 'pending') { $_SESSION['error']="Only pending bookings can be cancelled."; header("Location: my_bookings.php"); exit(); }
    $pdo->prepare("UPDATE bookings SET status_id=3 WHERE booking_id=?")->execute([$booking_id]);
    $_SESSION['success'] = "Booking cancelled successfully.";
} catch (PDOException $e) { error_log($e->getMessage()); $_SESSION['error']="Failed to cancel booking."; }
header("Location: my_bookings.php");
exit();
