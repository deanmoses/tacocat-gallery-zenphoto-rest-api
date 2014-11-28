<?php

/**
 * A JSON REST API for ZenPhoto.  Supports building mobile apps,
 * javascript-heavy web apps, and other types of integrations.
 *
 * This filter will detect an api=json parameter in the query string
 * and return a JSON representation of the album or photo instead of 
 * the normal HTML response.
 *
 *
 * The URL format is:<br>
 * <var>mod_rewrite</var><br>
 * 			/ <i>languageid</i> / <i>standard url</i><br>
 * <var>else</var><br>
 * 			<i>standard url</i>?locale=<i>languageid</i><br>
 * Where <i>languageid</i> is the local identifier (e.g. en, en_US, fr_FR, etc.)
 *
 *
 * @author Dean Moses (deanmoses)
 * @package plugins
 * @subpackage api
 */
$plugin_is_filter = 900 | FEATURE_PLUGIN;
$plugin_description = gettext('Allows retrieving albums via a REST API');
$plugin_author = "Dean Moses (deanmoses)";

// Handle API calls before anything else
if (!OFFSET_PATH && isset($_GET['api'])) {
	zp_register_filter('load_theme_script', 'executeRestApi', 9999);
}

function executeRestApi() {
	global $_zp_current_album, $_zp_current_image, $_zp_current_admin_obj, $_zp_current_category;
	header('Content-type: application/json; charset=UTF-8');
	$_zp_gallery_page = 'rest_api.php';

	$album = array();
	$album['path'] = $_zp_current_album->name;
	$album['title'] = $_zp_current_album->getTitle();
	$album['description'] = $_zp_current_album->getDesc();
	$album['published'] = (boolean) $_zp_current_album->getShow();
	$album['image_size'] = getOption('image_size');
	$album['thumb_size'] = getOption('thumb_size');
	
	//format:  2014-11-24 01:40:22
	$a = strptime($_zp_current_album->getDateTime(), '%Y-%m-%d %H:%M:%S');
	$album['date'] = mktime($a['tm_hour'], $a['tm_min'], $a['tm_sec'], $a['tm_mon']+1, $a['tm_mday'], $a['tm_year']+1900);
	
	$albums = array();
	while (next_album()):
		$albums[] = toChildAlbumApi($_zp_current_album);
	endwhile;
	if ($albums) {
		$album['albums'] = $albums;
	}
	
	$images = array();
	while (next_image()):
		$images[] = toImageApi($_zp_current_image);
	endwhile;
	if ($images) {
		$album['images'] = $images;
	}
	
	print(json_encode($album));
	exitZP();
}

// get just enough info about an image to render it on a standalone page
function toImageApi($image) {
	$ret = array();
	// strip /zenphoto/albums/ so that the path starts something like 2014/...
	$ret['path'] = str_replace('/zenphoto/albums/', '', $image->getFullImage());
	$ret['urlFull'] = $image->getFullImageURL();
	$ret['urlSized'] = $image->getSizedImage(getOption('image_size'));
	$ret['urlThumb'] = $image->getThumb();
	$ret['title'] = $image->getTitle();
	$ret['description'] = $image->getDesc();
	$a = strptime($image->getDateTime(), '%Y-%m-%d %H:%M:%S');
	$ret['date'] = mktime($a['tm_hour'], $a['tm_min'], $a['tm_sec'], $a['tm_mon']+1, $a['tm_mday'], $a['tm_year']+1900);
	return $ret;
}

// get just enough info about a child album to render it as thumbnails
function toChildAlbumApi($album) {
	$ret = array();
	$ret['path'] = $album->name;
	$ret['title'] = $album->getTitle();
	$ret['description'] = $album->getDesc();
	$ret['published'] = (boolean) $album->getShow();
	$thumbImage = $album->getAlbumThumbImage();
	if ($thumbImage) {
		$ret['urlThumb'] = $album->getAlbumThumbImage()->getThumb();
	}
	$a = strptime($album->getDateTime(), '%Y-%m-%d %H:%M:%S');
	$ret['date'] = mktime($a['tm_hour'], $a['tm_min'], $a['tm_sec'], $a['tm_mon']+1, $a['tm_mday'], $a['tm_year']+1900);
	
	return $ret;
}

?>