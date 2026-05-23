<?php
include ('../include/db.php');
include ('../include/cookiecheck.php');

$getAnnouncement = $conn->query("SELECT announce FROM account WHERE id = ".$id." LIMIT 1;") or die ('<center><h1>Unable to get announcement</h1><h3>Something Has Gone <span style="color:#F00;">Catastrophically</span> Wrong!</h3><h3>Wait a bit and try again</h3></center>');
while ($row = $getAnnouncement->fetch_assoc()) { $announcement = $row["announce"]; }
if ($announcement == 1) {
	include ('announce.php');
} else {
$variables = $_COOKIE['variables'];
include ('../include/variables.php');	  

if (isset($_POST['cancel'])) {
		include('../header/headerRedirect.php');
}//end if (isset($_POST['cancel'])

if (isset($_POST['done'])) {
	$cookieName = "L" . $_POST['c'];
	$cv = $_COOKIE[$cookieName];
	$cvExplode = explode("-", $cv);
	$dh = $cvExplode[7];
	$today = date('Y-m-d H:i:s');
	$lastLoad = $_POST['lastLoad'];
	$frtl= $_POST['FRTL'];
	$rawNotes = $_POST['note'];
	$editPost = $_POST['oldNum'];
	$newpay = $_POST['n'];
	$oldpay = $_POST['o'];
	$rawNotes = trim($rawNotes);
	  if ($rawNotes != "") {
		  $notes = preg_replace('/[^A-Za-z0-9\-]/', ' ', $rawNotes);
		  $notes = "'".$notes."'";
	  } else {
		  $notes = "NULL";
	  }//end if ($rawNotes != "")
	  
	  //paid variables
	  	
		$loadIndicator = 1;
		$lastIndicator = 0;
		$splitIndicator = 0;
		$extraIndicator = 0;
		$breakdownIndicator = 0;
		$demIndicator = 0;
		$emptyIndicator = 0;
		$deadheadIndicator = 0;
			
		
		//empty
		if ($cvExplode[1] > 0) {
			$emptyIndicator = 1;
		}
		
		//deadhead
		if ($cvExplode[7] > 0) {
			$deadheadIndicator = 1;
		}
				
		//split
		if ($cvExplode[4] > 0) {
			$splitIndicator = 1;
		}
		
		//extra
		if ($cvExplode[9] > 0) {
			$extraIndicator = 1;
		}
		
		//breakdown
		if ($cvExplode[11] > 0) {
			$breakdownIndicator = 1;
		}
		
		//demurrage
		if ($cvExplode[10] > 0) {
			$demIndicator = 1;
		}
		
		if ($lastLoad == 1) {
			$lastIndicator = 1;
		}//end if ($lastLoad == 1)
	  
	  
		$paid = $loadIndicator . "-" . $splitIndicator . "-" . $breakdownIndicator . "-" . $demIndicator . "-" . $extraIndicator . "-" . $lastIndicator . "-" . $emptyIndicator . "-" . $deadheadIndicator;
	  //paid variables
	  
	  
	  if (!is_numeric($frtl)) { ?>
	  
			<script type="text/javascript">
			  alert("Value must be Numeric.");
			  history.back();
			</script>
  
  <?php	exit;
	  }//end if (!is_numeric($frtl))
	  
	  if (strlen($frtl) < 7) { ?>
	  
			<script type="text/javascript">
			  alert("Too Few Numbers.");
			  history.back();
			</script>
  
  <?php	exit;	  
	  }//end if (strlen($frtl) < 7)
	  
	  if (strlen($frtl) > 7) { ?>
	  
			<script type="text/javascript">
			  alert("Too Many Numbers.");
			  history.back();
			</script>
  
  <?php	exit;	  
	  }//end if (strlen($frtl) < 7)

	  if (isset($editPost)) {
		  $conn->query("UPDATE loads".$id." SET frtl = '".$frtl."', notes =  ".$notes." WHERE loads".$id.".frtl = ".$editPost." LIMIT 1 ;") or die ('<center>
				  <h1>Unable to insert load info into database</h1>
				  <h3>Something Has Gone <span style="color:#F00;">Catastrophically</span> Wrong!</h3>
				  <h3>Wait a bit and try again</h3>
				  </center>');
		  $newCookie = $cvExplode[0] . "-" . $cvExplode[1] . "-" . $cvExplode[2] . "-" . $cvExplode[3] . "-" . $cvExplode[4] . "-" . $cvExplode[5] . "-" . $cvExplode[6] . "-" . $cvExplode[7] . "-" . $cvExplode[8] . "-" . $cvExplode[9] . "-" . $cvExplode[10] . "-" . $cvExplode[11] . "-" . $cvExplode[12] . "-" . $cvExplode[13] . "-" . $frtl;
		  setcookie($cookieName, $newCookie, time()+86400, '/');
	  } else {
		  
		  $checkSetQuery = $conn->query("SELECT loadinfo FROM loads".$id." WHERE frtl = '".$frtl."' LIMIT 1;");
		  if ($checkSetQuery->num_rows < 1) {
			  $conn->query("INSERT INTO loads".$id." (frtl, date, variables, loadinfo, paid, notes, np, op) VALUES ('".$frtl."', NOW() + INTERVAL 2 HOUR, '".$variables."', '".$cv."', '".$paid."', ".$notes.", '".$newpay."', '".$oldpay."');") or die ('<center>
			              <h1>Unable to insert load info into database</h1>
						  <h3>Something Has Gone <span style="color:#F00;">Catastrophically</span> Wrong!</h3>
						  <h3>Wait a bit and try again</h3>
						  </center>');
		  $newCookie = $cv . "-" . $frtl;
		  setcookie($cookieName, $newCookie, time()+86400, '/');
		  setcookie("active", 1, time()+36000, '/');
		  
		  $getWeeklyQuery = $conn->query("SELECT * FROM totals WHERE id = '".$id."' LIMIT 1;");
		  if ($getWeeklyQuery->num_rows > 0) {

			  while ($row = $getWeeklyQuery->fetch_assoc()) { $getWeekly = $row["curWeek"]; }
			  $weeklyTotal = $getWeekly + $newpay;
			  while ($row = $getWeeklyQuery->fetch_assoc()) { $getWeeklyDiff = $row["curDiff"]; }
			  $weeklyDiff = $getWeeklyDiff + $oldpay;
			  if (isset($lastLoad)) { $weeklyTotal = $weeklyTotal + 40; }//end if (isset($lastLoad))
			  $conn->query("UPDATE totals SET curWeek = '".$weeklyTotal."' WHERE totals.id = ".$id." LIMIT 1;") or die ('<center>
			  			  <h1>Unable to update current week totals</h1>	
						  <h3>Something Has Gone <span style="color:#F00;">Catastrophically</span> Wrong!</h3>
						  <h3>Wait a bit and try again</h3>
						  </center>');
			 
			  $conn->query("UPDATE totals SET curDiff = '".$weeklyDiff."' WHERE totals.id = ".$id." LIMIT 1;") or die ('<center>
			              <h1>Unable to update current week difference</h1>
						  <h3>Something Has Gone <span style="color:#F00;">Catastrophically</span> Wrong!</h3>
						  <h3>Wait a bit and try again</h3>
						  </center>');

		  } else {

			  echo '<center><h1>Unable to update current weekly total</h1>
					<h3>Something Has Gone <span style="color:#F00;">Catastrophically</span> Wrong!</h3>
					<h3>Wait a bit and try again</h3>
					</center>';
					exit;

		  }//end  if ($getWeeklyQuery->num_rows > 0) 
		    
		  } else {
			  
				?>
				  <script type="text/javascript">
					alert("The Load <?php echo $frtl; ?> Already Exists.");
					history.back();
				  </script>
				<?php	
				exit;
		  }//end if ($checkSetQuery->num_rows > 0)
	  }//end if (isset($editPost)) 
      include('../header/headerRedirect.php');

}//end if (isset($_POST['done']))

include('../include/meta.html'); ?> 
<title>Pay Tracking Portal</title>
<link href="../style.css" rel="stylesheet" type="text/css" />
<script src="http://code.jquery.com/jquery-latest.min.js"></script>
</head>

<body>
<noscript>
<h1 style="text-align: center;">Javascript Must Be Enabled To Use This Site</h1>
</noscript>
<?php include ('../include/topMenu.php'); ?>
  
<?php  

$ln = $_GET['c'];
$loadPay = $_GET['n'];
$oldPay = $_GET['o'];
$edit = $_GET['e'];
$pt = $_GET['p'];
$lastLoadGet = $_GET['l'];
$loadsTotal = $_COOKIE['loads'];

if (isset($loadsTotal)) {
	$loadsExplode = explode("-", $loadsTotal);
	$loads = $loadsExplode[0] + $loadsExplode[1] + $loadsExplode[2];
}//end if (isset($_COOKIE['loads']))

if ((isset($ln)) || (isset($loadPay)) || (isset($edit)) || (isset($pt)) || (isset($lastLoadGet))) {

?>    		
<div id="topwrapper" class="largeContain" style="overflow: visible;">
  <div id="Top" class="add-load-contain">

      <?php 	
	  
	  
	  if (isset($tenure)) { 
	  
		  if ($shift == "day") { $sd = "Day Shift"; }
		  if ($shift == "night") { $sd = "Night Shift"; }
		  if ($slip == "yes") { $ssd = "Slip Seat"; }
		  if ($slip == "no") { $ssd = "No Slip"; }
	  	
?> 
<div id="vContain">
  <div id="variables">&nbsp; <?php echo $td; ?> &nbsp; | &nbsp; <?php echo $sd; ?> &nbsp; | &nbsp; <?php echo $ssd; ?> &nbsp; 
  </div><!--end "variables"--> 
</div><!--end "vContain"-->
		<?php }//end if (isset($tenure)) 
		
		$c = "";
				
		$cookieName = "L" . $ln;
		$cv = $_COOKIE[$cookieName];
		$cvExplode = explode("-", $cv);
		$pu = $cvExplode[2];
		$del = $cvExplode[3];
		$llc = "";
		$offset = "";
		
		if (isset($edit)) {
			if ($pt > 1) {
				$oldNum = $pt;
				$getLocQuery = $conn->query("SELECT loadinfo FROM loads".$id." WHERE loads".$id.".frtl = ".$oldNum." LIMIT 1 ;") or die ('<center>
					  <h1>Unable to retieve load info</h1>
					  <h3>Something Has Gone <span style="color:#F00;">Catastrophically</span> Wrong!</h3>
					  <h3>Wait a bit and try again</h3>
					  </center>');
				while ($row = $getLocQuery->fetch_assoc()) { $loadInfo = $row["loadinfo"]; }
				$loadInfoExplode = explode("-", $loadInfo);
				$pu = $loadInfoExplode[2];
				$del = $loadInfoExplode[3];	
			} else {
			  $oldNum = $cvExplode[14];
			}//end if ($pt > 1)
			$getInfoQuery = $conn->query("SELECT * FROM loads".$id." WHERE loads".$id.".frtl = ".$oldNum." LIMIT 1 ;") or die ('<center>
					  <h1>Unable to retrieve all load info</h1>
					  <h3>Something Has Gone <span style="color:#F00;">Catastrophically</span> Wrong!</h3>
					  <h3>Wait a bit and try again</h3>
					  </center>');
					  
			  while ($row = $getInfoQuery->fetch_assoc()) {
			  $oldNotes = $row["notes"];
			  $paidInfo = $row["paid"];
			  }
			  $paidExplode = explode("-", $paidInfo);
			  $paidLastLoad = $paidExplode[5];
			  
			  if ($paidLastLoad > 0) {
				  $llc = "checked";
			  }//end if ($paidLastLoad > 0)
			
			$oldLoadNum = "value=\"".$oldNum."\"";
			
		} else {
			$oldLoadNum = "";
			$oldNotes = "";
		}//end if (isset($edit))
		
		
		?>
        <form id="Tracking" method="post">
        <h1 style="text-align:center;">Load <?php echo $ln; ?></h1>
        <h4 style="text-align:center;"><?php echo $pu . " to " . $del; ?></h4>
          <br /> 
            <p>
            Load #:&nbsp;&nbsp;&nbsp;<input type="number" name="FRTL" size="10" <?php echo $oldLoadNum; ?> />
            <br />
            Notes:
            <br />
            <script type="application/x-javascript">
				  const textarea = document.querySelector("textarea");
				  
				  textarea.addEventListener("input", event => {
					  const target = event.currentTarget;
					  const maxLength = target.getAttribute(1000);
					  const currentLength = target.value.length;
				  
					  if (currentLength >= maxLength) {
						  return console.log("You have reached the maximum number of characters.");
					  }
				  
					  console.log(`${maxLength - currentLength} chars left`);
				  });             
			</script>

			<textarea id="area" name="note" cols="29" rows="3" maxlength="1000"><?php echo $oldNotes; ?></textarea><br /><span id='count'></span>

            <div id="textarea_feedback" style="font-size:10px"></div>
            </p>
          <!--Last Load--> 
          <?php
          $noBCK = $_COOKIE['bck'];
          if ($noBCK != 1) {	
          	if (($lastLoadGet == 1) || ($paidLastLoad > 0)) {
          ?>
          <input type="checkbox" name="lastLoad" value="1" <?php echo $llc; ?> />&nbsp;&nbsp;Backhaul Load / &quot;Go Home Load&quot;&nbsp;&nbsp;<a class="tooltip" href="#"><strong>?</strong><span class="custom help"><img src="../images/Help.png" alt="Help" height="48" width="48" /><em>Backhaul Load</em>The Backhaul Differential Pay (i.e. $40.00) is typically paid on the last load, or the load that takes you back towards your home terminal.  Checking this box will doccument the $40.00 for this load number.</span></a>&nbsp;&nbsp;
         <br />
         <span class="notice">*Only once per day.</span>
          <?php	
          	}//end if ($lastLoadGet == 1)
          }//end if ($noBCK != 1)
          ?>
          <!--Last Load--> 
            <br />
            <?php if (isset($edit)) { ?>
            <input type="hidden" name="oldNum" value="<?php echo $oldNum; ?>" />
            <?php }//end if (isset($edit)) ?>
            <input type="hidden" name="c" value="<?php echo $ln; ?>" />
            <input type="hidden" name="n" value="<?php echo $loadPay; ?>" />
            <input type="hidden" name="o" value="<?php echo $oldPay; ?>" />
            <div class="clear"></div>
            <input type="submit" name="cancel" class="myButton quarterB greyB" value="Cancel" />
            <input type="submit" name="done" class="myButton threeQuarterB blueB" value="Done" />
        </form>


  </div><!--end Top-->
</div><!--end topwrapper-->
<?php }//end if ((isset($ln)) || (isset($loadPay)) || (isset($edit)) || (isset($pt)) || (isset($lastLoadGet))) ?>


<div id="smwrapper" class="largeContain">
  <div id="sm" class="add-load-contain">
  
  <?php
	  $getWeeklyTotal = $conn->query("SELECT * FROM totals WHERE id = '".$id."' LIMIT 1;");
	  if ($getWeeklyTotal->num_rows > 0) {
		
		while ($row = $getWeeklyTotal->fetch_assoc()) {
		  $curWeek = $row["curWeek"];
		  $curDiff = $row["curDiff"];
		  $preWeek = $row["preWeek"];
		  $preDiff = $row["preDiff"];
		}

	  } else {

		  echo '<center><h1>Unable to retrieve Current Weekly Total</h1>
				<h3>Something Has Gone <span style="color:#F00;">Catastrophically</span> Wrong!</h3>
				<h3>Wait a bit and try again</h3>
				</center>';

	  }//end  if ($getWeeklyTotal->num_rows > 0)
	

?>
     <div class="thisWeek"><h1>This Week: <span class="tanNumber">$<?php echo number_format($curWeek,2); ?></span></h1></div><!--end thisWeek-->
<?php
	if ($compare == 1) {
		$difference = $curWeek - $curDiff;
		if ($difference >= 0) {
			$dc = "Net Gain: <span style='color: #0F0;'>+\$" . number_format($difference,2) . "</span>";
		} else {
			$dc = "Net Loss: <span style='color: #F00;'>\$" . number_format($difference,2) . "</span>";
		}//end if ($diff > 0) 
		
		?>
        <div class="clear"></div>
	<center><h1><?php echo $dc; ?></h1></center>
        <?php
	}//end if ($compare == 1)
	?>

  </div><!--end sm-->
</div><!--end smwrapper-->

<div id="middlewrapper" class="largeContain">
  <div id="middle" class="add-load-contain">
  
  <?php
  	$getLastSixQuery = $conn->query("SELECT * FROM loads".$id." ORDER BY date DESC LIMIT 6;");
			  if ($getLastSixQuery->num_rows > 0) {

				  while($row = $getLastSixQuery->fetch_assoc()) {
					$date = $row["date"];	
					$dateStr = strtotime($date);
					$date = date('m-d-Y', $dateStr);
					$dateNumber = date('w', $dateStr);
					$frtl = $row["frtl"];
					$ec = "";
					
					
						for ($cn = 1; $cn <= $loads; $cn++) {
							$ccn = "L" . $cn;
							$ccv = $_COOKIE[$ccn];
							$cce = explode("-", $ccv);
							$ccf = $cce[14];
							if ($ccf == $frtl) {
								$ec = "&l=".$cn;
							}//end if ($ccf == $frtl)
						}//end for ($cn = 0; $cn < $loads; $cn++)
					
					?>
                    <div class="loadInfo"><strong><?php echo $date; ?> | <span class="loadNumber"><?php echo $frtl; ?></span></strong>&nbsp;&nbsp;&nbsp;&nbsp;
                    <a href="/edit.php?t=1&f=<?php echo $frtl.$ec; ?>" title="Edit"><img src="../images/edit-icon-outline.png" border="0" alt="Edit" width="15px" height="15px" /></a>&nbsp;&nbsp;&nbsp;&nbsp;
                    <a href="/deleteTracked.php?f=<?php echo $frtl.$ec; ?>" title="Delete"><img src="../images/x.png" border="0" alt="Delete" width="15px" height="15px" /></a>
                    </div><!--end loadInfo-->
                    <br /><br />
                    <?php
				  }//end while($row = $getLastSixQuery->fetch_assoc())


			  } else {
			?>				  
                    <center>
                    <h1>Either no loads have been tracked or</h1>
                    <h3>Something Has Gone <span style="color:#F00;">Catastrophically</span> Wrong!</h3>
                    </center>
			<?php
			  }//end if ($getLastSixQuery->num_rows > 0)
			?>

  </div><!--end middle-->
</div><!--end middlewrapper-->

<div id="sbwrapper" class="largeContain">
  <div id="sb" class="add-load-contain">
  
	<center><h1>Last Week: <span class="tanNumber">$<?php echo number_format($preWeek,2); ?></span></h1></center>
    <?php
	if ($compare == 1) {
		$preDiff = $preWeek - $preDiff;
		if ($preDiff >= 0) {
			$dc = "Net Gain: <span style='color: #0F0;'>+\$" . number_format($preDiff,2) . "</span>";
		} else {
			$dc = "Net Loss: <span style='color: #F00;'>\$" . number_format($preDiff,2) . "</span>";
		}//end if ($diff > 0) 
		
		?>
	<center><h1><?php echo $dc; ?></h1></center>
        <?php
	}//end if ($compare == 1)
	?>

  </div><!--end sb-->
</div><!--end sbwrapper-->


<?php include('../include/footer.html'); ?>
</body>
</html>
<?php }//end if ($announcement == 1) ?>