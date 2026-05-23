<?

setcookie("pensacola", 1, time()-1, '/');

header("Location: http://".$_SERVER['SERVER_NAME']."/?b=1");

?>