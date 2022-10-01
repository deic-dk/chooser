<?php
OCP\Util::addStyle('chooser', 'personalsettings');
$l = OC_L10N::get('chooser');
?>

<fieldset class="section">
	<h2><?php p($l->t('Data processing access control')); ?></h2>
	
	<?php p($l->t('Allow HTTP access from your own pods')); ?>
	<input type="checkbox" id="allow_internal_dav" value="0"
		   title="<?php p($l->t( 'Needed for unauthenticated file access from your pods/containers' )); ?>"
		   <?php if ($_['dav_enabled'] == 'yes'): ?> checked="checked"<?php endif; ?> />

	<br />

	<?php if(OC_App::isEnabled('files_external')){ p($l->t('Browse /storage in web interface')); ?>
	<input type="checkbox" id="show_storage_nfs" value="0"
		   title="<?php p($l->t( 'Show the persistent storage for your pods/containers' )); ?>"
		   <?php if ($_['storage_enabled'] == 'yes'): ?> checked="checked"<?php endif; }?> />

	<br />

	<?php p($l->t('Allow authentication with your personal X.509 certificate:').' '); ?>
	<input type="text" id="ssl_cert_dn"
		value="<?php print(isset($_['ssl_cert_dn'])?$_['ssl_cert_dn']:''); ?>"
		placeholder="<?php p($l->t('Certificate subject'));?>" />
		<label id="chooser_settings_submit" class="button"><?php p($l->t("Save"));?></label>&nbsp;<label id="chooser_msg"></label>
</fieldset>

