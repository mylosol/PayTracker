<?php
include ('../include/db.php'); 

  $email = $_POST['email'];
  $email = $conn->real_escape_string($email);
  $password = $_POST['pass1'];
  $agree = $_POST['agree'];

  $checkQuery = $conn->query("SELECT * FROM account WHERE user = '".$email."' LIMIT 1");
		
if ($checkQuery->num_rows > 0) {
			
		$accountValid = $checkQuery["accountValid"];
		$id = $checkQuery["id"];

		if ($accountValid < 1) {
			$activation = base64_encode($email);
			$valilation = $id * 69;
			header('Location: http://'.$_SERVER['SERVER_NAME'].'/pro/resend.php?a='.$activation.'&v='.$valilation.'&na=2');
			exit;
		} else {
			header('Location: http://'.$_SERVER['SERVER_NAME'].'/pro/login.php?c=1&d=1');
			exit;
		}//end accountValid


} else {

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
				$dateYesterday = date('Y-m-d', strtotime("-1 days"));
				$dateSevenDays = date('Y-m-d', strtotime("+7 days"));
				$conn->query("INSERT INTO account (id, user, joinDate, paidDate, emailValid, nonce, accountValid) VALUES (NULL, '".$email."', NOW(), '".$dateYesterday."', '".$dateSevenDays."', NULL, '0');") or die ('<center>
                <h1>Unable to insert new account</h1>
				<h3>Something Has Gone <span style="color:#F00;">Catastrophically</span> Wrong!</h3>
                <h3>Wait a bit and refresh the page or click <a href="http://'.$_SERVER['SERVER_NAME'].'/pro/login.php?c=1" target="_self" title="Create Account">Here</a> to try again.</h3>
                </center>');
		  
				$newid = $conn->insert_id;
				$salt = $newid * 43;
				$password = md5($password);
				$password = $salt . $password;
				$password = md5($password);
				$conn->query("INSERT INTO at (id, av) VALUES ('".$newid."', '".$password."');") or die ('<center>
                <h1>Unable to insert password</h1>
				<h3>Something Has Gone <span style="color:#F00;">Catastrophically</span> Wrong!</h3>
                <h3>Wait a bit and refresh the page or click <a href="http://'.$_SERVER['SERVER_NAME'].'/pro/login.php?c=1" target="_self" title="Create Account">Here</a> to try again.</h3>
                </center>');
				$activation = base64_encode($email);
				$valilation = $newid * 69;
				
				$id = $newid;
				include('../include/nonce.php');
				
			  //Email information
			
			  $headers = "MIME-Version: 1.0"."\r\n";
			  $headers .= "Content-Type: text/html; charset=ISO-8859-1"."\r\n";
			  $headers .= 'From: PayTracker Pro <noreply@'.$_SERVER['SERVER_NAME'].'>' . "\r\n";
			  $subject = "PayTracker Pro - Welcome Aboard!";
			  
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
	  ?>
	  <center>
	  <h3>An email has been sent to <?php echo $email; ?> with an activation link.</h3>
	  <br  />
	  <h4>Please check your email and click the link to activate your account.</h4>
	  <br  />
	  <h6>If you do not see the activation email, check your Spam/Junk folder, if it's there move it to your inbox.</h6>
	  </center>
		  
  </div><!--end wrapper-->
  
  <?php include('../include/footer.html'); ?>
  </body>
  </html>
  
  <?php 		

}//end if ($checkQuery->num_rows > 0)   
?>

