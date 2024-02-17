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

function addSubject(dn){
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
				$('#chooser_active_dns').append('<div class="chooser_active_dn"><label class="text">'+dn+
						'</label></div>');
			 }
		 },
		error:function(s){
			 $('#chooser_msg').removeClass('success');
			 OC.msg.finishedSaving('#chooser_msg', {status: 'error', data: {message: "Unexpected error"}});
		}
	});
}

function removeSubject(dn){
	if(typeof dn === 'undefined' || !dn){
		OC.msg.finishedSaving('#chooser_msg', {status: 'error', data: {message: "Empty subject"}});
		return false;
	}
	$.ajax(OC.linkTo('chooser','ajax/remove_cert_dn.php'), {
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
				$('.chooser_active_dn label[dn="'+s.dn+'"]').parent().remove();
			 }
		 },
		error:function(s){
			 $('#chooser_msg').removeClass('success');
			 OC.msg.finishedSaving('#chooser_msg', {status: 'error', data: {message: "Unexpected error"}});
		}
	});
}

function generateCert(){
	var days = $('#ssl_days').val();
	if(!/^-?\d+$/.test(days) || days==0){
		OC.msg.finishedSaving('#chooser_msg', {status: 'error', data: {message: "Please input the number of days your certificate should be valid!"}});
		return;
	}
	$.ajax(OC.linkTo('chooser','ajax/generate_cert.php'), {
		 type:'GET',
		 data:{
			days: parseInt(days)
		 },
		 dataType:'json',
		 success: function(s){
			 if(s.error || !s.dn){
				OC.msg.finishedSaving('#chooser_msg', {status: 'error', data: {message: s.error}});
			 }
			 else{
				$('#chooser_msg').show();
				$('#chooser_msg').removeClass('error');
				$('#chooser_sd_cert').removeClass('hidden');
				$('#chooser_sd_cert_dn').text(s.dn);
				$('#chooser_sd_cert_expires').text(s.expires);
				OC.msg.finishedSaving('#chooser_msg', {status: 'success', data: {message: s.message}});
				$('#chooser_sd_cert_dn').text(s.dn);
				addSubject(s.dn);
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
		var dn = $('input#ssl_cert_dn').val();
		addSubject(dn);
	});
	
	$('#chooser_sd_cert_generate').click(function(ev){
		generateCert();
	});
	
	$('#chooser_dav_path_submit').click(function(ev){
		saveDavPath();
	});
	
	$('.chooser_active_dn').click(function(ev){
		removeSubject($(ev.target).attr('dn'));
	});

});