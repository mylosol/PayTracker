<?php
include ('../include/db.php');

	$pro = $_COOKIE['pro'];
	$today = date('Y-m-d');
	if (isset($pro)) {
		$id = base64_decode($pro);
		  $checkPaidQuery = $conn->query("SELECT paidDate FROM account WHERE account.id = '".$id."' LIMIT 1");
		  if ($checkPaidQuery->num_rows > 0) {
			  while ($row = $checkPaidQuery->fetch_assoc()) { $paidDate = $row["paidDate"]; }
			  if ($paidDate < $today) {
				  $valid = 0;
			  } else {
				  $valid = 1;
				  $pd = date("F jS, Y", strtotime($paidDate));
			  }//end if ($paidDate < $today)
				  
		  } else {

			  ?>
				<script type="text/javascript">
				  alert("Unable to validate account!");
				</script>
				<meta http-equiv="refresh" content="0;URL='http://'.$_SERVER['SERVER_NAME'].'/pro/logout.php'" /> 
			  <?php
			  exit;	
			  
		  }//end if ($checkPaidQuery->num_rows > 0)
	}//end 	if (isset($proCookie)) 


//////////////////////////////////////////////////////////////////////////////////////////

$cancel = $_POST['cancel'];
$done = $_POST['confirm'];
$delete1 = $_POST['delete'];
$delete2 = trim($delete1);
$delete = strtolower($delete2);

if (isset($cancel)) {
header("Location: http://".$_SERVER['SERVER_NAME']."/pro/account.php");
exit;
}//end if (isset($cancel))

if (isset($done)) {
	if ($delete == "delete") {
		$getEmailQuery = $conn->query("SELECT `user` FROM `account` WHERE `account`.`id` = ".$id." LIMIT 1;");
		
		while ($row = $getEmailQuery->fetch_assoc()) {
			
		  $conn->query("INSERT INTO `pa` (`id` ,`email`) VALUES ('".$id."', '".$email."');");	
		  $conn->query("DELETE FROM `at` WHERE `at`.`id` = ".$id." LIMIT 1");
		  $conn->query("DELETE FROM `totals` WHERE `totals`.`id` = ".$id." LIMIT 1;");
		  $conn->query("DELETE FROM `account` WHERE `account`.`id` = ".$id." LIMIT 1;");
		  $conn->query("DROP TABLE `loads".$id."`");
		
		}//end while ($row = $getEmailQuery->fetch_assoc())
		
		include('../include/deleteEmail.php');
		setcookie("pro", 1, time()-1, '/');
		header('Location: http://'.$_SERVER['SERVER_NAME'].'/');
		
	}  else {
	?>
				<script type="text/javascript">
				  alert("Please confirm you wish to delete account.");
				  history.back();
				</script>
	<?php	
		exit;
	}//endif ($delete == "delete")

}//end if (isset($done))


include('../include/meta.html'); ?> 
<title>Pay Tracking My Account</title>
<link href="../style.css" rel="stylesheet" type="text/css" />
<script type="text/javascript" src="js/change.js"></script>
</head>

<body>
<noscript>
<h1 style="text-align: center;">Javascript Must Be Enabled To Use This Site</h1>
</noscript>
<?php include ('../include/topMenu.php'); ?>

<div id="delete" class="largeContain justText">
  <h1 style="text-align:center;">Delete Account</h1>
  
  <p style="text-align:center;">Deleting your account will permanently remove all data you have submitted, including all loads tracked.</p>
  <p style="text-align:center;"><span style="color:#F00;">*!*</span> This Can Not Be Undone! <span style="color:#F00;">*!*</span></p>
  
  <?php if ($valid == 1) { ?>
    <center>
    <img src="../images/warning.png" title="!" border="0">
    <img src="../images/warning.png" title="!" border="0">
    <img src="../images/warning.png" title="!" border="0">
    </center>
    <h2 style="text-align:center;">Your Account is Paid Through <?php echo $pd; ?>!</h2>
    <h3 class="bcml">Be Advised, No Refunds Will Be Issued For Unused Time.</h3>
    <h4 class="bcml">Deleting Your Account Will Cause You To Lose This Paid Time.</h4>
    <center>
    <img src="../images/warning.png" title="!" border="0">
    <img src="../images/warning.png" title="!" border="0">
    <img src="../images/warning.png" title="!" border="0">
    </center>
  <?php }//end if ($valid == 1) ?>

<hr width="75%">
<br />
	<center>
    <form method="post">
    Type 'Delete' to confirm:&nbsp;<input name="delete" type="text" width="5">
    <div class="clear"></div>
    <br />
    <input type="submit" name="cancel" class="myButton quarterB greyB" value="Cancel" />
    <input type="submit" name="confirm" class="myButton halfB blueB" value="Confirm" />
    </form>
    </center>

</div><!--end delete-->


<?php include('../include/footer.html'); ?>
</body>
</html>