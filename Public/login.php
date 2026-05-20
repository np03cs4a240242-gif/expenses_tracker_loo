<?php
declare(strict_types=1);

require_once __DIR__ . '/../Src/bootstrap_form.php';

if (auth_user()) {
  redirect(base_url('/dashboard.php'));
}

$title = 'Login';
$errors = ['email' => '', 'password' => '', 'general' => ''];

if (is_post()) {
  csrf_verify();

  $email = strtolower(str_trim($_POST['email'] ?? ''));
  $password = (string)($_POST['password'] ?? '');

  if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors['email'] = 'Enter a valid email.';
  if ($password === '') $errors['password'] = 'Password is required.';

  if (!$errors['email'] && !$errors['password']) {
    $user = find_user_by_email($email);

    if (!$user || !password_verify($password, $user['password_hash'])) {
      $errors['general'] = 'Invalid email or password.';
    } else {
      start_otp_flow($user, 'login');

      if (!(bool)($user['is_verified'] ?? false)) {
        flash_set('warning', 'Your account is not verified yet. Enter the code to continue.');
      } else {
        flash_set('success', 'We sent a login code to your email.');
      }

      redirect(base_url('/verify_otp.php'));
    }
  }
}

require_once __DIR__ . '/../Src/partials/header.php';
?>

<div style="max-width:440px; margin: 0 auto; padding-top: var(--space-7);">
  <div style="text-align:center; margin-bottom: var(--space-7);">
    <div style="width:48px;height:48px;display:grid;place-items:center;border-radius:var(--radius-sm);background:var(--fg);color:var(--bg);font-weight:800;font-size:16px;margin:0 auto var(--space-3);">ET</div>
    <h1 style="margin-bottom:4px;">Welcome back</h1>
    <p class="muted">Sign in to your ExpenseTracker account</p>
  </div>

  <div class="card" style="padding: var(--space-7);">
    <?php if ($errors['general']): ?>
      <div class="alert"><?= e($errors['general']) ?></div>
    <?php endif; ?>

    <form method="post" class="form">
      <?= csrf_field() ?>

      <div class="field">
        <label>Email</label>
        <input type="email" name="email" value="<?= e($_POST['email'] ?? '') ?>" placeholder="you@example.com" required>
        <?php if ($errors['email']): ?><div class="hint error"><?= e($errors['email']) ?></div><?php endif; ?>
      </div>

      <div class="field">
        <label>Password</label>
        <input type="password" name="password" placeholder="Your password" required>
        <?php if ($errors['password']): ?><div class="hint error"><?= e($errors['password']) ?></div><?php endif; ?>
      </div>

      <button class="btn btn-primary btn-block" type="submit" style="margin-top: var(--space-2);">Continue</button>
    </form>
  </div>

  <div style="text-align:center; margin-top: var(--space-4);">
    <p class="small muted">
      New here? <a href="<?= e(base_url('/register.php')) ?>">Create an account</a>
      &nbsp;&middot;&nbsp;
      <a href="<?= e(base_url('/forgot_password.php')) ?>">Forgot password?</a>
    </p>
  </div>
</div>

<?php require_once __DIR__ . '/../Src/partials/footer.php'; ?>
