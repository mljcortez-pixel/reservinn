<?php
// pages/_head.php
if (!isset($pageTitle)) $pageTitle = 'ReservInn';

$unreadNotifs = 0;
if (isset($_SESSION['user_id'])) {
    try {
        $s = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE user_id=? AND is_read=0");
        $s->execute([$_SESSION['user_id']]);
        $unreadNotifs = (int)$s->fetchColumn();
    } catch (PDOException $e) { $unreadNotifs = 0; }
}
function navHome() {
    $r = $_SESSION['user_role'] ?? '';
    if ($r === 'admin')  return 'admin_dashboard.php';
    if ($r === 'owner')  return 'owner_dashboard.php';
    return 'customer_dashboard.php';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?php echo htmlspecialchars($pageTitle); ?> — ReservInn</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link rel="stylesheet" href="../css/style.css">
</head>
<body>
<nav class="nav">
  <div class="nav__inner">
    <a href="<?php echo navHome(); ?>" class="nav__logo">
      <div class="nav__logo-icon">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 9l9-7 9 7v11a2 2 0 01-2 2H5a2 2 0 01-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>
      </div>
      ReservInn
    </a>
    <div class="nav__links">
      <?php if (($_SESSION['user_role'] ?? '') === 'customer'): ?>
        <a href="browse_resorts.php" class="nav__link">Browse</a>
        <a href="my_bookings.php"    class="nav__link">My Bookings</a>
      <?php elseif (($_SESSION['user_role'] ?? '') === 'owner'): ?>
        <a href="owner_dashboard.php"  class="nav__link">Dashboard</a>
        <a href="add_resort.php"       class="nav__link">Add Resort</a>
        <a href="owner_bookings.php"   class="nav__link">Bookings</a>
      <?php elseif (($_SESSION['user_role'] ?? '') === 'admin'): ?>
        <a href="admin_dashboard.php"  class="nav__link">Admin</a>
      <?php endif; ?>
    </div>
    <div class="nav__right">
      <?php if (isset($_SESSION['user_id'])): ?>
        <a href="notifications.php" class="nav__notif-btn" title="Notifications">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 8A6 6 0 006 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 01-3.46 0"/></svg>
          <?php if ($unreadNotifs > 0): ?><span class="nav__notif-badge"><?php echo $unreadNotifs > 9 ? '9+' : $unreadNotifs; ?></span><?php endif; ?>
        </a>
        <span class="nav__username"><?php echo htmlspecialchars($_SESSION['user_name'] ?? ''); ?></span>
        <a href="profile.php" class="nav__avatar" title="My Profile">
          <?php $ph = profilePhotoSrc($_SESSION['profile_photo'] ?? null); ?>
          <?php if ($ph): ?><img src="<?php echo htmlspecialchars($ph); ?>" alt="" style="width:100%;height:100%;object-fit:cover;border-radius:50%"><?php else: ?><?php echo strtoupper(substr($_SESSION['user_name'] ?? 'U', 0, 1)); ?><?php endif; ?>
        </a>
        <a href="logout.php" class="btn--signout">Sign out</a>
      <?php endif; ?>
    </div>
  </div>
</nav>
