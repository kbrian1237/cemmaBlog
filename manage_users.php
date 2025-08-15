<?php
$page_title = "Manage Users";

// Handle delete action
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    require_once __DIR__ . '/includes/db.php';
    session_start();
    require_once 'includes/functions.php';
    require_admin();

    $user_id_to_delete = (int)$_GET['delete'];

    // Prevent deletion of the current logged-in admin user
    if (isset($_SESSION['user_id']) && $_SESSION['user_id'] == $user_id_to_delete) {
        $_SESSION['message'] = "You cannot delete your own account!";
        $_SESSION['message_type'] = "error";
    } else {
        // Delete user's posts, comments, etc., before deleting the user
        // This assumes CASCADE DELETE is not set up in your database or you want explicit control.
        // For simplicity, we'll just delete the user here. In a real app, you'd handle associated data carefully.
        
        // Delete posts by the user
        $delete_posts_query = "DELETE FROM posts WHERE user_id = ?";
        $delete_posts_stmt = $conn->prepare($delete_posts_query);
        $delete_posts_stmt->bind_param("i", $user_id_to_delete);
        $delete_posts_stmt->execute();
        $delete_posts_stmt->close();

        // Delete comments by the user
        $delete_comments_query = "DELETE FROM comments WHERE user_id = ?";
        $delete_comments_stmt = $conn->prepare($delete_comments_query);
        $delete_comments_stmt->bind_param("i", $user_id_to_delete);
        $delete_comments_stmt->execute();
        $delete_comments_stmt->close();

        $delete_user_query = "DELETE FROM users WHERE id = ?";
        $delete_user_stmt = $conn->prepare($delete_user_query);
        $delete_user_stmt->bind_param("i", $user_id_to_delete);
        if ($delete_user_stmt->execute()) {
            $_SESSION['message'] = "User deleted successfully!";
            $_SESSION['message_type'] = "success";
        } else {
            $_SESSION['message'] = "Error deleting user: " . htmlspecialchars($conn->error);
            $_SESSION['message_type'] = "error";
        }
        $delete_user_stmt->close();
    }
    header("Location: manage_users.php");
    exit();
}

// Handle role update action
if (isset($_POST['update_role'])) {
    require_once __DIR__ . '/includes/db_connection.php';
    session_start();
    require_once 'includes/functions.php';
    require_admin();

    $user_id = (int)$_POST['user_id'];
    $new_role = sanitize_input($_POST['role']); // Assuming sanitize_input exists

    if (!in_array($new_role, ['user', 'admin'])) { // Validate roles
        $_SESSION['message'] = "Invalid role specified.";
        $_SESSION['message_type'] = "error";
    } else if (isset($_SESSION['user_id']) && $_SESSION['user_id'] == $user_id && $new_role !== 'admin') {
         // Prevent an admin from demoting themselves via this page
        $_SESSION['message'] = "You cannot demote your own account!";
        $_SESSION['message_type'] = "error";
    } else {
        $update_role_query = "UPDATE users SET role = ? WHERE id = ?";
        $update_role_stmt = $conn->prepare($update_role_query);
        $update_role_stmt->bind_param("si", $new_role, $user_id);
        if ($update_role_stmt->execute()) {
            $_SESSION['message'] = "User role updated successfully!";
            $_SESSION['message_type'] = "success";
        } else {
            $_SESSION['message'] = "Error updating user role: " . htmlspecialchars($conn->error);
            $_SESSION['message_type'] = "error";
        }
        $update_role_stmt->close();
    }
    header("Location: manage_users.php");
    exit();
}

include 'includes/header.php';

// Require admin access
require_admin();

// Fetch all users for display
$users_query = "SELECT * FROM users ORDER BY created_at DESC";
$users_result = $conn->query($users_query);

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
        <h1><i class="fas fa-users"></i> Manage Users</h1>
        <p class="text-muted">View and manage registered users.</p>
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
            <h3>All Users</h3>
            <!-- Potentially add a link to create a new user if that functionality exists -->
        </div>
        <div class="card-body">
            <?php if ($users_result->num_rows > 0): ?>
                <div class="table-responsive">
                    <table class="admin-table">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Username</th>
                                <th>Email</th>
                                <th>Role</th>
                                <th>Registered Date</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($user = $users_result->fetch_assoc()): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($user['id']); ?></td>
                                    <td><?php echo htmlspecialchars($user['username']); ?></td>
                                    <td><?php echo htmlspecialchars($user['email']); ?></td>
                                    <td>
                                        <form action="manage_users.php" method="POST" style="display:inline-block;">
                                            <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                            <select name="role" class="form-select-inline" onchange="this.form.submit()">
                                                <option value="user" <?php echo ($user['role'] == 'user') ? 'selected' : ''; ?>>User</option>
                                                <option value="admin" <?php echo ($user['role'] == 'admin') ? 'selected' : ''; ?>>Admin</option>
                                            </select>
                                            <input type="hidden" name="update_role" value="1">
                                        </form>
                                    </td>
                                    <td><?php echo format_datetime($user['created_at']); ?></td>
                                    <td class="actions">
                                        <a href="manage_users.php?delete=<?php echo $user['id']; ?>" class="btn btn-sm btn-danger" title="Delete" onclick="return confirm('Are you sure you want to delete this user? This will also delete their posts and comments.');"><i class="fas fa-trash"></i></a>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
                <?php
                // Pagination for Posts
                $base_url = 'manage_users.php';
                include 'pagination_snippet.php'; // Include the pagination snippet
                ?>
            <?php else: ?>
                <p class="text-muted">No users found.</p>
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

    .form-select-inline {
        padding: 6px 10px;
        border-radius: 5px;
        border: 1px solid var(--border-color);
        background-color: var(--background-white);
        font-size: 0.9rem;
        color: var(--text-color);
    }
    .form-select-inline:focus {
        border-color: var(--primary-color);
        outline: 0;
        box-shadow: 0 0 0 0.2rem rgba(52, 152, 219, 0.25);
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
        .admin-table td:nth-of-type(2):before { content: "Username"; }
        .admin-table td:nth-of-type(3):before { content: "Email"; }
        .admin-table td:nth-of-type(4):before { content: "Role"; }
        .admin-table td:nth-of-type(5):before { content: "Registered Date"; }
        .admin-table td:nth-of-type(6):before { content: "Actions"; }
        
        .admin-table .actions {
            display: flex;
            justify-content: flex-end;
            margin-top: 10px;
        }
    }
</style>

<?php include 'includes/footer.php'; ?>
