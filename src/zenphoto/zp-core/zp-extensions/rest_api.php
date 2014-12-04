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
	global $_zp_gallery, $_zp_current_album, $_zp_current_image, $_zp_albums;
	header('Content-type: application/json; charset=UTF-8');
	header('Access-Control-Allow-Origin: *');  // allow anybody on any server to retrieve this
	$_zp_gallery_page = 'rest_api.php';
		
	$album = array();
		
	// If no current album, we're at the root of the site
	if (!$_zp_current_album) {
		$album['image_size'] = getOption('image_size');
		$album['thumb_size'] = getOption('thumb_size');

		// Get the top-level albums
	   	$subAlbumNames = $_zp_gallery->getAlbums();
		if (is_array($subAlbumNames)) {
			$subAlbums = array();
			foreach ($subAlbumNames as $subAlbumName) {
				$subAlbum = new Album($subAlbumName, $_zp_gallery);
				$subAlbums[] = toChildAlbumApi($subAlbum);
			}
			if ($subAlbums) {
				$album['albums'] = $subAlbums;
			}
		}
		
		include 'image_album_statistics.php';
		$latestAlbumNames = getAlbumStatistic(1, 'latest-date');
		if (count($latestAlbumNames) > 0) {
			$latestAlbum = new Album($latestAlbumNames[0]['folder'], $_zp_gallery);
			$latest[] = toChildAlbumApi($latestAlbum);
			if ($latest) {
				$album['latest'] = $latest;
			}
		}
	}
	// Else we're in the context of an album
	else {
		$album['path'] = $_zp_current_album->name;
		$album['title'] = $_zp_current_album->getTitle();
		if ($_zp_current_album->getDesc()) $album['description'] = $_zp_current_album->getDesc();
		if (!(boolean) $_zp_current_album->getShow()) $album['unpublished'] = true;
		$album['image_size'] = getOption('image_size');
		$album['thumb_size'] = getOption('thumb_size');
	
		//format:  2014-11-24 01:40:22
		$a = strptime($_zp_current_album->getDateTime(), '%Y-%m-%d %H:%M:%S');
		$album['date'] = mktime($a['tm_hour'], $a['tm_min'], $a['tm_sec'], $a['tm_mon']+1, $a['tm_mday'], $a['tm_year']+1900);
	
		// Add info about this albums' subalbums
		$albums = array();
		while (next_album()):
			$albums[] = toChildAlbumApi($_zp_current_album);
		endwhile;
		if ($albums) {
			$album['albums'] = $albums;
		}
	
		// Add info about this albums' images
		$images = array();
		while (next_image()):
			$images[] = toImageApi($_zp_current_image);
		endwhile;
		if ($images) {
			$album['images'] = $images;
		}
		
		// Add info about parent album
		$parentAlbum = toRelatedAlbum($_zp_current_album->getParent());
		if ($parentAlbum) {
			$album['parent_album'] = $parentAlbum; // would like to use 'parent' but that's a reserved word in javascript
		}
		
		// Add info about next album
		$nextAlbum = toRelatedAlbum($_zp_current_album->getNextAlbum());
		if ($nextAlbum) {
			$album['next'] = $nextAlbum;
		}
		
		// Add info about prev album
		$prevAlbum = toRelatedAlbum($_zp_current_album->getPrevAlbum());
		if ($prevAlbum) {
			$album['prev'] = $prevAlbum;
		}
	}
	
	// Return the album to the client in JSON format
	print(json_encode($album));
	exitZP();
}

// just enough info about a parent / prev / next album to navigate to it
function toRelatedAlbum($album) {
	if ($album) {
		$ret = array();
		$ret['path'] = $album->name;
		$ret['title'] = $album->getTitle();
		$ret['date'] = toTimestamp($album->getDateTime());
		return $ret;
	}
	return;
}

// just enough info about an image to render it on a standalone page
function toImageApi($image) {
	$ret = array();
	// strip /zenphoto/albums/ so that the path starts something like 2014/...
	$ret['path'] = str_replace('/zenphoto/albums/', '', $image->getFullImage());
	$ret['title'] = $image->getTitle();
	$ret['date'] = toTimestamp($image->getDateTime());
	$ret['description'] = $image->getDesc();
	$ret['urlFull'] = $image->getFullImageURL();
	$ret['urlSized'] = $image->getSizedImage(getOption('image_size'));
	$ret['urlThumb'] = $image->getThumb();
	$ret['width'] = $image->getWidth();
	$ret['height'] = $image->getHeight();
	
	return $ret;
}

// just enough info about a child album to render its thumbnail
function toChildAlbumApi($album) {
	$ret = array();
	$ret['path'] = $album->name;
	$ret['title'] = $album->getTitle();
	$ret['date'] = toTimestamp($album->getDateTime());
	if ($album->getCustomData()) $ret['summary'] = $album->getCustomData();
	if (!(boolean) $album->getShow()) $ret['unpublished'] = true;
	$thumbImage = $album->getAlbumThumbImage();
	if ($thumbImage) {
		$ret['urlThumb'] = $album->getAlbumThumbImage()->getThumb();
	}
	
	return $ret;
}

// take a zenphoto date string and turn it into an integer timestamp
function toTimestamp($dateString) {
	$a = strptime($dateString, '%Y-%m-%d %H:%M:%S'); //format:  2014-11-24 01:40:22
	return mktime($a['tm_hour'], $a['tm_min'], $a['tm_sec'], $a['tm_mon']+1, $a['tm_mday'], $a['tm_year']+1900);
}

?>