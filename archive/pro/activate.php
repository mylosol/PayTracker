<?php 
include ('../include/db.php');
include('../include/meta.html'); ?> 
<title>Pay Tracker Activation</title>
<link href="../style.css" rel="stylesheet" type="text/css" />
</head>

<body>
<noscript>
<h1 style="text-align: center;">Javascript Must Be Enabled To Use This Site</h1>
</noscript>
<?php include ('../include/topMenu.php'); ?>
    		
<div id="wrapper" class="largeContain justText">

<?php 

	$encodedEmail = $_GET['a'];
	$valilation = $_GET['v'];
	$nonce = $_GET['n'];
	$getID = (int)$valilation;
	$id = ($getID / 69);
	$email = base64_decode($encodedEmail);
	$dateSevenDays = date('Y-m-d', strtotime("+7 days"));
	$today = date('Y-m-d');
	$getValidQuery = $conn->query("SELECT * FROM account WHERE user = '".$email."' AND `id` = ".$id." LIMIT 1");
	
		if ($getValidQuery->num_rows > 0) {

			while ($row = $getValidQuery->fetch_assoc()) {
			$EmailValidQuery = $row["emailValid"];
			$AccountValidQuery = $row["accountValid"];
			$nonceVerify = $row["nonce"];
			}//end while ($row = $getValidQuery->fetch_assoc())
			
	
	if ($AccountValidQuery == 1) {
	  ?>  
		<center>
		<h1>Account for <?php echo $email; ?> has already been activated.</h1>
		<br />
        <h3>Click <a href="http://'.$_SERVER['SERVER_NAME'].'/pro/login.php" target="_self" title="Home Page">Here</a> to Login.</h3>
		</center>
	  <?php
	} else {//else if ($AccountValidQuery == 1)
	
	
	if ($nonce == $nonceVerify) {		
		
		if ($EmailValidQuery <= $today) {
	?>  
	  <center>
	  <h1>Activation email for <?php echo $email; ?> has expired!</h1>
	  <br />
	  <h3>Click <a href="http://'.$_SERVER['SERVER_NAME'].'/pro/resend.php?a=<?php echo $_GET['a']; ?>&v=<?php echo $_GET['v']; ?>" target="_self" title="Resend Email">Here</a> to Resend Email.</h3>
	  </center>
	<?php
		} else {//else if ($dateSevenDays < $today)
		
		$dateThirtyDays = date('Y-m-d', strtotime("+30 days"));
		$dateThirtyDaysDisplay = date('l F jS, Y', strtotime("+30 days"));
		$dateYear = date('Y');

	  $getPa = $conn->query("SELECT * FROM pa WHERE email LIKE '".$email."' LIMIT 1;");
	  if ($getPa->num_rows > 0) {
		  
		$trial = "<h2>Your 30 Free trial has expired, you will need to add time to your account.</h2>";
		  				
	  } else {
		  
		$conn->query("UPDATE account SET paidDate =  '".$dateThirtyDays."', accountValid = '1', emailValid = '".$today."' WHERE account.id = ".$id." LIMIT 1 ;") or die ('<center>
                <h1>Unable to set paid date</h1>
				<h3>Something Has Gone <span style="color:#F00;">Catastrophically</span> Wrong!</h3>
                <h3>Contact The <a href="mailto:help@'.$_SERVER['SERVER_NAME'].'?subject=Error on account activation&body=Unable to set paid date for account '.$email.'">Webmaster</a> For Assistance</h3>
                </center>');
		$trial = "<h2>Your 30 Free trial will expire on ".$dateThirtyDaysDisplay."</h2>";
		
	  }//end if ($getPa->num_rows > 0)
		
		$conn->query("UPDATE account SET emailValid = '".$today."' WHERE account.id = ".$id." LIMIT 1 ;") or die ('<center>
                <h1>Unable to expire email</h1>
                <h3>Something Has Gone <span style="color:#F00;">Catastrophically</span> Wrong!</h3>
                <h3>Contact The <a href="mailto:help@'.$_SERVER['SERVER_NAME'].'?subject=Error on account activation&body=Unable to expire email for account '.$email.'">Webmaster</a> For Assistance</h3>
                </center>');
				
		$conn->query("UPDATE `account` SET `nonce` =  NULL WHERE `account`.`id` = ".$id." LIMIT 1 ;") or die ('<center>
                <h1>Unable to NULL nonce</h1>
                <h3>Something Has Gone <span style="color:#F00;">Catastrophically</span> Wrong!</h3>
                <h3>Contact The <a href="mailto:help@'.$_SERVER['SERVER_NAME'].'?subject=Error on account activation&body=Unable to NULL nonce for account '.$email.'">Webmaster</a> For Assistance</h3>
                </center>');
				
		$conn->query("CREATE TABLE loads".$id." (frtl INT( 7 ) NOT NULL, date DATETIME NOT NULL, variables VARCHAR( 30 ) NOT NULL, loadinfo VARCHAR( 100 ) NOT NULL, paid VARCHAR( 20 ) NOT NULL DEFAULT '1-0-0-0-0-0-0-0', notPaid INT( 1 ) NOT NULL DEFAULT  '0', notes VARCHAR( 1000 ) NULL, np DECIMAL ( 6,2 ) NOT NULL DEFAULT '0.00', op DECIMAL ( 6,2 ) NOT NULL DEFAULT '0.00', PRIMARY KEY ( frtl )) ENGINE = MYISAM CHARACTER SET utf8 COLLATE utf8_general_ci") or die ('<center>
                <h1>Unable to create load table</h1>
				<h3>Something Has Gone <span style="color:#F00;">Catastrophically</span> Wrong!</h3>
                <h3>Contact The <a href="mailto:help@'.$_SERVER['SERVER_NAME'].'?subject=Error on account activation&body=Unable to create load table for account '.$email.'">Webmaster</a> For Assistance</h3>
                </center>');
				
		$conn->query("INSERT INTO totals (id, ydy) VALUES ('".$id."', '".$dateYear."');") or die ('<center>
                <h1>Unable to insert new account into totals table</h1>
                <h3>Something Has Gone <span style="color:#F00;">Catastrophically</span> Wrong!</h3>
                <h3>Contact The <a href="mailto:help@'.$_SERVER['SERVER_NAME'].'?subject=Error on account activation&body=Unable to insert new account into totals table for account '.$email.'">Webmaster</a> For Assistance</h3>
                </center>');
	?>  
	  <center>
	  <h1>Account for <?php echo $email; ?> has been activated!</h1>
	  <br />
	  <?php echo $trial; ?>
	  <br />
	  <h3>Click <a href="http://'.$_SERVER['SERVER_NAME'].'/pro/login.php" target="_self" title="Home Page">Here</a> to Login.</h3>
	  </center>
	
    	<hr />
		<br />
      <center>
      <iframe width="360" height="650" src="http://www.youtube.com/embed/Wy8e1iz9qZo" frameborder="0" allow="accelerometer; autoplay; encrypted-media; gyroscope; picture-in-picture" allowfullscreen></iframe>
      </center>


	<?php
	$bcc = "1";
	include ('../include/bccEmail.php');
		}//end if ($EmailValidQuery < $today)
	} else {//else if ($nonce == $nonceVerify)
?>
	  <center>
	  <h1>Activation email for <?php echo $email; ?> is no longer valid!</h1>
	  <br />
	  <h3>Click <a href="http://'.$_SERVER['SERVER_NAME'].'/pro/resend.php?a=<?php echo $_GET['a']; ?>&v=<?php echo $_GET['v']; ?>" target="_self" title="Resend Email">Here</a> to Resend Email.</h3>
	  </center>
<?php	
	}//end if ($nonce == $nonceVerify)
		
	}//end if ($AccountValidQuery == 1)


		} else {

			?>
              <center>
              <h1>Account Associated With <?php echo $email; ?> Does Not Exist!</h1>
              <br />
              <h3>Click <a href="http://'.$_SERVER['SERVER_NAME'].'/pro/create.php" target="_self" title="Resend Email">Here</a> to Create an Account.</h3>
              </center>
            <?php
			exit;

  }//end if ($getValidQuery->num_rows > 0)

?>

</div><!--end wrapper-->
<?php include('../include/footer.html'); ?>
</body>
</html>