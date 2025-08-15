<?php
header('Content-Type: application/json');
require_once 'includes/functions.php';

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit();
}

// PDO connection setup
$host = 'localhost';
$db   = 'blog_db'; // Use your database name
$user = 'root';
$pass = ''; // Your DB password if any
$charset = 'utf8mb4';
$dsn = "mysql:host=$host;dbname=$db;charset=$charset";

try {
    $pdo = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
    ]);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database connection failed.']);
    exit();
}

// Initialize response
$response = ['success' => false, 'message' => '', 'comment_html' => ''];

try {
    // Get and validate input data
    $post_id = isset($_POST['post_id']) ? (int)$_POST['post_id'] : 0;
    $comment_content = isset($_POST['comment_content']) ? sanitize_input($_POST['comment_content']) : '';
    $parent_comment_id = isset($_POST['parent_comment_id']) && !empty($_POST['parent_comment_id']) ? (int)$_POST['parent_comment_id'] : null;

    // Validation
    if ($post_id <= 0) {
        $response['message'] = 'Invalid post ID.';
        echo json_encode($response);
        exit();
    }

    if (empty($comment_content)) {
        $response['message'] = 'Comment content is required.';
        echo json_encode($response);
        exit();
    }

    if (strlen($comment_content) > 1000) {
        $response['message'] = 'Comment is too long. Maximum 1000 characters allowed.';
        echo json_encode($response);
        exit();
    }

    // Check if post exists and is published
    $post_check_stmt = $pdo->prepare("SELECT id, title FROM posts WHERE id = ? AND status = 'published'");
    $post_check_stmt->execute([$post_id]);
    $post = $post_check_stmt->fetch(PDO::FETCH_ASSOC);

    if (!$post) {
        $response['message'] = 'Post not found or not published.';
        echo json_encode($response);
        exit();
    }

    // Check if parent comment exists (if replying)
    if ($parent_comment_id !== null) {
        $parent_check_stmt = $pdo->prepare("SELECT id FROM comments WHERE id = ? AND post_id = ?");
        $parent_check_stmt->execute([$parent_comment_id, $post_id]);
        if (!$parent_check_stmt->fetch(PDO::FETCH_ASSOC)) {
            $response['message'] = 'Parent comment not found.';
            echo json_encode($response);
            exit();
        }
    }

    // Prepare comment data
    $user_id = null;
    $author_name = null;
    $author_email = null;

    if (is_logged_in()) {
        // Logged in user comment
        $user_id = $_SESSION['user_id'];
        $user_data = get_user_by_id_pdo($pdo, $user_id);
        $author_name = $user_data['username'];
    } else {
        // Guest comment
        $author_name = isset($_POST['author_name']) ? sanitize_input($_POST['author_name']) : '';
        $author_email = isset($_POST['author_email']) ? sanitize_input($_POST['author_email']) : '';

        if (empty($author_name) || empty($author_email)) {
            $response['message'] = 'Name and email are required for guest comments.';
            echo json_encode($response);
            exit();
        }

        if (!validate_email($author_email)) {
            $response['message'] = 'Please enter a valid email address.';
            echo json_encode($response);
            exit();
        }

        if (strlen($author_name) > 100) {
            $response['message'] = 'Name is too long. Maximum 100 characters allowed.';
            echo json_encode($response);
            exit();
        }
    }

    // Rate limiting check (prevent spam)
    if ($user_id) {
        $rate_limit_stmt = $pdo->prepare("SELECT COUNT(*) as count FROM comments WHERE user_id = ? AND created_at > DATE_SUB(NOW(), INTERVAL 1 MINUTE)");
        $rate_limit_stmt->execute([$user_id]);
        $rate_limit_count = $rate_limit_stmt->fetch(PDO::FETCH_ASSOC)['count'];
        if ($rate_limit_count >= 3) {
            $response['message'] = 'You are posting comments too quickly. Please wait a moment.';
            echo json_encode($response);
            exit();
        }
    } else {
        $rate_limit_stmt = $pdo->prepare("SELECT COUNT(*) as count FROM comments WHERE author_email = ? AND created_at > DATE_SUB(NOW(), INTERVAL 2 MINUTE)");
        $rate_limit_stmt->execute([$author_email]);
        $rate_limit_count = $rate_limit_stmt->fetch(PDO::FETCH_ASSOC)['count'];
        if ($rate_limit_count >= 2) {
            $response['message'] = 'You are posting comments too quickly. Please wait a moment.';
            echo json_encode($response);
            exit();
        }
    }

    // Insert comment
    if ($user_id) {
        $stmt = $pdo->prepare("INSERT INTO comments (post_id, user_id, content, parent_comment_id, status) VALUES (?, ?, ?, ?, 'approved')");
        $stmt->execute([$post_id, $user_id, $comment_content, $parent_comment_id]);
    } else {
        $stmt = $pdo->prepare("INSERT INTO comments (post_id, author_name, author_email, content, parent_comment_id, status) VALUES (?, ?, ?, ?, ?, 'approved')");
        $stmt->execute([$post_id, $author_name, $author_email, $comment_content, $parent_comment_id]);
    }
    $comment_id = $pdo->lastInsertId();

    // Auto-approve comments from admin users
    if ($user_id && is_admin()) {
        $approve_stmt = $pdo->prepare("UPDATE comments SET status = 'approved' WHERE id = ?");
        $approve_stmt->execute([$comment_id]);
        $response['comment_html'] = generate_comment_html($comment_id, $author_name, $comment_content, date('Y-m-d H:i:s'), $parent_comment_id);
        $response['message'] = 'Comment posted successfully!';
    } else {
        $response['message'] = 'Comment submitted successfully! It will be visible after moderation.';
    }

    $response['success'] = true;

    // Send email notification to admin (optional)
    // if (!$user_id || !is_admin()) {
    //     // mail('admin@example.com', 'New Comment Pending Approval', 'A new comment has been submitted...');
    // }

} catch (Exception $e) {
    error_log('Comment submission error: ' . $e->getMessage());
    $response['message'] = 'An error occurred while submitting your comment. Please try again.';
}

echo json_encode($response);

// Helper for PDO user fetch
function get_user_by_id_pdo($pdo, $user_id) {
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

// Function to generate comment HTML for immediate display
function generate_comment_html($comment_id, $author_name, $content, $created_at, $parent_comment_id = null) {
    $formatted_date = format_datetime($created_at);
    $escaped_content = nl2br(htmlspecialchars($content));
    $escaped_author = htmlspecialchars($author_name);

    $comment_class = $parent_comment_id ? 'comment comment-reply' : 'comment';

    return "
    <div class='$comment_class' id='comment-$comment_id'>
        <div class='comment-header'>
            <span class='comment-author'>$escaped_author</span>
            <span class='comment-date'>$formatted_date</span>
            <span class='comment-status'><span class='tag tag-success'>Approved</span></span>
        </div>
        <div class='comment-content'>
            $escaped_content
        </div>
        <div class='comment-actions'>
            <button type='button' class='reply-btn' data-comment-id='$comment_id'>
                <i class='fas fa-reply'></i> Reply
            </button>
        </div>
    </div>";
}
?>
