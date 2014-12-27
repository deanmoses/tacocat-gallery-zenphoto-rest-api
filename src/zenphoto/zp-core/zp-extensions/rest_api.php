<?php

/**
 * A JSON REST API for ZenPhoto.  Supports building mobile apps,
 * javascript-heavy web apps, and other types of integrations.
 *
 * This filter will detect a query string parameter (?api) and
 * return a JSON representation of the album or search results
 * instead of the normal HTML response.
 *
 * @author Dean Moses (deanmoses)
 * @package plugins
 * @subpackage development
 */
$plugin_is_filter = 900 | FEATURE_PLUGIN;
$plugin_description = gettext('REST API for Zenphoto');
$plugin_author = "Dean Moses (deanmoses)";
$plugin_version = '0.9.0';

// Handle API calls before anything else
if (!OFFSET_PATH && isset($_GET['api'])) {
	zp_register_filter('load_theme_script', 'executeRestApi', 9999);
}

function executeRestApi() {
	global $_zp_gallery, $_zp_current_album, $_zp_current_image, $_zp_albums,$_zp_current_search,$_zp_current_context,$_zp_current_admin_obj;
	header('Content-type: application/json; charset=UTF-8');
	header('Access-Control-Allow-Origin: *');  // allow anybody on any server to retrieve this
	$_zp_gallery_page = 'rest_api.php';

	// the data structure we will be returning via JSON
	$ret = array();
	
	// If this is a request to see if the user is an admin, return that info
	if (isset($_GET['auth'])) {
		$ret['isAdmin'] = isset($_zp_current_admin_obj) && (bool) $_zp_current_admin_obj;
	}
	// If there's a search, return it instead of albums
	else if ($_zp_current_search) {
		$ret['thumb_size'] = (int) getOption('thumb_size');
		
		$_zp_current_search->setSortType('date');
		$_zp_current_search->setSortDirection('DESC');
		
		// add search results that are images
		$imageResults = array();
		$images = $_zp_current_search->getImages();
		foreach ($images as $image) {
			$imageIndex = $_zp_current_search->getImageIndex($image['folder'], $image['filename']);
			$imageObject = $_zp_current_search->getImage($imageIndex);
			$imageResults[] = toImage($imageObject);
		}
		if ($imageResults) {
			$ret['images'] = $imageResults;
		}
		
		// add search results that are albums
		$albumResults = array();
		while (next_album()) {
			$albumResults[] = toAlbumThumb($_zp_current_album);
		}
		if ($albumResults) {
			$ret['albums'] = $albumResults;
		}
	}
	// Else if the system in the context of an image, return info about the image
	else if ($_zp_current_image) {
		$ret['image'] = toImage($_zp_current_image);
	}
	// Else if the system is in the context of an album, return info about the album
	else if ($_zp_current_album) {
		$ret['path'] = $_zp_current_album->name;
		$ret['title'] = $_zp_current_album->getTitle();
		if ($_zp_current_album->getCustomData()) $ret['summary'] = $_zp_current_album->getCustomData();
		if ($_zp_current_album->getDesc()) $ret['description'] = $_zp_current_album->getDesc();
		if (!(boolean) $_zp_current_album->getShow()) $ret['unpublished'] = true;
		$ret['image_size'] = (int) getOption('image_size');
		$ret['thumb_size'] = (int) getOption('thumb_size');
		
		$thumb_path = $_zp_current_album->get('thumb');
		if (!is_numeric($thumb_path)) {
			$ret['thumb'] = $thumb_path;
		}
		
		//format:  2014-11-24 01:40:22
		$a = strptime($_zp_current_album->getDateTime(), '%Y-%m-%d %H:%M:%S');
		$ret['date'] = mktime($a['tm_hour'], $a['tm_min'], $a['tm_sec'], $a['tm_mon']+1, $a['tm_mday'], $a['tm_year']+1900);
	
		// Add info about this albums' subalbums
		$albums = array();
		while (next_album()):
			$albums[] = toAlbumThumb($_zp_current_album);
		endwhile;
		if ($albums) {
			$ret['albums'] = $albums;
		}
	
		// Add info about this albums' images
		$images = array();
		while (next_image()):
			$images[] = toImage($_zp_current_image);
		endwhile;
		if ($images) {
			$ret['images'] = $images;
		}
		
		// Add info about parent album
		$parentAlbum = toRelatedAlbum($_zp_current_album->getParent());
		if ($parentAlbum) {
			$ret['parent_album'] = $parentAlbum; // would like to use 'parent' but that's a reserved word in javascript
		}
		
		// Add info about next album
		$nextAlbum = toRelatedAlbum($_zp_current_album->getNextAlbum());
		if ($nextAlbum) {
			$ret['next'] = $nextAlbum;
		}
		
		// Add info about prev album
		$prevAlbum = toRelatedAlbum($_zp_current_album->getPrevAlbum());
		if ($prevAlbum) {
			$ret['prev'] = $prevAlbum;
		}
	}
	// Else if no current search, image or album, return info about the root albums of the site
	// TODO: detect we're not at the root and return a 404 or something
	else {
		$ret['image_size'] = (int) getOption('image_size');
		$ret['thumb_size'] = (int) getOption('thumb_size');

		// Get the top-level albums
	   	$subAlbumNames = $_zp_gallery->getAlbums();
		if (is_array($subAlbumNames)) {
			$subAlbums = array();
			foreach ($subAlbumNames as $subAlbumName) {
				$subAlbum = new Album($subAlbumName, $_zp_gallery);
				$subAlbums[] = toAlbumThumb($subAlbum);
			}
			if ($subAlbums) {
				$ret['albums'] = $subAlbums;
			}
		}
		
		// Get the latest album
		include 'image_album_statistics.php';
		$latestAlbumNames = getAlbumStatistic(1, 'latest-date', '2014');
		if (count($latestAlbumNames) > 0) {
			$latestAlbum = new Album($latestAlbumNames[0]['folder'], $_zp_gallery);
			$latest = toAlbumThumb($latestAlbum);
			if ($latest) {
				$ret['latest'] = $latest;
			}
		}
	}
	
	// Return the results to the client in JSON format
	print(json_encode($ret));
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

// just enough info about a child album to render its thumbnail
function toAlbumThumb($album) {
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

// just enough info about an image to render it on a standalone page
function toImage($image) {
	$ret = array();
	// strip /zenphoto/albums/ so that the path starts something like 2014/...
	$ret['path'] = str_replace('/zenphoto/albums/', '', $image->getFullImage());
	$ret['title'] = $image->getTitle();
	$ret['date'] = toTimestamp($image->getDateTime());
	$ret['description'] = $image->getDesc();
	$ret['urlFull'] = $image->getFullImageURL();
	$ret['urlSized'] = $image->getSizedImage(getOption('image_size'));
	$ret['urlThumb'] = $image->getThumb();
	$ret['width'] = (int) $image->getWidth();
	$ret['height'] = (int) $image->getHeight();
	return $ret;
}

// take a zenphoto date string and turn it into an integer timestamp
function toTimestamp($dateString) {
	$a = strptime($dateString, '%Y-%m-%d %H:%M:%S'); //format:  2014-11-24 01:40:22
	return (int) mktime($a['tm_hour'], $a['tm_min'], $a['tm_sec'], $a['tm_mon']+1, $a['tm_mday'], $a['tm_year']+1900);
}

?>