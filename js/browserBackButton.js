$(window).on("unload", function(e) {
	history.pushState(null, null, null);
});
/*
 * $(window).unload(function() {
	history.pushState(null, null, null);
});
*/
	function viewport() {
	    var e = window, a = 'inner';
	    if (!('innerWidth' in window )) {
	        a = 'client';
	        e = document.documentElement || document.body;
	    }
	    return { width : e[ a+'Width' ] , height : e[ a+'Height' ] };
	}
	ww = viewport().width;
		var popup_width= 0;
		var popup_top= 0;
		var popup_left=0;

	if (ww <=480) {
		  popup_width= 312;
		  popup_top= 148;
		  popup_left=0;
	}
	else if(ww <= 768){
		  popup_width= 450;
		  popup_top= 102;
		  popup_left=105;
	}
	else if(ww <= 1024){
		  popup_width= 450;
		  popup_top= 102;
		  popup_left=105;
	}
	else {
		  popup_width= 450;
		  popup_top= 102;
		  popup_left=105;
	}  
	
	$(document).ready(function($) {
			var preventDouble = 0;
			var url =window.location.href;
			history.pushState(null, null, null);	
			//to avoid double hit issue
			//history.pushState(null, null, null);	
			if (window.history && window.history.pushState) {
				$(window).on('popstate', function() {
				  var hashLocation = location.hash;
				  var hashSplit = hashLocation.split("#!/");
				  var hashName = hashSplit[1];
				  if (hashName !== '') {
					var hash = window.location.hash;
					if (hash === '') {
					 history.pushState(null, null, null);	
					 // $(location).attr('href', '${flowExecutionUrl}&_eventId=back');	
						preventDouble++;
						if(preventDouble==1){
							var modalObj = "<div id='modal_pop'><div class='ui-datablocknew' ><p class='ui-messagenew'>Browser Controls Inactive</p><p >Sorry - you can't use your browser's Back button to return to a previous screen.</p></div></div>";
						}
							$(modalObj).dialog({
									modal : true,
									width: popup_width,
                                    top: popup_top,
                                    left: popup_left,
									height : 200,
									position: ['center',20],
									closeOnEscape : false,							
									draggable : false,
									resizable : false,
									dialogClass: "backButtonModel",
									buttons : {
										'Ok' : function() {
											$(this).dialog('close');
											preventDouble = 0;
											//$('.ui-dialog .ui-dialog-buttonpane browserbackbutton').css('float','left');
										}
									},
									title : 'Warning!'
							});
							$('.ui-dialog .ui-dialog-buttonpane browserbackbutton').css('float','right').css("margin-right","110px");	
					}
				  }
				});
			} 
	});
	
