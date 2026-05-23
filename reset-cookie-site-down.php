<?php

setcookie("us", 1, time()-1, '/');
header("Location: http://".$_SERVER['SERVER_NAME']."/site-down.php");

?>