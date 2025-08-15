<?php
$page_title = "Home";
include 'includes/header.php';
require_once 'includes/functions.php'; // Ensure functions.php is included for dislike helpers

$available_avatars_global_for_display = get_available_avatars('avatars/');

// Pagination
$posts_per_page = 6;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $posts_per_page;

// Get total posts count
$total_posts_query = "SELECT COUNT(*) as total FROM posts WHERE status = 'published'";
$total_result = $conn->query($total_posts_query);
$total_posts = $total_result->fetch_assoc()['total'];
$total_pages = ceil($total_posts / $posts_per_page);

// Get featured posts (This section is handled by includes/featured_posts.php, which was already optimized)
// The queries for featured posts are left here as they might be used elsewhere or for context,
// but the actual display logic is in featured_posts.php.
$featured_query = "SELECT p.*, u.username, c.name as category_name 
                   FROM posts p 
                   LEFT JOIN users u ON p.user_id = u.id 
                   LEFT JOIN categories c ON p.category_id = c.id 
                   WHERE p.status = 'published' AND p.is_featured = 1 
                   ORDER BY p.published_at DESC 
                   LIMIT 3";
$featured_result = $conn->query($featured_query);

// Determine the view type
$view = isset($_GET['view']) && in_array($_GET['view'], ['popular','trending','latest'], true)
    ? $_GET['view']
    : 'latest';

// Base query for posts
$base_query = "SELECT p.*,
                        u.username,
                        c.name as category_name,
                        u.profile_image_path,
                        u.prefers_avatar,
                        u.gender,
                        (SELECT COUNT(*) FROM comments WHERE post_id = p.id AND status = 'approved') as comment_count,
                        (SELECT COUNT(*) FROM likes WHERE post_id = p.id) as like_count
                FROM posts p 
                LEFT JOIN users u ON p.user_id = u.id 
                LEFT JOIN categories c ON p.category_id = c.id 
                WHERE p.status = 'published'";

// Modify query based on view type
switch ($view) {
    case 'popular':
        $posts_query = $base_query . " ORDER BY like_count DESC, p.published_at DESC LIMIT $posts_per_page OFFSET $offset";
        break;
    case 'trending':
        // Trending: prioritize posts from last 7 days by likes, but don't exclude older posts
        $posts_query = $base_query . " ORDER BY 
                                      (p.published_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)) DESC, 
                                      like_count DESC, 
                                      p.published_at DESC 
                                      LIMIT $posts_per_page OFFSET $offset";
        break;
    case 'latest':
    default:
        $posts_query = $base_query . " ORDER BY p.published_at DESC LIMIT $posts_per_page OFFSET $offset";
        break;
}

$posts_result = $conn->query($posts_query);


?>

<!-- Hero Section -->
<section class="hero">
    <div class="container">
        <h1>Welcome to Cemma</h1>
        <p>Where words find meaning — explore stories, insights, and ideas worth sharing.</p>

        <a href="#latest-posts" class="btn btn-primary">Explore Posts</a>
    </div>
</section>
<!-- Featured Posts Section -->
<div class="container-f" style="width: 100%; max-width: 100vw;"></div>
<?php include 'featured_posts.php'; ?>
</div>
<!-- Latest Posts Section -->
<section id="latest-posts" class="latest-posts-section">
<?php
    $view_titles = [
        'popular' => 'Popular Posts',
        'trending' => 'Trending Posts',
        'latest' => 'Latest Posts'
    ];
    $view_title = isset($view_titles[$view]) ? $view_titles[$view] : 'Latest Posts';
?>
<h2 class="text-center mb-3"><?php echo $view_title; ?></h2>
<div class="post-view-switcher">
    <a href="index.php?view=popular#latest-posts" class="btn <?php echo ($view === 'popular') ? 'btn-primary' : 'btn-outline-primary'; ?> switch-btn">Popular</a>
    <a href="index.php?view=trending#latest-posts" class="btn <?php echo ($view === 'trending') ? 'btn-primary' : 'btn-outline-primary'; ?> switch-btn">Trending</a>
    <a href="index.php?view=latest#latest-posts" class="btn <?php echo ($view === 'latest' || empty($view)) ? 'btn-primary' : 'btn-outline-primary'; ?> switch-btn">Latest</a>
</div>

        
        <?php if ($posts_result->num_rows > 0): ?>
            <div class="posts-grid">
                <?php
                // Helper function to determine if a path is a URL
                function is_url_optimized($path) {
                    return filter_var($path, FILTER_VALIDATE_URL);
                }
                // Get current user info (assuming session is used)
                $current_user_id = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : null;
                $is_admin = isset($_SESSION['role']) && $_SESSION['role'] === 'admin';

                // Helper function to check if user can edit a post
                function can_edit_post($post_user_id, $current_user_id, $is_admin) {
                    // Admin can edit any post, others only their own
                    return $is_admin || ($current_user_id && $current_user_id == $post_user_id);
                }

                // Helper: get total likes for a post (already in functions.php)
                // Helper: check if user liked a post (already in functions.php)
                // Helper: get total dislikes for a post (already in functions.php)
                // Helper: check if user disliked a post (already in functions.php)
                ?>
                <?php while ($post = $posts_result->fetch_assoc()): ?>
                    <article class="post-card">
                        <?php 
                        $image_src = '';
                        if (!empty($post['image_path'])) {
                            if (is_url_optimized($post['image_path'])) {
                                // For Unsplash URLs, append optimization parameters. Adjust dimensions as needed for grid.
                                $image_src = htmlspecialchars($post['image_path']) . '?q=80&w=400&h=250&fit=crop'; 
                            } else {
                                $image_src = 'uploads/' . htmlspecialchars(basename($post['image_path'])); // Assuming local paths are 'uploads/filename.jpg'
                                // Fallback for local images if the path isn't relative to root correctly
                                if (!file_exists($image_src)) {
                                    $image_src = '../' . htmlspecialchars($post['image_path']);
                                }
                            }
                        }
                        ?>
                        <?php if ($image_src): ?>
                            <a href="post.php?id=<?php echo $post['id']; ?>"><div class="post-image">
                                <img src="<?php echo $image_src; ?>" 
                                     alt="<?php echo htmlspecialchars($post['title']); ?>" 
                                     class="post-image"
                                     loading="lazy"
                                     onerror="this.onerror=null;this.src='https://placehold.co/400x250/cccccc/333333?text=Image+Not+Found';">
                            </div></a>
                        <?php else: ?>
                            <div class="post-image no-image" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); display: flex; align-items: center; justify-content: center; color: white; font-size: 1.2rem;">
                                <i class="fas fa-image"></i>
                            </div>
                        <?php endif; ?>
                        
                        <div class="post-content">
                            <div class="post-meta">
                                <a href="category.php?id=<?php echo $post['category_id']; ?>"><span class="post-category"><?php echo htmlspecialchars($post['category_name'] ?? 'Uncategorized'); ?></span></a>
                                <span class="post-author-info">
                                    <a href="view_user.php?id=<?php echo $post['user_id']; ?>" class="post-author-avatar">
                                            <?php
                                            $author_profile_image = !empty($post['profile_image_path']) ? htmlspecialchars($post['profile_image_path']) : '';
                                            $author_prefers_avatar = isset($post['prefers_avatar']) ? (bool)$post['prefers_avatar'] : false; // Get the prefers_avatar flag

                                            $author_profile_image_display = '';

                                            if ($author_prefers_avatar) {
                                                // If user prefers avatar, use gender-based default or stored avatar path
                                                if (!empty($author_profile_image) && strpos($author_profile_image, 'avatars/') === 0 && file_exists($author_profile_image)) {
                                                    // If profile_image_path is already an avatar and exists
                                                    $author_profile_image_display = $author_profile_image;
                                                } else {
                                                    // Fallback to gender-based default if no valid avatar path or custom path preferred
                                                    if (isset($post['gender']) && $post['gender'] == 'male') {
                                                        $author_profile_image_display = 'avatars/male_avatar.png';
                                                    } elseif (isset($post['gender']) && $post['gender'] == 'female') {
                                                        $author_profile_image_display = 'avatars/female_avatar.png';
                                                    } else {
                                                        $author_profile_image_display = 'avatars/default_avatar.png';
                                                    }
                                                }
                                            } elseif (!empty($author_profile_image)) {
                                                // If user does not prefer avatar and has a custom profile image
                                                if (strpos($author_profile_image, 'profiles/') === 0) {
                                                    $author_profile_image_display = $author_profile_image;
                                                } else {
                                                    // Fallback for cases where path might be directly 'uploads/filename.jpg' or similar
                                                    $author_profile_image_display = 'uploads/' . basename($author_profile_image);
                                                    if (!file_exists($author_profile_image_display)) {
                                                        $author_profile_image_display = $author_profile_image; // Use as is if not in uploads
                                                    }
                                                }
                                            } else {
                                                // No profile image or avatar preference explicitly set, use default avatar based on gender
                                                if (isset($post['gender']) && $post['gender'] == 'male') {
                                                    $author_profile_image_display = 'avatars/male_avatar.png';
                                                } elseif (isset($post['gender']) && $post['gender'] == 'female') {
                                                    $author_profile_image_display = 'avatars/female_avatar.png';
                                                } else {
                                                    $author_profile_image_display = 'avatars/default_avatar.png';
                                                }
                                            }

                                            // Final check to ensure the path is correct if it's a local file
                                            if (!filter_var($author_profile_image_display, FILTER_VALIDATE_URL) && !file_exists($author_profile_image_display)) {
                                                 // If it's a local path and doesn't exist, try prepending '../' (adjust as per your actual file structure)
                                                if (file_exists('../' . $author_profile_image_display)) {
                                                    $author_profile_image_display = '../' . $author_profile_image_display;
                                                } else {
                                                    // As a last resort, use a generic default if none found
                                                    $author_profile_image_display = 'avatars/default_avatar.png';
                                                }
                                            }
                                            ?>
                                            <img src="<?php echo $author_profile_image_display; ?>" alt="<?php echo htmlspecialchars($post['username']); ?>'s Profile" class="avatar-small">
                                        </a>
                                    <a href="view_user.php?id=<?php echo $post['user_id']; ?>"><?php echo htmlspecialchars($post['username']); ?></a>
                                    <!-- Follower Count -->
                                    <span class="follower-count-display">
                                        (<a href="view_user.php?id=<?php echo $post['user_id']; ?>#profile-stats-bottom-row" class="text-primary"><?php echo get_follower_count($conn, $post['user_id']); ?> Followers</a>)
                                    </span>
                                </span>
                                <span><i class="fas fa-calendar"></i> <?php echo format_date($post['published_at']); ?></span>
                            </div>
                            
                            <h3 class="post-title">
                                <a href="post.php?id=<?php echo $post['id']; ?>"><?php echo htmlspecialchars($post['title']); ?></a>
                            </h3>
                            
                            <p class="post-excerpt"><?php echo truncate_text(strip_tags($post['content'])); ?></p>
                            
                            <div class="post-tags">
                                <?php
                                $tags = get_post_tags($conn, $post['id']);
                                foreach ($tags as $tag):
                                ?>
                                    <a href="tag.php?id=<?php echo $tag['id']; ?>" class="tag"><?php echo htmlspecialchars($tag['name']); ?></a>
                                <?php endforeach; ?>
                            </div>

                           <div class="post-actions-bottom">
                                <a href="post.php?id=<?php echo $post['id']; ?>" class="btn btn-primary">Read More</a>
                                <?php if (can_edit_post($post['user_id'], $current_user_id, $is_admin)): ?>
                                    <a href="edit_post.php?id=<?php echo $post['id']; ?>" class="edit-post-btn" onclick="return confirm('Edit this post?');">Edit</a>
                                <?php endif; ?>
                                <!-- Comment Count -->
                                <a href="post.php?id=<?php echo $post['id']; ?>#comments-section" class="comment-count-link">
                                    <i class="fas fa-comments"></i> <?php echo htmlspecialchars($post['comment_count']); ?>
                                </a>
                                <!-- Like and Dislike Buttons -->
                                <div class="reaction-buttons-card">
                                    <div class="post-likes" id="likes-<?php echo $post['id']; ?>">
                                        <?php
                                            $likes_count = get_post_likes($conn, $post['id']);
                                            $liked = user_liked_post($conn, $post['id'], $current_user_id);
                                        ?>
                                        <button 
                                            class="like-btn<?php echo $liked ? ' liked' : ''; ?>" 
                                            data-post-id="<?php echo $post['id']; ?>" 
                                            <?php echo $current_user_id ? '' : 'disabled title="Login to like"'; ?>>
                                            <i class="fas fa-thumbs-up"></i>
                                            <span class="like-count"><?php echo $likes_count; ?></span>
                                        </button>
                                    </div>
                                    <!-- Dislike Button - Only show if status is 'enabled' -->
                                    <?php if (isset($post['dislike_button_status']) && $post['dislike_button_status'] === 'enabled'): ?>
                                        <div class="post-dislikes" id="dislikes-<?php echo $post['id']; ?>">
                                            <?php
                                                $dislikes_count = get_post_dislikes($conn, $post['id']);
                                                $disliked = user_disliked_post($conn, $post['id'], $current_user_id);
                                            ?>
                                            <button 
                                                class="dislike-btn<?php echo $disliked ? ' disliked' : ''; ?>" 
                                                data-post-id="<?php echo $post['id']; ?>" 
                                                <?php echo $current_user_id ? '' : 'disabled title="Login to dislike"'; ?>>
                                                <i class="fas fa-thumbs-down"></i>
                                                <span class="dislike-count"><?php echo $dislikes_count; ?></span>
                                            </button>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </article>
                <?php endwhile; ?>
            </div>
<style>
/* Post view switcher */
.post-view-switcher {
    display: flex;
    gap: 0.5rem;
    justify-content: center;
    flex-wrap: wrap;
    margin: 0.5rem 0 1rem;
}
.post-view-switcher .switch-btn {
    margin: 0.125rem;
}
/* New styles for post-actions-bottom */
.post-actions-bottom {
    display: flex;
    flex-wrap: wrap; /* Allow items to wrap on smaller screens */
    justify-content: space-between;
    align-items: center;
    margin-top: 1rem;
    padding-top: 1rem;
    border-top: 1px solid var(--border-color, #eee);
    gap: 0.8rem; /* Space between action items */
}

.post-actions-bottom .btn {
    flex-shrink: 0; /* Prevent buttons from shrinking */
}

.comment-count-link {
    display: inline-flex;
    align-items: center;
    gap: 0.3rem;
    color: var(--secondary-color);
    font-size: 0.9rem;
    text-decoration: none;
    transition: color 0.2s ease;
}

.comment-count-link:hover {
    color: var(--primary-color);
}

.comment-count-link i {
    font-size: 1rem;
}

/* Adjustments for reaction buttons within post-actions-bottom */
.post-actions-bottom .reaction-buttons-card {
    margin-top: 0; /* Remove top margin if already in post-actions-bottom */
    padding-top: 0; /* Remove top padding */
    border-top: none; /* Remove border */
    justify-content: flex-start; /* Align to start within its flex container */
    flex-grow: 1; /* Allow it to take available space */
}

/* Ensure the author info is nicely aligned */
.post-author-info {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    flex-wrap: wrap; /* Allow wrapping for author info on small screens */
}

.follower-count-display {
    font-size: 0.85em;
    color: var(--secondary-color);
}

.follower-count-display a {
    color: var(--primary-color);
    text-decoration: none;
}

.follower-count-display a:hover {
    text-decoration: underline;
}

.edit-post-btn {
    margin-left: 8px;
    font-size: 0.95em;
    padding: 4px 10px;
    background: #f6c343;
    color: #222;
    border: none;
    border-radius: 3px;
    text-decoration: none;
    transition: background 0.2s;
}
.edit-post-btn {
    background: #e0a800;
    color: #fff;
}
/* Post Actions - Like/Dislike */
.reaction-buttons-card {
    display: flex;
    justify-content: flex-end;
    align-items: center;
    margin-top: 1rem;
    padding-top: 1rem;
    border-top: 1px solid var(--border-color, #eee);
    gap: 0.5rem;
}
.reaction-buttons-card .post-likes,
.reaction-buttons-card .post-dislikes {
    display: flex;
    align-items: center;
}
.reaction-buttons-card button.like-btn,
.reaction-buttons-card button.dislike-btn {
    background-color: var(--background-light,rgb(249, 249, 249));
    border: 1px solid var(--border-color, #ddd);
    border-radius: 8px;
    padding: 0.5rem 0.8rem;
    font-size: 0.9rem;
    cursor: pointer;
    display: flex;
    align-items: center;
    gap: 0.3rem;
    transition: all 0.2s ease;
    color: var(--text-color, #222);
    outline: none;
}
.reaction-buttons-card button.like-btn:hover,
.reaction-buttons-card button.dislike-btn:hover {
    border-color: var(--primary-color, #007bff);
    box-shadow: 0 2px 5px rgba(0,0,0,0.07);
}
.reaction-buttons-card button.like-btn.liked,
.reaction-buttons-card button.like-btn.active {
    background-color: #28a745;
    color: #fff;
    border-color: #28a745;
}
.reaction-buttons-card button.dislike-btn.disliked,
.reaction-buttons-card button.dislike-btn.active {
    background-color: #dc3545;
    color: #fff;
    border-color: #dc3545;
}
.reaction-buttons-card button.like-btn.liked:hover,
.reaction-buttons-card button.like-btn.active:hover {
    background-color: #218838;
    border-color: #218838;
}
.reaction-buttons-card button.dislike-btn.disliked:hover,
.reaction-buttons-card button.dislike-btn.active:hover {
    background-color: #c82333;
    border-color: #c82333;
}
.reaction-buttons-card button[disabled] {
    opacity: 0.6;
    cursor: not-allowed;
}

</style>
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Universal handler for Like and Dislike buttons
    function setupReactionButton(buttonClass, targetScript) {
        document.querySelectorAll(buttonClass).forEach(function(btn) {
            btn.addEventListener('click', function(e) {
                e.preventDefault();
                var postId = this.getAttribute('data-post-id');
                var btnElem = this;
                var iconClass = buttonClass === '.like-btn' ? 'liked' : 'disliked'; // CSS class to toggle
                // Removed isCurrentlyActive as server determines action

                // Determine if a cross-reaction button exists and its state (to update it visually)
                var otherButtonClass = (buttonClass === '.like-btn') ? '.dislike-btn' : '.like-btn';
                var otherBtnElem = document.querySelector(`${otherButtonClass}[data-post-id="${postId}"]`);
                var otherIconClass = (buttonClass === '.like-btn') ? 'disliked' : 'liked';
                
                fetch(targetScript, {
                    method: 'POST',
                    headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                    body: 'post_id=' + encodeURIComponent(postId)
                })
                .then(response => {
                    const contentType = response.headers.get("content-type");
                    if (contentType && contentType.indexOf("application/json") !== -1) {
                        return response.json();
                    } else {
                        console.error("Server response was not JSON. Response:", response);
                        throw new TypeError("Oops, we haven't got JSON!");
                    }
                })
                .then(data => {
                    if (data.success) {
                        // Update the clicked button's state and count
                        if (data.action === 'liked') {
                            btnElem.classList.add('liked');
                            btnElem.querySelector('span').textContent = data.total_likes;
                            // If dislike was removed, update dislike button
                            if (otherBtnElem) {
                                otherBtnElem.classList.remove('disliked');
                                otherBtnElem.querySelector('span').textContent = data.total_dislikes;
                            }
                        } else if (data.action === 'unliked') {
                            btnElem.classList.remove('liked');
                            btnElem.querySelector('span').textContent = data.total_likes;
                        } else if (data.action === 'disliked') {
                            btnElem.classList.add('disliked');
                            btnElem.querySelector('span').textContent = data.total_dislikes;
                            // If like was removed, update like button
                            if (otherBtnElem) {
                                otherBtnElem.classList.remove('liked');
                                otherBtnElem.querySelector('span').textContent = data.total_likes;
                            }
                        } else if (data.action === 'undisliked') {
                            btnElem.classList.remove('disliked');
                            btnElem.querySelector('span').textContent = data.total_dislikes;
                        }
                        
                    } else if (data.message) {
                        console.error("Error from server: " + data.message);
                    }
                })
                .catch(error => {
                    console.error('Fetch error:', error);
                    console.error('Failed to process reaction due to network or server error.');
                });
            });
        });
    }

    setupReactionButton('.like-btn', 'like_post.php');
    setupReactionButton('.dislike-btn', 'dislike_post.php');

});
</script>
            
            <!-- Pagination -->
            <?php if ($total_pages > 1): ?>
                <div class="pagination">
                    <?php $view_param = isset($view) ? '&view=' . urlencode($view) : ''; ?>
                    <?php if ($page > 1): ?>
                        <a href="?page=<?php echo ($page - 1) . $view_param; ?>#latest-posts">&laquo; Previous</a>
                    <?php endif; ?>
                    
                    <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                        <?php if ($i == $page): ?>
                            <span class="current"><?php echo $i; ?></span>
                        <?php else: ?>
                            <a href="?page=<?php echo $i . $view_param; ?>#latest-posts"><?php echo $i; ?></a>
                        <?php endif; ?>
                    <?php endfor; ?>
                    
                    <?php if ($page < $total_pages): ?>
                        <a href="?page=<?php echo ($page + 1) . $view_param; ?>#latest-posts">Next &raquo;</a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        <?php else: ?>
            <div class="text-center">
                <h3>No posts found</h3>
                <p>Be the first to create a post!</p>
                <?php if (is_logged_in()): ?>
                    <a href="create_post.php" class="btn btn-primary">Create Post</a>
                <?php else: ?>
                    <a href="register.php" class="btn btn-primary">Join Us</a>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </section>
</div>

<?php include 'includes/footer.php'; ?>
