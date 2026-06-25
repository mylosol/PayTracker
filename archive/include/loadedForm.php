       	<?php
		  if (isset($_COOKIE['lt'])) { $lastTerminal = $_COOKIE['lt']; }
		  if (isset($_COOKIE['nq'])) { $notQualified = $_COOKIE['nq']; }
		  if (isset($_COOKIE['owl'])) { $oneWay = $_COOKIE['owl']; }

		  if (isset($_COOKIE['loads'])) { $pastLoadsTotal = $_COOKIE['loads']; }
		  $pastLoadsExplode = explode("-", $pastLoadsTotal);
		  $pastLoads = $loadsExplode[0] + $loadsExplode[1] + $loadsExplode[2];
		  $pl = $pastLoads - 1;
		  $pastLoadInfo = "L" . $pl;
		  if (isset($_COOKIE[$pastLoadInfo])) { $pl = $_COOKIE[$pastLoadInfo]; }
//		  $plExplode = explode("-", $pl);
		  $wk = '';
     	  $sel = "";
     	  $sel2 = "";


	if ((isset($_COOKIE['pensacola'])) || ($homeCity == "pensacola") || ($pcola == 1)) { 
	   $terminalsql = "SELECT * FROM `pcola_terminal` ORDER BY `pcola_terminal`.`id` ASC";
		$terminal = $conn->query($terminalsql);
		
		
		$citysql = "SELECT city FROM pcola_largeMiles ORDER BY city ASC"; 
		$city = $conn->query($citysql);
			
	} else {	
		$terminalsql = "SELECT * FROM terminal ORDER BY terminal.id ASC";
		$terminal = $conn->query($terminalsql);
		
		
		$citysql = "SELECT city FROM largeMiles ORDER BY city ASC"; 
		$city = $conn->query($citysql);
		
	}//end if ((isset($_COOKIE['pensacola'])) || ($homeCity == "pensacola") || ($pcola == 1))



			?>
			<form method="post" action="loadedmiles.php">
            <div id="beginEmpty">
            <noscript>Begin Empty: <select name="beginEmpty"><option value=""></option><option value="Panama City">Panama Yard</option><option value="Niceville">Niceville</option><option value="Freeport">Freeport Yard</option></select><br /><br /></noscript>
            </div>
			Pick Up Location: <select name="pick-up">
			<?php while ($row = $terminal->fetch_assoc()) { 
				
				$t = $row["terminal"]; 
				if ($t == $lastTerminal) {
				$sel = "Selected";
				} else {
				$sel = "";
				}//end if ($t == $lastTerminal)
	
			  print "<option " . $sel . " >" . $t . "</option>\n";
				  }//end while
			?>
			</select>      
            <br /><br />
            <div id="split">
			First Stop: <select name="firstStop">
			  <option value=""></option>
			  <?php while ($row = $city->fetch_assoc()) {
			  		 $c = $row["city"]; 
				print "<option value=\"".$c."\">".$c."</option>\n";
				}//end while
	
			  if ($wk > 0) {
				  $ws = "Checked";
			  } else {
				  $ws = "";
			  }//end if ($wk > 0)
			  
			 ?>
			</select><br /><br />
            </div><!--end split-->


<?php 
	if ((isset($_COOKIE['pensacola'])) || ($homeCity == "pensacola") || ($pcola == 1)) { 
	   $terminalsql = "SELECT * FROM `pcola_terminal` ORDER BY `pcola_terminal`.`id` ASC";
		$terminal = $conn->query($terminalsql);
		
		
		$citysql = "SELECT city FROM pcola_largeMiles ORDER BY city ASC"; 
		$city = $conn->query($citysql);
			
	} else {	
		$terminalsql = "SELECT * FROM terminal ORDER BY terminal.id ASC";
		$terminal = $conn->query($terminalsql);
		
		
		$citysql = "SELECT city FROM largeMiles ORDER BY city ASC"; 
		$city = $conn->query($citysql);
		
	}//end if ((isset($_COOKIE['pensacola'])) || ($homeCity == "pensacola") || ($pcola == 1))

?>


			<span id="deliveryLocation">Delivery Location:</span> <select name="delivery">
			 <?php while ($row1 = $city->fetch_assoc()) {
			  		 $c = $row1["city"]; 
				print "<option value=\"".$c."\">".$c."</option>\n";
				}//end while
	
			  if ($wk > 0) {
				  $ws = "Checked";
			  } else {
				  $ws = "";
			  }//end if ($wk > 0)
			  
			 ?>
			</select><br /><br />            
            
            <?php
			
			if (isset($oneWay)) {
			?>
            
			<form method="post" action="loadedmiles.php">
			End Empty: <select name="return">
			<?php while ($row2 = $terminal->fetch_assoc()) {
				 $t = $row2["terminal"]; 
		
			print "<option>" . $t . "</option>\n";
				}//end while
			?>
			</select> 
            <br /><br />
            <?php     
			}//end if (isset($oneWay))
			?>
            
          <div id="Checks Contain" class="checksContain">
		
        	<?php
			if ((isset($_COOKIE['owl']) && (!isset($_COOKIE['nq'])))) {
				include ('owChecks.php');
			} else {
				include ('rtChecks.php');
			}//end if ((isset($_COOKIE['owl']) && (!isset($_COOKIE['nq']))))
			?>
        
                    
            <div id="di" class="middleChecks">
            <noscript><span class="instructions">Input as whole numbers in minutes.<br /></span></noscript>
            </div><!--end demurrage instructions-->
            
            <div id="Bottom Checks" class="bottomChecks">
                
                <div id="outerDemContain" class="checks">
                  <div id="demContain" class="addChecks">
                  Demurrage&nbsp;&nbsp;<input type="checkbox" name="dem" id="demurrage" value="0" class="dem" onClick="addDemurrage('demurrage', 'dem', 'di', 'dn')"  />&nbsp;&nbsp;&nbsp;&nbsp;
                  </div><!--end demContain-->
                  
                  <div id="dem" class="addTime">
                  <noscript><input type="number" name="demTime" maxlength="3" value="0" step="1" class="dem" /></noscript>
                  </div><!--end dem-->
                  
                  <div id="dn" class="dn_instructions">
					<noscript><span id="notice" class="notice">You need to -45 minutes from total time before input.</span></noscript>
					<div class="notice">&nbsp;</div> 
                  </div><!--end demurrage instructions-->
                </div><!--end outerDemContai-->
                
                <div id="outerBreakContain" class="checks">
                  <div id="breakContain" class="addChecks">
                  Breakdown&nbsp;&nbsp;<input type="checkbox" name="break" id="breakdown" value="0" class="dem" onClick="addBreakdown('breakdown', 'bre', 'di')"  />
                  </div><!--end breakContain-->
                  <div id="bre" class="addTime">
                  <noscript><input type="number" name="breakTime" maxlength="3" value="0" step="1" class="dem" /></noscript>
                  </div><!--end bre-->
                  

                </div><!--end outerBreakContain-->
            </div><!--end Bottom Checks-->  
          </div><!--end Checks Contain-->      
            
                     
            
			<input type="hidden" name="load" value="<?php echo $loads; ?>" />
	
			<?php	if (isset($rt)) { ?>
				<input type="hidden" name="type" value="1" />
			<?php  }//end if (isset($rt))
			
				if (isset($owl)) { ?>
				<input type="hidden" name="type" value="0" />
			<?php }//end if (isset($ow)) 
            
				if (isset($notQualified)) { ?>
				<input type="hidden" name="nq" value="1" />
				<input type="hidden" name="type" value="0" />
			<?php  }//end if (isset($notQualified)) ?>
            
            	<div class="clear"></div>
				<input type="submit" name="cancel" class="myButton quarterB greyB" value="Cancel" />
				<input type="submit" name="done" class="myButton threeQuarterB blueB" value="Done" />
			</form>
			
<?php 











  