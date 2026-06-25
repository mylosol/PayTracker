<?php
include('include/db.php');
ini_set ('display_errors', 1);

$id = $_GET['id'];
$anchor = $_GET['a'];
$t = $_GET['t'];

	if ($t == "pa") { $terminal = "PanamaPay"; $tn= 0; }
	if ($t == "pe") { $terminal = "PensacolaPay"; $tn = 1; }
	

if (isset($_POST['n'])) {
		echo $tn;
		//header('Location: http://'.$_SERVER['SERVER_NAME'].'/uppdelete.php?t='. $tn);
	}//end if (isset($_POST['n']))



$getQuery = $conn->query("SELECT * FROM `".$terminal."` WHERE `id` = '".$id."' LIMIT 1");

  if (isset($id)) {
	  while ($row = $getQuery->fetch_assoc()) {
		$miles = $row["miles"]; 
		$pay = $row["rate"]; 
	  }//end while ($row = $getQuery->fetch_assoc())
  }//end if (isset($id))

$postID = $_POST['id'];

  if (isset($postID)) {
  
  $miles = $_POST['m'];
  $pay = $_POST['p'];
  
	if (isset($_POST['y'])) {  	
				  
	 $conn->query("UPDATE `".$terminal."` SET `miles` = ".$miles." WHERE `".$terminal."`.`id` = ".$postID.";");
	 $conn->query("UPDATE `".$terminal."` SET `rate` = '".$pay."' WHERE `".$terminal."`.`id` = ".$postID.";");
	  header("Location: http://".$_SERVER['SERVER_NAME']."/UpdatePensacolaPay.php#".$anchor);
  
 	 }//end if (isset($_POST['y'))
  }//end if (isset($postID))

?>
<!DOCTYPE HTML>
<html>
<head>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8">
<?php include('include/meta.html'); ?> 
<title>Edit Pay Database</title>
<link href="style.css" rel="stylesheet" type="text/css" />
</head>
<body>


<div id="wrapper" class="largeContain" style="margin-top:30px;">
  <div id="Top" class="add-load-contain">
  
  <center>
  
  	<div id="cotainer" style="margin:30px;">
    
    	
        <form method="post" action="uppedit.php">

    	<h4>Miles:</h4>
   		<input type="text" size="5" name="m" value="<?php echo $miles; ?>" />

        <br /><br />
        
        <h4>Pay:</h4>
        <input type="text" size="5" name="p" value="<?php echo $pay; ?>" />

        <br /><br />
        
        <input type="hidden" name="id" value="<?php echo $id; ?>" />

               <input type="submit" name="y" class="myButton halfB blueB" value="Submit" /></td>
          
   		
        </form>
        <br /><br /><hr width="50%" align="center" /><br /><br />
        <form method="post" action="uppdelete.php">
        
        <input type="hidden" name="t" value="<?php echo $tn; ?>" />
        <input type="hidden" name="id" value="<?php echo $id; ?>" />
        
        <input type="submit" name="n" class="myButton halfB greyB" value="Delete" />
    
    </div><!--end cotainer-->
  
  
  </center>
  
  </div><!--end Top-->
</div><!--end wrapper-->



</body>
</html>
