        </main>
    </div>
    <script>window.SN_APP = { url: <?= json_encode(APP_URL) ?> };</script>
    <script src="<?= APP_URL ?>/assets/js/app.js"></script>
    <?= $extraScripts ?? '' ?>
</body>
</html>
