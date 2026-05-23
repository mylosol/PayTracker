<div id="topMenuContain">
  <div id="hbMenu">
      <div class="wrapper">
          <div class="content">
			<?php include('../include/menu.php'); ?>
          </div><!--end content-->
          <div class="parent"><img src="../images/hamburger.png"></div>
      </div><!--end wrapper-->
  </div><!--end hbMenu-->
<div id="logo" class="logo"><img src="../images/logo.png" alt="Pay Tracker" class="logo" /></div>
<?php
	if ((isset($pro)) && ($valid == 1)) {
?>
<div id="prologo" class="pro"><img src="../images/pro.png" alt="Pro" class="pro" /></div>
<?php
	}//end if (isset($pro))
	
	if (isset($beta)) {
?>
<div id="betalogo" class="beta"><img src="../images/Beta-Testing.png" alt="Beta" class="beta" /></div>
<?php
	}//end if (isset$_COOKIE('beta'))
?>
</div><!--end topMenuContain-->