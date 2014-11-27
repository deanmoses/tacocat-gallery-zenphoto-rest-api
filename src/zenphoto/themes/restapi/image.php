<?php
// force UTF-8 Ø

if (!defined('WEBPATH'))
	die();
header("content-type: application/json; charset: UTF-8");
if (isImagePhoto()) {
						$fullimage = getFullImageURL();
					} else {
						$fullimage = NULL;
					}
?>
{
	imageUrl: "<?php echo html_encode(pathurlencode($fullimage)); ?>",
	imageTitle: "<?php printImageTitle(); ?>",
	imageDescription: "<?php printImageDesc(); ?>",
						<?php
					if (hasPrevImage()) {
						?>
	prevImageUrl: "<?php echo html_encode(getPrevImageURL()); ?>"
		<?php } ?>

						<?php
					 if (hasNextImage()) {
						?>
	nextImageUrl: "<?php echo html_encode(getNextImageURL()); ?>",
						<?php
					}
					?>
}