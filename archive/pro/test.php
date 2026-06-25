<?php
include ('../include/db.php');
$valilation = $_GET['custom'];
$getID = (int)$valilation;
$id = ($getID / 69);

$getDateQuery = $conn->query("SELECT `paidDate` FROM `paytracking`.`account` WHERE `account`.`id` = ".$id." LIMIT 1;");
$date = $getDateQuery["paidDate"];
$date = date_create($date);
date_add($date, date_interval_create_from_date_string('365 days'));
$newDate = date_format($date, 'Y-m-d');
$conn->query("UPDATE `paytracking`.`account` SET `paidDate` =  '".$newDate."' WHERE  `account`.`id` = ".$id." LIMIT 1 ;");
?>