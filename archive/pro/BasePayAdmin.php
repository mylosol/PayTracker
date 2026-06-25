<?php 
include('../include/db.php');
include ('../include/variables.php');
	$rtb = "rtb";
	$lhb = "lhb";
	$resetAlert = "";
	$headder = "";

	
	if (isset($_COOKIE['pensacola'])) {
		$eTitle = "Pensacola";
	} else {
		$eTitle = "Panama City";
	}

	if(isset($_COOKIE['basePayType'])) {
		if ($_COOKIE['basePayType'] == $rtb) { 
			if(isset($_GET["rs"])) {
				$resetAlert = $_GET["rs"];
			}//end if(isset($_GET["rs"]))
			if ($resetAlert == 1) {
				?>
				<script type="text/javascript">
				  alert("Round Trip Base Pay has been reset to Default Values!!");
				  window.location.replace("http://".$_SERVER['SERVER_NAME']."/pro/BasePayAdmin.php");
				</script>
				<?php
			} //end if ($resetAlert == 1)
			
			if ($resetAlert == 2) {
				?>
				<script type="text/javascript">
				  alert("TEST Round Trip Base Pay has been reset to Current Values!!");
				  window.location.replace("http://".$_SERVER['SERVER_NAME']."/pro/BasePayAdmin.php");
				</script>
				<?php
			} //end if ($resetAlert == 2)
		}//end if ($_COOKIE['basePayType'] == $rtb)
	}//end if(isset($_COOKIE['basePayType']))
	
	if(isset($_COOKIE['basePayType'])) {
		if ($_COOKIE['basePayType'] == $lhb) { 
			if(isset($_GET["rs"])) { $resetAlert = $_GET["rs"]; }//end if(isset($_GET["rs"]))
			if ($resetAlert == 1) {
				?>
				<script type="text/javascript">
				  alert("Long Haul Base Pay has been reset to Default Values!!");
				  window.location.replace("http://".$_SERVER['SERVER_NAME']."/pro/BasePayAdmin.php");
				</script>
				<?php
			} //end if ($resetAlert == 1)
			
			if ($resetAlert == 2) {
				?>
				<script type="text/javascript">
				  alert("TEST Long Haul Base Pay has been reset to Current Values!!");
				  window.location.replace("http://".$_SERVER['SERVER_NAME']."/pro/BasePayAdmin.php");
				</script>
				<?php
			} //end if ($resetAlert == 2)
		}//end if ($_COOKIE['basePayType'] == $lhb)
	}//end if(isset($_COOKIE['basePayType']))
	
	if (!isset($_COOKIE['basePayType'])) { 
		if(isset($_GET["rs"])) {
			$resetAlert = $_GET["rs"];
		}//end if(isset($_GET["rs"]))
		if ($resetAlert == 1) {
			?>
			<script type="text/javascript">
			  alert("Long Haul Base Pay has been reset to Default Values!!");
			  window.location.replace("http://".$_SERVER['SERVER_NAME']."/pro/BasePayAdmin.php");
			</script>
			<?php
		} //end if ($resetAlert == 1)
		
		if ($resetAlert == 2) {
			?>
			<script type="text/javascript">
			  alert("TEST Long Haul Base Pay has been reset to Current Values!!");
			  window.location.replace("http://".$_SERVER['SERVER_NAME']."/pro/BasePayAdmin.php");
			</script>
			<?php
		} //end if ($resetAlert == 2)
	}
	
?>
<?php include('../include/meta.html'); ?> 
<title>Pay Checking <?php echo $eTitle; ?> </title>
<link href="../style.css" rel="stylesheet" type="text/css" />

<style type="text/css">
input[type='number']{
    width: 75px;
} 
</style>
</head>

<body>
<noscript>
<h1 style="text-align: center;">Javascript Must Be Enabled To Use This Site</h1>
</noscript>
<?php	
	$p = 0;
	$f = 0;
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
	}//end if ((isset($_COOKIE['pensacola'])) || ($homeCity == "pensacola"))  	


?> 
	

<div id="wrapper" class="largeContain">

<h4 style="text-align: center;">This page is for modifying the Global Base Pay.</h4> 


  <div id="Top" class="add-load-contain">


<hr />
<?php
	if(isset($_COOKIE['basePayType'])) {
		if ($_COOKIE['basePayType'] == $rtb) { 
	?>
			<div class="BaseTypeContain">
			<input type="button" class="myButton halfB redB" onclick="location.href='http://<?php echo $_SERVER['SERVER_NAME'] ?>/adminPaySelect.php?rt=1';" value="Round Trip Base" />
			<input type="button" class="myButton halfB blueB" onclick="location.href='http://<?php echo $_SERVER['SERVER_NAME'] ?>/adminPaySelect.php?lh=1';" value="Long Haul Base" />
			</div> <!--end BaseTypeContain-->
	<?php
		} else if ($_COOKIE['basePayType'] == $lhb) {
	?>
			<div class="BaseTypeContain">
			<input type="button" class="myButton halfB blueB" onclick="location.href='http://<?php echo $_SERVER['SERVER_NAME'] ?>/adminPaySelect.php?rt=1';" value="Round Trip Base" />
			<input type="button" class="myButton halfB redB" onclick="location.href='http://<?php echo $_SERVER['SERVER_NAME'] ?>/adminPaySelect.php?lh=1';" value="Long Haul Base" />
			</div> <!--end BaseTypeContain-->
	<?php
		} //end if ($_COOKIE['basePayType'] == $rtb)
	} else {
	?>
			<div class="BaseTypeContain">
			<input type="button" class="myButton halfB blueB" onclick="location.href='http://<?php echo $_SERVER['SERVER_NAME'] ?>/adminPaySelect.php?rt=1';" value="Round Trip Base" />
			<input type="button" class="myButton halfB blueB" onclick="location.href='http://<?php echo $_SERVER['SERVER_NAME'] ?>/adminPaySelect.php?lh=1';" value="Long Haul Base" />
			</div> <!--end BaseTypeContain-->
	<?php
	}//emd if(isset($_COOKIE['basePayType']))
	
	//Begin formatting of lower section based on choices.
	if(isset($_COOKIE['basePayType'])) {
		if ($_COOKIE['basePayType'] == $rtb) { //start of conditional formatting
	
?>
<hr /><br />  

<div id="variablesForm" class="BasePayOuterContain">
	<center>
	<h4>Round Trip Base</h4>
	<form action="../BasePayAdminSubmit.php" method="post">
	
<?php
		
	  $varRowQuery = "SELECT * FROM ".$dbTable2." WHERE 1";
	  $variablesConn = $conn->query($varRowQuery);
		
		while($row = $variablesConn->fetch_assoc()) {
			$miles = $row["miles"];
			$rate = $row["rate"];
			$label = $miles;
			
		
		if ($h = 1) { echo $headder; }
        
		echo "<div class=\"BasePayContain\"> \n <div class=\"BasePayBox floatLeft\">".$miles." Miles</div><!--end BasePayBox--> \n <div class=\"BasePayBox floatLeft\"> \$<input type=\"number\" step=\"any\" class=\"BasePayForm\" name=\"".$label."\" value=\"".$rate."\" /> </div><!--end BasePayBox--> \n </div><!--end BasePayContain--> \n <br /> \n";

		$headder = " ";
		$h = 0;
	
		}//end while($row = $variablesConn->fetch_assoc())
?>	</center>
    <br /><hr />
    <a name="buttons"></a>
    <?php if (isset($_COOKIE["bTest"])) { ?>
        <input type="submit" class="myButton halfB redB" name="settestdatalive" value="Set Test Data Live" />
        <input type="submit" class="myButton halfB blueB" name="updatetestdata" value="Update Test Data" />
        <br /><br /><hr /><br />
        <input type="submit" class="myButton halfB blueB" name="home" value="Home" />
        <input type="submit" class="myButton halfB redB" name="resettestdata" value="Reset Test Data" />
	<?php } else { ?> 
        <input type="submit" class="myButton halfB redB" name="updatedata" value="Update Live Data" />
        <input type="submit" class="myButton halfB blueB" name="updatetestdata" value="Enable Test Mode" /> 
        <br /><br /><hr /><br />
        <input type="submit" class="myButton halfB blueB" name="home" value="Home" />
        <input type="submit" class="myButton halfB redB" name="resettodefault" value="Reset to Default" />
   
	<?php } ?>
    
	</form>


</div><!--end variablesForm-->

<?php
	} else if ($_COOKIE['basePayType'] == $lhb) { //start of long haul conditional formatting
?>
<hr /><br />  

<div id="variablesForm" class="BasePayOuterContain">
	<center>
	<h4>Long Haul Base</h4>
	<form action="../BasePayAdminSubmit.php" method="post">
	
<?php
		
	  $varRowQuery = "SELECT * FROM ".$dbTable3." WHERE 1";
	  $variablesConn = $conn->query($varRowQuery);
		
		while($row = $variablesConn->fetch_assoc()) {
			$miles = $row["miles"];
			$rate = $row["rate"];
			$label = $miles;
			
		
		if ($h = 1) { echo $headder; }
        
		echo "<div class=\"BasePayContain\"> \n <div class=\"BasePayBox floatLeft\">".$miles." Miles</div><!--end BasePayBox--> \n <div class=\"BasePayBox floatLeft\"> \$<input type=\"number\" step=\"any\" class=\"BasePayForm\" name=\"".$label."\" value=\"".$rate."\" /> </div><!--end BasePayBox--> \n </div><!--end BasePayContain--> \n <br /> \n";

		$headder = " ";
		$h = 0;
	
		}//end while($row = $variablesConn->fetch_assoc())
?>	</center>
    <br /><hr />
    <a name="buttons"></a>
    <?php if (isset($_COOKIE["bTest"])) { ?>
        <input type="submit" class="myButton halfB redB" name="settestdatalive" value="Set Test Data Live" />
        <input type="submit" class="myButton halfB blueB" name="updatetestdata" value="Update Test Data" />
        <br /><br /><hr /><br />
        <input type="submit" class="myButton halfB blueB" name="home" value="Home" />
        <input type="submit" class="myButton halfB redB" name="resettestdata" value="Reset Test Data" />
	<?php } else { ?> 
        <input type="submit" class="myButton halfB redB" name="updatedata" value="Update Live Data" />
        <input type="submit" class="myButton halfB blueB" name="updatetestdata" value="Enable Test Mode" /> 
        <br /><br /><hr /><br />
        <input type="submit" class="myButton halfB blueB" name="home" value="Home" />
        <input type="submit" class="myButton halfB redB" name="resettodefault" value="Reset to Default" />
   
	<?php } ?>
    
	</form>


</div><!--end variablesForm-->

<?php
	}//end else if ($longHaulTripActive == 1)
}//end if(isset($_COOKIE['basePayType']))
?>





  </div><!--end Top-->
</div><!--end wrapper-->


<?php	include ('../include/footer.html'); 
	if ((!isset($pro)) || ((isset($pro)) && ($valid == 0))) { ?>
<br />
<div id="adPad" class="clear">&nbsp;</div><!--end adPad-->
<?php }//end if (!isset($pro)) ?>
</body>
</html>
