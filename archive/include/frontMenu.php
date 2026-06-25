<div class="menu-pointer">Menu</div>
<div id="topMenuContain">
  <div id="hbMenu">
      <div class="wrapper">
          <div class="content">
			<?php include('include/menu.php'); ?>
          </div><!--end content-->
          <div class="parent"><img src="images/hamburger.png"></div>
      </div><!--end wrapper-->
  </div><!--end hbMenu-->
<div id="logo" class="logo"><a href="http://'.$_SERVER['SERVER_NAME'].'/"><img src="images/logo.png" alt="Pay Tracker" class="logo" /></a></div>
<?php
	if ((isset($pro)) && ($valid == 1)) {
?>
<div id="prologo" class="pro"><img src="images/pro.png" alt="Pro" class="pro" /></div>
<?php
	}//end if (isset($pro))
?>
</div><!--end topMenuContain-->
<?php 
	if (isset($_COOKIE["vTest"])) { ?>
		<div class="testON"><h4 style="text-align: center;">USING TEST VARIABLES - NOT LIVE DATA!</h4><span class="closeTest"><b><a href="/pro/disabletestmode.php?o=v"><img src="../images/close.png"></a></b></span></div>
<?php 	} //end if (isset($_COOKIE["vTest"]))

	if (isset($_COOKIE["bTest"])) { ?>
		<div class="testON"><h4 style="text-align: center;">USING TEST BASE PAY - NOT LIVE DATA!</h4><span class="closeTest"><b><a href="/pro/disabletestmode.php?o=b"><img src="../images/close.png"></a></b></span></div>
<?php 	} //end if (isset($_COOKIE["vTest"]))
include('include/annihilateIcon.php');
?>