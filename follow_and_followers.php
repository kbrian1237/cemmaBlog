<?php
$page_title = "Followers & Following";
include 'includes/header.php';
require_once 'includes/functions.php';
require_once 'includes/db_connection.php';

require_login();
$user_id = $_SESSION['user_id'];

// Get counts for each tab (for badges)
$followers_count = get_follower_count($conn, $user_id);
$following_count = get_following_count($conn, $user_id);

// Determine current tab
$tab = isset($_GET['tab']) ? $_GET['tab'] : 'followers';
?>

<div class="container">
    <div class="follows-header mb-4">
        <h1><i class="fas fa-user-friends"></i> Followers & Following</h1>
        <p class="text-muted">Manage your social connections</p>
    </div>
    <div class="follows-nav animated-nav mb-4">
        <a href="?tab=followers" class="follows-tab <?php echo $tab == 'followers' ? 'active' : ''; ?>">
            <i class="fas fa-users"></i> Followers
            <span class="badge badge-primary"><?php echo $followers_count; ?></span>
        </a>
        <a href="?tab=following" class="follows-tab <?php echo $tab == 'following' ? 'active' : ''; ?>">
            <i class="fas fa-user-plus"></i> Following
            <span class="badge badge-success"><?php echo $following_count; ?></span>
        </a>
        <a href="?tab=mutual" class="follows-tab <?php echo $tab == 'mutual' ? 'active' : ''; ?>">
            <i class="fas fa-exchange-alt"></i> Mutual Followers
        </a>
        <a href="?tab=suggested" class="follows-tab <?php echo $tab == 'suggested' ? 'active' : ''; ?>">
            <i class="fas fa-user-plus"></i> Suggested
        </a>
    </div>
    <!-- Content for each tab -->
    <div id="follows-content">
        <?php if ($tab == 'followers'): ?>
            <!-- Followers Tab -->
            <div class="follows-section">
                <h3><i class="fas fa-users"></i> Your Followers (<?php echo $followers_count; ?>)</h3>
                <?php
                $followers = get_followers_users($conn, $user_id, 20, 0);
                if (!empty($followers)): ?>
                    <div class="users-grid">
                        <?php foreach ($followers as $follower): ?>
                            <div class="user-card">
                                <div class="user-avatar">
                                    <?php
                                    $avatar_src = 'avatars/default_avatar.png';
                                    if (!empty($follower['profile_image_path'])) {
                                        $avatar_src = $follower['profile_image_path'];
                                    } elseif ($follower['prefers_avatar']) {
                                        if ($follower['gender'] == 'male') {
                                            $avatar_src = 'avatars/male_avatar.png';
                                        } elseif ($follower['gender'] == 'female') {
                                            $avatar_src = 'avatars/female_avatar.png';
                                        }
                                    }
                                    ?>
                                    <img src="<?php echo $avatar_src; ?>" alt="<?php echo htmlspecialchars($follower['username']); ?>">
                                </div>
                                <div class="user-info">
                                    <h4><?php echo htmlspecialchars($follower['username']); ?></h4>
                                    <p class="user-status">Following you</p>
                                    <div class="user-actions">
                                        <?php if (is_following($conn, $user_id, $follower['id'])): ?>
                                            <button class="btn btn-outline btn-sm unfollow-btn" data-user-id="<?php echo $follower['id']; ?>">
                                                <i class="fas fa-user-minus"></i> Unfollow
                                            </button>
                                        <?php else: ?>
                                            <button class="btn btn-primary btn-sm follow-btn" data-user-id="<?php echo $follower['id']; ?>">
                                                <i class="fas fa-user-plus"></i> Follow
                                            </button>
                                        <?php endif; ?>
                                        <a href="view_user.php?id=<?php echo $follower['id']; ?>" class="btn btn-secondary btn-sm">
                                            <i class="fas fa-eye"></i> View
                                        </a>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="empty-state">
                        <i class="fas fa-users fa-3x"></i>
                        <h4>No followers yet</h4>
                        <p>Start creating great content to attract followers!</p>
                    </div>
                <?php endif; ?>
            </div>

        <?php elseif ($tab == 'following'): ?>
            <!-- Following Tab -->
            <div class="follows-section">
                <h3><i class="fas fa-user-plus"></i> Users You're Following (<?php echo $following_count; ?>)</h3>
                <?php
                $following = get_following_users($conn, $user_id, 20, 0);
                if (!empty($following)): ?>
                    <div class="users-grid">
                        <?php foreach ($following as $followed): ?>
                            <div class="user-card">
                                <div class="user-avatar">
                                    <?php
                                    $avatar_src = 'avatars/default_avatar.png';
                                    if (!empty($followed['profile_image_path'])) {
                                        $avatar_src = $followed['profile_image_path'];
                                    } elseif ($followed['prefers_avatar']) {
                                        if ($followed['gender'] == 'male') {
                                            $avatar_src = 'avatars/male_avatar.png';
                                        } elseif ($followed['gender'] == 'female') {
                                            $avatar_src = 'avatars/female_avatar.png';
                                        }
                                    }
                                    ?>
                                    <img src="<?php echo $avatar_src; ?>" alt="<?php echo htmlspecialchars($followed['username']); ?>">
                                </div>
                                <div class="user-info">
                                    <h4><?php echo htmlspecialchars($followed['username']); ?></h4>
                                    <p class="user-status">You're following</p>
                                    <div class="user-actions">
                                        <button class="btn btn-outline btn-sm unfollow-btn" data-user-id="<?php echo $followed['id']; ?>">
                                            <i class="fas fa-user-minus"></i> Unfollow
                                        </button>
                                        <a href="view_user.php?id=<?php echo $followed['id']; ?>" class="btn btn-secondary btn-sm">
                                            <i class="fas fa-eye"></i> View
                                        </a>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="empty-state">
                        <i class="fas fa-user-plus fa-3x"></i>
                        <h4>Not following anyone yet</h4>
                        <p>Start following users to see their content in your feed!</p>
                    </div>
                <?php endif; ?>
            </div>

        <?php elseif ($tab == 'mutual'): ?>
            <!-- Mutual Followers Tab -->
            <div class="follows-section">
                <h3><i class="fas fa-exchange-alt"></i> Mutual Followers</h3>
                <?php
                // Get mutual followers (users who follow you and you follow them)
                $mutual_query = "SELECT u.id, u.username, u.profile_image_path, u.prefers_avatar, u.gender
                                FROM users u
                                WHERE u.id IN (
                                    SELECT uf1.follower_id 
                                    FROM user_follows uf1 
                                    WHERE uf1.followed_id = ?
                                ) AND u.id IN (
                                    SELECT uf2.followed_id 
                                    FROM user_follows uf2 
                                    WHERE uf2.follower_id = ?
                                )";
                $mutual_stmt = $conn->prepare($mutual_query);
                $mutual_stmt->bind_param("ii", $user_id, $user_id);
                $mutual_stmt->execute();
                $mutual_result = $mutual_stmt->get_result();
                $mutual_users = $mutual_result->fetch_all(MYSQLI_ASSOC);
                $mutual_stmt->close();
                
                if (!empty($mutual_users)): ?>
                    <div class="users-grid">
                        <?php foreach ($mutual_users as $mutual): ?>
                            <div class="user-card mutual">
                                <div class="user-avatar">
                                    <?php
                                    $avatar_src = 'avatars/default_avatar.png';
                                    if (!empty($mutual['profile_image_path'])) {
                                        $avatar_src = $mutual['profile_image_path'];
                                    } elseif ($mutual['prefers_avatar']) {
                                        if ($mutual['gender'] == 'male') {
                                            $avatar_src = 'avatars/male_avatar.png';
                                        } elseif ($mutual['gender'] == 'female') {
                                            $avatar_src = 'avatars/female_avatar.png';
                                        }
                                    }
                                    ?>
                                    <img src="<?php echo $avatar_src; ?>" alt="<?php echo htmlspecialchars($mutual['username']); ?>">
                                </div>
                                <div class="user-info">
                                    <h4><?php echo htmlspecialchars($mutual['username']); ?></h4>
                                    <p class="user-status"><i class="fas fa-exchange-alt"></i> Mutual follower</p>
                                    <div class="user-actions">
                                        <button class="btn btn-outline btn-sm unfollow-btn" data-user-id="<?php echo $mutual['id']; ?>">
                                            <i class="fas fa-user-minus"></i> Unfollow
                                        </button>
                                        <a href="view_user.php?id=<?php echo $mutual['id']; ?>" class="btn btn-secondary btn-sm">
                                            <i class="fas fa-eye"></i> View
                                        </a>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="empty-state">
                        <i class="fas fa-exchange-alt fa-3x"></i>
                        <h4>No mutual followers yet</h4>
                        <p>When you follow someone who also follows you, they'll appear here!</p>
                    </div>
                <?php endif; ?>
            </div>

        <?php elseif ($tab == 'suggested'): ?>
            <!-- Suggested Followers Tab -->
            <div class="follows-section">
                <h3><i class="fas fa-user-plus"></i> Suggested Users to Follow</h3>
                <?php
                // Get suggested users (users you don't follow, excluding yourself)
                $suggested_query = "SELECT u.id, u.username, u.profile_image_path, u.prefers_avatar, u.gender,
                                   (SELECT COUNT(*) FROM posts WHERE user_id = u.id AND status = 'published') as post_count,
                                   (SELECT COUNT(*) FROM user_follows WHERE followed_id = u.id) as follower_count
                                   FROM users u
                                   WHERE u.id != ? 
                                   AND u.id NOT IN (
                                       SELECT followed_id FROM user_follows WHERE follower_id = ?
                                   )
                                   ORDER BY follower_count DESC, post_count DESC
                                   LIMIT 20";
                $suggested_stmt = $conn->prepare($suggested_query);
                $suggested_stmt->bind_param("ii", $user_id, $user_id);
                $suggested_stmt->execute();
                $suggested_result = $suggested_stmt->get_result();
                $suggested_users = $suggested_result->fetch_all(MYSQLI_ASSOC);
                $suggested_stmt->close();
                
                if (!empty($suggested_users)): ?>
                    <div class="users-grid">
                        <?php foreach ($suggested_users as $suggested): ?>
                            <div class="user-card suggested">
                                <div class="user-avatar">
                                    <?php
                                    $avatar_src = 'avatars/default_avatar.png';
                                    if (!empty($suggested['profile_image_path'])) {
                                        $avatar_src = $suggested['profile_image_path'];
                                    } elseif ($suggested['prefers_avatar']) {
                                        if ($suggested['gender'] == 'male') {
                                            $avatar_src = 'avatars/male_avatar.png';
                                        } elseif ($suggested['gender'] == 'female') {
                                            $avatar_src = 'avatars/female_avatar.png';
                                        }
                                    }
                                    ?>
                                    <img src="<?php echo $avatar_src; ?>" alt="<?php echo htmlspecialchars($suggested['username']); ?>">
                                </div>
                                <div class="user-info">
                                    <h4><?php echo htmlspecialchars($suggested['username']); ?></h4>
                                    <p class="user-stats">
                                        <span><i class="fas fa-file-alt"></i> <?php echo $suggested['post_count']; ?> posts</span>
                                        <span><i class="fas fa-users"></i> <?php echo $suggested['follower_count']; ?> followers</span>
                                    </p>
                                    <div class="user-actions">
                                        <button class="btn btn-primary btn-sm follow-btn" data-user-id="<?php echo $suggested['id']; ?>">
                                            <i class="fas fa-user-plus"></i> Follow
                                        </button>
                                        <a href="view_user.php?id=<?php echo $suggested['id']; ?>" class="btn btn-secondary btn-sm">
                                            <i class="fas fa-eye"></i> View
                                        </a>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="empty-state">
                        <i class="fas fa-user-plus fa-3x"></i>
                        <h4>No suggestions available</h4>
                        <p>You're already following everyone! Check back later for new users.</p>
                    </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<style>
.follows-header {
    background: linear-gradient(135deg, #3498db 0%, #764ba2 100%);
    color: white;
    padding: 2rem;
    border-radius: 12px;
    box-shadow: 0 4px 20px rgba(0,0,0,0.10);
    text-align: center;
    margin-bottom: 2rem;
}
.animated-nav {
    display: flex;
    background: var(--background-white);
    border-radius: 12px;
    box-shadow: var(--shadow-light);
    overflow: hidden;
    margin-bottom: 2rem;
    justify-content: center;
    gap: 0.5rem;
}
.follows-tab {
    flex: 1;
    padding: 1rem 1.5rem;
    text-decoration: none;
    color: var(--text-color);
    text-align: center;
    transition: all 0.3s cubic-bezier(.77,0,.18,1);
    border-bottom: 3px solid transparent;
    font-weight: 500;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 0.5rem;
    position: relative;
    background: none;
    font-size: 1.1rem;
    cursor: pointer;
    z-index: 1;
}
.follows-tab .badge {
    margin-left: 0.5rem;
    font-size: 0.85em;
    padding: 0.2em 0.6em;
    border-radius: 0.8em;
    background: var(--primary-color);
    color: #fff;
    font-weight: 600;
    box-shadow: 0 2px 8px rgba(52,152,219,0.10);
    transition: background 0.3s;
}
.follows-tab.active {
    background: linear-gradient(90deg, #764ba2 0%, #3498db 100%);
    color: #fff;
    border-bottom: 3px solid #764ba2;
    box-shadow: 0 4px 16px rgba(52,152,219,0.10);
    animation: tabPop 0.5s cubic-bezier(.77,0,.18,1);
}
.follows-tab.active .badge {
    background: #fff;
    color: #3498db;
}
.follows-tab i {
    font-size: 1.2rem;
    transition: transform 0.3s;
}
.follows-tab:hover i {
    transform: scale(1.2) rotate(-8deg);
}
@keyframes tabPop {
    0% { transform: scale(1); }
    60% { transform: scale(1.12); }
    100% { transform: scale(1); }
}
@media (max-width: 768px) {
    .animated-nav {
        flex-direction: column;
        gap: 0;
    }
    .follows-tab {
        border-bottom: none;
        border-left: 3px solid transparent;
        justify-content: flex-start;
        font-size: 1rem;
        padding: 0.8rem 1rem;
    }
    .follows-tab.active {
        border-left: 3px solid #764ba2;
        background: linear-gradient(90deg, #764ba2 0%, #3498db 100%);
    }
}

/* User Cards and Content Styles */
.follows-section {
    background: var(--background-white);
    border-radius: 12px;
    box-shadow: var(--shadow-light);
    padding: 2rem;
    margin-bottom: 2rem;
}

.follows-section h3 {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    color: var(--heading-color);
    margin-bottom: 1.5rem;
    font-size: 1.5rem;
}

.follows-section h3 i {
    color: var(--primary-color);
    font-size: 1.7rem;
}

.users-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
    gap: 1.5rem;
}

.user-card {
    background: var(--background-white);
    border: 1px solid var(--border-color);
    border-radius: 12px;
    padding: 1.5rem;
    display: flex;
    align-items: flex-start;
    gap: 1rem;
    transition: all 0.3s cubic-bezier(.77,0,.18,1);
    position: relative;
    overflow: hidden;
    min-height: 120px;
}

.user-card::before {
    content: '';
    position: absolute;
    top: 0;
    left: -100%;
    width: 100%;
    height: 100%;
    background: linear-gradient(90deg, transparent, rgba(52,152,219,0.1), transparent);
    transition: left 0.5s;
}

.user-card:hover::before {
    left: 100%;
}

.user-card:hover {
    transform: translateY(-5px);
    box-shadow: var(--shadow-medium);
    border-color: var(--primary-color);
}

.user-card.mutual {
    border-color: #f39c12;
    background: linear-gradient(135deg, #fff 0%, #fff8e1 100%);
}

.user-card.suggested {
    border-color: #9b59b6;
    background: linear-gradient(135deg, #fff 0%, #f3e5f5 100%);
}

.user-avatar {
    width: 60px;
    height: 60px;
    border-radius: 50%;
    overflow: hidden;
    border: 3px solid var(--primary-color);
    flex-shrink: 0;
    position: relative;
    margin-top: 0.25rem;
}

.user-avatar img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    transition: transform 0.3s;
}

.user-card:hover .user-avatar img {
    transform: scale(1.1);
}

.user-info {
    flex: 1 1 0;
    min-width: 0;
    margin-right: 1rem;
    display: flex;
    flex-direction: column;
    justify-content: flex-start;
    align-items: flex-start;
}

.user-info h4 {
    margin: 0 0 0.5rem 0;
    color: var(--heading-color);
    font-size: 1.1rem;
    font-weight: 600;
    word-break: normal;
    overflow-wrap: break-word;
    white-space: normal;
    line-height: 1.3;
    max-width: 100%;
}

.user-status {
    margin: 0 0 0.5rem 0;
    color: var(--secondary-color);
    font-size: 0.9rem;
    display: flex;
    align-items: center;
    gap: 0.5rem;
    white-space: normal;
    line-height: 1.4;
}

.user-stats {
    margin: 0;
    color: var(--secondary-color);
    font-size: 0.85rem;
    display: flex;
    gap: 1rem;
    flex-wrap: wrap;
    white-space: normal;
    line-height: 1.4;
}

.user-stats span {
    display: flex;
    align-items: center;
    gap: 0.25rem;
    white-space: nowrap;
}

.user-actions {
    display: flex;
    flex-direction: row;
    gap: 0.5rem;
    width: 100%;
    margin-top: 0.5rem;
    align-items: center;
    justify-content: flex-start;
}

.user-actions .btn {
    padding: 0.35rem 0.7rem;
    font-size: 0.85rem;
    border-radius: 7px;
    width: auto;
    min-width: 70px;
}

.follow-btn {
    background: var(--primary-color);
    color: white;
    border: none;
}

.follow-btn:hover {
    background: var(--primary-dark);
    transform: scale(1.05);
}

.unfollow-btn {
    background: transparent;
    color: var(--accent-color);
    border: 1px solid var(--accent-color);
}

.unfollow-btn:hover {
    background: var(--accent-color);
    color: white;
    transform: scale(1.05);
}

/* Empty State Styles */
.empty-state {
    text-align: center;
    padding: 3rem 2rem;
    color: var(--secondary-color);
}

.empty-state i {
    margin-bottom: 1rem;
    opacity: 0.5;
    animation: float 3s ease-in-out infinite;
}

.empty-state h4 {
    margin: 1rem 0 0.5rem 0;
    color: var(--heading-color);
    font-size: 1.3rem;
}

.empty-state p {
    margin: 0;
    font-size: 1rem;
    line-height: 1.5;
}

@keyframes float {
    0%, 100% { transform: translateY(0px); }
    50% { transform: translateY(-10px); }
}

/* Loading Animation */
.loading-spinner {
    display: inline-block;
    width: 20px;
    height: 20px;
    border: 3px solid rgba(255,255,255,.3);
    border-radius: 50%;
    border-top-color: #fff;
    animation: spin 1s ease-in-out infinite;
}

@keyframes spin {
    to { transform: rotate(360deg); }
}

/* Responsive Design */
@media (max-width: 768px) {
    .users-grid {
        grid-template-columns: 1fr;
    }
    
    .user-card {
        flex-direction: column;
        text-align: center;
        padding: 1.5rem;
    }
    
    .user-actions {
        flex-direction: column;
        width: 100%;
    }
    
    .user-actions .btn {
        width: 100%;
    }
    
    .follows-section {
        padding: 1.5rem;
    }
}

@media (min-width: 768px) {
  .user-card {
    flex-direction: row;
    align-items: flex-start;
    text-align: left;
  }
  .user-avatar {
    margin-right: 1.2rem;
    margin-bottom: 0;
  }
  .user-info {
    flex: 1 1 0;
    min-width: 0;
    margin-right: 0;
    display: flex;
    flex-direction: column;
    align-items: flex-start;
    justify-content: flex-start;
  }
  .user-actions {
    display: flex;
    flex-direction: row;
    gap: 0.5rem;
    width: 100%;
    margin-top: 0.5rem;
    align-items: center;
    justify-content: flex-start;
  }
  .user-actions .btn {
    padding: 0.35rem 0.7rem;
    font-size: 0.85rem;
    border-radius: 7px;
    width: auto;
    min-width: 70px;
  }
}

@media (max-width: 600px) {
    .user-actions {
        flex-direction: column;
        align-items: stretch;
    }
    .user-actions .btn {
        width: 100%;
        min-width: 0;
    }
}

/* Success/Error Messages */
.follow-message {
    position: fixed;
    top: 20px;
    right: 20px;
    padding: 1rem 1.5rem;
    border-radius: 8px;
    color: white;
    font-weight: 500;
    z-index: 1000;
    transform: translateX(100%);
    transition: transform 0.3s ease;
}

.follow-message.show {
    transform: translateX(0);
}

.follow-message.success {
    background: #27ae60;
}

.follow-message.error {
    background: #e74c3c;
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Follow/Unfollow functionality
    document.addEventListener('click', function(e) {
        if (e.target.closest('.follow-btn') || e.target.closest('.unfollow-btn')) {
            e.preventDefault();
            const button = e.target.closest('.follow-btn, .unfollow-btn');
            const userId = button.dataset.userId;
            const isFollow = button.classList.contains('follow-btn');
            const action = isFollow ? 'follow' : 'unfollow'; // Determine the action

            // Show loading state
            const originalText = button.innerHTML;
            button.innerHTML = '<span class="loading-spinner"></span> ' + (isFollow ? 'Following...' : 'Unfollowing...');
            button.disabled = true;

            // Make AJAX request using URL-encoded form data, matching view_user.php
            fetch('includes/follow_action.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded', // Set content type explicitly
                },
                body: `followed_id=${encodeURIComponent(userId)}&action=${encodeURIComponent(action)}` // Use 'followed_id'
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Update button
                    if (data.is_following) { // Use data.is_following to determine the new state
                        button.classList.remove('follow-btn', 'btn-primary');
                        button.classList.add('unfollow-btn', 'btn-outline');
                        button.innerHTML = '<i class="fas fa-user-minus"></i> Unfollow';
                    } else {
                        button.classList.remove('unfollow-btn', 'btn-outline');
                        button.classList.add('follow-btn', 'btn-primary');
                        button.innerHTML = '<i class="fas fa-user-plus"></i> Follow';
                    }

                    // Show success message
                    showMessage(data.message, 'success');

                    // Update counts in navigation if they exist
                    updateFollowCounts();
                } else {
                    // Show error message
                    showMessage(data.message, 'error');

                    // Restore original button state
                    button.innerHTML = originalText;
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showMessage('An error occurred. Please try again.', 'error');
                button.innerHTML = originalText;
            })
            .finally(() => {
                button.disabled = false;
            });
        }
    });

    // Function to show messages
    function showMessage(message, type) {
        const messageDiv = document.createElement('div');
        messageDiv.className = `follow-message ${type}`;
        messageDiv.textContent = message;
        document.body.appendChild(messageDiv);

        // Show message
        setTimeout(() => messageDiv.classList.add('show'), 100);

        // Remove message after 3 seconds
        setTimeout(() => {
            messageDiv.classList.remove('show');
            setTimeout(() => document.body.removeChild(messageDiv), 300);
        }, 3000);
    }

    // Function to update follow counts in navigation
    function updateFollowCounts() {
        // This could be enhanced to update the actual counts via AJAX
        // For now, we'll just reload the page to get updated counts
        setTimeout(() => {
            window.location.reload();
        }, 1000);
    }

    // Add hover effects for user cards
    const userCards = document.querySelectorAll('.user-card');
    userCards.forEach(card => {
        card.addEventListener('mouseenter', function() {
            this.style.transform = 'translateY(-5px) scale(1.02)';
        });

        card.addEventListener('mouseleave', function() {
            this.style.transform = 'translateY(0) scale(1)';
        });
    });
});
</script>

<?php include 'includes/footer.php'; ?>