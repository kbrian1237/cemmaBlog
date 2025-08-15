<?php
// Start session if not already started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Function to sanitize input data
function sanitize_input($data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data);
    return $data;
}

// Function to check if user is logged in
function is_logged_in() {
    return isset($_SESSION['user_id']);
}

// Function to check if user is admin
function is_admin() {
    return isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'admin';
}

// Function to redirect to login if not logged in
function require_login() {
    if (!is_logged_in()) {
        header("Location: login.php");
        exit();
    }
}

// Function to redirect to login if not admin
function require_admin() {
    if (!is_admin()) {
        header("Location: index.php");
        exit();
    }
}

// Function to validate email
function validate_email($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL);
}

// Function to validate password strength
function validate_password($password) {
    return preg_match('/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)[a-zA-Z\d@$!%*?&]{8,}$/', $password);
}

// Function to truncate text
function truncate_text($text, $length = 150) {
    if (strlen($text) > $length) {
        return substr($text, 0, $length) . '...';
    }
    return $text;
}

// Function to format date
function format_date($date) {
    return date('F j, Y', strtotime($date));
}

// Function to format datetime
function format_datetime($datetime) {
    return date('F j, Y g:i A', strtotime($datetime));
}

// Function to get user by ID
function get_user_by_id($conn, $user_id) {
    $stmt = $conn->prepare("SELECT id, username, email, role, created_at, gender, profile_image_path, login_status, prefers_avatar, bio FROM users WHERE id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    return $result->fetch_assoc();
}

// Function to get category by ID
function get_category_by_id($conn, $category_id) {
    $stmt = $conn->prepare("SELECT * FROM categories WHERE id = ?");
    $stmt->bind_param("i", $category_id);
    $stmt->execute();
    $result = $stmt->get_result();
    return $result->fetch_assoc();
}

// Function to get all categories
function get_all_categories($conn) {
    $result = $conn->query("SELECT * FROM categories ORDER BY name");
    return $result->fetch_all(MYSQLI_ASSOC);
}

// Function to get post tags
function get_post_tags($conn, $post_id) {
    $stmt = $conn->prepare("SELECT t.* FROM tags t JOIN post_tags pt ON t.id = pt.tag_id WHERE pt.post_id = ?");
    $stmt->bind_param("i", $post_id);
    $stmt->execute();
    $result = $stmt->get_result();
    return $result->fetch_all(MYSQLI_ASSOC);
}

// Function to upload image
function upload_image($file, $upload_dir = 'profiles/') {
    $allowed_types = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif'];
    $max_size = 5 * 1024 * 1024; // 5MB

    if ($file['error'] !== UPLOAD_ERR_OK) {
        return ['success' => false, 'message' => 'File upload error: ' . $file['error']];
    }

    if (!in_array($file['type'], $allowed_types)) {
        return ['success' => false, 'message' => 'Invalid file type. Only JPEG, PNG, and GIF are allowed.'];
    }

    if ($file['size'] > $max_size) {
        return ['success' => false, 'message' => 'File size too large. Maximum 5MB allowed.'];
    }

    $check = getimagesize($file['tmp_name']);
    if ($check === false) {
        return ['success' => false, 'message' => 'File is not a valid image.'];
    }

    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0755, true);
    }
    if (!is_writable($upload_dir)) {
        return ['success' => false, 'message' => 'Upload directory is not writable.'];
    }

    $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $filename = uniqid('profile_') . '.' . $extension;
    $filepath = $upload_dir . $filename;

    if (move_uploaded_file($file['tmp_name'], $filepath)) {
        optimize_image($filepath);
        return ['success' => true, 'filepath' => $filepath];
    } else {
        return ['success' => false, 'message' => 'Failed to upload file. Please try again.'];
    }
}

// Function to optimize images
function optimize_image($filepath) {
    $max_width = 300;
    $max_height = 300;
    $quality = 80;

    list($width, $height, $type) = getimagesize($filepath);

    if ($width > $max_width || $height > $max_height) {
        $image = null;
        switch ($type) {
            case IMAGETYPE_JPEG:
                $image = imagecreatefromjpeg($filepath);
                break;
            case IMAGETYPE_PNG:
                $image = imagecreatefrompng($filepath);
                break;
            case IMAGETYPE_GIF:
                $image = imagecreatefromgif($filepath);
                break;
            default:
                return;
        }

        $ratio = min($max_width / $width, $max_height / $height);
        $new_width = (int)($width * $ratio);
        $new_height = (int)($height * $ratio);

        $new_image = imagecreatetruecolor($new_width, $new_height);

        if ($type == IMAGETYPE_PNG || $type == IMAGETYPE_GIF) {
            imagecolortransparent($new_image, imagecolorallocatealpha($new_image, 0, 0, 0, 127));
            imagealphablending($new_image, false);
            imagesavealpha($new_image, true);
        }

        imagecopyresampled($new_image, $image, 0, 0, 0, 0, $new_width, $new_height, $width, $height);

        switch ($type) {
            case IMAGETYPE_JPEG:
                imagejpeg($new_image, $filepath, $quality);
                break;
            case IMAGETYPE_PNG:
                imagepng($new_image, $filepath, 9);
                break;
            case IMAGETYPE_GIF:
                imagegif($new_image, $filepath);
                break;
        }

        imagedestroy($image);
        imagedestroy($new_image);
    }
}

// Helper: get total likes for a post
function get_post_likes($conn, $post_id) {
    $stmt = $conn->prepare("SELECT COUNT(*) as total FROM likes WHERE post_id = ?");
    $stmt->bind_param("i", $post_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $stmt->close();
    return $row ? (int)$row['total'] : 0;
}

// Helper: check if user liked a post
function user_liked_post($conn, $post_id, $user_id) {
    if (!$user_id) return false;
    $stmt = $conn->prepare("SELECT 1 FROM likes WHERE post_id = ? AND user_id = ?");
    $stmt->bind_param("ii", $post_id, $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $liked = $result->num_rows > 0;
    $stmt->close();
    return $liked;
}

// Helper: get total dislikes for a post
function get_post_dislikes($conn, $post_id) {
    $stmt = $conn->prepare("SELECT COUNT(*) as total FROM dislikes WHERE post_id = ?");
    $stmt->bind_param("i", $post_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $stmt->close();
    return $row ? (int)$row['total'] : 0;
}

// Helper: check if user disliked a post
function user_disliked_post($conn, $post_id, $user_id) {
    if (!$user_id) return false;
    $stmt = $conn->prepare("SELECT 1 FROM dislikes WHERE post_id = ? AND user_id = ?");
    $stmt->bind_param("ii", $post_id, $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $disliked = $result->num_rows > 0;
    $stmt->close();
    return $disliked;
}

// Function to get available avatars from avatars/ folder
function get_available_avatars($avatar_dir = 'avatars/') {
    $avatars = [];
    if (is_dir($avatar_dir) && $handle = opendir($avatar_dir)) {
        while (false !== ($file = readdir($handle))) {
            if ($file != "." && $file != "..") {
                $filepath = $avatar_dir . $file;
                // Ensure it's a file and an image
                if (is_file($filepath) && preg_match('/\.(jpg|jpeg|png|gif)$/i', $file)) {
                    $avatars[] = $filepath;
                }
            }
        }
        closedir($handle);
    } else {
        error_log("Avatar directory ($avatar_dir) not found or not readable.");
    }
    return $avatars;
}

// Function to update user avatar preference
function update_user_avatar_preference($conn, $user_id, $profile_image_path, $prefers_avatar) {
    $stmt = $conn->prepare("UPDATE users SET profile_image_path = ?, prefers_avatar = ? WHERE id = ?");
    $prefers_avatar_int = $prefers_avatar ? 1 : 0;

    // Determine the final image path to save
    $final_image_path = null;
    if ($prefers_avatar) {
        // If user prefers avatar, ensure the path is one of the available avatars
        $available_avatars = get_available_avatars('avatars/');
        if (in_array($profile_image_path, $available_avatars)) {
            $final_image_path = $profile_image_path;
        } else {
            // If the provided path is not a valid avatar, default to a generic one
            // This case should ideally be handled by dashboard.php before calling this function
            // but this provides a fallback for database consistency.
            $final_image_path = 'avatars/default_avatar.png'; // Fallback
        }
    } else {
        // If user prefers custom image, ensure the path is a valid custom upload
        // The upload_image function already handles saving to 'profiles/'
        if (!empty($profile_image_path) && strpos($profile_image_path, 'profiles/') === 0 && file_exists($profile_image_path)) {
            $final_image_path = $profile_image_path;
        } else {
            // If the custom image path is invalid or empty, set to null
            $final_image_path = null;
        }
    }

    $stmt->bind_param("sii", $final_image_path, $prefers_avatar_int, $user_id);

    if ($stmt->execute()) {
        return true;
    } else {
        error_log("Error updating user avatar preference for user ID: $user_id Error: " . $stmt->error);
        return false;
    }
}

// NEW FUNCTION: update_user_bio
function update_user_bio($conn, $user_id, $new_bio) {
    // Prepare the update statement
    $stmt = $conn->prepare("UPDATE users SET bio = ? WHERE id = ?");
    if ($stmt === false) {
        error_log("Error preparing update_user_bio statement: " . $conn->error);
        return false;
    }

    // Bind parameters: 's' for string (bio), 'i' for integer (user_id)
    $stmt->bind_param("si", $new_bio, $user_id);

    // Execute the statement
    if ($stmt->execute()) {
        $stmt->close();
        return true; // Bio updated successfully
    } else {
        error_log("Error executing update_user_bio statement for user ID: $user_id Error: " . $stmt->error);
        $stmt->close();
        return false; // Failed to update bio
    }
}


if (!function_exists('build_pagination_url')) { // Add this check to be safe
    function build_pagination_url($base_url, $page_num, $page_param_name = 'page') {
        $parsed_url = parse_url($base_url);
        $query_params = [];
        if (isset($parsed_url['query'])) {
            parse_str($parsed_url['query'], $query_params);
        }

        // Remove the specific page parameter if it exists to avoid duplication
        unset($query_params[$page_param_name]);

        // Add the new page parameter
        $query_params[$page_param_name] = $page_num;

        $new_query_string = http_build_query($query_params);
        $path = $parsed_url['path'] ?? '';

        return $path . '?' . $new_query_string;
    }
}

/*
|--------------------------------------------------------------------------
| Followers and Following Functions
|--------------------------------------------------------------------------
|
| These functions handle the logic for user following and followers.
|
*/

/**
 * Checks if a user is following another user.
 *
 * @param mysqli $conn The database connection object.
 * @param int $follower_id The ID of the user who might be following.
 * @param int $followed_id The ID of the user who might be followed.
 * @return bool True if follower_id is following followed_id, false otherwise.
 */
function is_following($conn, $follower_id, $followed_id) {
    if ($follower_id <= 0 || $followed_id <= 0) {
        return false; // Invalid IDs
    }
    $stmt = $conn->prepare("SELECT 1 FROM user_follows WHERE follower_id = ? AND followed_id = ?");
    if ($stmt === false) {
        error_log("Error preparing is_following statement: " . $conn->error);
        return false;
    }
    $stmt->bind_param("ii", $follower_id, $followed_id);
    $stmt->execute();
    $stmt->store_result();
    $is_following = $stmt->num_rows > 0;
    $stmt->close();
    return $is_following;
}

/**
 * Allows a user to follow another user.
 *
 * @param mysqli $conn The database connection object.
 * @param int $follower_id The ID of the user who wants to follow.
 * @param int $followed_id The ID of the user to be followed.
 * @return array An associative array with 'success' (bool) and 'message' (string).
 */
function follow_user($conn, $follower_id, $followed_id) {
    if ($follower_id === $followed_id) {
        return ['success' => false, 'message' => 'You cannot follow yourself.'];
    }
    if (is_following($conn, $follower_id, $followed_id)) {
        return ['success' => false, 'message' => 'You are already following this user.'];
    }

    $stmt = $conn->prepare("INSERT INTO user_follows (follower_id, followed_id) VALUES (?, ?)");
    if ($stmt === false) {
        error_log("Error preparing follow_user statement: " . $conn->error);
        return ['success' => false, 'message' => 'Database error during follow operation.'];
    }
    $stmt->bind_param("ii", $follower_id, $followed_id);
    if ($stmt->execute()) {
        $stmt->close();
        return ['success' => true, 'message' => 'Successfully followed user.'];
    } else {
        error_log("Error executing follow_user statement: " . $stmt->error);
        return ['success' => false, 'message' => 'Failed to follow user.'];
    }
}

/**
 * Allows a user to unfollow another user.
 *
 * @param mysqli $conn The database connection object.
 * @param int $follower_id The ID of the user who wants to unfollow.
 * @param int $followed_id The ID of the user to be unfollowed.
 * @return array An associative array with 'success' (bool) and 'message' (string).
 */
function unfollow_user($conn, $follower_id, $followed_id) {
    if (!is_following($conn, $follower_id, $followed_id)) {
        return ['success' => false, 'message' => 'You are not following this user.'];
    }

    $stmt = $conn->prepare("DELETE FROM user_follows WHERE follower_id = ? AND followed_id = ?");
    if ($stmt === false) {
        error_log("Error preparing unfollow_user statement: " . $conn->error);
        return ['success' => false, 'message' => 'Database error during unfollow operation.'];
    }
    $stmt->bind_param("ii", $follower_id, $followed_id);
    if ($stmt->execute()) {
        $stmt->close();
        return ['success' => true, 'message' => 'Successfully unfollowed user.'];
    } else {
        error_log("Error executing unfollow_user statement: " . $stmt->error);
        return ['success' => false, 'message' => 'Failed to unfollow user.'];
    }
}

/**
 * Gets the total number of followers for a given user.
 *
 * @param mysqli $conn The database connection object.
 * @param int $user_id The ID of the user whose followers are to be counted.
 * @return int The total number of followers.
 */
function get_follower_count($conn, $user_id) {
    if ($user_id <= 0) return 0;
    $stmt = $conn->prepare("SELECT COUNT(*) AS total_followers FROM user_follows WHERE followed_id = ?");
    if ($stmt === false) {
        error_log("Error preparing get_follower_count statement: " . $conn->error);
        return 0;
    }
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $stmt->close();
    return $row ? (int)$row['total_followers'] : 0;
}

/**
 * Gets the total number of users a given user is following.
 *
 * @param mysqli $conn The database connection object.
 * @param int $user_id The ID of the user whose following count is to be retrieved.
 * @return int The total number of users being followed.
 */
function get_following_count($conn, $user_id) {
    if ($user_id <= 0) return 0;
    $stmt = $conn->prepare("SELECT COUNT(*) AS total_following FROM user_follows WHERE follower_id = ?");
    if ($stmt === false) {
        error_log("Error preparing get_following_count statement: " . $conn->error);
        return 0;
    }
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $stmt->close();
    return $row ? (int)$row['total_following'] : 0;
}

/**
 * Gets a list of users that a given user is following.
 *
 * @param mysqli $conn The database connection object.
 * @param int $user_id The ID of the user.
 * @param int $limit Optional. The maximum number of users to return. Default is 10.
 * @param int $offset Optional. The offset for pagination. Default is 0.
 * @return array An array of associative arrays, each representing a user being followed.
 */
function get_following_users($conn, $user_id, $limit = 10, $offset = 0) {
    if ($user_id <= 0) return [];
    $query = "SELECT u.id, u.username, u.profile_image_path, u.prefers_avatar, u.gender
              FROM user_follows uf
              JOIN users u ON uf.followed_id = u.id
              WHERE uf.follower_id = ?
              ORDER BY uf.created_at DESC
              LIMIT ? OFFSET ?";
    $stmt = $conn->prepare($query);
    if ($stmt === false) {
        error_log("Error preparing get_following_users statement: " . $conn->error);
        return [];
    }
    $stmt->bind_param("iii", $user_id, $limit, $offset);
    $stmt->execute();
    $result = $stmt->get_result();
    $users = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    return $users;
}

/**
 * Gets a list of users who are following a given user.
 *
 * @param mysqli $conn The database connection object.
 * @param int $user_id The ID of the user.
 * @param int $limit Optional. The maximum number of users to return. Default is 10.
 * @param int $offset Optional. The offset for pagination. Default is 0.
 * @return array An array of associative arrays, each representing a follower.
 */
function get_followers_users($conn, $user_id, $limit = 10, $offset = 0) {
    if ($user_id <= 0) return [];
    $query = "SELECT u.id, u.username, u.profile_image_path, u.prefers_avatar, u.gender
              FROM user_follows uf
              JOIN users u ON uf.follower_id = u.id
              WHERE uf.followed_id = ?
              ORDER BY uf.created_at DESC
              LIMIT ? OFFSET ?";
    $stmt = $conn->prepare($query);
    if ($stmt === false) {
        error_log("Error preparing get_followers_users statement: " . $conn->error);
        return [];
    }
    $stmt->bind_param("iii", $user_id, $limit, $offset);
    $stmt->execute();
    $result = $stmt->get_result();
    $users = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    return $users;
}

/**
 * Fetches posts from users that the current user is following.
 * This can be used to create a "following feed" on a dashboard or home page.
 *
 * @param mysqli $conn The database connection object.
 * @param int $current_user_id The ID of the user whose feed is being generated.
 * @param int $limit Optional. The maximum number of posts to return. Default is 10.
 * @param int $offset Optional. The offset for pagination. Default is 0.
 * @return array An array of associative arrays, each representing a post.
 */
function get_following_feed_posts($conn, $current_user_id, $limit = 10, $offset = 0) {
    if ($current_user_id <= 0) return [];

    $query = "SELECT p.*, u.username, c.name as category_name, u.profile_image_path, u.prefers_avatar, u.gender
              FROM posts p
              JOIN users u ON p.user_id = u.id
              LEFT JOIN categories c ON p.category_id = c.id
              WHERE p.user_id IN (SELECT followed_id FROM user_follows WHERE follower_id = ?)
              AND p.status = 'published'
              ORDER BY p.published_at DESC
              LIMIT ? OFFSET ?";
    $stmt = $conn->prepare($query);
    if ($stmt === false) {
        error_log("Error preparing get_following_feed_posts statement: " . $conn->error);
        return [];
    }
    $stmt->bind_param("iii", $current_user_id, $limit, $offset);
    $stmt->execute();
    $result = $stmt->get_result();
    $posts = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    return $posts;
}

// Start session if not already started (ensure this is at the very top of functions.php)
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Ensure db_connection.php is included to get the $conn object
// If $conn is not globally available or passed, you might need to adjust this.
// For simplicity, these functions assume $conn is available or passed.
// require_once 'includes/db_connection.php'; // Uncomment if $conn is not globally available

// --- Community Core Functions ---

/**
 * Creates a new group.
 *
 * @param mysqli $conn The database connection object.
 * @param string $name The name of the group.
 * @param string $description The description of the group.
 * @param int $creator_id The ID of the user creating the group.
 * @param bool $is_private Whether the group is private (true) or public (false).
 * @param string|null $join_code Optional join code for private groups.
 * @param string|null $group_image_path Optional path to the group's image.
 * @return int|false The ID of the newly created group on success, false on failure.
 */
if (!function_exists('create_group')) {
    function create_group($conn, $name, $description, $creator_id, $is_private = false, $join_code = null, $group_image_path = null) {
        $query = "INSERT INTO `groups` (name, description, creator_id, is_private, join_code, group_image_path) VALUES (?, ?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($query);
        if ($stmt === false) {
            error_log("Error preparing create_group statement: " . $conn->error);
            return false;
        }
        $is_private_int = $is_private ? 1 : 0; // Convert boolean to tinyint for DB
        $stmt->bind_param("ssiiss", $name, $description, $creator_id, $is_private_int, $join_code, $group_image_path);
        if ($stmt->execute()) {
            $group_id = $stmt->insert_id;
            // Add creator as an admin member of the group
            add_group_member($conn, $group_id, $creator_id, 'admin');
            $stmt->close();
            return $group_id;
        } else {
            error_log("Error executing create_group statement: " . $stmt->error);
            $stmt->close();
            return false;
        }
    }
}

/**
 * Gets details of a specific group by its ID.
 *
 * @param mysqli $conn The database connection object.
 * @param int $group_id The ID of the group.
 * @return array|null An associative array of group details, or null if not found.
 */
if (!function_exists('get_group_details')) {
    function get_group_details($conn, $group_id) {
        $query = "SELECT g.*, u.username as creator_username FROM `groups` g JOIN `users` u ON g.creator_id = u.id WHERE g.id = ?";
        $stmt = $conn->prepare($query);
        if ($stmt === false) {
            error_log("Error preparing get_group_details statement: " . $conn->error);
            return null;
        }
        $stmt->bind_param("i", $group_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $group = $result->fetch_assoc();
        $stmt->close();
        return $group;
    }
}

/**
 * Gets all public groups.
 *
 * @param mysqli $conn The database connection object.
 * @param int $limit Optional. The maximum number of groups to return. Default is 20.
 * @param int $offset Optional. The offset for pagination. Default is 0.
 * @return array An array of associative arrays, each representing a public group.
 */
if (!function_exists('get_public_groups')) {
    function get_public_groups($conn, $limit = 20, $offset = 0) {
        $query = "SELECT g.*, u.username as creator_username,
                         (SELECT COUNT(*) FROM group_members gm WHERE gm.group_id = g.id) as member_count
                  FROM `groups` g
                  JOIN `users` u ON g.creator_id = u.id
                  WHERE g.is_private = 0
                  ORDER BY g.created_at DESC
                  LIMIT ? OFFSET ?";
        $stmt = $conn->prepare($query);
        if ($stmt === false) {
            error_log("Error preparing get_public_groups statement: " . $conn->error);
            return [];
        }
        $stmt->bind_param("ii", $limit, $offset);
        $stmt->execute();
        $result = $stmt->get_result();
        $groups = $result->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        return $groups;
    }
}

/**
 * Gets groups that a specific user is a member of.
 *
 * @param mysqli $conn The database connection object.
 * @param int $user_id The ID of the user.
 * @param int $limit Optional. The maximum number of groups to return. Default is 20.
 * @param int $offset Optional. The offset for pagination. Default is 0.
 * @return array An array of associative arrays, each representing a group the user is in.
 */
if (!function_exists('get_user_groups')) {
    function get_user_groups($conn, $user_id, $limit = 20, $offset = 0) {
        $query = "SELECT g.*, u.username as creator_username, gm.role as user_role_in_group,
                         (SELECT COUNT(*) FROM group_members gm2 WHERE gm2.group_id = g.id) as member_count
                  FROM `groups` g
                  JOIN `group_members` gm ON g.id = gm.group_id
                  JOIN `users` u ON g.creator_id = u.id
                  WHERE gm.user_id = ?
                  ORDER BY gm.joined_at DESC
                  LIMIT ? OFFSET ?";
        $stmt = $conn->prepare($query);
        if ($stmt === false) {
            error_log("Error preparing get_user_groups statement: " . $conn->error);
            return [];
        }
        $stmt->bind_param("iii", $user_id, $limit, $offset);
        $stmt->execute();
        $result = $stmt->get_result();
        $groups = $result->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        return $groups;
    }
}

/**
 * Adds a user as a member to a group.
 *
 * @param mysqli $conn The database connection object.
 * @param int $group_id The ID of the group.
 * @param int $user_id The ID of the user to add.
 * @param string $role The role of the member ('member' or 'admin').
 * @return bool True on success, false on failure (e.g., already a member).
 */
if (!function_exists('add_group_member')) {
    function add_group_member($conn, $group_id, $user_id, $role = 'member') {
        // Check if already a member
        if (is_group_member($conn, $group_id, $user_id)) {
            return false; // Already a member
        }

        $query = "INSERT INTO `group_members` (group_id, user_id, role) VALUES (?, ?, ?)";
        $stmt = $conn->prepare($query);
        if ($stmt === false) {
            error_log("Error preparing add_group_member statement: " . $conn->error);
            return false;
        }
        $stmt->bind_param("iis", $group_id, $user_id, $role);
        $success = $stmt->execute();
        if (!$success) {
            error_log("Error executing add_group_member statement: " . $stmt->error);
        }
        $stmt->close();
        return $success;
    }
}

/**
 * Removes a user from a group.
 *
 * @param mysqli $conn The database connection object.
 * @param int $group_id The ID of the group.
 * @param int $user_id The ID of the user to remove.
 * @return bool True on success, false on failure.
 */
if (!function_exists('remove_group_member')) {
    function remove_group_member($conn, $group_id, $user_id) {
        $query = "DELETE FROM `group_members` WHERE group_id = ? AND user_id = ?";
        $stmt = $conn->prepare($query);
        if ($stmt === false) {
            error_log("Error preparing remove_group_member statement: " . $conn->error);
            return false;
        }
        $stmt->bind_param("ii", $group_id, $user_id);
        $success = $stmt->execute();
        if (!$success) {
            error_log("Error executing remove_group_member statement: " . $stmt->error);
        }
        $stmt->close();
        return $success;
    }
}

/**
 * Checks if a user is a member of a group.
 *
 * @param mysqli $conn The database connection object.
 * @param int $group_id The ID of the group.
 * @param int $user_id The ID of the user.
 * @return bool True if the user is a member, false otherwise.
 */
if (!function_exists('is_group_member')) {
    function is_group_member($conn, $group_id, $user_id) {
        $query = "SELECT COUNT(*) AS count FROM `group_members` WHERE group_id = ? AND user_id = ?";
        $stmt = $conn->prepare($query);
        if ($stmt === false) {
            error_log("Error preparing is_group_member statement: " . $conn->error);
            return false;
        }
        $stmt->bind_param("ii", $group_id, $user_id);
        $stmt->execute();
        $result = $stmt->get_result(); // Get the result set
        $row = $result->fetch_assoc(); // Fetch the associative array
        $count = $row ? $row['count'] : 0; // Get the count, default to 0 if no row
        $stmt->close();
        return $count > 0;
    }
}

/**
 * Checks if a user is an admin of a group.
 *
 * @param mysqli $conn The database connection object.
 * @param int $group_id The ID of the group.
 * @param int $user_id The ID of the user.
 * @return bool True if the user is an admin, false otherwise.
 */
if (!function_exists('is_group_admin')) {
    function is_group_admin($conn, $group_id, $user_id) {
        $query = "SELECT COUNT(*) AS count FROM `group_members` WHERE group_id = ? AND user_id = ? AND role = 'admin'";
        $stmt = $conn->prepare($query);
        if ($stmt === false) {
            error_log("Error preparing is_group_admin statement: " . $conn->error);
            return false;
        }
        $stmt->bind_param("ii", $group_id, $user_id);
        $stmt->execute();
        $result = $stmt->get_result(); // Get the result set
        $row = $result->fetch_assoc(); // Fetch the associative array
        $count = $row ? $row['count'] : 0; // Get the count, default to 0 if no row
        $stmt->close();
        return $count > 0;
    }
}

/**
 * Gets all members of a group.
 *
 * @param mysqli $conn The database connection object.
 * @param int $group_id The ID of the group.
 * @param int $limit Optional. The maximum number of members to return. Default is 20.
 * @param int $offset Optional. The offset for pagination. Default is 0.
 * @return array An array of associative arrays, each representing a group member.
 */
if (!function_exists('get_group_members')) {
    function get_group_members($conn, $group_id, $limit = 20, $offset = 0) {
        $query = "SELECT u.id, u.username, u.profile_image_path, u.prefers_avatar, u.gender, gm.role
                  FROM `group_members` gm
                  JOIN `users` u ON gm.user_id = u.id
                  WHERE gm.group_id = ?
                  ORDER BY gm.joined_at ASC
                  LIMIT ? OFFSET ?";
        $stmt = $conn->prepare($query);
        if ($stmt === false) {
            error_log("Error preparing get_group_members statement: " . $conn->error);
            return [];
        }
        $stmt->bind_param("iii", $group_id, $limit, $offset);
        $stmt->execute();
        $result = $stmt->get_result();
        $members = $result->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        return $members;
    }
}


// --- Group Messaging Functions ---

/**
 * Sends a message in a group chat.
 *
 * @param mysqli $conn The database connection object.
 * @param int $group_id The ID of the group.
 * @param int $sender_id The ID of the user sending the message.
 * @param string $message The message content.
 * @return bool True on success, false on failure.
 */
if (!function_exists('send_group_message')) {
    function send_group_message($conn, $group_id, $sender_id, $message) {
        $query = "INSERT INTO `group_messages` (group_id, sender_id, message) VALUES (?, ?, ?)";
        $stmt = $conn->prepare($query);
        if ($stmt === false) {
            error_log("Error preparing send_group_message statement: " . $conn->error);
            return false;
        }
        $stmt->bind_param("iis", $group_id, $sender_id, $message);
        $success = $stmt->execute();
        if (!$success) {
            error_log("Error executing send_group_message statement: " . $stmt->error);
        }
        $stmt->close();
        return $success;
    }
}

/**
 * Gets group chat messages for a specific group.
 *
 * @param mysqli $conn The database connection object.
 * @param int $group_id The ID of the group.
 * @param int $limit Optional. The maximum number of messages to return. Default is 50.
 * @param int $offset Optional. The offset for pagination (for older messages). Default is 0.
 * @return array An array of associative arrays, each representing a message.
 */
if (!function_exists('get_group_messages')) {
    function get_group_messages($conn, $group_id, $limit = 50, $offset = 0) {
        $query = "SELECT gm.*, u.username as sender_username, u.profile_image_path, u.prefers_avatar, u.gender
                  FROM `group_messages` gm
                  JOIN `users` u ON gm.sender_id = u.id
                  WHERE gm.group_id = ?
                  ORDER BY gm.sent_at DESC
                  LIMIT ? OFFSET ?"; // Order DESC to get latest first, then limit/offset for pagination
        $stmt = $conn->prepare($query);
        if ($stmt === false) {
            error_log("Error preparing get_group_messages statement: " . $conn->error);
            return [];
        }
        $stmt->bind_param("iii", $group_id, $limit, $offset);
        $stmt->execute();
        $result = $stmt->get_result();
        $messages = array_reverse($result->fetch_all(MYSQLI_ASSOC)); // Reverse to display oldest first for chat flow
        $stmt->close();
        return $messages;
    }
}

// --- Private Messaging Functions ---

/**
 * Sends a private message from one user to another.
 *
 * @param mysqli $conn The database connection object.
 * @param int $sender_id The ID of the user sending the message.
 * @param int $receiver_id The ID of the user receiving the message.
 * @param string $message The message content.
 * @return bool True on success, false on failure.
 */
if (!function_exists('send_private_message')) {
    function send_private_message($conn, $sender_id, $receiver_id, $message) {
        $query = "INSERT INTO `private_messages` (sender_id, receiver_id, message) VALUES (?, ?, ?)";
        $stmt = $conn->prepare($query);
        if ($stmt === false) {
            error_log("Error preparing send_private_message statement: " . $conn->error);
            return false;
        }
        $stmt->bind_param("iis", $sender_id, $receiver_id, $message);
        $success = $stmt->execute();
        if (!$success) {
            error_log("Error executing send_private_message statement: " . $stmt->error);
        }
        $stmt->close();
        return $success;
    }
}

/**
 * Gets private messages between two users, ordered by time.
 *
 * @param mysqli $conn The database connection object.
 * @param int $user1_id The ID of the first user.
 * @param int $user2_id The ID of the second user.
 * @param int $limit Optional. The maximum number of messages to return. Default is 50.
 * @param int $offset Optional. The offset for pagination. Default is 0.
 * @return array An array of associative arrays, each representing a private message.
 */
if (!function_exists('get_private_messages')) {
    function get_private_messages($conn, $user1_id, $user2_id, $limit = 50, $offset = 0) {
        $query = "SELECT pm.*, s.username as sender_username, r.username as receiver_username,
                         s.profile_image_path as sender_profile_image, s.prefers_avatar as sender_prefers_avatar, s.gender as sender_gender,
                         r.profile_image_path as receiver_profile_image, r.prefers_avatar as receiver_prefers_avatar, r.gender as receiver_gender
                  FROM `private_messages` pm
                  JOIN `users` s ON pm.sender_id = s.id
                  JOIN `users` r ON pm.receiver_id = r.id
                  WHERE (pm.sender_id = ? AND pm.receiver_id = ?) OR (pm.sender_id = ? AND pm.receiver_id = ?)
                  ORDER BY pm.sent_at ASC
                  LIMIT ? OFFSET ?";
        $stmt = $conn->prepare($query);
        if ($stmt === false) {
            error_log("Error preparing get_private_messages statement: " . $conn->error);
            return [];
        }
        $stmt->bind_param("iiiiii", $user1_id, $user2_id, $user2_id, $user1_id, $limit, $offset);
        $stmt->execute();
        $result = $stmt->get_result();
        $messages = $result->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        return $messages;
    }
}

/**
 * Gets a list of users with whom the current user has private message conversations.
 * Ordered by the last message exchanged.
 *
 * @param mysqli $conn The database connection object.
 * @param int $user_id The ID of the user.
 * @return array An array of associative arrays, each representing a conversation partner.
 */
if (!function_exists('get_user_conversations')) {
    function get_user_conversations($conn, $user_id) {
        $query = "
            SELECT
                CASE
                    WHEN pm.sender_id = ? THEN pm.receiver_id
                    ELSE pm.sender_id
                END AS conversation_partner_id,
                MAX(pm.sent_at) AS last_message_time,
                SUM(CASE WHEN pm.receiver_id = ? AND pm.is_read = 0 THEN 1 ELSE 0 END) AS unread_count
            FROM `private_messages` pm
            WHERE pm.sender_id = ? OR pm.receiver_id = ?
            GROUP BY conversation_partner_id
            ORDER BY last_message_time DESC
        ";
        $stmt = $conn->prepare($query);
        if ($stmt === false) {
            error_log("Error preparing get_user_conversations statement: " . $conn->error);
            return [];
        }
        $stmt->bind_param("iiii", $user_id, $user_id, $user_id, $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $conversations = $result->fetch_all(MYSQLI_ASSOC);
        $stmt->close();

        // Fetch user details for each conversation partner
        $full_conversations = [];
        foreach ($conversations as $conv) {
            $partner_id = $conv['conversation_partner_id'];
            $partner_details = get_user_by_id($conn, $partner_id); // Assuming get_user_by_id exists in your functions.php
            if ($partner_details) {
                $full_conversations[] = array_merge($conv, [
                    'partner_username' => $partner_details['username'],
                    'partner_profile_image_path' => $partner_details['profile_image_path'],
                    'partner_prefers_avatar' => $partner_details['prefers_avatar'],
                    'partner_gender' => $partner_details['gender'],
                ]);
            }
        }
        return $full_conversations;
    }
}

/**
 * Marks private messages sent to a specific user from another user as read.
 *
 * @param mysqli $conn The database connection object.
 * @param int $receiver_id The ID of the user who received the messages.
 * @param int $sender_id The ID of the user who sent the messages.
 * @return bool True on success, false on failure.
 */
if (!function_exists('mark_private_messages_read')) {
    function mark_private_messages_read($conn, $receiver_id, $sender_id) {
        $query = "UPDATE `private_messages` SET is_read = 1 WHERE receiver_id = ? AND sender_id = ? AND is_read = 0";
        $stmt = $conn->prepare($query);
        if ($stmt === false) {
            error_log("Error preparing mark_private_messages_read statement: " . $conn->error);
            return false;
        }
        $stmt->bind_param("ii", $receiver_id, $sender_id);
        $success = $stmt->execute();
        if (!$success) {
            error_log("Error executing mark_private_messages_read statement: " . $stmt->error);
        }
        $stmt->close();
        return $success;
    }
}

// --- Community Feed Functions ---

/**
 * Creates a new community feed post.
 *
 * @param mysqli $conn The database connection object.
 * @param int $user_id The ID of the user creating the post.
 * @param string $content The content of the post.
 * @param int|null $group_id Optional. The ID of the group this post belongs to.
 * @param string|null $image_path Optional. Path to an image for the post.
 * @return int|false The ID of the new post on success, false on failure.
 */
if (!function_exists('create_community_post')) {
    function create_community_post($conn, $user_id, $content, $group_id = null, $image_path = null) {
        $query = "INSERT INTO `community_feed_posts` (user_id, group_id, content, image_path) VALUES (?, ?, ?, ?)";
        $stmt = $conn->prepare($query);
        if ($stmt === false) {
            error_log("Error preparing create_community_post statement: " . $conn->error);
            return false;
        }
        $stmt->bind_param("iiss", $user_id, $group_id, $content, $image_path);
        if ($stmt->execute()) {
            $post_id = $stmt->insert_id;
            $stmt->close();
            return $post_id;
        } else {
            error_log("Error executing create_community_post statement: " . $stmt->error);
            $stmt->close();
            return false;
        }
    }
}

/**
 * Gets community feed posts. Can filter by group.
 *
 * @param mysqli $conn The database connection object.
 * @param int|null $group_id Optional. Filter posts by this group ID. Null for all public feed posts.
 * @param int $limit Optional. The maximum number of posts to return. Default is 10.
 * @param int $offset Optional. The offset for pagination. Default is 0.
 * @return array An array of associative arrays, each representing a community post.
 */
if (!function_exists('get_community_feed_posts')) {
    function get_community_feed_posts($conn, $group_id = null, $limit = 10, $offset = 0) {
        $where_clause = "WHERE 1=1";
        $params = [];
        $types = "";

        if ($group_id !== null) {
            $where_clause .= " AND cfp.group_id = ?";
            $params[] = $group_id;
            $types .= "i";
        } else {
            // For general feed, only show posts not associated with a specific group, or from public groups
            // This logic might need refinement based on how you define "general feed"
            $where_clause .= " AND (cfp.group_id IS NULL OR g.is_private = 0)";
        }

        $query = "SELECT cfp.*, u.username, u.profile_image_path, u.prefers_avatar, u.gender,
                         (SELECT COUNT(*) FROM community_feed_reactions cfr WHERE cfr.post_id = cfp.id AND cfr.reaction_type = 'like') as likes_count,
                         (SELECT COUNT(*) FROM community_feed_comments cfc WHERE cfc.post_id = cfp.id) as comments_count
                  FROM `community_feed_posts` cfp
                  JOIN `users` u ON cfp.user_id = u.id
                  LEFT JOIN `groups` g ON cfp.group_id = g.id
                  {$where_clause}
                  ORDER BY cfp.created_at DESC
                  LIMIT ? OFFSET ?";

        $params[] = $limit;
        $params[] = $offset;
        $types .= "ii";

        $stmt = $conn->prepare($query);
        if ($stmt === false) {
            error_log("Error preparing get_community_feed_posts statement: " . $conn->error);
            return [];
        }

        // Always use bind_param, but if $group_id is null, skip the first param
        if ($group_id !== null) {
            $stmt->bind_param($types, ...$params);
        } else {
            // Only limit and offset
            $stmt->bind_param("ii", $limit, $offset);
        }

        $stmt->execute();
        $result = $stmt->get_result();
        $posts = $result->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        return $posts;
    }
}

/**
 * Adds or updates a user's reaction to a community feed post.
 *
 * @param mysqli $conn The database connection object.
 * @param int $post_id The ID of the post.
 * @param int $user_id The ID of the user reacting.
 * @param string $reaction_type The type of reaction (e.g., 'like', 'love').
 * @return bool True on success, false on failure.
 */
if (!function_exists('add_community_post_reaction')) {
    function add_community_post_reaction($conn, $post_id, $user_id, $reaction_type) {
        // First, try to insert. If it's a duplicate, update.
        $query = "INSERT INTO `community_feed_reactions` (post_id, user_id, reaction_type) VALUES (?, ?, ?)
                  ON DUPLICATE KEY UPDATE reaction_type = VALUES(reaction_type), reacted_at = CURRENT_TIMESTAMP";
        $stmt = $conn->prepare($query);
        if ($stmt === false) {
            error_log("Error preparing add_community_post_reaction statement: " . $conn->error);
            return false;
        }
        $stmt->bind_param("iis", $post_id, $user_id, $reaction_type);
        $success = $stmt->execute();
        if (!$success) {
            error_log("Error executing add_community_post_reaction statement: " . $stmt->error);
        }
        $stmt->close();
        return $success;
    }
}

/**
 * Removes a user's reaction from a community feed post.
 *
 * @param mysqli $conn The database connection object.
 * @param int $post_id The ID of the post.
 * @param int $user_id The ID of the user whose reaction to remove.
 * @return bool True on success, false on failure.
 */
if (!function_exists('remove_community_post_reaction')) {
    function remove_community_post_reaction($conn, $post_id, $user_id) {
        $query = "DELETE FROM `community_feed_reactions` WHERE post_id = ? AND user_id = ?";
        $stmt = $conn->prepare($query);
        if ($stmt === false) {
            error_log("Error preparing remove_community_post_reaction statement: " . $conn->error);
            return false;
        }
        $stmt->bind_param("ii", $post_id, $user_id);
        $success = $stmt->execute();
        if (!$success) {
            error_log("Error executing remove_community_post_reaction statement: " . $stmt->error);
        }
        $stmt->close();
        return $success;
    }
}

/**
 * Gets the count of a specific reaction type for a community feed post.
 *
 * @param mysqli $conn The database connection object.
 * @param int $post_id The ID of the post.
 * @param string $reaction_type The type of reaction to count (e.g., 'like').
 * @return int The count of reactions.
 */
if (!function_exists('get_community_post_reactions_count')) {
    function get_community_post_reactions_count($conn, $post_id, $reaction_type) {
        $query = "SELECT COUNT(*) FROM `community_feed_reactions` WHERE post_id = ? AND reaction_type = ?";
        $stmt = $conn->prepare($query);
        if ($stmt === false) {
            error_log("Error preparing get_community_post_reactions_count statement: {$conn->error}");
            return 0;
        }
        $stmt->bind_param("is", $post_id, $reaction_type);
        $stmt->execute();
        $count = 0;
        $stmt->bind_result($count);
        $stmt->fetch();
        $stmt->close();
        return $count;
    }
}

/**
 * Adds a comment to a community feed post.
 *
 * @param mysqli $conn The database connection object.
 * @param int $post_id The ID of the post being commented on.
 * @param int $user_id The ID of the user making the comment.
 * @param string $comment_content The content of the comment.
 * @return bool True on success, false on failure.
 */
if (!function_exists('add_community_post_comment')) {
    function add_community_post_comment($conn, $post_id, $user_id, $comment_content) {
        $query = "INSERT INTO `community_feed_comments` (post_id, user_id, comment_content) VALUES (?, ?, ?)";
        $stmt = $conn->prepare($query);
        if ($stmt === false) {
            error_log("Error preparing add_community_post_comment statement: " . $conn->error);
            return false;
        }
        $stmt->bind_param("iis", $post_id, $user_id, $comment_content);
        $success = $stmt->execute();
        if (!$success) {
            error_log("Error executing add_community_post_comment statement: " . $stmt->error);
        }
        $stmt->close();
        return $success;
    }
}

/**
 * Gets comments for a specific community feed post.
 *
 * @param mysqli $conn The database connection object.
 * @param int $post_id The ID of the post.
 * @param int $limit Optional. The maximum number of comments to return. Default is 20.
 * @param int $offset Optional. The offset for pagination. Default is 0.
 * @return array An array of associative arrays, each representing a comment.
 */
if (!function_exists('get_community_post_comments')) {
    function get_community_post_comments($conn, $post_id, $limit = 20, $offset = 0) {
        $query = "SELECT cfc.*, u.username, u.profile_image_path, u.prefers_avatar, u.gender
                  FROM `community_feed_comments` cfc
                  JOIN `users` u ON cfc.user_id = u.id
                  WHERE cfc.post_id = ?
                  ORDER BY cfc.created_at ASC
                  LIMIT ? OFFSET ?";
        $stmt = $conn->prepare($query);
        if ($stmt === false) {
            error_log("Error preparing get_community_post_comments statement: " . $conn->error);
            return [];
        }
        $stmt->bind_param("iii", $post_id, $limit, $offset);
        $stmt->execute();
        $result = $stmt->get_result();
        $comments = $result->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        return $comments;
    }
}

// --- Games Functions ---

/**
 * Gets all available games.
 *
 * @param mysqli $conn The database connection object.
 * @return array An array of associative arrays, each representing a game.
 */
if (!function_exists('get_all_games')) {
    function get_all_games($conn) {
        $query = "SELECT * FROM `games` ORDER BY name ASC";
        $result = $conn->query($query);
        if ($result === false) {
            error_log("Error fetching all games: " . $conn->error);
            return [];
        }
        $games = $result->fetch_all(MYSQLI_ASSOC);
        return $games;
    }
}

/**
 * Gets details of a specific game by its ID.
 *
 * @param mysqli $conn The database connection object.
 * @param int $game_id The ID of the game.
 * @return array|null An associative array of game details, or null if not found.
 */
if (!function_exists('get_game_details')) {
    function get_game_details($conn, $game_id) {
        $query = "SELECT * FROM `games` WHERE id = ?";
        $stmt = $conn->prepare($query);
        if ($stmt === false) {
            error_log("Error preparing get_game_details statement: " . $conn->error);
            return null;
        }
        $stmt->bind_param("i", $game_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $game = $result->fetch_assoc();
        $stmt->close();
        return $game;
    }
}

/**
 * Creates a new game session.
 *
 * @param mysqli $conn The database connection object.
 * @param int $game_id The ID of the game being played.
 * @param int $creator_id The ID of the user creating the session.
 * @param int|null $group_id Optional. The ID of the group if played within a group.
 * @param array $initial_state Optional. The initial state of the game (e.g., empty Tic-Tac-Toe board).
 * @return int|false The ID of the new session on success, false on failure.
 */
if (!function_exists('create_game_session')) {
    function create_game_session($conn, $game_id, $creator_id, $group_id = null, $initial_state = []) {
        $initial_state_json = json_encode($initial_state);
        $query = "INSERT INTO `game_sessions` (game_id, creator_id, group_id, current_state, turn_user_id) VALUES (?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($query);
        if ($stmt === false) {
            error_log("Error preparing create_game_session statement: " . $conn->error);
            return false;
        }
        // Set initial turn to creator
        $stmt->bind_param("iiisi", $game_id, $creator_id, $group_id, $initial_state_json, $creator_id);
        if ($stmt->execute()) {
            $session_id = $stmt->insert_id;
            // Add creator as a player to the session
            add_game_session_player($conn, $session_id, $creator_id, true);
            $stmt->close();
            return $session_id;
        } else {
            error_log("Error executing create_game_session statement: " . $stmt->error);
            $stmt->close();
            return false;
        }
    }
}

/**
 * Adds a user as a player to a game session.
 *
 * @param mysqli $conn The database connection object.
 * @param int $session_id The ID of the game session.
 * @param int $user_id The ID of the user to add.
 * @param bool $is_host Whether the user is the host of the session.
 * @return bool True on success, false on failure.
 */
if (!function_exists('add_game_session_player')) {
    function add_game_session_player($conn, $session_id, $user_id, $is_host = false) {
        $query = "INSERT INTO `game_session_players` (session_id, user_id, is_host) VALUES (?, ?, ?)";
        $stmt = $conn->prepare($query);
        if ($stmt === false) {
            error_log("Error preparing add_game_session_player statement: " . $conn->error);
            return false;
        }
        $is_host_int = $is_host ? 1 : 0;
        $stmt->bind_param("iii", $session_id, $user_id, $is_host_int);
        $success = $stmt->execute();
        if (!$success) {
            error_log("Error executing add_game_session_player statement: " . $stmt->error);
        }
        $stmt->close();
        return $success;
    }
}

/**
 * Gets details of a specific game session, including players and game details.
 *
 * @param mysqli $conn The database connection object.
 * @param int $session_id The ID of the game session.
 * @return array|null An associative array of session details, or null if not found.
 */
if (!function_exists('get_game_session_details')) {
    function get_game_session_details($conn, $session_id) {
        $query = "SELECT gs.*, g.name as game_name, g.game_file, g.min_players, g.max_players,
                         u_creator.username as creator_username, u_turn.username as turn_username, u_winner.username as winner_username
                  FROM `game_sessions` gs
                  JOIN `games` g ON gs.game_id = g.id
                  JOIN `users` u_creator ON gs.creator_id = u_creator.id
                  LEFT JOIN `users` u_turn ON gs.turn_user_id = u_turn.id
                  LEFT JOIN `users` u_winner ON gs.winner_id = u_winner.id
                  WHERE gs.id = ?";
        $stmt = $conn->prepare($query);
        if ($stmt === false) {
            error_log("Error preparing get_game_session_details statement: " . $conn->error);
            return null;
        }
        $stmt->bind_param("i", $session_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $session = $result->fetch_assoc();
        $stmt->close();

        if ($session) {
            // Decode JSON state
            $session['current_state'] = json_decode($session['current_state'], true);
            // Fetch players for this session
            $session['players'] = [];
            $players_query = "SELECT u.id, u.username, u.profile_image_path, u.prefers_avatar, u.gender, gsp.is_host
                              FROM `game_session_players` gsp
                              JOIN `users` u ON gsp.user_id = u.id
                              WHERE gsp.session_id = ?";
            $players_stmt = $conn->prepare($players_query);
            if ($players_stmt === false) {
                error_log("Error preparing get_game_session_players statement: " . $conn->error);
            } else {
                $players_stmt->bind_param("i", $session_id);
                $players_stmt->execute();
                $players_result = $players_stmt->get_result();
                $session['players'] = $players_result->fetch_all(MYSQLI_ASSOC);
                $players_stmt->close();
            }
        }
        return $session;
    }
}

/**
 * Updates the state of a game session.
 *
 * @param mysqli $conn The database connection object.
 * @param int $session_id The ID of the game session.
 * @param array $new_state The new game state (will be JSON encoded).
 * @param int|null $turn_user_id Optional. The ID of the user whose turn it is next.
 * @param string|null $status Optional. The new status of the game ('in_progress', 'completed', 'cancelled').
 * @param int|null $winner_id Optional. The ID of the winner, if the game is completed.
 * @return bool True on success, false on failure.
 */
if (!function_exists('update_game_session_state')) {
    function update_game_session_state($conn, $session_id, $new_state, $turn_user_id = null, $status = null, $winner_id = null) {
        $new_state_json = json_encode($new_state);
        $query = "UPDATE `game_sessions` SET current_state = ?, turn_user_id = ?, status = ?, winner_id = ? WHERE id = ?";
        $stmt = $conn->prepare($query);
        if ($stmt === false) {
            error_log("Error preparing update_game_session_state statement: " . $conn->error);
            return false;
        }
        $stmt->bind_param("sisii", $new_state_json, $turn_user_id, $status, $winner_id, $session_id);
        $success = $stmt->execute();
        if (!$success) {
            error_log("Error executing update_game_session_state statement: " . $stmt->error);
        }
        $stmt->close();
        return $success;
    }
}

/**
 * Gets game sessions a user is involved in.
 *
 * @param mysqli $conn The database connection object.
 * @param int $user_id The ID of the user.
 * @param string|null $status Optional. Filter by session status (e.g., 'waiting', 'in_progress').
 * @return array An array of associative arrays, each representing a game session.
 */
if (!function_exists('get_user_game_sessions')) {
    function get_user_game_sessions($conn, $user_id, $status = null) {
        $query = "SELECT gs.*, g.name as game_name
                  FROM `game_sessions` gs
                  JOIN `game_session_players` gsp ON gs.id = gsp.session_id
                  JOIN `games` g ON gs.game_id = g.id
                  WHERE gsp.user_id = ?";
        $params = [$user_id];
        $types = "i";

        if ($status !== null) {
            $query .= " AND gs.status = ?";
            $params[] = $status;
            $types .= "s";
        }
        $query .= " ORDER BY gs.last_updated_at DESC";

        $stmt = $conn->prepare($query);
        if ($stmt === false) {
            error_log("Error preparing get_user_game_sessions statement: " . $conn->error);
            return [];
        }
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $result = $stmt->get_result();
        $sessions = $result->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        return $sessions;
    }
}

// Add other existing functions from your functions.php here if they are not already there
// e.g., sanitize_input, is_logged_in, is_admin, require_login, get_user_by_id, format_date, etc.
// Make sure to wrap them in if (!function_exists('function_name')) as well.

?>
