<?php 
include('include/db.php');
include('include/cookiecheckMP.php');
include ('include/variables.php');	  
	
	
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














  </div><!--end Top-->
</div><!--end wrapper-->


<?php	include ('include/footer.html'); 
	if ((!isset($pro)) || ((isset($pro)) && ($valid == 0))) { ?>
<br />
<div id="adPad" class="clear">&nbsp;</div><!--end adPad-->
<div id="bottomAd" class="adLock">
<center>
<?php 	
include ('include/ads/googlead.html'); 
?>
</center>
</div><!--end bottomAd-->
<?php }//end if (!isset($pro)) ?>
</body>
</html>
