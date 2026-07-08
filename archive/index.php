<?php 
$auth = $_COOKIE['auth'];
if (!isset($auth)) {
	include ('include/authInside.php');
} else {
	$td = '';
	$sd = '';
	include('include/db.php');
	include('include/cookiecheckMP.php');
	if (isset($_COOKIE['loads'])) { $loadsTotal = $_COOKIE['loads']; }
	if (isset($_COOKIE['active'])) { $active = $_COOKIE['active']; }
	if (isset($_COOKIE['reload'])) { $reload = $_COOKIE['reload']; }
	if (isset($_COOKIE['pro'])) { $pro = $_COOKIE['pro']; }
	if (isset($_COOKIE['variables'])) { $variables = $_COOKIE['variables']; }
	if (isset($_COOKIE['bck'])) { $noBCK = $_COOKIE['bck']; }
	include ('include/variables.php');	
	
	//Declare Variables
	$grandTotal = 0;
	$compareTotal = 0;
	$pu = "";
	$del = "";
	$dem = "";
	$break = "";
	$extra = "";
	$cd = "";
	//Declare Variables
	
	$loads = 0;
	if (isset($loadsTotal)) {
		$loadsExplode = explode("-", $loadsTotal);
		$loads = $loadsExplode[0] + $loadsExplode[1] + $loadsExplode[2];
	} else {
		$loadsExplode = 0;
	}//end if (isset($_COOKIE['loads']))
	
	if ($loads > 19) {
		setcookie("active", 1, time()-1);
		include('header/headerRedirect.php');
	}//end if ($loads > 19)
	
	if (!isset($active)) {

		for ($i=0; $i < 20; $i++) {

			$name = "L" . $i;
			if (isset($_COOKIE[$name])) {
			setcookie($name, 1, time()-1);
			}//end if (isset($_COOKIE[$name]))

		}//end for ($i=0; $i < 100; $i++)
		
		setcookie("loads", 1, time()-1);
		setcookie("rt", 1, time()-1);
		setcookie("owl", 1, time()-1);
		setcookie("owe", 1, time()-1);
		setcookie("nl", 1, time()-1);
		setcookie("nq", 1, time()-1);
		setcookie("nqe", 1, time()-1);
		setcookie("edit", 1, time()-1);
		setcookie("lt", 1, time()-1);
		setcookie("be", 1, time()-1);
		setcookie("beo", 1, time()-1);
		setcookie("bck", 1, time()-1);
		setcookie("spl", 1, time()-1, '/');
		setcookie("active", 1, time()+36000, '/');  
		include('header/headerRedirect.php');
	}//end if (!isset($_COOKIE['active']))
	
  	
	if (isset($_COOKIE['rt'])) { $rt = 1; }
	if (isset($_COOKIE['owl'])) { $owl = 1; }
	if (isset($_COOKIE['owe'])) { $owe = 1; }
	if (isset($_COOKIE['tr'])) { $tr = 1; }
	
	if (isset($tr)) { header("Location: http://".$_SERVER['SERVER_NAME']."/trainerredirect.php?l=".$loads.""); }// end if (isset($tr)) 

	
	$beta = 0;
	if(isset($_GET['b'])) {
		$beta = $_GET['b'];
		setcookie("beta", 1, time()+31536000); 
	} 
	
//	if (isset($_GET['a'])) {
//		//Do Nothing
//	} else { 
//		if (!isset($_COOKIE['seen']) && (!isset($pro) && (isset($_COOKIE['variables'])))) {
//			setcookie("seen", 1, time()+31536000, "/");
//			header("Location: http://".$_SERVER['SERVER_NAME']."/pro/");
//		}//end if (!isset($_COOKIE['seen']))
//	}//end if (isset($_GET['a']))

	$homeCity = '';
	if (isset($_GET['c'])) { $homeCity = $_GET['c']; }
	$pcola = 0;
	$eTitle = "";
	if($homeCity == "pensacola") { $pcola = 1; setcookie("pensacola", 1, time()+31536000); $eTitle = "Pensacola"; };
	if (isset($_COOKIE['pensacola'])) { $pcola = 1; $eTitle = "Pensacola"; };
	$nq = "";

?>
<?php include('include/meta.html'); ?> 
<title>Pay Checking <?php echo $eTitle; ?> </title>
<link rel="stylesheet" href="style.css" type="text/css" />
<!--MaxCDN, CDN support for Bootstrap CSS and JavaScript
<link rel="stylesheet" href="http://maxcdn.bootstrapcdn.com/bootstrap/3.4.1/css/bootstrap.min.css">
<script src="http://ajax.googleapis.com/ajax/libs/jquery/3.7.1/jquery.min.js"></script>
<script src="http://maxcdn.bootstrapcdn.com/bootstrap/3.4.1/js/bootstrap.min.js"></script>
MaxCDN, CDN support for Bootstrap CSS and JavaScript-->
<script type="text/javascript" src="js/addInput.js"></script>
</head>

<body>
<noscript>
<h1 style="text-align: center;">Javascript Must Be Enabled To Use This Site</h1>
</noscript>
<!-- Global site tag (gtag.js) - Google Analytics -->
<script async src="http://www.googletagmanager.com/gtag/js?id=UA-18845112-8"></script>
<script>
  window.dataLayer = window.dataLayer || [];
  function gtag(){dataLayer.push(arguments);}
  gtag('js', new Date());

  gtag('config', 'UA-18845112-8');
</script>

<?php
	
	
	$p = 0;
	$f = 0;
	if (isset($_COOKIE['beta'])) { $beta = 1; }
    include ('include/frontMenu.php'); 
	if ($beta == 1) { 
		echo "<div id=\"betalogo\" class=\"beta\"><img src=\"../images/beta-testing.png?v=4\" alt=\"Beta\" class=\"beta\" /></div>";	
	}//end if (isset($beta))
	if ((isset($_COOKIE['pensacola'])) || ($homeCity == "pensacola") || ($pcola == 1)) { ?>
	<div id="city" class="city"><p class="cityText"><strong>Pensacola</strong></p></div><!--end city-->
	<?php } else { ?>
	<div id="city" class="city"><p class="cityText"><strong>Panama City</strong></p></div><!--end city--> 
	<?php	
	}//end if ((isset($_COOKIE['pensacola'])) || ($homeCity == "pensacola"))  ?> 
	<!-- <div id="broken-notice" style="text-align:center;"><h4>Site is experiencing problems, I am woring on fixing it.</h4></div> -->
<div id="wrapper" class="largeContain">
  <div id="Top" class="add-load-contain">
			
      <?php 	
			if ($tenure <> 'NOTSET') { 
	  
				 	if ($shift == "day") { $sd = "Day Shift"; }
				 	if ($shift == "night") { $sd = "Night Shift"; }
					if ($slip == "yes") { $ssd = "Slip Seat"; }
					if ($slip == "no") { $ssd = "No Slip"; }
					
	  	
					?> 
                    <div id="vContain">
                    <div id="variables">&nbsp; <?php echo $td; ?> &nbsp; | &nbsp; <?php echo $sd; ?> &nbsp;
                    </div><!--end "variables"--> 
     	<?php
					if (!isset($_COOKIE['nl']) && (!isset($_COOKIE['ow']) && (!isset($_COOKIE['owl']) && (!isset($_COOKIE['owe']) && (!isset($_COOKIE['rt'])))))) {
		?>               
                    <div class="reset"><a href="resetcookie.php" target="_self"><img src="images/refresh-icon.png" title="reset" border="0" alt="reset" class="reset" /></a></div><!--end reset-->
		<?php 
					}//end if (!isset($_COOKIE['nl']) && (!isset($_COOKIE['ow']) && (!isset($_COOKIE['owl']) && (!isset($_COOKIE['owe']) && (!isset($_COOKIE['rt']))))))
		?>
                    </div><!--end "vContain"-->
		<?php
			} else { 
		?>

<form method="post" action="setvariables.php">
     
          Tenure: <select name="tenure">
          			<option value="6">0 - 6 Months</option>
          			<option value="12">7 - 12 Months</option>
                    <option value="24">13 - 24 Months</option>
                    <option value="60">25 - 60 Months</option>
                    <option value="108">61 - 108 Months</option>
                    <option value="168">109-168 Months</option>
                    <option value="max">169+ Months</option>
                 </select><br /><br />

             Shift: &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
             <input type="radio" name="shift" value="night" checked /> Night &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;  
             <input type="radio" name="shift" value="day" /> Day &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
		 		
<!--             <br /><br />
             Slip Seat: &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
             <input type="radio" name="slip" value="yes" checked /> Yes &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;  
             <input type="radio" name="slip" value="no" /> No
-->             
             <br /><br />
            
<input type="submit" class="myButton wideB blueB" value="Get Started" />
</form> 

<?php }//end if (isset($tenure)) 


 if (isset($rt) || (isset($owl))) { include('include/loadedForm.php'); }// end if (isset($rt) || (isset($owl)))

 if (isset($owe)) { include('include/emptyForm.php'); }// end if (isset($owe))
 
	
		if (isset($_COOKIE['nl'])) { ?>
            <form method="post" action="loadselect.php">
            <input type="submit" name="rt" class="myButton halfB blueB" value="+ Round Trip (REG)" />
            <input type="submit" name="ow" class="myButton halfB blueB" value="+ One Way (L/D)" />
			<br /><br />
            <input type="submit" name="trainer" class="myButton halfB blueB" value="Trainer" />
            <input type="submit" name="pl" class="myButton halfB blueB" value="Preload" />
			<br /><br />
            <input type="submit" name="cancel" class="myButton wideB greyB" value="Cancel" />
            </form>
          
   <?php      
      	if (isset($_COOKIE['beta'])) {
   ?>
			
			<br />
			<hr />
			<br />			
			<form method="post" action="addlocation.php">

				
			<input type="submit" name="um" class="myButton halfB blueB" value="Update Miles" />	
			<input type="submit" name="ad" class="myButton halfB blueB" value="Add Missing City" />
			</form>
			
							
<?php			
			}//end if (isset($_COOKIE['beta'])) 

		}//end if (isset($_COOKIE['nl']))
	
	
	?>



    <?php 	 
		if (isset($variables)) {
	?>
    <?php
		  if (isset($_COOKIE['nl']) || (isset($_COOKIE['ow']) || (isset($_COOKIE['owl']) || (isset($_COOKIE['owe']) || (isset($_COOKIE['rt'])))))) {
	?>
	  &nbsp; 
    <?php	  
		  } else {
	?>
	  <form method="post" action="newload.php">
	  <input type="submit" class="myButton wideB blueB" value="New Load" />
	  </form>
	<?php	
		  }//end if (isset($_COOKIE['nl']) || (isset($_COOKIE['ow']) || (isset($_COOKIE['rt']))))
		}//end if (isset($_COOKIE['variables']))
	?>		
  </div><!--end Top-->
</div><!--end wrapper-->

<?php 

if (!isset($_COOKIE['nl']) && (!isset($_COOKIE['ow']) && (!isset($_COOKIE['owl']) && (!isset($_COOKIE['owe']) && (!isset($_COOKIE['rt']) && (isset($_COOKIE['variables']))))))) {
  if (isset($loads)) {	

?>

<div id="wrapper" class="largeContain">
  <div id="Bottom" class="add-load-contain">
<?php

	for ($i=1; $i <= $loads; $i++) {
		$gm = "";
		$oneWay = 0;
		$roundTrip = 0;
		$compareTrip = 0;
		$oldPay = 0;
		$empty = 0;
		$miles = 0;
		$loadPayTotal = 0;
		$lastLoad = "";
		$sb = "";
		$splitLoad = 0;
		$weekendLoad = 0;
		$demurrage = 0;
		$breakdown = 0;
		$WM = "";
 		$em = "";
 		$im = "";
		$pd = "";
		$dh = "";
		$shiftPayDisplay = "";
		$seniorPayDisplay = "";
		$weekendPayDisplay = "";
		$oneWayWeekend = 0;
		$oneWaySeniority = 0;
		$oneWayShift = 0;
		$loadInfo = "Load " . $i;
		
		$cookieName = "L" . $i;
		$cv = $_COOKIE[$cookieName];
		$cvExplode = explode("-", $cv);
		if(isset($cvExplode[0])) { $loadType = $cvExplode[0]; }
		if(isset($cvExplode[1])) { $emptyMiles = $cvExplode[1]; }	
		if(isset($cvExplode[2])) { $pu = $cvExplode[2]; }
		if(isset($cvExplode[3])) { $del = $cvExplode[3]; }
		if(isset($cvExplode[4])) { $splitLoad = $cvExplode[4]; }
		if(isset($cvExplode[5])) { $weekendLoad = $cvExplode[5]; }
		if(isset($cvExplode[6])) { $emptyORloaded = $cvExplode[6]; }
		if(isset($cvExplode[7])) { $preDead = $cvExplode[7]; }
		if(isset($cvExplode[8])) { $gmc = $cvExplode[8]; }
		if(isset($cvExplode[9])) { $extra = $cvExplode[9]; }
		if(isset($cvExplode[10])) { $dem = $cvExplode[10]; }
		if(isset($cvExplode[11])) { $break = $cvExplode[11]; }
		if(isset($cvExplode[12])) { $ori = $cvExplode[12]; }
		if(isset($cvExplode[13])) { $orm = $cvExplode[13]; }
		if(isset($cvExplode[14])) { $frtl = $cvExplode[14]; } 
		$ormI = "";

		
		if(isset($emptyMiles )) { if ($emptyMiles == 1) { $emptyMiles = 0; } }
		

			if ($loadType == 2) {
			?>
			<div id="loads display" class="load-contain">
			  <h5>Load <?php echo $i; ?> Removed</h5>
			</div><!--end loads display-->
			<div id="controls" class="control-contain">&nbsp;</div><!--end controls-->
			  <hr align="left" width="100%" />&nbsp;
			<?php	
				continue;
			}//end if ($loadType == 2)



			if ($loadType == 3) {
			?>
			<div id="loads display" class="load-contain">
			  <h5>Load <?php echo $cvExplode[1]; ?> Deleted</h5>
			</div><!--end loads display-->
			<div id="controls" class="control-contain">&nbsp;</div><!--end controls-->
			  <hr align="left" width="100%" />&nbsp;
			<?php	
				continue;
			}//end if ($loadType == 3)



		if ($pcola == 1) {
			$milesSql = 'SELECT `'.$pu.'` FROM `pcola_largeMiles` WHERE `city` =  "'.$del.'" LIMIT 1;';
		} else {		
			$milesSql = 'SELECT `'.$pu.'` FROM `largeMiles` WHERE `city` =  "'.$del.'" LIMIT 1;';
		}//end if ($pcola == 1)
		
		if ($pu <> '' && $del <> '') {
			$milesQuery = $conn->query($milesSql);
			if ($milesQuery->num_rows > 0) {

				while($row = $milesQuery->fetch_assoc()) {
				  $loadMiles = $row["".$pu.""];
							
					if ($loadMiles == 0) {
						$newStart = preg_replace("/ /","+",$pu);
						$newEnd = preg_replace("/ /","+",$del);
						include('include/googleMapsAPI.php');
						$gm = " <img src=\"images/googleMaps.png\" class=\"googleMaps\" /> ";
					} //end if ($miles == 0)
				}//end while($row = $milesQuery->fetch_assoc())
		}//end if ($pu <> '' && $del <> '')

		} else {		

			$loadMiles = 999;

		}//end if ($milesQuery->num_rows > 0) 
		
		if ($weekendLoad > 0) {
			$weekend = $wk;
		} else {
			$weekend = 0;
		}//end if ($weekendLoad > 0)
		
		if ($dem > 0) {
			$demurrage = $dem * $demurrageCPM;
		}//end if ($dem > 0)
		
		if ($break > 0) {
			$breakdown = $break * $breakdownCPM;
		}//end if ($break > 0)
		
		if ($loadType == 0) {
			$oneWayBoostOffset = 0;//was 0.003
		} else {
			$oneWayBoostOffset = 0;
		}//end if ($loadType == 0) 

		
		if ($loadType == 0) {

			
			if ($emptyORloaded == 1) {
				
				if ($emptyMiles > 0) { // if actual empty miles > 0
					
					  $gmc = $cvExplode[8];
					  if ($gmc == 1) {
						  $gm = " <img src=\"images/googleMaps.png\" class=\"googleMaps\" /> ";
					  } else {
						  $gm = "";
					  }//end if ($gmc == 1)

					$empty = ($emptyMiles * $mt);
					$em = "<p>Empty Pay: " . $emptyMiles . $gm . " Miles @ ".$mt.": <span class=\"moneyDisplay\">$" . number_format($empty,2) . "</span></p>";
				} else {
					$em = "";
				}//end if ($miles > 0)
				
				$cd = $del . " to " . $pu;
				$sb = "";
				
			} else { //else if ($emptyORloaded == 1)
				
				if ($loadMiles > 0) {
					
				include('include/outofroute.php');
				
					$oneWayLoaded = $loadMiles;  //CHANGED FROM [$oneWayLoaded = $loadMiles/2;] 10/27/2022	
					$oneWayLoaded = round($oneWayLoaded, 0);
					if ($pcola == 1) {		
					  $getOneWay = $conn->query("SELECT rate FROM ".$dbTable3." WHERE miles >= ".$oneWayLoaded." LIMIT 1;");
					} else {
					  $getOneWay = $conn->query("SELECT rate FROM ".$dbTable3." WHERE miles >= ".$oneWayLoaded." LIMIT 1;");
					}//end if ($pcola == 1) 

					//$conn->query("SELECT `".$tenure."` FROM `oneWay` WHERE `miles` >= ".$loadMiles." LIMIT 1");
					
						
					  if ($getOneWay->num_rows > 0) {

						  while ($row = $getOneWay->fetch_assoc()) { $oneWayBase =  $row["rate"]; }
							$oneWayBase = ($oneWayBase * $raise) + $oneWayBase;
						  //$newBoosts = $boosts + $oneWayBoostOffset;
						  //$oneWay = ($oneWayBase * $newBoosts) + $oneWayBase;
						  //$oneWay = ($oneWay * $weekend) + $oneWay;
						  	$oneWay = $oneWayBase; //CHANGED FROM [$oneWay = ($oneWayBase * $newBump) + $oneWayBase;] 10/26/2022
								if ($loadMiles > 1) {
									$loadedMilesDisplay = $loadMiles;
									$milesDisplay = " Miles";
								} else {
									$loadedMilesDisplay = "Local";
									$milesDisplay = "";
								}
							$im = $ormI."<h4>One Way Pay</h4><p>" . $oneWayLoaded . $gm . $milesDisplay ." Base: <span class=\"moneyDisplay\">$" . number_format($oneWayBase,2);
//							if ($compare == 1) {
//							  //$getCompareInfo = $conn->query("SELECT `".$tenure."` FROM `roundTrip` WHERE `miles` >= ".$loadMiles." LIMIT 1");
//							  if ($pcola == 1) {	
//								$getCompareInfo = $conn->query("SELECT rate FROM ".$dbTable2." WHERE miles >= ".$loadMiles." LIMIT 1");
//							  } else {
//								$getCompareInfo = $conn->query("SELECT rate FROM ".$dbTable2." WHERE miles >= ".$loadMiles." LIMIT 1");
//							  }//end if ($pcola == 1) {	
//								if ($getCompareInfo->num_rows > 0) {
//																		
//								  while ($row = $getCompareInfo->fetch_assoc()) { $compaeBase =  $row["rate"]; }
//								  $compareTrip = ($compaeBase * $boosts) + $compaeBase;
//								  $compareTrip = ($compareTrip * $weekend) + $compareTrip;
//								  $compareTotal = $compareTrip + $compareTotal;
//								}//end if ($getCompareInfo->num_rows > 0)
//							}//end if ($compare == 1)
						  							
					  } else {

							if ($loadMiles == 999) {
								$im = "Something Has Gone<br /><span style=\"color:#F00;\">Catastrophically</span> Wrong!<br />Reload The Page or Wait a Few Minutes.";
							} else {
								$im = "Too Far To Calculate!<br />No Pay Information For Distance.";
							}//end if ($loadMiles = 999)
							
					  }//end if ($getOneWay->num_rows)
					
					
				} else {
					$im = "<p>No Miles For Load</p>";
				}//end if ($loadMiles > 0)
				
				
				if ($emptyMiles > 0) {
					
					  if ($gmc == 1) {
						  $gm = " <img src=\"images/googleMaps.png\" class=\"googleMaps\" /> ";
					  } else {
						  $gm = "";
					  }//end if ($gmc == 1)
					  

					$empty = ($emptyMiles * $mt);
					$em = "<p>Empty Pay: " . $emptyMiles . $gm . " Miles @ ".$mt.": <span class=\"moneyDisplay\">$" . number_format($empty,2) . "</span></p>";
					$sb = "<br />";
				} else {
					$em = "";
				}//end if ($miles > 0)
				
				$cd = $pu . " to " . $del;
				
				
				if ($preDead == "preload") {
					$nq = "&n=1";
					$cd = "Pre-Load to " . $del;
				} else {
					$nq = "";
				}//end if ($preDead == "preload")
				
				if ((is_numeric($preDead) && ($preDead > 0))) {
					$beginEmptyMiles = $preDead;
					$pd = "Deadhead to " . $pu . "<br />";
					$beginEmptyMiles = ($preDead * $mt);
					$returnEmpty = ($emptyMiles * $mt);
					$empty = $beginEmptyMiles + $returnEmpty;
					$em = "<p>Empty: " . $emptyMiles . $gm . " Miles @ ".$mt.": <span class=\"moneyDisplay\">$" . number_format($returnEmpty,2) . "</span></p>";
					$dh = "<p>D/H: " . $preDead . " Miles @ ".$mt.": <span class=\"moneyDisplay\">$" . number_format($beginEmptyMiles,2) . "</span></p>";
					$sb = "<br />";
				} else {
					$pd = "";
				}//end if ($preDead == 2)
				
			}//end if ($emptyORloaded == 1)
			
			
			
		} else if ($loadType == 1)  { //else if ($loadType == 0)
			
			if ($loadMiles > 0) {
				
				include('include/outofroute.php');
				
				if ($pcola == 1) {
				  $getRoundTrip = $conn->query("SELECT rate FROM ".$dbTable2." WHERE miles >= ".$loadMiles." LIMIT 1;");
				} else {
				  $getRoundTrip = $conn->query("SELECT rate FROM ".$dbTable2." WHERE miles >= ".$loadMiles." LIMIT 1;");
				}//end if ($pcola == 1) {
				//$conn->query("SELECT `".$tenure."` FROM `roundTrip` WHERE `miles` >= ".$loadMiles." LIMIT 1");
				
				if ($getRoundTrip->num_rows >0) {
					
					while ($row = $getRoundTrip->fetch_assoc()) { $roundTripBase =  $row["rate"]; }
					$roundTripBase = ($roundTripBase * $raise) + $roundTripBase;
					$roundTripSeniority = ($roundTripBase * $newBump);
					$roundTripShift = ($roundTripBase * $nightOn);
					if ($loadMiles > 1) {
						$loadedMilesDisplay = $loadMiles;
						$milesDisplay = " Miles";
					} else {
						$loadedMilesDisplay = "Local";
						$milesDisplay = "";
					}
					$im = $ormI."<h4>Round Trip Pay</h4><p>". $loadedMilesDisplay . $gm . $milesDisplay ." Base: <span class=\"moneyDisplay\">$" . number_format($roundTripBase,2) . "</span></p>";
					$seniorPayDisplay = $ormI."<p>Seniority Pay: <span class=\"moneyDisplay\">$" . number_format($roundTripSeniority,2) . "</span>&nbsp;<span class=\"percentageDisplay\">(". ($newBump * 100) ."%)</span></p>";
					$shiftPayDisplay = $ormI."<p>Shift Pay: <span class=\"moneyDisplay\">$" . number_format($roundTripShift,2) . "</span>&nbsp;<span class=\"percentageDisplay\">(". ($nightOn * 100) ."%)</span></p>";
					
						if ($weekend > 0) {
							$roundTripWeekend = ($roundTripBase * $weekend) + $roundTrip;
							$weekendPayDisplay = $ormI."<p>Weekend Pay: <span class=\"moneyDisplay\">$" . number_format($roundTripWeekend,2) . "</span>&nbsp;<span class=\"percentageDisplay\">(". ($weekend * 100) ."%)</span></p>";
						} else {
							$roundTripWeekend = 0;
						}//end if ($weekend > 0)
					$loadPayTotal = round($roundTripBase,2) + round($roundTripSeniority,2) + round($roundTripShift,2) + round($roundTripWeekend,2);	
					
				} else {
					
					if ($loadMiles == 999) {
						$im = "Something Has Gone<br /><span style=\"color:#F00;\">Catastrophically</span> Wrong!<br />Reload The Page or Wait a Few Minutes.";
					} else {
						$im = "Too Far To Calculate!<br />No Pay Information For Distance.";
					}//end if ($loadMiles = 999)
					  
				}//end if ($getRoundTrip->num_rows > 0)
				
//					if ($compare == 1) {
//						$compareTotal = $roundTrip + $compareTotal;
//					}//end if ($compare == 1)
				
			} else {
				$im = "<p>No Miles For Load</p>";
			}//end if ($miles > 0)
			
			$cd = $pu . " to " . $del;
		} else if ($loadType == 4)  { //else if ($loadType == 0)

			$im = "Trainer Pay: $" . $trainerpay;
			$loadPayTotal = $trainerpay;
			$oldPay = $trainerpay;

		}//end if ($loadType == 0)
		
			if (($oneWay > 0) || ($empty > 0)) {
		
				if ($oneWay == 0) {
					//$oneWay = "";
					$loadPayTotal = $empty;
				} 
				
				if ($empty == 0) {
					  if ($weekend > 0) {
						$oneWayWeekend = ($oneWay * $weekend);
						$weekendPayDisplay = $ormI."<p>Weekend Pay: <span class=\"moneyDisplay\">$" . number_format($oneWayWeekend,2) . "</span>&nbsp;<span class=\"percentageDisplay\">(". ($weekend * 100) ."%)</span></p>";
					  }//end if ($weekend > 0)
					//$empty = "";
					$loadPayTotal = $oneWay + $oneWayWeekend;
				} 
				
				if ($oneWay > 0) {
					$oneWaySeniority = ($oneWay + $empty) * $newBump;
					$oneWayShift = ($oneWay + $empty) * $nightOn; 
					$seniorPayDisplay = $ormI."<p>Seniority Pay: <span class=\"moneyDisplay\">$" . number_format($oneWaySeniority,2) . "</span>&nbsp;<span class=\"percentageDisplay\">(". ($newBump * 100) ."%)</span></p>";
					$shiftPayDisplay = $ormI."<p>Shift Pay: <span class=\"moneyDisplay\">$" . number_format($oneWayShift,2) . "</span>&nbsp;<span class=\"percentageDisplay\">(". ($nightOn * 100) ."%)</span></p>";
						
						if ($weekend > 0) {
							$oneWayWeekend = ($oneWay + $empty) * $weekend;
							$weekendPayDisplay = $ormI."<p>Weekend Pay: <span class=\"moneyDisplay\">$" . number_format($oneWayWeekend,2) . "</span>&nbsp;<span class=\"percentageDisplay\">(". ($weekend * 100) ."%)</span></p>";
						}//end if ($weekend > 0)
					
					$loadPayTotal = round($oneWay,2) + round($empty,2) + round($oneWaySeniority,2) + round($oneWayShift,2) + round($oneWayWeekend,2);
				}//end if ($oneWay > 0)

			}//end if (($oneWay > 0) || ($empty > 0)
			
//			if ($roundTrip > 0) {
//				$loadPayTotal = $roundTrip;
//				$roundTrip = number_format($roundTrip,2);
//			}//end if ($roundTrip > 0)
			
			if ($compareTrip > 0) {
				$oldPay = $compareTrip;
			} else {
				$oldPay = $roundTrip;
			}//end if (isset($compareTrip))
		
			if ($splitLoad > 0) {
					if (isset($_COOKIE['spl'])) {
						$splNum = $_COOKIE['spl'];
						if($splNum == $i) {
							$sp = "<p><strike>Split: <span class=\"moneyDisplay greyscale\">$15.00</span></strike> <a href=\"restore.php?r=" . $i . "\" target=\"_self\" title=\"Restore\"><img src=\"images/plus.png\" border=\"0\" alt=\"Restore\" width=\"5px\" height=\"5px\" class=\"sml-delete\" /></a></p>";					
						} else {
							$sp = "<p>Split: <span class=\"moneyDisplay\">$15.00</span></p>";
							$loadPayTotal = $loadPayTotal + 15;
							$compareTotal = $compareTotal + 15;
							$oldPay = $oldPay + 15;
						}//end if($splNum == $i)
					} else {
						if (!isset($frtl)) { $m = "<a href=\"delete.php?s=" . $i . "\" target=\"_self\" title=\"Delete\"><img src=\"images/minus.png\" border=\"0\" alt=\"Delete\" width=\"10px\" height=\"3px\" class=\"sml-delete greyscale\" /></a>"; } else { $m = ""; }//end if (!isset($frtl))
						$sp = "<p>Split: <span class=\"moneyDisplay\">$15.00</span>".$m."</p>";
						$loadPayTotal = $loadPayTotal + 15;
						$compareTotal = $compareTotal + 15;
						$oldPay = $oldPay + 15;
					}//end if (isset($_COOKIE['spl'])) 
			} else {
				$sp = "";
			}//end if ($splitLoad > 0)
			
			if ($extra > 0) {
				$ep = "<p>Extra: <span class=\"moneyDisplay\">$" . number_format($extra,2) . "</span></p>";
				$loadPayTotal = $loadPayTotal + $extra;
				$compareTotal = $compareTotal + $extra;
				$oldPay = $oldPay + $extra;
			} else {
				$ep = "";
			}//end if ($extra > 0)
			
			if ($demurrage > 0) {
				$dp = "<p>Demurrage: <span class=\"moneyDisplay\">$" . number_format($demurrage,2) . "</span></p>";
				$loadPayTotal = $loadPayTotal + $demurrage;
				$compareTotal = $compareTotal + $demurrage;
				$oldPay = $oldPay + $demurrage;
			} else {
				$dp = "";
			}//end if ($extra > 0)
			
			if ($breakdown > 0) {
				$bp = "<p>Breakdown: <span class=\"moneyDisplay\">$" . number_format($breakdown,2) . "</span></p>";
				$loadPayTotal = $loadPayTotal + $breakdown;
				$compareTotal = $compareTotal + $breakdown;
				$oldPay = $oldPay + $breakdown;
			} else {
				$bp = "";
			}//end if ($breakdown > 0)
			
			if ($weekendLoad > 0) {
			$WM = "<span style=\"color:#F00; font-size:10px;\"><sup>&nbsp;(W)</sup></span>";
			} //end if ($weekendLoad > 0)
							

		if (isset($pro)) {
			
//		if ($loadsExplode[2] > 0) {
//			
//			if (($loadsExplode[1] > 1) || (($loadsExplode[0] > 0) && ($loadsExplode[1] > 1))) {
//				$lastLoad = "&l=1";
//			}//end if (($loadsExplode[0] > 1) || (($loadsExplode[1] > 0 && ($loadsExplode[0] > 0))))
//			
//		} else {
//			
			if (($loadsExplode[1] > 1) || (($loadsExplode[0] > 0) && ($loadsExplode[1] > 0))) {
				$lastLoad = "&l=1";
			}//end if (($loadsExplode[0] > 1) || (($loadsExplode[1] > 0 && ($loadsExplode[0] > 0))))
//		  
//		}//end if ($loadsExplode[2] > 0)
			
			if (isset($frtl)) {
			  $trackingIcon = "";
			  $loadInfo = "Load # <span class=\"loadNumber\">".$frtl."</span>&nbsp;&nbsp;<a href=\"edit.php?t=1&l=".$i."\" title=\"Edit\"><img src=\"images/edit-icon-outline.png\" border=\"0\" alt=\"Edit\" width=\"10px\" height=\"10px\" /></a>";
			} else {
				
			  $trackingIcon = "&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;<a href=\"pro/tracking.php?c=".$i.$lastLoad."&n=".number_format($loadPayTotal, 2)."&o=".number_format($oldPay, 2)."\" title=\"Tracking\" target=\"_self\"><img src=\"images/footprints.png\" class=\"tracking\" /></a>";
			}//end if (isset($frtl)
			
		} else {
			  $trackingIcon = "";
		}//end if (isset($pro))

        ?>
		<div id="loads display" class="load-contain">
          <h5><?php echo $loadInfo . $trackingIcon; ?></h5><p><?php echo "<span class=\"delInfo\">" . $pd . $cd . "</span>" . $WM; ?></p><b><?php echo $dh . $im . $shiftPayDisplay . $seniorPayDisplay . $weekendPayDisplay . $em . $sp . $ep . $dp . $bp; ?></b><h6>Total Load Pay: <span class="loadNumber">$<?php echo number_format($loadPayTotal, 2); ?></span></h6> 
        </div><!--end loads display-->
        <?php
			if (!isset($frtl)) {
		?>
        <div id="controls" class="control-contain">
        	<div class="control-inner">
            <a href="delete.php?l=<?php echo $i . $nq; ?>" target="_self" title="Delete"><img src="images/minus.png" border="0" alt="Delete" width="20px" height="6px" /></a>
            </div><!--end control-inner-->
            <div class="control-inner">
        	<a href="edit.php?l=<?php echo $i. $nq; ?>" target="_self" title="Edit"><img src="images/edit-icon-outline.png" border="0" alt="Edit" width="20px" height="20px" /></a>
            </div><!--end control-inner-->
        </div><!--end controls-->
        <?php
			} else {
		?>
        <div id="controls" class="control-contain">
            <div class="control-inner">
            <a href="deleteTracked.php?l=<?php echo $i . $nq; ?>" target="_self" title="Delete"><img src="images/x.png" border="0" alt="Delete" width="20px" height="20px" /></a>
            </div><!--end control-inner-->
        </div><!--end controls-->
        <?php
			}//end if (!isset($frtl))
		?>
          <hr align="left" width="100%" />
        <?php 
		
//		$loadPayTotal = number_format($loadPayTotal, 2);  *!*Fixed*!* *!*Rounding Error*!*
		$grandTotal = $grandTotal + $loadPayTotal;
	}//end for ($i=1; $i <= $loads; $i++)
		
		echo "<br />";
		
		
	if(isset($noBCK)) {
		echo "<h5><strike>Backhaul Differential: <span class=\"moneyDisplay greyscale\">$40.00</span></strike> <a href=\"restore.php?b=1\" target=\"_self\" title=\"Restore\"><img src=\"images/plus.png\" border=\"0\" alt=\"Restore\" width=\"5px\" height=\"5px\" class=\"sml-delete\" /></a></h5>";		
	} else {		
		
		if (isset($_COOKIE['beo'])) {
			
				if (($loadsExplode[1] > 1) || (($loadsExplode[0] > 0) && ($loadsExplode[1] > 1))) {
					$grandTotal = $grandTotal + 40;
					echo "<h5>Backhaul Differential: <span class=\"moneyDisplay\">$40.00</span> <a href=\"delete.php?b=1\" target=\"_self\" title=\"Delete\"><img src=\"images/minus.png\" border=\"0\" alt=\"Delete\" width=\"10px\" height=\"3px\" class=\"sml-delete greyscale\" /></a></h5>";
					$lastLoad = "&l=".$lastLoad;
				}//end if (($loadsExplode[0] > 1) || (($loadsExplode[1] > 0 && ($loadsExplode[0] > 0))))
			
		} else {
				if (is_array($loadsExplode)) {
					if (($loadsExplode[1] > 1) || (($loadsExplode[0] > 0) && ($loadsExplode[1] > 0))) {
						$grandTotal = $grandTotal + 40;
						echo "<h5>Backhaul Differential: <span class=\"moneyDisplay\">$40.00</span> <a href=\"delete.php?b=1\" target=\"_self\" title=\"Delete\"><img src=\"images/minus.png\" border=\"0\" alt=\"Delete\" width=\"10px\" height=\"3px\" class=\"sml-delete greyscale\" /></a></h5>";
						$lastLoad = "&l=".$lastLoad;
					}//end if (($loadsExplode[0] > 1) || (($loadsExplode[1] > 0 && ($loadsExplode[0] > 0))))
				}// end if (is_array($loadsExplode))
		  
		}//end if (isset($_COOKIE['beo']))
	}//end if(isset($noBCK)) 

		
//		$difference = $grandTotal - $compareTotal;
		
		
//		if ($difference > 0) { $dc = "<span style='color: #0F0;'>+\$" . number_format($difference,2) . "</span>"; } else { $dc = "<span style='color: #F00;'>\$" . number_format($difference,2) . "</span>"; }
		
//		if ($compare == 1) {
//			if (number_format($compareTotal,2) != number_format($grandTotal,2)) {
//				?>
<!--            <h3>Current Pay Scale: $<?php// echo number_format($grandTotal,2); ?><br />Old Pay Scale: $<?php// echo number_format($compareTotal,2); ?></h3>
                <h4>Difference: <?php// echo $dc; ?></h4>
				  <form method="post" action="reset.php">
				  <input type="submit" name="reset" class="myButton halfB blueB" value="Reset" />
				  </form> -->
				<?php
//			} else {
//				echo "<h2>Grand Total: $" . number_format($grandTotal,2) . "</h2>";
				?>
<!--  		      <form method="post" action="reset.php">
				  <input type="submit" name="reset" class="myButton wideB blueB" value="Reset" />
				  </form> -->
				<?php
//			}//end if ($compareTotal != $grandTotal)
//		} else {
			if (number_format($compareTotal,2) != number_format($grandTotal,2)) {
				echo "<h2>Grand Total: $" . number_format($grandTotal,2) . "</h2>";
				?>
			  <form method="post" action="reset.php">
				  <input type="submit" name="reset" class="myButton wideB blueB" value="Reset" />
				  </form> 
				<?php
			} else {
				echo "<h2>Grand Total: $" . number_format($grandTotal,2) . "</h2>";
				?>
			  <form method="post" action="reset.php">
				  <input type="submit" name="reset" class="myButton wideB blueB" value="Reset" />
				  </form> 
				<?php
			}//end if ($compareTotal != $grandTotal)
//		}//end if ($variable[3] == 1)
		


  }//end if (isset($loads)) 
}//end if (!isset($_COOKIE['nl']) && (!isset($_COOKIE['ow']) && (!isset($_COOKIE['owl']) && (!isset($_COOKIE['owe']) && (!isset($_COOKIE['rt']))))))
?>
  </div><!--end Bottom-->
</div><!--end wrapper-->

<?php if (!isset($_COOKIE['nl']) && (!isset($_COOKIE['ow']) && (!isset($_COOKIE['owl']) && (!isset($_COOKIE['owe']) && (!isset($_COOKIE['rt']) && (isset($_COOKIE['variables']))))))) {


	if (!isset($_COOKIE['pensacola'])) {

//	include('include/scale_display.php');

}//end if (!isset($_COOKIE['pensacola']))  
	}//end if (!isset($_COOKIE['nl']) && (!isset($_COOKIE['ow']) && (!isset($_COOKIE['owl']) && (!isset($_COOKIE['owe']) && (!isset($_COOKIE['rt']) && (isset($_COOKIE['variables']))))))) for status
	
	include ('include/footer.html'); 
	if ((!isset($pro)) || ((isset($pro)) && ($valid == 0))) { 
		if (!isset($_COOKIE['beta'])) {
?>  	
<br />
<div id="adPad" class="clear">&nbsp;</div><!--end adPad-->
<div id="bottomAd" class="adLock">
<center>
<?php 	
//include ('include/ads/amazonad_320x50.html'); 
include ('include/ads/googlead.html'); 
?>
</center>
</div><!--end bottomAd-->
<?php 		}//end if (!isset($_COOKIE['beta']))
	}//end if ((!isset($pro)) || ((isset($pro)) && ($valid == 0))) 
?>
</body>
</html>
<?php }//end if (!isset($auth)) ?>