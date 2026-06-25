<?php


if (isset($_COOKIE['pro'])) {
$pro = $_COOKIE['pro'];
?>
<style>
#hbMenu .wrapper:active .content, 
#hbMenu .content:hover {
	<?php if (!isset($_COOKIE['beta'])) { echo "height: 180px;"; } else { echo "height: 240px;"; }?>
}
</style>
<ul id="menu">
    <a href="/" title="Checking"><li>Home</li></a>
    <a href="/pro/account.php" title="My Account"><li>My Account</li></a>
    <a href="/pro/tracking.php" title="Overview"><li>Overview</li></a>
    <a href="/pro/reconcile.php" title="Reconcile"><li>Reconcile</li></a>
    <a href="/pro/help.php" title="Help / F.A.Q."><li>Help / F.A.Q.</li></a>
    <a href="/pro/logout.php" title="Log Out"><li>Log Out</li></a>
	<?php 
		if (isset($_COOKIE['beta'])) { echo "<a href=\"/pro/betaAdmin.php\" title=\"Adjust Variables\"><li><span style=\"color:red;\">!!</span>Variables<span style=\"color:red;\">!!</span></li></a>"; }
		if (isset($_COOKIE['beta'])) { echo "<a href=\"/pro/BasePayAdmin.php\" title=\"Adjust Base Pay\"><li><span style=\"color:red;\">!!</span>Base Pay<span style=\"color:red;\">!!</span></li></a>"; }
	?>
</ul>
<?php
} else {
?>
<style>
#hbMenu .wrapper:active .content, 
#hbMenu .content:hover {/*30px per menu item*/
	<?php if (!isset($_COOKIE['beta'])) { echo "height: 40px;"; } else { echo "height: 100px;"; }?>
}
</style>
<ul id="menu">
    <!--<a href="/pro/index.php" title="Get Pro"><li>Get Pro</li></a>-->
    <a href="/pro/login.php" title="Log In"><li>Log In</li></a>
	<?php 
		if (isset($_COOKIE['beta'])) { echo "<a href=\"/pro/betaAdmin.php\" title=\"Adjust Variables\"><li><span style=\"color:red;\">!!</span>Variables<span style=\"color:red;\">!!</span></li></a>"; }
		if (isset($_COOKIE['beta'])) { echo "<a href=\"/pro/BasePayAdmin.php\" title=\"Adjust Base Pay\"><li><span style=\"color:red;\">!!</span>Base Pay<span style=\"color:red;\">!!</span></li></a>"; }
	?>
</ul>
<?php
}//end if (isset($pro)) 
?>

