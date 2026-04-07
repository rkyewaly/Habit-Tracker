<?php

require "DBConnect.php";

$full_name = $_GET['full_name'];
$email = $_GET['email'];
$password_hash = $_GET['password_hash'];
$role = $_GET['role'];

$sql = "insert into users (full_name, email, password_hash, role)
values ('$full_name', '$email', '$password_hash', '$role')";
echo modifyDB($sql) . "<br><h3>You have registered user $full_name as a $role under email $email</h3>";
?>