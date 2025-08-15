<?php
ob_start();
$page_title = "Manage Comments";
include 'includes/header.php';
require_once 'includes/functions.php'; // Ensure functions are available
require_once 'includes/db_connection.php'; // Ensure db connection is available

// Require admin access
require_admin();

// Handle status update, content update, or delete action
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['comment_id'])) {
    $comment_id = (int)$_POST['comment_id'];

    if (isset($_POST['action'])) {
        $action = $_POST['action'];

        if ($action == 'approve') {
            $update_query = "UPDATE comments SET status = 'approved' WHERE id = ?";
            $stmt = $conn->prepare($update_query);
            $stmt->bind_param("i", $comment_id);
            if ($stmt->execute()) {
                $_SESSION['message'] = "Comment approved successfully!";
                $_SESSION['message_type'] = "success";
            } else {
                $_SESSION['message'] = "Error approving comment: " . htmlspecialchars($conn->error);
                $_SESSION['message_type'] = "error";
            }
            $stmt->close();
        } elseif ($action == 'reject') {
            $update_query = "UPDATE comments SET status = 'rejected' WHERE id = ?";
            $stmt = $conn->prepare($update_query);
            $stmt->bind_param("i", $comment_id);
            if ($stmt->execute()) {
                $_SESSION['message'] = "Comment rejected successfully!";
                $_SESSION['message_type'] = "success";
            } else {
                $_SESSION['message'] = "Error rejecting comment: " . htmlspecialchars($conn->error);
                $_SESSION['message_type'] = "error";
            }
            $stmt->close();
        } elseif ($action == 'delete') {
            $delete_query = "DELETE FROM comments WHERE id = ?";
            $stmt = $conn->prepare($delete_query);
            $stmt->bind_param("i", $comment_id);
            if ($stmt->execute()) {
                $_SESSION['message'] = "Comment deleted successfully!";
                $_SESSION['message_type'] = "success";
            } else {
                $_SESSION['message'] = "Error deleting comment: " . htmlspecialchars($conn->error);
                $_SESSION['message_type'] = "error";
            }
            $stmt->close();
        } elseif ($action == 'update_status_ajax') {
            // Handle AJAX status update from dropdown
            $new_status = sanitize_input($_POST['new_status']);
            $allowed_statuses = ['pending', 'approved', 'rejected'];
            if (in_array($new_status, $allowed_statuses)) {
                $update_query = "UPDATE comments SET status = ? WHERE id = ?";
                $stmt = $conn->prepare($update_query);
                $stmt->bind_param("si", $new_status, $comment_id);
                if ($stmt->execute()) {
                    echo json_encode(['success' => true, 'message' => 'Status updated.']);
                } else {
                    echo json_encode(['success' => false, 'message' => 'DB Error: ' . $conn->error]);
                }
                $stmt->close();
            } else {
                echo json_encode(['success' => false, 'message' => 'Invalid status.']);
            }
            exit(); // Important for AJAX requests
        }
    }
    // Redirect only for non-AJAX POSTs
    header("Location: manage_comments.php");
    exit();
}

// Handle comment update from the edit form (if a search was performed and edit_comment was clicked)
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'update_comment_from_search' && isset($_POST['comment_id'])) {
    $comment_id = (int)$_POST['comment_id'];
    $edit_content = trim($_POST['edit_content']);
    $edit_status = $_POST['edit_status'];

    $update_comment_query = "UPDATE comments SET content = ?, status = ? WHERE id = ?";
    $stmt = $conn->prepare($update_comment_query);
    $stmt->bind_param("ssi", $edit_content, $edit_status, $comment_id);
    if ($stmt->execute()) {
        $_SESSION['message'] = "Comment updated successfully!";
        $_SESSION['message_type'] = "success";
    } else {
        $_SESSION['message'] = "Error updating comment: " . htmlspecialchars($conn->error);
        $_SESSION['message_type'] = "error";
    }
    $stmt->close();
    header("Location: manage_comments.php");
    exit();
}

// Pagination for Comments
$comments_per_page = 10;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $comments_per_page;

// Get total count of comments
$total_comments_query = "SELECT COUNT(*) as total FROM comments";
$total_comments_result = $conn->query($total_comments_query);
$total_comments = $total_comments_result->fetch_assoc()['total'];
$total_pages = ceil($total_comments / $comments_per_page);


// Fetch all comments for display, including post title and author
$comments_query = "SELECT c.*, p.title as post_title, u.username, u2.username as reply_to_username
                   FROM comments c
                   LEFT JOIN posts p ON c.post_id = p.id
                   LEFT JOIN users u ON c.user_id = u.id
                   LEFT JOIN comments pc ON c.parent_comment_id = pc.id
                   LEFT JOIN users u2 ON pc.user_id = u2.id -- To get username of parent comment author
                   ORDER BY c.created_at DESC
                   LIMIT ? OFFSET ?";
$comments_stmt = $conn->prepare($comments_query);
$comments_stmt->bind_param("ii", $comments_per_page, $offset);
$comments_stmt->execute();
$comments_result = $comments_stmt->get_result();

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
<?php
// If a search was performed and a comment edit is requested via search, show the edit form at the top
if (!empty($search_query)) {
    if (isset($_GET['edit_comment']) && is_numeric($_GET['edit_comment'])) {
        // Fetch the comment to edit
        $edit_comment_id = (int)$_GET['edit_comment'];
        $edit_comment_query = "SELECT c.*, u.username FROM comments c LEFT JOIN users u ON c.user_id = u.id WHERE c.id = ?";
        $stmt = $conn->prepare($edit_comment_query);
        $stmt->bind_param("i", $edit_comment_id);
        $stmt->execute();
        $edit_comment_result = $stmt->get_result();
        $edit_comment = $edit_comment_result->fetch_assoc();
        $stmt->close();

        if ($edit_comment) {
            echo '<div class="alert alert-info mb-4"><i class="fas fa-edit"></i> Editing comment found from search: <strong>' . htmlspecialchars(truncate_text($edit_comment['content'], 60)) . '</strong></div>';
            ?>
            <div class="card mb-4">
                <div class="card-header">
                    <h3>Edit Comment</h3>
                </div>
                <div class="card-body">
                    <form action="manage_comments.php" method="POST">
                        <input type="hidden" name="comment_id" value="<?php echo $edit_comment['id']; ?>">
                        <div class="mb-3">
                            <label for="edit_content" class="form-label">Content</label>
                            <textarea name="edit_content" id="edit_content" class="form-control" rows="3" required><?php echo htmlspecialchars($edit_comment['content']); ?></textarea>
                        </div>
                        <div class="mb-3">
                            <label for="edit_status" class="form-label">Status</label>
                            <select name="edit_status" id="edit_status" class="form-select">
                                <option value="pending" <?php if ($edit_comment['status'] == 'pending') echo 'selected'; ?>>Pending</option>
                                <option value="approved" <?php if ($edit_comment['status'] == 'approved') echo 'selected'; ?>>Approved</option>
                                <option value="rejected" <?php if ($edit_comment['status'] == 'rejected') echo 'selected'; ?>>Rejected</option>
                            </select>
                        </div>
                        <button type="submit" name="action" value="update_comment_from_search" class="btn btn-primary">Update Comment</button>
                        <a href="manage_comments.php" class="btn btn-secondary">Cancel</a>
                    </form>
                </div>
            </div>
            <?php
        }
    }
}
?>
<div class="container">
    <div class="admin-header mb-4">
        <h1><i class="fas fa-comments"></i> Manage Comments</h1>
        <p class="text-muted">Review, approve, reject, or delete user comments.</p>
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
            <h3>All Comments</h3>
        </div>
        <div class="card-body">
            <?php if ($comments_result->num_rows > 0): ?>
                <div class="table-responsive">
                    <table class="admin-table">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Content</th>
                                <th>Author</th>
                                <th>On Post</th>
                                <th>Parent Comment</th>
                                <th>Status</th>
                                <th>Date</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($comment = $comments_result->fetch_assoc()): ?>
                                <tr>
                                    <td data-label="ID"><?php echo htmlspecialchars($comment['id']); ?></td>
                                    <td data-label="Content"><?php echo htmlspecialchars(truncate_text($comment['content'], 60)); ?></td>
                                    <td data-label="Author"><?php echo htmlspecialchars($comment['username'] ?? $comment['author_name'] ?? 'Guest'); ?></td>
                                    <td data-label="On Post">
                                        <?php if ($comment['post_title']): ?>
                                            <a href="../post.php?id=<?php echo $comment['post_id']; ?>" target="_blank">
                                                <?php echo htmlspecialchars(truncate_text($comment['post_title'], 30)); ?>
                                            </a>
                                        <?php else: ?>
                                            N/A
                                        <?php endif; ?>
                                    </td>
                                    <td data-label="Parent Comment">
                                        <?php if ($comment['parent_comment_id']): ?>
                                            Reply to <?php echo htmlspecialchars($comment['reply_to_username'] ?? 'Comment ' . $comment['parent_comment_id']); ?>
                                        <?php else: ?>
                                            No Parent
                                        <?php endif; ?>
                                    </td>
                                    <td data-label="Status">
                                        <select class="comment-status-select form-select" data-comment-id="<?php echo $comment['id']; ?>">
                                            <option value="pending" <?php if ($comment['status'] == 'pending') echo 'selected'; ?>>Pending</option>
                                            <option value="approved" <?php if ($comment['status'] == 'approved') echo 'selected'; ?>>Approved</option>
                                            <option value="rejected" <?php if ($comment['status'] == 'rejected') echo 'selected'; ?>>Rejected</option>
                                        </select>
                                    </td>
                                    <td data-label="Date"><?php echo format_datetime($comment['created_at']); ?></td>
                                    <td class="actions" data-label="Actions">
                                        <form action="manage_comments.php" method="POST" style="display:inline-block;">
                                            <input type="hidden" name="comment_id" value="<?php echo $comment['id']; ?>">
                                            <button type="submit" name="action" value="delete" class="btn btn-sm btn-danger" title="Delete" onclick="return confirm('Are you sure you want to delete this comment?');"><i class="fas fa-trash"></i> Delete</button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
                <?php
                // Pagination for Comments
                $base_url = 'manage_comments.php';
                // Preserve existing GET parameters like filters/search if present
                $current_query_string = $_SERVER['QUERY_STRING'];
                if (!empty($current_query_string)) {
                    $base_url .= '?' . $current_query_string;
                }
                include 'pagination_snippet.php'; // Include the pagination snippet
                ?>
            <?php else: ?>
                <p class="text-muted">No comments found.</p>
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

    /* Re-using admin-table styles from manage_posts.php for consistency */
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

    /* Status color classes (kept for potential other uses or text spans if dropdown is not preferred everywhere) */
    .status-approved {
        color: var(--primary-color);
        font-weight: bold;
    }
    .status-pending {
        color: var(--secondary-color);
        font-weight: bold;
    }
    .status-rejected {
        color: var(--accent-color);
        font-weight: bold;
    }

    /* Styles for the new status select dropdown */
    .comment-status-select {
        padding: 0.5rem 0.75rem;
        border-radius: 8px;
        border: 1px solid var(--border-color);
        background-color: var(--background-white);
        font-size: 0.9rem;
        cursor: pointer;
        transition: border-color 0.2s ease, box-shadow 0.2s ease;
    }

    .comment-status-select:focus {
        border-color: var(--primary-color);
        box-shadow: 0 0 0 0.2rem rgba(52, 152, 219, 0.15);
        outline: none;
    }
    
    /* Responsive Table */
    @media (max-width: 768px) {
        .admin-table thead {
            display: none;
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
            padding-left: 50%;
            position: relative;
        }

        .admin-table td::before {
            content: attr(data-label);
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
        .admin-table td:nth-of-type(2):before { content: "Content"; }
        .admin-table td:nth-of-type(3):before { content: "Author"; }
        .admin-table td:nth-of-type(4):before { content: "On Post"; }
        .admin-table td:nth-of-type(5):before { content: "Parent Comment"; }
        .admin-table td:nth-of-type(6):before { content: "Status"; }
        .admin-table td:nth-of-type(7):before { content: "Date"; }
        .admin-table td:nth-of-type(8):before { content: "Actions"; }
        
        .admin-table .actions {
            display: flex;
            justify-content: flex-end;
            margin-top: 10px;
        }
    }
</style>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const statusSelects = document.querySelectorAll('.comment-status-select');

    statusSelects.forEach(select => {
        select.addEventListener('change', async function() {
            const commentId = this.dataset.commentId;
            const newStatus = this.value;

            try {
                const formData = new FormData();
                formData.append('comment_id', commentId);
                formData.append('new_status', newStatus);
                formData.append('action', 'update_status_ajax'); // Indicate AJAX action

                const response = await fetch('manage_comments.php', {
                    method: 'POST',
                    body: formData
                });

                const data = await response.json();

                if (data.success) {
                    // Optional: Provide visual feedback like a temporary success message
                    console.log('Comment status updated successfully:', data.message);
                    // Update the row's background or add a flash for success
                    const row = this.closest('tr');
                    if (row) {
                        row.style.transition = 'background-color 0.3s ease';
                        row.style.backgroundColor = 'rgba(46, 204, 113, 0.2)'; // Light green
                        setTimeout(() => {
                            row.style.backgroundColor = ''; // Reset after a short delay
                        }, 1000);
                    }
                } else {
                    alert('Failed to update comment status: ' + data.message);
                    console.error('Failed to update comment status:', data.message);
                    // Revert the dropdown to its previous value if update failed
                    // This requires storing the original value, or fetching it again
                    // For now, a simple alert.
                }
            } catch (error) {
                console.error('Error during AJAX request:', error);
                alert('An error occurred while updating the comment status. Please try again.');
            }
        });
    });
});
</script>
<?php ob_end_flush(); ?>
<?php include 'includes/footer.php'; ?>
