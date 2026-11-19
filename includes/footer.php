<?php
$scriptName = str_replace('\\', '/', (string) ($_SERVER['SCRIPT_NAME'] ?? ''));
$basePath   = (str_contains($scriptName, '/student/')
            || str_contains($scriptName, '/auth/')
            || str_contains($scriptName, '/lecturer/'))
            ? '../' : '';
?>
<footer class="site-footer">
  <div class="site-wrap">
    <div class="footer-inner">
      <div>
        <a class="footer-brand" href="<?= $basePath ?>index.php">
          <span class="brand-mark" aria-hidden="true"><i></i><i></i><i></i></span>
          ASATS
        </a>
        <p class="footer-tagline">Assignment tracking made clear.</p>
      </div>
      <p class="footer-copy">&copy; <?= date('Y') ?> ASATS. Built on XAMPP &middot; PHP &middot; MySQL.</p>
    </div>
  </div>
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"
        integrity="sha384-YvpcrYf0tY3lHB60NNkmXc4s9bIOgUxi8T/jzmE5xsj/MFHGHMvzV5b/+1pJLM"
        crossorigin="anonymous"></script>
<script src="<?= $basePath ?>assets/js/app.js"></script>
</body>
</html>
