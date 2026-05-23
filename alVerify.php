<?php 
include('include/db.php');
include('include/cookiecheckMP.php');
include ('include/variables.php');	  
	
	if (isset($_POST['cancel'])) {
		include('header/headerRedirect.php');
		exit;	
	}//end if(isset($_POST['cancel'])) 	
	
	
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
	
	
	if (isset($_COOKIE['pensacola'])) {
   	$dbl = "pcola_largeMiles";
   } else {
   	$dbl = "largeMiles";
   }//end if (isset($_COOKIE['pensacola']))
	
	$checkMiles = $_POST['check'];
	$c1 = $_POST['updateStart'];
	$c2 = $_POST['updateEnd'];
	
	if (isset($checkMiles)) {
		$getMilesQuery = $conn->query("SELECT `".$c1."` FROM `".$dbl."` WHERE `".$dbl."`.`city` =  '".$c2."' LIMIT 1;") or die ('<center><h1>Unable to retrieve miles</h1></center>');
			while($row = $getMilesQuery->fetch_assoc()) {
				$enterdMiles = $row[$c1];
			}//end while($row = $getMilesQuery->fetch_assoc())
		$c1 = explode(", ", $c1);
		$c1 = $c1[0] . "+" . $c1[1];
		$c2 = explode(", ", $c2);
		$c2 = $c2[0] . "+" . $c2[1];
		header("Location: http://".$_SERVER['SERVER_NAME']."/addlocation.php?um=1&m=" . $enterdMiles . "&c1=" . $c1 . "&c2=" . $c2);
	}//end if (isset($checkMiles))
	
	if(isset($_POST['cDone'])) {	
		if (isset($_POST['updateStart'])) { 
		   $miles = $_POST['updateMiles'];
			$conn->query("UPDATE `".$dbl."` SET `".$c1."` =  '".$miles."' WHERE CONVERT(`".$dbl."`.`city` USING utf8) =  '".$c2."' LIMIT 1;") or die ('<center><h1>Unable to Update city 1</h1></center>');
			$conn->query("UPDATE `".$dbl."` SET `".$c2."` =  '".$miles."' WHERE CONVERT(`".$dbl."`.`city` USING utf8)   =  '".$c1."' LIMIT 1;") or die ('<center><h1>Unable to Update city 2</h1></center>');
			$getMilesQuery = $conn->query("SELECT `".$c1."` FROM `".$dbl."` WHERE `".$dbl."`.`city` =  '".$c2."' LIMIT 1;") or die ('<center><h1>Unable to retrieve miles</h1></center>');
			while($row = $getMilesQuery->fetch_assoc()) {
				$enterdMiles = $row[$c1];
			}//end while($row = $getMilesQuery->fetch_assoc())
			$c1 = explode(", ", $c1);
			$c1 = $c1[0] . "_" . $c1[1];
			$c2 = explode(", ", $c2);
			$c2 = $c2[0] . "_" . $c2[1];
			header("Location: http://".$_SERVER['SERVER_NAME']."/ald.php?um=1&upm=" . $miles . "&em=" . $enterdMiles . "&c1=" . $c1 . "&c2=" .$c2);
		}//end if(isset($_POST['checkMilesHidden']))
	}//end if(isset($_POST['cDone']))
	
	
	$pickup = $_POST['pickup'];
	$miles = $_POST['miles'];
	$state = $_POST['state'];
	$newCityRT = $_POST['newCityRT'];
	$newCityRT = strtolower($newCityRT);
	$newCityRT = ucwords($newCityRT);
	$newCityOW = $_POST['newCityOW'];
	$newCityOW = strtolower($newCityOW);
	$newCityOW = ucwords($newCityOW);
	$endEmpty = $_POST['endEmpty'];		
	$emptyMiles = $_POST['emptyMiles'];
	$loadedMiles = $_POST['loadedMiles'];
	$oneWayFlag = $_POST['owhv'];

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
	echo "<h1 align=\"center\"><u>Confirm</u></h1> <br /> <h3 align=\"center\">Attempting to add<br /><span style=\"color:red;\">";	
	
	if(!isset($oneWayFlag)) { echo $newCityRT . ", " . $state; } else { echo $newCityOW . ", " . $state; }//end if(!isset($oneWayFlag))

	echo "</span><br />to the database with<br />";
	
	if(!isset($oneWayFlag)) { echo "<span style=\"color:red;\">" . $miles . "</span> Loaded miles from <span style=\"color:green;\">" . $pickup; } else { echo "<span style=\"color:red;\">" . $loadedMiles . "</span> Loaded miles from <span style=\"color:green;\">" . $pickup . "</span> and <span style=\"color:red;\">" . $emptyMiles . "</span> Empty miles to <span style=\"color:green;\">" . $endEmpty; }//end if(!isset($oneWayFlag))

	echo "</span></h3>";
?>

<br />
<br />

<form method="post"  action="alSubmit.php">


<?php
	echo "<input type=\"hidden\" name=\"pickup\" value=\"".$pickup."\" />";
	echo "<input type=\"hidden\" name=\"state\" value=\"".$state."\" />";
	
	if(!isset($oneWayFlag)) {
		echo "<input type=\"hidden\" name=\"miles\" value=\"".$miles."\" />";
		echo "<input type=\"hidden\" name=\"newCityRT\" value=\"".$newCityRT."\" />";
	} else {
		echo "<input type=\"hidden\" name=\"loadedMiles\" value=\"".$loadedMiles."\" />";
		echo "<input type=\"hidden\" name=\"emptyMiles\" value=\"".$emptyMiles."\" />";
		echo "<input type=\"hidden\" name=\"newCityOW\" value=\"".$newCityOW."\" />";
		echo "<input type=\"hidden\" name=\"endEmpty\" value=\"".$endEmpty."\" />";
	}//end if(!isset($oneWayFlag))
	
	
	
   
   	
?>	


<input type="submit" name="cancel" class="myButton quarterB greyB" value="Cancel" />                
<input type="submit" name="cDone" class="myButton threeQuarterB blueB" value="Submit" />
</form>







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
