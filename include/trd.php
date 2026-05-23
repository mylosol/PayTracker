<?php
	setcookie("nl", "1", time()-1);
	setcookie("tr", "1", time()-1);
	header("Location: http://".$_SERVER['SERVER_NAME']."/");

?>