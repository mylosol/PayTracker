// JavaScript Document
function firstEmail()
{
	var password = document.getElementById('newEmail1');
	var passMessage = document.getElementById('emailcheck');
	
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

}


function checkEmail()
{
    //Store the password field objects into variables ...
    var pass1 = document.getElementById('newEmail1');
    var pass2 = document.getElementById('newEmail2');
    //Store the Confimation Message Object ...
    var message = document.getElementById('confirmMessage');
	var ce = document.getElementById('ces');
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
        message.innerHTML = "Email Addresses Match!"
		ce.innerHTML = "<input type=\"submit\" name=\"reset\" value=\"Change Email Address\" class=\"myButton threeQuarterB blueB\" />";
    }else{
        //The passwords do not match.
        //Set the color to the bad color and
        //notify the user.
        pass2.style.backgroundColor = badColor;
        message.style.color = badColor;
        message.innerHTML = "Email Addresses Do Not Match!"
    }
}

function validateForm()
    {
    var a=document.forms["Form"]["p"].value;
    var b=document.forms["Form"]["newEmail1"].value;
    var c=document.forms["Form"]["newEmail2"].value;
    if (a==null || a=="",b==null || b=="",c==null || c=="")
      {
      alert("Please Fill All Required Field");
      return false;
      }
    }