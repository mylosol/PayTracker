<?php 
include('include/db.php');

if(isset($_POST["yes"])) { $confirm = $_POST["yes"]; }
if(isset($_POST["no"]))	{ $deny = $_POST["no"]; }
if(isset($_GET["t"])) { $testData = $_GET["t"]; }
if(isset($_GET["o"])) { $origin = $_GET["o"]; }
if(isset($_POST["origin"])) { $orginPOST = $_POST["origin"]; }

$lhb = "lhb";

	if (isset($_COOKIE['pensacola'])) {
		$eTitle = "Pensacola";
	} else {
		$eTitle = "Panama City";
	}

	if (isset ($_COOKIE['basePayType'])) {
		if ($_COOKIE['basePayType'] == $lhb) {
			$dbTable = "LHPensacolaPayTest";
			$current = "LHPensacolaPayCurrent";
			$default = "LHPensacolaPayDefault";
			$temp = "LHPensacolaPayTemp";
			$test = "LHPensacolaPayTest";
			$title = "Long Haul";
		} else {
			$dbTable = "PensacolaPayTest";
			$current = "PensacolaPayCurrent";
			$default = "PensacolaPayDefault";
			$temp = "PensacolaPayTemp";
			$test = "PensacolaPayTest";
			$title = "Round Trip";
		}
	}

	
	if(isset($origin)) {
		if ($origin == "v") {
			$hiddenOrgin = "<input type=\"hidden\" id=\"origin\" name=\"origin\" value=\"variables\">\n";
			$verbage = "Global Variables";
		}
	
		if ($origin == "b") {
			$hiddenOrgin = "<input type=\"hidden\" id=\"origin\" name=\"origin\" value=\"basepay\">\n";
			$verbage = "Base Pay";
		}
	}//end if(isset($_POST["origin"]))
if (isset($confirm)) {

	if ($orginPOST == "variables") {

		setcookie("vTest", 1, time()-1); 
		$stdlQuery1 = "UPDATE variablesCurrent VC JOIN variablesTemp VT ON VC.variable = VT.variable SET VC.amount = VT.amount WHERE VT.variable = VC.variable;";
		$stdlConn1 = $conn->query($stdlQuery1);
		
		?>
		<script type="text/javascript">
			  alert("Global Variables have been changed!!");
	   		  window.location.replace("http://<?php echo $_SERVER['SERVER_NAME'] ?>/");
			</script>

		<?php
	}//end if ($origin == "v")
	if ($orginPOST == "basepay") {
		setcookie("bTest", 1, time()-1); 
		$stdlQuery1 = "TRUNCATE TABLE ".$current.";";
		$stdlConn1 = $conn->query($stdlQuery1);
		
		$stdlQuery2 = "INSERT INTO ".$current." SELECT * FROM ".$temp.";";
		$stdlConn2 = $conn->query($stdlQuery2);
		?>
			<script type="text/javascript">
			  alert("<?php echo $title; ?> Base Pay has been changed!!");
	   		  window.location.replace("http://<?php echo $_SERVER['SERVER_NAME'] ?>/");
			</script>
		<?php
	}//end if ($origin == "b")
}//end if (isset($confirm))

if (isset($deny)) {
	if ($orginPOST == "variables") {
		header("Location: http://".$_SERVER['SERVER_NAME']."/pro/betaAdmin.php");
	}//end if ($origin == "v") 
	if ($orginPOST == "basepay") {
		header("Location: http://".$_SERVER['SERVER_NAME']."/pro/BasePayAdmin.php");
	}//end if ($origin == "b") 
}//end if (isset($deny))


include('include/meta.html'); ?> 
<title>Pay Checking <?php echo $eTitle; ?> </title>
<link href="style.css" rel="stylesheet" type="text/css" />
<script type="text/javascript" src="js/addInput.js"></script>
</head>

<body>
<noscript>
<h1 style="text-align: center;">Javascript Must Be Enabled To Use This Site</h1>
</noscript>
<?php
		
	$p = 0;
	$f = 0;
	if (isset($_COOKIE['beta'])) { $beta = 1; }
    include ('include/frontMenu.php'); 
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





<center>
<h1>WARNING!</h1>
<?php
	if ($origin == "v") {
		if (isset($testData)) {
			echo "<h4>This will set all the variables in the <u>Test Table</u> to the <u>Live Table</u> and change the Global variables for <b>Everyone</b>!</h4>";
		} else {
			echo "<h4>This will set all the variables in the fields on the <a href=\"pro/betaAdmin.php\" target=\"_self\" title=\"previous page\">previous page</a> to the <u>Live Table</u> and change the Global variables for <b>Everyone</b>!</h4>";
		}//end if (isset($testData))
	}//end if ($origin == "v")
	if ($origin == "b") {
		if (isset($testData)) {
			echo "<h4>This will set all the ".$title." Base Pay in the <u>Test Table</u> to the <u>Live Table</u> and change the ".$title." Base Pay for <b>Everyone</b>!</h4>";
		} else {
			echo "<h4>This will set all the ".$title." Base Pay in the fields on the <a href=\"pro/BasePayAdmin.php\" target=\"_self\" title=\"previous page\">previous page</a> to the <u>Live Table</u> and change the ".$title." Base Pay for <b>Everyone</b>!</h4>";
		}//end if (isset($testData))
	}//end if ($origin == "b")
?>
</center>
	<form action="setdatalive.php" method="post">
	<p style="font-size:14px; font-weight: bold; text-align: center;">Are You Sure You Want To Do This?</p>
    <hr />
    <?php echo $hiddenOrgin; ?>
	<input type="submit" class="myButton wideB redB" name="yes" value="Yes, Change <?php echo $verbage; ?>" />
	<br /><br />
    <input type="submit" class="myButton wideB blueB" name="no" value="No, Cancel!" />
    </form>




  </div><!--end Top-->
</div><!--end wrapper-->


<?php	include ('include/footer.html'); ?>
</body>
</html>
