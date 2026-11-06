<?php
/**
 * Sign in. One generic failure message for both a bad email and a bad
 * password, so the form cannot be used to discover which addresses exist.
 */

declare(strict_types=1);
require_once __DIR__ . '/includes/functions.php';

if (is_logged_in()) {
    redirect(is_admin() ? 'admin/dashboard.php' : 'menu.php');
}

$errors = [];
$email  = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (!csrf_valid()) {
        $errors['form'] = 'That form expired. Try again.';
    } else {
        $email    = trim((string)($_POST['email'] ?? ''));
        $password = (string)($_POST['password'] ?? '');

        if ($email === '' || $password === '') {
            $errors['form'] = 'Enter your email and password.';
        } else {
            $stmt = db()->prepare('SELECT id, name, email, password, role FROM users WHERE email = ?');
            $stmt->execute([$email]);
            $user = $stmt->fetch();

            if ($user && password_verify($password, $user['password'])) {

                // Re-hash if PHP's default cost has moved on since sign up
                if (password_needs_rehash($user['password'], PASSWORD_DEFAULT)) {
                    $newHash = password_hash($password, PASSWORD_DEFAULT);
                    db()->prepare('UPDATE users SET password = ? WHERE id = ?')
                        ->execute([$newHash, $user['id']]);
                }

                login_user($user);
                flash('Signed in as ' . $user['name'] . '.');

                $next = $_SESSION['redirect_after_login'] ?? null;
                unset($_SESSION['redirect_after_login']);

                if ($next && str_starts_with($next, BASE_URL . '/')) {
                    header('Location: ' . $next);
                    exit;
                }
                redirect($user['role'] === 'admin' ? 'admin/dashboard.php' : 'menu.php');
            }

            $errors['form'] = 'That email and password do not match an account.';
        }
    }
}

$pageTitle = 'Sign in';
require __DIR__ . '/includes/header.php';
?>

<div class="wrap">
  <div class="auth-card">
    <h1>Sign in</h1>
    <p class="muted" style="font-size:.92rem">Track your orders and check out faster.</p>

    <?php if (!empty($errors['form'])): ?>
      <div class="flash flash-error" role="alert"><?= e($errors['form']) ?></div>
    <?php endif; ?>

    <form method="post" action="<?= url('login.php') ?>" novalidate>
      <?= csrf_field() ?>

      <div class="field" style="margin-top:1.25rem">
        <label for="email">Email</label>
        <input type="email" id="email" name="email" value="<?= e($email) ?>" required autocomplete="email" autofocus>
      </div>

      <div class="field">
        <label for="password">Password</label>
        <input type="password" id="password" name="password" required autocomplete="current-password">
      </div>

      <button class="btn btn-primary btn-block" type="submit">Sign in</button>
    </form>

    <p class="auth-alt">New here? <a href="<?= url('register.php') ?>">Create an account</a></p>

    <div class="demo-creds">
      <p style="margin:0 0 .35rem"><strong>Demo accounts</strong></p>
      <p style="margin:0">Customer &mdash; <code>customer@bistro.test</code> / <code>customer123</code></p>
      <p style="margin:0">Staff &mdash; <code>admin@bistro.test</code> / <code>admin123</code></p>
    </div>
  </div>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
