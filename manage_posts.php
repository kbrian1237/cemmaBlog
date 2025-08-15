<?php
ob_start();
$page_title = "Manage Posts";
include 'includes/header.php';
require_once 'includes/functions.php'; // Ensure functions.php is included for get_post_likes, get_post_dislikes

// Require admin access
require_admin();

// Database connection is assumed to be established in header.php or an included config file.

// Handle delete action
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $post_id_to_delete = (int)$_GET['delete'];

    // Delete associated post_tags first
    $delete_tags_query = "DELETE FROM post_tags WHERE post_id = ?";
    $delete_tags_stmt = $conn->prepare($delete_tags_query);
    $delete_tags_stmt->bind_param("i", $post_id_to_delete);
    $delete_tags_stmt->execute();
    $delete_tags_stmt->close();

    // Delete associated comments
    $delete_comments_query = "DELETE FROM comments WHERE post_id = ?";
    $delete_comments_stmt = $conn->prepare($delete_comments_query);
    $delete_comments_stmt->bind_param("i", $post_id_to_delete);
    $delete_comments_stmt->execute();
    $delete_comments_stmt->close();

    // Delete associated likes
    $delete_likes_query = "DELETE FROM likes WHERE post_id = ?";
    $delete_likes_stmt = $conn->prepare($delete_likes_query);
    $delete_likes_stmt->bind_param("i", $post_id_to_delete);
    $delete_likes_stmt->execute();
    $delete_likes_stmt->close();

    // Delete associated dislikes
    $delete_dislikes_query = "DELETE FROM dislikes WHERE post_id = ?";
    $delete_dislikes_stmt = $conn->prepare($delete_dislikes_query);
    $delete_dislikes_stmt->bind_param("i", $post_id_to_delete);
    $delete_dislikes_stmt->execute();
    $delete_dislikes_stmt->close();


    // Then delete the post itself
    $delete_post_query = "DELETE FROM posts WHERE id = ?";
    $delete_post_stmt = $conn->prepare($delete_post_query);
    $delete_post_stmt->bind_param("i", $post_id_to_delete);
    if ($delete_post_stmt->execute()) {
        $_SESSION['message'] = "Post deleted successfully!";
        $_SESSION['message_type'] = "success";
    } else {
        $_SESSION['message'] = "Error deleting post: " . htmlspecialchars($conn->error);
        $_SESSION['message_type'] = "error";
    }
    $delete_post_stmt->close();
    header("Location: manage_posts.php");
    exit();
}

// Handle feature/unfeature actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['feature_toggle_id'])) {
    $post_id = (int)$_POST['feature_toggle_id'];
    $is_featured = isset($_POST['is_featured']) ? 1 : 0;
    
    $stmt = $conn->prepare("UPDATE posts SET is_featured = ? WHERE id = ?");
    $stmt->bind_param("ii", $is_featured, $post_id);
    $stmt->execute();
    $stmt->close();
    $_SESSION['message'] = "Featured status updated!";
    $_SESSION['message_type'] = "success";
    header("Location: manage_posts.php");
    exit();
}

// Handle dislike button status update action
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['dislike_status_post_id'])) {
    $post_id = (int)$_POST['dislike_status_post_id'];
    $new_status = sanitize_input($_POST['dislike_button_status']); // 'enabled', 'disabled', 'pending'

    // Validate the new status against allowed ENUM values
    $allowed_statuses = ['enabled', 'disabled', 'pending'];
    if (!in_array($new_status, $allowed_statuses)) {
        $_SESSION['message'] = "Invalid dislike button status provided.";
        $_SESSION['message_type'] = "error";
    } else {
        $stmt = $conn->prepare("UPDATE posts SET dislike_button_status = ? WHERE id = ?");
        $stmt->bind_param("si", $new_status, $post_id);
        if ($stmt->execute()) {
            $_SESSION['message'] = "Dislike button status updated to '" . htmlspecialchars($new_status) . "'!";
            $_SESSION['message_type'] = "success";
        } else {
            $_SESSION['message'] = "Error updating dislike button status: " . htmlspecialchars($conn->error);
            $_SESSION['message_type'] = "error";
        }
        $stmt->close();
    }
    header("Location: manage_posts.php");
    exit();
}

// Pagination for Posts
$posts_per_page = 10;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $posts_per_page;

// Get total count of posts
$total_posts_query = "SELECT COUNT(*) as total FROM posts";
$total_posts_result = $conn->query($total_posts_query);
$total_posts = $total_posts_result->fetch_assoc()['total'];
$total_pages = ceil($total_posts / $posts_per_page);

// Fetch all posts for display, including like/dislike counts and dislike_button_status
$posts_query = "SELECT p.*, u.username, c.name as category_name,
                       (SELECT COUNT(*) FROM likes WHERE post_id = p.id) as total_likes,
                       (SELECT COUNT(*) FROM dislikes WHERE post_id = p.id) as total_dislikes
                FROM posts p
                LEFT JOIN users u ON p.user_id = u.id
                LEFT JOIN categories c ON p.category_id = c.id
                ORDER BY p.published_at DESC
                LIMIT ? OFFSET ?";

// Execute the query
$posts_stmt = $conn->prepare($posts_query);
$posts_stmt->bind_param("ii", $posts_per_page, $offset);
$posts_stmt->execute();
$posts_result = $posts_stmt->get_result();

// Check if the query failed and display the specific MySQL error
if (!$posts_result) {
    echo "<div class='alert alert-danger'>Error fetching posts: " . htmlspecialchars($conn->error) . "</div>";
    // Exit or handle gracefully if the query fails, to prevent further errors
}

?>

<!-- Floating Back to Dashboard Button -->
<a href="admin_dashboard.php" class="floating-btn" title="Back to Dashboard">
    <i class="fas fa-arrow-left"></i>
</a>
<style>
.floating-btn {
    position: fixed;
    bottom: 32px;
    right: 32px;
    background: var(--primary-color);
    color: #fff;
    border-radius: 50%;
    width: 56px;
    height: 56px;
    display: flex;
    align-items: center;
    justify-content: center;
    box-shadow: var(--shadow-medium);
    font-size: 1.5rem;
    z-index: 1000;
    transition: background 0.2s, box-shadow 0.2s;
    text-decoration: none;
}
.floating-btn:hover {
    background: var(--primary-dark);
    box-shadow: var(--shadow-hover);
    color: #fff;
}
</style>
<div class="container">
    <?php include 'admin_search.php';?>
</div>

<div class="container">
    <div class="admin-header mb-4">
        <h1><i class="fas fa-file-alt"></i> Manage Posts</h1>
        <p class="text-muted">View, edit, or delete blog posts.</p>
    </div>

    <?php if (isset($_SESSION['message'])): ?>
        <div class="alert alert-<?php echo $_SESSION['message_type']; ?>">
            <?php echo $_SESSION['message']; ?>
        </div>
        <?php
        unset($_SESSION['message']);
        unset($_SESSION['message_type']);
        ?>
    <?php endif; ?>
    <div class="card mb-4">
        <div class="card-header">
            <h3>All Posts</h3>
            <a href="../create_post.php" class="btn btn-primary btn-sm"><i class="fas fa-plus"></i> Add New Post</a>
        </div>
        <div class="card-body">
            <?php 
            // Check if the query was successful AND returned rows
            if ($posts_result && $posts_result->num_rows > 0): 
            ?>
                <div class="table-responsive">
                    <table class="admin-table">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Title</th>
                                <th>Author</th>
                                <th>Category</th>
                                <th>Status</th>
                                <th>Likes</th> <!-- New Header -->
                                <th>Dislikes</th> <!-- New Header -->
                                <th>Dislike Btn Status</th> <!-- New Header -->
                                <th>Published Date</th>
                                <th>Featured</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($post = $posts_result->fetch_assoc()): ?>
                                <tr>
                                    <td data-label="ID"><?php echo htmlspecialchars($post['id']); ?></td>
                                    <td data-label="Title">
                                        <a href="../post.php?id=<?php echo $post['id']; ?>" target="_blank">
                                            <?php echo htmlspecialchars(truncate_text($post['title'], 50)); ?>
                                        </a>
                                    </td>
                                    <td data-label="Author"><?php echo htmlspecialchars($post['username'] ?? 'N/A'); ?></td>
                                    <td data-label="Category"><?php echo htmlspecialchars($post['category_name'] ?? 'N/A'); ?></td>
                                    <td data-label="Status"><span class="status-<?php echo $post['status']; ?>"><?php echo ucfirst($post['status']); ?></span></td>
                                    <td data-label="Likes"><?php echo htmlspecialchars($post['total_likes']); ?></td> <!-- New Data -->
                                    <td data-label="Dislikes"><?php echo htmlspecialchars($post['total_dislikes']); ?></td> <!-- New Data -->
                                    <td data-label="Dislike Btn Status">
                                        <form method="post" action="manage_posts.php" style="display:inline-block;">
                                            <input type="hidden" name="dislike_status_post_id" value="<?php echo $post['id']; ?>">
                                            <select name="dislike_button_status" onchange="this.form.submit()" class="form-select-inline">
                                                <option value="enabled" <?php echo ($post['dislike_button_status'] === 'enabled') ? 'selected' : ''; ?>>Enabled</option>
                                                <option value="disabled" <?php echo ($post['dislike_button_status'] === 'disabled') ? 'selected' : ''; ?>>Disabled</option>
                                                <option value="pending" <?php echo ($post['dislike_button_status'] === 'pending') ? 'selected' : ''; ?>>Pending</option>
                                            </select>
                                        </form>
                                    </td>
                                    <td data-label="Published Date"><?php echo format_datetime($post['published_at']); ?></td>
                                    <td data-label="Featured">
                                        <form method="post" action="manage_posts.php" style="display:inline;">
                                            <input type="hidden" name="feature_toggle_id" value="<?php echo $post['id']; ?>">
                                            <input type="checkbox" name="is_featured" value="1" onchange="this.form.submit()" <?php if (!empty($post['is_featured'])) echo 'checked'; ?> title="Toggle Featured">
                                        </form>
                                        <?php if (!empty($post['is_featured'])): ?>
                                            <span title="Featured" style="color: #f1c40f;"><i class="fas fa-star"></i></span>
                                        <?php else: ?>
                                            <span title="Not Featured" style="color: #ccc;"><i class="far fa-star"></i></span>
                                        <?php endif; ?>
                                    </td>
                                    <td data-label="Actions" class="actions">
                                        <a href="edit_post.php?id=<?php echo $post['id']; ?>" class="btn btn-sm btn-info" title="Edit"><i class="fas fa-edit"></i></a>
                                        <a href="manage_posts.php?delete=<?php echo $post['id']; ?>" class="btn btn-sm btn-danger" title="Delete" onclick="return confirm('Are you sure you want to delete this post and all its associated data (comments, tags, likes, dislikes)?');"><i class="fas fa-trash"></i></a>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
                <?php
                // Pagination for Posts
                $base_url = 'manage_posts.php';
                include 'pagination_snippet.php'; // Include the pagination snippet
                ?>
            <?php else: ?>
                <p class="text-muted">No posts found.</p>
            <?php endif; ?>
        </div>
    </div>
    </div>

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

    /* Dark Theme Variables */
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

    /* General Admin Table Styling */
    .admin-table {
        width: 100%;
        border-collapse: collapse;
        margin-top: 1.5rem;
        background: var(--background-white);
        color: var(--text-color);
        box-shadow: var(--shadow-light);
    }

    .admin-table th,
    .admin-table td {
        padding: 12px 15px;
        text-align: left;
        border-bottom: 1px solid var(--border-color);
    }

    .admin-table th {
        background-color: var(--background-light);
        color: var(--heading-color);
        font-weight: 600;
        text-transform: uppercase;
        font-size: 0.85rem;
    }

    .admin-table tbody tr:hover {
        background-color: var(--background-light);
    }

    .admin-table .actions .btn {
        margin-right: 5px;
    }

    /* Status color classes */
    .status-published {
        color: var(--primary-color);
        font-weight: 600;
    }
    .status-draft {
        color: var(--secondary-color);
        font-weight: 600;
    }
    .status-archived {
        color: var(--accent-color);
        font-weight: 600;
    }
    /* New: Styling for the inline select for dislike button status */
    .form-select-inline {
        padding: 6px 10px;
        border: 1px solid var(--border-color);
        border-radius: 5px;
        background-color: var(--background-white);
        color: var(--text-color);
        font-size: 0.9rem;
        cursor: pointer;
        width: auto; /* Allow it to size based on content */
        display: inline-block; /* Ensure it stays inline */
    }
    .form-select-inline:focus {
        border-color: var(--primary-color);
        box-shadow: 0 0 0 0.1rem rgba(52, 152, 219, 0.1);
        outline: none;
    }


    /* Responsive Table */
    .table-responsive {
        overflow-x: auto; /* This is the key change */
    }

    @media (max-width: 768px) {
        .admin-table thead {
            display: none; /* Hide table headers on small screens */
        }

        .admin-table,
        .admin-table tbody,
        .admin-table tr,
        .admin-table td {
            display: block;
            width: 100%;
        }

        .admin-table tr {
            margin-bottom: 1rem;
            border: 1px solid var(--border-color);
            border-radius: 8px;
            overflow: hidden;
            background-color: var(--background-white);
            box-shadow: var(--shadow-light);
        }

        .admin-table td {
            text-align: right;
            padding-left: 50%; /* Space for the label */
            position: relative;
        }

        .admin-table td::before {
            content: attr(data-label); /* Use data-label for content */
            position: absolute;
            left: 15px;
            width: calc(50% - 30px);
            padding-right: 10px;
            white-space: nowrap;
            text-align: left;
            font-weight: 600;
            color: var(--heading-color);
        }

        /* Specific data labels for each column */
        .admin-table td:nth-of-type(1):before { content: "ID"; }
        .admin-table td:nth-of-type(2):before { content: "Title"; }
        .admin-table td:nth-of-type(3):before { content: "Author"; }
        .admin-table td:nth-of-type(4):before { content: "Category"; }
        .admin-table td:nth-of-type(5):before { content: "Status"; }
        .admin-table td:nth-of-type(6):before { content: "Likes"; } /* New Data Label */
        .admin-table td:nth-of-type(7):before { content: "Dislikes"; } /* New Data Label */
        .admin-table td:nth-of-type(8):before { content: "Dislike Status"; } /* New Data Label */
        .admin-table td:nth-of-type(9):before { content: "Published Date"; }
        .admin-table td:nth-of-type(10):before { content: "Featured"; }
        .admin-table td:nth-of-type(11):before { content: "Actions"; }
        
        .admin-table .actions {
            display: flex;
            justify-content: flex-end;
            margin-top: 10px;
        }
    }
</style>
<?php ob_end_flush(); ?>
<?php include 'includes/footer.php'; ?>
