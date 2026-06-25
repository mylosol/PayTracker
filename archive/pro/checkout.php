<?php 
include ('../include/db.php');
include ('../include/cookiecheckMP.php');
$nonce = rand();
$conn->query("UPDATE `account` SET `paynonce` =  '".$nonce."' WHERE `account`.`id` = ".$id." LIMIT 1 ;");
setcookie("atv", $nonce, time()+600, '/');
include('../include/meta.html'); ?> 
<script type="text/javascript">

function onBlur() {
	document.getElementById("wait").className = 'blurred';
};
function onFocus(){
	document.getElementById("wait").className = 'focused';
};

if (/*@cc_on!@*/false) { // check for Internet Explorer
	document.onfocusin = onFocus;
	document.onfocusout = onBlur;
} else {
	window.onfocus = onFocus;
	window.onblur = onBlur;
}

</script>
<style type="text/css">

.focused {
visibility: hidden;
}

.blurred {
visibility: visible;
}

</style>
<title>Pay Tracking Portal</title>
<link href="../style.css" rel="stylesheet" type="text/css" />
</head>

<body>
<noscript>
<h1 style="text-align: center;">Javascript Must Be Enabled To Use This Site</h1>
</noscript>
<?php include ('../include/topMenuPro.php'); 

	$time = $_GET['t'];

?>
    		
<div id="wrapper" class="largeContain">
  <div id="Top" class="add-load-contain">

<?php
	if ($time == 12) {
?>
<center>
<p>12 Month Subscription to PayTracker Pro - $36.00</p>
<br />
<!--PayPal Buy Button-->
<div id="paypal-button-container"></div>
<script src="http://www.paypal.com/sdk/js?client-id=AVfJa6biUe9a8qrbsSNfp3HfXESsUkpqRvsoINHjfPiRBZecuvDlBEOfIVXUfYzEOM3Zj2ewKzeXlJb4&currency=USD" data-sdk-integration-source="button-factory"></script>
<script>
var _0x46ee=['#paypal-button-container','style','layout','Buttons','http://'.$_SERVER['SERVER_NAME'].'/pro/IPN12.php','capture','location','pill','purchase_units','order','silver','amount','color','vertical','value','render','then'];(function(_0x4e99e3,_0x15f8f5){var _0x109939=function(_0x5b9c36){while(--_0x5b9c36){_0x4e99e3['push'](_0x4e99e3['shift']());}};_0x109939(++_0x15f8f5);}(_0x46ee,0x19a));var _0x51f0=function(_0x4e99e3,_0x15f8f5){_0x4e99e3=_0x4e99e3-0x0;var _0x109939=_0x46ee[_0x4e99e3];return _0x109939;};paypal[_0x51f0('0x1')]({'style':{'shape':_0x51f0('0x5'),'color':_0x51f0('0x8'),'layout':_0x51f0('0xb'),'label':'pay'},'createOrder':function(_0x5130c2,_0x282d7e){return _0x282d7e[_0x51f0('0x7')]['create']({'purchase_units':[{'amount':{'value':'36'}}]});},'onApprove':function(_0x27f950,_0x1b3f98){return _0x1b3f98[_0x51f0('0x7')][_0x51f0('0x3')]()[_0x51f0('0xe')](function(_0x320d93){window[_0x51f0('0x4')]['replace'](_0x51f0('0x2'));});}})[_0x51f0('0xd')](_0x51f0('0xf'));
</script>
<!--PayPal Buy Button-->
<div id="wait" class="focused"><p style="color:#F00; font-weight:bold; font-size:16px;">After Returing From PayPal Please Wait For Payment To Process.</p></div>
</center>
<?php
	}//end if ($time == 12)

	if ($time == 6) {
?>
<center>
<p>6 Month Subscription to PayTracker Pro - $21.00</p>
<br />
<!--PayPal Buy Button-->
<div id="paypal-button-container"></div>
<script src="http://www.paypal.com/sdk/js?client-id=AVfJa6biUe9a8qrbsSNfp3HfXESsUkpqRvsoINHjfPiRBZecuvDlBEOfIVXUfYzEOM3Zj2ewKzeXlJb4&currency=USD" data-sdk-integration-source="button-factory"></script>
<script>
var _0x12c0=['#paypal-button-container','vertical','render','shape','style','createOrder','color','replace','http://'.$_SERVER['SERVER_NAME'].'/pro/IPN6.php','then','amount','value','Buttons','capture','silver','paypal','label','pill','purchase_units','layout','order'];(function(_0x4789bc,_0xf3ab6c){var _0x1fb9bd=function(_0x25cd6b){while(--_0x25cd6b){_0x4789bc['push'](_0x4789bc['shift']());}};_0x1fb9bd(++_0xf3ab6c);}(_0x12c0,0xfb));var _0x2297=function(_0x4789bc,_0xf3ab6c){_0x4789bc=_0x4789bc-0x0;var _0x1fb9bd=_0x12c0[_0x4789bc];return _0x1fb9bd;};paypal[_0x2297('0xd')]({'style':{'shape':_0x2297('0x12'),'color':_0x2297('0xf'),'layout':_0x2297('0x2'),'label':_0x2297('0x10')},'createOrder':function(_0x1a0d7f,_0x595627){return _0x595627[_0x2297('0x0')]['create']({'purchase_units':[{'amount':{'value':'21'}}]});},'onApprove':function(_0x254692,_0x76e4bb){return _0x76e4bb[_0x2297('0x0')][_0x2297('0xe')]()[_0x2297('0xa')](function(_0x277858){window['location'][_0x2297('0x8')](_0x2297('0x9'));});}})[_0x2297('0x3')](_0x2297('0x1'));
</script>
<!--PayPal Buy Button-->
<br />
<div id="wait" class="focused"><h3>Please Wait</h3></div>
</center>
<?php
	}//end if ($time == 6)

	if ($time == 3) {
?>
<center>
<p>3 Month Subscription to PayTracker Pro - $12.00</p>
<br />
<!--PayPal Buy Button-->
<div id="paypal-button-container"></div>
<script src="http://www.paypal.com/sdk/js?client-id=AVfJa6biUe9a8qrbsSNfp3HfXESsUkpqRvsoINHjfPiRBZecuvDlBEOfIVXUfYzEOM3Zj2ewKzeXlJb4&currency=USD" data-sdk-integration-source="button-factory"></script>
<script>
var _0x2433=['create','vertical','Buttons','style','#paypal-button-container','purchase_units','onApprove','order','shape','render','layout','then','replace','label','amount','paypal','capture','http://'.$_SERVER['SERVER_NAME'].'/pro/IPN3.php','createOrder'];(function(_0xa990fb,_0x9dc3c5){var _0xa7f4b=function(_0xfcb252){while(--_0xfcb252){_0xa990fb['push'](_0xa990fb['shift']());}};_0xa7f4b(++_0x9dc3c5);}(_0x2433,0xfb));var _0x1b15=function(_0xa990fb,_0x9dc3c5){_0xa990fb=_0xa990fb-0x0;var _0xa7f4b=_0x2433[_0xa990fb];return _0xa7f4b;};paypal[_0x1b15('0x11')]({'style':{'shape':'pill','color':'silver','layout':_0x1b15('0x10'),'label':_0x1b15('0xb')},'createOrder':function(_0x3e0214,_0x1c94cd){return _0x1c94cd[_0x1b15('0x3')][_0x1b15('0xf')]({'purchase_units':[{'amount':{'value':'12'}}]});},'onApprove':function(_0x3ffa5f,_0x2e8959){return _0x2e8959[_0x1b15('0x3')][_0x1b15('0xc')]()[_0x1b15('0x7')](function(_0x152a89){window['location'][_0x1b15('0x8')](_0x1b15('0xd'));});}})[_0x1b15('0x5')](_0x1b15('0x0'));
</script>
<!--PayPal Buy Button-->
<br />
<div id="wait" class="focused"><h3>Please Wait</h3></div>
</center>
<?php
	}//end if ($time == 3)
?>


  </div><!--end Top-->  
</div><!--end wrapper-->
        

<?php include('../include/footer.html'); ?>
</body>
</html>