<?php include(__DIR__ . '/../chatbot.php'); ?>
</main>

<footer class="footer">
    <div class="footer-container">
        <div class="footer-content">
            <div class="footer-section">
                <div class="nav-logo">
                    <a href="/blog_website_complete/blog_website/index.php">
                        <img style="width:90%" src="/blog_website_complete/blog_website/assets/images/logo.svg" alt="Cemma" class="logo">
                    </a>
                </div>
                <p>A place to share thoughts, ideas, and stories with the world.</p>
                <div class="social-links">
                    <a href="#" class="social-link"><i class="fab fa-facebook"></i></a>
                    <a href="#" class="social-link"><i class="fab fa-twitter"></i></a>
                    <a href="#" class="social-link"><i class="fab fa-linkedin"></i></a>
                    <a href="#" class="social-link"><i class="fab fa-instagram"></i></a>
                </div>
            </div>
            
            <div class="footer-section">
                <h4>Quick Links</h4>
                <ul class="footer-links">
                    <li><a href="/blog_website_complete/blog_website/index.php">Home</a></li>
                    <li><a href="/blog_website_complete/blog_website/about.php">About Us</a></li>
                    <li><a href="/blog_website_complete/blog_website/contact.php">Contact</a></li>
                    <li><a href="/blog_website_complete/blog_website/privacy.php">Privacy Policy</a></li>
                    <li><a href="/blog_website_complete/blog_website/terms.php">Terms of Service</a></li>
                </ul>
            </div>
            
            <div class="footer-section">
                <h4>Categories</h4>
                <ul class="footer-links">
                    <?php
                    $sql = "SELECT id, name FROM categories LIMIT 5";
                    $result = mysqli_query($conn, $sql);

                    if ($result && mysqli_num_rows($result) > 0) {
                        while ($category = mysqli_fetch_assoc($result)) {
                            echo '<li><a href="/blog_website_complete/blog_website/category.php?id=' . $category['id'] . '">' . htmlspecialchars($category['name']) . '</a></li>';
                        }
                    }
                    ?>
                </ul>
            </div>
            
            <div class="footer-section">
                <h4>Newsletter</h4>
                <p>Subscribe to get the latest posts delivered to your email.</p>
                <form class="newsletter-form">
                    <input type="email" placeholder="Your email address" class="newsletter-input">
                    <button type="submit" class="newsletter-btn">Subscribe</button>
                </form>
            </div>
        </div>   
        <div class="footer-bottom">
            <p>&copy; <?php echo date('Y'); ?> Cemma. All rights reserved.</p>
        </div>
    </div>
</footer>

<script src="/blog_website_complete/blog_website/assets/js/script.js"></script>
</body>
</html>
