<?php
// Start output buffering to prevent "headers already sent" errors.
// This captures any output (including whitespace) before headers are sent.
ob_start();

// Enable error reporting for debugging. REMOVE OR SET TO 0 IN PRODUCTION.
ini_set('display_errors', 1);
error_reporting(E_ALL);

// Define a default response in case of very early errors
$response = ['success' => false, 'message' => 'An unknown error occurred.'];

try {
    // Use __DIR__ to get the current directory of follow_action.php
    // This ensures paths are correct regardless of the calling script's location
    $db_connection_path = __DIR__ . '/db_connection.php';
    $functions_path = __DIR__ . '/functions.php';

    if (!file_exists($db_connection_path)) {
        throw new Exception('Error: db_connection.php not found at ' . $db_connection_path);
    }
    require_once $db_connection_path;

    if (!file_exists($functions_path)) {
        throw new Exception('Error: functions.php not found at ' . $functions_path);
    }
    require_once $functions_path;

    // Verify database connection immediately
    if (!isset($conn) || $conn->connect_error) {
        throw new Exception("Database connection failed: " . ($conn->connect_error ?? "Connection object not set."));
    }

    // Set JSON header after all potential output-generating requires
    header('Content-Type: application/json');

    // Log session status and user ID for debugging
    error_log("follow_action.php accessed. Session status: " . session_status());
    error_log("Current user ID (from session): " . (isset($_SESSION['user_id']) ? $_SESSION['user_id'] : 'Not set'));


    if (!is_logged_in()) {
        $response['message'] = 'Login required to perform this action.';
        // No need for ob_end_clean() here, as it's handled in the finally block
        echo json_encode($response);
        exit();
    }

    $follower_id = $_SESSION['user_id'];
    $followed_id = isset($_POST['followed_id']) ? (int)$_POST['followed_id'] : 0;
    $action = isset($_POST['action']) ? sanitize_input($_POST['action']) : '';

    // Log received POST data for debugging
    error_log("POST Data: followed_id=" . $followed_id . ", action=" . $action . ", follower_id=" . $follower_id);


    if ($followed_id <= 0) {
        $response['message'] = 'Invalid user ID.';
        echo json_encode($response);
        exit();
    }

    if ($follower_id === $followed_id) {
        $response['message'] = 'You cannot follow or unfollow yourself.';
        echo json_encode($response);
        exit();
    }

    switch ($action) {
        case 'follow':
            $result = follow_user($conn, $follower_id, $followed_id);
            if ($result['success']) {
                $response['success'] = true;
                $response['message'] = 'User followed successfully.';
                $response['is_following'] = true;
                $response['follower_count'] = get_follower_count($conn, $followed_id);
                error_log("Follow success. Follower count: " . $response['follower_count']);
            } else {
                $response['message'] = $result['message'];
                error_log("Follow failed: " . $result['message']);
            }
            break;
        case 'unfollow':
            $result = unfollow_user($conn, $follower_id, $followed_id);
            if ($result['success']) {
                $response['success'] = true;
                $response['message'] = 'User unfollowed successfully.';
                $response['is_following'] = false;
                $response['follower_count'] = get_follower_count($conn, $followed_id);
                error_log("Unfollow success. Follower count: " . $response['follower_count']);
            } else {
                $response['message'] = $result['message'];
                error_log("Unfollow failed: " . $result['message']);
            }
            break;
        default:
            $response['message'] = 'Invalid action specified.';
            error_log("Invalid action: " . $action);
            break;
    }

} catch (Exception $e) {
    // Catch any exceptions thrown during execution
    $response['message'] = "Server error: " . $e->getMessage();
    error_log("Fatal error in follow_action.php: " . $e->getMessage() . " on line " . $e->getLine());
    // Ensure JSON header is sent even on error, if not already
    if (!headers_sent()) {
        header('Content-Type: application/json');
    }
} finally {
    // Ensure output buffer is cleaned and flushed
    while (ob_get_level() > 0) {
        ob_end_clean(); // Clean and discard all levels of output buffering
    }
    // Only echo if the script hasn't already exited
    if (!defined('EXIT_CALLED')) { // Define a flag to prevent double echo if exit() was called earlier
        echo json_encode($response);
    }
    if (isset($conn) && $conn instanceof mysqli) {
        $conn->close();
    }
}
?>
