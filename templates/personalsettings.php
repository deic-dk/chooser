<?php
OCP\Util::addStyle('chooser', 'personalsettings');
$l = OC_L10N::get('chooser');
?>

<fieldset class="section">
	<h2><?php p($l->t('Data processing')); ?></h2>
	
	<?php p($l->t('Allow internal HTTP access from your own pods')); ?>
	<input type="checkbox" id="allow_internal_dav" value="0"
		title="<?php p($l->t( 'Needed for unauthenticated file access from your pods/containers' )); ?>"
	<?php if ($_['dav_enabled'] == 'yes'): ?> checked="checked"<?php endif; ?> />

	<br />

	<?php p($l->t('Restrict internal pod HTTP access to:').' '); ?>
	<input type="text" id="dav_path"
		value="<?php print(isset($_['dav_path'])?$_['dav_path']:''); ?>"
		placeholder="<?php p($l->t('Path'));?>" />
	<label id="chooser_dav_path_submit" class="button"><?php p($l->t("Save"));?></label>

	<br />

	<?php if(OC_App::isEnabled('files_external')){ p($l->t('Browse /storage in web interface')); ?>
	<input type="checkbox" id="show_storage_nfs" value="0"
		title="<?php p($l->t( 'Show the persistent storage for your pods/containers' )); ?>"
		<?php if ($_['storage_enabled'] == 'yes'): ?> checked="checked"<?php endif; }?> />

	<br />

	<?php p($l->t('Generate new personal X.509 certificate:').' '); ?>
	<input type="text" id="ssl_days"
		placeholder="<?php p($l->t('Days of validity'));?>" />
	<label id="chooser_sd_cert_generate" class="button"><?php p($l->t("Generate"));?></label>
	<label><?php p($l->t("Existing"));?>:</label>
	<span class="chooser_sd_cert<?php if(empty($_['sd_cert_dn'])){ ?> hidden<?php } ?>">DN:</span><label id="chooser_sd_cert_dn" class="text"><?php echo($_['sd_cert_dn']);?></label>
	<span class="chooser_sd_cert<?php if(empty($_['sd_cert_dn'])){ ?> hidden"<?php } ?>">Expires:</span><label id="chooser_sd_cert_expires" class="text"><?php echo($_['sd_cert_expires']);?></label>
	
	<br />

	<?php p($l->t('Allow authentication with external X.509 certificate:').' '); ?>
	<input type="text" id="ssl_cert_dn"
		value=""
		placeholder="<?php p($l->t('Certificate subject DN'));?>" />
	<label id="chooser_dn_submit" class="button"><?php p($l->t("Add"));?></label>

	<br />
	
	<b>Accepted DNs</b>:<br />
	<div id="chooser_active_dns">
	<?php
	foreach($_['ssl_active_dns'] as $dn){
	?>
		<div class="chooser_active_dn">
			<label class="text"><?php echo($dn);?></label>
			<label class="chooser_dn_deactivate btn btn-flat" dn="<?php echo($dn);?>" title="<?php p($l->t('Remove'));?>">-</label>
		</div>
	<?php } ?>
	</div>
	
	<label id="chooser_msg"></label>
</fieldset>

