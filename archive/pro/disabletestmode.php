<?php 

	$origin = $_GET["o"];
	
	if ($origin == "v") {
		header("Location: http://".$_SERVER['SERVER_NAME']."/betaAdminSubmit.php?dtm=1");
	}	
	if ($origin == "b") {
		header("Location: http://".$_SERVER['SERVER_NAME']."/BasePayAdminSubmit.php?dtm=1");
	}
?>
