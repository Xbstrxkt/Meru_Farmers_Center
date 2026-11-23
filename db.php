<?php
$host     = getenv('MYSQLHOST') ?: 'localhost';
$user     = getenv('MYSQLUSER') ?: 'root';
$password = getenv('MYSQLPASSWORD') !== false ? getenv('MYSQLPASSWORD') : '';
$dbname   = getenv('MYSQLDATABASE') ?: 'meru_farmers_db';
$port     = getenv('MYSQLPORT') ? (int)getenv('MYSQLPORT') : 3306;

$conn = new mysqli($host, $user, $password, $dbname, $port);

if ($conn->connect_error) {
    die("Database connection failed: " . $conn->connect_error);
}
?>