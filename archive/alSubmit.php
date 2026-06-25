<?php
include('include/db.php');

  if (isset($_POST['cancel'])) {
		include('header/headerRedirect.php');
		exit;	
	}//end if(isset($_POST['cancel'])) 	
		
	
	
   if (isset($_COOKIE['pensacola'])) {
   	$dbl = "pcola_largeMiles";
   } else {
   	$dbl = "largeMiles";
   }//end if (isset($_COOKIE['pensacola']))


	
	$pickup = $_POST['pickup'];
		
	
	
	if (isset($_POST['newCityRT'])) {
		$newCity = $_POST['newCityRT'];
		$state = $_POST['state'];
		$miles = $_POST['miles'];
		$city = $newCity . ", " . $state;
		$duplicateCheck = $conn->query("SELECT * FROM `".$dbl."` WHERE `city` LIKE '".$city."';");
	   $duplicatePositive = $duplicateCheck->num_rows;
	   	
	   	if($duplicatePositive > 0) {
	   		$city = explode(", ", $city);
				$city = $city[0] . "+" . $city[1];
	   		header("Location: http://".$_SERVER['SERVER_NAME']."/ald.php?d=1&c=" . $city );
	   		exit;
	   	}//end if($duplicatePositive > 0)
			
		$conn->query("ALTER TABLE `".$dbl."` ADD `".$city."` INT( 3 ) NOT NULL DEFAULT '0';") or die ('<center><h1>Unable to alter table</h1></center>');
		$conn->query("INSERT INTO `".$dbl."` (`city`) VALUES ('".$city."');") or die ('<center><h1>Unable to insert city</h1></center>');
		$conn->query("UPDATE `".$dbl."` SET `".$city."` = '".$miles."' WHERE `".$dbl."`.`city` = '".$pickup."';") or die ('<center><h1>Unable to update miles</h1></center>');
		$conn->query("UPDATE `".$dbl."` SET `".$pickup."` = '".$miles."' WHERE `".$dbl."`.`city` = '".$city."';") or die ('<center><h1>Unable to update miles</h1></center>');
		$city = explode(", ", $city);
		$city = $city[0] . "_" . $city[1];
		$pickup = explode(", ", $pickup);
		$pickup = $pickup[0] . "_" . $pickup[1];
		header("Location: http://".$_SERVER['SERVER_NAME']."/ald.php?ac=1&m=" . $miles . "&c=" . $city . "&p=" . $pickup);
	}//end if (isset($_POST['newCityRT']))
	
	
	if (isset($_POST['newCityOW'])) {
		$newCity = $_POST['newCityOW'];
		$endEmpty = $_POST['endEmpty'];		
		$state = $_POST['state'];
		$emptyMiles = $_POST['emptyMiles'];
		$loadedMiles = $_POST['loadedMiles'];
		$city = $newCity . ", " . $state;
		$duplicateCheck = $conn->query("SELECT * FROM `".$dbl."` WHERE `city` LIKE '".$city."';");
	   $duplicatePositive = mysql_num_rows($duplicateCheck);
	   	
	   	if($duplicatePositive > 0) {
	   		$city = explode(", ", $city);
				$city = $city[0] . "_" . $city[1];
	   		header("Location: http://".$_SERVER['SERVER_NAME']."/ald.php?d=1&c=" . $city );
	   		exit;
	   	}//end if($duplicatePositive > 0)
	   	
		$conn->query("ALTER TABLE `".$dbl."` ADD `".$city."` INT( 3 ) NOT NULL DEFAULT '0';") or die ('<center><h1>Unable to alter table</h1></center>');
		$conn->query("INSERT INTO `".$dbl."` (`city`) VALUES ('".$city."');") or die ('<center><h1>Unable to insert city</h1></center>');
		$conn->query("UPDATE `".$dbl."` SET `".$city."` = '".$loadedMiles."' WHERE `".$dbl."`.`city` = '".$pickup."';") or die ('<center><h1>Unable to update miles</h1></center>');
		$conn->query("UPDATE `".$dbl."` SET `".$pickup."` = '".$loadedMiles."' WHERE `".$dbl."`.`city` = '".$city."';") or die ('<center><h1>Unable to update miles</h1></center>');
		
		$conn->query("UPDATE `".$dbl."` SET `".$city."` = '".$emptyMiles."' WHERE `".$dbl."`.`city` = '".$endEmpty."';") or die ('<center><h1>Unable to update miles</h1></center>');
		$conn->query("UPDATE `".$dbl."` SET `".$endEmpty."` = '".$emptyMiles."' WHERE `".$dbl."`.`city` = '".$city."';") or die ('<center><h1>Unable to update miles</h1></center>');
		
		$urlCity = urlencode($newCity) . "_" . $state;	
		
		$pickup = explode(", ", $pickup);
		$pickup = $pickup[0] . "_" . $pickup[1];
		
		
		$endEmpty = explode(", ", $endEmpty);
		$endEmpty = $endEmpty[0] . "_" . $endEmpty[1];
		
		header("Location: http://".$_SERVER['SERVER_NAME']."/ald.php?ac=1&ow=1&m=" . $loadedMiles . "&c=" . $urlCity . "&p=" . $pickup . "&ee=" . $endEmpty . "&ept=" . $emptyMiles);
	}//end if (isset($_POST['newCityOW']))
	
	
	
	
	
	
	

?>