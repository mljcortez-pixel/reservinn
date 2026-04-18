<?php
// pages/my_bookings.php
require_once '../config.php';

if (!isset($_SESSION['user_id']))          { header("Location: login.php"); exit(); }
if ($_SESSION['user_role'] !== 'customer') { header("Location: owner_dashboard.php"); exit(); }

$bookings = [];
try {
    $stmt = $pdo->prepare("SELECT b.*, r.name as resort_name, r.location_city, r.price_per_night, bs.status_name FROM bookings b JOIN resorts r ON b.resort_id=r.resort_id JOIN booking_status bs ON b.status_id=bs.status_id WHERE b.customer_id=:cid ORDER BY b.created_at DESC");
    $stmt->execute([':cid' => $_SESSION['user_id']]);
    $bookings = $stmt->fetchAll();
} catch (PDOException $e) { error_log($e->getMessage()); }

// Load payment info per booking
function getPaymentStatus(PDO $pdo, int $booking_id, float $total_price): array {
    try {
        $stmt = $pdo->prepare("SELECT * FROM payments WHERE booking_id = ? AND payment_status = 'completed' ORDER BY created_at ASC");
        $stmt->execute([$booking_id]);
        $payments = $stmt->fetchAll();
        $total_paid  = array_sum(array_column($payments, 'amount'));
        $has_res     = false;
        $is_full     = false;
        $remaining   = 0;
        foreach ($payments as $p) {
            $pt = $p['payment_type'] ?? '';
            if ($pt === 'reservation_fee') { $has_res = true; $remaining = (float)($p['remaining_balance'] ?? 0); }
            if (in_array($pt, ['full_payment','balance'])) $is_full = true;
        }
        if ($total_paid >= $total_price && $total_paid > 0) $is_full = true;
        if ($is_full)    return ['label'=>'Fully Paid',              'class'=>'pay-status--full',    'remaining'=>0];
        if ($has_res)    return ['label'=>'Reservation Fee Paid',    'class'=>'pay-status--partial', 'remaining'=>$remaining];
        return           ['label'=>'Not Paid',                       'class'=>'pay-status--none',    'remaining'=>$total_price];
    } catch (PDOException $e) { return ['label'=>'Unknown','class'=>'pay-status--none','remaining'=>0]; }
}

$total_spent = array_reduce($bookings, fn($c,$b) => $c + ($b['status_name']!=='cancelled'?$b['total_price']:0), 0);

function reviewExists($pdo, $booking_id) {
    try {
        $stmt = $pdo->prepare("SELECT review_id FROM reviews WHERE booking_id = ?");
        $stmt->execute([$booking_id]);
        return (bool)$stmt->fetch();
    } catch (PDOException $e) { return false; }
}

if (isset($_SESSION['success'])) { $flash_success = $_SESSION['success']; unset($_SESSION['success']); }
if (isset($_SESSION['error']))   { $flash_error   = $_SESSION['error'];   unset($_SESSION['error']);   }

$pageTitle = 'My Bookings';
include '_head.php';
?>

<main class="section section--sm">
  <div class="container">

    <div class="section-header">
      <div>
        <span class="section-eyebrow">Your reservations</span>
        <h1>My Bookings</h1>
      </div>
      <a href="browse_resorts.php" class="btn btn--coral">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
        Browse Resorts
      </a>
    </div>

    <?php if (isset($flash_success)): ?><div class="alert alert--success" data-auto-dismiss="4000"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"/></svg><?php echo htmlspecialchars($flash_success); ?></div><?php endif; ?>
    <?php if (isset($flash_error)):   ?><div class="alert alert--error"   data-auto-dismiss="5000"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/></svg><?php echo htmlspecialchars($flash_error); ?></div><?php endif; ?>

    <div class="stats-grid" style="grid-template-columns:repeat(auto-fit,minmax(160px,1fr));margin-bottom:28px">
      <div class="stat-card">
        <div class="stat-card__icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="3" y1="10" x2="21" y2="10"/></svg></div>
        <div class="stat-card__label">Total Bookings</div>
        <div class="stat-card__value"><?php echo count($bookings); ?></div>
      </div>
      <div class="stat-card">
        <div class="stat-card__icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 000 7h5a3.5 3.5 0 010 7H6"/></svg></div>
        <div class="stat-card__label">Total Spent</div>
        <div class="stat-card__value" style="font-size:1.4rem">&#8369;<?php echo number_format($total_spent,0); ?></div>
      </div>
    </div>

    <?php if (count($bookings)): ?>
      <?php foreach ($bookings as $b): ?>
        <?php
        $nights     = (new DateTime($b['check_in_date']))->diff(new DateTime($b['check_out_date']))->days;
        $pay_status = getPaymentStatus($pdo, $b['booking_id'], (float)$b['total_price']);
        ?>
        <div class="booking-card">
          <div>
            <div class="booking-card__resort"><?php echo htmlspecialchars($b['resort_name']); ?></div>
            <div class="booking-card__meta">
              <span class="booking-card__meta-item">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0118 0z"/><circle cx="12" cy="10" r="3"/></svg>
                <?php echo htmlspecialchars($b['location_city']); ?>
              </span>
              <span class="booking-card__meta-item">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
                <?php echo date('M d',strtotime($b['check_in_date'])); ?> → <?php echo date('M d, Y',strtotime($b['check_out_date'])); ?>
              </span>
              <span class="booking-card__meta-item">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/><circle cx="9" cy="7" r="4"/></svg>
                <?php echo $b['guest_count']; ?> guest<?php echo $b['guest_count']>1?'s':''; ?>
              </span>
              <span class="booking-card__meta-item"><?php echo $nights; ?> night<?php echo $nights!==1?'s':''; ?></span>
            </div>

            <!-- Payment status pill -->
            <div style="margin-bottom:10px">
              <span class="pay-status-badge <?php echo $pay_status['class']; ?>">
                <?php if ($pay_status['class'] === 'pay-status--full'): ?>
                  <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>
                <?php elseif ($pay_status['class'] === 'pay-status--partial'): ?>
                  <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                <?php else: ?>
                  <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/></svg>
                <?php endif; ?>
                <?php echo $pay_status['label']; ?>
              </span>
              <?php if ($pay_status['class'] === 'pay-status--partial' && $pay_status['remaining'] > 0): ?>
                <span style="font-size:.72rem;color:var(--text-muted);margin-left:6px">
                  &#8369;<?php echo number_format($pay_status['remaining'],2); ?> remaining on arrival
                </span>
              <?php endif; ?>
            </div>

            <div class="booking-card__actions">
              <span class="badge badge--<?php echo $b['status_name']; ?>"><?php echo ucfirst($b['status_name']); ?></span>

              <?php if ($b['status_name'] === 'pending'): ?>
                <!-- No payment yet — can pay or cancel -->
                <?php if ($pay_status['class'] !== 'pay-status--full'): ?>
                  <a href="payment.php?booking_id=<?php echo $b['booking_id']; ?>"
                     class="btn btn--primary btn--sm">
                    Pay Now
                  </a>
                <?php endif; ?>
                <a href="cancel_booking.php?id=<?php echo $b['booking_id']; ?>"
                   class="btn btn--ghost btn--sm"
                   data-confirm="Cancel this booking?">Cancel</a>

              <?php elseif ($b['status_name'] === 'confirmed'): ?>
                <!-- Down payment paid — pay remaining balance -->
                <?php if ($pay_status['class'] !== 'pay-status--full'): ?>
                  <a href="payment.php?booking_id=<?php echo $b['booking_id']; ?>"
                     class="btn btn--primary btn--sm">
                    Pay Remaining Balance
                  </a>
                <?php endif; ?>
              <?php elseif ($b['status_name'] === 'paid'): ?>
                <!-- Fully paid — nothing more to do until completed -->
                <span style="font-size:.75rem;color:#155724;font-weight:600">✓ Payment complete. Enjoy your stay!</span>

              <?php elseif ($b['status_name'] === 'completed'): ?>
                <!-- Stay done — offer review -->
                <?php if (reviewExists($pdo,$b['booking_id'])): ?>
                  <span class="btn btn--ghost btn--sm" style="cursor:default;opacity:.6">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"/></svg>
                    Reviewed
                  </span>
                <?php else: ?>
                  <a href="add_review.php?booking_id=<?php echo $b['booking_id']; ?>" class="btn btn--sage btn--sm">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg>
                    Write Review
                  </a>
                <?php endif; ?>
              <?php endif; ?>
            </div>
          </div>
          <div class="booking-card__right">
            <div class="booking-card__price">&#8369;<?php echo number_format($b['total_price'],2); ?></div>
            <div style="font-size:.72rem;color:var(--text-muted)">Booking #<?php echo $b['booking_id']; ?></div>
          </div>
        </div>
      <?php endforeach; ?>
    <?php else: ?>
      <div class="card"><div class="card__body">
        <div class="empty-state">
          <svg class="empty-state__icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.25"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
          <h3 class="empty-state__title">No bookings yet</h3>
          <p class="empty-state__text">Start exploring private resorts and create your first reservation.</p>
          <a href="browse_resorts.php" class="btn btn--primary">Browse resorts</a>
        </div>
      </div></div>
    <?php endif; ?>

  </div>
</main>

<?php include '_foot.php'; ?>
