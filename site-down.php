<?php
include('include/db.php'); 
$name = $_POST['name'];
$email = $_POST['email'];
$agree = $_POST['agree'];
$news = $_POST['newsletter'];
$done = $_POST['done'];
$error = $_GET['e'];
$updateSub = $_COOKIE['us'];

if (isset($agree)) { $agree = 1; } else { $agree = 0; }//end if (isset($agree))

if (isset($news)) { $news = 1; } else { $news = 0; }//end if (isset($news))

if (!isset($updateSub)) { $notSub = 0; } else { $notSub = 1; }//end if (!$updateSub)


if (isset($done)) {
	
	if (empty($name)) { header("Location: http://".$_SERVER['SERVER_NAME']."/site-down.php?e=2"); }//end if (!isset($name))
	
	if (empty($email)) { header("Location: http://".$_SERVER['SERVER_NAME']."/site-down.php?e=3"); }//end if (!isset($done))
	
	if ($agree == 0) { header("Location: http://".$_SERVER['SERVER_NAME']."/site-down.php?e=1"); } else {
	  $insertData = "INSERT INTO `Fix` (`id`, `name`, `email`, `opt-in`) VALUES (NULL, '".$name."', '".$email."', '".$news."');";
	}//end if (!$agree)
	  
	  if ($conn->query($insertData) === TRUE) {
		  setcookie("us", 1, time()+31536000);
		  header("Location: http://".$_SERVER['SERVER_NAME']."/site-down.php");
	  } else {
		  echo "Error: " . $insertData . "<br>" . $conn->error;
	  }//end if ($conn->query($insertData) === TRUE)
		
}else { 

$getProgressUpdate = "SELECT progress1 FROM upgrade WHERE id LIKE 1 LIMIT 1;";

$progressUpdate = $conn->query($getProgressUpdate);

while($row = $progressUpdate->fetch_assoc()) { 
	 	$updateResults = $row["progress1"];
}//end while($row = $progressUpdate->fetch_assoc()) 
	 
	 
include('include/meta.html'); ?> 
<title>Pay Tracker </title>
<link href="style.css" rel="stylesheet" type="text/css" />
<style> 
p {font-size:14px;} 

p.progress {
	color: #F60;
}

 
 .containO {
	 width: 250px;
	 height: 20px;
	 margin: 0 auto;
	 border: #F60;
 }
 
 .progress1 {
	  width: <?php echo $updateResults; ?>%;
	  height: 100%;
	  border-radius: 25px;
	  background: #0F0;
 }
 
 .pWords {
	 position: relative;
	 top: -25px;
	 left: 0;
 }
  
 
 <?php if ($error == 1) { ?>
 .tos {
	 color: #F00; 
	 font-weight:bold;
	 }
 <?php }//end if ($error == 1)  ?>
  </style>
</head>

<body>
      
      
 <div id="topMenuContain">
<div id="logo" class="logo"><img src="http://'.$_SERVER['SERVER_NAME'].'/images/logo.png" alt="Pay Tracker" class="logo" /></div>
 </div><!--end "topMenuContain"-->  
 
 
<div id="wrapper" class="largeContain">
  <div id="broken" class="add-load-contain" style=" text-align: center;">
        
        
        <div id="top image" align="center" style="margin: 15pt auto;">
        <img src="images/UpdateSmall.png" align="middle" />
        </div><!--end top image-->
        
        <hr />
        
        <div id="outerUpdateContain" class="containO">
        
          
          	<div id="progressUpdate" class="progress1" >&nbsp;</div>
			<div id="progresssWords" class="pWords" ><p class="progress">Progress <?php echo $updateResults; ?>%</p></div>          
          
        </div><!--end outerUpdateContain-->
        
              
        <hr />
        
        <h2>Update Center:</h2>
        <p>I wanted to put together this quick news and infomation page.  Here you can track the progress of the update and upgrade of PayTracker.  If you want to be notified when PayTracker is back online enter your email address below.
        <br />
        <br />
       
       <?php if ($notSub == 0) { ?>
       
           <div id="form" style="text-align:left;">
             
              <form method="post">
                  Name: <input type="text" size="30" name="name" />
                  <br /><br />
                  Email: <input type="text" size="30" name="email" />
                  <br /><br />
                  <input type="checkbox" name="newsletter" checked /> I would like to be included in any future promotional or sale emails.
                  <br /><br />
                  <input type="checkbox" name="agree" id="tosAgree">
                  	<span class="tos">I have read and agree to the <br /><a href="http://'.$_SERVER['SERVER_NAME'].'/pro/help.php#tos" title="Terms of Service">Terms of Service</a> and <a href="http://'.$_SERVER['SERVER_NAME'].'/pro/help.php#privacy" title="Privacy Policy">Privacy Policy</a></span><br />
                  <br /><br />
                  <input type="hidden" name="done" value="1">
                  <input type="submit" class="myButton wideB blueB" value="Subscribe" />
              </form>  
          </div><!--end form-->
        
       <?php } else {
		   echo "<h3>Be watching your inbox for updates</h3>";
	   }//end if ($notSub = 0) { ?>
        
    <br /><br />    
    <hr>
    <br />
    <h2>We're Sorry...</h2>
        <br />
        <p>PHP is the programming language that powers many websits, including this one. It continues to be refined and improved over the years. As of December 2018, PHP 5.6 and 7.0 are no longer supported by the PHP project. To improve the overall security of the webserver, my hosting provider is phasing out older and unsupported versions of PHP. Starting at the end of October 2019, they began upgrading PHP 5.6 and 7.0 to PHP 7.2 on all webservers.</p>
<hr />
<p>PayTracker was built using PHP 5.6 and when this upgrade took place it broke the site, fixing this will mean an entire re-write of PayTracker, which will involve many man hours on my part.</p>
<p>As a result of this, when PayTracker re-launches it will be behind a pay wall.</p>
<p>I apoloigize about that, I always wanted to keep PayTarcker free but the overhead of Hosting now coupled with the site redesign has become too large and I will have to pass some of that cost on to justify keeping the site online and up to date.</p>
<hr />
<p>I will try and keep everyone posted with updates.</p>
        
  </div><!--end wrapper-->
</div><!--end add-load-contain-->
       
<br />
</body>
</html>

<?php }//end if ($name) ?>