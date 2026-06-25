<?php
if (isset($_POST['reset'])) {
setcookie("active", 1, time()-1, '/');
include('header/headerRedirect.php');
}//end if (isset($_POST['reset']))

if (isset($_POST['compare'])) {
$getVariables = $_COOKIE['variables'];
$vExplode = explode("-", $getVariables);

	if ($vExplode[3] == 0) {
		$compare = 1;
	} 

	if ($vExplode[3] == 1) {
		$compare = 0;
	} 
	
$variables = $vExplode[0] . "-" . $vExplode[1] . "-" . $vExplode[2] . "-" . $compare;
setcookie("variables", $variables, time()+31536000);
include('header/headerRedirect.php');
}//end if (isset($_POST['compare']))
?>