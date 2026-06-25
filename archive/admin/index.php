<? 
$auth = $_COOKIE['aa'];
if (!isset($auth)) {
	include ('aa.php');
} else {
	include ('../include/db.php');
	
	if (isset($_POST['mDone'])) { $updateMiles = $_POST['mDone']; }
	if (isset($_POST['mCheck'])) { $checkMiles = $_POST['mCheck']; }
	if (isset($_POST['cDone'])) { $addCity = $_POST['cDone']; }
	if (isset($_POST['tDone'])) { $addTime = $_POST['tDone']; }
	if (isset($_POST['aDone'])) { $announcement = $_POST['aDone']; }
	if (isset($_POST['aCancel'])) { $cancelAnnouncement = $_POST['aCancel']; }
	$addUpdate = "";
	$update = "";
	$timeUpdate = "";
	$annUpdate = "";
	$city = $conn->query("SELECT city FROM largeMiles ORDER BY city ASC;");
	$cityNum = $city->num_rows;
	$today = date_create();
	
	$p = 0;
	$f = 0;
	$g = 0;

	
	if (isset($addTime)) {
		$giftDate = $_POST['date'];
		$giftUser = $_POST['user-id'];
		$userId = $conn->query("SELECT * FROM `account` WHERE `user` = '".$giftUser."' LIMIT 1;");

		if (isset($giftUser)) {
			
			while ($row = $userId->fetch_assoc()) { $thePaidDate = $row["paidDate"]; }
			$thePaidDate = date_create($thePaidDate);
			$giftedTime =  date_create($giftDate);
			$giftDateDisplay = date('M jS, Y', strtotime($giftDate));
			$interval = date_diff($thePaidDate, $giftedTime);
			$years = $interval->format('%y'); 
			$months = $interval->format('%m'); 
			$days = $interval->format('%d'); 
				
				if ($years > 1) { $pluralYear = 's'; } else { $pluralYear = ''; }
				if ($months > 1) { $pluralMonth = 's'; $mand = ' and '; } else { $pluralMonth =''; $mand = ''; }
				if ($days > 1) { $pluralDay = 's'; } else { $pluralDay = ''; }
				if ($years != 0) { $displayYears = $years.' year'.$pluralYear.' '; } else { $displayYears = ''; }
				if ($months != 0) { $displayMonths = $months.' month'.$pluralMonth.' '; } else { $displayMonths = ''; }
				if ($days != 0) { $displayDays = $mand.$days.' day'.$pluralDay.' '; } else { $displayDays = ''; }
				
			$timeDifference = $displayYears.$displayMonths.$displayDays;
			$conn->query("UPDATE `account` SET `paidDate` = '".$giftDate."' WHERE `account`.`id` = ".$giftUser.";") or die ('<center><h1>Unable to Update paid date</h1></center>');
			include ('giftEmail.php');
			$timeUpdate = "An additional ".$timeDifference." has been added to ".$giftUser.".<br />Account now is valid through ".$giftDateDisplay.".";
		} else {
			echo '<center><h1>Unable to get user ID</h1>
				  <h3>Something Has Gone <span style="color:#F00;">Catastrophically</span> Wrong!</h3>
				  <h3>Wait a bit and try again</h3>
				  </center>';
				  exit;
		}//end if ($userId->num_rows > 0)
	}//end if (isset($addTime))
	
	if (isset($announcement)) {
		$title = $_POST['title'];
		$content = $_POST['content'];
		$getAllUsers = $conn->query("SELECT id FROM account;");
		$getNumUsers = $getAllUsers->num_rows;
			while ($row = $getAllUsers->fetch_assoc()) { $id = $row["id"]; 
			$conn->query("UPDATE `account` SET `announce` = '1' WHERE `account`.`id` = ".$id." LIMIT 1 ;") or die ('<center><h1>Unable to set announcement as unviewed</h1></center>');
			}//end while ($row = $getAllUsers->fetch_assoc()
			$conn->query("UPDATE `announce` SET  `title` =  '".$title."', `content` =  '".$content."' WHERE `announce`.`id` = 1 LIMIT 1 ;") or die ('<center><h1>Unable to set announcement</h1></center>');
			$annUpdate = "Announcemnet Set!";
	}//end if (isset($announcement))
	
	if (isset($cancelAnnouncement)) {
		$getAllUsers = $conn->query("SELECT id FROM account;");
		$getNumUsers = $getAllUsers->num_rows;
			while ($row = $getAllUsers->fetch_assoc()) { $id = $row["id"]; 
			$conn->query("UPDATE `account` SET `announce` = '0' WHERE `account`.`id` = ".$id." LIMIT 1 ;") or die ('<center><h1>Unable to set announcement as unviewed</h1></center>');
			}//end while ($row = $getAllUsers->fetch_assoc())
			$annUpdate = "Announcemnet Turned Off!";
	}//end if (isset($cancelAnnouncement)) 
	
	
	include('../include/meta.html'); ?> 
    <title>Pay Tracking Admin</title>
    <link href="../style.css" rel="stylesheet" type="text/css" />
	<script src="http://code.jquery.com/jquery-latest.min.js"></script>
    </head>
    
    <body>
    <noscript>
    <h1 style="text-align: center;">Javascript Must Be Enabled To Use This Site</h1>
    </noscript>
		<? include ('../include/topMenuPro.php'); ?>
                
				<div id="addTime" class="largeContain">
						<center><h1><? echo $timeUpdate; ?></h1>
				  <div id="timecontain" class="add-load-contain">
						<h3>Gift Time</h3>
                <? 
				$getUsersQuery = $conn->query("SELECT * FROM `account`;");
				?>
                
                <form method="post">
                User: <select name="user-id" onChange="getData(this)">
                  <? while ($row = $getUsersQuery->fetch_assoc()) { 
						$u = $row["user"];
						$userID = $row["id"]; 
						$curPaidDate = $row["paidDate"];
						$curPaidDate = date('n/j/Y', strtotime($curPaidDate));
						print "<option value=\"".$userID."\">".$u." | ".$curPaidDate."</option>\n";
					  }//end while ($row = $getUsersQuery->fetch_assoc())
                 ?>
                </select><br /><br />
                Date: <input type="date" name="date" size="10" />
				<br /><br />
                <input type="submit" name="tDone" class="myButton halfB blueB" value="Add Time" />
                </form>


				  </div><!--end timecontain-->
				</div><!--end addTime-->

				<div id="announcement" class="largeContain">
						<center><h1><? echo $annUpdate; ?></h1>
				  <div id="anncontain" class="add-load-contain">
						<h3>Announcement</h3>
                <? 
				$getUsersQuery = $conn->query("SELECT * FROM `account`;");
				$userNum = $getUsersQuery->num_rows;
				?>
                
                <form method="post" style="text-align: left;">
				<script>
                  $(document).ready(function() {
                      var text_max = 140;
                      $('#title_feedback').html(text_max + ' characters remaining');
                  
                      $('#title').keyup(function() {
                          var text_length = $('#title').val().length;
                          var text_remaining = text_max - text_length;
                  
                          $('#title_feedback').html(text_remaining + ' characters remaining');
                      });
                      var textarea_max = 1000;
                      $('#textarea_feedback').html(textarea_max + ' characters remaining');
                  
                      $('#textarea').keyup(function() {
                          var text_length = $('#textarea').val().length;
                          var text_remaining = textarea_max - text_length;
                  
                          $('#textarea_feedback').html(text_remaining + ' characters remaining');
                      });
                  });
                </script>
                Title:<br /><input type="text" id="title" name="title" />
                <div id="title_feedback" style="font-size:10px"></div>
                Content:<br /><textarea id="textarea" name="content" cols="29" rows="3" maxlength="1000"></textarea>
                <div id="textarea_feedback" style="font-size:10px"></div><br /><br />
                <input type="submit" name="aDone" class="myButton halfB blueB" value="Set Announcement" />
				<input type="submit" name="aCancel" class="myButton quarterB greyB" value="Turn Off" />
                </form>


				  </div><!--end anncontain-->
				</div><!--end announcement-->
                
				<div id="compose_email" class="largeContain">
				  <div id="compose_email_contain" class="add-load-contain">

						

				  </div><!--end milescontain-->
				</div><!--end miles-->

    </body>
    </html>
		<?		
}//end if (!isset($auth))
?>