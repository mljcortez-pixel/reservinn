<?php
// pages/payment.php — Fixed payment logic: auto-confirm on payment
require_once '../config.php';
if (!isset($_SESSION['user_id']))          { header("Location: login.php"); exit(); }
if ($_SESSION['user_role'] !== 'customer') { header("Location: customer_dashboard.php"); exit(); }

$booking_id = isset($_GET['booking_id']) ? intval($_GET['booking_id']) : 0;
$is_new     = isset($_GET['new']); // came directly from booking
$booking = null; $error = '';

$fee_pct   = RESERVATION_FEE_PERCENT / 100;
$fee_label = RESERVATION_FEE_PERCENT . '%';

if ($booking_id <= 0) {
    $error = "Invalid booking reference.";
} else {
    try {
        $stmt = $pdo->prepare("SELECT b.*, r.name as resort_name, r.location_city, r.price_per_night, r.owner_id, bs.status_name
            FROM bookings b JOIN resorts r ON b.resort_id=r.resort_id JOIN booking_status bs ON b.status_id=bs.status_id
            WHERE b.booking_id=:bid AND b.customer_id=:cid");
        $stmt->execute([':bid'=>$booking_id,':cid'=>$_SESSION['user_id']]);
        $booking = $stmt->fetch();
        if (!$booking) $error = "Booking not found.";
        elseif (in_array($booking['status_name'],['cancelled','rejected'])) $error = "This booking has been ".$booking['status_name'].". No payment required.";
        elseif ($booking['status_name'] === 'completed') $error = "This booking has been completed.";
    } catch (PDOException $e) { error_log($e->getMessage()); $error = "Unable to load booking."; }
}

// Load existing payments
$all_payments = []; $total_paid = 0; $has_down = false; $is_fully_paid = false;
if ($booking && !$error) {
    try {
        $cp = $pdo->prepare("SELECT * FROM payments WHERE booking_id=? AND payment_status='completed' ORDER BY created_at ASC");
        $cp->execute([$booking_id]);
        $all_payments = $cp->fetchAll();
        $total_paid   = array_sum(array_column($all_payments,'amount'));
        foreach ($all_payments as $p) {
            $pt = $p['payment_type'] ?? 'full_payment';
            if ($pt === 'reservation_fee') $has_down = true;
            if (in_array($pt,['full_payment','balance'])) $is_fully_paid = true;
        }
        if ($total_paid >= (float)$booking['total_price'] && $total_paid > 0) $is_fully_paid = true;
        if ($is_fully_paid) $error = "This booking has already been fully paid.";
    } catch (PDOException $e) { error_log($e->getMessage()); }
}

$total       = $booking ? (float)$booking['total_price'] : 0;
$down_amount = round($total * $fee_pct, 2);
$remaining   = round($total - $down_amount, 2);
$balance_due = max(0, round($total - $total_paid, 2));

// Handle POST
$payment_success = false; $success_message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$error && $booking) {
    $method       = $_POST['payment_method']           ?? '';
    $reference    = trim($_POST['transaction_reference'] ?? '');
    $payment_type = $_POST['payment_type']             ?? 'full_payment';
    $perrs = [];

    if (empty($method))                    $perrs[] = "Please select a payment method.";
    if (strlen($reference) < 3)            $perrs[] = "Transaction reference must be at least 3 characters.";
    if (!in_array($payment_type,['reservation_fee','full_payment','balance'])) $perrs[] = "Invalid payment option.";
    if ($payment_type === 'reservation_fee' && $has_down) $perrs[] = "Down payment already made.";
    if ($payment_type === 'balance' && !$has_down) $perrs[] = "Please pay the down payment first.";

    if (empty($perrs)) {
        try {
            $cr = $pdo->prepare("SELECT payment_id FROM payments WHERE transaction_reference=?");
            $cr->execute([$reference]);
            if ($cr->rowCount() > 0) $perrs[] = "This reference number has already been used.";
        } catch (PDOException $e) { $perrs[] = "Validation error."; }
    }

    if (empty($perrs)) {
        try {
            $pdo->beginTransaction();

            if ($payment_type === 'reservation_fee') {
                $pay_amount = $down_amount; $rem_stored = $remaining;
            } elseif ($payment_type === 'balance') {
                $pay_amount = $balance_due; $rem_stored = 0;
            } else {
                $pay_amount = $total; $rem_stored = 0;
            }

            $pdo->prepare("INSERT INTO payments (booking_id,amount,payment_method,transaction_reference,payment_status,payment_type,remaining_balance,paid_on_arrival,paid_at) VALUES (?,?,?,?,'completed',?,?,0,NOW())")
                ->execute([$booking_id,$pay_amount,$method,$reference,$payment_type,$rem_stored]);

            // Auto-confirm/paid based on payment type
            if ($payment_type === 'full_payment' || $payment_type === 'balance') {
                // Fully paid → status = paid (6)
                $pdo->prepare("UPDATE bookings SET status_id=6 WHERE booking_id=?")->execute([$booking_id]);
            } else {
                // Down payment → status = confirmed (2)
                $pdo->prepare("UPDATE bookings SET status_id=2 WHERE booking_id=?")->execute([$booking_id]);
            }

            $pdo->commit();
            $payment_success = true;
            $resortName = $booking['resort_name'];
            $ownerId    = (int)$booking['owner_id'];
            $custId     = (int)$_SESSION['user_id'];
            $custName   = $_SESSION['user_name'] ?? 'Customer';

            if ($payment_type === 'reservation_fee') {
                $success_message = "Down payment of <strong>&#8369;".number_format($pay_amount,2)."</strong> received! Remaining balance of <strong>&#8369;".number_format($rem_stored,2)."</strong> is due on arrival.";
                createNotification($pdo,$custId,'booking_confirmed','Down Payment Confirmed',"Your down payment of &#8369;".number_format($pay_amount,2)." for {$resortName} has been received. Your booking is now confirmed!",'my_bookings.php');
                createNotification($pdo,$ownerId,'booking_new','Down Payment Received',"{$custName} paid a down payment of &#8369;".number_format($pay_amount,2)." for booking #{$booking_id} at {$resortName}.",'owner_bookings.php?resort_id='.$booking['resort_id']);
            } elseif ($payment_type === 'balance') {
                $success_message = "Balance payment of <strong>&#8369;".number_format($pay_amount,2)."</strong> confirmed! Booking is now fully paid.";
                createNotification($pdo,$custId,'booking_confirmed','Booking Fully Paid',"Your balance payment for {$resortName} has been received. Booking is fully paid!",'my_bookings.php');
                createNotification($pdo,$ownerId,'booking_new','Balance Received — Fully Paid',"{$custName} completed balance payment for booking #{$booking_id} at {$resortName}.",'owner_bookings.php?resort_id='.$booking['resort_id']);
            } else {
                $success_message = "Full payment of <strong>&#8369;".number_format($pay_amount,2)."</strong> confirmed! Your booking is active.";
                createNotification($pdo,$custId,'booking_confirmed','Booking Confirmed — Fully Paid',"Your full payment for {$resortName} has been received. Your booking is confirmed and active!",'my_bookings.php');
                createNotification($pdo,$ownerId,'booking_new','Full Payment Received',"{$custName} made full payment of &#8369;".number_format($pay_amount,2)." for booking #{$booking_id} at {$resortName}.",'owner_bookings.php?resort_id='.$booking['resort_id']);
            }
            header("refresh:4;url=my_bookings.php");
        } catch (PDOException $e) {
            $pdo->rollBack(); error_log($e->getMessage()); $perrs[] = "Payment failed. Please try again.";
        }
    }
    if (!empty($perrs)) $error = implode(' ', $perrs);
}

$nights = $booking ? (new DateTime($booking['check_in_date']))->diff(new DateTime($booking['check_out_date']))->days : 0;
$pageTitle = 'Complete Payment';
include '_head.php';

$methods = [
    ['gcash','GCash','<path d="M12 22C6.48 22 2 17.52 2 12S6.48 2 12 2s10 4.48 10 10-4.48 10-10 10zm0-18C7.59 4 4 7.59 4 12s3.59 8 8 8 8-3.59 8-8-3.59-8-8-8z"/><path d="M13 7h-2v4H7v2h4v4h2v-4h4v-2h-4z"/>'],
    ['bank_transfer','Bank Transfer','<rect x="2" y="5" width="20" height="14" rx="2"/><line x1="2" y1="10" x2="22" y2="10"/>'],
    ['cash','Cash','<line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 000 7h5a3.5 3.5 0 010 7H6"/>'],
    ['credit_card','Credit Card','<rect x="1" y="4" width="22" height="16" rx="2"/><line x1="1" y1="10" x2="23" y2="10"/>'],
    ['paypal','PayPal','<path d="M17.5 7H17a5 5 0 00-5 5 4 4 0 004 4h.5a3.5 3.5 0 000-7z"/><path d="M7 7h1a5 5 0 015 5v1a4 4 0 01-4 4H7V7z"/>'],
    ['maya','Maya','<circle cx="12" cy="12" r="10"/><path d="M8 12l4-4 4 4"/>'],
];
?>
<main style="padding: calc(var(--nav-h) + 16px) 0 80px">
  <div class="container">
    <a href="my_bookings.php" class="btn btn--ghost btn--sm mb-24" style="display:inline-flex">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="15 18 9 12 15 6"/></svg>
      Back to bookings
    </a>
    <?php if ($is_new && !$error && !$payment_success): ?>
    <div class="alert alert--info" style="margin-bottom:20px">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
      <div><strong>Booking created!</strong> Complete your payment below to confirm your reservation. You can skip and pay later from My Bookings.</div>
    </div>
    <?php endif; ?>
    <div class="checkout-layout">
      <div>
        <div class="card">
          <div class="card__body">
            <h2 style="margin-bottom:4px">Complete Payment</h2>
            <p style="color:var(--text-muted);font-size:.82rem;margin-bottom:20px">Simulated payment — no real charges made.</p>

            <?php if ($payment_success): ?>
              <div class="alert alert--success">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"/></svg>
                <?php echo $success_message; ?> Redirecting…
              </div>
            <?php elseif ($error): ?>
              <div class="alert alert--error">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/></svg>
                <?php echo htmlspecialchars($error); ?>
              </div>
              <a href="my_bookings.php" class="btn btn--ghost">Back to My Bookings</a>
            <?php endif; ?>

            <?php if ($booking && !$error && !$payment_success): ?>
              <?php if ($has_down): ?>
                <div class="alert alert--info" style="margin-bottom:18px">
                  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
                  Down payment of <strong>&#8369;<?php echo number_format($down_amount,2); ?></strong> paid. Remaining: <strong>&#8369;<?php echo number_format($balance_due,2); ?></strong>
                </div>
              <?php endif; ?>

              <form method="POST" action="">

                <!-- Payment option -->
                <?php if (!$has_down): ?>
                <div class="form-group">
                  <label class="form-label">Payment Option</label>
                  <div class="payment-type-options">
                    <label class="payment-type-card selected" id="lbl_full_payment">
                      <input type="radio" name="payment_type" value="full_payment" id="pt_full" checked onchange="switchType('full_payment')">
                      <div class="payment-type-card__inner">
                        <div class="payment-type-card__title">
                          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"/></svg>
                          Full Payment <span class="badge badge--confirmed" style="margin-left:6px;font-size:.62rem">100%</span>
                        </div>
                        <div class="payment-type-card__amount">&#8369;<?php echo number_format($total,2); ?></div>
                        <div class="payment-type-card__note">Pay in full — no balance on arrival</div>
                      </div>
                    </label>
                    <label class="payment-type-card" id="lbl_reservation_fee">
                      <input type="radio" name="payment_type" value="reservation_fee" id="pt_down" onchange="switchType('reservation_fee')">
                      <div class="payment-type-card__inner">
                        <div class="payment-type-card__title">
                          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
                          Down Payment <span class="badge badge--pending" style="margin-left:6px;font-size:.62rem"><?php echo $fee_label; ?></span>
                        </div>
                        <div class="payment-type-card__amount">&#8369;<?php echo number_format($down_amount,2); ?></div>
                        <div class="payment-type-card__note">&#8369;<?php echo number_format($remaining,2); ?> balance due on arrival</div>
                      </div>
                    </label>
                  </div>
                </div>
                <?php else: ?>
                  <input type="hidden" name="payment_type" value="balance">
                  <div class="form-group">
                    <label class="form-label">Payment Option</label>
                    <div class="payment-type-card selected" style="pointer-events:none">
                      <div class="payment-type-card__inner">
                        <div class="payment-type-card__title">Pay Remaining Balance</div>
                        <div class="payment-type-card__amount">&#8369;<?php echo number_format($balance_due,2); ?></div>
                        <div class="payment-type-card__note">Completes your payment in full</div>
                      </div>
                    </div>
                  </div>
                <?php endif; ?>

                <!-- Amount display -->
                <div style="background:var(--pink);border-radius:var(--radius-md);padding:12px 16px;margin-bottom:18px">
                  <div style="display:flex;justify-content:space-between;align-items:center">
                    <span style="font-size:.76rem;color:var(--text-2);text-transform:uppercase;letter-spacing:.06em">Amount to Pay</span>
                    <span id="amount-to-pay" style="font-family:var(--font-display);font-size:1.4rem;font-weight:700;color:var(--rose)">
                      &#8369;<?php echo number_format($has_down ? $balance_due : $total, 2); ?>
                    </span>
                  </div>
                  <div id="balance-note" style="font-size:.72rem;color:var(--text-muted);margin-top:4px;text-align:right;<?php echo $has_down ? '' : 'display:none'; ?>">
                    &#8369;<?php echo number_format($remaining,2); ?> remaining due on arrival
                  </div>
                </div>

                <!-- Payment method -->
                <div class="form-group">
                  <label class="form-label">Payment Method</label>
                  <div class="payment-methods">
                    <?php foreach ($methods as [$val,$label,$icon]): ?>
                      <input class="payment-method" type="radio" name="payment_method" id="pm_<?php echo $val; ?>" value="<?php echo $val; ?>">
                      <label for="pm_<?php echo $val; ?>">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><?php echo $icon; ?></svg>
                        <?php echo $label; ?>
                      </label>
                    <?php endforeach; ?>
                  </div>
                </div>

                <div class="form-group">
                  <label class="form-label">Transaction Reference</label>
                  <input class="form-control" type="text" name="transaction_reference" placeholder="e.g. GCASH-20240101-123456" required>
                  <div class="form-hint">Enter the reference number from your payment app.</div>
                </div>

                <button type="submit" class="btn btn--primary btn--lg btn--block" id="submit-btn">
                  Confirm Full Payment
                </button>
                <a href="my_bookings.php" class="btn btn--ghost btn--block" style="margin-top:8px">Pay Later</a>
              </form>
            <?php endif; ?>
          </div>
        </div>
      </div>

      <!-- Summary -->
      <div class="checkout-summary">
        <div class="checkout-summary__resort"><?php echo $booking ? htmlspecialchars($booking['resort_name']) : 'Booking'; ?></div>
        <?php if ($booking): ?>
          <div class="checkout-summary__loc">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0118 0z"/><circle cx="12" cy="10" r="3"/></svg>
            <?php echo htmlspecialchars($booking['location_city']); ?>
          </div>
          <div class="checkout-summary__row"><span class="checkout-summary__label">Check-in</span><span><?php echo date('M d, Y',strtotime($booking['check_in_date'])); ?></span></div>
          <div class="checkout-summary__row"><span class="checkout-summary__label">Check-out</span><span><?php echo date('M d, Y',strtotime($booking['check_out_date'])); ?></span></div>
          <div class="checkout-summary__row"><span class="checkout-summary__label">Duration</span><span><?php echo $nights; ?> night<?php echo $nights!==1?'s':''; ?></span></div>
          <div class="checkout-summary__row"><span class="checkout-summary__label">Guests</span><span><?php echo $booking['guest_count']; ?></span></div>
          <div class="checkout-summary__row"><span class="checkout-summary__label">Rate</span><span>&#8369;<?php echo number_format($booking['price_per_night'],2); ?>/night</span></div>
          <?php if ($total_paid > 0): ?>
          <div class="checkout-summary__row"><span class="checkout-summary__label">Paid</span><span style="color:#a5f3da">&#8369;<?php echo number_format($total_paid,2); ?></span></div>
          <?php endif; ?>
          <div class="checkout-summary__total">
            <div class="checkout-summary__total-label">Total Booking</div>
            <div>&#8369;<?php echo number_format($total,2); ?></div>
          </div>
          <?php if (!$has_down && !$is_fully_paid): ?>
          <div style="margin-top:12px;padding:10px 12px;background:rgba(255,255,255,.1);border-radius:var(--radius-md)">
            <div style="font-size:.66rem;color:rgba(255,255,255,.45);text-transform:uppercase;letter-spacing:.08em;margin-bottom:5px">Down Payment Option</div>
            <div style="display:flex;justify-content:space-between;font-size:.78rem;margin-bottom:3px">
              <span style="color:rgba(255,255,255,.6)">Pay now (<?php echo $fee_label; ?>)</span>
              <span>&#8369;<?php echo number_format($down_amount,2); ?></span>
            </div>
            <div style="display:flex;justify-content:space-between;font-size:.78rem">
              <span style="color:rgba(255,255,255,.6)">On arrival</span>
              <span>&#8369;<?php echo number_format($remaining,2); ?></span>
            </div>
          </div>
          <?php endif; ?>
          <div style="margin-top:10px;font-size:.66rem;color:rgba(255,255,255,.3);text-align:center">Booking #<?php echo $booking_id; ?></div>
        <?php endif; ?>
      </div>
    </div>
  </div>
</main>
<script>
const TOTAL=<?php echo $total; ?>,DOWN=<?php echo $down_amount; ?>,REM=<?php echo $remaining; ?>;
function fmt(n){return'&#8369;'+n.toLocaleString('en-PH',{minimumFractionDigits:2,maximumFractionDigits:2})}
function switchType(t){
  document.querySelectorAll('.payment-type-card').forEach(c=>c.classList.remove('selected'));
  const l=document.getElementById('lbl_'+t);if(l)l.classList.add('selected');
  const a=document.getElementById('amount-to-pay'),n=document.getElementById('balance-note'),b=document.getElementById('submit-btn');
  if(t==='reservation_fee'){a.innerHTML=fmt(DOWN);if(n)n.style.display='block';if(b)b.textContent='Confirm Down Payment';}
  else{a.innerHTML=fmt(TOTAL);if(n)n.style.display='none';if(b)b.textContent='Confirm Full Payment';}
}
</script>
<?php include '_foot.php'; ?>
