<?php 

$confirm = $_POST["yes"];
$deny = $_POST["no"];

$_SESSION['originalref2'] = $_SERVER['HTTP_REFERER'];
$purl = $_SESSION['originalref2'];

if (isset($_COOKIE['beta'])) { 
	$beta = 1; 
}//end if (isset($_COOKIE['beta']))

if (isset($_COOKIE['pensacola'])) {
	$pensacola = 1;
}//end if (isset($_COOKIE['pensacola']))

if (isset($confirm)) {
	
  if (isset($_SERVER['HTTP_COOKIE'])) {
	  $cookies = explode(';', $_SERVER['HTTP_COOKIE']);
	  foreach($cookies as $cookie) {
		  $parts = explode('=', $cookie);
		  $name = trim($parts[0]);
		  setcookie($name, '', time()-1000);
		  setcookie($name, '', time()-1000, '/');
	  }//end foreach($cookies as $cookie)
  }//end if (isset($_SERVER['HTTP_COOKIE']))
  	
		if ($pensacola == 1) {
			if (isset($beta)) {
			  header("Location: http://".$_SERVER['SERVER_NAME']."?c=pensacola&b=1");
			} else {
			  header("Location: http://".$_SERVER['SERVER_NAME']."/pensacola/");
			}//end if (isset($beta))
		} else {
			if ($beta == 1) {
			  header("Location: http://".$_SERVER['SERVER_NAME']."?b=1");
			} else {
			  header("Location: http://".$_SERVER['SERVER_NAME']."/");
			}//end if (isset($beta))
		}//end if (isset($_COOKIE['pensacola']))
}//end if (isset($confirm))

if (isset($deny)) {
	header("Location: http://".$_SERVER['SERVER_NAME']."/");
}//end if (isset($deny))



include('include/meta.html'); ?> 
<title>Pay Checking Annihilate </title>
<link href="style.css" rel="stylesheet" type="text/css" />
<script type="text/javascript" src="js/addInput.js"></script>
</head>

<body>
<noscript>
<h1 style="text-align: center;">Javascript Must Be Enabled To Use This Site</h1>
</noscript>
<?php
	$p = 0;
	$f = 0;
    include ('include/frontMenu.php'); 
	if (isset($beta)) { 
		echo "<div id=\"betalogo\" class=\"beta\"><img src=\"../images/beta-testing.png?v=4\" alt=\"Beta\" class=\"beta\" /></div>";	
	}//end if (isset($beta))
	if ((isset($_COOKIE['pensacola'])) || ($homeCity == "pensacola") || ($pcola == 1)) { ?>
	<div id="city" class="city"><p class="cityText"><strong>Pensacola</strong></p></div><!--end city-->
	<?php } else { ?>
	<div id="city" class="city"><p class="cityText"><strong>Panama City</strong></p></div><!--end city-->
	<?php	
	}//end if ((isset($_COOKIE['pensacola'])) || ($homeCity == "pensacola"))  ?> 
	

<div id="wrapper" class="largeContain">
	  <div id="Top" class="add-load-contain">
      
      <center>
      	<img src="images/annihilate.png" alt="Annihilate Icon" />
      
      <br />
      <h1 style="text-align: center;">Annihilate</h1>

<p style="text-align: center; font-size: 14px;">This is for when something goes truly screwy and you need to reset EVERYTHING!</p>

<br />
<br />

	<form action="annihilate.php" method="post">
	<p style="font-size:14px; font-weight: bold; text-align: center;">Are You Sure You Want To Do This?</p>
    <hr />
    <input type="submit" class="myButton wideB redB" name="yes" value="Yes, Annihilate!" />
    <br /><br />
    <input type="submit" class="myButton wideB blueB" name="no" value="No, Cancel!" />
    <input type="hidden" name="cpurl" value="<?php echo $purl; ?>" />
    </form>
    </center>

	</div><!--end Top-->
</div><!--end wrapper-->


<?php	include ('include/footer.html'); 
	if ((!isset($pro)) || ((isset($pro)) && ($valid == 0))) { ?>
<br />
<div id="adPad" class="clear">&nbsp;</div><!--end adPad-->
<div id="bottomAd" class="adLock">
<center>
<?php 	
include ('include/ads/googlead.html'); 
?>
</center>
</div><!--end bottomAd-->
<?php }//end if (!isset($pro)) ?>
</body>
</html>
