<fieldset class="section">
	<h2><?php p($l->t('WebDAV access control')); ?></h2>
	<?php p($l->t('Allow unauthenticated WebDAV access from compute nodes')); ?>
	<input type="checkbox" name="allow_internal_dav" id="allow_internal_dav" value="0"
		   title="<?php p($l->t( 'Needed for file access from virtual machines.' )); ?>"
		   <?php if ($_['is_enabled'] == 'yes'): ?> checked="checked"<?php endif; ?> />
</fieldset>

