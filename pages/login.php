<?php
// pages/login.php
require_once '../config.php';
if (isset($_SESSION['user_id'])) {
    $r = $_SESSION['user_role'];
    header('Location: '.($r==='admin'?'admin_dashboard.php':($r==='owner'?'owner_dashboard.php':'customer_dashboard.php')));
    exit();
}
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $pass  = $_POST['password'] ?? '';
    $errs  = [];
    if (!$email) $errs[] = "Email is required.";
    if (!$pass)  $errs[] = "Password is required.";
    if (empty($errs)) {
        try {
            $stmt = $pdo->prepare("SELECT u.user_id,u.email,u.password_hash,u.full_name,u.is_active,r.role_name,r.role_id FROM users u JOIN user_roles r ON u.role_id=r.role_id WHERE u.email=?");
            $stmt->execute([$email]);
            $user = $stmt->fetch();
            if ($user && password_verify($pass, $user['password_hash'])) {
                if (!$user['is_active']) { $error = "Your account has been deactivated."; }
                else {
                    $_SESSION['user_id']    = $user['user_id'];
                    $_SESSION['user_name']  = $user['full_name'];
                    $_SESSION['user_email'] = $user['email'];
                    $_SESSION['user_role']  = $user['role_name'];
                    $_SESSION['role_id']    = $user['role_id'];
                    switch ($user['role_name']) {
                        case 'admin': header('Location: admin_dashboard.php'); break;
                        case 'owner': header('Location: owner_dashboard.php'); break;
                        default:      header('Location: customer_dashboard.php');
                    }
                    exit();
                }
            } else { $error = "Invalid email or password."; }
        } catch (PDOException $e) { error_log($e->getMessage()); $error = "Login failed. Please try again."; }
    } else { $error = implode(' ', $errs); }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Sign In — ReservInn</title>
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
      <p class="auth-visual__tagline">Your perfect resort stay starts here.</p>
      <p class="auth-visual__sub">Discover handpicked private resorts across the Philippines.</p>
    </div>
    <!-- Feature highlights -->
    <div style="position:relative;z-index:1;margin-top:48px;display:flex;flex-direction:column;gap:16px">
      <div style="display:flex;align-items:center;gap:12px">
        <div style="width:32px;height:32px;background:var(--rose);border-radius:8px;display:flex;align-items:center;justify-content:center;flex-shrink:0">
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2"><polyline points="20 6 9 17 4 12"/></svg>
        </div>
        <span style="font-size:.82rem;color:var(--pink-accent)">Book private resorts instantly</span>
      </div>
      <div style="display:flex;align-items:center;gap:12px">
        <div style="width:32px;height:32px;background:var(--rose);border-radius:8px;display:flex;align-items:center;justify-content:center;flex-shrink:0">
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2"><polyline points="20 6 9 17 4 12"/></svg>
        </div>
        <span style="font-size:.82rem;color:var(--pink-accent)">Flexible payment options</span>
      </div>
      <div style="display:flex;align-items:center;gap:12px">
        <div style="width:32px;height:32px;background:var(--rose);border-radius:8px;display:flex;align-items:center;justify-content:center;flex-shrink:0">
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2"><polyline points="20 6 9 17 4 12"/></svg>
        </div>
        <span style="font-size:.82rem;color:var(--pink-accent)">Handpicked resorts across the Philippines</span>
      </div>
    </div>
  </div>
  <div class="auth-form-area">
    <div class="auth-form-area__inner">
      <p class="section-eyebrow" style="margin-bottom:6px">Welcome back</p>
      <h1 class="auth-title">Sign in to ReservInn</h1>
      <p class="auth-subtitle">Enter your credentials to access your account.</p>
      <?php if ($error): ?>
        <div class="alert alert--error">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/></svg>
          <?php echo htmlspecialchars($error); ?>
        </div>
      <?php endif; ?>
      <form method="POST" action="">
        <div class="form-group">
          <label class="form-label" for="email">Email address</label>
          <input class="form-control" type="email" id="email" name="email"
                 value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>"
                 placeholder="you@example.com" required autofocus>
        </div>
        <div class="form-group">
          <label class="form-label" for="password">Password</label>
          <input class="form-control" type="password" id="password" name="password"
                 placeholder="Your password" required>
        </div>
        <button type="submit" class="btn btn--primary btn--block btn--lg" style="margin-top:4px">
          Sign in
        </button>
      </form>
      <div class="auth-divider">or</div>
      <div class="auth-link" style="margin-top:0">
        Don't have an account? <a href="register.php">Create one free</a>
      </div>
    </div>
  </div>
</div>
<script src="../js/script.js"></script>
</body>
</html>
