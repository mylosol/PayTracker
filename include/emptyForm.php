       <?php
		  $currentLoadInfo = "L" . $loads;
		  $cl = $_COOKIE[$currentLoadInfo];
		  $clExplode = explode("-", $cl);
		  $cpu = $clExplode[2];
		  $cdel = $clExplode[3];
     	  $sel = "";
     	  $sel1 = "";
     	  $sel2 = "";
	
		for ($m = 0; $m < $termianlNum; $m++) {
			while ($row = $terminal->fetch_assoc()) { $nem = $row["terminal"]; }
			if ($nem == $cdel) { $cpu = $nem; break;}
		}//end for ($m = 0; $m < $termianlNum; $m++)
		  
		  
	  	if (isset($_COOKIE['nq'])) {
			
		   ?>
			<form method="post" action="emptymiles.php">
			Pick Up Location: <select name="mtStart">
			<?php while ($p < $termianlNum) {
				while ($row = $terminal->fetch_assoc()) { $t = $row["terminal"]; }
				 
 					  if ($t == $lastTerminal) {
					  $sel = "Selected";
					  } else {
					  $sel = "";
					  }//end if ($t == $lastTerminal)
	
			print "<option " . $sel . " >" . $t . "</option>\n";
			$p++;
				}//end while
			$p = 0; 
			?>
			</select>      
            <br /><br />
			Ending Location: <select name="mtEnd">
			<?php while ($p < $termianlNum) {
				while ($row = $terminal->fetch_assoc()) { $t = $row["terminal"]; }
			print "<option>$t</option>\n";
			$p++;
				}//end while
			$p = 0; 
			?>
			</select>
			<br /><br />
			<input type="hidden" name="type" value="0" />
			<input type="hidden" name="load" value="<?php echo $loads; ?>" />
            <div class="clear"></div>
            <input type="submit" name="cancel" class="myButton quarterB greyB" value="Cancel" />
            <input type="submit" name="done" class="myButton threeQuarterB blueB" value="Done" />
			</form>
			<?php
			
		}//end if (isset($_COOKIE['nq']))

		?>
