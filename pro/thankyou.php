<?php 
setcookie("paid", 1, time()+600, '/');
include ('../include/db.php');
include ('../include/cookiecheckMP.php');
include('../include/meta.html'); ?> 
<title>Pay Tracking - Thank You For Your Purchase</title>
<link href="../style.css" rel="stylesheet" type="text/css" />
</head>

<body onLoad="countdown()">
<noscript>
<h1 style="text-align: center;">Javascript Must Be Enabled To Use This Site</h1>
</noscript>
<?php include ('../include/topMenuPro.php'); 

$pd = date("F jS, Y", strtotime($paidDate));


?>
   
<script type="text/javascript">
var ss = 16;
function countdown() {
ss = ss-1;
if (ss<1) {
window.location="http://'.$_SERVER['SERVER_NAME'].'/";
}
else {
document.getElementById("countdown").innerHTML=ss;
window.setTimeout("countdown()", 1500);
}
}
</script>
   
   
    		
<div id="wrapper" class="largeContain justText">
<center>
<h1>Thank you for your purchase!</h1>
<h3>Your PayTracker Pro Account is Paid Until <span class="dateLook1"><?php echo $pd; ?></span></h3>
<h4>Redirecting to <a href="http://'.$_SERVER['SERVER_NAME'].'/">'.$_SERVER['SERVER_NAME'].'</a> in <span id="countdown" style="color:red;">15</span> Seconds.</h4>
</center>
</div><!--end wrapper-->
<?php include('../include/footer.html'); ?>
</body>
</html>