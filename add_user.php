<?php
session_start();
require_once 'db.php';

// Check if user is admin
if (!isset($_SESSION['user_type']) || $_SESSION['user_type'] != 'admin') {
    header("Location: login.php");
    exit();
}

$message = "";
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Get form data
    $name = mysqli_real_escape_string($conn, $_POST['name']);
    $email = mysqli_real_escape_string($conn, $_POST['email']);
    $password = mysqli_real_escape_string($conn, $_POST['password']);
    $phone = isset($_POST['phone']) ? mysqli_real_escape_string($conn, $_POST['phone']) : '';
    $ward_number = isset($_POST['ward_number']) ? mysqli_real_escape_string($conn, $_POST['ward_number']) : '1';
    $address = isset($_POST['address']) ? mysqli_real_escape_string($conn, $_POST['address']) : '';
    $user_type = isset($_POST['user_type']) ? mysqli_real_escape_string($conn, $_POST['user_type']) : 'citizen';
    
    // Validate required fields
    if (empty($name) || empty($email) || empty($password)) {
        $message = "<div class='message error'><i class='fas fa-exclamation-circle'></i> Name, Email and Password are required!</div>";
    } else {
        // Check if email already exists
        $check_sql = "SELECT id FROM users WHERE email = '$email'";
        $check_result = mysqli_query($conn, $check_sql);
        
        if (!$check_result) {
            $message = "<div class='message error'><i class='fas fa-exclamation-circle'></i> Database error: " . mysqli_error($conn) . "</div>";
        } elseif (mysqli_num_rows($check_result) > 0) {
            $message = "<div class='message error'><i class='fas fa-exclamation-circle'></i> Email already exists!</div>";
        } else {
            // Hash the password
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            
            // Insert user
            $sql = "INSERT INTO users (name, email, password, phone, ward_number, address, user_type) 
                    VALUES ('$name', '$email', '$hashed_password', '$phone', '$ward_number', '$address', '$user_type')";
            
            if (mysqli_query($conn, $sql)) {
                $message = "<div class='message success'><i class='fas fa-check-circle'></i> User added successfully!</div>";
                // Clear form after success
                $_POST = array();
            } else {
                $message = "<div class='message error'><i class='fas fa-exclamation-circle'></i> Error: " . mysqli_error($conn) . "</div>";
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
    <title>Add User - Municipality System</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="auth_style.css">
    <style>
        /* Page specific styles that extend auth_style.css */
        .add-user-page {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            background: linear-gradient(135deg, #003366, #001a33, #8b0000);
            padding: 20px;
        }
        
        .add-user-box {
            background: white;
            border-radius: 10px;
            padding: 40px;
            width: 100%;
            max-width: 700px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.3);
            margin: 20px auto;
        }
        
        .page-header {
            text-align: center;
            margin-bottom: 30px;
        }
        
        .page-header h1 {
            color: #003366;
            font-size: 28px;
            margin-bottom: 5px;
        }
        
        .page-header p {
            color: #666;
            font-size: 14px;
        }
        
        .form-row {
            display: flex;
            gap: 20px;
            margin-bottom: 20px;
        }
        
        .form-col {
            flex: 1;
        }
        
        @media (max-width: 768px) {
            .form-row {
                flex-direction: column;
                gap: 0;
            }
            
            .add-user-box {
                padding: 30px 20px;
                max-width: 95%;
            }
        }
        
        @media (max-width: 480px) {
            .add-user-box {
                padding: 20px 15px;
            }
            
            .page-header h1 {
                font-size: 24px;
            }
        }
        
        .required::after {
            content: " *";
            color: #DC143C;
        }
        
        .form-text {
            font-size: 12px;
            color: #666;
            margin-top: 5px;
            display: block;
        }
        
        .action-buttons {
            display: flex;
            gap: 15px;
            margin-top: 30px;
        }
        
        .action-buttons .btn {
            flex: 1;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
        }
        
        .btn-secondary {
            background: linear-gradient(to right, #666, #888);
        }
        
        .admin-info {
            margin-top: 20px;
            padding-top: 20px;
            border-top: 1px solid #eee;
            font-size: 12px;
            color: #666;
            text-align: center;
        }
    </style>
</head>
<body class="add-user-page">
    <div class="add-user-box">
        <!-- Page Header -->
        <div class="page-header">
            <div class="logo-icon" style="margin: 0 auto 15px; width: 60px; height: 60px;">
                <i class="fas fa-user-plus"></i>
            </div>
            <h1 class="blue">Add New User</h1>
            <p>Municipality Complaint System - Admin Panel</p>
        </div>

        <!-- Messages -->
        <?php echo $message; ?>

        <!-- Add User Form -->
        <form method="POST" action="">
            <div class="form-row">
                <div class="form-col">
                    <div class="form-group">
                        <label class="form-label required">Full Name</label>
                        <input type="text" name="name" class="form-input" required 
                               value="<?php echo isset($_POST['name']) ? htmlspecialchars($_POST['name']) : ''; ?>"
                               placeholder="Enter full name">
                    </div>
                </div>
                
                <div class="form-col">
                    <div class="form-group">
                        <label class="form-label required">Email Address</label>
                        <input type="email" name="email" class="form-input" required 
                               value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>"
                               placeholder="Enter email address">
                    </div>
                </div>
            </div>

            <div class="form-row">
                <div class="form-col">
                    <div class="form-group">
                        <label class="form-label required">Password</label>
                        <input type="password" name="password" class="form-input" required 
                               minlength="6" placeholder="Enter password (min 6 chars)">
                        <span class="form-text">Minimum 6 characters</span>
                    </div>
                </div>
                
                <div class="form-col">
                    <div class="form-group">
                        <label class="form-label">Phone Number</label>
                        <input type="text" name="phone" class="form-input" 
                               value="<?php echo isset($_POST['phone']) ? htmlspecialchars($_POST['phone']) : ''; ?>"
                               placeholder="Enter phone number">
                    </div>
                </div>
            </div>

            <div class="form-row">
                <div class="form-col">
                    <div class="form-group">
                        <label class="form-label">Ward Number</label>
                        <select name="ward_number" class="form-select">
                            <?php for($i = 1; $i <= 3; $i++): ?>
                                <option value="<?php echo $i; ?>" <?php echo (isset($_POST['ward_number']) && $_POST['ward_number'] == $i) ? 'selected' : ''; ?>>
                                    Ward <?php echo $i; ?>
                                </option>
                            <?php endfor; ?>
                        </select>
                    </div>
                </div>
                
                <div class="form-col">
                    <div class="form-group">
                        <label class="form-label">User Type</label>
                        <select name="user_type" class="form-select">
                            <option value="citizen" <?php echo (isset($_POST['user_type']) && $_POST['user_type'] == 'citizen') ? 'selected' : 'selected'; ?>>Citizen</option>
                            <option value="admin" <?php echo (isset($_POST['user_type']) && $_POST['user_type'] == 'admin') ? 'selected' : ''; ?>>Admin</option>
                        </select>
                    </div>
                </div>
            </div>

            <div class="form-group">
                <label class="form-label">Address</label>
                <textarea name="address" class="form-textarea" rows="3" 
                          placeholder="Enter address (optional)"><?php echo isset($_POST['address']) ? htmlspecialchars($_POST['address']) : ''; ?></textarea>
            </div>

            <!-- Action Buttons -->
            <div class="action-buttons">
                <button type="submit" class="btn">
                    <i class="fas fa-user-plus"></i>
                    Add User
                </button>
                
                <a href="admin_dashboard.php" class="btn btn-secondary" style="text-decoration: none; text-align: center;">
                    <i class="fas fa-arrow-left"></i>
                    Back to Dashboard
                </a>
            </div>
        </form>

        <div class="admin-info">
            <i class="fas fa-user-shield blue"></i> 
            Logged in as: <?php echo isset($_SESSION['user_name']) ? $_SESSION['user_name'] : 'Admin'; ?>
        </div>
    </div>
</body>
</html>