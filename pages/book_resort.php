<?php
// pages/book_resort.php
require_once '../config.php';
if (!isset($_SESSION['user_id']))          { header("Location: login.php"); exit(); }
if ($_SESSION['user_role'] !== 'customer') { header("Location: owner_dashboard.php"); exit(); }

$resort_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
if ($resort_id <= 0) { header("Location: browse_resorts.php"); exit(); }

$resort = null;
try {
    $stmt = $pdo->prepare("SELECT r.*, u.full_name as owner_name, u.user_id as owner_id,
        (SELECT AVG(rv.rating) FROM reviews rv WHERE rv.resort_id=r.resort_id AND rv.is_approved=1) as avg_rating,
        (SELECT COUNT(*) FROM reviews rv WHERE rv.resort_id=r.resort_id AND rv.is_approved=1) as review_count
        FROM resorts r JOIN users u ON r.owner_id=u.user_id WHERE r.resort_id=? AND r.is_available=1");
    $stmt->execute([$resort_id]);
    $resort = $stmt->fetch();
    if (!$resort) { header("Location: browse_resorts.php"); exit(); }
} catch (PDOException $e) { error_log($e->getMessage()); header("Location: browse_resorts.php"); exit(); }

$reviews = [];
try {
    $s = $pdo->prepare("SELECT rv.*, u.full_name FROM reviews rv JOIN users u ON rv.customer_id=u.user_id WHERE rv.resort_id=? AND rv.is_approved=1 ORDER BY rv.created_at DESC LIMIT 5");
    $s->execute([$resort_id]); $reviews = $s->fetchAll();
} catch (PDOException $e) {}

$unavailableDates = [];
try {
    $s = $pdo->prepare("SELECT start_date, end_date, 'blocked' as type FROM resort_availability WHERE resort_id=?");
    $s->execute([$resort_id]); foreach ($s->fetchAll() as $b) $unavailableDates[] = $b;
    $s = $pdo->prepare("SELECT check_in_date as start_date, check_out_date as end_date, 'booked' as type FROM bookings WHERE resort_id=? AND status_id IN (1,2,6)");
    $s->execute([$resort_id]); foreach ($s->fetchAll() as $b) $unavailableDates[] = $b;
} catch (PDOException $e) {}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $check_in       = $_POST['check_in_date']   ?? '';
    $check_out      = $_POST['check_out_date']  ?? '';
    $arrival_time   = $_POST['arrival_time']    ?? '14:00';
    $departure_time = $_POST['departure_time']  ?? '12:00';
    $guest_count    = intval($_POST['guest_count'] ?? 1);
    $requests       = trim($_POST['special_requests'] ?? '');
    $errs = [];

    if (!$check_in || !$check_out)             $errs[] = "Check-in and check-out dates are required.";
    elseif ($check_out <= $check_in)           $errs[] = "Check-out must be after check-in.";
    elseif ($check_in < date('Y-m-d'))         $errs[] = "Check-in date cannot be in the past.";
    if ($guest_count < 1 || $guest_count > $resort['max_guests'])
        $errs[] = "Guest count must be between 1 and {$resort['max_guests']}.";

    if (empty($errs)) {
        try {
            $cx = $pdo->prepare("SELECT b.booking_id, r.name as resort_name FROM bookings b JOIN resorts r ON b.resort_id=r.resort_id WHERE b.customer_id=? AND b.status_id IN (1,2,6) AND b.check_in_date < ? AND b.check_out_date > ? LIMIT 1");
            $cx->execute([$_SESSION['user_id'], $check_out, $check_in]);
            $cl = $cx->fetch();
            if ($cl) $errs[] = "You already have an active booking at <strong>" . htmlspecialchars($cl['resort_name']) . "</strong> overlapping these dates.";
        } catch (PDOException $e) { $errs[] = "Date validation failed. Please try again."; }
    }

    if (empty($errs)) {
        $nights = (strtotime($check_out) - strtotime($check_in)) / 86400;
        $total  = $nights * $resort['price_per_night'];
        try {
            $pdo->prepare("INSERT INTO bookings (resort_id,customer_id,check_in_date,check_out_date,arrival_time,departure_time,guest_count,total_price,special_requests,status_id) VALUES (?,?,?,?,?,?,?,?,?,1)")
                ->execute([$resort_id,$_SESSION['user_id'],$check_in,$check_out,$arrival_time.':00',$departure_time.':00',$guest_count,$total,$requests]);
            $booking_id = $pdo->lastInsertId();

            // Notify owner
            createNotification($pdo, $resort['owner_id'], 'booking_new',
                'New Booking',
                htmlspecialchars($_SESSION['user_name']).' booked '.$resort['name'].' ('.date('M d',strtotime($check_in)).' – '.date('M d, Y',strtotime($check_out)).').',
                'owner_bookings.php?resort_id='.$resort_id
            );
            // Send customer to payment
            header("Location: payment.php?booking_id=$booking_id&new=1");
            exit();
        } catch (PDOException $e) { error_log($e->getMessage()); $error = "Booking failed. Please try again."; }
    } else {
        $error = implode('<br>', $errs);
    }
}

$pageTitle = $resort['name'];
include '_head.php';
?>
<main class="section--sm" style="padding: calc(var(--nav-h) + 16px) 0 80px">
  <div class="container">
    <a href="browse_resorts.php" class="btn btn--ghost btn--sm mb-24" style="display:inline-flex">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="15 18 9 12 15 6"/></svg>
      Back to browse
    </a>

    <!-- Resort header -->
    <div class="card mb-20">
      <?php if ($resort['image_path'] && file_exists('../'.$resort['image_path'])): ?>
        <img src="<?php echo htmlspecialchars('../'.$resort['image_path']); ?>" alt="" style="width:100%;height:260px;object-fit:cover">
      <?php else: ?>
        <div style="width:100%;height:200px;background:var(--pink);display:flex;align-items:center;justify-content:center">
          <svg width="56" height="56" viewBox="0 0 24 24" fill="none" stroke="var(--rose)" stroke-width="1"><path d="M3 9l9-7 9 7v11a2 2 0 01-2 2H5a2 2 0 01-2-2z"/></svg>
        </div>
      <?php endif; ?>
      <div class="card__body">
        <div style="display:flex;justify-content:space-between;align-items:flex-start;flex-wrap:wrap;gap:12px">
          <div>
            <h1 style="margin-bottom:4px"><?php echo htmlspecialchars($resort['name']); ?></h1>
            <div style="color:var(--text-muted);font-size:.82rem;display:flex;align-items:center;gap:6px;flex-wrap:wrap">
              <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0118 0z"/><circle cx="12" cy="10" r="3"/></svg>
              <?php echo htmlspecialchars($resort['location_city']); ?>
              <?php if ($resort['avg_rating']): ?>
                &nbsp;·&nbsp; ⭐ <?php echo number_format($resort['avg_rating'],1); ?> (<?php echo $resort['review_count']; ?> reviews)
              <?php endif; ?>
            </div>
          </div>
          <div style="text-align:right">
            <div style="font-family:var(--font-display);font-size:1.6rem;font-weight:600;color:var(--rose)">
              &#8369;<?php echo number_format($resort['price_per_night'],2); ?>
            </div>
            <div style="font-size:.74rem;color:var(--text-muted)">per night · max <?php echo $resort['max_guests']; ?> guests</div>
          </div>
        </div>
        <p style="margin-top:12px"><?php echo nl2br(htmlspecialchars($resort['description'])); ?></p>
      </div>
    </div>

    <div class="checkout-layout">
      <!-- Form -->
      <div>
        <div class="card">
          <div class="card__body">
            <h2 style="margin-bottom:4px">Book this resort</h2>
            <p style="color:var(--text-muted);font-size:.8rem;margin-bottom:18px">You'll be taken to payment after booking.</p>
            <?php if ($error): ?>
              <div class="alert alert--error">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/></svg>
                <?php echo $error; ?>
              </div>
            <?php endif; ?>
            <form method="POST" action="">
              <div class="form-row">
                <div class="form-group">
                  <label class="form-label">Check-in Date *</label>
                  <input class="form-control" type="date" id="check_in_date" name="check_in_date" value="<?php echo htmlspecialchars($_POST['check_in_date'] ?? ''); ?>" required>
                </div>
                <div class="form-group">
                  <label class="form-label">Check-out Date *</label>
                  <input class="form-control" type="date" id="check_out_date" name="check_out_date" value="<?php echo htmlspecialchars($_POST['check_out_date'] ?? ''); ?>" required>
                </div>
              </div>
              <div class="form-row">
                <div class="form-group">
                  <label class="form-label">Arrival Time</label>
                  <input class="form-control" type="time" name="arrival_time" value="<?php echo htmlspecialchars($_POST['arrival_time'] ?? '14:00'); ?>">
                  <div class="form-hint">Standard check-in: 2:00 PM</div>
                </div>
                <div class="form-group">
                  <label class="form-label">Departure Time</label>
                  <input class="form-control" type="time" name="departure_time" value="<?php echo htmlspecialchars($_POST['departure_time'] ?? '12:00'); ?>">
                  <div class="form-hint">Standard check-out: 12:00 PM</div>
                </div>
              </div>
              <div id="date-range-error" class="alert alert--error" style="display:none">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/></svg>
                Selected dates overlap with unavailable periods.
              </div>
              <div id="price-calc" style="display:none;background:var(--pink);border-radius:var(--radius-md);padding:12px 16px;margin-bottom:16px">
                <div style="display:flex;justify-content:space-between;font-size:.84rem;margin-bottom:4px">
                  <span style="color:var(--text-2)">Duration</span>
                  <span id="nights-preview" style="font-weight:600"></span>
                </div>
                <div style="display:flex;justify-content:space-between;font-size:.84rem">
                  <span style="color:var(--text-2)">Total</span>
                  <span id="price-preview" style="font-weight:700;color:var(--rose)"></span>
                </div>
              </div>
              <input type="hidden" id="price-per-night" value="<?php echo $resort['price_per_night']; ?>">
              <div class="form-group">
                <label class="form-label">Number of Guests *</label>
                <input class="form-control" type="number" name="guest_count" min="1" max="<?php echo $resort['max_guests']; ?>" value="<?php echo htmlspecialchars($_POST['guest_count'] ?? '1'); ?>" required>
                <div class="form-hint">Maximum <?php echo $resort['max_guests']; ?> guests</div>
              </div>
              <div class="form-group">
                <label class="form-label">Special Requests</label>
                <textarea class="form-control" name="special_requests" rows="3" placeholder="Any special needs or requests…"><?php echo htmlspecialchars($_POST['special_requests'] ?? ''); ?></textarea>
              </div>
              <button type="submit" class="btn btn--primary btn--lg btn--block" data-booking-submit>Continue to Payment</button>
            </form>
          </div>
        </div>
      </div>
      <!-- Summary -->
      <div class="checkout-summary">
        <div class="checkout-summary__resort"><?php echo htmlspecialchars($resort['name']); ?></div>
        <div class="checkout-summary__loc">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0118 0z"/><circle cx="12" cy="10" r="3"/></svg>
          <?php echo htmlspecialchars($resort['location_city']); ?>
        </div>
        <div class="checkout-summary__row"><span class="checkout-summary__label">Rate</span><span>&#8369;<?php echo number_format($resort['price_per_night'],2); ?>/night</span></div>
        <div class="checkout-summary__row"><span class="checkout-summary__label">Max guests</span><span><?php echo $resort['max_guests']; ?></span></div>
        <div class="checkout-summary__row"><span class="checkout-summary__label">Duration</span><span id="summary-nights">—</span></div>
        <div class="checkout-summary__total">
          <div class="checkout-summary__total-label">Estimated Total</div>
          <div id="summary-price" style="font-size:1.5rem">—</div>
        </div>
        <p style="font-size:.68rem;color:rgba(255,255,255,.35);text-align:center;margin-top:10px">No owner approval needed · Book instantly</p>
      </div>
    </div>

    <?php if (count($reviews)): ?>
    <div class="card" style="margin-top:24px">
      <div class="card__body">
        <h3 style="margin-bottom:16px">Guest Reviews</h3>
        <?php foreach ($reviews as $rv): ?>
          <div style="padding:12px 0;border-bottom:1px solid var(--border-light)">
            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:4px;flex-wrap:wrap;gap:6px">
              <strong style="font-size:.88rem"><?php echo htmlspecialchars($rv['full_name']); ?></strong>
              <div style="display:flex;gap:2px">
                <?php for($i=1;$i<=5;$i++): ?>
                  <svg width="13" height="13" viewBox="0 0 24 24" fill="<?php echo $i<=$rv['rating']?'#f59e0b':'#ddd'; ?>" stroke="none"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/></svg>
                <?php endfor; ?>
              </div>
            </div>
            <p style="font-size:.82rem;margin:0"><?php echo htmlspecialchars($rv['comment']); ?></p>
            <div style="font-size:.7rem;color:var(--text-muted);margin-top:3px"><?php echo date('M d, Y',strtotime($rv['created_at'])); ?></div>
          </div>
        <?php endforeach; ?>
      </div>
    </div>
    <?php endif; ?>
  </div>
</main>
<script>window.unavailableDates = <?php echo json_encode($unavailableDates); ?>;</script>
<?php include '_foot.php'; ?>
