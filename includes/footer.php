    </main>

    <footer class="site-footer">
        <div class="container footer-inner">
            <p>© <?php echo date('Y'); ?> MiniShop — Boutique simple et fonctionnelle.</p>
        </div>
    </footer>

    <script src="assets/js/app.js?v=4"></script>
    <script>
        if ('serviceWorker' in navigator) {
            window.addEventListener('load', function() {
                navigator.serviceWorker.register('./sw.js', { updateViaCache: 'none' }).catch(function(error) {
                    console.error('Enregistrement du service worker impossible.', error);
                });
            });
        }
    </script>
</body>
</html>
