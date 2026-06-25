<?php 
include('include/db.php');
include('include/cookiecheckMP.php');
//include ('include/variables.php');	 	
	
$addUpdate = null;	
	
	$rt = 1; 
	
	
	
	$beta = 0;
	if(isset($_GET['b'])) {
		$beta = $_GET['b'];
	}//end if isset($_GET['b'])
	if(isset($beta)) {
		setcookie("beta", 1, time()+31536000); 
	}//end if(isset($beta))
	
	$addDelivery = 0;
	$updateMiles = 0;
	if(isset($_POST['ad'])) { $addDelivery = 1; }//end if(isset($_POST['ad']))
	if(isset($_POST['um']) || (isset($_GET['um']))) { $updateMiles = 1; }//end if(isset($_POST['um']))
	
	if(isset($_GET['m'])) {
		$checkMiles = $_GET['m'];
	}//end if(isset($_GET['m'])
	
	if(isset($_GET['c1'])) {
		$checkc1 = $_GET['c1'];
	}//end $_GET['c1']
	if(isset($checkc1)) {
		$checkc1 = explode(" ", $checkc1);
		$checkc1 = $checkc1[0] . ", " . $checkc1[1];
	}//end if(isset($checkc1))
	
	if(isset($_GET['c2'])) {
		$checkc2 = $_GET['c2'];
	}//end $_GET['c2']
	if(isset($checkc2)) {
		$checkc2 = explode(" ", $checkc2);
		$checkc2 = $checkc2[0] . ", " . $checkc2[1];
	}//end if(isset($checkc2))
	
	if(isset($_GET['c'])) {
		$insertCity = $_GET['c'];
	}//end $_GET['c']
	if(isset($_GET['p'])) {
		$insertPickup = $_GET['p'];
	}//end $_GET['p']
	$pcola = 0;
	$eTitle = "";
	if (isset($_COOKIE['pensacola'])) { $pcola = 1; $eTitle = "Pensacola"; };
	
	$ttile = "";
	$ktitle = "";
	
	//if ($rt == 1) { $ttile = "Add Round Trip"; } elseif ($owl == 1) { $ttile = "Add One Way"; }//end type title set
	
	if ($addDelivery == 1) { $ktitle = "Add Delivery"; } elseif ($updateMiles == 1) { $ttile = "Update Miles"; $ktitle = "";	} //end kind title set
	

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
		
		$citysql = "SELECT city FROM pcola_largeMiles ORDER BY city ASC"; 
		$city = $conn->query($citysql);
		
    	$city2sql = "SELECT city FROM pcola_largeMiles ORDER BY city ASC"; 
		$city2 = $conn->query($city2sql);

	} else {	
		$terminalsql = "SELECT * FROM terminal ORDER BY terminal.id ASC";
		$terminal = $conn->query($terminalsql);
		
		$citysql = "SELECT city FROM largeMiles ORDER BY city ASC"; 
		$city = $conn->query($citysql);
		
    	$city2sql = "SELECT city FROM pcola_largeMiles ORDER BY city ASC"; 
		$city2 = $conn->query($city2sql);

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
	<div id="city" class="city"><p class="cityText"><strong>Pensacola</strong></p></div><!--end city--> <?php 
		$ht = "Pensacola";		
	} else { ?>
	<div id="city" class="city"><p class="cityText"><strong>Panama City</strong></p></div><!--end city-->
	<?php	
	   $ht = "Panama";		
	}//end if ((isset($_COOKIE['pensacola'])) || ($homeCity == "pensacola"))  ?> 
	




				<div id="addCity" class="largeContain">
						<center><h1><?php echo $addUpdate; ?></h1>
				  <div id="addcontain" class="add-load-contain">
											
						<h3><?php echo $ttile . " " . $ktitle; ?></h3>
                
				<form method="post"  action="alVerify.php">
							
			<?php if($updateMiles == 1) { ?>
				
				<span id="c1">From:</span> 
				<select name="updateStart">
				  <?php if ($city->num_rows > 0) {
				   	 	while ($row = $city->fetch_assoc()) { $c = $row["city"]; 
						   $sel = "";
						   if($c == $checkc1) { $sel = "selected "; }
						   print "<option value=\"".$c."\" ".$sel.">".$c."</option>\n";
						}//end while ($row = $city->fetch_assoc()) { $c = $row["city"]; 
					}//end if ($city->num_rows > 0)
				 	$f = 0;		  
				  ?>
				</select><br /><br />
				
				<span id="c2">To:</span> 
				<select name="updateEnd">
				  <?php if ($city2->num_rows > 0) {
				   	 	while ($row = $city2->fetch_assoc()) { $c = $row["city"]; 
							 $sel = "";
							 if($c == $checkc2) { $sel = "selected "; }
							 print "<option value=\"".$c."\" ".$sel.">".$c."</option>\n";
						}//end while ($row = $city->fetch_assoc())
					}//end if ($city->num_rows > 0)
				 	$f = 0;		  
				  ?>
				</select><br /><br />
				
				<div class="updateMilesForm">							
							Miles: <input type="number" name="updateMiles" value="<?php echo $checkMiles; ?>" /> &nbsp;&nbsp;
							<input type="submit" name="check" class="myButton quarterB greyB" value="Check" />
				</div><!-- end updateMilesForm -->
				
				<br />
				
				 
			
			<?php } elseif($addDelivery == 1) { ?>           	
           	
           	
           	
                Pick-up Location: <select name="pickup">
                  <?php if ($terminal->num_rows > 0) {
                  	 	while ($row = $terminal->fetch_assoc()) { $c = $row["terminal"]; 
							  print "<option value=\"".$c."\">".$c."</option>\n";
						}//end while ($row = $terminal->fetch_assoc())
                    }//end if ($terminal->num_rows > 0)
                 ?>
                </select>
                
                
				<br /><br />				
				<div class="cityFormWrapper">													
					<div class="newMilesForm">							
							State:&nbsp;<select name="state">
							<option value="FL">FL</option>							
							<option value="AL">AL</option>
							<option value="GA">GA</option>
							<option value="MS">MS</option>
							<option value="LA">LA</option>
							</select>							
					</div><!-- end newMilesForm -->                
			                

			              
               <div class="newCityForm">
							New City:&nbsp;<input type="text" name="newCityRT" />
					</div><!-- end newCityForm -->			
				</div><!-- end cityFormWrapper -->
				<br /><br />
				<div class="updateMilesForm">							
							Miles: <input type="number" name="miles" />
				</div><!-- end updateMilesForm -->
				
			<?php	}//end elseif($addDelivery ==1) ?>	
				
				<br /><br />				 
					 
					
					<?php if($rt == 1) { ?> <input type="hidden" name="rt" value="1" /> <?php }//end if($rt == 1) ?>
					
					<?php if($addDelivery == 1) { ?> <input type="hidden" name="add" value="1" /> <?php }//end if($addDelivery == 1) ?>		
					
					<?php if($updateMiles == 1) { ?> <input type="hidden" name="update" value="1" /> <?php }//end if($updateMiles == 1) ?>
					
					<?php if ((isset($_COOKIE['pensacola'])) || ($homeCity == "pensacola") || ($pcola == 1)) { ?>
							<input type="hidden" name="city" value="pensacola" />
					<?php } else { ?>
							<input type="hidden" name="city" value="panama" />
					<?php	}//end if ((isset($_COOKIE['pensacola'])) || ($homeCity == "pensacola") || ($pcola == 1)) ?>						
					
					<input type="submit" name="cancel" class="myButton quarterB greyB" value="Cancel" />                
               <input type="submit" name="cDone" class="myButton threeQuarterB blueB" value="Submit" />
          	</form>


				  </div><!--end addcontain-->
				</div><!--end addCity-->


 


<?php	include ('include/footer.html'); ?>
<br />
</body>
</html>