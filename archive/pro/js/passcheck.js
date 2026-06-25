function checkMail()
{
	var email = document.getElementById('email');
	var filledMessage = document.getElementById('filled');
	
    var goodColor = "#66cc66";
    var badColor = "#ff6666";
	var emptyColor = "#FFF";
	
	if (email) {

		email.style.backgroundColor = goodColor;
		filledMessage.innerHTML = "<img src=\"../images/checkmark.png\" border=\"0\" height=\"14\" width=\"20\">";
		
	} 
	
	if (email==null || email=="") {
		
		email.style.backgroundColor = badColor;
		filledMessage.innerHTML = "&nbsp;";
		
	}//end if (email)

}//end checkMail

function firstPass()
{
	var password = document.getElementById('pass1');
	var passMessage = document.getElementById('passcheck');
	
    var goodColor = "#66cc66";
    var badColor = "#ff6666";
	var emptyColor = "#FFF";
	
	if (password) {

		password.style.backgroundColor = goodColor;
		passMessage.innerHTML = "<img src=\"../images/checkmark.png\" border=\"0\" height=\"14\" width=\"20\">";
		
	} else {
		
		password.style.backgroundColor = emptyColor;
		passMessage.innerHTML = "&nbsp;";
		
	}//end if (email)

}//end firstPass


function checkPass()
{
    //Store the password field objects into variables ...
    var pass1 = document.getElementById('pass1');
    var pass2 = document.getElementById('pass2');
    //Store the Confimation Message Object ...
    var message = document.getElementById('confirmMessage');
    //Set the colors we will be using ...
    var goodColor = "#66cc66";
    var badColor = "#ff6666";
    //Compare the values in the password field 
    //and the confirmation field
    if(pass1.value == pass2.value){
        //The passwords match. 
        //Set the color to the good color and increate
        //the user that they have entered the correct password 
        pass2.style.backgroundColor = goodColor;
        message.style.color = goodColor;
        message.innerHTML = "Passwords Match!"
    }else{
        //The passwords do not match.
        //Set the color to the bad color and
        //notify the user.
        pass2.style.backgroundColor = badColor;
        message.style.color = badColor;
        message.innerHTML = "Passwords Do Not Match!"
    }//end if(pass1.value == pass2.value)
}//end checkPass

function agreed(theCheckbox, theDiv) {
	var checkbox = document.getElementById(theCheckbox);
	var div = document.getElementById(theDiv);
	
	if (checkbox.checked) {
		div.innerHTML = "<input type=\"submit\" name=\"done\" class=\"myButton threeQuarterB blueB\" value=\"Submit\" />";
	} else {
		div.innerHTML = "<h4>You Must Agree to Register!</h4>";
	}
}


function validateForm()
    {
    var a=document.forms["Form"]["email"].value;
    var b=document.forms["Form"]["pass1"].value;
    var c=document.forms["Form"]["pass2"].value;
	
	  if (a==null || a=="",b==null || b=="",c==null || c=="")
		{
		alert("Please Fill All Required Fields");
		return false;
		}//end if (a==null || a=="",b==null || b=="",c==null || c=="")
		
    }//end validateForm