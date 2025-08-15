<?php
$page_title = "Register";
include 'includes/header.php';

$error_message = '';
$success_message = '';


if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = sanitize_input($_POST['username']);
    $email = sanitize_input($_POST['email']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    $gender = sanitize_input($_POST['gender'] ?? ''); // Fixed: Use null coalescing operator for safety
    $agree_terms = isset($_POST['agree_terms']); // New: Terms checkbox
    $agree_privacy = isset($_POST['agree_privacy']); // New: Privacy checkbox
    
    // Validation
    if (empty($username) || empty($email) || empty($password) || empty($confirm_password) || empty($gender)) {
        $error_message = "All fields are required, including gender.";
    } elseif (!validate_email($email)) {
        $error_message = "Please enter a valid email address.";
    } elseif (!validate_password($password)) {
        $error_message = "Password must be at least 8 characters long and contain at least one uppercase letter, one lowercase letter, and one number.";
    } elseif ($password !== $confirm_password) {
        $error_message = "Passwords do not match.";
    } elseif (!$agree_terms) { // Server-side validation for terms
        $error_message = "You must agree to the Terms and Conditions.";
    } elseif (!$agree_privacy) { // Server-side validation for privacy
        $error_message = "You must agree to the Privacy Policy.";
    } else {
        // Check if username or email already exists
        $check_query = "SELECT id FROM users WHERE username = ? OR email = ?";
        $check_stmt = $conn->prepare($check_query);
        $check_stmt->bind_param("ss", $username, $email);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();
        
        if ($check_result->num_rows > 0) {
            $error_message = "Username or email already exists.";
        } else {
            // Hash password and insert user, including gender
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            $insert_query = "INSERT INTO users (username, email, password, gender) VALUES (?, ?, ?, ?)";
            $insert_stmt = $conn->prepare($insert_query);
            $insert_stmt->bind_param("ssss", $username, $email, $hashed_password, $gender);
            
            if ($insert_stmt->execute()) {
                $success_message = "Registration successful! You can now log in.";
                // Clear post data after successful registration to prevent re-population
                $_POST = array(); 
            } else {
                $error_message = "Registration failed. Please try again. " . htmlspecialchars($conn->error); // Added DB error for debugging
            }
        }
    }
}

?>

<div class="container" >
    <div class="form-container">
        <div class ="form-card">
        <h2 class="text-center mb-3">Create Your Account</h2>
        <p class="text-center mb-4">Join our community and start sharing your stories</p>
        
        <?php if ($error_message): ?>
            <div class="alert alert-error"><?php echo $error_message; ?></div>
        <?php endif; ?>
        
        <?php if ($success_message): ?>
            <div class="alert alert-success"><?php echo $success_message; ?></div>
        <?php endif; ?>
        
        <form method="POST" data-validate>
            <div class="form-group">
                <label for="username" class="form-label">Username</label>
                <input type="text" id="username" name="username" class="form-input" required 
                       value="<?php echo isset($_POST['username']) ? htmlspecialchars($_POST['username']) : ''; ?>">
            </div>
            
            <div class="form-group">
                <label for="email" class="form-label">Email Address</label>
                <input type="email" id="email" name="email" class="form-input" required 
                       value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>">
            </div>

            <div class="form-group">
                <label class="form-label">Gender</label>
                <div class="form-radio-group">
                    <div class="form-radio">
                        <input type="radio" id="gender_male" name="gender" value="male" 
                               <?php echo (isset($_POST['gender']) && $_POST['gender'] === 'male') ? 'checked' : ''; ?> required>
                        <label for="gender_male">Male</label>
                    </div>
                    <div class="form-radio">
                        <input type="radio" id="gender_female" name="gender" value="female" 
                               <?php echo (isset($_POST['gender']) && $_POST['gender'] === 'female') ? 'checked' : ''; ?> required>
                        <label for="gender_female">Female</label>
                    </div>
                    <div class="form-radio">
                        <input type="radio" id="gender_other" name="gender" value="other" 
                               <?php echo (isset($_POST['gender']) && $_POST['gender'] === 'other') ? 'checked' : ''; ?> required>
                        <label for="gender_other">Other</label>
                    </div>
                </div>
            </div>
            
            <div class="form-group">
                <label for="password" class="form-label">Password</label>
                <input type="password" id="password" name="password" class="form-input" required>
                <small class="text-muted">At least 8 characters with uppercase, lowercase, and number</small>
            </div>
            
            <div class="form-group">
                <label for="confirm_password" class="form-label">Confirm Password</label>
                <input type="password" id="confirm_password" name="confirm_password" class="form-input" required>
            </div>
            
            
            
            <div class="form-group">
                <div class="form-radio">
                    <input type="checkbox" id="agree_terms" name="agree_terms" 
                           <?php echo isset($_POST['agree_terms']) ? 'checked' : ''; ?> disabled>
                    <label for="agree_terms">I agree to the <a style="color:aqua;" href="terms.php" target="_blank" id="terms_link">Terms and Conditions</a></label>
                </div>
            </div>
            
            <div class="form-group">
                <div class="form-radio">
                    <input type="checkbox" id="agree_privacy" name="agree_privacy" 
                           <?php echo isset($_POST['agree_privacy']) ? 'checked' : ''; ?> disabled>
                    <label for="agree_privacy">I agree to our <a style="color:aqua;" href="privacy.php" target="_blank" id="privacy_link">Privacy Policy</a></label>
                </div>
            </div>
            
            <button type="submit" class="btn btn-primary" style="width: 100%;">Create Account</button>
        </form>
        
        <div class="text-center mt-3">
            <p>Already have an account? <a style="color: aqua;" href="login.php">Sign in here</a></p>
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
<script>
document.addEventListener('DOMContentLoaded', function() {
    const termsLink = document.getElementById('terms_link');
    const privacyLink = document.getElementById('privacy_link');
    const agreeTermsCheckbox = document.getElementById('agree_terms');
    const agreePrivacyCheckbox = document.getElementById('agree_privacy');

    let termsLinkClicked = false;
    let privacyLinkClicked = false;

    termsLink.addEventListener('click', function() {
        termsLinkClicked = true;
        agreeTermsCheckbox.disabled = false;
    });

    privacyLink.addEventListener('click', function() {
        privacyLinkClicked = true;
        agreePrivacyCheckbox.disabled = false;
    });

    // Optional: If you want to re-disable if they uncheck after clicking,
    // though the requirement is just to click *before* checking.
    // For simplicity, we'll keep them enabled once clicked.
});
</script>


<?php include 'includes/footer.php'; ?>

