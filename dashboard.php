<?php
$page_title = "Dashboard";
include 'includes/header.php';
require_once 'includes/functions.php'; // Ensure functions are available
require_once 'includes/db_connection.php'; // Ensure db connection is available

// Require login
require_login();

$user_id = $_SESSION['user_id'];
$user = get_user_by_id($conn, $user_id);

// Use 'avatars/' for predefined avatars
$available_avatars = get_available_avatars('avatars/');

// --- Start PHP for Profile Picture/Avatar Upload/Selection ---
$profile_error_message = '';
$profile_success_message = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_profile_pic'])) {
    // Corrected: Check if 'prefers_avatar' is set AND its value is '1' for true
    $prefers_avatar = isset($_POST['prefers_avatar']) && $_POST['prefers_avatar'] == '1' ? true : false;
    $selected_avatar = isset($_POST['selected_avatar']) ? sanitize_input($_POST['selected_avatar']) : null;
    $image_path = $user['profile_image_path']; // Keep current path by default

    // Handle custom image upload
    if (!$prefers_avatar && isset($_FILES['custom_profile_image']) && $_FILES['custom_profile_image']['error'] == 0) {
        $upload_dir = 'profiles/';
        // Ensure the upload directory exists and is writable
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0755, true);
        }
        if (!is_writable($upload_dir)) {
            $profile_error_message = "Upload directory is not writable.";
        } else {
            $upload_result = upload_image($_FILES['custom_profile_image'], $upload_dir);
            if ($upload_result['success']) {
                // Delete old custom image if it exists and was not an avatar
                if (!empty($user['profile_image_path']) && !$user['prefers_avatar'] && file_exists($user['profile_image_path'])) {
                    unlink($user['profile_image_path']);
                }
                $image_path = $upload_result['filepath'];
                $profile_success_message = "Profile image uploaded successfully!";
            } else {
                $profile_error_message = $upload_result['message'];
            }
        }
    } elseif ($prefers_avatar && $selected_avatar) {
        // Validate against available avatars from avatars/ folder
        if (in_array($selected_avatar, $available_avatars)) {
            // Delete old custom image if it exists and was not an avatar
            if (!empty($user['profile_image_path']) && !$user['prefers_avatar'] && file_exists($user['profile_image_path'])) {
                unlink($user['profile_image_path']);
            }
            $image_path = $selected_avatar;
            $profile_success_message = "Avatar selected successfully!";
        } else {
            $profile_error_message = "Invalid avatar selected.";
        }
    } else {
        // If no file uploaded and no avatar selected, and they prefer custom, clear path
        if (!$prefers_avatar && empty($_FILES['custom_profile_image']['tmp_name'])) {
            // If they are switching from avatar to no image, or from custom to no image
            if (!empty($user['profile_image_path']) && file_exists($user['profile_image_path']) && strpos($user['profile_image_path'], 'profiles/') === 0) {
                unlink($user['profile_image_path']); // Delete only custom uploaded images
            }
            $image_path = null;
            $profile_success_message = "Profile image cleared.";
        }
    }

    if (empty($profile_error_message)) {
        // Update user's profile_image_path and prefers_avatar in the database
        if (update_user_avatar_preference($conn, $user_id, $image_path, $prefers_avatar)) {
            // Re-fetch user data to reflect changes immediately
            $user = get_user_by_id($conn, $user_id);
            $profile_success_message = $profile_success_message ?: "Profile updated successfully!";
        } else {
            $profile_error_message = "Failed to update profile. Please try again.";
        }
    }
}


// Handle Bio Update
$bio_error_message = '';
$bio_success_message = '';
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_bio'])) {
    $new_bio = isset($_POST['bio']) ? sanitize_input($_POST['bio']) : '';
    if (update_user_bio($conn, $user_id, $new_bio)) {
        $user['bio'] = $new_bio; // Update local user array
        $bio_success_message = "Bio updated successfully!";
    } else {
        $bio_error_message = "Failed to update bio. Please try again.";
    }
}
// --- End PHP for Profile Picture/Avatar Upload/Selection and Bio ---

// Pagination for user posts
$posts_per_page = 10;
$current_page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$offset = ($current_page - 1) * $posts_per_page;

// Get total count of user's posts for pagination
$total_posts_query = "SELECT COUNT(*) as total FROM posts WHERE user_id = ?";
$total_posts_stmt = $conn->prepare($total_posts_query);
$total_posts_stmt->bind_param("i", $user_id);
$total_posts_stmt->execute();
$total_posts_result = $total_posts_stmt->get_result();
$total_posts = $total_posts_result->fetch_assoc()['total'];
$total_posts_stmt->close();

$total_pages = ceil($total_posts / $posts_per_page);

// Get user's posts with pagination
$user_posts_query = "SELECT p.*, c.name as category_name
                     FROM posts p
                     LEFT JOIN categories c ON p.category_id = c.id
                     WHERE p.user_id = ?
                     ORDER BY p.updated_at DESC
                     LIMIT ? OFFSET ?";
$user_posts_stmt = $conn->prepare($user_posts_query);
$user_posts_stmt->bind_param("iii", $user_id, $posts_per_page, $offset);
$user_posts_stmt->execute();
$user_posts_result = $user_posts_stmt->get_result();

// Get user's comments
$user_comments_query = "SELECT c.*, p.title as post_title
                        FROM comments c
                        LEFT JOIN posts p ON c.post_id = p.id
                        WHERE c.user_id = ?
                        ORDER BY c.created_at DESC
                        LIMIT 10";
$user_comments_stmt = $conn->prepare($user_comments_query);
$user_comments_stmt->bind_param("i", $user_id);
$user_comments_stmt->execute();
$user_comments_result = $user_comments_stmt->get_result();

// Get user statistics
$stats_query = "SELECT
                    (SELECT COUNT(*) FROM posts WHERE user_id = ?) as total_posts,
                    (SELECT COUNT(*) FROM posts WHERE user_id = ? AND status = 'published') as published_posts,
                    (SELECT COUNT(*) FROM posts WHERE user_id = ? AND status = 'draft') as draft_posts,
                    (SELECT COUNT(*) FROM comments WHERE user_id = ?) as total_comments,
                    (SELECT COUNT(*) FROM likes WHERE user_id = ?) as total_likes_given,
                    (SELECT COUNT(*) FROM dislikes WHERE user_id = ?) as total_dislikes_given";
$stats_stmt = $conn->prepare($stats_query);
$stats_stmt->bind_param("iiiiii", $user_id, $user_id, $user_id, $user_id, $user_id, $user_id);
$stats_stmt->execute();
$stats = $stats_stmt->get_result()->fetch_assoc();

// Prepare user_stats array for charts
// Get comment status counts
$comment_status_query = "SELECT
    SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending_comments,
    SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END) as approved_comments
    FROM comments WHERE user_id = ?";
$comment_status_stmt = $conn->prepare($comment_status_query);
$comment_status_stmt->bind_param("i", $user_id);
$comment_status_stmt->execute();
$comment_status = $comment_status_stmt->get_result()->fetch_assoc();

// Get counts of likes/dislikes on user's own posts
$user_post_reactions_query = "SELECT
    (SELECT COUNT(l.id) FROM likes l JOIN posts p ON l.post_id = p.id WHERE p.user_id = ?) as received_likes,
    (SELECT COUNT(d.id) FROM dislikes d JOIN posts p ON d.post_id = p.id WHERE p.user_id = ?) as received_dislikes";
$user_post_reactions_stmt = $conn->prepare($user_post_reactions_query);
$user_post_reactions_stmt->bind_param("ii", $user_id, $user_id);
$user_post_reactions_stmt->execute();
$user_post_reactions = $user_post_reactions_stmt->get_result()->fetch_assoc();

// Get follower and following counts
$follower_count = get_follower_count($conn, $user_id);
$following_count = get_following_count($conn, $user_id);


$user_stats = [
    'user_published_posts' => (int)$stats['published_posts'],
    'user_draft_posts' => (int)$stats['draft_posts'],
    'user_pending_comments' => (int)$comment_status['pending_comments'],
    'user_approved_comments' => (int)$comment_status['approved_comments'],
    'user_total_likes_given' => (int)$stats['total_likes_given'],
    'user_total_dislikes_given' => (int)$stats['total_dislikes_given'],
    'user_received_likes' => (int)$user_post_reactions['received_likes'],
    'user_received_dislikes' => (int)$user_post_reactions['received_dislikes'],
    'follower_count' => (int)$follower_count,
    'following_count' => (int)$following_count
];

// --- Enhanced Suggested Posts Algorithm ---
$suggested_posts = [];
if ($user_id > 0) {
    // 1. Get categories/tags from posts the user has liked
    $liked_categories_query = "SELECT DISTINCT c.id, COUNT(*) as like_count 
                              FROM likes l 
                              JOIN posts p ON l.post_id = p.id 
                              JOIN categories c ON p.category_id = c.id 
                              WHERE l.user_id = ? 
                              GROUP BY c.id 
                              ORDER BY like_count DESC";
    $liked_tags_query = "SELECT DISTINCT t.id, COUNT(*) as like_count 
                        FROM likes l 
                        JOIN posts p ON l.post_id = p.id 
                        JOIN post_tags pt ON p.id = pt.post_id 
                        JOIN tags t ON pt.tag_id = t.id 
                        WHERE l.user_id = ? 
                        GROUP BY t.id 
                        ORDER BY like_count DESC";

    $liked_category_ids = [];
    $stmt_cat = $conn->prepare($liked_categories_query);
    $stmt_cat->bind_param("i", $user_id);
    $stmt_cat->execute();
    $result_cat = $stmt_cat->get_result();
    while($row = $result_cat->fetch_assoc()) {
        $liked_category_ids[] = $row['id'];
    }
    $stmt_cat->close();

    $liked_tag_ids = [];
    $stmt_tag = $conn->prepare($liked_tags_query);
    $stmt_tag->bind_param("i", $user_id);
    $stmt_tag->execute();
    $result_tag = $stmt_tag->get_result();
    while($row = $result_tag->fetch_assoc()) {
        $liked_tag_ids[] = $row['id'];
    }
    $stmt_tag->close();

    // 2. Get categories/tags from user's own posts (their posting preferences)
    $user_post_categories_query = "SELECT DISTINCT c.id, COUNT(*) as post_count 
                                  FROM posts p 
                                  JOIN categories c ON p.category_id = c.id 
                                  WHERE p.user_id = ? AND p.status = 'published' 
                                  GROUP BY c.id 
                                  ORDER BY post_count DESC";
    $user_post_tags_query = "SELECT DISTINCT t.id, COUNT(*) as post_count 
                            FROM posts p 
                            JOIN post_tags pt ON p.id = pt.post_id 
                            JOIN tags t ON pt.tag_id = t.id 
                            WHERE p.user_id = ? AND p.status = 'published' 
                            GROUP BY t.id 
                            ORDER BY post_count DESC";

    $user_category_ids = [];
    $stmt_user_cat = $conn->prepare($user_post_categories_query);
    $stmt_user_cat->bind_param("i", $user_id);
    $stmt_user_cat->execute();
    $result_user_cat = $stmt_user_cat->get_result();
    while($row = $result_user_cat->fetch_assoc()) {
        $user_category_ids[] = $row['id'];
    }
    $stmt_user_cat->close();

    $user_tag_ids = [];
    $stmt_user_tag = $conn->prepare($user_post_tags_query);
    $stmt_user_tag->bind_param("i", $user_id);
    $stmt_user_tag->execute();
    $result_user_tag = $stmt_user_tag->get_result();
    while($row = $result_user_tag->fetch_assoc()) {
        $user_tag_ids[] = $row['id'];
    }
    $stmt_user_tag->close();

    // 3. Get users the current user is following
    $following_users_query = "SELECT followed_id FROM user_follows WHERE follower_id = ?";
    $following_user_ids = [];
    $stmt_following = $conn->prepare($following_users_query);
    $stmt_following->bind_param("i", $user_id);
    $stmt_following->execute();
    $result_following = $stmt_following->get_result();
    while($row = $result_following->fetch_assoc()) {
        $following_user_ids[] = $row['followed_id'];
    }
    $stmt_following->close();

    // 4. Get categories/tags from posts by users the current user follows
    $following_categories_query = "SELECT DISTINCT c.id, COUNT(*) as follow_count 
                                  FROM posts p 
                                  JOIN categories c ON p.category_id = c.id 
                                  WHERE p.user_id IN (" . (empty($following_user_ids) ? '0' : implode(',', array_fill(0, count($following_user_ids), '?'))) . ") 
                                  AND p.status = 'published' 
                                  GROUP BY c.id 
                                  ORDER BY follow_count DESC";
    $following_tags_query = "SELECT DISTINCT t.id, COUNT(*) as follow_count 
                            FROM posts p 
                            JOIN post_tags pt ON p.id = pt.post_id 
                            JOIN tags t ON pt.tag_id = t.id 
                            WHERE p.user_id IN (" . (empty($following_user_ids) ? '0' : implode(',', array_fill(0, count($following_user_ids), '?'))) . ") 
                            AND p.status = 'published' 
                            GROUP BY t.id 
                            ORDER BY follow_count DESC";

    $following_category_ids = [];
    if (!empty($following_user_ids)) {
        $stmt_following_cat = $conn->prepare($following_categories_query);
        $stmt_following_cat->bind_param(str_repeat('i', count($following_user_ids)), ...$following_user_ids);
        $stmt_following_cat->execute();
        $result_following_cat = $stmt_following_cat->get_result();
        while($row = $result_following_cat->fetch_assoc()) {
            $following_category_ids[] = $row['id'];
        }
        $stmt_following_cat->close();
    }

    $following_tag_ids = [];
    if (!empty($following_user_ids)) {
        $stmt_following_tag = $conn->prepare($following_tags_query);
        $stmt_following_tag->bind_param(str_repeat('i', count($following_user_ids)), ...$following_user_ids);
        $stmt_following_tag->execute();
        $result_following_tag = $stmt_following_tag->get_result();
        while($row = $result_following_tag->fetch_assoc()) {
            $following_tag_ids[] = $row['id'];
        }
        $stmt_following_tag->close();
    }

    // 5. Combine all preference signals with weights
    $all_category_ids = array_merge($liked_category_ids, $user_category_ids, $following_category_ids);
    $all_tag_ids = array_merge($liked_tag_ids, $user_tag_ids, $following_tag_ids);
    
    // Remove duplicates while preserving order (liked first, then user's posts, then following)
    $all_category_ids = array_unique($all_category_ids);
    $all_tag_ids = array_unique($all_tag_ids);

    // 6. Build the enhanced suggestion query
    if (!empty($all_category_ids) || !empty($all_tag_ids) || !empty($following_user_ids)) {
        $suggested_posts_params = [];
        $suggested_posts_types = "";
        $where_clauses = [];

        // Exclude posts already liked by the user
        $liked_post_ids_query = "SELECT post_id FROM likes WHERE user_id = ?";
        $stmt_liked_posts = $conn->prepare($liked_post_ids_query);
        $stmt_liked_posts->bind_param("i", $user_id);
        $stmt_liked_posts->execute();
        $result_liked_posts = $stmt_liked_posts->get_result();
        $excluded_post_ids = [];
        while($row = $result_liked_posts->fetch_assoc()) {
            $excluded_post_ids[] = $row['post_id'];
        }
        $stmt_liked_posts->close();

        if (!empty($excluded_post_ids)) {
            $exclude_placeholder = implode(',', array_fill(0, count($excluded_post_ids), '?'));
            $where_clauses[] = "p.id NOT IN ($exclude_placeholder)";
            $suggested_posts_types .= str_repeat('i', count($excluded_post_ids));
            $suggested_posts_params = array_merge($suggested_posts_params, $excluded_post_ids);
        }
        
        // Exclude user's own posts
        $where_clauses[] = "p.user_id != ?";
        $suggested_posts_types .= "i";
        $suggested_posts_params[] = $user_id;

        // Build preference conditions with weighted scoring
        $preference_conditions = [];
        
        // Category preferences
        if (!empty($all_category_ids)) {
            $cat_placeholder = implode(',', array_fill(0, count($all_category_ids), '?'));
            $preference_conditions[] = "p.category_id IN ($cat_placeholder)";
            $suggested_posts_types .= str_repeat('i', count($all_category_ids));
            $suggested_posts_params = array_merge($suggested_posts_params, $all_category_ids);
        }

        // Tag preferences
        if (!empty($all_tag_ids)) {
            $tag_placeholder = implode(',', array_fill(0, count($all_tag_ids), '?'));
            $preference_conditions[] = "pt.tag_id IN ($tag_placeholder)";
            $suggested_posts_types .= str_repeat('i', count($all_tag_ids));
            $suggested_posts_params = array_merge($suggested_posts_params, $all_tag_ids);
        }

        // Posts from followed users (highest priority)
        if (!empty($following_user_ids)) {
            $following_placeholder = implode(',', array_fill(0, count($following_user_ids), '?'));
            $preference_conditions[] = "p.user_id IN ($following_placeholder)";
            $suggested_posts_types .= str_repeat('i', count($following_user_ids));
            $suggested_posts_params = array_merge($suggested_posts_params, $following_user_ids);
        }

        // Combine all conditions
        $main_condition = '';
        if (!empty($preference_conditions)) {
            $main_condition = '(' . implode(' OR ', $preference_conditions) . ')';
        }

        if ($main_condition) {
            $final_where_clause = implode(' AND ', $where_clauses);
            $final_where_clause = $final_where_clause ? "WHERE $final_where_clause AND $main_condition" : "WHERE $main_condition";

            // Enhanced query with scoring based on multiple factors
            $suggested_query = "SELECT DISTINCT p.*, u.username, c.name as category_name,
                                (
                                    -- Posts from followed users get highest score (3 points)
                                    CASE WHEN p.user_id IN (" . (empty($following_user_ids) ? '0' : implode(',', array_fill(0, count($following_user_ids), '?'))) . ") THEN 3
                                    -- Posts with matching categories get medium score (2 points)
                                    WHEN p.category_id IN (" . (empty($all_category_ids) ? '0' : implode(',', array_fill(0, count($all_category_ids), '?'))) . ") THEN 2
                                    -- Posts with matching tags get lower score (1 point)
                                    WHEN pt.tag_id IN (" . (empty($all_tag_ids) ? '0' : implode(',', array_fill(0, count($all_tag_ids), '?'))) . ") THEN 1
                                    ELSE 0
                                    END
                                ) as relevance_score
                                FROM posts p
                                LEFT JOIN users u ON p.user_id = u.id
                                LEFT JOIN categories c ON p.category_id = c.id
                                LEFT JOIN post_tags pt ON p.id = pt.post_id
                                $final_where_clause AND p.status = 'published'
                                ORDER BY relevance_score DESC, p.published_at DESC
                                LIMIT 6";

            // Add parameters for scoring
            $scoring_params = array_merge($following_user_ids, $all_category_ids, $all_tag_ids);
            $scoring_types = str_repeat('i', count($scoring_params));
            
            $stmt_suggested = $conn->prepare($suggested_query);
            if (!empty($suggested_posts_types)) {
                $all_params = array_merge($suggested_posts_params, $scoring_params);
                $all_types = $suggested_posts_types . $scoring_types;
                $stmt_suggested->bind_param($all_types, ...$all_params);
            }
            $stmt_suggested->execute();
            $result_suggested = $stmt_suggested->get_result();
            while($post = $result_suggested->fetch_assoc()) {
                $suggested_posts[] = $post;
            }
            $stmt_suggested->close();
        }
    }
}
// Helper function to determine if a path is a URL (re-declared for scope or assumed global)
if (!function_exists('is_url_optimized')) {
    function is_url_optimized($path) {
        return filter_var($path, FILTER_VALIDATE_URL);
    }
}

// Determine the profile image to display
$display_profile_image = 'avatars/default_avatar.png'; // Fallback default

if (!empty($user['profile_image_path'])) {
    $display_profile_image = htmlspecialchars($user['profile_image_path']);
} elseif ($user['prefers_avatar']) {
    if ($user['gender'] == 'male') {
        $display_profile_image = 'avatars/male_avatar.png';
    } elseif ($user['gender'] == 'female') {
        $display_profile_image = 'avatars/female_avatar.png';
    } else {
        $display_profile_image = 'avatars/default_avatar.png';
    }
}

// Get current section from URL parameter
$current_section = isset($_GET['section']) ? $_GET['section'] : 'overview';
?>



<div class="container">
    <div class="dashboard-header mb-4">
        <div style="align-self:centre; margin-left:45%; " class="user-avatar-container">
            <img style="height:auto; width:30%; border-radius:50%;" src="<?php echo $display_profile_image; ?>" alt="Profile Picture" class="profile-pic">
        </div>
        <h1>Welcome back, <?php echo htmlspecialchars($user['username']); ?>!</h1>
        <p class="text-muted">Member since <?php echo format_date($user['created_at']); ?></p>
    </div>

    <!-- Dashboard Navigation Tabs -->
    <div class="dashboard-nav mb-4">
        <a href="?section=overview" class="nav-tab <?php echo $current_section == 'overview' ? 'active' : ''; ?>">
            <i class="fas fa-tachometer-alt"></i> Overview
        </a>
        <a href="?section=posts" class="nav-tab <?php echo $current_section == 'posts' ? 'active' : ''; ?>">
            <i class="fas fa-file-alt"></i> My Posts
        </a>
        <a href="?section=analytics" class="nav-tab <?php echo $current_section == 'analytics' ? 'active' : ''; ?>">
            <i class="fas fa-chart-bar"></i> Analytics
        </a>
        <a href="?section=profile" class="nav-tab <?php echo $current_section == 'profile' ? 'active' : ''; ?>">
            <i class="fas fa-user"></i> Profile
        </a>
        <a href="follow_and_followers.php" class="nav-tab">
            <i class="fas fa-user-friends"></i> Follows
        </a>
        <a href="?section=recommendations" class="nav-tab <?php echo $current_section == 'recommendations' ? 'active' : ''; ?>">
            <i class="fas fa-lightbulb"></i> Recommendations
        </a>
    </div>

    <!-- Overview Section -->
    <?php if ($current_section == 'overview'): ?>
        <!-- Statistics -->
        <div class="stats-grid mb-4">
            <div class="stat-card">
                <div class="stat-number"><?php echo $stats['total_posts']; ?></div>
                <div class="stat-label">Total Posts</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo $stats['published_posts']; ?></div>
                <div class="stat-label">Published Posts</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo $stats['draft_posts']; ?></div>
                <div class="stat-label">Draft Posts</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo $stats['total_comments']; ?></div>
                <div class="stat-label">Comments Made</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo $stats['total_likes_given']; ?></div>
                <div class="stat-label">Likes Given</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo $stats['total_dislikes_given']; ?></div>
                <div class="stat-label">Dislikes Given</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo $user_stats['user_received_likes']; ?></div>
                <div class="stat-label">Likes Received</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo $user_stats['user_received_dislikes']; ?></div>
                <div class="stat-label">Dislikes Received</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo $user_stats['follower_count']; ?></div>
                <div class="stat-label">Followers</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo $user_stats['following_count']; ?></div>
                <div class="stat-label">Following</div>
            </div>
        </div>

        <!-- Quick Actions -->
        <div class="dashboard-card mb-4">
            <h3><i class="fas fa-bolt"></i> Quick Actions</h3>
            <div class="quick-actions-grid">
                <a href="create_post.php" class="btn btn-primary">
                    <i class="fas fa-plus"></i> Create New Post
                </a>
                <a href="view_user.php?id=<?php echo $user['id']; ?>" class="btn btn-secondary">
                    <i class="fas fa-eye"></i> View My Profile
                </a>
                <?php if (is_admin()): ?>
                    <a href="admin_dashboard.php" class="btn btn-admin">
                        <i class="fas fa-cog"></i> Admin Panel
                    </a>
                <?php endif; ?>
            </div>
        </div>

        <!-- Most Liked/Disliked Posts -->
        <div class="dashboard-grid">
            <div class="dashboard-card">
                <h3><i class="fas fa-heart"></i> Most Liked Posts (Your Posts)</h3>
                <?php
                $most_liked_posts_query = "SELECT p.*, COUNT(l.id) as like_count
                                        FROM posts p
                                        LEFT JOIN likes l ON l.post_id = p.id
                                        WHERE p.user_id = ? AND p.status = 'published'
                                        GROUP BY p.id
                                        ORDER BY like_count DESC
                                        LIMIT 5";
                $most_liked_posts_stmt = $conn->prepare($most_liked_posts_query);
                $most_liked_posts_stmt->bind_param("i", $user_id);
                $most_liked_posts_stmt->execute();
                $most_liked_posts_result = $most_liked_posts_stmt->get_result();
                ?>
                <?php if ($most_liked_posts_result->num_rows > 0): ?>
                    <ul class="post-list-simple">
                        <?php while ($post = $most_liked_posts_result->fetch_assoc()): ?>
                            <li>
                                <a href="post.php?id=<?php echo $post['id']; ?>"><?php echo htmlspecialchars(truncate_text($post['title'], 40)); ?></a>
                                <span class="badge badge-success"><?php echo $post['like_count']; ?> <i class="fas fa-thumbs-up"></i></span>
                            </li>
                        <?php endwhile; ?>
                    </ul>
                <?php else: ?>
                    <p class="text-muted">No liked posts to display yet.</p>
                <?php endif; ?>
            </div>

            <div class="dashboard-card">
                <h3><i class="fas fa-thumbs-down"></i> Most Disliked Posts (Your Content)</h3>
                <?php
                $most_disliked_posts_query = "SELECT p.*, (SELECT COUNT(*) FROM dislikes WHERE post_id = p.id) as dislike_count
                                            FROM posts p
                                            WHERE p.user_id = ? AND p.status = 'published' AND p.dislike_button_status = 'enabled'
                                            ORDER BY dislike_count DESC
                                            LIMIT 5";
                $most_disliked_posts_stmt = $conn->prepare($most_disliked_posts_query);
                $most_disliked_posts_stmt->bind_param("i", $user_id);
                $most_disliked_posts_stmt->execute();
                $most_disliked_posts_result = $most_disliked_posts_stmt->get_result();
                ?>
                <?php if ($most_disliked_posts_result->num_rows > 0): ?>
                    <ul class="post-list-simple">
                        <?php while ($post = $most_disliked_posts_result->fetch_assoc()): ?>
                            <li>
                                <a href="post.php?id=<?php echo $post['id']; ?>"><?php echo htmlspecialchars(truncate_text($post['title'], 40)); ?></a>
                                <span class="badge badge-danger"><?php echo $post['dislike_count']; ?> <i class="fas fa-thumbs-down"></i></span>
                            </li>
                        <?php endwhile; ?>
                    </ul>
                <?php else: ?>
                    <p class="text-muted">No disliked posts to display yet or dislike button is not enabled.</p>
                <?php endif; ?>
            </div>
        </div>
    <?php endif; ?>

    <!-- Posts Section -->
    <?php if ($current_section == 'posts'): ?>
        <div class="dashboard-card">
            <div class="section-header">
                <h3><i class="fas fa-file-alt"></i> My Posts</h3>
                <a href="create_post.php" class="btn btn-primary">
                    <i class="fas fa-plus"></i> Create New Post
                </a>
            </div>
            
            <?php if ($user_posts_result->num_rows > 0): ?>
                <div class="admin-table">
                    <table>
                        <thead>
                            <tr>
                                <th>Title</th>
                                <th>Category</th>
                                <th>Status</th>
                                <th>Updated</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($post = $user_posts_result->fetch_assoc()): ?>
                                <tr>
                                    <td>
                                        <strong><?php echo htmlspecialchars($post['title']); ?></strong>
                                        <?php if ($post['is_featured']): ?>
                                            <span class="tag" style="background: #f39c12; color: white; margin-left: 0.5rem;">Featured</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo htmlspecialchars($post['category_name'] ?? 'Uncategorized'); ?></td>
                                    <td>
                                        <span class="tag <?php echo $post['status'] == 'published' ? 'tag-success' : 'tag-warning'; ?>">
                                            <?php echo ucfirst($post['status']); ?>
                                        </span>
                                    </td>
                                    <td><?php echo format_datetime($post['updated_at']); ?></td>
                                    <td class="action-buttons">
                                        <a href="post.php?id=<?php echo $post['id']; ?>" class="btn btn-sm btn-outline">View</a>
                                        <a href="edit_post.php?id=<?php echo $post['id']; ?>" class="btn btn-sm btn-secondary">Edit</a>
                                        <a href="delete_post.php?id=<?php echo $post['id']; ?>" class="btn btn-sm btn-danger" 
                                           onclick="return confirm('Are you sure you want to delete this post?')">Delete</a>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                <?php if ($total_pages > 1): ?>
                    <div class="pagination-container mt-4">
                        <div class="pagination-info">
                            Showing <?php echo ($offset + 1); ?> to <?php echo min($offset + $posts_per_page, $total_posts); ?> of <?php echo $total_posts; ?> posts
                        </div>
                        <div class="pagination">
                            <?php if ($current_page > 1): ?>
                                <a href="?section=posts&page=<?php echo ($current_page - 1); ?>" class="page-link">
                                    <i class="fas fa-chevron-left"></i> Previous
                                </a>
                            <?php endif; ?>
                            
                            <?php
                            $start_page = max(1, $current_page - 2);
                            $end_page = min($total_pages, $current_page + 2);
                            
                            if ($start_page > 1): ?>
                                <a href="?section=posts&page=1" class="page-link">1</a>
                                <?php if ($start_page > 2): ?>
                                    <span class="page-ellipsis">...</span>
                                <?php endif; ?>
                            <?php endif; ?>
                            
                            <?php for ($i = $start_page; $i <= $end_page; $i++): ?>
                                <a href="?section=posts&page=<?php echo $i; ?>" class="page-link <?php echo $i == $current_page ? 'active' : ''; ?>">
                                    <?php echo $i; ?>
                                </a>
                            <?php endfor; ?>
                            
                            <?php if ($end_page < $total_pages): ?>
                                <?php if ($end_page < $total_pages - 1): ?>
                                    <span class="page-ellipsis">...</span>
                                <?php endif; ?>
                                <a href="?section=posts&page=<?php echo $total_pages; ?>" class="page-link"><?php echo $total_pages; ?></a>
                            <?php endif; ?>
                            
                            <?php if ($current_page < $total_pages): ?>
                                <a href="?section=posts&page=<?php echo ($current_page + 1); ?>" class="page-link">
                                    Next <i class="fas fa-chevron-right"></i>
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endif; ?>
            <?php else: ?>
                <div class="text-center">
                    <p>You haven't created any posts yet.</p>
                    <a href="create_post.php" class="btn btn-primary">Create Your First Post</a>
                </div>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <!-- Analytics Section -->
    <?php if ($current_section == 'analytics'): ?>
        <div class="dashboard-grid mb-4">
            <div class="dashboard-card">
                <h3><i class="fas fa-chart-pie"></i> Your Post Status</h3>
                <canvas id="userPostStatusChart" style="max-height: 250px;"></canvas>
            </div>
            <div class="dashboard-card">
                <h3><i class="fas fa-chart-bar"></i> Your Comment Status</h3>
                <canvas id="userCommentStatusChart" style="max-height: 250px;"></canvas>
            </div>
            <div class="dashboard-card">
                <h3><i class="fas fa-chart-line"></i> Likes vs. Dislikes on Your Posts</h3>
                <canvas id="likesDislikesChart" style="max-height: 250px;"></canvas>
            </div>
        </div>

        <!-- Recent Comments -->
        <div class="dashboard-card">
            <h3><i class="fas fa-comments"></i> Recent Comments</h3>
            
            <?php if ($user_comments_result->num_rows > 0): ?>
                <div class="comments-list">
                    <?php while ($comment = $user_comments_result->fetch_assoc()): ?>
                        <div class="comment">
                            <div class="comment-header">
                                <span class="comment-post">On: <a href="post.php?id=<?php echo $comment['post_id']; ?>"><?php echo htmlspecialchars($comment['post_title']); ?></a></span>
                                <span class="comment-date"><?php echo format_datetime($comment['created_at']); ?></span>
                            </div>
                            <div class="comment-content">
                                <?php echo truncate_text(htmlspecialchars($comment['content']), 100); ?>
                            </div>
                            <div class="comment-status">
                                <span class="tag <?php echo $comment['status'] == 'approved' ? 'tag-success' : ($comment['status'] == 'pending' ? 'tag-warning' : 'tag-danger'); ?>">
                                    <?php echo ucfirst($comment['status']); ?>
                                </span>
                            </div>
                        </div>
                    <?php endwhile; ?>
                </div>
            <?php else: ?>
                <div class="text-center">
                    <p>You haven't made any comments yet.</p>
                </div>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <!-- Profile Section -->
    <?php if ($current_section == 'profile'): ?>
        <div class="dashboard-card">
            <h3><i class="fas fa-user"></i> Profile Information</h3>

            <?php if ($profile_error_message): ?>
                <div class="alert alert-error"><?php echo $profile_error_message; ?></div>
            <?php endif; ?>

            <?php if ($profile_success_message): ?>
                <div class="alert alert-success"><?php echo $profile_success_message; ?></div>
            <?php endif; ?>

            <?php if ($bio_error_message): ?>
                <div class="alert alert-error"><?php echo $bio_error_message; ?></div>
            <?php endif; ?>

            <?php if ($bio_success_message): ?>
                <div class="alert alert-success"><?php echo $bio_success_message; ?></div>
            <?php endif; ?>

            <div class="profile-header-area">
                <div class="profile-info">
                    <p><strong>Username:</strong> <?php echo htmlspecialchars($user['username']); ?></p>
                    <p><strong>Email:</strong> <?php echo htmlspecialchars($user['email']); ?></p>
                    <?php if (isset($user['gender'])): ?>
                        <p><strong>Gender:</strong> <?php echo htmlspecialchars(ucfirst($user['gender'])); ?></p>
                    <?php endif; ?>
                    <p><strong>Joined:</strong> <?php echo format_date($user['created_at']); ?></p>
                </div>
                <div class="profile-pic-container">
                    <img src="<?php echo $display_profile_image; ?>" alt="Profile Picture" class="profile-pic">
                    <button id="change-profile-pic-btn" class="btn btn-sm btn-icon-only" title="Change Profile Picture">
                        <i class="fas fa-camera"></i>
                    </button>
                </div>
            </div>
            <div class="mt-3">
                <h4>Bio</h4>
                <p><?php echo !empty($user['bio']) ? nl2br(htmlspecialchars($user['bio'])) : 'No bio yet. Click "Edit Profile" to add one.'; ?></p>
            </div>
            <div class="mt-3">
                <a href="edit_profile.php" class="btn btn-secondary btn-sm">Edit Profile</a>
                <a href="change_password.php" class="btn btn-outline btn-sm">Change Password</a>
            </div>
        </div>
    <?php endif; ?>

    <!-- Recommendations Section -->
    <?php if ($current_section == 'recommendations'): ?>
        <div class="dashboard-card">
            <h3><i class="fas fa-lightbulb"></i> Personalized Post Recommendations</h3>
            <p class="text-muted mb-3">Based on your likes, follows, and posting patterns</p>
            <?php if (!empty($suggested_posts)): ?>
                <div class="suggested-posts-grid">
                    <?php
                    $count = 0;
                    foreach ($suggested_posts as $post):
                        if ($count >= 6) break;
                        $image_src = '';
                        if (!empty($post['image_path'])) {
                            if (is_url_optimized($post['image_path'])) {
                                $image_src = htmlspecialchars($post['image_path']) . '?q=80&w=250&h=150&fit=crop&auto=webp';
                            } else {
                                $image_src = 'Uploads/' . htmlspecialchars(basename($post['image_path']));
                                if (!file_exists($image_src)) {
                                    $image_src = '../' . htmlspecialchars($post['image_path']);
                                }
                            }
                        }
                        
                        // Add relevance indicator based on score
                        $relevance_class = '';
                        $relevance_text = '';
                        if (isset($post['relevance_score'])) {
                            if ($post['relevance_score'] >= 3) {
                                $relevance_class = 'relevance-high';
                                $relevance_text = 'From followed user';
                            } elseif ($post['relevance_score'] >= 2) {
                                $relevance_class = 'relevance-medium';
                                $relevance_text = 'Matching category';
                            } elseif ($post['relevance_score'] >= 1) {
                                $relevance_class = 'relevance-low';
                                $relevance_text = 'Matching tag';
                            }
                        }
                    ?>
                        <article class="post-card-small">
                            <?php if ($image_src): ?>
                                <div class="post-image-small">
                                    <img src="<?php echo $image_src; ?>"
                                         alt="<?php echo htmlspecialchars($post['title']); ?>"
                                         loading="lazy"
                                         decoding="async"
                                         onerror="this.onerror=null;this.src='https://placehold.co/250x150/cccccc/333333?text=Image+Not+Found';">
                                </div>
                            <?php else: ?>
                                <div class="post-image-small no-image" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);">
                                    <i class="fas fa-image"></i>
                                </div>
                            <?php endif; ?>
                            <div class="post-content-small">
                                <h4 class="post-title-small"><a href="post.php?id=<?php echo $post['id']; ?>"><?php echo htmlspecialchars(truncate_text($post['title'], 50)); ?></a></h4>
                                <p class="post-meta-small">By <?php echo htmlspecialchars($post['username']); ?> on <?php echo format_date($post['published_at']); ?></p>
                                <?php if ($relevance_text): ?>
                                    <span class="relevance-badge <?php echo $relevance_class; ?>"><?php echo $relevance_text; ?></span>
                                <?php endif; ?>
                            </div>
                        </article>
                    <?php
                        $count++;
                    endforeach;
                    ?>
                </div>
            <?php else: ?>
                <p class="text-muted">No personalized recommendations found yet. Start liking posts, following users, and creating content to get better suggestions!</p>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</div>

<div id="profile-pic-modal" class="modal" style="display: none;">
    <div class="modal-content">
        <div class="modal-header">
            <h3>Change Profile Picture</h3>
            <button type="button" class="modal-close">&times;</button>
        </div>
        <div class="modal-body">
            <form method="POST" enctype="multipart/form-data" id="profile-pic-form">
                <input type="hidden" name="update_profile_pic" value="1">
                <input type="hidden" name="selected_avatar" id="selected-avatar-input" value="">

                <div class="modal-scroll-content" style="max-height: 400px; overflow-y: auto;">
                    <div class="form-group">
                        <label class="form-label">Choose an option:</label>
                        <div class="form-radio-group">
                            <div class="form-radio">
                                <input type="radio" id="option-avatar" name="prefers_avatar" value="1" <?php echo ($user['prefers_avatar'] || empty($user['profile_image_path'])) ? 'checked' : ''; ?>>
                                <label for="option-avatar">Use an Avatar</label>
                            </div>
                            <div class="form-radio">
                                <input type="radio" id="option-custom" name="prefers_avatar" value="0" <?php echo (!$user['prefers_avatar'] && !empty($user['profile_image_path'])) ? 'checked' : ''; ?>>
                                <label for="option-custom">Upload Custom Image</label>
                            </div>
                        </div>
                    </div>

                
                    <div id="avatar-selection-area" class="avatar-selection-grid" style="<?php echo ($user['prefers_avatar'] || empty($user['profile_image_path'])) ? 'display: grid;' : 'display: none;'; ?>">
                        <p class="text-muted" >Select one of our cool avatars:</p>
                        <div class="avatar-options">
                            <?php foreach ($available_avatars as $avatar_path): ?>
                                <img src="<?php echo htmlspecialchars($avatar_path); ?>"
                                    alt="<?php echo htmlspecialchars(basename($avatar_path, '.png')); ?>"
                                    class="avatar-option <?php echo ($user['profile_image_path'] == $avatar_path && $user['prefers_avatar']) ? 'selected' : ''; ?>"
                                    data-avatar-path="<?php echo htmlspecialchars($avatar_path); ?>">
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <div id="custom-upload-area" style="<?php echo (!$user['prefers_avatar'] && !empty($user['profile_image_path'])) ? 'display: block;' : 'display: none;'; ?>">
                        <div class="form-group">
                            <label for="custom_profile_image" class="form-label">Upload your image:</label>
                            <input type="file" id="custom_profile_image" name="custom_profile_image" class="form-input" accept="image/*">
                            <small class="text-muted">Max file size: 5MB. JPG, PNG, GIF.</small>
                            <div id="image-preview" style="margin-top: 10px;"></div>
                        </div>
                    </div>
                </div> <!-- END .modal-scroll-content -->

                <!-- This is the fixed footer for buttons, placed AFTER the scrollable content but WITHIN the form -->
                <div class="form-group mt-4 text-right modal-footer-buttons">
                    <button type="submit" class="btn btn-primary">Save Changes</button>
                    <button type="button" class="btn btn-secondary modal-close">Cancel</button>
                </div>
            </form>
        </div>
    </div>
</div>


<style>
/* Dashboard Specific Styles */
.dashboard-header {
    background: linear-gradient(135deg, #3498db 0%, #2c3e50 100%);
    color: white;
    padding: 2.5rem;
    border-radius: 12px;
    box-shadow: 0 4px 20px rgba(0,0,0,0.15);
    text-align: center;
    margin-bottom: 2rem;
}

/* Dashboard Navigation Tabs */
.dashboard-nav {
    display: flex;
    background: var(--background-white);
    border-radius: 12px;
    box-shadow: var(--shadow-light);
    overflow: hidden;
    margin-bottom: 2rem;
}

.nav-tab {
    flex: 1;
    padding: 1rem 1.5rem;
    text-decoration: none;
    color: var(--text-color);
    text-align: center;
    transition: all 0.3s ease;
    border-bottom: 3px solid transparent;
    font-weight: 500;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 0.5rem;
}

.nav-tab:hover {
    background-color: var(--background-light);
    color: var(--primary-color);
    text-decoration: none;
}

.nav-tab.active {
    background-color: var(--primary-color);
    color: white;
    border-bottom-color: var(--primary-color);
}

.nav-tab i {
    font-size: 1.1rem;
}

/* Section Header */
.section-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 1.5rem;
    flex-wrap: wrap;
    gap: 1rem;
}

.section-header h3 {
    margin: 0;
}

/* Quick Actions Grid */
.quick-actions-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 1rem;
}

/* Pagination Styles */
.pagination-container {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 1rem;
    padding: 1.5rem 0;
    border-top: 1px solid var(--border-color);
}

.pagination-info {
    color: var(--secondary-color);
    font-size: 0.9rem;
}

.pagination {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    flex-wrap: wrap;
    justify-content: center;
}

.page-link {
    display: inline-flex;
    align-items: center;
    gap: 0.25rem;
    padding: 0.5rem 0.75rem;
    border: 1px solid var(--border-color);
    border-radius: 6px;
    text-decoration: none;
    color: var(--text-color);
    background: var(--background-white);
    transition: all 0.2s ease;
    font-size: 0.9rem;
    min-width: 40px;
    justify-content: center;
}

.page-link:hover {
    background: var(--primary-color);
    color: white;
    border-color: var(--primary-color);
    text-decoration: none;
}

.page-link.active {
    background: var(--primary-color);
    color: white;
    border-color: var(--primary-color);
}

.page-ellipsis {
    padding: 0.5rem 0.25rem;
    color: var(--secondary-color);
}

.dashboard-header h1 {
    font-size: 2.5rem;
    margin-bottom: 0.5rem;
    color: white;
}

.dashboard-header p {
    font-size: 1.1rem;
    color: rgba(255, 255, 255, 0.8);
}

.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
    gap: 1.5rem;
    margin-bottom: 2rem;
}

.stat-card {
    background: var(--background-white);
    padding: 1.5rem;
    border-radius: 12px;
    box-shadow: var(--shadow-light);
    text-align: center;
    transition: transform 0.2s ease, box-shadow 0.2s ease;
}

.stat-card:hover {
    transform: translateY(-5px);
    box-shadow: var(--shadow-medium);
}

.stat-number {
    font-size: 2.5rem;
    font-weight: 700;
    color: var(--primary-color);
    margin-bottom: 0.5rem;
}

.stat-label {
    font-size: 1rem;
    color: var(--secondary-color);
    font-weight: 500;
}

.dashboard-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
    gap: 1.5rem;
}

.dashboard-card {
    background: var(--background-white);
    padding: 2rem;
    border-radius: 12px;
    box-shadow: var(--shadow-medium);
}

.dashboard-card h3 {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    color: var(--heading-color);
    margin-bottom: 1.5rem;
    font-size: 1.5rem;
}

.dashboard-card h3 .fas {
    color: var(--primary-color);
    font-size: 1.7rem;
}

.profile-info p {
    margin-bottom: 0.75rem;
    font-size: 1.05rem;
    color: var(--text-color);
}

.profile-info strong {
    color: var(--heading-color);
}

.quick-actions .btn {
    width: 100%;
    margin-bottom: 0.75rem;
    justify-content: flex-start;
}

.quick-actions .btn:last-child {
    margin-bottom: 0;
}

.admin-table {
    width: 100%;
    border-collapse: collapse;
    margin-top: 1rem;
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
    font-size: 0.9rem;
    text-transform: uppercase;
}

.admin-table tbody tr:hover {
    background-color: var(--background-light);
}

.admin-table .action-buttons .btn {
    margin-right: 8px;
    font-size: 0.85rem;
    padding: 6px 12px;
}

.comments-list .comment {
    border: 1px solid var(--border-color);
    border-radius: 8px;
    padding: 1rem;
    margin-bottom: 1rem;
    background-color: var(--background-white);
}

.comments-list .comment-header {
    display: flex;
    justify-content: space-between;
    font-size: 0.9rem;
    color: var(--secondary-color);
    margin-bottom: 0.5rem;
}

.comments-list .comment-post a {
    color: var(--primary-color);
    text-decoration: none;
    font-weight: 500;
}

.comments-list .comment-post a:hover {
    text-decoration: underline;
}

.comments-list .comment-content {
    margin-bottom: 0.75rem;
    color: var(--text-color);
}

.comments-list .comment-status .tag {
    font-size: 0.8rem;
    padding: 0.3rem 0.6rem;
    border-radius: 5px;
    font-weight: 600;
}

/* Specific Tag Styles for dashboard */
.tag {
    display: inline-block;
    padding: 0.25em 0.6em;
    font-size: 75%;
    font-weight: 700;
    line-height: 1;
    text-align: center;
    white-space: nowrap;
    vertical-align: baseline;
    border-radius: 0.25rem;
    margin-right: 0.25rem;
}

.tag-success {
    background: #27ae60;
    color: white;
}

.tag-warning {
    background: #f39c12;
    color: white;
}

.tag-danger {
    background: #e74c3c;
    color: white;
}

.btn-outline {
    background-color: transparent;
    color: var(--primary-color);
    border: 1px solid var(--primary-color);
}

.btn-outline:hover {
    background-color: var(--primary-color);
    color: white;
}

.btn-admin {
    background:rgb(211, 44, 2);
    color: #fff;
    border: none;
}

.btn-admin:hover, .btn-admin:focus {
    background: #2c3e50;
    color: #fff;
}

/* Suggested Posts Grid */
.suggested-posts-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
    gap: 1.5rem;
}

.post-card-small {
    background: var(--background-white);
    border-radius: 12px;
    box-shadow: var(--shadow-light);
    overflow: hidden;
    display: flex;
    flex-direction: column;
    transition: transform 0.2s ease, box-shadow 0.2s ease;
}

.post-card-small:hover {
    transform: translateY(-5px);
    box-shadow: var(--shadow-medium);
}

.post-image-small {
    width: 100%;
    height: 150px; /* Fixed height for consistency */
    overflow: hidden;
    display: flex;
    align-items: center;
    justify-content: center;
    background-color: #f0f0f0; /* Placeholder background */
}

.post-image-small img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.post-image-small.no-image {
    font-size: 3rem;
    color: white;
}

.post-content-small {
    padding: 1rem;
}

.post-title-small {
    font-size: 1.1rem;
    margin-bottom: 0.5rem;
    line-height: 1.3;
}

.post-title-small a {
    color: var(--heading-color);
    text-decoration: none;
    transition: color 0.2s ease;
}

.post-title-small a:hover {
    color: var(--primary-color);
}

.post-meta-small {
    font-size: 0.85rem;
    color: var(--secondary-color);
}

/* Relevance Badge Styles for Suggested Posts */
.relevance-badge {
    display: inline-block;
    padding: 0.2em 0.5em;
    border-radius: 0.25rem;
    font-size: 0.7rem;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    margin-top: 0.5rem;
}

.relevance-high {
    background-color: #e74c3c;
    color: white;
}

.relevance-medium {
    background-color: #f39c12;
    color: white;
}

.relevance-low {
    background-color: #95a5a6;
    color: white;
}

/* Simple Post List (for Most Liked/Disliked) */
.post-list-simple {
    list-style: none;
    padding: 0;
    margin: 0;
}

.post-list-simple li {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 0.75rem 0;
    border-bottom: 1px solid var(--border-color);
}

.post-list-simple li:last-child {
    border-bottom: none;
}

.post-list-simple li a {
    color: var(--text-color);
    text-decoration: none;
    font-weight: 500;
}

.post-list-simple li a:hover {
    color: var(--primary-color);
    text-decoration: underline;
}

.post-list-simple .badge {
    padding: 0.3em 0.6em;
    border-radius: 0.25rem;
    font-size: 0.8em;
    font-weight: bold;
    color: white;
}

.post-list-simple .badge-success {
    background-color: #28a745;
}

.post-list-simple .badge-danger {
    background-color: #dc3545;
}


/* New styles for profile picture and modal */
.profile-header-area {
    display: flex;
    align-items: center;
    gap: 1.5rem;
    margin-bottom: 1.5rem;
    flex-wrap: wrap; /* Allow wrapping on smaller screens */
}

.profile-pic-container {
    position: relative;
    width: 120px; /* Adjust size as needed */
    height: 120px; /* Adjust size as needed */
    border-radius: 50%;
    overflow: hidden;
    border: 3px solid #3498db; /* Blue border */
    box-shadow: 0 4px 8px rgba(0,0,0,0.1);
    flex-shrink: 0; /* Prevent shrinking */
}

.profile-pic {
    width: 100%;
    height: 100%;
    object-fit: cover;
    display: block;
}

.btn-icon-only {
    position: absolute;
    bottom: 5px;
    right: 5px;
    background-color: rgba(0,0,0,0.6);
    color: white;
    border: none;
    border-radius: 50%;
    width: 35px;
    height: 35px;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    font-size: 0.9rem;
    transition: background-color 0.3s ease;
}

.btn-icon-only:hover {
    background-color: rgba(0,0,0,0.8);
}

/* Modal styles (similar to create_post.php but adapted) */
/* Modal styles (similar to create_post.php but adapted) */
.modal {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0,0,0,0.6);
    z-index: 10000;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 1rem;
}

.modal-content {
    background: var(--background-white);
    border-radius: 12px;
    max-width: 600px;
    max-height: 90vh; /* Limits the overall height of the modal */
    width: 100%;
    box-shadow: 0 8px 16px rgba(0,0,0,0.2);
    animation: fadeIn 0.3s ease-out;

    /* Add flexbox for column layout to stack header, body, and footer */
    display: flex;
    flex-direction: column;
}

.modal-header {
    padding: 1.2rem 1.5rem;
    border-bottom: 1px solid #e9ecef;
    display: flex;
    justify-content: space-between;
    align-items: center;
    background-color: #f8f9fa;
    border-top-left-radius: 12px;
    border-top-right-radius: 12px;
    flex-shrink: 0; /* Prevent header from shrinking */
}

.modal-header h3 {
    margin: 0;
    color: #34495e;
}

.modal-close {
    background: none;
    border: none;
    font-size: 1.8rem;
    cursor: pointer;
    color: #6c757d;
    line-height: 1;
}

.modal-close:hover {
    color: #333;
}

.modal-body {
    padding: 1.5rem;
    /* Make modal-body a flex container for its children (scrollable content + footer buttons) */
    display: flex;
    flex-direction: column; /* Stack form content and buttons vertically */
    flex-grow: 1; /* Allow modal-body to grow and take available space */
    overflow: hidden; /* Prevent modal-body from scrolling itself, let the inner div scroll */
}

.modal-scroll-content {
    flex-grow: 1; /* Allows this div to take up available space, pushing footer down */
    overflow-y: auto; /* Enable vertical scrolling for this specific content */
    padding-right: 15px; /* Add some padding for the scrollbar, if visible */
    /* No need for max-height here if flex-grow handles it correctly within flex-direction: column */
}

/* Style for the fixed buttons container */
.modal-footer-buttons {
    padding-top: 1.5rem; /* Add padding to separate from scrolling content */
    border-top: 1px solid #e9ecef; /* Optional: Add a subtle separator */
    background-color: var(--background-white); /* Ensure background matches modal-content */
    z-index: 10; /* Ensure it stays on top of scrolled content */
    flex-shrink: 0; /* Prevent it from shrinking */
    padding-bottom: 0; /* Remove default bottom padding if using padding-top for spacing */
    margin-top: auto; /* This pushes the element to the bottom in a flex column */
}

/* Existing avatar and form styles */
.avatar-selection-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(100px, 1fr));
    gap: 1rem;
    margin-top: 1rem;
    justify-items: center;
}

.avatar-options {
    display: contents; /* Allows grid items inside to be direct children of .avatar-selection-grid */
}

.avatar-option {
    width: 100px;
    height: 100px;
    border-radius: 50%;
    object-fit: cover;
    border: 3px solid transparent;
    cursor: pointer;
    transition: all 0.2s ease-in-out;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}

.avatar-option:hover {
    border-color: #a0d9ff;
    transform: translateY(-3px);
    box-shadow: 0 6px 12px rgba(0,0,0,0.15);
}

.avatar-option.selected {
    border-color: #3498db;
    box-shadow: 0 0 0 4px rgba(52, 152, 219, 0.5);
}

.form-group {
    margin-bottom: 1rem;
}

.form-radio-group {
    display: flex;
    gap: 1.5rem;
    margin-bottom: 1rem;
}

.form-radio {
    display: flex;
    align-items: center;
}

.form-radio input[type="radio"] {
    margin-right: 0.5rem;
    transform: scale(1.2); /* Slightly larger radio buttons */
}

/* Animations */
@keyframes fadeIn {
    from { opacity: 0; transform: translateY(-20px); }
    to { opacity: 1; transform: translateY(0); }
}

/* Responsive adjustments */
@media (max-width: 768px) {
    .dashboard-grid {
        grid-template-columns: 1fr; /* Stack columns on small screens */
    }
    .profile-header-area {
        flex-direction: column;
        text-align: center;
    }
    .profile-info {
        margin-top: 1rem;
    }
    .profile-pic-container {
        margin: 0 auto; /* Center profile pic */
    }
}


/* Responsive Adjustments */
@media (max-width: 768px) {
    .dashboard-header h1 {
        font-size: 2rem;
    }

    .stats-grid, .dashboard-grid, .suggested-posts-grid {
        grid-template-columns: 1fr;
    }

    .dashboard-card {
        padding: 1.5rem;
    }

    .admin-table th, .admin-table td {
        padding: 10px;
    }

    /* Mobile Navigation Tabs */
    .dashboard-nav {
        flex-direction: column;
        border-radius: 8px;
    }

    .nav-tab {
        padding: 0.75rem 1rem;
        border-bottom: 1px solid var(--border-color);
        border-right: none;
    }

    .nav-tab:last-child {
        border-bottom: none;
    }

    .nav-tab.active {
        border-bottom-color: var(--primary-color);
    }

    /* Mobile Section Header */
    .section-header {
        flex-direction: column;
        align-items: stretch;
        text-align: center;
    }

    .section-header .btn {
        width: 100%;
    }

    /* Mobile Quick Actions */
    .quick-actions-grid {
        grid-template-columns: 1fr;
    }

    /* Mobile Pagination */
    .pagination {
        gap: 0.25rem;
    }

    .page-link {
        padding: 0.4rem 0.6rem;
        font-size: 0.8rem;
        min-width: 35px;
    }
}
</style>

<?php include 'includes/footer.php'; ?>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>


<script>
document.addEventListener('DOMContentLoaded', function() {
    // Profile Modal JavaScript - Only run if we're in the profile section
    const profilePicModal = document.getElementById('profile-pic-modal');
    const changeProfilePicBtn = document.getElementById('change-profile-pic-btn');
    
    if (profilePicModal && changeProfilePicBtn) {
        const modalCloseButtons = profilePicModal.querySelectorAll('.modal-close');
        const optionAvatar = document.getElementById('option-avatar');
        const optionCustom = document.getElementById('option-custom');
        const avatarSelectionArea = document.getElementById('avatar-selection-area');
        const customUploadArea = document.getElementById('custom-upload-area');
        const avatarOptions = profilePicModal.querySelectorAll('.avatar-option');
        const selectedAvatarInput = document.getElementById('selected-avatar-input');
        const customProfileImageInput = document.getElementById('custom_profile_image');
        const imagePreview = document.getElementById('image-preview');

        // Function to open the modal
        changeProfilePicBtn.addEventListener('click', function() {
            profilePicModal.style.display = 'flex';
            if (optionAvatar.checked) {
                avatarSelectionArea.style.display = 'grid';
                customUploadArea.style.display = 'none';
            } else {
                avatarSelectionArea.style.display = 'none';
                customUploadArea.style.display = 'block';
            }
        });

        // Close modal
        modalCloseButtons.forEach(button => {
            button.addEventListener('click', function() {
                profilePicModal.style.display = 'none';
                if (imagePreview) imagePreview.innerHTML = ''; // Clear preview on close
            });
        });

        profilePicModal.addEventListener('click', function(e) {
            if (e.target === profilePicModal) {
                profilePicModal.style.display = 'none';
                if (imagePreview) imagePreview.innerHTML = ''; // Clear preview on close
            }
        });

        // Toggle avatar/custom
        if (optionAvatar) {
            optionAvatar.addEventListener('change', function() {
                if (this.checked) {
                    avatarSelectionArea.style.display = 'grid';
                    customUploadArea.style.display = 'none';
                    if (customProfileImageInput) customProfileImageInput.value = '';
                    if (imagePreview) imagePreview.innerHTML = ''; // Clear preview
                }
            });
        }

        if (optionCustom) {
            optionCustom.addEventListener('change', function() {
                if (this.checked) {
                    avatarSelectionArea.style.display = 'none';
                    customUploadArea.style.display = 'block';
                    if (selectedAvatarInput) selectedAvatarInput.value = '';
                    avatarOptions.forEach(img => img.classList.remove('selected'));
                }
            });
        }

        // Handle avatar selection
        avatarOptions.forEach(img => {
            img.addEventListener('click', function() {
                avatarOptions.forEach(i => i.classList.remove('selected'));
                this.classList.add('selected');
                if (selectedAvatarInput) selectedAvatarInput.value = this.dataset.avatarPath;
                if (optionAvatar) optionAvatar.checked = true;
                avatarSelectionArea.style.display = 'grid';
                customUploadArea.style.display = 'none';
                if (customProfileImageInput) customProfileImageInput.value = '';
                if (imagePreview) imagePreview.innerHTML = ''; // Clear preview
            });
        });

        // Initialize selected avatar
        const currentProfilePath = "<?php echo $user['profile_image_path']; ?>";
        const prefersAvatar = <?php echo $user['prefers_avatar'] ? 'true' : 'false'; ?>;
        if (prefersAvatar && optionAvatar) {
            optionAvatar.checked = true;
            avatarOptions.forEach(img => {
                if (img.dataset.avatarPath === currentProfilePath) {
                    img.classList.add('selected');
                    if (selectedAvatarInput) selectedAvatarInput.value = currentProfilePath;
                }
            });
            avatarSelectionArea.style.display = 'grid';
            customUploadArea.style.display = 'none';
        } else if (optionCustom) {
            optionCustom.checked = true;
            avatarSelectionArea.style.display = 'none';
            customUploadArea.style.display = 'block';
        }

        // Handle custom image upload
        if (customProfileImageInput) {
            customProfileImageInput.addEventListener('change', function() {
                const file = this.files[0];
                if (file && imagePreview) {
                    const reader = new FileReader();
                    reader.onload = function(e) {
                        imagePreview.innerHTML = `<img src="${e.target.result}" alt="Profile Preview" style="max-width: 100%; border-radius: 8px;">`;
                        if (selectedAvatarInput) selectedAvatarInput.value = ''; // Clear avatar selection
                    };
                    reader.readAsDataURL(file);
                } else if (imagePreview) {
                    imagePreview.innerHTML = ''; // Clear preview if no file selected
                }
            });
        }
    }

    // --- Chart.js Initialization ---
    // Only initialize charts if we're in the analytics section and Chart.js is loaded
    if (typeof Chart !== 'undefined') {
        const userStats = <?php echo json_encode($user_stats); ?>;
        
        // Check if chart elements exist before initializing
        const userPostStatusChart = document.getElementById('userPostStatusChart');
        const userCommentStatusChart = document.getElementById('userCommentStatusChart');
        const likesDislikesChart = document.getElementById('likesDislikesChart');

        // --- User Post Status Chart (Pie Chart) ---
        if (userPostStatusChart) {
            const userPostStatusCtx = userPostStatusChart.getContext('2d');
            new Chart(userPostStatusCtx, {
                type: 'pie',
                data: {
                    labels: ['Published Posts', 'Draft Posts'],
                    datasets: [{
                        data: [userStats.user_published_posts, userStats.user_draft_posts],
                        backgroundColor: [
                            'rgba(46, 204, 113, 0.8)', // Green for published
                            'rgba(241, 196, 15, 0.8)'  // Yellow for draft
                        ],
                        borderColor: [
                            'rgba(46, 204, 113, 1)',
                            'rgba(241, 196, 15, 1)'
                        ],
                        borderWidth: 1
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'bottom',
                        },
                        title: {
                            display: false,
                            text: 'Your Post Status'
                        }
                    }
                }
            });
        }

        // --- User Comment Status Chart (Bar Chart) ---
        if (userCommentStatusChart) {
            const userCommentStatusCtx = userCommentStatusChart.getContext('2d');
            new Chart(userCommentStatusCtx, {
                type: 'bar',
                data: {
                    labels: ['Pending Comments', 'Approved Comments'],
                    datasets: [{
                        label: 'Number of Comments',
                        data: [userStats.user_pending_comments, userStats.user_approved_comments],
                        backgroundColor: [
                            'rgba(231, 76, 60, 0.8)',  // Red for pending
                            'rgba(39, 174, 96, 0.8)'   // Green for approved
                        ],
                        borderColor: [
                            'rgba(231, 76, 60, 1)',
                            'rgba(39, 174, 96, 1)'
                        ],
                        borderWidth: 1
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            display: false,
                        },
                        title: {
                            display: false,
                            text: 'Your Comment Status'
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                precision: 0 // Ensure integer ticks for count
                            }
                        }
                    }
                }
            });
        }

        // --- Likes vs. Dislikes on Your Posts Chart (Bar Chart) ---
        if (likesDislikesChart) {
            const likesDislikesCtx = likesDislikesChart.getContext('2d');
            new Chart(likesDislikesCtx, {
                type: 'bar',
                data: {
                    labels: ['Likes Received', 'Dislikes Received'],
                    datasets: [{
                        label: 'Reactions',
                        data: [userStats.user_received_likes, userStats.user_received_dislikes],
                        backgroundColor: [
                            'rgba(46, 204, 113, 0.8)', // Green for likes
                            'rgba(231, 76, 60, 0.8)'   // Red for dislikes
                        ],
                        borderColor: [
                            'rgba(46, 204, 113, 1)',
                            'rgba(231, 76, 60, 1)'
                        ],
                        borderWidth: 1
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            display: false,
                        },
                        title: {
                            display: false,
                            text: 'Likes vs. Dislikes on Your Posts'
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                precision: 0 // Ensure integer ticks for count
                            }
                        }
                    }
                }
            });
        }
    } else {
        console.error("Chart.js library not loaded. Charts will not function.");
    }
});
</script>

