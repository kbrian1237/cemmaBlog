<?php
require_once 'includes/functions.php';
require_once 'includes/db_connection.php';

// Ensure user is logged in
require_login();

// Check if a post ID is provided and is a valid integer
if (!isset($_GET['id']) || !filter_var($_GET['id'], FILTER_VALIDATE_INT)) {
    // If no valid ID is provided, redirect or display an error
    header('Location: dashboard.php?error=no_post_id_provided');
    exit();
}

$post_id = (int)$_GET['id'];
$current_user_id = $_SESSION['user_id'];
$is_admin = isset($_SESSION['role']) && $_SESSION['role'] === 'admin';

// Fetch post details to verify ownership or admin status
$get_post_owner_query = "SELECT user_id FROM posts WHERE id = ?";
$stmt = $conn->prepare($get_post_owner_query);
$stmt->bind_param("i", $post_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    // Post not found
    header('Location: dashboard.php?error=post_not_found');
    exit();
}

$post = $result->fetch_assoc();
$post_owner_id = $post['user_id'];

// Check authorization: Admin can delete any post, regular user can only delete their own
if (!$is_admin && $current_user_id != $post_owner_id) {
    // Unauthorized attempt to delete post
    header('Location: dashboard.php?error=unauthorized_delete');
    exit();
}

// If authorized, proceed with deletion
try {
    // Start a transaction to ensure atomicity
    $conn->begin_transaction();

    // 1. Delete associated likes
    $delete_likes_query = "DELETE FROM likes WHERE post_id = ?";
    $stmt_likes = $conn->prepare($delete_likes_query);
    $stmt_likes->bind_param("i", $post_id);
    $stmt_likes->execute();
    $stmt_likes->close();

    // 2. Delete associated dislikes
    $delete_dislikes_query = "DELETE FROM dislikes WHERE post_id = ?";
    $stmt_dislikes = $conn->prepare($delete_dislikes_query);
    $stmt_dislikes->bind_param("i", $post_id);
    $stmt_dislikes->execute();
    $stmt_dislikes->close();

    // 3. Delete associated comments
    $delete_comments_query = "DELETE FROM comments WHERE post_id = ?";
    $stmt_comments = $conn->prepare($delete_comments_query);
    $stmt_comments->bind_param("i", $post_id);
    $stmt_comments->execute();
    $stmt_comments->close();

    // 4. Delete associated post_tags
    $delete_post_tags_query = "DELETE FROM post_tags WHERE post_id = ?";
    $stmt_post_tags = $conn->prepare($delete_post_tags_query);
    $stmt_post_tags->bind_param("i", $post_id);
    $stmt_post_tags->execute();
    $stmt_post_tags->close();
    
    // 5. Get image path for deletion (if exists)
    $get_image_path_query = "SELECT image_path FROM posts WHERE id = ?";
    $stmt_get_image = $conn->prepare($get_image_path_query);
    $stmt_get_image->bind_param("i", $post_id);
    $stmt_get_image->execute();
    $image_result = $stmt_get_image->get_result();
    $image_path_row = $image_result->fetch_assoc();
    $image_to_delete = null;
    if ($image_path_row && !empty($image_path_row['image_path'])) {
        $image_to_delete = $image_path_row['image_path'];
    }
    $stmt_get_image->close();

    // 6. Delete the post itself
    $delete_post_query = "DELETE FROM posts WHERE id = ?";
    $stmt_delete_post = $conn->prepare($delete_post_query);
    $stmt_delete_post->bind_param("i", $post_id);
    $stmt_delete_post->execute();
    $deleted_rows = $stmt_delete_post->affected_rows;
    $stmt_delete_post->close();

    if ($deleted_rows > 0) {
        // If post was successfully deleted, delete the image file if it's a local file
        if ($image_to_delete && !filter_var($image_to_delete, FILTER_VALIDATE_URL)) {
            $full_image_path = __DIR__ . '/uploads/' . basename($image_to_delete);
            if (file_exists($full_image_path)) {
                unlink($full_image_path); // Delete the file
            }
        }
        $conn->commit(); // Commit the transaction
        $_SESSION['success_message'] = "Post and all related data deleted successfully.";
    } else {
        $conn->rollback(); // Rollback if no rows were affected by post deletion
        $_SESSION['error_message'] = "Failed to delete post or post not found.";
    }

} catch (Exception $e) {
    $conn->rollback(); // Rollback on any exception
    $_SESSION['error_message'] = "Database error: " . $e->getMessage();
}

$conn->close();

// Redirect back to the dashboard (or the page where the delete action was initiated)
header('Location: dashboard.php');
exit();
?>
