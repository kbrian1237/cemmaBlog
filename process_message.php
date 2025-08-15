<?php
// process_message.php
// Handles saving messages from contact form and chatbot admin requests to the database.

// Include database connection
// Assuming 'includes/db_connect.php' exists and establishes $conn
require_once 'includes/db_connection.php'; 
require_once 'includes/functions.php'; // For sanitize_input and other helpers

header('Content-Type: application/json'); // Respond with JSON

$response = ['success' => false, 'message' => 'Invalid request.'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $sender_name = sanitize_input($_POST['sender_name'] ?? '');
    $sender_email = sanitize_input($_POST['sender_email'] ?? '');
    $message_content = sanitize_input($_POST['message_content'] ?? '');
    $message_type = sanitize_input($_POST['message_type'] ?? ''); // 'contact_form' or 'chatbot_admin_request'
    $priority = (int)($_POST['priority'] ?? 0); // 1 for contact, 2 for chatbot admin request
    $subject = sanitize_input($_POST['subject'] ?? null); // Only for contact form

    // Get user_id if logged in (for chatbot admin requests or logged-in contact users)
    $user_id = null;
    if (session_status() == PHP_SESSION_NONE) {
        session_start();
    }
    if (isset($_SESSION['user_id'])) {
        $user_id = $_SESSION['user_id'];
    }

    // Basic validation
    if (empty($sender_name) || empty($sender_email) || empty($message_content) || empty($message_type)) {
        $response['message'] = 'Missing required fields.';
        echo json_encode($response);
        exit();
    }

    if (!validate_email($sender_email)) {
        $response['message'] = 'Invalid email format.';
        echo json_encode($response);
        exit();
    }

    // Insert into database
    $stmt = $conn->prepare("INSERT INTO messages (sender_name, sender_email, user_id, subject, message_content, message_type, priority, status) VALUES (?, ?, ?, ?, ?, ?, ?, 'new')");
    
    if ($stmt) {
        // Corrected binding:
        // 's': sender_name (string)
        // 's': sender_email (string)
        // 'i': user_id (integer, can be null, but bind_param expects type)
        // 's': subject (string, can be null)
        // 's': message_content (string)
        // 's': message_type (string)
        // 'i': priority (integer)
        
        // For NULL values, you still need to pass a variable that holds NULL.
        // The 'i' type for user_id is fine, as bind_param handles NULL for integer types.
        // For subject, if it's null, we pass a variable that is null.
        $null_subject_val = $subject; // Use a new variable to ensure it's passed by reference correctly

        $stmt->bind_param("ssisssi", $sender_name, $sender_email, $user_id, $null_subject_val, $message_content, $message_type, $priority);
        
        if ($stmt->execute()) {
            $response['success'] = true;
            $response['message'] = 'Message saved successfully.';
        } else {
            $response['message'] = 'Database error: ' . $stmt->error;
        }
        $stmt->close();
    } else {
        $response['message'] = 'Database prepare error: ' . $conn->error;
    }
}

echo json_encode($response);

// Close database connection (assuming db_connect.php doesn't close it)
if ($conn) {
    $conn->close();
}
?>
