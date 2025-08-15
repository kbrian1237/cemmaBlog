<?php
error_reporting(E_ALL); // Report all PHP errors
ini_set('display_errors', 1); // Display errors in the browser

// Ensure session is started if not already
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Include database connection and functions early, as they are needed for checks
require_once 'includes/db_connection.php';
require_once 'includes/functions.php';

// Get user ID from URL
$user_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

$user = null; // This will hold the fetched user data if it's a registered user
$is_guest_user = false; // Flag to determine if the current view is for a guest

if ($user_id <= 0) {
    // If user ID is 0 or less, it's definitively a guest user or an invalid ID.
    // Set the guest flag and an appropriate page title immediately.
    $is_guest_user = true;
    $page_title = "Guest User Profile";
} else {
    // Attempt to fetch user details for a registered user
    $user_query = "SELECT id, username, email, created_at, profile_image_path, prefers_avatar, bio, gender FROM users WHERE id = ?";
    $user_stmt = $conn->prepare($user_query);

    if ($user_stmt === false) {
        // If query preparation fails, log the error and treat as guest to avoid fatal errors.
        error_log("Failed to prepare user query in view_user.php: " . $conn->error);
        $is_guest_user = true;
        $page_title = "Error Loading Profile"; // Indicate a database error for the guest profile
    } else {
        $user_stmt->bind_param("i", $user_id);
        $user_stmt->execute();
        $user_result = $user_stmt->get_result();
        $user = $user_result->fetch_assoc(); // Attempt to fetch user data
        $user_stmt->close();

        if (!$user) {
            // If no user is found with the given ID, treat as guest or non-existent user.
            $is_guest_user = true;
            $page_title = "User Not Found";
        } else {
            // User found, set the page title to their username.
            $page_title = htmlspecialchars($user['username']) . "'s Profile";
        }
    }
}

// Now that we've determined if it's a guest or registered user, and fetched data if applicable,
// we can include the header. No more header() calls or output before this point.
include 'includes/header.php';

// If it's a guest user, display the guest message and exit PHP execution here.
// The rest of the script (fetching posts, etc.) is only for registered users.
if ($is_guest_user):
?>
    <div class="user-profile-container" >
        <div class="container">
            <div class="text-center py-5">
                <i class="fas fa-user-secret fa-5x text-muted mb-3"></i>
                <h2 class="text-heading">This is a Guest User Profile</h2>
                <p class="text-secondary">
                    This user is not a registered member of our community. 
                    Guest users do not have public profiles or associated posts.
                </p>
                <p class="text-secondary">
                    If you'd like to share your thoughts, consider <a href="register.php" class="text-primary-link">joining us</a>!
                </p>
                <a href="index.php" class="btn btn-primary mt-3"><i class="fas fa-home"></i> Back to Home</a>
            </div>
        </div>
    </div>
<?php
    include 'includes/footer.php';
    exit(); // IMPORTANT: Exit here to prevent further execution for guest users
endif;

// --- From this point onwards, we are guaranteed to have a valid $user object for a registered user ---
$profile_user = $user; // Rename $user to $profile_user for clarity in the rest of the script

$available_avatars_global_for_display = get_available_avatars('avatars/');

// Check if the database connection is valid
if (!isset($conn) || $conn->connect_error) {
    $error_message = "Database connection failed: " . ($conn->connect_error ?? "Connection object not set.");
    error_log($error_message); // Log to server error log
    exit("Database connection failed. Please try again later. Error: " . ($conn->connect_error ?? "Unknown connection error.")); // Display to user
}

// Get user ID from URL
$user_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

$user = null;
$is_guest_user = false;

if ($user_id <= 0) {
    // If user ID is 0 or less, it's likely a guest user or invalid ID
    $is_guest_user = true;
    $page_title = "Guest User Profile"; // Set a more appropriate title
} else {
    // Fetch user details for a registered user
    $user_query = "SELECT id, username, email, created_at, profile_image_path, prefers_avatar, bio, gender FROM users WHERE id = ?";
    $user_stmt = $conn->prepare($user_query);
    if ($user_stmt === false) {
        error_log("Failed to prepare user query: " . $conn->error);
        // Fallback to guest message if query fails
        $is_guest_user = true;
        $page_title = "Guest User Profile (DB Error)";
    } else {
        $user_stmt->bind_param("i", $user_id);
        $user_stmt->execute();
        $user_result = $user_stmt->get_result();
        $user = $user_result->fetch_assoc();
        $user_stmt->close();

        if (!$user) {
            // User not found in database, treat as guest or non-existent
            $is_guest_user = true;
            $page_title = "User Not Found";
        } else {
            $page_title = htmlspecialchars($user['username']) . "'s Profile";
        }
    }
}

if ($user_id <= 0) {
    // Redirect to index if no valid user ID is provided
    header("Location: index.php");
    exit();
}

// Fetch user details
$user_query = "SELECT id, username, email, created_at, profile_image_path, prefers_avatar, bio, gender FROM users WHERE id = ?";
$user_stmt = $conn->prepare($user_query);
if ($user_stmt === false) {
    $error_message = "Failed to prepare user query: " . $conn->error;
    error_log($error_message);
    exit("Error fetching user profile. Please try again later. Details: " . $conn->error);
}
$user_stmt->bind_param("i", $user_id);
$user_stmt->execute();
$user_result = $user_stmt->get_result();
$profile_user = $user_result->fetch_assoc();
$user_stmt->close(); // Close the statement after use

if (!$profile_user) {
    // Redirect if user not found
    header("Location: index.php");
    exit();
}

$page_title = htmlspecialchars($profile_user['username']) . "'s Profile";

// --- Pagination for All Posts ---
$posts_per_page = 6;
$page_all_posts = isset($_GET['page_all']) ? (int)$_GET['page_all'] : 1;
$offset_all_posts = ($page_all_posts - 1) * $posts_per_page;

// Get total count of all posts by this user
$total_all_posts_query = "SELECT COUNT(*) as total FROM posts WHERE user_id = ? AND status = 'published'";
$total_all_posts_stmt = $conn->prepare($total_all_posts_query);
if ($total_all_posts_stmt === false) {
    error_log("Failed to prepare total all posts query: " . $conn->error);
    exit("Error counting user posts. Please try again later. Details: " . $conn->error);
}
$total_all_posts_stmt->bind_param("i", $user_id);
$total_all_posts_stmt->execute();
$total_all_posts = $total_all_posts_stmt->get_result()->fetch_assoc()['total'];
$total_pages_all_posts = ceil($total_all_posts / $posts_per_page);
$total_all_posts_stmt->close();

// Get all posts by this user, including comment count
$all_posts_query = "SELECT p.*, u.username, c.name as category_name, u.profile_image_path, u.prefers_avatar, u.gender,
                           (SELECT COUNT(*) FROM comments WHERE post_id = p.id AND status = 'approved') as comment_count
                    FROM posts p
                    LEFT JOIN users u ON p.user_id = u.id
                    LEFT JOIN categories c ON p.category_id = c.id
                    WHERE p.user_id = ? AND p.status = 'published'
                    ORDER BY p.published_at DESC
                    LIMIT ? OFFSET ?";
$all_posts_stmt = $conn->prepare($all_posts_query);
if ($all_posts_stmt === false) {
    error_log("Failed to prepare all posts query: " . $conn->error);
    exit("Error fetching user posts. Please try again later. Details: " . $conn->error);
}
$all_posts_stmt->bind_param("iii", $user_id, $posts_per_page, $offset_all_posts);
$all_posts_stmt->execute();
$all_posts_result = $all_posts_stmt->get_result();
$current_user_id = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : null;
$is_admin = isset($_SESSION['role']) && $_SESSION['role'] === 'admin';
// Statement for all posts will be closed after fetching all data for like/dislike status

// --- Pagination for Top Liked Posts ---
$top_liked_posts_per_page = 3; // Fewer posts for top liked section
$page_top_liked = isset($_GET['page_liked']) ? (int)$_GET['page_liked'] : 1;
$offset_top_liked = ($page_top_liked - 1) * $top_liked_posts_per_page;

// Get total count of top liked posts by this user
// Note: This query counts distinct posts that have at least one like.
// If a user has posts but none are liked, this count will be 0.
$total_top_liked_posts_query = "SELECT COUNT(DISTINCT p.id) as total
                                FROM posts p
                                JOIN likes l ON p.id = l.post_id
                                WHERE p.user_id = ? AND p.status = 'published'";
$total_top_liked_posts_stmt = $conn->prepare($total_top_liked_posts_query);
if ($total_top_liked_posts_stmt === false) {
    error_log("Failed to prepare total top liked posts query: " . $conn->error);
    exit("Error counting top liked posts. Please try again later. Details: " . $conn->error);
}
$total_top_liked_posts_stmt->bind_param("i", $user_id);
$total_top_liked_posts_stmt->execute();
$total_top_liked_posts_row = $total_top_liked_posts_stmt->get_result()->fetch_assoc();
$total_top_liked_posts = $total_top_liked_posts_row['total'];
$total_pages_top_liked = ceil($total_top_liked_posts / $top_liked_posts_per_page);
$total_top_liked_posts_stmt->close();


// Get top liked posts by this user, including comment count
$top_liked_posts_query = "SELECT p.*, u.username, c.name as category_name, u.profile_image_path, u.prefers_avatar, u.gender,
                          COUNT(l.id) as like_count,
                          (SELECT COUNT(*) FROM comments WHERE post_id = p.id AND status = 'approved') as comment_count
                          FROM posts p
                          LEFT JOIN users u ON p.user_id = u.id
                          LEFT JOIN categories c ON p.category_id = c.id
                          LEFT JOIN likes l ON p.id = l.post_id
                          WHERE p.user_id = ? AND p.status = 'published'
                          GROUP BY p.id
                          ORDER BY like_count DESC, p.published_at DESC
                          LIMIT ? OFFSET ?";
$top_liked_posts_stmt = $conn->prepare($top_liked_posts_query);
if ($top_liked_posts_stmt === false) {
    error_log("Failed to prepare top liked posts query: " . $conn->error);
    exit("Error fetching top liked posts. Please try again later. Details: " . $conn->error);
}
$top_liked_posts_stmt->bind_param("iii", $user_id, $top_liked_posts_per_page, $offset_top_liked);
$top_liked_posts_stmt->execute();
$top_liked_posts_result = $top_liked_posts_stmt->get_result();
// Statement for top liked posts will be closed after fetching all data for like/dislike status

// Get current user's liked and disliked posts for this page
$current_user_id = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : 0;
$user_liked_posts = [];
$user_disliked_posts = [];
if ($current_user_id > 0) {
    // Get all post IDs from both results to fetch like/dislike status efficiently
    $post_ids_on_page = [];
    if ($all_posts_result) {
        $all_posts_result->data_seek(0); // Rewind to start
        while ($row = $all_posts_result->fetch_assoc()) {
            $post_ids_on_page[] = $row['id'];
        }
    }
    if ($top_liked_posts_result) {
        $top_liked_posts_result->data_seek(0); // Rewind to start
        while ($row = $top_liked_posts_result->fetch_assoc()) {
            $post_ids_on_page[] = $row['id'];
        }
    }
    $post_ids_on_page = array_unique($post_ids_on_page); // Remove duplicates

    if (!empty($post_ids_on_page)) {
        $ids_placeholder = implode(',', array_fill(0, count($post_ids_on_page), '?'));

        $liked_query = "SELECT post_id FROM likes WHERE user_id = ? AND post_id IN ($ids_placeholder)";
        $liked_stmt = $conn->prepare($liked_query);
        if ($liked_stmt === false) {
            error_log("Failed to prepare liked query: " . $conn->error);
            exit("Error fetching like status. Please try again later. Details: " . $conn->error);
        }
        $types = 'i' . str_repeat('i', count($post_ids_on_page));
        $bind_params = array_merge([$current_user_id], $post_ids_on_page);
        $liked_stmt->bind_param($types, ...$bind_params);
        $liked_stmt->execute();
        $liked_result = $liked_stmt->get_result();
        while ($row = $liked_result->fetch_assoc()) {
            $user_liked_posts[$row['post_id']] = true;
        }
        $liked_stmt->close();

        $disliked_query = "SELECT post_id FROM dislikes WHERE user_id = ? AND post_id IN ($ids_placeholder)";
        $disliked_stmt = $conn->prepare($disliked_query);
        if ($disliked_stmt === false) {
            error_log("Failed to prepare disliked query: " . $conn->error);
            exit("Error fetching dislike status. Please try again later. Details: " . $conn->error);
        }
        $disliked_stmt->bind_param($types, ...$bind_params);
        $disliked_stmt->execute();
        $disliked_result = $disliked_stmt->get_result();
        while ($row = $disliked_result->fetch_assoc()) {
            $user_disliked_posts[$row['post_id']] = true;
        }
        $disliked_stmt->close();
    }
    // Rewind results for display loop
    if ($all_posts_result) $all_posts_result->data_seek(0);
    if ($top_liked_posts_result) $top_liked_posts_result->data_seek(0);
}

// Helper function to determine if a path is a URL and optimize it
if (!function_exists('is_url_optimized')) {
    function is_url_optimized($path) {
        return filter_var($path, FILTER_VALIDATE_URL);
    }
}

// Helper function to check if user can edit a post

if (!function_exists('can_edit_post')) {
    function can_edit_post($post_user_id, $current_user_id, $is_admin) {
        return $post_user_id === $current_user_id || $is_admin;
    }
}

?>

<style>
    /* Custom styles for view_user.php */
    body {
        background-color: var(--background-light);
    }

    .user-profile-container {
        background-image: url('assets/images/herobg.png'); /* Use the hero background image */
        background-size: cover;
        background-position: center;
        background-repeat: no-repeat;
        padding: var(--spacing-xl) 0;
        margin-bottom: var(--spacing-xl);
        border-radius: var(--border-radius-lg);
        overflow: hidden;
        color: white; /* Ensure text is readable on the background */
    }

    .user-profile-header {
        text-align: center;
        padding: var(--spacing-lg);
        background: rgba(0, 0, 0, 0.4); /* Semi-transparent overlay for readability */
        border-radius: var(--border-radius-lg);
        margin: 0 auto;
        max-width: 800px;
        display: flex; /* Use flexbox for overall header layout */
        flex-direction: column; /* Stack elements vertically */
        align-items: center; /* Center items horizontally */
    }

    .profile-top-row {
        display: flex;
        align-items: center;
        justify-content: center; /* Center the avatar and counts */
        gap: var(--spacing-lg); /* Space between avatar and counts */
        margin-bottom: var(--spacing-md); /* Space below this row */
        width: 100%; /* Take full width to center content */
    }

    .profile-avatar-wrapper {
        width: 150px;
        height: 150px;
        border-radius: 50%;
        overflow: hidden;
        border: 5px solid rgba(255, 255, 255, 0.8);
        box-shadow: 0 0 15px rgba(0, 0, 0, 0.3);
        flex-shrink: 0; /* Prevent shrinking */
    }

    .profile-avatar {
        width: 100%;
        height: 100%;
        object-fit: cover;
    }

    .profile-username {
        font-size: var(--font-size-xxl);
        font-weight: 700;
        margin-bottom: var(--spacing-xs);
        color: white;
    }

    .profile-bio {
        font-size: var(--font-size-md);
        color: #e0e0e0;
        margin-bottom: var(--spacing-md);
        max-width: 600px;
        margin-left: auto;
        margin-right: auto;
        text-align: center; /* Center the bio text */
    }

    .profile-stats {
        display: flex;
        justify-content: center;
        gap: var(--spacing-lg);
        margin-top: var(--spacing-md);
        flex-wrap: wrap; /* Allow stats to wrap on smaller screens */
    }

    .profile-stat-item {
        text-align: center;
        font-size: var(--font-size-base);
        color: #f0f0f0;
        min-width: 80px; /* Ensure stats have enough space */
    }

    .profile-stat-number {
        font-size: var(--font-size-xl);
        font-weight: 600;
        color: var(--primary-color);
        margin-bottom: 5px;
    }

    .posts-section {
        margin-top: var(--spacing-xl);
        padding: var(--spacing-lg) 0;
    }

    .section-title {
        font-size: var(--font-size-xxl);
        font-weight: 700;
        color: var(--heading-color);
        text-align: center;
        margin-bottom: var(--spacing-xl);
        position: relative;
    }

    .section-title::after {
        content: '';
        display: block;
        width: 80px;
        height: 4px;
        background: var(--primary-color);
        margin: var(--spacing-sm) auto 0;
        border-radius: 2px;
    }

    .posts-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
        gap: var(--spacing-lg);
        margin-bottom: var(--spacing-xl);
    }

    .post-card {
        border-radius: var(--border-radius-lg);
        overflow: hidden;
        box-shadow: var(--shadow-medium);
        transition: transform 0.3s ease, box-shadow 0.3s ease;
    }

    .post-card:hover {
        transform: translateY(-5px);
        box-shadow: var(--shadow-hover);
    }

    .post-image {
        width: 100%;
        height: 200px;
        object-fit: cover;
    }

    .post-content {
        padding: var(--spacing-md);
    }

    .post-meta {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        gap: var(--spacing-sm);
        margin-bottom: var(--spacing-sm);
        font-size: var(--font-size-sm);
        color: var(--secondary-color);
    }

    .post-category {
        background: var(--primary-color);
        color: white;
        padding: 0.25rem var(--spacing-xs);
        border-radius: var(--border-radius-sm);
        font-size: var(--font-size-sm);
    }

    .post-title {
        font-size: var(--font-size-xl);
        font-weight: 600;
        margin-bottom: var(--spacing-xs);
        color: var(--heading-color);
    }

    .post-title a {
        color: inherit;
        transition: color 0.3s ease;
    }

    .post-title a:hover {
        color: var(--primary-color);
    }

    .post-excerpt {
        color: var(--secondary-color);
        margin-bottom: var(--spacing-sm);
        line-height: 1.6;
    }

    .post-tags {
        display: flex;
        flex-wrap: wrap;
        gap: var(--spacing-xs);
        margin-bottom: var(--spacing-sm);
    }

    .tag {
        background: var(--background-light);
        color: var(--secondary-color);
        padding: 0.25rem var(--spacing-xs);
        border-radius: var(--border-radius-sm);
        font-size: var(--font-size-sm);
        border: 1px solid var(--border-color);
    }

    .tag:hover {
        background: var(--border-color);
    }

    .post-actions {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-top: 1rem;
        padding-top: 1rem;
        border-top: 1px solid var(--border-color);
    }
    .post-actions .comment-count {
        font-size: var(--font-size-sm);
        color: var(--secondary-color);
    }
    .likes-dislikes {
        display: flex;
        gap: 0.5rem;
    }

    .likes-dislikes button {
        background-color: var(--background-light);
        border: 1px solid var(--border-color);
        border-radius: 8px;
        padding: 0.5rem 0.8rem;
        font-size: 0.9rem;
        cursor: pointer;
        display: flex;
        align-items: center;
        gap: 0.3rem;
        transition: all 0.2s ease;
        color: var(--text-color);
    }

    .likes-dislikes button:hover {
        border-color: var(--primary-color);
        box-shadow: 0 2px 5px rgba(0,0,0,0.1);
    }

    .likes-dislikes .like-btn.active {
        background-color: #28a745;
        color: white;
        border-color: #28a745;
    }

    .likes-dislikes .dislike-btn.active {
        background-color: #dc3545;
        color: white;
        border-color: #dc3545;
    }

    .likes-dislikes .like-btn.active:hover {
        background-color: #218838;
        border-color: #218838;
    }

    .likes-dislikes .dislike-btn.active:hover {
        background-color: #c82333;
        border-color: #c82333;
    }

    /* Pagination */
    .pagination {
        display: flex;
        justify-content: center;
        flex-wrap: wrap;
        gap: var(--spacing-xs);
        margin: var(--spacing-xl) 0;
    }

    .pagination a,
    .pagination span {
        padding: 0.6rem var(--spacing-sm);
        border: 1px solid var(--border-color);
        border-radius: var(--border-radius-md);
        color: var(--primary-color);
        transition: all 0.3s ease;
        font-size: var(--font-size-base);
        min-width: 44px;
        text-align: center;
    }

    .pagination a:hover {
        background: var(--primary-color);
        color: white;
    }

    .pagination .current {
        background: var(--primary-color);
        color: white;
        border-color: var(--primary-color);
        pointer-events: none;
    }

    /* New style for comment count link */
    .comment-count-link {
        display: inline-flex;
        align-items: center;
        gap: 0.3rem;
        color: var(--secondary-color);
        font-size: 0.9rem;
        text-decoration: none;
        transition: color 0.2s ease;
    }

    .comment-count-link:hover {
        color: var(--primary-color);
    }

    .comment-count-link i {
        font-size: 1rem;
    }

    @media (min-width: 768px) {
        .user-profile-header {
            padding: var(--spacing-xl);
        }
        .posts-grid {
            grid-template-columns: repeat(2, 1fr);
        }
    }

    @media (min-width: 992px) {
        .posts-grid {
            grid-template-columns: repeat(3, 1fr);
        }
    }
</style>

<div class="user-profile-container" >
    <div class="container">
        <?php if ($is_guest_user): ?>
                <div class="text-center py-5">
                    <i class="fas fa-user-secret fa-5x text-muted mb-3"></i>
                    <h2 class="text-heading">This is a Guest User Profile</h2>
                    <p class="text-secondary">
                        This user is not a registered member of our community. 
                        Guest users do not have public profiles or associated posts.
                    </p>
                    <p class="text-secondary">
                        If you'd like to share your thoughts, consider <a href="register.php" class="text-primary-link">joining us</a>!
                    </p>
                    <a href="index.php" class="btn btn-primary mt-3"><i class="fas fa-home"></i> Back to Home</a>
                </div>
            <?php else: ?>
                <!-- Profile Header -->
        <div class="user-profile-header">
            <?php
            $profile_image_path_display = 'avatars/default_avatar.png'; // Default fallback

            if ($profile_user['prefers_avatar']) {
                // User prefers an avatar
                if (isset($profile_user['gender']) && $profile_user['gender'] == 'male') {
                    $profile_image_path_display = 'avatars/male_avatar.png';
                } elseif (isset($profile_user['gender']) && $profile_user['gender'] == 'female') {
                    $profile_image_path_display = 'avatars/female_avatar.png';
                } else {
                    $profile_image_path_display = 'avatars/default_avatar.png';
                }
                // If a specific avatar path is stored and it's one of the available ones, use it
                if (!empty($profile_user['profile_image_path']) && in_array($profile_user['profile_image_path'], $available_avatars_global_for_display)) {
                    $profile_image_path_display = htmlspecialchars($profile_user['profile_image_path']);
                }
            } elseif (!empty($profile_user['profile_image_path'])) {
                // User prefers custom image and a path exists
                $custom_path = htmlspecialchars($profile_user['profile_image_path']);
                // Check if the custom path exists, if not, fallback
                if (file_exists($custom_path)) {
                    $profile_image_path_display = $custom_path;
                } elseif (file_exists('../' . $custom_path)) { // Try one level up
                    $profile_image_path_display = '../' . $custom_path;
                } else {
                    $profile_image_path_display = 'avatars/default_avatar.png'; // Fallback if custom image not found
                }
            }
            // If after all checks, it's still empty or invalid, it remains the default.
            ?>
            <h1 class="profile-username"><?php echo htmlspecialchars($profile_user['username']); ?></h1>

            <div class="profile-top-row">
                <!-- Followers count (left) -->
                <div class="profile-stat-item profile-followers">
                    <div class="profile-stat-number" id="follower-count-<?php echo $profile_user['id']; ?>">
                        <?php echo get_follower_count($conn, $profile_user['id']); ?>
                    </div>
                    <div class="profile-stat-label">Followers</div>
                </div>

                <!-- Profile Avatar -->
                <div class="profile-avatar-wrapper">
                    <img src="<?php echo $profile_image_path_display; ?>" alt="<?php echo htmlspecialchars($profile_user['username']); ?>'s Profile Picture" class="profile-avatar" loading="lazy">
                </div>

                <!-- Following count (right) -->
                <div class="profile-stat-item profile-following">
                    <div class="profile-stat-number">
                        <?php echo get_following_count($conn, $profile_user['id']); ?>
                    </div>
                    <div class="profile-stat-label">Following</div>
                </div>
            </div>

            <?php
            // Check if the current logged-in user is viewing their own profile
            $is_own_profile = is_logged_in() && ($_SESSION['user_id'] == $profile_user['id']);
            $is_currently_following = is_logged_in() ? is_following($conn, $_SESSION['user_id'], $profile_user['id']) : false;
            ?>

            <?php if (!$is_own_profile): // Only show follow button if not viewing own profile ?>
                <div class="mt-4 profile-follow-button">
                    <button
                        class="btn <?php echo $is_currently_following ? 'btn-secondary' : 'btn-primary'; ?> follow-btn"
                        data-user-id="<?php echo htmlspecialchars($profile_user['id']); ?>"
                        data-action="<?php echo $is_currently_following ? 'unfollow' : 'follow'; ?>"
                        <?php echo is_logged_in() ? '' : 'disabled title="Login to follow"'; ?>
                    >
                        <?php echo $is_currently_following ? 'Unfollow' : 'Follow'; ?>
                    </button>
                </div>
            <?php endif; ?>

            <?php if (!empty($profile_user['bio'])): ?>
                <p class="profile-bio"><?php echo nl2br(htmlspecialchars($profile_user['bio'])); ?></p>
            <?php endif; ?>

            <!-- Original profile stats (Posts, Likes, Dislikes) -->
            <div class="profile-stats profile-stats-bottom-row">
                <div class="profile-stat-item">
                    <div class="stat-number"><?php echo $total_all_posts; ?></div>
                    <div class="stat-label">Posts</div>
                </div>
                <div class="profile-stat-item">
                    <div class="stat-number">
                        <?php
                        // Calculate total likes on all user's posts
                        $total_likes_query = "SELECT SUM(total_likes) as total FROM (SELECT COUNT(*) as total_likes FROM likes JOIN posts ON likes.post_id = posts.id WHERE posts.user_id = ? GROUP BY posts.id) as subquery";
                        $total_likes_stmt = $conn->prepare($total_likes_query);
                        if ($total_likes_stmt === false) {
                            error_log("Failed to prepare total likes query: " . $conn->error);
                            $total_likes_count = 0;
                        } else {
                            $total_likes_stmt->bind_param("i", $user_id);
                            $total_likes_stmt->execute();
                            $total_likes_result = $total_likes_stmt->get_result()->fetch_assoc();
                            $total_likes_count = $total_likes_result['total'] ?? 0;
                            $total_likes_stmt->close();
                        }
                        echo $total_likes_count;
                        ?>
                    </div>
                    <div class="stat-label">Total Likes</div>
                </div>
                <div class="profile-stat-item">
                    <div class="stat-number">
                        <?php
                        // Calculate total dislikes on all user's posts
                        $total_dislikes_query = "SELECT SUM(total_dislikes) as total FROM (SELECT COUNT(*) as total_dislikes FROM dislikes JOIN posts ON dislikes.post_id = posts.id WHERE posts.user_id = ? GROUP BY posts.id) as subquery";
                        $total_dislikes_stmt = $conn->prepare($total_dislikes_query);
                        if ($total_dislikes_stmt === false) {
                            error_log("Failed to prepare total dislikes query: " . $conn->error);
                            $total_dislikes_count = 0;
                        } else {
                            $total_dislikes_stmt->bind_param("i", $user_id);
                            $total_dislikes_stmt->execute();
                            $total_dislikes_result = $total_dislikes_stmt->get_result()->fetch_assoc();
                            $total_dislikes_count = $total_dislikes_result['total'] ?? 0;
                            $total_dislikes_stmt->close();
                        }
                        echo $total_dislikes_count;
                        ?>
                    </div>
                    <div class="stat-label">Total Dislikes</div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="container posts-section">
    <!-- Top Liked Posts Section -->
    <h2 class="section-title">Top Liked Posts</h2>
    <?php if ($top_liked_posts_result->num_rows > 0): ?>
        <div class="posts-grid">
            <?php while ($post = $top_liked_posts_result->fetch_assoc()): ?>
                <article class="post-card">
                    <?php
                    $image_src = '';
                    if (!empty($post['image_path'])) {
                        if (is_url_optimized($post['image_path'])) {
                            $image_src = htmlspecialchars($post['image_path']) . '?q=80&w=400&h=250&fit=crop';
                        } else {
                            $image_src = 'uploads/' . htmlspecialchars(basename($post['image_path']));
                            if (!file_exists($image_src)) {
                                $image_src = '../' . htmlspecialchars($post['image_path']);
                            }
                        }
                    }
                    ?>
                    <?php if ($image_src): ?>
                        <a href="post.php?id=<?php echo $post['id']; ?>"><div class="post-image">
                            <img src="<?php echo $image_src; ?>"
                                 alt="<?php echo htmlspecialchars($post['title']); ?>"
                                 class="post-image"
                                 loading="lazy"
                                 onerror="this.onerror=null;this.src='https://placehold.co/400x250/cccccc/333333?text=Image+Not+Found';">
                        </div></a>
                    <?php else: ?>
                        <div class="post-image no-image" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); display: flex; align-items: center; justify-content: center; color: white; font-size: 1.2rem;">
                            <i class="fas fa-image"></i>
                        </div>
                    <?php endif; ?>

                    <div class="post-content">
                        <div class="post-meta">
                            <a href="category.php?id=<?php echo $post['category_id']; ?>"><span class="post-category"><?php echo htmlspecialchars($post['category_name'] ?? 'Uncategorized'); ?></span></a>
                            <span>
                                    <a href="view_user.php?id=<?php echo $post['user_id']; ?>" class="post-author-avatar">
                                            <?php
                                            $author_profile_image = !empty($post['profile_image_path']) ? htmlspecialchars($post['profile_image_path']) : '';
                                            $author_prefers_avatar = isset($post['prefers_avatar']) ? (bool)$post['prefers_avatar'] : false;

                                            $author_profile_image_display = '';

                                            if ($author_prefers_avatar) {
                                                if (isset($post['gender']) && $post['gender'] == 'male') {
                                                    $author_profile_image_display = 'avatars/male_avatar.png';
                                                } elseif (isset($post['gender']) && $post['gender'] == 'female') {
                                                    $author_profile_image_display = 'avatars/female_avatar.png';
                                                } else {
                                                    $author_profile_image_display = 'avatars/default_avatar.png';
                                                }
                                                if (!empty($author_profile_image) && in_array($author_profile_image, $available_avatars_global_for_display)) {
                                                    $author_profile_image_display = $author_profile_image;
                                                }
                                            } elseif (!empty($author_profile_image)) {
                                                $custom_author_path = htmlspecialchars($author_profile_image);
                                                if (file_exists($custom_author_path)) {
                                                    $author_profile_image_display = $custom_author_path;
                                                } elseif (file_exists('../' . $custom_author_path)) {
                                                    $author_profile_image_display = '../' . $custom_author_path;
                                                } else {
                                                    $author_profile_image_display = 'avatars/default_avatar.png';
                                                }
                                            } else {
                                                if (isset($post['gender']) && $post['gender'] == 'male') {
                                                    $author_profile_image_display = 'avatars/male_avatar.png';
                                                } elseif (isset($post['gender']) && $post['gender'] == 'female') {
                                                    $author_profile_image_display = 'avatars/female_avatar.png';
                                                } else {
                                                    $author_profile_image_display = 'avatars/default_avatar.png';
                                                }
                                            }
                                            ?>
                                            <img src="<?php echo $author_profile_image_display; ?>" alt="<?php echo htmlspecialchars($post['username']); ?>'s Profile" class="avatar-small">
                                        </a>
                                    <a href="view_user.php?id=<?php echo $post['user_id']; ?>"><?php echo htmlspecialchars($post['username']); ?></a>
                                </span>
                            <span><i class="fas fa-calendar"></i> <?php echo format_date($post['published_at']); ?></span>
                        </div>

                        <h3 class="post-title">
                            <a href="post.php?id=<?php echo $post['id']; ?>"><?php echo htmlspecialchars($post['title']); ?></a>
                        </h3>

                        <p class="post-excerpt"><?php echo truncate_text(strip_tags($post['content'])); ?></p>

                        <div class="post-tags">
                            <?php
                            $tags = get_post_tags($conn, $post['id']);
                            foreach ($tags as $tag):
                            ?>
                                <a href="tag.php?id=<?php echo $tag['id']; ?>" class="tag"><?php echo htmlspecialchars($tag['name']); ?></a>
                            <?php endforeach; ?>
                        </div>

                        <div class="post-actions">
                            <a href="post.php?id=<?php echo $post['id']; ?>" class="btn btn-primary">Read More</a>
                            <?php if (can_edit_post($post['user_id'], $current_user_id, $is_admin)): ?>
                                <a href="edit_post.php?id=<?php echo $post['id']; ?>" class="edit-post-btn" onclick="return confirm('Edit this post?');">Edit</a>
                            <?php endif; ?>
                            <!-- Comment Count -->
                            <a href="post.php?id=<?php echo $post['id']; ?>#comments-section" class="comment-count-link">
                                <i class="fas fa-comments"></i> <?php echo htmlspecialchars($post['comment_count']); ?>
                            </a>
                            <div class="likes-dislikes">
                                <button class="like-btn <?php echo isset($user_liked_posts[$post['id']]) ? 'active' : ''; ?>" data-post-id="<?php echo $post['id']; ?>" data-action="like">
                                    <i class="fas fa-thumbs-up"></i> <span class="like-count"><?php echo get_post_likes($conn, $post['id']); ?></span>
                                </button>
                                <?php if (isset($post['dislike_button_status']) && $post['dislike_button_status'] === 'enabled'): ?>
                                    <button class="dislike-btn <?php echo isset($user_disliked_posts[$post['id']]) ? 'active' : ''; ?>" data-post-id="<?php echo $post['id']; ?>" data-action="dislike">
                                        <i class="fas fa-thumbs-down"></i> <span class="dislike-count"><?php echo get_post_dislikes($conn, $post['id']); ?></span>
                                    </button>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </article>
            <?php endwhile; ?>
        </div>

        <!-- Pagination for Top Liked Posts -->
        <?php if ($total_pages_top_liked > 1): ?>
            <div class="pagination">
                <?php if ($page_top_liked > 1): ?>
                    <a href="?id=<?php echo $user_id; ?>&page_liked=<?php echo $page_top_liked - 1; ?>#top-liked-posts">&laquo; Previous</a>
                <?php endif; ?>

                <?php for ($i = 1; $i <= $total_pages_top_liked; $i++): ?>
                    <?php if ($i == $page_top_liked): ?>
                        <span class="current"><?php echo $i; ?></span>
                    <? else: ?>
                        <a href="?id=<?php echo $user_id; ?>&page_liked=<?php echo $i; ?>#top-liked-posts"><?php echo $i; ?></a>
                    <?php endif; ?>
                <?php endfor; ?>

                <?php if ($page_top_liked < $total_pages_top_liked): ?>
                    <a href="?id=<?php echo $user_id; ?>&page_liked=<?php echo $page_top_liked + 1; ?>#top-liked-posts">Next &raquo;</a>
                <?php endif; ?>
            </div>
        <?php endif; ?>

    <?php else: ?>
        <div class="text-center mb-5">
            <p><?php echo htmlspecialchars($profile_user['username']); ?> has no top liked posts yet.</p>
        </div>
    <?php endif; ?>

    <!-- All Posts Section -->
    <h2 class="section-title">All Posts by <?php echo htmlspecialchars($profile_user['username']); ?></h2>
    <?php if ($all_posts_result->num_rows > 0): ?>
        <div class="posts-grid">
            <?php while ($post = $all_posts_result->fetch_assoc()): ?>
                <article class="post-card">
                    <?php
                    $image_src = '';
                    if (!empty($post['image_path'])) {
                        if (is_url_optimized($post['image_path'])) {
                            $image_src = htmlspecialchars($post['image_path']) . '?q=80&w=400&h=250&fit=crop';
                        } else {
                            $image_src = 'uploads/' . htmlspecialchars(basename($post['image_path']));
                            if (!file_exists($image_src)) {
                                $image_src = '../' . htmlspecialchars($post['image_path']);
                            }
                        }
                    }
                    ?>
                    <?php if ($image_src): ?>
                        <a href="post.php?id=<?php echo $post['id']; ?>"><div class="post-image">
                            <img src="<?php echo $image_src; ?>"
                                 alt="<?php echo htmlspecialchars($post['title']); ?>"
                                 class="post-image"
                                 loading="lazy"
                                 onerror="this.onerror=null;this.src='https://placehold.co/400x250/cccccc/333333?text=Image+Not+Found';">
                        </div></a>
                    <?php else: ?>
                        <div class="post-image no-image" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); display: flex; align-items: center; justify-content: center; color: white; font-size: 1.2rem;">
                            <i class="fas fa-image"></i>
                        </div>
                    <?php endif; ?>

                    <div class="post-content">
                        <div class="post-meta">
                            <a href="category.php?id=<?php echo $post['category_id']; ?>"><span class="post-category"><?php echo htmlspecialchars($post['category_name'] ?? 'Uncategorized'); ?></span></a>
                            <span>
                                    <a href="view_user.php?id=<?php echo $post['user_id']; ?>" class="post-author-avatar">
                                            <?php
                                            $author_profile_image = !empty($post['profile_image_path']) ? htmlspecialchars($post['profile_image_path']) : '';
                                            $author_prefers_avatar = isset($post['prefers_avatar']) ? (bool)$post['prefers_avatar'] : false;

                                            $author_profile_image_display = '';

                                            if ($author_prefers_avatar) {
                                                if (isset($post['gender']) && $post['gender'] == 'male') {
                                                    $author_profile_image_display = 'avatars/male_avatar.png';
                                                } elseif (isset($post['gender']) && $post['gender'] == 'female') {
                                                    $author_profile_image_display = 'avatars/female_avatar.png';
                                                } else {
                                                    $author_profile_image_display = 'avatars/default_avatar.png';
                                                }
                                                if (!empty($author_profile_image) && in_array($author_profile_image, $available_avatars_global_for_display)) {
                                                    $author_profile_image_display = $author_profile_image;
                                                }
                                            } elseif (!empty($author_profile_image)) {
                                                $custom_author_path = htmlspecialchars($author_profile_image);
                                                if (file_exists($custom_author_path)) {
                                                    $author_profile_image_display = $custom_author_path;
                                                } elseif (file_exists('../' . $custom_author_path)) {
                                                    $author_profile_image_display = '../' . $custom_author_path;
                                                } else {
                                                    $author_profile_image_display = 'avatars/default_avatar.png';
                                                }
                                            } else {
                                                if (isset($post['gender']) && $post['gender'] == 'male') {
                                                    $author_profile_image_display = 'avatars/male_avatar.png';
                                                } elseif (isset($post['gender']) && $post['gender'] == 'female') {
                                                    $author_profile_image_display = 'avatars/female_avatar.png';
                                                } else {
                                                    $author_profile_image_display = 'avatars/default_avatar.png';
                                                }
                                            }
                                            ?>
                                            <img src="<?php echo $author_profile_image_display; ?>" alt="<?php echo htmlspecialchars($post['username']); ?>'s Profile" class="avatar-small">
                                        </a>
                                    <a href="view_user.php?id=<?php echo $post['user_id']; ?>"><?php echo htmlspecialchars($post['username']); ?></a>
                                </span>
                            <span><i class="fas fa-calendar"></i> <?php echo format_date($post['published_at']); ?></span>
                        </div>

                        <h3 class="post-title">
                            <a href="post.php?id=<?php echo $post['id']; ?>"><?php echo htmlspecialchars($post['title']); ?></a>
                        </h3>

                        <p class="post-excerpt"><?php echo truncate_text(strip_tags($post['content'])); ?></p>

                        <div class="post-tags">
                            <?php
                            $tags = get_post_tags($conn, $post['id']);
                            foreach ($tags as $tag):
                            ?>
                                <a href="tag.php?id=<?php echo $tag['id']; ?>" class="tag"><?php echo htmlspecialchars($tag['name']); ?></a>
                            <?php endforeach; ?>
                        </div>

                        <div class="post-actions">
                            <a href="post.php?id=<?php echo $post['id']; ?>" class="btn btn-primary">Read More</a>
                            <?php if (can_edit_post($post['user_id'], $current_user_id, $is_admin)): ?>
                                <a href="edit_post.php?id=<?php echo $post['id']; ?>" class="edit-post-btn" onclick="return confirm('Edit this post?');">Edit</a>
                            <?php endif; ?>
                            <!-- Comment Count -->
                            <a href="post.php?id=<?php echo $post['id']; ?>#comments-section" class="comment-count-link">
                                <i class="fas fa-comments"></i> <?php echo htmlspecialchars($post['comment_count']); ?>
                            </a>
                            <div class="likes-dislikes">
                                <button class="like-btn <?php echo isset($user_liked_posts[$post['id']]) ? 'active' : ''; ?>" data-post-id="<?php echo $post['id']; ?>" data-action="like">
                                    <i class="fas fa-thumbs-up"></i> <span class="like-count"><?php echo get_post_likes($conn, $post['id']); ?></span>
                                </button>
                                <?php if (isset($post['dislike_button_status']) && $post['dislike_button_status'] === 'enabled'): ?>
                                    <button class="dislike-btn <?php echo isset($user_disliked_posts[$post['id']]) ? 'active' : ''; ?>" data-post-id="<?php echo $post['id']; ?>" data-action="dislike">
                                        <i class="fas fa-thumbs-down"></i> <span class="dislike-count"><?php echo get_post_dislikes($conn, $post['id']); ?></span>
                                    </button>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </article>
            <?php endwhile; ?>
        </div>

        <!-- Pagination for All Posts -->
        <?php if ($total_pages_all_posts > 1): ?>
            <div class="pagination">
                <?php if ($page_all_posts > 1): ?>
                    <a href="?id=<?php echo $user_id; ?>&page_all=<?php echo $page_all_posts - 1; ?>#all-posts">&laquo; Previous</a>
                <?php endif; ?>

                <?php for ($i = 1; $i <= $total_pages_all_posts; $i++): ?>
                    <?php if ($i == $page_all_posts): ?>
                        <span class="current"><?php echo $i; ?></span>
                    <?php else: ?>
                        <a href="?id=<?php echo $user_id; ?>&page_all=<?php echo $i; ?>#all-posts"><?php echo $i; ?></a>
                    <?php endif; ?>
                <?php endfor; ?>

                <?php if ($page_all_posts < $total_pages_all_posts): ?>
                    <a href="?id=<?php echo $user_id; ?>&page_all=<?php echo $page_all_posts + 1; ?>#all-posts">Next &raquo;</a>
                <?php endif; ?>
            </div>
        <?php endif; ?>

    <?php else: ?>
        <div class="text-center">
            <p><?php echo htmlspecialchars($profile_user['username']); ?> has not published any posts yet.</p>
        </div>
    <?php endif; ?>
    
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Universal handler for Like and Dislike buttons
    function setupReactionButton(buttonClass, targetScript) {
        document.querySelectorAll(buttonClass).forEach(function(btn) {
            btn.addEventListener('click', function(e) {
                e.preventDefault();
                var postId = this.getAttribute('data-post-id');
                var btnElem = this;

                // Determine if a cross-reaction button exists and its state (to update it visually)
                var otherButtonClass = (buttonClass === '.like-btn') ? '.dislike-btn' : '.like-btn';
                var otherBtnElem = document.querySelector(`${otherButtonClass}[data-post-id="${postId}"]`);
                
                fetch(targetScript, {
                    method: 'POST',
                    headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                    body: 'post_id=' + encodeURIComponent(postId)
                })
                .then(response => {
                    const contentType = response.headers.get("content-type");
                    if (contentType && contentType.indexOf("application/json") !== -1) {
                        return response.json();
                    } else {
                        console.error("Server response was not JSON. Response:", response);
                        throw new TypeError("Oops, we haven't got JSON!");
                    }
                })
                .then(data => {
                    if (data.success) {
                        // Update the clicked button's state and count
                        if (data.action === 'liked') {
                            btnElem.classList.add('active'); // Use 'active' class as per style.css
                            btnElem.querySelector('span').textContent = data.total_likes;
                            // If dislike was removed, update dislike button
                            if (otherBtnElem) {
                                otherBtnElem.classList.remove('active');
                                otherBtnElem.querySelector('span').textContent = data.total_dislikes;
                            }
                        } else if (data.action === 'unliked') {
                            btnElem.classList.remove('active');
                            btnElem.querySelector('span').textContent = data.total_likes;
                        } else if (data.action === 'disliked') {
                            btnElem.classList.add('active'); // Use 'active' class as per style.css
                            btnElem.querySelector('span').textContent = data.total_dislikes;
                            // If like was removed, update like button
                            if (otherBtnElem) {
                                otherBtnElem.classList.remove('active');
                                otherBtnElem.querySelector('span').textContent = data.total_likes;
                            }
                        } else if (data.action === 'undisliked') {
                            btnElem.classList.remove('active');
                            btnElem.querySelector('span').textContent = data.total_dislikes;
                        }
                        
                    } else if (data.message) {
                        console.error("Error from server: " + data.message);
                        if (data.message === 'Login required') {
                            // Using a custom modal/message box instead of alert()
                            // For simplicity in this example, I'll use a console log,
                            // but in a real app, you'd show a user-friendly modal.
                            console.log('Login required to like or dislike posts. Redirecting...');
                            window.location.href = 'login.php'; // Redirect to login page
                        } else {
                            console.error('Error: ' + data.message);
                            // Show user-friendly error message
                        }
                    }
                })
                .catch(error => {
                    console.error('Fetch error:', error);
                    // Show user-friendly error message
                });
            });
        });
    }

    setupReactionButton('.like-btn', 'like_post.php');
    setupReactionButton('.dislike-btn', 'dislike_post.php');

    // Lazy loading for images
    const lazyImages = document.querySelectorAll('img[loading="lazy"]');
    if ('IntersectionObserver' in window) {
        let lazyImageObserver = new IntersectionObserver(function(entries, observer) {
            entries.forEach(function(entry) {
                if (entry.isIntersecting) {
                    let lazyImage = entry.target;
                    lazyImage.src = lazyImage.dataset.src || lazyImage.src; // Use data-src if available, otherwise its current src
                    lazyImage.removeAttribute('loading');
                    lazyImageObserver.unobserve(lazyImage);
                }
            });
        });

        lazyImages.forEach(function(lazyImage) {
            lazyImageObserver.observe(lazyImage);
        });
    } else {
        // Fallback for browsers that don't support Intersection Observer
        lazyImages.forEach(function(lazyImage) {
            lazyImage.src = lazyImage.dataset.src || lazyImage.src;
            lazyImage.removeAttribute('loading');
        });
    }

    // Function to set up the follow/unfollow button
    function setupFollowButton(buttonSelector) {
        document.querySelectorAll(buttonSelector).forEach(function(btn) {
            btn.addEventListener('click', function(e) {
                e.preventDefault();
                const followedId = this.dataset.userId; // The ID of the user being followed
                const currentAction = this.dataset.action; // 'follow' or 'unfollow'
                const btnElement = this;
                const followerCountSpan = document.getElementById(`follower-count-${followedId}`);

                // Disable button to prevent multiple clicks
                btnElement.disabled = true;

                // Corrected path for follow_action.php
                fetch('includes/follow_action.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: `followed_id=${encodeURIComponent(followedId)}&action=${encodeURIComponent(currentAction)}`
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        // Update button text and class
                        if (data.is_following) {
                            btnElement.textContent = 'Unfollow';
                            btnElement.classList.remove('btn-primary');
                            btnElement.classList.add('btn-secondary');
                            btnElement.dataset.action = 'unfollow';
                        } else {
                            btnElement.textContent = 'Follow';
                            btnElement.classList.remove('btn-secondary');
                            btnElement.classList.add('btn-primary');
                            btnElement.dataset.action = 'follow';
                        }
                        // Update follower count
                        if (followerCountSpan) {
                            followerCountSpan.textContent = data.follower_count;
                        }
                    } else {
                        console.error('Follow/Unfollow Error:', data.message);
                        // Optionally show a user-friendly message (e.g., a toast notification)
                        if (data.message === 'Login required to perform this action.') {
                            console.log('Login required to follow or unfollow. Redirecting...');
                            window.location.href = 'login.php';
                        }
                    }
                })
                .catch(error => {
                    console.error('Fetch Error:', error);
                    // Optionally show a user-friendly error message
                })
                .finally(() => {
                    // Re-enable button
                    btnElement.disabled = false;
                });
            });
        });
    }

    // Call the setup function for all follow buttons on the page
    setupFollowButton('.follow-btn');
});
</script>
<?php include 'includes/footer.php'; ?>
<?php endif; ?>