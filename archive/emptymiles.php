<?php
if (isset($_POST['done'])) {
include('include/db.php');
	$pu = $_POST['mtEnd'];
	$del = $_POST['mtStart'];
	$type = $_POST['type'];
	$edit = $_COOKIE['edit'];
	$notQulaified = $_COOKIE['nq'];
	$gm = 0;

	if (isset($edit)) {
		$load = $edit;
	} else {
		$load = $_POST['load'];
	}//end if (isset($edit))
		
	$milesSql = 'SELECT `'.$pu.'` FROM `miles` WHERE `city` =  "'.$del.'" LIMIT 1;';
	$milesQuery = $conn->query($milesSql);
	if (!$milesQuery) {
		$miles = 999;
	} else {
		while($row = $result->fetch_assoc()) { $miles = $milesQuery[$pu]; }//end while($row = $result->fetch_assoc())
	  
			if ($miles == 0) {
				include('include/googleMapsAPI.php');
				$gm = "1";
				$miles = $loadMiles;
			} //end if ($miles == 0)
			
	  if ($miles == 1) { $miles = 0; }
	}//end if (!$milesQuery || !mysql_num_rows($milesQuery))

	if (isset($notQulaified)) {
		$nq = $notQulaified;
	} else {
		$nq = 0;
	}//end if (isset($notQulaified))

	$cookieName = "L" . $load;
	
	if (isset($_COOKIE[$cookieName])) {
		$cv = $_COOKIE[$cookieName];
		$cvExplode = explode("-", $cv);
		$om = $cvExplode[1];
		$nm = $om + $miles;
		$cookie = $type . "-" . $nm . "-" . $cvExplode[2] . "-" . $cvExplode[3] . "-" . $cvExplode[4] . "-" . $cvExplode[5] . "-" . $cvExplode[6] . "-" . $nq . "-" . $gm . "-" . $cvExplode[9] . "-" . $cvExplode[10] . "-" . $cvExplode[11] . "-" . $cvExplode[12] . "-" . $cvExplode[13];
		setcookie($cookieName, $cookie, time()+86400);
		$lastTerminal = $pu;
		setcookie("lt", $lastTerminal, time()+86400);
	} else {
		$cookie = $type . "-" . $miles . "-" . $pu . "-" . $del . "-" . "0" . "-" . "0" . "-" . "1" . "-" . $nq . "-" . $gm . "-" . "0" . "-" . "0" . "-" . "0" . "-" . "0" . "-" . "0";
		setcookie($cookieName, $cookie, time()+86400);
		$lastTerminal = $pu;
		setcookie("lt", $lastTerminal, time()+86400);
	}//end if (isset($_COOKIE[$cookieName]))
	
		  if (isset($edit)) {
			setcookie("edit", $loadID, time()-1);
		  }//end if (isset($edit))
		  
		  if (isset($_COOKIE['nq'])) {
			setcookie("nq", $loadID, time()-1);
		  }//end if (isset($edit))
		  
}//end if (isset($_POST['done']))

if (isset($_POST['cancel'])) {
    $tpArray = $_COOKIE['loads'];
	$tpExplode = explode("-", $tpArray);
	$rt = $tpExplode[0];
	$ow = $tpExplode[1];
	$nq = $tpExplode[2];
	
	  if (isset($_COOKIE['nq'])) {
		  $nq--;
		  $newNQ = $rt . "-" . $ow . "-" . $nq;
		  setcookie("loads", $newNQ, time()+43200);
	  }//end if (isset($_POST['nq']))
	  
}//end if (isset($_POST['cancel']))

	setcookie("nq", 1, time()-1);
	setcookie("owe", 1, time()-1);
	setcookie("owl", 1, time()-1);
	setcookie("rt", 1, time()-1);
	include('header/headerRedirect.php');

?>