<?php
	$homeCity = $_GET['c'];
	$beta = $_GET['b'];
	$pcola = 0;
	$eTitle = "";
	if($homeCity == "pensacola") { $pcola = 1; setcookie("pensacola", 1, time()+31536000); $eTitle = "Pensacola"; };
	
	if(isset($_GET['b'])) {
		$beta = $_GET['b'];
		setcookie("beta", 1, time()+31536000); 
	} 

	//if (isset($_COOKIE['pensacola'])) { $pcola = 1; $eTitle = "Pensacola"; };
   include('meta.html'); 
?>
<title>Pay Checking <?php echo $eTitle; ?> </title>
<link href="/style.css" rel="stylesheet" type="text/css" />
</head>

<body>
<noscript>
<h1 style="text-align: center;">Javascript Must Be Enabled To Use This Site</h1>
</noscript>
<br /><br /><br />    		
<div id="wrapper" class="largeContain">
  <div id="Top" class="add-load-contain">
    
    	<div id="login" class="login">
                  <center>
				  <h1 style="color:#F00;">Restricted Access</h1>
				  <h3>Authorization Required</h3>
			  	  <form action="auth.php" method="post">
				  <label>
                  	<p style="text-align: left; padding: 0 0 0 35px">Password:</p>
                  	<input type="password" name="auth" id="auth">
                  </label>
                  <br /><br />
                  <label>
                   	<div class="clear"></div>
                   	<?php //if($pcola == 1) { echo "<input type=\"hidden\" name=\"pcola\" value=\"1\">" }; ?>
                   	<?php //if($beta == 1) { echo "<input type=\"hidden\" name=\"beta\" value=\"1\">" }; ?>
                  <input type="submit" name="done" class="myButton threeQuarterB blueB" value="Submit" />
                  </label> 
				  </form>
                  </center>
      
        </div><!--end login-->
    
  </div><!--end Top-->  
</div><!--end wrapper-->
</body>
</html>