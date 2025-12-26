<?php
// Start session and check if admin
session_start();
if ($_SESSION['user_type'] != 'admin') {
    header("Location: login.php");
    exit();
}

// Connect to database
$conn = mysqli_connect("localhost", "root", "", "complaint_system");

// If form is submitted
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $name = $_POST['name'];
    $email = $_POST['email'];
    $password = $_POST['password'];
    
    // Hash password
    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
    
    // Insert into database
    $sql = "INSERT INTO users (name, email, password) 
            VALUES ('$name', '$email', '$hashed_password')";
    
    if (mysqli_query($conn, $sql)) {
        echo "User added successfully!";
    } else {
        echo "Error: " . mysqli_error($conn);
    }
}
?>

<!-- Simple HTML Form -->
<form method="POST">
    Name: <input type="text" name="name" required><br>
    Email: <input type="email" name="email" required><br>
    Password: <input type="password" name="password" required><br>
    <button type="submit">Add User</button>
</form>