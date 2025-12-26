<?php
session_start();
require_once 'db.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$user_name = $_SESSION['user_name'];

// Get categories using MySQLi
$categories = array();
$categories_query = "SELECT * FROM categories";
$categories_result = $conn->query($categories_query);

if ($categories_result && $categories_result->num_rows > 0) {
    while($row = $categories_result->fetch_assoc()) {
        $categories[] = $row;
    }
}

$error = '';
$success = '';
$tracking_code = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['submit_complaint'])) {
    
    // Get form data
    $category = $_POST['category'] ?? '';
    $ward = $_POST['ward'] ?? '';
    $address = $_POST['address'] ?? '';
    $complaint_details = $_POST['complaint'] ?? '';
    
    // Simple validation
    if (empty($category) || empty($ward) || empty($address) || empty($complaint_details)) {
        $error = "Please fill all required fields!";
    } else {
        // Generate tracking code
        $tracking_code = 'LC-' . date('Ymd') . '-' . strtoupper(substr(md5(time() . $user_id), 0, 6));
        
        // Handle file upload
        $image_path = '';
        if (isset($_FILES['complaint_image']) && $_FILES['complaint_image']['error'] == 0) {
            $allowed_types = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif'];
            $file_type = $_FILES['complaint_image']['type'];
            $file_size = $_FILES['complaint_image']['size'];
            
            // Check file type
            if (in_array($file_type, $allowed_types)) {
                // Check file size (max 5MB)
                if ($file_size <= 5 * 1024 * 1024) {
                    // Create uploads directory if it doesn't exist
                    $upload_dir = 'uploads/complaints/';
                    if (!file_exists($upload_dir)) {
                        mkdir($upload_dir, 0777, true);
                    }
                    
                    // Generate unique filename
                    $file_extension = pathinfo($_FILES['complaint_image']['name'], PATHINFO_EXTENSION);
                    $filename = 'complaint_' . time() . '_' . uniqid() . '.' . $file_extension;
                    $upload_path = $upload_dir . $filename;
                    
                    // Move uploaded file
                    if (move_uploaded_file($_FILES['complaint_image']['tmp_name'], $upload_path)) {
                        $image_path = $upload_path;
                    } else {
                        $error = "Failed to upload image.";
                    }
                } else {
                    $error = "Image size must be less than 5MB.";
                }
            } else {
                $error = "Only JPG, JPEG, PNG & GIF files are allowed.";
            }
        }
        
        // If no error with image upload, proceed
        if (empty($error)) {
            // Prepare title
            $title = "Complaint from $user_name (Ward $ward)";
            
            // Insert into database using MySQLi prepared statement
            $stmt = $conn->prepare("INSERT INTO complaints 
                                  (user_id, category_id, title, description, ward_number, address, tracking_code, image_path, status, created_at) 
                                  VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'pending', NOW())");
            
            if ($stmt) {
                $stmt->bind_param("iissssss", 
                    $user_id, 
                    $category, 
                    $title, 
                    $complaint_details,
                    $ward, 
                    $address, 
                    $tracking_code,
                    $image_path
                );
                
                if ($stmt->execute()) {
                    $complaint_id = $stmt->insert_id;
                    $success = "Complaint submitted successfully! Tracking Code: $tracking_code";
                    
                    // Clear form after success
                    $_POST = array();
                } else {
                    $error = "Failed to submit complaint: " . $stmt->error;
                }
                $stmt->close();
            } else {
                $error = "Database error: " . $conn->error;
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
    <title>Submit Complaint</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="auth_style.css">
    
  
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
                        <li><a href="dashboard.php">Dashboard</a></li>
                        <li><a href="submit_complaint.php" class="active">File Complaint</a></li>
                        <li><a href="track_complaint.php">Track Status</a></li>
                    </ul>
                </nav>
                <div class="dashboard-user-info">
                    <span><?php echo htmlspecialchars($user_name); ?></span>
                    <a href="logout.php" class="auth-button">Logout</a>
                </div>
            </div>
        </div>
    </header>

    <section class="dashboard-section">
        <div class="container">
            <div style="margin-bottom: 2rem;">
                <h2>File a New Complaint</h2>
                <a href="dashboard.php" class="auth-button auth-button-secondary">
                    <i class="fas fa-arrow-left"></i> Back to Dashboard
                </a>
            </div>
            
            <!-- Success Message -->
            <?php if($success): ?>
                <div class="auth-alert auth-alert-success">
                    <i class="fas fa-check-circle"></i>
                    <div>
                        <strong>Success!</strong> <?php echo $success; ?>
                    </div>
                </div>
                <div style="text-align: center; margin: 2rem 0;">
                    <a href="submit_complaint.php" class="auth-button">
                        <i class="fas fa-plus"></i> File Another Complaint
                    </a>
                    <a href="track_complaint.php?tracking_code=<?php echo urlencode($tracking_code); ?>" 
                       class="auth-button" style="background: var(--info);">
                        <i class="fas fa-search"></i> Track This Complaint
                    </a>
                </div>
            <?php endif; ?>
            
            <!-- Error Message -->
            <?php if($error): ?>
                <div class="auth-alert auth-alert-danger">
                    <i class="fas fa-exclamation-circle"></i>
                    <div>
                        <strong>Error:</strong> <?php echo $error; ?>
                    </div>
                </div>
            <?php endif; ?>
            
            <!-- Complaint Form -->
            <?php if(!$success): ?>
                <div class="auth-form-container">
                    <h3 class="auth-form-title">Complaint Details</h3>
                    
                    <form method="POST" action="" enctype="multipart/form-data">
                        <!-- User Info -->
                        <div class="auth-form-group">
                            <label>Full Name</label>
                            <input type="text" value="<?php echo htmlspecialchars($user_name); ?>" readonly style="background: #f8f9fa;">
                        </div>
                        
                        <!-- Category and Ward -->
                        <div class="auth-form-row">
                            <div class="auth-form-group">
                                <label for="category">Category *</label>
                                <select id="category" name="category" required>
                                    <option value="">Select Category</option>
                                    <?php foreach($categories as $cat): ?>
                                        <option value="<?php echo $cat['id']; ?>" 
                                            <?php echo (isset($_POST['category']) && $_POST['category'] == $cat['id']) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($cat['category_name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="auth-form-group">
                                <label for="ward">Ward Number *</label>
                                <select id="ward" name="ward" required>
                                    <option value="">Select Ward</option>
                                    <?php for($i = 1; $i <= 3; $i++): ?>
                                        <option value="<?php echo $i; ?>" 
                                            <?php echo (isset($_POST['ward']) && $_POST['ward'] == $i) ? 'selected' : ''; ?>>
                                            Ward <?php echo $i; ?>
                                        </option>
                                    <?php endfor; ?>
                                </select>
                            </div>
                        </div>
                        
                        <!-- Address -->
                        <div class="auth-form-group">
                            <label for="address">Location/Address *</label>
                            <input type="text" id="address" name="address" 
                                   value="<?php echo htmlspecialchars($_POST['address'] ?? ''); ?>" 
                                   placeholder="Where exactly is the issue located?" 
                                   required>
                        </div>
                        
                        <!-- Complaint Details -->
                        <div class="auth-form-group">
                            <label for="complaint">Complaint Details *</label>
                            <textarea id="complaint" name="complaint" 
                                      placeholder="Describe the issue in detail..." 
                                      rows="6" required><?php echo htmlspecialchars($_POST['complaint'] ?? ''); ?></textarea>
                        </div>
                        
                        <!-- Image Upload -->
                        <div class="auth-form-group">
                            <label for="complaint_image">Upload Picture*</label>
                            <div class="file-upload-area" onclick="document.getElementById('complaint_image').click()">
                                <i class="fas fa-cloud-upload-alt"></i>
                                <p>Click to upload an image</p>
                                <span>Supports: JPG, JPEG, PNG, GIF (Max 5MB)</span>
                                <div class="file-name" id="file-name"></div>
                            </div>
                            <input type="file" id="complaint_image" name="complaint_image" 
                                   accept="image/*" style="display: none;" onchange="previewImage(this)" required>
                            
                            <div class="image-preview-container" id="image-preview-container">
                                <img src="" alt="Preview" class="image-preview" id="image-preview">
                            </div>
                        </div>
            
                        <!-- Submit Button -->
                        <div class="auth-action-buttons">
                            <button type="submit" name="submit_complaint" class="auth-button">
                                <i class="fas fa-paper-plane"></i> Submit Complaint
                            </button>
                        </div>
                    </form>
                </div>
            <?php endif; ?>
        </div>
    </section>

    <script>
        function previewImage(input) {
            const file = input.files[0];
            const fileName = document.getElementById('file-name');
            const previewContainer = document.getElementById('image-preview-container');
            const preview = document.getElementById('image-preview');
            
            if (file) {
                // Display file name
                fileName.textContent = 'Selected: ' + file.name;
                
                // Create and display preview
                const reader = new FileReader();
                reader.onload = function(e) {
                    preview.src = e.target.result;
                    previewContainer.style.display = 'block';
                }
                reader.readAsDataURL(file);
            } else {
                fileName.textContent = '';
                previewContainer.style.display = 'none';
                preview.src = '';
            }
        }
    </script>
</body>
</html>