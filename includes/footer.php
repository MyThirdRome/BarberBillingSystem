    </main>
    
    <footer class="footer bg-light border-top mt-5">
        <div class="container-fluid py-3">
            <div class="row">
                <div class="col-md-6">
                    <small class="text-muted">
                        <?= APP_NAME ?> v<?= APP_VERSION ?>
                        &copy; <?= date('Y') ?>
                    </small>
                </div>
                <div class="col-md-6 text-end">
                    <small class="text-muted">
                        Connecté en tant que: <strong><?= htmlspecialchars($_SESSION['username']) ?></strong>
                        <?php if (isset($_SESSION['last_activity'])): ?>
                            | Dernière activité: <?= date('H:i', $_SESSION['last_activity']) ?>
                        <?php endif; ?>
                    </small>
                </div>
            </div>
        </div>
    </footer>
    
    <!-- Bootstrap JavaScript -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- Chart.js for statistics -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    
    <!-- Custom JavaScript -->
    <script src="assets/script.js"></script>
    
    <!-- Additional JavaScript for forms -->
    <script>
        // Form validation enhancement
        document.addEventListener('DOMContentLoaded', function() {
            // Auto-dismiss alerts after 5 seconds
            const alerts = document.querySelectorAll('.alert:not(.alert-permanent)');
            alerts.forEach(alert => {
                setTimeout(() => {
                    const alertInstance = new bootstrap.Alert(alert);
                    alertInstance.close();
                }, 5000);
            });
            
            // Confirm delete operations
            const deleteButtons = document.querySelectorAll('[data-action="delete"]');
            deleteButtons.forEach(button => {
                button.addEventListener('click', function(e) {
                    if (!confirm('Êtes-vous sûr de vouloir supprimer cet élément ?')) {
                        e.preventDefault();
                        return false;
                    }
                });
            });
            
            // Auto-format currency inputs
            const currencyInputs = document.querySelectorAll('input[type="number"][step="0.01"]');
            currencyInputs.forEach(input => {
                input.addEventListener('blur', function() {
                    if (this.value) {
                        this.value = parseFloat(this.value).toFixed(2);
                    }
                });
            });
            
            // Auto-format phone inputs
            const phoneInputs = document.querySelectorAll('input[type="tel"], input[name*="phone"]');
            phoneInputs.forEach(input => {
                input.addEventListener('input', function() {
                    // Remove non-numeric characters except +, -, (, ), and spaces
                    this.value = this.value.replace(/[^0-9\+\-\(\)\s]/g, '');
                });
            });
            
            // Form submission loading state
            const forms = document.querySelectorAll('form');
            forms.forEach(form => {
                form.addEventListener('submit', function() {
                    const submitButton = form.querySelector('button[type="submit"]');
                    if (submitButton) {
                        submitButton.disabled = true;
                        const originalText = submitButton.innerHTML;
                        submitButton.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Traitement...';
                        
                        // Re-enable after 10 seconds as fallback
                        setTimeout(() => {
                            submitButton.disabled = false;
                            submitButton.innerHTML = originalText;
                        }, 10000);
                    }
                });
            });
        });
        
        // Global functions
        function formatCurrency(amount) {
            return amount.toFixed(3) + ' TND';
        }
        
        function formatDate(dateString) {
            return new Date(dateString).toLocaleDateString('fr-FR');
        }
        
        function showToast(message, type = 'success') {
            const toast = document.createElement('div');
            toast.className = `toast align-items-center text-white bg-${type} border-0`;
            toast.setAttribute('role', 'alert');
            toast.innerHTML = `
                <div class="d-flex">
                    <div class="toast-body">${message}</div>
                    <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
                </div>
            `;
            
            document.body.appendChild(toast);
            const bsToast = new bootstrap.Toast(toast);
            bsToast.show();
            
            toast.addEventListener('hidden.bs.toast', () => {
                document.body.removeChild(toast);
            });
        }
    </script>
    
    <!-- CSRF Token for AJAX requests -->
    <script>
        window.csrfToken = '<?= generateCSRFToken() ?>';
    </script>
</body>
</html>
