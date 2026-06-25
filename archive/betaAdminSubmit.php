<?php
include('include/db.php');

  if (isset($_COOKIE['vTest'])) {
	  $dbTable = "variablesTest";
  } else {
	  $dbTable = "variablesCurrent";
  }//end if (isset($_COOKIE['vTest']))

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
		  setcookie("vTest", 1, time()-1); 
		  header("Location: http://".$_SERVER['SERVER_NAME']."/pro/betaAdmin.php");
	  } //end if (isset($disabletestmode))
	  
	  if (isset($disabletestmodeGET)) { 
		  setcookie("vTest", 1, time()-1); 
		  header("Location: http://".$_SERVER['SERVER_NAME']."/");
	  } //end if (isset($disabletestmode))
	  
	  if (isset($home)) { 
		header("Location: http://".$_SERVER['SERVER_NAME']."/");
	  } //end if (isset($home))
	  
	  
	  if (isset($settestdatalive)) {
		$varRowQuery = "SELECT * FROM `".$dbTable."`";
		$variablesConn = $conn->query($varRowQuery);
		  
		  while($row = $variablesConn->fetch_assoc()) {
			   $varID = $row["id"];
			   $title = $row["variable"];
			   $newVar = $_POST["".$title.""];
			   $newVarQuery = "UPDATE `variablesTemp` SET amount = '".$newVar."' WHERE id = ".$varID."";
			   $varUpdateConn = $conn->query($newVarQuery);
			}//end while($row = $variablesConn->fetch_assoc()
		header("Location: http://".$_SERVER['SERVER_NAME']."/setdatalive.php?t=1&o=v");
	  }//end if (isset($settestdatalive))
	  
	  if (isset($resettodefault)) { 
	  	
		$rdQuery1 = "TRUNCATE TABLE variablesCurrent;";
		$rdConn1 = $conn->query($rdQuery1);
		
		$rdQuery2 = "INSERT INTO variablesCurrent SELECT * FROM variablesDefault;";
		$rdConn2 = $conn->query($rdQuery2);	
			
		header("Location: http://".$_SERVER['SERVER_NAME']."/pro/betaAdmin.php?rs=1");
	  } //end if (isset($resettodefault))
	  
	  if (isset($resettestdata)) { 
	  	
		$rdQuery1 = "TRUNCATE TABLE variablesTest;";
		$rdConn1 = $conn->query($rdQuery1);
		
		$rdQuery2 = "INSERT INTO variablesTest SELECT * FROM variablesCurrent;";
		$rdConn2 = $conn->query($rdQuery2);	
			
		header("Location: http://".$_SERVER['SERVER_NAME']."/pro/betaAdmin.php?rs=2");
	  } //end if (isset($resettodefault))
	  
	  if (isset($updatetestdata)) { 
		setcookie("vTest", 1, time()+43200); 
		$varRowQuery = "SELECT * FROM `variablesTest`";
		$variablesConn = $conn->query($varRowQuery);
		  
		  while($row = $variablesConn->fetch_assoc()) {
			   $varID = $row["id"];
			   $title = $row["variable"];
			   $newVar = $_POST["".$title.""];
			   $newVarQuery = "UPDATE `variablesTest` SET amount = '".$newVar."' WHERE id = ".$varID."";
			   $varUpdateConn = $conn->query($newVarQuery);
			}//end while($row = $variablesConn->fetch_assoc()
		header("Location: http://".$_SERVER['SERVER_NAME']."/");
	  } //end if (isset($updatedata))

	  if (isset($updatedata)) { 
		$varRowQuery = "SELECT * FROM `".$dbTable."`";
		$variablesConn = $conn->query($varRowQuery);
		  
		  while($row = $variablesConn->fetch_assoc()) {
			   $varID = $row["id"];
			   $title = $row["variable"];
			   $newVar = $_POST["".$title.""];
			   $newVarQuery = "UPDATE `variablesTemp` SET amount = '".$newVar."' WHERE id = ".$varID."";
			   $varUpdateConn = $conn->query($newVarQuery);
			}//end while($row = $variablesConn->fetch_assoc()
		header("Location: http://".$_SERVER['SERVER_NAME']."/setdatalive.php?o=v");
	  } //end if (isset($updatedata))
	  
?>