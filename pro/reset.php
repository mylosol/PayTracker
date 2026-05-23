<?php 
include ('../include/db.php');
include('../include/meta.html'); ?> 
<title>Pay Tracking - Reset Password</title>
<link href="../style.css" rel="stylesheet" type="text/css" />
</head>

<body>
<noscript>
<h1 style="text-align: center;">Javascript Must Be Enabled To Use This Site</h1>
</noscript>
<?php include ('../include/topMenu.php'); ?>
    		
<div id="wrapper" class="largeContain justText">
<?php 
     		$today = date('Y-m-d');

	if (!isset($_POST['pass1'])) {

			$activation = $_GET['a'];
			$email = base64_decode($activation);
			$valilation = $_GET['v'];
			$nonceVerify = $_GET['n'];
			$getID = (int)$valilation;
			$id = ($getID / 69);

		
			$getInfo = $conn->query("SELECT * FROM account WHERE user = '".$email."' AND `id` = ".$id." LIMIT 1");
			if ($getInfo->num_rows > 0) {

				  while ($row = $getInfo->fetch_assoc()) {
				  $EmailValidQuery = $row["emailValid"];
				  $nonce = $row["nonce"];
				  }//end while ($row = $getInfo->fetch_assoc())
				  
			if ($nonce == $nonceVerify) {
			  
				if ($EmailValidQuery <= $today) {
			?>  
			  <center>
			  <h1>Password Reset Email For <?php echo $email; ?><br />Has Expired or Has Already Been Used!</h1>
			  <br />
			  <h3>Click <a href="http://'.$_SERVER['SERVER_NAME'].'/pro/forgot.php" target="_self" title="Forgot Password">Here</a> to Resend Email.</h3>
			  </center>
			<?php
				} else {//else if ($EmailValidQuery <= $today)
			?>
  <div id="Top" class="add-load-contain">
        <div id="login" class="login">
				  <script type="text/javascript" src="js/passcheck.js"></script>
				  <form name="Reset" method="post" class="loginForm" onSubmit="return validateForm()" action="reset.php">
                  
				  <h1>Reset Password</h1>
                    <h3 style="text-align: center;">Email Address: <?php echo $email; ?></h3>
				  	<p>For your security, we can't reveal your old password but you can choose a new one that will allow you to access your account.</p>
				  <label>
                  	<span>Password: <i>(5-20 characters)</i></span><br />
                  	<input type="password" name="pass1" id="pass1" onChange="firstPass(); return false;">
                    <span id="passcheck"></span>
                  </label>
				  
				  <label>
                  	<span>Confirm Password:</span><br />
                    <input type="password" name="pass2" id="pass2" onKeyUp="checkPass(); return false;">
                    <br /><span id="confirmMessage" class="confirmMessage"></span><br />
                  </label>
                  <label>
                    <span>&nbsp;</span> 
                   	<div class="clear"></div>
                  	<input type="hidden" name="v" value="<?php echo $valilation; ?>">
                    <input type="hidden" name="a" value="<?php echo $activation; ?>">
                  	<input type="submit" name="done" class="myButton threeQuarterB blueB" value="Submit" />
                  </label> 
				  </form>
        </div><!--end login-->
  </div><!--end Top-->  
<?php
				}//end if ($EmailValidQuery < $today)
			} else {//else if ($nonce == $nonceVerify)
?>
              <center>
              <h1>Password Reset Email For <?php echo $email; ?> Is No Longer Valid!</h1>
              <br />
              <h3>Click <a href="http://'.$_SERVER['SERVER_NAME'].'/pro/forgot.php" target="_self" title="Forgot Password">Here</a> To Try Again.</h3>
              </center>
<?php
			}//end if ($nonce == $nonceVerify)

			} else {

				?>
                <center>
                <h1>Unable to get account info</h1>
                <h3>Something Has Gone <span style="color:#F00;">Catastrophically</span> Wrong!</h3>
                <h3>Check to ensure you copied the reset link properly.</h3>
                <br  />
                <h5>Click <a href="http://'.$_SERVER['SERVER_NAME'].'/pro/forgot.php" target="_self" title="Forgot Password">Here</a> to Resend Email.</h5>
                </center>
                <?php

			}//end if ($getInfo->num_rows > 0)
			
	} else {//else if (!isset($_POST['pass1']))
		$password = $_POST['pass1'];
		$activation = $_POST['a'];
		$email = base64_decode($activation);
		$valilation = $_POST['v'];
		$getID = (int)$valilation;
		$id = ($getID / 69);
		
		$salt = $id * 43;
		$password = md5($password);
		$password = $salt . $password;
		$password = md5($password);
		
		$conn->query("UPDATE at SET av = '".$password."' WHERE at.id = ".$id." LIMIT 1 ;") or die ('<center>
                <h1>Unable to update password</h1>
				<h3>Something Has Gone <span style="color:#F00;">Catastrophically</span> Wrong!</h3>
                <h3>Wait a bit and refresh the page or click <a href="http://'.$_SERVER['SERVER_NAME'].'/pro/forgot.php" target="_self" title="Forgot Password">Here</a> to try again.</h3>
                </center>');
		
		$conn->query("UPDATE account SET emailValid = '".$today."' WHERE account.id = ".$id." LIMIT 1 ;") or die ('<center>
                <h1>Unable to update email validation date</h1>
                <h3>Something Has Gone <span style="color:#F00;">Catastrophically</span> Wrong!</h3>
                <h3>Wait a bit and refresh the page or click <a href="http://'.$_SERVER['SERVER_NAME'].'/pro/forgot.php" target="_self" title="Forgot Password">Here</a> to try again.</h3>
                </center>');
				
		$conn->query("UPDATE `account` SET `nonce` =  NULL WHERE `account`.`id` = ".$id." LIMIT 1 ;") or die ('<center>
                <h1>Unable to NULL nonce</h1>
                <h3>Something Has Gone <span style="color:#F00;">Catastrophically</span> Wrong!</h3>
                <h3>Wait a bit and refresh the page or click <a href="http://'.$_SERVER['SERVER_NAME'].'/pro/forgot.php" target="_self" title="Forgot Password">Here</a> to try again.</h3>
                </center>');

		?>
			  <center>
			  <h1>Password Reset For <?php echo $email; ?>!</h1>
			  <br />
              <?php if (isset($_COOKIE['pro'])) { ?>
              <form><input type="button" value="Continue" class="myButton wideB blueB" onClick="window.location.href='account.php';"></form>
			  <?php } else { ?>
			  <h3>Click <a href="http://'.$_SERVER['SERVER_NAME'].'/pro/login.php" target="_self" title="Login">Here</a> to Login.</h3>
              <?php } ?>
			  </center>
        <?php
}//end if (!isset($_POST['pass1']))
?>
</div><!--end wrapper-->

<?php include('../include/footer.html'); ?>
</body>
</html>