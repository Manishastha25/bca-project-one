<?php
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

require_once 'db.php';

$user_id = $_SESSION['user_id'];
$user_name = $_SESSION['user_name'];

// Check if user is admin
$sql = "SELECT user_type FROM users WHERE id = '$user_id'";
$result = mysqli_query($conn, $sql);

if ($result && mysqli_num_rows($result) > 0) {
    $user = mysqli_fetch_assoc($result);
    $user_type = $user['user_type'];
    
    if ($user_type !== 'admin') {
        $_SESSION['error'] = "Access denied. Admin privileges required.";
        header("Location: " . ($user_type == 'citizen' ? 'dashboard.php' : 'login.php'));
        exit();
    }
} else {
    $_SESSION['error'] = "User not found.";
    header("Location: logout.php");
    exit();
}

// Get filter parameters
$status_filter = isset($_GET['status']) ? mysqli_real_escape_string($conn, $_GET['status']) : 'all';
$type_filter = isset($_GET['type']) ? mysqli_real_escape_string($conn, $_GET['type']) : 'all';
$search = isset($_GET['search']) ? mysqli_real_escape_string($conn, $_GET['search']) : '';
$ward_filter = isset($_GET['ward']) ? mysqli_real_escape_string($conn, $_GET['ward']) : 'all';

// Build base queries
$user_query = "SELECT 
    c.id,
    c.user_id,
    c.category_id,
    c.title,
    c.description,
    c.ward_number,
    c.address,
    c.status,
    c.tracking_code,
    c.created_at,
    u.name as user_name, 
    cat.category_name, 
    'user' as complaint_type
    FROM complaints c 
    LEFT JOIN users u ON c.user_id = u.id 
    LEFT JOIN categories cat ON c.category_id = cat.id 
    WHERE 1=1";

$guest_query = "SELECT 
    gc.id,
    NULL as user_id,
    gc.category_id,
    gc.complaint_details as title,
    gc.complaint_details as description,
    gc.ward_number,
    gc.address,
    gc.status,
    gc.tracking_code,
    gc.created_at,
    gc.name as user_name, 
    cat.category_name, 
    'guest' as complaint_type
    FROM guest_complaints gc 
    LEFT JOIN categories cat ON gc.category_id = cat.id 
    WHERE 1=1";

// Apply filters to user query
if ($status_filter !== 'all') {
    $user_query .= " AND c.status = '$status_filter'";
}

if ($search) {
    $user_query .= " AND (c.tracking_code LIKE '%$search%' OR c.title LIKE '%$search%' OR u.name LIKE '%$search%' OR c.description LIKE '%$search%')";
}

if ($ward_filter !== 'all') {
    $user_query .= " AND c.ward_number = '$ward_filter'";
}

// Apply filters to guest query
if ($status_filter !== 'all') {
    $guest_query .= " AND gc.status = '$status_filter'";
}

if ($search) {
    $guest_query .= " AND (gc.tracking_code LIKE '%$search%' OR gc.name LIKE '%$search%' OR gc.complaint_details LIKE '%$search%')";
}

if ($ward_filter !== 'all') {
    $guest_query .= " AND gc.ward_number = '$ward_filter'";
}

// Combine queries based on type filter
if ($type_filter === 'all') {
    $query = "($user_query) UNION ALL ($guest_query) ORDER BY created_at DESC";
} elseif ($type_filter === 'user') {
    $query = "$user_query ORDER BY c.created_at DESC";
} else {
    $query = "$guest_query ORDER BY gc.created_at DESC";
}

// Get complaints
$result = mysqli_query($conn, $query);
$complaints = [];
if ($result) {
    while($row = mysqli_fetch_assoc($result)) {
        $complaints[] = $row;
    }
}

// Get statistics
$total_complaints = 0;
$total_guest_complaints = 0;
$pending_count = 0;
$progress_count = 0;
$resolved_count = 0;

// Count queries
$count1 = mysqli_query($conn, "SELECT COUNT(*) as count FROM complaints");
if ($count1) {
    $row = mysqli_fetch_assoc($count1);
    $total_complaints = $row['count'];
}

$count2 = mysqli_query($conn, "SELECT COUNT(*) as count FROM guest_complaints");
if ($count2) {
    $row = mysqli_fetch_assoc($count2);
    $total_guest_complaints = $row['count'];
}

$count3 = mysqli_query($conn, "SELECT COUNT(*) as count FROM complaints WHERE status = 'pending'");
if ($count3) {
    $row = mysqli_fetch_assoc($count3);
    $pending_count += $row['count'];
}

$count4 = mysqli_query($conn, "SELECT COUNT(*) as count FROM guest_complaints WHERE status = 'pending'");
if ($count4) {
    $row = mysqli_fetch_assoc($count4);
    $pending_count += $row['count'];
}

$count5 = mysqli_query($conn, "SELECT COUNT(*) as count FROM complaints WHERE status = 'in_progress'");
if ($count5) {
    $row = mysqli_fetch_assoc($count5);
    $progress_count += $row['count'];
}

$count6 = mysqli_query($conn, "SELECT COUNT(*) as count FROM guest_complaints WHERE status = 'in_progress'");
if ($count6) {
    $row = mysqli_fetch_assoc($count6);
    $progress_count += $row['count'];
}

$count7 = mysqli_query($conn, "SELECT COUNT(*) as count FROM complaints WHERE status = 'resolved'");
if ($count7) {
    $row = mysqli_fetch_assoc($count7);
    $resolved_count += $row['count'];
}

$count8 = mysqli_query($conn, "SELECT COUNT(*) as count FROM guest_complaints WHERE status = 'resolved'");
if ($count8) {
    $row = mysqli_fetch_assoc($count8);
    $resolved_count += $row['count'];
}

// Get unique wards
$wards = [];
$ward_query = mysqli_query($conn, "SELECT DISTINCT ward_number FROM complaints UNION SELECT DISTINCT ward_number FROM guest_complaints ORDER BY ward_number");
if ($ward_query) {
    while($row = mysqli_fetch_assoc($ward_query)) {
        $wards[] = $row;
    }
}

// Handle status update
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_status'])) {
    $tracking_code = mysqli_real_escape_string($conn, $_POST['tracking_code']);
    $new_status = mysqli_real_escape_string($conn, $_POST['status']);
    $update_notes = isset($_POST['update_notes']) ? mysqli_real_escape_string($conn, $_POST['update_notes']) : '';
    
    // Check if it's a regular complaint
    $check_sql = "SELECT id FROM complaints WHERE tracking_code = '$tracking_code'";
    $check_result = mysqli_query($conn, $check_sql);
    
    if (mysqli_num_rows($check_result) > 0) {
        $update_sql = "UPDATE complaints SET status = '$new_status' WHERE tracking_code = '$tracking_code'";
        mysqli_query($conn, $update_sql);
        $_SESSION['success'] = "Status updated successfully!";
    } else {
        // Check guest complaint
        $check_sql = "SELECT id FROM guest_complaints WHERE tracking_code = '$tracking_code'";
        $check_result = mysqli_query($conn, $check_sql);
        
        if (mysqli_num_rows($check_result) > 0) {
            $update_sql = "UPDATE guest_complaints SET status = '$new_status' WHERE tracking_code = '$tracking_code'";
            mysqli_query($conn, $update_sql);
            $_SESSION['success'] = "Status updated successfully!";
        } else {
            $_SESSION['error'] = "Complaint not found!";
        }
    }
    
    header("Location: manage_complaints.php");
    exit();
}

// Handle delete
if (isset($_GET['delete'])) {
    $tracking_code = mysqli_real_escape_string($conn, $_GET['delete']);
    
    // Try to delete from complaints table
    $delete_sql = "DELETE FROM complaints WHERE tracking_code = '$tracking_code'";
    mysqli_query($conn, $delete_sql);
    
    // If not found in complaints, try guest_complaints
    if (mysqli_affected_rows($conn) == 0) {
        $delete_sql = "DELETE FROM guest_complaints WHERE tracking_code = '$tracking_code'";
        mysqli_query($conn, $delete_sql);
    }
    
    $_SESSION['success'] = "Complaint deleted successfully!";
    header("Location: manage_complaints.php");
    exit();
}

mysqli_close($conn);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Complaints</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="auth_style.css">
    <style>
        .filter-section {
            background: white;
            padding: 25px;
            border-radius: 12px;
            margin-bottom: 30px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
        }
        
        .filter-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 20px;
        }
        
        .stats-cards {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .stat-card {
            background: white;
            padding: 20px;
            border-radius: 10px;
            text-align: center;
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
            transition: transform 0.3s;
            border-left: 5px solid #0000AA;
        }
        
        .stat-card.pending { border-left-color: #ffc107; }
        .stat-card.progress { border-left-color: #17a2b8; }
        .stat-card.resolved { border-left-color: #28a745; }
        
        .stat-number {
            font-size: 2.5rem;
            font-weight: 800;
            margin-bottom: 10px;
        }
        
        .stat-card.pending .stat-number { color: #ffc107; }
        .stat-card.progress .stat-number { color: #17a2b8; }
        .stat-card.resolved .stat-number { color: #28a745; }
        
        .complaint-type-badge {
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 600;
            white-space: nowrap;
        }
        
        .type-user { background: rgba(0, 0, 170, 0.1); color: #0000AA; }
        .type-guest { background: rgba(221, 0, 0, 0.1); color: #DD0000; }
        
        .status-badge {
            padding: 6px 15px;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 600;
            white-space: nowrap;
        }
        
        .status-pending { background: #FFF3CD; color: #856404; }
        .status-in_progress { background: #CCE5FF; color: #004085; }
        .status-resolved { background: #D4EDDA; color: #155724; }
        .status-rejected { background: #F8D7DA; color: #721c24; }
        
        .action-buttons {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
        }
        
        .action-btn {
            padding: 6px 12px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 0.85rem;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 5px;
            transition: all 0.3s;
            white-space: nowrap;
        }
        
        .view-btn { background: #0000AA; color: white; }
        .edit-btn { background: #6c757d; color: white; }
        .delete-btn { background: #dc3545; color: white; }
        
        .action-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 3px 10px rgba(0,0,0,0.2);
        }
        
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0,0,0,0.5);
            z-index: 1000;
            align-items: center;
            justify-content: center;
        }
        
        .modal-content {
            background: white;
            padding: 30px;
            border-radius: 15px;
            width: 90%;
            max-width: 500px;
        }
        
        .empty-state {
            text-align: center;
            padding: 50px 20px;
            color: #666;
        }
        
        .empty-state i {
            font-size: 4rem;
            color: #ddd;
            margin-bottom: 20px;
        }
        
        /* Table responsive fixes */
        .auth-table-container {
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
        }
        
        .auth-table {
            width: 100%;
            min-width: 800px; /* Minimum width before scrolling */
            border-collapse: collapse;
        }
        
        .auth-table th,
        .auth-table td {
            padding: 12px 15px;
            text-align: left;
            border-bottom: 1px solid #eaeaea;
        }
        
        .auth-table th {
            background-color: #f8f9fa;
            font-weight: 600;
            color: #333;
            position: sticky;
            top: 0;
        }
        
        .auth-table tr:hover {
            background-color: #f8f9fa;
        }
        
        /* Make table more compact on mobile */
        @media (max-width: 992px) {
            .auth-table {
                min-width: 900px;
            }
            
            .auth-table th,
            .auth-table td {
                padding: 10px 12px;
                font-size: 0.9rem;
            }
        }
        
        @media (max-width: 768px) {
            .filter-grid {
                grid-template-columns: 1fr;
            }
            
            .stats-cards {
                grid-template-columns: 1fr 1fr;
            }
            
            .action-buttons {
                flex-direction: column;
            }
            
            .action-btn {
                width: 100%;
                justify-content: center;
            }
            
            .auth-table {
                min-width: 1000px;
            }
            
            .auth-table th,
            .auth-table td {
                padding: 8px 10px;
                font-size: 0.85rem;
            }
        }
        
        @media (max-width: 480px) {
            .stats-cards {
                grid-template-columns: 1fr;
            }
            
            .stat-card {
                padding: 15px;
            }
            
            .stat-number {
                font-size: 2rem;
            }
        }
        
        /* Add some spacing improvements */
        .container {
            padding-left: 15px;
            padding-right: 15px;
        }
        
        .dashboard-section {
            padding: 20px 0;
        }
    </style>
</head>
<body>
    <!-- Header -->
    <header class="dashboard-header">
        <div class="container">
            <div class="dashboard-header-content">
                <div class="dashboard-logo">
                    <i class="fas fa-landmark"></i>
                    <div>
                        <h1>नगरपालिका</h1>
                        <span>Manage Complaints</span>
                    </div>
                </div>
                
                <nav class="dashboard-nav">
                    <ul>
                        <li><a href="admin_dashboard.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
                        <li><a href="manage_complaints.php" class="active"><i class="fas fa-clipboard-list"></i> Complaints</a></li>
                        <li><a href="manage_users.php"><i class="fas fa-users"></i> Users</a></li>
                        <li><a href="reports.php"><i class="fas fa-chart-bar"></i> Reports</a></li>
                    </ul>
                </nav>
                
                <div class="dashboard-user-info">
                    <div style="display: flex; flex-direction: column; align-items: flex-end; margin-right: 15px;">
                        <span style="font-weight: 600;"><?php echo htmlspecialchars($user_name); ?></span>
                        <small style="font-size: 0.8em; opacity: 0.8; background: #e74c3c; color: white; padding: 2px 8px; border-radius: 10px;">Admin</small>
                    </div>
                    <a href="logout.php" class="auth-button">
                        <i class="fas fa-sign-out-alt"></i> Logout
                    </a>
                </div>
            </div>
        </div>
    </header>

    <!-- Main Content -->
    <section class="dashboard-section">
        <div class="container">
            <!-- Success/Error Messages -->
            <?php if(isset($_SESSION['success'])): ?>
                <div class="auth-alert auth-alert-success">
                    <i class="fas fa-check-circle"></i>
                    <div><?php echo $_SESSION['success']; unset($_SESSION['success']); ?></div>
                </div>
            <?php endif; ?>
            
            <?php if(isset($_SESSION['error'])): ?>
                <div class="auth-alert auth-alert-danger">
                    <i class="fas fa-exclamation-circle"></i>
                    <div><?php echo $_SESSION['error']; unset($_SESSION['error']); ?></div>
                </div>
            <?php endif; ?>

            <!-- Page Title -->
            <div style="margin-bottom: 30px;">
                <h1 style="color: #0000AA; margin-bottom: 10px;">Manage Complaints</h1>
                <p style="color: #666;">View and manage all complaints</p>
            </div>

            <!-- Statistics Cards -->
            <div class="stats-cards">
                <div class="stat-card">
                    <div class="stat-number"><?php echo $total_complaints + $total_guest_complaints; ?></div>
                    <div style="font-weight: 600;">Total Complaints</div>
                    <small><?php echo $total_complaints; ?> user + <?php echo $total_guest_complaints; ?> guest</small>
                </div>
                
                <div class="stat-card pending">
                    <div class="stat-number"><?php echo $pending_count; ?></div>
                    <div style="font-weight: 600;">Pending</div>
                    <small>Awaiting action</small>
                </div>
                
                <div class="stat-card progress">
                    <div class="stat-number"><?php echo $progress_count; ?></div>
                    <div style="font-weight: 600;">In Progress</div>
                    <small>Being processed</small>
                </div>
                
                <div class="stat-card resolved">
                    <div class="stat-number"><?php echo $resolved_count; ?></div>
                    <div style="font-weight: 600;">Resolved</div>
                    <small>Successfully resolved</small>
                </div>
            </div>

            <!-- Filter Section -->
            <div class="filter-section">
                <form method="GET" action="">
                    <div class="filter-grid">
                        <div class="search-box">
                            <div class="auth-form-group">
                                <label><i class="fas fa-search"></i> Search Complaints</label>
                                <input type="text" name="search" placeholder="Search by tracking code, name, or details..." 
                                       value="<?php echo htmlspecialchars($search); ?>">
                            </div>
                        </div>
                        
                        <div class="auth-form-group">
                            <label>Status Filter</label>
                            <select name="status">
                                <option value="all" <?php echo $status_filter == 'all' ? 'selected' : ''; ?>>All Statuses</option>
                                <option value="pending" <?php echo $status_filter == 'pending' ? 'selected' : ''; ?>>Pending</option>
                                <option value="in_progress" <?php echo $status_filter == 'in_progress' ? 'selected' : ''; ?>>In Progress</option>
                                <option value="resolved" <?php echo $status_filter == 'resolved' ? 'selected' : ''; ?>>Resolved</option>
                            </select>
                        </div>
                        
                        <div class="auth-form-group">
                            <label>Complaint Type</label>
                            <select name="type">
                                <option value="all" <?php echo $type_filter == 'all' ? 'selected' : ''; ?>>All Types</option>
                                <option value="user" <?php echo $type_filter == 'user' ? 'selected' : ''; ?>>User Complaints</option>
                                <option value="guest" <?php echo $type_filter == 'guest' ? 'selected' : ''; ?>>Guest Complaints</option>
                            </select>
                        </div>
                        
                        <div class="auth-form-group">
                            <label>Ward Number</label>
                            <select name="ward">
                                <option value="all" <?php echo $ward_filter == 'all' ? 'selected' : ''; ?>>All Wards</option>
                                <?php foreach($wards as $ward): ?>
                                    <option value="<?php echo htmlspecialchars($ward['ward_number']); ?>" <?php echo $ward_filter == $ward['ward_number'] ? 'selected' : ''; ?>>
                                        Ward <?php echo htmlspecialchars($ward['ward_number']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    
                    <div style="display: flex; gap: 15px; margin-top: 10px;">
                        <button type="submit" class="auth-button">
                            <i class="fas fa-filter"></i> Apply Filters
                        </button>
                        <a href="manage_complaints.php" class="auth-button auth-button-secondary">
                            <i class="fas fa-times"></i> Clear Filters
                        </a>
                    </div>
                </form>
            </div>

            <!-- Complaints Table -->
            <div class="auth-table-container">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                    <h3 class="auth-form-title">Complaints List</h3>
                    <div>
                        <span style="color: #666; font-size: 0.9rem;">
                            Showing <?php echo count($complaints); ?> complaint(s)
                        </span>
                    </div>
                </div>
                
                <?php if(count($complaints) > 0): ?>
                    <table class="auth-table">
                        <thead>
                            <tr>
                                <th style="min-width: 80px;">Type</th>
                                <th style="min-width: 150px;">Tracking Code</th>
                                <th style="min-width: 120px;">Complainant</th>
                                <th style="min-width: 120px;">Category</th>
                                <th style="min-width: 80px;">Ward</th>
                                <th style="min-width: 100px;">Status</th>
                                <th style="min-width: 100px;">Date</th>
                                <th style="min-width: 200px;">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($complaints as $complaint): 
                                $is_guest = ($complaint['complaint_type'] ?? 'user') === 'guest';
                            ?>
                            <tr>
                                <td>
                                    <span class="complaint-type-badge <?php echo $is_guest ? 'type-guest' : 'type-user'; ?>">
                                        <?php echo $is_guest ? '<i class="fas fa-user-clock"></i> Guest' : '<i class="fas fa-user"></i> User'; ?>
                                    </span>
                                </td>
                                <td>
                                    <strong style="font-size: 0.9rem;"><?php echo htmlspecialchars($complaint['tracking_code']); ?></strong>
                                </td>
                                <td><?php echo htmlspecialchars($complaint['user_name']); ?></td>
                                <td><?php echo htmlspecialchars($complaint['category_name'] ?? 'N/A'); ?></td>
                                <td>Ward <?php echo htmlspecialchars($complaint['ward_number']); ?></td>
                                <td>
                                    <span class="status-badge status-<?php echo htmlspecialchars($complaint['status']); ?>">
                                        <?php echo ucfirst(str_replace('_', ' ', $complaint['status'])); ?>
                                    </span>
                                </td>
                                <td><?php echo date('M d, Y', strtotime($complaint['created_at'])); ?></td>
                                <td>
                                    <div class="action-buttons">
                                        <button onclick="viewComplaint('<?php echo $complaint['tracking_code']; ?>', '<?php echo $is_guest ? 'guest' : 'user'; ?>')" 
                                                class="action-btn view-btn">
                                            <i class="fas fa-eye"></i> View
                                        </button>
                                        <button onclick="updateStatus('<?php echo $complaint['tracking_code']; ?>')" 
                                                class="action-btn edit-btn">
                                            <i class="fas fa-edit"></i> Update
                                        </button>
                                        <button onclick="confirmDelete('<?php echo $complaint['tracking_code']; ?>')" 
                                                class="action-btn delete-btn">
                                            <i class="fas fa-trash"></i> Delete
                                        </button>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <div class="empty-state">
                        <i class="fas fa-clipboard-list"></i>
                        <h3>No Complaints Found</h3>
                        <p>No complaints match your current filters.</p>
                        <a href="manage_complaints.php" class="auth-button" style="margin-top: 15px;">
                            <i class="fas fa-times"></i> Clear Filters
                        </a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </section>

    <!-- Update Status Modal -->
    <div id="statusModal" class="modal">
        <div class="modal-content">
            <h3 class="auth-form-title">Update Complaint Status</h3>
            <form method="POST" action="">
                <input type="hidden" name="tracking_code" id="modal_tracking_code">
                
                <div class="auth-form-group">
                    <label>New Status</label>
                    <select name="status" required>
                        <option value="">Select Status</option>
                        <option value="pending">Pending</option>
                        <option value="in_progress">In Progress</option>
                        <option value="resolved">Resolved</option>
                        <option value="rejected">Rejected</option>
                    </select>
                </div>
                
                <div class="auth-form-group">
                    <label>Update Notes (Optional)</label>
                    <textarea name="update_notes" rows="3" placeholder="Add any notes about this status update..."></textarea>
                </div>
                
                <div style="display: flex; gap: 15px; margin-top: 20px;">
                    <button type="submit" name="update_status" class="auth-button">
                        <i class="fas fa-save"></i> Update Status
                    </button>
                    <button type="button" onclick="closeModal()" class="auth-button auth-button-secondary">
                        <i class="fas fa-times"></i> Cancel
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function updateStatus(trackingCode) {
            document.getElementById('modal_tracking_code').value = trackingCode;
            document.getElementById('statusModal').style.display = 'flex';
        }
        
        function viewComplaint(trackingCode, type) {
            window.location.href = 'view_complaint.php?tracking_code=' + trackingCode + '&type=' + type;
        }
        
        function confirmDelete(trackingCode) {
            if (confirm('Are you sure you want to delete this complaint? This action cannot be undone.')) {
                window.location.href = 'manage_complaints.php?delete=' + trackingCode;
            }
        }
        
        function closeModal() {
            document.getElementById('statusModal').style.display = 'none';
        }
        
        window.onclick = function(event) {
            const modal = document.getElementById('statusModal');
            if (event.target == modal) {
                closeModal();
            }
        }
        
        setTimeout(() => {
            document.querySelectorAll('.auth-alert').forEach(alert => {
                alert.style.transition = 'opacity 0.5s';
                alert.style.opacity = '0';
                setTimeout(() => alert.style.display = 'none', 500);
            });
        }, 5000);
    </script>
</body>
</html>