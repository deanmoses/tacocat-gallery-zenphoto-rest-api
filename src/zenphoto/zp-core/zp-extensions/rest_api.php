<?php

/**
 * Adds a JSON REST API to ZenPhoto to support building mobile apps,
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
	
	// Album getAlbum( int $index  )
	// makeAlbumCurrent(someAlbum)
	
	
	$album = array();
	$album['path'] = $_zp_current_album->name;
	$album['title'] = $_zp_current_album->getTitle();
	$album['description'] = $_zp_current_album->getDesc();
	$album['published'] = $_zp_current_album->getShow();
	$album['date'] = $_zp_current_album->getDateTime();
	$album['thumb'] = toThumbApi($_zp_current_album->getAlbumThumbImage());
	$albums = array();
	while (next_album()):
		$albums[] = toChildAlbumApi($_zp_current_album);
	endwhile;
	$album['albums'] = $albums;
	$images = array();
	while (next_image()):
		$images[] = toImageApi($_zp_current_image);
	endwhile;
	$album['images'] = $images;
	print(json_encode($album));
	exitZP();
}

// get just enough info about an image to render it on a standalone page
function toImageApi($image) {
	$ret = array();
	// strip /zenphoto/albums/ so that the path starts something like 2014/...
	$ret['path'] = str_replace('/zenphoto/albums/', '', $image->getFullImage());
	$ret['url'] = getImageURL(); // relies on $_zp_current_image being set correctly
	$ret['title'] = $image->getTitle();
	$ret['description'] = $image->getDesc();
	$ret['date'] = $image->getDateTime();
	return $ret;
}

// get just enough info about a child album to render it as thumbnails
function toChildAlbumApi($album) {
	$ret = array();
	$ret['path'] = $album->name;
	$ret['title'] = $album->getTitle();
	$ret['description'] = $album->getDesc();
	$ret['published'] = $album->getShow();
	$ret['date'] = $album->getDateTime();
	$ret['thumb'] = toThumbApi($album->getAlbumThumbImage());
	return $ret;
}

// get just enough info about the thumbnail version of an image to render it
function toThumbApi($image) {
	$thumb = array();
	$thumb['url'] = $image->getThumb();
	// would like to get width and height, but that seems to be a property
	// of the theme, and not easy to get...
	return $thumb;
}

?>