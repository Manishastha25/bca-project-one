<?php
session_start();
require_once 'db.php';

// Check if user is logged in (for regular users)
$is_guest_tracking = isset($_GET['tracking_code']) && !isset($_SESSION['user_id']);

if (!isset($_SESSION['user_id']) && !$is_guest_tracking) {
    header("Location: login.php");
    exit();
}

// Get tracking code
$tracking_code = $_GET['tracking_code'] ?? '';

if (empty($tracking_code)) {
    $_SESSION['error'] = "Tracking code is required.";
    header("Location: " . (isset($_SESSION['user_id']) ? 'dashboard.php' : 'index.php'));
    exit();
}

// Check if user is admin
$is_admin = false;
if (isset($_SESSION['user_id'])) {
    $user_id = $_SESSION['user_id'];
    $result = mysqli_query($conn, "SELECT user_type FROM users WHERE id = '$user_id'");
    if ($result && $user = mysqli_fetch_assoc($result)) {
        $is_admin = ($user['user_type'] === 'admin');
    }
}

// AUTO-DETECT complaint type
$complaint = null;
$type = '';

// First check regular complaints table
$query = "SELECT c.*, u.name as user_name, u.email, u.phone, cat.category_name 
          FROM complaints c 
          LEFT JOIN users u ON c.user_id = u.id 
          LEFT JOIN categories cat ON c.category_id = cat.id 
          WHERE c.tracking_code = '$tracking_code'";
$result = mysqli_query($conn, $query);

if ($result && mysqli_num_rows($result) > 0) {
    // Found in regular complaints table
    $type = 'user';
    $complaint = mysqli_fetch_assoc($result);
} else {
    // Check guest complaints table
    $query = "SELECT gc.*, cat.category_name 
              FROM guest_complaints gc 
              LEFT JOIN categories cat ON gc.category_id = cat.id 
              WHERE gc.tracking_code = '$tracking_code'";
    $result = mysqli_query($conn, $query);
    
    if ($result && mysqli_num_rows($result) > 0) {
        // Found in guest complaints table
        $type = 'guest';
        $complaint = mysqli_fetch_assoc($result);
    } else {
        // Not found in either table
        $_SESSION['error'] = "Complaint not found.";
        header("Location: " . (isset($_SESSION['user_id']) ? ($is_admin ? 'manage_complaints.php' : 'dashboard.php') : 'index.php'));
        exit();
    }
}

// Set permissions - who can view this complaint
if ($type === 'guest') {
    // For guest complaints, check if user is admin OR has the tracking code
    $can_view = $is_admin || $is_guest_tracking;
} else {
    // For user complaints, check if user is admin OR is the complaint owner
    $can_view = $is_admin || (isset($_SESSION['user_id']) && $complaint && $complaint['user_id'] == $_SESSION['user_id']);
}

if (!$can_view) {
    $_SESSION['error'] = "You don't have permission to view this complaint.";
    header("Location: " . (isset($_SESSION['user_id']) ? 'dashboard.php' : 'index.php'));
    exit();
}

// Get status updates (for admin view) - WITH PROPER ERROR HANDLING
$updates = [];
if ($is_admin) {
    if ($type === 'user') {
        // Check if table exists first without causing error
        $table_check = mysqli_query($conn, "SHOW TABLES LIKE 'complaint_updates'");
        if ($table_check && mysqli_num_rows($table_check) > 0) {
            $query = "SELECT * FROM complaint_updates WHERE complaint_id = '{$complaint['id']}' ORDER BY created_at DESC";
            $result = mysqli_query($conn, $query);
            if ($result) {
                while ($row = mysqli_fetch_assoc($result)) {
                    $updates[] = $row;
                }
            }
        }
        // If table doesn't exist, just show empty updates (no error)
    } else {
        // Check if table exists first
        $table_check = mysqli_query($conn, "SHOW TABLES LIKE 'guest_complaint_updates'");
        if ($table_check && mysqli_num_rows($table_check) > 0) {
            $query = "SELECT * FROM guest_complaint_updates WHERE guest_complaint_id = '{$complaint['id']}' ORDER BY created_at DESC";
            $result = mysqli_query($conn, $query);
            if ($result) {
                while ($row = mysqli_fetch_assoc($result)) {
                    $updates[] = $row;
                }
            }
        }
        // If table doesn't exist, just show empty updates (no error)
    }
}

// Handle status update (admin only)
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_status']) && $is_admin) {
    $new_status = mysqli_real_escape_string($conn, $_POST['status']);
    $update_notes = mysqli_real_escape_string($conn, $_POST['update_notes'] ?? '');
    
    if ($type === 'user') {
        // Update regular complaint
        $query = "UPDATE complaints SET status = '$new_status' WHERE tracking_code = '$tracking_code'";
        mysqli_query($conn, $query);
        
        // Check if complaint_updates table exists before trying to insert
        $table_check = mysqli_query($conn, "SHOW TABLES LIKE 'complaint_updates'");
        if ($table_check && mysqli_num_rows($table_check) > 0) {
            $query = "INSERT INTO complaint_updates (complaint_id, status, description, updated_by) 
                      VALUES ('{$complaint['id']}', '$new_status', '$update_notes', '$user_id')";
            mysqli_query($conn, $query);
        }
    } else {
        // Update guest complaint
        $query = "UPDATE guest_complaints SET status = '$new_status' WHERE tracking_code = '$tracking_code'";
        mysqli_query($conn, $query);
        
        // Check if guest_complaint_updates table exists
        $table_check = mysqli_query($conn, "SHOW TABLES LIKE 'guest_complaint_updates'");
        if ($table_check && mysqli_num_rows($table_check) > 0) {
            $query = "INSERT INTO guest_complaint_updates (guest_complaint_id, status, description) 
                      VALUES ('{$complaint['id']}', '$new_status', '$update_notes')";
            mysqli_query($conn, $query);
        }
    }
    
    $_SESSION['success'] = "Status updated successfully!";
    header("Location: view_complaint.php?tracking_code=$tracking_code");
    exit();
}

// Get status badge class
$status_badge_class = '';
switch ($complaint['status']) {
    case 'pending': $status_badge_class = 'status-pending'; break;
    case 'in_progress': $status_badge_class = 'status-in_progress'; break;
    case 'resolved': $status_badge_class = 'status-resolved'; break;
    case 'rejected': $status_badge_class = 'status-rejected'; break;
}

// Process image path
$image_path = $complaint['image_path'] ?? '';
$image_exists = false;

if (!empty($image_path)) {
    // If path is relative and starts with uploads/
    if (strpos($image_path, 'uploads/') === 0) {
        // Check if file exists
        if (file_exists($image_path)) {
            $image_exists = true;
        } else {
            // Try with project folder prefix
            $project_path = 'project2/' . $image_path;
            if (file_exists($project_path)) {
                $image_path = $project_path;
                $image_exists = true;
            }
        }
    }
    // If it's just a filename
    elseif (!strpos($image_path, '/')) {
        $possible_paths = [
            'uploads/' . $image_path,
            'uploads/complaints/' . $image_path,
            'project2/uploads/' . $image_path,
            'project2/uploads/complaints/' . $image_path
        ];
        
        foreach ($possible_paths as $path) {
            if (file_exists($path)) {
                $image_path = $path;
                $image_exists = true;
                break;
            }
        }
    }
    // Try direct path
    elseif (file_exists($image_path)) {
        $image_exists = true;
    }
    
    // If still not found, try URL access
    if (!$image_exists) {
        // Try to construct URL path
        $url_path = 'http://' . $_SERVER['HTTP_HOST'] . '/' . ltrim($image_path, '/');
        // We'll try to use it anyway
        $image_path = $url_path;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Complaint - Nagarpalika</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600&display=swap" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Poppins', sans-serif;
        }
        
        body {
            background: #f5f7fa;
            color: #333;
            line-height: 1.6;
            padding: 20px;
        }
        
        .container {
            max-width: 1000px;
            margin: 0 auto;
            background:white;
            border-radius: 10px;
            padding: 30px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
        
        /* Alert Messages */
        .alert {
            padding: 12px 15px;
            border-radius: 6px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
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
        
        .alert-info {
            background: #d1ecf1;
            color: #0c5460;
            border: 1px solid #bee5eb;
        }
        
        /* Header */
        .header {
            background:white;
            border-bottom: 2px solid #eee;
            padding-bottom: 20px;
            margin-bottom: 25px;
        }
        
        .tracking-code {
            font-size: 24px;
            font-weight: 600;
            color: #3498db;
            margin-bottom: 10px;
        }
        
        .complaint-title {
            font-size: 20px;
            font-weight: 500;
            margin-bottom: 15px;
            color: #408cd8ff;
        }
        
        .status-badge {
            display: inline-block;
            padding: 8px 20px;
            border-radius: 20px;
            font-weight: 500;
            font-size: 14px;
        }
        
        .status-pending { background: #fff3cd; color: #856404; }
        .status-in_progress { background: #cce5ff; color: #004085; }
        .status-resolved { background: #d4edda; color: #155724; }
        .status-rejected { background: #f8d7da; color: #721c24; }
        
        /* Detail Grid */
        .detail-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 15px;
            margin-bottom: 25px;
        }
        
        .detail-item {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 8px;
        }
        
        .detail-label {
            font-size: 14px;
            color: #666;
            margin-bottom: 5px;
        }
        
        .detail-value {
            font-size: 16px;
            font-weight: 500;
            color: #333;
        }
        
        /* Description Box */
        .description-box {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 25px;
        }
        
        .description-label {
            font-size: 14px;
            color: #666;
            margin-bottom: 10px;
            font-weight: 500;
        }
        
        .description-content {
            color: #333;
            white-space: pre-wrap;
            line-height: 1.6;
        }
        
        /* Image Section */
        .image-section {
            margin-bottom: 25px;
        }
        
        .complaint-image {
            max-width: 100%;
            max-height: 400px;
            border-radius: 8px;
            margin-top: 10px;
            cursor: pointer;
            box-shadow: 0 3px 10px rgba(0,0,0,0.1);
            border: 1px solid #ddd;
            display: block;
        }
        
        .image-placeholder {
            background: #f8f9fa;
            padding: 40px;
            border-radius: 8px;
            text-align: center;
            color: #666;
            border: 2px dashed #ddd;
            margin-top: 10px;
        }
        
        .image-loading {
            background: #f8f9fa;
            padding: 40px;
            border-radius: 8px;
            text-align: center;
            color: #666;
            margin-top: 10px;
        }
        
        /* Status Update Form (Admin Only) */
        .status-update-form {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
            margin-top: 25px;
        }
        
        .form-group {
            margin-bottom: 15px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: 500;
            color: #333;
        }
        
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 6px;
            font-size: 16px;
        }
        
        .form-group textarea {
            min-height: 100px;
            resize: vertical;
        }
        
        .btn {
            background: linear-gradient(to right, darkblue, red);
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-size: 16px;
            font-weight: 500;
        }
        
        .btn:hover {
            opacity: 0.9;
        }
        
        /* Updates History */
        .updates-history {
            margin-top: 25px;
        }
        
        .update-item {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 10px;
            border-left: 4px solid #3498db;
        }
        
        .update-meta {
            display: flex;
            justify-content: space-between;
            margin-bottom: 8px;
            font-size: 14px;
            color: #666;
        }
        
        .update-status {
            font-weight: 500;
        }
        
        .update-description {
            color: #333;
            line-height: 1.5;
        }
        
        /* Action Buttons */
        .action-buttons {
            margin-top: 20px;
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }
        
        .btn-secondary {
            background: #6c757d;
        }
        
        .btn-secondary:hover {
            background: #5a6268;
        }
        
        /* Image Modal */
        .image-modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.9);
            padding: 50px 0;
        }
        
        .modal-content {
            display: block;
            margin: 0 auto;
            max-width: 90%;
            max-height: 90vh;
        }
        
        .close-modal {
            position: absolute;
            top: 20px;
            right: 30px;
            color: white;
            font-size: 40px;
            font-weight: bold;
            cursor: pointer;
        }
        
        /* Responsive */
        @media (max-width: 768px) {
            .container {
                padding: 15px;
            }
            
            .detail-grid {
                grid-template-columns: 1fr;
            }
            
            .tracking-code {
                font-size: 20px;
            }
            
            .complaint-title {
                font-size: 18px;
            }
            
            .action-buttons {
                flex-direction: column;
            }
            
            .btn {
                width: 100%;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Display Messages -->
        <?php if(isset($_SESSION['success'])): ?>
            <div class="alert alert-success">
                <span>✓</span> <?php echo $_SESSION['success']; unset($_SESSION['success']); ?>
            </div>
        <?php endif; ?>
        
        <?php if(isset($_SESSION['error'])): ?>
            <div class="alert alert-error">
                <span>!</span> <?php echo $_SESSION['error']; unset($_SESSION['error']); ?>
            </div>
        <?php endif; ?>
        
        <!-- Complaint Header -->
        <div class="header">
            <div class="tracking-code">
                Tracking Code: <?php echo htmlspecialchars($tracking_code); ?>
            </div>
            <div class="complaint-title">
                <?php echo htmlspecialchars($complaint['title'] ?? $complaint['complaint_details'] ?? 'No Title'); ?>
            </div>
            <div>
                <span class="status-badge <?php echo $status_badge_class; ?>">
                    <?php echo ucfirst(str_replace('_', ' ', $complaint['status'])); ?>
                </span>
                <span style="margin-left: 10px; color: #666; font-size: 14px;">
                    Submitted on: <?php echo date('F j, Y', strtotime($complaint['created_at'])); ?>
                </span>
            </div>
        </div>
        
        <!-- Complaint Details Grid -->
        <div class="detail-grid">
            <div class="detail-item">
                <div class="detail-label">Category</div>
                <div class="detail-value"><?php echo htmlspecialchars($complaint['category_name']); ?></div>
            </div>
            
            <div class="detail-item">
                <div class="detail-label">Ward Number</div>
                <div class="detail-value"><?php echo htmlspecialchars($complaint['ward_number']); ?></div>
            </div>
            
            <div class="detail-item">
                <div class="detail-label">Location/Address</div>
                <div class="detail-value"><?php echo htmlspecialchars($complaint['address'] ?? 'Not specified'); ?></div>
            </div>
            
            <?php if($type === 'user'): ?>
            <div class="detail-item">
                <div class="detail-label">Submitted By</div>
                <div class="detail-value"><?php echo htmlspecialchars($complaint['user_name']); ?></div>
            </div>
            
            <div class="detail-item">
                <div class="detail-label">Contact Email</div>
                <div class="detail-value"><?php echo htmlspecialchars($complaint['email']); ?></div>
            </div>
            
            <div class="detail-item">
                <div class="detail-label">Contact Phone</div>
                <div class="detail-value"><?php echo htmlspecialchars($complaint['phone']); ?></div>
            </div>
            <?php else: ?>
            <div class="detail-item">
                <div class="detail-label">Submitted By</div>
                <div class="detail-value"><?php echo htmlspecialchars($complaint['name']); ?> (Guest)</div>
            </div>
            
            <?php if(!empty($complaint['email'])): ?>
            <div class="detail-item">
                <div class="detail-label">Contact Email</div>
                <div class="detail-value"><?php echo htmlspecialchars($complaint['email']); ?></div>
            </div>
            <?php endif; ?>
            
            <?php if(!empty($complaint['phone'])): ?>
            <div class="detail-item">
                <div class="detail-label">Contact Phone</div>
                <div class="detail-value"><?php echo htmlspecialchars($complaint['phone']); ?></div>
            </div>
            <?php endif; ?>
            
            <div class="detail-item">
                <div class="detail-label">Municipality</div>
                <div class="detail-value"><?php echo htmlspecialchars($complaint['municipality']); ?></div>
            </div>
            <?php endif; ?>
            
            <div class="detail-item">
                <div class="detail-label">Last Updated</div>
                <div class="detail-value"><?php echo date('F j, Y h:i A', strtotime($complaint['updated_at'])); ?></div>
            </div>
        </div>
        
        <!-- Description -->
        <div class="description-box">
            <div class="description-label">Complaint Details:</div>
            <div class="description-content">
                <?php echo nl2br(htmlspecialchars($complaint['description'] ?? $complaint['complaint_details'])); ?>
            </div>
        </div>
        
        <!-- Image Section -->
        <div class="image-section">
            <div class="description-label">Attached Image:</div>
            
            <?php if(!empty($complaint['image_path'])): ?>
                <div id="image-container">
                    <img src="<?php echo htmlspecialchars($image_path); ?>" 
                         alt="Complaint Image" 
                         class="complaint-image"
                         onclick="openImageModal('<?php echo htmlspecialchars($image_path); ?>')"
                         onload="hideLoading()"
                         onerror="showImageError()">
                    <div id="image-loading" class="image-loading">
                        Loading image...
                    </div>
                    <div id="image-error" class="image-placeholder" style="display: none;">
                        <p>⚠️ Image failed to load</p>
                        <p>Please check if the image file exists in the server.</p>
                    </div>
                </div>
            <?php else: ?>
                <div class="image-placeholder">
                    <p>No image attached to this complaint</p>
                </div>
            <?php endif; ?>
        </div>
        
        <!-- Image Modal -->
        <div id="imageModal" class="image-modal">
            <span class="close-modal" onclick="closeImageModal()">&times;</span>
            <img class="modal-content" id="modalImage">
        </div>
        
        <!-- Status Update Form (Admin Only) -->
        <?php if($is_admin): ?>
        <div class="status-update-form">
            <h3>Update Status</h3>
            <form method="POST">
                <div class="form-group">
                    <label for="status">Status</label>
                    <select name="status" id="status" required>
                        <option value="pending" <?php echo $complaint['status'] == 'pending' ? 'selected' : ''; ?>>Pending</option>
                        <option value="in_progress" <?php echo $complaint['status'] == 'in_progress' ? 'selected' : ''; ?>>In Progress</option>
                        <option value="resolved" <?php echo $complaint['status'] == 'resolved' ? 'selected' : ''; ?>>Resolved</option>
                        <option value="rejected" <?php echo $complaint['status'] == 'rejected' ? 'selected' : ''; ?>>Rejected</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="update_notes">Update Notes (Optional)</label>
                    <textarea name="update_notes" id="update_notes" 
                              placeholder="Add any notes about this status update..."></textarea>
                </div>
                
                <button type="submit" name="update_status" class="btn">Update Status</button>
            </form>
        </div>
        <?php endif; ?>
        
        <!-- Status Updates History (Admin Only) -->
        <?php if($is_admin && !empty($updates)): ?>
        <div class="updates-history">
            <h3>Status History</h3>
            <?php foreach($updates as $update): ?>
                <div class="update-item">
                    <div class="update-meta">
                        <span class="update-status"><?php echo ucfirst(str_replace('_', ' ', $update['status'])); ?></span>
                        <span><?php echo date('M j, Y h:i A', strtotime($update['created_at'])); ?></span>
                    </div>
                    <?php if(!empty($update['description'])): ?>
                        <div class="update-description">
                            <?php echo nl2br(htmlspecialchars($update['description'])); ?>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
        
        <!-- Action Buttons -->
        <div class="action-buttons">
            <?php if($is_admin): ?>
                <a href="manage_complaints.php" class="btn btn-secondary">Back to Complaints</a>
            <?php else: ?>
                <a href="<?php echo isset($_SESSION['user_id']) ? 'dashboard.php' : 'index.php'; ?>" 
                   class="btn btn-secondary">
                   <?php echo isset($_SESSION['user_id']) ? 'Back to Dashboard' : 'Back to Home'; ?>
                </a>
            <?php endif; ?>
            
            <!-- Print button -->
            <button onclick="window.print()" class="btn">Print Details</button>
            
            <!-- Share tracking code -->
            <?php if(!$is_admin): ?>
            <button onclick="shareTrackingCode()" class="btn">Share Tracking Code</button>
            <?php endif; ?>
        </div>
    </div>

    <script>
        // Image modal functions
        function openImageModal(imageSrc) {
            const modal = document.getElementById('imageModal');
            const modalImg = document.getElementById('modalImage');
            modal.style.display = "block";
            modalImg.src = imageSrc;
        }

        function closeImageModal() {
            document.getElementById('imageModal').style.display = "none";
        }

        // Close modal when clicking outside
        window.onclick = function(event) {
            const modal = document.getElementById('imageModal');
            if (event.target === modal) {
                closeImageModal();
            }
        }

        // Close with Escape key
        document.addEventListener('keydown', function(event) {
            if (event.key === 'Escape') {
                closeImageModal();
            }
        });

        // Image loading handlers
        function hideLoading() {
            const loading = document.getElementById('image-loading');
            if (loading) {
                loading.style.display = 'none';
            }
        }

        function showImageError() {
            const loading = document.getElementById('image-loading');
            const error = document.getElementById('image-error');
            const image = document.querySelector('.complaint-image');
            
            if (loading) loading.style.display = 'none';
            if (error) error.style.display = 'block';
            if (image) image.style.display = 'none';
            
            // Hide loading after timeout
            setTimeout(hideLoading, 3000);
        }

        // Auto-hide image loading after timeout
        setTimeout(hideLoading, 5000);

        function shareTrackingCode() {
            const trackingCode = "<?php echo $tracking_code; ?>";
            const shareUrl = window.location.origin + '/project2/view_complaint.php?tracking_code=' + trackingCode;
            
            if (navigator.share) {
                navigator.share({
                    title: 'Complaint Tracking Code',
                    text: 'Track your complaint with this code: ' + trackingCode,
                    url: shareUrl
                });
            } else {
                // Fallback: Copy to clipboard
                navigator.clipboard.writeText(shareUrl).then(() => {
                    alert('Link copied to clipboard! Share this link to track the complaint.');
                });
            }
        }
        
        // Auto-hide alerts after 5 seconds
        setTimeout(() => {
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(alert => {
                alert.style.transition = 'opacity 0.5s';
                alert.style.opacity = '0';
                setTimeout(() => alert.remove(), 500);
            });
        }, 5000);
    </script>
</body>
</html>