<?php
	
	$today = date('Y-m-d');
	if (isset($_COOKIE['pro'])) {
		$id = base64_decode($pro);
		$cpqr = $conn->query("SELECT paidDate FROM account WHERE account.id = '".$id."' LIMIT 1");
		$valid = 1;

		if ($cpqr->num_rows > 0) {

			while($row = $cpqr->fetch_assoc()) {
				$paidDate = $row["paidDate"];
				if ($paidDate < $today) {
					$valid = 0;
				}//end if ($paidDate < $today)
			}//end while($row = $cpqr->fetch_assoc())
				
		} else {

			?>
			  <script type="text/javascript">
                alert("Unable to validate account!");
              </script>
              <meta http-equiv="refresh" content="0;URL='http://'.$_SERVER['SERVER_NAME'].'/pro/logout.php'" /> 
            <?php
			exit;	

		}//end if (!$cpqr || !mysql_num_rows($cpqr))
	}//end if (isset($pro))
?>