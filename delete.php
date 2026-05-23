<?php

if($_GET['b'] == 1) {	
	setcookie("bck", 1, time()+36000, '/');
} else if(isset($_GET['s'])) {
	$spl = $_GET['s'];
	setcookie("spl", $spl, time()+36000, '/');
} else {

	$loadID = $_GET['l'];
	$cookieName = "L" . $loadID;
	$nq = $_GET['n'];
	if (isset($_COOKIE[$cookieName])) {
		$cv = $_COOKIE[$cookieName];
		$cvExplode = explode("-", $cv);
		
		$tpArray = $_COOKIE['loads'];
		$tpExplode = explode("-", $tpArray);
		$rt = $tpExplode[0];
		$ow = $tpExplode[1];
		$notQualified = $tpExplode[2];
		
			if ($cvExplode[0] == 0) {
				
				if (!isset($nq)) {
				  $ow--;
				  $notQualified++;
				}//end (!isset($nq))
				
			  $newLoads = $rt . "-" . $ow . "-" . $notQualified;
			}//end if ($cvExplode[0] == 0)
			
			if ($cvExplode[0] == 1) {
				
				if (!isset($nq)) {
				  $rt--;
				  $notQualified++;
				}//end (!isset($nq))
				
			  $newLoads = $rt . "-" . $ow . "-" . $notQualified;
			}//end if ($cvExplode[0] == 1)
			
		$cookie = "2";
		setcookie($cookieName, $cookie, time()+43200);
		setcookie("loads", $newLoads, time()+43200);
	}//end if (isset($_COOKIE[$cookieName]))

}
	
include('header/headerRedirect.php');
?>