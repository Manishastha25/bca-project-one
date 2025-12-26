<?php
// view_users.php - Simple script to see users and passwords
require_once 'db.php';

echo "<h2>All Users in Database</h2>";
echo "<table border='1' cellpadding='10'>";
echo "<tr><th>ID</th><th>Name</th><th>Email</th><th>Hashed Password</th></tr>";

$sql = "SELECT id, name, email, password FROM users";
$result = mysqli_query($conn, $sql);

while ($row = mysqli_fetch_assoc($result)) {
    echo "<tr>";
    echo "<td>" . $row['id'] . "</td>";
    echo "<td>" . htmlspecialchars($row['name']) . "</td>";
    echo "<td>" . htmlspecialchars($row['email']) . "</td>";
    echo "<td style='font-family: monospace; font-size: 12px;'>" . htmlspecialchars($row['password']) . "</td>";
    echo "</tr>";
}

echo "</table>";

echo "<br><hr><br>";
echo "<h3>Test Users & Passwords:</h3>";
echo "<ul>";
echo "<li><strong>admin@test.com</strong> → Password: <code>password</code></li>";
echo "<li><strong>user@test.com</strong> → Password: <code>password</code></li>";
echo "</ul>";
echo "<p><em>Both have the same hashed password because they use 'password' as their password.</em></p>";
?>