<?php
// pages/owner_reviews.php — Customer reviews for owner's resorts
require_once '../config.php';

if (!isset($_SESSION['user_id']))        { header("Location: login.php"); exit(); }
if ($_SESSION['user_role'] !== 'owner') { header("Location: customer_dashboard.php"); exit(); }

// ── Filters ──────────────────────────────────────────────────
$filter_resort = isset($_GET['resort_id']) ? intval($_GET['resort_id']) : 0;
$sort          = $_GET['sort'] ?? 'latest';

// ── Owner's resorts (for filter dropdown) ────────────────────
$my_resorts = [];
try {
    $stmt = $pdo->prepare("SELECT resort_id, name FROM resorts WHERE owner_id=? ORDER BY name");
    $stmt->execute([$_SESSION['user_id']]);
    $my_resorts = $stmt->fetchAll();
} catch (PDOException $e) { error_log($e->getMessage()); }

// ── Fetch reviews ────────────────────────────────────────────
$reviews    = [];
$avg_rating = 0;
$total_rev  = 0;

if ($my_resorts) {
    $resort_ids  = array_column($my_resorts, 'resort_id');
    $placeholders = implode(',', array_fill(0, count($resort_ids), '?'));

    $where_resort = $filter_resort && in_array($filter_resort, $resort_ids)
        ? " AND rv.resort_id = " . $filter_resort
        : " AND rv.resort_id IN ($placeholders)";

    $order = match($sort) {
        'rating_high' => 'rv.rating DESC, rv.created_at DESC',
        'rating_low'  => 'rv.rating ASC,  rv.created_at DESC',
        default       => 'rv.created_at DESC',
    };

    $params = $filter_resort && in_array($filter_resort, $resort_ids)
        ? []
        : $resort_ids;

    try {
        $stmt = $pdo->prepare("
            SELECT rv.review_id, rv.rating, rv.comment, rv.created_at, rv.is_approved,
                   u.full_name  AS customer_name,
                   r.name       AS resort_name,
                   r.resort_id
            FROM reviews rv
            JOIN users   u ON rv.customer_id = u.user_id
            JOIN resorts r ON rv.resort_id   = r.resort_id
            WHERE 1=1 $where_resort
            ORDER BY $order
        ");
        $stmt->execute($params);
        $reviews   = $stmt->fetchAll();
        $total_rev = count($reviews);

        if ($total_rev > 0) {
            $avg_rating = array_sum(array_column($reviews, 'rating')) / $total_rev;
        }
    } catch (PDOException $e) { error_log($e->getMessage()); }
}

// ── Star helper ───────────────────────────────────────────────
function renderStars(int $rating, bool $large = false): string {
    $size = $large ? '18px' : '13px';
    $html = '<span style="display:inline-flex;gap:2px">';
    for ($i = 1; $i <= 5; $i++) {
        $fill = $i <= $rating ? '#d4a857' : '#dde2ea';
        $html .= "<svg width=\"$size\" height=\"$size\" viewBox=\"0 0 24 24\" fill=\"$fill\" stroke=\"none\"><path d=\"M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z\"/></svg>";
    }
    return $html . '</span>';
}

$pageTitle = 'Customer Reviews';
$activePage = 'reviews';
include '_head.php';
?>

<div class="dash-layout">
  <?php include '_owner_sidebar.php'; ?>

  <main class="dash-main">
    <div class="dash-header">
      <p class="dash-header__greeting">Owner Portal</p>
      <h2>Customer Reviews</h2>
    </div>

    <!-- ── Summary Stats ──────────────────────────────────── -->
    <?php if ($my_resorts): ?>
    <div class="stats-grid" style="grid-template-columns:repeat(auto-fit,minmax(160px,1fr));margin-bottom:24px">
      <div class="stat-card">
        <div class="stat-card__icon">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg>
        </div>
        <div class="stat-card__label">Total Reviews</div>
        <div class="stat-card__value"><?php echo $total_rev; ?></div>
      </div>
      <div class="stat-card">
        <div class="stat-card__icon">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/></svg>
        </div>
        <div class="stat-card__label">Average Rating</div>
        <div class="stat-card__value" style="display:flex;align-items:center;gap:8px">
          <?php if ($avg_rating > 0): ?>
            <span><?php echo number_format($avg_rating, 1); ?></span>
            <span style="font-size:.9rem"><?php echo renderStars((int)round($avg_rating)); ?></span>
          <?php else: ?>
            <span style="font-size:1.2rem;color:var(--text-muted)">—</span>
          <?php endif; ?>
        </div>
      </div>
      <div class="stat-card">
        <div class="stat-card__icon">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 9l9-7 9 7v11a2 2 0 01-2 2H5a2 2 0 01-2-2z"/></svg>
        </div>
        <div class="stat-card__label">Resorts</div>
        <div class="stat-card__value"><?php echo count($my_resorts); ?></div>
      </div>
    </div>

    <!-- ── Filter Bar ─────────────────────────────────────── -->
    <div class="card mb-24">
      <div class="card__body" style="padding:16px 20px">
        <form method="GET" action="" style="display:flex;gap:10px;align-items:flex-end;flex-wrap:wrap">
          <div class="form-group" style="flex:1;min-width:180px;margin:0">
            <label class="form-label">Resort</label>
            <select class="form-control form-control--select" name="resort_id">
              <option value="0">All my resorts</option>
              <?php foreach ($my_resorts as $r): ?>
                <option value="<?php echo $r['resort_id']; ?>"
                  <?php echo $filter_resort === $r['resort_id'] ? 'selected' : ''; ?>>
                  <?php echo htmlspecialchars($r['name']); ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="form-group" style="min-width:160px;margin:0">
            <label class="form-label">Sort By</label>
            <select class="form-control form-control--select" name="sort">
              <option value="latest"      <?php echo $sort==='latest'      ?'selected':''; ?>>Latest First</option>
              <option value="rating_high" <?php echo $sort==='rating_high' ?'selected':''; ?>>Highest Rating</option>
              <option value="rating_low"  <?php echo $sort==='rating_low'  ?'selected':''; ?>>Lowest Rating</option>
            </select>
          </div>
          <div style="padding-bottom:18px;display:flex;gap:8px">
            <button type="submit" class="btn btn--primary">Filter</button>
            <?php if ($filter_resort || $sort !== 'latest'): ?>
              <a href="owner_reviews.php" class="btn btn--ghost">Reset</a>
            <?php endif; ?>
          </div>
        </form>
      </div>
    </div>

    <!-- ── Reviews ───────────────────────────────────────── -->
    <?php if (count($reviews)): ?>
      <p style="font-size:.78rem;color:var(--text-muted);margin-bottom:16px">
        <?php echo $total_rev; ?> review<?php echo $total_rev !== 1 ? 's' : ''; ?> found
      </p>

      <?php foreach ($reviews as $rv): ?>
        <div style="
          display:flex; gap:16px; align-items:flex-start;
          background:#ffffff;
          border:1px solid var(--border-light);
          border-radius:var(--radius-lg);
          padding:20px 22px;
          margin-bottom:14px;
          box-shadow:0 2px 8px rgba(15,30,48,.07);
        ">
          <!-- Avatar -->
          <div style="
            width:44px; height:44px; border-radius:50%;
            background:var(--navy); color:#fff;
            font-family:var(--font-display); font-size:1.15rem; font-weight:600;
            display:flex; align-items:center; justify-content:center;
            flex-shrink:0;
          ">
            <?php echo strtoupper(substr($rv['customer_name'], 0, 1)); ?>
          </div>

          <!-- Content -->
          <div style="flex:1;min-width:0">
            <!-- Top row: name + stars/date -->
            <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:12px;margin-bottom:8px;flex-wrap:wrap">
              <div>
                <div style="font-weight:600;font-size:.95rem;color:var(--navy);margin-bottom:3px">
                  <?php echo htmlspecialchars($rv['customer_name']); ?>
                </div>
                <div style="display:flex;align-items:center;gap:5px;font-size:.75rem;color:var(--text-muted)">
                  <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 9l9-7 9 7v11a2 2 0 01-2 2H5a2 2 0 01-2-2z"/></svg>
                  <?php echo htmlspecialchars($rv['resort_name']); ?>
                </div>
              </div>
              <div style="text-align:right;flex-shrink:0">
                <!-- Stars -->
                <span style="display:inline-flex;gap:2px;margin-bottom:4px">
                  <?php for ($i = 1; $i <= 5; $i++): ?>
                    <svg width="15" height="15" viewBox="0 0 24 24"
                         fill="<?php echo $i <= $rv['rating'] ? '#d4a857' : '#dde2ea'; ?>"
                         stroke="none">
                      <path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/>
                    </svg>
                  <?php endfor; ?>
                </span>
                <div style="font-size:.72rem;color:var(--text-muted)">
                  <?php echo date('M d, Y', strtotime($rv['created_at'])); ?>
                </div>
              </div>
            </div>

            <!-- Rating label -->
            <div style="margin-bottom:8px">
              <span style="
                display:inline-block;padding:2px 10px;border-radius:20px;
                font-size:.7rem;font-weight:600;
                background:<?php echo $rv['rating'] >= 4 ? '#d4edda' : ($rv['rating'] == 3 ? '#fff3cd' : '#f8d7da'); ?>;
                color:<?php echo $rv['rating'] >= 4 ? '#155724' : ($rv['rating'] == 3 ? '#856404' : '#721c24'); ?>;
              ">
                <?php
                  $labels = [1=>'Poor',2=>'Fair',3=>'Good',4=>'Very Good',5=>'Excellent'];
                  echo $labels[$rv['rating']] ?? $rv['rating'] . ' / 5';
                ?>
              </span>
            </div>

            <!-- Comment -->
            <p style="font-size:.875rem;color:var(--text-secondary);line-height:1.65;margin:0">
              <?php echo nl2br(htmlspecialchars($rv['comment'])); ?>
            </p>
          </div>
        </div>
      <?php endforeach; ?>

    <?php elseif (empty($my_resorts)): ?>
      <div class="card"><div class="card__body">
        <div class="empty-state" style="padding:48px 0">
          <svg class="empty-state__icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.25"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg>
          <h3 class="empty-state__title">No resorts yet</h3>
          <p class="empty-state__text">Add your first resort to start receiving reviews.</p>
          <a href="add_resort.php" class="btn btn--coral">Add a Resort</a>
        </div>
      </div></div>
    <?php else: ?>
      <div class="card"><div class="card__body">
        <div class="empty-state" style="padding:48px 0">
          <svg class="empty-state__icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.25"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg>
          <h3 class="empty-state__title">No reviews yet</h3>
          <p class="empty-state__text">Reviews from customers will appear here once submitted.</p>
        </div>
      </div></div>
    <?php endif; ?>

    <?php else: ?>
      <div class="card"><div class="card__body">
        <div class="empty-state" style="padding:48px 0">
          <svg class="empty-state__icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.25"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg>
          <h3 class="empty-state__title">No resorts yet</h3>
          <p class="empty-state__text">Add your first resort to start receiving reviews.</p>
          <a href="add_resort.php" class="btn btn--coral">Add a Resort</a>
        </div>
      </div></div>
    <?php endif; ?>

  </main>
</div>

<?php include '_foot.php'; ?>
