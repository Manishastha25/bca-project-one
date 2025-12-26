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
$user_type = $_SESSION['user_type'];

// Check if user is admin
if ($user_type !== 'admin') {
    $_SESSION['error'] = "Access denied. Admin privileges required.";
    header("Location: dashboard.php");
    exit();
}

// Get statistics using mysqli
$total_complaints = 0;
$total_guest_complaints = 0;
$pending_complaints = 0;
$pending_guest_complaints = 0;
$in_progress_complaints = 0;
$in_progress_guest_complaints = 0;
$resolved_complaints = 0;
$resolved_guest_complaints = 0;
$total_users = 0;
$total_citizens = 0;
$total_admins = 0;
$recent_complaints = [];

// Total complaints from registered users
$sql = "SELECT COUNT(*) as count FROM complaints";
$result = mysqli_query($conn, $sql);
$row = mysqli_fetch_assoc($result);
$total_complaints = $row['count'];

// Total guest complaints
$sql = "SELECT COUNT(*) as count FROM guest_complaints";
$result = mysqli_query($conn, $sql);
$row = mysqli_fetch_assoc($result);
$total_guest_complaints = $row['count'];

// Pending complaints
$sql = "SELECT COUNT(*) as count FROM complaints WHERE status = 'pending'";
$result = mysqli_query($conn, $sql);
$row = mysqli_fetch_assoc($result);
$pending_complaints = $row['count'];

$sql = "SELECT COUNT(*) as count FROM guest_complaints WHERE status = 'pending'";
$result = mysqli_query($conn, $sql);
$row = mysqli_fetch_assoc($result);
$pending_guest_complaints = $row['count'];

// In progress complaints
$sql = "SELECT COUNT(*) as count FROM complaints WHERE status = 'in_progress'";
$result = mysqli_query($conn, $sql);
$row = mysqli_fetch_assoc($result);
$in_progress_complaints = $row['count'];

$sql = "SELECT COUNT(*) as count FROM guest_complaints WHERE status = 'in_progress'";
$result = mysqli_query($conn, $sql);
$row = mysqli_fetch_assoc($result);
$in_progress_guest_complaints = $row['count'];

// Resolved complaints
$sql = "SELECT COUNT(*) as count FROM complaints WHERE status = 'resolved'";
$result = mysqli_query($conn, $sql);
$row = mysqli_fetch_assoc($result);
$resolved_complaints = $row['count'];

$sql = "SELECT COUNT(*) as count FROM guest_complaints WHERE status = 'resolved'";
$result = mysqli_query($conn, $sql);
$row = mysqli_fetch_assoc($result);
$resolved_guest_complaints = $row['count'];

// User statistics
$sql = "SELECT COUNT(*) as count FROM users";
$result = mysqli_query($conn, $sql);
$row = mysqli_fetch_assoc($result);
$total_users = $row['count'];

$sql = "SELECT COUNT(*) as count FROM users WHERE user_type = 'citizen'";
$result = mysqli_query($conn, $sql);
$row = mysqli_fetch_assoc($result);
$total_citizens = $row['count'];

$sql = "SELECT COUNT(*) as count FROM users WHERE user_type = 'admin'";
$result = mysqli_query($conn, $sql);
$row = mysqli_fetch_assoc($result);
$total_admins = $row['count'];

// Recent complaints (last 10)
$sql = "(SELECT 'logged_in' as type, id, tracking_code, 
                (SELECT name FROM users WHERE id = complaints.user_id) as name, 
                title as complaint, ward_number, address, status, created_at
         FROM complaints 
         ORDER BY created_at DESC 
         LIMIT 5)
         UNION
         (SELECT 'guest' as type, id, tracking_code, name, 
                 complaint_details as complaint, 
                 ward_number, address, status, created_at
         FROM guest_complaints 
         ORDER BY created_at DESC 
         LIMIT 5)
         ORDER BY created_at DESC 
         LIMIT 10";

$result = mysqli_query($conn, $sql);
while($row = mysqli_fetch_assoc($result)) {
    $recent_complaints[] = $row;
}

$total_all_complaints = $total_complaints + $total_guest_complaints;
$total_pending = $pending_complaints + $pending_guest_complaints;
$total_in_progress = $in_progress_complaints + $in_progress_guest_complaints;
$total_resolved = $resolved_complaints + $resolved_guest_complaints;

// Handle status update
$update_success = '';
$update_error = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_status'])) {
    $tracking_code = mysqli_real_escape_string($conn, $_POST['tracking_code'] ?? '');
    $new_status = mysqli_real_escape_string($conn, $_POST['status'] ?? '');
    $description = mysqli_real_escape_string($conn, $_POST['description'] ?? '');
    
    if (empty($tracking_code) || empty($new_status)) {
        $update_error = "Tracking code and status are required!";
    } else {
        // Check if it's a logged-in user complaint
        $sql = "SELECT id FROM complaints WHERE tracking_code = '$tracking_code'";
        $result = mysqli_query($conn, $sql);
        
        if (mysqli_num_rows($result) == 1) {
            // Update logged-in complaint
            $sql = "UPDATE complaints SET status = '$new_status', updated_at = NOW() WHERE tracking_code = '$tracking_code'";
            if (mysqli_query($conn, $sql)) {
                $update_success = "Status updated successfully!";
            } else {
                $update_error = "Error updating status: " . mysqli_error($conn);
            }
        } else {
            // Check if it's a guest complaint
            $sql = "SELECT id FROM guest_complaints WHERE tracking_code = '$tracking_code'";
            $result = mysqli_query($conn, $sql);
            
            if (mysqli_num_rows($result) == 1) {
                // Update guest complaint
                $sql = "UPDATE guest_complaints SET status = '$new_status', updated_at = NOW() WHERE tracking_code = '$tracking_code'";
                if (mysqli_query($conn, $sql)) {
                    $update_success = "Status updated successfully!";
                } else {
                    $update_error = "Error updating status: " . mysqli_error($conn);
                }
            } else {
                $update_error = "Tracking code not found!";
            }
        }
        
        // Refresh the page after successful update
        if (!empty($update_success)) {
            header("Location: " . $_SERVER['PHP_SELF']);
            exit();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - Municipality System</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="admin_style.css">
</head>
<body>
    <body class="admin-page">
    <!-- Admin Header -->
    <div class="admin-header">
        <div class="header-top">
            <div class="logo">
                <i class="fas fa-landmark"></i>
                <div>
                    <h1>नगरपालिका</h1>
                    <span>Admin Dashboard</span>
                </div>
            </div>
            
            <div class="user-info">
                <div class="user-details">
                    <h3><?php echo htmlspecialchars($user_name); ?></h3>
                    <p><i class="fas fa-user-shield"></i> Administrator</p>
                </div>
                <a href="logout.php" class="logout-btn">
                    <i class="fas fa-sign-out-alt"></i> Logout
                </a>
            </div>
        </div>
        
        <nav class="admin-nav">
            <ul class="nav-links">
                <li><a href="admin_dashboard.php" class="active"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
                <li><a href="manage_complaints.php"><i class="fas fa-clipboard-list"></i> Manage Complaints</a></li>
                <li><a href="manage_users.php"><i class="fas fa-users"></i> Manage Users</a></li>
                <li><a href="categories.php"><i class="fas fa-tags"></i> Categories</a></li>
                <li><a href="reports.php"><i class="fas fa-chart-bar"></i> Reports</a></li>
            </ul>
        </nav>
    </div>

    <!-- Main Content -->
    <div class="admin-content">
        <!-- Welcome Section -->
        <div class="welcome-card">
            <h2>Welcome back, <?php echo htmlspecialchars($user_name); ?>! <span class="admin-badge">ADMIN</span></h2>
            <p>Monitor complaints, manage users, and oversee system operations from your admin dashboard.</p>
            
            <div class="quick-actions">
                <a href="manage_complaints.php" class="action-btn primary">
                    <i class="fas fa-tasks"></i> Manage Complaints
                </a>
                <a href="add_user.php" class="action-btn secondary">
                    <i class="fas fa-user-plus"></i> Add New User
                </a>
                <a href="reports.php" class="action-btn success">
                    <i class="fas fa-file-export"></i> Generate Reports
                </a>
            </div>
        </div>

        <!-- Statistics Grid -->
        <div class="stats-grid">
            <div class="stat-card total">
                <div class="stat-icon">
                    <i class="fas fa-clipboard-list"></i>
                </div>
                <div class="stat-number"><?php echo $total_all_complaints; ?></div>
                <h3 class="stat-title">Total Complaints</h3>
                <small><?php echo $total_complaints; ?> user + <?php echo $total_guest_complaints; ?> guest</small>
            </div>
            
            <div class="stat-card pending">
                <div class="stat-icon">
                    <i class="fas fa-clock"></i>
                </div>
                <div class="stat-number"><?php echo $total_pending; ?></div>
                <h3 class="stat-title">Pending Complaints</h3>
                <small>Awaiting action</small>
            </div>
            
            <div class="stat-card progress">
                <div class="stat-icon">
                    <i class="fas fa-spinner"></i>
                </div>
                <div class="stat-number"><?php echo $total_in_progress; ?></div>
                <h3 class="stat-title">In Progress</h3>
                <small>Currently being processed</small>
            </div>
            
            <div class="stat-card resolved">
                <div class="stat-icon">
                    <i class="fas fa-check-circle"></i>
                </div>
                <div class="stat-number"><?php echo $total_resolved; ?></div>
                <h3 class="stat-title">Resolved</h3>
                <small>Successfully resolved</small>
            </div>
        </div>

        <!-- Dashboard Grid -->
        <div class="dashboard-grid">
            <!-- Quick Status Update Form -->
            <div class="dashboard-card">
                <h3><i class="fas fa-sync-alt"></i> Quick Status Update</h3>
                
                <?php if(!empty($update_success)): ?>
                    <div class="alert success">
                        <i class="fas fa-check-circle"></i>
                        <?php echo $update_success; ?>
                    </div>
                <?php endif; ?>
                
                <?php if(!empty($update_error)): ?>
                    <div class="alert error">
                        <i class="fas fa-exclamation-circle"></i>
                        <?php echo $update_error; ?>
                    </div>
                <?php endif; ?>
                
                <form method="POST" action="">
                    <div class="form-group">
                        <label for="tracking_code"><i class="fas fa-barcode"></i> Tracking Code</label>
                        <input type="text" id="tracking_code" name="tracking_code" 
                               placeholder="Enter tracking code" required
                               value="<?php echo isset($_POST['tracking_code']) ? htmlspecialchars($_POST['tracking_code']) : ''; ?>">
                    </div>
                    
                    <div class="form-group">
                        <label for="status"><i class="fas fa-flag"></i> New Status</label>
                        <select id="status" name="status" required>
                            <option value="">Select Status</option>
                            <option value="pending" <?php echo (isset($_POST['status']) && $_POST['status'] == 'pending') ? 'selected' : ''; ?>>⏳ Pending</option>
                            <option value="in_progress" <?php echo (isset($_POST['status']) && $_POST['status'] == 'in_progress') ? 'selected' : ''; ?>>🔄 In Progress</option>
                            <option value="resolved" <?php echo (isset($_POST['status']) && $_POST['status'] == 'resolved') ? 'selected' : ''; ?>>✅ Resolved</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="description"><i class="fas fa-comment"></i> Update Notes</label>
                        <textarea id="description" name="description" 
                                  placeholder="Add update notes..." rows="3"><?php echo isset($_POST['description']) ? htmlspecialchars($_POST['description']) : ''; ?></textarea>
                    </div>
                    
                    <button type="submit" name="update_status" class="btn btn-primary">
                        <i class="fas fa-sync-alt"></i> Update Status
                    </button>
                </form>
            </div>

            <!-- System Overview -->
            <div class="dashboard-card">
                <h3><i class="fas fa-chart-line"></i> System Overview</h3>
                
                <div class="quick-stats">
                    <div class="mini-stat">
                        <div class="mini-stat-icon user">
                            <i class="fas fa-users"></i>
                        </div>
                        <div class="mini-stat-info">
                            <div class="mini-stat-number"><?php echo $total_users; ?></div>
                            <div class="mini-stat-title">Total Users</div>
                            <small><?php echo $total_citizens; ?> citizens, <?php echo $total_admins; ?> admins</small>
                        </div>
                    </div>
                    
                    <div class="mini-stat">
                        <div class="mini-stat-icon rate">
                            <i class="fas fa-chart-pie"></i>
                        </div>
                        <div class="mini-stat-info">
                            <div class="mini-stat-number">
                                <?php echo $total_all_complaints > 0 ? round(($total_resolved / $total_all_complaints) * 100) : 0; ?>%
                            </div>
                            <div class="mini-stat-title">Resolution Rate</div>
                            <small>Overall success rate</small>
                        </div>
                    </div>
                </div>
                
                <h4 style="margin-top: 25px;"><i class="fas fa-bolt"></i> Quick Actions</h4>
                <div class="quick-action-buttons">
                    <a href="manage_complaints.php?status=pending" class="btn btn-warning btn-small">
                        <i class="fas fa-clock"></i> View Pending
                    </a>
                    <a href="add_user.php" class="btn btn-success btn-small">
                        <i class="fas fa-user-plus"></i> Add User
                    </a>
                    <a href="export.php" class="btn btn-info btn-small">
                        <i class="fas fa-download"></i> Export Data
                    </a>
                </div>
            </div>
        </div>

        <!-- Recent Complaints -->
        <div class="recent-complaints">
            <div class="section-header">
                <h3><i class="fas fa-history"></i> Recent Complaints</h3>
                <div class="filter-tabs">
                    <button class="filter-btn active" onclick="filterComplaints('all')">
                        All <span class="count-badge"><?php echo $total_all_complaints; ?></span>
                    </button>
                    <button class="filter-btn" onclick="filterComplaints('pending')">
                        Pending <span class="count-badge"><?php echo $total_pending; ?></span>
                    </button>
                    <button class="filter-btn" onclick="filterComplaints('in_progress')">
                        In Progress <span class="count-badge"><?php echo $total_in_progress; ?></span>
                    </button>
                    <a href="manage_complaints.php" class="btn btn-primary btn-small">
                        <i class="fas fa-external-link-alt"></i> View All
                    </a>
                </div>
            </div>
            
            <?php if(count($recent_complaints) > 0): ?>
                <div class="table-container">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Type</th>
                                <th>Tracking Code</th>
                                <th>Complainant</th>
                                <th>Complaint</th>
                                <th>Ward</th>
                                <th>Status</th>
                                <th>Date</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody id="complaintsTable">
                            <?php foreach($recent_complaints as $complaint): ?>
                            <tr data-status="<?php echo $complaint['status']; ?>">
                                <td>
                                    <span class="type-badge <?php echo $complaint['type']; ?>">
                                        <?php if($complaint['type'] == 'logged_in'): ?>
                                            <i class="fas fa-user"></i> User
                                        <?php else: ?>
                                            <i class="fas fa-user-clock"></i> Guest
                                        <?php endif; ?>
                                    </span>
                                </td>
                                <td><strong><?php echo htmlspecialchars($complaint['tracking_code']); ?></strong></td>
                                <td><?php echo htmlspecialchars($complaint['name']); ?></td>
                                <td><?php echo htmlspecialchars(substr($complaint['complaint'], 0, 50)); ?>...</td>
                                <td><span class="ward-badge">Ward <?php echo htmlspecialchars($complaint['ward_number']); ?></span></td>
                                <td>
                                    <span class="status-badge <?php echo $complaint['status']; ?>">
                                        <?php echo ucfirst(str_replace('_', ' ', $complaint['status'])); ?>
                                    </span>
                                </td>
                                <td><?php echo date('M d, Y', strtotime($complaint['created_at'])); ?></td>
                                <td>
                                    <div class="action-buttons">
                                        <a href="view_complaint.php?tracking_code=<?php echo urlencode($complaint['tracking_code']); ?>&type=<?php echo $complaint['type']; ?>" 
                                           class="btn btn-sm btn-info" title="View">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        <button onclick="quickUpdate('<?php echo $complaint['tracking_code']; ?>')" 
                                               class="btn btn-sm btn-warning" title="Update">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="empty-state">
                    <i class="fas fa-clipboard-list"></i>
                    <h3>No Complaints Yet</h3>
                    <p>No complaints have been submitted to the system.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script>
        function filterComplaints(status) {
            const buttons = document.querySelectorAll('.filter-btn');
            buttons.forEach(btn => btn.classList.remove('active'));
            event.target.classList.add('active');
            
            const rows = document.querySelectorAll('#complaintsTable tr');
            rows.forEach(row => {
                if (status === 'all') {
                    row.style.display = '';
                } else {
                    if (row.getAttribute('data-status') === status) {
                        row.style.display = '';
                    } else {
                        row.style.display = 'none';
                    }
                }
            });
        }
        
        function quickUpdate(trackingCode) {
            document.getElementById('tracking_code').value = trackingCode;
            document.getElementById('tracking_code').focus();
        }
        
        // Auto-hide alerts after 5 seconds
        setTimeout(() => {
            document.querySelectorAll('.alert').forEach(alert => {
                alert.style.display = 'none';
            });
        }, 5000);
    </script>
</body>
</html>