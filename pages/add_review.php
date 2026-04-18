<?php
// pages/add_review.php
require_once '../config.php';

if (!isset($_SESSION['user_id']))          { header("Location: login.php"); exit(); }
if ($_SESSION['user_role'] !== 'customer') { header("Location: owner_dashboard.php"); exit(); }

$booking_id      = isset($_GET['booking_id']) ? intval($_GET['booking_id']) : 0;
$booking         = null;
$existing_review = null;
$error           = '';
$success         = '';

if ($booking_id <= 0) { header("Location: my_bookings.php"); exit(); }

try {
    $stmt = $pdo->prepare("SELECT b.*, r.name as resort_name, r.resort_id, bs.status_name FROM bookings b JOIN resorts r ON b.resort_id=r.resort_id JOIN booking_status bs ON b.status_id=bs.status_id WHERE b.booking_id=:bid AND b.customer_id=:cid");
    $stmt->execute([':bid'=>$booking_id,':cid'=>$_SESSION['user_id']]);
    $booking = $stmt->fetch();
    if (!$booking) { header("Location: my_bookings.php"); exit(); }
    if ($booking['status_id'] != 4) $error = "You can only review completed bookings.";
    $cr = $pdo->prepare("SELECT * FROM reviews WHERE booking_id = ?");
    $cr->execute([$booking_id]);
    $existing_review = $cr->fetch();
    if ($existing_review) $error = "You have already submitted a review for this booking.";
} catch (PDOException $e) { error_log($e->getMessage()); $error = "Unable to load booking."; }

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$error && !$existing_review) {
    $rating  = intval($_POST['rating']  ?? 0);
    $comment = trim($_POST['comment']   ?? '');
    $rerrors = [];
    if ($rating < 1 || $rating > 5) $rerrors[] = "Please select a rating.";
    if (strlen($comment) < 10)      $rerrors[] = "Review must be at least 10 characters.";
    if (empty($rerrors)) {
        try {
            $pdo->prepare("INSERT INTO reviews (resort_id,customer_id,booking_id,rating,comment,is_approved,created_at) VALUES (?,?,?,?,?,1,NOW())")
                ->execute([$booking['resort_id'],$_SESSION['user_id'],$booking_id,$rating,$comment]);
            $success = "Thank you for your review!";
            header("refresh:2;url=my_bookings.php");
        } catch (PDOException $e) { error_log($e->getMessage()); $rerrors[] = "Failed to save review."; }
    }
    if (!empty($rerrors)) $error = implode(' ', $rerrors);
}

$pageTitle = 'Write a Review';
include '_head.php';
?>

<main class="section section--sm">
  <div class="container container--sm">

    <a href="my_bookings.php" class="btn btn--ghost btn--sm mb-24" style="display:inline-flex">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="15 18 9 12 15 6"/></svg>
      Back to bookings
    </a>

    <div class="card">
      <div class="card__body">
        <span class="section-eyebrow">Share your experience</span>
        <h2 style="margin-bottom:22px">Write a Review</h2>

        <?php if ($success): ?>
          <div class="alert alert--success">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"/></svg>
            <?php echo htmlspecialchars($success); ?> Redirecting…
          </div>

        <?php elseif ($error): ?>
          <div class="alert alert--error">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/></svg>
            <?php echo htmlspecialchars($error); ?>
          </div>
          <a href="my_bookings.php" class="btn btn--ghost">Back to My Bookings</a>

        <?php else: ?>
          <!-- Booking info strip -->
          <div style="background:var(--sand);border-radius:var(--radius-md);padding:14px 18px;margin-bottom:24px;display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:10px">
            <div>
              <div style="font-family:var(--font-display);font-size:1.05rem;font-weight:600;color:var(--navy)"><?php echo htmlspecialchars($booking['resort_name']); ?></div>
              <div style="font-size:.76rem;color:var(--text-muted);margin-top:3px">
                <?php echo date('M d, Y',strtotime($booking['check_in_date'])); ?> — <?php echo date('M d, Y',strtotime($booking['check_out_date'])); ?>
              </div>
            </div>
            <span class="badge badge--completed">Completed</span>
          </div>

          <form method="POST" action="">
            <div class="form-group">
              <label class="form-label">Your Rating</label>
              <div class="rating-stars" data-input="rating">
                <?php for ($i=1;$i<=5;$i++): ?>
                  <span class="rating-stars__star" data-value="<?php echo $i; ?>">
                    <svg style="width:30px;height:30px;fill:currentColor" viewBox="0 0 24 24"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/></svg>
                  </span>
                <?php endfor; ?>
              </div>
              <input type="hidden" id="rating" name="rating" required>
              <div class="form-hint">Click a star to select your rating</div>
            </div>

            <div class="form-group">
              <label class="form-label" for="comment">Your Review</label>
              <textarea class="form-control" id="comment" name="comment" rows="5"
                placeholder="Tell others about your experience — the atmosphere, amenities, service, and what made your stay memorable…" required><?php echo htmlspecialchars($_POST['comment'] ?? ''); ?></textarea>
              <div class="form-hint">Minimum 10 characters</div>
            </div>

            <button type="submit" class="btn btn--primary btn--lg">Submit Review</button>
          </form>
        <?php endif; ?>
      </div>
    </div>
  </div>
</main>

<?php include '_foot.php'; ?>
