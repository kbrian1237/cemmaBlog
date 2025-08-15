<?php
// manage_followers.php - Allows administrators to view and manage user follow relationships.

ob_start(); // Start output buffering
$page_title = "Manage Followers";
include 'includes/header.php';
require_once 'includes/functions.php'; // Ensure functions.php is included
require_once 'includes/db_connection.php'; // Ensure db connection is available

// Require admin access
require_admin();

// --- Handle Delete Action (for direct link or simple form submission, though AJAX is preferred) ---
// This GET-based delete logic assumes a single 'id' for the follow relationship.
// Since user_follows table uses a composite primary key (follower_id, followed_id),
// direct deletion via a single 'delete' GET parameter is not ideal unless your table
// has an additional auto-incrementing 'id' column.
// The AJAX button at the bottom of the page uses both follower_id and followed_id,
// which is the correct way to identify and delete a follow relationship for this schema.
// Keeping this block for backward compatibility if 'id' was intended to be added later,
// but it will likely not work as expected with the current schema.
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    // This part is problematic with the current user_follows schema (no 'id' column).
    // The unfollow_user function correctly uses follower_id and followed_id.
    // If you need direct URL deletion, you should pass both follower_id and followed_id.
    // E.g., manage_followers.php?delete_follower=1&delete_followed=2
    $_SESSION['message'] = "Direct 'delete' by single ID is not supported for follow relationships. Please use the 'Unfollow' button.";
    $_SESSION['message_type'] = "error";
    header("Location: manage_followers.php");
    exit();
}

// --- Pagination ---
$follows_per_page = 10;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $follows_per_page;

// Get total follows count
$total_follows_query = "SELECT COUNT(*) as total FROM user_follows";
$total_result = $conn->query($total_follows_query);
$total_follows = $total_result->fetch_assoc()['total'];
$total_pages = ceil($total_follows / $follows_per_page);

// Get follow relationships with usernames
// IMPORTANT FIX: Removed 'uf.id' from SELECT statement as your user_follows table does not have an 'id' column.
// The primary key is the composite of (follower_id, followed_id).
$follows_query = "SELECT uf.follower_id, uf.followed_id, uf.created_at,
                         u1.username AS follower_username,
                         u2.username AS followed_username
                  FROM user_follows uf
                  JOIN users u1 ON uf.follower_id = u1.id
                  JOIN users u2 ON uf.followed_id = u2.id
                  ORDER BY uf.created_at DESC
                  LIMIT ? OFFSET ?";
$follows_stmt = $conn->prepare($follows_query);

// --- IMPORTANT FIX: Check if prepare was successful before binding parameters ---
if ($follows_stmt === false) {
    error_log("Failed to prepare follows query: " . $conn->error);
    $_SESSION['message'] = "Database error: Could not retrieve follow relationships. Please check server logs for details (e.g., SQL syntax error or table issues).";
    $_SESSION['message_type'] = "error";
    $follows_result = false; // Set to false to indicate an error and prevent further execution
} else {
    $follows_stmt->bind_param("ii", $follows_per_page, $offset);
    $follows_stmt->execute();
    $follows_result = $follows_stmt->get_result();
    $follows_stmt->close();
}

// Display session messages
if (isset($_SESSION['message'])): ?>
    <div class="alert alert-<?php echo $_SESSION['message_type']; ?> mb-3">
        <?php echo $_SESSION['message']; ?>
        <button type="button" class="close-alert" onclick="this.parentElement.style.display='none';">&times;</button>
    </div>
    <?php
    unset($_SESSION['message']);
    unset($_SESSION['message_type']);
endif;
?>

<div class="container">
    <div class="admin-header mb-4">
        <h1><i class="fas fa-user-friends"></i> Manage Followers</h1>
        <p class="text-muted">View and manage follow relationships between users.</p>
    </div>

    <div class="dashboard-card">
        <h3>All Follow Relationships</h3>
        <?php if ($follows_result && $follows_result->num_rows > 0): // Check if $follows_result is not false and has rows ?>
            <div class="table-responsive">
                <table class="admin-table">
                    <thead>
                        <tr>
                            <!-- IMPORTANT FIX: Removed ID column as user_follows table does not have one -->
                            <th>Follower</th>
                            <th>Followed</th>
                            <th>Follow Date</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($follow = $follows_result->fetch_assoc()): ?>
                            <tr data-follower-id="<?php echo $follow['follower_id']; ?>" data-followed-id="<?php echo $follow['followed_id']; ?>">
                                <!-- IMPORTANT FIX: Removed TD for ID -->
                                <td data-label="Follower">
                                    <a href="view_user.php?id=<?php echo $follow['follower_id']; ?>">
                                        <?php echo htmlspecialchars($follow['follower_username']); ?>
                                    </a>
                                </td>
                                <td data-label="Followed">
                                    <a href="view_user.php?id=<?php echo $follow['followed_id']; ?>">
                                        <?php echo htmlspecialchars($follow['followed_username']); ?>
                                    </a>
                                </td>
                                <td data-label="Follow Date"><?php echo format_datetime($follow['created_at']); ?></td>
                                <td data-label="Actions">
                                    <button class="btn btn-danger btn-sm unfollow-btn"
                                            data-follower-id="<?php echo $follow['follower_id']; ?>"
                                            data-followed-id="<?php echo $follow['followed_id']; ?>">
                                        <i class="fas fa-user-minus"></i> Unfollow
                                    </button>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <?php if ($total_pages > 1): ?>
                <div class="pagination mt-3">
                    <?php if ($page > 1): ?>
                        <a href="?page=<?php echo $page - 1; ?>">&laquo; Previous</a>
                    <?php endif; ?>
                    
                    <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                        <?php if ($i == $page): ?>
                            <span class="current"><?php echo $i; ?></span>
                        <?php else: ?>
                            <a href="?page=<?php echo $i; ?>"><?php echo $i; ?></a>
                        <?php endif; ?>
                    <?php endfor; ?>
                    
                    <?php if ($page < $total_pages): ?>
                        <a href="?page=<?php echo $page + 1; ?>">Next &raquo;</a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

        <?php else: ?>
            <p class="text-muted">No follow relationships found yet or an error occurred.</p>
        <?php endif; ?>
    </div>
</div>

<style>
/* Reusing styles from admin_dashboard.php and manage_posts.php for consistency */
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

.container {
    max-width: 1200px;
    margin: 20px auto;
    padding: 0 15px;
}

.admin-header {
    background: var(--background-white);
    padding: 2rem;
    border-radius: 12px;
    box-shadow: var(--shadow-medium);
    text-align: center;
    margin-bottom: 2rem;
}

.admin-header h1 {
    color: var(--heading-color);
    margin-bottom: 0.5rem;
    font-size: 2.5rem;
}

.admin-header p {
    color: var(--secondary-color);
    font-size: 1.1rem;
}

.dashboard-card {
    background: var(--background-white);
    padding: 1.5rem;
    border-radius: 12px;
    box-shadow: var(--shadow-medium);
    display: flex;
    flex-direction: column;
}

.dashboard-card h3 {
    color: var(--heading-color);
    margin-bottom: 1rem;
    display: flex;
    align-items: center;
    gap: 0.75rem;
    font-size: 1.5rem;
}

.dashboard-card h3 .fas {
    color: var(--primary-color);
}

.table-responsive {
    overflow-x: auto;
}

.admin-table {
    width: 100%;
    border-collapse: collapse;
    margin-top: 1rem;
    background-color: var(--background-white);
    border-radius: 8px;
    overflow: hidden; /* Ensures rounded corners apply to table content */
    box-shadow: var(--shadow-light);
}

.admin-table th,
.admin-table td {
    padding: 12px 15px;
    text-align: left;
    border-bottom: 1px solid var(--border-color);
    color: var(--text-color);
}

.admin-table th {
    background-color: var(--background-light);
    color: var(--heading-color);
    font-weight: 600;
    text-transform: uppercase;
    font-size: 0.9rem;
}

.admin-table tbody tr:last-child td {
    border-bottom: none;
}

.admin-table tbody tr:hover {
    background-color: var(--background-light);
}

.admin-table a {
    color: var(--primary-color);
    text-decoration: none;
    transition: color 0.2s ease;
}

.admin-table a:hover {
    text-decoration: underline;
    color: var(--primary-dark);
}

.btn {
    padding: 8px 12px;
    border-radius: 8px;
    cursor: pointer;
    font-size: 0.85rem;
    font-weight: 500;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    transition: background-color 0.2s ease, border-color 0.2s ease, color 0.2s ease, box-shadow 0.2s ease;
    border: 1px solid transparent;
}

.btn-primary {
    background-color: var(--primary-color);
    color: #fff;
    border-color: var(--primary-color);
}

.btn-primary:hover {
    background-color: var(--primary-dark);
    border-color: var(--primary-dark);
    box-shadow: var(--shadow-hover);
}

.btn-danger {
    background-color: var(--accent-color);
    color: #fff;
    border-color: var(--accent-color);
}

.btn-danger:hover {
    background-color: #c0392b;
    border-color: #c0392b;
    box-shadow: var(--shadow-hover);
}

.btn-sm {
    padding: 0.4rem 0.7rem;
    font-size: 0.75rem;
}

/* Pagination styles */
.pagination {
    display: flex;
    justify-content: center;
    align-items: center;
    margin-top: 2rem;
    gap: 0.5rem;
}

.pagination a,
.pagination span {
    display: inline-block;
    padding: 0.75rem 1.25rem;
    border: 1px solid var(--border-color);
    border-radius: 8px;
    text-decoration: none;
    color: var(--primary-color);
    background-color: var(--background-white);
    transition: all 0.3s ease;
}

.pagination a:hover {
    background-color: var(--primary-color);
    color: #fff;
    border-color: var(--primary-color);
    box-shadow: var(--shadow-light);
}

.pagination .current {
    background-color: var(--primary-color);
    color: #fff;
    border-color: var(--primary-color);
    font-weight: bold;
    cursor: default;
}

.text-muted {
    color: var(--secondary-color);
    text-align: center;
    padding: 1rem;
}

.alert {
    padding: 0.75rem 1rem;
    border-radius: 8px;
    font-weight: 500;
    display: flex;
    align-items: center;
    gap: 0.75rem;
    margin-bottom: 1.5rem;
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

.close-alert {
    background: none;
    border: none;
    font-size: 1.5rem;
    line-height: 1;
    cursor: pointer;
    color: inherit;
    margin-left: auto;
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

    /* Updated data-label content for responsive table */
    /* Removed ID related labels as the column is removed */
    .admin-table td:nth-of-type(1):before { content: "Follower"; }
    .admin-table td:nth-of-type(2):before { content: "Followed"; }
    .admin-table td:nth-of-type(3):before { content: "Follow Date"; }
    .admin-table td:nth-of-type(4):before { content: "Actions"; }

    .admin-table td .btn {
        width: 100%;
        justify-content: center;
    }
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Function to handle unfollow action via AJAX
    document.querySelectorAll('.unfollow-btn').forEach(button => {
        button.addEventListener('click', async function() {
            const followerId = this.dataset.followerId;
            const followedId = this.dataset.followedId;
            const followRow = this.closest('tr'); // Get the table row

            // Show a confirmation dialog (using a custom modal if available, or browser confirm for now)
            if (!confirm(`Are you sure you want to unfollow user ID ${followedId} from user ID ${followerId}?`)) {
                return; // User cancelled
            }

            this.disabled = true; // Disable button to prevent multiple clicks

            const formData = new FormData();
            formData.append('action', 'unfollow');
            formData.append('follower_id', followerId); // Need follower_id to identify the specific follow
            formData.append('followed_id', followedId);

            try {
                const response = await fetch('follow_action.php', {
                    method: 'POST',
                    body: formData
                });
                const data = await response.json();

                if (data.success) {
                    // Remove the row from the table on success
                    if (followRow) {
                        followRow.remove();
                    }
                    // Optionally, show a success message to the user
                    const alertDiv = document.createElement('div');
                    alertDiv.classList.add('alert', 'alert-success', 'mb-3');
                    alertDiv.innerHTML = `<i class="fas fa-check-circle"></i> ${data.message}
                                          <button type="button" class="close-alert" onclick="this.parentElement.style.display='none';">&times;</button>`;
                    document.querySelector('.container').prepend(alertDiv);
                } else {
                    // Show error message
                    const alertDiv = document.createElement('div');
                    alertDiv.classList.add('alert', 'alert-error', 'mb-3');
                    alertDiv.innerHTML = `<i class="fas fa-exclamation-circle"></i> ${data.message}
                                          <button type="button" class="close-alert" onclick="this.parentElement.style.display='none';">&times;</button>`;
                    document.querySelector('.container').prepend(alertDiv);
                }
            } catch (error) {
                console.error('Fetch error:', error);
                const alertDiv = document.createElement('div');
                alertDiv.classList.add('alert', 'alert-error', 'mb-3');
                alertDiv.innerHTML = `<i class="fas fa-exclamation-circle"></i> An error occurred. Please try again.
                                      <button type="button" class="close-alert" onclick="this.parentElement.style.display='none';">&times;</button>`;
                document.querySelector('.container').prepend(alertDiv);
            } finally {
                this.disabled = false; // Re-enable button
            }
        });
    });
});
</script>

<?php ob_end_flush(); ?>
<?php include 'includes/footer.php'; ?>
