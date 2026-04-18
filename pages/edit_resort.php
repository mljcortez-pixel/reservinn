<?php
// pages/edit_resort.php
require_once '../config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'owner') { header("Location: login.php"); exit(); }

$resort_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
if ($resort_id <= 0) { header("Location: owner_dashboard.php"); exit(); }

$resort = null;
try {
    $stmt = $pdo->prepare("SELECT * FROM resorts WHERE resort_id = ? AND owner_id = ?");
    $stmt->execute([$resort_id, $_SESSION['user_id']]);
    $resort = $stmt->fetch();
    if (!$resort) { header("Location: owner_dashboard.php"); exit(); }
} catch (PDOException $e) { error_log($e->getMessage()); header("Location: owner_dashboard.php"); exit(); }

$error   = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name             = trim($_POST['name']             ?? '');
    $description      = trim($_POST['description']      ?? '');
    $location_city    = trim($_POST['location_city']    ?? '');
    $location_address = trim($_POST['location_address'] ?? '');
    $price_per_night  = floatval($_POST['price_per_night'] ?? 0);
    $max_guests       = intval($_POST['max_guests']     ?? 0);
    $is_available     = isset($_POST['is_available'])   ? 1 : 0;
    $errors           = [];

    if (empty($name))          $errors[] = "Resort name is required.";
    if (empty($description))   $errors[] = "Description is required.";
    if (empty($location_city)) $errors[] = "City is required.";
    if ($price_per_night <= 0) $errors[] = "Valid price per night is required.";
    if ($max_guests < 1)       $errors[] = "Maximum guests must be at least 1.";

    // Handle image upload
    $image_path = $resort['image_path']; // keep existing by default

    // Remove image if requested
    if (isset($_POST['remove_image'])) {
        if ($image_path && file_exists('../' . $image_path)) @unlink('../' . $image_path);
        $image_path = null;
    }

    if (!empty($_FILES['resort_image']['name']) && !isset($_POST['remove_image'])) {
        $file    = $_FILES['resort_image'];
        $allowed = ['image/jpeg','image/png','image/webp'];
        $maxSize = 5 * 1024 * 1024;
        $ext     = pathinfo($file['name'], PATHINFO_EXTENSION);

        if (!in_array($file['type'], $allowed))   $errors[] = "Only JPG, PNG, WEBP images allowed.";
        elseif ($file['size'] > $maxSize)         $errors[] = "Image must be under 5MB.";
        elseif ($file['error'] !== UPLOAD_ERR_OK) $errors[] = "Image upload failed.";
        else {
            $filename   = uniqid('resort_', true) . '.' . strtolower($ext);
            $uploadPath = UPLOAD_DIR . $filename;
            if (!is_dir(UPLOAD_DIR)) mkdir(UPLOAD_DIR, 0755, true);
            if (move_uploaded_file($file['tmp_name'], $uploadPath)) {
                // Remove old image
                if ($resort['image_path'] && file_exists('../' . $resort['image_path'])) @unlink('../' . $resort['image_path']);
                $image_path = 'uploads/resorts/' . $filename;
            } else {
                $errors[] = "Failed to save image.";
            }
        }
    }

    if (empty($errors)) {
        try {
            $pdo->prepare("UPDATE resorts SET name=?,description=?,location_city=?,location_address=?,price_per_night=?,max_guests=?,is_available=?,image_path=? WHERE resort_id=? AND owner_id=?")
                ->execute([$name,$description,$location_city,$location_address,$price_per_night,$max_guests,$is_available,$image_path,$resort_id,$_SESSION['user_id']]);
            $success = "Resort updated successfully!";
            $stmt = $pdo->prepare("SELECT * FROM resorts WHERE resort_id = ? AND owner_id = ?");
            $stmt->execute([$resort_id, $_SESSION['user_id']]);
            $resort = $stmt->fetch();
        } catch (PDOException $e) { error_log($e->getMessage()); $error = "Failed to update resort."; }
    } else {
        $error = implode(' ', $errors);
    }
}

$pageTitle = 'Edit ' . $resort['name'];
$activePage = 'dashboard';
include '_head.php';
?>

<div class="dash-layout">
  <?php include '_owner_sidebar.php'; ?>
  <main class="dash-main">
    <div class="section-header">
      <div>
        <span class="section-eyebrow">Owner Portal</span>
        <h1>Edit Resort</h1>
      </div>
    </div>

    <div class="card">
      <div class="card__body">

        <?php if ($error): ?>
          <div class="alert alert--error">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/></svg>
            <?php echo htmlspecialchars($error); ?>
          </div>
        <?php endif; ?>
        <?php if ($success): ?>
          <div class="alert alert--success" data-auto-dismiss="4000">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"/></svg>
            <?php echo htmlspecialchars($success); ?>
          </div>
        <?php endif; ?>

        <form method="POST" action="" enctype="multipart/form-data">

          <!-- Image Upload -->
          <div class="form-group">
            <label class="form-label">Resort Image</label>
            <?php if ($resort['image_path'] && file_exists('../' . $resort['image_path'])): ?>
              <div style="position:relative;margin-bottom:10px">
                <img src="<?php echo htmlspecialchars('../' . $resort['image_path']); ?>" alt="Current image"
                     style="width:100%;max-height:220px;object-fit:cover;border-radius:var(--radius-md)">
                <div style="display:flex;align-items:center;gap:8px;margin-top:8px">
                  <label class="form-check">
                    <input class="form-check__input" type="checkbox" name="remove_image"> Remove current image
                  </label>
                </div>
              </div>
            <?php endif; ?>
            <div class="image-upload-area">
              <input type="file" id="resort_image" name="resort_image" accept="image/jpeg,image/png,image/webp">
              <svg class="image-upload-area__icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/></svg>
              <div class="image-upload-area__text">
                <strong>Upload new image</strong> (replaces current)<br>
                JPG, PNG or WEBP &mdash; max 5MB
              </div>
            </div>
            <div id="image-preview-wrap" style="display:none" class="image-preview">
              <img id="image-preview-img" src="" alt="Preview">
              <button type="button" id="image-remove-btn" class="image-preview__remove">&times;</button>
            </div>
          </div>

          <div class="form-group">
            <label class="form-label" for="name">Resort Name <span style="color:var(--coral)">*</span></label>
            <input class="form-control" type="text" id="name" name="name" value="<?php echo htmlspecialchars($resort['name']); ?>" required>
          </div>

          <div class="form-group">
            <label class="form-label" for="description">Description <span style="color:var(--coral)">*</span></label>
            <textarea class="form-control" id="description" name="description" rows="5" required><?php echo htmlspecialchars($resort['description']); ?></textarea>
          </div>

          <div class="form-row">
            <div class="form-group">
              <label class="form-label" for="location_city">City <span style="color:var(--coral)">*</span></label>
              <input class="form-control" type="text" id="location_city" name="location_city" value="<?php echo htmlspecialchars($resort['location_city']); ?>" required>
            </div>
            <div class="form-group">
              <label class="form-label" for="location_address">Full Address</label>
              <input class="form-control" type="text" id="location_address" name="location_address" value="<?php echo htmlspecialchars($resort['location_address'] ?? ''); ?>">
            </div>
          </div>

          <div class="form-row">
            <div class="form-group">
              <label class="form-label" for="price_per_night">Price per Night (₱) <span style="color:var(--coral)">*</span></label>
              <input class="form-control" type="number" id="price_per_night" name="price_per_night" value="<?php echo $resort['price_per_night']; ?>" min="1" step="0.01" required>
            </div>
            <div class="form-group">
              <label class="form-label" for="max_guests">Maximum Guests <span style="color:var(--coral)">*</span></label>
              <input class="form-control" type="number" id="max_guests" name="max_guests" value="<?php echo $resort['max_guests']; ?>" min="1" required>
            </div>
          </div>

          <div class="form-group">
            <label class="form-check">
              <input class="form-check__input" type="checkbox" name="is_available" <?php echo $resort['is_available'] ? 'checked' : ''; ?>>
              Resort is available for booking
            </label>
          </div>

          <div style="display:flex;gap:12px;margin-top:8px;flex-wrap:wrap">
            <button type="submit" class="btn btn--primary btn--lg">Save Changes</button>
            <a href="owner_dashboard.php" class="btn btn--ghost btn--lg">Cancel</a>
          </div>
        </form>
      </div>
    </div>
  </main>
</div>

<?php include '_foot.php'; ?>
