<?php
require_once 'connect.php';
$result = $conn->query("SELECT * FROM transactions ORDER BY id DESC LIMIT 5");
if ($result->num_rows > 0) {
    while($row = $result->fetch_assoc()) {
        print_r($row);
        echo "\n-----------------------------------\n";
    }
} else {
    echo "No transactions found.";
}
?>
