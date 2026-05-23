// JavaScript Document
function addDemurrage(theCheckbox, theDiv, theInstructions, theNotice) {

    var checkboxvar = document.getElementById(theCheckbox);
    var divVar = document.getElementById(theDiv);
	var instructions = document.getElementById(theInstructions);
	var notice = document.getElementById(theNotice);
    if (checkboxvar.checked) {
        divVar.innerHTML = "<br /><input type=\"number\" name=\"demTime\" maxlength=\"3\" value=\"0\" step=\"1\" class=\"dem\" /><br />";
		instructions.innerHTML = "<span class=\"instructions\">Input as whole numbers in minutes.</span><br />";
		notice.innerHTML = "<span class=\"notice\">You need to -45 minutes from total time before input.</span>";
    } 
    else {
		divVar.innerHTML = "";
		instructions.innerHTML = "<br />";
		notice.innerHTML = "";
    }
}

function addBreakdown(theCheckbox, theDiv, theInstructions) {

    var checkboxvar = document.getElementById(theCheckbox);
    var divVar = document.getElementById(theDiv);
	var instructions = document.getElementById(theInstructions);
    if (checkboxvar.checked) {
        divVar.innerHTML = "<br /><input type=\"number\" name=\"breakTime\" maxlength=\"3\" value=\"0\" step=\"1\" class=\"dem\" /><br />";
		instructions.innerHTML = "<span class=\"instructions\">Input as whole numbers in minutes.</span><br />";
    } 
    else {
		divVar.innerHTML = "";
		instructions.innerHTML = "<br />";
    }
}

function addSplit(theCheckbox, theStop, theDel) {
	
    var checkboxvar = document.getElementById(theCheckbox);
    var firstStop = document.getElementById(theStop);
    var delivery = document.getElementById(theDel);
	
    if (checkboxvar.checked) {
		delivery.style.display = 'inherit'; // show
		document.getElementById("deliveryLocation").innerHTML = "Second Stop:";    
	} 
    else {
		delivery.style.display = 'none'; // hide
		document.getElementById("deliveryLocation").innerHTML = "Delivery Location:";
    }
}

function addDeadhead(theCheckbox, theDiv) {
	
    var checkboxvar = document.getElementById(theCheckbox);
    var divVar = document.getElementById(theDiv);
	
    if (checkboxvar.checked) {
		divVar.innerHTML = 'Begin Empty: <select name="beginEmpty"><option value="Panama City, FL">Panama City, FL</option><option value="Niceville, FL">Niceville, FL</option><option value="Freeport, FL">Freeport, FL</option><option value="Pensacola, FL">Pensacola, FL</option></select><br /><br />';    
	} 
    else {
		divVar.innerHTML = "";
    }
}

function addExtra(theCheckbox, theDiv) {
    var checkboxvar = document.getElementById(theCheckbox);
    var divVar = document.getElementById(theDiv);
	
    if (checkboxvar.checked) {
		divVar.innerHTML = '<select name="extraChoice"><option value="5">$5</option><option value="10">$10</option><option value="12.5">$12.50</option><option value="15">$15</option><option value="20">$20</option><option value="25">$25</option><option value="30">$30</option><option value="50">$50</option><option value="75">$75</option></select>';    
	} 
    else {
		divVar.innerHTML = "";
    }
}