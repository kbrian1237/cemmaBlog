<?php
$page_title = "Admin Dashboard";
include 'includes/header.php';
require_once 'includes/functions.php'; // Ensure functions.php is included
require_once 'includes/db_connection.php'; // Ensure db connection is available

// Require admin access
require_admin();

// Helper function for safe query execution
function safe_query($conn, $query, $error_message) {
    $result = $conn->query($query);
    if ($result === false) {
        error_log("Database Error: " . $error_message . " Query: " . $query . " Error: " . $conn->error);
        // In a production environment, you might want a more user-friendly message
        // For debugging, we'll show the error directly.
        echo "<div class='alert alert-error'><strong>Database Error:</strong> " . htmlspecialchars($error_message) . " Please check your database tables and schema. MySQL Error: " . htmlspecialchars($conn->error) . "</div>";
        return null; // Return null on failure
    }
    return $result;
}

// Function to get a setting (moved here for immediate availability)
// Ideally, this should be in includes/functions.php or a dedicated settings file.
if (!function_exists('get_setting')) {
    function get_setting($conn, $key, $default = '') {
        $stmt = $conn->prepare("SELECT setting_value FROM settings WHERE setting_key = ?");
        if ($stmt) {
            $stmt->bind_param("s", $key);
            $stmt->execute();
            $result = $stmt->get_result();
            if ($row = $result->fetch_assoc()) {
                return htmlspecialchars($row['setting_value']);
            }
            $stmt->close();
        } else {
            error_log("Error preparing get_setting query: " . $conn->error);
        }
        return $default; // Default empty string if not found or error
    }
}

// Function to update a setting (moved here for immediate availability)
// Ideally, this should be in includes/functions.php or a dedicated settings file.
if (!function_exists('update_setting')) {
    function update_setting($conn, $key, $value) {
        $stmt = $conn->prepare("INSERT INTO settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = ?");
        if ($stmt) {
            $stmt->bind_param("sss", $key, $value, $value);
            $success = $stmt->execute();
            $stmt->close();
            if (!$success) {
                error_log("Error updating setting '{$key}': " . $conn->error);
            }
            return $success;
        } else {
            error_log("Error preparing update_setting query: " . $conn->error);
            return false;
        }
    }
}

/**
 * Sends text to the AI Offensive Word Detector API for analysis.
 * This function is included here for demonstration purposes.
 * In a production environment, it should ideally reside in 'includes/functions.php'
 * or a dedicated API handler file.
 *
 * @param string $text The text content to analyze.
 * @return array An associative array with 'is_offensive' (boolean) and 'score' (float/int),
 * or an error array if the API call fails.
 */
function analyze_content_for_offense($text) {
    // IMPORTANT: Replace with your actual API endpoint and key.
    // Ensure this endpoint uses HTTPS as recommended in the API Usage Guide.
    $api_endpoint = 'YOUR_OFFENSIVE_WORD_DETECTOR_API_ENDPOINT'; // e.g., 'https://api.example.com/offensive-detector/v1/analyze'
    $api_key = 'YOUR_API_KEY_HERE'; // Obtain this securely, e.g., from environment variables or a secure config.

    // Basic validation for demonstration. In production, ensure these are properly configured.
    if (empty($api_endpoint) || empty($api_key) || !filter_var($api_endpoint, FILTER_VALIDATE_URL)) {
        error_log("Offensive Word API: Endpoint or key not properly configured.");
        return ['error' => 'API not configured or invalid endpoint.'];
    }

    $payload = json_encode(['text' => $text]);

    $ch = curl_init($api_endpoint);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true); // Return the response as a string
    curl_setopt($ch, CURLOPT_POST, true);           // Set as POST request
    curl_setopt($ch, CURLOPT_POSTFIELDS, $payload); // Set the JSON payload
    curl_setopt($ch, CURLOPT_HTTPHEADER, [          // Set headers
        'Content-Type: application/json',
        'Authorization: Bearer ' . $api_key         // Assuming Bearer token authentication
    ]);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);          // Implement request timeouts (10 seconds)
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true); // Ensure HTTPS certificate verification

    $response = curl_exec($ch);
    $http_status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curl_error = curl_error($ch);
    curl_close($ch);

    // --- Error Handling as per API Usage Guide ---
    if ($curl_error) {
        error_log("Offensive Word API cURL Error: " . $curl_error);
        // Provide fallback behavior for network failures
        return ['error' => 'Network error during API call: ' . $curl_error];
    }

    // Check response status codes before processing results
    if ($http_status !== 200) {
        error_log("Offensive Word API returned HTTP status {$http_status}: " . $response);
        if ($http_status === 429) { // Rate Limiting
            // In a real application, you might implement exponential backoff here.
            return ['error' => 'API rate limit exceeded. Please try again later.'];
        }
        // Provide fallback behavior for API unavailability/errors
        return ['error' => 'API returned an error: HTTP ' . $http_status . ' - ' . substr($response, 0, 200)];
    }

    $result = json_decode($response, true);

    if (json_last_error() !== JSON_ERROR_NONE) {
        error_log("Offensive Word API: Failed to decode JSON response: " . json_last_error_msg() . " Response: " . $response);
        return ['error' => 'Invalid API response format.'];
    }

    // Assuming the API returns a structure like: {'is_offensive': true, 'score': 0.9}
    // Adjust this parsing based on the actual API response structure.
    if (isset($result['is_offensive']) && isset($result['score'])) {
        // Never log or store sensitive content being analyzed (only the outcome)
        return ['is_offensive' => (bool)$result['is_offensive'], 'score' => (float)$result['score']];
    } else {
        error_log("Offensive Word API: Unexpected response structure. Response: " . print_r($result, true));
        return ['error' => 'Unexpected API response structure.'];
    }
}

// Example of how 'auto_moderate_content' setting would trigger the API call.
// This block is for conceptual understanding within admin_dashboard.php.
// The actual implementation would be in your content submission/editing scripts (e.g., create_post.php, manage_comments.php).
/*
if (isset($conn)) { // Ensure database connection is available
    $auto_moderate_content_enabled = get_setting($conn, 'auto_moderate_content', '0');

    if ($auto_moderate_content_enabled === '1') {
        // This is where you would get the actual content from a post or comment submission
        // For demonstration, let's use a sample text:
        $sample_content_to_moderate = "This is a sample text that might contain offensive words.";

        $moderation_result = analyze_content_for_offense($sample_content_to_moderate);

        if (isset($moderation_result['error'])) {
            error_log("Content moderation failed: " . $moderation_result['error']);
            // Handle error: e.g., set content status to 'pending_review' or notify admin
            // $_SESSION['moderation_status'] = ['type' => 'error', 'message' => 'AI moderation failed. Manual review needed.'];
        } elseif ($moderation_result['is_offensive']) {
            error_log("Offensive content detected! Score: " . $moderation_result['score']);
            // Handle offensive content: e.g., set content status to 'pending' or 'rejected'
            // $_SESSION['moderation_status'] = ['type' => 'warning', 'message' => 'Offensive content detected. Set to pending review.'];
        } else {
            error_log("Content is clean. Score: " . $moderation_result['score']);
            // Content is clean: proceed with normal publishing
            // $_SESSION['moderation_status'] = ['type' => 'success', 'message' => 'Content passed AI moderation.'];
        }
    } else {
        error_log("AI Content Moderation is disabled.");
    }
}
*/


// Handle toggle updates (AJAX endpoint) - MUST be at the very top to prevent HTML output
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_setting'])) {
    $setting_name = sanitize_input($_POST['setting_name']);
    $setting_value = sanitize_input($_POST['setting_value']);
    
    // Set content type to JSON
    header('Content-Type: application/json');

    if (update_setting($conn, $setting_name, $setting_value)) {
        echo json_encode(['success' => true, 'message' => 'Setting updated.']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to update setting.']);
    }
    exit(); // Important: Exit immediately after sending JSON response
}


// Get statistics
$stats_query = "SELECT 
                    (SELECT COUNT(*) FROM posts) as total_posts,
                    (SELECT COUNT(*) FROM posts WHERE status = 'published') as published_posts,
                    (SELECT COUNT(*) FROM posts WHERE status = 'draft') as draft_posts,
                    (SELECT COUNT(*) FROM users) as total_users,
                    (SELECT COUNT(*) FROM users WHERE role = 'admin') as admin_users,
                    (SELECT COUNT(*) FROM comments) as total_comments,
                    (SELECT COUNT(*) FROM comments WHERE status = 'pending') as pending_comments,
                    (SELECT COUNT(*) FROM comments WHERE status = 'approved') as approved_comments,
                    (SELECT COUNT(*) FROM categories) as total_categories,
                    (SELECT COUNT(*) FROM tags) as total_tags,
                    (SELECT COUNT(*) FROM user_follows) as total_follows"; // Corrected table name to user_follows
$stats_result = safe_query($conn, $stats_query, "Failed to fetch general statistics.");
$stats = $stats_result ? $stats_result->fetch_assoc() : [];

// Get recent posts
$recent_posts_query = "SELECT p.*, u.username 
                       FROM posts p 
                       LEFT JOIN users u ON p.user_id = u.id 
                       ORDER BY p.published_at DESC 
                       LIMIT 5";
$recent_posts_result = safe_query($conn, $recent_posts_query, "Failed to fetch recent posts.");

// Get recent comments
$recent_comments_query = "SELECT c.*, p.title as post_title, u.username 
                          FROM comments c 
                          LEFT JOIN posts p ON c.post_id = p.id 
                          LEFT JOIN users u ON c.user_id = u.id 
                          ORDER BY c.created_at DESC 
                          LIMIT 5";
$recent_comments_result = safe_query($conn, $recent_comments_query, "Failed to fetch recent comments.");

// Get recent users
$recent_users_query = "SELECT * FROM users ORDER BY created_at DESC LIMIT 5";
$recent_users_result = safe_query($conn, $recent_users_query, "Failed to fetch recent users.");

// NEW: Get recent messages (from chatbot_admin_request and contact_form)
// Order by priority (higher first), then by created_at (newest first)
$recent_messages_query = "SELECT 
                            id, sender_name, sender_email, message_content, message_type, priority, status, created_at, subject
                          FROM messages 
                          WHERE message_type IN ('chatbot_admin_request', 'contact_form')
                          ORDER BY priority DESC, created_at DESC 
                          LIMIT 5";
$recent_messages_result = safe_query($conn, $recent_messages_query, "Failed to fetch recent messages.");

// NEW: Get total pending messages for badge
$pending_messages_query = "SELECT COUNT(*) AS total_pending FROM messages WHERE status = 'new' AND message_type IN ('chatbot_admin_request', 'contact_form')";
$pending_messages_result_obj = safe_query($conn, $pending_messages_query, "Failed to fetch pending messages count.");
$total_pending_messages = $pending_messages_result_obj ? $pending_messages_result_obj->fetch_assoc()['total_pending'] : 0;

// Get total likes
$likes_count_query = "SELECT COUNT(*) AS total_likes FROM likes";
$likes_count_result_obj = safe_query($conn, $likes_count_query, "Failed to fetch total likes count.");
$likes_count = $likes_count_result_obj ? $likes_count_result_obj->fetch_assoc()['total_likes'] : 0;

// Get total dislikes
$dislikes_count_query = "SELECT COUNT(*) AS total_dislikes FROM dislikes";
$dislikes_count_result_obj = safe_query($conn, $dislikes_count_query, "Failed to fetch total dislikes count.");
$dislikes_count = $dislikes_count_result_obj ? $dislikes_count_result_obj->fetch_assoc()['total_dislikes'] : 0;

// Get posts with dislike button status 'pending'
$pending_dislike_btn_query = "SELECT COUNT(*) AS pending_dislike_btn FROM posts WHERE dislike_button_status = 'pending'";
$pending_dislike_btn_result_obj = safe_query($conn, $pending_dislike_btn_query, "Failed to fetch pending dislike button count.");
$pending_dislike_btn = $pending_dislike_btn_result_obj ? $pending_dislike_btn_result_obj->fetch_assoc()['pending_dislike_btn'] : 0;

// Get website settings (for toggles)
// Ensure these settings exist in your 'settings' table or have sensible defaults.
$website_settings = [
    'ai_help_enabled' => get_setting($conn, 'ai_help_enabled', '0'), // '1' or '0'
    'auto_approve_comments' => get_setting($conn, 'auto_approve_comments', '0'),
    'auto_moderate_content' => get_setting($conn, 'auto_moderate_content', '0'),
    'enable_dislikes' => get_setting($conn, 'enable_dislikes', '1'),
    'public_registration' => get_setting($conn, 'public_registration', '1'),
];


// Fetch posts by category for the chart
$category_chart_query = "SELECT c.name AS category_name, COUNT(p.id) AS post_count
                         FROM categories c
                         LEFT JOIN posts p ON p.category_id = c.id AND p.status = 'published'
                         GROUP BY c.id, c.name
                         ORDER BY post_count DESC, c.name ASC";
$category_chart_result = safe_query($conn, $category_chart_query, "Failed to fetch posts by category for chart.");

$categoryLabels = [];
$categoryPostCounts = [];
if ($category_chart_result) {
    while ($row = $category_chart_result->fetch_assoc()) {
        $categoryLabels[] = $row['category_name'];
        $categoryPostCounts[] = (int)$row['post_count'];
    }
}

// Fetch user roles for chart
$user_roles_query = "SELECT role, COUNT(id) as user_count FROM users GROUP BY role";
$user_roles_result = safe_query($conn, $user_roles_query, "Failed to fetch user roles for chart.");
$userRoleLabels = [];
$userRoleCounts = [];
if ($user_roles_result) {
    while ($row = $user_roles_result->fetch_assoc()) {
        $userRoleLabels[] = ucfirst($row['role']);
        $userRoleCounts[] = (int)$row['user_count'];
    }
}

// Fetch total followers and total following for chart
$total_followers_query = "SELECT COUNT(DISTINCT follower_id) as count FROM user_follows"; // Corrected table name
$total_followers_result_obj = safe_query($conn, $total_followers_query, "Failed to fetch total followers count.");
$total_followers = $total_followers_result_obj ? $total_followers_result_obj->fetch_assoc()['count'] : 0;

$total_following_query = "SELECT COUNT(DISTINCT followed_id) as count FROM user_follows"; // Corrected table name
$total_following_result_obj = safe_query($conn, $total_following_query, "Failed to fetch total following count.");
$total_following = $total_following_result_obj ? $total_following_result_obj->fetch_assoc()['count'] : 0;

// Fetch total posts and total comments for Content Type Distribution Chart
$total_posts_count_query = "SELECT COUNT(*) as total FROM posts WHERE status = 'published'";
$total_posts_count_result_obj = safe_query($conn, $total_posts_count_query, "Failed to fetch total published posts count.");
$total_published_posts = $total_posts_count_result_obj ? $total_posts_count_result_obj->fetch_assoc()['total'] : 0;

$total_comments_count_query = "SELECT COUNT(*) as total FROM comments WHERE status = 'approved'";
$total_comments_count_result_obj = safe_query($conn, $total_comments_count_query, "Failed to fetch total approved comments count.");
$total_approved_comments = $total_comments_count_result_obj ? $total_comments_count_result_obj->fetch_assoc()['total'] : 0;

?>
<div class="container">
    <div class="admin-header mb-4">
        <h1><i class="fas fa-tachometer-alt"></i> Admin Dashboard</h1>
        <p class="text-muted">Welcome to the administration panel</p>
    </div>

    <?php include 'admin_search.php'; ?>

    <!-- Statistics Grid -->
    <div class="stats-grid mb-4">
        <div class="stat-card">
            <div class="stat-icon"><i class="fas fa-file-alt"></i></div>
            <div class="stat-info">
                <div class="stat-number"><?php echo $stats['total_posts'] ?? 0; ?></div>
                <div class="stat-label">Total Posts</div>
                <div class="stat-detail">
                    <?php echo $stats['published_posts'] ?? 0; ?> published,
                    <?php echo $stats['draft_posts'] ?? 0; ?> drafts
                </div>
            </div>
        </div>

        <div class="stat-card">
            <div class="stat-icon"><i class="fas fa-users"></i></div>
            <div class="stat-info">
                <div class="stat-number"><?php echo $stats['total_users'] ?? 0; ?></div>
                <div class="stat-label">Total Users</div>
                <div class="stat-detail">
                    <?php echo $stats['admin_users'] ?? 0; ?> admins
                </div>
            </div>
        </div>

        <div class="stat-card">
            <div class="stat-icon"><i class="fas fa-comments"></i></div>
            <div class="stat-info">
                <div class="stat-number"><?php echo $stats['total_comments'] ?? 0; ?></div>
                <div class="stat-label">Total Comments</div>
                <div class="stat-detail">
                    <?php echo $stats['pending_comments'] ?? 0; ?> pending approval
                </div>
            </div>
        </div>

        <div class="stat-card">
            <div class="stat-icon"><i class="fas fa-thumbs-up"></i></div>
            <div class="stat-info">
                <div class="stat-number"><?php echo $likes_count; ?></div>
                <div class="stat-label">Total Likes</div>
                <div class="stat-detail">
                    Across all posts
                </div>
            </div>
        </div>

        <div class="stat-card">
            <div class="stat-icon"><i class="fas fa-thumbs-down"></i></div>
            <div class="stat-info">
                <div class="stat-number"><?php echo $dislikes_count; ?></div>
                <div class="stat-label">Total Dislikes</div>
                <div class="stat-detail">
                    <?php echo $pending_dislike_btn; ?> posts waiting for dislike button approval
                </div>
            </div>
        </div>

        <div class="stat-card">
            <div class="stat-icon"><i class="fas fa-tags"></i></div>
            <div class="stat-info">
                <div class="stat-number"><?php echo $stats['total_categories'] ?? 0; ?></div>
                <div class="stat-label">Categories</div>
                <div class="stat-detail">
                    <?php echo $stats['total_tags'] ?? 0; ?> tags
                </div>
            </div>
        </div>
        
        <!-- NEW: Total Follows Stat Card -->
        <div class="stat-card">
            <div class="stat-icon"><i class="fas fa-user-friends"></i></div>
            <div class="stat-info">
                <div class="stat-number"><?php echo $stats['total_follows'] ?? 0; ?></div>
                <div class="stat-label">Total Follows</div>
                <div class="stat-detail">
                    Across all users
                </div>
            </div>
        </div>
    </div>

    <!-- Quick Actions -->
    <div class="quick-actions mb-4">
        <h3>Quick Actions</h3>
        <div class="action-buttons">
            <a href="../create_post.php" class="btn btn-primary">
                <i class="fas fa-plus"></i> Create New Post
            </a>
            <a href="ai_help.php" class="btn btn-info">
                <i class="fas fa-robot"></i> AI Assistance
            </a>
            <a href="manage_website_settings.php" class="btn btn-admin">
                <i class="fas fa-cogs"></i> Website Settings
            </a>
            <a href="manage_posts.php" class="btn btn-secondary">
                <i class="fas fa-file-alt"></i> Manage Posts
            </a>
            <a href="manage_users.php" class="btn btn-secondary">
                <i class="fas fa-users"></i> Manage Users
            </a>
            <a href="manage_comments.php" class="btn btn-secondary">
                <i class="fas fa-comments"></i> Moderate Comments
                <?php if (($stats['pending_comments'] ?? 0) > 0): ?>
                    <span class="badge"><?php echo $stats['pending_comments']; ?></span>
                <?php endif; ?>
            </a>
            <a href="manage_categories.php" class="btn btn-secondary">
                <i class="fas fa-folder"></i> Manage Categories
            </a>
            <a href="manage_likes_dislikes.php" class="btn btn-secondary">
                <i class="fas fa-thumbs-up"></i> Manage Likes
            </a>
            <a href="manage_messages.php" class="btn btn-secondary">
                <i class="fas fa-envelope"></i> Manage Messages
                <?php if ($total_pending_messages > 0): ?>
                    <span class="badge"><?php echo $total_pending_messages; ?></span>
                <?php endif; ?>
            </a>
            <a href="manage_follows.php" class="btn btn-secondary">
                <i class="fas fa-user-friends"></i> Manage Follows
            </a>
        </div>
    </div>

    <!-- NEW: Website Settings Toggles -->
    <div class="dashboard-card mb-4">
        <h3><i class="fas fa-sliders-h"></i> Website Settings Toggles</h3>
        <div class="settings-toggles-grid">
            <div class="toggle-item">
                <label for="ai_help_enabled">Enable AI Help:</label>
                <label class="switch">
                    <input type="checkbox" id="ai_help_enabled" data-setting-name="ai_help_enabled" <?php echo $website_settings['ai_help_enabled'] == '1' ? 'checked' : ''; ?>>
                    <span class="slider round"></span>
                </label>
            </div>
            <div class="toggle-item">
                <label for="auto_approve_comments">Auto Approve Comments:</label>
                <label class="switch">
                    <input type="checkbox" id="auto_approve_comments" data-setting-name="auto_approve_comments" <?php echo $website_settings['auto_approve_comments'] == '1' ? 'checked' : ''; ?>>
                    <span class="slider round"></span>
                </label>
            </div>
            <div class="toggle-item">
                <label for="auto_moderate_content">Auto Moderate Content (AI):</label>
                <label class="switch">
                    <input type="checkbox" id="auto_moderate_content" data-setting-name="auto_moderate_content" <?php echo $website_settings['auto_moderate_content'] == '1' ? 'checked' : ''; ?>>
                    <span class="slider round"></span>
                </label>
            </div>
            <div class="toggle-item">
                <label for="enable_dislikes">Enable Dislike Button:</label>
                <label class="switch">
                    <input type="checkbox" id="enable_dislikes" data-setting-name="enable_dislikes" <?php echo $website_settings['enable_dislikes'] == '1' ? 'checked' : ''; ?>>
                    <span class="slider round"></span>
                </label>
            </div>
            <div class="toggle-item">
                <label for="public_registration">Enable Public Registration:</label>
                <label class="switch">
                    <input type="checkbox" id="public_registration" data-setting-name="public_registration" <?php echo $website_settings['public_registration'] == '1' ? 'checked' : ''; ?>>
                    <span class="slider round"></span>
                </label>
            </div>
            <!-- Add more toggles as needed -->
        </div>
    </div>

    <!-- Charts Section -->
    <div class="dashboard-grid mb-4">
        <div class="dashboard-card">
            <h3><i class="fas fa-chart-pie"></i> Post Status</h3>
            <canvas id="postStatusChart" style="max-height: 250px;"></canvas>
        </div>
        <div class="dashboard-card">
            <h3><i class="fas fa-chart-bar"></i> User Roles</h3>
            <canvas id="userRoleChart" style="max-height: 250px;"></canvas>
        </div>
        <div class="dashboard-card">
            <h3><i class="fas fa-chart-line"></i> Comment Status</h3>
            <canvas id="commentStatusChart" style="max-height: 250px;"></canvas>
        </div>
        <div class="dashboard-card">
            <h3><i class="fas fa-chart-area"></i> Posts by Category</h3>
            <canvas id="postsByCategoryChart" style="max-height: 250px;"></canvas>
        </div>
        <!-- Likes & Dislikes Chart -->
        <div class="dashboard-card">
            <h3><i class="fas fa-thumbs-up"></i> Likes vs Dislikes</h3>
            <canvas id="likesDislikesChart" style="max-height: 250px;"></canvas>
        </div>
        <!-- Pending Dislike Button Chart -->
        <div class="dashboard-card">
            <h3><i class="fas fa-hourglass-half"></i> Dislike Button Approval</h3>
            <canvas id="pendingDislikeBtnChart" style="max-height: 250px;"></canvas>
        </div>
        <!-- NEW: Followers vs Following Chart -->
        <div class="dashboard-card">
            <h3><i class="fas fa-user-friends"></i> User Follow Statistics</h3>
            <canvas id="userFollowChart" style="max-height: 250px;"></canvas>
        </div>
        <!-- NEW: Content Type Distribution (Posts vs. Comments) Chart -->
        <div class="dashboard-card">
            <h3><i class="fas fa-chart-pie"></i> Content Type Distribution</h3>
            <canvas id="contentTypeChart" style="max-height: 250px;"></canvas>
        </div>
    </div>
    <!-- Dashboard Grid -->
    <div class="dashboard-grid">
        <!-- Recent Posts -->
        <div class="dashboard-card">
            <h3><i class="fas fa-file-alt"></i> Recent Posts</h3>
            <?php
            // Get latest posts
            $posts_per_page = 5;
            $offset = 0;
            $posts_query = "SELECT p.*, u.username, c.name as category_name 
                    FROM posts p 
                    LEFT JOIN users u ON p.user_id = u.id 
                    LEFT JOIN categories c ON p.category_id = c.id 
                    WHERE p.status = 'published' 
                    ORDER BY p.published_at DESC 
                    LIMIT $posts_per_page OFFSET $offset";
            $posts_result = safe_query($conn, $posts_query, "Failed to fetch recent posts for card.");
            ?>
            <?php if ($posts_result && $posts_result->num_rows > 0): ?>
            <div class="recent-items">
                <?php while ($post = $posts_result->fetch_assoc()): ?>
                <div class="recent-item">
                    <div class="item-info">
                    <h4>
                        <a href="post.php?id=<?php echo $post['id']; ?>">
                        <?php echo htmlspecialchars($post['title']); ?>
                        </a>
                    </h4>
                    <p class="item-meta">
                        By <?php echo htmlspecialchars($post['username']); ?> • 
                        <?php echo format_datetime($post['published_at']); ?> • 
                        <?php if (!empty($post['category_name'])): ?>
                        <span><?php echo htmlspecialchars($post['category_name']); ?></span> • 
                        <?php endif; ?>
                        <span class="status-<?php echo $post['status']; ?>"><?php echo ucfirst($post['status']); ?></span>
                    </p>
                    </div>
                    <div class="item-actions">
                    <a href="manage_posts.php?edit=<?php echo $post['id']; ?>" class="btn btn-sm btn-outline">Edit</a>
                    </div>
                </div>
                <?php endwhile; ?>
            </div>
            <div class="card-footer">
                <a href="manage_posts.php" class="btn btn-outline">View All Posts</a>
            </div>
            <?php else: ?>
            <p class="text-muted">No posts yet.</p>
            <?php endif; ?>
        </div>
        
        <!-- Recent Comments -->
        <div class="dashboard-card">
            <h3><i class="fas fa-comments"></i> Recent Comments</h3>
            <?php if ($recent_comments_result && $recent_comments_result->num_rows > 0): ?>
                <div class="recent-items">
                    <?php while ($comment = $recent_comments_result->fetch_assoc()): ?>
                        <div class="recent-item">
                            <div class="item-info">
                                <h4><?php echo truncate_text(htmlspecialchars($comment['content']), 60); ?></h4>
                                <p class="item-meta">
                                    By <?php echo htmlspecialchars($comment['username'] ?? $comment['author_name']); ?> • 
                                    On <a href="post.php?id=<?php echo $comment['post_id']; ?>"><?php echo htmlspecialchars($comment['post_title']); ?></a> • 
                                    <span class="status-<?php echo $comment['status']; ?>"><?php echo ucfirst($comment['status']); ?></span>
                                </p>
                            </div>
                            <div class="item-actions">
                                <a href="manage_comments.php?comment=<?php echo $comment['id']; ?>" class="btn btn-sm btn-outline">Review</a>
                            </div>
                        </div>
                    <?php endwhile; ?>
                </div>
                <div class="card-footer">
                    <a href="manage_comments.php" class="btn btn-outline">View All Comments</a>
                </div>
            <?php else: ?>
                <p class="text-muted">No comments yet.</p>
            <?php endif; ?>
        </div>
        
        <!-- Recent Users -->
        <div class="dashboard-card">
            <h3><i class="fas fa-users"></i> Recent Users</h3>
            <?php if ($recent_users_result && $recent_users_result->num_rows > 0): ?>
                <div class="recent-items">
                    <?php while ($user = $recent_users_result->fetch_assoc()): ?>
                        <div class="recent-item">
                            <div class="item-info">
                                <h4><?php echo htmlspecialchars($user['username']); ?></h4>
                                <p class="item-meta">
                                    <?php echo htmlspecialchars($user['email']); ?> • 
                                    <?php echo format_datetime($user['created_at']); ?> • 
                                    <span class="role-<?php echo $user['role']; ?>"><?php echo ucfirst($user['role']); ?></span>
                                </p>
                            </div>
                            <div class="item-actions">
                                <a href="manage_users.php?user=<?php echo $user['id']; ?>" class="btn btn-sm btn-outline">View</a>
                            </div>
                        </div>
                    <?php endwhile; ?>
                </div>
                <div class="card-footer">
                    <a href="manage_users.php" class="btn btn-outline">View All Users</a>
                </div>
            <?php else: ?>
                <p class="text-muted">No users yet.</p>
            <?php endif; ?>
        </div>

        <!-- Recent Images -->
        <div class="dashboard-card">
            <h3><i class="fas fa-image"></i> Recent Images</h3>
            <?php
            // Fetch recent images directly from the 'posts' table where image_path is not NULL
            $recent_images_query = "SELECT id, title, image_path, published_at 
                                    FROM posts 
                                    WHERE image_path IS NOT NULL AND image_path != ''
                                    ORDER BY published_at DESC 
                                    LIMIT 5";
            $recent_images_result = safe_query($conn, $recent_images_query, "Failed to fetch recent images.");
            ?>
            <?php if ($recent_images_result && $recent_images_result->num_rows > 0): ?>
                <div class="recent-items">
                    <?php while ($image_post = $recent_images_result->fetch_assoc()): 
                        $image_src = '';
                        if (!empty($image_post['image_path'])) {
                            if (filter_var($image_post['image_path'], FILTER_VALIDATE_URL)) {
                                // For Unsplash URLs, append optimization parameters. Adjust dimensions as needed.
                                $image_src = htmlspecialchars($image_post['image_path']) . '?q=80&w=120&h=120&fit=crop'; 
                            } else {
                                $image_src = '../' . htmlspecialchars($image_post['image_path']); // Assuming local paths are relative to root
                            }
                        }
                    ?>
                        <div class="recent-item">
                            <div class="item-info" style="display: flex; align-items: center; gap: 1rem;">
                                <?php if ($image_src): ?>
                                    <img src="<?php echo $image_src; ?>" alt="<?php echo htmlspecialchars($image_post['title']); ?>" 
                                         style="width: 60px; height: 60px; object-fit: cover; border-radius: 8px; border: 1px solid #e9ecef;"
                                         loading="lazy"
                                         onerror="this.onerror=null;this.src='https://placehold.co/60x60/cccccc/333333?text=N/A';">
                                <?php else: ?>
                                    <div style="width: 60px; height: 60px; display: flex; align-items: center; justify-content: center; background-color: #f0f0f0; border-radius: 8px; border: 1px solid #e9ecef; color: #999;">
                                        <i class="fas fa-image" style="font-size: 1.5rem;"></i>
                                    </div>
                                <?php endif; ?>
                                <div>
                                    <h4 style="margin:0;">
                                        <a href="../post.php?id=<?php echo $image_post['id']; ?>">
                                            <?php echo htmlspecialchars(truncate_text($image_post['title'], 40)); ?>
                                        </a>
                                    </h4>
                                    <p class="item-meta">
                                        Published: <?php echo format_datetime($image_post['published_at']); ?>
                                    </p>
                                </div>
                            </div>
                            <div class="item-actions">
                                <a href="<?php echo $image_src; ?>" target="_blank" class="btn btn-sm btn-outline">View Image</a>
                                <a href="manage_posts.php?edit=<?php echo $image_post['id']; ?>" class="btn btn-sm btn-outline">Edit Post</a>
                            </div>
                        </div>
                    <?php endwhile; ?>
                </div>
                <div class="card-footer">
                    <a href="manage_posts.php" class="btn btn-outline">View All Posts (with Images)</a>
                </div>
            <?php else: ?>
                <p class="text-muted">No post images found yet.</p>
            <?php endif; ?>
        </div>
        <!-- Recent Likes -->
        <div class="dashboard-card">
            <h3><i class="fas fa-thumbs-up"></i> Recent Likes</h3>
            <?php
            // Fetch recent likes (assuming a 'likes' table with user_id, post_id, created_at)
            $recent_likes_query = "SELECT l.*, u.username, p.title AS post_title
                                   FROM likes l
                                   LEFT JOIN users u ON l.user_id = u.id
                                   LEFT JOIN posts p ON l.post_id = p.id
                                   ORDER BY l.created_at DESC
                                   LIMIT 5";
            $recent_likes_result = safe_query($conn, $recent_likes_query, "Failed to fetch recent likes.");
            ?>
            <?php if ($recent_likes_result && $recent_likes_result->num_rows > 0): ?>
                <div class="recent-items">
                    <?php while ($like = $recent_likes_result->fetch_assoc()): ?>
                        <div class="recent-item">
                            <div class="item-info">
                                <h4>
                                    <i class="fas fa-thumbs-up" style="color:#3498db;"></i>
                                    <?php echo htmlspecialchars($like['username']); ?>
                                </h4>
                                <p class="item-meta">
                                    Liked 
                                    <a href="../post.php?id=<?php echo $like['post_id']; ?>">
                                        <?php echo htmlspecialchars(truncate_text($like['post_title'], 40)); ?>
                                    </a>
                                    • <?php echo format_datetime($like['created_at']); ?>
                                </p>
                            </div>
                            <div class="item-actions">
                                <a href="manage_posts.php?edit=<?php echo $like['post_id']; ?>" class="btn btn-sm btn-outline">View Post</a>
                                <a href="manage_users.php?user=<?php echo $like['user_id']; ?>" class="btn btn-sm btn-outline">View User</a>
                            </div>
                        </div>
                    <?php endwhile; ?>
                </div>
                <div class="card-footer">
                    <a href="manage_likes.php" class="btn btn-outline">View All Likes</a>
                </div>
            <?php else: ?>
                <p class="text-muted">No recent likes yet.</p>
            <?php endif; ?>
        </div>
        <!-- Recent Dislikes -->
        <div class="dashboard-card">
            <h3><i class="fas fa-thumbs-down"></i> Recent Dislikes</h3>
            <?php
            // Fetch recent dislikes (assuming a 'dislikes' table with user_id, post_id, created_at)
            $recent_dislikes_query = "SELECT d.*, u.username, p.title AS post_title
                                      FROM dislikes d
                                      LEFT JOIN users u ON d.user_id = u.id
                                      LEFT JOIN posts p ON d.post_id = p.id
                                      ORDER BY d.created_at DESC
                                      LIMIT 5";
            $recent_dislikes_result = safe_query($conn, $recent_dislikes_query, "Failed to fetch recent dislikes.");
            ?>
            <?php if ($recent_dislikes_result && $recent_dislikes_result->num_rows > 0): ?>
                <div class="recent-items">
                    <?php while ($dislike = $recent_dislikes_result->fetch_assoc()): ?>
                        <div class="recent-item">
                            <div class="item-info">
                                <h4>
                                    <i class="fas fa-thumbs-down" style="color:#e74c3c;"></i>
                                    <?php echo htmlspecialchars($dislike['username']); ?>
                                </h4>
                                <p class="item-meta">
                                    Disliked 
                                    <a href="../post.php?id=<?php echo $dislike['post_id']; ?>">
                                        <?php echo htmlspecialchars(truncate_text($dislike['post_title'], 40)); ?>
                                    </a>
                                    • <?php echo format_datetime($dislike['created_at']); ?>
                                </p>
                            </div>
                            <div class="item-actions">
                                <a href="manage_posts.php?edit=<?php echo $dislike['post_id']; ?>" class="btn btn-sm btn-outline">View Post</a>
                                <a href="manage_users.php?user=<?php echo $dislike['user_id']; ?>" class="btn btn-sm btn-outline">View User</a>
                            </div>
                        </div>
                    <?php endwhile; ?>
                </div>
                <div class="card-footer">
                    <a href="manage_dislikes.php" class="btn btn-outline">View All Dislikes</a>
                </div>
            <?php else: ?>
                <p class="text-muted">No recent dislikes yet.</p>
            <?php endif; ?>
        </div>

        <!-- NEW: Recent Messages -->
        <div class="dashboard-card">
            <h3><i class="fas fa-envelope"></i> Recent Messages</h3>
            <?php if ($recent_messages_result && $recent_messages_result->num_rows > 0): ?>
                <div class="recent-items">
                    <?php while ($message = $recent_messages_result->fetch_assoc()): ?>
                        <div class="recent-item">
                            <div class="item-info">
                                <h4>
                                    <?php 
                                    if ($message['message_type'] === 'contact_form') {
                                        echo '<i class="fas fa-inbox" style="color: #28a745;" title="Contact Form Message"></i> ';
                                        echo htmlspecialchars($message['subject'] ? truncate_text($message['subject'], 40) : 'No Subject');
                                    } elseif ($message['message_type'] === 'chatbot_admin_request') {
                                        echo '<i class="fas fa-robot" style="color: #3498db;" title="Chatbot Admin Request"></i> ';
                                        echo 'Admin Request';
                                    }
                                    ?>
                                </h4>
                                <p class="item-meta">
                                    From: <?php echo htmlspecialchars($message['sender_name']); ?> (<?php echo htmlspecialchars($message['sender_email']); ?>) • 
                                    <?php echo format_datetime($message['created_at']); ?> • 
                                    <span class="status-<?php echo $message['status']; ?>"><?php echo ucfirst($message['status']); ?></span>
                                </p>
                                <p class="item-meta message-preview"><?php echo truncate_text(htmlspecialchars($message['message_content']), 80); ?></p>
                            </div>
                            <div class="item-actions">
                                <a href="manage_messages.php?message_id=<?php echo $message['id']; ?>" class="btn btn-sm btn-outline">View</a>
                            </div>
                        </div>
                    <?php endwhile; ?>
                </div>
                <div class="card-footer">
                    <a href="manage_messages.php" class="btn btn-outline">View All Messages</a>
                </div>
            <?php else: ?>
                <p class="text-muted">No recent messages yet.</p>
            <?php endif; ?>
        </div>
        <!-- NEW: Recent Follows -->
        <div class="dashboard-card">
            <h3><i class="fas fa-user-friends"></i> Recent Follows</h3>
            <?php
            $recent_follows_query = "SELECT f.*, u1.username AS follower_username, u2.username AS followed_username
                                     FROM user_follows f
                                     LEFT JOIN users u1 ON f.follower_id = u1.id
                                     LEFT JOIN users u2 ON f.followed_id = u2.id
                                     ORDER BY f.created_at DESC
                                     LIMIT 5";
            $recent_follows_result = safe_query($conn, $recent_follows_query, "Failed to fetch recent follows.");
            ?>
            <?php if ($recent_follows_result && $recent_follows_result->num_rows > 0): ?>
                <div class="recent-items">
                    <?php while ($follow = $recent_follows_result->fetch_assoc()): ?>
                        <div class="recent-item">
                            <div class="item-info">
                                <h4>
                                    <a href="manage_users.php?user=<?php echo $follow['follower_id']; ?>">
                                        <?php echo htmlspecialchars($follow['follower_username']); ?>
                                    </a>
                                    <i class="fas fa-arrow-right" style="margin: 0 5px; color: var(--secondary-color);"></i>
                                    <a href="manage_users.php?user=<?php echo $follow['followed_id']; ?>">
                                        <?php echo htmlspecialchars($follow['followed_username']); ?>
                                    </a>
                                </h4>
                                <p class="item-meta">
                                    Followed on <?php echo format_datetime($follow['created_at']); ?>
                                </p>
                            </div>
                            <div class="item-actions">
                                <a href="manage_follows.php" class="btn btn-sm btn-outline">Manage</a>
                            </div>
                        </div>
                    <?php endwhile; ?>
                </div>
                <div class="card-footer">
                    <a href="manage_follows.php" class="btn btn-outline">View All Follows</a>
                </div>
            <?php else: ?>
                <p class="text-muted">No recent follows yet.</p>
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
.btn-info {
    background-color: var(--primary-color);
    color: #fff;
    border: none;
}

.admin-header {
    background: var(--background-white);
    padding: 2rem;
    border-radius: 12px;
    box-shadow: var(--shadow-medium);
    text-align: center;
}

.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 1.5rem;
}

.stat-card {
    background: var(--background-white);
    padding: 1.5rem;
    border-radius: 12px;
    box-shadow: var(--shadow-medium);
    display: flex;
    align-items: center;
    gap: 1rem;
}

.stat-icon {
    width: 60px;
    height: 60px;
    border-radius: 50%;
    background: linear-gradient(135deg, var(--primary-color) 0%, var(--primary-dark) 100%);
    display: flex;
    align-items: center;
    justify-content: center;
    color: #fff;
    font-size: 1.5rem;
}

.stat-info {
    flex: 1;
}

.stat-number {
    font-size: 2rem;
    font-weight: 700;
    color: var(--heading-color);
}

.stat-label {
    color: var(--secondary-color);
    margin-bottom: 0.25rem;
}

.stat-detail {
    font-size: 0.9rem;
    color: var(--secondary-color);
}

.quick-actions {
    background: var(--background-white);
    padding: 2rem;
    border-radius: 12px;
    box-shadow: var(--shadow-medium);
}

.quick-actions h3 {
    margin-bottom: 1.5rem;
    color: var(--heading-color);
}

.action-buttons {
    display: flex;
    flex-wrap: wrap;
    gap: 1rem;
}

.action-buttons .btn {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    position: relative;
}

.badge {
    position: absolute;
    top: -8px;
    right: -8px;
    background: var(--accent-color);
    color: #fff;
    border-radius: 50%;
    width: 20px;
    height: 20px;
    font-size: 0.7rem;
    display: flex;
    align-items: center;
    justify-content: center;
}

.recent-items {
    max-height: 300px;
    overflow-y: auto;
}

.recent-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 1rem 0;
    border-bottom: 1px solid var(--border-color);
}

.recent-item:last-child {
    border-bottom: none;
}

.item-info h4 {
    margin: 0 0 0.25rem 0;
    font-size: 1rem;
    color: var(--heading-color);
}

.item-info h4 a {
    color: inherit;
    text-decoration: none;
}

.item-info h4 a:hover {
    color: var(--primary-color);
}

.item-meta {
    margin: 0;
    font-size: 0.9rem;
    color: var(--secondary-color);
}

.item-meta a {
    color: var(--primary-color);
    text-decoration: none;
}

.item-meta a:hover {
    text-decoration: underline;
}

.status-published,
.role-admin {
    color: #27ae60;
    font-weight: 500;
}

.status-draft {
    color: #f39c12;
    font-weight: 500;
}

.status-new { /* Style for new messages */
    color: var(--accent-color); /* Red for new/pending */
    font-weight: 500;
}
.status-read { /* Style for read messages */
    color: var(--secondary-color); /* Grey for read */
    font-weight: 500;
}
.status-responded { /* Style for responded messages */
    color: #27ae60; /* Green for responded */
    font-weight: 500;
}

.status-pending {
    color: var(--accent-color);
    font-weight: 500;
}

.status-approved {
    color: #27ae60;
    font-weight: 500;
}

.role-user {
    color: var(--secondary-color);
    font-weight: 500;
}

.card-footer {
    margin-top: 1rem;
    padding-top: 1rem;
    border-top: 1px solid var(--border-color);
    text-align: center;
}

/* NEW: Settings Toggles Grid */
.settings-toggles-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
    gap: 1.5rem;
    margin-top: 1.5rem;
}

.toggle-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    background: var(--background-light);
    padding: 1rem 1.5rem;
    border-radius: 10px;
    box-shadow: var(--shadow-light);
}

.toggle-item label {
    color: var(--heading-color);
    font-weight: 500;
    margin-right: 1rem;
}

/* The switch - the box around the slider */
.switch {
  position: relative;
  display: inline-block;
  width: 50px;
  height: 28px;
}

/* Hide default HTML checkbox */
.switch input {
  opacity: 0;
  width: 0;
  height: 0;
}

/* The slider */
.slider {
  position: absolute;
  cursor: pointer;
  top: 0;
  left: 0;
  right: 0;
  bottom: 0;
  background-color: #ccc;
  -webkit-transition: .4s;
  transition: .4s;
  border-radius: 28px; /* Make it round */
}

.slider:before {
  position: absolute;
  content: "";
  height: 20px;
  width: 20px;
  left: 4px;
  bottom: 4px;
  background-color: white;
  -webkit-transition: .4s;
  transition: .4s;
  border-radius: 50%; /* Make it round */
}

input:checked + .slider {
  background-color: var(--primary-color);
}

input:focus + .slider {
  box-shadow: 0 0 1px var(--primary-color);
}

input:checked + .slider:before {
  -webkit-transform: translateX(22px);
  -ms-transform: translateX(22px);
  transform: translateX(22px);
}

/* Rounded sliders */
.slider.round {
  border-radius: 28px;
}

.slider.round:before {
  border-radius: 50%;
}


@media (max-width: 768px) {
    .stats-grid {
        grid-template-columns: 1fr;
    }
    
    .action-buttons {
        flex-direction: column;
    }
    
    .recent-item {
        flex-direction: column;
        align-items: flex-start;
        gap: 0.5rem;
    }
    
    .item-actions {
        align-self: flex-end;
    }

    .settings-toggles-grid {
        grid-template-columns: 1fr;
    }
}
</style>

<?php include 'includes/footer.php'; ?>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
<?php
// Fetch posts by category for the chart
$category_chart_query = "SELECT c.name AS category_name, COUNT(p.id) AS post_count
                         FROM categories c
                         LEFT JOIN posts p ON p.category_id = c.id AND p.status = 'published'
                         GROUP BY c.id, c.name
                         ORDER BY post_count DESC, c.name ASC";
$category_chart_result = safe_query($conn, $category_chart_query, "Failed to fetch posts by category for chart.");

$categoryLabels = [];
$categoryPostCounts = [];
if ($category_chart_result) {
    while ($row = $category_chart_result->fetch_assoc()) {
        $categoryLabels[] = $row['category_name'];
        $categoryPostCounts[] = (int)$row['post_count'];
    }
}

// Fetch user roles for chart
$user_roles_query = "SELECT role, COUNT(id) as user_count FROM users GROUP BY role";
$user_roles_result = safe_query($conn, $user_roles_query, "Failed to fetch user roles for chart.");
$userRoleLabels = [];
$userRoleCounts = [];
if ($user_roles_result) {
    while ($row = $user_roles_result->fetch_assoc()) {
        $userRoleLabels[] = ucfirst($row['role']);
        $userRoleCounts[] = (int)$row['user_count'];
    }
}

// Fetch total followers and total following for chart
$total_followers_query = "SELECT COUNT(DISTINCT follower_id) as count FROM user_follows"; // Corrected table name
$total_followers_result_obj = safe_query($conn, $total_followers_query, "Failed to fetch total followers count.");
$total_followers = $total_followers_result_obj ? $total_followers_result_obj->fetch_assoc()['count'] : 0;

$total_following_query = "SELECT COUNT(DISTINCT followed_id) as count FROM user_follows"; // Corrected table name
$total_following_result_obj = safe_query($conn, $total_following_query, "Failed to fetch total following count.");
$total_following = $total_following_result_obj ? $total_following_result_obj->fetch_assoc()['count'] : 0;

// Fetch total posts and total comments for Content Type Distribution Chart
$total_posts_count_query = "SELECT COUNT(*) as total FROM posts WHERE status = 'published'";
$total_posts_count_result_obj = safe_query($conn, $total_posts_count_query, "Failed to fetch total published posts count.");
$total_published_posts = $total_posts_count_result_obj ? $total_posts_count_result_obj->fetch_assoc()['total'] : 0;

$total_comments_count_query = "SELECT COUNT(*) as total FROM comments WHERE status = 'approved'";
$total_comments_count_result_obj = safe_query($conn, $total_comments_count_query, "Failed to fetch total approved comments count.");
$total_approved_comments = $total_comments_count_result_obj ? $total_comments_count_result_obj->fetch_assoc()['total'] : 0;

?>
document.addEventListener('DOMContentLoaded', function() {
    // Data from PHP, ensure it's safely embedded
    const stats = <?php echo json_encode($stats); ?>;
    const categoryLabels = <?php echo json_encode($categoryLabels); ?>;
    const categoryPostCounts = <?php echo json_encode($categoryPostCounts); ?>;
    const userRoleLabels = <?php echo json_encode($userRoleLabels); ?>;
    const userRoleCounts = <?php echo json_encode($userRoleCounts); ?>;
    const totalLikes = <?php echo (int)$likes_count; ?>;
    const totalDislikes = <?php echo (int)$dislikes_count; ?>;
    const pendingDislikeBtn = <?php echo (int)$pending_dislike_btn; ?>;
    const totalPostsOverall = <?php echo (int)($stats['total_posts'] ?? 0); ?>; // Use overall total posts for this calculation
    const totalFollowers = <?php echo (int)$total_followers; ?>;
    const totalFollowing = <?php echo (int)$total_following; ?>;
    const totalPublishedPosts = <?php echo (int)$total_published_posts; ?>;
    const totalApprovedComments = <?php echo (int)$total_approved_comments; ?>;

    // Color palettes for charts
    const primaryColor = 'rgba(52, 152, 219, 0.8)'; // Blue
    const secondaryColor = 'rgba(149, 165, 166, 0.8)'; // Grey
    const successColor = 'rgba(46, 204, 113, 0.8)'; // Green
    const warningColor = 'rgba(241, 196, 15, 0.8)'; // Yellow
    const dangerColor = 'rgba(231, 76, 60, 0.8)'; // Red
    const infoColor = 'rgba(52, 152, 219, 0.8)'; // Blue (can reuse primary)
    const purpleColor = 'rgba(155, 89, 182, 0.8)'; // Purple
    const orangeColor = 'rgba(230, 126, 34, 0.8)'; // Orange

    const categoryColors = [
        primaryColor, successColor, warningColor, dangerColor,
        purpleColor, orangeColor, 'rgba(26, 188, 156, 0.8)', 'rgba(127, 140, 141, 0.8)', 'rgba(52, 73, 94, 0.8)'
    ];
    const categoryBorderColors = [
        'rgba(52, 152, 219, 1)', 'rgba(46, 204, 113, 1)', 'rgba(241, 196, 15, 1)',
        'rgba(231, 76, 60, 1)', 'rgba(155, 89, 182, 1)', 'rgba(230, 126, 34, 1)',
        'rgba(26, 188, 156, 1)', 'rgba(127, 140, 141, 1)', 'rgba(52, 73, 94, 1)'
    ];

    // --- Post Status Chart (Pie Chart) ---
    const postStatusCtx = document.getElementById('postStatusChart').getContext('2d');
    new Chart(postStatusCtx, {
        type: 'pie',
        data: {
            labels: ['Published Posts', 'Draft Posts'],
            datasets: [{
                data: [stats.published_posts, stats.draft_posts],
                backgroundColor: [successColor, warningColor],
                borderColor: [successColor.replace('0.8', '1'), warningColor.replace('0.8', '1')],
                borderWidth: 1
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { position: 'bottom' },
                title: { display: false }
            }
        }
    });

    // --- User Roles Chart (Doughnut Chart) ---
    const userRoleCtx = document.getElementById('userRoleChart').getContext('2d');
    new Chart(userRoleCtx, {
        type: 'doughnut',
        data: {
            labels: userRoleLabels,
            datasets: [{
                data: userRoleCounts,
                backgroundColor: [primaryColor, secondaryColor], // Assuming Admin and Regular User
                borderColor: [primaryColor.replace('0.8', '1'), secondaryColor.replace('0.8', '1')],
                borderWidth: 1
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { position: 'bottom' },
                title: { display: false }
            }
        }
    });

    // --- Comment Status Chart (Bar Chart) ---
    const commentStatusCtx = document.getElementById('commentStatusChart').getContext('2d');
    new Chart(commentStatusCtx, {
        type: 'bar',
        data: {
            labels: ['Pending Comments', 'Approved Comments'],
            datasets: [{
                label: 'Number of Comments',
                data: [stats.pending_comments, stats.approved_comments],
                backgroundColor: [dangerColor, successColor],
                borderColor: [dangerColor.replace('0.8', '1'), successColor.replace('0.8', '1')],
                borderWidth: 1
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { display: false },
                title: { display: false }
            },
            scales: {
                y: { beginAtZero: true, ticks: { precision: 0 } }
            }
        }
    });

     // --- Posts by Category Chart (Bar Chart) ---
    const postsByCategoryCtx = document.getElementById('postsByCategoryChart').getContext('2d');
    new Chart(postsByCategoryCtx, {
        type: 'bar',
        data: {
            labels: categoryLabels,
            datasets: [{
                label: 'Number of Posts',
                data: categoryPostCounts,
                backgroundColor: categoryColors.slice(0, categoryLabels.length),
                borderColor: categoryBorderColors.slice(0, categoryLabels.length),
                borderWidth: 1
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { display: false },
                title: { display: false }
            },
            scales: {
                y: { beginAtZero: true, ticks: { precision: 0 } },
                x: { ticks: { autoSkip: false, maxRotation: 45, minRotation: 45 } }
            }
        }
    });
    
    // --- Likes vs Dislikes Chart (Polar Area) ---
    const likesDislikesCtx = document.getElementById('likesDislikesChart').getContext('2d');
    new Chart(likesDislikesCtx, {
        type: 'polarArea',
        data: {
            labels: ['Likes', 'Dislikes'],
            datasets: [{
                data: [totalLikes, totalDislikes],
                backgroundColor: [infoColor, dangerColor],
                borderColor: [infoColor.replace('0.8', '1'), dangerColor.replace('0.8', '1')],
                borderWidth: 2
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { position: 'bottom' },
                title: { display: false }
            }
        }
    });

    // --- Pending Dislike Button Chart (Doughnut) ---
    const pendingDislikeBtnCtx = document.getElementById('pendingDislikeBtnChart').getContext('2d');
    new Chart(pendingDislikeBtnCtx, {
        type: 'doughnut',
        data: {
            labels: ['Pending Approval', 'Approved/Other'],
            datasets: [{
                data: [pendingDislikeBtn, totalPostsOverall - pendingDislikeBtn],
                backgroundColor: [warningColor, successColor.replace('0.8', '0.7')],
                borderColor: [warningColor.replace('0.8', '1'), successColor.replace('0.8', '1')],
                borderWidth: 2
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { position: 'bottom' },
                title: { display: false }
            }
        }
    });

    // --- NEW: User Follow Statistics Chart (Bar Chart) ---
    const userFollowCtx = document.getElementById('userFollowChart').getContext('2d');
    new Chart(userFollowCtx, {
        type: 'bar',
        data: {
            labels: ['Total Followers', 'Total Following'],
            datasets: [{
                label: 'Count',
                data: [totalFollowers, totalFollowing],
                backgroundColor: [primaryColor, purpleColor],
                borderColor: [primaryColor.replace('0.8', '1'), purpleColor.replace('0.8', '1')],
                borderWidth: 1
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { display: false },
                title: { display: false }
            },
            scales: {
                y: { beginAtZero: true, ticks: { precision: 0 } }
            }
        }
    });

    // --- NEW: Content Type Distribution (Posts vs. Comments) Chart ---
    const contentTypeCtx = document.getElementById('contentTypeChart').getContext('2d');
    new Chart(contentTypeCtx, {
        type: 'pie',
        data: {
            labels: ['Published Posts', 'Approved Comments'],
            datasets: [{
                data: [totalPublishedPosts, totalApprovedComments],
                backgroundColor: [infoColor, successColor],
                borderColor: [infoColor.replace('0.8', '1'), successColor.replace('0.8', '1')],
                borderWidth: 1
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { position: 'bottom' },
                title: { display: false }
            }
        }
    });

    // --- Toggle Switch Logic (for settings) ---
    document.querySelectorAll('.switch input[type="checkbox"]').forEach(toggle => {
        toggle.addEventListener('change', function() {
            const settingName = this.dataset.settingName;
            const settingValue = this.checked ? '1' : '0';

            // Send AJAX request to update setting
            const formData = new FormData();
            formData.append('update_setting', '1');
            formData.append('setting_name', settingName);
            formData.append('setting_value', settingValue);

            fetch('admin_dashboard.php', { // Or a dedicated settings update endpoint
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    console.log(`Setting '${settingName}' updated to '${settingValue}'.`);
                    // Optionally, show a small success toast
                } else {
                    console.error(`Failed to update setting '${settingName}':`, data.message);
                    // Revert toggle state if update failed
                    this.checked = !this.checked;
                    // Optionally, show an error toast
                }
            })
            .catch(error => {
                console.error('Error updating setting:', error);
                this.checked = !this.checked; // Revert on network error
            });
        });
    });
});
</script>
