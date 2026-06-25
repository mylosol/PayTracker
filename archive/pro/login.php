<?php 
include ('../include/db.php');
include ('../include/cookiecheckR.php');
include('../include/meta.html'); ?> 
<title>Pay Tracking Portal</title>
<link href="../style.css" rel="stylesheet" type="text/css" />
</head>

<body>
<noscript>
<h1 style="text-align: center;">Javascript Must Be Enabled To Use This Site</h1>
</noscript>
<?php include ('../include/topMenu.php'); ?>
    		
<div id="wrapper" class="largeContain">
  <div id="Top" class="add-load-contain">
    
    	<div id="login" class="login">
        <?php
			if (isset($_GET['c'])) { $create = $_GET['c']; }
			if (isset($_GET['d'])) { $duplicate = $_GET['d']; }
						
			if (isset($create)) {
				  ?>
				  <script type="text/javascript" src="js/passcheck.js"></script>
				  <form name="Form" method="post" class="loginForm" onsubmit="return validateForm()" action="create.php">
                  
				  <h1>Create Account</h1>
				  <?php if (isset($duplicate)) { echo "<p class=\"duplicate\" id=\"duplicate\">Email Address Already in Use.<br />Please Chose Another or <a href=\"login.php\" title=\"Login\">Login</a></p>"; } ?>
				  <label>
                    <span>E-mail Address:</span><br />
                    <input name="email" type="email" id="email" onChange="checkMail(); return false;">
                    <span id="filled"></span>
                  </label> 
				  
				  <label>
                  	<span>Password: <i>(5-20 characters)</i></span><br />
                  	<input type="password" name="pass1" id="pass1" onChange="firstPass(); return false;">
                    <span id="passcheck"></span>
                  </label>
				  
				  <label>
                  	<span>Confirm Password:</span><br />
                    <input type="password" name="pass2" id="pass2" onkeyup="checkPass(); return false;">
                    <br /><span id="confirmMessage" class="confirmMessage"></span><br />
                  </label>
                  <label>
                  	<input type="checkbox" name="agree" id="tosAgree" onChange="agreed('tosAgree', 'agreedDiv')">&nbsp;&nbsp;
                  	<span>I have read and agree to the <br /><a href="http://'.$_SERVER['SERVER_NAME'].'/pro/help.php#tos" title="Terms of Service">Terms of Service</a> and <a href="http://'.$_SERVER['SERVER_NAME'].'/pro/help.php#privacy" title="Privacy Policy">Privacy Policy</a></span><br />
                  </label>
                  <label>
                    <span>&nbsp;</span> 
                   	<div class="clear"></div>
                  <div id="agreedDiv"><h4>You Must Agree to Register!</h4></div>
                  </label> 
				  </form>
                  <br />
				  <?php
			} else {
				  ?>	
				  <form method="post" class="loginForm" action="lverify.php">
				  <h1>Login</h1>
				  <?php if (isset($duplicate)) { echo "<p class=\"duplicate\" id=\"duplicate\">Email Address or Password Do Not Match.<br />Please Use Another or <a href=\"login.php?c=1\" title=\"Create Account\">Create Account</a>.</p>"; } ?>
				  <label>
                    <span>E-mail Address:</span><br />
                    <input name="email" type="email" id="email">
                  </label> 
                  
				  <label>
                  	<span>Password:</span><br />
                  	<input type="password" name="password" id="password" >
                  </label>
                  <label>
                    <span>&nbsp;</span> 
                   	<div class="clear"></div>
                  <input type="submit" name="done" class="myButton threeQuarterB blueB" value="Submit" />
                  </label> 
                  
				  </form>
                  <p><a href="login.php?c=1" target="_self" title="Create an Account">Create an Account</a>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;|&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
                  <a href="forgot.php" target="_self" title="Forgot Password">Forgot Password</a></p>
				  <?php
			}//end if (isset($create))
		
		
		?>        
        </div><!--end login-->
    
  </div><!--end Top-->  
</div><!--end wrapper-->

<?php include('../include/footer.html'); ?>
</body>
</html>