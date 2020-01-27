
function selectDevice(ev){
	if($('option:selected', this).attr("data-new")){
		OC.dialogs.prompt(t("chooser", "Please enter a name for your device"),
				"Device name",
				function(ok, arg){
					if(ok && arg){
						$('#device_token option:selected').text(arg);
					}
					else{
						// Cancel
					}
			}, true, "Name", false, "Continue", "Cancel");
		}
	}

function authorizeDevice(){
	var token = $('#device_token').val();
	var device_name = $('#device_token option:selected').text().trim();
	if(!device_name){
		OC.dialogs.alert(t("chooser", "Please select a device"), "Select device");
		return;
	}
	/*We don't use the stored tokens - as these are not tokens, but hashes of tokens.
					Just discard and use the new one - that's what the client expects.  */
	$.ajax(OC.linkTo('chooser','login.php'), {
		 type:'GET',
		  data:{
			  token: token,
			  device_name: device_name,
			  set_device_token: true
		 },
		 dataType:'json',
		 success: function(s){
			 if(s.error){
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

$(document).ready(function() {
	$('#authorize_device').click(function(ev){
		// Old case - redirect and have client pick up nc protocol. Apparently not supported anymore.
		if(false){
			var server = $('#authorize_device').attr('server');
			var user = $('#authorize_device').attr('user');
			var password = $('#device_token').val();
			window.location.href="nc://login/server:"+server+"&user:"+user+"&password:"+password;
		}
		else{
			authorizeDevice();
		}
		});
	$('#device_token').on('change', selectDevice);
});
