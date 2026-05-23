<?php
//send out expiring paid account emails
	$getNumUsersQuery = $conn->query("SELECT * FROM account;");

	while ($row = $getNumUsersQuery->fetch_assoc()) {
	  $userEmail = $row["user"];
	  $getPaidDate = $row["paidDate"];
	  $userActive = $row["accountValid"];
	  $joinDate = $row["joinDate"];
	  $id = $row["id"];
	
	  if ($userActive > 0) {
		  $paidDate = date('Y-m-d', strtotime($getPaidDate));
		  $paidDateDisplay = date('M jS, Y', strtotime($paidDate));
		  $dateFuture = date('Y-m-d', strtotime("+15 days"));
  //		$todayCreate = date_create();
  //		$paidCreate = date_create($paidDate);
  //		$interval = date_diff($todayCreate, $paidCreate);
  //		$days = $interval->format('%d'); 
		  if ($paidDate < $dateFuture) { //Within 30 Days
		  	if ($today < $paidDate) { 
			  include('expireEmail.php');
			  echo "Expiration Email Sent to: ".$userEmail."     |     ";
			}//end if ($today < $paidDate)
		  }//end if ($paidDate < $dateFuture)
	  } else {
		  if ($joinDate < $dateFifteenDaysPast) {
			  echo "id ".$id." ".$userEmail." Never Activated, Joined ".date('m-d-Y', strtotime($joinDate))."     |     ";
			  $conn->query("DELETE FROM account WHERE account.id = ".$id." LIMIT 1;");
		  }
	  }//end if ($userActive > 0)
	}//end while ($row = $getNumUsersQuery->fetch_assoc())
//send out expiring paid account emails

?>