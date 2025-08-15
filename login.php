<?php
// All PHP logic, especially session_start() and header() redirects,
// MUST come before any HTML output or including header.php.

// Include necessary files. These should not output anything before their PHP tags.
require_once 'includes/db_connection.php';
require_once 'includes/functions.php';

// Ensure session is started. This is critical for $_SESSION variables.
// It should be here or in includes/header.php, but before any output.
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Redirect if already logged in. This should happen before any HTML is sent.
if (is_logged_in()) {
    if (isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'admin') {
        header("Location: admin_dashboard.php");
    } else {
        header("Location: dashboard.php");
    }
    exit(); // Always exit after a header redirect
}

$page_title = "Login"; // Set page title here, before including header.php
$error_message = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $email_or_username = sanitize_input($_POST['email_or_username']);
    $password = $_POST['password'];
    $remember_me = isset($_POST['remember_me']);
    
    if (empty($email_or_username) || empty($password)) {
        $error_message = "Please enter both email/username and password.";
    } else {
        // Check if user exists
        $login_query = "SELECT id, username, email, password, role FROM users WHERE email = ? OR username = ?";
        $login_stmt = $conn->prepare($login_query);
        $login_stmt->bind_param("ss", $email_or_username, $email_or_username);
        $login_stmt->execute();
        $result = $login_stmt->get_result();
        
        if ($result->num_rows == 1) {
            $user = $result->fetch_assoc();
            
            if (password_verify($password, $user['password'])) {
                // Login successful
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['user_role'] = $user['role'];
                
                // Handle remember me (optional - basic implementation)
                if ($remember_me) {
                    // Set cookie for 30 days. Adjust path '/' if your application is in a subfolder.
                    setcookie('remember_user', $user['id'], time() + (30 * 24 * 60 * 60), '/'); 
                }
                
                // Redirect based on role. These are header() calls that must happen early.
                if ($user['role'] === 'admin') {
                    header("Location: admin_dashboard.php");
                } else {
                    $redirect = isset($_GET['redirect']) ? $_GET['redirect'] : 'dashboard.php';
                    header("Location: " . $redirect);
                }
                exit(); // Always exit after a header redirect to prevent further script execution
            } else {
                $error_message = "Invalid password.";
            }
        } else {
            $error_message = "User not found.";
        }
    }
}

// Now that all potential header redirects are handled, include the header HTML.
include 'includes/header.php'; 
?>

<div class="container">
    <div class="form-container">
    <div class="form-card">
        <h2 class="text-center mb-3">Welcome Back</h2>
        <p class="text-center mb-4">Sign in to your account to continue</p>
        
        <?php if ($error_message): ?>
            <div class="alert alert-error"><?php echo htmlspecialchars($error_message); ?></div>
        <?php endif; ?>
        
        <form method="POST" data-validate>
            <div class="form-group">
                <label for="email_or_username" class="form-label">Email or Username</label>
                <input type="text" id="email_or_username" name="email_or_username" class="form-input" required 
                       value="<?php echo isset($_POST['email_or_username']) ? htmlspecialchars($_POST['email_or_username']) : ''; ?>">
            </div>
            
            <div class="form-group">
                <label for="password" class="form-label">Password</label>
                <input type="password" id="password" name="password" class="form-input" required>
            </div>
            
            <div class="form-group">
                <div class="form-radio">
                    <input type="checkbox" id="remember_me" name="remember_me" 
                           <?php echo isset($_POST['remember_me']) ? 'checked' : ''; ?>>
                    <label for="remember_me">Remember me</label>
                </div>
            </div>
            
            <button type="submit" class="btn btn-primary" style="width: 100%;">Sign In</button>
        </form>
        
        <div class="text-center mt-3">
            <p>Don't have an account? <a style="color: aqua;" href="register.php">Create one here</a></p>
            <p><a a style="color: aqua;" href="#" class="text-muted">Forgot your password?</a></p>
        </div>
    </div>
    </div>
</div>
<style>
    .main-content{
        background-image:
        linear-gradient(rgba(0, 0, 0, 0.29), rgba(0, 0, 0, 0.15)), /* Dark overlay */
        url('assets/images/herobg.png');
    background-size: cover;
    background-position: center;
    padding: 50px 0; /* Adjusted padding for better spacing */
    
    }
    .form-container {
    background-image:
        linear-gradient(rgba(0, 0, 0, 0.89), rgba(0, 0, 0, 0.4)), /* Dark overlay */
        url('assets/images/YouCut_20250706_104901376.gif');
    background-size: cover;
    background-position: center;
    backdrop-filter: blur(10px);
    color: white;
    }
    .form-card {
        background: rgba(0, 0, 0, 0.26); /* Semi-transparent white */
        padding: 20px;
        border-radius: 10px;
        box-shadow: 0 4px 10px rgba(0, 0, 0, 0.2);
    }
    
    .form-radio-group {
        display: flex;
        gap: 15px;
    }
    .form-radio {
        display: flex;
        align-items: center;
    }
    .form-radio input[type="radio"] {
        margin-right: 5px;
    }
    .form-label {
        color:white
    }
</style>

<?php include 'includes/footer.php'; ?>
