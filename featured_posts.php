    <?php
    // Ensure database connection and functions are available
    // Assumes $conn is available from includes/header.php
    // Assumes functions like truncate_text, format_date, get_post_tags are in includes/functions.php

    // Get featured posts: manually featured OR top 3 by likes
    // This query prioritizes manually featured posts, then posts by total likes, then by published date.
    // It also ensures distinct posts are returned if a post is both manually featured and in top likes.

    $available_avatars_global_for_display = get_available_avatars('avatars/');

    $featured_query = "SELECT DISTINCT
                            p.*,
                            u.username,
                            u.profile_image_path,
                            u.prefers_avatar,
                            u.gender,
                            c.name as category_name,
                            (SELECT COUNT(*) FROM likes WHERE post_id = p.id) as total_likes
                        FROM
                            posts p
                        LEFT JOIN
                            users u ON p.user_id = u.id
                        LEFT JOIN
                            categories c ON p.category_id = c.id
                        LEFT JOIN (
                            SELECT post_id, COUNT(*) as like_count
                            FROM likes
                            GROUP BY post_id
                            ORDER BY like_count DESC
                            LIMIT 3
                        ) AS top_liked_posts ON p.id = top_liked_posts.post_id
                        WHERE
                            p.status = 'published' AND (
                                p.is_featured = 1
                                OR top_liked_posts.post_id IS NOT NULL -- This means it's one of the top 3 liked posts
                            )
                        ORDER BY
                            p.is_featured DESC, -- Manually featured posts first (1 before 0)
                            total_likes DESC,    -- Then by likes (highest likes first)
                            p.published_at DESC  -- Then by publish date (newest first for tie-breaking)
                        LIMIT 6"; // Limit the total number of featured posts shown in the carousel

    $featured_result = $conn->query($featured_query);

    // Add error handling for the query
    if (!$featured_result) {
        // Log the error for debugging purposes
        error_log("SQL Error in featured_posts.php: " . $conn->error);
        // Display a user-friendly error message
        echo "<div class='alert alert-danger text-center'>Error loading featured posts. Please try again later. (Error code: " . $conn->errno . ")</div>";
        // To prevent further PHP errors, set $featured_posts to an empty array
        $featured_posts = [];
        $display_carousel = false; // Flag to prevent carousel display
    } else {
        $featured_posts = [];
        while ($post = $featured_result->fetch_assoc()) {
            $featured_posts[] = $post;
        }
        $display_carousel = count($featured_posts) > 0;
    }

    

    ?>

    <?php if ($display_carousel): // Only display section if there are posts to show ?>
    <h2 class="text-center mb-3">Featured Posts</h2>
    <section class="featured-section mb-4" id="featured-section-bg">
        <div class="featured-carousel-wrapper">
            <div class="featured-carousel">
                <?php 
                // Clone last and first post for seamless looping, but avoid pause by instantly jumping after transition
                $cloned_posts = [];
                $count = count($featured_posts);
                if ($count > 1) {
                    $cloned_posts[] = $featured_posts[$count - 1]; // last as first clone
                    foreach ($featured_posts as $p) $cloned_posts[] = $p;
                    $cloned_posts[] = $featured_posts[0]; // first as last clone
                } else {
                    // If only one post, clone it so the carousel still has something to slide to and from
                    foreach ($featured_posts as $p) $cloned_posts[] = $p;
                    if ($count === 1) { // If only one real post, duplicate it for cloning effect
                        $cloned_posts[] = $featured_posts[0]; 
                        $cloned_posts = array_merge([end($cloned_posts)], $cloned_posts); // Add one at start as well
                    }
                }

                foreach ($cloned_posts as $post): ?>
                    <article class="post-card"><a href="post.php?id=<?php echo $post['id']; ?>">
                        <?php 
                        $image_src = '';
                        if (!empty($post['image_path'])) {
                            if (filter_var($post['image_path'], FILTER_VALIDATE_URL)) {
                                // For Unsplash or external URLs, append optimization parameters if needed
                                $image_src = htmlspecialchars($post['image_path']) . '?q=80&w=600&h=400&fit=crop';
                            } else {
                                // Assume local paths are 'uploads/filename.jpg'
                                $image_src = 'uploads/' . htmlspecialchars(basename($post['image_path']));
                                // Fallback for local images if the file doesn't exist in uploads/
                                if (!file_exists($image_src)) {
                                    $image_src = '../' . htmlspecialchars($post['image_path']);
                                }
                            }
                        }    ?>
                        <?php if ($image_src): ?>
                            <a href="post.php?id=<?php echo $post['id']; ?>"><div class="post-image">
                                <img src="<?php echo $image_src; ?>" 
                                     alt="<?php echo htmlspecialchars($post['title']); ?>" 
                                     loading="lazy"
                                     onerror="this.onerror=null;this.src='https://placehold.co/600x400/cccccc/333333?text=Image+Not+Found';">
                            </div></a>
                        <?php else: ?>
                            <div class="post-image no-image" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);">
                                <i class="fas fa-image"></i>
                            </div>
                        <?php endif; ?>
                        
                        <div class="post-content">
                            <div class="post-meta">
                                <span class="post-category"><?php echo htmlspecialchars($post['category_name'] ?? 'Uncategorized'); ?></span>
                                <span>
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
                            
                            <a href="post.php?id=<?php echo $post['id']; ?>" class="btn btn-primary">Read More</a>
                        </div></a>
                    </article>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="carousel-nav">
            <button id="carousel-prev" aria-label="Previous Featured Post"><i class="fas fa-chevron-left"></i></button>
            <button id="carousel-pause" aria-label="Pause/Play Carousel"><i class="fas fa-pause"></i></button>
            <button id="carousel-next" aria-label="Next Featured Post"><i class="fas fa-chevron-right"></i></button>
        </div>

        <style>
            /* Styles for the featured section and carousel */
            .featured-section {
                border-left: 6px solid #667eea;
                border-right: 6px solid #764ba2;
                border-radius: var(--border-radius-lg);
                box-shadow: var(--shadow-medium);
                max-width: 100%;
                position: relative;
                transition: background 0.5s; /* For smooth background image changes */
                background-size: cover;
                background-position: center;
                background-repeat: no-repeat;
                background-color: #f8f8fc; /* Fallback background color */
                overflow: hidden; /* Ensure content and pseudo-elements stay within bounds */
                /* Increased padding top and bottom */
                padding: 4rem 0; /* Added space at top/bottom */
            }

            .featured-section::before {
                content: "";
                position: absolute;
                inset: 0;
                background: rgba(40, 30, 60, 0.45); /* Overlay for readability */
                border-radius: inherit;
                z-index: 0;
                pointer-events: none; /* Allows clicks on elements behind it */
            }

            .featured-section > * {
                position: relative;
                z-index: 1; /* Ensure carousel content is above the background overlay */
            }

            .featured-carousel-wrapper {
                max-width: 100%;
                box-sizing: border-box;
                padding: 0 1rem; /* Add some padding to the wrapper for spacing at edges */
                margin: 0 auto; /* Center the wrapper */
            }

            .featured-carousel {
                display: flex;
                align-items: center; /* Center items vertically */
                gap: 1.5rem; /* Gap between carousel items */
                transition: transform 0.6s cubic-bezier(.4,0,.2,1); /* Main slide animation */
                will-change: transform;
                /* Added padding to ensure space for enlarged center card */
                padding: 1.5rem 0; 
            }

            .featured-carousel .post-card {
                flex-shrink: 0; /* Prevent items from shrinking */
                box-sizing: border-box; /* Ensure padding/border are included in width calculation */
                width: calc(100% - 1.5rem);
                max-height:90%; /* Default: 1 item per view (minus gap) on mobile */
                display: flex;
                flex-direction: column; /* Stack image and content vertically */
                background: var(--background-light);
                border-radius: var(--border-radius-lg);
                box-shadow: var(--shadow-medium);
                transition: transform 0.3s ease, box-shadow 0.3s ease, border 0.3s, width 0.3s ease, height 0.3s ease, margin 0.3s ease;
                border: 1px solid #e0d7f3; /* Default border */
                overflow: hidden;
                /* Default size for non-centered cards */
                transform: scale(0.85); /* Smaller default size */
                opacity: 0.7; /* Slightly faded */
            }
            
            /* Styles for the centered, enlarged card */
            .featured-carousel .post-card.highlighted-bg {
                border: 2px solid var(--primary-color); /* Stronger border for highlighted card */
                box-shadow: var(--shadow-hover); /* Enhanced shadow for highlight */
                transform: scale(1); /* Original size for centered card */
                opacity: 1; /* Fully opaque */
                margin: 0 1rem; /* Add horizontal margin for breathing room */
                z-index: 2; /* Bring to front */
            }


            .featured-carousel .post-card:hover {
                transform: translateY(-5px) scale(1.02); /* Maintain hover effect */
                box-shadow: var(--shadow-hover);
                border-color: rgb(255, 255, 255); /* White border on hover for contrast */
            }
            
            .featured-carousel .post-card .post-image {
                /* Make the image section square based on its width */
                height: 0; 
                padding-bottom: 100%; /* Creates a square container based on its width */
                position: relative; 
                overflow: hidden; 
                border-top-left-radius: var(--border-radius-lg);
                border-top-right-radius: var(--border-radius-lg);
            }

            .featured-carousel .post-card .post-image img {
                position: absolute;
                top: 0;
                left: 0;
                width: 100%;
                height: 100%;
                object-fit: cover;
            }

            /* Fallback for no image case (maintaining square aspect) */
            .featured-carousel .post-card .post-image.no-image {
                display: flex;
                align-items: center;
                justify-content: center;
                color: white;
                font-size: 2rem; 
            }

            .featured-carousel .post-card .post-content {
                padding: var(--spacing-md);
                flex-grow: 1; 
                display: flex;
                flex-direction: column;
                justify-content: space-between; 
            }
            
            .featured-carousel .post-card .post-title {
                font-size: var(--font-size-lg); 
                margin-bottom: var(--spacing-xs);
            }

            .featured-carousel .post-card .post-excerpt {
                flex-grow: 1; 
                margin-bottom: var(--spacing-sm);
            }

            .featured-carousel .post-card .btn {
                margin-top: var(--spacing-sm); 
            }

            /* Carousel navigation buttons */
            .carousel-nav {
                display: flex;
                justify-content: center;
                gap: 1.5rem;
                margin: 1.5rem 0; /* Spacing above and below nav buttons */
            }
            .carousel-nav button {
                background: var(--background-white);
                border: 1px solid var(--primary-color);
                color: var(--primary-color);
                border-radius: 50%;
                width: 2.8rem; /* Slightly larger buttons */
                height: 2.8rem;
                font-size: 1.2rem;
                cursor: pointer;
                transition: background 0.2s ease, color 0.2s ease, box-shadow 0.2s ease;
                display: flex;
                align-items: center;
                justify-content: center;
                box-shadow: var(--shadow-light);
            }
            .carousel-nav button:hover {
                background: var(--primary-color);
                color: #fff;
                box-shadow: var(--shadow-medium);
            }
            .carousel-nav button.paused {
                background: var(--accent-color); /* Red for paused state */
                border-color: var(--accent-color);
                color: #fff;
            }
            .carousel-nav button.paused:hover {
                background: #c0392b; /* Darker red on hover */
            }

            /* Responsive adjustments for number of posts shown */
            @media (min-width: 768px) {
                .featured-carousel .post-card {
                    /* Adjusted width to allow for scaling of the center card */
                    width: calc(33.333% - 1.5rem); /* Show 3 cards, but center one will expand */
                }
                .featured-carousel-wrapper {
                    padding: 0 1.5rem; 
                }
                .featured-carousel .post-card.highlighted-bg {
                    width: calc(50% - 1.5rem); /* Larger width for the centered card on medium screens */
                }
            }

            @media (min-width: 992px) {
                .featured-carousel .post-card {
                    width: calc(25% - 2rem); /* Show more cards, allowing center to be bigger */
                }
                .featured-carousel-wrapper {
                    padding: 0 2rem; 
                }
                 .featured-carousel .post-card.highlighted-bg {
                    width: calc(33.333% - 2rem); /* Larger width for the centered card on large screens */
                }
            }
        </style>

        <script>
    (function() {
    const carouselWrapper = document.querySelector('.featured-carousel-wrapper');
    const carousel = document.querySelector('.featured-carousel');
    const posts = document.querySelectorAll('.featured-carousel .post-card');
    const totalActualPosts = <?php echo count($featured_posts); ?>;
    const totalCarouselItems = posts.length;
    const featuredSection = document.getElementById('featured-section-bg');

    // Collect image paths for all actual posts (not clones)
    const featuredImages = [
        <?php foreach ($featured_posts as $p): ?>
            <?php
            $image_src_js = '';
            if (!empty($p['image_path'])) {
                if (filter_var($p['image_path'], FILTER_VALIDATE_URL)) {
                    $image_src_js = htmlspecialchars($p['image_path'], ENT_QUOTES) . '?q=80&w=1200&h=800&fit=crop';
                } else {
                    $image_src_js = 'uploads/' . htmlspecialchars(basename($p['image_path']));
                }
            }
            ?>
            "<?php echo $image_src_js; ?>",
        <?php endforeach; ?>
    ];

    if (totalActualPosts === 0) {
        featuredSection.style.display = 'none';
        return;
    }

    let index = 1; // Start at the first actual slide
    let animating = false;
    let autoSlideInterval;
    let paused = false;
    let isTransitioning = false;

    // Helper to get the actual "slide unit" distance between the start of two consecutive cards
    function getSlideUnit() {
        if (posts.length < 2 || !posts[0] || !posts[1]) {
            return posts[0] ? (posts[0].offsetWidth + parseFloat(getComputedStyle(carousel).gap)) : 0;
        }
        return posts[1].offsetLeft - posts[0].offsetLeft;
    }

    // Function to find and return the highlighted post (with blue border)
    function getHighlightedPost() {
        return document.querySelector('.featured-carousel .post-card.highlighted-bg');
    }

    // Function to set background image based on the highlighted post
    function setBgFromHighlightedPost() {
        const highlightedPost = getHighlightedPost();
        if (!highlightedPost) return;

        // Find the index of the highlighted post in the DOM
        const highlightedIndex = Array.from(posts).indexOf(highlightedPost);

        // Map DOM index to actual post index (accounting for clones)
        let actualPostIndex;
        if (highlightedIndex === 0) {
            actualPostIndex = totalActualPosts - 1; // First clone is last actual post
        } else if (highlightedIndex === totalActualPosts + 1) {
            actualPostIndex = 0; // Last clone is first actual post
        } else {
            actualPostIndex = highlightedIndex - 1; // Adjust for first clone
        }

        // Ensure we have a valid index
        if (actualPostIndex >= 0 && actualPostIndex < featuredImages.length) {
            const img = featuredImages[actualPostIndex];
            if (img && img !== '') {
                featuredSection.style.backgroundImage = `linear-gradient(rgba(40,30,60,0.45),rgba(40,30,60,0.45)),url('${img}')`;
            } else {
                featuredSection.style.backgroundImage = 'linear-gradient(rgba(40,30,60,0.45),rgba(40,30,60,0.45))';
            }
        }
    }

    // Function to apply card-specific transforms and classes
    function applyCardTransforms(carouselIndex) {
        let targetCardDOMIndex = carouselIndex;
        let cardsVisible = 1;

        if (window.innerWidth >= 992) {
            cardsVisible = 3;
            targetCardDOMIndex = carouselIndex + 1; // Middle card of 3
        } else if (window.innerWidth >= 768) {
            cardsVisible = 2;
            targetCardDOMIndex = carouselIndex; // Leftmost of 2
        }

        // First remove all highlights and reset transforms
        document.querySelectorAll('.featured-carousel .post-card').forEach((card, idx) => {
            card.classList.remove('highlighted-bg');
            card.style.transform = 'scale(0.85)';
            card.style.opacity = '0.7';
            card.style.zIndex = '1';
            card.style.transition = 'transform 0.4s ease, opacity 0.4s ease';
        });

        // Apply highlight to the target card
        if (targetCardDOMIndex >= 0 && targetCardDOMIndex < posts.length) {
            posts[targetCardDOMIndex].classList.add('highlighted-bg');
            posts[targetCardDOMIndex].style.transform = 'scale(1)';
            posts[targetCardDOMIndex].style.opacity = '1';
            posts[targetCardDOMIndex].style.zIndex = '2';

            // Update background to match the newly highlighted post
            setBgFromHighlightedPost();
        }
    }

    // Main function to update slide position and appearance
    function showSlide(i, animate = true) {
        // Allow the function to proceed even if animating, IF animate is false (for instant jumps)
        if (animating && animate) return;

        // Set animating and transitioning flags
        animating = true;
        isTransitioning = true;

        // Disable transitions for instant jumps, enable for smooth animations
        if (!animate) {
            carousel.style.transition = 'none';
        } else {
            // Only set transition if it's currently 'none' or different from desired
            if (carousel.style.transition === 'none' || carousel.style.transition === '') {
                carousel.style.transition = 'transform 0.6s cubic-bezier(0.2, 0.8, 0.4, 1)';
            }
        }

        // Apply card transforms first (before positioning the carousel)
        applyCardTransforms(i);

        // Force reflow to ensure computed styles are up-to-date BEFORE calculating new transform
        void carousel.offsetWidth;

        const wrapperWidth = carouselWrapper.offsetWidth;
        // const slideUnit = getSlideUnit(); // This might not be strictly needed for centering logic

        let targetCardDOMIndex = i;
        if (window.innerWidth >= 992) {
            targetCardDOMIndex = i + 1;
        } else if (window.innerWidth >= 768) {
            targetCardDOMIndex = i;
        }

        const currentCenterCard = posts[targetCardDOMIndex];

        if (currentCenterCard) {
            const carouselRect = carousel.getBoundingClientRect();
            const wrapperWidth = carouselRect.width;

            const currentCardRect = currentCenterCard.getBoundingClientRect();
            const currentCardCenterRelativeToViewport = currentCardRect.left + currentCardRect.width / 2;

            const carouselCenterRelativeToViewport = carouselRect.left + wrapperWidth / 2;

            const calculatedTransformX = carouselCenterRelativeToViewport - currentCardCenterRelativeToViewport;

            carousel.style.transform = `translateX(${calculatedTransformX}px)`;
        }

        // Reset animating flag after transition ends
        if (animate) {
            const transitionEndHandler = () => {
                carousel.removeEventListener('transitionend', transitionEndHandler);
                animating = false; // Important: Reset animating here
                isTransitioning = false; // Reset transitioning here

                // Handle seamless looping for going past the last slide (cloned first slide)
                if (index >= totalActualPosts + 1) { // Use 'index' not 'i' here, as 'index' is the global state
                    carousel.style.transition = 'none'; // Temporarily remove transition
                    index = 1; // Set index to the first actual post
                    showSlide(index, false); // Call showSlide with animate=false for instant jump
                    void carousel.offsetWidth; // Force reflow
                    // No further showSlide() call here. The startAutoSlide() will handle the next animation.

                // Handle seamless looping for going before the first slide (cloned last slide)
                } else if (index <= 0) { // Use 'index' not 'i' here
                    carousel.style.transition = 'none'; // Temporarily remove transition
                    index = totalActualPosts; // Set index to the last actual post
                    showSlide(index, false); // Call showSlide with animate=false
                    void carousel.offsetWidth; // Force reflow
                }

                // After handling any jumps or normal transitions,
                // if not paused, start auto-sliding for the *next* natural transition.
                // This ensures auto-slide continues after a loop or a normal slide.
                if (!paused) {
                    startAutoSlide();
                }
            };

            // Ensure the event listener is added only once
            carousel.addEventListener('transitionend', transitionEndHandler, { once: true });
        } else {
            // If not animating (i.e., this was an instant jump),
            // flags are already reset by the start of the function,
            // but we still need to potentially start auto-slide.
            animating = false;
            isTransitioning = false;
            if (!paused) {
                startAutoSlide();
            }
        }
    }

    // MutationObserver to watch for changes in highlighted post
    const observer = new MutationObserver(function(mutations) {
        mutations.forEach(function(mutation) {
            if (mutation.type === 'attributes' && mutation.attributeName === 'class') {
                const target = mutation.target;
                // Only update if the target *gains* the highlighted class
                if (target.classList.contains('highlighted-bg') && !target.dataset.hasBeenHighlighted) {
                    setBgFromHighlightedPost();
                    target.dataset.hasBeenHighlighted = true; // Prevent multiple calls for same highlight
                } else if (!target.classList.contains('highlighted-bg')) {
                    delete target.dataset.hasBeenHighlighted; // Reset when unhighlighted
                }
            }
        });
    });

    // Observe all post cards for class changes
    posts.forEach(post => {
        observer.observe(post, { attributes: true });
    });

    // Advances to the next slide
    function nextSlide() {
        if (animating) return; // Prevent rapid clicks/auto-advances
        index++;
        showSlide(index);
    }

    // Advances to the previous slide
    function prevSlide() {
        if (animating) return; // Prevent rapid clicks/auto-advances
        index--;
        showSlide(index);
    }

    // Starts the automatic slide show
    function startAutoSlide() {
        if (autoSlideInterval) clearInterval(autoSlideInterval);
        if (!paused && !isTransitioning) {
            autoSlideInterval = setInterval(() => {
                if (!isTransitioning) { // Double check inside interval
                    nextSlide();
                }
            }, 5000);
        }
    }

    // Pauses the automatic slide show
    function pauseAutoSlide() {
        if (autoSlideInterval) clearInterval(autoSlideInterval);
    }

    // Recalculate and reset on window resize
    function handleResize() {
        pauseAutoSlide(); // Pause during resize
        showSlide(index, false); // Instantly snap to current slide's correct position
        // After resize, if not paused, re-enable auto-slide
        if (!paused) {
            startAutoSlide();
        }
    }

    // Throttle resize events
    let resizeTimeout;
    window.addEventListener('resize', () => {
        clearTimeout(resizeTimeout);
        resizeTimeout = setTimeout(handleResize, 100);
    });

    // --- Carousel Navigation Buttons ---
    const btnPrev = document.getElementById('carousel-prev');
    const btnNext = document.getElementById('carousel-next');
    const btnPause = document.getElementById('carousel-pause');

    if (btnPrev) { // Add null checks for buttons
        btnPrev.addEventListener('click', function() {
            pauseAutoSlide();
            prevSlide();
        });
    }

    if (btnNext) {
        btnNext.addEventListener('click', function() {
            pauseAutoSlide();
            nextSlide();
        });
    }

    if (btnPause) {
        btnPause.addEventListener('click', function() {
            paused = !paused;
            const icon = btnPause.querySelector('i');
            if (paused) {
                pauseAutoSlide();
                btnPause.classList.add('paused');
                icon.classList.remove('fa-pause');
                icon.classList.add('fa-play');
                icon.setAttribute('aria-label', 'Play Carousel');
            } else {
                btnPause.classList.remove('paused');
                icon.classList.remove('fa-play');
                icon.classList.add('fa-pause');
                icon.setAttribute('aria-label', 'Pause Carousel');
                startAutoSlide();
            }
        });
    }


    // Keyboard accessibility for navigation buttons (add null checks here too)
    if (btnPrev) {
        btnPrev.addEventListener('keydown', function(e) {
            if (e.key === 'Enter' || e.key === ' ') {
                e.preventDefault();
                btnPrev.click();
            }
        });
    }
    if (btnNext) {
        btnNext.addEventListener('keydown', function(e) {
            if (e.key === 'Enter' || e.key === ' ') {
                e.preventDefault();
                btnNext.click();
            }
        });
    }
    if (btnPause) {
        btnPause.addEventListener('keydown', function(e) {
            if (e.key === 'Enter' || e.key === ' ') {
                e.preventDefault();
                btnPause.click();
            }
        });
    }

    // Initial setup when the page loads
    // Ensure initial showSlide runs AFTER the DOM is fully ready
    document.addEventListener('DOMContentLoaded', () => {
        if (totalActualPosts > 0) { // Only initialize if there are posts
            showSlide(index, false); // Show first slide instantly
            startAutoSlide();
        }
    });

})();
</script>
    </section>
    <?php endif; ?>
