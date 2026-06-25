<?php
 if ($timeP == 3) { $timeO = "3 month"; } else if ($timeP == 6) { $timeO = "6 month"; } else if ($timeP == 12) { $timeO = "1 year"; }
 if ($bcc == 1) { $subject = "New Account Created"; $dynamicbody = $email." Just verified their new Pro account";}
 if ($bcc == 2) { $subject = "Subscription Alert"; $dynamicbody = $email." Just paid for a ".$timeO." subscription";}

			  $headers = "MIME-Version: 1.0"."\r\n";
			  $headers .= "Content-Type: text/html; charset=ISO-8859-1"."\r\n";
			  $headers .= 'From: PayTracker Pro <noreply@paytracker.xyz>' . "\r\n";
			  $subject = "PayTracker Pro - ".$subject."!";
			  
			  $message = '<html><body>';
			  $message .= '<div id="Logo" style="margin: 0 auto; width: 305px; height: 41px;"><img src="http://'.$_SERVER['SERVER_NAME'].'/images/logo.png" alt="Pay Tracker" height="41px" width="216px" /><img src="http://'.$_SERVER['SERVER_NAME'].'/images/pro.png" alt="Pro" height="41px" width="87px" /></div>';
			  $message .= '<h3 style="text-align:center;">Activity on <a href="http://'.$_SERVER['SERVER_NAME'].'/" target="_blank" title="Pay Tracker Pro">PayTracker Pro</a></h3>';
			  $message .= '<h3 style="text-align:center;">'.$dynamicbody.'</h3>';
			  $message .= '<span style="font-size: 11px; font-style:italic;">This is an automated message sent out by '.$_SERVER['SERVER_NAME'].'</span>';			  
			  $message .= '</body></html>';			  
			  //send email
			  mail("mylosol@gmail.com", $subject, $message, $headers);

?>