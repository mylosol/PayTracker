<?php
	include('include/db.php');
   include('include/cookiecheckMP.php');
	$timestamp = date("Y-m-d H:i:s");
	
	
	if(isset($_POST['open'])) {		
			$status = 1;
		}//end if(isset($_POST['open']))
	
	if(isset($_POST['closed'])) {		
			$status = 0;	
		}//end if(isset($_POST['closed']))
		
	if(isset($status)) {	
		$conn->query("UPDATE `weigh` SET `time` = '".$timestamp."', `status` = '".$status."' WHERE `weigh`.`id` =1 LIMIT 1 ;");
	   include('header/headerRedirect.php');
	} else {

include('include/meta.html'); ?> 
<title>Scale House</title>
<link href="style.css" rel="stylesheet" type="text/css" />
<script type="text/javascript" src="js/addInput.js"></script>
</head>

<body>
<noscript>
<h1 style="text-align: center;">Javascript Must Be Enabled To Use This Site</h1>
</noscript>
<script>
  (function(i,s,o,g,r,a,m){i['GoogleAnalyticsObject']=r;i[r]=i[r]||function(){
  (i[r].q=i[r].q||[]).push(arguments)},i[r].l=1*new Date();a=s.createElement(o),
  m=s.getElementsByTagName(o)[0];a.async=1;a.src=g;m.parentNode.insertBefore(a,m)
  })(window,document,'script','http://www.google-analytics.com/analytics.js','ga');

  ga('create', 'UA-18845112-8', 'auto');
  ga('send', 'pageview');

</script>
<?php

	$beta = $_GET['b'];
	if (isset($pro) || isset($beta)) { include ('include/frontMenu.php'); } else {
echo "	<br /> <br />";
	}//end if (isset($pro))
?>
</div><!--end topMenuContain-->

<div id="wrapper" class="largeContain">
  <div id="Top" class="add-load-contain">
		
		<h3 style="text-align:center;">Hwy 77 Scale House</h3>
		<h3 style="text-align:center;">Report Status</h3>
		<form method="post">
	      <input type="submit" name="open" class="myButton halfB blueB" value="Open" />
	      <input type="submit" name="closed" class="myButton halfB greyB" value="Closed" />
      </form>
      <br />
		<center>      
      <form method="post" action="loadselect.php">
	  		<input type="submit" name="cancel" class="myButton wideB greyB" value="Cancel" />
	   </form>
	   </center>	
      
	</div><!--end Top-->
</div><!--end wrapper-->
<?php

	}//end if(isset($status))

?>