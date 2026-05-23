<?php
	$tracking = $_GET['t'];
	$loadID = $_GET['l'];
	$frtl = $_GET['f'];
	$pt = "";
	
	if (!isset($loadID)) {
	  if ($frtl > 1) {
		  $pt = "&p=".$frtl;
	  }//end if ($frtl > 1)
	}//end if (!isset($loadID))
	
	if (isset($tracking)) {
		header("Location: http://".$_SERVER['SERVER_NAME']."/pro/tracking.php?c=".$loadID."&e=1".$pt."");
	} else {

	if (!isset($_COOKIE['nl'])) {
		$nl = 1;
		setcookie("nl", $nl, time()+3600);
	}//end if (!isset($_COOKIE['nl']))
	
		$cookieName = "L" . $loadID;
		$cv = $_COOKIE[$cookieName];
		$cvExplode = explode("-", $cv);
		$type = $cvExplode[0];
		$notQualified = $cvExplode[7];
		
		$tpArray = $_COOKIE['loads'];
		$tpExplode = explode("-", $tpArray);
		$rt = $tpExplode[0];
		$ow = $tpExplode[1]; 
		$nq = $tpExplode[2];
		
		if ($rt == -1) { $rt = 0; };
		if ($ow == -1) { $ow = 0; };
		if ($nq == -1) { $nq = 0; };
		
		if ($notQualified > 0)  {
			if ($nq <> 0) { $nq--; }
			if ($tpArray == "0-1-0") { $ow = 0; } //bandaid on a different issue
		} else {
			
			if ($type == 1) {
				if($rt <> 0) { $rt--; }
			}//end if ($type == 0)
		   
			if ($type == 0) {
				if($ow <> 0) { $ow--; }
			}//end if ($type == 1)
			
		}//end if ($notQualified > 0)
			
		if(isset($_COOKIE['be'])) { setcookie("be", 1, time()-1); }
		
		$newLoads = $rt . "-" . $ow . "-" . $nq;
		setcookie("loads", $newLoads, time()+43200);
		setcookie("edit", $loadID, time()+3600);
		//echo "rt= " . $rt . " | ow= " . $ow . " | nq= " . $nq . " tpExplode[0]= " . $tpExplode[0]. " | tpExplode[1]=" . $tpExplode[1]. " | tpExplode[2]=" . $tpExplode[2]. " tpExplode[3]=" . $tpExplode[3] . " | newLoads=" . $newLoads;
		include('header/headerRedirect.php');
	}//end if (isset($tracking)
?>