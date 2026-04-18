<?php
// pages/_owner_sidebar.php — Shared sidebar for all Owner pages.
// Include AFTER defining $activePage, e.g.:
//   $activePage = 'dashboard'; // dashboard | add_resort | bookings | notifications | profile
// If $activePage is not set, no item is highlighted.

$activePage = $activePage ?? '';

// Pending bookings badge
$ownerPending = 0;
try {
    $ownResorts = $pdo->prepare("SELECT resort_id FROM resorts WHERE owner_id=?");
    $ownResorts->execute([$_SESSION['user_id']]);
    $ids = array_column($ownResorts->fetchAll(), 'resort_id');
    if ($ids) {
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $ownerPending = (int)$pdo->prepare("SELECT COUNT(*) FROM bookings WHERE resort_id IN ($placeholders) AND status_id=1")
            ->execute($ids) ?
            $pdo->query("SELECT COUNT(*) FROM bookings WHERE resort_id IN (" . implode(',', $ids) . ") AND status_id=1")->fetchColumn() : 0;
    }
} catch (PDOException $e) { $ownerPending = 0; }

function ownerNavItem(string $href, string $icon, string $label, bool $active, $badge = null): string {
    $activeClass = $active ? ' active' : '';
    $badgeHtml   = $badge ? "<span class=\"sidebar-badge\">{$badge}</span>" : '';
    return "<a class=\"dash-nav__item{$activeClass}\" href=\"{$href}\">{$icon} {$label}{$badgeHtml}</a>";
}

$icons = [
    'dashboard'     => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 9l9-7 9 7v11a2 2 0 01-2 2H5a2 2 0 01-2-2z"/></svg>',
    'add_resort'    => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="16"/><line x1="8" y1="12" x2="16" y2="12"/></svg>',
    'bookings'      => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="3" y1="10" x2="21" y2="10"/></svg>',
    'notifications' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 8A6 6 0 006 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 01-3.46 0"/></svg>',
    'reviews'       => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg>',
    'profile'       => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 00-4-4H8a4 4 0 00-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>',
    'signout'       => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 21H5a2 2 0 01-2-2V5a2 2 0 012-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>',
];
?>
<aside class="dash-sidebar">
  <div class="dash-sidebar__section">
    <span class="dash-sidebar__label">Menu</span>
    <?php echo ownerNavItem('owner_dashboard.php',  $icons['dashboard'],     'My Resorts',    $activePage==='dashboard'); ?>
    <?php echo ownerNavItem('add_resort.php',        $icons['add_resort'],    'Add Resort',    $activePage==='add_resort'); ?>
    <?php echo ownerNavItem('owner_bookings.php',    $icons['bookings'],      'Bookings',      $activePage==='bookings', $ownerPending ?: null); ?>
    <?php echo ownerNavItem('owner_reviews.php',     $icons['reviews'],       'Reviews',       $activePage==='reviews'); ?>
    <?php echo ownerNavItem('notifications.php',     $icons['notifications'], 'Notifications', $activePage==='notifications'); ?>
    <?php echo ownerNavItem('profile.php',           $icons['profile'],       'My Profile',    $activePage==='profile'); ?>
  </div>
  <div class="dash-sidebar__section dash-sidebar__section--bottom">
    <a class="dash-nav__item" href="logout.php">
      <?php echo $icons['signout']; ?> Sign out
    </a>
  </div>
</aside>
