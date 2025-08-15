<?php
// dislike_post.php

ob_start(); // Start output buffering

session_start();
// Adjust path if necessary based on your file structure
require_once 'includes/db_connection.php'; 
require_once 'includes/functions.php'; // Corrected path to functions.php

// Set global error and exception handlers for consistent JSON output
set_exception_handler(function ($exception) {
    ob_clean();
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
        return false;
    }
    ob_clean();
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'message' => 'A PHP error occurred: ' . $message . ' in ' . $file . ' on line ' . $line,
        'severity' => $severity
    ]);
    exit();
}, E_ALL);


if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['post_id'])) {
    ob_clean(); // Clear any buffered output before sending JSON
    header('Content-Type: application/json');
    try {
        $post_id = (int)$_POST['post_id'];
        $user_id = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : 0;

        if (!$user_id) {
            echo json_encode(['success' => false, 'message' => 'Login required']);
            exit;
        }

        $action = '';
        if (user_disliked_post($conn, $post_id, $user_id)) {
            // User already disliked, so undislike
            $stmt = $conn->prepare("DELETE FROM dislikes WHERE post_id = ? AND user_id = ?");
            if (!$stmt) { throw new Exception("Prepare failed: " . $conn->error); }
            $stmt->bind_param("ii", $post_id, $user_id);
            $stmt->execute();
            $stmt->close();
            $action = 'undisliked';
        } else {
            // User wants to dislike, first check if they liked it, and if so, remove the like
            if (user_liked_post($conn, $post_id, $user_id)) {
                $stmt = $conn->prepare("DELETE FROM likes WHERE post_id = ? AND user_id = ?");
                if (!$stmt) { throw new Exception("Prepare failed (unlike): " . $conn->error); }
                $stmt->bind_param("ii", $post_id, $user_id);
                $stmt->execute();
                $stmt->close();
            }

            // Now, add the dislike
            $stmt = $conn->prepare("INSERT INTO dislikes (post_id, user_id) VALUES (?, ?)");
            if (!$stmt) { throw new Exception("Prepare failed (dislike): " . $conn->error); }
            $stmt->bind_param("ii", $post_id, $user_id);
            $stmt->execute();
            $stmt->close();
            $action = 'disliked';
        }

        // Get updated counts
        $total_dislikes = get_post_dislikes($conn, $post_id);
        $total_likes = get_post_likes($conn, $post_id); // Fetch updated like count as well

        echo json_encode([
            'success' => true,
            'action' => $action,
            'total_dislikes' => $total_dislikes,
            'total_likes' => $total_likes // Return updated like count
        ]);
        exit;
    } catch (Exception $e) {
        error_log("Error in dislike/undislike action: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Server error processing your request.']);
        exit;
    }
}

// Optionally, handle GET requests for AJAX dislike count
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['post_id'])) {
    ob_clean();
    header('Content-Type: application/json');
    try {
        $post_id = (int)$_GET['post_id'];
        // Use helper function from functions.php
        $total_dislikes = get_post_dislikes($conn, $post_id);
        // Corrected: Return total_dislikes under 'total_dislikes' key
        echo json_encode(['total_dislikes' => $total_dislikes]);
        exit;
    } catch (Exception $e) {
        error_log("Error in GET request for dislikes: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Server error fetching dislike count.']);
        exit;
    }
}

ob_end_flush(); // End output buffering
?>
