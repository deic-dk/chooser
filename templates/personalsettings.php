<?php OCP\Util::addStyle('chooser', 'personalsettings');?>

<fieldset class="section">
	<h2><?php p($l->t('WebDAV access control')); ?></h2>
	
	<?php p($l->t('Allow unauthenticated WebDAV access from compute nodes')); ?>
	<input type="checkbox" id="allow_internal_dav" value="0"
		   title="<?php p($l->t( 'Needed for file access from your virtual machines.' )); ?>"
		   <?php if ($_['is_enabled'] == 'yes'): ?> checked="checked"<?php endif; ?> />
		   
	<br />
	
	<?php p($l->t('Allow authentication with X.509 certificate: ')); ?>
	<input type="text" id="ssl_cert_dn"
		value="<?php print(isset($_['ssl_cert_dn'])?$_['ssl_cert_dn']:''); ?>"
		placeholder="Certificate subject" />
		<label id="chooser_settings_submit" class="button">Save</label>&nbsp;<label id="chooser_msg"></label>
</fieldset>

