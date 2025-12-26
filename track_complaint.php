<?php
session_start();
require_once 'db.php';

// Redirect if not logged in (optional - remove if you want guests to track)
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$user_name = $_SESSION['user_name'];

// Initialize variables
$complaint = null;
$tracking_code = '';
$search_result = '';
$error = '';
$success = '';

// Handle search form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['search'])) {
    $tracking_code = mysqli_real_escape_string($conn, $_POST['tracking_code'] ?? '');
    
    if (!empty($tracking_code)) {
        // Search for complaint
        $query = "SELECT c.*, cat.category_name 
                 FROM complaints c 
                 LEFT JOIN categories cat ON c.category_id = cat.id 
                 WHERE c.tracking_code = '$tracking_code' 
                 AND c.user_id = '$user_id'";
        
        $result = mysqli_query($conn, $query);
        
        if ($result && mysqli_num_rows($result) > 0) {
            $complaint = mysqli_fetch_assoc($result);
            $success = "Complaint found!";
        } else {
            $error = "No complaint found with tracking code: $tracking_code";
        }
    } else {
        $error = "Please enter a tracking code!";
    }
}

// If tracking code is in URL
if (isset($_GET['tracking_code']) && empty($complaint)) {
    $tracking_code = mysqli_real_escape_string($conn, $_GET['tracking_code']);
    
    $query = "SELECT c.*, cat.category_name 
             FROM complaints c 
             LEFT JOIN categories cat ON c.category_id = cat.id 
             WHERE c.tracking_code = '$tracking_code' 
             AND c.user_id = '$user_id'";
    
    $result = mysqli_query($conn, $query);
    
    if ($result && mysqli_num_rows($result) > 0) {
        $complaint = mysqli_fetch_assoc($result);
        $success = "Complaint found!";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Track Complaint</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Poppins', sans-serif;
            background-color: #f5f5f5;
            color: #333;
        }
        
        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 20px;
        }
        
        /* Header Styles */
        .header {
            background: linear-gradient(to right, darkblue, red);
            color: white;
            padding: 15px 0;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .header-content {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
        }
        
        .logo {
            display: flex;
            align-items: center;
            gap: 15px;
        }
        
        .logo i {
            font-size: 28px;
            color: #ff6b6b;
        }
        
        .logo h1 {
            font-size: 24px;
            font-weight: 600;
        }
        
        .logo span {
            font-size: 12px;
            opacity: 0.8;
        }
        
        .nav ul {
            display: flex;
            list-style: none;
            gap: 20px;
        }
        
        .nav a {
            color: white;
            text-decoration: none;
            padding: 8px 15px;
            border-radius: 5px;
            transition: all 0.3s;
            font-weight: 500;
        }
        
        .nav a:hover {
            background: rgba(255, 255, 255, 0.2);
            transform: translateY(-2px);
        }
        
        .nav a.active {
            background: rgba(255, 255, 255, 0.3);
            font-weight: 600;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2);
        }
        
        .user-info {
            display: flex;
            align-items: center;
            gap: 15px;
        }
        
        .user-info span {
            font-weight: 500;
        }
        
        /* Buttons */
        .btn {
            display: inline-block;
            padding: 10px 20px;
            background: red;
            color: white;
            text-decoration: none;
            border-radius: 5px;
            border: none;
            cursor: pointer;
            font-weight: 500;
            transition: all 0.3s;
        }
        
        .btn:hover {
            background: darkred;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.2);
        }
        
        .btn-secondary {
            background: #6c757d;
        }
        
        .btn-secondary:hover {
            background: #5a6268;
        }
        
        .btn-small {
            padding: 6px 12px;
            font-size: 14px;
        }
        
        /* Main Content */
        .main-content {
            padding: 30px 0;
        }
        
        /* Welcome Section */
        .welcome-card {
            background: linear-gradient(135deg, darkblue, red);
            color: white;
            padding: 30px;
            border-radius: 10px;
            margin-bottom: 30px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
        
        .welcome-card h2 {
            font-size: 28px;
            margin-bottom: 10px;
        }
        
        .welcome-card p {
            margin-bottom: 20px;
            opacity: 0.9;
        }
        
        /* Search Box */
        .search-box {
            background: white;
            padding: 25px;
            border-radius: 8px;
            margin-bottom: 30px;
            box-shadow: 0 3px 10px rgba(0,0,0,0.1);
        }
        
        .search-box h3 {
            font-size: 20px;
            margin-bottom: 20px;
            color: darkblue;
            border-bottom: 2px solid red;
            padding-bottom: 10px;
        }
        
        .search-form {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
        }
        
        .search-input {
            flex: 1;
            padding: 12px;
            border: 2px solid #ddd;
            border-radius: 5px;
            font-family: 'Poppins', sans-serif;
            font-size: 16px;
        }
        
        .search-input:focus {
            outline: none;
            border-color: darkblue;
        }
        
        /* Alerts */
        .alert {
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 15px;
        }
        
        .alert-success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .alert-error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        
        .alert i {
            font-size: 20px;
        }
        
        /* Complaint Details */
        .complaint-card {
            background: white;
            padding: 25px;
            border-radius: 8px;
            margin-bottom: 30px;
            box-shadow: 0 3px 10px rgba(0,0,0,0.1);
        }
        
        .complaint-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 2px solid #eee;
        }
        
        .tracking-code {
            font-size: 24px;
            font-weight: 700;
            color: darkblue;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .status-badge {
            padding: 8px 20px;
            border-radius: 20px;
            font-weight: 600;
            display: inline-block;
        }
        
        .status-pending {
            background: #fff3cd;
            color: #856404;
        }
        
        .status-in_progress {
            background: #cce5ff;
            color: #004085;
        }
        
        .status-resolved {
            background: #d4edda;
            color: #155724;
        }
        
        .complaint-details {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 25px;
        }
        
        .detail-item {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 6px;
            border-left: 4px solid darkblue;
        }
        
        .detail-item h4 {
            color: darkblue;
            margin-bottom: 5px;
            font-size: 14px;
        }
        
        .detail-item p {
            font-size: 16px;
            font-weight: 500;
        }
        
        .description-box {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 6px;
            margin-bottom: 25px;
        }
        
        .description-box h4 {
            color: darkblue;
            margin-bottom: 10px;
        }
        
        .description-box p {
            line-height: 1.6;
        }
        
        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 50px 20px;
            background: white;
            border-radius: 8px;
            box-shadow: 0 3px 10px rgba(0,0,0,0.1);
        }
        
        .empty-state i {
            font-size: 60px;
            color: #ddd;
            margin-bottom: 20px;
        }
        
        .empty-state h3 {
            margin-bottom: 10px;
            color: #666;
        }
        
        /* Action Buttons */
        .action-buttons {
            display: flex;
            gap: 15px;
            margin-top: 20px;
        }
        
        /* Responsive */
        @media (max-width: 768px) {
            .header-content {
                flex-direction: column;
                gap: 15px;
                text-align: center;
            }
            
            .nav ul {
                justify-content: center;
                flex-wrap: wrap;
            }
            
            .search-form {
                flex-direction: column;
            }
            
            .complaint-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 15px;
            }
            
            .action-buttons {
                flex-direction: column;
            }
        }
    </style>
</head>
<body>
    <!-- Header -->
    <header class="header">
        <div class="container">
            <div class="header-content">
                <div class="logo">
                    <i class="fas fa-landmark"></i>
                    <div>
                        <h1>नगरपालिका</h1>
                        <span>Lalitpur Complaint System</span>
                    </div>
                </div>
                
                <nav class="nav">
                    <ul>
                        <li><a href="dashboard.php">Dashboard</a></li>
                        <li><a href="submit_complaint.php">File Complaint</a></li>
                        <li><a href="track_complaint.php" class="active">Track Status</a></li>
                    </ul>
                </nav>
                
                <div class="user-info">
                    <span><?php echo htmlspecialchars($user_name); ?></span>
                    <a href="logout.php" class="btn btn-small">Logout</a>
                </div>
            </div>
        </div>
    </header>

    <!-- Main Content -->
    <div class="main-content">
        <div class="container">
            <!-- Welcome Card -->
            <div class="welcome-card">
                <h2>Track Your Complaint</h2>
                <p>Enter your tracking code to check the current status of your complaint.</p>
            </div>

            <!-- Search Box -->
            <div class="search-box">
                <h3>Search Complaint</h3>
                
                <?php if($success): ?>
                    <div class="alert alert-success">
                        <i class="fas fa-check-circle"></i>
                        <div><?php echo $success; ?></div>
                    </div>
                <?php endif; ?>
                
                <?php if($error): ?>
                    <div class="alert alert-error">
                        <i class="fas fa-exclamation-circle"></i>
                        <div><?php echo $error; ?></div>
                    </div>
                <?php endif; ?>
                
                <form method="POST" action="" class="search-form">
                    <input type="text" 
                           name="tracking_code" 
                           class="search-input" 
                           placeholder="Enter tracking code (e.g., LC-20241218-ABC123)" 
                           value="<?php echo htmlspecialchars($tracking_code); ?>"
                           required>
                    <button type="submit" name="search" class="btn">
                        <i class="fas fa-search"></i> Search
                    </button>
                </form>
                
                <p style="color: #666; font-size: 14px;">
                    <i class="fas fa-info-circle"></i> 
                    You can find your tracking code in your email or on your dashboard.
                </p>
            </div>

            <!-- Complaint Details -->
            <?php if($complaint): ?>
                <div class="complaint-card">
                    <!-- Complaint Header -->
                    <div class="complaint-header">
                        <div class="tracking-code">
                            <i class="fas fa-barcode"></i>
                            <?php echo htmlspecialchars($complaint['tracking_code']); ?>
                        </div>
                        <span class="status-badge status-<?php echo $complaint['status']; ?>">
                            <?php 
                                $status = str_replace('_', ' ', $complaint['status']);
                                echo ucwords($status); 
                            ?>
                        </span>
                    </div>
                    
                    <!-- Complaint Details -->
                    <div class="complaint-details">
                        <div class="detail-item">
                            <h4>Category</h4>
                            <p><?php echo htmlspecialchars($complaint['category_name']); ?></p>
                        </div>
                        
                        <div class="detail-item">
                            <h4>Ward Number</h4>
                            <p>Ward <?php echo $complaint['ward_number']; ?></p>
                        </div>
                        
                        <div class="detail-item">
                            <h4>Submitted Date</h4>
                            <p><?php echo date('M d, Y', strtotime($complaint['created_at'])); ?></p>
                        </div>
                        
                        <div class="detail-item">
                            <h4>Last Updated</h4>
                            <p>
                                <?php 
                                    if($complaint['updated_at']) {
                                        echo date('M d, Y', strtotime($complaint['updated_at']));
                                    } else {
                                        echo "Not updated yet";
                                    }
                                ?>
                            </p>
                        </div>
                    </div>
                    
                    <!-- Description -->
                    <div class="description-box">
                        <h4>Complaint Description</h4>
                        <p><?php echo nl2br(htmlspecialchars($complaint['description'])); ?></p>
                    </div>
                    
                    <!-- Address -->
                    <div class="detail-item" style="margin-bottom: 20px;">
                        <h4>Address</h4>
                        <p><?php echo htmlspecialchars($complaint['address']); ?></p>
                    </div>
                    
                    <!-- Image if exists -->
                    <?php if(!empty($complaint['image_path']) && file_exists($complaint['image_path'])): ?>
                        <div class="detail-item">
                            <h4>Attached Image</h4>
                            <div style="margin-top: 10px;">
                                <img src="<?php echo htmlspecialchars($complaint['image_path']); ?>" 
                                     alt="Complaint Image" 
                                     style="max-width: 300px; border-radius: 5px; border: 1px solid #ddd;">
                            </div>
                        </div>
                    <?php endif; ?>
                    
                    <!-- Action Buttons -->
                    <div class="action-buttons">
                        <a href="dashboard.php" class="btn btn-secondary">
                            <i class="fas fa-arrow-left"></i> Back to Dashboard
                        </a>
                        <a href="submit_complaint.php" class="btn">
                            <i class="fas fa-plus"></i> File New Complaint
                        </a>
                        <button onclick="window.print()" class="btn" style="background: #6c757d;">
                            <i class="fas fa-print"></i> Print Details
                        </button>
                    </div>
                </div>
                
            <?php elseif(!$complaint && empty($tracking_code)): ?>
                <!-- Empty State -->
                <div class="empty-state">
                    <i class="fas fa-search"></i>
                    <h3>Enter Tracking Code</h3>
                    <p>Use the search box above to track your complaint status.</p>
                    <p style="margin-top: 15px; color: #888;">
                        <i class="fas fa-lightbulb"></i> 
                        Tip: Check your email for the tracking code after submitting a complaint.
                    </p>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script>
        // Auto-hide alerts after 5 seconds
        setTimeout(() => {
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(alert => {
                alert.style.opacity = '0';
                setTimeout(() => alert.style.display = 'none', 500);
            });
        }, 5000);

        // Auto-focus search input
        document.addEventListener('DOMContentLoaded', function() {
            const searchInput = document.querySelector('input[name="tracking_code"]');
            if (searchInput) {
                searchInput.focus();
            }
        });
    </script>
</body>
</html>