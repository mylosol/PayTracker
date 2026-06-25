<?php 
include ('../include/db.php');
include ('../include/cookiecheck.php');
include('../include/meta.html'); 
	$pcola = 0;
	$eTitle = "";
	if (isset($_COOKIE['pensacola'])) { $pcola = 1; $eTitle = "Pensacola"; };
?> 

<title>Pay Tracking Reconcile  <?php echo $eTitle; ?> </title>
<link href="../style.css" rel="stylesheet" type="text/css" />
<script type="text/javascript" src="js/reconcile.js"></script>
<script type="text/javascript" src="js/modal.js"></script>
</head>

<body>
<noscript>
<h1 style="text-align: center;">Javascript Must Be Enabled To Use This Site</h1>
</noscript>
<div id="undo" class="undo">
<?php 	$undo = $_GET['u'];
if ($undo != 1) { ?>
<a href="reconcile.php?u=1" title="Undo"><img src="../images/undo.png" alt="Undo" border="0" /></a>
<?php } else { ?>
<a href="reconcile.php" title="Redo"><img src="../images/redo.png" alt="Redo" border="0" /></a>
<?php }//end if ($undo != 1) ?>
</div><!--end undo-->
<?php include ('../include/topMenu.php'); ?>
    		

<?php
	
		if ($undo != 1) {
		$getUnpaidLoadsQuery = $conn->query("SELECT * FROM loads".$id." WHERE paid LIKE '%1%' ORDER BY date ASC LIMIT 100;");
		} else {
		$getUnpaidLoadsQuery = $conn->query("SELECT * FROM loads".$id." WHERE paid LIKE '%2%' ORDER BY date DESC LIMIT 100;");
		}//end if ($undo != 1)
			  if ($getUnpaidLoadsQuery->num_rows > 0) {
				$i = 0;
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
					$em = "";
					$im = "";
					$pd = "";
					$noteIcon = "";
					$ll = "";
					$tripIndicator = "";
					$dh = "";
					
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
						$ll = "DW: <span class=\"moneyDisplay\">$40.00</span>";
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
					$em = "Empty: <span class=\"moneyDisplay\">$" . number_format($empty,2) . "</span>";
				} else {
					$em = "";
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
						  $im = "Load: <span class=\"moneyDisplay\">$" . number_format($oneWay,2) . "</span>";
						  $tripIndicator = "Long Haul Dump";
					  							
					  } else {

							if ($loadMiles == 999) {
								$im = "Something Has Gone<br /><span style=\"color:#F00;\">Catastrophically</span> Wrong!<br />Reload The Page or Wait a Few Minutes.";
							} else {
								$im = "To Far To Calculate!<br />No Pay Information For Distance.";
							}//end if ($loadMiles = 999)

					  }//end if ($getOneWay->num_rows > 0)
					
					
				} else {
					$im = "<p>No Miles For Load</p>";
				}//end if ($loadMiles > 0)
				
				
				if ($emptyMiles > 0) {
					
					  if ($gmc == 1) {
						  $gm = " <img src=\"http://'.$_SERVER['SERVER_NAME'].'/images/googleMaps.png\" class=\"googleMaps\" /> ";
					  } else {
						  $gm = "";
					  }//end if ($gmc == 1)
					  

					$empty = ($emptyMiles * $mt);
					$em = "Empty: <span class=\"moneyDisplay\">$" . number_format($empty,2) . "</span>";
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
					$pd = "Deadhead to " . $pu . "<br />";
					//$totalEmpty = $beginEmptyMiles + $emptyMiles;
					$beginEmptyMiles = ($preDead * $mt);
					$empty = ($emptyMiles * $mt);
					$em = "Empty: <span class=\"moneyDisplay\">$" . number_format($empty,2) . "</span>";
					$dh = "D/H: <span class=\"moneyDisplay\">$" . number_format($beginEmptyMiles,2) . "</span>";
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
				    $im = "Load: <span class=\"moneyDisplay\">$" . number_format($roundTrip,2) . "</span>";
					$tripIndicator = "Round Trip";  
					
				} else {

					if ($loadMiles == 999) {
						$im = "Something Has Gone<br /><span style=\"color:#F00;\">Catastrophically</span> Wrong!<br />Reload The Page or Wait a Few Minutes.";
					} else {
						$im = "To Far To Calculate!<br />No Pay Information For Distance.";
					}//end if ($loadMiles = 999)

					
				}//end if ($getRoundTrip->num_rows > 0
				
				
			} else {
				$im = "<p>No Miles For Load</p>";
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
				$roundTrip = number_format($roundTrip,2);
				
			}//end if ($roundTrip > 0)
		
			if ($splitLoad > 0) {
				$sp = "Split: <span class=\"moneyDisplay\">$15.00</span>";
			} else {
				$sp = "";
			}//end if ($splitLoad > 0)
			
			if ($extra > 0) {
				$ep = "<p>Extra: <span class=\"moneyDisplay\">$" . number_format($extra,2) . "</span></p>";
			} else {
				$ep = "";
			}//end if ($extra > 0)
			
			if ($demurrage > 0) {
				$dp = "Demurrage: <span class=\"moneyDisplay\">$" . number_format($demurrage,2) . "</span>";
			} else {
				$dp = "";
			}//end if ($demurrage > 0)
			
			if ($breakdown > 0) {
				$bp = "Breakdown: <span class=\"moneyDisplay\">$" . number_format($breakdown,2) . "</span>";
			} else {
				$bp = "";
			}//end if ($breakdown > 0)
			
			if ($weekendLoad > 0) {
			$WM = "<span style=\"color:#F00; font-size:10px;\"><sup>&nbsp;(W)</sup></span>";
			} //end if ($weekendLoad > 0)
							

	  
?>			  <a name="<?php echo $i; ?>" class="anchor"></a>	
              <div id="wrapper" class="largeContain lcf">
                  <div id="<?php echo $frtl; ?>" class="add-load-contain">
                  	
                    <div class="loadInfo"><strong><?php echo $date; ?> | <span class="loadNumber"><?php echo $frtl." ".$gm; ?></span></strong></div>
                    <div class="clear"></div><br /> 
                    <div class="loadInfo"><strong><?php echo $pd . $cd . $WM; ?></strong></div>
                    <div class="loadInfo"><?php echo $tripIndicator; ?></div>
                    <?php if (!empty($ormI)) { ?>
                    <div class="loadInfo"><?php echo $ormI; ?></div>
                    <?php }//end if (!empty($ormI)) ?>
                    <div class="clear" style="margin-bottom:10px;"></div>
                    <?php if ($undo != 1) { ?>
                    <form id="<?php echo $frtl; ?>" method="post" action="paid.php">
                    <?php } else { ?>
                    <form id="<?php echo $frtl; ?>" method="post" action="undo.php">
					<?php }//end if ($undo != 1) ?>                    
                    	<?php if (!empty($im)) { ?>
                        <div class="recContain">
                        	<div class="recCheck">
                            	<?php if ($isPaid[0] < 2) { ?>
                                      <input type="checkbox" id="loadcheck<?php echo $i; ?>" name="load" value="2" onChange="notPaid('loadcheck<?php echo $i; ?>', 'im<?php echo $i; ?>')" checked />
                                <?php } else { ?>
                                      <img src="../images/checkmark.png" height="100%" width="100%">
                                <?php 	  if ($undo == 1) { ?> <input type="hidden" id="loadcheck<?php echo $i; ?>" name="undoload" value="1" /> <?php }//end if ($undo == 1) 	
								   }//end if ($isPaid[0] < 2) ?>
                                </div>
                            <div class="recType"><?php echo $im; ?></div>
                        	<div id="im<?php echo $i; ?>" class="recCheck"></div>
                        </div><!--end recContain-->
                        <?php }//end if (!empty($im)) ?>
                        
                    	<?php if (!empty($em)) { ?>
                    	<div class="recContain">
                        	<div class="recCheck">
                            	<?php if ($isPaid[6] < 2) { ?>
                                      <input type="checkbox" id="emptycheck<?php echo $i; ?>" name="empty" value="2" onChange="notPaid('emptycheck<?php echo $i; ?>', 'em<?php echo $i; ?>')" checked />
                                <?php } else { ?>
                                      <img src="../images/checkmark.png" height="100%" width="100%">
                                <?php 	  if ($undo == 1) { ?> <input type="hidden" id="emptycheck<?php echo $i; ?>" name="undoempty" value="1" /> <?php }//end if ($undo == 1) 	
								}//end if ($isPaid[0] < 2) ?>
                                </div>
                            <div class="recType"><?php echo $em; ?></div>
                        	<div id="em<?php echo $i; ?>" class="recCheck"></div>
                        </div><!--end recContain-->
                        <?php }//end if (!empty($em)) ?>
                        
                    	<?php if (!empty($dh)) { ?>
                    	<div class="recContain">
                        	<div class="recCheck">
                            	<?php if ($isPaid[7] < 2) { ?>
                                      <input type="checkbox" id="deadheadcheck<?php echo $i; ?>" name="dh" value="2" onChange="notPaid('deadheadcheck<?php echo $i; ?>', 'em<?php echo $i; ?>')" checked />
                                <?php } else { ?>
                                      <img src="../images/checkmark.png" height="100%" width="100%">
                                <?php 	  if ($undo == 1) { ?> <input type="hidden" id="deadheadcheck<?php echo $i; ?>" name="undodh" value="1" /> <?php }//end if ($undo == 1) 	
								}//end if ($isPaid[0] < 2) ?>
                                </div>
                            <div class="recType"><?php echo $dh; ?></div>
                        	<div id="em<?php echo $i; ?>" class="recCheck"></div>
                        </div><!--end recContain-->
                        <?php }//end if (!empty($em)) ?>
                        
                    	<?php if (!empty($sp)) { ?>
                    	<div class="recContain">
                        	<div class="recCheck">
                            	<?php if ($isPaid[1] < 2) { ?>
                                      <input type="checkbox" id="splitcheck<?php echo $i; ?>" name="split" value="2" onChange="notPaid('splitcheck<?php echo $i; ?>', 'sp<?php echo $i; ?>')" checked />
                                <?php } else { ?>
                                      <img src="../images/checkmark.png" height="100%" width="100%">
                                <?php 	  if ($undo == 1) { ?> <input type="hidden" id="splitcheck<?php echo $i; ?>" name="undosplit" value="1" /> <?php }//end if ($undo == 1) 	
								}//end if ($isPaid[0] < 2) ?>
                                </div>
                            <div class="recType"><?php echo $sp; ?></div>
                        	<div id="sp<?php echo $i; ?>" class="recCheck"></div>
                        </div><!--end recContain-->
                        <?php }//end if (!empty($sp)) ?>
                        
                    	<?php if (!empty($ep)) { ?>
                    	<div class="recContain">
                        	<div class="recCheck">
                            	<?php if ($isPaid[4] < 2) { ?>
                                      <input type="checkbox" id="extracheck<?php echo $i; ?>" name="extra" value="2" onChange="notPaid('extracheck<?php echo $i; ?>', 'ep<?php echo $i; ?>')" checked />
                                <?php } else { ?>
                                      <img src="../images/checkmark.png" height="100%" width="100%">
                                <?php 	  if ($undo == 1) { ?> <input type="hidden" id="extracheck<?php echo $i; ?>" name="undoextra" value="1" /> <?php }//end if ($undo == 1) 	
								}//end if ($isPaid[0] < 2) ?>
                                </div>
                            <div class="recType"><?php echo $ep; ?></div>
                        	<div id="ep<?php echo $i; ?>" class="recCheck"></div>
                        </div><!--end recContain-->
                        <?php }//end if (!empty($ep)) ?>
                        
                    	<?php if (!empty($dp)) { ?>
                    	<div class="recContain">
                        	<div class="recCheck">
                            	<?php if ($isPaid[3] < 2) { ?>
                                      <input type="checkbox" id="demcheck<?php echo $i; ?>" name="dem" value="2" onChange="notPaid('demcheck<?php echo $i; ?>', 'dp<?php echo $i; ?>')" checked />
                                <?php } else { ?>
                                      <img src="../images/checkmark.png" height="100%" width="100%">
                                <?php 	  if ($undo == 1) { ?> <input type="hidden" id="demcheck<?php echo $i; ?>" name="undodem" value="1" /> <?php }//end if ($undo == 1) 	
								}//end if ($isPaid[0] < 2) ?>
                                </div>
                            <div class="recType"><?php echo $dp; ?></div>
                        	<div id="dp<?php echo $i; ?>" class="recCheck"></div>
                        </div><!--end recContain-->
                        <?php }//end if (!empty($dp)) ?>
                        
                    	<?php if (!empty($bp)) { ?>
                    	<div class="recContain">
                        	<div class="recCheck">
                            	<?php if ($isPaid[2] < 2) { ?>
                                      <input type="checkbox" id="breakcheck<?php echo $i; ?>" name="break" value="2" onChange="notPaid('breakcheck<?php echo $i; ?>', 'bp<?php echo $i; ?>')" checked />
                                <?php } else { ?>
                                      <img src="../images/checkmark.png" height="100%" width="100%">
                                <?php 	  if ($undo == 1) { ?> <input type="hidden" id="breakcheck<?php echo $i; ?>" name="undobreak" value="1" /> <?php }//end if ($undo == 1)<strong></strong>	
								}//end if ($isPaid[0] < 2) ?>
                                </div>
                            <div class="recType"><?php echo $bp; ?></div>
                        	<div id="bp<?php echo $i; ?>" class="recCheck"></div>
                        </div><!--end recContain-->
                        <?php }//end if (!empty($bp)) ?>
                        
                    	<?php if (!empty($ll)) { ?>
                    	<div class="recContain">
                        	<div class="recCheck">
                            	<?php if ($isPaid[5] < 2) { ?>
                                      <input type="checkbox" id="lastcheck<?php echo $i; ?>" name="last" value="2" onChange="notPaid('lastcheck<?php echo $i; ?>', 'll<?php echo $i; ?>')" checked />
                                <?php } else { ?>
                                      <img src="../images/checkmark.png" height="100%" width="100%">
                                <?php 	  if ($undo == 1) { ?> <input type="hidden" id="lastcheck<?php echo $i; ?>" name="undolast" value="1" /> <?php }//end if ($undo == 1)
								}//end if ($isPaid[0] < 2) ?>
                                </div>
                            <div class="recType"><?php echo $ll; ?></div>
                        	<div id="ll<?php echo $i; ?>" class="recCheck"></div>
                        </div><!--end recContain-->
                        <?php }//end if (!empty($ll)) ?>
                        
                    <br />		
                    <input type="hidden" name="ln" value="<?php echo $frtl; ?>" />
                    <input type="hidden" name="paid" value="<?php echo $paidArray; ?>" />
                    <input type="hidden" name="anchor" value="<?php echo $i; ?>" />
                    
                    <?php if ($undo != 1) { ?>	
					<input type="image" src="../images/thumbs_up.png" alt="Submit" height="40px" width="40px" class="thumb" />
                    <?php } else { ?>
					<input type="image" src="../images/thumbs_netural.png" alt="Submit" height="40px" width="40px" class="thumb" />
                    <?php }//end if ($undo != 1) ?>
                    </form>
                    <?php if ($_GET['u'] != 1) { ?>
                    <form method="post" action="unpaid.php">
                     <input type="hidden" name="ln" value="<?php echo $frtl; ?>" />
								<?php if ($notpaid == 1) { ?>
                     		<input type="image" src="../images/notPaid.png?v=3" alt="notPaid" height="40px" width="40px" class="notPaid" /> 
                     	<?php } else { ?>
									<input type="image" src="../images/notPaid.png?v=3" alt="notPaid" height="40px" width="40px" class="notPaid grayscale" />  
								<?php }//end if ($notpaid == 1) ?>                
                    </form>  
                    <?php }//end if ($_GET['u'] != 1) ?>
                  </div><!--end <?php echo $frtl; ?>-->  
<?php     
			  if ($notes != NULL) {
				  ?>
                  <a href="javascript:void(0)" onMouseOver="notePopup('myModal<?php echo $i; ?>', 'note<?php echo $i; ?>', 'modal-close<?php echo $i; ?>')"><img src="../images/notes.png?v=2" id="note<?php echo $i; ?>" class="noteBtn"></a>
				  <div id="myModal<?php echo $i; ?>" class="modal">
				  
					<!-- Modal content -->
					<div class="modal-content">
					  <div class="modal-header">
						<span id="modal-close<?php echo $i; ?>" class="modal-close">x</span>
						<h2>Notes</h2>
					  </div><!--end modal-header-->
					  <div class="modal-body">
						<h2><?php echo $notes; ?></h2>
					  </div><!--end modal-body-->
					</div><!--end modal-content-->
				  
				  </div><!--end myModal-->
				  <?php
			  }//end if ($notes != NULL)
?>                                
              </div><!--end wrapper-->
<?php
                  $i++;
				  }//end while ($row = $getUnpaidLoadsQuery->fetch_assoc())


			  } else {
				  
?>
                  <div class="largeContain justText">
                    <center>
                    <h1>Either no loads have been tracked or</h1>
                    <h3>Something Has Gone <span style="color:#F00;">Catastrophically</span> Wrong!</h3>
                    </center>
                  </div><!--end error-->
<?php				  
				  
			  }//end if ($getUnpaidLoadsQuery->num_rows > 0) 
?>

        
<div class="clear"></div>
<?php include('../include/footer.html'); ?>
</body>
</html>
