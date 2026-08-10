    </main>

    <footer class="site-footer">
        <p><?= e(__('app.footer', ['year' => date('Y')])) ?></p>
    </footer>
    <?php
    if (!empty($pageScriptsBeforeApp)) {
        echo '<script>' . $pageScriptsBeforeApp . "</script>\n";
    }
    ?>
    <script src="<?= e(assetUrl('assets/js/app.js')) ?>"></script>
    <!-- ISS base_path=<?= e(appBasePath() ?: '/') ?> -->
</body>
</html>
