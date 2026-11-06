<?php
/**
 * Customer sign up. The role column is hard coded to 'customer' here, so a
 * crafted form post cannot create a staff account.
 */

declare(strict_types=1);
require_once __DIR__ . '/includes/functions.php';

if (is_logged_in()) {
    redirect('menu.php');
}

$errors = [];
$name   = '';
$email  = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (!csrf_valid()) {
        $errors['form'] = 'That form expired. Try again.';
    } else {
        $name     = trim((string)($_POST['name'] ?? ''));
        $email    = trim((string)($_POST['email'] ?? ''));
        $password = (string)($_POST['password'] ?? '');
        $confirm  = (string)($_POST['password_confirm'] ?? '');

        if ($name === '' || mb_strlen($name) > 100) {
            $errors['name'] = 'Enter your name, up to 100 characters.';
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || mb_strlen($email) > 190) {
            $errors['email'] = 'Enter a valid email address.';
        }
        if (strlen($password) < 8) {
            $errors['password'] = 'Use at least 8 characters.';
        }
        if ($password !== $confirm) {
            $errors['password_confirm'] = 'The two passwords do not match.';
        }

        if (!$errors) {
            $exists = db()->prepare('SELECT 1 FROM users WHERE email = ?');
            $exists->execute([$email]);

            if ($exists->fetchColumn()) {
                $errors['email'] = 'There is already an account with that email.';
            } else {
                $hash = password_hash($password, PASSWORD_DEFAULT);

                $stmt = db()->prepare(
                    "INSERT INTO users (name, email, password, role) VALUES (?, ?, ?, 'customer')"
                );
                $stmt->execute([$name, $email, $hash]);

                login_user([
                    'id'    => (int)db()->lastInsertId(),
                    'name'  => $name,
                    'email' => $email,
                    'role'  => 'customer',
                ]);

                flash('Welcome, ' . $name . '. Your account is ready.');
                redirect('menu.php');
            }
        }
    }
}

$pageTitle = 'Create an account';
require __DIR__ . '/includes/header.php';
?>

<div class="wrap">
  <div class="auth-card">
    <h1>Create an account</h1>
    <p class="muted" style="font-size:.92rem">You only need this to place an order.</p>

    <?php if (!empty($errors['form'])): ?>
      <div class="flash flash-error" role="alert"><?= e($errors['form']) ?></div>
    <?php endif; ?>

    <form method="post" action="<?= url('register.php') ?>" novalidate>
      <?= csrf_field() ?>

      <div class="field" style="margin-top:1.25rem">
        <label for="name">Name</label>
        <input type="text" id="name" name="name" value="<?= e($name) ?>" required
               autocomplete="name" <?= isset($errors['name']) ? 'aria-invalid="true"' : '' ?>>
        <?php if (isset($errors['name'])): ?><p class="field-error"><?= e($errors['name']) ?></p><?php endif; ?>
      </div>

      <div class="field">
        <label for="email">Email</label>
        <input type="email" id="email" name="email" value="<?= e($email) ?>" required
               autocomplete="email" <?= isset($errors['email']) ? 'aria-invalid="true"' : '' ?>>
        <?php if (isset($errors['email'])): ?><p class="field-error"><?= e($errors['email']) ?></p><?php endif; ?>
      </div>

      <div class="field">
        <label for="password">Password</label>
        <input type="password" id="password" name="password" required minlength="8"
               autocomplete="new-password" <?= isset($errors['password']) ? 'aria-invalid="true"' : '' ?>>
        <p class="hint">At least 8 characters.</p>
        <?php if (isset($errors['password'])): ?><p class="field-error"><?= e($errors['password']) ?></p><?php endif; ?>
      </div>

      <div class="field">
        <label for="password_confirm">Confirm password</label>
        <input type="password" id="password_confirm" name="password_confirm" required
               autocomplete="new-password" <?= isset($errors['password_confirm']) ? 'aria-invalid="true"' : '' ?>>
        <?php if (isset($errors['password_confirm'])): ?><p class="field-error"><?= e($errors['password_confirm']) ?></p><?php endif; ?>
      </div>

      <button class="btn btn-primary btn-block" type="submit">Create account</button>
    </form>

    <p class="auth-alt">Already have one? <a href="<?= url('login.php') ?>">Sign in</a></p>
  </div>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
