<?php
// pages/manage_availability.php
require_once '../config.php';

if (!isset($_SESSION['user_id']))        { header("Location: login.php"); exit(); }
if ($_SESSION['user_role'] !== 'owner') { header("Location: customer_dashboard.php"); exit(); }

$resort_id = isset($_GET['resort_id']) ? intval($_GET['resort_id']) : 0;
$error     = '';
$success   = '';

try {
    $check = $pdo->prepare("SELECT * FROM resorts WHERE resort_id = ? AND owner_id = ?");
    $check->execute([$resort_id, $_SESSION['user_id']]);
    $resort = $check->fetch();
    if (!$resort) { header("Location: owner_dashboard.php"); exit(); }
} catch (PDOException $e) { error_log($e->getMessage()); header("Location: owner_dashboard.php"); exit(); }

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'add') {
        $start  = $_POST['start_date'] ?? '';
        $end    = $_POST['end_date']   ?? '';
        $reason = trim($_POST['reason'] ?? 'maintenance');
        $errs   = [];
        if (empty($start) || empty($end)) $errs[] = "Both dates are required.";
        elseif ($start > $end)            $errs[] = "End date must be after start date.";
        elseif ($start < date('Y-m-d'))   $errs[] = "Cannot block past dates.";
        if (empty($errs)) {
            try {
                $overlap = $pdo->prepare("SELECT COUNT(*) FROM resort_availability WHERE resort_id=? AND start_date<=? AND end_date>=?");
                $overlap->execute([$resort_id, $end, $start]);
                if ($overlap->fetchColumn() > 0) {
                    $errs[] = "These dates overlap with an existing blocked period.";
                } else {
                    $pdo->prepare("INSERT INTO resort_availability (resort_id,start_date,end_date,reason) VALUES (?,?,?,?)")->execute([$resort_id,$start,$end,$reason]);
                    $success = "Dates blocked successfully.";
                }
            } catch (PDOException $e) { error_log($e->getMessage()); $errs[] = "Failed to block dates."; }
        }
        if (!empty($errs)) $error = implode(' ', $errs);
    }
    if ($_POST['action'] === 'delete') {
        try {
            $pdo->prepare("DELETE FROM resort_availability WHERE block_id=? AND resort_id=?")->execute([intval($_POST['block_id']),$resort_id]);
            $success = "Block removed successfully.";
        } catch (PDOException $e) { error_log($e->getMessage()); $error = "Failed to remove block."; }
    }
}

$blocks = [];
try {
    $stmt = $pdo->prepare("SELECT * FROM resort_availability WHERE resort_id=? ORDER BY start_date DESC");
    $stmt->execute([$resort_id]);
    $blocks = $stmt->fetchAll();
} catch (PDOException $e) { }

$pageTitle = 'Block Dates — ' . $resort['name'];
$activePage = 'dashboard';
include '_head.php';
?>

<div class="dash-layout">
  <?php include '_owner_sidebar.php'; ?>
  <main class="dash-main">
    <div class="section-header">
      <div>
        <span class="section-eyebrow">Availability Management</span>
        <h1>Block Dates</h1>
        <p style="color:var(--text-muted);font-size:.84rem;margin-top:4px"><?php echo htmlspecialchars($resort['name']); ?></p>
      </div>
    </div>

    <?php if ($error):   ?><div class="alert alert--error"  ><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/></svg><?php echo htmlspecialchars($error);   ?></div><?php endif; ?>
    <?php if ($success): ?><div class="alert alert--success" data-auto-dismiss="4000"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"/></svg><?php echo htmlspecialchars($success); ?></div><?php endif; ?>

    <div class="card mb-24">
      <div class="card__body">
        <h3 style="margin-bottom:18px">Add Unavailable Period</h3>
        <form method="POST" action="">
          <input type="hidden" name="action" value="add">
          <div class="form-row" style="grid-template-columns:1fr 1fr 1fr">
            <div class="form-group">
              <label class="form-label" for="start_date">Start Date</label>
              <input class="form-control" type="date" id="start_date" name="start_date" min="<?php echo date('Y-m-d'); ?>" required>
            </div>
            <div class="form-group">
              <label class="form-label" for="end_date">End Date</label>
              <input class="form-control" type="date" id="end_date" name="end_date" min="<?php echo date('Y-m-d'); ?>" required>
            </div>
            <div class="form-group">
              <label class="form-label" for="reason">Reason</label>
              <select class="form-control form-control--select" id="reason" name="reason">
                <option value="maintenance">Maintenance</option>
                <option value="holiday">Holiday / Private Event</option>
                <option value="owner_block">Owner Unavailable</option>
                <option value="renovation">Renovation</option>
              </select>
            </div>
          </div>
          <button type="submit" class="btn btn--primary">Block These Dates</button>
        </form>
      </div>
    </div>

    <div class="card">
      <div class="card__body">
        <h3 style="margin-bottom:18px">Blocked Periods</h3>
        <?php if (count($blocks)): ?>
          <div class="table-wrap">
            <table class="table">
              <thead>
                <tr><th>Start Date</th><th>End Date</th><th>Reason</th><th>Added</th><th></th></tr>
              </thead>
              <tbody>
                <?php foreach ($blocks as $block): ?>
                  <?php $isPast = $block['end_date'] < date('Y-m-d'); ?>
                  <tr style="<?php echo $isPast?'opacity:.45':''; ?>">
                    <td><?php echo date('M d, Y',strtotime($block['start_date'])); ?></td>
                    <td><?php echo date('M d, Y',strtotime($block['end_date']));   ?></td>
                    <td><span class="badge badge--pending"><?php echo ucwords(str_replace('_',' ',$block['reason'])); ?></span></td>
                    <td style="color:var(--text-muted);font-size:.76rem"><?php echo date('M d, Y',strtotime($block['created_at'])); ?></td>
                    <td>
                      <form method="POST" action="" style="display:inline">
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="block_id" value="<?php echo $block['block_id']; ?>">
                        <button type="submit" class="btn btn--danger btn--sm" data-confirm="Remove this block?">Remove</button>
                      </form>
                    </td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
          <div class="date-legend" style="margin-top:18px;padding-top:16px;border-top:1px solid var(--border-light)">
            <div class="date-legend__item"><div class="date-legend__dot date-legend__dot--available"></div> Available</div>
            <div class="date-legend__item"><div class="date-legend__dot date-legend__dot--booked"></div> Booked by guest</div>
            <div class="date-legend__item"><div class="date-legend__dot date-legend__dot--blocked"></div> Blocked by you</div>
          </div>
        <?php else: ?>
          <div class="empty-state" style="padding:36px 0">
            <svg class="empty-state__icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.25"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
            <p class="empty-state__text">No blocked dates. Your resort is fully available.</p>
          </div>
        <?php endif; ?>
      </div>
    </div>

  </main>
</div>

<?php include '_foot.php'; ?>
