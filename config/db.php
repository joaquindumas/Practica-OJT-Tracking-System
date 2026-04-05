<?php
$host     = 'fdb1033.awardspace.net';
$db_name  = '4744980_practica';
$username = '4744980_practica';
$password = '{u__ok_Q5CEby(cR';  // default XAMPP password is empty

$conn = new mysqli($host, $username, $password, $db_name);

if ($conn->connect_error) {
    die('Database connection failed: ' . $conn->connect_error);
}