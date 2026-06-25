<?php 
include ('../include/db.php');
include('../include/meta.html'); ?> 
<title>Pay Tracking Portal</title>
<link href="../style.css" rel="stylesheet" type="text/css" />
</head>

<body>
<noscript>
<h1 style="text-align: center;">Javascript Must Be Enabled To Use This Site</h1>
</noscript>
<?php include ('../include/topMenu.php'); ?>
    		
<div id="wrapper" class="largeContain justText">

<?php
  $activation = $_GET['a'];
  $email = base64_decode($activation);
  $valilation = $_GET['v'];
  $getID = (int)$valilation;
  $id = ($getID / 69);
  $na = "";
  $notActive = $_GET['na'];
  
  
  if (isset($notActive)) {
	  if ($notActive == 1) {
	  $na = "<h2>Your account is not yet activated!  You must verify your email address to continue!</h2><br />";
	  } else if ($notActive == 2) {
	  $na = "<h2>Your account has previously been created, however is not yet activated!<br />You must verify your email address to continue!</h2><br />";
	  }
  }//end if (isset($_GET['na']))
  
  
  $getInfo = $conn->query("SELECT * FROM account WHERE user = '".$email."' AND `id` = ".$id." LIMIT 1");
  if ($getInfo->num_rows > 0) {
	  ?>
	  <center>
      <h1>Unable to get account info</h1>
	  <h3>Something Has Gone <span style="color:#F00;">Catastrophically</span> Wrong!</h3>
	  <h3>Check to ensure you copied the reset link properly.</h3>
	  </center>
	  <?php
  } else {
	  $active = $getInfo["accountValid"];
	  if ($active == 0) {
		
		include('../include/nonce.php');
		//Email information
	  
		$headers = "MIME-Version: 1.0"."\r\n";
		$headers .= "Content-Type: text/html; charset=ISO-8859-1"."\r\n";
		$headers .= 'From: PayTracker Pro <noreply@'.$_SERVER['SERVER_NAME'].'>' . "\r\n";
		$subject = "[PayTracker Pro] Welcome Aboard!";
		
		$message = '<html><body>';
		$message .= '<div id="Logo" style="margin: 0 auto; width: 305px; height: 41px;"><img src="http://'.$_SERVER['SERVER_NAME'].'/images/logo.png" alt="Pay Tracker" height="41px" width="216px" /><img src="http://'.$_SERVER['SERVER_NAME'].'/images/pro.png" alt="Pro" height="41px" width="87px" /></div>';
		$message .= '<h3 style="text-align:center;">Thank you for registering with <a href="http://'.$_SERVER['SERVER_NAME'].'/" target="_blank" title="Pay Tracker Pro">PayTracker Pro</a></h3>';			  
		$message .= '<h3 style="text-align:center;">Click on the link below to activate your PayTracker Pro account.</h3>';			  
		$message .= '<h4 style="text-align:center;"><b>URL: </b><a href="http://'.$_SERVER['SERVER_NAME'].'/pro/activate.php?a='.$activation.'&v='.$valilation.'&n='.$nonce.'" target="_blank" title="Activate Account">http://'.$_SERVER['SERVER_NAME'].'/pro/activate.php?a='.$activation.'&v='.$valilation.'&n='.$nonce.'</a></h4><br />';	
		$message .= "</h4><br /><p>If clicking doesn't work, you can copy and paste the link into your browser's address bar or retype it there.</p>";
		$message .= '<p>This link will expire in 7 days.</p>';			  
		$message .= '<span style="font-size: 11px; font-style:italic;">This is an automated message sent out by '.$_SERVER['SERVER_NAME'].'</span>';			  
		$message .= '</body></html>';			  
		//send email
		mail($email, $subject, $message, $headers);
		
		$dateSevenDays = date('Y-m-d', strtotime("+7 days"));
		$conn->query("UPDATE account SET emailValid = '".$dateSevenDays."' WHERE account.id = ".$id." LIMIT 1 ;") or die ('<center>
                <h1>Unable to update email validation date</h1>
				<h3>Something Has Gone <span style="color:#F00;">Catastrophically</span> Wrong!</h3>
                <h3>Wait a bit and refresh the page.</h3>
                </center>');
	  ?>
        <center>
        <?php echo $na; ?>
        <h3>Another email has been sent to <?php echo $email; ?> with an activation link.</h3>
        <br  />
        <h4>Please check your email and click the link to activate your account.</h4>
        <br  />
        <h6>If you do not see the activation email, check your Spam/Junk folder, if it's there move it to your inbox.</h6>
        </center>
	  <?php	
	  } else {
	  ?>  
		<center>
		<h1>Account For <?php echo $email; ?> Has Already Been Activated</h1>
		<br />
        <h3>Click <a href="http://'.$_SERVER['SERVER_NAME'].'/pro/login.php" target="_self" title="Home Page">Here</a> to Login.</h3>
		</center>
	  <?php
	  }//end if ($active == 0)
  }//end if ($getInfo->num_rows > 0)
?>

</div><!--end wrapper-->
        

<?php include('../include/footer.html'); ?>
</body>
</html>