<?php
session_start();
require_once 'db.php';

if (isset($_SESSION['user_id'])) {
    // Redirect based on user type
    if ($_SESSION['user_type'] == 'admin') {
        header("Location: admin_dashboard.php");
    } else {
        header("Location: dashboard.php");
    }
    exit();
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['login'])) {
        $email = mysqli_real_escape_string($conn, $_POST['email']);
        $password = $_POST['password'];
        
        $sql = "SELECT * FROM users WHERE email = '$email'";
        $result = mysqli_query($conn, $sql);
        
        if (mysqli_num_rows($result) == 1) {
            $user = mysqli_fetch_assoc($result);
            
            if (password_verify($password, $user['password'])) {
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['user_name'] = $user['name'];
                $_SESSION['user_email'] = $user['email'];
                $_SESSION['user_type'] = $user['user_type'];
                $_SESSION['ward_number'] = $user['ward_number'];
                
                // Redirect based on user type
                if ($user['user_type'] == 'admin') {
                    header("Location: admin_dashboard.php");
                } else {
                    header("Location: dashboard.php");
                }
                exit();
            } else {
                $error = "Incorrect password!";
            }
        } else {
            $error = "Email not found!";
        }
    }
    
    if (isset($_POST['forgot_password'])) {
        $email = mysqli_real_escape_string($conn, $_POST['email_forgot']);
        
        $sql = "SELECT id, name FROM users WHERE email = '$email'";
        $result = mysqli_query($conn, $sql);
        
        if (mysqli_num_rows($result) == 1) {
            $token = bin2hex(random_bytes(32));
            $sql_update = "UPDATE users SET reset_token = '$token' WHERE email = '$email'";
            mysqli_query($conn, $sql_update);
            
            $reset_link = "reset_password.php?email=" . urlencode($email) . "&token=" . $token;
            $success = "Password reset link generated.<br>
                      <a href='$reset_link' style='font-size: 12px;'>Click here to reset password</a>";
        } else {
            $error = "Email not found!";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Municipality System</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="auth_style.css">
</head>
<body>
    <body class="login-page">
    <div class="box">
        <!-- Logo -->
        <div class="logo">
            <div class="logo-icon">
                <i class="fas fa-landmark"></i>
            </div>
            <h1 class="blue">नगरपालिका</h1>
            <p>Municipality Complaint System</p>
        </div>

        <!-- Messages -->
        <?php if($error): ?>
            <div class="message error">
                <i class="fas fa-exclamation-circle"></i>
                <?php echo $error; ?>
            </div>
        <?php endif; ?>

        <?php if($success): ?>
            <div class="message success">
                <i class="fas fa-check-circle"></i>
                <?php echo $success; ?>
            </div>
        <?php endif; ?>

        <!-- Tabs -->
        <div class="tabs">
            <button class="tab active" onclick="showTab('login')">Login</button>
            <button class="tab" onclick="showTab('forgot')">Forgot Password</button>
        </div>

        <!-- Login Form -->
        <div id="login" class="tab-content active">
            <form method="POST" action="">
                <div class="form-group">
                    <label class="form-label">Email Address</label>
                    <input type="email" name="email" class="form-input" required 
                           placeholder="Enter your email">
                </div>

                <div class="form-group">
                    <label class="form-label">Password</label>
                    <input type="password" name="password" class="form-input" required 
                           placeholder="Enter password">
                </div>

                <button type="submit" name="login" class="btn">
                    <i class="fas fa-sign-in-alt"></i>
                    Login
                </button>
            </form>
        </div>

        <!-- Forgot Password Form -->
        <div id="forgot" class="tab-content">
            <form method="POST" action="">
                <div class="form-group">
                    <label class="form-label">Enter your email</label>
                    <input type="email" name="email_forgot" class="form-input" required 
                           placeholder="Your registered email">
                </div>

                <p style="color: #666; font-size: 14px; margin-bottom: 15px;">
                    <i class="fas fa-info-circle blue"></i>
                    We will send a password reset link.
                </p>

                <button type="submit" name="forgot_password" class="btn">
                    <i class="fas fa-paper-plane"></i>
                    Send Reset Link
                </button>
            </form>
        </div>

        <!-- Links -->
        <div class="links">
            <a href="signup.php" class="link">
                <i class="fas fa-user-plus blue"></i> Sign Up
            </a>
            <a href="index.php" class="link">
                <i class="fas fa-home blue"></i> Home
            </a>
        </div>
    </div>

    <script>
        function showTab(tabName) {
            // Hide all tabs
            document.querySelectorAll('.tab-content').forEach(tab => {
                tab.classList.remove('active');
            });
            
            // Remove active from all tab buttons
            document.querySelectorAll('.tab').forEach(tab => {
                tab.classList.remove('active');
            });
            
            // Show selected tab
            document.getElementById(tabName).classList.add('active');
            
            // Activate button
            event.target.classList.add('active');
        }
    </script>
</body>
</html>