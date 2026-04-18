<?php
// pages/profile.php — Universal profile for Admin, Owner, Customer
require_once '../config.php';

if (!isset($_SESSION['user_id'])) { header("Location: login.php"); exit(); }

$uid   = (int)$_SESSION['user_id'];
$error = '';
$success = '';

// Load user
$user = null;
try {
    $stmt = $pdo->prepare("SELECT u.*, r.role_name FROM users u JOIN user_roles r ON u.role_id=r.role_id WHERE u.user_id=?");
    $stmt->execute([$uid]);
    $user = $stmt->fetch();
} catch (PDOException $e) { error_log($e->getMessage()); }

if (!$user) { header("Location: logout.php"); exit(); }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? 'profile';

    if ($action === 'profile') {
        $full_name = trim($_POST['full_name'] ?? '');
        $phone     = trim($_POST['phone']     ?? '');
        $bio       = trim($_POST['bio']       ?? '');
        $address   = trim($_POST['address']   ?? '');
        $errs      = [];

        if (empty($full_name)) $errs[] = "Full name is required.";

        // Handle profile photo
        $photo_path = $user['profile_photo'] ? basename($user['profile_photo']) : null;

        if (isset($_POST['remove_photo'])) {
            if ($photo_path && file_exists(PROFILE_DIR . $photo_path)) @unlink(PROFILE_DIR . $photo_path);
            $photo_path = null;
        } elseif (!empty($_FILES['profile_photo']['name'])) {
            $file    = $_FILES['profile_photo'];
            $allowed = ['image/jpeg','image/png','image/webp'];
            $maxSize = 3 * 1024 * 1024;
            $ext     = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            if (!in_array($file['type'], $allowed))   $errs[] = "Only JPG, PNG, WEBP allowed.";
            elseif ($file['size'] > $maxSize)          $errs[] = "Photo must be under 3MB.";
            elseif ($file['error'] !== UPLOAD_ERR_OK)  $errs[] = "Upload failed.";
            else {
                if (!is_dir(PROFILE_DIR)) mkdir(PROFILE_DIR, 0755, true);
                $newFilename = 'user_' . $uid . '_' . time() . '.' . $ext;
                $dest        = PROFILE_DIR . $newFilename;
                if (move_uploaded_file($file['tmp_name'], $dest)) {
                    // Remove old photo file
                    if ($photo_path && file_exists(PROFILE_DIR . $photo_path)) {
                        @unlink(PROFILE_DIR . $photo_path);
                    }
                    $photo_path = $newFilename; // store filename only
                } else {
                    $errs[] = "Failed to save photo. Check folder permissions.";
                }
            }
        }

        if (empty($errs)) {
            try {
                $pdo->prepare("UPDATE users SET full_name=?,phone=?,bio=?,address=?,profile_photo=? WHERE user_id=?")
                    ->execute([$full_name,$phone,$bio,$address,$photo_path,$uid]);
                $_SESSION['user_name']    = $full_name;
                $_SESSION['profile_photo'] = $photo_path;
                $success = "Profile updated successfully.";
                $user['full_name']    = $full_name;
                $user['phone']        = $phone;
                $user['bio']          = $bio;
                $user['address']      = $address;
                $user['profile_photo']= $photo_path;
            } catch (PDOException $e) { error_log($e->getMessage()); $error = "Update failed."; }
        } else {
            $error = implode(' ', $errs);
        }
    }

    if ($action === 'password') {
        $current  = $_POST['current_password']  ?? '';
        $new_pw   = $_POST['new_password']       ?? '';
        $confirm  = $_POST['confirm_password']   ?? '';
        $errs     = [];

        if (!password_verify($current, $user['password_hash'])) $errs[] = "Current password is incorrect.";
        if (strlen($new_pw) < 6)  $errs[] = "New password must be at least 6 characters.";
        if ($new_pw !== $confirm) $errs[] = "Passwords do not match.";

        if (empty($errs)) {
            try {
                $pdo->prepare("UPDATE users SET password_hash=? WHERE user_id=?")->execute([password_hash($new_pw, PASSWORD_DEFAULT), $uid]);
                $success = "Password changed successfully.";
            } catch (PDOException $e) { $error = "Password update failed."; }
        } else {
            $error = implode(' ', $errs);
        }
    }
}

$pageTitle = 'My Profile';
include '_head.php';
?>

<main class="section section--sm">
  <div class="container container--sm" style="max-width:740px">

    <div class="section-header">
      <div>
        <span class="section-eyebrow">Account</span>
        <h1>My Profile</h1>
      </div>
    </div>

    <?php if ($error):   ?><div class="alert alert--error"  ><?php echo htmlspecialchars($error);   ?></div><?php endif; ?>
    <?php if ($success): ?><div class="alert alert--success" data-auto-dismiss="4000"><?php echo htmlspecialchars($success); ?></div><?php endif; ?>

    <!-- Profile photo + info -->
    <div class="card mb-24">
      <div class="card__body" style="display:flex;align-items:center;gap:20px;flex-wrap:wrap">
        <div style="flex-shrink:0">
          <?php $displaySrc = profilePhotoSrc($user['profile_photo']); ?>
          <?php if ($displaySrc): ?>
            <img src="<?php echo htmlspecialchars($displaySrc); ?>"
                 alt="Profile"
                 style="width:72px;height:72px;border-radius:50%;object-fit:cover;border:3px solid var(--border)">
          <?php else: ?>
            <div style="width:72px;height:72px;border-radius:50%;background:var(--coral);color:#fff;font-family:var(--font-display);font-size:1.8rem;font-weight:600;display:flex;align-items:center;justify-content:center">
              <?php echo strtoupper(substr($user['full_name'],0,1)); ?>
            </div>
          <?php endif; ?>
        </div>
        <div>
          <h2 style="margin:0"><?php echo htmlspecialchars($user['full_name']); ?></h2>
          <div style="color:var(--text-muted);font-size:.84rem;margin-top:3px"><?php echo htmlspecialchars($user['email']); ?></div>
          <div style="margin-top:6px">
            <span class="badge badge--confirmed"><?php echo ucfirst($user['role_name']); ?></span>
            <span style="font-size:.75rem;color:var(--text-muted);margin-left:8px">Joined <?php echo date('M d, Y',strtotime($user['created_at'])); ?></span>
          </div>
        </div>
      </div>
    </div>

    <!-- Edit Profile -->
    <div class="card mb-24">
      <div class="card__body">
        <h3 style="margin-bottom:18px">Edit Profile</h3>
        <form method="POST" action="" enctype="multipart/form-data">
          <input type="hidden" name="action" value="profile">

          <div class="form-group">
            <label class="form-label">Profile Photo</label>
            <?php $editSrc = profilePhotoSrc($user['profile_photo']); ?>
            <?php if ($editSrc): ?>
              <div style="display:flex;align-items:center;gap:12px;margin-bottom:10px">
                <img src="<?php echo htmlspecialchars($editSrc); ?>"
                     alt="Current photo"
                     style="width:48px;height:48px;border-radius:50%;object-fit:cover;border:2px solid var(--border)">
                <label class="form-check">
                  <input class="form-check__input" type="checkbox" name="remove_photo"> Remove photo
                </label>
              </div>
            <?php endif; ?>
            <input class="form-control" type="file" name="profile_photo" accept="image/jpeg,image/png,image/webp">
            <div class="form-hint">JPG, PNG or WEBP · max 3MB</div>
          </div>

          <div class="form-group">
            <label class="form-label" for="full_name">Full Name <span style="color:var(--coral)">*</span></label>
            <input class="form-control" type="text" id="full_name" name="full_name" value="<?php echo htmlspecialchars($user['full_name']); ?>" required>
          </div>

          <div class="form-row">
            <div class="form-group">
              <label class="form-label" for="phone">Phone</label>
              <input class="form-control" type="tel" id="phone" name="phone" value="<?php echo htmlspecialchars($user['phone']??''); ?>" placeholder="+63 912 345 6789">
            </div>
            <div class="form-group">
              <label class="form-label" for="address">Address</label>
              <input class="form-control" type="text" id="address" name="address" value="<?php echo htmlspecialchars($user['address']??''); ?>" placeholder="City, Province">
            </div>
          </div>

          <div class="form-group">
            <label class="form-label" for="bio">Bio</label>
            <textarea class="form-control" id="bio" name="bio" rows="3" placeholder="Tell others a bit about yourself…"><?php echo htmlspecialchars($user['bio']??''); ?></textarea>
          </div>

          <button type="submit" class="btn btn--primary">Save Changes</button>
        </form>
      </div>
    </div>

    <!-- Change Password -->
    <div class="card">
      <div class="card__body">
        <h3 style="margin-bottom:18px">Change Password</h3>
        <form method="POST" action="">
          <input type="hidden" name="action" value="password">
          <div class="form-group">
            <label class="form-label" for="current_password">Current Password</label>
            <input class="form-control" type="password" id="current_password" name="current_password" required>
          </div>
          <div class="form-row">
            <div class="form-group">
              <label class="form-label" for="new_password">New Password</label>
              <input class="form-control" type="password" id="new_password" name="new_password" placeholder="Min. 6 characters" required>
            </div>
            <div class="form-group">
              <label class="form-label" for="confirm_password">Confirm Password</label>
              <input class="form-control" type="password" id="confirm_password" name="confirm_password" required>
            </div>
          </div>
          <button type="submit" class="btn btn--ghost">Update Password</button>
        </form>
      </div>
    </div>

  </div>
</main>

<?php include '_foot.php'; ?>
