<?php
ob_start(); // Start output buffering
$page_title = "Manage Dislike Requests";
include 'includes/header.php';
require_once 'includes/functions.php'; // Ensure functions.php is included for necessary helpers

// Require admin access
require_admin(); // This function should redirect if the user is not an admin

// Database connection is assumed to be established in header.php or an included config file.

// Handle dislike button status update action for individual posts
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['dislike_status_post_id'])) {
    $post_id = (int)$_POST['dislike_status_post_id'];
    $new_status = sanitize_input($_POST['new_dislike_status']); // 'enabled' or 'disabled'

    // Validate the new status against allowed values for admin approval
    $allowed_statuses_for_approval = ['enabled', 'disabled'];
    if (!in_array($new_status, $allowed_statuses_for_approval)) {
        $_SESSION['message'] = "Invalid status for dislike button approval provided.";
        $_SESSION['message_type'] = "error";
    } else {
        $stmt = $conn->prepare("UPDATE posts SET dislike_button_status = ? WHERE id = ?");
        $stmt->bind_param("si", $new_status, $post_id);
        if ($stmt->execute()) {
            $_SESSION['message'] = "Dislike button status for post ID " . $post_id . " updated to '" . htmlspecialchars($new_status) . "'!";
            $_SESSION['message_type'] = "success";
        } else {
            $_SESSION['message'] = "Error updating dislike button status: " . htmlspecialchars($conn->error);
            $_SESSION['message_type'] = "error";
        }
        $stmt->close();
    }
    header("Location: manage_likes_dislikes.php"); // Redirect back to this page
    exit();
}

// Handle "Approve All" action
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['approve_all_dislikes'])) {
    $stmt = $conn->prepare("UPDATE posts SET dislike_button_status = 'enabled' WHERE dislike_button_status = 'pending'");
    if ($stmt->execute()) {
        $rows_affected = $stmt->affected_rows;
        if ($rows_affected > 0) {
            $_SESSION['message'] = "Successfully approved " . $rows_affected . " pending dislike button requests!";
            $_SESSION['message_type'] = "success";
        } else {
            $_SESSION['message'] = "No pending dislike button requests to approve.";
            $_SESSION['message_type'] = "info";
        }
    } else {
        $_SESSION['message'] = "Error approving all dislike button requests: " . htmlspecialchars($conn->error);
        $_SESSION['message_type'] = "error";
    }
    $stmt->close();
    header("Location: manage_likes_dislikes.php"); // Redirect back to this page
    exit();
}

// Pagination for Pending Dislike Requests
$requests_per_page = 10;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $requests_per_page;

// Get total count of pending dislike requests
$total_requests_query = "SELECT COUNT(*) as total FROM posts WHERE dislike_button_status = 'pending'";
$total_requests_result = $conn->query($total_requests_query);
$total_requests = $total_requests_result->fetch_assoc()['total'];
$total_pages = ceil($total_requests / $requests_per_page);

// Fetch posts that have dislike_button_status as 'pending'
$pending_posts_query = "SELECT p.id, p.title, p.published_at, u.username,
                               (SELECT COUNT(*) FROM likes WHERE post_id = p.id) as total_likes,
                               (SELECT COUNT(*) FROM dislikes WHERE post_id = p.id) as total_dislikes
                        FROM posts p
                        LEFT JOIN users u ON p.user_id = u.id
                        WHERE p.dislike_button_status = 'pending'
                        ORDER BY p.published_at DESC
                        LIMIT ? OFFSET ?";

$pending_posts_stmt = $conn->prepare($pending_posts_query);
$pending_posts_stmt->bind_param("ii", $requests_per_page, $offset);
$pending_posts_stmt->execute();
$pending_posts_result = $pending_posts_stmt->get_result();

if (!$pending_posts_result) {
    echo "<div class='alert alert-danger'>Error fetching pending dislike requests: " . htmlspecialchars($conn->error) . "</div>";
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
    <div class="admin-header mb-4">
        <h1><i class="fas fa-thumbs-down"></i> Manage Dislike Button Requests</h1>
        <p class="text-muted">Review and approve or reject user requests to enable dislike buttons on posts.</p>
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
            <h3>Posts Awaiting Dislike Button Approval</h3>
            <?php if ($pending_posts_result->num_rows > 0): ?>
                <form method="post" action="manage_likes_dislikes.php" style="display:inline;">
                    <button type="submit" name="approve_all_dislikes" class="btn btn-success btn-sm" onclick="return confirm('Are you sure you want to approve ALL pending dislike button requests?');">
                        <i class="fas fa-check-double"></i> Approve All Pending
                    </button>
                </form>
            <?php endif; ?>
        </div>
        <div class="card-body">
            <?php if ($pending_posts_result->num_rows > 0): ?>
                <div class="table-responsive">
                    <table class="admin-table">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Post Title</th>
                                <th>Author</th>
                                <th>Published Date</th>
                                <th>Current Likes</th>
                                <th>Current Dislikes</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            // Rewind result set to display posts after "Approve All" button (if any were approved)
                            $pending_posts_result->data_seek(0);
                            while ($post = $pending_posts_result->fetch_assoc()): ?>
                                <tr>
                                    <td data-label="ID"><?php echo htmlspecialchars($post['id']); ?></td>
                                    <td data-label="Post Title">
                                        <a href="../post.php?id=<?php echo $post['id']; ?>" target="_blank">
                                            <?php echo htmlspecialchars(truncate_text($post['title'], 50)); ?>
                                        </a>
                                    </td>
                                    <td data-label="Author"><?php echo htmlspecialchars($post['username'] ?? 'N/A'); ?></td>
                                    <td data-label="Published Date"><?php echo format_datetime($post['published_at']); ?></td>
                                    <td data-label="Likes"><?php echo htmlspecialchars($post['total_likes']); ?></td>
                                    <td data-label="Dislikes"><?php echo htmlspecialchars($post['total_dislikes']); ?></td>
                                    <td data-label="Action" class="actions">
                                        <form method="post" action="manage_likes_dislikes.php" style="display:inline;">
                                            <input type="hidden" name="dislike_status_post_id" value="<?php echo $post['id']; ?>">
                                            <button type="submit" name="new_dislike_status" value="enabled" class="btn btn-sm btn-success" title="Approve">
                                                <i class="fas fa-check"></i> Approve
                                            </button>
                                            <button type="submit" name="new_dislike_status" value="disabled" class="btn btn-sm btn-danger" title="Reject" onclick="return confirm('Are you sure you want to reject the dislike button for this post?');">
                                                <i class="fas fa-times"></i> Reject
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
                <?php
                // Pagination for Pending Dislike Requests
                $base_url = 'manage_likes_dislikes.php';
                include 'pagination_snippet.php'; // Include the pagination snippet
                ?>
            <?php else: ?>
                <p class="text-muted">No posts currently awaiting dislike button approval.</p>
            <?php endif; ?>
        </div>
    </div>
</div>

<style>
    /* Basic admin table styles (can be shared with manage_posts.php or moved to a common CSS file) */
    .admin-header {
        background: var(--background-white);
        padding: 2rem;
        border-radius: 12px;
        box-shadow: var(--shadow-medium);
        text-align: center;
        margin-bottom: 2rem;
    }
    .card {
        background: var(--background-white);
        border-radius: 12px;
        box-shadow: var(--shadow-medium);
        overflow: hidden;
    }
    .card-header {
        background-color: var(--background-light);
        padding: 1.5rem;
        border-bottom: 1px solid var(--border-color);
        display: flex;
        justify-content: space-between;
        align-items: center;
        font-weight: 600;
        color: var(--heading-color);
    }
    .card-body {
        padding: 2rem;
    }
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
    /* Responsive Table */
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
        .admin-table td:nth-of-type(1):before { content: "ID"; }
        .admin-table td:nth-of-type(2):before { content: "Post Title"; }
        .admin-table td:nth-of-type(3):before { content: "Author"; }
        .admin-table td:nth-of-type(4):before { content: "Published Date"; }
        .admin-table td:nth-of-type(5):before { content: "Likes"; }
        .admin-table td:nth-of-type(6):before { content: "Dislikes"; }
        .admin-table td:nth-of-type(7):before { content: "Action"; }
        .admin-table .actions {
            display: flex;
            justify-content: flex-end;
            margin-top: 10px;
        }
    }
    /* Button styles from previous files for consistency */
    .btn {
        padding: 10px 20px;
        border-radius: 8px;
        cursor: pointer;
        font-size: 1rem;
        font-weight: 500;
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
        transition: background-color 0.2s ease, border-color 0.2s ease, color 0.2s ease, box-shadow 0.2s ease;
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
    .btn-success {
        background-color: #28a745;
        color: #fff;
        border: 1px solid #28a745;
    }
    .btn-success:hover {
        background-color: #218838;
        border-color: #1e7e34;
    }
    .btn-danger {
        background-color: #dc3545;
        color: #fff;
        border: 1px solid #dc3545;
    }
    .btn-danger:hover {
        background-color: #c82333;
        border-color: #bd2130;
    }
    .alert {
        padding: 1rem;
        margin-bottom: 1rem;
        border-radius: 8px;
        font-weight: 500;
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
</style>

<?php ob_end_flush(); ?>
<?php include 'includes/footer.php'; ?>
