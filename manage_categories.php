<?php
$page_title = "Manage Categories";

// Handle add/edit category and delete actions BEFORE any output
// (move all POST/GET logic above header include)

ob_start(); // Start output buffering

include 'includes/header.php';

// Require admin access
require_admin();

// Handle add/edit category
if ($_SERVER['REQUEST_METHOD'] == 'POST' && (isset($_POST['add_category']) || isset($_POST['edit_category']))) {
    $category_name = sanitize_input($_POST['name']);
    // Slug field removed from form and logic

    if (empty($category_name)) {
        $_SESSION['message'] = "Category name cannot be empty.";
        $_SESSION['message_type'] = "error";
    } else {
        if (isset($_POST['add_category'])) {
            // Updated INSERT query to remove slug
            $insert_query = "INSERT INTO categories (name) VALUES (?)";
            $stmt = $conn->prepare($insert_query);
            $stmt->bind_param("s", $category_name); // Only one 's' for name
            if ($stmt->execute()) {
                $_SESSION['message'] = "Category added successfully!";
                $_SESSION['message_type'] = "success";
            } else {
                $_SESSION['message'] = "Error adding category: " . htmlspecialchars($conn->error);
                $_SESSION['message_type'] = "error";
            }
            $stmt->close();
        } elseif (isset($_POST['edit_category'])) {
            $category_id = (int)$_POST['category_id'];
            // Updated UPDATE query to remove slug
            $update_query = "UPDATE categories SET name = ? WHERE id = ?";
            $stmt = $conn->prepare($update_query);
            $stmt->bind_param("si", $category_name, $category_id); // 's' for name, 'i' for id
            if ($stmt->execute()) {
                $_SESSION['message'] = "Category updated successfully!";
                $_SESSION['message_type'] = "success";
            } else {
                $_SESSION['message'] = "Error updating category: " . htmlspecialchars($conn->error);
                $_SESSION['message_type'] = "error";
            }
            $stmt->close();
        }
    }
    header("Location: manage_categories.php");
    exit();
}

// Handle delete action
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $category_id_to_delete = (int)$_GET['delete'];

    // Before deleting a category, reassign posts in this category to NULL
    // This ensures data integrity by not deleting posts associated with the category.
    $update_posts_query = "UPDATE posts SET category_id = NULL WHERE category_id = ?";
    $update_posts_stmt = $conn->prepare($update_posts_query);
    $update_posts_stmt->bind_param("i", $category_id_to_delete);
    $update_posts_stmt->execute();
    $update_posts_stmt->close();

    $delete_category_query = "DELETE FROM categories WHERE id = ?";
    $delete_category_stmt = $conn->prepare($delete_category_query);
    $delete_category_stmt->bind_param("i", $category_id_to_delete);
    if ($delete_category_stmt->execute()) {
        $_SESSION['message'] = "Category deleted successfully!";
        $_SESSION['message_type'] = "success";
    } else {
        $_SESSION['message'] = "Error deleting category: " . htmlspecialchars($conn->error);
        $_SESSION['message_type'] = "error";
    }
    $delete_category_stmt->close();
    header("Location: manage_categories.php");
    exit();
}

// Pagination for Categories
$categories_per_page = 10;
$page_categories = isset($_GET['page_categories']) ? (int)$_GET['page_categories'] : 1;
$offset_categories = ($page_categories - 1) * $categories_per_page;

// Get total count of categories
$total_categories_query = "SELECT COUNT(*) as total FROM categories";
$total_categories_result = $conn->query($total_categories_query);
$total_categories = $total_categories_result->fetch_assoc()['total'];
$total_pages_categories = ceil($total_categories / $categories_per_page);

// Fetch categories for display, including a count of associated posts
$categories_query = "SELECT c.*, COUNT(p.id) AS post_count
                     FROM categories c
                     LEFT JOIN posts p ON c.id = p.category_id
                     GROUP BY c.id
                     ORDER BY c.name ASC
                     LIMIT ? OFFSET ?";
$categories_stmt = $conn->prepare($categories_query);
$categories_stmt->bind_param("ii", $categories_per_page, $offset_categories);
$categories_stmt->execute();
$categories_result = $categories_stmt->get_result();

// Check if the query failed and display the specific MySQL error
if (!$categories_result) {
    echo "<div class='alert alert-danger'>Error fetching categories: " . htmlspecialchars($conn->error) . "</div>";
    // Exit or handle gracefully if the query fails, to prevent further errors
}


// Fetch category to edit if 'edit' parameter is set
$edit_category = null;
if (isset($_GET['edit']) && is_numeric($_GET['edit'])) {
    $edit_id = (int)$_GET['edit'];
    // Adjusted query to fetch only name for edit form
    $edit_query = "SELECT id, name FROM categories WHERE id = ?";
    $edit_stmt = $conn->prepare($edit_query);
    $edit_stmt->bind_param("i", $edit_id);
    $edit_stmt->execute();
    $edit_result = $edit_stmt->get_result();
    if ($edit_result->num_rows > 0) {
        $edit_category = $edit_result->fetch_assoc();
    }
    $edit_stmt->close();
}

?>
<!-- Floating Back to Dashboard Button -->
<a href="admin_dashboard.php" class="floating-btn" title="Back to Dashboard">
    <i class="fas fa-arrow-left"></i>
</a>
<style>
.floating-btn {
    position: fixed;
    bottom: 32px;
    right: 32px;
    background: var(--primary-color);
    color: #fff;
    border-radius: 50%;
    width: 56px;
    height: 56px;
    display: flex;
    align-items: center;
    justify-content: center;
    box-shadow: var(--shadow-medium);
    font-size: 1.5rem;
    z-index: 2000;
    transition: background 0.2s, box-shadow 0.2s;
    text-decoration: none;
    border: none;
    outline: none;
}
.floating-btn:hover,
.floating-btn:focus {
    background: var(--primary-dark);
    box-shadow: var(--shadow-hover);
    color: #fff;
}
</style>
<div class="container">
    <?php include 'admin_search.php';?>
</div>
<?php
// If a search was performed and a category or tag edit is requested via search, show the edit form at the top
if (!empty($search_query)) {
    if (isset($_GET['edit']) && is_numeric($_GET['edit'])) {
        // Category edit via search result
        echo '<div class="alert alert-info mb-4"><i class="fas fa-edit"></i> Editing category found from search: <strong>' . htmlspecialchars($edit_category['name'] ?? '') . '</strong></div>';
    }
    if (isset($_GET['edit_tag']) && is_numeric($_GET['edit_tag'])) {
        // Tag edit via search result
        echo '<div class="alert alert-info mb-4"><i class="fas fa-edit"></i> Editing tag found from search: <strong>' . htmlspecialchars($edit_tag['name'] ?? '') . '</strong></div>';
    }
}
?>
<div class="container">
    <div class="admin-header mb-4">
        <h1><i class="fas fa-folder"></i> Manage Categories And Tags</h1>
        <p class="text-muted">Add, edit, or delete post categories.</p>
    </div>
    
    <div class="card mb-5">
        <div class="card-header mb-4">
            <h3><i class="fas fa-folder"></i> Manage Categories</h3>
            <p class="text-muted">Add, edit, or delete post categories.</p>
        </div>
    <div class="card-body">

    <?php if (isset($_SESSION['message'])): ?>
        <div class="alert alert-<?php echo $_SESSION['message_type']; ?>">
            <?php echo $_SESSION['message']; ?>
        </div>
        <?php
        unset($_SESSION['message']);
        unset($_SESSION['message_type']);
        ?>
    <?php endif; ?>

    <div class="card mb-4">
        <div class="card-header">
            <h3><?php echo $edit_category ? 'Edit Category' : 'Add New Category'; ?></h3>
        </div>
        <div class="card-body">
            <form action="manage_categories.php" method="POST">
                <?php if ($edit_category): ?>
                    <input type="hidden" name="category_id" value="<?php echo htmlspecialchars($edit_category['id']); ?>">
                    <input type="hidden" name="edit_category" value="1">
                <?php else: ?>
                    <input type="hidden" name="add_category" value="1">
                <?php endif; ?>

                <div class="form-group">
                    <label for="name" class="form-label">Category Name</label>
                    <input type="text" id="name" name="name" class="form-input" value="<?php echo htmlspecialchars($edit_category['name'] ?? ''); ?>" required>
                </div>
                <!-- Removed slug input field -->
                <button type="submit" class="btn btn-primary">
                    <i class="fas <?php echo $edit_category ? 'fa-save' : 'fa-plus'; ?>"></i> <?php echo $edit_category ? 'Update Category' : 'Add Category'; ?>
                </button>
                <?php if ($edit_category): ?>
                    <a href="manage_categories.php" class="btn btn-secondary">Cancel Edit</a>
                <?php endif; ?>
            </form>
        </div>
    </div>

    <div class="card mb-4">
        <div class="card-header">
            <h3>Existing Categories</h3>
        </div>
        <div class="card-body">
            <?php if ($categories_result && $categories_result->num_rows > 0): ?>
                <div class="table-responsive">
                    <table class="admin-table">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Name</th>
                                <th>Number of Posts</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($category = $categories_result->fetch_assoc()): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($category['id']); ?></td>
                                    <td><?php echo htmlspecialchars($category['name']); ?></td>
                                    
                                    <td><?php echo htmlspecialchars($category['post_count'] ?? 0); ?></td> 
                                    <td class="actions">
                                        <a href="manage_categories.php?edit=<?php echo $category['id']; ?>" class="btn btn-sm btn-info" title="Edit"><i class="fas fa-edit"></i></a>
                                        <a href="manage_categories.php?delete=<?php echo $category['id']; ?>" class="btn btn-sm btn-danger" title="Delete" onclick="return confirm('Are you sure you want to delete this category? Posts assigned to this category will become un-categorized.');"><i class="fas fa-trash"></i></a>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
                <?php
                // Pagination for Categories
                $page = $page_categories;
                $total_pages = $total_pages_categories;
                $base_url = 'manage_categories.php';
                $page_param_name = 'page_categories'; // Explicitly set page parameter name for categories
                include 'pagination_snippet.php'; // Include the pagination snippet
                ?>
            <?php else: ?>
                <p class="text-muted">No categories found. Add one above!</p>
            <?php endif; ?>
        </div>
        </div>

        </div>

    </div>
    <!-- Tag Management Section -->
        <div class="card mt-5">
            <div class="card-header">
                <h3><i class="fas fa-tags"></i> Manage Tags</h3>
                <p class="text-muted">Add, edit, or delete post tags.</p>
            </div>
            <div class="card-body">
                <?php
                // Handle add/edit tag
                if ($_SERVER['REQUEST_METHOD'] == 'POST' && (isset($_POST['add_tag']) || isset($_POST['edit_tag']))) {
                    $tag_name = sanitize_input($_POST['tag_name']);
                    if (empty($tag_name)) {
                        $_SESSION['tag_message'] = "Tag name cannot be empty.";
                        $_SESSION['tag_message_type'] = "error";
                    } else {
                        if (isset($_POST['add_tag'])) {
                            $insert_tag_query = "INSERT INTO tags (name) VALUES (?)";
                            $stmt = $conn->prepare($insert_tag_query);
                            $stmt->bind_param("s", $tag_name);
                            if ($stmt->execute()) {
                                $_SESSION['tag_message'] = "Tag added successfully!";
                                $_SESSION['tag_message_type'] = "success";
                            } else {
                                $_SESSION['tag_message'] = "Error adding tag: " . htmlspecialchars($conn->error);
                                $_SESSION['tag_message_type'] = "error";
                            }
                            $stmt->close();
                        } elseif (isset($_POST['edit_tag'])) {
                            $tag_id = (int)$_POST['tag_id'];
                            $update_tag_query = "UPDATE tags SET name = ? WHERE id = ?";
                            $stmt = $conn->prepare($update_tag_query);
                            $stmt->bind_param("si", $tag_name, $tag_id);
                            if ($stmt->execute()) {
                                $_SESSION['tag_message'] = "Tag updated successfully!";
                                $_SESSION['tag_message_type'] = "success";
                            } else {
                                $_SESSION['tag_message'] = "Error updating tag: " . htmlspecialchars($conn->error);
                                $_SESSION['tag_message_type'] = "error";
                            }
                            $stmt->close();
                        }
                    }
                    header("Location: manage_categories.php#tags");
                    exit();
                }

                // Handle delete tag
                if (isset($_GET['delete_tag']) && is_numeric($_GET['delete_tag'])) {
                    $tag_id_to_delete = (int)$_GET['delete_tag'];
                    // Remove tag associations first
                    $delete_post_tags_query = "DELETE FROM post_tags WHERE tag_id = ?";
                    $stmt = $conn->prepare($delete_post_tags_query);
                    $stmt->bind_param("i", $tag_id_to_delete);
                    $stmt->execute();
                    $stmt->close();

                    $delete_tag_query = "DELETE FROM tags WHERE id = ?";
                    $stmt = $conn->prepare($delete_tag_query);
                    $stmt->bind_param("i", $tag_id_to_delete);
                    if ($stmt->execute()) {
                        $_SESSION['tag_message'] = "Tag deleted successfully!";
                        $_SESSION['tag_message_type'] = "success";
                    } else {
                        $_SESSION['tag_message'] = "Error deleting tag: " . htmlspecialchars($conn->error);
                        $_SESSION['tag_message_type'] = "error";
                    }
                    $stmt->close();
                    header("Location: manage_categories.php#tags");
                    exit();
                }

                // Pagination for Tags
                $tags_per_page = 10;
                $page_tags = isset($_GET['page_tags']) ? (int)$_GET['page_tags'] : 1;
                $offset_tags = ($page_tags - 1) * $tags_per_page;

                // Get total count of tags
                $total_tags_query = "SELECT COUNT(*) as total FROM tags";
                $total_tags_result = $conn->query($total_tags_query);
                $total_tags = $total_tags_result->fetch_assoc()['total'];
                $total_pages_tags = ceil($total_tags / $tags_per_page);

                // Fetch tags for display, including a count of associated posts
                $tags_query = "SELECT t.*, COUNT(pt.post_id) AS post_count
                               FROM tags t
                               LEFT JOIN post_tags pt ON t.id = pt.tag_id
                               GROUP BY t.id
                               ORDER BY t.name ASC
                               LIMIT ? OFFSET ?";
                $tags_stmt = $conn->prepare($tags_query);
                $tags_stmt->bind_param("ii", $tags_per_page, $offset_tags);
                $tags_stmt->execute();
                $tags_result = $tags_stmt->get_result();

                // Fetch tag to edit if 'edit_tag' parameter is set
                $edit_tag = null;
                if (isset($_GET['edit_tag']) && is_numeric($_GET['edit_tag'])) {
                    $edit_tag_id = (int)$_GET['edit_tag'];
                    $edit_tag_query = "SELECT id, name FROM tags WHERE id = ?";
                    $stmt = $conn->prepare($edit_tag_query);
                    $stmt->bind_param("i", $edit_tag_id);
                    $stmt->execute();
                    $result = $stmt->get_result();
                    if ($result->num_rows > 0) {
                        $edit_tag = $result->fetch_assoc();
                    }
                    $stmt->close();
                }
                ?>

                <?php if (isset($_SESSION['tag_message'])): ?>
                    <div class="alert alert-<?php echo $_SESSION['tag_message_type']; ?>">
                        <?php echo $_SESSION['tag_message']; ?>
                    </div>
                    <?php
                    unset($_SESSION['tag_message']);
                    unset($_SESSION['tag_message_type']);
                    ?>
                <?php endif; ?>

                <div class="card mb-4">
                    <div class="card-header">
                        <h4><?php echo $edit_tag ? 'Edit Tag' : 'Add New Tag'; ?></h4>
                    </div>
                    <div class="card-body">
                        <form action="manage_categories.php#tags" method="POST">
                            <?php if ($edit_tag): ?>
                                <input type="hidden" name="tag_id" value="<?php echo htmlspecialchars($edit_tag['id']); ?>">
                                <input type="hidden" name="edit_tag" value="1">
                            <?php else: ?>
                                <input type="hidden" name="add_tag" value="1">
                            <?php endif; ?>
                            <div class="form-group">
                                <label for="tag_name" class="form-label">Tag Name</label>
                                <input type="text" id="tag_name" name="tag_name" class="form-input" value="<?php echo htmlspecialchars($edit_tag['name'] ?? ''); ?>" required>
                            </div>
                            <button type="submit" class="btn btn-primary">
                                <i class="fas <?php echo $edit_tag ? 'fa-save' : 'fa-plus'; ?>"></i> <?php echo $edit_tag ? 'Update Tag' : 'Add Tag'; ?>
                            </button>
                            <?php if ($edit_tag): ?>
                                <a href="manage_categories.php#tags" class="btn btn-secondary">Cancel Edit</a>
                            <?php endif; ?>
                        </form>
                    </div>
                </div>

                <div class="card">
                    <div class="card-header">
                        <h4>Existing Tags</h4>
                    </div>
                    <div class="card-body">
                        <?php if ($tags_result && $tags_result->num_rows > 0): ?>
                            <div class="table-responsive">
                                <table class="admin-table">
                                    <thead>
                                        <tr>
                                            <th>ID</th>
                                            <th>Name</th>
                                            <th>Number of Posts</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php while ($tag = $tags_result->fetch_assoc()): ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($tag['id']); ?></td>
                                                <td><?php echo htmlspecialchars($tag['name']); ?></td>
                                                <td><?php echo htmlspecialchars($tag['post_count'] ?? 0); ?></td>
                                                <td class="actions">
                                                    <a href="manage_categories.php?edit_tag=<?php echo $tag['id']; ?>#tags" class="btn btn-sm btn-info" title="Edit"><i class="fas fa-edit"></i></a>
                                                    <a href="manage_categories.php?delete_tag=<?php echo $tag['id']; ?>#tags" class="btn btn-sm btn-danger" title="Delete" onclick="return confirm('Are you sure you want to delete this tag? This will remove the tag from all posts.');"><i class="fas fa-trash"></i></a>
                                                </td>
                                            </tr>
                                        <?php endwhile; ?>
                                    </tbody>
                                </table>
                            </div>
                            <?php
                            // Pagination for Tags
                            $page = $page_tags;
                            $total_pages = $total_pages_tags;
                            $base_url = 'manage_categories.php#tags'; // Anchor to tags section
                            $page_param_name = 'page_tags'; // Use specific param name for tags pagination
                            include 'pagination_snippet.php'; // Include the pagination snippet
                            ?>
                        <?php else: ?>
                            <p class="text-muted">No tags found. Add one above!</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
</div>

<style>
    /* Custom Properties (CSS Variables) for Light Theme */
    :root {
        --primary-color: #3498db;
        --primary-dark: #2980b9;
        --secondary-color: #6c757d;
        --accent-color: #e74c3c;
        --text-color: #333;
        --heading-color: #2c3e50;
        --background-light: #f8f9fa;
        --background-white: #fff;
        --border-color: #e9ecef;
        --shadow-light: 0 2px 10px rgba(0,0,0,0.08);
        --shadow-medium: 0 4px 15px rgba(0,0,0,0.1);
        --shadow-hover: 0 8px 25px rgba(0,0,0,0.15);
    }

    /* Dark Theme Variables */
    :root[data-theme="dark"] {
        --primary-color: #3498db;
        --primary-dark: #2980b9;
        --secondary-color: #95a5a6;
        --accent-color: #e74c3c;
        --text-color: #ecf0f1;
        --heading-color: #ffffff;
        --background-light: #34495e;
        --background-white: #2c3e50;
        --border-color: #4b6584;
        --shadow-light: 0 2px 10px rgba(0,0,0,0.2);
        --shadow-medium: 0 4px 15px rgba(0,0,0,0.3);
        --shadow-hover: 0 8px 25px rgba(0,0,0,0.4);
    }

    /* Re-using admin-table styles from manage_posts.php for consistency */
    .admin-table {
        width: 100%;
        border-collapse: collapse;
        margin-top: 1.5rem;
        background: var(--background-white);
        color: var(--text-color);
    }

    .admin-table th,
    .admin-table td {
        padding: 12px 15px;
        text-align: left;
        border-bottom: 1px solid var(--border-color);
    }

    .admin-table th {
        background-color: var(--background-light);
        color: var(--heading-color);
        font-weight: 600;
        text-transform: uppercase;
        font-size: 0.85rem;
    }

    .admin-table tbody tr:hover {
        background-color: var(--background-light);
    }

    .admin-table .actions .btn {
        margin-right: 5px;
    }

    /* Form styling */
    .form-group {
        margin-bottom: 1rem;
    }
    .form-label {
        display: block;
        margin-bottom: 0.5rem;
        font-weight: 500;
        color: var(--heading-color);
    }
    .form-input {
        width: 100%;
        padding: 10px 15px;
        border: 1px solid var(--border-color);
        border-radius: 8px;
        box-sizing: border-box;
        font-size: 1rem;
        background: var(--background-white);
        color: var(--text-color);
    }
    .form-input:focus {
        border-color: var(--primary-color);
        outline: 0;
        box-shadow: 0 0 0 0.2rem rgba(52, 152, 219, 0.25);
    }
    .text-muted {
        font-size: 0.875em;
        color: var(--secondary-color);
    }

    /* Responsive Table */
    @media (max-width: 768px) {
        .admin-table thead {
            display: none;
        }

        .admin-table,
        .admin-table tbody,
        .admin-table tr,
        .admin-table td {
            display: block;
            width: 100%;
        }

        .admin-table tr {
            margin-bottom: 1rem;
            border: 1px solid var(--border-color);
            border-radius: 8px;
            overflow: hidden;
            background-color: var(--background-white);
            box-shadow: var(--shadow-light);
        }

        .admin-table td {
            text-align: right;
            padding-left: 50%;
            position: relative;
        }

        .admin-table td::before {
            content: attr(data-label);
            position: absolute;
            left: 15px;
            width: calc(50% - 30px);
            padding-right: 10px;
            white-space: nowrap;
            text-align: left;
            font-weight: 600;
            color: var(--heading-color);
        }

        .admin-table td:nth-of-type(1):before { content: "ID"; }
        .admin-table td:nth-of-type(2):before { content: "Name"; }
        .admin-table td:nth-of-type(3):before { content: "Number of Posts"; }
        .admin-table td:nth-of-type(4):before { content: "Actions"; }
        
        .admin-table .actions {
            display: flex;
            margin-top: 10px;
        }
</style>

<?php ob_end_flush(); ?>
<?php include 'includes/footer.php'; ?>
