<?php 
include('include/db.php');
include('include/cookiecheckMP.php');
include ('include/variables.php');	  
	
	
	if (isset($_COOKIE['rt'])) { $rt = 1; }
	if (isset($_COOKIE['owl'])) { $owl = 1; }
	
	
	$beta = 0;
	$beta = $_GET['b'];
	if(isset($beta)) {
		setcookie("beta", 1, time()+31536000); 
	}//end if(isset($beta))
	
	$homeCity = $_GET['c'];
	$pcola = 0;
	$eTitle = "";
	if($homeCity == "pensacola") { $pcola = 1; setcookie("pensacola", 1, time()+31536000); $eTitle = "Pensacola"; };
	if (isset($_COOKIE['pensacola'])) { $pcola = 1; $eTitle = "Pensacola"; };

?>
<?php include('include/meta.html'); ?> 
<title>Pay Checking <?php echo $eTitle; ?> </title>
<link href="style.css" rel="stylesheet" type="text/css" />
<script type="text/javascript" src="js/addInput.js"></script>
</head>

<body>
<noscript>
<h1 style="text-align: center;">Javascript Must Be Enabled To Use This Site</h1>
</noscript>
<?php
	
	if ((isset($_COOKIE['pensacola'])) || ($homeCity == "pensacola") || ($pcola == 1)) { 
	   $terminalsql = "SELECT * FROM `pcola_terminal` ORDER BY `pcola_terminal`.`id` ASC";
		$terminal = $conn->query($terminalsql);
		$termianlNum = $terminal->num_rows;
		
		$citysql = "SELECT city FROM pcola_largeMiles ORDER BY city ASC"; 
		$city = $conn->query($citysql);
		$cityNum = $city->num_rows;	
	} else {	
		$terminalsql = "SELECT * FROM terminal ORDER BY terminal.id ASC";
		$terminal = $conn->query($terminalsql);
		$termianlNum = $terminal->num_rows;
		
		$citysql = "SELECT city FROM largeMiles ORDER BY city ASC"; 
		$city = $conn->query($citysql);
		$cityNum = $city->num_rows;
	}//end if ((isset($_COOKIE['pensacola'])) || ($homeCity == "pensacola") || ($pcola == 1))
	
	$p = 0;
	$f = 0;
	if (isset($_COOKIE['beta'])) { $beta = 1; }
	if (isset($pro) || isset($beta)) { 
		include ('include/frontMenu.php'); 
		echo "<div id=\"betalogo\" class=\"beta\"><img src=\"../images/beta-testing.png?v=4\" alt=\"Beta\" class=\"beta\" /></div>";	
	} else { 
		echo "	<br /> <br />"; 
	}//end if (isset($pro))
	
	if ((isset($_COOKIE['pensacola'])) || ($homeCity == "pensacola") || ($pcola == 1)) { ?>
	<div id="city" class="city"><p class="cityText"><strong>Pensacola</strong></p></div><!--end city-->
	<?php } else { ?>
	<div id="city" class="city"><p class="cityText"><strong>Panama City</strong></p></div><!--end city-->
	<?php	
	}//end if ((isset($_COOKIE['pensacola'])) || ($homeCity == "pensacola"))  ?> 
	

<div id="wrapper" class="largeContain">
  <div id="Top" class="add-load-contain">



<?php
	$checkMiles = $_GET['m'];
	$emptyMiles = $_GET['ept'];
	$endEmpty = $_GET['ee'];
	$endEmpty = explode("_", $endEmpty);
	$endEmpty = $endEmpty[0] . ", " . $endEmpty[1];
	
	$checkc1 = $_GET['c1'];
	$checkc1 = explode("_", $checkc1);
	$checkc1 = $checkc1[0] . ", " . $checkc1[1];
	
	$checkc2 = $_GET['c2'];
	$checkc2 = explode("_", $checkc2);
	$checkc2 = $checkc2[0] . ", " . $checkc2[1];
	
	$insertCity = $_GET['c'];
	$insertCity = explode("_", $insertCity);
	$insertCity = $insertCity[0] . ", " . $insertCity[1];
	
	$insertPickup = $_GET['p'];
	$insertPickup = explode("_", $insertPickup);
	$insertPickup = $insertPickup[0] . ", " . $insertPickup[1];
	
	$addCity = $_GET['ac'];
	$getUpdate = $_GET['upm'];
	$enterdMiles = $_GET['em'];
	$ow = $_GET['ow'];
	$duplicate = $_GET['d'];	
	
	
	if(isset($getUpdate)) {
		if($getUpdate == $enterdMiles) {
			echo "<h3 align=\"center\">Success!<br >Current miles ".$checkc1 . " to " . $checkc2 . " is now:<br /> ".$enterdMiles." miles</h3>";
		}//end if($getUpdate == $enterdMiles)
	}//end if(isset($getUpdate))
	
	if(isset($addCity)) {
		if($ow != 1) {						
			echo "<h3 align=\"center\">" . $insertCity . " inserted into database<br />" . $checkMiles . " miles from " . $insertPickup . "</h3>"; 
	   } else {
	   	echo "<h3 align=\"center\">" . $insertCity . " inserted into database<br />" . $checkMiles . " Loaded miles from " . $insertPickup . "<br />and " . $emptyMiles . " Empty miles to " . $endEmpty . "</h3>";
	   }//end if($ow != 1)
	}//end if(isset($insertCity))
	
	if(isset($duplicate)) {
		echo "<h3><span style=\"color: red; weight: bold;\">Oops</span> The city: " . $insertCity . " is already in the database.</h3>";
	}//end if(isset($duplicate))
	
?>
<br /> 
<br />
<form method="post"  action="index.php"> 
<input type="submit" name="Done" class="myButton wideB blueB" value="Done" />







  </div><!--end Top-->
</div><!--end wrapper-->


<?php	include ('include/footer.html'); 
	if ((!isset($pro)) || ((isset($pro)) && ($valid == 0))) { ?>
<br />
<div id="adPad" class="clear">&nbsp;</div><!--end adPad-->
<div id="bottomAd" class="adLock">
<center>
<?php 	
include ('include/googlead.html'); 
?>
</center>
</div><!--end bottomAd-->
<?php }//end if (!isset($pro)) ?>
</body>
</html>
