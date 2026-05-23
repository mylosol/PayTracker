<?php
					$headers = "MIME-Version: 1.0"."\r\n";
					$headers .= "Content-Type: text/html; charset=ISO-8859-1"."\r\n";
					$headers .= 'From: PayTracker Pro <noreply@'.$_SERVER['SERVER_NAME'].'>' . "\r\n";
					$subject = "PayTracker Pro - Account Deleted!";
					
					$message = '<html><body>';
					$message .= '<div id="Logo" style="margin: 0 auto; width: 305px; height: 41px;"><img src="http://'.$_SERVER['SERVER_NAME'].'/images/logo.png" alt="Pay Tracker" height="41px" width="216px" /><img src="http://'.$_SERVER['SERVER_NAME'].'/images/pro.png" alt="Pro" height="41px" width="87px" /></div>';
					$message .= '<h4 style="text-align:center;">You are receiving this email because someone requested the account associated with this e-mail address be deleted.</h4>';			  
					$message .= '<h4 style="text-align:center;">This is a confirmation that the account has been deleted and all accociated information has been removed from the PayTracker database.</h4>';			  
					$message .= '<span style="font-size: 11px; font-style:italic;">This is an automated message sent out by '.$_SERVER['SERVER_NAME'].'</span>';			  
					$message .= '</body></html>';			  
					//send email
					mail($email, $subject, $message, $headers);
?>