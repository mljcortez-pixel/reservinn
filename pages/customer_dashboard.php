<?php
// pages/customer_dashboard.php
require_once '../config.php';

if (!isset($_SESSION['user_id']))          { header("Location: login.php"); exit(); }
if ($_SESSION['user_role'] !== 'customer') { header("Location: owner_dashboard.php"); exit(); }

$recent_bookings = [];
try {
    $stmt = $pdo->prepare("SELECT b.*, r.name as resort_name, r.location_city, r.image_path, bs.status_name FROM bookings b JOIN resorts r ON b.resort_id=r.resort_id JOIN booking_status bs ON b.status_id=bs.status_id WHERE b.customer_id=:cid ORDER BY b.created_at DESC LIMIT 5");
    $stmt->execute([':cid' => $_SESSION['user_id']]);
    $recent_bookings = $stmt->fetchAll();
} catch (PDOException $e) { error_log($e->getMessage()); }

$total_spent = array_reduce($recent_bookings, fn($c,$b) => $c + ($b['status_name']!=='cancelled'?$b['total_price']:0), 0);

$pageTitle = 'Dashboard';
include '_head.php';
?>

<main class="section section--sm">
  <div class="container">

    <!-- Welcome Hero -->
    <div style="background:linear-gradient(135deg,var(--rose) 0%,var(--pink-accent) 60%,#e8829a 100%);border-radius:var(--radius-xl);padding:44px 48px;margin-bottom:28px;position:relative;overflow:hidden">
      <!-- decorative circles -->
      <div style="position:absolute;top:-50px;right:-50px;width:220px;height:220px;border-radius:50%;background:rgba(255,255,255,.08);pointer-events:none"></div>
      <div style="position:absolute;bottom:-40px;right:160px;width:140px;height:140px;border-radius:50%;background:rgba(255,255,255,.06);pointer-events:none"></div>
      <!-- content -->
      <p style="font-family:var(--font-display);font-style:italic;font-size:.9rem;color:rgba(255,255,255,.8);margin-bottom:6px;letter-spacing:.01em">Good to see you,</p>
      <h2 style="font-family:var(--font-display);font-size:clamp(1.6rem,4vw,2.4rem);color:#fff;margin-bottom:14px;line-height:1.2;font-weight:600"><?php echo htmlspecialchars($_SESSION['user_name']); ?></h2>
      <p style="color:rgba(255,255,255,.88);font-size:.875rem;margin-bottom:28px;max-width:440px;line-height:1.65">Explore hand-picked private resorts across the Philippines and create your next unforgettable experience.</p>
      <div style="display:flex;gap:10px;flex-wrap:wrap">
        <a href="browse_resorts.php" style="display:inline-flex;align-items:center;gap:6px;background:#fff;color:var(--rose);border:none;padding:11px 22px;border-radius:var(--radius-md);font-size:.85rem;font-weight:600;cursor:pointer;text-decoration:none;transition:all .18s ease;box-shadow:0 2px 8px rgba(0,0,0,.1)" onmouseover="this.style.background='var(--pink)'" onmouseout="this.style.background='#fff'">
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
          Browse Resorts
        </a>
        <a href="my_bookings.php" style="display:inline-flex;align-items:center;gap:6px;background:rgba(255,255,255,.18);color:#fff;border:1.5px solid rgba(255,255,255,.5);padding:11px 22px;border-radius:var(--radius-md);font-size:.85rem;font-weight:600;cursor:pointer;text-decoration:none;transition:all .18s ease;backdrop-filter:blur(4px)" onmouseover="this.style.background='rgba(255,255,255,.28)'" onmouseout="this.style.background='rgba(255,255,255,.18)'">
          My Bookings
        </a>
      </div>
    </div>

    <!-- Stats -->
    <div class="stats-grid" style="grid-template-columns:repeat(auto-fit,minmax(170px,1fr));margin-bottom:28px">
      <div class="stat-card">
        <div class="stat-card__icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg></div>
        <div class="stat-card__label">Total Bookings</div>
        <div class="stat-card__value"><?php echo count($recent_bookings); ?></div>
      </div>
      <div class="stat-card">
        <div class="stat-card__icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 000 7h5a3.5 3.5 0 010 7H6"/></svg></div>
        <div class="stat-card__label">Total Spent</div>
        <div class="stat-card__value" style="font-size:1.5rem">&#8369;<?php echo number_format($total_spent,0); ?></div>
      </div>
    </div>

    <!-- Recent Bookings -->
    <div class="card">
      <div class="card__body">
        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:20px;flex-wrap:wrap;gap:8px">
          <h3 style="margin:0">Recent Bookings</h3>
          <a href="my_bookings.php" class="btn btn--ghost btn--sm">View all</a>
        </div>

        <?php if (count($recent_bookings)): ?>
          <?php foreach ($recent_bookings as $b): ?>
            <div style="display:flex;justify-content:space-between;align-items:center;padding:13px 0;border-bottom:1px solid var(--border-light);flex-wrap:wrap;gap:10px">
              <div>
                <div style="font-family:var(--font-display);font-weight:600;font-size:1rem;color:var(--text);margin-bottom:3px"><?php echo htmlspecialchars($b['resort_name']); ?></div>
                <div style="font-size:.76rem;color:var(--text-muted);display:flex;align-items:center;gap:4px">
                  <svg style="width:11px;height:11px" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
                  <?php echo date('M d, Y',strtotime($b['check_in_date'])); ?> → <?php echo date('M d, Y',strtotime($b['check_out_date'])); ?>
                </div>
              </div>
              <div style="display:flex;align-items:center;gap:12px">
                <span class="badge badge--<?php echo $b['status_name']; ?>"><?php echo ucfirst($b['status_name']); ?></span>
                <span style="font-family:var(--font-display);font-size:1.05rem;font-weight:600;color:var(--text)">&#8369;<?php echo number_format($b['total_price'],2); ?></span>
              </div>
            </div>
          <?php endforeach; ?>
        <?php else: ?>
          <div class="empty-state" style="padding:36px 0">
            <svg class="empty-state__icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.25"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
            <p class="empty-state__text">No bookings yet. <a href="browse_resorts.php" style="color:var(--rose);font-weight:600">Start exploring</a></p>
          </div>
        <?php endif; ?>
      </div>
    </div>

  </div>
</main>

<?php include '_foot.php'; ?>
