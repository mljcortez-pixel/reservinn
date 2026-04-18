<?php
// pages/admin_dashboard.php — Enhanced with Resort List, Owner & Customer Monitoring
require_once '../config.php';

if (!isset($_SESSION['user_id']))       { header("Location: login.php"); exit(); }
if ($_SESSION['user_role'] !== 'admin') { header("Location: customer_dashboard.php"); exit(); }

// ── No POST actions — admin is view-only ─────────────────────

// ── Active tab ──────────────────────────────────────────────
$tab = $_GET['tab'] ?? 'bookings';

// ── Stats ───────────────────────────────────────────────────
$stats = [];
try {
    $stats['total_users']      = $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
    $stats['total_resorts']    = $pdo->query("SELECT COUNT(*) FROM resorts WHERE is_available=1")->fetchColumn();
    $stats['total_revenue']    = $pdo->query("SELECT COALESCE(SUM(amount),0) FROM payments WHERE payment_status='completed'")->fetchColumn();
    $stats['pending_bookings'] = $pdo->query("SELECT COUNT(*) FROM bookings WHERE status_id=1")->fetchColumn();
    $stats['total_bookings']   = $pdo->query("SELECT COUNT(*) FROM bookings")->fetchColumn();
    $stats['total_owners']     = $pdo->query("SELECT COUNT(*) FROM users WHERE role_id=2")->fetchColumn();
    $stats['total_customers']  = $pdo->query("SELECT COUNT(*) FROM users WHERE role_id=3")->fetchColumn();
} catch (PDOException $e) { error_log($e->getMessage()); }

// ── Filter params ────────────────────────────────────────────
$bk_search  = trim($_GET['bk_search']  ?? '');
$bk_status  = trim($_GET['bk_status']  ?? '');
$bk_resort  = intval($_GET['bk_resort'] ?? 0);

$rs_search  = trim($_GET['rs_search']  ?? '');
$rs_city    = trim($_GET['rs_city']    ?? '');
$rs_status  = trim($_GET['rs_status']  ?? '');

$ow_search  = trim($_GET['ow_search']  ?? '');

$cu_search  = trim($_GET['cu_search']  ?? '');

// Pagination
define('ADMIN_PER_PAGE', 10);
$page = max(1, intval($_GET['page'] ?? 1));

function adminPageUrl(int $p): string {
    $q = $_GET; $q['page'] = $p; return '?' . http_build_query($q);
}
function adminPagination(int $total, int $page, int $perPage): string {
    $tp = max(1,(int)ceil($total/$perPage));
    if ($tp<=1) return '';
    $prev = $page>1 ? '<a href="'.adminPageUrl($page-1).'" class="pagination__btn"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="15 18 9 12 15 6"/></svg> Prev</a>'
                    : '<span class="pagination__btn pagination__btn--disabled"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="15 18 9 12 15 6"/></svg> Prev</span>';
    $next = $page<$tp ? '<a href="'.adminPageUrl($page+1).'" class="pagination__btn">Next <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="9 18 15 12 9 6"/></svg></a>'
                      : '<span class="pagination__btn pagination__btn--disabled">Next <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="9 18 15 12 9 6"/></svg></span>';
    $pages=''; $s=max(1,$page-2); $e=min($tp,$page+2);
    if($s>1) $pages.='<a href="'.adminPageUrl(1).'" class="pagination__page">1</a>';
    if($s>2) $pages.='<span class="pagination__ellipsis">…</span>';
    for($i=$s;$i<=$e;$i++) $pages.='<a href="'.adminPageUrl($i).'" class="pagination__page'.($i===$page?' pagination__page--active':'').'">'.$i.'</a>';
    if($e<$tp-1) $pages.='<span class="pagination__ellipsis">…</span>';
    if($e<$tp)   $pages.='<a href="'.adminPageUrl($tp).'" class="pagination__page">'.$tp.'</a>';
    return '<div class="pagination" style="margin-top:20px">'.$prev.'<div class="pagination__pages">'.$pages.'</div>'.$next.'</div>';
}

// ── Recent Bookings — paginated ──────────────────────────────
$recent_bookings = []; $bk_total = 0;
try {
    $bk_where = []; $bk_params = [];
    if ($bk_search) { $bk_where[] = "(u.full_name LIKE ? OR r.name LIKE ?)"; $bk_params[] = "%$bk_search%"; $bk_params[] = "%$bk_search%"; }
    if ($bk_status) { $bk_where[] = "bs.status_name = ?"; $bk_params[] = $bk_status; }
    if ($bk_resort) { $bk_where[] = "b.resort_id = ?";    $bk_params[] = $bk_resort; }
    $bk_join = "FROM bookings b JOIN resorts r ON b.resort_id=r.resort_id JOIN users u ON b.customer_id=u.user_id JOIN booking_status bs ON b.status_id=bs.status_id"
        . ($bk_where ? " WHERE ".implode(" AND ",$bk_where) : "");
    $tc=$pdo->prepare("SELECT COUNT(*) $bk_join"); $tc->execute($bk_params); $bk_total=(int)$tc->fetchColumn();
    $bk_off=($page-1)*ADMIN_PER_PAGE;
    $s=$pdo->prepare("SELECT b.*, r.name as resort_name, u.full_name as customer_name, bs.status_name $bk_join ORDER BY b.created_at DESC LIMIT ".ADMIN_PER_PAGE." OFFSET $bk_off");
    $s->execute($bk_params); $recent_bookings=$s->fetchAll();
} catch (PDOException $e) { error_log($e->getMessage()); }

// ── All Resorts — paginated ──────────────────────────────────
$all_resorts = []; $rs_total = 0;
try {
    $rs_where = []; $rs_params = [];
    if ($rs_search) { $rs_where[] = "(r.name LIKE ? OR r.location_city LIKE ? OR u.full_name LIKE ?)"; $rs_params[] = "%$rs_search%"; $rs_params[] = "%$rs_search%"; $rs_params[] = "%$rs_search%"; }
    if ($rs_city)   { $rs_where[] = "r.location_city = ?"; $rs_params[] = $rs_city; }
    if ($rs_status === 'active')   $rs_where[] = "r.is_available = 1";
    if ($rs_status === 'inactive') $rs_where[] = "r.is_available = 0";
    $rs_join = "FROM resorts r JOIN users u ON r.owner_id=u.user_id".($rs_where?" WHERE ".implode(" AND ",$rs_where):"");
    $tc=$pdo->prepare("SELECT COUNT(*) $rs_join"); $tc->execute($rs_params); $rs_total=(int)$tc->fetchColumn();
    $rs_off=($page-1)*ADMIN_PER_PAGE;
    $s=$pdo->prepare("SELECT r.*, u.full_name as owner_name, u.email as owner_email, u.phone as owner_phone,
        (SELECT AVG(rv.rating) FROM reviews rv WHERE rv.resort_id=r.resort_id AND rv.is_approved=1) as avg_rating,
        (SELECT COUNT(*) FROM reviews rv WHERE rv.resort_id=r.resort_id AND rv.is_approved=1) as review_count,
        (SELECT COUNT(*) FROM bookings b WHERE b.resort_id=r.resort_id AND b.status_id NOT IN(3,5)) as booking_count
        $rs_join ORDER BY r.created_at DESC LIMIT ".ADMIN_PER_PAGE." OFFSET $rs_off");
    $s->execute($rs_params); $all_resorts=$s->fetchAll();
} catch (PDOException $e) { error_log($e->getMessage()); }

// City list for resort filter dropdown
$city_list = [];
try { $city_list = $pdo->query("SELECT DISTINCT location_city FROM resorts WHERE location_city IS NOT NULL AND location_city != '' ORDER BY location_city")->fetchAll(PDO::FETCH_COLUMN); } catch (PDOException $e) {}

// Resort list for booking filter dropdown
$resort_list = [];
try { $resort_list = $pdo->query("SELECT resort_id, name FROM resorts ORDER BY name")->fetchAll(); } catch (PDOException $e) {}

// ── Owners — paginated ───────────────────────────────────────
$owners = []; $ow_total = 0;
try {
    $ow_where = ["u.role_id = 2"]; $ow_params = [];
    if ($ow_search) { $ow_where[] = "(u.full_name LIKE ? OR u.email LIKE ? OR u.phone LIKE ?)"; $ow_params[] = "%$ow_search%"; $ow_params[] = "%$ow_search%"; $ow_params[] = "%$ow_search%"; }
    $ow_w = "WHERE ".implode(" AND ",$ow_where);
    $tc=$pdo->prepare("SELECT COUNT(*) FROM users u $ow_w"); $tc->execute($ow_params); $ow_total=(int)$tc->fetchColumn();
    $ow_off=($page-1)*ADMIN_PER_PAGE;
    $s=$pdo->prepare("SELECT u.*,
        (SELECT COUNT(*) FROM resorts rs WHERE rs.owner_id=u.user_id) as resort_count,
        (SELECT COUNT(*) FROM resorts rs WHERE rs.owner_id=u.user_id AND rs.is_available=1) as active_resorts,
        (SELECT COUNT(*) FROM bookings b JOIN resorts rs ON b.resort_id=rs.resort_id WHERE rs.owner_id=u.user_id AND b.status_id NOT IN(3,5)) as total_bookings,
        (SELECT COALESCE(SUM(p.amount),0) FROM payments p JOIN bookings b ON p.booking_id=b.booking_id JOIN resorts rs ON b.resort_id=rs.resort_id WHERE rs.owner_id=u.user_id AND p.payment_status='completed') as total_revenue
        FROM users u $ow_w ORDER BY u.created_at DESC LIMIT ".ADMIN_PER_PAGE." OFFSET $ow_off");
    $s->execute($ow_params); $owners=$s->fetchAll();
} catch (PDOException $e) { error_log($e->getMessage()); }

// ── Customers — paginated ────────────────────────────────────
$customers = []; $cu_total = 0;
try {
    $cu_where = ["u.role_id = 3"]; $cu_params = [];
    if ($cu_search) { $cu_where[] = "(u.full_name LIKE ? OR u.email LIKE ? OR u.phone LIKE ?)"; $cu_params[] = "%$cu_search%"; $cu_params[] = "%$cu_search%"; $cu_params[] = "%$cu_search%"; }
    $cu_w = "WHERE ".implode(" AND ",$cu_where);
    $tc=$pdo->prepare("SELECT COUNT(*) FROM users u $cu_w"); $tc->execute($cu_params); $cu_total=(int)$tc->fetchColumn();
    $cu_off=($page-1)*ADMIN_PER_PAGE;
    $s=$pdo->prepare("SELECT u.*,
        (SELECT COUNT(*) FROM bookings b WHERE b.customer_id=u.user_id) as booking_count,
        (SELECT COUNT(*) FROM bookings b WHERE b.customer_id=u.user_id AND b.status_id=2) as confirmed_count,
        (SELECT COUNT(*) FROM bookings b WHERE b.customer_id=u.user_id AND b.status_id=4) as completed_count,
        (SELECT COALESCE(SUM(b.total_price),0) FROM bookings b WHERE b.customer_id=u.user_id AND b.status_id NOT IN(3,5)) as total_spent,
        (SELECT COUNT(*) FROM reviews rv WHERE rv.customer_id=u.user_id) as review_count
        FROM users u $cu_w ORDER BY u.created_at DESC LIMIT ".ADMIN_PER_PAGE." OFFSET $cu_off");
    $s->execute($cu_params); $customers=$s->fetchAll();
} catch (PDOException $e) { error_log($e->getMessage()); }

// ── Reviews (with filters) ───────────────────────────────────
$rv_filter_resort  = isset($_GET['rv_resort'])  ? intval($_GET['rv_resort'])          : 0;
$rv_filter_rating  = isset($_GET['rv_rating'])  ? intval($_GET['rv_rating'])           : 0;
$rv_sort           = $_GET['rv_sort']           ?? 'latest';

// Dropdown data
$all_resorts_list = [];
try {
    $all_resorts_list = $pdo->query("SELECT resort_id, name FROM resorts ORDER BY name")->fetchAll();
} catch (PDOException $e) { }

$recent_reviews = [];
if ($tab === 'reviews') {
    $rv_where  = [];
    $rv_params = [];
    if ($rv_filter_resort > 0) { $rv_where[] = "rv.resort_id = ?";  $rv_params[] = $rv_filter_resort; }
    if ($rv_filter_rating > 0) { $rv_where[] = "rv.rating = ?";     $rv_params[] = $rv_filter_rating; }
    $rv_where_sql = $rv_where ? "WHERE " . implode(" AND ", $rv_where) : "";
    $rv_order = match($rv_sort) {
        'rating_high' => 'rv.rating DESC, rv.created_at DESC',
        'rating_low'  => 'rv.rating ASC,  rv.created_at DESC',
        default       => 'rv.created_at DESC',
    };
    try {
        $stmt = $pdo->prepare("SELECT rv.*, u.full_name, rs.name as resort_name, rs.location_city,
            ow.full_name as owner_name
            FROM reviews rv
            JOIN users   u  ON rv.customer_id = u.user_id
            JOIN resorts rs ON rv.resort_id   = rs.resort_id
            JOIN users   ow ON rs.owner_id    = ow.user_id
            $rv_where_sql
            ORDER BY $rv_order");
        $stmt->execute($rv_params);
        $recent_reviews = $stmt->fetchAll();
    } catch (PDOException $e) { error_log($e->getMessage()); }
} else {
    // lightweight fetch for non-reviews tabs (stats only)
    try {
        $recent_reviews = $pdo->query("SELECT rv.*, u.full_name, rs.name as resort_name, rs.location_city,
            ow.full_name as owner_name
            FROM reviews rv
            JOIN users u ON rv.customer_id=u.user_id
            JOIN resorts rs ON rv.resort_id=rs.resort_id
            JOIN users ow ON rs.owner_id=ow.user_id
            ORDER BY rv.created_at DESC LIMIT 20")->fetchAll();
    } catch (PDOException $e) { error_log($e->getMessage()); }
}

// ── Owner detail view ───────────────────────────────────────
$owner_detail   = null;
$owner_resorts  = [];
if ($tab === 'owner_detail' && isset($_GET['owner_id'])) {
    $oid = intval($_GET['owner_id']);
    try {
        $stmt = $pdo->prepare("SELECT u.*,
            (SELECT COUNT(*) FROM resorts rs WHERE rs.owner_id=u.user_id) as resort_count,
            (SELECT COALESCE(SUM(p.amount),0) FROM payments p JOIN bookings b ON p.booking_id=b.booking_id JOIN resorts rs ON b.resort_id=rs.resort_id WHERE rs.owner_id=u.user_id AND p.payment_status='completed') as total_revenue
            FROM users u WHERE u.user_id=? AND u.role_id=2");
        $stmt->execute([$oid]);
        $owner_detail = $stmt->fetch();
        if ($owner_detail) {
            $rStmt = $pdo->prepare("SELECT r.*,
                (SELECT COUNT(*) FROM bookings b WHERE b.resort_id=r.resort_id AND b.status_id NOT IN(3,5)) as booking_count,
                (SELECT AVG(rv.rating) FROM reviews rv WHERE rv.resort_id=r.resort_id AND rv.is_approved=1) as avg_rating
                FROM resorts r WHERE r.owner_id=? ORDER BY r.created_at DESC");
            $rStmt->execute([$oid]);
            $owner_resorts = $rStmt->fetchAll();
        }
    } catch (PDOException $e) { error_log($e->getMessage()); }
}

// ── Customer detail view ────────────────────────────────────
$customer_detail   = null;
$customer_bookings = [];
if ($tab === 'customer_detail' && isset($_GET['customer_id'])) {
    $cid = intval($_GET['customer_id']);
    try {
        $stmt = $pdo->prepare("SELECT u.*,
            (SELECT COUNT(*) FROM bookings b WHERE b.customer_id=u.user_id) as booking_count,
            (SELECT COALESCE(SUM(b.total_price),0) FROM bookings b WHERE b.customer_id=u.user_id AND b.status_id NOT IN(3,5)) as total_spent
            FROM users u WHERE u.user_id=? AND u.role_id=3");
        $stmt->execute([$cid]);
        $customer_detail = $stmt->fetch();
        if ($customer_detail) {
            $bStmt = $pdo->prepare("SELECT b.*, r.name as resort_name, r.location_city, bs.status_name FROM bookings b JOIN resorts r ON b.resort_id=r.resort_id JOIN booking_status bs ON b.status_id=bs.status_id WHERE b.customer_id=? ORDER BY b.created_at DESC");
            $bStmt->execute([$cid]);
            $customer_bookings = $bStmt->fetchAll();
        }
    } catch (PDOException $e) { error_log($e->getMessage()); }
}

$pageTitle = 'Admin Dashboard';
include '_head.php';
?>

<div class="dash-layout">
  <!-- Sidebar -->
  <aside class="dash-sidebar">
    <div class="dash-sidebar__section">
      <span class="dash-sidebar__label">System</span>
      <a class="dash-nav__item <?php echo in_array($tab,['bookings'])  ? 'active':'' ?>" href="?tab=bookings">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
        Bookings
        <?php if ($stats['pending_bookings']>0): ?><span class="sidebar-badge"><?php echo $stats['pending_bookings']; ?></span><?php endif; ?>
      </a>
      <a class="dash-nav__item <?php echo $tab==='resorts'  ? 'active':'' ?>" href="?tab=resorts">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 9l9-7 9 7v11a2 2 0 01-2 2H5a2 2 0 01-2-2z"/></svg>
        All Resorts
      </a>
      <a class="dash-nav__item <?php echo in_array($tab,['owners','owner_detail']) ? 'active':'' ?>" href="?tab=owners">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 00-4-4H8a4 4 0 00-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
        Owner Monitoring
      </a>
      <a class="dash-nav__item <?php echo in_array($tab,['customers','customer_detail']) ? 'active':'' ?>" href="?tab=customers">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 00-3-3.87M16 3.13a4 4 0 010 7.75"/></svg>
        Customer Monitoring
      </a>
      <a class="dash-nav__item <?php echo $tab==='reviews' ? 'active':'' ?>" href="?tab=reviews">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg>
        Reviews
      </a>
    </div>
    <div class="dash-sidebar__section" style="border-top:1px solid rgba(255,255,255,.07);padding-top:16px">
      <a class="dash-nav__item <?php echo $tab==='profile' ? 'active':'' ?>" href="profile.php">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 00-4-4H8a4 4 0 00-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
        My Profile
      </a>
      <a class="dash-nav__item" href="notifications.php">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 8A6 6 0 006 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 01-3.46 0"/></svg>
        Notifications
      </a>
      <a class="dash-nav__item" href="logout.php">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 21H5a2 2 0 01-2-2V5a2 2 0 012-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
        Sign out
      </a>
    </div>
  </aside>

  <main class="dash-main">
    <!-- Sticky filter bar — shown on filterable tabs -->
    <?php if (in_array($tab, ['bookings','resorts','owners','customers'])): ?>
    <div class="admin-sticky-bar" id="admin-sticky-bar">
      <div style="display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap">
        <div style="font-size:.78rem;color:var(--text-muted)">
          <?php
          $cnt = match($tab) {
            'bookings'  => $bk_total . ' booking' . ($bk_total!==1?'s':''),
            'resorts'   => $rs_total . ' resort'  . ($rs_total!==1?'s':''),
            'owners'    => $ow_total . ' owner'   . ($ow_total!==1?'s':''),
            'customers' => $cu_total . ' customer'. ($cu_total!==1?'s':''),
            default     => '',
          };
          echo $cnt . ' found · Page ' . $page;
          ?>
        </div>
        <a href="?tab=<?php echo $tab; ?>" class="btn btn--ghost btn--sm" style="font-size:.72rem">Reset filters</a>
      </div>
    </div>
    <?php endif; ?>

    <div class="dash-header">
      <p class="dash-header__greeting">Administrator</p>
      <h2>System Dashboard</h2>
    </div>

    <!-- Stats -->
    <div class="stats-grid" style="margin-bottom:28px">
      <div class="stat-card"><div class="stat-card__icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="3" y1="10" x2="21" y2="10"/></svg></div><div class="stat-card__label">Total Bookings</div><div class="stat-card__value"><?php echo $stats['total_bookings']; ?></div></div>
      <div class="stat-card"><div class="stat-card__icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 9l9-7 9 7v11a2 2 0 01-2 2H5a2 2 0 01-2-2z"/></svg></div><div class="stat-card__label">Active Resorts</div><div class="stat-card__value"><?php echo $stats['total_resorts']; ?></div></div>
      <div class="stat-card"><div class="stat-card__icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 000 7h5a3.5 3.5 0 010 7H6"/></svg></div><div class="stat-card__label">Total Revenue</div><div class="stat-card__value" style="font-size:1.3rem">&#8369;<?php echo number_format($stats['total_revenue'],0); ?></div></div>
      <div class="stat-card"><div class="stat-card__icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/><circle cx="9" cy="7" r="4"/></svg></div><div class="stat-card__label">Customers</div><div class="stat-card__value"><?php echo $stats['total_customers']; ?></div></div>
    </div>

    <!-- ── BOOKINGS TAB ──────────────────────────────────────── -->
    <?php if ($tab === 'bookings'): ?>
    <div class="monitor-panel">
      <!-- Fixed header with filters -->
      <div class="monitor-panel__header">
        <form method="GET" action="" class="monitor-filter-form">
          <input type="hidden" name="tab" value="bookings">
          <div class="form-group" style="flex:2;min-width:160px;margin:0">
            <input class="form-control form-control--sm" type="text" name="bk_search" value="<?php echo htmlspecialchars($bk_search); ?>" placeholder="Customer or resort…">
          </div>
          <div class="form-group" style="min-width:130px;margin:0">
            <select class="form-control form-control--select form-control--sm" name="bk_status">
              <option value="">All Statuses</option>
              <?php foreach (['pending','confirmed','cancelled','completed','rejected'] as $st): ?>
                <option value="<?php echo $st; ?>" <?php echo $bk_status===$st?'selected':''; ?>><?php echo ucfirst($st); ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="form-group" style="min-width:150px;margin:0">
            <select class="form-control form-control--select form-control--sm" name="bk_resort">
              <option value="0">All Resorts</option>
              <?php foreach ($resort_list as $rl): ?>
                <option value="<?php echo $rl['resort_id']; ?>" <?php echo $bk_resort===$rl['resort_id']?'selected':''; ?>><?php echo htmlspecialchars($rl['name']); ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div style="display:flex;gap:6px;align-items:center">
            <button type="submit" class="btn btn--primary btn--sm">Filter</button>
            <?php if ($bk_search||$bk_status||$bk_resort): ?><a href="?tab=bookings" class="btn btn--ghost btn--sm">Reset</a><?php endif; ?>
            <span class="monitor-count"><?php echo $bk_total; ?> found · pg <?php echo $page; ?></span>
          </div>
        </form>
      </div>
      <!-- Scrollable body -->
      <div class="monitor-panel__body">
        <?php if (count($recent_bookings)): ?>
        <table class="table table--fixed">
          <thead><tr><th>#</th><th>Customer</th><th>Resort</th><th>Check-in</th><th>Check-out</th><th>Guests</th><th>Total</th><th>Status</th></tr></thead>
          <tbody>
            <?php foreach ($recent_bookings as $b): ?>
            <tr>
              <td style="color:var(--text-muted);font-size:.75rem"><?php echo $b['booking_id']; ?></td>
              <td style="font-weight:500"><?php echo htmlspecialchars($b['customer_name']); ?></td>
              <td><?php echo htmlspecialchars($b['resort_name']); ?></td>
              <td><?php echo date('M d, Y',strtotime($b['check_in_date'])); ?></td>
              <td><?php echo date('M d, Y',strtotime($b['check_out_date'])); ?></td>
              <td><?php echo $b['guest_count']; ?></td>
              <td style="font-weight:600">&#8369;<?php echo number_format($b['total_price'],2); ?></td>
              <td><span class="badge badge--<?php echo $b['status_name']; ?>"><?php echo ucfirst($b['status_name']); ?></span></td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
        <?php else: ?>
          <div class="empty-state" style="padding:48px 0">
            <svg class="empty-state__icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.25"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
            <h3 class="empty-state__title">No bookings found</h3>
            <p class="empty-state__text">Try adjusting your filters.</p>
          </div>
        <?php endif; ?>
      </div>
      <!-- Pagination footer -->
      <div class="monitor-panel__footer">
        <?php echo adminPagination($bk_total, $page, ADMIN_PER_PAGE); ?>
      </div>
    </div>

    <!-- ── ALL RESORTS TAB ──────────────────────────────────── -->
    <?php elseif ($tab === 'resorts'): ?>
    <div class="monitor-panel">
      <div class="monitor-panel__header">
        <form method="GET" action="" class="monitor-filter-form">
          <input type="hidden" name="tab" value="resorts">
          <div class="form-group" style="flex:2;min-width:160px;margin:0">
            <input class="form-control form-control--sm" type="text" name="rs_search" value="<?php echo htmlspecialchars($rs_search); ?>" placeholder="Resort, city, or owner…">
          </div>
          <div class="form-group" style="min-width:130px;margin:0">
            <select class="form-control form-control--select form-control--sm" name="rs_city">
              <option value="">All Cities</option>
              <?php foreach ($city_list as $c): ?>
                <option value="<?php echo htmlspecialchars($c); ?>" <?php echo $rs_city===$c?'selected':''; ?>><?php echo htmlspecialchars($c); ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="form-group" style="min-width:110px;margin:0">
            <select class="form-control form-control--select form-control--sm" name="rs_status">
              <option value="">All</option>
              <option value="active"   <?php echo $rs_status==='active'  ?'selected':''; ?>>Active</option>
              <option value="inactive" <?php echo $rs_status==='inactive'?'selected':''; ?>>Inactive</option>
            </select>
          </div>
          <div style="display:flex;gap:6px;align-items:center">
            <button type="submit" class="btn btn--primary btn--sm">Filter</button>
            <?php if ($rs_search||$rs_city||$rs_status): ?><a href="?tab=resorts" class="btn btn--ghost btn--sm">Reset</a><?php endif; ?>
            <span class="monitor-count"><?php echo $rs_total; ?> found · pg <?php echo $page; ?></span>
          </div>
        </form>
      </div>
      <div class="monitor-panel__body">
        <?php if (count($all_resorts)): ?>
        <table class="table table--fixed">
          <thead><tr><th>Resort</th><th>Owner</th><th>City</th><th>Price/Night</th><th>Bookings</th><th>Avg Rating</th><th>Status</th><th></th></tr></thead>
          <tbody>
            <?php foreach ($all_resorts as $r): ?>
            <tr>
              <td>
                <div style="font-weight:600;color:var(--navy)"><?php echo htmlspecialchars($r['name']); ?></div>
                <?php if($r['location_address']): ?><div style="font-size:.7rem;color:var(--text-muted)"><?php echo htmlspecialchars($r['location_address']); ?></div><?php endif; ?>
              </td>
              <td>
                <a href="?tab=owner_detail&owner_id=<?php echo $r['owner_id']; ?>" style="color:var(--navy);font-weight:500;text-decoration:underline"><?php echo htmlspecialchars($r['owner_name']); ?></a>
                <div style="font-size:.7rem;color:var(--text-muted)"><?php echo htmlspecialchars($r['owner_email']); ?></div>
              </td>
              <td><?php echo htmlspecialchars($r['location_city']); ?></td>
              <td style="font-weight:600">&#8369;<?php echo number_format($r['price_per_night'],2); ?></td>
              <td><?php echo $r['booking_count']; ?></td>
              <td><?php echo $r['avg_rating'] ? '⭐ '.number_format($r['avg_rating'],1).' ('.$r['review_count'].')' : '—'; ?></td>
              <td><span class="badge <?php echo $r['is_available']?'badge--available':'badge--cancelled'; ?>"><?php echo $r['is_available']?'Active':'Inactive'; ?></span></td>
              <td><a href="?tab=owner_detail&owner_id=<?php echo $r['owner_id']; ?>" class="btn btn--ghost btn--sm">Owner</a></td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
        <?php else: ?>
          <div class="empty-state" style="padding:48px 0">
            <svg class="empty-state__icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.25"><path d="M3 9l9-7 9 7v11a2 2 0 01-2 2H5a2 2 0 01-2-2z"/></svg>
            <h3 class="empty-state__title">No resorts found</h3><p class="empty-state__text">Try adjusting filters.</p>
          </div>
        <?php endif; ?>
      </div>
      <div class="monitor-panel__footer"><?php echo adminPagination($rs_total, $page, ADMIN_PER_PAGE); ?></div>
    </div>

    <!-- ── OWNER MONITORING TAB ─────────────────────────────── -->
    <?php elseif ($tab === 'owners'): ?>
    <div class="monitor-panel">
      <div class="monitor-panel__header">
        <form method="GET" action="" class="monitor-filter-form">
          <input type="hidden" name="tab" value="owners">
          <div class="form-group" style="flex:1;min-width:220px;margin:0">
            <input class="form-control form-control--sm" type="text" name="ow_search" value="<?php echo htmlspecialchars($ow_search); ?>" placeholder="Search by name, email, or phone…">
          </div>
          <div style="display:flex;gap:6px;align-items:center">
            <button type="submit" class="btn btn--primary btn--sm">Search</button>
            <?php if ($ow_search): ?><a href="?tab=owners" class="btn btn--ghost btn--sm">Reset</a><?php endif; ?>
            <span class="monitor-count"><?php echo $ow_total; ?> found · pg <?php echo $page; ?></span>
          </div>
        </form>
      </div>
      <div class="monitor-panel__body">
        <?php if (count($owners)): ?>
        <table class="table table--fixed">
          <thead><tr><th>Owner</th><th>Contact</th><th>Resorts</th><th>Active</th><th>Total Bookings</th><th>Revenue</th><th>Joined</th><th></th></tr></thead>
          <tbody>
            <?php foreach ($owners as $o): ?>
            <tr>
              <td>
                <div style="font-weight:600"><?php echo htmlspecialchars($o['full_name']); ?></div>
                <div style="font-size:.72rem;color:var(--text-muted)"><?php echo htmlspecialchars($o['email']); ?></div>
              </td>
              <td style="font-size:.8rem;color:var(--text-muted)"><?php echo htmlspecialchars($o['phone'] ?? '—'); ?></td>
              <td style="font-weight:600;text-align:center"><?php echo $o['resort_count']; ?></td>
              <td style="text-align:center"><?php echo $o['active_resorts']; ?></td>
              <td style="text-align:center"><?php echo $o['total_bookings']; ?></td>
              <td style="font-weight:600">&#8369;<?php echo number_format($o['total_revenue'],0); ?></td>
              <td style="font-size:.75rem;color:var(--text-muted)"><?php echo date('M d, Y',strtotime($o['created_at'])); ?></td>
              <td><a href="?tab=owner_detail&owner_id=<?php echo $o['user_id']; ?>" class="btn btn--primary btn--sm">View</a></td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
        <?php else: ?>
          <div class="empty-state" style="padding:48px 0">
            <svg class="empty-state__icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.25"><path d="M20 21v-2a4 4 0 00-4-4H8a4 4 0 00-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
            <h3 class="empty-state__title">No owners found</h3><p class="empty-state__text">Try a different search term.</p>
          </div>
        <?php endif; ?>
      </div>
      <div class="monitor-panel__footer"><?php echo adminPagination($ow_total, $page, ADMIN_PER_PAGE); ?></div>
    </div>

    <!-- ── OWNER DETAIL TAB ─────────────────────────────────── -->
    <?php elseif ($tab === 'owner_detail' && $owner_detail): ?>
    <a href="?tab=owners" class="btn btn--ghost btn--sm mb-24" style="display:inline-flex">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="15 18 9 12 15 6"/></svg> Back to Owners
    </a>
    <div class="card mb-24">
      <div class="card__body">
        <div style="display:flex;align-items:center;gap:20px;flex-wrap:wrap;margin-bottom:20px">
          <?php $ownerPhotoSrc = profilePhotoSrc($owner_detail['profile_photo']); ?>
          <?php if ($ownerPhotoSrc): ?>
            <img src="<?php echo htmlspecialchars($ownerPhotoSrc); ?>"
                 alt="<?php echo htmlspecialchars($owner_detail['full_name']); ?>"
                 style="width:56px;height:56px;border-radius:50%;object-fit:cover;border:2px solid var(--border);flex-shrink:0">
          <?php else: ?>
            <div class="nav__avatar" style="width:56px;height:56px;font-size:1.4rem;flex-shrink:0"><?php echo strtoupper(substr($owner_detail['full_name'],0,1)); ?></div>
          <?php endif; ?>
          <div>
            <h2 style="margin:0"><?php echo htmlspecialchars($owner_detail['full_name']); ?></h2>
            <div style="color:var(--text-muted);font-size:.84rem"><?php echo htmlspecialchars($owner_detail['email']); ?> &nbsp;·&nbsp; <?php echo htmlspecialchars($owner_detail['phone']??'No phone'); ?></div>
            <div style="margin-top:6px">
              <span class="badge <?php echo $owner_detail['is_active']?'badge--available':'badge--cancelled'; ?>"><?php echo $owner_detail['is_active']?'Active':'Inactive'; ?></span>
              <span style="font-size:.76rem;color:var(--text-muted);margin-left:8px">Joined <?php echo date('M d, Y',strtotime($owner_detail['created_at'])); ?></span>
            </div>
          </div>
        </div>
        <div class="stats-grid" style="grid-template-columns:repeat(auto-fit,minmax(140px,1fr))">
          <div class="stat-card"><div class="stat-card__label">Resorts</div><div class="stat-card__value"><?php echo $owner_detail['resort_count']; ?></div></div>
          <div class="stat-card"><div class="stat-card__label">Revenue</div><div class="stat-card__value" style="font-size:1.2rem">&#8369;<?php echo number_format($owner_detail['total_revenue'],0); ?></div></div>
        </div>
      </div>
    </div>
    <h3 style="margin-bottom:14px">Resorts by <?php echo htmlspecialchars($owner_detail['full_name']); ?></h3>
    <?php foreach ($owner_resorts as $r): ?>
    <div class="card mb-16">
      <div class="card__body" style="display:flex;justify-content:space-between;align-items:flex-start;flex-wrap:wrap;gap:12px">
        <div>
          <div style="font-family:var(--font-display);font-size:1.05rem;font-weight:600;color:var(--navy)"><?php echo htmlspecialchars($r['name']); ?></div>
          <div style="font-size:.8rem;color:var(--text-muted);margin-top:3px"><?php echo htmlspecialchars($r['location_city']); ?><?php if($r['location_address']): ?> · <?php echo htmlspecialchars($r['location_address']); ?><?php endif; ?></div>
          <div style="font-size:.8rem;margin-top:6px;display:flex;gap:16px;flex-wrap:wrap">
            <span>&#8369;<?php echo number_format($r['price_per_night'],2); ?>/night</span>
            <span><?php echo $r['max_guests']; ?> max guests</span>
            <span><?php echo $r['booking_count']; ?> bookings</span>
            <?php if($r['avg_rating']): ?><span>⭐ <?php echo number_format($r['avg_rating'],1); ?></span><?php endif; ?>
          </div>
        </div>
        <span class="badge <?php echo $r['is_available']?'badge--available':'badge--cancelled'; ?>"><?php echo $r['is_available']?'Active':'Inactive'; ?></span>
      </div>
    </div>
    <?php endforeach; ?>
    <?php if (!count($owner_resorts)): ?><p style="color:var(--text-muted)">No resorts listed yet.</p><?php endif; ?>

    <!-- ── CUSTOMER MONITORING TAB ──────────────────────────── -->
    <?php elseif ($tab === 'customers'): ?>
    <div class="monitor-panel">
      <div class="monitor-panel__header">
        <form method="GET" action="" class="monitor-filter-form">
          <input type="hidden" name="tab" value="customers">
          <div class="form-group" style="flex:1;min-width:220px;margin:0">
            <input class="form-control form-control--sm" type="text" name="cu_search" value="<?php echo htmlspecialchars($cu_search); ?>" placeholder="Search by name, email, or phone…">
          </div>
          <div style="display:flex;gap:6px;align-items:center">
            <button type="submit" class="btn btn--primary btn--sm">Search</button>
            <?php if ($cu_search): ?><a href="?tab=customers" class="btn btn--ghost btn--sm">Reset</a><?php endif; ?>
            <span class="monitor-count"><?php echo $cu_total; ?> found · pg <?php echo $page; ?></span>
          </div>
        </form>
      </div>
      <div class="monitor-panel__body">
        <?php if (count($customers)): ?>
        <table class="table table--fixed">
          <thead><tr><th>Customer</th><th>Contact</th><th>Bookings</th><th>Confirmed</th><th>Completed</th><th>Reviews</th><th>Total Spent</th><th>Joined</th><th></th></tr></thead>
          <tbody>
            <?php foreach ($customers as $c): ?>
            <tr>
              <td>
                <div style="font-weight:600"><?php echo htmlspecialchars($c['full_name']); ?></div>
                <div style="font-size:.72rem;color:var(--text-muted)"><?php echo htmlspecialchars($c['email']); ?></div>
              </td>
              <td style="font-size:.8rem;color:var(--text-muted)"><?php echo htmlspecialchars($c['phone']??'—'); ?></td>
              <td style="text-align:center;font-weight:600"><?php echo $c['booking_count']; ?></td>
              <td style="text-align:center"><?php echo $c['confirmed_count']; ?></td>
              <td style="text-align:center"><?php echo $c['completed_count']; ?></td>
              <td style="text-align:center"><?php echo $c['review_count']; ?></td>
              <td style="font-weight:600">&#8369;<?php echo number_format($c['total_spent'],0); ?></td>
              <td style="font-size:.75rem;color:var(--text-muted)"><?php echo date('M d, Y',strtotime($c['created_at'])); ?></td>
              <td><a href="?tab=customer_detail&customer_id=<?php echo $c['user_id']; ?>" class="btn btn--primary btn--sm">View</a></td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
        <?php else: ?>
          <div class="empty-state" style="padding:48px 0">
            <svg class="empty-state__icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.25"><path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/><circle cx="9" cy="7" r="4"/></svg>
            <h3 class="empty-state__title">No customers found</h3><p class="empty-state__text">Try a different search term.</p>
          </div>
        <?php endif; ?>
      </div>
      <div class="monitor-panel__footer"><?php echo adminPagination($cu_total, $page, ADMIN_PER_PAGE); ?></div>
    </div>

    <!-- ── CUSTOMER DETAIL TAB ──────────────────────────────── -->
    <?php elseif ($tab === 'customer_detail' && $customer_detail): ?>
    <a href="?tab=customers" class="btn btn--ghost btn--sm mb-24" style="display:inline-flex">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="15 18 9 12 15 6"/></svg> Back to Customers
    </a>
    <div class="card mb-24">
      <div class="card__body">
        <div style="display:flex;align-items:center;gap:20px;flex-wrap:wrap;margin-bottom:20px">
          <?php $custPhotoSrc = profilePhotoSrc($customer_detail['profile_photo']); ?>
          <?php if ($custPhotoSrc): ?>
            <img src="<?php echo htmlspecialchars($custPhotoSrc); ?>"
                 alt="<?php echo htmlspecialchars($customer_detail['full_name']); ?>"
                 style="width:56px;height:56px;border-radius:50%;object-fit:cover;border:2px solid var(--border);flex-shrink:0">
          <?php else: ?>
            <div class="nav__avatar" style="width:56px;height:56px;font-size:1.4rem;flex-shrink:0"><?php echo strtoupper(substr($customer_detail['full_name'],0,1)); ?></div>
          <?php endif; ?>
          <div>
            <h2 style="margin:0"><?php echo htmlspecialchars($customer_detail['full_name']); ?></h2>
            <div style="color:var(--text-muted);font-size:.84rem"><?php echo htmlspecialchars($customer_detail['email']); ?> &nbsp;·&nbsp; <?php echo htmlspecialchars($customer_detail['phone']??'No phone'); ?></div>
            <div style="margin-top:6px">
              <span class="badge <?php echo $customer_detail['is_active']?'badge--available':'badge--cancelled'; ?>"><?php echo $customer_detail['is_active']?'Active':'Inactive'; ?></span>
              <span style="font-size:.76rem;color:var(--text-muted);margin-left:8px">Joined <?php echo date('M d, Y',strtotime($customer_detail['created_at'])); ?></span>
            </div>
            <?php if ($customer_detail['bio']): ?><p style="margin-top:8px;font-size:.84rem;color:var(--text-secondary)"><?php echo htmlspecialchars($customer_detail['bio']); ?></p><?php endif; ?>
          </div>
        </div>
        <div class="stats-grid" style="grid-template-columns:repeat(auto-fit,minmax(140px,1fr))">
          <div class="stat-card"><div class="stat-card__label">Total Bookings</div><div class="stat-card__value"><?php echo $customer_detail['booking_count']; ?></div></div>
          <div class="stat-card"><div class="stat-card__label">Total Spent</div><div class="stat-card__value" style="font-size:1.2rem">&#8369;<?php echo number_format($customer_detail['total_spent'],0); ?></div></div>
        </div>
      </div>
    </div>
    <h3 style="margin-bottom:14px">Booking History</h3>
    <div class="table-wrap">
      <table class="table">
        <thead><tr><th>#</th><th>Resort</th><th>City</th><th>Check-in</th><th>Check-out</th><th>Guests</th><th>Total</th><th>Status</th></tr></thead>
        <tbody>
          <?php foreach ($customer_bookings as $b): ?>
          <tr>
            <td style="color:var(--text-muted);font-size:.75rem"><?php echo $b['booking_id']; ?></td>
            <td style="font-weight:500"><?php echo htmlspecialchars($b['resort_name']); ?></td>
            <td><?php echo htmlspecialchars($b['location_city']); ?></td>
            <td><?php echo date('M d, Y',strtotime($b['check_in_date'])); ?></td>
            <td><?php echo date('M d, Y',strtotime($b['check_out_date'])); ?></td>
            <td><?php echo $b['guest_count']; ?></td>
            <td style="font-weight:600">&#8369;<?php echo number_format($b['total_price'],2); ?></td>
            <td><span class="badge badge--<?php echo $b['status_name']; ?>"><?php echo ucfirst($b['status_name']); ?></span></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>

    <!-- ── REVIEWS TAB ──────────────────────────────────────── -->
    <?php elseif ($tab === 'reviews'): ?>

    <!-- Stats bar -->
    <?php
    $rv_total  = count($recent_reviews);
    $rv_avg    = $rv_total ? array_sum(array_column($recent_reviews,'rating')) / $rv_total : 0;
    ?>
    <div class="stats-grid" style="grid-template-columns:repeat(auto-fit,minmax(150px,1fr));margin-bottom:20px">
      <div class="stat-card">
        <div class="stat-card__icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg></div>
        <div class="stat-card__label">Total Reviews</div>
        <div class="stat-card__value"><?php echo $rv_total; ?></div>
      </div>
      <div class="stat-card">
        <div class="stat-card__icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/></svg></div>
        <div class="stat-card__label">Avg Rating</div>
        <div class="stat-card__value"><?php echo $rv_total ? number_format($rv_avg,1) : '—'; ?></div>
      </div>
    </div>

    <!-- Filter bar -->
    <div class="card mb-20">
      <div class="card__body" style="padding:14px 18px">
        <form method="GET" action="" style="display:flex;gap:10px;align-items:flex-end;flex-wrap:wrap">
          <input type="hidden" name="tab" value="reviews">
          <div class="form-group" style="flex:2;min-width:180px;margin:0">
            <label class="form-label">Resort</label>
            <select class="form-control form-control--select" name="rv_resort">
              <option value="0">All Resorts</option>
              <?php foreach ($all_resorts_list as $ar): ?>
                <option value="<?php echo $ar['resort_id']; ?>"
                  <?php echo $rv_filter_resort===$ar['resort_id']?'selected':''; ?>>
                  <?php echo htmlspecialchars($ar['name']); ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="form-group" style="min-width:140px;margin:0">
            <label class="form-label">Rating</label>
            <select class="form-control form-control--select" name="rv_rating">
              <option value="0">All Ratings</option>
              <?php for($s=5;$s>=1;$s--): ?>
                <option value="<?php echo $s; ?>" <?php echo $rv_filter_rating===$s?'selected':''; ?>>
                  <?php echo str_repeat('★',$s) . str_repeat('☆',5-$s); ?> (<?php echo $s; ?>)
                </option>
              <?php endfor; ?>
            </select>
          </div>
          <div class="form-group" style="min-width:150px;margin:0">
            <label class="form-label">Sort By</label>
            <select class="form-control form-control--select" name="rv_sort">
              <option value="latest"      <?php echo $rv_sort==='latest'     ?'selected':''; ?>>Latest First</option>
              <option value="rating_high" <?php echo $rv_sort==='rating_high'?'selected':''; ?>>Highest Rating</option>
              <option value="rating_low"  <?php echo $rv_sort==='rating_low' ?'selected':''; ?>>Lowest Rating</option>
            </select>
          </div>
          <div style="padding-bottom:18px;display:flex;gap:8px">
            <button type="submit" class="btn btn--primary">Filter</button>
            <?php if ($rv_filter_resort || $rv_filter_rating || $rv_sort !== 'latest'): ?>
              <a href="?tab=reviews" class="btn btn--ghost">Reset</a>
            <?php endif; ?>
          </div>
        </form>
      </div>
    </div>

    <?php if ($rv_total): ?>
      <p style="font-size:.78rem;color:var(--text-muted);margin-bottom:14px">
        <?php echo $rv_total; ?> review<?php echo $rv_total!==1?'s':''; ?> found
      </p>

      <?php foreach ($recent_reviews as $rv): ?>
        <div style="
          display:flex;gap:16px;align-items:flex-start;
          background:#ffffff;
          border:1px solid var(--border-light);
          border-radius:var(--radius-lg);
          padding:18px 20px;margin-bottom:12px;
          box-shadow:0 2px 8px rgba(15,30,48,.07);
        ">
          <!-- Avatar -->
          <div style="
            width:42px;height:42px;border-radius:50%;
            background:var(--navy);color:#fff;
            font-family:var(--font-display);font-size:1.1rem;font-weight:600;
            display:flex;align-items:center;justify-content:center;flex-shrink:0;
          "><?php echo strtoupper(substr($rv['full_name'],0,1)); ?></div>

          <!-- Body -->
          <div style="flex:1;min-width:0">
            <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:12px;margin-bottom:8px;flex-wrap:wrap">
              <div>
                <div style="font-weight:600;font-size:.92rem;color:var(--navy);margin-bottom:2px">
                  <?php echo htmlspecialchars($rv['full_name']); ?>
                </div>
                <div style="font-size:.74rem;color:var(--text-muted);display:flex;align-items:center;gap:10px;flex-wrap:wrap">
                  <span style="display:flex;align-items:center;gap:3px">
                    <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 9l9-7 9 7v11a2 2 0 01-2 2H5a2 2 0 01-2-2z"/></svg>
                    <?php echo htmlspecialchars($rv['resort_name']); ?>
                    <?php if ($rv['location_city']): ?>· <?php echo htmlspecialchars($rv['location_city']); ?><?php endif; ?>
                  </span>
                  <span style="display:flex;align-items:center;gap:3px">
                    <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 00-4-4H8a4 4 0 00-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                    Owner: <?php echo htmlspecialchars($rv['owner_name']); ?>
                  </span>
                </div>
              </div>
              <div style="text-align:right;flex-shrink:0">
                <span style="display:inline-flex;gap:2px;margin-bottom:3px">
                  <?php for($i=1;$i<=5;$i++): ?>
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="<?php echo $i<=$rv['rating']?'#d4a857':'#dde2ea'; ?>" stroke="none"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/></svg>
                  <?php endfor; ?>
                </span>
                <div style="font-size:.7rem;color:var(--text-muted)"><?php echo date('M d, Y',strtotime($rv['created_at'])); ?></div>
              </div>
            </div>
            <!-- Rating label -->
            <?php
            $rl = [1=>'Poor',2=>'Fair',3=>'Good',4=>'Very Good',5=>'Excellent'];
            $rc = $rv['rating']>=4?['#d4edda','#155724']:($rv['rating']==3?['#fff3cd','#856404']:['#f8d7da','#721c24']);
            ?>
            <span style="display:inline-block;padding:2px 9px;border-radius:20px;font-size:.68rem;font-weight:600;background:<?php echo $rc[0]; ?>;color:<?php echo $rc[1]; ?>;margin-bottom:8px">
              <?php echo $rl[$rv['rating']] ?? $rv['rating'].'/5'; ?>
            </span>
            <!-- Comment -->
            <p style="font-size:.84rem;color:var(--text-secondary);line-height:1.6;margin:0 0 10px">
              <?php echo nl2br(htmlspecialchars($rv['comment'])); ?>
            </p>
            <!-- Delete -->
            <a href="delete_review.php?id=<?php echo $rv['review_id']; ?>"
               class="btn btn--danger btn--sm"
               data-confirm="Delete this review permanently?">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14H6L5 6"/><path d="M10 11v6M14 11v6"/></svg>
              Delete
            </a>
          </div>
        </div>
      <?php endforeach; ?>

    <?php else: ?>
      <div class="card"><div class="card__body">
        <div class="empty-state" style="padding:44px 0">
          <svg class="empty-state__icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.25"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg>
          <h3 class="empty-state__title">No reviews found</h3>
          <p class="empty-state__text">Try adjusting your filters.</p>
          <a href="?tab=reviews" class="btn btn--ghost">Clear Filters</a>
        </div>
      </div></div>
    <?php endif; ?>

    <?php endif; ?>
  </main>
</div>

<?php include '_foot.php'; ?>
