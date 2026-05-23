<?php 
include('../include/db.php');
include('../include/cookiecheckMP.php');
include ('../include/variables.php');	  
	
	
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
<?php include('../include/meta.html'); ?> 
<title>Pay Checking <?php echo $eTitle; ?> </title>
<link href="../style.css" rel="stylesheet" type="text/css" />
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
	$today = date('Y-m-d');
	if (isset($_COOKIE['beta'])) { $beta = 1; }
    include ('../include/topMenu.php'); 
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

<?php
		
		$newestDate = $conn->query("SELECT date FROM loads".$id." WHERE paid LIKE '%1%' ORDER BY date DESC LIMIT 1;");
		while ($row = $newestDate->fetch_assoc()) { $lastLoadDate = $row["date"]; }//end while ($row = $newestDate->fetch_assoc())
		
		$lastLoadDateSTR =  strtotime($lastLoadDate);
		$lastLoadDay = date('N', $lastLoadDateSTR);
		echo "lastLoadDay: ".$lastLoadDay."<br />";
		$todayDay = date('N');
		$remainingDays = 0;//5 - $todayDay;
		$todayDateSTR = strtotime("-7 days");
		$todayDateVariable = date($todayDateSTR);
    	$endDateConstruct = date('Y-m-d');


	
	for ($fl = 0; $fl < 3; $fl++) {
		$dateRangeEnd = date('Y-m-d', $lastLoadDate);
		$dateRangeBegin = strtotime("-", $lastLoadDate);
		
	
		if ($lastLoadDay == 1) { 
		  if ($fl == 0) { $daysBack = date('Y-m-d', strtotime("-11 days")); }
		  if ($fl == 1) { $daysBack = date('Y-m-d', strtotime("-18 days")); }
		  if ($fl == 2) { $daysBack = date('Y-m-d', strtotime("-25 days")); }
		  $dateRangeEnd = $daysBack;
		  if ($fl == 0) { $daysBack1 = date('Y-m-d', strtotime("-17 days")); }
		  if ($fl == 1) { $daysBack1 = date('Y-m-d', strtotime("-24 days")); }
		  if ($fl == 2) { $daysBack1 = date('Y-m-d', strtotime("-31 days")); }
		  $dateRangeBegin = $daysBack1;
		}//end if ($lastLoadDay == 1)
		
		if ($lastLoadDay == 2) { 
		  if ($fl == 0) { $daysBack = date('Y-m-d', strtotime("-11 days")); }
		  if ($fl == 1) { $daysBack = date('Y-m-d', strtotime("-18 days")); }
		  if ($fl == 2) { $daysBack = date('Y-m-d', strtotime("-25 days")); }
		  $dateRangeEnd = $daysBack;
  		  if ($fl == 0) { $daysBack1 = date('Y-m-d', strtotime("-17 days")); }
		  if ($fl == 1) { $daysBack1 = date('Y-m-d', strtotime("-24 days")); }
		  if ($fl == 2) { $daysBack1 = date('Y-m-d', strtotime("-31 days")); }
		  $dateRangeBegin = $daysBack1;

		}//end if ($lastLoadDay == 2)
		
		if ($lastLoadDay == 3) { 
		  if ($fl == 0) { $daysBack = date('Y-m-d', strtotime("-11 days")); }
		  if ($fl == 1) { $daysBack = date('Y-m-d', strtotime("-18 days")); }
		  if ($fl == 2) { $daysBack = date('Y-m-d', strtotime("-25 days")); }
		  $dateRangeEnd = $daysBack;
		  if ($fl == 0) { $daysBack1 = date('Y-m-d', strtotime("-17 days")); }
		  if ($fl == 1) { $daysBack1 = date('Y-m-d', strtotime("-24 days")); }
		  if ($fl == 2) { $daysBack1 = date('Y-m-d', strtotime("-31 days")); }
		  $dateRangeBegin = $daysBack1;
		}//end if ($lastLoadDay == 3)
		
		if ($lastLoadDay == 4) { 
		  if ($fl == 0) { $daysBack = date('Y-m-d', strtotime("-11 days")); }
		  if ($fl == 1) { $daysBack = date('Y-m-d', strtotime("-18 days")); }
		  if ($fl == 2) { $daysBack = date('Y-m-d', strtotime("-25 days")); }
		  $dateRangeEnd = $daysBack;
		  if ($fl == 0) { $daysBack1 = date('Y-m-d', strtotime("-17 days")); }
		  if ($fl == 1) { $daysBack1 = date('Y-m-d', strtotime("-24 days")); }
		  if ($fl == 2) { $daysBack1 = date('Y-m-d', strtotime("-31 days")); }
		  $dateRangeBegin = $daysBack1;
		}//end if ($lastLoadDay == 4)
		
		if ($lastLoadDay == 5) { 
		  if ($fl == 0) { $daysBack = date('Y-m-d', strtotime("-11 days")); }
		  if ($fl == 1) { $daysBack = date('Y-m-d', strtotime("-18 days")); }
		  if ($fl == 2) { $daysBack = date('Y-m-d', strtotime("-25 days")); }
		  $dateRangeEnd = $daysBack;
		  if ($fl == 0) { $daysBack1 = date('Y-m-d', strtotime("-17 days")); }
		  if ($fl == 1) { $daysBack1 = date('Y-m-d', strtotime("-24 days")); }
		  if ($fl == 2) { $daysBack1 = date('Y-m-d', strtotime("-31 days")); }
		  $dateRangeBegin = $daysBack1;
		}//end if ($lastLoadDay == 5)
		
		if ($lastLoadDay == 6) { 
		  if ($fl == 0) { $daysBack = date('Y-m-d', strtotime("-11 days")); }
		  if ($fl == 1) { $daysBack = date('Y-m-d', strtotime("-18 days")); }
		  if ($fl == 2) { $daysBack = date('Y-m-d', strtotime("-25 days")); }
		  $dateRangeEnd = $daysBack;
		  if ($fl == 0) { $daysBack1 = date('Y-m-d', strtotime("-17 days")); }
		  if ($fl == 1) { $daysBack1 = date('Y-m-d', strtotime("-24 days")); }
		  if ($fl == 2) { $daysBack1 = date('Y-m-d', strtotime("-31 days")); }
		  $dateRangeBegin = $daysBack1;
		}//end if ($lastLoadDay == 6)
		
		if ($lastLoadDay == 7) { 
		  if ($fl == 0) { $daysBack = date('Y-m-d', strtotime("-11 days")); }
		  if ($fl == 1) { $daysBack = date('Y-m-d', strtotime("-18 days")); }
		  if ($fl == 2) { $daysBack = date('Y-m-d', strtotime("-25 days")); }
		  $dateRangeEnd = $daysBack;
		  if ($fl == 0) { $daysBack1 = date('Y-m-d', strtotime("-17 days")); }
		  if ($fl == 1) { $daysBack1 = date('Y-m-d', strtotime("-24 days")); }
		  if ($fl == 2) { $daysBack1 = date('Y-m-d', strtotime("-31 days")); }
		  $dateRangeBegin = $daysBack1;
		}//end if ($lastLoadDay == 7)

		echo "<br /> First dateRangeBegin: ". $dateRangeBegin."<br />";
		echo "First dateRangeEnd: ".$dateRangeEnd."<hr />";
	
		if ($undo != 1) {
		$getUnpaidLoadsQuery = $conn->query("SELECT * FROM loads".$id." WHERE paid LIKE '%1%' AND date BETWEEN '".$dateRangeBegin."' AND '".$dateRangeEnd."' ORDER BY `date` ASC LIMIT 50;");
		} else {
		$getUnpaidLoadsQuery = $conn->query("SELECT * FROM loads".$id." WHERE paid LIKE '%1%' AND date BETWEEN '".$dateRangeBegin."' AND '".$dateRangeEnd."' ORDER BY `date` ASC LIMIT 50;");
		}//end if ($undo != 1)
			  if ($getUnpaidLoadsQuery->num_rows > 0) {

				  
				  while ($row = $getUnpaidLoadsQuery->fetch_assoc()) {
					$gm = "";
					$oneWay = 0;
					$roundTrip = 0;
					$compareTrip = 0;
					$empty = 0;
					$miles = 0;
					$loadPayTotal = 0;
					$lastLoad = "";
					$sb = "";
					$splitLoad = 0;
					$weekendLoad = 0;
				    $break = 0;
				    $breakdown = 0;
					$dem = 0;
					$demurrage = 0;
					$extra = 0;
					$notpaid = 0;
					$WM = "";
					$em = 0;
					$im = 0;
					$pd = "";
					$noteIcon = "";
					$ll = "";
					$tripIndicator = "";
					$dh = 0;
					
					$date = $row["date"];	
					$dateStr = strtotime($date);
					$date = date('m-d-Y', $dateStr);
					$frtl = $row["frtl"];	
					$variables = $row["variables"];
					$loadInfoArray = $row["loadinfo"];
					$paidArray = $row["paid"];
					$notpaid = $row["notPaid"];
					$notes = $row["notes"];	

					include ('../include/variables.php');
					$loadInfo = explode("-", $loadInfoArray);	
					$isPaid = explode("-", $paidArray);	

					$loadType = $loadInfo[0];
					$emptyMiles = $loadInfo[1];		
					$pu = $loadInfo[2];
					$del = $loadInfo[3];
					$splitLoad = $loadInfo[4];
					$weekendLoad = $loadInfo[5];
					$emptyORloaded = $loadInfo[6];
					$preDead = $loadInfo[7];
					$gmc = $loadInfo[8];
					$extra = $loadInfo[9];
					$dem = $loadInfo[10];
					$break = $loadInfo[11];
					$ori = $loadInfo[12];
					$orm = $loadInfo[13];
					
					$loadPaid = $isPaid[0];
					$splitPaid = $isPaid[1];
					$breakdownPaid = $isPaid[2];
					$demurragePaid = $isPaid[3];
					$extraPaid = $isPaid[4];
					$lastLoadPaid = $isPaid[5];
					$emptyPaid = $isPaid[6];
					
					if ($lastLoadPaid > 0) {
						$ll = 40;
					} else {
						$ll = 0;
					}

					
			  if ($loadType == 0) {
				  $oneWayBoostOffset = 0.003;
			  } else {
				  $oneWayBoostOffset = 0;
			  }//end if ($loadType == 0)
			  			  
		if ($emptyMiles == 1) { $emptyMiles = 0; }
								

	if ($pcola == 1) { 		
		$milesSql = 'SELECT `'.$pu.'` FROM `pcola_largeMiles` WHERE `city` =  "'.$del.'" LIMIT 1;';
	} else {
		$milesSql = 'SELECT `'.$pu.'` FROM `largeMiles` WHERE `city` =  "'.$del.'" LIMIT 1;';
	}
		$milesQuery = $conn->query($milesSql);
		if ($milesQuery->num_rows > 0) {
			
			while ($row = $milesQuery->fetch_assoc()) { $loadMiles = $row[$pu]; }
			
				if ($loadMiles == 0) {
					$newStart = preg_replace("/ /","+",$pu);
					$newEnd = preg_replace("/ /","+",$del);
					include('../include/googleMapsAPI.php');
					$gm = " <img src=\"http://'.$_SERVER['SERVER_NAME'].'/images/googleMaps.png\" class=\"googleMaps\" /> ";
				} //end if ($miles == 0)
		} else {		
			$loadMiles = 999;
		}//end if ($milesQuery->num_rows > 0)
		
		if ($weekendLoad > 0) {
			$weekend = 0.10;
		} else {
			$weekend = 0;
		}//end if ($weekendLoad > 0)
		
		if ($dem > 0) {
			$demurrage = $dem * 0.25;
		}//end if ($dem > 0)
		
		if ($break > 0) {
			$breakdown = $break * 0.30;
		}//end if ($break > 0)
		
		if ($loadType == 0) {
			$oneWayBoostOffset = 0.003;
		} else {
			$oneWayBoostOffset = 0;
		}//end if ($loadType == 0) 

		
		if ($loadType == 0) {

			
			if ($emptyORloaded == 1) {
				
				if ($emptyMiles > 0) {
					
					  $gmc = $cvExplode[8];
					  if ($gmc == 1) {
						  $gm = " <img src=\"http://'.$_SERVER['SERVER_NAME'].'/images/googleMaps.png\" class=\"googleMaps\" /> ";
					  } else {
						  $gm = "";
					  }//end if ($gmc == 1)

					$empty = ($emptyMiles * $mt);
					$em = $empty;
				} else {
					$em = 0;
				}//end if ($miles > 0)
				
				$cd = $del . " to " . $pu;
				$sb = "";
				
			} else { //else if ($emptyORloaded == 1)
				
				if ($loadMiles > 0) {
					
				include('../include/outofroute.php');
				
									
					$getOneWay = $conn->query("SELECT `".$tenure."` FROM oneWay WHERE miles >= ".$loadMiles." LIMIT 1");
						
					  if ($getOneWay->num_rows > 0) {
						  
						  while ($row = $getOneWay->fetch_assoc()) { $oneWayBase =  $row[$tenure]; }
						  $newBoosts = $boosts + $oneWayBoostOffset;
						  $oneWay = ($oneWayBase * $newBoosts) + $oneWayBase;
						  $oneWay = ($oneWay * $weekend) + $oneWay;
						  $im = $oneWay;
						  $tripIndicator = "Long Haul Dump";
					  							
					  }//end if ($getOneWay->num_rows > 0)
					
					
				} else {
					$im = 0;
				}//end if ($loadMiles > 0)
				
				
				if ($emptyMiles > 0) {
					
					  if ($gmc == 1) {
						  $gm = " <img src=\"http://'.$_SERVER['SERVER_NAME'].'/images/googleMaps.png\" class=\"googleMaps\" /> ";
					  } else {
						  $gm = "";
					  }//end if ($gmc == 1)
					  

					$empty = ($emptyMiles * $mt);
					$em = $empty;
					$sb = "<br />";
				} else {
					$em = 0;
				}//end if ($miles > 0)
				
				$cd = $pu . " to " . $del;
				
				if ($preDead == "preload") {
					$nq = "&n=1";
					$cd = "Pre-Load to " . $del;
				} else {
					$nq = "";
				}//end if ($preDead == "preload")
				
				if ((is_numeric($preDead) && ($preDead > 0))) {
					$pd = "Deadhead to " . $pu . "<br />";
					//$totalEmpty = $beginEmptyMiles + $emptyMiles;
					$beginEmptyMiles = ($preDead * $mt);
					$empty = ($emptyMiles * $mt);
					$em = $empty;
					$dh = $beginEmptyMiles;
					$sb = "<br />";
				} else {
					$pd = "";
				}//end if ($preDead == 2)
				
			}//end if ($emptyORloaded == 1)
			
			
			
		} else { //else if ($loadType == 0)
			
			if ($loadMiles > 0) {
				
				include('../include/outofroute.php');
				
				$getRoundTrip = $conn->query("SELECT `".$tenure."` FROM roundTrip WHERE miles >= ".$loadMiles." LIMIT 1");
				
				if ($getRoundTrip->num_rows > 0) {
					
					while ($row = $getRoundTrip->fetch_assoc()) { $roundTripBase =  $row[$tenure]; }
					$roundTrip = ($roundTripBase * $boosts) + $roundTripBase;
					$roundTrip = ($roundTrip * $weekend) + $roundTrip;
				    $im = $roundTrip;
					$tripIndicator = "Round Trip";  
					
				}//end if ($getRoundTrip->num_rows > 0
				
				
			} else {
				$im = 0;
			}//end if ($miles > 0)
			
			$cd = $pu . " to " . $del;
		}//end if ($loadType == 0)
		
			if (($oneWay > 0) || ($empty > 0)) {
		
				if ($oneWay == 0) {
					$oneWay = "";
					$loadPayTotal = $empty;
				} else if ($empty == 0) {
					$empty = "";
					$loadPayTotal = $oneWay;
				} else if (($oneWay > 0) && ($empty > 0)) {
					$loadPayTotal = $oneWay + $empty;
				}

			}//end if (($oneWay > 0) || ($empty > 0)
			
			if ($roundTrip > 0) {
				
				$loadPayTotal = $roundTrip;
				$roundTrip = $roundTrip;
				
			}//end if ($roundTrip > 0)
		
			if ($splitLoad > 0) {
				$sp = 15;
			} else {
				$sp = 0;
			}//end if ($splitLoad > 0)
			
			if ($extra > 0) {
				$ep = $extra;
			} else {
				$ep = 0;
			}//end if ($extra > 0)
			
			if ($demurrage > 0) {
				$dp = $demurrage;
			} else {
				$dp = 0;
			}//end if ($demurrage > 0)
			
			if ($breakdown > 0) {
				$bp = $breakdown;
			} else {
				$bp = 0;
			}//end if ($breakdown > 0)
			
							

	  
			$weekTotal = $weekTotal + $im + $em + $dh + $sp + $ep + $dp + $bp + $ll;
			
				  }//end while ($row = $getUnpaidLoadsQuery->fetch_assoc())


			  } else {
				  
?>

<!--Stop looking at my code... I'm a lady and I don't appreciate it!-->

<?php				  
				  
			  }//end if ($getUnpaidLoadsQuery->num_rows > 0) 
echo "dateRangeBegin: ".$dateRangeBegin."<br />";
$drbDateStr = strtotime($dateRangeBegin);
echo "drbDateStr: ".$drbDateStr."<br />";
$dateRangeBegin = date('m-d-Y', $drbDateStr);
echo "dateRangeBegin: ".$dateRangeBegin."<br />";
$dreDateStr = strtotime($dateRangeEnd);
echo "dreDateStr: ".$dreDateStr."<br />";
$dateRangeEnd = date('m-d-Y', $dreDateStr);
echo "dateRangeEnd: ".$dateRangeEnd>"<br />";	  
?>
<center>
	<h2>Week of: <?php echo $dateRangeBegin; ?> to <?php echo $dateRangeEnd ?></h2>
</center>
<?php			  
			  

	}//end for ($fl = 0; $fl < 2; $fl++;)
?>


  </div><!--end Top-->
</div><!--end wrapper-->


<?php	include ('../include/footer.html'); 
	if ((!isset($pro)) || ((isset($pro)) && ($valid == 0))) { ?>
<br />
<div id="adPad" class="clear">&nbsp;</div><!--end adPad-->
<div id="bottomAd" class="adLock">
<center>
<?php 	
include ('../include/ads/googlead.html'); 
?>
</center>
</div><!--end bottomAd-->
<?php }//end if (!isset($pro)) ?>
</body>
</html>
