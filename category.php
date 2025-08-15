<?php
$page_title = "Category";
include 'includes/header.php';
require_once 'includes/functions.php'; // Ensure functions are available
require_once 'includes/db_connection.php'; // Ensure db connection is available

// Get category ID from URL
$category_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($category_id <= 0) {
    header("Location: index.php");
    exit();
}

// Get category details
$category = get_category_by_id($conn, $category_id);

if (!$category) {
    header("Location: index.php");
    exit();
}

$page_title = $category['name'];

// Pagination
$posts_per_page = 9;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $posts_per_page;

// Get total posts count for this category
$total_posts_query = "SELECT COUNT(*) as total FROM posts WHERE category_id = ? AND status = 'published'";
$total_stmt = $conn->prepare($total_posts_query);
$total_stmt->bind_param("i", $category_id);
$total_stmt->execute();
$total_posts = $total_stmt->get_result()->fetch_assoc()['total'];
$total_pages = ceil($total_posts / $posts_per_page);

// Get posts for this category, including like/dislike counts, dislike_button_status, user profile info, and comment count
$posts_query = "SELECT p.*, u.username, u.profile_image_path, u.prefers_avatar, u.gender,
                (SELECT COUNT(*) FROM likes WHERE post_id = p.id) as total_likes,
                (SELECT COUNT(*) FROM dislikes WHERE post_id = p.id) as total_dislikes,
                (SELECT COUNT(*) FROM comments WHERE post_id = p.id AND status = 'approved') as comment_count
                FROM posts p 
                LEFT JOIN users u ON p.user_id = u.id 
                WHERE p.category_id = ? AND p.status = 'published' 
                ORDER BY p.published_at DESC 
                LIMIT ? OFFSET ?";
$posts_stmt = $conn->prepare($posts_query);
$posts_stmt->bind_param("iii", $category_id, $posts_per_page, $offset);
$posts_stmt->execute();
$posts_result = $posts_stmt->get_result();

// Get current user's liked and disliked posts for this page
$current_user_id = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : 0;
$is_admin = isset($_SESSION['role']) && $_SESSION['role'] === 'admin';
$user_liked_posts = [];
$user_disliked_posts = [];
if ($current_user_id > 0) {
    // Fetch all post IDs on the current category page to optimize the subqueries
    $post_ids_on_page = [];
    if ($posts_result) {
        $temp_posts_data = [];
        while ($row = $posts_result->fetch_assoc()) {
            $temp_posts_data[] = $row;
            $post_ids_on_page[] = $row['id'];
        }
        $posts_result->data_seek(0); // Rewind for the main loop
    }

    if (!empty($post_ids_on_page)) {
        $ids_placeholder = implode(',', array_fill(0, count($post_ids_on_page), '?'));

        $liked_query = "SELECT post_id FROM likes WHERE user_id = ? AND post_id IN ($ids_placeholder)";
        $liked_stmt = $conn->prepare($liked_query);
        $types = 'i' . str_repeat('i', count($post_ids_on_page));
        $bind_params = array_merge([$current_user_id], $post_ids_on_page);
        $liked_stmt->bind_param($types, ...$bind_params);
        $liked_stmt->execute();
        $liked_result = $liked_stmt->get_result();
        while ($row = $liked_result->fetch_assoc()) {
            $user_liked_posts[$row['post_id']] = true;
        }
        $liked_stmt->close();

        $disliked_query = "SELECT post_id FROM dislikes WHERE user_id = ? AND post_id IN ($ids_placeholder)";
        $disliked_stmt = $conn->prepare($disliked_query);
        $disliked_stmt->bind_param($types, ...$bind_params);
        $disliked_stmt->execute();
        $disliked_result = $disliked_stmt->get_result();
        while ($row = $disliked_result->fetch_assoc()) {
            $user_disliked_posts[$row['post_id']] = true;
        }
        $disliked_stmt->close();
    }
}

// Helper function to determine if a path is a URL
if (!function_exists('is_url_optimized')) {
    function is_url_optimized($path) {
        return filter_var($path, FILTER_VALIDATE_URL);
    }
}

// Helper function to check if user can edit a post
function can_edit_post($post_user_id, $current_user_id, $is_admin) {
    return $is_admin || ($current_user_id && $current_user_id == $post_user_id);
}

?>

<div class="container">
    <!-- Category Header -->
    <div class="category-header text-center mb-4">
        <h1>Category: <?php echo htmlspecialchars($category['name']); ?></h1>
        <?php if ($category['description']): ?>
            <p class="category-description"><?php echo htmlspecialchars($category['description']); ?></p>
        <?php endif; ?>
        <p class="text-muted"><?php echo $total_posts; ?> post<?php echo $total_posts != 1 ? 's' : ''; ?> found</p>
    </div>
    
    <!-- Posts Grid -->
    <?php if ($posts_result->num_rows > 0): ?>
        <div class="posts-grid">
            <?php while ($post = $posts_result->fetch_assoc()): ?>
                <article class="post-card">
                    <?php 
                    $image_src = '';
                    if (!empty($post['image_path'])) {
                        if (is_url_optimized($post['image_path'])) {
                            $image_src = htmlspecialchars($post['image_path']) . '?q=80&w=400&h=250&fit=crop'; 
                        } else {
                            $image_src = 'uploads/' . htmlspecialchars(basename($post['image_path']));
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
                            <a href="category.php?id=<?php echo $category['id']; ?>"><span class="post-category"><?php echo htmlspecialchars($category['name']); ?></span></a>
                            <span class="post-author-info">
                                <a href="view_user.php?id=<?php echo $post['user_id']; ?>" class="post-author-avatar">
                                    <?php
                                    $author_profile_image = !empty($post['profile_image_path']) ? htmlspecialchars($post['profile_image_path']) : '';
                                    $author_prefers_avatar = isset($post['prefers_avatar']) ? (bool)$post['prefers_avatar'] : false;

                                    $author_profile_image_display = '';

                                    if ($author_prefers_avatar) {
                                        if (!empty($author_profile_image) && strpos($author_profile_image, 'avatars/') === 0 && file_exists($author_profile_image)) {
                                            $author_profile_image_display = $author_profile_image;
                                        } else {
                                            if (isset($post['gender']) && $post['gender'] == 'male') {
                                                $author_profile_image_display = 'avatars/male_avatar.png';
                                            } elseif (isset($post['gender']) && $post['gender'] == 'female') {
                                                $author_profile_image_display = 'avatars/female_avatar.png';
                                            } else {
                                                $author_profile_image_display = 'avatars/default_avatar.png';
                                            }
                                        }
                                    } elseif (!empty($author_profile_image)) {
                                        if (strpos($author_profile_image, 'profiles/') === 0) {
                                            $author_profile_image_display = $author_profile_image;
                                        } else {
                                            $author_profile_image_display = 'uploads/' . basename($author_profile_image);
                                            if (!file_exists($author_profile_image_display)) {
                                                $author_profile_image_display = $author_profile_image;
                                            }
                                        }
                                    } else {
                                        if (isset($post['gender']) && $post['gender'] == 'male') {
                                            $author_profile_image_display = 'avatars/male_avatar.png';
                                        } elseif (isset($post['gender']) && $post['gender'] == 'female') {
                                            $author_profile_image_display = 'avatars/female_avatar.png';
                                        } else {
                                            $author_profile_image_display = 'avatars/default_avatar.png';
                                        }
                                    }

                                    if (!filter_var($author_profile_image_display, FILTER_VALIDATE_URL) && !file_exists($author_profile_image_display)) {
                                        if (file_exists('../' . $author_profile_image_display)) {
                                            $author_profile_image_display = '../' . $author_profile_image_display;
                                        } else {
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
                            <div class="reaction-buttons-card">
                                <div class="post-likes" id="likes-<?php echo $post['id']; ?>">
                                    <button class="like-btn <?php echo isset($user_liked_posts[$post['id']]) ? 'active' : ''; ?>" data-post-id="<?php echo $post['id']; ?>" data-action="like">
                                        <i class="fas fa-thumbs-up"></i> <span class="like-count"><?php echo $post['total_likes']; ?></span>
                                    </button>
                                </div>
                                <?php if (isset($post['dislike_button_status']) && $post['dislike_button_status'] === 'enabled'): ?>
                                    <div class="post-dislikes" id="dislikes-<?php echo $post['id']; ?>">
                                        <button class="dislike-btn <?php echo isset($user_disliked_posts[$post['id']]) ? 'active' : ''; ?>" data-post-id="<?php echo $post['id']; ?>" data-action="dislike">
                                            <i class="fas fa-thumbs-down"></i> <span class="dislike-count"><?php echo $post['total_dislikes']; ?></span>
                                        </button>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </article>
            <?php endwhile; ?>
        </div>
        
        <!-- Pagination -->
        <?php if ($total_pages > 1): ?>
            <div class="pagination">
                <?php if ($page > 1): ?>
                    <a href="?id=<?php echo $category_id; ?>&page=<?php echo $page - 1; ?>">&laquo; Previous</a>
                <?php endif; ?>
                
                <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                    <?php if ($i == $page): ?>
                        <span class="current"><?php echo $i; ?></span>
                    <?php else: ?>
                        <a href="?id=<?php echo $category_id; ?>&page=<?php echo $i; ?>"><?php echo $i; ?></a>
                    <?php endif; ?>
                <?php endfor; ?>
                
                <?php if ($page < $total_pages): ?>
                    <a href="?id=<?php echo $category_id; ?>&page=<?php echo $page + 1; ?>">Next &raquo;</a>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    <?php else: ?>
        <div class="text-center">
            <h3>No posts found in this category</h3>
            <p>Check back later for new content!</p>
            <a href="index.php" class="btn btn-primary">Browse All Posts</a>
        </div>
    <?php endif; ?>
</div>

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

                // Determine if a cross-reaction button exists and its state (to update it visually)
                var otherButtonClass = (buttonClass === '.like-btn') ? '.dislike-btn' : '.like-btn';
                var otherBtnElem = document.querySelector(`${otherButtonClass}[data-post-id="${postId}"]`);
                
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
                            btnElem.classList.add('active');
                            btnElem.querySelector('span').textContent = data.total_likes;
                            // If dislike was removed, update dislike button
                            if (otherBtnElem) {
                                otherBtnElem.classList.remove('active');
                                otherBtnElem.querySelector('span').textContent = data.total_dislikes;
                            }
                        } else if (data.action === 'unliked') {
                            btnElem.classList.remove('active');
                            btnElem.querySelector('span').textContent = data.total_likes;
                        } else if (data.action === 'disliked') {
                            btnElem.classList.add('active');
                            btnElem.querySelector('span').textContent = data.total_dislikes;
                            // If like was removed, update like button
                            if (otherBtnElem) {
                                otherBtnElem.classList.remove('active');
                                otherBtnElem.querySelector('span').textContent = data.total_likes;
                            }
                        } else if (data.action === 'undisliked') {
                            btnElem.classList.remove('active');
                            btnElem.querySelector('span').textContent = data.total_dislikes;
                        }
                        
                    } else if (data.message) {
                        console.error("Error from server: " + data.message);
                        if (data.message === 'Login required') {
                            console.log('Login required to like or dislike posts. Redirecting...');
                            window.location.href = 'login.php';
                        } else {
                            console.error('Error: ' + data.message);
                        }
                    }
                })
                .catch(error => {
                    console.error('Fetch error:', error);
                });
            });
        });
    }

    setupReactionButton('.like-btn', 'like_post.php');
    setupReactionButton('.dislike-btn', 'dislike_post.php');
});
</script>

<style>
/* Add these styles to your existing CSS or create a new file if preferred */
.category-header {
    background: linear-gradient(135deg,#667eea 0%, #764ba2 100%);
    padding: 2rem;
    border-radius: 12px;
    box-shadow: 0 4px 15px rgba(0,0,0,0.1);
}

.category-description {
    font-size: 1.1rem;
    color: #6c757d;
    margin: 1rem 0;
}

/* Post Actions - Like/Dislike */
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
    background-color: var(--background-light, #f9f9f9);
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
.reaction-buttons-card button.like-btn.active {
    background-color: #28a745;
    color: #fff;
    border-color: #28a745;
}
.reaction-buttons-card button.dislike-btn.active {
    background-color: #dc3545;
    color: #fff;
    border-color: #dc3545;
}
.reaction-buttons-card button.like-btn.active:hover {
    background-color: #218838;
    border-color: #218838;
}
.reaction-buttons-card button.dislike-btn.active:hover {
    background-color: #c82333;
    border-color: #c82333;
}
.reaction-buttons-card button[disabled] {
    opacity: 0.6;
    cursor: not-allowed;
}

</style>

<?php include 'includes/footer.php'; ?>
