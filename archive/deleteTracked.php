<?php
	include ('include/db.php');
	include ('include/cookiecheck.php');
	$loadID = $_GET['l'];
	$cookieName = "L" . $loadID;
	$nq = $_GET['n'];
	$delete1 = $_POST['delete'];
	$delete2 = trim($delete1);
	$delete = strtolower($delete2);
	$cv = $_COOKIE[$cookieName];
	$cvExplode = explode("-", $cv);
	$pt = $_GET['f'];
	$curYear = date('Y');
	
	if ($pt > 1) {
		$frtl = $pt;
	} else {
		$frtl = $cvExplode[14];
	}//end if ($pt > 1)
	
	$getPayInfo = $conn->query("SELECT `paid` FROM `loads".$id."` WHERE `loads".$id."`.`frtl` = ".$frtl." LIMIT 1;");
	  if (!$getPayInfo) {
		  echo '<center><h1>Unable to retrieve load information</h1>
				<h3>Something Has Gone <span style="color:#F00;">Catastrophically</span> Wrong!</h3>
				<h3>Wait a bit and try again</h3>
				</center>';
				exit;
	  } else  {
		  while($row = $getPayInfo->fetch_assoc()) { $getll = $row["paid"]; }//end while($row = $getPayInfo->fetch_assoc())
		  $llE = explode("-", $getll);
		  $isLastLoadPaid = $llE[5];
	  }
			  
	if (isset($_POST['cancel'])) {
		include('header/headerRedirect.php');
		exit;
	}//end if isset($_POST['cancel']))
	
	if (!isset($_POST['delete'])) {
		
		include('include/meta.html'); ?> 
		<title>Pay Tracking Delete</title>
		<link href="style.css" rel="stylesheet" type="text/css" />
		</head>
		
		<body>
		<noscript>
		<h1 style="text-align: center;">Javascript Must Be Enabled To Use This Site</h1>
		</noscript>
            <div id="topMenuContain">
              <div id="hbMenu">
                  <div class="wrapper">
                      <div class="content">
                        <?php include('include/menu.php'); ?>
                      </div><!--end content-->
                      <div class="parent"><img src="../images/hamburger.png"></div>
                  </div><!--end wrapper-->
              </div><!--end hbMenu-->
            <div id="logo" class="logo"><img src="../images/logo.png" alt="Pay Tracker" class="logo" /></div>
            <?php
                if ((isset($pro)) && ($valid == 1)) {
            ?>
            <div id="logo" class="pro"><img src="../images/pro.png" alt="Pro" class="pro" /></div>
            <?php
                }//end if (isset($pro))
            ?>
            </div><!--end topMenuContain-->
            
				<div id="wrapper" class="largeContain">
				  <div id="delete" class="add-load-contain">
						<center><h1>Delete Load# <?php echo $frtl; ?>?</h1>
						
						<form method="post">
						Type 'Delete' to confirm:&nbsp;<input name="delete" type="text" width="5">
                        <div class="clear"></div>
                        <br />
                        <?php
							if ($isLastLoadPaid == 1) {
						?>
                        	<p><span style="color:#F00;">*</span>This load is associated with the $40.00 &quot;Last Load&quot; pay.  Deleting this record will also remove that addtional $40.</p>
                        <?php
							}//end if ($paidLastLoad == 1)
						?>
				        <input type="submit" name="cancel" class="myButton quarterB greyB" value="Cancel" />
						<input type="submit" name="confirm" class="myButton halfB blueB" value="Confirm" />
						</form>
						
						</center>
				  </div><!--end add-load-contain-->
				</div><!--end largeContain-->
		</body>
		</html>
		<?php		
	}//else if (!isset($delete))
	
	  if (isset($delete1)) {
		if ($delete == "delete") {
			
			$tpArray = $_COOKIE['loads'];
			$tpExplode = explode("-", $tpArray);
			$rt = $tpExplode[0];
			$ow = $tpExplode[1];
			$notQualified = $tpExplode[2];

			$datew = date('w');
			if ($datew == 0) {
				$oneWeek =  date('Y-m-d', strtotime("-2 days"));
				$twoWeek =  date('Y-m-d', strtotime("-9 days"));
			}
			if ($datew == 1) {	
				$oneWeek =  date('Y-m-d', strtotime("-3 days"));
				$twoWeek =  date('Y-m-d', strtotime("-10 days"));
			}
			if ($datew == 2) {
				$oneWeek =  date('Y-m-d', strtotime("-4 days"));
				$twoWeek =  date('Y-m-d', strtotime("-11 days"));
			}
			if ($datew == 3) {
				$oneWeek =  date('Y-m-d', strtotime("-5 days"));
				$twoWeek =  date('Y-m-d', strtotime("-12 days"));
			}
			if ($datew == 4) {
				$oneWeek =  date('Y-m-d', strtotime("-6 days"));
				$twoWeek =  date('Y-m-d', strtotime("-13 days"));	
			}
			if ($datew == 5) {
				$oneWeek = date('Y-m-d');
				$twoWeek =  date('Y-m-d', strtotime("-7 days"));
			}
			if ($datew == 6) {
				$oneWeek =  date('Y-m-d', strtotime("-1 days"));
				$twoWeek =  date('Y-m-d', strtotime("-8 days"));
			}
			

			$getPayQuery = $conn->query("SELECT * FROM `loads".$id."` WHERE `loads".$id."`.`frtl` = ".$frtl." LIMIT 1;");
			  if ($getPayQuery->num_rows < 1) {
				  echo '<center><h1>Unable to retrieve load information</h1>
						<h3>Something Has Gone <span style="color:#F00;">Catastrophically</span> Wrong!</h3>
						<h3>Wait a bit and try again</h3>
						</center>';
						exit;
			  } else  {
				  while($row = $getPayQuery->fetch_assoc()) { 
					$np = $row["np"]; 
					$op = $row["op"];
					$loadDate = $row["date"];
					$paidll = $row["paid"];
				  }//end while($row = $getPayQuery->fetch_assoc())
				  
			  $dateOfLoad =  date('Y-m-d', strtotime($loadDate));
			  $loadYear = date('Y', strtotime($loadDate));
			  $paidE = explode("-", $paidll);
			  $paidLastLoad = $paidE[5];

				  $getWeeklyQuery = $conn->query("SELECT * FROM `totals` WHERE `id` = ".$id." LIMIT 1;");
				  if (!$getWeeklyQuery) {
					  echo '<center><h1>Unable to update current weekly totals</h1>
							<h3>Something Has Gone <span style="color:#F00;">Catastrophically</span> Wrong!</h3>
							<h3>Wait a bit and try again</h3>
							</center>';
							exit;
				  } else {
					  
					  if ($dateOfLoad >= $twoWeek) {
						  if ($dateOfLoad >= $oneWeek) {
							  while($row = $getWeeklyQuery->fetch_assoc()) {
								$curWeek = $row["curWeek"];
								$curDiff = $row["curDiff"];
							  }//end while($row = $getWeeklyQuery->fetch_assoc())
							  
							  $weeklyTotal = $curWeek - $np;
							  $weeklyDiff = $curDiff - $op;
							  	if ($paidLastLoad == 1) { $weeklyTotal = $weeklyTotal - 40; }//end if ($paidLastLoad == 1)
							  $conn->query("UPDATE `totals` SET `curWeek` = '".$weeklyTotal."' WHERE `totals`.`id` = ".$id." LIMIT 1;") or die ('<center>
								  <h1>Unable to set current week totals</h1>
								  <h3>Something Has Gone <span style="color:#F00;">Catastrophically</span> Wrong!</h3>
								  <h3>Wait a bit and try again</h3>
								  </center>');
							  $conn->query("UPDATE `totals` SET `curDiff` = '".$weeklyDiff."' WHERE `totals`.`id` = ".$id." LIMIT 1;") or die ('<center>
								  <h1>Unable to set current week difference</h1>
								  <h3>Something Has Gone <span style="color:#F00;">Catastrophically</span> Wrong!</h3>
								  <h3>Wait a bit and try again</h3>
								  </center>');						
						  } else {
							  while($row = $getWeeklyQuery->fetch_assoc()) {
								$preWeek = $row["preWeek"];
								$preDiff = $row["preDiff"];
							  }
							  $preWeeklyTotal = $preWeek - $np;
							  $preWeeklyDiff = $preDiff - $op;
							  	if ($paidLastLoad == 1) { $preWeeklyTotal = $preWeeklyTotal - 40; }//end if ($paidLastLoad == 1)
							  $conn->query("UPDATE `totals` SET `preWeek` = '".$preWeeklyTotal."' WHERE `totals`.`id` = ".$id." LIMIT 1;") or die ('<center>
								  <h1>Unable to set previous week totals</h1>
								  <h3>Something Has Gone <span style="color:#F00;">Catastrophically</span> Wrong!</h3>
								  <h3>Wait a bit and try again</h3>
								  </center>');
							  $conn->query("UPDATE `totals` SET `preDiff` = '".$preWeeklyDiff."' WHERE `totals`.`id` = ".$id." LIMIT 1;") or die ('<center>
								  <h1>Unable to set previous week difference</h1>
								  <h3>Something Has Gone <span style="color:#F00;">Catastrophically</span> Wrong!</h3>
								  <h3>Wait a bit and try again</h3>
								  </center>');
						  }//end if ($dateOfLoad >= $oneWeek)
					  }//end if ($dateOfLoad >= $twoWeek) *Within 2 Weeks*
					  
										  
				  }//end  if (!$getWeeklyQuery || !mysql_num_rows($getWeeklyQuery))
			  }//end if (!$getPayQuery || !mysql_num_rows($getPayQuery))

			
			$conn->query("DELETE FROM `loads".$id."` WHERE `loads".$id."`.`frtl` = ".$frtl." LIMIT 1;") or die ('<center>
							  <h1>Unable to delete load</h1>	
							  <h3>Something Has Gone <span style="color:#F00;">Catastrophically</span> Wrong!</h3>
							  <h3>Wait a bit and try again</h3>
							  </center>');
			
			  if (isset($loadID)) {
				  
				if ($cvExplode[0] == 0) {
					
					if (!isset($nq)) {
					  $ow--;
					  $notQualified++;
					}//end (!isset($nq))
					
				  $newLoads = $rt . "-" . $ow . "-" . $notQualified;
				}//end if ($cvExplode[0] == 0)
				
				if ($cvExplode[0] == 1) {
					
					if (!isset($nq)) {
					  $rt--;
					  $notQualified++;
					}//end (!isset($nq))
					
				  $newLoads = $rt . "-" . $ow . "-" . $notQualified;
				}//end if ($cvExplode[0] == 1)
				
			  $cookie = "3-".$frtl;
			  setcookie($cookieName, $cookie, time()+43200);
			  setcookie("loads", $newLoads, time()+43200);
			  }//end if (isset($loadID))
			include('header/headerRedirect.php');
		} else {
	?>
				<script type="text/javascript">
				  alert("Please confirm you wish to delete load.");
				  history.back();
				</script>
	<?php	
		exit;
		}//endif ($delete == "delete")
	  }//end if (isset($delete1))
	
?>