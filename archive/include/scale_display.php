<div id="status-wrapper" class="largeContain">
  <div id="status" class="add-load-contain">
 	
<?php
	$getStatus = $conn->query("SELECT * FROM `weigh` ORDER BY `weigh`.`time` DESC LIMIT 1");
	while ($row = $getStatus->fetch_assoc()) {
	$stationStatus = $row["status"];
	$stationDate = $row["time"];
	}//end while ($row = $getStatus->fetch_assoc())
	$stationDateStr = strtotime($stationDate);
	$stationDisplayTime = date('m-d-Y @ h:i a', $stationDateStr);
	$stationTimeCompare = date('m-d-Y H:i', $stationDateStr);
	$stationFiveHours = date('m-d-Y H:i', strtotime("-5 hours"));
	$stationEightHours = date('m-d-Y H:i', strtotime("-8 hours"));
	
	?> <div id="status Container" class="statusContain">
	   <center>
	   <p>Hwy 77 Southbound Scale House</p>
<?php	   
	if($stationTimeCompare  < $stationFiveHours) {	
		$oldReportStyle = "greyscale";
		$scaleImageClass = "oldScaleImage";
	} else {
		$oldReportStyle = "";
		$scaleImageClass = "scaleImage";
	}//end if($stationDisplayTime < $stationFiveHours)
	
	
	
	if($stationTimeCompare  < $stationEightHours) {
			
		echo "<h4>Driver Report Stale <br /> Please Update Report &#62;&#62;</h4>";
		
	} else {

		echo "<h4>Driver Reported<span style=\"color:#F00;\"><sup>&#42;&#42;</sup></span></h4>";

	
	
		if($stationStatus > 0) {


			echo "<img src=\"images/open.png\" class=\"ssImage ".$oldReportStyle."\">";
		
				
		} else {


			echo "<img src=\"images/closed.png\" class=\"ssImage ".$oldReportStyle."\">";
			
		
		}//end if($stationStatus > 0)
	

	}//end if($stationDisplayTime < $stationEightHours)
?>	   
	   
	<div id="status timestamp" class="wsStatus"><h4> <?php echo $stationDisplayTime ?> </h4></div>
		</center>
		</div><!-- end status Container -->
	<div id="report icon" class="reportIcon">
	
<?php	echo "<a href=\"scale.php\" target=\"_self\" title=\"Report Scale House Status\"><img src=\"images/scale.png\" alt=\"Scale\" class=\"".$scaleImageClass."\" height=\"100%\" width=\"100%\"></a>"; ?>
	
	</div><!--end report icon-->
 	
	</div><!--end status-->
</div><!--end status-wrapper-->
