<?php include ('../include/db.php'); 
   	
	$email = $_POST['email'];
	$password = $_POST['password'];
	$today = date('Y-m-d');
	$getIDQuery = $conn->query("SELECT * FROM account WHERE account.user = '".$email."' LIMIT 1;") or die($conn->error);

		if ($getIDQuery->num_rows > 0) {

			while ($row = $getIDQuery->fetch_assoc()) { $id = $row["id"]; $accountValid = $row["accountValid"]; }

		} else {

			header('Location: http://'.$_SERVER['SERVER_NAME'].'/pro/login.php?d=1');
			exit;

		}//end if ($getIDQuery->num_rows > 0)
		
		if ($accountValid < 1) {
			$activation = base64_encode($email);
			$valilation = $id * 69;
			header('Location: http://'.$_SERVER['SERVER_NAME'].'/pro/resend.php?a='.$activation.'&v='.$valilation.'&na=1');
		} else {
		  $salt = $id * 43;
		  $password = md5($password);
		  $password = $salt . $password;
		  $password = md5($password);
	  
		  $checkPassQuery = $conn->query("SELECT av FROM at WHERE at.id = '".$id."' LIMIT 1;");
  
		  if ($checkPassQuery->num_rows < 1) {
			  header('Location: http://'.$_SERVER['SERVER_NAME'].'/pro/login.php?d=1');
			  exit;
		  } else {
			  
		 while ($row = $checkPassQuery->fetch_assoc()) { $checkPass = $row["av"]; }
			  if ($checkPass != $password) {
				  header('Location: http://'.$_SERVER['SERVER_NAME'].'/pro/login.php?d=1');
				  exit;
			  } else {
				  $cookieValue = base64_encode($id);
				  setcookie("pro", $cookieValue, time()+31536000, '/');
				  $checkPaidQuery = $conn->query("SELECT paidDate FROM account WHERE account.id = '".$id."' LIMIT 1");
				  while ($row = $checkPaidQuery->fetch_assoc()) { $paidDate = $row["paidDate"]; }
				  if ($paidDate > $today) {
					  header('Location: http://'.$_SERVER['SERVER_NAME'].'/');
				  } else {
					  header('Location: http://'.$_SERVER['SERVER_NAME'].'/pro/buy.php?e=1');
				  }//end if ($paidDate > $today)
			  
			  }//end if ($checkPass != $password)
		  }//end if ($getIDQuery->num_rows < 1)
		}//end if ($accountValid < 1)

?> 
