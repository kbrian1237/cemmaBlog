// DOM Content Loaded
document.addEventListener('DOMContentLoaded', function() {
    // Mobile Navigation Toggle (now applies to all screens)
    const hamburger = document.querySelector('.hamburger'); // <-- FIXED: use class selector
    const sideNav = document.getElementById('sideNav');
    const overlay = document.getElementById('overlay');
    
    // Check if all elements for the side navigation exist
    if (hamburger && sideNav && overlay) {
        // Event listener for the hamburger icon click
        hamburger.addEventListener('click', function() {
            hamburger.classList.toggle('active');
            sideNav.classList.toggle('active');
            overlay.classList.toggle('active');
        });
        
        // Event listener for the overlay click to close the side navigation
        overlay.addEventListener('click', function() {
            hamburger.classList.remove('active');
            sideNav.classList.remove('active');
            overlay.classList.remove('active');
        });
    }

    // Dropdown functionality for side navigation categories
    const dropdownToggle = document.querySelector('.side-nav-item.dropdown .dropdown-toggle');
    if (dropdownToggle) {
        dropdownToggle.addEventListener('click', function(e) {
            e.preventDefault();
            const dropdownMenu = this.nextElementSibling;
            if (dropdownMenu && dropdownMenu.classList.contains('side-nav-dropdown-menu')) {
                dropdownMenu.classList.toggle('active');
                this.querySelector('i').classList.toggle('fa-chevron-down');
                this.querySelector('i').classList.toggle('fa-chevron-up');
            }
        });
    }


    // Initialize other functionalities from the original script
    initFormValidation();
    initImagePreview();
    initTagInput();
    initCommentReply();
    initAjaxComments();
    initLoadMore();
    initToastNotifications();
    initThemeSwitcher(); // This will now handle the single theme switcher in side nav

    // Initialize new community features (assuming these functions are defined below)
    initCommunityFeed();
    initGroupActions();
    initPrivateMessaging();
    initGameLobby();
});
// Form Validation
function initFormValidation() {
    const forms = document.querySelectorAll('form[data-validate]');
    
    forms.forEach(form => {
        form.addEventListener('submit', function(e) {
            if (!validateForm(this)) {
                e.preventDefault();
            }
        });
        
        // Real-time validation
        const inputs = form.querySelectorAll('input, textarea');
        inputs.forEach(input => {
            input.addEventListener('blur', function() {
                validateField(this);
            });
        });
    });
}

function validateForm(form) {
    let isValid = true;
    const inputs = form.querySelectorAll('input[required], textarea[required]');
    
    inputs.forEach(input => {
        if (!validateField(input)) {
            isValid = false;
        }
    });
    
    // Password confirmation
    const password = form.querySelector('input[name="password"]');
    const confirmPassword = form.querySelector('input[name="confirm_password"]');
    
    if (password && confirmPassword) {
        if (password.value !== confirmPassword.value) {
            showFieldError(confirmPassword, 'Passwords do not match');
            isValid = false;
        }
    }
    
    return isValid;
}

function validateField(field) {
    const value = field.value.trim();
    const type = field.type;
    const name = field.name;
    
    // Clear previous errors
    clearFieldError(field);
    
    // Required field validation
    if (field.hasAttribute('required') && !value) {
        showFieldError(field, 'This field is required');
        return false;
    }
    
    // Email validation
    if (type === 'email' && value) {
        const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        if (!emailRegex.test(value)) {
            showFieldError(field, 'Please enter a valid email address');
            return false;
        }
    }
    
    // Password validation
    if (name === 'password' && value) {
        if (value.length < 8) {
            showFieldError(field, 'Password must be at least 8 characters long');
            return false;
        }
        
        const passwordRegex = /^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)[a-zA-Z\d@$!%*?&]{8,}$/;
        if (!passwordRegex.test(value)) {
            showFieldError(field, 'Password must contain at least one uppercase letter, one lowercase letter, and one number');
            return false;
        }
    }
    
    // Username validation
    if (name === 'username' && value) {
        if (value.length < 3) {
            showFieldError(field, 'Username must be at least 3 characters long');
            return false;
        }
        
        const usernameRegex = /^[a-zA-Z0-9_]+$/;
        if (!usernameRegex.test(value)) {
            showFieldError(field, 'Username can only contain letters, numbers, and underscores');
            return false;
        }
    }
    
    return true;
}

function showFieldError(field, message) {
    clearFieldError(field);
    
    field.classList.add('error');
    const errorDiv = document.createElement('div');
    errorDiv.className = 'field-error';
    errorDiv.textContent = message;
    
    field.parentNode.appendChild(errorDiv);
}

function clearFieldError(field) {
    field.classList.remove('error');
    const existingError = field.parentNode.querySelector('.field-error');
    if (existingError) {
        existingError.remove();
    }
}

// Image Preview
function initImagePreview() {
    const imageInputs = document.querySelectorAll('input[type="file"][accept*="image"]');
    
    imageInputs.forEach(input => {
        input.addEventListener('change', function() {
            const file = this.files[0];
            if (file) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    let preview = input.parentNode.querySelector('.image-preview');
                    if (!preview) {
                        preview = document.createElement('div');
                        preview.className = 'image-preview';
                        input.parentNode.appendChild(preview);
                    }
                    
                    preview.innerHTML = `
                        <img src="${e.target.result}" alt="Preview" style="max-width: 200px; max-height: 200px; border-radius: 8px; margin-top: 1rem;">
                        <button type="button" class="remove-image" style="display: block; margin-top: 0.5rem; color: #e74c3c; background: none; border: none; cursor: pointer;">Remove</button>
                    `;
                    
                    preview.querySelector('.remove-image').addEventListener('click', function() {
                        input.value = '';
                        preview.remove();
                    });
                };
                reader.readAsDataURL(file);
            }
        });
    });
}

// Dynamic Tag Input
function initTagInput() {
    const tagInputs = document.querySelectorAll('.tag-input');
    
    tagInputs.forEach(input => {
        const container = document.createElement('div');
        container.className = 'tag-container';
        
        const tagsDisplay = document.createElement('div');
        tagsDisplay.className = 'tags-display';
        
        const hiddenInput = document.createElement('input');
        hiddenInput.type = 'hidden';
        hiddenInput.name = input.name;
        
        input.parentNode.insertBefore(container, input);
        container.appendChild(tagsDisplay);
        container.appendChild(input);
        container.appendChild(hiddenInput);
        
        input.removeAttribute('name');
        input.placeholder = 'Type a tag and press Enter';
        
        let tags = [];
        
        input.addEventListener('keydown', function(e) {
            if (e.key === 'Enter' || e.key === ',') {
                e.preventDefault();
                addTag(this.value.trim());
                this.value = '';
            }
        });
        
        function addTag(tagText) {
            if (tagText && !tags.includes(tagText)) {
                tags.push(tagText);
                updateTagsDisplay();
                updateHiddenInput();
            }
        }
        
        function removeTag(tagText) {
            tags = tags.filter(tag => tag !== tagText);
            updateTagsDisplay();
            updateHiddenInput();
        }
        
        function updateTagsDisplay() {
            tagsDisplay.innerHTML = tags.map(tag => `
                <span class="tag-item">
                    ${tag}
                    <button type="button" class="tag-remove" data-tag="${tag}">×</button>
                </span>
            `).join('');
            
            tagsDisplay.querySelectorAll('.tag-remove').forEach(btn => {
                btn.addEventListener('click', function() {
                    removeTag(this.dataset.tag);
                });
            });
        }
        
        function updateHiddenInput() {
            hiddenInput.value = tags.join(',');
        }
    });
}

// Comment Reply Toggle
function initCommentReply() {
    const replyButtons = document.querySelectorAll('.reply-btn');
    
    replyButtons.forEach(btn => {
        btn.addEventListener('click', function() {
            const commentId = this.dataset.commentId;
            const replyForm = document.querySelector(`#reply-form-${commentId}`);
            
            if (replyForm) {
                replyForm.style.display = replyForm.style.display === 'none' ? 'block' : 'none';
            }
        });
    });
}

const sideNavDropdown = document.querySelector('.side-nav .dropdown-toggle');

if (sideNavDropdown) {
    sideNavDropdown.addEventListener('click', function(e) {
        e.preventDefault();
        this.parentElement.classList.toggle('active');
        const dropdownMenu = this.nextElementSibling;
        if (dropdownMenu) {
            dropdownMenu.style.display = dropdownMenu.style.display === 'block' ? 'none' : 'block';
        }
    });
}

// AJAX Comment Submission
function initAjaxComments() {
    const commentForms = document.querySelectorAll('.comment-form');
    
    commentForms.forEach(form => {
        form.addEventListener('submit', function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            const submitBtn = this.querySelector('button[type="submit"]');
            const originalText = submitBtn.textContent;
            
            submitBtn.textContent = 'Submitting...';
            submitBtn.disabled = true;
            
            fetch('submit_comment.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showToast('Comment submitted successfully!', 'success');
                    this.reset();
                    window.location.reload(); // force reload
                    // Optionally reload comments or add the new comment to the DOM
                    if (data.comment_html) {
                        const commentsContainer = document.querySelector('.comments-list');
                        if (commentsContainer) {
                            commentsContainer.insertAdjacentHTML('beforeend', data.comment_html);
                        }
                    }
                
                    window.location.reload(); // force reload

                } else {
                    showToast(data.message || 'Error submitting comment', 'error');
                }
            })
            .catch(error => {
                showToast('Error submitting comment', 'error');
                console.error('Error:', error);
            })
            .finally(() => {
                submitBtn.textContent = originalText;
                submitBtn.disabled = false;
            });
        });
    });
}

// Load More Posts
function initLoadMore() {
    const loadMoreBtn = document.querySelector('.load-more-btn');
    
    if (loadMoreBtn) {
        loadMoreBtn.addEventListener('click', function() {
            const page = parseInt(this.dataset.page) || 1;
            const nextPage = page + 1;
            
            this.textContent = 'Loading...';
            this.disabled = true;
            
            fetch(`load_more_posts.php?page=${nextPage}`)
            .then(response => response.json())
            .then(data => {
                if (data.success && data.posts_html) {
                    const postsContainer = document.querySelector('.posts-grid');
                    if (postsContainer) {
                        postsContainer.insertAdjacentHTML('beforeend', data.posts_html);
                    }
                    
                    this.dataset.page = nextPage;
                    
                    if (!data.has_more) {
                        this.style.display = 'none';
                    }
                } else {
                    showToast('No more posts to load', 'info');
                    this.style.display = 'none';
                }
            })
            .catch(error => {
                showToast('Error loading posts', 'error');
                console.error('Error:', error);
            })
            .finally(() => {
                this.textContent = 'Load More';
                this.disabled = false;
            });
        });
    }
}

// Toast Notifications
function initToastNotifications() {
    // Create toast container if it doesn't exist
    if (!document.querySelector('.toast-container')) {
        const container = document.createElement('div');
        container.className = 'toast-container';
        document.body.appendChild(container);
    }
}

function showToast(message, type = 'info', duration = 5000) {
    const container = document.querySelector('.toast-container');
    const toast = document.createElement('div');
    toast.className = `toast toast-${type}`;
    toast.innerHTML = `
        <span class="toast-message">${message}</span>
        <button class="toast-close">&times;</button>
    `;
    
    container.appendChild(toast);
    
    // Show toast
    setTimeout(() => toast.classList.add('show'), 100);
    
    // Auto remove
    const autoRemove = setTimeout(() => removeToast(toast), duration);
    
    // Manual close
    toast.querySelector('.toast-close').addEventListener('click', () => {
        clearTimeout(autoRemove);
        removeToast(toast);
    });
}

function removeToast(toast) {
    toast.classList.remove('show');
    setTimeout(() => {
        if (toast.parentNode) {
            toast.parentNode.removeChild(toast);
        }
    }, 300);
}


function initThemeSwitcher() {
    const themeSwitcher = document.getElementById('themeSwitcher');
    const themeSwitcherSide = document.getElementById('themeSwitcherSide');
    const doc = document.documentElement;

    // Check for a saved theme in localStorage and apply it
    const currentTheme = localStorage.getItem('theme') || (window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light');
    if (currentTheme) {
        doc.setAttribute('data-theme', currentTheme);
    }

    if (themeSwitcher) {
        themeSwitcher.addEventListener('click', () => {
            let newTheme = doc.getAttribute('data-theme') === 'dark' ? 'light' : 'dark';
            doc.setAttribute('data-theme', newTheme);
            localStorage.setItem('theme', newTheme);
        });
    }
    if (themeSwitcherSide) {
        themeSwitcherSide.addEventListener('click', () => {
            let newTheme = doc.getAttribute('data-theme') === 'dark' ? 'light' : 'dark';
            doc.setAttribute('data-theme', newTheme);
            localStorage.setItem('theme', newTheme);
        });
    }
}


// Utility Functions
function debounce(func, wait) {
    let timeout;
    return function executedFunction(...args) {
        const later = () => {
            clearTimeout(timeout);
            func(...args);
        };
        clearTimeout(timeout);
        timeout = setTimeout(later, wait);
    };
}

function throttle(func, limit) {
    let inThrottle;
    return function() {
        const args = arguments;
        const context = this;
        if (!inThrottle) {
            func.apply(context, args);
            inThrottle = true;
            setTimeout(() => inThrottle = false, limit);
        }
    }
}

// Global loader functions to show and hide the loader
        window.showGlobalLoader = function() {
            const loader = document.getElementById('globalLoader');
            if (loader) {
                loader.classList.add('active');
            }
        };

        window.hideGlobalLoader = function() {
            const loader = document.getElementById('globalLoader');
            if (loader) {
                loader.classList.remove('active');
            }
        };

        // Hide loader when the DOM content is fully loaded
        document.addEventListener('DOMContentLoaded', function() {
            window.hideGlobalLoader();
        });

        // Show loader when any link is clicked
        document.addEventListener('click', function(event) {
            // Check if the clicked element or its parent is an anchor tag
            let targetElement = event.target;
            while (targetElement && targetElement !== document.body) {
                if (targetElement.tagName === 'A') {
                    // Exclude links that are anchors to the current page (e.g., #comments-section)
                    // and external links that open in a new tab
                    const href = targetElement.getAttribute('href');
                    const target = targetElement.getAttribute('target');

                    if (href && !href.startsWith('#') && target !== '_blank') {
                        window.showGlobalLoader();
                    }
                    break; // Stop climbing the DOM if an anchor is found
                }
                targetElement = targetElement.parentNode;
            }
        });
const additionalCSS = `
.form-input.error,
.form-textarea.error,
.form-select.error {
    border-color: #e74c3c;
    box-shadow: 0 0 0 3px rgba(231, 76, 60, 0.1);
}

.field-error {
    color: #e74c3c;
    font-size: 0.875rem;
    margin-top: 0.25rem;
}

.tag-container {
    position: relative;
}

.tags-display {
    display: flex;
    flex-wrap: wrap;
    gap: 0.5rem;
    margin-bottom: 0.5rem;
}

.tag-item {
    background: #3498db;
    color: white;
    padding: 0.25rem 0.5rem;
    border-radius: 4px;
    font-size: 0.875rem;
    display: flex;
    align-items: center;
    gap: 0.25rem;
}

.tag-remove {
    background: none;
    border: none;
    color: white;
    cursor: pointer;
    font-size: 1rem;
    line-height: 1;
}

.tag-remove:hover {
    opacity: 0.8;
}

.toast-container {
    position: fixed;
    top: 20px;
    right: 20px;
    z-index: 10000;
    display: flex;
    flex-direction: column;
    gap: 10px;
}

.toast {
    background: white;
    border-radius: 8px;
    box-shadow: 0 4px 15px rgba(0,0,0,0.1);
    padding: 1rem;
    display: flex;
    align-items: center;
    justify-content: space-between;
    min-width: 300px;
    transform: translateX(100%);
    opacity: 0;
    transition: all 0.3s ease;
}

.toast.show {
    transform: translateX(0);
    opacity: 1;
}

.toast-success {
    border-left: 4px solid #27ae60;
}

.toast-error {
    border-left: 4px solid #e74c3c;
}

.toast-info {
    border-left: 4px solid #3498db;
}

.toast-close {
    background: none;
    border: none;
    font-size: 1.2rem;
    cursor: pointer;
    color: #6c757d;
    margin-left: 1rem;
}

.toast-close:hover {
    color: #333;
}

@media (max-width: 768px) {
    .toast-container {
        left: 20px;
        right: 20px;
    }
    
    .toast {
        min-width: auto;
    }
}
`;

// Inject additional CSS
const style = document.createElement('style');
style.textContent = additionalCSS;
document.head.appendChild(style);

// NOTE: The faulty second 'DOMContentLoaded' event listener that was at the end of the original file has been removed.
// Ensure this is at the top of your script.js or within the DOMContentLoaded block
document.addEventListener('DOMContentLoaded', function() {
    // ... (your existing initializations like initFormValidation, initImagePreview, etc.) ...

    // Initialize new community features
    initCommunityFeed();
    initGroupActions();
    initPrivateMessaging();
    initGameLobby(); // For the main games page
    // Note: Specific game logic (like Tic-Tac-Toe moves) will be in their own game files.
});


// --- Community Feed Functions ---

function initCommunityFeed() {
    const feedContainer = document.getElementById('communityFeed');
    if (!feedContainer) return; // Exit if community feed container doesn't exist on the page

    // Handle New Community Post Form Submission
    const newPostForm = document.getElementById('newCommunityPostForm');
    if (newPostForm) {
        newPostForm.addEventListener('submit', async function(e) {
            e.preventDefault();
            const submitBtn = this.querySelector('button[type="submit"]');
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<span class="loading-spinner"></span> Posting...';

            const formData = new FormData(this); // Automatically handles file uploads if any

            try {
                const response = await fetch('community/api/create_feed_post.php', {
                    method: 'POST',
                    body: formData
                });
                const data = await response.json();

                if (data.success) {
                    showMessage('Post created successfully!', 'success');
                    this.reset(); // Clear the form
                    // Optionally, prepend the new post to the feed without a full reload
                    // This would require the server to return the HTML for the new post
                    // For now, we'll suggest a reload or more advanced dynamic update.
                    setTimeout(() => window.location.reload(), 1000); // Simple reload to see new post
                } else {
                    showMessage('Error creating post: ' + data.message, 'error');
                }
            } catch (error) {
                console.error('Fetch error:', error);
                showMessage('An error occurred while creating the post.', 'error');
            } finally {
                submitBtn.disabled = false;
                submitBtn.innerHTML = 'Post'; // Restore original text
            }
        });
    }

    // Handle Community Post Reactions (Likes, etc.)
    feedContainer.addEventListener('click', async function(e) {
        const reactionBtn = e.target.closest('.community-reaction-btn');
        if (reactionBtn) {
            e.preventDefault();
            const postId = reactionBtn.dataset.postId;
            const reactionType = reactionBtn.dataset.reactionType; // e.g., 'like', 'love'
            const currentCountSpan = reactionBtn.querySelector('.reaction-count');
            const originalCount = parseInt(currentCountSpan.textContent);

            // Optimistic UI update
            const isActive = reactionBtn.classList.toggle('active');
            currentCountSpan.textContent = isActive ? originalCount + 1 : originalCount - 1;

            try {
                const response = await fetch('community/api/react_to_feed_post.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: `post_id=${postId}&reaction_type=${reactionType}&action=${isActive ? 'add' : 'remove'}`
                });
                const data = await response.json();

                if (data.success) {
                    // Update with actual count from server
                    currentCountSpan.textContent = data.new_count;
                } else {
                    showMessage('Error reacting: ' + data.message, 'error');
                    // Revert optimistic update on error
                    reactionBtn.classList.toggle('active');
                    currentCountSpan.textContent = originalCount;
                }
            } catch (error) {
                console.error('Fetch error:', error);
                showMessage('An error occurred while reacting.', 'error');
                // Revert optimistic update on error
                reactionBtn.classList.toggle('active');
                currentCountSpan.textContent = originalCount;
            }
        }

        // Handle Community Post Comment Form Submission
        const commentForm = e.target.closest('.community-comment-form');
        if (commentForm) {
            e.preventDefault();
            const postId = commentForm.dataset.postId;
            const commentContent = commentForm.querySelector('textarea').value.trim();
            const submitBtn = commentForm.querySelector('button[type="submit"]');

            if (!commentContent) {
                showMessage('Comment cannot be empty.', 'error');
                return;
            }

            submitBtn.disabled = true;
            submitBtn.innerHTML = '<span class="loading-spinner"></span>';

            try {
                const response = await fetch('community/api/comment_on_feed_post.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: `post_id=${postId}&comment_content=${encodeURIComponent(commentContent)}`
                });
                const data = await response.json();

                if (data.success) {
                    showMessage('Comment added!', 'success');
                    commentForm.reset(); // Clear the textarea
                    // Optionally, dynamically add the new comment to the comments section
                    // For now, a simple reload or a separate function to load comments.
                    // Example: loadCommentsForPost(postId);
                    setTimeout(() => window.location.reload(), 1000); // Simple reload
                } else {
                    showMessage('Error adding comment: ' + data.message, 'error');
                }
            } catch (error) {
                console.error('Fetch error:', error);
                showMessage('An error occurred while adding comment.', 'error');
            } finally {
                submitBtn.disabled = false;
                submitBtn.innerHTML = 'Comment';
            }
        }
    });

    // Implement "Load More" for Community Feed Posts (if applicable)
    const loadMoreBtn = document.getElementById('loadMoreCommunityPosts');
    if (loadMoreBtn) {
        let offset = 10; // Initial offset, assuming 10 posts are loaded initially
        const limit = 10; // Number of posts to load each time

        loadMoreBtn.addEventListener('click', async function() {
            this.disabled = true;
            this.innerHTML = '<span class="loading-spinner"></span> Loading...';

            try {
                const response = await fetch(`community/api/load_more_feed_posts.php?offset=${offset}&limit=${limit}`);
                const data = await response.json();

                if (data.success && data.posts.length > 0) {
                    // Append new posts to the feed container
                    data.posts.forEach(postHtml => {
                        feedContainer.insertAdjacentHTML('beforeend', postHtml);
                    });
                    offset += data.posts.length; // Update offset
                } else {
                    showMessage('No more posts to load.', 'info');
                    this.style.display = 'none'; // Hide button if no more posts
                }
            } catch (error) {
                console.error('Fetch error:', error);
                showMessage('Error loading more posts.', 'error');
            } finally {
                this.disabled = false;
                this.innerHTML = 'Load More Posts';
            }
        });
    }
}


// --- Group Actions & Chat Functions ---

function initGroupActions() {
    const groupListContainer = document.getElementById('groupList'); // Assuming an ID for the list of groups
    const groupChatContainer = document.getElementById('groupChatBox'); // Assuming an ID for group chat

    // Handle Join/Leave Group Buttons
    if (groupListContainer) {
        groupListContainer.addEventListener('click', async function(e) {
            const joinLeaveBtn = e.target.closest('.join-group-btn, .leave-group-btn');
            if (joinLeaveBtn) {
                e.preventDefault();
                const groupId = joinLeaveBtn.dataset.groupId;
                const action = joinLeaveBtn.classList.contains('join-group-btn') ? 'join' : 'leave';
                const originalText = joinLeaveBtn.innerHTML;

                joinLeaveBtn.disabled = true;
                joinLeaveBtn.innerHTML = '<span class="loading-spinner"></span>';

                try {
                    const response = await fetch('community/api/group_actions.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                        body: `group_id=${groupId}&action=${action}`
                    });
                    const data = await response.json();

                    if (data.success) {
                        showMessage(data.message, 'success');
                        // Update button state visually
                        if (action === 'join') {
                            joinLeaveBtn.classList.remove('join-group-btn', 'btn-primary');
                            joinLeaveBtn.classList.add('leave-group-btn', 'btn-outline');
                            joinLeaveBtn.innerHTML = '<i class="fas fa-sign-out-alt"></i> Leave Group';
                        } else {
                            joinLeaveBtn.classList.remove('leave-group-btn', 'btn-outline');
                            joinLeaveBtn.classList.add('join-group-btn', 'btn-primary');
                            joinLeaveBtn.innerHTML = '<i class="fas fa-sign-in-alt"></i> Join Group';
                        }
                        // Optionally, trigger a reload of the group list or group page
                        setTimeout(() => window.location.reload(), 500);
                    } else {
                        showMessage('Error: ' + data.message, 'error');
                        joinLeaveBtn.innerHTML = originalText; // Revert
                    }
                } catch (error) {
                    console.error('Fetch error:', error);
                    showMessage('An error occurred.', 'error');
                    joinLeaveBtn.innerHTML = originalText; // Revert
                } finally {
                    joinLeaveBtn.disabled = false;
                }
            }
        });
    }

    // Handle Group Chat Message Sending
    const groupChatForm = document.getElementById('groupChatForm');
    if (groupChatForm) {
        groupChatForm.addEventListener('submit', async function(e) {
            e.preventDefault();
            const groupId = this.dataset.groupId;
            const messageInput = this.querySelector('textarea');
            const messageContent = messageInput.value.trim();
            const sendBtn = this.querySelector('button[type="submit"]');

            if (!messageContent) {
                showMessage('Message cannot be empty.', 'error');
                return;
            }

            sendBtn.disabled = true;
            sendBtn.innerHTML = '<span class="loading-spinner"></span>';

            try {
                const response = await fetch('community/api/group_chat.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: `group_id=${groupId}&message=${encodeURIComponent(messageContent)}&action=send_message`
                });
                const data = await response.json();

                if (data.success) {
                    messageInput.value = ''; // Clear input
                    // Immediately append message to chat box (optimistic update)
                    appendGroupMessage(data.message_html); // Assuming server returns HTML for the message
                    groupChatContainer.scrollTop = groupChatContainer.scrollHeight; // Scroll to bottom
                } else {
                    showMessage('Error sending message: ' + data.message, 'error');
                }
            } catch (error) {
                console.error('Fetch error:', error);
                showMessage('An error occurred while sending message.', 'error');
            } finally {
                sendBtn.disabled = false;
                sendBtn.innerHTML = '<i class="fas fa-paper-plane"></i> Send';
            }
        });

        // Polling for Group Chat Messages (basic real-time)
        // For true real-time, consider WebSockets
        let lastMessageId = 0; // Keep track of the last message ID received
        const messagesDisplay = document.getElementById('groupMessagesDisplay');

        async function fetchNewGroupMessages() {
            if (!messagesDisplay || !groupChatForm.dataset.groupId) return;

            const groupId = groupChatForm.dataset.groupId;
            try {
                const response = await fetch(`community/api/group_chat.php?action=get_messages&group_id=${groupId}&last_id=${lastMessageId}`);
                const data = await response.json();

                if (data.success && data.messages.length > 0) {
                    data.messages.forEach(msg => {
                        appendGroupMessage(msg.html); // Assuming server sends HTML for each message
                        if (msg.id > lastMessageId) {
                            lastMessageId = msg.id;
                        }
                    });
                    messagesDisplay.scrollTop = messagesDisplay.scrollHeight; // Scroll to bottom
                }
            } catch (error) {
                console.error('Error fetching group messages:', error);
            }
        }

        // Helper to append a message to the chat display
        function appendGroupMessage(messageHtml) {
            if (messagesDisplay) {
                messagesDisplay.insertAdjacentHTML('beforeend', messageHtml);
            }
        }

        // Start polling every 3 seconds if on a group chat page
        if (groupChatContainer) {
            // Initial load of messages
            fetchNewGroupMessages();
            setInterval(fetchNewGroupMessages, 3000); // Poll every 3 seconds
        }
    }
}


// --- Private Messaging Functions ---

function initPrivateMessaging() {
    const privateMessagesList = document.getElementById('privateMessagesList'); // List of conversations
    const privateMessageThread = document.getElementById('privateMessageThread'); // Individual thread
    const sendPrivateMessageForm = document.getElementById('sendPrivateMessageForm');

    // Handle Send Private Message
    if (sendPrivateMessageForm) {
        sendPrivateMessageForm.addEventListener('submit', async function(e) {
            e.preventDefault();
            const receiverId = this.dataset.receiverId; // User ID of the recipient
            const messageInput = this.querySelector('textarea');
            const messageContent = messageInput.value.trim();
            const sendBtn = this.querySelector('button[type="submit"]');

            if (!messageContent) {
                showMessage('Message cannot be empty.', 'error');
                return;
            }

            sendBtn.disabled = true;
            sendBtn.innerHTML = '<span class="loading-spinner"></span>';

            try {
                const response = await fetch('community/api/private_messages.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: `receiver_id=${receiverId}&message=${encodeURIComponent(messageContent)}&action=send_message`
                });
                const data = await response.json();

                if (data.success) {
                    messageInput.value = ''; // Clear input
                    // Append new message to thread (optimistic update)
                    appendPrivateMessage(data.message_html); // Assuming server returns HTML for the message
                    privateMessageThread.scrollTop = privateMessageThread.scrollHeight; // Scroll to bottom
                } else {
                    showMessage('Error sending message: ' + data.message, 'error');
                }
            } catch (error) {
                console.error('Fetch error:', error);
                showMessage('An error occurred while sending message.', 'error');
            } finally {
                sendBtn.disabled = false;
                sendBtn.innerHTML = '<i class="fas fa-paper-plane"></i> Send';
            }
        });

        // Polling for Private Messages (basic real-time)
        let lastPmId = 0; // Keep track of the last message ID received
        const messagesDisplay = document.getElementById('privateMessageThread');

        async function fetchNewPrivateMessages() {
            if (!messagesDisplay || !sendPrivateMessageForm.dataset.receiverId) return;

            const receiverId = sendPrivateMessageForm.dataset.receiverId;
            try {
                const response = await fetch(`community/api/private_messages.php?action=get_messages&partner_id=${receiverId}&last_id=${lastPmId}`);
                const data = await response.json();

                if (data.success && data.messages.length > 0) {
                    data.messages.forEach(msg => {
                        appendPrivateMessage(msg.html); // Assuming server sends HTML for each message
                        if (msg.id > lastPmId) {
                            lastPmId = msg.id;
                        }
                    });
                    messagesDisplay.scrollTop = messagesDisplay.scrollHeight; // Scroll to bottom
                    // Mark newly fetched messages as read
                    markMessagesAsRead(receiverId);
                }
            } catch (error) {
                console.error('Error fetching private messages:', error);
            }
        }

        // Helper to append a message to the chat display
        function appendPrivateMessage(messageHtml) {
            if (messagesDisplay) {
                messagesDisplay.insertAdjacentHTML('beforeend', messageHtml);
            }
        }

        // Function to mark messages as read
        async function markMessagesAsRead(partnerId) {
            try {
                await fetch('community/api/private_messages.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: `partner_id=${partnerId}&action=mark_read`
                });
                // No need for success message, it's a background action
            } catch (error) {
                console.error('Error marking messages as read:', error);
            }
        }

        // Start polling every 2 seconds if on a private message thread page
        if (privateMessageThread) {
            // Initial load of messages
            fetchNewPrivateMessages();
            setInterval(fetchNewPrivateMessages, 2000); // Poll every 2 seconds
        }
    }

    // Handle Mark as Read button on message list (if implemented)
    if (privateMessagesList) {
        privateMessagesList.addEventListener('click', async function(e) {
            const markReadBtn = e.target.closest('.mark-as-read-btn');
            if (markReadBtn) {
                e.preventDefault();
                const partnerId = markReadBtn.dataset.partnerId;
                markReadBtn.disabled = true;
                markReadBtn.innerHTML = '<span class="loading-spinner"></span>';

                try {
                    const response = await fetch('community/api/private_messages.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                        body: `partner_id=${partnerId}&action=mark_read`
                    });
                    const data = await response.json();

                    if (data.success) {
                        showMessage('Messages marked as read.', 'success');
                        // Update UI: remove unread badge/indicator
                        const unreadBadge = markReadBtn.closest('.conversation-item').querySelector('.unread-badge');
                        if (unreadBadge) unreadBadge.remove();
                    } else {
                        showMessage('Error marking messages as read: ' + data.message, 'error');
                    }
                } catch (error) {
                    console.error('Fetch error:', error);
                    showMessage('An error occurred.', 'error');
                } finally {
                    markReadBtn.disabled = false;
                    markReadBtn.innerHTML = 'Mark Read'; // Restore original text
                }
            }
        });
    }
}


// --- Game Lobby Functions ---

function initGameLobby() {
    const gameLobbyContainer = document.getElementById('gameLobby');
    if (!gameLobbyContainer) return;

    // Handle Create New Game Session
    const createGameForm = document.getElementById('createGameSessionForm');
    if (createGameForm) {
        createGameForm.addEventListener('submit', async function(e) {
            e.preventDefault();
            const gameId = this.querySelector('select[name="game_id"]').value;
            const submitBtn = this.querySelector('button[type="submit"]');

            submitBtn.disabled = true;
            submitBtn.innerHTML = '<span class="loading-spinner"></span> Creating...';

            try {
                const response = await fetch('community/api/game_actions.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: `game_id=${gameId}&action=create_session`
                });
                const data = await response.json();

                if (data.success) {
                    showMessage('Game session created! Redirecting...', 'success');
                    // Redirect to the new game session page
                    window.location.href = `community/game_session.php?id=${data.session_id}`;
                } else {
                    showMessage('Error creating game session: ' + data.message, 'error');
                }
            } catch (error) {
                console.error('Fetch error:', error);
                showMessage('An error occurred while creating game session.', 'error');
            } finally {
                submitBtn.disabled = false;
                submitBtn.innerHTML = 'Create Session';
            }
        });
    }

    // Handle Join Game Session
    gameLobbyContainer.addEventListener('click', async function(e) {
        const joinGameBtn = e.target.closest('.join-game-session-btn');
        if (joinGameBtn) {
            e.preventDefault();
            const sessionId = joinGameBtn.dataset.sessionId;
            const originalText = joinGameBtn.innerHTML;

            joinGameBtn.disabled = true;
            joinGameBtn.innerHTML = '<span class="loading-spinner"></span> Joining...';

            try {
                const response = await fetch('community/api/game_actions.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: `session_id=${sessionId}&action=join_session`
                });
                const data = await response.json();

                if (data.success) {
                    showMessage('Joined game session! Redirecting...', 'success');
                    window.location.href = `community/game_session.php?id=${sessionId}`;
                } else {
                    showMessage('Error joining game: ' + data.message, 'error');
                    joinGameBtn.innerHTML = originalText; // Revert
                }
            } catch (error) {
                console.error('Fetch error:', error);
                showMessage('An error occurred while joining game.', 'error');
                joinGameBtn.innerHTML = originalText; // Revert
            } finally {
                joinGameBtn.disabled = false;
            }
        }
    });

    // Note: Individual game logic (e.g., Tic-Tac-Toe moves, board updates)
    // will reside in their respective game files (e.g., tic_tac_toe.php, which
    // would be included by game_session.php). These files would have their
    // own JavaScript to interact with `community/api/game_actions.php`
    // using actions like `make_move` and `get_state`.
}

// Global function for showing messages (assuming it exists in your script.js)
// If not, add this:
/*
function showMessage(message, type) {
    const messageDiv = document.createElement('div');
    messageDiv.className = `follow-message ${type}`; // Reusing existing styles
    messageDiv.textContent = message;
    document.body.appendChild(messageDiv);

    setTimeout(() => messageDiv.classList.add('show'), 100);
    setTimeout(() => {
        messageDiv.classList.remove('show');
        setTimeout(() => document.body.removeChild(messageDiv), 300);
    }, 3000);
}
*/