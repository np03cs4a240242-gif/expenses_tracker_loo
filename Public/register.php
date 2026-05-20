<?php
declare(strict_types=1);

require_once __DIR__ . '/../Src/bootstrap_form.php';

if (auth_user()) {
  redirect(base_url('/dashboard.php'));
}

$title = 'Register';
$errors = ['name' => '', 'email' => '', 'password' => '', 'general' => ''];

if (is_post()) {
  csrf_verify();

  $name = str_trim($_POST['name'] ?? '');
  $email = strtolower(str_trim($_POST['email'] ?? ''));
  $password = (string)($_POST['password'] ?? '');

  if ($name === '' || mb_strlen($name) < 2) $errors['name'] = 'Name must be at least 2 characters.';
  if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors['email'] = 'Enter a valid email.';
  if (strlen($password) < 6) $errors['password'] = 'Password must be at least 6 characters.';

  if (!$errors['name'] && !$errors['email'] && !$errors['password']) {
    if (find_user_by_email($email)) {
      $errors['email'] = 'Email already registered. Try logging in.';
    } else {
      try {
        $uid = create_user($name, $email, $password);
        $user = find_user_by_id($uid);

        if (!$user) {
          throw new RuntimeException('User not found after registration.');
        }

        start_otp_flow($user, 'register');
        flash_set('success', 'Account created. Enter the code to verify your email.');
        redirect(base_url('/verify_otp.php'));
      } catch (Throwable $e) {
        $errors['general'] = 'Something went wrong. Please try again.';
      }
    }
  }
}

require_once __DIR__ . '/../Src/partials/header.php';
?>

<div style="max-width:440px; margin: 0 auto; padding-top: var(--space-6);">
  <div style="text-align:center; margin-bottom: var(--space-6);">
    <div style="width:48px;height:48px;display:grid;place-items:center;border-radius:var(--radius-sm);background:var(--fg);color:var(--bg);font-weight:800;font-size:16px;margin:0 auto var(--space-3);">ET</div>
    <h1 style="margin-bottom:4px;">Create account</h1>
    <p class="muted">Start tracking your expenses in a simple way</p>
  </div>

  <div class="card" style="padding: var(--space-6);">
    <?php if ($errors['general']): ?>
      <div class="alert"><?= e($errors['general']) ?></div>
    <?php endif; ?>

    <form method="post" class="form">
      <?= csrf_field() ?>

      <div class="field">
        <label>Name</label>
        <input name="name" value="<?= e($_POST['name'] ?? '') ?>" placeholder="Your name" required>
        <?php if ($errors['name']): ?><div class="hint error"><?= e($errors['name']) ?></div><?php endif; ?>
      </div>

      <div class="field">
        <label>Email</label>
        <input type="email" name="email" value="<?= e($_POST['email'] ?? '') ?>" placeholder="you@example.com" required>
        <?php if ($errors['email']): ?><div class="hint error"><?= e($errors['email']) ?></div><?php endif; ?>
      </div>

      <div class="field">
        <label>Password</label>
        <input type="password" name="password" placeholder="At least 6 characters" required>
        <?php if ($errors['password']): ?><div class="hint error"><?= e($errors['password']) ?></div><?php endif; ?>
      </div>

      <button class="btn btn-primary btn-block" type="submit" style="margin-top: var(--space-2);">Create account</button>
    </form>
  </div>

  <div style="text-align:center; margin-top: var(--space-4);">
    <p class="small muted">
      Already have an account? <a href="<?= e(base_url('/login.php')) ?>">Sign in</a>
    </p>
  </div>
</div>

<?php require_once __DIR__ . '/../Src/partials/footer.php'; ?>
