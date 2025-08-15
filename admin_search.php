<?php

require_once 'includes/functions.php';
// admin_search.php
// This file provides a universal search functionality for the admin panel.
// It can be included in other admin files (e.g., admin_dashboard.php, manage_posts.php, etc.)

// Ensure admin access is required before including this file
if (!isset($conn)) {
    // If $conn is not set, include the database connection and admin check.
    require_once __DIR__ . '/includes/db_connection.php';
    require_once __DIR__ . '/includes/functions.php'; // For require_admin()
    require_admin(); // Ensure admin is logged in
}

// Get the current page to determine context for personalized search results
$current_page = basename($_SERVER['PHP_SELF']);

// Define which types of results are relevant for each page
$page_specific_results_map = [
    'admin_dashboard.php'   => ['posts', 'users', 'comments', 'categories', 'tags'],
    'manage_posts.php'      => ['posts'],
    'manage_users.php'      => ['users'],
    'manage_comments.php'   => ['comments'],
    'manage_categories.php' => ['categories', 'tags'], // Categories and Tags are managed together
    // Add other admin pages here if you introduce them and need specific search results
];

// Determine which result types to search/display based on the current page
$allowed_result_types = $page_specific_results_map[$current_page] ?? ['posts', 'users', 'comments', 'categories', 'tags']; // Default to all for dashboard or unknown pages

// Get search query from GET request
$search_query = isset($_GET['admin_q']) ? sanitize_input($_GET['admin_q']) : '';
$search_results = [];
$message = '';
$message_type = '';

if (!empty($search_query)) {
    // --- Search Posts ---
    if (in_array('posts', $allowed_result_types)) {
        $posts_search_query = "SELECT p.id, p.title, p.status, p.published_at, u.username, c.name as category_name
                               FROM posts p
                               LEFT JOIN users u ON p.user_id = u.id
                               LEFT JOIN categories c ON p.category_id = c.id
                               WHERE p.title LIKE ? OR p.content LIKE ? OR u.username LIKE ? OR c.name LIKE ?
                               ORDER BY p.published_at DESC LIMIT 10";
        $stmt = $conn->prepare($posts_search_query);
        $param = '%' . $search_query . '%';
        $stmt->bind_param("ssss", $param, $param, $param, $param);
        $stmt->execute();
        $posts_result = $stmt->get_result();
        if ($posts_result) {
            while ($row = $posts_result->fetch_assoc()) {
                $search_results['posts'][] = $row;
            }
        }
        $stmt->close();
    }

    // --- Search Users ---
    if (in_array('users', $allowed_result_types)) {
        $users_search_query = "SELECT id, username, email, role, created_at
                               FROM users
                               WHERE username LIKE ? OR email LIKE ? OR role LIKE ?
                               ORDER BY created_at DESC LIMIT 10";
        $stmt = $conn->prepare($users_search_query);
        $param = '%' . $search_query . '%';
        $stmt->bind_param("sss", $param, $param, $param);
        $stmt->execute();
        $users_result = $stmt->get_result();
        if ($users_result) {
            while ($row = $users_result->fetch_assoc()) {
                $search_results['users'][] = $row;
            }
        }
        $stmt->close();
    }

    // --- Search Comments ---
    if (in_array('comments', $allowed_result_types)) {
        $comments_search_query = "SELECT c.id, c.content, c.status, c.created_at, p.title as post_title, u.username as author_username
                                  FROM comments c
                                  LEFT JOIN posts p ON c.post_id = p.id
                                  LEFT JOIN users u ON c.user_id = u.id
                                  WHERE c.content LIKE ? OR u.username LIKE ? OR c.author_name LIKE ? OR p.title LIKE ?
                                  ORDER BY c.created_at DESC LIMIT 10";
        $stmt = $conn->prepare($comments_search_query);
        $param = '%' . $search_query . '%';
        // Note: The original query assumed author_name might exist, ensure your DB schema supports it or adjust
        $stmt->bind_param("ssss", $param, $param, $param, $param);
        $stmt->execute();
        $comments_result = $stmt->get_result();
        if ($comments_result) {
            while ($row = $comments_result->fetch_assoc()) {
                $search_results['comments'][] = $row;
            }
        }
        $stmt->close();
    }

    // --- Search Categories ---
    if (in_array('categories', $allowed_result_types)) {
        $categories_search_query = "SELECT id, name FROM categories WHERE name LIKE ? ORDER BY name ASC LIMIT 5";
        $stmt = $conn->prepare($categories_search_query);
        $param = '%' . $search_query . '%';
        $stmt->bind_param("s", $param);
        $stmt->execute();
        $categories_result = $stmt->get_result();
        if ($categories_result) {
            while ($row = $categories_result->fetch_assoc()) {
                $search_results['categories'][] = $row;
            }
        }
        $stmt->close();
    }

    // --- Search Tags ---
    if (in_array('tags', $allowed_result_types)) {
        $tags_search_query = "SELECT id, name FROM tags WHERE name LIKE ? ORDER BY name ASC LIMIT 5";
        $stmt = $conn->prepare($tags_search_query);
        $param = '%' . $search_query . '%';
        $stmt->bind_param("s", $param);
        $stmt->execute();
        $tags_result = $stmt->get_result();
        if ($tags_result) {
            while ($row = $tags_result->fetch_assoc()) {
                $search_results['tags'][] = $row;
            }
        }
        $stmt->close();
    }

    // Check if any results were found for the current page's allowed types
    $found_any_results = false;
    foreach ($allowed_result_types as $type) {
        if (!empty($search_results[$type])) {
            $found_any_results = true;
            break;
        }
    }

    if (!$found_any_results) {
        $message = "No results found for '<strong>" . htmlspecialchars($search_query) . "</strong>' on this page.";
        $message_type = "info";
    }
}
?>

<?php
// Only display the search form and results if not already included in a full page context
// If this file is included, we assume the header/footer and main container are handled by the parent file.
// If it's accessed directly (e.g., admin_search.php?admin_q=...), then show full page structure.
$is_standalone_search_page = ($current_page === 'admin_search.php' && !empty($search_query));
if ($is_standalone_search_page) {
    include 'includes/header.php'; // Include header if standalone
    echo '<div class="container">'; // Start container for standalone
}
?>

<div class="admin-search-container mb-4">
    <form action="
        <?php
            // If on admin_dashboard, action goes to admin_search.php for full results.
            // Otherwise, action goes to the current page to filter results by context.
            if ($current_page === 'admin_dashboard.php') {
                echo 'admin_search.php';
            } else {
                echo htmlspecialchars($current_page);
            }
        ?>
    " method="GET" class="admin-search-form">
        <input type="text" name="admin_q" placeholder="Search admin data..."
               value="<?php echo htmlspecialchars($search_query); ?>"
               class="admin-search-input">
        <button type="submit" class="admin-search-button">
            <i class="fas fa-search"></i>
        </button>
    </form>
</div>

<?php if (!empty($search_query)): // Only show results if a search was performed ?>
    <div class="search-results-section mb-4">
        <h3>Search Results for "<?php echo htmlspecialchars($search_query); ?>"</h3>

        <?php if (!empty($message)): ?>
            <div class="alert alert-<?php echo $message_type; ?>"><?php echo $message; ?></div>
        <?php endif; ?>

        <?php if (!empty($search_results['posts']) && in_array('posts', $allowed_result_types)): ?>
            <div class="card mb-3">
                <div class="card-header"><h4><i class="fas fa-file-alt"></i> Posts</h4></div>
                <div class="card-body">
                    <ul class="admin-search-list">
                        <?php foreach ($search_results['posts'] as $post): ?>
                            <li>
                                <a href="edit_post.php?id=<?php echo $post['id']; ?>">
                                    <?php echo htmlspecialchars($post['title']); ?>
                                </a>
                                <div class="item-meta">
                                    <span class="text-muted">(By <?php echo htmlspecialchars($post['username']); ?> | <?php echo htmlspecialchars($post['category_name'] ?? 'N/A'); ?> | <span class="status-<?php echo $post['status']; ?>"><?php echo ucfirst($post['status']); ?></span>)</span>
                                    <a href="edit_post.php?id=<?php echo $post['id']; ?>" class="btn btn-sm btn-outline-info ml-2">Edit Post</a>
                                </div>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                    <div class="card-footer text-right">
                        <a href="manage_posts.php?admin_q=<?php echo urlencode($search_query); ?>" class="btn btn-sm btn-outline">View All Post Results</a>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <?php if (!empty($search_results['users']) && in_array('users', $allowed_result_types)): ?>
            <div class="card mb-3">
                <div class="card-header"><h4><i class="fas fa-users"></i> Users</h4></div>
                <div class="card-body">
                    <ul class="admin-search-list">
                        <?php foreach ($search_results['users'] as $user): ?>
                            <li>
                                <a href="manage_users.php?user=<?php echo $user['id']; ?>">
                                    <?php echo htmlspecialchars($user['username']); ?>
                                </a>
                                <div class="item-meta">
                                    <span class="text-muted">(<?php echo htmlspecialchars($user['email']); ?> | <span class="status-<?php echo $user['role']; ?>"><?php echo ucfirst($user['role']); ?></span>)</span>
                                    <a href="manage_users.php?user=<?php echo $user['id']; ?>" class="btn btn-sm btn-outline-info ml-2">Manage User</a>
                                </div>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                     <div class="card-footer text-right">
                        <a href="manage_users.php?admin_q=<?php echo urlencode($search_query); ?>" class="btn btn-sm btn-outline">View All User Results</a>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <?php if (!empty($search_results['comments']) && in_array('comments', $allowed_result_types)): ?>
            <div class="card mb-3">
                <div class="card-header"><h4><i class="fas fa-comments"></i> Comments</h4></div>
                <div class="card-body">
                    <ul class="admin-search-list">
                        <?php foreach ($search_results['comments'] as $comment): ?>
                            <li>
                                <a href="manage_comments.php?comment=<?php echo $comment['id']; ?>">
                                    <?php echo htmlspecialchars(truncate_text($comment['content'], 50)); ?>
                                </a>
                                <div class="item-meta">
                                    <span class="text-muted">(By <?php echo htmlspecialchars($comment['author_username'] ?? 'N/A'); ?> on "<?php echo htmlspecialchars(truncate_text($comment['post_title'] ?? 'N/A', 20)); ?>" | <span class="status-<?php echo $comment['status']; ?>"><?php echo ucfirst($comment['status']); ?></span>)</span>
                                    <a href="manage_comments.php?comment=<?php echo $comment['id']; ?>" class="btn btn-sm btn-outline-info ml-2">Review Comment</a>
                                </div>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                     <div class="card-footer text-right">
                        <a href="manage_comments.php?admin_q=<?php echo urlencode($search_query); ?>" class="btn btn-sm btn-outline">View All Comment Results</a>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <?php if (!empty($search_results['categories']) && in_array('categories', $allowed_result_types)): ?>
            <div class="card mb-3">
                <div class="card-header"><h4><i class="fas fa-folder"></i> Categories</h4></div>
                <div class="card-body">
                    <ul class="admin-search-list">
                        <?php foreach ($search_results['categories'] as $category): ?>
                            <li>
                                <a href="manage_categories.php?edit=<?php echo $category['id']; ?>">
                                    <?php echo htmlspecialchars($category['name']); ?>
                                </a>
                                <div class="item-meta">
                                    <a href="manage_categories.php?edit=<?php echo $category['id']; ?>" class="btn btn-sm btn-outline-info ml-2">Edit Category</a>
                                </div>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                     <div class="card-footer text-right">
                        <a href="manage_categories.php?admin_q=<?php echo urlencode($search_query); ?>" class="btn btn-sm btn-outline">View All Category Results</a>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <?php if (!empty($search_results['tags']) && in_array('tags', $allowed_result_types)): ?>
            <div class="card mb-3">
                <div class="card-header"><h4><i class="fas fa-tags"></i> Tags</h4></div>
                <div class="card-body">
                    <ul class="admin-search-list">
                        <?php foreach ($search_results['tags'] as $tag): ?>
                            <li>
                                <a href="manage_categories.php?edit_tag=<?php echo $tag['id']; ?>#tags">
                                    <?php echo htmlspecialchars($tag['name']); ?>
                                </a>
                                <div class="item-meta">
                                    <a href="manage_categories.php?edit_tag=<?php echo $tag['id']; ?>#tags" class="btn btn-sm btn-outline-info ml-2">Edit Tag</a>
                                </div>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                     <div class="card-footer text-right">
                        <a href="manage_categories.php?admin_q=<?php echo urlencode($search_query); ?>#tags" class="btn btn-sm btn-outline">View All Tag Results</a>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <?php
        // Check if no results were found for the current page's allowed types for the specific query
        $display_no_results_message = true;
        foreach ($allowed_result_types as $type) {
            if (!empty($search_results[$type])) {
                $display_no_results_message = false;
                break;
            }
        }
        if ($display_no_results_message):
        ?>
             <div class="alert alert-info text-center">
                No results found for "<strong><?php echo htmlspecialchars($search_query); ?></strong>" within the current section.
            </div>
        <?php endif; ?>

    </div>
<?php endif; ?>

<?php
if ($is_standalone_search_page) {
    echo '</div>'; // End container for standalone
    include 'includes/footer.php'; // Include footer if standalone
}
?>

<style>
/* Custom Properties (CSS Variables) for Light Theme */
:root {
    --primary-color: #3498db;
    --primary-dark: #2980b9;
    --secondary-color: #6c757d;
    --accent-color: #e74c3c;
    --text-color: #333;
    --heading-color: #2c3e50;
    --background-light: #f8f9fa;
    --background-white: #fff;
    --border-color: #e9ecef;
    --shadow-light: 0 2px 10px rgba(0,0,0,0.08);
    --shadow-medium: 0 4px 15px rgba(0,0,0,0.1);
    --shadow-hover: 0 8px 25px rgba(0,0,0,0.15);
}

/* Dark Theme Variables (assuming these are defined in a global style.css or header) */
:root[data-theme="dark"] {
    --primary-color: #3498db;
    --primary-dark: #2980b9;
    --secondary-color: #95a5a6;
    --accent-color: #e74c3c;
    --text-color: #ecf0f1;
    --heading-color: #ffffff;
    --background-light: #34495e;
    --background-white: #2c3e50;
    --border-color: #4b6584;
    --shadow-light: 0 2px 10px rgba(0,0,0,0.2);
    --shadow-medium: 0 4px 15px rgba(0,0,0,0.3);
    --shadow-hover: 0 8px 25px rgba(0,0,0,0.4);
}

/* General Admin Component Styling (Re-used from other admin pages) */
.card {
    background: var(--background-white);
    border-radius: 12px;
    box-shadow: var(--shadow-medium);
    overflow: hidden;
    margin-bottom: 2rem; /* Consistent spacing */
}
.card-header {
    background-color: var(--background-light);
    padding: 1rem 1.5rem; /* Adjusted padding */
    border-bottom: 1px solid var(--border-color);
    display: flex;
    justify-content: space-between;
    align-items: center;
    font-weight: 600;
    color: var(--heading-color);
}
.card-header h4 {
    margin: 0; /* Override default h4 margin */
    display: flex;
    align-items: center;
    gap: 0.5rem; /* Space between icon and text */
}
.card-body {
    padding: 1.5rem; /* Adjusted padding */
}
.card-footer {
    padding: 1rem 1.5rem;
    border-top: 1px solid var(--border-color);
    background-color: var(--background-light);
    text-align: right;
}

.btn {
    padding: 8px 16px; /* Smaller padding for consistent look */
    border-radius: 8px;
    cursor: pointer;
    font-size: 0.9rem; /* Smaller font size */
    font-weight: 500;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    transition: background-color 0.2s ease, border-color 0.2s ease, color 0.2s ease, box-shadow 0.2s ease;
    white-space: nowrap; /* Prevent button text from wrapping */
}
.btn-primary {
    background-color: var(--primary-color);
    color: #fff;
    border: 1px solid var(--primary-color);
}
.btn-primary:hover {
    background-color: var(--primary-dark);
    border-color: var(--primary-dark);
    box-shadow: var(--shadow-hover);
}
.btn-outline {
    background-color: transparent;
    color: var(--primary-color);
    border: 1px solid var(--primary-color);
}
.btn-outline:hover {
    background-color: var(--primary-color);
    color: #fff;
    box-shadow: var(--shadow-hover);
}
.btn-sm {
    padding: 6px 12px;
    font-size: 0.8rem;
    border-radius: 6px;
}
.btn-outline-info { /* New style for specific action buttons */
    background-color: transparent;
    color: var(--primary-color);
    border: 1px solid var(--primary-color);
}
.btn-outline-info:hover {
    background-color: var(--primary-color);
    color: #fff;
}
.ml-2 {
    margin-left: 0.5rem;
}


.alert {
    padding: 1rem;
    margin-bottom: 1.5rem; /* Consistent margin */
    border-radius: 8px;
    font-weight: 500;
    display: flex;
    align-items: center;
    gap: 0.75rem;
}
.alert-success {
    background-color: #d4edda;
    color: #155724;
    border: 1px solid #c3e6cb;
}
.alert-error, .alert-danger {
    background-color: #f8d7da;
    color: #721c24;
    border: 1px solid #f5c6cb;
}
.alert-info {
    background-color: #d1ecf1;
    color: #0c5460;
    border: 1px solid #bee5eb;
}


/* Admin Search Bar Styling - Refined */
.admin-search-container {
    background: var(--background-white);
    padding: 1.5rem;
    border-radius: 12px;
    box-shadow: var(--shadow-medium);
    margin-bottom: 2rem;
}

.admin-search-form {
    display: flex;
    gap: 0.5rem;
    max-width: 600px;
    margin: 0 auto;
    border: 1px solid var(--border-color); /* Add border to the whole form group */
    border-radius: 8px; /* Rounded corners for the group */
    overflow: hidden; /* Ensures input and button borders are contained */
}

.admin-search-input {
    flex-grow: 1;
    padding: 0.75rem 1rem;
    border: none; /* Remove individual border */
    border-radius: 0; /* Remove individual border-radius */
    font-size: 1rem;
    color: var(--text-color);
    background: var(--background-white); /* Match card background */
    transition: none; /* No transition on input itself, it's on the container */
}

.admin-search-input:focus {
    outline: none; /* Remove default outline */
}

/* Apply focus style to the container instead */
.admin-search-form:focus-within {
    border-color: var(--primary-color);
    box-shadow: 0 0 0 0.2rem rgba(52, 152, 219, 0.15);
}


.admin-search-button {
    padding: 0.75rem 1.25rem;
    background-color: var(--primary-color);
    color: #fff;
    border: none; /* Remove border */
    border-radius: 0; /* Remove individual border-radius */
    cursor: pointer;
    transition: background-color 0.2s ease, box-shadow 0.2s ease;
    display: flex;
    align-items: center;
    justify-content: center;
}

.admin-search-button:hover {
    background-color: var(--primary-dark);
    box-shadow: none; /* Remove box-shadow on button hover, as it's on the form */
}

/* Search Results Section Styling - Refined */
.search-results-section h3 {
    margin-bottom: 1.5rem;
    color: var(--heading-color);
    text-align: center;
}

.admin-search-list {
    list-style: none;
    padding: 0;
    margin: 0;
}

.admin-search-list li {
    padding: 0.75rem 0;
    border-bottom: 1px solid var(--border-color);
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 1rem;
    color: var(--text-color); /* Ensure text color matches theme */
}

.admin-search-list li:last-child {
    border-bottom: none;
}

.admin-search-list li a {
    font-weight: 500;
    color: var(--primary-color);
    text-decoration: none;
    transition: color 0.2s ease;
}

.admin-search-list li a:hover {
    color: var(--primary-dark);
    text-decoration: underline;
}

.admin-search-list li .item-meta {
    display: flex;
    align-items: center;
    font-size: 0.85rem;
    flex-shrink: 0;
    color: var(--secondary-color); /* Ensure text muted color matches theme */
}

/* Status Colors for search results */
.search-results-section .status-published,
.search-results-section .status-approved,
.search-results-section .status-admin {
    color: #27ae60; /* Green */
    font-weight: 500;
}
.search-results-section .status-draft,
.search-results-section .status-pending {
    color: #f39c12; /* Yellow/Orange */
    font-weight: 500;
}
.search-results-section .status-rejected {
    color: var(--accent-color); /* Red */
    font-weight: 500;
}
.search-results-section .status-user {
    color: var(--secondary-color); /* Grey for regular users */
    font-weight: 500;
}

/* Responsive Adjustments */
@media (max-width: 768px) {
    .admin-search-form {
        flex-direction: column;
        gap: 1rem;
        border-radius: 12px; /* Full rounded corners for mobile vertical stack */
    }
    .admin-search-input,
    .admin-search-button {
        border-radius: 8px; /* Rounded corners for input/button on mobile */
    }
    .admin-search-button {
        width: 100%;
    }
    .admin-search-list li {
        flex-direction: column;
        align-items: flex-start;
    }
    .admin-search-list li .item-meta {
        flex-direction: column; /* Stack meta and button vertically */
        align-items: flex-start;
        width: 100%;
        margin-top: 0.5rem; /* Space between link and meta */
    }
    .admin-search-list li .btn {
        margin-left: 0 !important; /* Override ml-2 on mobile */
        margin-top: 0.5rem; /* Space between meta text and button */
        width: fit-content; /* Adjust button width */
    }
    .card-body {
        padding: 1rem; /* Adjust padding for mobile cards */
    }
}
</style>
