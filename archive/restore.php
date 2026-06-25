<?php

if($_GET['r'] > 0) {
	$sr = $_GET['r'];
	setcookie("spl", $sr, time()-1, '/');
}//end if($_GET['r'] > 0)

if($_GET['b'] > 0) {
	$bh = $_GET['b'];
	setcookie("bck", $bh, time()-1, '/');
}//end if($_GET['r'] > 0)
	
include('header/headerRedirect.php');
?>