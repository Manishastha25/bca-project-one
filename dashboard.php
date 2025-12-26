<?php
session_start();
require_once 'db.php';

// Redirect if not logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$user_name = $_SESSION['user_name'];

// Get complaint statistics
$total_query = "SELECT COUNT(*) as count FROM complaints WHERE user_id = '$user_id'";
$total_result = mysqli_query($conn, $total_query);
$total_row = mysqli_fetch_assoc($total_result);
$total_complaints = $total_row['count'];

$pending_query = "SELECT COUNT(*) as count FROM complaints WHERE user_id = '$user_id' AND status = 'pending'";
$pending_result = mysqli_query($conn, $pending_query);
$pending_row = mysqli_fetch_assoc($pending_result);
$pending_complaints = $pending_row['count'];

$progress_query = "SELECT COUNT(*) as count FROM complaints WHERE user_id = '$user_id' AND status = 'in_progress'";
$progress_result = mysqli_query($conn, $progress_query);
$progress_row = mysqli_fetch_assoc($progress_result);
$in_progress_complaints = $progress_row['count'];

$resolved_query = "SELECT COUNT(*) as count FROM complaints WHERE user_id = '$user_id' AND status = 'resolved'";
$resolved_result = mysqli_query($conn, $resolved_query);
$resolved_row = mysqli_fetch_assoc($resolved_result);
$resolved_complaints = $resolved_row['count'];

// Get recent complaints
$recent_query = "SELECT c.*, cat.category_name 
                 FROM complaints c 
                 LEFT JOIN categories cat ON c.category_id = cat.id 
                 WHERE c.user_id = '$user_id' 
                 ORDER BY c.created_at DESC 
                 LIMIT 5";
                $recent_result = mysqli_query($conn, $recent_query);
$recent_complaints = array();
if ($recent_result && mysqli_num_rows($recent_result) > 0) {
    while($row = mysqli_fetch_assoc($recent_result)) {
        $recent_complaints[] = $row;
    }
}

// Get categories
$categories_query = "SELECT * FROM categories";
$categories_result = mysqli_query($conn, $categories_query);
$categories = array();
if ($categories_result && mysqli_num_rows($categories_result) > 0) {
    while($row = mysqli_fetch_assoc($categories_result)) {
        $categories[] = $row;
    }
}

// Handle quick complaint submission
$quick_success = '';
$quick_error = '';
$quick_tracking = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['quick_complaint'])) {
    $category = mysqli_real_escape_string($conn, $_POST['category'] ?? '');
    $ward = mysqli_real_escape_string($conn, $_POST['ward'] ?? '');
    $address = mysqli_real_escape_string($conn, $_POST['address'] ?? '');
    $description = mysqli_real_escape_string($conn, $_POST['description'] ?? '');
    
    if (!empty($category) && !empty($ward) && !empty($address) && !empty($description)) {
        $title = "Complaint from $user_name";
        $tracking_code = 'LC-' . date('Ymd') . '-' . strtoupper(substr(md5(time() . $user_id), 0, 6));
        
        $insert_query = "INSERT INTO complaints 
                        (user_id, category_id, title, description, ward_number, address, tracking_code, status, created_at) 
                        VALUES 
                        ('$user_id', '$category', '$title', '$description', '$ward', '$address', '$tracking_code', 'pending', NOW())";
        
        if (mysqli_query($conn, $insert_query)) {
            $quick_success = "Quick complaint submitted successfully!";
            $quick_tracking = $tracking_code;
            
            // Refresh statistics
            $total_result = mysqli_query($conn, $total_query);
            $total_row = mysqli_fetch_assoc($total_result);
            $total_complaints = $total_row['count'];
            
            $pending_result = mysqli_query($conn, $pending_query);
            $pending_row = mysqli_fetch_assoc($pending_result);
            $pending_complaints = $pending_row['count'];
            
            // Refresh recent complaints
            $recent_result = mysqli_query($conn, $recent_query);
            $recent_complaints = array();
            if ($recent_result && mysqli_num_rows($recent_result) > 0) {
                while($row = mysqli_fetch_assoc($recent_result)) {
                    $recent_complaints[] = $row;
                }
            }
        } else {
            $quick_error = "Error: " . mysqli_error($conn);
        }
    } else {
        $quick_error = "Please fill all required fields!";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard</title>
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
        
        /* Header Styles - RED & DARKBLUE GRADIENT */
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
        
        /* BUTTONS - SOLID COLORS (NO GRADIENT) */
        .btn {
            display: inline-block;
            padding: 10px 20px;
            background: darkblue; 
            color: white;
            text-decoration: none;
            border-radius: 5px;
            border: none;
            cursor: pointer;
            font-weight: 500;
            transition: all 0.3s;
        }
        
        .btn:hover {
            background: darkred; /* Darker red on hover */
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.2);
        }
        
        .btn-secondary {
            background: #6c757d; /* Gray color */
        }
        
        .btn-secondary:hover {
            background: #5a6268; /* Darker gray on hover */
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
            background: linear-gradient(to right,darkblue,red);
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
        
        /* Stats Grid */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .stat-card {
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 3px 10px rgba(0,0,0,0.1);
            text-align: center;
        }
        
        .stat-icon {
            font-size: 40px;
            margin-bottom: 10px;
        }
        
        .stat-icon.total { color: darkblue; }
        .stat-icon.pending { color: red; }
        .stat-icon.progress { color: #ffa500; }
        .stat-icon.resolved { color: #28a745; }
        
        .stat-number {
            font-size: 32px;
            font-weight: 700;
            margin-bottom: 5px;
        }
        
        /* Forms */
        .form-container {
            background: white;
            padding: 25px;
            border-radius: 8px;
            margin-bottom: 30px;
            box-shadow: 0 3px 10px rgba(0,0,0,0.1);
        }
        
        .form-title {
            font-size: 20px;
            margin-bottom: 20px;
            color: darkblue;
            border-bottom: 2px solid red;
            padding-bottom: 10px;
        }
        
        .form-row {
            display: flex;
            gap: 20px;
            margin-bottom: 20px;
        }
        
        .form-group {
            flex: 1;
            margin-bottom: 15px;
        }
        
        label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
            color: darkblue;
        }
        
        input, select, textarea {
            width: 100%;
            padding: 12px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-family: 'Poppins', sans-serif;
        }
        
        textarea {
            min-height: 100px;
            resize: vertical;
        }
        
        .form-buttons {
            display: flex;
            gap: 15px;
            margin-top: 20px;
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
        
        /* Table */
        .table-container {
            background: white;
            padding: 25px;
            border-radius: 8px;
            box-shadow: 0 3px 10px rgba(0,0,0,0.1);
        }
        
        .table-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
        }
        
        th {
            background: linear-gradient(to right, darkblue, red);
            color: white;
            padding: 15px;
            text-align: left;
            font-weight: 600;
        }
        
        td {
            padding: 15px;
            border-bottom: 1px solid #eee;
        }
        
        tr:hover {
            background: #f8f9fa;
        }
        
        /* Status Badges */
        .status-badge {
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 500;
            display: inline-block;
        }
        
        .status-pending {
            background: #ffe6e6;
            color: #cc0000;
            border: 1px solid #ff9999;
        }
        
        .status-in_progress {
            background: #e6f0ff;
            color: darkblue;
            border: 1px solid #99b3ff;
        }
        
        .status-resolved {
            background: #e6ffe6;
            color: #006600;
            border: 1px solid #99cc99;
        }
        
        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 40px 20px;
        }
        
        .empty-state i {
            font-size: 50px;
            color: #ddd;
            margin-bottom: 20px;
        }
        
        .empty-state h3 {
            margin-bottom: 10px;
            color: #666;
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
            
            .form-row {
                flex-direction: column;
                gap: 0;
            }
            
            .table-header {
                flex-direction: column;
                gap: 15px;
                text-align: center;
            }
            
            .stats-grid {
                grid-template-columns: 1fr;
            }
            
            table {
                display: block;
                overflow-x: auto;
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
                        <li><a href="dashboard.php" class="active">Dashboard</a></li>
                        <li><a href="submit_complaint.php">File Complaint</a></li>
                        <li><a href="track_complaint.php">Track Status</a></li>
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
                <h2>Welcome, <?php echo htmlspecialchars($user_name); ?>!</h2>
                <p>Manage your complaints and help improve our municipality.</p>
                <div class="form-buttons">
                    <a href="submit_complaint.php" class="btn">
                        <i class="fas fa-plus-circle"></i> File New Complaint
                    </a>
                    <a href="track_complaint.php" class="btn btn-secondary">
                        <i class="fas fa-search"></i> Track Complaint
                    </a>
                </div>
            </div>

            <!-- Statistics -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-icon total">
                        <i class="fas fa-clipboard-list"></i>
                    </div>
                    <div class="stat-number"><?php echo $total_complaints; ?></div>
                    <div>Total Complaints</div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon pending">
                        <i class="fas fa-clock"></i>
                    </div>
                    <div class="stat-number"><?php echo $pending_complaints; ?></div>
                    <div>Pending</div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon progress">
                        <i class="fas fa-spinner"></i>
                    </div>
                    <div class="stat-number"><?php echo $in_progress_complaints; ?></div>
                    <div>In Progress</div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon resolved">
                        <i class="fas fa-check-circle"></i>
                    </div>
                    <div class="stat-number"><?php echo $resolved_complaints; ?></div>
                    <div>Resolved</div>
                </div>
            </div>

            <!-- Quick Complaint Form -->
            <div class="form-container">
                <h3 class="form-title">Quick Complaint Submission</h3>
                
                <?php if($quick_success): ?>
                    <div class="alert alert-success">
                        <i class="fas fa-check-circle"></i>
                        <div>
                            <strong>Success!</strong> <?php echo $quick_success; ?>
                            <?php if($quick_tracking): ?>
                                <div style="margin-top: 10px; font-weight: 600;">Tracking Code: <?php echo $quick_tracking; ?></div>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endif; ?>
                
                <?php if($quick_error): ?>
                    <div class="alert alert-error">
                        <i class="fas fa-exclamation-circle"></i>
                        <div><?php echo $quick_error; ?></div>
                    </div>
                <?php endif; ?>
                
                <form method="POST" action="">
                    <div class="form-row">
                        <div class="form-group">
                            <label for="category">Category *</label>
                            <select id="category" name="category" required>
                                <option value="">Select Category</option>
                                <?php foreach($categories as $cat): ?>
                                    <option value="<?php echo $cat['id']; ?>">
                                        <?php echo htmlspecialchars($cat['category_name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label for="ward">Ward Number *</label>
                            <select id="ward" name="ward" required>
                                <option value="">Select Ward</option>
                                <?php for($i = 1; $i <= 3; $i++): ?>
                                    <option value="<?php echo $i; ?>">Ward <?php echo $i; ?></option>
                                <?php endfor; ?>
                            </select>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="address">Location/Address *</label>
                        <input type="text" id="address" name="address" placeholder="Where is the issue located?" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="description">Complaint Details *</label>
                        <textarea id="description" name="description" placeholder="Briefly describe the issue..." required></textarea>
                    </div>
                    
                    <div class="form-buttons">
                        <button type="submit" name="quick_complaint" class="btn">
                            <i class="fas fa-paper-plane"></i> Submit Quick Complaint
                        </button>
                        <a href="submit_complaint.php" class="btn btn-secondary">
                            <i class="fas fa-file-alt"></i> Use Full Form
                        </a>
                    </div>
                </form>
            </div>

            <!-- Recent Complaints -->
            <div class="table-container">
                <div class="table-header">
                    <h3 class="form-title">Recent Complaints</h3>
                    <a href="track_complaint.php" class="btn btn-small">
                        <i class="fas fa-list"></i> View All
                    </a>
                </div>
                
                <?php if(count($recent_complaints) > 0): ?>
                    <table>
                        <thead>
                            <tr>
                                <th>Tracking Code</th>
                                <th>Title</th>
                                <th>Category</th>
                                <th>Ward</th>
                                <th>Status</th>
                                <th>Date</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($recent_complaints as $complaint): ?>
                            <tr>
                                <td>
                                    <strong><?php echo $complaint['tracking_code']; ?></strong>
                                </td>
                                <td><?php echo htmlspecialchars($complaint['title']); ?></td>
                                <td><?php echo htmlspecialchars($complaint['category_name']); ?></td>
                                <td>Ward <?php echo $complaint['ward_number']; ?></td>
                                <td>
                                    <span class="status-badge status-<?php echo $complaint['status']; ?>">
                                        <?php 
                                        $status = str_replace('_', ' ', $complaint['status']);
                                        echo ucwords($status); 
                                        ?>
                                    </span>
                                </td>
                                <td><?php echo date('M d, Y', strtotime($complaint['created_at'])); ?></td>
                                <td>
                                    <a href="track_complaint.php?tracking_code=<?php echo $complaint['tracking_code']; ?>" class="btn btn-small">
                                        <i class="fas fa-eye"></i> View
                                    </a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <div class="empty-state">
                        <i class="fas fa-clipboard-list"></i>
                        <h3>No Complaints Yet</h3>
                        <p>You haven't filed any complaints yet.</p>
                        <a href="submit_complaint.php" class="btn" style="margin-top: 15px;">
                            <i class="fas fa-plus"></i> File First Complaint
                        </a>
                    </div>
                <?php endif; ?>
            </div>
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

        // Form loading state
        const form = document.querySelector('form');
        if (form) {
            form.addEventListener('submit', function(e) {
                const btn = this.querySelector('button[type="submit"]');
                if (btn) {
                    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Submitting...';
                    btn.disabled = true;
                }
            });
        }
    </script>
</body>
</html>