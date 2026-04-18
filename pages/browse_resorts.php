<?php
// pages/browse_resorts.php — 12 per page, scrollable container, sticky search bar
require_once '../config.php';

if (!isset($_SESSION['user_id']))          { header("Location: login.php"); exit(); }
if ($_SESSION['user_role'] !== 'customer') { header("Location: owner_dashboard.php"); exit(); }

$search   = trim($_GET['search'] ?? '');
$city     = trim($_GET['city']   ?? '');
$sort     = $_GET['sort']        ?? 'newest';
$per_page = 12;
$page     = max(1, intval($_GET['page'] ?? 1));
$offset   = ($page - 1) * $per_page;

$base   = "FROM resorts r JOIN users u ON r.owner_id = u.user_id WHERE r.is_available = 1";
$params = [];

if ($search) {
    $base .= " AND (r.name LIKE :search OR r.description LIKE :search2 OR r.location_city LIKE :search3)";
    $params[':search'] = $params[':search2'] = $params[':search3'] = "%$search%";
}
if ($city) {
    $base .= " AND r.location_city = :city";
    $params[':city'] = $city;
}

$order = match($sort) {
    'price_asc'  => 'r.price_per_night ASC',
    'price_desc' => 'r.price_per_night DESC',
    'rating'     => 'avg_rating DESC',
    default      => 'r.created_at DESC',
};

$total_resorts = 0;
try {
    $c = $pdo->prepare("SELECT COUNT(*) $base");
    $c->execute($params);
    $total_resorts = (int)$c->fetchColumn();
} catch (PDOException $e) { error_log($e->getMessage()); }

$total_pages = max(1, (int)ceil($total_resorts / $per_page));
$page        = min($page, $total_pages);

$resorts = [];
try {
    $sql  = "SELECT r.*, u.full_name as owner_name,
             (SELECT AVG(rv.rating) FROM reviews rv WHERE rv.resort_id=r.resort_id AND rv.is_approved=1) as avg_rating,
             (SELECT COUNT(*) FROM reviews rv WHERE rv.resort_id=r.resort_id AND rv.is_approved=1) as review_count
             $base ORDER BY $order LIMIT :lim OFFSET :off";
    $stmt = $pdo->prepare($sql);
    foreach ($params as $k => $v) $stmt->bindValue($k, $v);
    $stmt->bindValue(':lim', $per_page, PDO::PARAM_INT);
    $stmt->bindValue(':off', $offset,   PDO::PARAM_INT);
    $stmt->execute();
    $resorts = $stmt->fetchAll();
} catch (PDOException $e) { error_log($e->getMessage()); }

$cities = [];
try {
    $cities = $pdo->query("SELECT DISTINCT location_city FROM resorts WHERE is_available=1 ORDER BY location_city")->fetchAll(PDO::FETCH_COLUMN);
} catch (PDOException $e) { }

function pageUrl(int $p): string {
    $q = $_GET; $q['page'] = $p;
    return '?' . http_build_query($q);
}

$pageTitle = 'Browse Resorts';
include '_head.php';
?>

<!-- ── Page wrapper: flex column, full viewport height ───────── -->
<div class="browse-page">

  <!-- ── Sticky search header ─────────────────────────────────── -->
  <div class="browse-header">
    <div class="container">
      <div style="display:flex;align-items:center;justify-content:space-between;gap:12px;padding:10px 0 4px;flex-wrap:wrap">
        <div>
          <span class="section-eyebrow" style="margin-bottom:0">Explore</span>
          <h1 style="font-size:1.4rem;margin:0">Browse Resorts</h1>
        </div>
        <?php if ($total_resorts > 0): ?>
          <span style="font-size:.78rem;color:var(--text-muted)">
            <?php echo $total_resorts; ?> resort<?php echo $total_resorts!==1?'s':''; ?>
            &nbsp;·&nbsp; Page <?php echo $page; ?> of <?php echo $total_pages; ?>
          </span>
        <?php endif; ?>
      </div>

      <!-- Filter form -->
      <form method="GET" action="" class="browse-filter-form">
        <div class="form-group" style="flex:2;min-width:180px;margin:0">
          <input class="form-control" type="text" name="search"
                 value="<?php echo htmlspecialchars($search); ?>"
                 placeholder="Search resort or city…">
        </div>
        <div class="form-group" style="flex:1;min-width:130px;margin:0">
          <select class="form-control form-control--select" name="city">
            <option value="">All Cities</option>
            <?php foreach ($cities as $c): ?>
              <option value="<?php echo htmlspecialchars($c); ?>"
                <?php echo $city===$c?'selected':''; ?>>
                <?php echo htmlspecialchars($c); ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-group" style="min-width:130px;margin:0">
          <select class="form-control form-control--select" name="sort">
            <option value="newest"     <?php echo $sort==='newest'    ?'selected':''; ?>>Newest</option>
            <option value="price_asc"  <?php echo $sort==='price_asc' ?'selected':''; ?>>Price ↑</option>
            <option value="price_desc" <?php echo $sort==='price_desc'?'selected':''; ?>>Price ↓</option>
            <option value="rating"     <?php echo $sort==='rating'    ?'selected':''; ?>>Top Rated</option>
          </select>
        </div>
        <div style="display:flex;gap:6px;align-items:center">
          <button type="submit" class="btn btn--primary btn--sm">Search</button>
          <?php if ($search || $city): ?>
            <a href="browse_resorts.php" class="btn btn--ghost btn--sm">Clear</a>
          <?php endif; ?>
        </div>
      </form>
    </div>
  </div>

  <!-- ── Scrollable resort list ────────────────────────────────── -->
  <div class="browse-scroll-body">
    <div class="container" style="padding-top:24px;padding-bottom:32px">

      <?php if (count($resorts) > 0): ?>
        <div class="resort-grid">
          <?php foreach ($resorts as $r): ?>
            <div class="resort-card">
              <?php if ($r['image_path'] && file_exists('../' . $r['image_path'])): ?>
                <img class="resort-card__image"
                     src="<?php echo htmlspecialchars('../' . $r['image_path']); ?>"
                     alt="<?php echo htmlspecialchars($r['name']); ?>">
              <?php else: ?>
                <div class="resort-card__image-placeholder">
                  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1"><path d="M3 9l9-7 9 7v11a2 2 0 01-2 2H5a2 2 0 01-2-2z"/></svg>
                </div>
              <?php endif; ?>
              <div class="resort-card__body">
                <div class="resort-card__name"><?php echo htmlspecialchars($r['name']); ?></div>
                <div class="resort-card__loc">
                  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0118 0z"/><circle cx="12" cy="10" r="3"/></svg>
                  <?php echo htmlspecialchars($r['location_city']); ?>
                  <?php if ($r['avg_rating']): ?>
                    &nbsp;&middot;&nbsp;
                    <svg style="width:11px;height:11px;fill:#d4a857;margin-right:2px" viewBox="0 0 24 24"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/></svg>
                    <?php echo number_format($r['avg_rating'],1); ?> (<?php echo $r['review_count']; ?>)
                  <?php endif; ?>
                </div>
                <div class="resort-card__desc"><?php echo htmlspecialchars($r['description']); ?></div>
                <div class="resort-card__footer">
                  <div class="resort-card__price">
                    &#8369;<?php echo number_format($r['price_per_night'],2); ?>
                    <span>/ night</span>
                  </div>
                  <a href="book_resort.php?id=<?php echo $r['resort_id']; ?>" class="btn btn--coral btn--sm">Book Now</a>
                </div>
              </div>
            </div>
          <?php endforeach; ?>
        </div>

        <!-- Pagination -->
        <?php if ($total_pages > 1): ?>
          <div class="pagination">
            <?php if ($page > 1): ?>
              <a href="<?php echo pageUrl($page-1); ?>" class="pagination__btn">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="15 18 9 12 15 6"/></svg> Prev
              </a>
            <?php else: ?>
              <span class="pagination__btn pagination__btn--disabled">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="15 18 9 12 15 6"/></svg> Prev
              </span>
            <?php endif; ?>

            <div class="pagination__pages">
              <?php
              $s = max(1,$page-2); $e = min($total_pages,$page+2);
              if($s>1) echo '<a href="'.pageUrl(1).'" class="pagination__page">1</a>';
              if($s>2) echo '<span class="pagination__ellipsis">…</span>';
              for($i=$s;$i<=$e;$i++)
                echo '<a href="'.pageUrl($i).'" class="pagination__page'.($i===$page?' pagination__page--active':'').'">'.$i.'</a>';
              if($e<$total_pages-1) echo '<span class="pagination__ellipsis">…</span>';
              if($e<$total_pages)   echo '<a href="'.pageUrl($total_pages).'" class="pagination__page">'.$total_pages.'</a>';
              ?>
            </div>

            <?php if ($page < $total_pages): ?>
              <a href="<?php echo pageUrl($page+1); ?>" class="pagination__btn">
                Next <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="9 18 15 12 9 6"/></svg>
              </a>
            <?php else: ?>
              <span class="pagination__btn pagination__btn--disabled">
                Next <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="9 18 15 12 9 6"/></svg>
              </span>
            <?php endif; ?>
          </div>
        <?php endif; ?>

      <?php else: ?>
        <div class="card"><div class="card__body">
          <div class="empty-state" style="padding:48px 0">
            <svg class="empty-state__icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.25"><path d="M3 9l9-7 9 7v11a2 2 0 01-2 2H5a2 2 0 01-2-2z"/></svg>
            <h3 class="empty-state__title">No resorts found</h3>
            <p class="empty-state__text">Try adjusting your search or filters.</p>
            <a href="browse_resorts.php" class="btn btn--primary">View all resorts</a>
          </div>
        </div></div>
      <?php endif; ?>
    </div>
  </div><!-- /.browse-scroll-body -->

</div><!-- /.browse-page -->

<?php include '_foot.php'; ?>
