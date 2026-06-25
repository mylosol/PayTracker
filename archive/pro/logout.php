<?php
setcookie("pro", 1, time()-1, '/');
	
header('Location: http://'.$_SERVER['SERVER_NAME'].'/');
?>