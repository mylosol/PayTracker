<?php
include('include/db.php');
ini_set ('display_errors', 1);

$id = $_POST['id'];
$t = $_POST['t'];


	if ($t == 0) { $terminal = "PanamaPay"; $tn=0; }
	if ($t == 1) { $terminal = "PensacolaPay"; $tn =1; }



	$conn->query("DELETE FROM `".$terminal."` WHERE `id` = ".$id);
	header("Location: http://".$_SERVER['SERVER_NAME']."/Update".$terminal.".php");

?>