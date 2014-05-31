<?php require_once('apps/chooser/lib/lib_chooser.php'); ?>

<form id="chooserform">

	<fieldset class="personalblock">
		<strong><?php p($l->t('Secure unauthenticated WebDAV')); ?></strong><br />
		<?php p($l->t('WebDAV address from compute nodes:')); ?>
		<code><?php print_unescaped(OCP\Util::linkToRemote('chooser')); ?></code><br />

		<input type="checkbox" name="allow_internal_dav" id="allow_internal_dav" value="0"
			   title="<?php p($l->t( 'Needed for file access from virtual machines.' )); ?>"
			   <?php if (OC_Chooser::getEnabled() == 'yes'): ?> checked="checked"<?php endif; ?> />

		<label for="allow_internal_dav"><?php p($l->t( 'Enable' )); ?></label><br/>

	</fieldset>

</form>
