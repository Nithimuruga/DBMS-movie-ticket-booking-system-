    </main>

    <!-- Footer -->
    <footer class="footer mt-auto py-4 bg-dark border-top border-secondary">
        <div class="container">
            <div class="row">
                <div class="col-md-4 mb-4 mb-md-0">
                    <h5 class="text-primary mb-3"><?= SITE_NAME ?></h5>
                    <p class="text-muted">Experience the best of cinema with our premium movie booking platform. Easy booking, great movies, and comfortable theaters!</p>
                    <div class="social-icons">
                        <a href="#" class="text-light me-2"><i class="fab fa-facebook-f"></i></a>
                        <a href="#" class="text-light me-2"><i class="fab fa-twitter"></i></a>
                        <a href="#" class="text-light me-2"><i class="fab fa-instagram"></i></a>
                        <a href="#" class="text-light"><i class="fab fa-youtube"></i></a>
                    </div>
                </div>
                <div class="col-md-2 mb-4 mb-md-0">
                    <h6 class="text-light mb-3">Quick Links</h6>
                    <ul class="list-unstyled footer-links">
                        <li><a href="<?= SITE_URL ?>/frontend/index.php" class="text-muted">Home</a></li>
                        <li><a href="#" class="text-muted">Movies</a></li>
                        <li><a href="#" class="text-muted">Theaters</a></li>
                        <li><a href="#" class="text-muted">Offers</a></li>
                        <li><a href="#" class="text-muted">Gift Cards</a></li>
                    </ul>
                </div>
                <div class="col-md-3 mb-4 mb-md-0">
                    <h6 class="text-light mb-3">Helpful Links</h6>
                    <ul class="list-unstyled footer-links">
                        <li><a href="#" class="text-muted">About Us</a></li>
                        <li><a href="#" class="text-muted">Contact Us</a></li>
                        <li><a href="#" class="text-muted">Terms & Conditions</a></li>
                        <li><a href="#" class="text-muted">Privacy Policy</a></li>
                        <li><a href="#" class="text-muted">FAQs</a></li>
                    </ul>
                </div>
                <div class="col-md-3">
                    <h6 class="text-light mb-3">Contact Us</h6>
                    <ul class="list-unstyled footer-links">
                        <li class="text-muted"><i class="fas fa-map-marker-alt me-2"></i> 123 Cinema Street, Movie City</li>
                        <li class="text-muted"><i class="fas fa-phone me-2"></i> +91 9876543210</li>
                        <li class="text-muted"><i class="fas fa-envelope me-2"></i> <?= ADMIN_EMAIL ?></li>
                    </ul>
                </div>
            </div>
            <hr class="border-secondary">
            <div class="row">
                <div class="col-md-6 text-center text-md-start">
                    <p class="mb-0 text-muted">&copy; <?= date('Y') ?> <?= SITE_NAME ?>. All rights reserved.</p>
                </div>
                <div class="col-md-6 text-center text-md-end">
                    <p class="mb-0 text-muted">Designed with <i class="fas fa-heart text-danger"></i> by Movie Lovers</p>
                </div>
            </div>
        </div>
    </footer>

    <!-- Bootstrap JS Bundle with Popper -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <!-- SweetAlert for notifications -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <!-- Custom JS -->
    <script src="<?= SITE_URL ?>/assets/js/script.js"></script>
</body>
</html>
