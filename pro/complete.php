<?php
$redirect = $_COOKIE['paid'];

	if (isset($redirect)) {
		header('Location: http://'.$_SERVER['SERVER_NAME'].'/pro/account.php');
	} else {
		header('Location: http://'.$_SERVER['SERVER_NAME'].'/pro/thankyou.php');
	}//end if (isset($redirect)

?>