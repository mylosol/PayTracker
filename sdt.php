<?php 
include('include/db.php'); 
$up = $_GET['u'];
$down = $_GET['d'];
$edit = $_GET['e'];

$getProgressUpdate = "SELECT progress1 FROM upgrade WHERE id LIKE 1 LIMIT 1;";

$progressUpdate = $conn->query($getProgressUpdate);

while($row = $progressUpdate->fetch_assoc()) { 
	 	$updateResults = $row["progress1"];
}//end while($row = $progressUpdate->fetch_assoc()) 


if (isset($edit)) {

	if (isset($up)) {
		$newProgress = $updateResults + 1;
	}//end if (isset($up))
	
	if (isset($down)) {
		$newProgress = $updateResults - 1;
	}//end if (isset($down))
	
	
	$updateProgress = "UPDATE `upgrade` SET `progress1` = '".$newProgress."' WHERE `upgrade`.`id` = 1;";
	
	if ($conn->query($updateProgress) === TRUE) { header("Location: http://".$_SERVER['SERVER_NAME']."/sdt.php"); } else { echo "Error updating record: " . $conn->error; }//end if ($conn->query($updateProgress) === TRUE)

}//end if (isset($edit))


include('include/meta.html'); ?> 
<title>Pay Tracker </title>
<link href="style.css" rel="stylesheet" type="text/css" />
<style> 

p {font-size:14px;} 

p.per {
	color:#0F0;
	font-weight:bold;
	font-size:16px;
}

</style>
</head>

<body>
      
      
 <div id="topMenuContain">
<div id="logo" class="logo"><img src="http://'.$_SERVER['SERVER_NAME'].'/images/logo.png" alt="Pay Tracker" class="logo" /></div>
 </div><!--end "topMenuContain"-->  
 
 

 
<div id="wrapper" class="largeContain">
  <div id="broken" class="add-load-contain" style=" text-align: center;">
        
<center>
        
<table width="200" border="0">
  <tr>
    <td><a href="sdt.php?u=1&e=1"><img src="images/upArrow_Green.png" border="0"></a></td>
    <td><p class="per"><?php echo $updateResults . "%"; ?></p></td>
    <td><a href="sdt.php?d=1&e=1"><img src="images/downArrow_Red.png" border="0"></a></td>
  </tr>
</table>

</center>        
        
  </div><!--end wrapper-->
</div><!--end add-load-contain-->
       

</body>
</html>