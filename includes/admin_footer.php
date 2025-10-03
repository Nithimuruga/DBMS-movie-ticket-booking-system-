    </main>

    <!-- Admin Footer -->
    <footer class="admin-footer mt-auto py-3 bg-dark border-top border-secondary">
        <div class="container-fluid">
            <div class="row">
                <div class="col-md-6 text-center text-md-start">
                    <span class="text-muted">&copy; <?= date('Y') ?> <?= SITE_NAME ?> Admin Panel. All rights reserved.</span>
                </div>
                <div class="col-md-6 text-center text-md-end">
                    <span class="text-muted">Developed by Movie Booking System Team</span>
                </div>
            </div>
        </div>
    </footer>

    <!-- Bootstrap JS Bundle with Popper -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <!-- jQuery -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <!-- DataTables JS -->
    <script src="https://cdn.datatables.net/1.11.3/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.11.3/js/dataTables.bootstrap5.min.js"></script>
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <!-- SweetAlert for notifications -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <!-- Custom JS -->
    <script src="<?= SITE_URL ?>/assets/js/script.js"></script>
    
    <script>
    // Initialize DataTables where present
    $(document).ready(function() {
        if ($.fn.DataTable && $('.datatable').length > 0) {
            $('.datatable').DataTable({
                responsive: true,
                "pageLength": 25,
                "lengthMenu": [[10, 25, 50, -1], [10, 25, 50, "All"]],
                "language": {
                    "search": "Filter records:"
                }
            });
        }
        
        // Tooltips
        var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'))
        var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
            return new bootstrap.Tooltip(tooltipTriggerEl)
        });
    });
    </script>
</body>
</html>
