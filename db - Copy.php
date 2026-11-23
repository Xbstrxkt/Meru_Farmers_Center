<?php
$host = "localhost";
$user = "root";
$password = "";
$dbname = "meru_farmers_db"; // The database name you created in phpMyAdmin

$conn = new mysqli($host, $user, $password, $dbname);

if ($conn->connect_error) {
    die("Database connection failed: " . $conn->connect_error);
}
// echo "Connected successfully!"; // Uncomment to test connection
?>