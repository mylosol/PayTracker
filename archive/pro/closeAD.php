<?php
	setcookie("seen", 2, time()+31536000, "/");
	header("Location: http://".$_SERVER['SERVER_NAME']."/");
?>