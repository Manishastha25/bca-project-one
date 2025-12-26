<?php
$host = "localhost";
$username = "root";
$password = "1234";
$database = "municipality_complaint_system";

$conn = mysqli_connect($host, $username, $password, $database);

if (!$conn) {
    die("Connection failed: " . mysqli_connect_error());
}
?>