<?php
$auth = $_POST['auth'];
$authString = "monkey";
$auth = trim($auth);
$auth = strtolower($auth);
$pcola = $_POST['pcola'];
$beta = $_POST['beta'];

if ($auth == $authString) {
	setcookie("auth", 1, time()+31536000);

	if(isset($beta)) {	
		setcookie("beta", 1, time()+31536000);
	}//end if(isset($beta))
	

	if (isset($_COOKIE['pensacola'])) { 
		header("Location: http://".$_SERVER['SERVER_NAME']."/pensacola");
		exit;
 	} else {	
		header("Location: http://".$_SERVER['SERVER_NAME']."/");
		exit;		  		  
 	}//end if(!isset ($pcola))

} else {
	setcookie("auth", 1, time()-1);
?>
			<script type="text/javascript">
			  alert("Access Denied!");
			  history.back();
			</script>
<?php	
	exit;
}//end if ($auth == $authString)
?>