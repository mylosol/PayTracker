<?
					$headers = "MIME-Version: 1.0"."\r\n";
					$headers .= "Content-Type: text/html; charset=ISO-8859-1"."\r\n";
					$headers .= 'From: PayTracker Pro <noreply@192.168.43.200>' . "\r\n";
					$subject = "PayTracker Pro - Gift!";
					
					$message = '<html><body>';
					$message .= '<div id="Logo" style="margin: 0 auto; width: 305px; height: 41px;"><img src="http://192.168.43.200/images/logo.png" alt="Pay Tracker" height="41px" width="216px" /><img src="http://192.168.43.200/images/pro.png" alt="Pro" height="41px" width="87px" /></div>';
					$message .= '<h3 style="text-align:center;">You have been gifted an additional '.$timeDifference.' towards your paid PayTracker Pro account.</h3>';			  
					$message .= '<h4 style="text-align:center;">Your pro account now is valid through '.$giftDateDisplay.'</h4><br /><br />';			  
					$message .= '<span style="font-size: 11px; font-style:italic;">This is an automated message sent out by 192.168.43.200</span>';			  
					$message .= '</body></html>';			  
					//send email
					mail($giftUser, $subject, $message, $headers);
?>