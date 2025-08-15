<?php
// ai_help.php - Admin interface for AI assistance features.

// Ensure session is started to check admin status
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Include necessary files
require_once 'includes/db_connection.php'; // For database connection ($conn)
require_once 'includes/functions.php';     // For utility functions like require_admin(), sanitize_input()

// Require admin access for this page
require_admin();

// --- Handle AJAX requests for AI actions ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    // Set content type to JSON for AJAX responses
    header('Content-Type: application/json');

    $response = ['success' => false, 'message' => ''];
    $action = sanitize_input($_POST['action']);

    // OpenAI API Configuration
    // IMPORTANT: Replace 'YOUR_OPENAI_API_KEY_HERE' with your actual OpenAI API Key.
    // You can obtain one from https://platform.openai.com/account/api-keys
   // $api_key = ''; 
    $api_endpoint = 'https://api.openai.com/v1/chat/completions'; // OpenAI Chat Completions endpoint
    $openai_model = 'gpt-3.5-turbo'; // Specify the OpenAI model to use

    // Validate API Key before proceeding
    if (empty($api_key) || $api_key === 'YOUR_OPENAI_API_KEY_HERE') {
        $response['message'] = 'API Key is not configured. Please edit ai_help.php and replace "YOUR_OPENAI_API_KEY_HERE" with your actual OpenAI API Key.';
        echo json_encode($response);
        exit();
    }

    switch ($action) {
        case 'analyze_content':
            $content_type = sanitize_input($_POST['content_type'] ?? ''); // 'post', 'comment', 'text'
            $content_id = isset($_POST['content_id']) ? (int)$_POST['content_id'] : 0;
            $text_to_analyze = $_POST['text_to_analyze'] ?? ''; // Do not sanitize here, let AI handle raw text

            $analysis_text = '';
            if ($content_type === 'post' && $content_id > 0) {
                $stmt = $conn->prepare("SELECT title, content FROM posts WHERE id = ?");
                $stmt->bind_param("i", $content_id);
                $stmt->execute();
                $result = $stmt->get_result();
                if ($row = $result->fetch_assoc()) {
                    $analysis_text = "Title: " . $row['title'] . "\nContent: " . $row['content'];
                } else {
                    $response['message'] = 'Post not found.';
                    echo json_encode($response);
                    exit();
                }
                $stmt->close();
            } elseif ($content_type === 'comment' && $content_id > 0) {
                $stmt = $conn->prepare("SELECT content FROM comments WHERE id = ?");
                $stmt->bind_param("i", $content_id);
                $stmt->execute();
                $result = $stmt->get_result();
                if ($row = $result->fetch_assoc()) {
                    $analysis_text = "Comment: " . $row['content'];
                } else {
                    $response['message'] = 'Comment not found.';
                    echo json_encode($response);
                    exit();
                }
                $stmt->close();
            } elseif ($content_type === 'text' && !empty($text_to_analyze)) {
                $analysis_text = $text_to_analyze;
            } else {
                $response['message'] = 'No content provided for analysis.';
                echo json_encode($response);
                exit();
            }

            if (empty($analysis_text)) {
                $response['message'] = 'Content to analyze is empty.';
                echo json_encode($response);
                exit();
            }

            // Construct the prompt for OpenAI API (as a user message)
            $prompt = "Analyze the following text for potential issues such as bullying, hate speech, fake news, content against terms and conditions, or any other harmful content. Provide a concise report, indicating if issues are found and why. If no issues, state 'No significant issues found.'\n\nText: \"" . $analysis_text . "\"";

            try {
                $payload = [
                    'model' => $openai_model,
                    'messages' => [
                        ['role' => 'user', 'content' => $prompt]
                    ]
                ];

                $ch = curl_init($api_endpoint);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_POST, true);
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
                curl_setopt($ch, CURLOPT_HTTPHEADER, [
                    'Content-Type: application/json',
                    'Authorization: Bearer ' . $api_key // OpenAI authentication
                ]);

                $api_response = curl_exec($ch);
                $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                $curl_error = curl_error($ch);
                curl_close($ch);

                if ($api_response === false) {
                    throw new Exception("cURL error: " . $curl_error);
                }

                $result = json_decode($api_response, true);

                if ($http_code !== 200) {
                    $error_message = 'API request failed with status ' . $http_code . ': ' . ($result['error']['message'] ?? 'Unknown API error');
                    error_log("OpenAI API Error: " . $error_message);
                    throw new Exception($error_message);
                }

                // Parse OpenAI response structure
                if (isset($result['choices'][0]['message']['content'])) {
                    $response['success'] = true;
                    $response['report'] = $result['choices'][0]['message']['content'];
                } else {
                    $response['message'] = 'Could not get a valid response from the AI.';
                    error_log("Invalid AI response structure: " . json_encode($result));
                }
            } catch (Exception $e) {
                $response['message'] = 'AI analysis failed: ' . $e->getMessage();
                error_log("AI analysis exception: " . $e->getMessage());
            }
            break;

        case 'get_db_insight':
            $insight_query = sanitize_input($_POST['query'] ?? '');

            if (empty($insight_query)) {
                $response['message'] = 'Please provide a query for database insight.';
                echo json_encode($response);
                exit();
            }

            // Define your database schema for the AI
            $db_schema = "Database schema:\n" .
                         "- Table 'users': id (INT), username (VARCHAR), email (VARCHAR), created_at (DATETIME), profile_image_path (VARCHAR), prefers_avatar (BOOLEAN), bio (TEXT), gender (VARCHAR)\n" .
                         "- Table 'posts': id (INT), user_id (INT), category_id (INT), title (VARCHAR), content (TEXT), image_path (VARCHAR), published_at (DATETIME), status (VARCHAR), is_featured (BOOLEAN), dislike_button_status (VARCHAR)\n" .
                         "- Table 'comments': id (INT), post_id (INT), user_id (INT), content (TEXT), created_at (DATETIME), status (VARCHAR)\n" .
                         "- Table 'likes': id (INT), post_id (INT), user_id (INT), created_at (DATETIME)\n" .
                         "- Table 'dislikes': id (INT), post_id (INT), user_id (INT), created_at (DATETIME)\n" .
                         "- Table 'categories': id (INT), name (VARCHAR)\n" .
                         "- Table 'tags': id (INT), name (VARCHAR)\n" .
                         "- Table 'post_tags': post_id (INT), tag_id (INT)\n" .
                         "- Table 'user_follows': follower_id (INT), followed_id (INT), created_at (DATETIME)\n" .
                         "- Table 'messages': id (INT), sender_name (VARCHAR), sender_email (VARCHAR), subject (VARCHAR), message_content (TEXT), message_type (VARCHAR), priority (INT), status (VARCHAR), created_at (DATETIME)\n" .
                         "- Table 'settings': setting_key (VARCHAR), setting_value (TEXT)\n\n";

            $prompt = $db_schema . "Based on the schema, answer the following question about the data. If you need to query the database, suggest a SQL SELECT query. If the question cannot be answered directly from the schema, explain why. Do NOT generate UPDATE, DELETE, or INSERT queries. Just provide insights or suggest SELECT queries.\n\nQuestion: \"" . $insight_query . "\"";

            try {
                $payload = [
                    'model' => $openai_model,
                    'messages' => [
                        ['role' => 'user', 'content' => $prompt]
                    ]
                ];

                $ch = curl_init($api_endpoint);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_POST, true);
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
                curl_setopt($ch, CURLOPT_HTTPHEADER, [
                    'Content-Type: application/json',
                    'Authorization: Bearer ' . $api_key
                ]);

                $api_response = curl_exec($ch);
                $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                $curl_error = curl_close($ch);

                if ($api_response === false) {
                    throw new Exception("cURL error: " . $curl_error);
                }

                $result = json_decode($api_response, true);

                if ($http_code !== 200) {
                    $error_message = 'API request failed with status ' . $http_code . ': ' . ($result['error']['message'] ?? 'Unknown API error');
                    error_log("OpenAI API Error: " . $error_message);
                    throw new Exception($error_message);
                }

                if (isset($result['choices'][0]['message']['content'])) {
                    $response['success'] = true;
                    $response['insight'] = $result['choices'][0]['message']['content'];
                } else {
                    $response['message'] = 'Could not get a valid response from the AI.';
                    error_log("Invalid AI response structure: " . json_encode($result));
                }
            } catch (Exception $e) {
                $response['message'] = 'AI insight generation failed: ' . $e->getMessage();
                error_log("AI insight exception: " . $e->getMessage());
            }
            break;

        case 'chat_with_ai':
            $user_message = $_POST['message'] ?? '';
            $chat_history_json = $_POST['chat_history'] ?? '[]';
            $chat_history = json_decode($chat_history_json, true);

            if (empty($user_message)) {
                $response['message'] = 'Please type a message to the AI.';
                echo json_encode($response);
                exit();
            }

            // Prepare messages for OpenAI format
            $openai_messages = [];

            // Add system instruction as the first message
            $db_schema_for_chat = "You are an AI assistant for a blog platform. Here is the database schema you can refer to:\n" .
                                  "- Table 'users': id (INT), username (VARCHAR), email (VARCHAR), created_at (DATETIME), profile_image_path (VARCHAR), prefers_avatar (BOOLEAN), bio (TEXT), gender (VARCHAR)\n" .
                                  "- Table 'posts': id (INT), user_id (INT), category_id (INT), title (VARCHAR), content (TEXT), image_path (VARCHAR), published_at (DATETIME), status (VARCHAR), is_featured (BOOLEAN), dislike_button_status (VARCHAR)\n" .
                                  "- Table 'comments': id (INT), post_id (INT), user_id (INT), content (TEXT), created_at (DATETIME), status (VARCHAR)\n" .
                                  "- Table 'likes': id (INT), post_id (INT), user_id (INT), created_at (DATETIME)\n" .
                                  "- Table 'dislikes': id (INT), post_id (INT), user_id (INT), created_at (DATETIME)\n" .
                                  "- Table 'categories': id (INT), name (VARCHAR)\n" .
                                  "- Table 'tags': id (INT), name (VARCHAR)\n" .
                                  "- Table 'post_tags': post_id (INT), tag_id (INT)\n" .
                                  "- Table 'user_follows': follower_id (INT), followed_id (INT), created_at (DATETIME)\n" .
                                  "- Table 'messages': id (INT), sender_name (VARCHAR), sender_email (VARCHAR), subject (VARCHAR), message_content (TEXT), message_type (VARCHAR), priority (INT), status (VARCHAR), created_at (DATETIME)\n" .
                                  "- Table 'settings': setting_key (VARCHAR), setting_value (TEXT)\n\n" .
                                  "When asked about database information, you can suggest SQL SELECT queries. Do NOT generate UPDATE, DELETE, or INSERT queries. Keep responses concise and helpful.";
            
            $openai_messages[] = ['role' => 'system', 'content' => $db_schema_for_chat];

            // Convert existing chat history to OpenAI format
            foreach ($chat_history as $msg) {
                $role = ($msg['role'] === 'model') ? 'assistant' : $msg['role']; // Map 'model' to 'assistant'
                $openai_messages[] = ['role' => $role, 'content' => $msg['parts'][0]['text']];
            }

            // Add the current user message
            $openai_messages[] = ['role' => 'user', 'content' => $user_message];

            try {
                $payload = [
                    'model' => $openai_model,
                    'messages' => $openai_messages
                ];

                $ch = curl_init($api_endpoint);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_POST, true);
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
                curl_setopt($ch, CURLOPT_HTTPHEADER, [
                    'Content-Type: application/json',
                    'Authorization: Bearer ' . $api_key
                ]);

                $api_response = curl_exec($ch);
                $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                $curl_error = curl_close($ch);

                if ($api_response === false) {
                    throw new Exception("cURL error: " . $curl_error);
                }

                $result = json_decode($api_response, true);

                if ($http_code !== 200) {
                    $error_message = 'API request failed with status ' . $http_code . ': ' . ($result['error']['message'] ?? 'Unknown API error');
                    error_log("OpenAI API Error: " . $error_message);
                    throw new Exception($error_message);
                }

                if (isset($result['choices'][0]['message']['content'])) {
                    $ai_response_text = $result['choices'][0]['message']['content'];
                    $response['success'] = true;
                    $response['ai_message'] = $ai_response_text;
                    // Append AI response to history for next turn (using 'model' for frontend consistency)
                    $chat_history[] = ['role' => 'model', 'parts' => [['text' => $ai_response_text]]];
                    $response['chat_history'] = $chat_history;
                } else {
                    $response['message'] = 'Could not get a valid response from the AI.';
                    error_log("Invalid AI response structure: " . json_encode($result));
                }
            } catch (Exception $e) {
                $response['message'] = 'AI chat failed: ' . $e->getMessage();
                error_log("AI chat exception: " . $e->getMessage());
            }
            break;

        default:
            $response['message'] = 'Unknown AI action.';
            break;
    }

    echo json_encode($response);
    exit(); // Exit after handling AJAX request
}

// --- HTML for the AI Help Page ---
$page_title = "AI Assistance";
include 'includes/header.php'; // Includes header HTML and opens <body>
?>

<div class="container">
    <div class="admin-header mb-4">
        <h1><i class="fas fa-robot"></i> AI Assistance Dashboard</h1>
        <p class="text-muted">Leverage AI for content analysis, database insights, and general queries.</p>
    </div>

    <!-- API Key Warning/Guidance -->
    <?php if (empty($api_key) || $api_key === 'YOUR_OPENAI_API_KEY_HERE'): ?>
        <div class="alert alert-warning mb-4">
            <i class="fas fa-exclamation-triangle"></i> <strong>API Key Required:</strong> To use AI features, please edit `ai_help.php` and replace `YOUR_OPENAI_API_KEY_HERE` with your actual OpenAI API Key.
            <button type="button" class="close-alert" onclick="this.parentElement.style.display='none';">&times;</button>
        </div>
    <?php endif; ?>

    <div class="dashboard-grid">
        <!-- AI Chat Section -->
        <div class="dashboard-card chat-card">
            <h3><i class="fas fa-comments"></i> Chat with AI</h3>
            <div class="chat-history" id="chatHistory">
                <div class="ai-message">Hello! I'm your AI assistant. How can I help you today?</div>
            </div>
            <div class="chat-input-area">
                <textarea id="userMessage" placeholder="Ask me anything... (e.g., 'How many published posts are there?', 'Analyze this text: ...')" rows="3"></textarea>
                <button id="sendMessageBtn" class="btn btn-primary"><i class="fas fa-paper-plane"></i> Send</button>
            </div>
            <div id="chatLoading" class="loading-indicator" style="display: none;">
                <i class="fas fa-spinner fa-spin"></i> Thinking...
            </div>
            <div id="chatError" class="alert alert-error mt-2" style="display: none;"></div>
        </div>

        <!-- Content Analysis Section -->
        <div class="dashboard-card">
            <h3><i class="fas fa-search"></i> Content Analysis</h3>
            <div class="form-group">
                <label for="analysisContentType">Analyze:</label>
                <select id="analysisContentType" class="form-input">
                    <option value="text">Custom Text</option>
                    <option value="post">Post by ID</option>
                    <option value="comment">Comment by ID</option>
                </select>
            </div>
            <div class="form-group" id="analysisContentIdGroup" style="display: none;">
                <label for="analysisContentId">ID:</label>
                <input type="number" id="analysisContentId" class="form-input" placeholder="Enter ID (e.g., 1)">
            </div>
            <div class="form-group" id="analysisCustomTextGroup">
                <label for="analysisCustomText">Text to Analyze:</label>
                <textarea id="analysisCustomText" class="form-input" rows="5" placeholder="Enter text here for analysis..."></textarea>
            </div>
            <button id="analyzeContentBtn" class="btn btn-primary"><i class="fas fa-brain"></i> Analyze Content</button>
            <div id="analysisLoading" class="loading-indicator" style="display: none;">
                <i class="fas fa-spinner fa-spin"></i> Analyzing...
            </div>
            <div id="analysisReport" class="ai-report mt-3"></div>
            <div id="analysisError" class="alert alert-error mt-2" style="display: none;"></div>
        </div>

        <!-- Database Insight Section -->
        <div class="dashboard-card">
            <h3><i class="fas fa-database"></i> Database Insight</h3>
            <div class="form-group">
                <label for="dbInsightQuery">Your Database Question:</label>
                <textarea id="dbInsightQuery" class="form-input" rows="3" placeholder="e.g., 'What are the top 5 most liked posts?', 'How many users registered last month?'"></textarea>
            </div>
            <button id="getDbInsightBtn" class="btn btn-primary"><i class="fas fa-lightbulb"></i> Get Insight</button>
            <div id="dbInsightLoading" class="loading-indicator" style="display: none;">
                <i class="fas fa-spinner fa-spin"></i> Getting Insight...
            </div>
            <div id="dbInsightResult" class="ai-report mt-3"></div>
            <div id="dbInsightError" class="alert alert-error mt-2" style="display: none;"></div>
        </div>

        <!-- Placeholder for Future AI Reports (e.g., Bullying Detection, Spam Detection) -->
        <div class="dashboard-card">
            <h3><i class="fas fa-chart-line"></i> AI Reports & Summaries</h3>
            <p class="text-muted">Future reports will appear here, such as:</p>
            <ul>
                <li><i class="fas fa-exclamation-triangle text-danger"></i> Potential Bullying/Hate Speech Overview</li>
                <li><i class="fas fa-filter text-info"></i> Spam Comment Detection Summary</li>
                <li><i class="fas fa-newspaper text-success"></i> Trending Topics Analysis</li>
                <li><i class="fas fa-chart-bar text-primary"></i> User Engagement Patterns</li>
            </ul>
            <div class="ai-report-placeholder">
                <p>Reports will be dynamically generated based on system data and AI analysis.</p>
            </div>
        </div>
    </div>
</div>

<style>
/* General Styles */
:root {
    --primary-color: #3498db;
    --primary-dark: #2980b9;
    --secondary-color: #6c757d;
    --accent-color: #e74c3c;
    --text-color: #333;
    --heading-color: #2c3e50;
    --background-light: #f8f9fa;
    --background-white: #fff;
    --border-color: #e9ecef;
    --shadow-light: 0 2px 10px rgba(0,0,0,0.08);
    --shadow-medium: 0 4px 15px rgba(0,0,0,0.1);
    --shadow-hover: 0 8px 25px rgba(0,0,0,0.15);
}

:root[data-theme="dark"] {
    --primary-color: #3498db;
    --primary-dark: #2980b9;
    --secondary-color: #95a5a6;
    --accent-color: #e74c3c;
    --text-color: #ecf0f1;
    --heading-color: #ffffff;
    --background-light: #34495e;
    --background-white: #2c3e50;
    --border-color: #4b6584;
    --shadow-light: 0 2px 10px rgba(0,0,0,0.2);
    --shadow-medium: 0 4px 15px rgba(0,0,0,0.3);
    --shadow-hover: 0 8px 25px rgba(0,0,0,0.4);
}

.container {
    max-width: 1200px;
    margin: 20px auto;
    padding: 0 15px;
}

.admin-header {
    background: var(--background-white);
    padding: 2rem;
    border-radius: 12px;
    box-shadow: var(--shadow-medium);
    text-align: center;
    margin-bottom: 2rem;
}

.admin-header h1 {
    color: var(--heading-color);
    margin-bottom: 0.5rem;
    font-size: 2.5rem;
}

.admin-header p {
    color: var(--secondary-color);
    font-size: 1.1rem;
}

.dashboard-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
    gap: 1.5rem;
}

.dashboard-card {
    background: var(--background-white);
    padding: 1.5rem;
    border-radius: 12px;
    box-shadow: var(--shadow-medium);
    display: flex;
    flex-direction: column;
}

.dashboard-card h3 {
    color: var(--heading-color);
    margin-bottom: 1rem;
    display: flex;
    align-items: center;
    gap: 0.75rem;
    font-size: 1.5rem;
}

.dashboard-card h3 .fas {
    color: var(--primary-color);
}

.form-group {
    margin-bottom: 1rem;
}

.form-group label {
    display: block;
    margin-bottom: 0.5rem;
    color: var(--text-color);
    font-weight: 500;
}

.form-input {
    width: 100%;
    padding: 0.75rem;
    border: 1px solid var(--border-color);
    border-radius: 8px;
    background-color: var(--background-light);
    color: var(--text-color);
    box-sizing: border-box;
    transition: border-color 0.2s ease, box-shadow 0.2s ease;
}

.form-input:focus {
    border-color: var(--primary-color);
    box-shadow: 0 0 0 3px rgba(52, 152, 219, 0.25);
    outline: none;
}

.btn {
    padding: 0.75rem 1.25rem;
    border-radius: 8px;
    cursor: pointer;
    font-size: 1rem;
    font-weight: 500;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 0.5rem;
    transition: background-color 0.2s ease, border-color 0.2s ease, color 0.2s ease, box-shadow 0.2s ease;
    border: 1px solid transparent;
}

.btn-primary {
    background-color: var(--primary-color);
    color: #fff;
    border-color: var(--primary-color);
}

.btn-primary:hover {
    background-color: var(--primary-dark);
    border-color: var(--primary-dark);
    box-shadow: var(--shadow-hover);
}

.btn:disabled {
    opacity: 0.6;
    cursor: not-allowed;
}

.mt-2 { margin-top: 0.5rem; }
.mt-3 { margin-top: 1rem; }
.mb-4 { margin-bottom: 1.5rem; }

/* Chat Specific Styles */
.chat-card {
    grid-column: span 2; /* Make chat section wider */
    display: flex;
    flex-direction: column;
    height: 500px; /* Fixed height for chat window */
}

.chat-history {
    flex-grow: 1;
    overflow-y: auto;
    border: 1px solid var(--border-color);
    border-radius: 8px;
    padding: 1rem;
    margin-bottom: 1rem;
    background-color: var(--background-light);
    display: flex;
    flex-direction: column;
    gap: 0.75rem;
    scroll-behavior: smooth;
}

.user-message, .ai-message {
    padding: 0.75rem 1rem;
    border-radius: 15px;
    max-width: 80%;
    word-wrap: break-word;
}

.user-message {
    background-color: var(--primary-color);
    color: #fff;
    align-self: flex-end;
    border-bottom-right-radius: 5px;
}

.ai-message {
    background-color: var(--secondary-color);
    color: #fff;
    align-self: flex-start;
    border-bottom-left-radius: 5px;
}

.chat-input-area {
    display: flex;
    gap: 0.5rem;
    margin-top: auto; /* Pushes input area to the bottom */
}

.chat-input-area textarea {
    flex-grow: 1;
    resize: vertical;
    min-height: 60px;
}

/* AI Report Styles */
.ai-report {
    background-color: var(--background-light);
    border: 1px solid var(--border-color);
    border-radius: 8px;
    padding: 1rem;
    min-height: 100px;
    white-space: pre-wrap; /* Preserve whitespace and line breaks */
    word-wrap: break-word;
    color: var(--text-color);
}

.ai-report-placeholder {
    background-color: var(--background-light);
    border: 1px dashed var(--border-color);
    border-radius: 8px;
    padding: 1rem;
    text-align: center;
    color: var(--secondary-color);
    min-height: 100px;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-direction: column;
}

.ai-report-placeholder ul {
    list-style: none;
    padding: 0;
    margin-top: 1rem;
    text-align: left;
    width: 100%;
}

.ai-report-placeholder li {
    margin-bottom: 0.5rem;
    color: var(--text-color);
}

.loading-indicator {
    text-align: center;
    margin-top: 1rem;
    color: var(--primary-color);
    font-weight: bold;
}

.alert {
    padding: 0.75rem 1rem;
    border-radius: 8px;
    font-weight: 500;
    display: flex;
    align-items: center;
    gap: 0.75rem;
}

.alert-error {
    background-color: #f8d7da;
    color: #721c24;
    border: 1px solid #f5c6cb;
}

.alert-warning {
    background-color: #fff3cd;
    color: #856404;
    border: 1px solid #ffeeba;
}

.close-alert {
    background: none;
    border: none;
    font-size: 1.5rem;
    line-height: 1;
    cursor: pointer;
    color: inherit;
    margin-left: auto;
}

/* Responsive adjustments */
@media (max-width: 992px) {
    .chat-card {
        grid-column: span 1; /* Stack on smaller screens */
    }
}

@media (max-width: 768px) {
    .dashboard-grid {
        grid-template-columns: 1fr;
    }
    .chat-card {
        height: 400px; /* Adjust height for mobile */
    }
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const chatHistoryDiv = document.getElementById('chatHistory');
    const userMessageInput = document.getElementById('userMessage');
    const sendMessageBtn = document.getElementById('sendMessageBtn');
    const chatLoading = document.getElementById('chatLoading');
    const chatError = document.getElementById('chatError');

    const analysisContentType = document.getElementById('analysisContentType');
    const analysisContentIdGroup = document.getElementById('analysisContentIdGroup');
    const analysisCustomTextGroup = document.getElementById('analysisCustomTextGroup');
    const analysisContentId = document.getElementById('analysisContentId');
    const analysisCustomText = document.getElementById('analysisCustomText');
    const analyzeContentBtn = document.getElementById('analyzeContentBtn');
    const analysisLoading = document.getElementById('analysisLoading');
    const analysisReport = document.getElementById('analysisReport');
    const analysisError = document.getElementById('analysisError');

    const dbInsightQuery = document.getElementById('dbInsightQuery');
    const getDbInsightBtn = document.getElementById('getDbInsightBtn');
    const dbInsightLoading = document.getElementById('dbInsightLoading');
    const dbInsightResult = document.getElementById('dbInsightResult');
    const dbInsightError = document.getElementById('dbInsightError');

    let chatHistory = []; // Stores chat history for conversational AI

    // Function to add a message to the chat history UI
    function addMessageToChat(message, sender) {
        const messageDiv = document.createElement('div');
        messageDiv.classList.add(sender === 'user' ? 'user-message' : 'ai-message');
        messageDiv.textContent = message;
        chatHistoryDiv.appendChild(messageDiv);
        chatHistoryDiv.scrollTop = chatHistoryDiv.scrollHeight; // Scroll to bottom
    }

    // Handle sending chat messages
    sendMessageBtn.addEventListener('click', async function() {
        const message = userMessageInput.value.trim();
        if (message === '') {
            chatError.textContent = 'Please enter a message.';
            chatError.style.display = 'block';
            return;
        }

        chatError.style.display = 'none';
        addMessageToChat(message, 'user');
        userMessageInput.value = '';
        sendMessageBtn.disabled = true;
        chatLoading.style.display = 'block';

        const formData = new FormData();
        formData.append('action', 'chat_with_ai');
        formData.append('message', message);
        formData.append('chat_history', JSON.stringify(chatHistory)); // Send full history

        try {
            const response = await fetch('ai_help.php', {
                method: 'POST',
                body: formData
            });
            const data = await response.json();

            if (data.success) {
                addMessageToChat(data.ai_message, 'ai');
                chatHistory = data.chat_history; // Update chat history from server
            } else {
                chatError.textContent = data.message || 'Error communicating with AI.';
                chatError.style.display = 'block';
                // Re-add user message to input if AI failed to respond
                userMessageInput.value = message;
            }
        } catch (error) {
            console.error('Fetch error:', error);
            chatError.textContent = 'Network error or server issue. Please try again.';
            chatError.style.display = 'block';
            userMessageInput.value = message; // Keep message in input on network error
        } finally {
            sendMessageBtn.disabled = false;
            chatLoading.style.display = 'none';
        }
    });

    // Handle Content Analysis type change
    analysisContentType.addEventListener('change', function() {
        if (this.value === 'text') {
            analysisCustomTextGroup.style.display = 'block';
            analysisContentIdGroup.style.display = 'none';
            analysisContentId.value = ''; // Clear ID when switching to text
        } else {
            analysisCustomTextGroup.style.display = 'none';
            analysisContentIdGroup.style.display = 'block';
            analysisCustomText.value = ''; // Clear text when switching to ID
        }
        analysisReport.textContent = ''; // Clear previous report
        analysisError.style.display = 'none'; // Hide error
    });

    // Handle Content Analysis button click
    analyzeContentBtn.addEventListener('click', async function() {
        const contentType = analysisContentType.value;
        let textToAnalyze = '';
        let contentId = 0;

        if (contentType === 'text') {
            textToAnalyze = analysisCustomText.value.trim();
            if (textToAnalyze === '') {
                analysisError.textContent = 'Please enter text to analyze.';
                analysisError.style.display = 'block';
                return;
            }
        } else {
            contentId = parseInt(analysisContentId.value.trim());
            if (isNaN(contentId) || contentId <= 0) {
                analysisError.textContent = `Please enter a valid ${contentType} ID.`;
                analysisError.style.display = 'block';
                return;
            }
        }

        analysisError.style.display = 'none';
        analysisReport.textContent = '';
        analyzeContentBtn.disabled = true;
        analysisLoading.style.display = 'block';

        const formData = new FormData();
        formData.append('action', 'analyze_content');
        formData.append('content_type', contentType);
        formData.append('content_id', contentId);
        formData.append('text_to_analyze', textToAnalyze); // Always send, PHP will pick based on type

        try {
            const response = await fetch('ai_help.php', {
                method: 'POST',
                body: formData
            });
            const data = await response.json();

            if (data.success) {
                analysisReport.textContent = data.report;
            } else {
                analysisError.textContent = data.message || 'Error analyzing content.';
                analysisError.style.display = 'block';
            }
        } catch (error) {
            console.error('Fetch error:', error);
            analysisError.textContent = 'Network error or server issue. Please try again.';
            analysisError.style.display = 'block';
        } finally {
            analyzeContentBtn.disabled = false;
            analysisLoading.style.display = 'none';
        }
    });

    // Handle Database Insight button click
    getDbInsightBtn.addEventListener('click', async function() {
        const query = dbInsightQuery.value.trim();
        if (query === '') {
            dbInsightError.textContent = 'Please enter a question for database insight.';
            dbInsightError.style.display = 'block';
            return;
        }

        dbInsightError.style.display = 'none';
        dbInsightResult.textContent = '';
        getDbInsightBtn.disabled = true;
        dbInsightLoading.style.display = 'block';

        const formData = new FormData();
        formData.append('action', 'get_db_insight');
        formData.append('query', query);

        try {
            const response = await fetch('ai_help.php', {
                method: 'POST',
                body: formData
            });
            const data = await response.json();

            if (data.success) {
                dbInsightResult.textContent = data.insight;
            } else {
                dbInsightError.textContent = data.message || 'Error getting database insight.';
                dbInsightError.style.display = 'block';
            }
        } catch (error) {
            console.error('Fetch error:', error);
            dbInsightError.textContent = 'Network error or server issue. Please try again.';
            dbInsightError.style.display = 'block';
        } finally {
            getDbInsightBtn.disabled = false;
            dbInsightLoading.style.display = 'none';
        }
    });
});
</script>

<?php include 'includes/footer.php'; ?>
