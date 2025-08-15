<?php
// edit_profile.php - Allows authenticated users to edit their profile details.

// Start a session to access user data
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Include necessary files
require_once 'includes/db_connection.php'; // Assumed to establish $conn (mysqli)
require_once 'includes/functions.php';     // Assumed to contain is_logged_in(), sanitize_input(), validate_email(), get_user_by_id(), update_user_bio()

// Redirect if not logged in
require_login(); // This function should redirect if user is not logged in

$user_id = $_SESSION['user_id'];

// Fetch current user details for the form
$user = get_user_by_id($conn, $user_id); // Assuming get_user_by_id fetches by ID

if (!$user) {
    // User not found, possibly invalid session, destroy session and redirect
    session_unset();
    session_destroy();
    header('Location: login.php');
    exit();
}

$page_title = "Edit Profile";
include 'includes/header.php'; // Includes header HTML and opens <body>

$success_message = '';
$error_message = '';

// Handle Form Submission (POST Request)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $new_username = sanitize_input($_POST['username'] ?? '');
    $new_email = sanitize_input($_POST['email'] ?? '');
    $new_bio = sanitize_input($_POST['bio'] ?? ''); // Get the new bio

    // Basic validation for username and email
    if (empty($new_username) || empty($new_email)) {
        $error_message = 'Username and Email are required.';
    } elseif (!validate_email($new_email)) {
        $error_message = 'Please enter a valid email address.';
    } else {
        // Check if username or email already exists for another user
        $check_query = "SELECT id FROM users WHERE (username = ? OR email = ?) AND id != ?";
        $check_stmt = $conn->prepare($check_query);
        $check_stmt->bind_param("ssi", $new_username, $new_email, $user_id);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();

        if ($check_result->num_rows > 0) {
            $error_message = 'Username or email already exists for another user.';
        } else {
            // Update user details (username, email, and bio)
            $update_query = "UPDATE users SET username = ?, email = ?, bio = ? WHERE id = ?";
            $update_stmt = $conn->prepare($update_query);
            $update_stmt->bind_param("sssi", $new_username, $new_email, $new_bio, $user_id);

            if ($update_stmt->execute()) {
                $success_message = 'Profile updated successfully!';
                // Update session variables immediately
                $_SESSION['username'] = $new_username;
                // Re-fetch user data to update the displayed values
                $user = get_user_by_id($conn, $user_id);
            } else {
                $error_message = 'Failed to update profile. Please try again.';
            }
        }
    }
}

// Use current or updated user data for form fields
$display_username = htmlspecialchars($user['username'] ?? '');
$display_email = htmlspecialchars($user['email'] ?? '');
$display_bio = htmlspecialchars($user['bio'] ?? ''); // Get the current bio for display
?>

<div class="container">
    <div class="form-container">
        <h2 class="text-center mb-4">Edit Your Profile</h2>

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

        <form action="edit_profile.php" method="POST">
            <div class="form-group">
                <label for="username" class="form-label">Username:</label>
                <input type="text" id="username" name="username" class="form-input" value="<?php echo $display_username; ?>" required>
            </div>

            <div class="form-group">
                <label for="email" class="form-label">Email:</label>
                <input type="email" id="email" name="email" class="form-input" value="<?php echo $display_email; ?>" required>
            </div>

            <div class="form-group">
                <label for="bio" class="form-label">Bio:</label>
                <textarea id="bio" name="bio" class="form-input" rows="5" placeholder="Tell us a little about yourself..."><?php echo $display_bio; ?></textarea>
                <small class="text-muted">A short description about yourself (max 255 characters).</small>
            </div>

            <button type="submit" class="btn btn-primary btn-full-width">Save Changes</button>
        </form>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
