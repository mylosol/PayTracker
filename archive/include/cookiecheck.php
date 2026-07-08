<?php
	if (isset($_COOKIE['pro'])) { $pro = $_COOKIE['pro']; }
	$today = date('Y-m-d');
	if (isset($pro)) {
		$id = base64_decode($pro);
		$checkPaidQuery = $conn->query("SELECT paidDate FROM account WHERE account.id = '".$id."' LIMIT 1");
		$valid = 1;
		if ($checkPaidQuery->num_rows < 1) {
			
			?>
			  <script type="text/javascript">
                alert("Unable to validate account!");
              </script>
              <meta http-equiv="refresh" content="0;URL='http://'.$_SERVER['SERVER_NAME'].'/pro/logout.php'" /> 
            <?php
			exit;	
							
		} else {
						
			while ($row = $checkPaidQuery->fetch_assoc()) { $paidDate = $row["paidDate"]; }
			if ($paidDate < $today) {
				header('Location: http://'.$_SERVER['SERVER_NAME'].'/pro/buy.php?e=1');
			}//end if ($paidDate < $today)

		}//end if ($result->num_rows > 0)
	} else {
		header('Location: http://'.$_SERVER['SERVER_NAME'].'/pro/login.php');
	}//end if (isset($pro))
?>