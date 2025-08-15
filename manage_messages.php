<?php
ob_start(); // Start output buffering
$page_title = "Manage Messages";
include 'includes/header.php';
require_once 'includes/functions.php'; // Ensure functions are available
require_once 'includes/db_connection.php'; // Ensure db connection is available

// Require admin access
require_admin();

// Handle message status update or delete action
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['message_id'])) {
    $message_id = (int)$_POST['message_id'];

    if (isset($_POST['action'])) {
        $action = $_POST['action'];

        if ($action == 'update_status_ajax') {
            // Handle AJAX status update from dropdown
            $new_status = sanitize_input($_POST['new_status']);
            $allowed_statuses = ['new', 'read', 'responded'];
            if (in_array($new_status, $allowed_statuses)) {
                $update_query = "UPDATE messages SET status = ?, responded_at = NOW() WHERE id = ?";
                $stmt = $conn->prepare($update_query);
                $stmt->bind_param("si", $new_status, $message_id);
                if ($stmt->execute()) {
                    echo json_encode(['success' => true, 'message' => 'Message status updated.']);
                } else {
                    echo json_encode(['success' => false, 'message' => 'DB Error: ' . $conn->error]);
                }
                $stmt->close();
            } else {
                echo json_encode(['success' => false, 'message' => 'Invalid status.']);
            }
            exit(); // Important for AJAX requests
        } elseif ($action == 'delete') {
            $delete_query = "DELETE FROM messages WHERE id = ?";
            $stmt = $conn->prepare($delete_query);
            $stmt->bind_param("i", $message_id);
            if ($stmt->execute()) {
                $_SESSION['message'] = "Message deleted successfully!";
                $_SESSION['message_type'] = "success";
            } else {
                $_SESSION['message'] = "Error deleting message: " . htmlspecialchars($conn->error);
                $_SESSION['message_type'] = "error";
            }
            $stmt->close();
        }
    }
    // Redirect only for non-AJAX POSTs (e.g., delete action)
    header("Location: manage_messages.php");
    exit();
}

// Fetch messages for display
$filter_status = sanitize_input($_GET['status'] ?? 'all');
$filter_type = sanitize_input($_GET['type'] ?? 'all');
$search_query_text = sanitize_input($_GET['search'] ?? '');

$sql_base = "FROM messages WHERE 1=1";
$params = [];
$param_types = "";

if ($filter_status !== 'all') {
    $sql_base .= " AND status = ?";
    $params[] = $filter_status;
    $param_types .= "s";
}

if ($filter_type !== 'all') {
    $sql_base .= " AND message_type = ?";
    $params[] = $filter_type;
    $param_types .= "s";
}

if (!empty($search_query_text)) {
    $sql_base .= " AND (sender_name LIKE ? OR sender_email LIKE ? OR message_content LIKE ? OR subject LIKE ?)";
    $search_term = '%' . $search_query_text . '%';
    $params[] = $search_term;
    $params[] = $search_term;
    $params[] = $search_term;
    $params[] = $search_term;
    $param_types .= "ssss";
}

// Pagination for Messages
$messages_per_page = 10;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $messages_per_page;

// Get total count of messages with current filters
$total_messages_query = "SELECT COUNT(*) as total " . $sql_base;
$total_stmt = $conn->prepare($total_messages_query);
if ($total_stmt) {
    if (!empty($params)) {
        $total_stmt->bind_param($param_types, ...$params);
    }
    $total_stmt->execute();
    $total_messages = $total_stmt->get_result()->fetch_assoc()['total'];
    $total_stmt->close();
} else {
    $total_messages = 0;
    $_SESSION['message'] = "Database query preparation failed for total count: " . htmlspecialchars($conn->error);
    $_SESSION['message_type'] = "error";
}
$total_pages = ceil($total_messages / $messages_per_page);


// Fetch messages for display with pagination
$sql = "SELECT id, sender_name, sender_email, subject, message_content, message_type, priority, status, created_at " . $sql_base;
$sql .= " ORDER BY priority DESC, created_at DESC LIMIT ? OFFSET ?";

$stmt = $conn->prepare($sql);

if ($stmt) {
    // Add pagination parameters to the existing parameters
    $params[] = $messages_per_page;
    $params[] = $offset;
    $param_types .= "ii"; // Add types for limit and offset

    $stmt->bind_param($param_types, ...$params);
    $stmt->execute();
    $messages_result = $stmt->get_result();
} else {
    $messages_result = false;
    $_SESSION['message'] = "Database query preparation failed: " . htmlspecialchars($conn->error);
    $_SESSION['message_type'] = "error";
}

?>

<!-- Floating Back to Dashboard Button -->
<a href="admin_dashboard.php" class="floating-btn" title="Back to Dashboard">
    <i class="fas fa-arrow-left"></i>
</a>
<style>
.floating-btn {
    position: fixed;
    bottom: 32px;
    right: 32px;
    background: var(--primary-color);
    color: #fff;
    border-radius: 50%;
    width: 56px;
    height: 56px;
    display: flex;
    align-items: center;
    justify-content: center;
    box-shadow: var(--shadow-medium);
    font-size: 1.5rem;
    z-index: 1000;
    transition: background 0.2s, box-shadow 0.2s;
    text-decoration: none;
}
.floating-btn:hover {
    background: var(--primary-dark);
    box-shadow: var(--shadow-hover);
    color: #fff;
}
</style>

<div class="container">
    <div class="admin-header mb-4">
        <h1><i class="fas fa-envelope"></i> Manage Messages</h1>
        <p class="text-muted">Review and manage messages from the contact form and chatbot.</p>
    </div>

    <?php if (isset($_SESSION['message'])): ?>
        <div class="alert alert-<?php echo $_SESSION['message_type']; ?>">
            <?php echo $_SESSION['message']; ?>
        </div>
        <?php
        unset($_SESSION['message']);
        unset($_SESSION['message_type']);
        ?>
    <?php endif; ?>

    <div class="card mb-4">
        <div class="card-header">
            <h3>Filter Messages</h3>
        </div>
        <div class="card-body">
            <form action="manage_messages.php" method="GET" class="form-inline">
                <div class="form-group me-3">
                    <label for="status_filter" class="form-label visually-hidden">Status:</label>
                    <select name="status" id="status_filter" class="form-select-inline">
                        <option value="all" <?php echo ($filter_status === 'all') ? 'selected' : ''; ?>>All Statuses</option>
                        <option value="new" <?php echo ($filter_status === 'new') ? 'selected' : ''; ?>>New</option>
                        <option value="read" <?php echo ($filter_status === 'read') ? 'selected' : ''; ?>>Read</option>
                        <option value="responded" <?php echo ($filter_status === 'responded') ? 'selected' : ''; ?>>Responded</option>
                    </select>
                </div>
                <div class="form-group me-3">
                    <label for="type_filter" class="form-label visually-hidden">Type:</label>
                    <select name="type" id="type_filter" class="form-select-inline">
                        <option value="all" <?php echo ($filter_type === 'all') ? 'selected' : ''; ?>>All Types</option>
                        <option value="contact_form" <?php echo ($filter_type === 'contact_form') ? 'selected' : ''; ?>>Contact Form</option>
                        <option value="chatbot_admin_request" <?php echo ($filter_type === 'chatbot_admin_request') ? 'selected' : ''; ?>>Chatbot Admin Request</option>
                        <option value="chatbot_inquiry" <?php echo ($filter_type === 'chatbot_inquiry') ? 'selected' : ''; ?>>Chatbot Inquiry</option>
                    </select>
                </div>
                <div class="form-group me-3">
                    <label for="search_query" class="form-label visually-hidden">Search:</label>
                    <input type="text" name="search" id="search_query" class="form-input-inline" placeholder="Search messages..." value="<?php echo htmlspecialchars($search_query_text); ?>">
                </div>
                <button type="submit" class="btn btn-primary btn-sm"><i class="fas fa-filter"></i> Filter</button>
                <a href="manage_messages.php" class="btn btn-secondary btn-sm ms-2"><i class="fas fa-sync"></i> Reset</a>
            </form>
        </div>
    </div>

    <div class="card mb-4">
        <div class="card-header">
            <h3>All Messages</h3>
        </div>
        <div class="card-body">
            <?php if ($messages_result && $messages_result->num_rows > 0): ?>
                <div class="table-responsive">
                    <table class="admin-table">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Sender</th>
                                <th>Email</th>
                                <th>Type</th>
                                <th>Subject/Content Preview</th>
                                <th>Priority</th>
                                <th>Status</th>
                                <th>Date</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($message = $messages_result->fetch_assoc()): ?>
                                <tr>
                                    <td data-label="ID"><?php echo htmlspecialchars($message['id']); ?></td>
                                    <td data-label="Sender"><?php echo htmlspecialchars($message['sender_name']); ?></td>
                                    <td data-label="Email"><?php echo htmlspecialchars($message['sender_email']); ?></td>
                                    <td data-label="Type">
                                        <?php 
                                            if ($message['message_type'] === 'contact_form') {
                                                echo '<span class="badge-type badge-contact"><i class="fas fa-inbox"></i> Contact</span>';
                                            } elseif ($message['message_type'] === 'chatbot_admin_request') {
                                                echo '<span class="badge-type badge-admin-request"><i class="fas fa-robot"></i> Admin Request</span>';
                                            } elseif ($message['message_type'] === 'chatbot_inquiry') {
                                                echo '<span class="badge-type badge-inquiry"><i class="fas fa-comment-dots"></i> Inquiry</span>';
                                            }
                                        ?>
                                    </td>
                                    <td data-label="Subject/Content Preview" class="message-preview">
                                        <?php 
                                        if ($message['message_type'] === 'contact_form' && !empty($message['subject'])) {
                                            echo '<strong>Subject:</strong> ' . htmlspecialchars(truncate_text($message['subject'], 50)) . '<br>';
                                            echo truncate_text(htmlspecialchars($message['message_content']), 50);
                                        } else {
                                            echo htmlspecialchars(truncate_text($message['message_content'], 100));
                                        }
                                        ?>
                                    </td>
                                    <td data-label="Priority">
                                        <?php 
                                            if ($message['priority'] == 2) {
                                                echo '<span class="badge-priority badge-high-priority">High</span>';
                                            } elseif ($message['priority'] == 1) {
                                                echo '<span class="badge-priority badge-medium-priority">Medium</span>';
                                            } else {
                                                echo '<span class="badge-priority badge-low-priority">Low</span>';
                                            }
                                        ?>
                                    </td>
                                    <td data-label="Status">
                                        <select class="message-status-select form-select" data-message-id="<?php echo $message['id']; ?>">
                                            <option value="new" <?php echo ($message['status'] == 'new') ? 'selected' : ''; ?>>New</option>
                                            <option value="read" <?php echo ($message['status'] == 'read') ? 'selected':''; ?>>Read</option>
                                            <option value="responded" <?php echo ($message['status'] == 'responded') ? 'selected' : ''; ?>>Responded</option>
                                        </select>
                                    </td>
                                    <td data-label="Date"><?php echo format_datetime($message['created_at']); ?></td>
                                    <td class="actions" data-label="Actions">
                                        <button type="button" class="btn btn-sm btn-info view-message-btn" data-message-id="<?php echo $message['id']; ?>" title="View Full Message">
                                            <i class="fas fa-eye"></i> View
                                        </button>
                                        <form action="manage_messages.php" method="POST" style="display:inline-block;">
                                            <input type="hidden" name="message_id" value="<?php echo $message['id']; ?>">
                                            <button type="submit" name="action" value="delete" class="btn btn-sm btn-danger" title="Delete" onclick="return confirm('Are you sure you want to delete this message?');">
                                                <i class="fas fa-trash"></i> Delete
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
                <?php
                // Pagination for Messages
                $base_url = 'manage_messages.php';
                // Preserve existing GET parameters like filters/search
                $current_query_params = $_GET;
                unset($current_query_params['page']); // Remove 'page' to rebuild it
                $base_url .= '?' . http_build_query($current_query_params);
                include 'pagination_snippet.php'; // Include the pagination snippet
                ?>
            <?php else: ?>
                <p class="text-muted">No messages found matching your criteria.</p>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Message View Modal -->
<div id="messageViewModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3 id="modalMessageSubject">Message Details</h3>
            <span class="close-button">&times;</span>
        </div>
        <div class="modal-body">
            <p><strong>From:</strong> <span id="modalSenderName"></span> (<span id="modalSenderEmail"></span>)</p>
            <p><strong>Type:</strong> <span id="modalMessageType"></span></p>
            <p><strong>Date:</strong> <span id="modalMessageDate"></span></p>
            <hr>
            <p><strong>Message:</strong></p>
            <p id="modalMessageContent"></p>
        </div>
        <div class="modal-footer">
            <button class="btn btn-secondary close-button">Close</button>
        </div>
    </div>
</div>

<style>
    /* Admin Table Styles (copied from manage_posts.php for consistency) */
    .admin-table {
        width: 100%;
        border-collapse: collapse;
        margin-top: 1.5rem;
        background: var(--background-white);
        color: var(--text-color);
        box-shadow: var(--shadow-light);
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
        text-transform: uppercase;
        font-size: 0.85rem;
    }

    .admin-table tbody tr:hover {
        background-color: var(--background-light);
    }

    .admin-table .actions .btn {
        margin-right: 5px;
    }

    /* Form Inline for Filters */
    .form-inline {
        display: flex;
        flex-wrap: wrap;
        gap: 1rem;
        align-items: center;
    }
    .form-group.me-3 {
        margin-right: 1rem; /* Spacing between form groups */
    }
    .form-select-inline, .form-input-inline {
        padding: 8px 12px;
        border: 1px solid var(--border-color);
        border-radius: 8px;
        background-color: var(--background-white);
        font-size: 0.95rem;
        color: var(--text-color);
    }
    .form-input-inline {
        min-width: 150px; /* Ensure search input has some width */
    }
    .form-select-inline:focus, .form-input-inline:focus {
        border-color: var(--primary-color);
        outline: 0;
        box-shadow: 0 0 0 0.2rem rgba(52, 152, 219, 0.25);
    }
    .visually-hidden {
        position: absolute;
        width: 1px;
        height: 1px;
        margin: -1px;
        padding: 0;
        overflow: hidden;
        clip: rect(0, 0, 0, 0);
        border: 0;
    }

    /* Badge styles for message types and priorities */
    .badge-type, .badge-priority {
        display: inline-flex;
        align-items: center;
        padding: 4px 8px;
        border-radius: 5px;
        font-size: 0.75rem;
        font-weight: 600;
        margin-right: 5px;
        gap: 5px;
    }
    .badge-contact { background-color: #e6ffe6; color: #1e8449; border: 1px solid #1e8449; } /* Light green */
    .badge-admin-request { background-color: #e0f2ff; color: #2196f3; border: 1px solid #2196f3; } /* Light blue */
    .badge-inquiry { background-color: #fff3e0; color: #ff9800; border: 1px solid #ff9800; } /* Light orange */

    .badge-high-priority { background-color: #ffe6e6; color: #dc3545; border: 1px solid #dc3545; } /* Light red */
    .badge-medium-priority { background-color: #fffde7; color: #ffc107; border: 1px solid #ffc107; } /* Light yellow */
    .badge-low-priority { background-color: #e0e0e0; color: #6c757d; border: 1px solid #6c757d; } /* Light grey */

    /* Modal Styles */
    .modal {
        display: none; /* Hidden by default */
        position: fixed; /* Stay in place */
        z-index: 2000; /* Sit on top */
        left: 0;
        top: 0;
        width: 100%; /* Full width */
        height: 100%; /* Full height */
        overflow: auto; /* Enable scroll if needed */
        background-color: rgba(0,0,0,0.6); /* Black w/ opacity */
        justify-content: center;
        align-items: center;
    }

    .modal-content {
        background-color: var(--background-white);
        margin: auto;
        padding: 0;
        border-radius: 12px;
        box-shadow: 0 4px 20px rgba(0,0,0,0.4);
        width: 90%;
        max-width: 700px;
        animation-name: animatetop;
        animation-duration: 0.4s;
        display: flex;
        flex-direction: column;
        max-height: 90vh; /* Limit height for scrollable content */
    }

    .modal-header {
        padding: 15px 20px;
        background: linear-gradient(135deg, var(--primary-color) 0%, var(--primary-dark) 100%);
        color: white;
        border-top-left-radius: 10px;
        border-top-right-radius: 10px;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }

    .modal-header h3 {
        margin: 0;
        color: white;
    }

    .modal-body {
        padding: 20px;
        overflow-y: auto; /* Make body scrollable */
        flex-grow: 1;
        color: var(--text-color);
    }

    .modal-body p {
        margin-bottom: 10px;
        line-height: 1.6;
    }

    .modal-footer {
        padding: 15px 20px;
        border-top: 1px solid var(--border-color);
        text-align: right;
        background-color: var(--background-light);
        border-bottom-left-radius: 10px;
        border-bottom-right-radius: 10px;
    }

    .close-button {
        color: white;
        font-size: 28px;
        font-weight: bold;
        cursor: pointer;
        background: none;
        border: none;
        outline: none;
        padding: 0;
    }

    .close-button:hover,
    .close-button:focus {
        color: #ddd;
        text-decoration: none;
        cursor: pointer;
    }

    @keyframes animatetop {
        from {top: -300px; opacity: 0}
        to {top: 0; opacity: 1}
    }

    /* Responsive Table (copied from manage_posts.php for consistency) */
    .table-responsive {
        overflow-x: auto; 
    }

    @media (max-width: 768px) {
        .admin-table thead {
            display: none; 
        }

        .admin-table,
        .admin-table tbody,
        .admin-table tr,
        .admin-table td {
            display: block;
            width: 100%;
        }

        .admin-table tr {
            margin-bottom: 1rem;
            border: 1px solid var(--border-color);
            border-radius: 8px;
            overflow: hidden;
            background-color: var(--background-white);
            box-shadow: var(--shadow-light);
        }

        .admin-table td {
            text-align: right;
            padding-left: 50%; 
            position: relative;
        }

        .admin-table td::before {
            content: attr(data-label); 
            position: absolute;
            left: 15px;
            width: calc(50% - 30px);
            padding-right: 10px;
            white-space: nowrap;
            text-align: left;
            font-weight: 600;
            color: var(--heading-color);
        }

        /* Specific data labels for each column */
        .admin-table td:nth-of-type(1):before { content: "ID"; }
        .admin-table td:nth-of-type(2):before { content: "Sender"; }
        .admin-table td:nth-of-type(3):before { content: "Email"; }
        .admin-table td:nth-of-type(4):before { content: "Type"; }
        .admin-table td:nth-of-type(5):before { content: "Subject/Content"; }
        .admin-table td:nth-of-type(6):before { content: "Priority"; }
        .admin-table td:nth-of-type(7):before { content: "Status"; }
        .admin-table td:nth-of-type(8):before { content: "Date"; }
        .admin-table td:nth-of-type(9):before { content: "Actions"; }
        
        .admin-table .actions {
            display: flex;
            justify-content: flex-end;
            margin-top: 10px;
        }

        .form-inline {
            flex-direction: column;
            align-items: stretch;
        }
        .form-group.me-3 {
            margin-right: 0;
            margin-bottom: 1rem;
            width: 100%;
        }
        .form-select-inline, .form-input-inline, .form-inline .btn {
            width: 100%;
            margin-right: 0 !important; /* Override me-3 */
        }
        .form-inline .btn.ms-2 {
            margin-left: 0 !important;
            margin-top: 1rem;
        }
    }
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // AJAX for status updates
    const statusSelects = document.querySelectorAll('.message-status-select');

    statusSelects.forEach(select => {
        select.addEventListener('change', async function() {
            const messageId = this.dataset.messageId;
            const newStatus = this.value;

            try {
                const formData = new FormData();
                formData.append('message_id', messageId);
                formData.append('new_status', newStatus);
                formData.append('action', 'update_status_ajax'); // Indicate AJAX action

                const response = await fetch('manage_messages.php', {
                    method: 'POST',
                    body: formData
                });

                const data = await response.json();

                if (data.success) {
                    console.log('Message status updated successfully:', data.message);
                    const row = this.closest('tr');
                    if (row) {
                        row.style.transition = 'background-color 0.3s ease';
                        row.style.backgroundColor = 'rgba(46, 204, 113, 0.2)'; // Light green
                        setTimeout(() => {
                            row.style.backgroundColor = ''; // Reset after a short delay
                        }, 1000);
                    }
                } else {
                    alert('Failed to update message status: ' + data.message);
                    console.error('Failed to update message status:', data.message);
                }
            } catch (error) {
                console.error('Error during AJAX request:', error);
                alert('An error occurred while updating the message status. Please try again.');
            }
        });
    });

    // Modal functionality for viewing full messages
    const messageViewModal = document.getElementById('messageViewModal');
    const closeButtons = messageViewModal.querySelectorAll('.close-button');
    const viewMessageBtns = document.querySelectorAll('.view-message-btn');

    viewMessageBtns.forEach(btn => {
        btn.addEventListener('click', async function() {
            const messageId = this.dataset.messageId;
            // Find the row to get message details
            const row = this.closest('tr');
            const senderName = row.querySelector('td[data-label="Sender"]').textContent;
            const senderEmail = row.querySelector('td[data-label="Email"]').textContent;
            const messageType = row.querySelector('td[data-label="Type"] span').textContent; // Get text from the badge
            const messageDate = row.querySelector('td[data-label="Date"]').textContent;
            
            // For full content, we might need to fetch it if truncated, or store it in a data attribute
            // For now, we'll use the truncated preview.
            const messageContentPreview = row.querySelector('.message-preview').textContent;
            const messageSubject = row.querySelector('td[data-label="Subject/Content Preview"] strong') ? 
                                   row.querySelector('td[data-label="Subject/Content Preview"] strong').textContent.replace('Subject:', '').trim() : 'N/A';

            document.getElementById('modalSenderName').textContent = senderName;
            document.getElementById('modalSenderEmail').textContent = senderEmail;
            document.getElementById('modalMessageType').textContent = messageType;
            document.getElementById('modalMessageDate').textContent = messageDate;
            document.getElementById('modalMessageSubject').textContent = messageSubject !== 'N/A' ? `Message: ${messageSubject}` : 'Message Details';
            document.getElementById('modalMessageContent').textContent = messageContentPreview; // Update if you fetch full content

            messageViewModal.style.display = 'flex'; // Show the modal
        });
    });

    closeButtons.forEach(btn => {
        btn.addEventListener('click', function() {
            messageViewModal.style.display = 'none'; // Hide the modal
        });
    });

    // Close modal if clicked outside content
    window.addEventListener('click', function(event) {
        if (event.target == messageViewModal) {
            messageViewModal.style.display = 'none';
        }
    });
});
</script>

<?php ob_end_flush(); ?>
<?php include 'includes/footer.php'; ?>
