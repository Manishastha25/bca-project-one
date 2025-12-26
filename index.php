<?php
session_start();
include 'db.php';

// Get categories for dropdown
$categories = [];
$result = mysqli_query($conn, "SELECT * FROM categories");
if ($result) {
    $categories = mysqli_fetch_all($result, MYSQLI_ASSOC);
}

// Process form if submitted
$success_message = '';
$error_message = '';
$tracking_code = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['submit_complaint'])) {
    // Get form data
    $name = mysqli_real_escape_string($conn, $_POST['name']);
    $email = mysqli_real_escape_string($conn, $_POST['email'] ?? '');
    $phone = mysqli_real_escape_string($conn, $_POST['phone']);
    $ward = mysqli_real_escape_string($conn, $_POST['ward']);
    $municipality = mysqli_real_escape_string($conn, $_POST['municipality'] ?? 'Lalitpur');
    $address = mysqli_real_escape_string($conn, $_POST['address']);
    $category = mysqli_real_escape_string($conn, $_POST['category']);
    $complaint_details = mysqli_real_escape_string($conn, $_POST['complaint']);
    
    // Check if user is logged in
    $is_logged_in = isset($_SESSION['user_id']);
    $user_id = $is_logged_in ? $_SESSION['user_id'] : null;
    
    // Generate tracking code
    $tracking_prefix = $is_logged_in ? 'LC-' : 'GC-';
    $tracking_code = $tracking_prefix . date('Ymd') . '-' . strtoupper(substr(md5(time()), 0, 6));
    
    if ($is_logged_in) {
        // Insert into complaints table (logged-in users)
        $query = "INSERT INTO complaints (user_id, category_id, title, description, ward_number, address, tracking_code) 
                  VALUES ('$user_id', '$category', 'Complaint from $name', '$complaint_details', '$ward', '$address', '$tracking_code')";
    } else {
        // Insert into guest_complaints table (guest users)
        $query = "INSERT INTO guest_complaints (name, email, phone, ward_number, municipality, address, category_id, complaint_details, tracking_code) 
                  VALUES ('$name', '$email', '$phone', '$ward', '$municipality', '$address', '$category', '$complaint_details', '$tracking_code')";
    }
    
    if (mysqli_query($conn, $query)) {
        $complaint_id = mysqli_insert_id($conn);
        
        // Handle file upload
        if (isset($_FILES['file']) && $_FILES['file']['error'] == UPLOAD_ERR_OK) {
            $file = $_FILES['file'];
            
            if ($file['size'] <= 2 * 1024 * 1024) {
                $allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
                $file_type = mime_content_type($file['tmp_name']);
                
                if (in_array($file_type, $allowed_types)) {
                    $upload_dir = 'uploads/';
                    if (!file_exists($upload_dir)) {
                        mkdir($upload_dir, 0777, true);
                    }
                    
                    $file_name = time() . '_' . basename($file['name']);
                    $file_path = $upload_dir . $file_name;
                    
                    if (move_uploaded_file($file['tmp_name'], $file_path)) {
                        // Update complaint with image path
                        if ($is_logged_in) {
                            $update_query = "UPDATE complaints SET image_path = '$file_path' WHERE id = '$complaint_id'";
                        } else {
                            $update_query = "UPDATE guest_complaints SET image_path = '$file_path' WHERE id = '$complaint_id'";
                        }
                        mysqli_query($conn, $update_query);
                    }
                }
            }
        }
        
        $success_message = "Complaint submitted successfully! Your Tracking Code: <strong>$tracking_code</strong>";
        
    } else {
        $error_message = "Error submitting complaint. Please try again.";
    }
}

// Check if user is logged in
$is_logged_in = isset($_SESSION['user_id']);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Nagarpalika - Nepal Municipal Complaint System</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="style.css">
</head>
<body>

    <header>
        <div class="container">
            <div class="header-content">
                <div class="logo">
                    <i class="fas fa-landmark"></i>
                    <div>
                        <h1>नगरपालिका</h1>
                        <span>Lalitpur Complaint System</span>
                    </div>
                </div>
                   <nav>
                    <ul><li><a href="#">Home</a></li>
                        <li><a href="#complaint">Submit Complaint</a></li>
                        <li><a href="track_complaint.php">Track Status</a></li>
                        <li><a href="#features">Services</a></li>
                        <li><a href="#contact">Contact</a></li>
                    </ul>
                </nav>
    <div class="header-buttons">
                    <?php if($is_logged_in): ?>
                        <span class="welcome-text">Welcome, <?php echo htmlspecialchars($_SESSION['user_name']); ?></span>
                        <a href="dashboard.php" class="button">Dashboard</a>
                        <a href="logout.php" class="button">Logout</a>
                    <?php else: ?>
                        <a href="login.php" class="button">Login</a>
                        <a href="signup.php" class="button">Signup</a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </header>

    <section class="hero">
        <div class="container">
            <h2>Municipal Services & Complaint Portal</h2>
            <p>Report issues, track resolution status, and help us make your municipality better. Together we can build a cleaner, more efficient community.</p>
            <a href="#complaint" class="button">File a Complaint</a>
        </div>
    </section>

    <section id="features">
        <div class="container">
            <div class="section-title">
                <h2>Our Services</h2>
                <p>We provide various municipal services to citizens and address complaints efficiently</p>
            </div>
 <div class="features-grid">
                <div class="feature-card">
                    <i class="fas fa-trash-alt"></i>
                    <h3>Waste Management</h3>
                    <p>Report garbage collection issues, illegal dumping, or request new bins</p>
                </div>
                <div class="feature-card">
         <i class="fas fa-road"></i>
                    <h3>Road Maintenance</h3>
                    <p>Report potholes, damaged roads, or request new road construction</p>
                </div>
                <div class="feature-card">
                    <i class="fas fa-tint"></i>
                    <h3>Water Supply</h3>
                    <p>Report water leakage, quality issues, or supply problems</p>
                </div>
                <div class="feature-card">
                    <i class="fas fa-lightbulb"></i>
                    <h3>Street Lighting</h3>
                    <p>Report faulty street lights or request new lighting installations</p></div>
                <div class="feature-card">
                    <i class="fas fa-tree"></i>
                    <h3>Parks & Environment</h3>
                    <p>Report issues with public parks, green spaces, or environmental concerns</p>
                </div>
                <div class="feature-card">
                    <i class="fas fa-id-card"></i>
                    <h3>Document Services</h3>
                    <p>Apply for certificates, licenses, and other municipal documents</p>
                </div>
            </div>
        </div>
    </section>

    <section id="complaint" class="complaint-form">
        <div class="container">
            <div class="section-title">
                <h2>File a Complaint</h2>
                <p>Help us improve your municipality by reporting issues and concerns</p>
            </div>
            
            <?php if($success_message): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i> <?php echo $success_message; ?>
                    <div class="tracking-code"><?php echo $tracking_code; ?></div>
                    <p style="margin-top: 10px; font-size: 0.9em;">
                        <a href="track_complaint.php" class="button small-btn">
                            <i class="fas fa-search"></i> Track Status
                        </a>
                    </p>
                </div>
                
            <?php endif; ?>
            
            <?php if($error_message): ?>
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-circle"></i> <?php echo $error_message; ?>
                </div>
            <?php endif; ?>
            
            <?php if($is_logged_in): ?>
                <div class="form-note">
                    <i class="fas fa-user-check"></i> 
                    You are logged in as <strong><?php echo htmlspecialchars($_SESSION['user_name']); ?></strong>. 
                    Your complaints will be linked to your account.
                </div>
            <?php else: ?>
                <div class="form-note">
                    <i class="fas fa-info-circle"></i> 
                    You are not logged in. You can still submit complaints as a guest. 
                    We recommend <a href="login.php" class="login-link">logging in</a> 
                    to track all your complaints in one place.
                </div>
            <?php endif; ?>
            
            <div class="form-container">
                <form id="complaintForm" method="POST" action="" enctype="multipart/form-data">
                    <div class="form-row">
                        <div class="form-group">
                            <label for="name">Full Name *</label>
                            <input type="text" id="name" name="name" 
                                   value="<?php echo $is_logged_in ? htmlspecialchars($_SESSION['user_name']) : (isset($_POST['name']) ? htmlspecialchars($_POST['name']) : ''); ?>" 
                                   placeholder="Enter your full name" 
                                   required>
                        </div>
                        <div class="form-group">
                            <label for="phone">Phone Number *</label>
                            <input type="tel" id="phone" name="phone" 
                                   value="<?php echo isset($_POST['phone']) ? htmlspecialchars($_POST['phone']) : ''; ?>" 
                                   placeholder="Enter your phone number" 
                                   required>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="email">Email Address</label>
                        <input type="email" id="email" name="email" 
                               value="<?php echo $is_logged_in ? (isset($_SESSION['user_email']) ? htmlspecialchars($_SESSION['user_email']) : '') : (isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''); ?>" 
                               placeholder="Enter your email address">
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="ward">Ward Number *</label>
                            <select id="ward" name="ward" required>
                                <option value="">Select Ward</option>
                                <?php for($i = 1; $i <= 3; $i++): ?>
                                    <option value="<?php echo $i; ?>" <?php echo (isset($_POST['ward']) && $_POST['ward'] == $i) ? 'selected' : ''; ?>>
                                        Ward <?php echo $i; ?>
                                    </option>
                                <?php endfor; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="municipality">Municipality</label>
                            <input type="text" id="municipality" name="municipality" 
                                   value="<?php echo isset($_POST['municipality']) ? htmlspecialchars($_POST['municipality']) : 'Lalitpur'; ?>" 
                                   placeholder="Enter your municipality">
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="address">Full Address *</label>
                        <input type="text" id="address" name="address" 
                               value="<?php echo isset($_POST['address']) ? htmlspecialchars($_POST['address']) : ''; ?>" 
                               placeholder="Enter your full address" 
                               required>
                    </div>
                    
                    <div class="form-group">
                        <label for="category">Complaint Category *</label>
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
                    
                    <div class="form-group">
                        <label for="complaint">Complaint Details *</label>
                        <textarea id="complaint" name="complaint" 
                                  placeholder="Please describe your issue in detail" 
                                  required><?php echo isset($_POST['complaint']) ? htmlspecialchars($_POST['complaint']) : ''; ?></textarea>
                    </div>
                    
                    <div class="form-group">
                        <label for="file">Upload Photo *</label>
                        <input type="file" id="file" name="file" accept="image/*" required>
                        <small> Supported formats: JPG, PNG, GIF</small>
                    </div>
                    
                    <button type="submit" name="submit_complaint" class="button btn-submit">
                        <i class="fas fa-paper-plane"></i> Submit Complaint
                    </button>
                </form>
            </div>
        </div>
    </section>

    <section class="map-section">
        <div class="container">
            <div class="section-title">
                <h2>Complaint Map</h2>
                <p>View recent complaints and their status across the municipality</p>
            </div>
            <div class="map-container">
                <div class="map-placeholder">
                    <i class="fas fa-map-marker-alt"></i>
                    <p>Municipality Map Would Appear Here</p>
                </div>
            </div>
        </div>
    </section>

    <footer>
        <div class="container">
            <div class="footer-content">
                <section id="contact" class="footer-section">
                    <h3>Contact Information</h3>
                    <p><i class="fas fa-map-marker-alt"></i> Municipal Office, Main Road, Lalitpur</p>
                    <p><i class="fas fa-phone"></i> +977-1-XXXXXXX</p>
                    <p><i class="fas fa-envelope"></i> info@nagarpalika.gov.np</p>
                </section>
                <div class="footer-section">
                    <h3>Quick Links</h3>
                    <a href="#">Home</a>
                    <a href="#complaint">Submit Complaint</a>
                    <a href="track_complaint.php">Track Status</a>
                    <a href="#features">Services</a>
                    <a href="#">About Us</a>
                </div>
                <div class="footer-section">
                    <h3>Office Hours</h3>
                    <p>Sunday - Thursday: 10AM - 5PM</p>
                    <p>Friday: 10AM - 3PM</p>
                    <p>Saturday: Closed</p>
                    <div class="social-icons">
                        <a href="#"><i class="fab fa-facebook-f"></i></a>
                        <a href="#"><i class="fab fa-twitter"></i></a>
                        <a href="#"><i class="fab fa-instagram"></i></a>
                        <a href="#"><i class="fab fa-youtube"></i></a>
                    </div>
                </div>
            </div>
            <div class="copyright">
                <p>&copy; <?php echo date('Y'); ?> Nagarpalika Municipal Office. All rights reserved.</p>
            </div>
        </div>
    </footer>

    <script>
        // Auto-scroll to complaint section after form submission
        <?php if($success_message || $error_message): ?>
            document.addEventListener('DOMContentLoaded', function() {
                document.getElementById('complaint').scrollIntoView({
                    behavior: 'smooth'
                });
            });
        <?php endif; ?>
        
        // Form validation
        document.getElementById('complaintForm').addEventListener('submit', function(e) {
            const phone = document.getElementById('phone').value;
            const phonePattern = /^[0-9]{10}$/;
            
            if (!phonePattern.test(phone)) {
                alert('Please enter a valid 10-digit phone number');
                e.preventDefault();
                return false;
            }
            
            const fileInput = document.getElementById('file');
            if (fileInput.files.length > 0) {
                const fileSize = fileInput.files[0].size / 1024 / 1024; // in MB
                if (fileSize > 2) {
                    alert('File size exceeds 2MB limit');
                    e.preventDefault();
                    return false;
                }
            }
            
            return true;
        });
    </script>

</body>
</html>