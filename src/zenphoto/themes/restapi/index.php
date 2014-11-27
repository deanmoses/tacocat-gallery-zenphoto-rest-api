<?php
if (!defined('WEBPATH'))
	die();

header("content-type: application/json; charset: "+LOCAL_CHARSET);
?>
{
	title: "<?php
					printHomeLink('', ' | ');
					printGalleryTitle();
					?>",
					description: "<?php printGalleryDesc(); ?>"
					
}