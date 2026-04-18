<?php
// pages/notifications.php
require_once '../config.php';

if (!isset($_SESSION['user_id'])) { header("Location: login.php"); exit(); }

// Mark all as read
try {
    $pdo->prepare("UPDATE notifications SET is_read=1 WHERE user_id=?")->execute([$_SESSION['user_id']]);
} catch (PDOException $e) { }

// Delete single
if (isset($_GET['delete']) && intval($_GET['delete']) > 0) {
    try {
        $pdo->prepare("DELETE FROM notifications WHERE notif_id=? AND user_id=?")->execute([intval($_GET['delete']), $_SESSION['user_id']]);
    } catch (PDOException $e) { }
    header("Location: notifications.php");
    exit();
}

// Clear all
if (isset($_GET['clear_all'])) {
    try {
        $pdo->prepare("DELETE FROM notifications WHERE user_id=?")->execute([$_SESSION['user_id']]);
    } catch (PDOException $e) { }
    header("Location: notifications.php");
    exit();
}

$notifications = [];
try {
    $stmt = $pdo->prepare("SELECT * FROM notifications WHERE user_id=? ORDER BY created_at DESC LIMIT 60");
    $stmt->execute([$_SESSION['user_id']]);
    $notifications = $stmt->fetchAll();
} catch (PDOException $e) { error_log($e->getMessage()); }

$pageTitle = 'Notifications';
include '_head.php';

$iconMap = [
    'booking_new'       => '<path d="M18 8A6 6 0 006 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 01-3.46 0"/>',
    'booking_confirmed' => '<polyline points="20 6 9 17 4 12"/>',
    'booking_rejected'  => '<circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/>',
    'booking_cancelled' => '<circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/>',
    'review_new'        => '<polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/>',
    'system'            => '<circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/>',
];
$colorMap = [
    'booking_new'       => '#004085',
    'booking_confirmed' => '#155724',
    'booking_rejected'  => '#721c24',
    'booking_cancelled' => '#856404',
    'review_new'        => '#856404',
    'system'            => '#004085',
];
$bgMap = [
    'booking_new'       => '#cce5ff',
    'booking_confirmed' => '#d4edda',
    'booking_rejected'  => '#f8d7da',
    'booking_cancelled' => '#fff3cd',
    'review_new'        => '#fff3cd',
    'system'            => '#cce5ff',
];
?>

<main class="section section--sm">
  <div class="container container--sm">

    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:24px;flex-wrap:wrap;gap:12px">
      <div>
        <span class="section-eyebrow">Inbox</span>
        <h1>Notifications</h1>
      </div>
      <?php if (count($notifications)): ?>
        <a href="notifications.php?clear_all=1" class="btn btn--danger btn--sm" data-confirm="Clear all notifications?">Clear All</a>
      <?php endif; ?>
    </div>

    <?php if (count($notifications)): ?>
      <?php foreach ($notifications as $n):
        $icon  = $iconMap[$n['type']]  ?? $iconMap['system'];
        $color = $colorMap[$n['type']] ?? $colorMap['system'];
        $bg    = $bgMap[$n['type']]    ?? $bgMap['system'];
        $ago   = human_time_diff(strtotime($n['created_at']));
      ?>
        <div class="card mb-12" style="<?php echo !$n['is_read']?'border-left:3px solid var(--navy)':''; ?>">
          <div class="card__body" style="display:flex;gap:14px;align-items:flex-start;padding:16px 20px">
            <div style="width:36px;height:36px;border-radius:50%;background:<?php echo $bg; ?>;color:<?php echo $color; ?>;display:flex;align-items:center;justify-content:center;flex-shrink:0">
              <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><?php echo $icon; ?></svg>
            </div>
            <div style="flex:1">
              <div style="display:flex;justify-content:space-between;align-items:center;gap:8px;flex-wrap:wrap">
                <div style="font-weight:600;font-size:.9rem;color:var(--navy)"><?php echo htmlspecialchars($n['title']); ?></div>
                <div style="font-size:.72rem;color:var(--text-muted);white-space:nowrap"><?php echo $ago; ?></div>
              </div>
              <p style="margin:4px 0 8px;font-size:.84rem;color:var(--text-secondary)"><?php echo htmlspecialchars($n['message']); ?></p>
              <div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap">
                <?php if ($n['link']): ?>
                  <a href="<?php echo htmlspecialchars($n['link']); ?>" class="btn btn--ghost btn--sm">View Details</a>
                <?php endif; ?>
                <a href="notifications.php?delete=<?php echo $n['notif_id']; ?>" class="btn btn--sm" style="color:var(--text-muted);border-color:transparent;background:transparent">Dismiss</a>
              </div>
            </div>
          </div>
        </div>
      <?php endforeach; ?>
    <?php else: ?>
      <div class="card"><div class="card__body">
        <div class="empty-state" style="padding:56px 0">
          <svg class="empty-state__icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.25"><path d="M18 8A6 6 0 006 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 01-3.46 0"/></svg>
          <h3 class="empty-state__title">All clear!</h3>
          <p class="empty-state__text">No notifications at the moment.</p>
        </div>
      </div></div>
    <?php endif; ?>

  </div>
</main>

<?php include '_foot.php'; ?>
<?php
function human_time_diff(int $from): string {
    $diff = time() - $from;
    if ($diff < 60)     return 'just now';
    if ($diff < 3600)   return floor($diff/60)   . 'm ago';
    if ($diff < 86400)  return floor($diff/3600)  . 'h ago';
    if ($diff < 604800) return floor($diff/86400) . 'd ago';
    return date('M d', $from);
}
?>
