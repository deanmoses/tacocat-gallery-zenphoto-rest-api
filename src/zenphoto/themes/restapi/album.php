<?php
// force UTF-8 Ø
if (!defined('WEBPATH'))
	die();
header("content-type: application/json; charset: UTF-8");
?>
{
	title: "<?php printAlbumTitle(); ?>",
	description: "<?php printAlbumDesc(); ?>",
	url: "<?php echo html_encode(getAlbumURL()); ?>",
	albums: 
	[
	<?php while (next_album()): ?>
		{
		title: "<?php printAlbumTitle(); ?>",
		description: "<?php printAlbumDesc(); ?>",
		url: "<?php echo html_encode(getAlbumURL()); ?>"
		},
	<?php endwhile; ?>
	],
	images:
	[
	<?php while (next_image()): ?>
		{
		title: "<?php printBareImageTitle(); ?>",
		url: "<?php echo html_encode(getImageURL()); ?>",
		thumbUrl: "<?php echo html_encode(getImageThumb()); ?>"
		},
	<?php endwhile; ?>
	]
}