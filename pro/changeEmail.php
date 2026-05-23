<?php include ('../include/db.php'); 
$password = $_POST['p'];
$e1 = $_POST['email1'];
$e1 = trim($e1);
$e2 = $_POST['email2'];
$e2 = trim($e2);
$oe = $_POST['oldEmail'];
$oe = trim($oe);
$encodedEmail = $_GET['a'];
$valilation = $_GET['v'];
$nonceVerify = $_GET['n'];

	if ($e1 != $e2) {
		?>
			<script type="text/javascript">
			  alert("Email Addresses Don't Match.");
			  history.back();
			</script>
        <?php
		exit;
	}//end if ($e1 != $e2)
	
   	if (isset($password)) {
	$today = date('Y-m-d');
	$getIDQuery = $conn->query("SELECT id FROM account WHERE account.user = '".$oe."' LIMIT 1");

		if ($getIDQuery->num_rows < 1) {
			header('Location: http://'.$_SERVER['SERVER_NAME'].'/pro/login.php');
			exit;
		} else {
			while ($row = $getIDQuery->fetch_assoc()) { $id = $row["id"]; }
		}//end if ($getIDQuery->num_rows) < 1)
		
		$salt = $id * 43;
		$password = md5($password);
		$password = $salt . $password;
		$password = md5($password);
        $valilation = $id * 69;
		$activation = base64_encode($e1);
	
		$checkPassQuery = $conn->query("SELECT av FROM at WHERE at.id = '".$id."' LIMIT 1;");

		if ($checkPassQuery->num_rows < 1) {
			header('Location: http://'.$_SERVER['SERVER_NAME'].'/pro/login.php');
			exit;
		} else {
			
		while ($row = $checkPassQuery->fetch_assoc()) { $checkPass = $row["av"]; }
			if ($checkPass != $password) {
				?>
					<script type="text/javascript">
					  alert("Incorrect Password.");
					  history.back();
					</script>
				<?php
				exit;
			} else {
				$checkPaidQuery = $conn->query("SELECT paidDate FROM account WHERE account.id = '".$id."' LIMIT 1");
				while ($row = $checkPaidQuery->fetch_assoc()) { $paidDate = $row["paidDate"]; }
				if ($paidDate > $today) {
					
					$dateSevenDays = date('Y-m-d', strtotime("+7 days"));
					$conn->query("UPDATE account SET emailValid = '".$dateSevenDays."' WHERE account.id = ".$id." LIMIT 1 ;") or die ('<center>
					  <h1>Unable to update email validation</h1>
					  <h3>Something Has Gone <span style="color:#F00;">Catastrophically</span> Wrong!</h3>
					  <h3>Wait a bit and refresh the page.</h3>
					  </center>');

					include('../include/nonce.php');
					$headers = "MIME-Version: 1.0"."\r\n";
					$headers .= "Content-Type: text/html; charset=ISO-8859-1"."\r\n";
					$headers .= 'From: PayTracker Pro <noreply@'.$_SERVER['SERVER_NAME'].'>' . "\r\n";
					$subject = "PayTracker Pro - Change Email Request!";
					
					$message = '<html><body>';
					$message .= '<div id="Logo" style="margin: 0 auto; width: 305px; height: 41px;"><img src="http://'.$_SERVER['SERVER_NAME'].'/images/logo.png" alt="Pay Tracker" height="41px" width="216px" /><img src="http://'.$_SERVER['SERVER_NAME'].'/images/pro.png" alt="Pro" height="41px" width="87px" /></div>';
					$message .= '<h3 style="text-align:center;">You are receiving this email because someone requested the email address be changed for the account.</h3>';			  
					$message .= '<h3 style="text-align:center;">Click on the link below to confirm this change.</h3>';			  
					$message .= '<h4 style="text-align:center;"><b>URL: </b><a href="http://'.$_SERVER['SERVER_NAME'].'/pro/changeEmail.php?a='.$activation.'&v='.$valilation.'&n='.$nonce.'" target="_blank" title="Activate Account">http://'.$_SERVER['SERVER_NAME'].'/pro/changeEmail.php?a='.$activation.'&v='.$valilation.'&n='.$nonce.'</a></h4><br />';
					$message .= "</h4><br /><p>If clicking doesn't work, you can copy and paste the link into your browser's address bar or retype it there.</p>";
					$message .= '<p>This link will expire in 7 days.</p>';			  
					$message .= '<span style="font-size: 11px; font-style:italic;">This is an automated message sent out by '.$_SERVER['SERVER_NAME'].'</span>';			  
					$message .= '</body></html>';			  
					//send email
					mail($e1, $subject, $message, $headers);
					
  include('../include/meta.html'); ?> 
  <title>Pay Tracking Change E-mail</title>
  <link href="../style.css" rel="stylesheet" type="text/css" />
  </head>
  
  <body>
  <noscript>
  <h1 style="text-align: center;">Javascript Must Be Enabled To Use This Site</h1>
  </noscript>
  <?php include ('../include/topMenu.php'); ?>
	  
  <div id="email" class="largeContain justText">
	  <center>
	  <h3>An e-mail has been sent to <?php echo $e1; ?> with an confirmation link.</h3>
	  <br  />
	  <h4>Please check your e-mail and click the link to confirm this change.</h4>
	  <br  />
	  <h6>If you do not see the confirmation e-mail, check your Spam/Junk folder, if it's there move it to your inbox.</h6>
	  </center>
  </div><!--end email-->
  <?php include('../include/footer.html'); ?>
  </body>
  </html>				
<?php
					
				} else {
				header('Location: http://'.$_SERVER['SERVER_NAME'].'/pro/buy.php?e=1&a='.$id.'');
				}//end if ($paidDate > $today)
			
			}//end if ($checkPass != $password)
		}//end if ($getIDQuery->num_rows < 1)
	}//end if (isset($password)) 
	
	
	
	if (isset($encodedEmail)) {
		
  include('../include/meta.html'); ?> 
  <title>Pay Tracking Change E-mail</title>
  <link href="../style.css" rel="stylesheet" type="text/css" />
  </head>
  
  <body>
  <noscript>
  <h1 style="text-align: center;">Javascript Must Be Enabled To Use This Site</h1>
  </noscript>
  <?php include ('../include/topMenu.php'); ?>

<div id="email" class="largeContain">
<?php

	$getID = (int)$valilation;
	$id = ($getID / 69);
	$email = base64_decode($encodedEmail);
	$today = date('Y-m-d');
	$getValidQuery = $conn->query("SELECT * FROM account WHERE `id` = ".$id." LIMIT 1");
	
		if ($getValidQuery->num_rows > 0) {

		while ($row = $getValidQuery->fetch_assoc()) {
		$EmailValidQuery = $row["emailValid"];
		$nonce = $row["nonce"];
		}//end while ($row = $getValidQuery->fetch_assoc())
		
	  if ($nonce == $nonceVerify) {
		
		if ($EmailValidQuery <= $today) {
	?>  
	  <center>
	  <h1>Confirmation E-mail For <?php echo $email; ?> Has Expired!</h1>
	  <br />
	  <h3>Click <a href="http://'.$_SERVER['SERVER_NAME'].'/pro/account.php" target="_self" title="Resend Email">Here</a> to Change E-mail.</h3>
	  </center>
	<?php
		} else {//else if ($EmailValidQuery <= $today)
		
		$conn->query("UPDATE `account` SET  `user` =  '".$email."' WHERE  `account`.`id` = ".$id." LIMIT 1;") or die ('<center>
                <h1>Unable to change email address</h1>
				<h3>Something Has Gone <span style="color:#F00;">Catastrophically</span> Wrong!</h3>
                <h3>Wait a bit and try again</h3>
                </center>');
		$conn->query("UPDATE `account` SET `emailValid` = '".$today."' WHERE `account`.`id` = ".$id." LIMIT 1 ;") or die ('<center>
                <h1>Unable to expire email</h1>
                <h3>Something Has Gone <span style="color:#F00;">Catastrophically</span> Wrong!</h3>
                <h3>Wait a bit and try again</h3>
                </center>');
		$conn->query("UPDATE `account` SET `nonce` =  NULL WHERE `account`.`id` = ".$id." LIMIT 1 ;") or die ('<center>
                <h1>Unable to NULL nonce</h1>
                <h3>Something Has Gone <span style="color:#F00;">Catastrophically</span> Wrong!</h3>
                <h3>Wait a bit and try again</h3>
                </center>');
	?>  
	  <center>
	  <h1>E-mail Address Changed to <?php echo $email; ?>!</h1>
      <form><input type="button" value="Continue" class="myButton wideB blueB" onClick="window.location.href='account.php';"></form>
	  </center>
	<?php
		}//end if ($EmailValidQuery < $today)
	  } else {//else if ($nonce == $nonceVerify)
?>
          <center>
          <h1>Change Email For <?php echo $email; ?> Is No Longer Valid!</h1>
          <br />
          <h3>Click <a href="http://'.$_SERVER['SERVER_NAME'].'/pro/account.php" target="_self" title="Account Page">Here</a> To Try Again.</h3>
          </center>
<?php
	  }//end if ($nonce == $nonceVerify)

?>
</div><!--end email-->
<?php include('../include/footer.html'); ?>
</body>
</html>
<?php


		} else {

			?>
                <center>
                <h1>Unable to retrieve account info</h1>
                <h3>Something Has Gone <span style="color:#F00;">Catastrophically</span> Wrong!</h3>
                <h3>Check to ensure you copied the activation link properly.</h3>
                </center>
            <?php
			exit;

		}//end if ($getValidQuery->num_rows > 0)
	}//end if ((isset($encodedEmail)) || (isset($valilation)))
	
?>

