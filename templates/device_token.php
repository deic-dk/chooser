<?php
$themepath = OC::$WEBROOT.'/themes/'.OC_Util::getTheme();
?>
<header>
	<div id="header" class="text-center">
		<h1>Authorize device</h1>
		<br>
	</div>
</header>

<div class="content">
	<div class="row">
<?php if (isset($_['content'])): ?>
		<div class="col-xs-12">
	<?php print_unescaped($_['content']) ?>
		</div>
<?php else: ?>
		<div class="col-sm-6 col-xs-10 col-sm-offset-3 col-xs-offset-1">
			<span class="text-center text-white">
				<?php p($l->t('Device name: ')); ?>
			</span>
			<select id='device_token' data-inputtitle="<?php p($l->t('Please choose a name for your device')) ?>">
				<option>
				</option>
				<?php foreach($_['device_tokens'] as $device_name=>$token):?>
					<option value='<?php p($_['device_token']);?>'>
						<?php p(substr($device_name, 13));?>
					</option>
				<?php endforeach;?>
				<option data-new="true" value='<?php p($_['device_token']); ?>'>
					<?php p($l->t('New'));?> ...
				</option>
			</select>
			<p />
			<div>
				<button class="btn btn-default btn-flat"
				id="authorize_device" server="<?php p($_['server']);?>" user="<?php p($_['user']);?>">
				<?php p($l->t("Authorize device"));?></button>
				<a class="btn btn-flat" href="/"><?php p($l->t("Cancel"));?></a>
				<p id='chooser_msg'></p>
			</div>
		</div>
<?php endif; ?>
<?php if (isset($_['flow'])): ?>
<input type="hidden" id="flow" value="true" />
<?php endif; ?>
	</div>
</div>
