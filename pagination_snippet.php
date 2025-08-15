<!-- HTML/PHP Pagination Snippet -->
<?php
// Ensure these variables are defined before including this snippet:
// $page (current page number)
// $total_pages (total number of pages)
// $base_url (e.g., 'index.php', 'category.php?id=123', 'manage_posts.php?search=abc')
// $page_param_name (optional, default 'page')

// Example default values for demonstration if not set (remove in production)
if (!isset($page)) $page = 1;
if (!isset($total_pages)) $total_pages = 1;
if (!isset($base_url)) $base_url = basename($_SERVER['PHP_SELF']); // Current script name
if (!isset($page_param_name)) $page_param_name = 'page'; // Default page parameter name

// The build_pagination_url function is assumed to be defined in includes/functions.php
// DO NOT redeclare it here.

if ($total_pages > 1): ?>
    <div class="pagination">
        <?php if ($page > 1): ?>
            <a href="<?php echo build_pagination_url($base_url, $page - 1, $page_param_name); ?>">&laquo; Previous</a>
        <?php endif; ?>

        <?php
        // Logic to display a limited number of page links around the current page
        $num_links_to_show = 5; // Total number of page links to display (e.g., 1 2 [3] 4 5)
        $start_page = max(1, $page - floor($num_links_to_show / 2));
        $end_page = min($total_pages, $start_page + $num_links_to_show - 1);

        // Adjust start_page if end_page hits total_pages but start_page is too low
        if ($end_page - $start_page + 1 < $num_links_to_show) {
            $start_page = max(1, $end_page - $num_links_to_show + 1);
        }

        // Display first page link if not in range
        if ($start_page > 1) {
            echo '<a href="' . build_pagination_url($base_url, 1, $page_param_name) . '">1</a>';
            if ($start_page > 2) {
                echo '<span>...</span>'; // Ellipsis
            }
        }

        for ($i = $start_page; $i <= $end_page; $i++): ?>
            <?php if ($i == $page): ?>
                <span class="current"><?php echo $i; ?></span>
            <?php else: ?>
                <a href="<?php echo build_pagination_url($base_url, $i, $page_param_name); ?>"><?php echo $i; ?></a>
            <?php endif; ?>
        <?php endfor; ?>

        <?php
        // Display last page link if not in range
        if ($end_page < $total_pages) {
            if ($end_page < $total_pages - 1) {
                echo '<span>...</span>'; // Ellipsis
            }
            echo '<a href="' . build_pagination_url($base_url, $total_pages, $page_param_name) . '">' . $total_pages . '</a>';
        }
        ?>

        <?php if ($page < $total_pages): ?>
            <a href="<?php echo build_pagination_url($base_url, $page + 1, $page_param_name); ?>">Next &raquo;</a>
        <?php endif; ?>
    </div>
<?php endif; ?>

<!-- CSS for Pagination (can be added to your existing style.css) -->
<style>
    .pagination {
        display: flex;
        justify-content: center;
        flex-wrap: wrap;
        gap: var(--spacing-xs); /* Using your existing CSS variable */
        margin: var(--spacing-xl) 0; /* Using your existing CSS variable */
        padding: 1rem; /* Add some padding around the pagination block */
        background-color: var(--background-white); /* Match card background */
        border-radius: var(--border-radius-lg); /* Rounded corners */
        box-shadow: var(--shadow-light); /* Subtle shadow */
        max-width: fit-content; /* Adjust width to content */
        margin-left: auto;
        margin-right: auto;
    }

    .pagination a,
    .pagination span {
        padding: 0.6rem var(--spacing-sm);
        border: 1px solid var(--border-color);
        border-radius: var(--border-radius-md);
        color: var(--primary-color);
        transition: all 0.3s ease;
        font-size: var(--font-size-base);
        min-width: 44px; /* Ensures touch target size */
        text-align: center;
        text-decoration: none; /* Remove underline from links */
        display: flex; /* For better alignment of content */
        align-items: center;
        justify-content: center;
    }

    .pagination a:hover {
        background: var(--primary-color);
        color: white;
        box-shadow: var(--shadow-hover);
    }

    .pagination .current {
        background: var(--primary-color);
        color: white;
        border-color: var(--primary-color);
        pointer-events: none; /* Disable clicks on the current page */
        font-weight: bold;
    }

    .pagination span { /* For ellipsis or other non-link spans */
        border: none;
        background: transparent;
        color: var(--secondary-color);
        cursor: default;
    }

    /* Responsive adjustments for smaller screens */
    @media (max-width: 600px) {
        .pagination {
            padding: 0.75rem;
            margin: var(--spacing-lg) 0;
            border-radius: var(--border-radius-md);
        }
        .pagination a,
        .pagination span {
            padding: 0.5rem 0.8rem;
            font-size: 0.9rem;
            min-width: 38px;
        }
    }
</style>

<!-- No specific JavaScript is needed for this server-side pagination.
     The links handle page navigation directly. -->
