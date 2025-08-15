<?php
ob_start();
$page_title = "Manage Website Settings";
include 'includes/header.php';
require_once 'includes/functions.php';
require_once 'includes/db_connection.php'; // Ensure db connection is available

// Require admin access
require_admin();

// --- Database Table for Settings ---
// Assumed table structure (you would create this once, e.g., in blog_db.sql):
// CREATE TABLE IF NOT EXISTS settings (
//     setting_key VARCHAR(100) PRIMARY KEY,
//     setting_value TEXT
// );
// INSERT IGNORE INTO settings (setting_key, setting_value) VALUES
// ('site_title', 'My Awesome Blog'),
// ('site_description', 'A blog about everything and anything.'),
// ('posts_per_page', '10'),
// ('comments_moderation', 'auto'); // 'auto' or 'manual'

// Function to get a setting
function get_setting($conn, $key) {
    $stmt = $conn->prepare("SELECT setting_value FROM settings WHERE setting_key = ?");
    $stmt->bind_param("s", $key);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($row = $result->fetch_assoc()) {
        return htmlspecialchars($row['setting_value']);
    }
    return ''; // Default empty string if not found
}

// Function to update a setting
function update_setting($conn, $key, $value) {
    $stmt = $conn->prepare("INSERT INTO settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = ?");
    $stmt->bind_param("sss", $key, $value, $value);
    return $stmt->execute();
}


// Handle form submission for updating settings
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_settings'])) {
    $site_title = sanitize_input($_POST['site_title']);
    $site_description = sanitize_input($_POST['site_description']);
    $posts_per_page = (int)sanitize_input($_POST['posts_per_page']);
    $comments_moderation = sanitize_input($_POST['comments_moderation']);

    // Basic validation
    $errors = [];
    if (empty($site_title)) {
        $errors[] = "Site Title cannot be empty.";
    }
    if ($posts_per_page <= 0) {
        $errors[] = "Posts per page must be a positive number.";
    }
    if (!in_array($comments_moderation, ['auto', 'manual'])) {
        $errors[] = "Invalid comments moderation setting.";
    }

    if (empty($errors)) {
        $success = true;
        $success = $success && update_setting($conn, 'site_title', $site_title);
        $success = $success && update_setting($conn, 'site_description', $site_description);
        $success = $success && update_setting($conn, 'posts_per_page', (string)$posts_per_page); // Store as string
        $success = $success && update_setting($conn, 'comments_moderation', $comments_moderation);

        if ($success) {
            $_SESSION['message'] = "Website settings updated successfully!";
            $_SESSION['message_type'] = "success";
        } else {
            $_SESSION['message'] = "Error updating website settings. Please try again.";
            $_SESSION['message_type'] = "error";
        }
    } else {
        $_SESSION['message'] = "Please fix the following errors: <br>" . implode("<br>", $errors);
        $_SESSION['message_type'] = "error";
    }
    header("Location: manage_website_settings.php");
    exit();
}

// Fetch current settings
$current_site_title = get_setting($conn, 'site_title');
$current_site_description = get_setting($conn, 'site_description');
$current_posts_per_page = (int)get_setting($conn, 'posts_per_page');
$current_comments_moderation = get_setting($conn, 'comments_moderation');

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
        <h1><i class="fas fa-cogs"></i> Manage Website Settings</h1>
        <p class="text-muted">Adjust global settings for your blog.</p>
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
            <h3>General Settings</h3>
        </div>
        <div class="card-body">
            <form action="manage_website_settings.php" method="POST">
                <div class="mb-3">
                    <label for="site_title" class="form-label">Site Title</label>
                    <input type="text" name="site_title" id="site_title" class="form-control" 
                           value="<?php echo $current_site_title; ?>" required>
                </div>
                <div class="mb-3">
                    <label for="site_description" class="form-label">Site Description</label>
                    <textarea name="site_description" id="site_description" class="form-control" rows="3"><?php echo $current_site_description; ?></textarea>
                </div>
                <div class="mb-3">
                    <label for="posts_per_page" class="form-label">Posts Per Page (Front-end listings)</label>
                    <input type="number" name="posts_per_page" id="posts_per_page" class="form-control" 
                           value="<?php echo $current_posts_per_page; ?>" min="1" required>
                </div>
                <div class="mb-3">
                    <label for="comments_moderation" class="form-label">Comments Moderation</label>
                    <select name="comments_moderation" id="comments_moderation" class="form-select">
                        <option value="auto" <?php if ($current_comments_moderation == 'auto') echo 'selected'; ?>>Automatically Approve</option>
                        <option value="manual" <?php if ($current_comments_moderation == 'manual') echo 'selected'; ?>>Manual Approval Required</option>
                    </select>
                </div>
                <button type="submit" name="update_settings" class="btn btn-primary">Save Settings</button>
            </form>
        </div>
    </div>
</div>

<style>
    /* Basic form and admin-related styles (can be shared with other admin pages) */
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
    .form-label {
        font-weight: 600;
        margin-bottom: 0.5rem;
        display: block;
        color: var(--heading-color);
    }
    .form-control, .form-select {
        width: 100%;
        padding: 12px 15px;
        margin-top: 0.25rem;
        margin-bottom: 1rem;
        background: var(--background-white);
        border: 1px solid var(--border-color);
        border-radius: 8px;
        box-sizing: border-box;
        font-size: 1rem;
        line-height: 1.5;
        transition: border-color 0.15s ease-in-out, box-shadow 0.15s ease-in-out;
        color: var(--text-color);
    }
    .form-control:focus, .form-select:focus {
        border-color: var(--primary-color);
        box-shadow: 0 0 0 0.2rem rgba(52, 152, 219, 0.25);
        outline: none;
    }
    .mb-3 {
        margin-bottom: 1rem;
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
    .btn-primary {
        background-color: var(--primary-color);
        color: #fff;
        border: 1px solid var(--primary-color);
        padding: 10px 20px;
        border-radius: 8px;
        cursor: pointer;
        font-size: 1rem;
        font-weight: 500;
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
        transition: background-color 0.2s ease, border-color 0.2s ease, box-shadow 0.2s ease;
    }
    .btn-primary:hover {
        background-color: var(--primary-dark);
        border-color: var(--primary-dark);
        box-shadow: var(--shadow-hover);
    }

    /* Responsive */
    @media (max-width: 768px) {
        .admin-header {
            padding: 1.5rem;
        }
        .admin-header h1 {
            font-size: 1.8rem;
        }
        .card-body {
            padding: 1.5rem;
        }
    }
</style>

<?php ob_end_flush(); ?>
<?php include 'includes/footer.php'; ?>
