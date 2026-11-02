<?php
// database connection settings
$servername ="localhost";
$username = "root";
$password = "";
$dbname ="online_payment";

// create connection 
$conn=new mysqli($servername, $username, $password, $dbname);

//check connection 
if($conn->connect_error) {
    die("connection failed: " .$conn->connect_error);
}
// echo "connection successfully to the online_payment database";
?>
