<?php
// pages/owner_bookings.php — Enhanced with Confirm/Reject
require_once '../config.php';

if (!isset($_SESSION['user_id']))        { header("Location: login.php"); exit(); }
if ($_SESSION['user_role'] !== 'owner') { header("Location: customer_dashboard.php"); exit(); }

$resorts = [];
try {
    $stmt = $pdo->prepare("SELECT resort_id, name FROM resorts WHERE owner_id=? ORDER BY name");
    $stmt->execute([$_SESSION['user_id']]);
    $resorts = $stmt->fetchAll();
} catch (PDOException $e) { error_log($e->getMessage()); }

$selected_resort = isset($_GET['resort_id']) ? intval($_GET['resort_id']) : 0;
$bookings        = [];
$resort_name     = '';
$status_msg      = '';
$status_err      = '';

// ── Handle status update ────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
    $bid    = intval($_POST['booking_id']);
    $action = $_POST['action_type'] ?? '';

    try {
        $check = $pdo->prepare("SELECT b.*, r.name as resort_name, u.user_id as cust_id FROM bookings b JOIN resorts r ON b.resort_id=r.resort_id JOIN users u ON b.customer_id=u.user_id JOIN booking_status bs ON b.status_id=bs.status_id WHERE b.booking_id=? AND r.owner_id=?");
        $check->execute([$bid, $_SESSION['user_id']]);
        $bk = $check->fetch();

        if ($bk && $action === 'complete') {
            $pdo->prepare("UPDATE bookings SET status_id=4 WHERE booking_id=?")->execute([$bid]);
            $status_msg = "Booking marked as completed.";
        }
    } catch (PDOException $e) { error_log($e->getMessage()); $status_err = "Failed to update."; }

    header("Location: owner_bookings.php?resort_id=$selected_resort");
    exit();
}

if ($selected_resort > 0) {
    try {
        $check = $pdo->prepare("SELECT name FROM resorts WHERE resort_id=? AND owner_id=?");
        $check->execute([$selected_resort, $_SESSION['user_id']]);
        $rd = $check->fetch();
        if ($rd) {
            $resort_name = $rd['name'];
            $stmt = $pdo->prepare("SELECT b.*, u.full_name as customer_name, u.email, u.phone, bs.status_name FROM bookings b JOIN users u ON b.customer_id=u.user_id JOIN booking_status bs ON b.status_id=bs.status_id WHERE b.resort_id=? ORDER BY FIELD(b.status_id,1,2,6,3,4,5), b.check_in_date ASC");
            $stmt->execute([$selected_resort]);
            $bookings = $stmt->fetchAll();
        }
    } catch (PDOException $e) { error_log($e->getMessage()); }
}

// ── Payment status helper for owner view ────────────────────
function getOwnerPaymentStatus(PDO $pdo, int $booking_id, float $total): array {
    try {
        $s = $pdo->prepare("SELECT * FROM payments WHERE booking_id=? AND payment_status='completed' ORDER BY created_at ASC");
        $s->execute([$booking_id]);
        $payments   = $s->fetchAll();
        $total_paid = array_sum(array_column($payments, 'amount'));
        $has_down   = false;
        $is_full    = false;
        $remaining  = 0;
        foreach ($payments as $p) {
            $pt = $p['payment_type'] ?? 'full_payment';
            if ($pt === 'reservation_fee') { $has_down = true; $remaining = (float)($p['remaining_balance'] ?? 0); }
            if (in_array($pt, ['full_payment','balance'])) $is_full = true;
        }
        if ($total_paid >= $total && $total_paid > 0) $is_full = true;

        if ($is_full)  return ['label' => 'Fully Paid',    'class' => 'pay-status--full',    'paid' => $total_paid, 'remaining' => 0];
        if ($has_down) return ['label' => 'Down Payment',  'class' => 'pay-status--partial', 'paid' => $total_paid, 'remaining' => $remaining];
        return         ['label' => 'Not Paid',             'class' => 'pay-status--none',    'paid' => 0,           'remaining' => $total];
    } catch (PDOException $e) {
        return ['label' => 'Unknown', 'class' => 'pay-status--none', 'paid' => 0, 'remaining' => $total];
    }
}

// Count pending across all resorts for badge
$totalPending = 0;
if ($resorts) {
    $ids = implode(',', array_column($resorts, 'resort_id'));
    try { $totalPending = (int)$pdo->query("SELECT COUNT(*) FROM bookings WHERE resort_id IN ($ids) AND status_id=1")->fetchColumn(); } catch(PDOException $e){}
}

$pageTitle = 'Manage Bookings';
$activePage = 'bookings';
include '_head.php';
?>

<div class="dash-layout">
  <?php include '_owner_sidebar.php'; ?>

  <main class="dash-main">
    <div class="dash-header">
      <p class="dash-header__greeting">Owner Portal</p>
      <h2>Manage Bookings</h2>
    </div>

    <?php if ($status_msg): ?><div class="alert alert--success" data-auto-dismiss="4000"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"/></svg><?php echo htmlspecialchars($status_msg); ?></div><?php endif; ?>
    <?php if ($status_err): ?><div class="alert alert--error"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/></svg><?php echo htmlspecialchars($status_err); ?></div><?php endif; ?>

    <div class="card mb-24">
      <div class="card__body">
        <form method="GET" action="" style="display:flex;gap:12px;align-items:flex-end;flex-wrap:wrap">
          <div class="form-group" style="flex:1;min-width:220px;margin:0">
            <label class="form-label">Select Resort</label>
            <select class="form-control form-control--select" name="resort_id" required>
              <option value="">— Choose a resort —</option>
              <?php foreach ($resorts as $r): ?>
                <?php
                $pendCnt = 0;
                try { $pendCnt = (int)$pdo->query("SELECT COUNT(*) FROM bookings WHERE resort_id={$r['resort_id']} AND status_id=1")->fetchColumn(); } catch(PDOException $e){}
                ?>
                <option value="<?php echo $r['resort_id']; ?>" <?php echo $selected_resort==$r['resort_id']?'selected':''; ?>>
                  <?php echo htmlspecialchars($r['name']); ?><?php echo $pendCnt?" ({$pendCnt} pending)":''; ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>
          <button type="submit" class="btn btn--primary" style="height:42px">View Bookings</button>
        </form>
      </div>
    </div>

    <?php if ($selected_resort > 0 && $resort_name): ?>
      <h3 style="margin-bottom:18px"><?php echo htmlspecialchars($resort_name); ?></h3>

      <?php if (count($bookings)): ?>
        <?php foreach ($bookings as $b): ?>
          <?php
          $nights     = (new DateTime($b['check_in_date']))->diff(new DateTime($b['check_out_date']))->days;
          $pay_info   = getOwnerPaymentStatus($pdo, $b['booking_id'], (float)$b['total_price']);
          ?>
          <div class="booking-card <?php echo $b['status_name']==='pending'?'booking-card--highlight':''; ?>">
            <div style="flex:1">
              <div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap;margin-bottom:8px">
                <div class="booking-card__resort"><?php echo htmlspecialchars($b['customer_name']); ?></div>
                <span class="badge badge--<?php echo $b['status_name']; ?>"><?php echo ucfirst($b['status_name']); ?></span>
                <!-- Payment status badge -->
                <span class="pay-status-badge <?php echo $pay_info['class']; ?>">
                  <?php if ($pay_info['class'] === 'pay-status--full'): ?>
                    <svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>
                  <?php elseif ($pay_info['class'] === 'pay-status--partial'): ?>
                    <svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                  <?php else: ?>
                    <svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/></svg>
                  <?php endif; ?>
                  <?php echo $pay_info['label']; ?>
                </span>
              </div>

              <!-- Payment amount detail -->
              <div style="font-size:.76rem;color:var(--text-muted);margin-bottom:8px;display:flex;gap:14px;flex-wrap:wrap">
                <?php if ($pay_info['paid'] > 0): ?>
                  <span>Paid: <strong style="color:var(--sage)">&#8369;<?php echo number_format($pay_info['paid'],2); ?></strong></span>
                <?php endif; ?>
                <?php if ($pay_info['class'] === 'pay-status--partial' && $pay_info['remaining'] > 0): ?>
                  <span>Balance due on arrival: <strong style="color:var(--coral)">&#8369;<?php echo number_format($pay_info['remaining'],2); ?></strong></span>
                <?php elseif ($pay_info['class'] === 'pay-status--none'): ?>
                  <span style="color:var(--coral)">No payment received yet</span>
                <?php endif; ?>
              </div>
              <div class="booking-card__meta">
                <span class="booking-card__meta-item">
                  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:12px;height:12px"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg>
                  <?php echo htmlspecialchars($b['email']); ?>
                </span>
                <?php if ($b['phone']): ?>
                  <span class="booking-card__meta-item"><?php echo htmlspecialchars($b['phone']); ?></span>
                <?php endif; ?>
                <span class="booking-card__meta-item">
                  <?php echo date('M d',strtotime($b['check_in_date'])); ?> → <?php echo date('M d, Y',strtotime($b['check_out_date'])); ?>
                  &middot; <?php echo $nights; ?> night<?php echo $nights!==1?'s':''; ?>
                </span>
                <span class="booking-card__meta-item">
                  Arrival: <?php echo date('g:i A', strtotime($b['arrival_time'])); ?>
                  &nbsp;|&nbsp;
                  Departure: <?php echo date('g:i A', strtotime($b['departure_time'])); ?>
                </span>
                <span class="booking-card__meta-item"><?php echo $b['guest_count']; ?> guest<?php echo $b['guest_count']>1?'s':''; ?></span>
              </div>
              <?php if ($b['special_requests']): ?>
                <div style="font-size:.76rem;color:var(--text-muted);margin:8px 0;padding:7px 11px;background:var(--sand);border-radius:var(--radius-sm)">
                  <strong>Requests:</strong> <?php echo htmlspecialchars($b['special_requests']); ?>
                </div>
              <?php endif; ?>

              <!-- Action buttons — payment monitoring -->
              <div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap;margin-top:10px">
                <?php if ($b['status_name'] === 'pending'): ?>
                  <span style="font-size:.75rem;color:var(--text-muted);font-style:italic">
                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                    Awaiting customer payment
                  </span>

                <?php elseif ($b['status_name'] === 'confirmed'): ?>
                  <span style="font-size:.75rem;color:#e65100;font-style:italic">Down payment received — balance due on arrival</span>

                <?php elseif ($b['status_name'] === 'paid'): ?>
                  <span style="font-size:.75rem;color:#2e7d32;font-weight:600">&#x2713; Fully paid</span>
                  <form method="POST" action="?resort_id=<?php echo $selected_resort; ?>" style="display:inline">
                    <input type="hidden" name="booking_id"    value="<?php echo $b['booking_id']; ?>">
                    <input type="hidden" name="update_status" value="1">
                    <input type="hidden" name="action_type"   value="complete">
                    <button type="submit" class="btn btn--primary btn--sm" data-confirm="Mark this booking as completed?">
                      Mark Completed
                    </button>
                  </form>
                <?php endif; ?>
              </div>
            </div>
            <div class="booking-card__right">
              <div class="booking-card__price">&#8369;<?php echo number_format($b['total_price'],2); ?></div>
              <div style="font-size:.72rem;color:var(--text-muted)">#<?php echo $b['booking_id']; ?></div>
            </div>
          </div>
        <?php endforeach; ?>

      <?php else: ?>
        <div class="card"><div class="card__body">
          <div class="empty-state" style="padding:44px 0">
            <svg class="empty-state__icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.25"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
            <h3 class="empty-state__title">No bookings yet</h3>
            <p class="empty-state__text">This resort hasn't received any bookings.</p>
          </div>
        </div></div>
      <?php endif; ?>
    <?php endif; ?>
  </main>
</div>

<?php include '_foot.php'; ?>
