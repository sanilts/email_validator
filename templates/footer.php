<!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- Chart.js for analytics -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    
    <!-- Custom JS -->
    <script src="<?php echo BASE_URL; ?>assets/js/main.js"></script>
    
    <?php if (isset($additional_js)): ?>
        <?php foreach ($additional_js as $js_file): ?>
            <script src="<?php echo BASE_URL . $js_file; ?>"></script>
        <?php endforeach; ?>
    <?php endif; ?>
    
    <script>
        // Global configuration
        window.APP_CONFIG = {
            baseUrl: '<?php echo BASE_URL; ?>',
            userId: <?php echo $_SESSION['user_id'] ?? 'null'; ?>,
            userRole: '<?php echo $_SESSION['role'] ?? 'guest'; ?>',
            csrfToken: '<?php echo generate_csrf_token(); ?>'
        };
    </script>
</body>
</html>
