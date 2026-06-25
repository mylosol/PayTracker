<?php 
include('../include/db.php');
include ('../include/variables.php');

	if (isset($_COOKIE['pensacola'])) {
		$eTitle = "Pensacola";
	} else {
		$eTitle = "Panama City";
	}

	if(isset($_GET["rs"])) { 
		$resetAlert = $_GET["rs"]; 
		if ($resetAlert == 1) {
			?>
			<script type="text/javascript">
			  alert("Variables have been reset to Default Values!!");
			  window.location.replace("http://".$_SERVER['SERVER_NAME']."/pro/betaAdmin.php");
			</script>
			<?php
		} //end if ($resetAlert == 1)
		
		if ($resetAlert == 2) {
			?>
			<script type="text/javascript">
			  alert("TEST Variables have been reset to Current Values!!");
			  window.location.replace("http://".$_SERVER['SERVER_NAME']." /pro/betaAdmin.php");
			</script>
			<?php
		} //end if ($resetAlert == 2)
	}//end if(isset($_GET["rs"]))
	
?>
<?php include('../include/meta.html'); ?> 
<title>Pay Checking <?php echo $eTitle; ?> </title>
<link href="../style.css" rel="stylesheet" type="text/css" />
<script type="text/javascript" src="js/addInput.js"></script>
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

<h4 style="text-align: center;">This page is for modifying the variables that make up the various raises and boosts.</h4> 
<hr width="50%" />
<p style="font-size: 12px; text-align: center;">Items with a ($) are in Dollars and Cents, Items with a (%) are a decimal representation of the percentage boost.</p>
<hr width="50%" />
<p style="font-size: 12px; text-align: center;">(%) values are used against a static Base Pay.</p>

  <div id="Top" class="add-load-contain">


<hr /><br />

<div id="variablesForm" class="variablesContain">

	<form action="../betaAdminSubmit.php" method="post">
	
<?php
	  $newCSS = "";
	  $h = 0;
	  $varRowQuery = "SELECT * FROM ".$dbTable." WHERE 1";
	  $variablesConn = $conn->query($varRowQuery);
		
		while($row = $variablesConn->fetch_assoc()) {
			 $title = $row["variable"];
			 $amount = $row["amount"];
			 
			 $titleExplode = explode("_", $title);
			 	
			if(isset($titleExplode[1])) {
				if ($titleExplode[1] == "mt") { $label = "Empty Miles (\$)"; }
				if ($titleExplode[1] == "wk") { $label = "Weekend Boost (%)"; }
				if ($titleExplode[1] == "newBump") { $label = "Tenure Boost (%)"; }
				if ($titleExplode[1] == "night") { $label = "Night Shift (%)"; }
				if ($titleExplode[1] == "pay") { $label = "Trainer Pay (\$)"; }
			}//end if(isset($titleExplode[1]))
				if ($title == "demurrage" || $title == "breakdown") { $label = "CPM (\$)"; $newCSS = "style = \"width: 135px;\""; } else { $newCSS = ""; }
				if ($title == "raise") { $label = "Global Raise (%)"; }
				
			 
		if ($title == "raise") { $headder = "<h3 class=\"varableheader\">Raise</h3> \n"; $h = 1;}
		if ($title == "trainer_pay") { $headder = "<h3 class=\"varableheader\">Trainer Pay</h3> \n"; $h = 1;}
		if ($title == "demurrage") { $headder = "<h3 class=\"varableheader\">Demurrage</h3> \n"; $h = 1;}
		if ($title == "breakdown") { $headder = "<h3 class=\"varableheader\">Breakdown</h3> \n"; $h = 1;}
		if ($title == "6_mt") { $headder = "<h3 class=\"varableheader\">0 to 6 Months</h3> \n"; $h = 1;}
		if ($title == "12_mt") { $headder = "<h3 class=\"varableheader\">7 to 12 Months</h3> \n"; $h = 1;}
		if ($title == "24_mt") { $headder = "<h3 class=\"varableheader\">13 to 24 Months</h3> \n"; $h = 1;}
		if ($title == "60_mt") { $headder = "<h3 class=\"varableheader\">25 to 60 Months</h3> \n"; $h = 1;}
		if ($title == "108_mt") { $headder = "<h3 class=\"varableheader\">61 to 108 Months</h3> \n"; $h = 1;}
		if ($title == "168_mt") { $headder = "<h3 class=\"varableheader\">109 to 168 Months</h3> \n"; $h = 1;}
		if ($title == "max_mt") { $headder = "<h3 class=\"varableheader\">169+ Months</h3> \n"; $h = 1;}
		
		if ($h = 1) { echo $headder; }
        
		echo "<div class=\"betaAdminContain\"> \n <div class=\"betaAdminBox floatLeft\">".$label."</div><!--end betaAdminBox--> \n <div class=\"betaAdminBox floatRight\"> <input type=\"number\" step=\"any\" class=\"betaAdminForm\" name=\"".$title."\" value=\"".$amount."\" ".$newCSS." /> </div><!--end betaAdminBox--> \n </div><!--end betaAdminContain--> \n <br /> \n";

		$headder = " ";
		$h = 0;
		$label = " ";	
		}//end while($row = $variablesConn->fetch_assoc())
?>
    <br /><hr />
    <a name="buttons"></a>
    <?php if (isset($_COOKIE["vTest"])) { ?>
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






  </div><!--end Top-->
</div><!--end wrapper-->


<?php	include ('../include/footer.html'); 
	if ((!isset($pro)) || ((isset($pro)) && ($valid == 0))) { ?>
<br />
<div id="adPad" class="clear">&nbsp;</div><!--end adPad-->
<?php }//end if (!isset($pro)) ?>
</body>
</html>
