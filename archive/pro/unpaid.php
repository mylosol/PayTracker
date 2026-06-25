<?php
include ('../include/db.php');
include ('../include/cookiecheck.php');
$frtl = $_POST['ln'];

$conn->query("UPDATE loads".$id." SET notPaid = '1' WHERE loads".$id.".frtl = ".$frtl." LIMIT 1 ;") or die ('<center>
			  <h3>Something Has Gone <span style="color:#F00;">Catastrophically</span> Wrong!</h3>
			  <h3>Wait a bit and try again</h3>
			  </center>');

header("Location: http://".$_SERVER['SERVER_NAME']."/pro/reconcile.php");
?>