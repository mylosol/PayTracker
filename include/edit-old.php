<?php
include('include/db.php');
?>

<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
<meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=0"/>
<meta name="MobileOptimized" content="width" />
<meta name="HandheldFriendly" content="true" />
<title>Load Editing</title>
<link href="style.css" rel="stylesheet" type="text/css" />
</head>

<body>

<?php 

	$passload = $_GET['l']; 
	
	if (isset($passload)) {
	
	$editQuery = $conn->query("SELECT * FROM loads WHERE frtl LIKE $passload LIMIT 1;");
	
	while ($row = $editQuery-fetch_assoc()) {
	$id = $row["id"];
	$load = $row["frtl"];
	$pu = $row["pickup"];
	$del = $row["delivery"];
	$split = $row["split"];
	$drop = $row["add"];
	$dem = $row["demurrage"];
	$we = $row["weekend"];
	$note = $row["note"];
	}//end while ($row = $editQuery-fetch_assoc()) 
	
	$terminal = $conn->query("SELECT * FROM terminal ORDER BY terminal.id ASC");
	$termianlNum = $terminal->num_rows;
	
	$city = $conn->query("SELECT * FROM city ORDER BY city.city ASC"); 
	$cityNum = $city->num_rows;
	
	$i = 0;
	$f = 0;
	
	

?>

<div id="wrapper">
<h1 align="center">Edit <?php echo $passload; ?></h1>
  <div id="add-load-contain">
  <form action="edit.php" method="post">
  <p>
  FRTL #: <input type="number" name="FRTL" size="10" value="<?php echo $load; ?>" />
  <br />
  <br />
  Pick Up Location: <select name="pick-up">
  <?php while ($i < $termianlNum) {
      while ($row = $terminal->ftech_assoc()) { $t = $row["terminal"]; }
	  	if ($pu == $t) {
			$selected = "selected";
		} else {
			$selected = "";
		}
  print "<option value=\"".$t."\" ".$selected.">$t</option>\n";
  $i++;
  }?>
  </select><br /><br />
  Delivery Location: <select name="delivery">
  <?php while ($f < $cityNum) {
      while ($row = $city-fetch_assoc()) { $c = $row["city"]; }
	  if ($del == $c) {
			$selected = "selected";
		} else {
			$selected = "";
		}
  print "<option value=\"".$c."\" ".$selected.">$c</option>\n";
  $f++;
  }?>
  </select>
  <br />
  <br />
  	<?php 
	if ($split == 10) {
		$splitLoad = "checked";
	} else {
		$splitLoad = "";
	}//end if ($split == 10)
	
	if ($drop == 10) {
		$dropLoad = "checked";
	} else {
		$dropLoad = "";
	}//end if ($drop == 10)
	
	if ($we == 1) {
		$weekendLoad = "checked";
	} else {
		$weekendLoad = "";
	}//end if ($we == 10)

	
	if ($dem == 0) {
		$demurrage = 0;
	} else {
		$demurrage = $dem;
	}//end if ($dem == 0)
	?>
  Split<input type="checkbox" name="split" value="split" <?php echo $splitLoad ?> />&nbsp;&nbsp;&nbsp;
  Extra Pay<input type="checkbox" name="add" value="add" <?php echo $dropLoad ?>  />&nbsp;&nbsp;&nbsp;
  Weekend<input type="checkbox" name="weekend" value="weekend" <?php echo $weekendLoad ?>  /><br /><br />
  Demurrage: <input type="number" name="demurrage" size="4" value="<?php echo $demurrage; ?>" />&nbsp;<sup><span style="color:#F00;">(-45 Minutes)</span></sup>
  <br />
  Notes:
  <br />
  <textarea name="note" cols="30" rows="5"><?php echo $note; ?></textarea>
  </p>
	<input type="hidden" name="id" value="<?php echo $id; ?>" />
  <input type="submit" value="submit" />
  </form>
  </div><!--end "add-load-contain"-->
<?php
	}//end if isset $passload

	$idN = $_POST['id'];
	$frtlN = $_POST['FRTL'];
	$puN = $_POST['pick-up'];
	$delN = $_POST['delivery'];
	$splitN = $_POST['split'];
	$weekend = $_POST['weekend'];
	$addN = $_POST['add'];
	$demN = $_POST['demurrage'];
	$noteN = $_POST['note'];
	
	if (isset($splitN)) {
		$splitV = 10;
	} else {
		$splitV = "NULL";
	}//end if (isset($splitN))
	
	if (isset($weekend)) {
		$weV = 1;
	} else {
		$weV = 0;
	}//end if (isset($weekend))
	
	if (isset($addN)) {
		$addV = 10;
	} else {
		$addV = "NULL";
	}//end if (isset($addN))
	
	if (empty($noteN)) {	$noteN = NULL;	}//end if (empty($noteN))


if (isset($frtlN)) {
	$conn->query("UPDATE loads SET frtl = '".$frtlN."', pickup = '".$puN."', delivery = '".$delN."', split = '".$splitV."', `add` = '".$addV."', `weekend` = '".$weV."', demurrage = '".$demN."', note = '".$noteN."' WHERE id = ".$idN." LIMIT 1");
	
		$baseQuery = $conn->query("SELECT base FROM $puN WHERE city LIKE ".$delN."");
		while ($row = $baseQuery->fetch_assoc()) { $base = $row["base"]; }
		
		if (!isset($weekend)) {
			$pay = ($base * 0.25) + $base;	
		} else {
			$pay = ($base * 0.375) + $base;	
		}//end if (!isset($weekend))
		$pay = (round($pay,2));	
?>
<center><h1>Load <?php echo $frtlN; ?> Entered</h1>
<p>Load Pay: <b>$<?php echo $pay; ?></b></p>
<?php 
	if (isset($splitN)) {
		print "<p>Split Pay: <b>$15.00</b></p>";
	}//end if (isset($splitN))
	if (isset($addN)) {
		print "<p>Extra Pay: <b>$5.00</b></p>";
	}//end if (isset($addN))
	
	if ($demN > 0) {
		$totalDem = ($demN * .25);
		print "<p>Demurrage: <b>\$$totalDem</b></p>";
	}//end if ($demN > 0)
	
	$grandTotal = ($pay + $splitN + $addN + $totalDem);
	if ($grandTotal > $pay) {
	print "<p>Grand Total: <b>$" . number_format ($grandTotal, 2) . "</b></p>";
	}//end 	if ($grandTotal > $pay) 

?>
<br /><br />
<form><input type="button" value="All Done" onClick="window.location.href='http://www.google.com';"></form>
<br /><br />
<form><input type="button" value="Reconcile" onClick="window.location.href='http://robertsheriff.com/pay/rstlne/reconcile.php';"></form>
<br /><br />
<form><input type="button" value="Tracking" onClick="window.location.href='http://robertsheriff.com/pay/rstlne/';"></form>
</center>
<?php
}
	

?>  
</div><!--end "wrapper"-->
</body>
</html>