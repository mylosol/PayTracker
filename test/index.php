<?
	echo "this is before";
	
	include('../include/db.php');


	if (isset($_COOKIE['vTest'])) {
		$dbTable = "variablesTest";
	} else {
		$dbTable = "variablesCurrent";
	}//end if (isset($_COOKIE['vTest']))
	
	
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
		$raise = 0.15;
		$trainerpay = 327.75;		
		
	}//end if (isset($_COOKIE['tenure']))
	
//TEST		
			
	$mtSql = "SELECT amount FROM `".$dbTable."` WHERE `variable` LIKE '6_mt'"; 
			$mtQuery = $conn->query($mtSql);
				while($row = $mtQuery->fetch_assoc()) {
					$mt = $row["amount"];
				}
			echo "mt = " . $mt; 
//TEST

	
	
	if ($tenure == 6) { 
		$td = "0 - 6 M"; 
		$mtQuery = "SELECT amount FROM `" . $dbTable . "` WHERE variable = '6_mt' LIMIT 1;"; 
			$mt = $conn->query($mtQuery);
			echo "mt = " . $mt; 
		$tb = .0325; 
		$wk = 0.11; 
		$newBump = 0.1075; 
		$night =  0.15;
	}
	
	if ($tenure == 12) { 
		$td = "7 - 12 M"; 
		$mt = 0.3951; 
		$tb = .0325; 
		$wk = 0.115; 
		$newBump = 0.1075; 
		$night =  0.15;
	}
	
	if ($tenure == 13 || $tenure == 24) {
		$td = "13 - 24 M"; 
		$mt = 0.3951; 
		$tb = .0325; 
		$wk = 0.115; 
		$newBump = 0.1075; 
		$night = 0.16; 
	}
	
	if ($tenure == 24 || $tenure == 60) { 
		$td = "25 - 60 M"; 
		$mt = 0.4069; 
		$tb = .0625; 
		$wk = 0.12; 
		$newBump = 0.1275; 
		$night = 0.16; 
	}
	
	if ($tenure == 60 || $tenure == 108) { 
		$td = "61 - 108 M"; 
		$mt = 0.4322; 
		$tb = .1075; 
		$wk = 0.1225; 
		$newBump = 0.1475; 
		$night = 0.165; 
	}
	
	if ($tenure == 108 || $tenure == 168) { 
		$td = "109-168 M"; 
		$mt = 0.4351; 
		$tb = .1425; 
		$wk = 0.125; 
		$newBump = 0.1775; 
		$night = 0.17; 
	}
	
	if ($tenure == "max") { 
		$td = "169+ M"; 
		$mt = 0.4481; 
		$tb = .1775; 
		$wk = 0.125; 
		$newBump = 0.2275; 
		$night= 0.17; 
	}


	if ($shift == "night") { $nightOn = $night; } else { $nightOn = 0; } 
	
	$boosts = $newBump + $nightOn;

?>