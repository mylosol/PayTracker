<?php 
include ('../include/db.php');
include ('../include/cookiecheckMP.php');
$nonceValidation = $_COOKIE['atv'];
setcookie("atv", 1, time()-1, '/');
$time = $_GET['t'];
$getPayNonce = $conn->query("SELECT `paynonce` FROM `paytracking`.`account` WHERE `account`.`id` = ".$id." LIMIT 1;"); 
$conn->query("UPDATE `account` SET `paynonce` = NULL WHERE `account`.`id` = ".$id." LIMIT 1 ;");
		
		if ($getPayNonce->num_rows > 0)  {

			$paynonceDB = $getPayNonce["paynonce"];
			
			  if ($paynonceDB == $nonceValidation) {
				$today = date('Y-m-d');
				if ($paidDate < $today) {
					$date = $today;
				} else {
					$date = $paidDate;
				}//end if ($date < $today)
				$date = date_create($date);
					if ($time == 12) { date_add($date, date_interval_create_from_date_string('365 days')); $time = "12 Months";} //end if ($time == 12)
					if ($time == 6) { date_add($date, date_interval_create_from_date_string('6 months')); $time = "6 Months";} //end if ($time == 6)
					if ($time == 3) { date_add($date, date_interval_create_from_date_string('3 months')); $time = "3 Months"; } //end if ($time == 3)
				$newDate = date_format($date, 'Y-m-d');
				$pd = date("F jS, Y", strtotime($newDate));
				$conn->query("UPDATE `account` SET `paidDate` =  '".$newDate."' WHERE  `account`.`id` = ".$id." LIMIT 1 ;");
				$getEmail = $conn->query("SELECT `user` FROM `account` WHERE  `account`.`id` = ".$id." LIMIT 1 ;");
				$email = $getEmail["user"];
				$headers = "MIME-Version: 1.0"."\r\n";
				$headers .= "Content-Type: text/html; charset=ISO-8859-1"."\r\n";
				$headers .= 'From: PayTracker Pro <noreply@'.$_SERVER['SERVER_NAME'].'>' . "\r\n";
				$subject = "PayTracker Pro - Thank You!";
				
				$message = '<html><body>';
				$message .= '<div id="Logo" style="margin: 0 auto; width: 305px; height: 41px;"><img src="http://'.$_SERVER['SERVER_NAME'].'/images/logo.png" alt="Pay Tracker" height="41px" width="216px" /><img src="http://'.$_SERVER['SERVER_NAME'].'/images/pro.png" alt="Pro" height="41px" width="87px" /></div>';
				$message .= '<h3 style="text-align:center;">Thank you for your subscription!</h3>';			  
				$message .= '<h4 style="text-align:center;">Your continued support helps keep this site up and running by offsetting the costs of development and maintenance.</h4><h4 style="text-align:center;">Thank you for helping me keep this site up and running!</h4>';
				$message .= '<h4 style="text-align:center;">You have added '.$time.' to your <a href="http://'.$_SERVER['SERVER_NAME'].'/pro/account.php" target="_blank" title="Pay Tracker Pro">PayTracker Pro</a> account, your account is valid through '.$pd.'.</h4>';	  
				$message .= '<span style="font-size: 11px; font-style:italic;">This is an automated message sent out by '.$_SERVER['SERVER_NAME'].'</span>';			  
				$message .= '</body></html>';			  
				//send email
				mail($email, $subject, $message, $headers);
				header('Location: http://'.$_SERVER['SERVER_NAME'].'/pro/complete.php');
			  } else {
				header('Location: http://'.$_SERVER['SERVER_NAME'].'/pro/buyerror.php'); //ERROR - Time Already Added!
			  }//end if ($paynonceDB == $nonceValidation)

		} else {
			//ERROR - Unable to retrieve data!			  
		}// end if ($getPayNonce->num_rows > 0)
?>