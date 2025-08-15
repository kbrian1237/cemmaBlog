<?php
// post.php
// IMPORTANT: All database queries and redirection logic must be at the very top before any HTML output.

// Ensure session is started if not already
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Include database connection and functions early, as they are needed for checks
require_once 'includes/db_connection.php';
require_once 'includes/functions.php'; // Ensure functions.php is included for helpers like get_available_avatars, get_post_tags, get_follower_count, get_post_likes, user_liked_post, get_post_dislikes, user_disliked_post, can_edit_post

// Get post ID from URL as early as possible
$post_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// Redirect if no valid post ID is provided
if ($post_id <= 0) {
    header("Location: index.php");
    exit();
}

// Get post details, including author's profile info and comment count
// This query MUST happen BEFORE including header.php
$post_query = "SELECT p.*, u.username, u.profile_image_path, u.gender, u.prefers_avatar, c.name as category_name,
               (SELECT COUNT(*) FROM comments WHERE post_id = p.id AND status = 'approved') as comment_count
               FROM posts p 
               LEFT JOIN users u ON p.user_id = u.id 
               LEFT JOIN categories c ON p.category_id = c.id 
               WHERE p.id = ? AND p.status = 'published'";
$post_stmt = $conn->prepare($post_query);

if ($post_stmt === false) {
    // Handle SQL prepare error
    error_log("Post query prepare failed: " . $conn->error);
    header("Location: index.php?error=db_error"); // Redirect with an error indicator
    exit();
}

$post_stmt->bind_param("i", $post_id);
$post_stmt->execute();
$post_result = $post_stmt->get_result();

if ($post_result->num_rows == 0) {
    // If post not found, redirect BEFORE any HTML output
    header("Location: index.php");
    exit();
}

$post = $post_result->fetch_assoc();
$post_stmt->close(); // Close the statement after fetching results

// Set page title after fetching post data
$page_title = $post['title'];

// Now that all critical checks and redirects are done, include the header.
include 'includes/header.php'; // This includes header HTML and opens <body>

// From here onwards, HTML output has started, so no more header() calls.

$available_avatars_global_for_display = get_available_avatars('avatars/');


// Get post tags
$tags = get_post_tags($conn, $post_id);

// Get related posts (same category or similar tags), including author's profile info
$related_query = "SELECT DISTINCT p.*, u.username, u.profile_image_path, u.gender, u.prefers_avatar  
                  FROM posts p 
                  LEFT JOIN users u ON p.user_id = u.id 
                  LEFT JOIN post_tags pt ON p.id = pt.post_id 
                  WHERE p.status = 'published' 
                  AND p.id != ? 
                  AND (p.category_id = ? OR pt.tag_id IN (
                      SELECT tag_id FROM post_tags WHERE post_id = ?
                  ))
                  ORDER BY p.published_at DESC 
                  LIMIT 3";
$related_stmt = $conn->prepare($related_query);
$related_stmt->bind_param("iii", $post_id, $post['category_id'], $post_id);
$related_stmt->execute();
$related_result = $related_stmt->get_result();

// Get approved comments, including user's profile image path and gender
$comments_query = "SELECT c.*, u.username, u.profile_image_path, u.gender 
                   FROM comments c 
                   LEFT JOIN users u ON c.user_id = u.id 
                   WHERE c.post_id = ? AND c.status = 'approved' AND c.parent_comment_id IS NULL 
                   ORDER BY c.created_at ASC";
$comments_stmt = $conn->prepare($comments_query);
$comments_stmt->bind_param("i", $post_id);
$comments_stmt->execute();
$comments_result = $comments_stmt->get_result();

// Handle comment submission
$comment_message = '';
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['submit_comment'])) {
    $comment_content = sanitize_input($_POST['comment_content']);
    $parent_comment_id = !empty($_POST['parent_comment_id']) ? (int)$_POST['parent_comment_id'] : null;
    
    if (empty($comment_content)) {
        $comment_message = "Comment content is required.";
    } else {
        if (is_logged_in()) {
            // Logged in user comment
            $comment_insert = "INSERT INTO comments (post_id, user_id, content, parent_comment_id) VALUES (?, ?, ?, ?)";
            $comment_stmt = $conn->prepare($comment_insert);
            $comment_stmt->bind_param("iisi", $post_id, $_SESSION['user_id'], $comment_content, $parent_comment_id);
        } else {
            // Guest comment
            $author_name = sanitize_input($_POST['author_name']);
            $author_email = sanitize_input($_POST['author_email']);
            
            if (empty($author_name) || empty($author_email)) {
                $comment_message = "Name and email are required for guest comments.";
            } elseif (!validate_email($author_email)) {
                $comment_message = "Please enter a valid email address.";
            } else {
                $comment_insert = "INSERT INTO comments (post_id, author_name, author_email, content, parent_comment_id) VALUES (?, ?, ?, ?, ?)";
                $comment_stmt = $conn->prepare($comment_insert);
                $comment_stmt->bind_param("isssi", $post_id, $author_name, $author_email, $comment_content, $parent_comment_id);
            }
        }
        
        if (empty($comment_message) && $comment_stmt->execute()) {
            $comment_message = "Comment submitted successfully! It will be visible after moderation.";
        } elseif (empty($comment_message)) {
            $comment_message = "Failed to submit comment. Please try again.";
        }
    }
}

// Function to get comment replies, including user's profile image path and gender
function get_comment_replies($conn, $parent_id) {
    $replies_query = "SELECT c.*, u.username, u.profile_image_path, u.gender 
                      FROM comments c 
                      LEFT JOIN users u ON c.user_id = u.id 
                      WHERE c.parent_comment_id = ? AND c.status = 'approved' 
                      ORDER BY c.created_at ASC";
    $replies_stmt = $conn->prepare($replies_query);
    $replies_stmt->bind_param("i", $parent_id);
    $replies_stmt->execute();
    return $replies_stmt->get_result();
}

// Helper function to determine if a path is a URL
if (!function_exists('is_url_optimized')) {
    function is_url_optimized($path) {
        return filter_var($path, FILTER_VALIDATE_URL);
    }
}

// Get current user info (assuming session is used)
$current_user_id = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : null;
$is_admin = isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'admin'; // Corrected session variable for role

// Helper functions for likes/dislikes are expected to be in functions.php
// No need to redefine them here if functions.php is properly included.

// Fetch current like and dislike stats
$likes_count = get_post_likes($conn, $post['id']);
$liked = user_liked_post($conn, $post['id'], $current_user_id);
$dislikes_count = get_post_dislikes($conn, $post['id'], $current_user_id);
$disliked = user_disliked_post($conn, $post['id'], $current_user_id);

function can_edit_post($post_user_id, $current_user_id, $is_admin) {
    // Admin can edit any post, others only their own
    return $is_admin || ($current_user_id && $current_user_id == $post_user_id);
}

?>

<div class="container post-page-layout">
    <div class="main-content-column">
        <!-- Post Header -->
        <div class="post-header">
            <h1 class="post-title-single"><?php echo htmlspecialchars($post['title']); ?></h1>
            <div class="post-meta-single">
                <span class="post-author-info">
                    <a href="view_user.php?id=<?php echo htmlspecialchars($post['user_id']); ?>" class="post-author-avatar">
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
                        <img src="<?php echo $author_profile_image_display; ?>" 
                             alt="<?php echo htmlspecialchars($post['username']); ?>'s Profile" 
                             class="avatar-small"
                             loading="lazy"
                             onerror="this.onerror=null;this.src='../avatars/default_avatar.png';">
                    </a>
                    <a href="view_user.php?id=<?php echo htmlspecialchars($post['user_id']); ?>"><?php echo htmlspecialchars($post['username']); ?></a>
                    <!-- Follower Count -->
                    <span class="follower-count-display">
                        (<a href="view_user.php?id=<?php echo $post['user_id']; ?>#profile-stats-bottom-row" class="text-primary"><?php echo get_follower_count($conn, $post['user_id']); ?> Followers</a>)
                    </span>
                </span>
                <span><i class="fas fa-calendar"></i> <?php echo format_datetime($post['published_at']); ?></span>
                <?php if ($post['category_name']): ?>
                    <span><i class="fas fa-folder"></i> <a href="category.php?id=<?php echo htmlspecialchars($post['category_id']); ?>"><?php echo htmlspecialchars($post['category_name']); ?></a></span>
                <?php endif; ?>
                <!-- Comment Count -->
                <a href="#comments-section" class="comment-count-link">
                    <i class="fas fa-comments"></i> <?php echo htmlspecialchars($post['comment_count']); ?>
                </a>
            </div>
            
            <?php if (!empty($tags)): ?>
                <div class="post-tags text-center">
                    <?php foreach ($tags as $tag): ?>
                        <a href="tag.php?id=<?php echo htmlspecialchars($tag['id']); ?>" class="tag"><?php echo htmlspecialchars($tag['name']); ?></a>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
        
        <!-- Post Content -->
        <article class="post-body">
            <?php 
            $main_image_src = '';
            if (!empty($post['image_path'])) {
                if (is_url_optimized($post['image_path'])) {
                    // For Unsplash URLs, optimize for a larger display on a single post page
                    $main_image_src = htmlspecialchars($post['image_path']) . '?q=80&w=900&h=600&fit=crop'; 
                } else {
                    // Use 'uploads/' directory for local images and fallback if not found
                    $image_src = 'uploads/' . htmlspecialchars(basename($post['image_path'])); // Assuming local paths are 'uploads/filename.jpg'
                    if (!file_exists($image_src)) {
                        $image_src = '../' . htmlspecialchars($post['image_path']);
                    }
                    $main_image_src = $image_src;
                }
            }
            ?>
            <?php if ($main_image_src): ?>
                <img src="<?php echo $main_image_src; ?>" 
                     alt="<?php echo htmlspecialchars($post['title']); ?>" 
                     class="post-featured-image"
                     loading="lazy"
                     onerror="this.onerror=null;this.src='https://placehold.co/900x600/cccccc/333333?text=Image+Not+Found';">
            <?php endif; ?>
            
            <div class="post-content">
                <?php echo $post['content']; ?>
            </div>

            <!-- Like/Dislike Buttons and Edit Button -->
            <div class="reaction-buttons mt-3 mb-4 text-center">
                <!-- Like Button -->
                <div class="post-likes" id="likes-<?php echo htmlspecialchars($post['id']); ?>">
                    <button 
                        class="like-btn<?php echo $liked ? ' liked' : ''; ?>" 
                        data-post-id="<?php echo htmlspecialchars($post['id']); ?>" 
                        <?php echo $current_user_id ? '' : 'disabled title="Login to like"'; ?>>
                        <i class="fas fa-thumbs-up"></i>
                        <span class="like-count"><?php echo htmlspecialchars($likes_count); ?></span>
                    </button>
                </div>

                <!-- Dislike Button - Only show if status is 'enabled' -->
                <?php if ($post['dislike_button_status'] === 'enabled'): ?>
                    <div class="post-dislikes" id="dislikes-<?php echo htmlspecialchars($post['id']); ?>">
                        <button 
                            class="dislike-btn<?php echo $disliked ? ' disliked' : ''; ?>" 
                            data-post-id="<?php echo htmlspecialchars($post['id']); ?>" 
                            <?php echo $current_user_id ? '' : 'disabled title="Login to dislike"'; ?>>
                            <i class="fas fa-thumbs-down"></i>
                            <span class="dislike-count"><?php echo htmlspecialchars($dislikes_count); ?></span>
                        </button>
                    </div>
                <?php endif; ?>

                <?php if (can_edit_post($post['user_id'], $current_user_id, $is_admin)): ?>
                    <a href="edit_post.php?id=<?php echo htmlspecialchars($post['id']); ?>" class="edit-post-btn" onclick="return confirm('Edit this post?');">Edit</a>
                <?php endif; ?>

                <!-- Social Share -->
                <div class="share-buttons">
                    <a href="https://twitter.com/intent/tweet?text=<?php echo urlencode($post['title']); ?>&url=<?php echo urlencode('http://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI']); ?>" 
                       target="_blank" class="btn btn-outline">
                        <i class="fab fa-twitter"></i> Twitter
                    </a>
                    <a href="https://www.facebook.com/sharer/sharer.php?u=<?php echo urlencode('http://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI']); ?>" 
                       target="_blank" class="btn btn-outline">
                        <i class="fab fa-facebook"></i> Facebook
                    </a>
                    <a href="https://www.linkedin.com/sharing/share-offsite/?url=<?php echo urlencode('http://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI']); ?>" 
                       target="_blank" class="btn btn-outline">
                        <i class="fab fa-linkedin"></i> LinkedIn
                    </a>
                </div>
            </div>
        </article>
    </div> <!-- End of main-content-column -->
    
    <div class="sidebar-column">
        <!-- Related Posts -->
        <?php if ($related_result->num_rows > 0): ?>
            <section class="related-posts mt-4 mb-4">
                <h3>Related Posts</h3>
                <div class="posts-grid-column"> <!-- Changed class to posts-grid-column -->
                    <?php while ($related_post = $related_result->fetch_assoc()): ?>
                        <article class="post-card">
                            <?php 
                            $related_image_src = '';
                            if (!empty($related_post['image_path'])) {
                                if (is_url_optimized($related_post['image_path'])) {
                                    // For Unsplash URLs, optimize for related posts thumbnails
                                    $related_image_src = htmlspecialchars($related_post['image_path']) . '?q=80&w=400&h=250&fit=crop'; 
                                } else {
                                    // Use 'uploads/' directory for local images and fallback if not found
                                    $related_image_basename = htmlspecialchars(basename($related_post['image_path']));
                                    $related_image_src = "uploads/{$related_image_basename}";
                                    if (!file_exists($related_image_src)) {
                                        $related_image_src = '../' . htmlspecialchars($related_post['image_path']);
                                    }
                                }
                            }
                            ?>
                            <?php if ($related_image_src): ?>
                                <div class="post-image">
                                    <img src="<?php echo $related_image_src; ?>" 
                                         alt="<?php echo htmlspecialchars($related_post['title']); ?>" 
                                         class="post-image"
                                         loading="lazy"
                                         onerror="this.onerror=null;this.src='https://placehold.co/400x250/cccccc/333333?text=Image+Not+Found';">
                                </div>
                            <?php else: ?>
                                <div class="post-image" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); display: flex; align-items: center; justify-content: center; color: white; font-size: 1.2rem;">
                                    <i class="fas fa-image"></i>
                                </div>
                            <?php endif; ?>
                            
                            <div class="post-content">
                                <h4 class="post-title">
                                    <a href="post.php?id=<?php echo htmlspecialchars($related_post['id']); ?>"><?php echo htmlspecialchars($related_post['title']); ?></a>
                                </h4>
                                <p class="post-excerpt"><?php echo truncate_text(strip_tags($related_post['content']), 100); ?></p>
                                <div class="post-meta">
                                    <span class="post-author-info">
                                        <a href="view_user.php?id=<?php echo htmlspecialchars($related_post['user_id']); ?>" class="post-author-avatar">
                                            <?php
                                            $author_profile_image = !empty($related_post['profile_image_path']) ? htmlspecialchars($related_post['profile_image_path']) : '';
                                            $author_prefers_avatar = isset($related_post['prefers_avatar']) ? (bool)$related_post['prefers_avatar'] : false;

                                            $author_profile_image_display = '';

                                            if ($author_prefers_avatar) {
                                                if (!empty($author_profile_image) && strpos($author_profile_image, 'avatars/') === 0 && file_exists($author_profile_image)) {
                                                    $author_profile_image_display = $author_profile_image;
                                                } else {
                                                    if (isset($related_post['gender']) && $related_post['gender'] == 'male') {
                                                        $author_profile_image_display = 'avatars/male_avatar.png';
                                                    } elseif (isset($related_post['gender']) && $related_post['gender'] == 'female') {
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
                                                if (isset($related_post['gender']) && $related_post['gender'] == 'male') {
                                                    $author_profile_image_display = 'avatars/male_avatar.png';
                                                } elseif (isset($related_post['gender']) && $related_post['gender'] == 'female') {
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
                                            <img src="<?php echo $author_profile_image_display; ?>" 
                                                 alt="<?php echo htmlspecialchars($related_post['username']); ?>'s Profile" 
                                                 class="avatar-small"
                                                 loading="lazy"
                                                 onerror="this.onerror=null;this.src='../avatars/default_avatar.png';">
                                        </a>
                                        <a href="view_user.php?id=<?php echo htmlspecialchars($related_post['user_id']); ?>"><?php echo htmlspecialchars($related_post['username']); ?></a>
                                    </span>
                                    <span><i class="fas fa-calendar"></i> <?php echo format_date($related_post['published_at']); ?></span>
                                </div>
                            </div>
                        </article>
                    <?php endwhile; ?>
                </div>
            </section>
        <?php endif; ?>
        
        <!-- Comments Section -->
        <section class="comments-section" id="comments-section">
            <h3 class="comments-title">Comments</h3>
            
            <!-- Comment Form -->
            <div class="comment-form-container mb-4">
                <?php if ($comment_message): ?>
                    <div class="alert <?php echo strpos($comment_message, 'successfully') !== false ? 'alert-success' : 'alert-error'; ?>">
                        <?php echo htmlspecialchars($comment_message); ?>
                    </div>
                <?php endif; ?>
                
                <form method="POST" class="comment-form" data-validate>
                    <input type="hidden" name="post_id" value="<?php echo (int)($_GET['id'] ?? 0); ?>">
                    <input type="hidden" name="parent_comment_id" value="">
                    
                    <?php if (!is_logged_in()): ?>
                        <div class="form-group">
                            <label for="author_name" class="form-label">Name</label>
                            <input type="text" id="author_name" name="author_name" class="form-input" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="author_email" class="form-label">Email</label>
                            <input type="email" id="author_email" name="author_email" class="form-input" required>
                            <small class="text-muted">Your email will not be published</small>
                        </div>
                    <?php endif; ?>
                    
                    <div class="form-group">
                        <label for="comment_content" class="form-label">Comment</label>
                        <textarea  id="comment_content" name="comment_content" class="form-textarea" required placeholder="Share your thoughts..."></textarea>
                    </div>
                    
                    <button type="submit" name="submit_comment" class="btn btn-primary">Post Comment</button>
                    <button type="button" class="btn btn-secondary cancel-reply" style="display: none;">Cancel Reply</button>
                </form>
            </div>
            
            <!-- Comments List -->
            <div class="comments-list">
                <?php if ($comments_result->num_rows > 0): ?>
                    <?php while ($comment = $comments_result->fetch_assoc()): ?>
                        <div class="comment" id="comment-<?php echo htmlspecialchars($comment['id']); ?>">
                            <div class="comment-header">
                                <!-- Commenter Avatar and Name for main comments -->
                                <span class="post-author-info">
                                    <a href="view_user.php?id=<?php echo htmlspecialchars($comment['user_id']); ?>" class="post-author-avatar">
                                        <?php
                                        $commenter_profile_image = !empty($comment['profile_image_path']) ? htmlspecialchars($comment['profile_image_path']) : '';
                                        $commenter_prefers_avatar = isset($comment['prefers_avatar']) ? (bool)$comment['prefers_avatar'] : false;

                                        $commenter_profile_image_display = '';

                                        if ($commenter_prefers_avatar) {
                                            if (!empty($commenter_profile_image) && strpos($commenter_profile_image, 'avatars/') === 0 && file_exists($commenter_profile_image)) {
                                                $commenter_profile_image_display = $commenter_profile_image;
                                            } else {
                                                if (isset($comment['gender']) && $comment['gender'] == 'male') {
                                                    $commenter_profile_image_display = 'avatars/male_avatar.png';
                                                } elseif (isset($comment['gender']) && $comment['gender'] == 'female') {
                                                    $commenter_profile_image_display = 'avatars/female_avatar.png';
                                                } else {
                                                    $commenter_profile_image_display = 'avatars/default_avatar.png';
                                                }
                                            }
                                        } elseif (!empty($commenter_profile_image)) {
                                            if (strpos($commenter_profile_image, 'profiles/') === 0) {
                                                $commenter_profile_image_display = $commenter_profile_image;
                                            } else {
                                                $commenter_profile_image_display = 'uploads/' . basename($commenter_profile_image);
                                                if (!file_exists($commenter_profile_image_display)) {
                                                    $commenter_profile_image_display = $commenter_profile_image;
                                                }
                                            }
                                        } else {
                                            if (isset($comment['gender']) && $comment['gender'] == 'male') {
                                                $commenter_profile_image_display = 'avatars/male_avatar.png';
                                            } elseif (isset($comment['gender']) && $comment['gender'] == 'female') {
                                                $commenter_profile_image_display = 'avatars/female_avatar.png';
                                            } else {
                                                $commenter_profile_image_display = 'avatars/default_avatar.png';
                                            }
                                        }

                                        if (!filter_var($commenter_profile_image_display, FILTER_VALIDATE_URL) && !file_exists($commenter_profile_image_display)) {
                                            if (file_exists('../' . $commenter_profile_image_display)) {
                                                $commenter_profile_image_display = '../' . $commenter_profile_image_display;
                                            } else {
                                                $commenter_profile_image_display = 'avatars/default_avatar.png';
                                            }
                                        }
                                        ?>
                                        <img src="<?php echo $commenter_profile_image_display; ?>" 
                                             alt="<?php echo htmlspecialchars($comment['username'] ?? $comment['author_name']); ?>'s Profile" 
                                             class="avatar-small" 
                                             loading="lazy" 
                                             onerror="this.onerror=null;this.src='avatars/default_avatar.png';">
                                    </a>
                                    <a href="view_user.php?id=<?php echo htmlspecialchars($comment['user_id']); ?>"><?php echo htmlspecialchars($comment['username'] ?? $comment['author_name']); ?></a>
                                </span>
                                
                                <span class="comment-date"><?php echo format_datetime($comment['created_at']); ?></span>
                            </div>
                            <div class="comment-content">
                                <?php echo nl2br(htmlspecialchars($comment['content'])); ?>
                            </div>
                            <div class="comment-actions">
                                <button type="button" class="reply-btn" data-comment-id="<?php echo htmlspecialchars($comment['id']); ?>">
                                    <i class="fas fa-reply"></i> Reply
                                </button>
                            </div>
                            
                            <!-- Comment Replies -->
                            <?php
                            $replies_result = get_comment_replies($conn, $comment['id']);
                            if ($replies_result->num_rows > 0):
                            ?>
                                <div class="comment-replies">
                                    <?php while ($reply = $replies_result->fetch_assoc()): ?>
                                        <div class="comment comment-reply">
                                            <div class="comment-header">
                                                <!-- Commenter Avatar and Name for replies -->
                                                <span class="post-author-info">
                                                    <a href="view_user.php?id=<?php echo htmlspecialchars($reply['user_id']); ?>" class="post-author-avatar">
                                                        <?php
                                                        $reply_commenter_profile_image = !empty($reply['profile_image_path']) ? htmlspecialchars($reply['profile_image_path']) : '';
                                                        $reply_commenter_prefers_avatar = isset($reply['prefers_avatar']) ? (bool)$reply['prefers_avatar'] : false;

                                                        $reply_commenter_profile_image_display = '';

                                                        if ($reply_commenter_prefers_avatar) {
                                                            if (!empty($reply_commenter_profile_image) && strpos($reply_commenter_profile_image, 'avatars/') === 0 && file_exists($reply_commenter_profile_image)) {
                                                                $reply_commenter_profile_image_display = $reply_commenter_profile_image;
                                                            } else {
                                                                if (isset($reply['gender']) && $reply['gender'] == 'male') {
                                                                    $reply_commenter_profile_image_display = 'avatars/male_avatar.png';
                                                                } elseif (isset($reply['gender']) && $reply['gender'] == 'female') {
                                                                    $reply_commenter_profile_image_display = 'avatars/female_avatar.png';
                                                                } else {
                                                                    $reply_commenter_profile_image_display = 'avatars/default_avatar.png';
                                                                }
                                                            }
                                                        } elseif (!empty($reply_commenter_profile_image)) {
                                                            if (strpos($reply_commenter_profile_image, 'profiles/') === 0) {
                                                                $reply_commenter_profile_image_display = $reply_commenter_profile_image;
                                                            } else {
                                                                $reply_commenter_profile_image_display = 'uploads/' . basename($reply_commenter_profile_image);
                                                                if (!file_exists($reply_commenter_profile_image_display)) {
                                                                    $reply_commenter_profile_image_display = $reply_commenter_profile_image;
                                                                }
                                                            }
                                                        } else {
                                                            if (isset($reply['gender']) && $reply['gender'] == 'male') {
                                                                $reply_commenter_profile_image_display = 'avatars/male_avatar.png';
                                                            } elseif (isset($reply['gender']) && $reply['gender'] == 'female') {
                                                                $reply_commenter_profile_image_display = 'avatars/female_avatar.png';
                                                            } else {
                                                                $reply_commenter_profile_image_display = 'avatars/default_avatar.png';
                                                            }
                                                        }

                                                        if (!filter_var($reply_commenter_profile_image_display, FILTER_VALIDATE_URL) && !file_exists($reply_commenter_profile_image_display)) {
                                                            if (file_exists('../' . $reply_commenter_profile_image_display)) {
                                                                $reply_commenter_profile_image_display = '../' . $reply_commenter_profile_image_display;
                                                            } else {
                                                                $reply_commenter_profile_image_display = 'avatars/default_avatar.png';
                                                            }
                                                        }
                                                        ?>
                                                        <img src="<?php echo $reply_commenter_profile_image_display; ?>" 
                                                             alt="<?php echo htmlspecialchars($reply['username'] ?? $reply['author_name']); ?>'s Profile" 
                                                             class="avatar-small" 
                                                             loading="lazy" 
                                                             onerror="this.onerror=null;this.src='../avatars/default_avatar.png';">
                                                    </a>
                                                    <a href="view_user.php?id=<?php echo htmlspecialchars($reply['user_id']); ?>"><?php echo htmlspecialchars($reply['username'] ?? $reply['author_name']); ?></a>
                                                </span>
                                                <span class="comment-date"><?php echo format_datetime($reply['created_at']); ?></span>
                                            </div>
                                            <div class="comment-content">
                                                <?php echo nl2br(htmlspecialchars($reply['content'])); ?>
                                            </div>
                                        </div>
                                    <?php endwhile; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endwhile; ?>
                <?php else: ?>
                    <div class="text-center">
                        <p>No comments yet. Be the first to comment!</p>
                    </div>
                <?php endif; ?>
            </div>
        </section>
    </div> <!-- End of sidebar-column -->
</div> <!-- End of container -->

<style>
/* Responsive Two-Column Layout */
.post-content, .post-header {
    padding: 2px; /* Space between post header and content */
}
.post-page-layout {
    display: flex;
    flex-direction: column; /* Default to single column on small screens */
    gap: 2rem; /* Space between columns */
}

@media (min-width: 768px) { /* Apply two-column layout for tablets and desktops */
    .post-page-layout {
        flex-direction: row;
        align-items: flex-start; /* Align items to the top */
    }

    .main-content-column {
        flex: 2; /* Main content takes 2/3 of the space */
        min-width: 0; /* Allow content to shrink */
    }

    .sidebar-column {
        flex: 1; /* Sidebar takes 1/3 of the space */
        min-width: 0; /* Allow content to shrink */
        margin-top:15%;
    }
}

/* Existing Styles (ensure they are still relevant and not overridden by new styles) */
.post-featured-image {
    width: 100%;
    max-height: 400px;
    object-fit: cover;
    border-radius: 8px;
    margin-bottom: 2rem;
}

/* Responsive images and general videos within post content */
/* These CSS rules serve as a fallback but the JS will apply inline styles for max specificity */
.post-content img,
.post-content video {
    max-width: 100%;
    height: auto; 
    display: block; 
    margin: 0 auto; 
}

/* Responsive video embeds (e.g., YouTube, Vimeo) */
.video-responsive {
    overflow: hidden;
    /* 16:9 aspect ratio (height / width = 9 / 16 = 0.5625) */
    /* For 4:3 aspect ratio, use padding-bottom: 75%; */
    padding-bottom: 56.25%; 
    position: relative;
    height: 0;
    margin-bottom: 1rem; /* Add some space below the video */
}

/* These CSS rules serve as a fallback but the JS will apply inline styles for max specificity */
.video-responsive iframe {
    left: 0;
    top: 0;
    height: 100%; 
    width: 100%; 
    position: absolute;
    border: none; /* Remove default iframe border */
}


.social-share {
    border-top: 1px solid #e9ecef;
    border-bottom: 1px solid #e9ecef;
    padding: 2rem 0;
}

.share-buttons {
    display: flex;
    flex-wrap: wrap;
    justify-content: flex-end;
    gap: 1rem;
    margin-top: 1rem;
}
@media (max-width: 600px) {
    .share-buttons {
        justify-content: center;
        gap: 0.5rem;
    }
}

.share-buttons .btn {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    font-size: 1rem;
    padding: 0.4rem 1rem;
    border-radius: 6px;
    border: 1px solid #e0e0e0;
    background: linear-gradient(90deg, #667eea 0%, #764ba2 100%);
    color: #fff;
    transition: background 0.2s, color 0.2s;
}
.share-buttons .btn:hover {
    background:#007bff;
    color:rgb(255, 255, 255);
}

.comment-form-container {
    background: linear-gradient(135deg,#667eea 0%, #764ba2 100%);
    padding: 2rem;
    border-radius: 12px;
    box-shadow: 0 4px 15px rgba(0,0,0,0.1);
    max-width: 700px; /* This might need adjustment if it's too wide in the sidebar */
    margin: 0 auto 2rem auto; /* Center on small screens, remove auto margin in sidebar */
}
@media (min-width: 768px) {
    .comment-form-container {
        max-width: none; /* Remove max-width restriction in sidebar */
        margin: 0 0 2rem 0; /* Align to left in sidebar */
    }
}
@media (max-width: 600px) {
    .comment-form-container {
        padding: 1rem;
        border-radius: 8px;
    }
}

.comment-replies {
    margin-left: 2rem;
    border-left: 3px solid #e9ecef;
    padding-left: 1rem;
    margin-top: 1rem;
}
@media (max-width: 600px) {
    .comment-replies {
        margin-left: 0.7rem;
        padding-left: 0.5rem;
    }
}

.comment-actions {
    margin-top: 0.5rem;
}

.reply-btn {
    background: none;
    border: none;
    color: #3498db;
    cursor: pointer;
    font-size: 0.95rem;
    display: flex;
    align-items: center;
    gap: 0.25rem;
    padding: 0.2rem 0.5rem;
    border-radius: 4px;
    transition: background 0.15s;
}
.reply-btn:hover {
    text-decoration: underline;
    background: #eaf6ff;
}

.cancel-reply {
    margin-left: 0.5rem;
    background: #f5f5f5;
    color: #444;
    border: 1px solid #ddd;
    border-radius: 4px;
    padding: 0.2rem 0.7rem;
    font-size: 0.95rem;
    transition: background 0.15s;
}
.cancel-reply:hover {
    background: #e2e2e2;
}

/* Reaction Buttons Responsive & Improved */
.reaction-buttons {
    display: flex;
    flex-wrap: wrap;
    justify-content: center;
    align-items: center;
    margin-top: 1rem;
    padding-top: 1rem;
    border-top: 1px solid var(--border-color, #eee);
    gap: 0.7rem;
}
@media (max-width: 600px) {
    .reaction-buttons {
        flex-direction: column;
        gap: 0.5rem;
        padding-top: 0.5rem;
    }
}
.reaction-buttons .post-likes,
.reaction-buttons .post-dislikes {
    display: flex;
    align-items: center;
}

.reaction-buttons button.like-btn,
.reaction-buttons button.dislike-btn {
    background-color: var(--background-light, #f9f9f9);
    border: 1px solid var(--border-color, #ddd);
    border-radius: 8px;
    padding: 0.5rem 1.2rem;
    font-size: 1.05rem;
    cursor: pointer;
    display: flex;
    align-items: center;
    gap: 0.4rem;
    transition: all 0.2s ease;
    color: var(--text-color, #222);
    outline: none;
    min-width: 90px;
    justify-content: center;
    font-weight: 500;
    box-shadow: 0 1px 4px rgba(0,0,0,0.04);
}
.reaction-buttons button.like-btn:hover,
.reaction-buttons button.dislike-btn:hover {
    border-color: var(--primary-color, #007bff);
    box-shadow: 0 2px 8px rgba(0,0,0,0.09);
    background: #f0f8ff;
}
.reaction-buttons button.like-btn.liked,
.reaction-buttons button.like-btn.active {
    background-color: #28a745;
    color: #fff;
    border-color: #28a745;
}
.reaction-buttons button.dislike-btn.disliked,
.reaction-buttons button.dislike-btn.active {
    background-color: #dc3545;
    color: #fff;
    border-color: #dc3545;
}
.reaction-buttons button.like-btn.liked:hover,
.reaction-buttons button.like-btn.active:hover {
    background-color: #218838;
    border-color: #218838;
}
.reaction-buttons button.dislike-btn.disliked:hover,
.reaction-buttons button.dislike-btn.active:hover {
    background-color: #c82333;
    border-color: #c82333;
}
.reaction-buttons button[disabled] {
    opacity: 0.6;
    cursor: not-allowed;
    background: #f5f5f5;
    color: #aaa;
    border-color: #eee;
}
/* New style for columnar related posts */
.related-posts .posts-grid-column {
    display: flex;
    flex-direction: column;
    gap: 1.5rem; /* Adjust spacing between related posts */

}

/* Ensure post-card within related posts still looks good */
.related-posts .post-card {
    display: flex;
    flex-direction: column; /* Stack image and content vertically */
    border-radius: 8px;
    overflow: hidden;
    box-shadow: 0 2px 8px rgba(0,0,0,0.05);
    transition: transform 0.2s ease;
}

.related-posts .post-card:hover {
    transform: translateY(-5px);
}

.related-posts .post-card .post-image img {
    width: 100%;
    height: 180px; /* Fixed height for consistency */
    object-fit: cover;
}

.related-posts .post-card .post-content {
    padding: 1rem;
}

.related-posts .post-card .post-title {
    font-size: 1.1rem;
    margin-top: 0;
    margin-bottom: 0.5rem;
}

.related-posts .post-card .post-title a {
    text-decoration: none;
    transition: color 0.2s;
}

.related-posts .post-card .post-title a:hover {
    color: #007bff;
}

.related-posts .post-card .post-excerpt {
    font-size: 0.9rem;
    margin-bottom: 1rem;
}

.related-posts .post-card .post-meta {
    font-size: 0.8rem;
    display: flex;
    justify-content: space-between;
}
/* Styles for author info and follower count */
.post-author-info {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    flex-wrap: wrap; /* Allow wrapping for author info on small screens */
}

.follower-count-display {
    font-size: 0.85em;
    color: var(--secondary-color);
    white-space: nowrap; /* Prevent breaking of "X Followers" */
}

.follower-count-display a {
    color: var(--primary-color);
    text-decoration: none;
}

.follower-count-display a:hover {
    text-decoration: underline;
}

@media (min-width: 992px){
    .container{
        padding: 0 1rem; /* Add padding to the container for larger screens */
    }
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const replyButtons = document.querySelectorAll('.reply-btn');
    const commentForm = document.querySelector('.comment-form');
    const parentCommentInput = commentForm.querySelector('input[name="parent_comment_id"]');
    const cancelReplyBtn = commentForm.querySelector('.cancel-reply');
    const originalFormContainer = document.querySelector('.comment-form-container');
    
    replyButtons.forEach(btn => {
        btn.addEventListener('click', function() {
            const commentId = this.dataset.commentId;
            const commentElement = document.getElementById(`comment-${commentId}`);
            
            // Move form to reply position
            commentElement.appendChild(originalFormContainer);
            parentCommentInput.value = commentId;
            cancelReplyBtn.style.display = 'inline-block';
            
            // Focus on comment textarea
            commentForm.querySelector('#comment_content').focus();
        });
    });
    
    cancelReplyBtn.addEventListener('click', function() {
        // Move form back to original position
        document.querySelector('.comments-section').insertBefore(originalFormContainer, document.querySelector('.comments-list'));
        parentCommentInput.value = '';
        this.style.display = 'none';
    });

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

    // Function to make images, videos, and iframes responsive
    function applyResponsiveStyles(element) {
        if (!element || !element.tagName) return; // Basic check for valid element

        const tagName = element.tagName.toUpperCase();

        // Always remove inline width and height attributes first
        // This is crucial to prevent them from overriding our style properties
        element.removeAttribute('width');
        element.removeAttribute('height');

        if (tagName === 'IMG') {
            // Apply inline styles for images
            element.style.width = '100%';
            element.style.height = 'auto'; // Maintain aspect ratio
            element.style.maxWidth = '100%'; // Ensure it doesn't exceed its container
            element.setAttribute('loading', 'lazy'); // Add lazy loading
            console.log(`Applied styles and lazy loading to IMG: width=${element.style.width}, height=${element.style.height}, maxWidth=${element.style.maxWidth}`);
        } else if (tagName === 'VIDEO') {
            // Apply inline styles for general videos
            element.style.width = '100%';
            element.style.height = 'auto'; // Maintain aspect ratio
            element.style.maxWidth = '100%'; // Ensure it doesn't exceed its container
            element.setAttribute('preload', 'none'); // Optimize video loading
            element.setAttribute('controls', ''); // Ensure controls are visible
            console.log(`Applied styles and preload to VIDEO: width=${element.style.width}, height=${element.style.height}, maxWidth=${element.style.maxWidth}`);
        } else if (tagName === 'IFRAME') {
            // For all iframes, ensure they are wrapped in the responsive container
            // This is the most reliable way to handle external video embeds
            if (!element.closest('.video-responsive')) {
                const wrapper = document.createElement('div');
                wrapper.classList.add('video-responsive');
                // Insert the wrapper before the iframe
                element.parentNode.insertBefore(wrapper, element);
                // Move the iframe inside the wrapper
                wrapper.appendChild(element);
                console.log(`Wrapped iframe: ${element.getAttribute('src') || 'No SRC'}`);
            }
            // Ensure the iframe inside the responsive wrapper takes full dimensions of the wrapper
            element.style.width = '100%';
            element.style.height = '100%'; // Height is controlled by the wrapper's padding-bottom
            element.style.maxWidth = '100%'; // Ensure it doesn't exceed its container
            element.setAttribute('loading', 'lazy'); // Lazy load iframes if possible (browser support varies)
            console.log(`Applied styles and lazy loading to IFRAME: width=${element.style.width}, height=${element.style.height}, maxWidth=${element.style.maxWidth}`);
        }
    }

    // Process existing media elements on initial load
    document.querySelectorAll('.post-content img, .post-content video, .post-content iframe').forEach(applyResponsiveStyles);

    // Set up a MutationObserver to watch for dynamically added/modified content
    const postContent = document.querySelector('.post-content');
    if (postContent) {
        const observer = new MutationObserver(mutations => {
            mutations.forEach(mutation => {
                // Handle added nodes (e.g., new elements inserted by TinyMCE)
                if (mutation.type === 'childList' && mutation.addedNodes.length > 0) {
                    mutation.addedNodes.forEach(node => {
                        if (node.nodeType === 1) { // Element node
                            // Check the added node itself
                            if (node.matches('img, video, iframe')) {
                                applyResponsiveStyles(node);
                            }
                            // Check descendants of the added node
                            node.querySelectorAll('img, video, iframe').forEach(applyResponsiveStyles);
                        }
                    });
                }
                // Handle attribute changes (e.g., TinyMCE modifying width/height attributes or inline style)
                if (mutation.type === 'attributes' && (mutation.attributeName === 'width' || mutation.attributeName === 'height' || mutation.attributeName === 'style')) {
                    applyResponsiveStyles(mutation.target);
                }
            });
        });

        // Start observing the .post-content div for child additions, subtree changes, and attribute changes on relevant elements
        observer.observe(postContent, {
            childList: true, // Observe direct children additions/removals
            subtree: true,   // Observe changes in the entire subtree (nested elements)
            attributes: true, // Observe attribute changes on elements
            attributeFilter: ['width', 'height', 'style'] // Only observe these specific attributes
        });
    }
});
</script>

<?php include 'includes/footer.php'; ?>
