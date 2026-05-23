<?php 
include ('../include/db.php');
include ('../include/cookiecheckMP.php');
include('../include/meta.html'); 
$pro = $_COOKIE['pro'];
?> 
<title>Pay Tracking</title>
<link href="../style.css" rel="stylesheet" type="text/css" />
<script type="text/javascript" src="js/faq.js"></script>
</head>

<body>
<noscript>
<h1 style="text-align: center;">Javascript Must Be Enabled To Use This Site</h1>
</noscript>
<?php include ('../include/topMenu.php'); 

if (isset($pro)) { ?>

<div id="faqWrapper" class="largeContain">

<h1 style="text-align:center;">F.A.Q.</h1>
<?php include ('../include/faq.php'); ?>

</div><!--end faqWrapper-->

<div id="helpWrapper" class="largeContain justText">

<h1 class="tac">Contact Info</h1>
<h4>Found a bug, Glitch or Error?  Report it! <a href="mailto:bug@'.$_SERVER['SERVER_NAME'].'?subject=Bug Found" target="_blank">bug@'.$_SERVER['SERVER_NAME'].'</a></h4>

<hr />
<h4>Just need help?  Contact me. <a href="mailto:help@'.$_SERVER['SERVER_NAME'].'?subject=Help Request" target="_blank">help@'.$_SERVER['SERVER_NAME'].'</a></h4>

</div><!--end helpWrapper-->

<?php }//end if (isset($pro)) ?>

<a name="tos" class="anchor"></a>
<div id="tosWrapper" class="largeContain justText">

<?php include('../include/tos.html'); ?>

</div><!--end tosWrapper-->

<a name="privacy" class="anchor"></a>     
<div id="privacyWrapper" class="largeContain justText">

<?php include('../include/privacypolicy.html'); ?>

</div><!--end privacyWrapper-->


<?php include('../include/footer.html'); ?>
</body>
</html>