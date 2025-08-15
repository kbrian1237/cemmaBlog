document.addEventListener('DOMContentLoaded', function () {
    // Message button logic
    const messageButton = document.getElementById('messageButton');
    if (messageButton) {
        messageButton.addEventListener('click', function () {
            // Prevent messaging yourself
            if (parseInt(profileUserId) === parseInt(currentLoggedInUserId)) {
                alert("You cannot message yourself.");
                return;
            }
            // Redirect to chat page with the selected user's ID as a query parameter
            window.location.href = `/candid/chat.php?user_id=${encodeURIComponent(profileUserId)}`;
        });
    }
});