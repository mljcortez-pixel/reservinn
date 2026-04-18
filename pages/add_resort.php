<?php
// pages/add_resort.php
require_once '../config.php';

if (!isset($_SESSION['user_id']))        { header("Location: login.php"); exit(); }
if ($_SESSION['user_role'] !== 'owner') { header("Location: customer_dashboard.php"); exit(); }

$error   = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name             = trim($_POST['name']             ?? '');
    $description      = trim($_POST['description']      ?? '');
    $location_city    = trim($_POST['location_city']    ?? '');
    $location_address = trim($_POST['location_address'] ?? '');
    $price_per_night  = floatval($_POST['price_per_night'] ?? 0);
    $max_guests       = intval($_POST['max_guests']     ?? 0);
    $errors           = [];

    if (empty($name))          $errors[] = "Resort name is required.";
    if (empty($description))   $errors[] = "Description is required.";
    if (empty($location_city)) $errors[] = "City is required.";
    if ($price_per_night <= 0) $errors[] = "Valid price per night is required.";
    if ($max_guests < 1)       $errors[] = "Maximum guests must be at least 1.";

    // Handle image upload
    $image_path = null;
    if (!empty($_FILES['resort_image']['name'])) {
        $file     = $_FILES['resort_image'];
        $allowed  = ['image/jpeg','image/png','image/webp'];
        $maxSize  = 5 * 1024 * 1024;
        $ext      = pathinfo($file['name'], PATHINFO_EXTENSION);

        if (!in_array($file['type'], $allowed))     $errors[] = "Only JPG, PNG, WEBP images allowed.";
        elseif ($file['size'] > $maxSize)           $errors[] = "Image must be under 5MB.";
        elseif ($file['error'] !== UPLOAD_ERR_OK)   $errors[] = "Image upload failed.";
        else {
            $filename   = uniqid('resort_', true) . '.' . strtolower($ext);
            $uploadPath = UPLOAD_DIR . $filename;
            if (!is_dir(UPLOAD_DIR)) mkdir(UPLOAD_DIR, 0755, true);
            if (move_uploaded_file($file['tmp_name'], $uploadPath)) {
                $image_path = 'uploads/resorts/' . $filename;
            } else {
                $errors[] = "Failed to save image.";
            }
        }
    }

    if (empty($errors)) {
        try {
            $pdo->prepare("INSERT INTO resorts (owner_id,name,description,location_city,location_address,price_per_night,max_guests,is_available,image_path) VALUES (:oid,:name,:desc,:city,:addr,:price,:guests,1,:img)")
                ->execute([':oid'=>$_SESSION['user_id'],':name'=>$name,':desc'=>$description,':city'=>$location_city,':addr'=>$location_address,':price'=>$price_per_night,':guests'=>$max_guests,':img'=>$image_path]);
            $success = "Resort added successfully!";
            $_POST   = [];
        } catch (PDOException $e) { error_log($e->getMessage()); $error = "Failed to add resort. Please try again."; }
    } else {
        $error = implode(' ', $errors);
    }
}

$pageTitle = 'Add Resort';
$activePage = 'add_resort';
include '_head.php';
?>

<div class="dash-layout">
  <?php include '_owner_sidebar.php'; ?>
  <main class="dash-main">
    <div class="section-header">
      <div>
        <span class="section-eyebrow">Owner Portal</span>
        <h1>Add New Resort</h1>
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
          <div class="alert alert--success" data-auto-dismiss="5000">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"/></svg>
            <?php echo htmlspecialchars($success); ?>
            <a href="owner_dashboard.php" style="font-weight:600;margin-left:8px">View dashboard</a>
          </div>
        <?php endif; ?>

        <form method="POST" action="" enctype="multipart/form-data">

          <!-- Image Upload -->
          <div class="form-group">
            <label class="form-label">Resort Image</label>
            <div class="image-upload-area">
              <input type="file" id="resort_image" name="resort_image" accept="image/jpeg,image/png,image/webp">
              <svg class="image-upload-area__icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/></svg>
              <div class="image-upload-area__text">
                <strong>Click to upload</strong> or drag & drop<br>
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
            <input class="form-control" type="text" id="name" name="name" value="<?php echo htmlspecialchars($_POST['name'] ?? ''); ?>" placeholder="e.g. Villa Natividad Private Resort" required>
          </div>

          <div class="form-group">
            <label class="form-label" for="description">Description <span style="color:var(--coral)">*</span></label>
            <textarea class="form-control" id="description" name="description" rows="5" placeholder="Describe the resort's atmosphere, amenities, nearby attractions…" required><?php echo htmlspecialchars($_POST['description'] ?? ''); ?></textarea>
          </div>

          <div class="form-row">
            <div class="form-group">
              <label class="form-label" for="location_city">City <span style="color:var(--coral)">*</span></label>
              <input class="form-control" type="text" id="location_city" name="location_city" value="<?php echo htmlspecialchars($_POST['location_city'] ?? ''); ?>" placeholder="e.g. Batangas" required>
            </div>
            <div class="form-group">
              <label class="form-label" for="location_address">Full Address</label>
              <input class="form-control" type="text" id="location_address" name="location_address" value="<?php echo htmlspecialchars($_POST['location_address'] ?? ''); ?>" placeholder="Street, Barangay, Municipality">
            </div>
          </div>

          <div class="form-row">
            <div class="form-group">
              <label class="form-label" for="price_per_night">Price per Night (₱) <span style="color:var(--coral)">*</span></label>
              <input class="form-control" type="number" id="price_per_night" name="price_per_night" value="<?php echo htmlspecialchars($_POST['price_per_night'] ?? ''); ?>" min="1" step="0.01" placeholder="e.g. 5000" required>
            </div>
            <div class="form-group">
              <label class="form-label" for="max_guests">Maximum Guests <span style="color:var(--coral)">*</span></label>
              <input class="form-control" type="number" id="max_guests" name="max_guests" value="<?php echo htmlspecialchars($_POST['max_guests'] ?? ''); ?>" min="1" placeholder="e.g. 20" required>
            </div>
          </div>

          <div style="display:flex;gap:12px;margin-top:8px;flex-wrap:wrap">
            <button type="submit" class="btn btn--primary btn--lg">Add Resort</button>
            <a href="owner_dashboard.php" class="btn btn--ghost btn--lg">Cancel</a>
          </div>
        </form>

      </div>
    </div>
  </main>
</div>

<?php include '_foot.php'; ?>
