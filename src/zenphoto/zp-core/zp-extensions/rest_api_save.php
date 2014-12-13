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


// eip_context:  'image' or 'album'
if (zp_loggedin()) {
	if (!empty($_POST["eip_context"])) {
		executeRestApiSave($_POST["eip_context"], $_POST["title"], $_POST["desc"]);
	}
}

function executeRestApiSave($context = '', $title, $desc) {
	header('Access-Control-Allow-Origin: *');  // allow anybody on any server to invoke this
	header('Content-type: application/json; charset=UTF-8');
	
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
	$result = $object->save();
	if ($result !== false) {
		$ret['success'] = true;
	} 
	else {
		$ret['fail'] = true;
		$ret['message'] = 'Could not save';
	}
	// Return the results to the client in JSON format
	print(json_encode($ret));
	exitZP();
}

function sanitizeField($value, $level) {
	return str_replace("\n", '<br />', sanitize($value, $level)); // note: not using nl2br() here because it adds an extra "\n"
}

?>