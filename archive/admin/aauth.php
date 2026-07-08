<?
$auth = $_POST['auth'];
$authString = "gr3ybird43";
$auth = trim($auth);
$auth = strtolower($auth);

if ($auth == $authString) {
	setcookie("aa", 1, time()+31536000);
	header('Location: http://192.168.43.200/admin/');
} else {
	setcookie("aa", 1, time()-1);
?>
			<script type="text/javascript">
			  alert("Access Denied!");
			  history.back();
			</script>
<?	
	exit;
}//end if ($auth == $authString)
?>