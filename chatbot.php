<?php
// chatbot.php
// This file contains the HTML, CSS, and JavaScript for the floating chatbot interface.
// It will be included in the footer.php to appear on all pages.

// Ensure session is started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// For this component to be self-contained, we use require_once.
// It's safe even if these files are included elsewhere.
require_once 'includes/db_connection.php';
require_once 'includes/functions.php';

$current_user_id = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : null;
$current_username = 'Guest';
$current_user_email = '';

// If a user is logged in, fetch their details from the database
if ($current_user_id && isset($conn)) {
    $user = get_user_by_id($conn, $current_user_id);
    if ($user) {
        $current_username = $user['username'];
        $current_user_email = $user['email']; // FIX: Correctly get email
    }
}

// API Configuration
//$api_key = ''; // Replace with your actual API key
$api_endpoint = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-2.0-flash:generateContent?key=';

?>

<style>
    /* Floating Chatbot Button */
    .chatbot-toggle-button {
        position: fixed;
        bottom: 90px;
        right: 20px;
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        border: none;
        border-radius: 50%;
        width: 60px;
        height: 60px;
        font-size: 1.8rem;
        display: flex;
        align-items: center;
        justify-content: center;
        cursor: grab; /* Draggable cursor */
        box-shadow: 0 4px 15px rgba(0, 0, 0, 0.2);
        z-index: 10000;
        transition: transform 0.3s ease, background 0.3s ease;
        outline: none;
        touch-action: none; /* Prevent page scroll on touch */
    }

    .chatbot-toggle-button:hover {
        transform: translateY(-5px);
        background: linear-gradient(135deg, #5a67d8 0%, #683a90 100%);
    }
    
    .chatbot-toggle-button.dragging {
        cursor: grabbing;
        transform: scale(1.1);
        box-shadow: 0 8px 25px rgba(0,0,0,0.3);
    }

    /* Chatbot Container (Modal-like) */
    .chatbot-container {
        display: none; /* Hidden by default */
        position: fixed;
        bottom: 140px; /* Initial position relative to button */
        right: 20px;
        width: 350px;
        height: 500px;
        background: var(--background-white);
        border-radius: 15px;
        box-shadow: 0 8px 25px rgba(0, 0, 0, 0.3);
        z-index: 9999;
        flex-direction: column;
        overflow: hidden;
        font-family: 'Inter', sans-serif;
        transform: scale(0.8);
        opacity: 0;
        transition: transform 0.3s ease, opacity 0.3s ease;
        transform-origin: bottom right;
    }

    .chatbot-container.active {
        display: flex;
        transform: scale(1);
        opacity: 1;
    }

    /* Chatbot Header */
    .chatbot-header {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        padding: 15px;
        border-top-left-radius: 15px;
        border-top-right-radius: 15px;
        display: flex;
        justify-content: space-between;
        align-items: center;
        font-size: 1.1rem;
        font-weight: bold;
    }

    .chatbot-header .close-btn {
        background: none;
        border: none;
        color: white;
        font-size: 1.5rem;
        cursor: pointer;
        outline: none;
    }

    /* Chat Messages Area */
    .chatbot-messages {
        flex-grow: 1;
        padding: 15px;
        overflow-y: auto;
        background-color: var(--background-light);
        display: flex;
        flex-direction: column;
        gap: 10px;
    }

    .chatbot-message {
        max-width: 80%;
        padding: 10px 12px;
        border-radius: 12px;
        line-height: 1.4;
        word-wrap: break-word;
    }

    .chatbot-message.user {
        align-self: flex-end;
        background-color: #dcf8c6;
        color: #333;
        border-bottom-right-radius: 2px;
        box-shadow: 0 1px 2px rgba(0,0,0,0.1);
    }

    .chatbot-message.bot {
        align-self: flex-start;
        background-color: #e0e0e0;
        color: #333;
        border-bottom-left-radius: 2px;
        box-shadow: 0 1px 2px rgba(0,0,0,0.1);
    }

    .chatbot-message.loading {
        background-color: #f0f0f0;
        font-style: italic;
        color: #666;
    }
    .chatbot-message.loading::after {
        content: '...';
        animation: blink 1s steps(5, start) infinite;
    }
    @keyframes blink {
        to { visibility: hidden; }
    }

    /* Chat Input Area */
    .chatbot-input-area {
        display: flex;
        padding: 10px;
        border-top: 1px solid var(--border-color);
        background-color: var(--background-white);
    }

    .chatbot-input-area input[type="text"] {
        flex-grow: 1;
        padding: 10px;
        border: 1px solid var(--border-color);
        border-radius: 20px;
        outline: none;
        font-size: 0.95rem;
        margin-right: 10px;
        background-color: var(--background-light);
        color: var(--text-color);
    }

    .chatbot-input-area button {
        background: var(--primary-color);
        color: white;
        border: none;
        border-radius: 50%;
        width: 40px;
        height: 40px;
        display: flex;
        align-items: center;
        justify-content: center;
        cursor: pointer;
        font-size: 1.2rem;
        outline: none;
        transition: background 0.2s ease;
    }

    /* Admin Message Section - IMPROVED STYLES */
    .chatbot-admin-message-section {
        padding: 20px 15px;
        background-color: var(--background-light);
        border-top: 1px solid var(--border-color);
        display: none;
        flex-direction: column;
        gap: 15px;
        flex-grow: 1; /* Allow it to fill space */
        overflow-y: auto;
    }

    .chatbot-admin-message-section.active {
        display: flex;
    }

    .chatbot-admin-message-section h4 {
        text-align: center;
        color: var(--heading-color);
        font-size: 1.1rem;
        margin: 0 0 5px 0;
    }
    
    .chatbot-admin-message-section .form-group {
        display: flex;
        flex-direction: column;
    }

    .chatbot-admin-message-section label {
        font-size: 0.85rem;
        color: var(--secondary-color);
        margin-bottom: 5px;
        font-weight: 500;
    }

    .chatbot-admin-message-section input,
    .chatbot-admin-message-section textarea {
        width: 100%;
        box-sizing: border-box;
        padding: 12px;
        border: 1px solid #ccc;
        border-radius: 8px;
        resize: vertical;
        font-size: 0.95rem;
        background-color: var(--background-white);
        color: var(--text-color);
        transition: border-color 0.2s, box-shadow 0.2s;
    }

    .chatbot-admin-message-section input:focus,
    .chatbot-admin-message-section textarea:focus {
        outline: none;
        border-color: var(--primary-color);
        box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.2);
    }
    
    .chatbot-admin-message-section input[readonly] {
        background-color: #e9ecef;
        cursor: not-allowed;
    }

    .chatbot-admin-message-section textarea {
        min-height: 100px;
    }

    .chatbot-admin-message-section button {
        background: var(--accent-color);
        color: white;
        border: none;
        border-radius: 8px;
        padding: 12px 15px;
        cursor: pointer;
        font-size: 1rem;
        font-weight: bold;
        transition: background 0.2s ease, transform 0.1s ease;
        margin-top: 5px;
    }

    .chatbot-admin-message-section button:hover {
        background: #c0392b;
    }

    /* Toggle between chat and admin message */
    .chatbot-mode-toggle {
        background: none;
        border: none;
        color: var(--primary-color);
        font-size: 0.9rem;
        cursor: pointer;
        text-decoration: underline;
        padding: 10px;
        align-self: center;
        outline: none;
    }

    /* Responsive adjustments */
    @media (max-width: 400px) {
        .chatbot-container {
            width: 90vw;
            height: 80vh;
        }
    }
</style>

<button class="chatbot-toggle-button" id="chatbot-toggle-btn" aria-label="Open Chatbot">
    <i class="fas fa-comment-dots"></i>
</button>

<div class="chatbot-container" id="chatbot-container">
    <div class="chatbot-header">
        <span>Cemma Chatbot</span>
        <button class="close-btn" id="chatbot-close-btn" aria-label="Close Chatbot">&times;</button>
    </div>
    <div class="chatbot-messages" id="chatbot-messages">
        <div class="chatbot-message bot">Hello! How can I help you today?</div>
    </div>
    <div class="chatbot-input-area" id="chat-input-area">
        <input type="text" id="chatbot-input" placeholder="Type your message..." aria-label="Chat input">
        <button id="chatbot-send-btn"><i class="fas fa-paper-plane"></i></button>
    </div>

    <div class="chatbot-admin-message-section" id="admin-message-section">
        <h4>Contact Us</h4>
        <p style="font-size: 0.85rem; text-align: center; margin: -10px 0 10px; color: var(--secondary-color);">If the chatbot can't help, send a direct message.</p>
        <div class="form-group">
            <label for="admin-message-name">Your Name</label>
            <input type="text" id="admin-message-name" placeholder="Enter your name" value="<?php echo htmlspecialchars($current_username); ?>" <?php echo $current_user_id ? 'readonly' : 'required'; ?>>
        </div>
        <div class="form-group">
            <label for="admin-message-email">Your Email</label>
            <input type="email" id="admin-message-email" placeholder="Enter your email" value="<?php echo htmlspecialchars($current_user_email); ?>" <?php echo $current_user_id ? 'readonly' : 'required'; ?>>
        </div>
        <div class="form-group">
            <label for="admin-message-content">Your Message</label>
            <textarea id="admin-message-content" placeholder="Your message or question to us..." required></textarea>
        </div>
        <button id="send-admin-message-btn">Send Message</button>
    </div>
    <button class="chatbot-mode-toggle" id="chatbot-mode-toggle">Ask A Direct Question</button>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const chatbotToggleBtn = document.getElementById('chatbot-toggle-btn');
    const chatbotContainer = document.getElementById('chatbot-container');
    const chatbotCloseBtn = document.getElementById('chatbot-close-btn');
    const chatbotMessages = document.getElementById('chatbot-messages');
    const chatbotInput = document.getElementById('chatbot-input');
    const chatbotSendBtn = document.getElementById('chatbot-send-btn');
    const chatbotModeToggle = document.getElementById('chatbot-mode-toggle');
    const chatInputArea = document.getElementById('chat-input-area');
    const adminMessageSection = document.getElementById('admin-message-section');
    const adminMessageName = document.getElementById('admin-message-name');
    const adminMessageEmail = document.getElementById('admin-message-email');
    const adminMessageContent = document.getElementById('admin-message-content');
    const sendAdminMessageBtn = document.getElementById('send-admin-message-btn');

    let isChatMode = true;
    let hasDragged = false;

    // User data from PHP
    const currentUserId = <?php echo json_encode($current_user_id); ?>;
    const apiKey = <?php echo json_encode($api_key); ?>;
    const apiEndpoint = <?php echo json_encode($api_endpoint); ?>;

    function addMessage(sender, text, isTyping = false) {
        const messageDiv = document.createElement('div');
        messageDiv.classList.add('chatbot-message', sender);
        if (isTyping) {
            messageDiv.classList.add('loading');
        }
        messageDiv.textContent = text;
        chatbotMessages.appendChild(messageDiv);
        chatbotMessages.scrollTop = chatbotMessages.scrollHeight;
        return messageDiv;
    }

    async function sendToChatbotAPI(message) {
        addMessage('user', message);
        chatbotInput.value = '';
        const loadingMessage = addMessage('bot', 'Typing', true);

        try {
            const response = await fetch(apiEndpoint + apiKey, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ contents: [{ role: "user", parts: [{ text: message }] }] })
            });
            if (!response.ok) throw new Error(`API error: ${response.status}`);
            const result = await response.json();
            const botResponse = result.candidates?.[0]?.content?.parts?.[0]?.text;
            if (botResponse) {
                loadingMessage.textContent = botResponse;
                loadingMessage.classList.remove('loading');
            } else {
                throw new Error("Invalid API response structure.");
            }
        } catch (error) {
            loadingMessage.textContent = "Error: " + error.message;
            loadingMessage.classList.remove('loading');
            console.error('Chatbot API fetch error:', error);
        }
    }

    async function sendToAdmin(name, email, message) {
        if (!name || !email || !message) {
            alert('Please fill in all required fields.');
            return;
        }
        const formData = new FormData();
        formData.append('sender_name', name);
        formData.append('sender_email', email);
        formData.append('message_content', message);
        formData.append('message_type', 'chatbot_admin_request');
        if (currentUserId) formData.append('user_id', currentUserId);

        try {
            const response = await fetch('process_message.php', { method: 'POST', body: formData });
            const result = await response.json();
            if (result.success) {
                alert('Message sent to admin successfully!');
                adminMessageContent.value = '';
                toggleChatbotMode();
            } else {
                alert('Failed to send message: ' + (result.message || 'Unknown error.'));
            }
        } catch (error) {
            alert('An error occurred while sending your message.');
        }
    }

    // Toggle chatbot visibility
    chatbotToggleBtn.addEventListener('click', function() {
        if (hasDragged) return;
        chatbotContainer.classList.toggle('active');
        if (chatbotContainer.classList.contains('active')) {
            updateChatbotContainerPosition();
            chatbotInput.focus();
        }
    });

    chatbotCloseBtn.addEventListener('click', () => chatbotContainer.classList.remove('active'));
    chatbotSendBtn.addEventListener('click', () => {
        const message = chatbotInput.value.trim();
        if (message) sendToChatbotAPI(message);
    });
    chatbotInput.addEventListener('keypress', (e) => {
        if (e.key === 'Enter') chatbotSendBtn.click();
    });

    function toggleChatbotMode() {
        isChatMode = !isChatMode;
        chatInputArea.style.display = isChatMode ? 'flex' : 'none';
        adminMessageSection.classList.toggle('active', !isChatMode);
        chatbotModeToggle.textContent = isChatMode ? 'Switch to Admin Message' : 'Back to Chatbot';
        if (isChatMode) chatbotInput.focus(); else adminMessageContent.focus();
    }
    chatbotModeToggle.addEventListener('click', toggleChatbotMode);

    sendAdminMessageBtn.addEventListener('click', () => {
        sendToAdmin(adminMessageName.value.trim(), adminMessageEmail.value.trim(), adminMessageContent.value.trim());
    });

    // --- DRAGGABLE BUTTON LOGIC ---
    let isDragging = false;
    let offsetX, offsetY;

    function onDragStart(e) {
        hasDragged = false;
        isDragging = true;
        chatbotToggleBtn.classList.add('dragging');
        const rect = chatbotToggleBtn.getBoundingClientRect();
        if (e.type === 'touchstart') {
            offsetX = e.touches[0].clientX - rect.left;
            offsetY = e.touches[0].clientY - rect.top;
        } else {
            offsetX = e.clientX - rect.left;
            offsetY = e.clientY - rect.top;
        }
        document.addEventListener('mousemove', onDragMove);
        document.addEventListener('mouseup', onDragEnd);
        document.addEventListener('touchmove', onDragMove, { passive: false });
        document.addEventListener('touchend', onDragEnd);
    }

    function onDragMove(e) {
        if (!isDragging) return;
        hasDragged = true;
        e.preventDefault();

        const VpWidth = window.innerWidth;
        const VpHeight = window.innerHeight;
        const elWidth = chatbotToggleBtn.offsetWidth;
        const elHeight = chatbotToggleBtn.offsetHeight;
        
        let newLeft, newTop;
        if (e.type === 'touchmove') {
            newLeft = e.touches[0].clientX - offsetX;
            newTop = e.touches[0].clientY - offsetY;
        } else {
            newLeft = e.clientX - offsetX;
            newTop = e.clientY - offsetY;
        }

        // Constrain to viewport
        if (newLeft < 0) newLeft = 0;
        if (newTop < 0) newTop = 0;
        if (newLeft > VpWidth - elWidth) newLeft = VpWidth - elWidth;
        if (newTop > VpHeight - elHeight) newTop = VpHeight - elHeight;

        chatbotToggleBtn.style.left = `${newLeft}px`;
        chatbotToggleBtn.style.top = `${newTop}px`;
        chatbotToggleBtn.style.right = 'auto';
        chatbotToggleBtn.style.bottom = 'auto';
        updateChatbotContainerPosition();
    }

    function onDragEnd() {
        isDragging = false;
        chatbotToggleBtn.classList.remove('dragging');
        document.removeEventListener('mousemove', onDragMove);
        document.removeEventListener('mouseup', onDragEnd);
        document.removeEventListener('touchmove', onDragMove);
        document.removeEventListener('touchend', onDragEnd);
        snapToSide();
        setTimeout(() => { hasDragged = false; }, 50); // Reset drag flag after a short delay
    }

    function snapToSide() {
        const rect = chatbotToggleBtn.getBoundingClientRect();
        const VpWidth = window.innerWidth;
        const margin = 20;
        if ((rect.left + rect.width / 2) < VpWidth / 2) {
            chatbotToggleBtn.style.left = `${margin}px`;
            chatbotToggleBtn.style.right = 'auto';
        } else {
            chatbotToggleBtn.style.left = 'auto';
            chatbotToggleBtn.style.right = `${margin}px`;
        }
        updateChatbotContainerPosition();
    }

    function updateChatbotContainerPosition() {
        if (!chatbotContainer.classList.contains('active')) return;
        const buttonRect = chatbotToggleBtn.getBoundingClientRect();
        const VpWidth = window.innerWidth;
        const VpHeight = window.innerHeight;

        if ((buttonRect.left + buttonRect.width / 2) < VpWidth / 2) {
            container.style.left = `${buttonRect.left}px`;
            container.style.right = 'auto';
            container.style.transformOrigin = 'bottom left';
        } else {
            container.style.left = 'auto';
            container.style.right = `${VpWidth - buttonRect.right}px`;
            container.style.transformOrigin = 'bottom right';
        }
        container.style.bottom = `${VpHeight - buttonRect.top}px`;
    }

    chatbotToggleBtn.addEventListener('mousedown', onDragStart);
    chatbotToggleBtn.addEventListener('touchstart', onDragStart, { passive: true });
    window.addEventListener('resize', snapToSide);
});
</script>
