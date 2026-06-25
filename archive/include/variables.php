<?php
	if (isset($_COOKIE['vTest'])) {
		$dbTable = "variablesTest";
	} else {
		$dbTable = "variablesCurrent";
	}//end if (isset($_COOKIE['vTest']))
	
	if (isset($_COOKIE['bTest'])) {
		$dbTable2 = "PensacolaPayTest";
	} else {
		$dbTable2 = "PensacolaPayCurrent";
	}//end if (isset($_COOKIE['vTest']))

	if (isset($_COOKIE['bTest'])) {
		$dbTable3 = "LHPensacolaPayTest";
	} else {
		$dbTable3 = "LHPensacolaPayCurrent";
	}//end if (isset($_COOKIE['vTest']))
	
	$tenure = 'NOTSET';
	$night = 0;
	$shift = 0;
	$newBump = 0;
	
	if (isset($variables)) {
		$variable = explode("-", $variables);
		$tenure = $variable[0];
		$shift = $variable[1];
//		$compare = $variable[3];
//			if ($shift == "night") { $shiftBoost = 0.5; }
//			if ($shift == "day") { $shiftBoost = 0; }
		$slip = $variable[2];
//			if ($slip == "yes") { $slipBoost = 0.05; }
//			if ($slip == "no") { $slipBoost = 0; }
		
	}//end if (isset($variables))
		
		$raiseSql = "SELECT amount FROM `".$dbTable."` WHERE `variable` LIKE 'raise'"; 
		$raiseQuery = $conn->query($raiseSql);
		  while($row = $raiseQuery->fetch_assoc()) {
			  $raise = $row["amount"];
		  }//end raise
		
		$trainer_paySql = "SELECT amount FROM `".$dbTable."` WHERE `variable` LIKE 'trainer_pay'"; 
		$trainer_payQuery = $conn->query($trainer_paySql);
		  while($row = $trainer_payQuery->fetch_assoc()) {
			  $trainerpay = $row["amount"];
		  }//end trainer_pay
		  
		$demurrageSql = "SELECT amount FROM `".$dbTable."` WHERE `variable` LIKE 'demurrage'"; 
		$demurrageQuery = $conn->query($demurrageSql);
		  while($row = $demurrageQuery->fetch_assoc()) {
			  $demurrageCPM = $row["amount"];
		  }//end demurrage

		$breakdownSql = "SELECT amount FROM `".$dbTable."` WHERE `variable` LIKE 'breakdown'"; 
		$breakdownQuery = $conn->query($breakdownSql);
		  while($row = $breakdownQuery->fetch_assoc()) {
			  $breakdownCPM = $row["amount"];
		  }//end breakdown
	
	if ($tenure == 6) { 
		$td = "0 - 6 M"; 
		$mtSql = "SELECT amount FROM `".$dbTable."` WHERE `variable` LIKE '6_mt'"; 
		$mtQuery = $conn->query($mtSql);
		  while($row = $mtQuery->fetch_assoc()) {
			  $mt = $row["amount"];
		  }//end 6_mt
		$tbSql = "SELECT amount FROM `".$dbTable."` WHERE `variable` LIKE '6_tb'"; 
		$tbQuery = $conn->query($tbSql);
		  while($row = $tbQuery->fetch_assoc()) {
			  $tb = $row["amount"];
		  }//end 6_mt
		$wkSql = "SELECT amount FROM `".$dbTable."` WHERE `variable` LIKE '6_wk'"; 
		$wkQuery = $conn->query($wkSql);
		  while($row = $wkQuery->fetch_assoc()) {
			  $wk = $row["amount"];
		  }//end 6_wk
		$newBumpSql = "SELECT amount FROM `".$dbTable."` WHERE `variable` LIKE '6_newBump'"; 
		$newBumpQuery = $conn->query($newBumpSql);
		  while($row = $newBumpQuery->fetch_assoc()) {
			  $newBump = $row["amount"];
		  }//end 6_newBump
		$nightSql = "SELECT amount FROM `".$dbTable."` WHERE `variable` LIKE '6_night'"; 
		$nightQuery = $conn->query($nightSql);
		  while($row = $nightQuery->fetch_assoc()) {
			  $night = $row["amount"];
		  }//end 6_night
	}
	
	if ($tenure == 12) { 
		$td = "7 - 12 M"; 
		$mtSql = "SELECT amount FROM `".$dbTable."` WHERE `variable` LIKE '12_mt'"; 
		$mtQuery = $conn->query($mtSql);
		  while($row = $mtQuery->fetch_assoc()) {
			  $mt = $row["amount"];
		  }//end 12_mt
		$tbSql = "SELECT amount FROM `".$dbTable."` WHERE `variable` LIKE '12_tb'"; 
		$tbQuery = $conn->query($tbSql);
		  while($row = $tbQuery->fetch_assoc()) {
			  $tb = $row["amount"];
		  }//end 12_tb
		$wkSql = "SELECT amount FROM `".$dbTable."` WHERE `variable` LIKE '12_wk'"; 
		$wkQuery = $conn->query($wkSql);
		  while($row = $wkQuery->fetch_assoc()) {
			  $wk = $row["amount"];
		  }//end 12_wk
		$newBumpSql = "SELECT amount FROM `".$dbTable."` WHERE `variable` LIKE '12_newBump'"; 
		$newBumpQuery = $conn->query($newBumpSql);
		  while($row = $newBumpQuery->fetch_assoc()) {
			  $newBump = $row["amount"];
		  }//end 12_newBump
		$nightSql = "SELECT amount FROM `".$dbTable."` WHERE `variable` LIKE '12_night'"; 
		$nightQuery = $conn->query($nightSql);
		  while($row = $nightQuery->fetch_assoc()) {
			  $night = $row["amount"];
		  }//end 12_night
	}
	
	if ($tenure == 13 || $tenure == 24) {
		$td = "13 - 24 M"; 
		$mtSql = "SELECT amount FROM `".$dbTable."` WHERE `variable` LIKE '24_mt'"; 
		$mtQuery = $conn->query($mtSql);
		  while($row = $mtQuery->fetch_assoc()) {
			  $mt = $row["amount"];
		  }//end 24_mt
		$tbSql = "SELECT amount FROM `".$dbTable."` WHERE `variable` LIKE '24_tb'"; 
		$tbQuery = $conn->query($tbSql);
		  while($row = $tbQuery->fetch_assoc()) {
			  $tb = $row["amount"];
		  }//end 24_tb
		$wkSql = "SELECT amount FROM `".$dbTable."` WHERE `variable` LIKE '24_wk'"; 
		$wkQuery = $conn->query($wkSql);
		  while($row = $wkQuery->fetch_assoc()) {
			  $wk = $row["amount"];
		  }//end 24_wk
		$newBumpSql = "SELECT amount FROM `".$dbTable."` WHERE `variable` LIKE '24_newBump'"; 
		$newBumpQuery = $conn->query($newBumpSql);
		  while($row = $newBumpQuery->fetch_assoc()) {
			  $newBump = $row["amount"];
		  }//end 24_newBump
		$nightSql = "SELECT amount FROM `".$dbTable."` WHERE `variable` LIKE '24_night'"; 
		$nightQuery = $conn->query($nightSql);
		  while($row = $nightQuery->fetch_assoc()) {
			  $night = $row["amount"];
		  }//end 24_night
	}
	
	if ($tenure == 24 || $tenure == 60) { 
		$td = "25 - 60 M"; 
		$mtSql = "SELECT amount FROM `".$dbTable."` WHERE `variable` LIKE '60_mt'"; 
		$mtQuery = $conn->query($mtSql);
		  while($row = $mtQuery->fetch_assoc()) {
			  $mt = $row["amount"];
		  }//end 60_mt
		$tbSql = "SELECT amount FROM `".$dbTable."` WHERE `variable` LIKE '60_tb'"; 
		$tbQuery = $conn->query($tbSql);
		  while($row = $tbQuery->fetch_assoc()) {
			  $tb = $row["amount"];
		  }//end 60_tb
		$wkSql = "SELECT amount FROM `".$dbTable."` WHERE `variable` LIKE '60_wk'"; 
		$wkQuery = $conn->query($wkSql);
		  while($row = $wkQuery->fetch_assoc()) {
			  $wk = $row["amount"];
		  }//end 60_wk
		$newBumpSql = "SELECT amount FROM `".$dbTable."` WHERE `variable` LIKE '60_newBump'"; 
		$newBumpQuery = $conn->query($newBumpSql);
		  while($row = $newBumpQuery->fetch_assoc()) {
			  $newBump = $row["amount"];
		  }//end 60_newBump
		$nightSql = "SELECT amount FROM `".$dbTable."` WHERE `variable` LIKE '60_night'"; 
		$nightQuery = $conn->query($nightSql);
		  while($row = $nightQuery->fetch_assoc()) {
			  $night = $row["amount"];
		  }//end 60_night
	}
	
	if ($tenure == 60 || $tenure == 108) { 
		$td = "61 - 108 M"; 
		$mtSql = "SELECT amount FROM `".$dbTable."` WHERE `variable` LIKE '108_mt'"; 
		$mtQuery = $conn->query($mtSql);
		  while($row = $mtQuery->fetch_assoc()) {
			  $mt = $row["amount"];
		  }//end 108_mt
		$tbSql = "SELECT amount FROM `".$dbTable."` WHERE `variable` LIKE '108_tb'"; 
		$tbQuery = $conn->query($tbSql);
		  while($row = $tbQuery->fetch_assoc()) {
			  $tb = $row["amount"];
		  }//end 108_tb
		$wkSql = "SELECT amount FROM `".$dbTable."` WHERE `variable` LIKE '108_wk'"; 
		$wkQuery = $conn->query($wkSql);
		  while($row = $wkQuery->fetch_assoc()) {
			  $wk = $row["amount"];
		  }//end 108_wk
		$newBumpSql = "SELECT amount FROM `".$dbTable."` WHERE `variable` LIKE '108_newBump'"; 
		$newBumpQuery = $conn->query($newBumpSql);
		  while($row = $newBumpQuery->fetch_assoc()) {
			  $newBump = $row["amount"];
		  }//end 108_newBump
		$nightSql = "SELECT amount FROM `".$dbTable."` WHERE `variable` LIKE '108_night'"; 
		$nightQuery = $conn->query($nightSql);
		  while($row = $nightQuery->fetch_assoc()) {
			  $night = $row["amount"];
		  }//end 108_night
	}
	
	if ($tenure == 108 || $tenure == 168) { 
		$td = "109-168 M"; 
		$mtSql = "SELECT amount FROM `".$dbTable."` WHERE `variable` LIKE '168_mt'"; 
		$mtQuery = $conn->query($mtSql);
		  while($row = $mtQuery->fetch_assoc()) {
			  $mt = $row["amount"];
		  }//end 168_mt
		$tbSql = "SELECT amount FROM `".$dbTable."` WHERE `variable` LIKE '168_tb'"; 
		$tbQuery = $conn->query($tbSql);
		  while($row = $tbQuery->fetch_assoc()) {
			  $tb = $row["amount"];
		  }//end 168_tb
		$wkSql = "SELECT amount FROM `".$dbTable."` WHERE `variable` LIKE '168_wk'"; 
		$wkQuery = $conn->query($wkSql);
		  while($row = $wkQuery->fetch_assoc()) {
			  $wk = $row["amount"];
		  }//end 168_wk
		$newBumpSql = "SELECT amount FROM `".$dbTable."` WHERE `variable` LIKE '168_newBump'"; 
		$newBumpQuery = $conn->query($newBumpSql);
		  while($row = $newBumpQuery->fetch_assoc()) {
			  $newBump = $row["amount"];
		  }//end 168_newBump
		$nightSql = "SELECT amount FROM `".$dbTable."` WHERE `variable` LIKE '168_night'"; 
		$nightQuery = $conn->query($nightSql);
		  while($row = $nightQuery->fetch_assoc()) {
			  $night = $row["amount"];
		  }//end 168_night
	}
	
	if ($tenure == "max") { 
		$td = "169+ M"; 
		$mtSql = "SELECT amount FROM `".$dbTable."` WHERE `variable` LIKE 'max_mt'"; 
		$mtQuery = $conn->query($mtSql);
		  while($row = $mtQuery->fetch_assoc()) {
			  $mt = $row["amount"];
		  }//end max_mt
		$tbSql = "SELECT amount FROM `".$dbTable."` WHERE `variable` LIKE 'max_tb'"; 
		$tbQuery = $conn->query($tbSql);
		  while($row = $tbQuery->fetch_assoc()) {
			  $tb = $row["amount"];
		  }//end max_tb
		$wkSql = "SELECT amount FROM `".$dbTable."` WHERE `variable` LIKE 'max_wk'"; 
		$wkQuery = $conn->query($wkSql);
		  while($row = $wkQuery->fetch_assoc()) {
			  $wk = $row["amount"];
		  }//end max_wk
		$newBumpSql = "SELECT amount FROM `".$dbTable."` WHERE `variable` LIKE 'max_newBump'"; 
		$newBumpQuery = $conn->query($newBumpSql);
		  while($row = $newBumpQuery->fetch_assoc()) {
			  $newBump = $row["amount"];
		  }//end max_newBump
		$nightSql = "SELECT amount FROM `".$dbTable."` WHERE `variable` LIKE 'max_night'"; 
		$nightQuery = $conn->query($nightSql);
		  while($row = $nightQuery->fetch_assoc()) {
			  $night = $row["amount"];
		  }//end max_night
	}


	if ($shift == "night") { $nightOn = $night; } else { $nightOn = 0; } 
	
	$boosts = $newBump + $nightOn;

?>