<?php
// db_connect.php

// Database configuration
define('DB_HOST', 'localhost');
define('DB_USERNAME', 'root');
define('DB_PASSWORD', '');
define('DB_NAME', 'blog_db');

// Create connection
$conn = new mysqli(DB_HOST, DB_USERNAME, DB_PASSWORD, DB_NAME);

// Check connection
if ($conn->connect_error) {
    // Log the error instead of dying directly
    error_log("Failed to connect to MySQL: " . $conn->connect_error);
    // Optionally, you can also send a JSON error response here if this file
    // were ever accessed directly, but typically, the calling script (like_post.php)
    // would handle the failure of the $conn object.
    // For now, exit to prevent further execution if connection fails.
    exit(); 
}

// Set charset to utf8mb4 for proper emoji and special character handling
// It's generally better to use utf8mb4 over utf8 for broader character support.
$conn->set_charset("utf8mb4"); 

// No closing  tag to prevent accidental whitespace output.
// This is critical for AJAX responses expecting pure JSON.
