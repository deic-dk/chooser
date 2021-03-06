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

function saveSubject(){
	var dn = $('input#ssl_cert_dn').val();
	if(typeof dn === 'undefined'){
		OC.msg.finishedSaving('#chooser_msg', {status: 'error', data: {message: "Empty subject"}});
		return false;
	}
	$.ajax(OC.linkTo('chooser','ajax/set_cert_dn.php'), {
		 type:'POST',
		  data:{
			  dn: dn
		 },
		 dataType:'json',
		 success: function(s){
			 if(s.error){
				 OC.msg.finishedSaving('#chooser_msg', {status: 'success', data: {message: s.error}});
			 }
			 else{
				 //$("#chooser_msg").html("Subject saved");
				 $('#chooser_msg').show();
				 $('#chooser_msg').removeClass('error');
				 OC.msg.finishedSaving('#chooser_msg', {status: 'success', data: {message: s.message}});
			 }
		 },
		error:function(s){
			 $('#chooser_msg').removeClass('success');
			 OC.msg.finishedSaving('#chooser_msg', {status: 'error', data: {message: "Unexpected error"}});
		}
	});
}

$(document).ready(function(){
  $('#allow_internal_dav').click(function(){
  	toggleDav();
    //alert( $(this).attr("id") );
  });
  
  $('#chooser_settings_submit').click(function(ev){
  	saveSubject();
  });
  
});