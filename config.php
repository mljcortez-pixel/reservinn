<?php
session_start();

// Railway MySQL Connection Settings
$db_host = getenv('MYSQL_HOST') ?: 'nozomi.proxy.rlwy.net';
$db_port = getenv('MYSQL_PORT') ?: '16595';
$db_name = getenv('MYSQL_DATABASE') ?: 'railway';
$db_user = getenv('MYSQL_USER') ?: 'root';
$db_pass = getenv('MYSQL_PASSWORD') ?: '';

// Use environment variables if available (Railway sets these automatically)
if (getenv('RAILWAY_ENVIRONMENT')) {
    $db_host = getenv('MYSQL_HOST');
    $db_port = getenv('MYSQL_PORT');
    $db_name = getenv('MYSQL_DATABASE');
    $db_user = getenv('MYSQL_USER');
    $db_pass = getenv('MYSQL_PASSWORD');
}

define('DB_HOST', $db_host . ':' . $db_port);
define('DB_NAME', $db_name);
define('DB_USER', $db_user);
define('DB_PASS', $db_pass);
define('APP_NAME', 'ReservInn');
define('UPLOAD_DIR', __DIR__ . '/uploads/resorts/');
define('PROFILE_DIR', __DIR__ . '/uploads/profiles/');
define('PROFILE_URL', 'uploads/profiles/');
define('RESERVATION_FEE_PERCENT', 30);

// Status IDs
define('STATUS_PENDING',   1);
define('STATUS_CONFIRMED', 2);
define('STATUS_CANCELLED', 3);
define('STATUS_COMPLETED', 4);
define('STATUS_REJECTED',  5);
define('STATUS_PAID',      6);

try {
    $pdo = new PDO(
        "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
        DB_USER, 
        DB_PASS,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false
        ]
    );
} catch (PDOException $e) {
    error_log("DB failed: " . $e->getMessage());
    die("Database connection failed. Please check your configuration.");
}

// Rest of your functions remain the same...
function autoCompleteBookings(PDO $pdo): void {
    try {
        $pdo->query("UPDATE bookings SET status_id=".STATUS_COMPLETED."
                     WHERE status_id IN (".STATUS_CONFIRMED.",".STATUS_PAID.")
                     AND check_out_date < CURDATE()");
    } catch (PDOException $e) { error_log($e->getMessage()); }
}

function profilePhotoSrc(?string $filename, int $depth = 1): string {
    if (!$filename) return '';
    $base = str_repeat('../', $depth) . 'uploads/profiles/';
    $abs  = PROFILE_DIR . basename($filename);
    return file_exists($abs) ? $base . basename($filename) : '';
}

function profilePhotoExists(?string $f): bool {
    return $f && file_exists(PROFILE_DIR . basename($f));
}

function getPaymentInfo(PDO $pdo, int $booking_id, float $total): array {
    try {
        $s = $pdo->prepare("SELECT * FROM payments WHERE booking_id=? AND payment_status='completed' ORDER BY created_at ASC");
        $s->execute([$booking_id]);
        $payments   = $s->fetchAll();
        $total_paid = array_sum(array_column($payments, 'amount'));
        $has_down   = false; $is_full = false; $remaining = 0;
        foreach ($payments as $p) {
            $pt = $p['payment_type'] ?? 'full_payment';
            if ($pt === 'reservation_fee') { $has_down = true; $remaining = (float)($p['remaining_balance'] ?? 0); }
            if (in_array($pt, ['full_payment','balance'])) $is_full = true;
        }
        if ($total_paid >= $total && $total_paid > 0) $is_full = true;
        if ($is_full)  return ['label'=>'Fully Paid',   'class'=>'pay-status--full',    'paid'=>$total_paid,'remaining'=>0];
        if ($has_down) return ['label'=>'Down Payment', 'class'=>'pay-status--partial', 'paid'=>$total_paid,'remaining'=>$remaining];
        return         ['label'=>'Not Paid',            'class'=>'pay-status--none',    'paid'=>0,          'remaining'=>$total];
    } catch (PDOException $e) { return ['label'=>'Unknown','class'=>'pay-status--none','paid'=>0,'remaining'=>$total]; }
}

function createNotification(PDO $pdo, int $userId, string $type, string $title, string $message, string $link = ''): void {
    try {
        $pdo->prepare("INSERT INTO notifications (user_id,type,title,message,link) VALUES (?,?,?,?,?)")
            ->execute([$userId, $type, $title, $message, $link ?: null]);
    } catch (PDOException $e) { error_log($e->getMessage()); }
}

if (isset($_SESSION['user_id']) && !array_key_exists('profile_photo', $_SESSION)) {
    try {
        $ps = $pdo->prepare("SELECT profile_photo FROM users WHERE user_id=?");
        $ps->execute([$_SESSION['user_id']]);
        $_SESSION['profile_photo'] = $ps->fetchColumn() ?: null;
    } catch (PDOException $e) { $_SESSION['profile_photo'] = null; }
}

if (isset($_SESSION['user_id'])) autoCompleteBookings($pdo);
?>