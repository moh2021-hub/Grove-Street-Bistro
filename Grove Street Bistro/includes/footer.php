</main>

<footer class="site-footer">
  <div class="wrap footer-inner">
    <div>
      <p class="footer-brand"><?= e(APP_NAME) ?></p>
      <p class="footer-note">1420 Grove Street, Detroit MI &middot; Open 11am to 10pm daily</p>
    </div>
    <p class="footer-note">
      CSC 5750 term project &middot; PHP, MySQL, and vanilla JavaScript on AWS
    </p>
  </div>
</footer>

<!-- Live region for Ajax feedback -->
<div class="toast-stack" id="toast-stack" aria-live="polite" aria-atomic="false"></div>

<script src="<?= url('assets/js/app.js') ?>"></script>
<?php foreach (($pageScripts ?? []) as $script): ?>
  <script src="<?= url('assets/js/' . $script) ?>"></script>
<?php endforeach; ?>
</body>
</html>
