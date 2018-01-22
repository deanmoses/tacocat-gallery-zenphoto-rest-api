<?php

/** 
 * Allows ajax and other front ends to update images.
 *
 * @author Dean Moses (deanmoses)
 * @package plugins
 * @subpackage development
 */
$plugin_is_filter = 5|THEME_PLUGIN;
$plugin_description = gettext('Allows ajax and other front ends to update images.');
$plugin_author = "Dean Moses (deanmoses)";
$plugin_version = '0.9.0';

// eip_context:  'image' or 'album'
if (zp_loggedin()) {
	if (!empty($_POST["eip_context"])) {

		header('Content-type: application/json; charset=UTF-8');

		// If the request is coming from a subdomain, send the headers
		// that allow cross domain AJAX.  This is important when the web 
		// front end is being served from sub.domain.com, but its AJAX
		// requests are hitting this zenphoto installation on domain.com

		// Browsers send the Origin header only when making an AJAX request
		// to a different domain than the page was served from.  Format: 
		// protocol://hostname that the web app was served from.  In most 
		// cases it'll be a subdomain like http://cdn.zenphoto.com
	    if (isset($_SERVER['HTTP_ORIGIN'])) {
	    	// The Host header is the hostname the browser thinks it's 
	    	// sending the AJAX request to. In most casts it'll be the root 
	    	// domain like zenphoto.com

	    	// If the Host is a substring within Origin, Origin is most likely a subdomain
	    	// Todo: implement a proper 'endsWith'
	        if (strpos($_SERVER['HTTP_ORIGIN'], $_SERVER['HTTP_HOST']) !== false) {
	        	// Allow CORS requests from the subdomain the ajax request is coming from
	        	header("Access-Control-Allow-Origin: {$_SERVER['HTTP_ORIGIN']}");

	        	// Allow credentials to be sent in CORS requests
	        	header('Access-Control-Allow-Credentials: true');
	        }
	    }

		if (!empty($_POST["thumb"])) {
			executeSetAlbumThumb($_POST["eip_context"], $_POST["thumb"]);
		}
		else {
			$title = (isset($_POST["title"])) ? $_POST["title"] : null;
			$desc = (isset($_POST["desc"])) ? $_POST["desc"] : null;
			$customdata = (isset($_POST["customdata"])) ? $_POST["customdata"] : null;
			$unpublished = (isset($_POST["unpublished"])) ? $_POST["unpublished"] : null;
			executeRestApiSave($_POST["eip_context"], $title, $desc, $customdata, $unpublished);
		}
	}
}

/**
 * Set the current album's thumbnail
 *
 * @param $thumb_path path to an image RELATIVE to the parent album, like 'felix.jpg'
 */
function executeSetAlbumThumb($context, $thumb_path) {
	if (!in_context(ZP_ALBUM))
	die ('Cannot edit from this page, must be an album page');
	
	$ret = array();
	
	// Make a copy of context object
	switch ($context) {
		case 'album':
			global $_zp_current_album;
			$object = $_zp_current_album;
			break;
		default:
			die (gettext('Error: malformed Ajax POST, unknown context: '.$context));
	}
	
	if (empty($object)) {
		$ret['fail'] = true;
		$ret['message'] = 'Could not retrieve album';
	}
	else if (empty($thumb_path)) {
		$ret['fail'] = true;
		$ret['message'] = 'Empty thumbnail path';
	}
	else {
		// set thumbnail
		$object->setThumb($thumb_path);
		// retrieve it, just to be sure we can
		$newThumbImage = $object->getAlbumThumbImage();
		if (!$newThumbImage) {
			$ret['fail'] = true;
			$ret['message'] = 'Invalid thumb path: '.$thumb_path;
		}
		else {
			$result = $object->save();
			if ($result !== false) {
				$ret['success'] = true;
				$ret['urlThumb'] = $newThumbImage->getThumb();
			} 
			else {
				$ret['fail'] = true;
				$ret['message'] = 'Could not save';
			}
		}
	}

	// Return the results to the client in JSON format
	print(json_encode($ret));
	exitZP();
}

/**
 * Set the title and description of either an album or an image.
 */
function executeRestApiSave($context, $title, $desc, $customdata, $unpublished) {
	$ret = array();
	
	if (!in_context(ZP_IMAGE) && !in_context(ZP_ALBUM))
	die ('Cannot edit from this page');
	
	// Make a copy of context object
	switch ($context) {
		case 'image':
			global $_zp_current_image;
			$object = $_zp_current_image;
			break;
		case 'album':
			global $_zp_current_album;
			$object = $_zp_current_album;
			break;
		default:
			die (gettext('Error: malformed Ajax POST, unknown context: '.$context));
	}
		
	// // Dates need to be handled before stored
	// if ($field == 'date') {
	// 	$value = date('Y-m-d H:i:s', strtotime($value));
	// }
	
	// // Sanitize new value
	// switch ($field) {
	// 	case 'desc':
	// 		$level = 1;
	// 		break;
	// 	case 'title':
	// 		$level = 2;
	// 		break;
	// 	default:
	// 		$level = 3;
	// }
	if (!empty($title)) $object->set('title', sanitizeField($title, 2));
	if (!empty($desc)) $object->set('desc', sanitizeField($desc, 1));
	if (isset($customdata)) $object->setCustomData(sanitizeField($customdata, 2));

	if (isset($unpublished)) {
		$published = !filter_var($unpublished, FILTER_VALIDATE_BOOLEAN);
		$object->setShow($published);
	}

	$result = $object->save();
	if ($result !== false) {
		$ret['success'] = true;
	} 
	else {
		$ret['fail'] = true;
		$ret['message'] = 'Could not save - Zenphoto sucks at giving reasons';
	}
	// Return the results to the client in JSON format
	print(json_encode($ret));
	exitZP();
}

function sanitizeField($value, $level) {
	return str_replace("\n", '<br />', sanitize($value, $level)); // note: not using nl2br() here because it adds an extra "\n"
}

?>