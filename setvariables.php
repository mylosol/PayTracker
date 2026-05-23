<?php                                                                 
$variables = $_POST['tenure'] . "-" . $_POST['shift'] . "-" . $_POST['slip'] . "-0";
setcookie("variables", $variables, time()+31536000);
setcookie("hasreset", 1, time()+31536000);          
include('header/headerRedirect.php');
?>