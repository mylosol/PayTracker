<?php
$_SESSION['originalref2'] = $_SERVER['HTTP_REFERER'];
$purl = $_SESSION['originalref2'];
echo "Your URL is: http://" . $_SERVER['SERVER_NAME'];
?>