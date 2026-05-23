function notePopup(theDiv, theNote, theClose) {
  // Get the modal
  var modal = document.getElementById(theDiv);
  
  // Get the button that opens the modal
  var btn = document.getElementById(theNote);
  
  // Get the <span> element that modal-closes the modal
  var span = document.getElementById(theClose);
  
  // When the user clicks the button, open the modal
  btn.onclick = function() {
	  modal.style.display = "block";
  }
  
  // When the user clicks on <span> (x), modal-close the modal
  span.onclick = function() {
	  modal.style.display = "none";
  }
  
  // When the user clicks anywhere outside of the modal, modal-close it
  window.onclick = function(event) {
	  if (event.target == modal) {
		  modal.style.display = "none";
	  }
  }
}//end function
