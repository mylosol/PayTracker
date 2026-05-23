<?php
if ($ori > 0) {
	
	if ($orm > ($loadMiles + 3)) {
		$loadMiles = $orm;
		$ormI = "<p class=\"notice\">*This load may contain out of route miles<p>";
		if ($ori == 2) {
			$gm = " <img src=\"images/googleMaps.png\" class=\"googleMaps\" /> ";
		} else {
			$gm = "";
		}//end if ($ori == 2)
	} else {
		$loadMiles = $loadMiles;
		$ormI = "";
		$gm = "";
	}//end if ($orm > $loadVariance)
	
} else {
	$ormI = "";
}//end if ($ori > 0)
?>