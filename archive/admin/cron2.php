<?
include('../include/db.php');
	

	$getNumUsersQuery = $conn->query("SELECT * FROM account;");
	$dateFifteenDaysPast = date('Y-m-d', strtotime("-15 days"));
	$dateThirtyDaysPast = date('Y-m-d', strtotime("-30 days"));
	$curYear = date('Y');
	$today = date('Y-m-d');
	$i = 0;
 
	
	while ($row = $getNumUsersQuery->fetch_assoc()) { 
	$id = $row["id"]; 

//delete old entries
//		$getOldPaidQuery = $conn->query("SELECT * FROM loads".$id." WHERE paid NOT LIKE '%1%' ORDER BY DATE ASC LIMIT 100 ;");
//			if ($result->num_rows > 0) {
//				while ($row = $getOldPaidQuery->fetch_assoc()) {
//					  $date = $row["date"];
//					  $frtl = $row["frtl"];
//					  $dateStr = strtotime($date);
//					  $date = date('Y-m-d', $dateStr);
//						if ($date < $dateFifteenDaysPast) {
//							$conn->query("DELETE FROM loads".$id." WHERE loads".$id.".frtl = ".$frtl." LIMIT 1;");
//						}//end if ($date < $dateFifteenDaysPast)
//				}//end while ($row = $getOldPaidQuery->fetch_assoc())
//			} //end if ($result->num_rows > 0)
//delete old entries
			
//update weekly
	  $getWeeklyTotal = $conn->query("SELECT * FROM totals WHERE id = '".$id."' LIMIT 1;");
		 while ($row = $getWeeklyTotal->fetch_assoc()) { 
			$curWeek = $row["curWeek"];
			$curDiff = $row["curDiff"];
			$yearDiff = $row["yearDiff"];
			$ydy = $row["ydy"];
		  
//		  if ($curWeek < 1000) {
//			  $curWeek = 1000;
//			  $curDiff = 1000;
//		  }//end if ($curWeek < 850)
		  
		  $difference = $curWeek - $curDiff;
		  $yearDiffTotal = $yearDiff + $difference;	
		  
		  $conn->query("UPDATE `totals` SET `preWeek` = \'".$curWeek."\', `preDiff` = \'".$curDiff."\' WHERE `totals`.`id` = ".$id."");
//		  $conn->query("UPDATE totals SET preWeek = ".$curWeek." WHERE totals.id = ".$id." LIMIT 1;");
//		  $conn->query("UPDATE totals SET preDiff = ".$curDiff." WHERE totals.id = ".$id." LIMIT 1;");
		  $conn->query("UPDATE totals SET curWeek = 0.00 WHERE totals.id= ".$id." LIMIT 1;");
		  $conn->query("UPDATE totals SET curDiff = 0.00 WHERE totals.id= ".$id." LIMIT 1;");
		  
		  if ($curYear != $ydy) {
		  $conn->query("UPDATE totals SET yearDiff = 0.00 WHERE totals.id= ".$id." LIMIT 1;");
		  $conn->query("UPDATE totals SET ydy = ".$curYear." WHERE totals.id= ".$id." LIMIT 1;");
		  
		  } else {
		  $conn->query("UPDATE totals SET yearDiff = ".number_format($yearDiffTotal,2)." WHERE totals.id= ".$id." LIMIT 1;");
		  }//end if ($curYear != $ydy)
		 }//end while ($row = $getWeeklyTotal->fetch_assoc())
//update weekly

	}//end while ($row = $getNumUsersQuery->fetch_assoc())


?>