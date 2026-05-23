<?php
$nonce = rand();
$conn->query("UPDATE `account` SET `nonce` =  '".$nonce."' WHERE `account`.`id` = ".$id." LIMIT 1 ;");
?>