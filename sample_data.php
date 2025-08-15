<?php
// Sample data insertion script for testing
require_once 'includes/db_connection.php';

// Insert sample categories
$categories = [
    ['Technology', 'Posts about technology, programming, and digital trends'],
    ['Lifestyle', 'Posts about lifestyle, health, and personal development'],
    ['Travel', 'Travel experiences, tips, and destination guides'],
    ['Food', 'Recipes, restaurant reviews, and culinary adventures'],
    ['Business', 'Business insights, entrepreneurship, and career advice']
];

foreach ($categories as $category) {
    $stmt = $conn->prepare("INSERT IGNORE INTO categories (name, description) VALUES (?, ?)");
    $stmt->bind_param("ss", $category[0], $category[1]);
    $stmt->execute();
}

// Insert sample tags
$tags = ['PHP', 'JavaScript', 'Web Development', 'Tutorial', 'Tips', 'Review', 'Guide', 'News', 'Opinion', 'How-to'];

foreach ($tags as $tag) {
    $stmt = $conn->prepare("INSERT IGNORE INTO tags (name) VALUES (?)");
    $stmt->bind_param("s", $tag);
    $stmt->execute();
}

// Create admin user (password: admin123)
$admin_password = password_hash('admin123', PASSWORD_DEFAULT);
$stmt = $conn->prepare("INSERT IGNORE INTO users (username, email, password, role) VALUES (?, ?, ?, ?)");
$username = 'admin';
$email = 'admin@example.com';
$role = 'admin';
$stmt->bind_param("ssss", $username, $email, $admin_password, $role);
$stmt->execute();

// Create sample user (password: user123)
$user_password = password_hash('user123', PASSWORD_DEFAULT);
$stmt = $conn->prepare("INSERT IGNORE INTO users (username, email, password, role) VALUES (?, ?, ?, ?)");
$username = 'testuser';
$email = 'user@example.com';
$role = 'user';
$stmt->bind_param("ssss", $username, $email, $user_password, $role);
$stmt->execute();

echo "Sample data inserted successfully!\n";
echo "Admin login: admin@example.com / admin123\n";
echo "User login: user@example.com / user123\n";
?>

