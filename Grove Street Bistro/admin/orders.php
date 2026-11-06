<?php
/**
 * Kitchen queue. Tickets and status changes both go over Ajax, and the list
 * refreshes on a timer so a new order appears without anyone touching the page.
 */

declare(strict_types=1);
require_once __DIR__ . '/../includes/functions.php';

require_admin();

$pageTitle   = 'Order queue';
$pageScripts = ['admin-orders.js'];
require __DIR__ . '/../includes/header.php';
?>

<div class="wrap section">

  <div class="page-head">
    <div>
      <p class="eyebrow">Staff</p>
      <h1>Order queue</h1>
    </div>
    <p class="muted" style="margin:0;font-size:.88rem">
      Refreshed <span id="last-refresh">just now</span>
    </p>
  </div>

  <div class="stat-grid" id="status-counts"></div>

  <div class="toolbar">
    <label class="sr-only" for="filter-status">Filter by status</label>
    <select id="filter-status">
      <option value="active">Open tickets</option>
      <option value="">All orders</option>
      <option value="Received">Received</option>
      <option value="Preparing">Preparing</option>
      <option value="Ready">Ready</option>
      <option value="Completed">Completed</option>
      <option value="Cancelled">Cancelled</option>
    </select>

    <button class="btn btn-ghost btn-sm" type="button" id="refresh-now">Refresh</button>

    <span class="spacer"></span>

    <div class="checkbox-row">
      <input type="checkbox" id="auto-refresh" checked>
      <label for="auto-refresh">Auto refresh</label>
    </div>
  </div>

  <div class="data-table-wrap">
    <table class="data-table">
      <thead>
        <tr>
          <th style="width:80px">Order</th>
          <th>Customer</th>
          <th>Items</th>
          <th>Placed</th>
          <th class="col-right">Total</th>
          <th style="width:160px">Status</th>
        </tr>
      </thead>
      <tbody id="orders-body">
        <tr><td colspan="6" class="muted">Loading tickets…</td></tr>
      </tbody>
    </table>
  </div>
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>
