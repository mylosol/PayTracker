// JavaScript Document
function notPaid(theCheckbox, theDiv) {
	var checkbox = document.getElementById(theCheckbox);
	var div = document.getElementById(theDiv);
	
	if (checkbox.checked) {
		div.innerHTML = "";
	} else {
		div.innerHTML = "<img src=\"../images/x.png\" height=\"100%\" width=\"100%\">";
	}
}
