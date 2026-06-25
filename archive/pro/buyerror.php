<?php 
include ('../include/db.php');
include ('../include/cookiecheck.php');
include('../include/meta.html'); ?> 
<title>Pay Tracking Portal</title>
<link href="../style.css" rel="stylesheet" type="text/css" />
</head>

<body>
<noscript>
<h1 style="text-align: center;">Javascript Must Be Enabled To Use This Site</h1>
</noscript>
<?php include ('../include/topMenuPro.php'); 
$pd = date("F jS, Y", strtotime($paidDate));
$getAccountQuery = $conn->query("SELECT user FROM account WHERE id = ".$id." LIMIT 1");
while ($row = $getAccountQuery->fetch_assoc()) { $email = $row["user"]; }
?>
    		
<div id="wrapper" class="largeContain justText tac">

<h3>Oh No! Something Has Gone <span style="color:#F00;">Catastrophically</span> Wrong!</h3>
<h4>Your account is currently paid until <span class="dateLook1"><?php echo $pd; ?></span>
<h4>If you paid for time and are seeing this message, and the date above does not reflect the additional time you need to <a href="mailto:help@'.$_SERVER['SERVER_NAME'].'?subject=Account Issue&body=An error occurred while trying to add time to my account for <?php echo $email; ?> - Please Help!">contact the webmaster</a> for assistance!</h4>



</div><!--end wrapper-->
        

<?php include('../include/footer.html'); ?>
</body>
</html>