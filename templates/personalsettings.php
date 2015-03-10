<?php require_once('apps/chooser/lib/lib_chooser.php'); ?>

<form id="chooserform">

	<fieldset class="section">
		<h2><?php p($l->t('WebDAV sccess control')); ?></h2>
		<?php p($l->t('Allow unauthenticated WebDAV access from compute nodes')); ?>
		<input type="checkbox" name="allow_internal_dav" id="allow_internal_dav" value="0"
			   title="<?php p($l->t( 'Needed for file access from virtual machines.' )); ?>"
			   <?php if (OC_Chooser::getEnabled() == 'yes'): ?> checked="checked"<?php endif; ?> />
	</fieldset>

</form>
