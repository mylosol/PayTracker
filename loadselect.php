<?php
	$edit = $_COOKIE['edit'];
    $tpArray = $_COOKIE['loads'];
	$tpExplode = explode("-", $tpArray);
	$rt = $tpExplode[0];
	$ow = $tpExplode[1];
	$nq = $tpExplode[2];
	
		if (isset($_POST['rt'])) {
			$rt++;
			$newRT = $rt . "-" . $ow . "-" . $nq;
			setcookie("loads", $newRT, time()+43200);
			setcookie("rt", $rt, time()+43200);
			setcookie("nl", "", time()-1);
		}//end if (isset($_POST['rt']))
		
		if (isset($_POST['ow'])) {
			$ow++;
			$newOW = $rt . "-" . $ow . "-" . $nq;
			setcookie("loads", $newOW, time()+43200);
			setcookie("owl", $ow, time()+43200);
			setcookie("nl", "", time()-1);
		}//end if (isset($_POST['ow']))
		
		if (isset($_POST['pl'])) {
			$nq++;
			$newNQ = $rt . "-" . $ow . "-" . $nq;
			setcookie("loads", $newNQ, time()+43200);
			setcookie("nl", "1", time()-1);
			setcookie("owl", $ow, time()+43200);
			setcookie("nq", "1", time()+43200);
		}//end if (isset($_POST['pl']))

		if (isset($_POST['trainer'])) {
			$nq++;
			$newNQ = $rt . "-" . $ow . "-" . $nq;
			setcookie("loads", $newNQ, time()+43200);
			setcookie("nl", "1", time()-1);
			setcookie("tr", "1", time()+43200);
		}//end if (isset($_POST['trainer']))
						
		
		if (isset($_POST['cancel'])) {
			setcookie("nl", "", time()-1);
		}//end if (isset($_POST['cancel']))
	

include('header/headerRedirect.php');
?>
