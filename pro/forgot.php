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
    
    	<?php if (isset($_POST['email'])) {
			
			$email = $_POST['email'];
			$activation = base64_encode($email);
			
			$getID = $conn->query("SELECT id FROM account WHERE user = '".$email."' LIMIT 1;");
			
			if ($getID->num_rows > 0) {

			  while ($row = $getID->fetch_assoc()) { $id = $row{"id"}; }
			  $valilation = $id * 69;
			  $dateTomorrow = date('Y-m-d', strtotime("+1 days"));
			  $conn->query("UPDATE account SET emailValid = '".$dateTomorrow."' WHERE account.id = ".$id." LIMIT 1 ;") or die ('<center>
			    <h1>Unable to set email expiration date</h1>
                <h3>Something Has Gone <span style="color:#F00;">Catastrophically</span> Wrong!</h3>
                <h3>Wait a bit and refresh the page or click <a href="http://'.$_SERVER['SERVER_NAME'].'/pro/forgot.php" target="_self" title="Forgot Password">Here</a> to try again.</h3>
                </center>');

              include('../include/nonce.php');
			  //Email information
			  $headers = "MIME-Version: 1.0"."\r\n";
			  $headers .= "Content-Type: text/html; charset=ISO-8859-1"."\r\n";
			  $headers .= 'From: Pay Tracker Pro <noreply@'.$_SERVER['SERVER_NAME'].'>' . "\r\n";
			  $subject = "Pay Tracker Pro - Password Reset";
			  
			  $message = "<html><body>";
			  $message .= '<div id="Logo" style="margin: 0 auto; width: 305px; height: 41px;"><img src="http://'.$_SERVER['SERVER_NAME'].'/images/logo.png" alt="Pay Tracker" height="41px" width="216px" /><img src="http://'.$_SERVER['SERVER_NAME'].'/images/pro.png" alt="Pro" height="41px" width="87px" /></div>';
			  $message .= '<h4 style="text-align: center;">You are receiving this email because someone requested the password be reset for the account under this email address.</h4>';
			  $message .= '<h4 style="text-align: center;">Click on the link below to reset your Pay Tracker Pro password.</h4>';
			  $message .= '<h4 style="text-align: center;">';
			  $message .= '<b>URL: </b> <a href="http://'.$_SERVER['SERVER_NAME'].'/pro/reset.php?a='.$activation.'&v='.$valilation.'&n='.$nonce.'" target="_blank" title="Reset Password">http://'.$_SERVER['SERVER_NAME'].'/pro/reset.php?a='.$activation.'&v='.$valilation.'&n='.$nonce.'</a>'; 
			  $message .= "</h4><br /><p>If clicking doesn't work, you can copy and paste the link into your browser's address bar or retype it there.</p>";
			  $message .= "<p>This link will expire in 1 day.</p>";
			  $message .= '<span style="font-size: 11px; font-style:italic;">';
			  $message .= 'This is an automated message sent out by '.$_SERVER['SERVER_NAME'].'';
			  $message .= "</span>";
			  $message .= "</body></html>";
			  
			  //send email
			  mail($email, $subject, $message, $headers);

	?>
	<center>
    <h3>An Email has been sent to <?php echo $email; ?> with an reset link.</h3>
    <br  />
    <h4>please check your email and click the link to reset your password.</h4>
    <br  />
    <h6>If you do not see the reset email, check your Spam/Junk folder, if it's there move it to your inbox.</h6>
    </center>
        
<?php			

			} else {
				
			  ?>
			  <center>
			  <h3>The email <?php echo $email; ?> does not match our records.</h3>
			  <br  />
			  <h5>Please check your email and try again.</h5>
			  </center>
			  <?php
			  exit;

			}//end ($getID->num_rows > 0)

		} else {//else if (isset($_POST))
?>
  <div id="Top" class="add-load-contain">
        <div id="login" class="login">
        <?php
						
				  ?>
				  <form name="Forgot" method="post" class="loginForm">
                  
				  <h1>Forgot Password</h1>
				  <label>
                    <span>Email Address:</span><br />
                    <input name="email" type="email" id="email">
                  </label> 
                  <label>
                    <span>&nbsp;</span> 
                   	<div class="clear"></div>
                  <input type="submit" name="done" class="myButton threeQuarterB blueB" value="Submit" />
                  </label> 
				  
				  </form>
                  <br />
		
        </div><!--end login-->
  </div><!--end Top-->  
<?php		}//end if (isset($_POST)) ?>
</div><!--end wrapper-->

<?php include('../include/footer.html'); ?>
</body>
</html>