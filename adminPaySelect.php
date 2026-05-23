<?php
	$roundTripActive = $_GET['rt'];
	$longHaulTripActive = $_GET['lh'];
	$rtb = "rtb";
	$lhb = "lhb";
	
	if (isset($roundTripActive)) {
		setcookie("basePayType", 1, time()-1, '/');
		setcookie("basePayType", $rtb, time()+31536000); 
	}
	
	if (isset($longHaulTripActive)) {
		setcookie("basePayType", 1, time()-1, '/');
		setcookie("basePayType", $lhb, time()+31536000); 
	}
	
	if ((!isset($roundTripActive)) && (!isset($longHaulTripActive))) {
		setcookie("basePayType", 1, time()-1, '/');
	}
	
	
	header("Location: http://".$_SERVER['SERVER_NAME']."/pro/BasePayAdmin.php");

?>