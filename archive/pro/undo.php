<?php
include ('../include/db.php');
include ('../include/cookiecheck.php');
$frtl = $_POST['ln'];
$paidArray = $_POST['paid'];
$isPaid = explode("-", $paidArray);	
$loadPaid = $isPaid[0];
$splitPaid = $isPaid[1];
$breakdownPaid = $isPaid[2];
$demurragePaid = $isPaid[3];
$extraPaid = $isPaid[4];
$lastLoadPaid = $isPaid[5];
$emptyPaid = $isPaid[6];
$deadheadPaid =  $isPaid[7];

$load = $_POST['undoload'];
$empty = $_POST['undoempty'];
$split = $_POST['undosplit'];
$extra = $_POST['undoextra'];
$dem = $_POST['undodem'];
$break = $_POST['undobreak'];
$last = $_POST['undolast'];
$dh = $_POST['undodh'];


if (isset($load)) { $loadPaid = 1; }
if (isset($empty)) { $emptyPaid = 1; }
if (isset($split)) { $splitPaid = 1; }
if (isset($extra)) { $extraPaid = 1; }
if (isset($dem)) { $demurragePaid = 1; }
if (isset($break)) { $breakdownPaid = 1; }
if (isset($last)) { $lastLoadPaid = 1; }
if (isset($dh)) { $deadheadPaid = 1; }

$newPaid = $loadPaid . "-" . $splitPaid . "-" . $breakdownPaid . "-" . $demurragePaid . "-" . $extraPaid . "-" . $lastLoadPaid . "-" . $emptyPaid . "-" . $deadheadPaid;

$conn->query("UPDATE loads".$id." SET paid = '".$newPaid."' WHERE loads".$id.".frtl = ".$frtl." LIMIT 1 ;") or die ('<center>
			  <h3>Something Has Gone <span style="color:#F00;">Catastrophically</span> Wrong!</h3>
			  <h3>Wait a bit and try again</h3>
			  </center>');
			  
header("Location: http://".$_SERVER['SERVER_NAME']."/pro/reconcile.php");
?>