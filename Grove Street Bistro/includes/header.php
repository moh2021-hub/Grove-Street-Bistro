<?php
/**
 * Shared page top. A page sets $pageTitle and optionally $bodyClass before
 * including this file.
 */
require_once __DIR__ . '/functions.php';

$user      = current_user();
$pageTitle = $pageTitle ?? APP_NAME;
$bodyClass = $bodyClass ?? '';
$flashes   = take_flashes();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($pageTitle) ?> &middot; <?= e(APP_NAME) ?></title>

<!-- Read by assets/js/app.js so every fetch() can send the CSRF token -->
<meta name="csrf-token" content="<?= e(csrf_token()) ?>">
<meta name="base-url" content="<?= e(BASE_URL) ?>">

<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,400;9..144,600;9..144,700&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= url('assets/css/style.css') ?>">
</head>
<body class="<?= e($bodyClass) ?>">

<a class="skip-link" href="#main">Skip to content</a>

<header class="site-header">
  <div class="wrap header-inner">
    <a class="brand" href="<?= url('index.php') ?>">
      <span class="brand-mark" aria-hidden="true">GS</span>
      <span class="brand-name"><?= e(APP_NAME) ?></span>
    </a>

    <button class="nav-toggle" type="button" aria-expanded="false" aria-controls="site-nav">
      <span class="nav-toggle-bar" aria-hidden="true"></span>
      <span class="sr-only">Show navigation</span>
    </button>

    <nav class="site-nav" id="site-nav" aria-label="Main">
      <a href="<?= url('menu.php') ?>">Menu</a>

      <?php if ($user && $user['role'] === 'admin'): ?>
        <a href="<?= url('admin/dashboard.php') ?>">Dashboard</a>
        <a href="<?= url('admin/menu.php') ?>">Manage menu</a>
        <a href="<?= url('admin/orders.php') ?>">Orders</a>
      <?php elseif ($user): ?>
        <a href="<?= url('orders.php') ?>">My orders</a>
      <?php endif; ?>

      <?php if (!$user || $user['role'] !== 'admin'): ?>
        <a class="cart-link" href="<?= url('cart.php') ?>">
          Cart
          <span class="cart-badge<?= cart_count() ? '' : ' is-empty' ?>" data-cart-badge><?= cart_count() ?></span>
        </a>
      <?php endif; ?>

      <?php if ($user): ?>
        <span class="nav-user">
          <span class="nav-user-name"><?= e($user['name']) ?></span>
          <?php if ($user['role'] === 'admin'): ?><span class="tag tag-admin">Staff</span><?php endif; ?>
        </span>
        <a class="btn btn-ghost btn-sm" href="<?= url('logout.php') ?>">Sign out</a>
      <?php else: ?>
        <a class="btn btn-ghost btn-sm" href="<?= url('login.php') ?>">Sign in</a>
        <a class="btn btn-primary btn-sm" href="<?= url('register.php') ?>">Create account</a>
      <?php endif; ?>
    </nav>
  </div>
</header>

<?php if ($flashes): ?>
  <div class="wrap flash-stack">
    <?php foreach ($flashes as $f): ?>
      <div class="flash flash-<?= e($f['type']) ?>" role="status"><?= e($f['message']) ?></div>
    <?php endforeach; ?>
  </div>
<?php endif; ?>

<main id="main">
