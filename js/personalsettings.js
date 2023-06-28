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

function toggleNfs(){
	$.ajax(OC.linkTo('chooser','ajax/toggle_nfs.php'), {
		 type:'GET',
		  data:{
		 },
		 dataType:'json',
		 success: function(s){
			if(s==null){
				OC.msg.finishedSaving('#chooser_msg', {status: 'error', data: {}});
			}
			 else if(s.error){
				OC.msg.finishedSaving('#chooser_msg', {status: 'error', data: {message: s.error}});
			}
			else{
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

function saveDavPath(){
	var path = $('input#dav_path').val();
	if(typeof path === 'undefined'){
		OC.msg.finishedSaving('#chooser_msg', {status: 'error', data: {message: "Empty path"}});
		return false;
	}
	$.ajax(OC.linkTo('chooser','ajax/set_dav_path.php'), {
		 type:'POST',
		  data:{
		  	path: path
		 },
		 dataType:'json',
		 success: function(s){
			 if(s.error){
				 OC.msg.finishedSaving('#chooser_msg', {status: 'success', data: {message: s.error}});
			 }
			 else{
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
	});

	$('#show_storage_nfs').click(function(){
		toggleNfs();
	});

	$('#chooser_dn_submit').click(function(ev){
		saveSubject();
	});
	
	$('#chooser_dav_path_submit').click(function(ev){
		saveDavPath();
	});

});