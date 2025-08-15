<?php
// change_password.php - Allows authenticated users to change their password.

// Start a session to access user data
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Include necessary files
require_once 'includes/db_connection.php'; // Assumed to establish $conn (mysqli)
require_once 'includes/functions.php';     // Assumed to contain require_login(), sanitize_input(), validate_password(), get_user_by_id()

// Redirect if not logged in
require_login();

$user_id = $_SESSION['user_id'];

// Fetch current user details to get the stored password hash
// Assuming get_user_by_id fetches user data including password hash,
// and that the column for hashed password is named 'password' as per your login.php
$user = get_user_by_id($conn, $user_id);

if (!$user) {
    // User not found, possibly invalid session, destroy session and redirect
    session_unset();
    session_destroy();
    header('Location: login.php');
    exit();
}

$page_title = "Change Password";
include 'includes/header.php'; // Includes header HTML and opens <body>

$success_message = '';
$error_message = '';

// Handle Form Submission (POST Request)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $current_password = $_POST['current_password'] ?? '';
    $new_password = $_POST['new_password'] ?? '';
    $confirm_new_password = $_POST['confirm_new_password'] ?? '';

    // Basic validation
    if (empty($current_password) || empty($new_password) || empty($confirm_new_password)) {
        $error_message = 'All fields are required.';
    } elseif ($new_password !== $confirm_new_password) {
        $error_message = 'New password and confirmation do not match.';
    } elseif (!validate_password($new_password)) { // Using your existing validate_password() function
        $error_message = "New password must be at least 8 characters long and contain at least one uppercase letter, one lowercase letter, and one number.";
    } else {
        // Verify current password against the stored hash
        // Using 'password' column as per your login.php and register.php
        if (password_verify($current_password, $user['password'])) {
            // Current password is correct, hash the new password
            $new_password_hashed = password_hash($new_password, PASSWORD_DEFAULT);

            // Update the user's password in the database
            $update_query = "UPDATE users SET password = ? WHERE id = ?";
            $update_stmt = $conn->prepare($update_query);
            $update_stmt->bind_param("si", $new_password_hashed, $user_id);

            if ($update_stmt->execute()) {
                $success_message = 'Password changed successfully!';
            } else {
                $error_message = 'Failed to change password. Please try again.';
            }
        } else {
            $error_message = 'Incorrect current password.';
        }
    }
}
?>

<div class="container">
    <div class="form-container">
        <h2 class="text-center mb-4">Change Your Password</h2>

        <?php if ($success_message): ?>
            <div class="alert alert-success mb-3">
                <i class="fas fa-check-circle"></i> <?php echo $success_message; ?>
            </div>
        <?php endif; ?>

        <?php if ($error_message): ?>
            <div class="alert alert-error mb-3">
                <i class="fas fa-exclamation-circle"></i> <?php echo $error_message; ?>
            </div>
        <?php endif; ?>

        <form action="change_password.php" method="POST">
            <div class="form-group">
                <label for="current_password" class="form-label">Current Password:</label>
                <input type="password" id="current_password" name="current_password" class="form-input" required>
            </div>

            <div class="form-group">
                <label for="new_password" class="form-label">New Password:</label>
                <input type="password" id="new_password" name="new_password" class="form-input" required>
                <small class="text-muted">At least 8 characters with uppercase, lowercase, and number</small>
            </div>

            <div class="form-group">
                <label for="confirm_new_password" class="form-label">Confirm New Password:</label>
                <input type="password" id="confirm_new_password" name="confirm_new_password" class="form-input" required>
            </div>

            <button type="submit" class="btn btn-primary btn-full-width">Change Password</button>
        </form>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
