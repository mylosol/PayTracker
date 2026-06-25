<?php
	include('include/db.php');
	if (isset($_COOKIE['pensacola'])) { $pcola = 1;};
	$trainer = $_COOKIE['tr'];
	$load = $_GET['l'];
	$cookieName = "L" . $load;
	$type = 4;
	$trainerpay = 285;
	$cookie = $type . "-" . $trainerpay;
	
	if (isset($trainer)) {
	setcookie($cookieName, $cookie, time()+86400);
	setcookie("nl", "1", time()-1);
	setcookie("tr", "1", time()-1);
	header("Location: http://".$_SERVER['SERVER_NAME']."/");
	}//end if (isset($trainer))
?>