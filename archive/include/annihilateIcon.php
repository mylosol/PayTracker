<!--Begin Annihilate-->
<?php 	if (isset($_GET["a"])) { $a = $_GET["a"]; }
	if (!isset($a)) { 
?>
	<div id="annihilate" class="annihilate">
    	<a href="../annihilate.php?a=1" target="_self">
        	<img src="../images/annihilate.png" border="0" height="100%" width="100%" />
        </a>
    </div>
<?php
	}//end if (!isset($a))
?>
<!--End Annihilate-->