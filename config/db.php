<?php
$host     = 'sql306.infinityfree.com';
$db_name  = 'if0_41453530_XXX';
$username = 'root';
$password = 'IprcWDC2TPx';  // default XAMPP password is empty

$conn = new mysqli($host, $username, $password, $db_name);

if ($conn->connect_error) {
    die('Database connection failed: ' . $conn->connect_error);
}