<?php 
include ('../include/db.php');
include ('../include/cookiecheckMP.php');
$nonce = rand();
$conn->query("UPDATE `account` SET `paynonce` =  '".$nonce."' WHERE `account`.`id` = ".$id." LIMIT 1 ;");
setcookie("atv", $nonce, time()+600, '/');
include('../include/meta.html'); ?> 
<title>Pay Tracking Portal</title>
<link href="../style.css" rel="stylesheet" type="text/css" />
</head>

<body>
<noscript>
<h1 style="text-align: center;">Javascript Must Be Enabled To Use This Site</h1>
</noscript>
<?php include ('../include/topMenuPro.php'); 

	$time = $_GET['t'];
	

	
?>
    		
<div id="wrapper" class="largeContain">
  <div id="Top" class="add-load-contain">

<?php
	if ($time == 12) {
?>
<center>
<p>12 Month Subscription to PayTracker Pro - $30.00 (<i>Sandbox</i>)</p>
<br />
<form action="http://www.sandbox.paypal.com/cgi-bin/webscr" method="post" target="_top">
<input type="hidden" name="cmd" value="_s-xclick">
<input type="hidden" name="hosted_button_id" value="VMK9FHFUFE45Q">
<input type="image" src="http://www.sandbox.paypal.com/en_US/i/btn/btn_buynowCC_LG.gif" border="0" name="submit" alt="PayPal - The safer, easier way to pay online!">
<img alt="" border="0" src="http://www.sandbox.paypal.com/en_US/i/scr/pixel.gif" width="1" height="1">
</form>
<strong><span style="color:#F00;">*</span><span style="color:#0F0;">!</span><span style="color:#F00;">* IMPORTANT</span>: After completing the transaction on PayPal you <u>MUST</u> click the link to &quot;<span style="color:#0FF;">Return to EotS Creations</span>&quot; in order to complete the transaction!  If you do not click this, the time will NOT be added to your account! <span style="color:#F00;">*</span><span style="color:#0F0;">!</span><span style="color:#F00;">*</span></strong>
</center>
<?php
	}//end if ($time == 12)

	if ($time == 6) {
?>
<center>
<p>6 Month Subscription to PayTracker Pro - $12.00 (<i>Sandbox</i>)</p>
<br />
<form action="http://www.sandbox.paypal.com/cgi-bin/webscr" method="post" target="_top">
<input type="hidden" name="cmd" value="_s-xclick">
<input type="hidden" name="hosted_button_id" value="H3W54G4YXSXTQ">
<input type="image" src="http://www.sandbox.paypal.com/en_US/i/btn/btn_buynowCC_LG.gif" border="0" name="submit" alt="PayPal - The safer, easier way to pay online!">
<img alt="" border="0" src="http://www.sandbox.paypal.com/en_US/i/scr/pixel.gif" width="1" height="1">
</form>
<strong><span style="color:#F00;">*</span><span style="color:#0F0;">!</span><span style="color:#F00;">* IMPORTANT</span>: After completing the transaction on PayPal you <u>MUST</u> click the link to &quot;<span style="color:#0FF;">Return to EotS Creations</span>&quot; in order to complete the transaction!  If you do not click this, the time will NOT be added to your account! <span style="color:#F00;">*</span><span style="color:#0F0;">!</span><span style="color:#F00;">*</span></strong>

</center>
<?php
	}//end if ($time == 6)

	if ($time == 3) {
?>
<center>
<p>3 Month Subscription to PayTracker Pro - $6.00 (<i>Sandbox</i>)</p>
<br />
<form action="http://www.sandbox.paypal.com/cgi-bin/webscr" method="post" target="_top">
<input type="hidden" name="cmd" value="_s-xclick">
<input type="hidden" name="hosted_button_id" value="C5KJXJGZ4H3Z4">
<input type="image" src="http://www.sandbox.paypal.com/en_US/i/btn/btn_buynowCC_LG.gif" border="0" name="submit" alt="PayPal - The safer, easier way to pay online!">
<img alt="" border="0" src="http://www.sandbox.paypal.com/en_US/i/scr/pixel.gif" width="1" height="1">
</form>
<strong><span style="color:#F00;">*</span><span style="color:#0F0;">!</span><span style="color:#F00;">* IMPORTANT</span>: After completing the transaction on PayPal you <u>MUST</u> click the link to &quot;<span style="color:#0FF;">Return to EotS Creations</span>&quot; in order to complete the transaction!  If you do not click this, the time will NOT be added to your account! <span style="color:#F00;">*</span><span style="color:#0F0;">!</span><span style="color:#F00;">*</span></strong>

</center>
<?php
	}//end if ($time == 3)
?>


  </div><!--end Top-->  
</div><!--end wrapper-->
        

<?php include('../include/footer.html'); ?>
</body>
</html>