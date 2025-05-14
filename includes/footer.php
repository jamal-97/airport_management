</main>
    
    <footer class="bg-dark text-white mt-5 py-4">
        <div class="container">
            <div class="row">
                <div class="col-md-4">
                    <h5>About <?php echo APP_NAME; ?></h5>
                    <p>A comprehensive airport management system designed to streamline operations, enhance passenger experience, and optimize resource utilization.</p>
                </div>
                <div class="col-md-4">
                    <h5>Quick Links</h5>
                    <ul class="list-unstyled">
                        <li><a href="<?php echo APP_URL; ?>/index.php" class="text-white">Home</a></li>
                        <li><a href="<?php echo APP_URL; ?>/about.php" class="text-white">About Us</a></li>
                        <li><a href="<?php echo APP_URL; ?>/contact.php" class="text-white">Contact Us</a></li>
                        <li><a href="<?php echo APP_URL; ?>/faq.php" class="text-white">FAQs</a></li>
                    </ul>
                </div>
                <div class="col-md-4">
                    <h5>Contact Us</h5>
                    <address>
                        <i class="fas fa-map-marker-alt"></i> Airport Authority Building,<br>
                        International Airport Road,<br>
                        Dhaka, Bangladesh, 12345<br>
                        <i class="fas fa-phone"></i> +1 234 567 8901<br>
                        <i class="fas fa-envelope"></i> info@airport-system.com
                    </address>
                </div>
            </div>
            <hr class="bg-light">
            <div class="row">
                <div class="col-md-6">
                    <p>&copy; <?php echo date('Y'); ?> <?php echo APP_NAME; ?>. All rights reserved.</p>
                </div>
                <div class="col-md-6 text-md-right">
                    <ul class="list-inline mb-0">
                        <li class="list-inline-item"><a href="<?php echo APP_URL; ?>/privacy.php" class="text-white">Privacy Policy</a></li>
                        <li class="list-inline-item"><a href="<?php echo APP_URL; ?>/terms.php" class="text-white">Terms of Service</a></li>
                    </ul>
                </div>
            </div>
        </div>
    </footer>
    
    <!-- jQuery first, then Popper.js, then Bootstrap JS -->
    <script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/popper.js@1.16.1/dist/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
    
    <!-- Custom JavaScript -->
    <script src="<?php echo APP_URL; ?>/js/script.js"></script>
</body>
</html>