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

$load = $_POST['load'];
$empty = $_POST['empty'];
$split = $_POST['split'];
$extra = $_POST['extra'];
$dem = $_POST['dem'];
$break = $_POST['break'];
$last = $_POST['last'];
$dh = $_POST['dh'];
$getanchor = $_POST['anchor'];
if ($getanchor > 0) { $anchor = $getanchor + 1; $anchor = "#".$anchor; } else { $anchor = ''; }


if (isset($load)) { $loadPaid++; }
if (isset($empty)) { $emptyPaid++; }
if (isset($split)) { $splitPaid++; }
if (isset($extra)) { $extraPaid++; }
if (isset($dem)) { $demurragePaid++; }
if (isset($break)) { $breakdownPaid++; }
if (isset($last)) { $lastLoadPaid++; }
if (isset($dh)) { $deadheadPaid++; }

$newPaid = $loadPaid . "-" . $splitPaid . "-" . $breakdownPaid . "-" . $demurragePaid . "-" . $extraPaid . "-" . $lastLoadPaid . "-" . $emptyPaid . "-" . $deadheadPaid;


$conn->query("UPDATE loads".$id." SET paid = '".$newPaid."', `notPaid` =  '0' WHERE loads".$id.".frtl = ".$frtl." LIMIT 1 ;") or die ('<center>
			  <h3>Something Has Gone <span style="color:#F00;">Catastrophically</span> Wrong!</h3>
			  <h3>Wait a bit and try again</h3>
			  </center>');
			  
header("Location: http://".$_SERVER['SERVER_NAME']."/pro/reconcile.php");
?>