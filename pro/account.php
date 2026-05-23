<?php 
include ('../include/db.php');
include ('../include/cookiecheckMP.php');
include('../include/meta.html'); ?> 
<title>Pay Tracking My Account</title>
<link href="../style.css" rel="stylesheet" type="text/css" />
<script type="text/javascript" src="js/change.js"></script>
</head>

<body>
<noscript>
<h1 style="text-align: center;">Javascript Must Be Enabled To Use This Site</h1>
</noscript>
<?php include ('../include/topMenu.php'); 
$curYear = date('Y');
$pd = date("F jS, Y", strtotime($paidDate));

$getAccountQuery = $conn->query("SELECT * FROM account WHERE id = ".$id." LIMIT 1");
if ($getAccountQuery->num_rows > 0) {

	while ($row = $getAccountQuery->fetch_assoc()) {
	$emailAddress = $row["user"];
	$joinDate = $row["joinDate"];
	}//end while ($row = $getAccountQuery->fetch_assoc())
	$emailExplode = explode("@", $emailAddress);
	$jd = date("F jS, Y", strtotime($joinDate));

} else {

	echo '<center><h1>Unable to retrieve account information</h1>
		  <h3>Something Has Gone <span style="color:#F00;">Catastrophically</span> Wrong!</h3>
		  <h3>Wait a bit and try again</h3>
		  </center>';

}//end if ($getAccountQuery->num_rows > 0)
?>
    		
<div id="overview" class="largeContain">

	<center>
    <h1>My Account - <?php echo ucfirst($emailExplode[1]); ?></h1>
    <h3>Member Since <span class="dateLook1"><?php echo $jd; ?></span></h3>
   
    <h3>
	<?php 	if ($valid == 0) { ?>
    <img src="../images/warning.png" title="!" border="0">
    <?php }//end if ($valid != 0) ?>
    Paid Until <span class="dateLook1"><?php echo $pd; ?></span>
    <?php 	if ($valid == 0) { ?>
    <img src="../images/warning.png" title="!" border="0">
    <?php }//end if ($valid != 0) ?>
    </h3>
    <br />
    <form><input type="button" value="Add Time" onClick="window.location.href='/pro/buy.php'" class="myButton wideB blueB"></form>    
    </center>
</div><!--end overview-->

<div id="email" class="largeContain">
  <div id="eConatin" class="add-load-contain">

    
    <div id="cdiv">
    <h3 style="text-align: center;"><u>Change Email Address</u></h3>
    
    <form name="Form" method="post" class="loginForm" onSubmit="return validateForm()" action="changeEmail.php">
    <label>
      <span>Current Password:</span><br />
      <input type="password" name="p" />
    </label>
    <label>
      <span>New Email:</span><br />
      <input type="text" name="email1" id="newEmail1" onChange="firstEmail(); return false;">
      <span id="emailcheck"></span>
    </label>
    <label>
      <span>Confirm Email:</span><br />
      <input type="text" name="email2" id="newEmail2" onKeyUp="checkEmail(); return false;">
      <br /><div id="confirmMessage" class="confirmMessage"></div>
    </label>
    <input type="hidden" name="oldEmail" value="<?php echo $emailAddress; ?>" />
    <input type="submit" name="cea" value="Change Email Address" class="myButton wideB blueB" />    
    </form>
    <br /><br /><br /><hr width="75%"><h3 style="text-align: center;"><u>Change Password</u></h3><br />
    <form action="forgot.php" method="post">
    <input type="hidden" name="email" value="<?php echo $emailAddress; ?>" />
    <input type="submit" name="reset" value="Reset Password" class="myButton wideB blueB" />
    </form>
    <br /><br /><br /><hr width="75%"><h3 style="text-align: center;"><u>Delete Account</u></h3><br />
    <form><input type="button" value="Delete Account" class="myButton wideB blueB" onClick="window.location.href='deleteAccount.php';"></form>

    
    </div><!--end cediv-->
    
	
  </div><!--end eConatin-->  
</div><!--end email-->


<?php include('../include/footer.html'); ?>
</body>
</html>