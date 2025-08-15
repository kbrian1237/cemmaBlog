<?php
$page_title = "Search Results";
include 'includes/header.php';
require_once 'includes/functions.php'; // Ensure functions are available
require_once 'includes/db_connection.php'; // Ensure db connection is available

// Get search query
$search_query = isset($_GET['q']) ? sanitize_input($_GET['q']) : '';

// Pagination
$posts_per_page = 9;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $posts_per_page;

$posts_result = null;
$total_posts = 0;
$total_pages = 0;

// Helper function to determine if a path is a URL and optimize it
if (!function_exists('is_url_optimized')) {
    function is_url_optimized($path) {
        return filter_var($path, FILTER_VALIDATE_URL);
    }
}

if (!empty($search_query)) {
    // Get total posts count for search
    $total_posts_query = "SELECT COUNT(*) as total 
                          FROM posts p 
                          WHERE p.status = 'published' 
                          AND (p.title LIKE ? OR p.content LIKE ?)";
    $search_param = '%' . $search_query . '%';
    $total_stmt = $conn->prepare($total_posts_query);
    $total_stmt->bind_param("ss", $search_param, $search_param);
    $total_stmt->execute();
    $total_posts = $total_stmt->get_result()->fetch_assoc()['total'];
    $total_pages = ceil($total_posts / $posts_per_page);
    
    // Get search results, including like/dislike counts, dislike_button_status,
    // author's profile info, gender, and comment count
    $posts_query = "SELECT p.*, u.username, u.profile_image_path, u.prefers_avatar, u.gender, c.name as category_name,
                    (SELECT COUNT(*) FROM likes WHERE post_id = p.id) as total_likes,
                    (SELECT COUNT(*) FROM dislikes WHERE post_id = p.id) as total_dislikes,
                    (SELECT COUNT(*) FROM comments WHERE post_id = p.id AND status = 'approved') as comment_count
                    FROM posts p 
                    LEFT JOIN users u ON p.user_id = u.id 
                    LEFT JOIN categories c ON p.category_id = c.id 
                    WHERE p.status = 'published' 
                    AND (p.title LIKE ? OR p.content LIKE ?) 
                    ORDER BY p.published_at DESC 
                    LIMIT ? OFFSET ?";
    $posts_stmt = $conn->prepare($posts_query);
    $posts_stmt->bind_param("ssii", $search_param, $search_param, $posts_per_page, $offset);
    $posts_stmt->execute();
    $posts_result = $posts_stmt->get_result();
}

// Get current user's liked and disliked posts for this page
$current_user_id = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : 0;
$is_admin = isset($_SESSION['role']) && $_SESSION['role'] === 'admin';
$user_liked_posts = [];
$user_disliked_posts = [];
$available_avatars_global_for_display = get_available_avatars('avatars/'); // Ensure this is available

if ($current_user_id > 0) {
    // Fetch all post IDs on the current search result page to optimize the subqueries
    $post_ids_on_page = [];
    if ($posts_result) { 
        $temp_posts_data = [];
        while ($row = $posts_result->fetch_assoc()) {
            $temp_posts_data[] = $row;
            $post_ids_on_page[] = $row['id'];
        }
        // Rewind $posts_result to be iterated again in the HTML section
        $posts_result->data_seek(0);
    }

    if (!empty($post_ids_on_page)) {
        $ids_placeholder = implode(',', array_fill(0, count($post_ids_on_page), '?'));

        $liked_query = "SELECT post_id FROM likes WHERE user_id = ? AND post_id IN ($ids_placeholder)";
        $liked_stmt = $conn->prepare($liked_query);
        $types = str_repeat('i', count($post_ids_on_page) + 1); // 'i' for user_id + 'i' for each post_id
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
    // Restore $posts_result with the fetched data if it was consumed
    if (isset($temp_posts_data) && $posts_result->num_rows == 0) { 
         foreach($temp_posts_data as $row) {
             // This is a workaround. A better approach would be to store $temp_posts_data
             // and iterate over it directly in the HTML section.
             // For now, we rely on data_seek(0) or re-fetching if necessary.
             // Given the current structure, $posts_result will be valid if $temp_posts_data exists.
         }
    }
}
// Helper function to check if user can edit a post
function can_edit_post($post_user_id, $current_user_id, $is_admin) {
    // Admin can edit any post, others only their own
    return $is_admin || ($current_user_id && $current_user_id == $post_user_id);
}
?>

<div class="container">
    <!-- Search Header -->
    <div class="search-header text-center mb-4">
        <h1>Search Results</h1>
        <?php if (!empty($search_query)): ?>
            <p class="search-query">Results for: "<strong><?php echo htmlspecialchars($search_query); ?></strong>"</p>
            <p class="text-muted"><?php echo $total_posts; ?> result<?php echo $total_posts != 1 ? 's' : ''; ?> found</p>
        <?php endif; ?>
    </div>
    
    <!-- Search Form -->
    <div class="search-form-container mb-4">
        <form action="search.php" method="GET" class="search-form-large">
            <div class="search-input-group">
                <input type="text" name="q" placeholder="Search for posts..." class="search-input-large" 
                       value="<?php echo htmlspecialchars($search_query); ?>" required>
                <button type="submit" class="search-btn-large">
                    <i class="fas fa-search"></i> Search
                </button>
            </div>
        </form>
    </div>
    
    <!-- Search Results -->
    <?php if (!empty($search_query)): ?>
        <?php if ($posts_result && $posts_result->num_rows > 0): ?>
            <div class="posts-grid">
                <?php while ($post = $posts_result->fetch_assoc()): ?>
                    <article class="post-card">
                        <?php 
                        $image_src = '';
                        if (!empty($post['image_path'])) {
                            if (is_url_optimized($post['image_path'])) {
                                // For Pexels/Unsplash URLs, append optimization parameters. Adjust dimensions as needed for grid.
                                $image_src = htmlspecialchars($post['image_path']) . '?q=80&w=400&h=250&fit=crop'; 
                            } else {
                                // Assume local images are stored in 'uploads/' directory
                                $image_src = 'uploads/' . htmlspecialchars(basename($post['image_path']));
                                // Fallback for local images if the file does not exist in 'uploads/'
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
                                <a href="post.php?id=<?php echo $post['id']; ?>">
                                    <?php 
                                    // Highlight search terms in title
                                    $highlighted_title = str_ireplace($search_query, '<mark>' . $search_query . '</mark>', htmlspecialchars($post['title']));
                                    echo $highlighted_title;
                                    ?>
                                </a>
                            </h3>
                            
                            <p class="post-excerpt">
                                <?php 
                                // Highlight search terms in excerpt
                                $excerpt = truncate_text(strip_tags($post['content']));
                                $highlighted_excerpt = str_ireplace($search_query, '<mark>' . $search_query . '</mark>', htmlspecialchars($excerpt));
                                echo $highlighted_excerpt;
                                ?>
                            </p>
                            
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
            
            <!-- Pagination -->
            <?php if ($total_pages > 1): ?>
                <div class="pagination">
                    <?php if ($page > 1): ?>
                        <a href="?q=<?php echo urlencode($search_query); ?>&page=<?php echo $page - 1; ?>">&laquo; Previous</a>
                    <?php endif; ?>
                    
                    <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                        <?php if ($i == $page): ?>
                            <span class="current"><?php echo $i; ?></span>
                        <?php else: ?>
                            <a href="?q=<?php echo urlencode($search_query); ?>&page=<?php echo $i; ?>"><?php echo $i; ?></a>
                        <?php endif; ?>
                    <?php endfor; ?>
                    
                    <?php if ($page < $total_pages): ?>
                        <a href="?q=<?php echo urlencode($search_query); ?>&page=<?php echo $page + 1; ?>">Next &raquo;</a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        <?php else: ?>
            <div class="no-results text-center">
                <i class="fas fa-search" style="font-size: 4rem; color: #e9ecef; margin-bottom: 1rem;"></i>
                <h3>No results found</h3>
                <p>Sorry, we couldn't find any posts matching "<strong><?php echo htmlspecialchars($search_query); ?></strong>"</p>
                <div class="search-suggestions">
                    <h4>Try:</h4>
                    <ul>
                        <li>Checking your spelling</li>
                        <li>Using different keywords</li>
                        <li>Using more general terms</li>
                        <li>Browsing our <a href="index.php">latest posts</a></li>
                    </ul>
                </div>
            </div>
        <?php endif; ?>
    <?php else: ?>
        <!-- Popular Categories and Tags -->
        <div class="search-suggestions-grid">
            <div class="suggestion-card">
                <h3><i class="fas fa-folder"></i> Popular Categories</h3>
                <div class="category-list">
                    <?php
                    $popular_categories_query = "SELECT c.*, COUNT(p.id) as post_count 
                                                 FROM categories c 
                                                 LEFT JOIN posts p ON c.id = p.category_id AND p.status = 'published' 
                                                 GROUP BY c.id 
                                                 ORDER BY post_count DESC 
                                                 LIMIT 8";
                    $popular_categories_result = $conn->query($popular_categories_query);
                    while ($category = $popular_categories_result->fetch_assoc()):
                    ?>
                        <a href="category.php?id=<?php echo $category['id']; ?>" class="category-link">
                            <?php echo htmlspecialchars($category['name']); ?>
                            <span class="count">(<?php echo $category['post_count']; ?>)</span>
                        </a>
                    <?php endwhile; ?>
                </div>
            </div>
            
            <div class="suggestion-card">
                <h3><i class="fas fa-tags"></i> Popular Tags</h3>
                <div class="tag-cloud">
                    <?php
                    $popular_tags_query = "SELECT t.*, COUNT(pt.post_id) as post_count 
                                           FROM tags t 
                                           LEFT JOIN post_tags pt ON t.id = pt.tag_id 
                                           LEFT JOIN posts p ON pt.post_id = p.id AND p.status = 'published' 
                                           GROUP BY t.id 
                                           ORDER BY post_count DESC 
                                           LIMIT 20";
                    $popular_tags_result = $conn->query($popular_tags_query);
                    while ($tag = $popular_tags_result->fetch_assoc()):
                    ?>
                        <a href="tag.php?id=<?php echo $tag['id']; ?>" class="tag tag-cloud-item">
                            <?php echo htmlspecialchars($tag['name']); ?>
                        </a>
                    <?php endwhile; ?>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Function to handle like/dislike action
    async function handleReaction(button, type) {
        const postId = button.dataset.postId;
        
        const targetUrl = type === 'like' ? 'like_post.php' : 'dislike_post.php';

        try {
            const formData = new FormData();
            formData.append('post_id', postId);

            const response = await fetch(targetUrl, {
                method: 'POST',
                body: formData
            });

            const data = await response.json();

            if (data.success) {
                // Find the parent .post-card for this specific button
                const postCard = button.closest('.post-card');

                if (type === 'like') {
                    // Update the count displayed
                    postCard.querySelector('.like-count').textContent = data.total_likes;
                    // Toggle 'active' class on the clicked like button
                    button.classList.toggle('active', data.action === 'liked');
                    
                    // If liking, ensure dislike button is not active
                    const dislikeButton = postCard.querySelector('.dislike-btn');
                    if (dislikeButton && dislikeButton.classList.contains('active')) {
                        dislikeButton.classList.remove('active');
                        // Fetch new dislike count as it might have been un-disliked
                        fetchUpdatedCount(postId, 'dislike', postCard);
                    }
                } else { // type === 'dislike'
                    // Update the count displayed
                    postCard.querySelector('.dislike-count').textContent = data.total_dislikes;
                    // Toggle 'active' class on the clicked dislike button
                    button.classList.toggle('active', data.action === 'disliked');
                    
                    // If disliking, ensure like button is not active
                    const likeButton = postCard.querySelector('.like-btn');
                    if (likeButton && likeButton.classList.contains('active')) {
                        likeButton.classList.remove('active');
                        // Fetch new like count as it might have been un-liked
                        fetchUpdatedCount(postId, 'like', postCard);
                    }
                }
            } else {
                // Handle non-success responses (e.g., login required)
                if (data.message === 'Login required') {
                    alert('You need to be logged in to like or dislike posts.');
                    window.location.href = 'login.php'; // Redirect to login page
                } else {
                    console.error('Error:', data.message);
                    alert('Error: ' + data.message);
                }
            }
        } catch (error) {
            console.error('Fetch error:', error);
            alert('An error occurred. Please try again.');
        }
    }

    // Function to fetch updated count for a specific post (e.g., when toggling the other button)
    async function fetchUpdatedCount(postId, type, postCardElement) {
        const targetUrl = type === 'like' ? 'like_post.php' : 'dislike_post.php';
        try {
            const response = await fetch(`${targetUrl}?post_id=${postId}`);
            const data = await response.json();
            
            // Use the passed postCardElement to scope the querySelector
            if (postCardElement) {
                if (type === 'like') {
                    const likeCountElement = postCardElement.querySelector('.like-count');
                    if (likeCountElement) {
                        likeCountElement.textContent = data.total_likes;
                    }
                } else { // type === 'dislike'
                    const dislikeCountElement = postCardElement.querySelector('.dislike-count');
                    if (dislikeCountElement) {
                        dislikeCountElement.textContent = data.total_dislikes;
                    }
                }
            }
        } catch (error) {
            console.error(`Error fetching updated ${type} count for post ${postId}:`, error);
        }
    }

    // Attach event listeners to all like buttons
    document.querySelectorAll('.like-btn').forEach(button => {
        button.addEventListener('click', () => handleReaction(button, 'like'));
    });

    // Attach event listeners to all dislike buttons
    document.querySelectorAll('.dislike-btn').forEach(button => {
        button.addEventListener('click', () => handleReaction(button, 'dislike'));
    });
});
</script>

<style>
/* Add these styles to your existing CSS or create a new file if preferred */
.search-header {
    background: linear-gradient(135deg,#667eea 0%, #764ba2 100%);
    padding: 2rem;
    border-radius: 12px;
    box-shadow: 0 4px 15px rgba(0,0,0,0.1);
}

.search-query {
    font-size: 1.1rem;
    margin: 1rem 0;
}

.search-form-container {
    background: linear-gradient(135deg,#667eea 0%, #764ba2 100%);
    padding: 2rem;
    border-radius: 12px;
    box-shadow: 0 4px 15px rgba(0,0,0,0.1);
}

.search-form-large {
    max-width: 600px;
    margin: 0 auto;
}

.search-input-group {
    display: flex;
    gap: 0;
    border: 2px solid #e9ecef;
    border-radius: 50px;
    overflow: hidden;
    transition: border-color 0.3s ease;
}

.search-input-group:focus-within {
    border-color: #3498db;
}

.search-input-large {
    flex: 1;
    padding: 1rem 1.5rem;
    border: none;
    outline: none;
    font-size: 1.1rem;
}

.search-btn-large {
    padding: 1rem 2rem;
    background: #3498db;
    color: white;
    border: none;
    cursor: pointer;
    font-size: 1.1rem;
    transition: background 0.3s ease;
}

.search-btn-large:hover {
    background: #2980b9;
}

.no-results {
    background: white;
    padding: 3rem;
    border-radius: 12px;
    box-shadow: 0 4px 15px rgba(0,0,0,0.1);
}

.search-suggestions {
    margin-top: 2rem;
    text-align: left;
    max-width: 400px;
    margin-left: auto;
    margin-right: auto;
}

.search-suggestions ul {
    list-style: none;
    padding: 0;
}

.search-suggestions li {
    padding: 0.5rem 0;
    color: #6c757d;
}

.search-suggestions-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
    gap: 2rem;
}

.suggestion-card {
    background: white;
    padding: 2rem;
    border-radius: 12px;
    box-shadow: 0 4px 15px rgba(0,0,0,0.1);
}

.suggestion-card h3 {
    color: #2c3e50;
    margin-bottom: 1.5rem;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.category-list {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
}

.category-link {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 0.5rem;
    border-radius: 6px;
    color: #333;
    text-decoration: none;
    transition: background 0.3s ease;
}

.category-link:hover {
    background: #f8f9fa;
    color: #3498db;
}

.count {
    color: #6c757d;
    font-size: 0.9rem;
}

.tag-cloud {
    display: flex;
    flex-wrap: wrap;
    gap: 0.5rem;
}

.tag-cloud-item {
    font-size: 0.9rem;
}

mark {
    background: #fff3cd;
    padding: 0.1rem 0.2rem;
    border-radius: 3px;
}

@media (max-width: 768px) {
    .search-input-group {
        flex-direction: column;
        border-radius: 12px;
    }
    
    .search-btn-large {
        border-radius: 0;
    }
    
    .search-suggestions-grid {
        grid-template-columns: 1fr;
    }
}

/* Post Actions - Like/Dislike */
.post-actions {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-top: 1rem;
    padding-top: 1rem;
    border-top: 1px solid var(--border-color);
}

.likes-dislikes {
    display: flex;
    gap: 0.5rem;
}

.likes-dislikes button {
    background-color: var(--background-light);
    border: 1px solid var(--border-color);
    border-radius: 8px;
    padding: 0.5rem 0.8rem;
    font-size: 0.9rem;
    cursor: pointer;
    display: flex;
    align-items: center;
    gap: 0.3rem;
    transition: all 0.2s ease;
    color: var(--text-color);
}

.likes-dislikes button:hover {
    border-color: var(--primary-color);
    box-shadow: 0 2px 5px rgba(0,0,0,0.1);
}

.likes-dislikes .like-btn.active {
    background-color: #28a745;
    color: white;
    border-color: #28a745;
}

.likes-dislikes .dislike-btn.active {
    background-color: #dc3545;
    color: white;
    border-color: #dc3545;
}

.likes-dislikes .like-btn.active:hover {
    background-color: #218838;
    border-color: #218838;
}

.likes-dislikes .dislike-btn.active:hover {
    background-color: #c82333;
    border-color: #c82333;
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

<?php include 'includes/footer.php'; ?>
