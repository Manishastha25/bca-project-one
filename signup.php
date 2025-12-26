<?php
session_start();
require_once 'db.php';

if (isset($_SESSION['user_id'])) {
    header("Location: dashboard.php");
    exit();
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $name = mysqli_real_escape_string($conn, $_POST['name']);
    $email = mysqli_real_escape_string($conn, $_POST['email']);
    $phone = mysqli_real_escape_string($conn, $_POST['phone']);
    $password = $_POST['password'];
    $confirm = $_POST['confirm_password'];
    $ward = mysqli_real_escape_string($conn, $_POST['ward_number']);
    $address = mysqli_real_escape_string($conn, $_POST['address']);
    
    if ($password !== $confirm) {
        $error = "Passwords do not match!";
    } else {
        $check = "SELECT id FROM users WHERE email = '$email'";
        $result = mysqli_query($conn, $check);
        
        if (mysqli_num_rows($result) > 0) {
            $error = "Email already registered!";
        } else {
            $hash = password_hash($password, PASSWORD_DEFAULT);
            
            $sql = "INSERT INTO users (name, email, phone, password, ward_number, address) 
                    VALUES ('$name', '$email', '$phone', '$hash', '$ward', '$address')";
            
            if (mysqli_query($conn, $sql)) {
                $success = "Registration successful! You can now login.";
                header("refresh:2;url=login.php");
            } else {
                $error = "Error: " . mysqli_error($conn);
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sign Up - Municipality System</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="auth_style.css">
</head>
<body>
    <body class="login-page">
    <div class="box">
        <!-- Logo -->
        <div class="logo">
            <div class="logo-icon">
                <i class="fas fa-user-plus"></i>
            </div>
            <h1 class="blue">Create Account</h1>
            <p>Join our complaint system</p>
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

        <!-- Registration Form -->
        <form method="POST" action="">
            <div class="form-group">
                <label class="form-label">Full Name *</label>
                <input type="text" name="name" class="form-input" required 
                       placeholder="Enter your full name">
            </div>

            <div class="form-group">
                <label class="form-label">Email Address *</label>
                <input type="email" name="email" class="form-input" required 
                       placeholder="Enter your email">
            </div>

            <div class="form-group">
                <label class="form-label">Phone Number</label>
                <input type="tel" name="phone" class="form-input" 
                       placeholder="Phone number">
            </div>

            <div class="form-group">
                <label class="form-label">Ward Number *</label>
                <select name="ward_number" class="form-select" required>
                    <option value="">Select Ward</option>
                    <?php for($i=1; $i<=3; $i++): ?>
                        <option value="<?php echo $i; ?>">Ward <?php echo $i; ?></option>
                    <?php endfor; ?>
                </select>
            </div>

            <div class="form-group">
                <label class="form-label">Address</label>
                <textarea name="address" class="form-textarea" 
                          placeholder="Enter your address"></textarea>
            </div>

            <div class="form-group">
                <label class="form-label">Password *</label>
                <input type="password" name="password" class="form-input" required 
                       placeholder="Create password (min 6 characters)">
            </div>

            <div class="form-group">
                <label class="form-label">Confirm Password *</label>
                <input type="password" name="confirm_password" class="form-input" required 
                       placeholder="Re-enter password">
            </div>

            <button type="submit" class="btn">
                <i class="fas fa-user-plus"></i>
                Create Account
            </button>
        </form>

        <!-- Links -->
        <div class="links">
            <a href="login.php" class="link">
                <i class="fas fa-sign-in-alt blue"></i> Already have account? Login
            </a>
            <a href="index.php" class="link">
                <i class="fas fa-home blue"></i> Home
            </a>
        </div>
    </div>
</body>
</html>