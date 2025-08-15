<?php
// Start output buffering to prevent headers already sent error
ob_start();

$page_title = "Edit Post";
require_once 'includes/functions.php'; // Ensure functions.php is included for upload_image(), etc.

// Include database connection if not already included
require_once __DIR__ . '/includes/db_connection.php'; // Make sure this file sets up $conn

// Require user to be logged in
require_login(); // Assuming this function redirects if not logged in

// Get post ID from URL
$post_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// Check if user is admin or the owner of the post
if (!is_admin()) {
    // Not admin, check ownership after fetching post
    if ($post_id > 0) {
        $owner_query = "SELECT user_id FROM posts WHERE id = ?";
        $owner_stmt = $conn->prepare($owner_query);
        $owner_stmt->bind_param("i", $post_id);
        $owner_stmt->execute();
        $owner_stmt->bind_result($owner_id);
        $owner_stmt->fetch();
        $owner_stmt->close();

        if ($owner_id != $_SESSION['user_id']) {
            $_SESSION['message'] = "You do not have permission to edit this post.";
            $_SESSION['message_type'] = "error";
            header("Location: manage_posts.php");
            exit();
        }
    } else {
        $_SESSION['message'] = "Invalid post ID.";
        $_SESSION['message_type'] = "error";
        header("Location: manage_posts.php");
        exit();
    }
}

if ($post_id <= 0) {
    $_SESSION['message'] = "Invalid post ID.";
    $_SESSION['message_type'] = "error";
    header("Location: manage_posts.php");
    exit();
}

// Fetch existing post data, including dislike_button_status
$post_query = "SELECT p.*, u.username
               FROM posts p
               LEFT JOIN users u ON p.user_id = u.id
               WHERE p.id = ?";
$post_stmt = $conn->prepare($post_query);
$post_stmt->bind_param("i", $post_id);
$post_stmt->execute();
$post_result = $post_stmt->get_result();

if ($post_result->num_rows == 0) {
    $_SESSION['message'] = "Post not found.";
    $_SESSION['message_type'] = "error";
    header("Location: manage_posts.php");
    exit();
}

$post_data = $post_result->fetch_assoc();
$post_stmt->close();

// Now include header.php after all redirects and header() calls
include 'includes/header.php'; // Assuming header.php handles session_start() and database connection

// Fetch all categories for the dropdown
$categories_query = "SELECT id, name FROM categories ORDER BY name ASC";
$categories_result = $conn->query($categories_query);
$categories = [];
if ($categories_result) {
    while ($row = $categories_result->fetch_assoc()) {
        $categories[] = $row;
    }
}

// Fetch existing tags for this post
$current_tags = get_post_tags($conn, $post_id); // Assuming get_post_tags function exists
$current_tag_names = array_column($current_tags, 'name'); // Get just the tag names

// Handle form submission for updating the post
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_post'])) {
    $title = sanitize_input($_POST['title']);
    $content = $_POST['content']; // HTML content, handle carefully
    $status = sanitize_input($_POST['status']);
    $category_id = isset($_POST['category_id']) && $_POST['category_id'] !== '' ? (int)$_POST['category_id'] : NULL;
    $is_featured = isset($_POST['is_featured']) ? 1 : 0; // This will only be set if the checkbox is visible and checked
    $new_tags_input = sanitize_input($_POST['tags']); // Comma-separated string of tags
    $dislike_button_pref = sanitize_input($_POST['dislike_button_pref'] ?? 'disabled'); // New: dislike button preference

    // Initialize image_path to current path
    $image_path = $post_data['image_path'];
    $image_option = $_POST['image_option'] ?? 'upload'; // Default to 'upload'

    // Basic validation
    if (empty($title) || empty($content)) {
        $_SESSION['message'] = "Title and content cannot be empty.";
        $_SESSION['message_type'] = "error";
    } else {
        // Handle image input based on user's choice
        if ($image_option === 'url') {
            $image_url = filter_var($_POST['featured_image_url'] ?? '', FILTER_SANITIZE_URL);
            if (!empty($image_url) && !filter_var($image_url, FILTER_VALIDATE_URL)) {
                $_SESSION['message'] = "Please enter a valid image URL.";
                $_SESSION['message_type'] = "error";
            } else {
                $image_path = empty($image_url) ? NULL : $image_url; // Set to NULL if URL is empty
            }
        } else { // 'upload' option
            if (isset($_FILES['featured_image']) && $_FILES['featured_image']['error'] == UPLOAD_ERR_OK) {
                $upload_result = upload_image($_FILES['featured_image']);
                if ($upload_result['success']) {
                    $image_path = $upload_result['filepath'];
                } else {
                    $_SESSION['message'] = $upload_result['message'];
                    $_SESSION['message_type'] = "error";
                }
            } elseif (isset($_POST['remove_image']) && $_POST['remove_image'] == '1') {
                // Remove existing image if checked AND no new file uploaded
                if ($image_path && !filter_var($image_path, FILTER_VALIDATE_URL) && file_exists('../' . $image_path)) {
                    unlink('../' . $image_path);
                }
                $image_path = NULL;
            } else {
                // No new upload, no remove checked. Keep existing image_path unless it was a URL.
                // If it was a URL and user switches to upload but doesn't upload a file, keep the URL
                // Or, if user wanted to clear the URL, they would make the URL input empty.
                // So, if no new file, and not explicitly removed, and it was a URL, keep it.
                if ($post_data['image_path'] && filter_var($post_data['image_path'], FILTER_VALIDATE_URL)) {
                    $image_path = $post_data['image_path'];
                }
            }
        }

        // Only proceed with database update if no image-related errors or other validation errors occurred
        if (!isset($_SESSION['message_type']) || $_SESSION['message_type'] !== "error") {
            // Determine dislike button status based on user preference and admin status
            $db_dislike_status = $post_data['dislike_button_status']; // Start with current status

            if (is_admin()) {
                // Admin can set status directly
                $db_dislike_status = $dislike_button_pref;
                // If admin, also update is_featured based on form submission
                $is_featured = isset($_POST['is_featured']) ? 1 : 0;
            } else {
                // Regular user: 'enabled' means 'pending', 'disabled' stays 'disabled'
                if ($dislike_button_pref === 'enabled') {
                    $db_dislike_status = 'pending';
                } else {
                    $db_dislike_status = 'disabled';
                }
                // For non-admins, ensure is_featured remains unchanged from its current value in the DB
                $is_featured = $post_data['is_featured'];
            }

            // Update post in database, including dislike_button_status
            $update_query = "UPDATE posts SET title = ?, content = ?, status = ?, category_id = ?, is_featured = ?, image_path = ?, dislike_button_status = ?, updated_at = NOW() WHERE id = ?";
            $update_stmt = $conn->prepare($update_query);
            $update_stmt->bind_param("sssiissi", $title, $content, $status, $category_id, $is_featured, $image_path, $db_dislike_status, $post_id);

            if ($update_stmt->execute()) {
                // Update tags
                // First, delete existing tags for this post
                $delete_tags_query = "DELETE FROM post_tags WHERE post_id = ?";
                $delete_tags_stmt = $conn->prepare($delete_tags_query);
                $delete_tags_stmt->bind_param("i", $post_id);
                $delete_tags_stmt->execute();
                $delete_tags_stmt->close();

                // Then, insert new tags
                if (!empty($new_tags_input)) {
                    $tag_names = array_map('trim', explode(',', $new_tags_input));
                    foreach ($tag_names as $tag_name) {
                        if (!empty($tag_name)) {
                            // Check if tag exists, if not, create it
                            $tag_id = null;
                            $find_tag_query = "SELECT id FROM tags WHERE name = ?";
                            $find_tag_stmt = $conn->prepare($find_tag_query);
                            $find_tag_stmt->bind_param("s", $tag_name);
                            $find_tag_stmt->execute();
                            $find_tag_result = $find_tag_stmt->get_result();
                            if ($find_tag_row = $find_tag_result->fetch_assoc()) {
                                $tag_id = $find_tag_row['id'];
                            } else {
                                $insert_tag_query = "INSERT INTO tags (name) VALUES (?)";
                                $insert_tag_stmt = $conn->prepare($insert_tag_query);
                                $insert_tag_stmt->bind_param("s", $tag_name);
                                $insert_tag_stmt->execute();
                                $tag_id = $conn->insert_id;
                                $insert_tag_stmt->close();
                            }
                            $find_tag_stmt->close();

                            // Link tag to post
                            if ($tag_id) {
                                $link_tag_query = "INSERT INTO post_tags (post_id, tag_id) VALUES (?, ?)";
                                $link_tag_stmt = $conn->prepare($link_tag_query);
                                $link_tag_stmt->bind_param("ii", $post_id, $tag_id);
                                $link_tag_stmt->execute();
                                $link_tag_stmt->close();
                            }
                        }
                    }
                }

                $_SESSION['message'] = "Post updated successfully!";
                $_SESSION['message_type'] = "success";
                header("Location: manage_posts.php");
                exit();
            } else {
                $_SESSION['message'] = "Error updating post: " . htmlspecialchars($conn->error);
                $_SESSION['message_type'] = "error";
            }
            $update_stmt->close();
        }
    }
}
?>
<?php if (is_admin()): ?>
<!-- Floating Back to Dashboard Button -->
<a href="manage_posts.php" class="floating-btn" title="Back to Dashboard">
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
<?php endif; ?>

<div class="container">
    <div class="admin-header mb-4">
        <h1><i class="fas fa-edit"></i> Edit Post: <?php echo htmlspecialchars(truncate_text($post_data['title'], 40)); ?></h1>
        <p class="text-muted">Modify the details of this blog post.</p>
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
            <h3>Post Details</h3>
        </div>
        <div class="card-body">
            <form action="edit_post.php?id=<?php echo $post_id; ?>" method="POST" enctype="multipart/form-data">
                <input type="hidden" name="post_id" value="<?php echo htmlspecialchars($post_id); ?>">
                <input type="hidden" name="update_post" value="1"> <!-- Hidden field to confirm form submission -->
                
                <div class="form-group">
                    <label for="title" class="form-label">Post Title</label>
                    <input type="text" id="title" name="title" class="form-input" value="<?php echo htmlspecialchars($post_data['title']); ?>" required>
                </div>

                <div class="form-group">
                    <label for="content" class="form-label">Content</label>
                    <textarea id="content" name="content" rows="15"><?php echo htmlspecialchars($post_data['content']); ?></textarea>
                </div>
                <div class="form-group" style="text-align:right;">
                    <button type="button" class="btn btn-secondary" id="preview-btn" style="margin-bottom:1rem;">
                        <i class="fas fa-eye"></i> Preview
                    </button>
                </div>
                <!-- Preview Modal -->
                <div id="preview-modal" style="display:none; position:fixed; top:0; left:0; width:100vw; height:100vh; background:rgba(0,0,0,0.6); align-items:center; justify-content:center; z-index:2000;">
                    <div style="background:#fff; border-radius:12px; max-width:700px; width:90%; padding:2rem; position:relative; box-shadow:0 8px 32px rgba(0,0,0,0.25);">
                        <button type="button" class="modal-close" style="position:absolute; top:1rem; right:1rem; background:none; border:none; font-size:2rem; color:#888; cursor:pointer;">&times;</button>
                        <div id="preview-content"></div>
                    </div>
                </div>
                <script>
// Rich text editor initialization (using TinyMCE CDN)
document.addEventListener('DOMContentLoaded', function() {
    // Load TinyMCE
    const script = document.createElement('script');
  //  script.src = /tinymce/6/tinymce.min.js';
    script.referrerPolicy = 'origin';
    script.onload = function() {
        tinymce.init({
            selector: '#content',
            height: 400,
            menubar: false,
            plugins: [
                'advlist', 'autolink', 'lists', 'link', 'image', 'charmap', 'preview',
                'anchor', 'searchreplace', 'visualblocks', 'code', 'fullscreen',
                'insertdatetime', 'media', 'table', 'help', 'wordcount', 'media',
                'emoticons', 'nonbreaking', 'hr', 'pagebreak', 'visualchars' // Added more commonly used plugins
            ],
            // Updated toolbar to include buttons for the new plugins and better organization
            toolbar: 'undo redo | blocks | ' +
                     'bold italic forecolor | alignleft aligncenter alignright alignjustify | ' +
                     'bullist numlist outdent indent | removeformat | help | ' +
                     'link image media | table | insertdatetime | ' +
                     'emoticons charmap hr pagebreak | fullscreen code preview | visualblocks visualchars wordcount',
            content_style: 'body { font-family: -apple-system, BlinkMacSystemFont, San Francisco, Segoe UI, Roboto, Helvetica Neue, sans-serif; font-size: 14px; }'
        });
    };
    document.head.appendChild(script);

    // Preview functionality
    const previewBtn = document.getElementById('preview-btn');
    const previewModal = document.getElementById('preview-modal');
    const previewContent = document.getElementById('preview-content');
    const modalClose = document.querySelector('.modal-close');

    previewBtn.addEventListener('click', function() {
        const title = document.getElementById('title').value;
        let content = '';

        if (typeof tinymce !== 'undefined' && tinymce.get('content')) {
            content = tinymce.get('content').getContent();
        } else {
            content = document.getElementById('content').value;
        }

        previewContent.innerHTML = `
            <h1>${title || 'Untitled Post'}</h1>
            <div>${content || 'No content yet...'}</div>
        `;

        previewModal.style.display = 'flex';
    });

    modalClose.addEventListener('click', function() {
        previewModal.style.display = 'none';
    });

    previewModal.addEventListener('click', function(e) {
        if (e.target === previewModal) {
            previewModal.style.display = 'none';
        }
    });
});

                </script>

                <div class="form-group">
                    <label for="category_id" class="form-label">Category</label>
                    <select id="category_id" name="category_id" class="form-input">
                        <option value="">-- Select Category --</option>
                        <?php foreach ($categories as $category): ?>
                            <option value="<?php echo htmlspecialchars($category['id']); ?>"
                                <?php echo ($post_data['category_id'] == $category['id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($category['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label for="status" class="form-label">Status</label>
                    <select id="status" name="status" class="form-input">
                        <option value="draft" <?php echo ($post_data['status'] == 'draft') ? 'selected' : ''; ?>>Draft</option>
                        <option value="published" <?php echo ($post_data['status'] == 'published') ? 'selected' : ''; ?>>Published</option>
                    </select>
                </div>

                <?php if (is_admin()): // Only show feature checkbox to admins ?>
                <div class="form-group">
                    <div class="checkbox-group">
                        <input type="checkbox" id="is_featured" name="is_featured" value="1"
                               <?php echo ($post_data['is_featured'] == 1) ? 'checked' : ''; ?>>
                        <label for="is_featured">Feature this post</label>
                    </div>
                </div>
                <?php endif; ?>

                <div class="form-group">
                    <label for="tags" class="form-label">Tags (comma-separated)</label>
                    <input type="text" id="tags" name="tags" class="form-input" value="<?php echo htmlspecialchars(implode(', ', $current_tag_names)); ?>">
                    <small class="text-muted">Separate tags with commas (e.g., tech, web-dev, coding)</small>
                </div>

                <div class="form-group">
                    <label class="form-label">Featured Image (Optional)</label>
                    <div style="display: flex; gap: 1rem; align-items: center; margin-bottom: 1rem;">
                        <label>
                            <input type="radio" name="image_option" value="upload" id="image_option_upload_edit"
                                <?php echo (!filter_var($post_data['image_path'], FILTER_VALIDATE_URL) && !empty($post_data['image_path'])) ? 'checked' : ''; ?>
                                <?php echo (empty($post_data['image_path']) || (!filter_var($post_data['image_path'], FILTER_VALIDATE_URL) && empty($post_data['image_path']))) ? 'checked' : ''; ?>>
                            Upload Image
                        </label>
                        <label>
                            <input type="radio" name="image_option" value="url" id="image_option_url_edit"
                                <?php echo (filter_var($post_data['image_path'], FILTER_VALIDATE_URL)) ? 'checked' : ''; ?>>
                            Use Image URL
                        </label>
                    </div>

                    <div id="upload_section_edit" style="<?php echo (filter_var($post_data['image_path'], FILTER_VALIDATE_URL)) ? 'display:none;' : ''; ?>">
                        <?php if (!empty($post_data['image_path']) && !filter_var($post_data['image_path'], FILTER_VALIDATE_URL)): ?>
                            <div class="mb-3">
                                <p class="text-muted">Current Uploaded Image:</p>
                                <img src="../<?php echo htmlspecialchars($post_data['image_path']); ?>" alt="Current Featured Image" style="max-width: 200px; height: auto; border-radius: 8px;" loading="lazy">
                                <div class="checkbox-group mt-2">
                                    <input type="checkbox" id="remove_image_upload" name="remove_image" value="1">
                                    <label for="remove_image_upload">Remove current uploaded image</label>
                                </div>
                            </div>
                        <?php endif; ?>
                        <input type="file" id="featured_image_upload" name="featured_image" class="form-input" accept="image/*">
                        <small class="text-muted">Upload a new image (optional). Max size: 5MB. Allowed types: JPG, PNG, GIF.</small>
                    </div>

                    <div id="url_section_edit" style="<?php echo (!filter_var($post_data['image_path'], FILTER_VALIDATE_URL) && !empty($post_data['image_path'])) ? 'display:none;' : ''; ?> <?php echo (empty($post_data['image_path']) && !filter_var($post_data['image_path'], FILTER_VALIDATE_URL)) ? 'display:none;' : ''; ?>">
                        <?php if (!empty($post_data['image_path']) && filter_var($post_data['image_path'], FILTER_VALIDATE_URL)): ?>
                            <div class="mb-3">
                                <p class="text-muted">Current Image URL:</p>
                                <img src="<?php echo htmlspecialchars($post_data['image_path']); ?>" alt="Current Featured Image" style="max-width: 200px; height: auto; border-radius: 8px;" onerror="this.onerror=null;this.src='https://placehold.co/200x150/cccccc/333333?text=Invalid+URL';" loading="lazy">
                                <div class="checkbox-group mt-2">
                                    <input type="checkbox" id="remove_image_url" name="remove_image" value="1">
                                    <label for="remove_image_url">Remove current image URL</label>
                                </div>
                            </div>
                        <?php endif; ?>
                        <input type="url" id="featured_image_url_edit" name="featured_image_url" class="form-input"
                            placeholder="https://example.com/image.jpg"
                            value="<?php echo (filter_var($post_data['image_path'], FILTER_VALIDATE_URL)) ? htmlspecialchars($post_data['image_path']) : ''; ?>">
                        <small class="text-muted">Paste a direct image URL (JPEG, PNG, GIF, max 5MB)</small>
                    </div>
                    <script>
                        document.addEventListener('DOMContentLoaded', function() {
                            function toggleImageOptionEdit() {
                                var uploadSection = document.getElementById('upload_section_edit');
                                var urlSection = document.getElementById('url_section_edit');
                                var uploadRadio = document.getElementById('image_option_upload_edit');
                                if (uploadRadio.checked) {
                                    uploadSection.style.display = '';
                                    urlSection.style.display = 'none';
                                } else {
                                    uploadSection.style.display = 'none';
                                    urlSection.style.display = '';
                                }
                            }
                            document.getElementById('image_option_upload_edit').addEventListener('change', toggleImageOptionEdit);
                            document.getElementById('image_option_url_edit').addEventListener('change', toggleImageOptionEdit);
                            // Initial state on load
                            toggleImageOptionEdit();
                        });
                    </script>
                </div>

                <!-- New Field: Dislike Button Option -->
                <div class="form-group">
                    <label class="form-label">Dislike Button</label>
                    <div class="form-radio-group">
                        <div class="form-radio">
                            <input type="radio" id="dislike_enable_edit" name="dislike_button_pref" value="enabled"
                                <?php echo ($post_data['dislike_button_status'] === 'enabled') ? 'checked' : ''; ?>>
                            <label for="dislike_enable_edit">Enabled</label>
                        </div>
                        <div class="form-radio">
                            <input type="radio" id="dislike_disabled_edit" name="dislike_button_pref" value="disabled"
                                <?php echo ($post_data['dislike_button_status'] === 'disabled') ? 'checked' : ''; ?>>
                            <label for="dislike_disabled_edit">Disabled</label>
                        </div>
                        <div class="form-radio">
                            <input type="radio" id="dislike_pending_edit" name="dislike_button_pref" value="pending"
                                <?php echo ($post_data['dislike_button_status'] === 'pending') ? 'checked' : ''; ?>>
                            <label for="dislike_pending_edit">Pending Approval (User Request)</label>
                        </div>
                    </div>
                    <?php if (!is_admin()): ?>
                        <small class="text-muted">Your choice to enable the dislike button requires administrator approval. Current status: <strong><?php echo htmlspecialchars($post_data['dislike_button_status']); ?></strong></small>
                    <?php else: ?>
                        <small class="text-muted">As an admin, you can directly control the dislike button visibility. Current status: <strong><?php echo htmlspecialchars($post_data['dislike_button_status']); ?></strong></small>
                    <?php endif; ?>
                </div>

                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-save"></i> Update Post
                </button>
                <a href="manage_posts.php" class="btn btn-secondary">Cancel</a>
            </form>
        </div>
    </div>
</div>

<style>

    /* Utility classes */
    .mb-3 { margin-bottom: 1rem !important; }
    .mb-4 { margin-bottom: 2rem !important; }
    .mt-2 { margin-top: 0.5rem !important; }
    .text-center { text-align: center !important; }
    .text-muted { color: var(--secondary-color) !important; }
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

    /* General Admin Form and Table Styling */
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
    .form-group {
        margin-bottom: 1.5rem;
    }
    .form-label {
        display: block;
        margin-bottom: 0.5rem;
        font-weight: 500;
        color: var(--heading-color);
    }
    .form-input,
    .form-textarea {
        width: 100%;
        padding: 12px 15px;
        border: 1px solid var(--border-color);
        border-radius: 8px;
        box-sizing: border-box;
        font-size: 1rem;
        line-height: 1.5;
        transition: border-color 0.15s ease-in-out, box-shadow 0.15s ease-in-out;
        color: var(--text-color);
        background: var(--background-white);
    }
    .form-input:focus,
    .form-textarea:focus {
        border-color: var(--primary-color);
        outline: 0;
        box-shadow: 0 0 0 0.2rem rgba(52, 152, 219, 0.15);
    }
    .form-textarea {
        resize: vertical;
    }
    .checkbox-group {
        display: flex;
        align-items: center;
        margin-top: 0.5rem;
    }
    .checkbox-group input[type="checkbox"] {
        margin-right: 0.5rem;
        width: auto;
    }
    .checkbox-group label {
        margin-bottom: 0;
        font-weight: normal;
    }
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
    .btn-secondary {
        background-color: var(--secondary-color);
        color: #fff;
        border: 1px solid var(--secondary-color);
        margin-left: 10px;
    }
    .btn-secondary:hover {
        background-color: #5a6268;
        border-color: #545b62;
        box-shadow: 0 2px 8px rgba(108, 117, 125, 0.3);
    }
    .text-muted {
        font-size: 0.875em;
        color: var(--secondary-color);
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

    /* Responsive adjustments */
    @media (max-width: 768px) {
        .admin-header, .card-body {
            padding: 1.5rem;
        }
    }
        /* Custom Properties (CSS Variables) for Light Theme */

    /* Reset and base styles for all elements */
    /* Scope all styles to .edit-post-page to affect only edit_post.php */
    .edit-post-page *,
    .edit-post-page *::before,
    .edit-post-page *::after {
        box-sizing: border-box;
        margin: 0;
        padding: 0;
    }

    .edit-post-page html {
        font-size: 16px;
        scroll-behavior: smooth;
        background: var(--background-light);
        color: var(--text-color);
        min-height: 100%;
    }

    .edit-post-page body {
        font-family: 'Segoe UI', 'Roboto', 'Arial', sans-serif;
        background: var(--background-light);
        color: var(--text-color);
        min-height: 100vh;
        line-height: 1.6;
    }

    .edit-post-page h1,
    .edit-post-page h2,
    .edit-post-page h3,
    .edit-post-page h4,
    .edit-post-page h5,
    .edit-post-page h6 {
        color: var(--heading-color);
        font-weight: 700;
        margin-bottom: 1rem;
        line-height: 1.2;
    }

    .edit-post-page p {
        margin-bottom: 1rem;
    }

    .edit-post-page a {
        color: var(--primary-color);
        text-decoration: none;
        transition: color 0.2s;
    }
    .edit-post-page a:hover,
    .edit-post-page a:focus {
        color: var(--primary-dark);
        text-decoration: underline;
    }

    .edit-post-page ul,
    .edit-post-page ol {
        margin-left: 2rem;
        margin-bottom: 1rem;
    }

    .edit-post-page img {
        max-width: 100%;
        height: auto;
        display: block;
    }

    .edit-post-page button,
    .edit-post-page input,
    .edit-post-page select,
    .edit-post-page textarea {
        font-family: inherit;
        font-size: 1rem;
        color: var(--text-color);
        background: var(--background-white);
        border: 1px solid var(--border-color);
        border-radius: 8px;
        outline: none;
        transition: border-color 0.15s, box-shadow 0.15s;
    }

    .edit-post-page button {
        cursor: pointer;
        border: none;
        background: var(--primary-color);
        color: #fff;
        padding: 10px 20px;
        border-radius: 8px;
        font-weight: 500;
        transition: background 0.2s, box-shadow 0.2s;
    }
    .edit-post-page button:hover,
    .edit-post-page button:focus {
        background: var(--primary-dark);
        box-shadow: var(--shadow-hover);
    }

    .edit-post-page input[type="text"],
    .edit-post-page input[type="file"],
    .edit-post-page select,
    .edit-post-page textarea {
        width: 100%;
        padding: 12px 15px;
        margin-top: 0.25rem;
        margin-bottom: 0.5rem;
        background: var(--background-white);
        border: 1px solid var(--border-color);
        border-radius: 8px;
        box-sizing: border-box;
        font-size: 1rem;
        line-height: 1.5;
        transition: border-color 0.15s, box-shadow 0.15s;
    }

    .edit-post-page input[type="text"]:focus,
    .edit-post-page input[type="file"]:focus,
    .edit-post-page select:focus,
    .edit-post-page textarea:focus {
        border-color: var(--primary-color);
        box-shadow: 0 0 0 0.2rem rgba(52, 152, 219, 0.15);
    }

    .edit-post-page input[type="checkbox"] {
        width: 18px;
        height: 18px;
        accent-color: var(--primary-color);
        margin-right: 0.5rem;
    }

    .edit-post-page label {
        font-weight: 500;
        color: var(--heading-color);
        margin-bottom: 0.25rem;
        display: inline-block;
    }

    /* Container */
    .edit-post-page .container {
        max-width: 800px;
        margin: 2rem auto;
        padding: 2rem;
        background: var(--background-white);
        border-radius: 16px;
    }

</style>
<?php include 'includes/footer.php'; ?>
<?php
// End output buffering and flush output
ob_end_flush();
?>
