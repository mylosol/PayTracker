<?php
include ('../include/db.php');
			//$valilation = $_GET['cm'];
			//$getID = (int)$valilation;
			//$id = $getID;
			$pro = $_COOKIE['pro'];
			$id = base64_decode($pro);
			$getDateQuery = $conn->query("SELECT * FROM `account` WHERE `account`.`id` = ".$id." LIMIT 1;");
			  while ($row = $getDateQuery->fetch_assoc()) { 
					$date = $row["paidDate"];
					$buyerEmail = $row["user"];
					$ulk = $row["ulk"];
			  }//end while ($row = $getDateQuery->fetch_assoc())
			if ($ulk < 1) { $conn->query("UPDATE `account` SET `ulk` =  '1' WHERE  `account`.`id` = ".$id." LIMIT 1 ;"); }
			$date = date_create($date);
			date_add($date, date_interval_create_from_date_string('365 days'));
			$newDate = date_format($date, 'Y-m-d');
			$newDateReadable = date_format($date, 'M jS, Y');
			$conn->query("UPDATE `account` SET `paidDate` =  '".$newDate."' WHERE  `account`.`id` = ".$id." LIMIT 1 ;");
			$email = $buyerEmail;
			$headers = "MIME-Version: 1.0"."\r\n";
			$headers .= "Content-Type: text/html; charset=ISO-8859-1"."\r\n";
			$headers .= 'From: PayTracker Pro <noreply@'.$_SERVER['SERVER_NAME'].'>' . "\r\n";
			$subject = "PayTracker Pro - Confirmed!";
			
			$message = '<html><body>';
			$message .= '<div id="Logo" style="margin: 0 auto; width: 305px; height: 41px;"><img src="http://'.$_SERVER['SERVER_NAME'].'/images/logo.png" alt="Pay Tracker" height="41px" width="216px" /><img src="http://'.$_SERVER['SERVER_NAME'].'/images/pro.png" alt="Pro" height="41px" width="87px" /></div>';
			$message .= '<h2>Thank You for your subscription!  You are now paid through '.$newDateReadable.'</h2>';	
			$message .= '<br /><br /><span style="font-size: 11px; font-style:italic;">This is an automated message sent out by '.$_SERVER['SERVER_NAME'].'</span>';			  
			$message .= '</body></html>';			  
			//send email
			mail($email, $subject, $message, $headers);
			header("Location: http://".$_SERVER['SERVER_NAME']."/pro/thankyou.php");
			
$bcc = "2";
$timeP = "12";
include ('../include/bccEmail.php');

?>