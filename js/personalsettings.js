function toggleDav() {
	// See http://stackoverflow.com/questions/3038901/how-to-get-the-response-of-xmlhttprequest
	var xhr = new XMLHttpRequest();
	xhr.onreadystatechange = function() {
	    if (xhr.readyState == 4) {
	        //alert(xhr.responseText);
	    }
	}
	xhr.open('GET', OC.webroot + '/apps/chooser/ajax/toggle_dav.php', true);
	xhr.send();
}
$(document).ready(function(){
  $("#allow_internal_dav").click(function(){
  	toggleDav();
    //alert( $(this).attr("id") );
  });
});