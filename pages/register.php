<?php
// pages/register.php
require_once '../config.php';
$error = ''; $success = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $full_name = trim($_POST['full_name'] ?? '');
    $email     = trim($_POST['email']     ?? '');
    $password  = $_POST['password']       ?? '';
    $confirm   = $_POST['confirm_password']?? '';
    $phone     = trim($_POST['phone']     ?? '');
    $role_id   = intval($_POST['role_id'] ?? 3);
    $errs = [];
    if (!$full_name) $errs[] = "Full name is required.";
    if (!$email || !filter_var($email,FILTER_VALIDATE_EMAIL)) $errs[] = "Valid email is required.";
    if (strlen($password) < 6) $errs[] = "Password must be at least 6 characters.";
    if ($password !== $confirm) $errs[] = "Passwords do not match.";
    if (empty($errs)) {
        try {
            $s = $pdo->prepare("SELECT user_id FROM users WHERE email=?"); $s->execute([$email]);
            if ($s->fetch()) $errs[] = "Email already registered.";
        } catch (PDOException $e) { $errs[] = "Database error."; }
    }
    if (empty($errs)) {
        try {
            $h = password_hash($password, PASSWORD_DEFAULT);
            $pdo->prepare("INSERT INTO users (email,password_hash,full_name,phone,role_id,is_active) VALUES (?,?,?,?,?,1)")
                ->execute([$email,$h,$full_name,$phone,$role_id]);
            $success = "Account created! You can now sign in.";
            $full_name = $email = $phone = '';
        } catch (PDOException $e) { error_log($e->getMessage()); $error = "Registration failed. Please try again."; }
    } else { $error = implode(' ', $errs); }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Create Account — ReservInn</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link rel="stylesheet" href="../css/style.css">
</head>
<body>
<div class="auth-layout">
  <div class="auth-visual">
    <div class="auth-visual__decoration"></div>
    <div class="auth-visual__decoration2"></div>
    <div class="auth-visual__logo">
      <div class="auth-visual__logo-icon">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 9l9-7 9 7v11a2 2 0 01-2 2H5a2 2 0 01-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>
      </div>
      ReservInn
    </div>
    <div style="position:relative;z-index:1">
      <p class="auth-visual__tagline">Find and book the perfect resort.</p>
      <p class="auth-visual__sub">Join thousands of guests enjoying private resorts across the Philippines.</p>
    </div>
    <!-- Steps -->
    <div style="position:relative;z-index:1;margin-top:48px;display:flex;flex-direction:column;gap:20px">
      <div style="display:flex;align-items:flex-start;gap:14px">
        <div style="width:28px;height:28px;background:var(--rose);border-radius:50%;display:flex;align-items:center;justify-content:center;flex-shrink:0;font-size:.72rem;font-weight:700;color:#fff">1</div>
        <div>
          <div style="font-size:.82rem;font-weight:600;color:var(--rose)">Create your account</div>
          <div style="font-size:.76rem;color:var(--pink-accent);margin-top:2px">Takes less than a minute</div>
        </div>
      </div>
      <div style="display:flex;align-items:flex-start;gap:14px">
        <div style="width:28px;height:28px;background:var(--rose);border-radius:50%;display:flex;align-items:center;justify-content:center;flex-shrink:0;font-size:.72rem;font-weight:700;color:#fff">2</div>
        <div>
          <div style="font-size:.82rem;font-weight:600;color:var(--rose)">Browse & book a resort</div>
          <div style="font-size:.76rem;color:var(--pink-accent);margin-top:2px">100+ handpicked resorts</div>
        </div>
      </div>
      <div style="display:flex;align-items:flex-start;gap:14px">
        <div style="width:28px;height:28px;background:var(--rose);border-radius:50%;display:flex;align-items:center;justify-content:center;flex-shrink:0;font-size:.72rem;font-weight:700;color:#fff">3</div>
        <div>
          <div style="font-size:.82rem;font-weight:600;color:var(--rose)">Enjoy your stay</div>
          <div style="font-size:.76rem;color:var(--pink-accent);margin-top:2px">Memorable experiences await</div>
        </div>
      </div>
    </div>
  </div>
  <div class="auth-form-area">
    <div class="auth-form-area__inner" style="max-width:420px">
      <p class="section-eyebrow" style="margin-bottom:6px">Get started</p>
      <h1 class="auth-title">Create your account</h1>
      <p class="auth-subtitle">Fill in your details to join ReservInn.</p>
      <?php if ($error): ?>
        <div class="alert alert--error">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/></svg>
          <?php echo htmlspecialchars($error); ?>
        </div>
      <?php endif; ?>
      <?php if ($success): ?>
        <div class="alert alert--success">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"/></svg>
          <?php echo htmlspecialchars($success); ?> <a href="login.php" style="font-weight:600;color:inherit">Sign in now →</a>
        </div>
      <?php endif; ?>
      <form method="POST" action="">
        <div class="form-group">
          <label class="form-label">Full Name</label>
          <input class="form-control" type="text" name="full_name"
                 value="<?php echo htmlspecialchars($full_name ?? ''); ?>"
                 placeholder="Maria Santos" required autofocus>
        </div>
        <div class="form-group">
          <label class="form-label">Email Address</label>
          <input class="form-control" type="email" name="email"
                 value="<?php echo htmlspecialchars($email ?? ''); ?>"
                 placeholder="you@example.com" required>
        </div>
        <div class="form-group">
          <label class="form-label">Phone <span style="font-weight:400;color:var(--text-muted);text-transform:none;letter-spacing:0">(optional)</span></label>
          <input class="form-control" type="tel" name="phone"
                 value="<?php echo htmlspecialchars($phone ?? ''); ?>"
                 placeholder="+63 912 345 6789">
        </div>
        <div class="form-row">
          <div class="form-group">
            <label class="form-label">Password</label>
            <input class="form-control" type="password" name="password"
                   placeholder="Min. 6 characters" required>
          </div>
          <div class="form-group">
            <label class="form-label">Confirm</label>
            <input class="form-control" type="password" name="confirm_password"
                   placeholder="Repeat password" required>
          </div>
        </div>
        <div class="form-group">
          <label class="form-label">I want to</label>
          <select class="form-control form-control--select" name="role_id">
            <option value="3">Book resorts (Guest)</option>
            <option value="2">List my resort (Owner)</option>
          </select>
        </div>
        <button type="submit" class="btn btn--primary btn--block btn--lg" style="margin-top:4px">
          Create account
        </button>
      </form>
      <div class="auth-divider">or</div>
      <div class="auth-link" style="margin-top:0">
        Already have an account? <a href="login.php">Sign in</a>
      </div>
    </div>
  </div>
</div>
<script src="../js/script.js"></script>
</body>
</html>
