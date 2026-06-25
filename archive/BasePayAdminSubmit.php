<?php
include('include/db.php');
$lhb = "lhb";
	if (isset($_COOKIE['bTest'])) {
		if ($_COOKIE['basePayType'] == $lhb) {
			$dbTable = "LHPensacolaPayTest";
			$current = "LHPensacolaPayCurrent";
			$default = "LHPensacolaPayDefault";
			$temp = "LHPensacolaPayTemp";
			$test = "LHPensacolaPayTest";
		} else {
			$dbTable = "PensacolaPayTest";
			$current = "PensacolaPayCurrent";
			$default = "PensacolaPayDefault";
			$temp = "PensacolaPayTemp";
			$test = "PensacolaPayTest";
		}
	} else {
		if ($_COOKIE['basePayType'] == $lhb) {
			$dbTable = "LHPensacolaPayCurrent";
			$current = "LHPensacolaPayCurrent";
			$default = "LHPensacolaPayDefault";
			$temp = "LHPensacolaPayTemp";
			$test = "LHPensacolaPayTest";
		} else {
			$dbTable = "PensacolaPayCurrent";
			$current = "PensacolaPayCurrent";
			$default = "PensacolaPayDefault";
			$temp = "PensacolaPayTemp";
			$test = "PensacolaPayTest";
		}
	}//end if (isset($_COOKIE['bTest']))

	$disabletestmodeGET = $_GET["dtm"];
	$testBit = $_POST["test"];
	$disabletestmode = $_POST["disabletestmode"];
	$settestdatalive = $_POST["settestdatalive"];
	$home = $_POST["home"];
	$resettodefault = $_POST["resettodefault"];
	$resettestdata = $_POST["resettestdata"];
	$updatetestdata = $_POST["updatetestdata"];
	$updatedata = $_POST["updatedata"];
  
	  
	  if (isset($testBit)) { 
	  } //end if (isset($testBit))
	  
	  if (isset($disabletestmode)) { 
		  setcookie("bTest", 1, time()-1); 
		  header("Location: http://".$_SERVER['SERVER_NAME']."/pro/BasePayAdmin.php");
	  } //end if (isset($disabletestmode))
	 
	 if (isset($disabletestmodeGET)) { 
		  setcookie("bTest", 1, time()-1); 
		  header("Location: http://".$_SERVER['SERVER_NAME']."/");
	  } //end if (isset($disabletestmode))
	  
	  if (isset($home)) { 
		header("Location: http://".$_SERVER['SERVER_NAME']."/");
	  } //end if (isset($home))
	  
	  
	  if (isset($settestdatalive)) {
		$varRowQuery = "SELECT * FROM `".$dbTable."`";
		$PensacolaPayConn = $conn->query($varRowQuery);
		  
		  while($row = $PensacolaPayConn->fetch_assoc()) {
			   $miles = $row["miles"];
			   $rate = $row["rate"];
			   $newVar = $_POST["".$miles.""];
			   $newVarQuery = "UPDATE `".$temp."` SET rate = '".$newVar."' WHERE miles = ".$miles."";
			   $varUpdateConn = $conn->query($newVarQuery);
			}//end while($row = $PensacolaPayConn->fetch_assoc()
		header("Location: http://".$_SERVER['SERVER_NAME']."/setdatalive.php?t=1&o=b");
	  }//end if (isset($settestdatalive))
	  
	  if (isset($resettodefault)) { 
	  	
		$rdQuery1 = "TRUNCATE TABLE ".$current.";";
		$rdConn1 = $conn->query($rdQuery1);
		
		$rdQuery2 = "INSERT INTO ".$current." SELECT * FROM PensacolaPayDefault;";
		$rdConn2 = $conn->query($rdQuery2);	
			
		header("Location: http://".$_SERVER['SERVER_NAME']."/pro/BasePayAdmin.php?rs=1");
	  } //end if (isset($resettodefault))
	  
	  if (isset($resettestdata)) { 
	  	
		$rdQuery1 = "TRUNCATE TABLE ".$test.";";
		$rdConn1 = $conn->query($rdQuery1);
		
		$rdQuery2 = "INSERT INTO ".$test." SELECT * FROM ".$current.";";
		$rdConn2 = $conn->query($rdQuery2);	
			
		header("Location: http://".$_SERVER['SERVER_NAME']."/pro/BasePayAdmin.php?rs=2");
	  } //end if (isset($resettestdata))
	  
	  if (isset($updatetestdata)) { 
	  	setcookie("bTest", 1, time()+43200); 
		$varRowQuery = "SELECT * FROM `".$test."`";
		$PensacolaPayConn = $conn->query($varRowQuery);
		  
		  while($row = $PensacolaPayConn->fetch_assoc()) {
			   $miles = $row["miles"];
			   $rate = $row["rate"];
			   $newVar = $_POST["".$miles.""];
			   $newVarQuery = "UPDATE `".$test."` SET rate = '".$newVar."' WHERE miles = ".$miles."";
			   $varUpdateConn = $conn->query($newVarQuery);
			}//end while($row = $PensacolaPayConn->fetch_assoc()
		header("Location: http://".$_SERVER['SERVER_NAME']."/");
	  } //end if (isset($updatetestdata))

	  if (isset($updatedata)) { 
		$varRowQuery = "SELECT * FROM `".$dbTable."`";
		$PensacolaPayConn = $conn->query($varRowQuery);
		  
		  while($row = $PensacolaPayConn->fetch_assoc()) {
			   $miles = $row["miles"];
			   $rate = $row["rate"];
			   $newVar = $_POST["".$miles.""];
			   $newVarQuery = "UPDATE `".$temp."` SET rate = '".$newVar."' WHERE miles = ".$miles."";
			   $varUpdateConn = $conn->query($newVarQuery);
			}//end while($row = $PensacolaPayConn->fetch_assoc()
		header("Location: http://".$_SERVER['SERVER_NAME']."/setdatalive.php?o=b");
	  } //end if (isset($updatedata))
	  
?>

