<?php
// Ensure no whitespace before this opening PHP tag.
// It's crucial for header() functions to work correctly.

require_once __DIR__ . '/db_connection.php';
require_once __DIR__ . '/functions.php';
 // Ensure this file contains your query functions
// Start session here if it's not already started in db_connection.php or functions.php
// It should be the first thing in the script if you plan to use sessions.
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($page_title) ? $page_title . ' - ' : ''; ?>Cemma</title>
    <link rel="stylesheet" href="/blog_website_complete/blog_website/assets/css/style.css?v=<?php echo time(); ?>">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body>
    <!-- Global Loader HTML -->
    <div id="globalLoader" class="global-loader">
        <div class="loader-c"></div>
    </div>

    <header class="header">
        <nav class="navbar">
            <div class="nav-container">
                <div class="nav-logo">
                    <a href="/blog_website_complete/blog_website/index.php">
                        <img  src="/blog_website_complete/blog_website/assets/images/logo.svg" alt="Cemma" class="logo">
                    </a>
                </div>
                
                <ul class="nav-menu">
                    <li class="nav-item">
                        <a href="/blog_website_complete/blog_website/index.php" class="nav-link">Home</a>
                    </li>
                    <li class="nav-item dropdown">
                        <a href="#" class="nav-link dropdown-toggle">Categories <i class="fas fa-chevron-down"></i></a>
                        <ul class="dropdown-menu">
                            <?php
                            $categories = get_all_categories($conn);
                            foreach ($categories as $category) {
                                echo '<li><a href="/blog_website_complete/blog_website/category.php?id=' . $category['id'] . '">' . htmlspecialchars($category['name']) . '</a></li>';
                            }
                            ?>
                        </ul>
                    </li>
                    <li class="nav-item">
                        <a href="/blog_website_complete/blog_website/about.php" class="nav-link">About</a>
                    </li>
                    <li class="nav-item">
                        <a href="/blog_website_complete/blog_website/contact.php" class="nav-link">Contact</a>
                    </li>
                </ul>
                
                <div class="nav-actions">

                    <!-- Theme Switcher Button -->
                    <div class="theme-switcher" id="themeSwitcher">
                        <i class="fas fa-sun"></i>
                        <i class="fas fa-moon"></i>
                    </div>

                    <form class="search-form" action="/blog_website_complete/blog_website/search.php" method="GET">
                        <input type="text" name="q" placeholder="Search posts..." class="search-input">
                        <button type="submit" class="search-btn"><i class="fas fa-search"></i></button>
                    </form>
                    
                    <!-- Hamburger User Menu Button -->
                    <div class="user-menu-container">
                        <button id="userMenuBtn" class="user-menu-btn" aria-label="User Menu">
                            <i class="fas fa-user"></i>
                            <i class="fas fa-chevron-down"></i>
                        </button>
                        <div id="userDropdown" class="user-dropdown-menu">
                            <?php if (is_logged_in()): ?>
                                <a href="/blog_website_complete/blog_website/create_post.php" class="dropdown-link"><i class="fas fa-plus"></i> Create Post</a>
                                <a href="/blog_website_complete/blog_website/dashboard.php" class="dropdown-link"><i class="fas fa-tachometer-alt"></i> Dashboard</a>
                                <a href="/blog_website_complete/blog_website/candid/index.php" class="dropdown-link"><i class="fas fa-users"></i> Candid</a>
                                <?php if (is_admin()): ?>
                                    <a href="/blog_website_complete/blog_website/admin_dashboard.php" class="dropdown-link"><i class="fas fa-user-shield"></i> Admin</a>
                                <?php endif; ?>
                                <a href="/blog_website_complete/blog_website/logout.php" class="dropdown-link"><i class="fas fa-sign-out-alt"></i> Logout</a>
                            <?php else: ?>
                                <a href="/blog_website_complete/blog_website/login.php" class="dropdown-link"><i class="fas fa-sign-in-alt"></i> Login</a>
                                <a href="/blog_website_complete/blog_website/register.php" class="dropdown-link"><i class="fas fa-user-plus"></i> Register</a>
                            <?php endif; ?>
                        </div>
                    </div>
                    <style>
                    .user-menu-container {
                        position: relative;
                        display: inline-block;
                    }
                    .user-menu-btn {
                        background: none;
                        border: none;
                        color: #4a5568;
                        font-size: 1.3rem;
                        cursor: pointer;
                        display: flex;
                        align-items: center;
                        gap: 4px;
                        padding: 8px 10px;
                        border-radius: 5px;
                        transition: background 0.2s;
                    }
                    .user-menu-btn:hover, .user-menu-btn:focus {
                        background: #f0f4fa;
                        outline: none;
                    }
                    .user-dropdown-menu {
                        display: none;
                        position: absolute;
                        right: 0;
                        top: 110%;
                        min-width: 170px;
                        background: var(--background-gradient, #fff);
                        border: 1px solid #e2e8f0;
                        border-radius: 7px;
                        box-shadow: 0 4px 16px rgba(0,0,0,0.07);
                        z-index: 100;
                        padding: 8px 0;
                    }
                    .user-dropdown-menu .dropdown-link {
                        display: flex;
                        align-items: center;
                        gap: 8px;
                        padding: 10px 18px;
                        color: var(--text-color, #2d3748);
                        text-decoration: none;
                        font-size: 1rem;
                        transition: background 0.18s, color 0.18s;
                    }
                    .user-dropdown-menu .dropdown-link:hover {
                        background: #f1f5fb;
                        color: #2563eb;
                    }
                    </style>
                    <script>
                    document.addEventListener('DOMContentLoaded', function() {
                        const btn = document.getElementById('userMenuBtn');
                        const menu = document.getElementById('userDropdown');
                        let menuOpen = false;

                        btn.addEventListener('click', function(e) {
                            e.stopPropagation();
                            menu.style.display = menuOpen ? 'none' : 'block';
                            menuOpen = !menuOpen;
                        });

                        // Hide dropdown when clicking outside
                        document.addEventListener('click', function(e) {
                            if (menuOpen && !btn.contains(e.target) && !menu.contains(e.target)) {
                                menu.style.display = 'none';
                                menuOpen = false;
                            }
                        });

                        // Optional: Hide dropdown on ESC key
                        document.addEventListener('keydown', function(e) {
                            if (e.key === "Escape" && menuOpen) {
                                menu.style.display = 'none';
                                menuOpen = false;
                            }
                        });
                    });
                    </script>
                </div>
                
                <form id="search-form" class="search-form-phone" action="/blog_website_complete/blog_website/search.php" method="GET">
                        <input type="text" name="q" placeholder="Search posts..." class="search-input">
                        <button type="submit" class="search-btn"><i class="fas fa-search"></i></button>
                </form>

                <div class="hamburger">
                    <span class="bar"></span>
                    <span class="bar"></span>
                    <span class="bar"></span>
                </div>
            </div>
        </nav>
    </header>
    <nav class="side-nav" id="sideNav">
        <ul>
            <li style="display: flex; align-items: center;">
                <a href="/blog_website_complete/blog_website/index.php" style="margin-right: 10px;">Home</a>
                <div class="theme-switcher" id="themeSwitcherSide" style="display: flex; align-items: center;">
                    <i class="fas fa-sun"></i>
                    <i class="fas fa-moon"></i>
                </div>
            </li>
            <li class="nav-item dropdown">
                <a href="#" class="nav-link dropdown-toggle">Categories <i class="fas fa-chevron-down"></i></a>
                <ul class="dropdown-menu">
                    <?php
                    // Ensure get_all_categories($conn) is defined and returns results
                    $categories = get_all_categories($conn);
                    if ($categories) { // Check if categories result is not null/false
                        foreach ($categories as $category) {
                            echo '<li><a href="category.php?id=' . $category['id'] . '">' . htmlspecialchars($category['name']) . '</a></li>';
                        }
                    }
                    ?>
                </ul>
            </li>
            <li><a href="/blog_website_complete/blog_website/about.php">About</a></li>
            <li><a href="/blog_website_complete/blog_website/contact.php">Contact</a></li>
            <li style="display: flex; flex-direction: column; gap: 10px; margin-top: 20px;">
                <?php if (is_logged_in()): ?>
                    <a href="/blog_website_complete/blog_website/create_post.php" class="btn btn-primary" style="background: rgb(92, 150, 250); color: #fff; padding: 10px 18px; border-radius: 5px; text-align: center; margin-bottom: 5px;">Create Post</a>
                    <a href="/blog_website_complete/blog_website/dashboard.php" class="btn btn-secondary" style="background: #4a5568; color: #fff; padding: 10px 18px; border-radius: 5px; text-align: center; margin-bottom: 5px;">Dashboard</a>
                    <!-- COMMUNITY BUTTON ADDED HERE FOR MOBILE NAV -->
                    <a href="/blog_website_complete/blog_website/candid/index.php" class="btn btn-primary" style="background:rgb(255, 217, 0); color: #fff; padding: 10px 18px; border-radius: 5px; text-align: center; margin-bottom: 5px;><i class="fas fa-users"></i> Candid</a>
                    <?php if (is_admin()): ?>
                        <a href="/blog_website_complete/blog_website/admin_dashboard.php" class="btn btn-admin" style="background: #e53e3e; color: #fff; padding: 10px 18px; border-radius: 5px; text-align: center; margin-bottom: 5px;">Admin</a>
                    <?php endif; ?>
                    <a href="/blog_website_complete/blog_website/logout.php" class="btn btn-outline" style="background: transparent; color: rgb(213, 229, 255); border: 1px solid rgb(213, 229, 255); padding: 10px 18px; border-radius: 5px; text-align: center;">Logout</a>
                <?php else: ?>
                    <a href="/blog_website_complete/blog_website/login.php" class="btn btn-outline" style="background: transparent; color:rgb(213, 229, 255); border: 1px solid rgb(213, 229, 255); padding: 10px 18px; border-radius: 5px; text-align: center; margin-bottom: 5px;">Login</a>
                    <a href="/blog_website_complete/blog_website/register.php" class="btn btn-primary" style="background:rgb(92, 150, 250); color: #fff; padding: 10px 18px; border-radius: 5px; text-align: center;">Register</a>
                <?php endif; ?>
            </li>
        </ul>
    </nav>
    <div class="overlay" id="overlay"></div>
    
    <main class="main-content">