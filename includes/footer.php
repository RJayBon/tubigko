</div>
    <footer class="foot">
      <span>&copy; <?= date('Y') ?> TubigKo Water Refilling Station System</span>
    </footer>
  </main>
</div>
<?php
$__pageJs = $PAGE_JS ?? [];
if (is_string($__pageJs)) $__pageJs = [$__pageJs];
if (!is_array($__pageJs)) $__pageJs = [];
$__appJsPath = __DIR__ . '/../assets/js/app.js';
?>
<script src="<?= e($BASE) ?>/assets/js/app.js?v=<?= @filemtime($__appJsPath) ?: time() ?>"></script>
<?php foreach ($__pageJs as $js):
    $jsRel  = ltrim((string)$js, '/');
    $jsPath = __DIR__ . '/../assets/js/' . $jsRel;
?>
<script src="<?= e($BASE) ?>/assets/js/<?= e($jsRel) ?>?v=<?= @filemtime($jsPath) ?: time() ?>"></script>
<?php endforeach; ?>
</body>
</html>