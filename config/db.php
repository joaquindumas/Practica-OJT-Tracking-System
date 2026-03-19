<?php
$host     = 'localhost';
$db_name  = 'ojt_tracker';
$username = 'root';
$password = '';  // default XAMPP password is empty

$conn = new mysqli($host, $username, $password, $db_name);

if ($conn->connect_error) {
    die('Database connection failed: ' . $conn->connect_error);
}