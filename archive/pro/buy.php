<?php 
include ('../include/db.php');
include ('../include/cookiecheckMP.php');
include('../include/meta.html'); ?> 
<title>Pay Tracking Portal</title>
<link href="../style.css" rel="stylesheet" type="text/css" />
<style>
.add-load-contain { text-align: center; }
</style>
</head>

<body>
<noscript>
<h1 style="text-align: center;">Javascript Must Be Enabled To Use This Site</h1>
</noscript>
<?php include ('../include/topMenu.php'); 
$expired = $_GET['e'];

if ($valid == 1) {
	$verbage = "Add";
}

if ($valid == 0) {
	$verbage = "Buy";
}


if (isset($expired)) {
?>
<div id="expired wrapper" class="largeContain justText">
<h3 style="text-align:center;">Your Paytracker Pro Subscription has expired.  Please add more time to continue using the pro features.</h3>
</div><!--end wrapper-->
<?php
}//end if (isset($expired))
$rp = $_COOKIE["rp"];
if (!isset($rp)) { $rp = 0; }
$getDateQuery = $conn->query("SELECT * FROM `account` WHERE `account`.`id` = ".$id." LIMIT 1;");
while ($row = $getDateQuery->fetch_assoc()) { $ulk = $row["ulk"]; }//end while ($row = $getDateQuery->fetch_assoc())
  if ($ulk == 0 && $rp == 0) { 
?>

	<div id="rp" class="takeover">
    
    </div><!--end takeover-->

<?php
  }//end if ($ulk == 0)
?>
    		
<div id="12 month wrapper" class="largeContain">
  <div id="Top" class="add-load-contain">
<img src="../images/bestvalue.png" class="bv">
<h1>12 Months</h1>
<h3><strike>$48.00</strike></h3>
<h2 class="bvp moneyDisplay">$36.00</h2>
<br />
<form><input type="button" value="<?php echo $verbage; ?> 12 Months" onClick="window.location.href='/pro/checkout.php?t=12'" class="myButton wideB blueB"></form>    

  </div><!--end Top-->  
</div><!--end wrapper-->

<div id="6 month wrapper" class="largeContain">
  <div id="middle" class="add-load-contain">
	<div id="6 months" class="locked">
	  <?php if ($ulk == 0) { ?>
      <div class="overlay"><img src="../images/locked.png" border="0" /></div>
      <?php }//end if ($ulk == 0) ?>  
      <h1>6 Months</h1>
      <h3><strike>$24.00</strike></h3>
      <h2 class="bvp moneyDisplay">$21.00</h2>
      <br />
      <form><input type="button" value="<?php echo $verbage; ?> 6 Months" onClick="window.location.href='/pro/checkout.php?t=6'" class="myButton wideB blueB"></form>    
	</div><!--end 6 months-->
  </div><!--end middle-->  
</div><!--end wrapper-->

<div id="3 month wrapper" class="largeContain">
  <div id="bottom" class="add-load-contain">
	<div id="3 months" class="locked">
	  <?php if ($ulk == 0) { ?>
      <div class="overlay"><img src="../images/locked.png" border="0" /></div>
      <?php }//end if ($ulk == 0) ?>  
      <h1>3 Months</h1>
      <h3 class="moneyDisplay">$12.00</h3>
      <br />
      <form><input type="button" value="<?php echo $verbage; ?> 3 Months" onClick="window.location.href='/pro/checkout.php?t=3'" class="myButton wideB blueB"></form>    
	</div><!--end 3 months-->
  </div><!--end bottom-->  
</div><!--end wrapper-->
        
<?php
include('../include/footer.html'); ?>
</body>
</html>