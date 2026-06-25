<?php 
include ('../include/db.php');
	if (isset($_COOKIE['pro'])) { $pro = $_COOKIE['pro']; }
	$today = date('Y-m-d');
	if (isset($pro)) {
		header('Location: http://'.$_SERVER['SERVER_NAME'].'/');
	}

include('../include/meta.html'); ?> 
<title>Pay Tracking Portal</title>
<link href="../style.css" rel="stylesheet" type="text/css" />
<style type="text/css">
ul.bl {
	font-size: 18px;
	color: #CCC;
	font-weight: bold;
}

.screenshot {
	margin: 0 auto;
}

.close_button {
	position: fixed;
	top: 10px;
	right: 10px;
	height: 50px;
	width: 50px;
}
</style>
</head>

<body>
<noscript>
<h1 style="text-align: center;">Javascript Must Be Enabled To Use This Site</h1>
</noscript>
<?php include ('../include/topMenu.php'); 
$seen = $_COOKIE['seen'];
if ($seen == 1) {
?>
	<div id="close_button" class="close_button"><a href="closeAD.php" target="_self" title="Close"><img src="../images/close_button.png" border="0" height="100%" width="100%" /></a></div>
<?php 
}//end if ($_COOKIE['seen'] == 1) ?>
    		
<div id="wrapper" class="largeContain justText tac">
<h4 style="text-align: center;">Already Have an Account? Login <a href="login.php" title="Login" target="_self">Here</a>.</h4>
</div><!--end wrapper-->


<div id="wrapper" class="largeContain">
  <div id="Top" class="add-load-contain">
<h3 align="center">Upgrade to:</h3>
<div id="Logo" style="margin: 0 auto; width: 305px; height: 41px;"><img src="http://'.$_SERVER['SERVER_NAME'].'/images/logo.png" alt="Pay Tracker" height="41px" width="216px" /><img src="http://'.$_SERVER['SERVER_NAME'].'/images/pro.png" alt="Pro" height="41px" width="87px" /></div>

<br />
<div id="30 day" class="30day"><a href="login.php?c=1" target="_self"><img src="../images/30-day-button.png" border="0" /></a></div><!--end 30 day-->
<hr />

<ul class="bl">
<li>Track your loads for the whole week.</li>
<li>Keep a closer eye on your money.</li>
</ul>
<div class="screenshot"><img src="../images/ss1.png" border="0" width="270px"/></div><!--end screen shot-->
<br />
<hr />
<ul class="bl">
<li>Reconcile against your pay sheet, quickly and easily confirm that a load has been paid.</li>
<li>Keep track of what has been paid and what hasn't.</li>
<li>Also tracks &quot;splits, pumps, move truck, and more&quot;</li>
</ul>
<div class="screenshot"><img src="../images/ss2.png" border="0" width="270px"/></div><!--end screen shot-->
<br />
<hr />
<ul class="bl">
<li>Keep notes on tracked loads.</li>
</ul>
<div class="screenshot"><img src="../images/ss3.png" border="0" width="270px"/></div><!--end screen shot-->
<br />
<hr />
<form method="post" action="login.php?c=1">
<input type="submit" name="reset" class="myButton wideB blueB" value="Sign Up Now" />
</form> 
<hr />
<br />
<center><p><a href="closeAD.php" target="_self" title="Close">No Thanks, I'll Keep My Free Account</a></p></center>

  </div><!--end Top-->  
</div><!--end wrapper-->

<?php include('../include/footer.html'); ?>
</body>
</html>