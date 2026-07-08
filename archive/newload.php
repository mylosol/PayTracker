<?php
	
	if (!isset($_COOKIE['loads'])) {
		$loads = "0-0-0";
		setcookie("loads", $loads, time()+43200);
	}//end if (!isset($_COOKIE['loads']))
	
	if (!isset($_COOKIE['nl'])) {
		$nl = 1;
		setcookie("nl", $nl, time()+3600);
	}//end if (!isset($_COOKIE['nl']))
	
	  setcookie("active", 1, time()+36000, '/');  
	
include('header/headerRedirect.php');
?>