
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

function authorizeDevice(callback){
	var token = $('#device_token').val();
	var device_name = $('#device_token option:selected').text().trim();
	if(!device_name){
		OC.dialogs.alert(t("chooser", "Please select a device"), "Select device");
		return;
	}
	/*We don't use the stored tokens - as these are not tokens, but hashes of tokens.
					Just discard and use the new one - that's what the client expects.  */
	var loginLink = OC.linkTo('chooser','login.php');
	$.ajax(loginLink, {
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
				if(typeof callback !== 'undefined'){
					callback();
				}
			}
		},
		error:function(jqXHR, exception){
			 $('#chooser_msg').removeClass('success');
			// From https://stackoverflow.com/questions/6792878/jquery-ajax-error-function
			var msg = '';
			if (jqXHR.status === 0) {
				msg = 'Not connected. Verify Network. ';
			}
			else if (jqXHR.status == 404) {
				msg = 'Requested page not found. [404]';
			}
			else if (jqXHR.status == 500) {
				msg = 'Internal Server Error [500].';
			}
			else if (exception === 'parsererror') {
				msg = 'Requested JSON parse failed.';
			}
			else if (exception === 'timeout') {
				msg = 'Time out error.';
			}
			else if (exception === 'abort') {
				msg = 'Ajax request aborted.';
			}
			else {
				msg = 'Uncaught Error.' + jqXHR.responseText;
			}
			 OC.msg.finishedSaving('#chooser_msg', {status: 'error', data: {message: "Unexpected error: "+loginLink+"-->"+msg+"-->"+exception}});
		}
	});
}

function sendObjectMessage(url) {
  var iframe = document.createElement('iframe');
  iframe.setAttribute('src', url);
  document.documentElement.appendChild(iframe);
  iframe.parentNode.removeChild(iframe);
  iframe = null;
}

$(document).ready(function() {
	$('#authorize_device').click(function(ev){
		ev.stopPropagation();
		ev.preventDefault();
		// Android case - redirect and have client pick up nc protocol.
		//if($('input#flow').length){
			authorizeDevice(function(){
				var server = $('#authorize_device').attr('server');
				var user = $('#authorize_device').attr('user');
				var password = $('#device_token').val();
				//var url = "nc://login/server:"+server+"&user:"+user+"&password:"+password;
				//var url = "nc://login?server="+server+"&user="+user+"&password="+password;
				var url =OC.webroot+"/apps/chooser/login.php?server="+server+"&user="+user+"&password="+password;
				$('#done_link').attr("href", url);
				$('#done_link').show();
				window.location = url;
				//document.location.replace(url);
				//window.open(url, '_system');
				//sendObjectMessage(url);
			});
		/*}
		else{
			authorizeDevice();
		}*/
		});
	$('#device_token').on('change', selectDevice);
});
