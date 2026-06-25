<?php
if (isset($_POST['done'])) {
	include('include/db.php');
	if (isset($_POST['beginEmpty'])) { $begin = $_POST['beginEmpty']; }
	if (isset($_POST['pick-up'])) { $pu = $_POST['pick-up']; }
	if (isset($_POST['delivery'])) { $del = $_POST['delivery']; }
	if (isset($_POST['return'])) { $return = $_POST['return']; }
	if (isset($_POST['split'])) { $split = $_POST['split']; }
	if (isset($_POST['weekend'])) { $weekend = $_POST['weekend']; }
	if (isset($_POST['type'])) { $type = $_POST['type']; }
	if (isset($_POST['extra'])) { $extra = $_POST['extra']; }
	if (isset($_POST['extraChoice'])) { $extraChoice = $_POST['extraChoice']; }
	if (isset($_POST['dem'])) { $dem = $_POST['dem']; }
	if (isset($_POST['break'])) { $break = $_POST['break']; }
	if (isset($_POST['firstStop'])) { $firstStop = $_POST['firstStop']; }
	if (isset($_POST['nq'])) { $nqt = $_POST['nq']; }
	if (isset($_COOKIE['be'])) { $be = $_COOKIE['be']; }
	if (isset($_COOKIE['edit'])) { $edit = $_COOKIE['edit']; }
	if (isset($_COOKIE['nq'])) { $notQulaified = $_COOKIE['nq']; }
    if (isset($_COOKIE['loads'])) { $tpArray = $_COOKIE['loads']; }
	if (isset($tpArray)) { $tpExplode = explode("-", $tpArray); }
	$rt = $tpExplode[0];
	$ow = $tpExplode[1];
	$nq = $tpExplode[2];
	$pd = 0;
	$emptyMiles = 0;
	$gm = 0;
	$ori = 0;
	$orm = 0;

	if (isset($be)) {
		if ($be == $pu) {
		  $ow--;
		  $nq++;
		  $newNQ = $rt . "-" . $ow . "-" . $nq;
		  setcookie("loads", $newNQ, time()+43200);
		  setcookie("beo", 1, time()+43200);
	  	  setcookie("be", 1, time()-1);
		}
	}//end if (isset($be))
	
	if (isset($_COOKIE['pensacola'])) { $pcola = 1;};
	
	if (isset($edit)) {
		$load = $edit;
	} else {
		$load = $_POST['load'];
	}//end if (isset($edit))
	
	$cookieName = "L" . $load;
	if (isset($_COOKIE[$cookieName])) {
		$cv = $_COOKIE[$cookieName];
		$cvExplode = explode("-", $cv);
	}//end if (isset($_COOKIE[$cookieName]))
	
		if (isset($split)) {
			$split = 1;
		} else {
			$split = 0;
		}//end if (isset($split))
		
		if (isset($weekend)) {
			$weekend = 1;
		} else {
			$weekend = 0;
		}//end if (isset($weekend))
		
		if (isset($extra)) {
			$extra = $extraChoice;
		} else {
			$extra = 0;
		}//end if (!isset($extra))
	
		if (!isset($dem)) {
			$demTime = 0;
		} else {
			$demTime = $_POST['demTime'];
		}//end if (!isset($dem))
		
		if (!isset($break)) {
			$breakTime = 0;
		} else {
			$breakTime = $_POST['breakTime'];
		}//end if (!isset($break))

	//Begin empty miles
	if (isset($begin)) {
	
		if (isset($pcola)) { 
			$beginMilesSql = 'SELECT `'.$begin.'` FROM `pcola_largeMiles` WHERE `city` =  "'.$pu.'" LIMIT 1;';
		} else {	
			$beginMilesSql = 'SELECT `'.$begin.'` FROM `largeMiles` WHERE `city` =  "'.$pu.'" LIMIT 1;';	
		}//end if ($pcola == 1)
		
	$beginMilesQuery = $conn->query($beginMilesSql);
	setcookie("be", $pu, time()+43200);

	  if (!$beginMilesQuery) {
		  $beginEmptyMiles = 999;
	  } else {
		  while($row = $beginMilesQuery->fetch_assoc()) { $beginEmptyMiles = $row[$begin]; }//end while($row = $beginMilesQuery->fetch_assoc())
		  
				if ($beginEmptyMiles == 0) {
					$newStart = preg_replace("/ /","+",$return);
					$newEnd = preg_replace("/ /","+",$del);
					include('include/googleMapsAPI.php');
					$beginEmptyMiles = $loadMiles;
					$gm = "1";
				}//end if ($beginEmptyMiles == 0)
		  
		  if ($beginEmptyMiles == 1) { $beginEmptyMiles = 0; }
	  }//end 	if (!$milesQuery || !mysql_num_rows($milesQuery)) 
	}//end if (isset($return))
	//Begin empty miles	

		
	//empty miles
	if (isset($return)) {
	
	if (isset($pcola)) { 
		$milesSql = 'SELECT `'.$return.'` FROM `pcola_largeMiles` WHERE `city` =  "'.$del.'" LIMIT 1;';	
	} else {
		$milesSql = 'SELECT `'.$return.'` FROM `largeMiles` WHERE `city` =  "'.$del.'" LIMIT 1;';
	}//end if ($pcola == 1)
		
	$milesQuery = $conn->query($milesSql);

	  if (!$milesQuery) {
		  $emptyMiles = 999;
	  } else {
		  while ($row = $milesQuery->fetch_assoc()) { $emptyMiles = $row[$return]; }//end while ($row = $milesQuery->fetch_assoc())
		  
				if ($emptyMiles == 0) {
					$newStart = preg_replace("/ /","+",$return);
					$newEnd = preg_replace("/ /","+",$del);
					include('include/googleMapsAPI.php');
					$emptyMiles = $loadMiles;
					$gm = "1";
				}//end if ($emptyMiles == 0)
		  
		  if ($emptyMiles == 1) { $emptyMiles = 0; }
	  }//end 	if (!$milesQuery || !mysql_num_rows($milesQuery)) 
	}//end if (isset($return))
	//empty miles	
	
	//out of route miles
	if ($firstStop != "") {
	$ori = 1;
	$loadMiles = 0;	
	
	if (isset($pcola)) {
		$firstMilesSql = 'SELECT `'.$pu.'` FROM `pcola_largeMiles` WHERE `city` =  "'.$firstStop.'" LIMIT 1;';
	} else {
		$firstMilesSql = 'SELECT `'.$pu.'` FROM `largeMiles` WHERE `city` =  "'.$firstStop.'" LIMIT 1;';		
	}//end if ($pcola == 1)
	
	$firstMilesQuery = $conn->query($firstMilesSql);
	  if (!$firstMilesQuery) {
		  $pu2first = 999;
	  } else {
		  while ($row = $firstMilesQuery->Fetch_assoc()) { $pu2first = $row[$pu]; }//end while ($row = $firstMilesQuery->Fetch_assoc())
 			  
			  if ($pu2first == 0) {
				  $newStart = preg_replace("/ /","+",$pu);
				  $newEnd = preg_replace("/ /","+",$firstStop);
				  include('include/googleMapsAPI.php');
				  $pu2first = $loadMiles;
				  $ori = 2;
			  }//end if ($pu2second == 0)
			  
	  }//end if (!$milesQuery || !mysql_num_rows($milesQuery))
	  
	$loadMiles = 0;
	
	if (isset($pcola)) { 
		$secondMilesSql = 'SELECT `'.$firstStop.'` FROM `pcola_largeMiles` WHERE `city` =  "'.$del.'" LIMIT 1;';
	} else {
		$secondMilesSql = 'SELECT `'.$firstStop.'` FROM `largeMiles` WHERE `city` =  "'.$del.'" LIMIT 1;';
		}//end if ($pcola == 1)
	
	$secondMilesQuery = $conn->query($secondMilesSql);
	  if (!$secondMilesQuery) {
		  $pu2second = 999;
	  } else {
		  while($row = $secondMilesQuery->fetch_assoc()) { $pu2second = $row["".$firstStop.""]; }//end  while($row = $secondMilesQuery->fetch_assoc())
			  
			  if ($pu2second == 0) {
				  $newStart = preg_replace("/ /","+",$firstStop);
				  $newEnd = preg_replace("/ /","+",$del);
				  include('include/googleMapsAPI.php');
				  $pu2second = $loadMiles;
				  $ori = 2;
			  }//end if ($pu2second == 0)
	  }//end if (mysql_num_rows($secondMilesQuery) > 0)
		
		if (($pu2first == 999) || ($pu2second == 999)) {
			$orm = 999;
		} else {
			$orm = $pu2first + $pu2second;
		}//end if (($pu2first == 999) || ($pu2second == 999))
		
	} // end if ($firstStop != "")
	//out of route miles
	
		if (isset($notQulaified)) {
			$pd = "preload";
		}//end if (isset($notQulaified))

		if (isset($beginEmptyMiles)) {
			$pd = $beginEmptyMiles;
		}//end if (isset($emptyMiles))

		
		$cookie = $type . "-" . $emptyMiles . "-" . $pu . "-" . $del . "-" . $split . "-" . $weekend . "-" . "2" . "-" . $pd . "-" . $gm . "-" . $extra . "-" . $demTime . "-" . $breakTime . "-" . $ori . "-" . $orm;
		setcookie($cookieName, $cookie, time()+86400);
		
		if (isset($return)) {
			$lastTerminal = $return;
		} else {
			$lastTerminal = $pu;
		}//end if (isset($return))
	
		if (isset($edit)) {
		  setcookie("edit", $loadID, time()-1);
		}//end if (isset($_POST['done']))
		
	setcookie("lt", $lastTerminal, time()+86400);
}//end if (isset($_POST['done']))

if (isset($_POST['cancel'])) {
    $tpArray = $_COOKIE['loads'];
	$tpExplode = explode("-", $tpArray);
	$rt = $tpExplode[0];
	$ow = $tpExplode[1];
	$nq = $tpExplode[2];

	  if (isset($_COOKIE['rt'])) {
		  $rt--;
		  $newRT = $rt . "-" . $ow . "-" . $nq;
		  setcookie("loads", $newRT, time()+43200);
	  }//end if (isset($_POST['rt']))
	  
	  if ((isset($_COOKIE['owl']) && (!isset($_COOKIE['nq'])))) {
		  $ow--;
		  $newOW = $rt . "-" . $ow . "-" . $nq;
		  setcookie("loads", $newOW, time()+43200);
	  }//end if (isset($_POST['owl']))
	  
	  if (isset($_COOKIE['nq'])) {
		  $nq--;
		  $newNQ = $rt . "-" . $ow . "-" . $nq;
		  setcookie("loads", $newNQ, time()+43200);
	  }//end if (isset($_POST['nq']))
	  
}//end if (isset($_POST['cancel']))

	setcookie("nq", 1, time()-1);
	setcookie("rt", 1, time()-1);
	setcookie("owe", 1, time()-1);
	setcookie("owl", 1, time()-1);
	include('header/headerRedirect.php');
?>