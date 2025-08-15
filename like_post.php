<?php
// like_post.php

// Start output buffering to prevent any premature output
ob_start();

session_start();
require_once 'includes/db_connection.php'; // Assumes you have a db_connect.php for $conn
require_once 'includes/functions.php'; // Include functions for get_post_dislikes, user_disliked_post, etc.

// Set a default error handler to catch unexpected PHP errors and return JSON
set_exception_handler(function ($exception) {
    ob_clean(); // Clear any buffered output before sending JSON
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'message' => 'An unexpected server error occurred: ' . $exception->getMessage(),
        'error_code' => $exception->getCode()
    ]);
    exit();
});

set_error_handler(function ($severity, $message, $file, $line) {
    if (!(error_reporting() & $severity)) {
        // This error code is not included in error_reporting
        return false;
    }
    ob_clean(); // Clear any buffered output before sending JSON
    // For general errors, return JSON
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'message' => 'A PHP error occurred: ' . $message . ' in ' . $file . ' on line ' . $line,
        'severity' => $severity
    ]);
    exit();
}, E_ALL);


// Handle like/unlike action
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['post_id'])) {
    // Clear any buffered output before sending JSON
    ob_clean();
    header('Content-Type: application/json'); // Ensure header is always sent for JSON response
    try {
        $post_id = (int)$_POST['post_id'];
        $user_id = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : 0;

        if (!$user_id) {
            echo json_encode(['success' => false, 'message' => 'Login required']);
            exit;
        }

        $action = '';
        if (user_liked_post($conn, $post_id, $user_id)) {
            // User already liked, so unlike
            $stmt = $conn->prepare("DELETE FROM likes WHERE post_id = ? AND user_id = ?");
            if (!$stmt) { throw new Exception("Prepare failed: " . $conn->error); }
            $stmt->bind_param("ii", $post_id, $user_id);
            $stmt->execute();
            $stmt->close();
            $action = 'unliked';
        } else {
            // User wants to like, first check if they disliked it, and if so, remove the dislike
            if (user_disliked_post($conn, $post_id, $user_id)) {
                $stmt = $conn->prepare("DELETE FROM dislikes WHERE post_id = ? AND user_id = ?");
                if (!$stmt) { throw new Exception("Prepare failed (undislike): " . $conn->error); }
                $stmt->bind_param("ii", $post_id, $user_id);
                $stmt->execute();
                $stmt->close();
            }

            // Now, add the like
            $stmt = $conn->prepare("INSERT INTO likes (post_id, user_id) VALUES (?, ?)");
            if (!$stmt) { throw new Exception("Prepare failed (like): " . $conn->error); }
            $stmt->bind_param("ii", $post_id, $user_id);
            $stmt->execute();
            $stmt->close();
            $action = 'liked';
        }

        // Get updated counts
        $total_likes = get_post_likes($conn, $post_id);
        $total_dislikes = get_post_dislikes($conn, $post_id); // Fetch updated dislike count as well

        echo json_encode([
            'success' => true,
            'action' => $action,
            'total_likes' => $total_likes,
            'total_dislikes' => $total_dislikes // Return updated dislike count
        ]);
        exit;
    } catch (Exception $e) {
        error_log("Error in like/unlike action: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Server error processing your request.']);
        exit;
    }
}

// Optionally, handle GET requests for AJAX like count
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['post_id'])) {
    // Clear any buffered output before sending JSON
    ob_clean();
    header('Content-Type: application/json'); // Ensure header is always sent for JSON response
    try {
        $post_id = (int)$_GET['post_id'];
        $total_likes = get_post_likes($conn, $post_id);
        echo json_encode(['total_likes' => $total_likes]);
        exit;
    } catch (Exception $e) {
        error_log("Error in GET request for likes: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Server error fetching like count.']);
        exit;
    }
}

// End output buffering for regular script execution if no early exit occurred
ob_end_flush();
?>
