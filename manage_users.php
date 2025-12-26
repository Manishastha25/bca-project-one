<?php
session_start();
require_once 'db.php'; // This uses mysqli connection

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] != 'admin') {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$user_name = $_SESSION['user_name'];

// Initialize variables
$error = '';
$success = '';
$users = [];

// Get all users
$sql = "SELECT * FROM users ORDER BY created_at DESC";
$result = mysqli_query($conn, $sql);

if ($result) {
    $users = mysqli_fetch_all($result, MYSQLI_ASSOC);
} else {
    $error = "Error fetching users: " . mysqli_error($conn);
}

// Handle add user
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_user'])) {
    $name = mysqli_real_escape_string($conn, $_POST['name'] ?? '');
    $email = mysqli_real_escape_string($conn, $_POST['email'] ?? '');
    $phone = mysqli_real_escape_string($conn, $_POST['phone'] ?? '');
    $password = $_POST['password'] ?? '';
    $ward_number = mysqli_real_escape_string($conn, $_POST['ward_number'] ?? '');
    $address = mysqli_real_escape_string($conn, $_POST['address'] ?? '');
    $user_type = mysqli_real_escape_string($conn, $_POST['user_type'] ?? 'citizen');

    // Validation
    if (empty($name) || empty($email) || empty($password) || empty($ward_number)) {
        $error = "Please fill all required fields!";
    } else {
        // Check if email already exists
        $check_sql = "SELECT id FROM users WHERE email = '$email'";
        $check_result = mysqli_query($conn, $check_sql);
        
        if (mysqli_num_rows($check_result) > 0) {
            $error = "Email already exists!";
        } else {
            // Hash password
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            
            // Insert new user
            $insert_sql = "INSERT INTO users 
                (name, email, phone, password, ward_number, address, user_type, created_at) 
                VALUES ('$name', '$email', '$phone', '$hashed_password', 
                '$ward_number', '$address', '$user_type', NOW())";
            
            if (mysqli_query($conn, $insert_sql)) {
                $success = "User added successfully!";
                // Refresh user list
                $result = mysqli_query($conn, "SELECT * FROM users ORDER BY created_at DESC");
                $users = mysqli_fetch_all($result, MYSQLI_ASSOC);
            } else {
                $error = "Failed to add user: " . mysqli_error($conn);
            }
        }
    }
}

// Handle delete user
if (isset($_GET['delete_id'])) {
    $delete_id = mysqli_real_escape_string($conn, $_GET['delete_id']);
    
    // Prevent admin from deleting themselves
    if ($delete_id == $user_id) {
        $error = "You cannot delete your own account!";
    } else {
        $delete_sql = "DELETE FROM users WHERE id = '$delete_id'";
        
        if (mysqli_query($conn, $delete_sql)) {
            if (mysqli_affected_rows($conn) > 0) {
                $success = "User deleted successfully!";
                // Refresh user list
                $result = mysqli_query($conn, "SELECT * FROM users ORDER BY created_at DESC");
                $users = mysqli_fetch_all($result, MYSQLI_ASSOC);
            }
        } else {
            $error = "Error deleting user: " . mysqli_error($conn);
        }
    }
}

// Handle edit user
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['edit_user'])) {
    $edit_id = mysqli_real_escape_string($conn, $_POST['edit_id'] ?? '');
    $name = mysqli_real_escape_string($conn, $_POST['name'] ?? '');
    $email = mysqli_real_escape_string($conn, $_POST['email'] ?? '');
    $phone = mysqli_real_escape_string($conn, $_POST['phone'] ?? '');
    $ward_number = mysqli_real_escape_string($conn, $_POST['ward_number'] ?? '');
    $address = mysqli_real_escape_string($conn, $_POST['address'] ?? '');
    $user_type = mysqli_real_escape_string($conn, $_POST['user_type'] ?? 'citizen');
    $password = $_POST['password'] ?? '';
    
    // Check if email already exists for another user
    $check_sql = "SELECT id FROM users WHERE email = '$email' AND id != '$edit_id'";
    $check_result = mysqli_query($conn, $check_sql);
    
    if (mysqli_num_rows($check_result) > 0) {
        $error = "Email already exists for another user!";
    } else {
        if (!empty($password)) {
            // Update with new password
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            $update_sql = "UPDATE users SET 
                name = '$name', 
                email = '$email', 
                phone = '$phone', 
                password = '$hashed_password', 
                ward_number = '$ward_number', 
                address = '$address', 
                user_type = '$user_type' 
                WHERE id = '$edit_id'";
        } else {
            // Update without changing password
            $update_sql = "UPDATE users SET 
                name = '$name', 
                email = '$email', 
                phone = '$phone', 
                ward_number = '$ward_number', 
                address = '$address', 
                user_type = '$user_type' 
                WHERE id = '$edit_id'";
        }
        
        if (mysqli_query($conn, $update_sql)) {
            $success = "User updated successfully!";
            // Refresh user list
            $result = mysqli_query($conn, "SELECT * FROM users ORDER BY created_at DESC");
            $users = mysqli_fetch_all($result, MYSQLI_ASSOC);
        } else {
            $error = "Error updating user: " . mysqli_error($conn);
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Users - Lalitpur Complaint System</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="auth_style.css">
    <style>
        .users-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
            background: white;
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .users-table th, .users-table td {
            padding: 15px;
            text-align: left;
            border-bottom: 1px solid #e0e0e0;
        }
        
        .users-table th {
            background: var(--primary);
            color: white;
            font-weight: 600;
        }
        
        .users-table tr:hover {
            background: #f8f9fa;
        }
        
        .user-actions {
            display: flex;
            gap: 10px;
        }
        
        .action-btn {
            padding: 8px 15px;
            border-radius: 5px;
            border: none;
            cursor: pointer;
            font-size: 14px;
            display: inline-flex;
            align-items: center;
            gap: 5px;
            transition: all 0.3s;
        }
        
        .edit-btn {
            background: var(--info);
            color:red;
        }
        
        .delete-btn {
            background: var(--danger);
            color: red;
        }
        
        .user-badge {
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 500;
        }
        
        .badge-admin {
            background: #ff6b6b;
            color: white;
        }
        
        .badge-citizen {
            background: #4ecdc4;
            color: white;
        }
        
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            z-index: 1000;
            justify-content: center;
            align-items: center;
        }
        
        .modal-content {
            background: white;
            padding: 30px;
            border-radius: 10px;
            max-width: 500px;
            width: 90%;
            max-height: 80vh;
            overflow-y: auto;
        }
        
        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }
        
        .close-modal {
            background: none;
            border: none;
            font-size: 24px;
            cursor: pointer;
            color: #666;
        }
        
        .stats-cards {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .stat-card {
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            text-align: center;
        }
        
        .stat-card i {
            font-size: 40px;
            margin-bottom: 10px;
            color: var(--primary);
        }
        
        .stat-number {
            font-size: 32px;
            font-weight: 700;
            color: var(--primary);
        }
        
        .stat-label {
            color: #666;
            margin-top: 5px;
        }
    </style>
</head>
<body>
    <header class="dashboard-header">
        <div class="container">
            <div class="dashboard-header-content">
                <div class="dashboard-logo">
                    <i class="fas fa-landmark"></i>
                    <div>
                        <h1>नगरपालिका</h1>
                        <span>Lalitpur Complaint System</span>
                    </div>
                </div>
                <nav class="dashboard-nav">
                    <ul>
                        <li><a href="admin_dashboard.php">Dashboard</a></li>
                        <li><a href="manage_complaints.php">Manage Complaints</a></li>
                        <li><a href="manage_users.php" class="active">Manage Users</a></li>
                        <li><a href="reports.php">Reports</a></li>
                    </ul>
                </nav>
                <div class="dashboard-user-info">
                    <span><i class="fas fa-user-shield"></i> <?php echo htmlspecialchars($user_name); ?></span>
                    <a href="logout.php" class="auth-button">Logout</a>
                </div>
            </div>
        </div>
    </header>

    <section class="dashboard-section">
        <div class="container">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem;">
                <h2>User Management</h2>
                <button onclick="openAddUserModal()" class="auth-button">
                    <i class="fas fa-user-plus"></i> Add New User
                </button>
            </div>
            
            <!-- Stats Cards -->
            <div class="stats-cards">
                <?php
                $total_users = count($users);
                $admin_count = 0;
                $citizen_count = 0;
                
                foreach ($users as $user) {
                    if ($user['user_type'] == 'admin') {
                        $admin_count++;
                    } else {
                        $citizen_count++;
                    }
                }
                ?>
                <div class="stat-card">
                    <i class="fas fa-users"></i>
                    <div class="stat-number"><?php echo $total_users; ?></div>
                    <div class="stat-label">Total Users</div>
                </div>
                <div class="stat-card">
                    <i class="fas fa-user-shield"></i>
                    <div class="stat-number"><?php echo $admin_count; ?></div>
                    <div class="stat-label">Administrators</div>
                </div>
                <div class="stat-card">
                    <i class="fas fa-user"></i>
                    <div class="stat-number"><?php echo $citizen_count; ?></div>
                    <div class="stat-label">Citizens</div>
                </div>
            </div>
            
            <!-- Success/Error Messages -->
            <?php if($success): ?>
                <div class="auth-alert auth-alert-success">
                    <i class="fas fa-check-circle"></i>
                    <div>
                        <strong>Success!</strong> <?php echo $success; ?>
                    </div>
                </div>
            <?php endif; ?>
            
            <?php if($error): ?>
                <div class="auth-alert auth-alert-danger">
                    <i class="fas fa-exclamation-circle"></i>
                    <div>
                        <strong>Error:</strong> <?php echo $error; ?>
                    </div>
                </div>
            <?php endif; ?>
            
            <!-- Users Table -->
            <div class="auth-form-container">
                <h3 class="auth-form-title">All Users</h3>
                
                <?php if(empty($users)): ?>
                    <div style="text-align: center; padding: 2rem;">
                        <i class="fas fa-users-slash" style="font-size: 3rem; color: #ddd; margin-bottom: 1rem;"></i>
                        <p>No users found.</p>
                    </div>
                <?php else: ?>
                    <table class="users-table">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Name</th>
                                <th>Email</th>
                                <th>Phone</th>
                                <th>Ward</th>
                                <th>Type</th>
                                <th>Joined</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($users as $user): ?>
                                <tr>
                                    <td><?php echo $user['id']; ?></td>
                                    <td><?php echo htmlspecialchars($user['name']); ?></td>
                                    <td><?php echo htmlspecialchars($user['email']); ?></td>
                                    <td><?php echo htmlspecialchars($user['phone'] ?? 'N/A'); ?></td>
                                    <td>Ward <?php echo htmlspecialchars($user['ward_number']); ?></td>
                                    <td>
                                        <span class="user-badge <?php echo $user['user_type'] == 'admin' ? 'badge-admin' : 'badge-citizen'; ?>">
                                            <?php echo ucfirst($user['user_type']); ?>
                                        </span>
                                    </td>
                                    <td><?php echo date('M d, Y', strtotime($user['created_at'])); ?></td>
                                    <td>
                                        <div class="user-actions">
                                            <button onclick="openEditUserModal(<?php echo htmlspecialchars(json_encode($user)); ?>)" 
                                                    class="action-btn edit-btn">
                                                <i class="fas fa-edit"></i> Edit
                                            </button>
                                            <?php if($user['id'] != $user_id): ?>
                                                <a href="?delete_id=<?php echo $user['id']; ?>" 
                                                   class="action-btn delete-btn"
                                                   onclick="return confirm('Are you sure you want to delete this user?')">
                                                    <i class="fas fa-trash"></i> Delete
                                                </a>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </div>
    </section>

    <!-- Add User Modal -->
    <div id="addUserModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Add New User</h3>
                <button class="close-modal" onclick="closeAddUserModal()">&times;</button>
            </div>
            <form method="POST" action="">
                <div class="auth-form-group">
                    <label for="name">Full Name *</label>
                    <input type="text" id="name" name="name" required>
                </div>
                
                <div class="auth-form-row">
                    <div class="auth-form-group">
                        <label for="email">Email *</label>
                        <input type="email" id="email" name="email" required>
                    </div>
                    <div class="auth-form-group">
                        <label for="phone">Phone</label>
                        <input type="text" id="phone" name="phone">
                    </div>
                </div>
                
                <div class="auth-form-group">
                    <label for="password">Password *</label>
                    <input type="password" id="password" name="password" required>
                </div>
                
                <div class="auth-form-row">
                    <div class="auth-form-group">
                        <label for="ward_number">Ward Number *</label>
                        <select id="ward_number" name="ward_number" required>
                            <option value="">Select Ward</option>
                            <?php for($i = 1; $i <= 3; $i++): ?>
                                <option value="<?php echo $i; ?>">Ward <?php echo $i; ?></option>
                            <?php endfor; ?>
                        </select>
                    </div>
                    <div class="auth-form-group">
                        <label for="user_type">User Type *</label>
                        <select id="user_type" name="user_type" required>
                            <option value="citizen">Citizen</option>
                            <option value="admin">Administrator</option>
                        </select>
                    </div>
                </div>
                
                <div class="auth-form-group">
                    <label for="address">Address</label>
                    <textarea id="address" name="address" rows="3"></textarea>
                </div>
                
                <div class="auth-action-buttons">
                    <button type="button" class="auth-button auth-button-secondary" onclick="closeAddUserModal()">Cancel</button>
                    <button type="submit" name="add_user" class="auth-button">Add User</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Edit User Modal -->
    <div id="editUserModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Edit User</h3>
                <button class="close-modal" onclick="closeEditUserModal()">&times;</button>
            </div>
            <form method="POST" action="">
                <input type="hidden" id="edit_id" name="edit_id">
                
                <div class="auth-form-group">
                    <label for="edit_name">Full Name *</label>
                    <input type="text" id="edit_name" name="name" required>
                </div>
                
                <div class="auth-form-row">
                    <div class="auth-form-group">
                        <label for="edit_email">Email *</label>
                        <input type="email" id="edit_email" name="email" required>
                    </div>
                    <div class="auth-form-group">
                        <label for="edit_phone">Phone</label>
                        <input type="text" id="edit_phone" name="phone">
                    </div>
                </div>
                
                <div class="auth-form-group">
                    <label for="edit_password">New Password (leave blank to keep current)</label>
                    <input type="password" id="edit_password" name="password">
                </div>
                
                <div class="auth-form-row">
                    <div class="auth-form-group">
                        <label for="edit_ward_number">Ward Number *</label>
                        <select id="edit_ward_number" name="ward_number" required>
                            <?php for($i = 1; $i <= 32; $i++): ?>
                                <option value="<?php echo $i; ?>">Ward <?php echo $i; ?></option>
                            <?php endfor; ?>
                        </select>
                    </div>
                    <div class="auth-form-group">
                        <label for="edit_user_type">User Type *</label>
                        <select id="edit_user_type" name="user_type" required>
                            <option value="citizen">Citizen</option>
                            <option value="admin">Administrator</option>
                        </select>
                    </div>
                </div>
                
                <div class="auth-form-group">
                    <label for="edit_address">Address</label>
                    <textarea id="edit_address" name="address" rows="3"></textarea>
                </div>
                
                <div class="auth-action-buttons">
                    <button type="button" class="auth-button auth-button-secondary" onclick="closeEditUserModal()">Cancel</button>
                    <button type="submit" name="edit_user" class="auth-button">Update User</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        // Modal functions
        function openAddUserModal() {
            document.getElementById('addUserModal').style.display = 'flex';
        }
        
        function closeAddUserModal() {
            document.getElementById('addUserModal').style.display = 'none';
        }
        
        function openEditUserModal(user) {
            document.getElementById('edit_id').value = user.id;
            document.getElementById('edit_name').value = user.name;
            document.getElementById('edit_email').value = user.email;
            document.getElementById('edit_phone').value = user.phone || '';
            document.getElementById('edit_ward_number').value = user.ward_number;
            document.getElementById('edit_user_type').value = user.user_type;
            document.getElementById('edit_address').value = user.address || '';
            
            document.getElementById('editUserModal').style.display = 'flex';
        }
        
        function closeEditUserModal() {
            document.getElementById('editUserModal').style.display = 'none';
        }
        
        // Close modals when clicking outside
        window.onclick = function(event) {
            if (event.target.classList.contains('modal')) {
                event.target.style.display = 'none';
            }
        }
    </script>
</body>
</html>