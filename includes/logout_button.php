<?php
declare(strict_types=1);
require_once __DIR__ . '/auth.php';
?>
<form method="post" action="<?= e(app_url('auth/logout.php')) ?>" class="d-inline">
  <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
  <button type="submit" class="btn btn-outline-light btn-sm">Log out</button>
</form>