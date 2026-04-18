<?php
// pages/owner_dashboard.php
require_once '../config.php';

if (!isset($_SESSION['user_id']))        { header("Location: login.php"); exit(); }
if ($_SESSION['user_role'] !== 'owner') { header("Location: customer_dashboard.php"); exit(); }

$resorts = [];
try {
    $stmt = $pdo->prepare("SELECT * FROM resorts WHERE owner_id = :oid ORDER BY created_at DESC");
    $stmt->execute([':oid' => $_SESSION['user_id']]);
    $resorts = $stmt->fetchAll();
} catch (PDOException $e) { error_log($e->getMessage()); }

$total_bookings = 0;
$total_revenue  = 0;
if ($resorts) {
    $ids = implode(',', array_column($resorts, 'resort_id'));
    try {
        $total_bookings = (int)$pdo->query("SELECT COUNT(*) FROM bookings WHERE resort_id IN ($ids) AND status_id NOT IN (3,5)")->fetchColumn();
        $total_revenue  = (float)$pdo->query("SELECT COALESCE(SUM(p.amount),0) FROM payments p JOIN bookings b ON p.booking_id=b.booking_id WHERE b.resort_id IN ($ids) AND p.payment_status='completed'")->fetchColumn();
    } catch (PDOException $e) { }
}

if (isset($_SESSION['message'])) { $flash_ok  = $_SESSION['message']; unset($_SESSION['message']); }
if (isset($_SESSION['error']))   { $flash_err = $_SESSION['error'];   unset($_SESSION['error']);   }

$pageTitle = 'Owner Dashboard';
$activePage = 'dashboard';
include '_head.php';
?>

<div class="dash-layout">
  <?php include '_owner_sidebar.php'; ?>

  <main class="dash-main">
    <div class="dash-header">
      <p class="dash-header__greeting">Resort Owner Portal</p>
      <h2>Welcome, <?php echo htmlspecialchars($_SESSION['user_name']); ?></h2>
    </div>

    <?php if (isset($flash_ok)):  ?><div class="alert alert--success" data-auto-dismiss="4000"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"/></svg><?php echo htmlspecialchars($flash_ok);  ?></div><?php endif; ?>
    <?php if (isset($flash_err)): ?><div class="alert alert--error"   data-auto-dismiss="5000"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/></svg><?php echo htmlspecialchars($flash_err); ?></div><?php endif; ?>

    <div class="stats-grid">
      <div class="stat-card">
        <div class="stat-card__icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 9l9-7 9 7v11a2 2 0 01-2 2H5a2 2 0 01-2-2z"/></svg></div>
        <div class="stat-card__label">My Resorts</div>
        <div class="stat-card__value"><?php echo count($resorts); ?></div>
      </div>
      <div class="stat-card">
        <div class="stat-card__icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="3" y1="10" x2="21" y2="10"/></svg></div>
        <div class="stat-card__label">Total Bookings</div>
        <div class="stat-card__value"><?php echo $total_bookings; ?></div>
      </div>
      <div class="stat-card">
        <div class="stat-card__icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 000 7h5a3.5 3.5 0 010 7H6"/></svg></div>
        <div class="stat-card__label">Total Revenue</div>
        <div class="stat-card__value" style="font-size:1.5rem">&#8369;<?php echo number_format($total_revenue,0); ?></div>
      </div>
    </div>

    <div data-dash-panel="resorts" style="display:block">
      <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:18px;flex-wrap:wrap;gap:10px">
        <h3 style="margin:0">My Resorts</h3>
        <a href="add_resort.php" class="btn btn--coral">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
          Add New Resort
        </a>
      </div>

      <?php if (count($resorts)): ?>
        <?php foreach ($resorts as $r): ?>
          <div class="owner-resort-card">
            <?php if ($r['image_path'] && file_exists('../' . $r['image_path'])): ?>
              <img class="owner-resort-card__image" src="<?php echo htmlspecialchars('../' . $r['image_path']); ?>" alt="<?php echo htmlspecialchars($r['name']); ?>">
            <?php else: ?>
              <div class="owner-resort-card__image-placeholder">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1"><path d="M3 9l9-7 9 7v11a2 2 0 01-2 2H5a2 2 0 01-2-2z"/></svg>
              </div>
            <?php endif; ?>
            <div class="owner-resort-card__header">
              <div>
                <div class="owner-resort-card__name"><?php echo htmlspecialchars($r['name']); ?></div>
                <div class="owner-resort-card__loc">
                  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0118 0z"/><circle cx="12" cy="10" r="3"/></svg>
                  <?php echo htmlspecialchars($r['location_city']); ?>
                  &nbsp;&middot;&nbsp; Max <?php echo $r['max_guests']; ?> guests
                </div>
              </div>
              <div>
                <div class="owner-resort-card__price">&#8369;<?php echo number_format($r['price_per_night'],2); ?><small> / night</small></div>
                <div style="text-align:right;margin-top:5px">
                  <span class="badge <?php echo $r['is_available']?'badge--available':'badge--cancelled'; ?>">
                    <?php echo $r['is_available']?'Available':'Unavailable'; ?>
                  </span>
                </div>
              </div>
            </div>
            <div class="owner-resort-card__body">
              <p class="owner-resort-card__desc"><?php echo htmlspecialchars(substr($r['description'],0,180)); ?>…</p>
              <div class="owner-resort-card__actions">
                <a href="edit_resort.php?id=<?php echo $r['resort_id']; ?>" class="btn btn--ghost btn--sm">
                  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 00-2 2v14a2 2 0 002 2h14a2 2 0 002-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 013 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                  Edit
                </a>
                <a href="manage_availability.php?resort_id=<?php echo $r['resort_id']; ?>" class="btn btn--ghost btn--sm">
                  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
                  Block Dates
                </a>
                <a href="owner_bookings.php?resort_id=<?php echo $r['resort_id']; ?>" class="btn btn--primary btn--sm">
                  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
                  Bookings
                </a>
                <a href="delete_resort.php?id=<?php echo $r['resort_id']; ?>" class="btn btn--danger btn--sm"
                   data-confirm="Delete this resort? All bookings and reviews will also be removed.">
                  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14H6L5 6"/><path d="M10 11v6M14 11v6"/><path d="M9 6V4h6v2"/></svg>
                  Delete
                </a>
              </div>
            </div>
          </div>
        <?php endforeach; ?>

      <?php else: ?>
        <div class="card">
          <div class="card__body">
            <div class="empty-state" style="padding:48px 0">
              <svg class="empty-state__icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.25"><path d="M3 9l9-7 9 7v11a2 2 0 01-2 2H5a2 2 0 01-2-2z"/></svg>
              <h3 class="empty-state__title">No resorts yet</h3>
              <p class="empty-state__text">List your first property and start accepting bookings today.</p>
              <a href="add_resort.php" class="btn btn--coral">Add your first resort</a>
            </div>
          </div>
        </div>
      <?php endif; ?>
    </div>
  </main>
</div>

<?php include '_foot.php'; ?>
