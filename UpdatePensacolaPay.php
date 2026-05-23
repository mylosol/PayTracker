<?php
include('include/db.php');
ini_set ('display_errors', 1);
$getPay = $_POST['p'];
$getMiles = $_POST['m'];
$anchor = $_GET['a'];
$getNumQuery = "SELECT * FROM `PensacolaPay` ORDER BY `id` DESC";
$result = $conn->query($getNumQuery);



	if (isset($getMiles)) {

		
		$conn->query("INSERT INTO `PensacolaPay` (`id`, `miles`, `rate`) VALUES (NULL, '".$getMiles."', '".$getPay."')");
		header("Location: http://".$_SERVER['SERVER_NAME']."/UpdatePensacolaPay.php");

	}//end if (isset($getMiles))
?>
<!DOCTYPE HTML>
<html>
<head>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8">
<?php include('include/meta.html'); ?> 
<title>Pensacola Pay Database</title>
<link href="style.css" rel="stylesheet" type="text/css" />
</head>
<body>
<div id="wrapper" class="largeContain" style="margin-top:30px;">
  <div id="Top" class="add-load-contain">
	<!--<div id="inputContain" style="margin:5px; text-align:center;">-->
    <span style="text-align:center;">
    <h1>Pensacola</h1>
    
    </span>
    <center>
<form method="post" action="UpdatePensacolaPay.php">
   
   		Miles:<br />
        <input type="text" size="5" name="m" />
        <br /><br />
        Pay:<br />
        <input type="text" size="5" name="p" />
        <br /><br />
   <input type="submit" class="myButton halfB blueB" value="Go!" />
   
   </form>
</center>
	</div><!--end inputContain   
  </div><!--end Top-->
      <br /><br />
      <hr width="30%" align="center" />
    
    <center>
    <div id="cotainer" style="margin:30px;">
    <table width="200" style="font-size:16px; font-weight:bold;border-spacing: 20px; border-collapse:separate;  padding:10px;">
		<tr>
        	<td style="border-bottom-style: ridge; border-bottom-color: #0F6; border-bottom-width: thick;">Miles</td>
            <td style="border-bottom-style: ridge; border-bottom-color: #0F6; border-bottom-width: thick;">Rate</td>
        </tr>
<?php		
	if ($result->num_rows > 0) {
    
	  while($row = $result->fetch_assoc()) {
		$miles = $row["miles"];
		$pay = $row["rate"];
		$id = $row["id"];
?>
			<a name="<?php echo $i; ?>">&nbsp;</a>
              <tr>
                <td style="border-bottom-style: ridge; border-bottom-color: #CCC; border-bottom-width: thin;"><?php echo $miles; ?></td>
                <td style="border-bottom-style: ridge; border-bottom-color: #CCC; border-bottom-width: thin;"><?php echo $pay; ?></td>
                <td> <a href="uppedit.php?id=<?php echo $id; ?>&a=<?php echo $i; ?>&t=pe">Edit</a></td>
       
             </tr>
<?php
	  }//end  while($row = $result->fetch_assoc())
				
	}//end if ($result->num_rows > 0) 
?>
	</table>
    
    </div><!--end cotainer-->
    </center>

</div><!--end large contain-->
</body>
</html>