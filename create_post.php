<?php
$page_title = "Create Post";
include 'includes/header.php';

// Require login
require_login();

$error_message = '';
$success_message = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $title = sanitize_input($_POST['title']);
    $content = $_POST['content']; // Don't sanitize content as it may contain HTML from rich editor
    $category_id = !empty($_POST['category_id']) ? (int)$_POST['category_id'] : null;
    $tags = sanitize_input($_POST['tags']);
    $status = $_POST['status'];
    $image_path = null; // Initialize image_path to null
    // New: Dislike button preference
    $dislike_button_pref = sanitize_input($_POST['dislike_button_pref'] ?? 'disabled'); // Default to 'disabled'

    // Determine if user chose to upload a file or provide a URL
    $image_option = $_POST['image_option'] ?? 'upload'; // Default to 'upload' if not set

    // Validation
    if (empty($title) || empty($content)) {
        $error_message = "Title and content are required.";
    } elseif (!in_array($status, ['draft', 'published'])) {
        $error_message = "Invalid status selected.";
    } else {
        // --- Handle Image Input (Upload or URL) ---
        if ($image_option === 'url') {
            $image_url = filter_var($_POST['featured_image_url'] ?? '', FILTER_SANITIZE_URL);
            
            if (empty($image_url)) {
                $error_message = "Image URL cannot be empty if 'Use Image URL' is selected.";
            } elseif (!filter_var($image_url, FILTER_VALIDATE_URL)) {
                $error_message = "Please enter a valid image URL.";
            } else {
                // Store the validated URL directly
                $image_path = $image_url;
            }
        } else { // image_option is 'upload' or not specified (default)
            if (isset($_FILES['featured_image']) && $_FILES['featured_image']['error'] == 0) {
                // Call your existing upload_image function
                $upload_result = upload_image($_FILES['featured_image']);
                if ($upload_result['success']) {
                    $image_path = $upload_result['filepath'];
                } else {
                    $error_message = $upload_result['message']; // Error from upload_image function
                }
            } elseif (isset($_FILES['featured_image']) && $_FILES['featured_image']['error'] !== UPLOAD_ERR_NO_FILE) {
                 // Handle other file upload errors (e.g., file too large, partial upload)
                 $error_message = "File upload error occurred. Error code: " . $_FILES['featured_image']['error'];
            }
            // If $_FILES['featured_image']['error'] == UPLOAD_ERR_NO_FILE, it means no file was uploaded,
            // which is fine as the image is optional. $image_path remains null.
        }
        
        // Only proceed with database insert if no image-related errors or other validation errors occurred
        if (empty($error_message)) {
            // Determine dislike button status based on user preference
            // If user selected 'enable', it goes to 'pending' for admin approval
            // If user selected 'disable', it's 'disabled'
            $db_dislike_status = ($dislike_button_pref === 'enable') ? 'pending' : 'disabled';


            // Insert post
            // The 'image_path' column will store either the local file path or the external URL
            $insert_query = "INSERT INTO posts (user_id, title, content, image_path, category_id, status, dislike_button_status) VALUES (?, ?, ?, ?, ?, ?, ?)";
            $insert_stmt = $conn->prepare($insert_query);
            $insert_stmt->bind_param("issssss", $_SESSION['user_id'], $title, $content, $image_path, $category_id, $status, $db_dislike_status);
            
            if ($insert_stmt->execute()) {
                $post_id = $conn->insert_id;
                
                // Handle tags
                if (!empty($tags)) {
                    $tag_array = array_map('trim', explode(',', $tags));
                    foreach ($tag_array as $tag_name) {
                        if (!empty($tag_name)) {
                            // Check if tag exists
                            $tag_check = $conn->prepare("SELECT id FROM tags WHERE name = ?");
                            $tag_check->bind_param("s", $tag_name);
                            $tag_check->execute();
                            $tag_result = $tag_check->get_result();
                            
                            if ($tag_result->num_rows > 0) {
                                $tag_id = $tag_result->fetch_assoc()['id'];
                            } else {
                                // Create new tag
                                $tag_insert = $conn->prepare("INSERT INTO tags (name) VALUES (?)");
                                $tag_insert->bind_param("s", $tag_name);
                                $tag_insert->execute();
                                $tag_id = $conn->insert_id;
                            }
                            
                            // Link post to tag
                            $post_tag_insert = $conn->prepare("INSERT IGNORE INTO post_tags (post_id, tag_id) VALUES (?, ?)");
                            $post_tag_insert->bind_param("ii", $post_id, $tag_id);
                            $post_tag_insert->execute();
                        }
                    }
                }
                
                $success_message = "Post created successfully!";
                if ($status == 'published') {
                    $success_message .= " <a href='post.php?id=$post_id'>View your post</a>";
                }
            } else {
                $error_message = "Failed to create post. Please try again.";
            }
        }
    }
}

// Get categories for dropdown
$categories = get_all_categories($conn);
?>

<div class="container">
    <div class="form-container" style="max-width: 800px;">
        <h2 class="text-center mb-3">Create New Post</h2>
        
        <?php if ($error_message): ?>
            <div class="alert alert-error"><?php echo $error_message; ?></div>
        <?php endif; ?>
        
        <?php if ($success_message): ?>
            <div class="alert alert-success"><?php echo $success_message; ?></div>
        <?php endif; ?>
        
        <form method="POST" enctype="multipart/form-data" data-validate>
            <div class="form-group">
                <label for="title" class="form-label">Post Title</label>
                <input type="text" id="title" name="title" class="form-input" required 
                       value="<?php echo isset($_POST['title']) ? htmlspecialchars($_POST['title']) : ''; ?>">
            </div>
            
            <div class="form-group">
                <label for="content" class="form-label">Content</label>
                <textarea id="content" name="content" class="form-textarea" style="min-height: 300px;"><?php echo isset($_POST['content']) ? htmlspecialchars($_POST['content']) : ''; ?></textarea>
            </div>
            <div class="form-group">
                <label class="form-label">Featured Image (Optional)</label>
                <div style="display: flex; gap: 1rem; align-items: center;">
                    <label>
                        <input type="radio" name="image_option" value="upload" id="image_option_upload"
                            <?php echo (!isset($_POST['image_option']) || $_POST['image_option'] == 'upload') ? 'checked' : ''; ?>>
                        Upload Image
                    </label>
                    <label>
                        <input type="radio" name="image_option" value="url" id="image_option_url"
                            <?php echo (isset($_POST['image_option']) && $_POST['image_option'] == 'url') ? 'checked' : ''; ?>>
                        Use Image URL
                    </label>
                </div>
                <div id="upload_section" style="<?php echo (isset($_POST['image_option']) && $_POST['image_option'] == 'url') ? 'display:none;' : ''; ?>">
                    <input type="file" id="featured_image" name="featured_image" class="form-input" accept="image/*">
                    <small class="text-muted">Maximum file size: 5MB. Supported formats: JPEG, PNG, GIF</small>
                </div>
                <div id="url_section" style="<?php echo (!isset($_POST['image_option']) || $_POST['image_option'] == 'upload') ? 'display:none;' : ''; ?>">
                    <input type="url" id="featured_image_url" name="featured_image_url" class="form-input"
                        placeholder="https://example.com/image.jpg"
                        value="<?php echo isset($_POST['featured_image_url']) ? htmlspecialchars($_POST['featured_image_url']) : ''; ?>">
                    <small class="text-muted">Paste a direct image URL (JPEG, PNG, GIF, max 5MB)</small>
                </div>
            </div>
            <script>
            document.addEventListener('DOMContentLoaded', function() {
                function toggleImageOption() {
                    var uploadSection = document.getElementById('upload_section');
                    var urlSection = document.getElementById('url_section');
                    var uploadRadio = document.getElementById('image_option_upload');
                    if (uploadRadio.checked) {
                        uploadSection.style.display = '';
                        urlSection.style.display = 'none';
                    } else {
                        uploadSection.style.display = 'none';
                        urlSection.style.display = '';
                    }
                }
                document.getElementById('image_option_upload').addEventListener('change', toggleImageOption);
                document.getElementById('image_option_url').addEventListener('change', toggleImageOption);
            });
            </script>

            <!-- New Field: Dislike Button Option -->
            <div class="form-group">
                <label class="form-label">Dislike Button</label>
                <div class="form-radio-group">
                    <div class="form-radio">
                        <input type="radio" id="dislike_enable" name="dislike_button_pref" value="enable" 
                               <?php echo (isset($_POST['dislike_button_pref']) && $_POST['dislike_button_pref'] == 'enable') ? 'checked' : ''; ?>>
                        <label for="dislike_enable">Enable (requires admin approval)</label>
                    </div>
                    <div class="form-radio">
                        <input type="radio" id="dislike_disable" name="dislike_button_pref" value="disabled" 
                               <?php echo (!isset($_POST['dislike_button_pref']) || $_POST['dislike_button_pref'] == 'disabled') ? 'checked' : ''; ?>>
                        <label for="dislike_disable">Disable</label>
                    </div>
                </div>
                <small class="text-muted">Choose whether to allow a dislike button on this post. If enabled, it will be visible after administrator approval.</small>
            </div>
            
            <div class="form-group">
                <label for="category_id" class="form-label">Category</label>
                <select id="category_id" name="category_id" class="form-select">
                    <option value="">Select a category (optional)</option>
                    <?php foreach ($categories as $category): ?>
                        <option value="<?php echo $category['id']; ?>" 
                                <?php echo (isset($_POST['category_id']) && $_POST['category_id'] == $category['id']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($category['name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="form-group">
                <label for="tags" class="form-label">Tags</label>
                <input type="text" id="tags" name="tags" class="form-input tag-input" 
                       value="<?php echo isset($_POST['tags']) ? htmlspecialchars($_POST['tags']) : ''; ?>"
                       placeholder="Enter tags separated by commas">
                <small class="text-muted">Separate multiple tags with commas</small>
            </div>
            
            <div class="form-group">
                <label class="form-label">Status</label>
                <div class="form-radio-group">
                    <div class="form-radio">
                        <input type="radio" id="draft" name="status" value="draft" 
                               <?php echo (!isset($_POST['status']) || $_POST['status'] == 'draft') ? 'checked' : ''; ?>>
                        <label for="draft">Save as Draft</label>
                    </div>
                    <div class="form-radio">
                        <input type="radio" id="published" name="status" value="published" 
                               <?php echo (isset($_POST['status']) && $_POST['status'] == 'published') ? 'checked' : ''; ?>>
                        <label for="published">Publish Now</label>
                    </div>
                </div>
            </div>
            
            <div class="form-group">
                <button type="submit" class="btn btn-primary">Create Post</button>
                <a href="dashboard.php" class="btn btn-secondary">Cancel</a>
                <button type="button" id="preview-btn" class="btn btn-outline">Preview</button>
            </div>
        </form>
    </div>
</div>

<!-- Preview Modal -->
<div id="preview-modal" class="modal" style="display: none;">
    <div class="modal-content">
        <div class="modal-header">
            <h3>Post Preview</h3>
            <button type="button" class="modal-close">&times;</button>
        </div>
        <div class="modal-body">
            <div id="preview-content"></div>
        </div>
    </div>
</div>

<style>
.modal {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0,0,0,0.5);
    z-index: 10000;
    display: flex;
    align-items: center;
    justify-content: center;
}

.modal-content {
    background: white;
    border-radius: 12px;
    max-width: 800px;
    max-height: 80vh;
    overflow-y: auto;
    margin: 2rem;
    width: 100%;
}

.modal-header {
    padding: 1.5rem;
    border-bottom: 1px solid #e9ecef;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.modal-close {
    background: none;
    border: none;
    font-size: 1.5rem;
    cursor: pointer;
    color: #6c757d;
}

.modal-close:hover {
    color: #333;
}

.modal-body {
    padding: 1.5rem;
}

#preview-content {
    line-height: 1.6;
}

#preview-content h1,
#preview-content h2,
#preview-content h3 {
    color: #2c3e50;
    margin: 1rem 0;
}

#preview-content p {
    margin-bottom: 1rem;
}
</style>

<script>
// Rich text editor initialization (using TinyMCE CDN)
document.addEventListener('DOMContentLoaded', function() {
    // Load TinyMCE
    const script = document.createElement('script');
  //  script.src = 'https:///tinymce/6/tinymce.min.js';
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

<?php include 'includes/footer.php'; ?>
