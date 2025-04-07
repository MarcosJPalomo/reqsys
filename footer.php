</div> <!-- Fin del main-content -->
    </div> <!-- Fin del wrapper -->
    
    <footer class="bg-light py-3 text-center mt-auto">
        <div class="container">
            <span class="text-muted">Sistema de Requisiciones © <?php echo date('Y'); ?></span>
        </div>
    </footer>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script>
        // Activar tooltips
        var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'))
        var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
            return new bootstrap.Tooltip(tooltipTriggerEl)
        });
        
        // Activar la opción de menú actual
        $(document).ready(function() {
            const currentPath = window.location.pathname;
            const filename = currentPath.substring(currentPath.lastIndexOf('/')+1);
            
            $('.nav-link').each(function() {
                const href = $(this).attr('href');
                if (href === filename) {
                    $(this).addClass('active');
                }
            });
        });
    </script>
</body>
</html>