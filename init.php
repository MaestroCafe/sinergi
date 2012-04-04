<?php

/**
 * Remove PHP from the header. 
 *
 */
header_remove('X-Powered-By');

/**/
if (!defined('PROCESS_EXECUTION')) define('PROCESS_EXECUTION', false);

/**
 * Define the root path of the application and the configs path
 *
 */
if(!defined('DOCUMENT_ROOT')) define('DOCUMENT_ROOT', str_replace('core/init.php', '', $_SERVER['SCRIPT_FILENAME']));
define('SETTINGS', DOCUMENT_ROOT.'settings/');

/**
 * Check if script is executing as API.
 * 
 */
if (!empty($_SERVER['OAUTH_SERVER'])) {
	define('API_EXECUTION', true);
} else {
	define('API_EXECUTION', false);
}

/**
 * Get the configuration file and set the default timezone. 
 *
 */
$config = [];
require SETTINGS.'application.php';
if (isset($config['time_zone'])) date_default_timezone_set($config['time_zone']);

/**
 * Define the URL 
 *
 */
if (!PROCESS_EXECUTION) define('URL', ((( isset($_SERVER['HTTPS']) ) ? 'https://' : 'http://') . $_SERVER['SERVER_NAME'] . $_SERVER['REQUEST_URI'] ));
else define('URL', '');

/**
 * Check if application is in production or development, turn on errors if in development. 
 *
 */
if (
	(isset($config['dev']) && $config['dev']==true) || 
	(!PROCESS_EXECUTION and substr($_SERVER['SERVER_ADDR'], 0, 10)=='192.168.1.') || 
	(PROCESS_EXECUTION and isset($_SERVER['argv'][2]) and $_SERVER['argv'][2]=='DEV')
) {
	define('DEV', true);
	error_reporting(E_ALL);
	ini_set('display_errors', 'On');
} else {
	define('DEV', false);
}

/**
 * Get the DOMAIN_NAME, REQUEST_URN, QUERY_STRING and REQUEST_FOLDER
 *
 */
if (!PROCESS_EXECUTION) {
	define('DOMAIN_NAME', $_SERVER['HTTP_HOST']);
	define('REQUEST_URN', str_replace('?' . $_SERVER['QUERY_STRING'], '', $_SERVER['REQUEST_URI']));
	define('QUERY_STRING', $_SERVER['QUERY_STRING']);
} else {
	define('DOMAIN_NAME', '');
	define('REQUEST_URN', '');
	define('QUERY_STRING', '');
}

define('REQUEST_FOLDER', (isset($config['request_folder']) ? '/'.trim($config['request_folder'], '/').'/' : ''));

/**
 * Define the paths to different parts of the framework. 
 *
 */
define('APPLICATION', DOCUMENT_ROOT.'application/');
define('CORE', DOCUMENT_ROOT.'core/');
define('ERRORS', DOCUMENT_ROOT.'errors/');
define('PLUGINS', DOCUMENT_ROOT.'plugins/');

define('API', APPLICATION.'api/');
define('ASSETS', APPLICATION.'assets/');
define('CONFIGS', APPLICATION.'configs/');
define('CONTROLLERS', APPLICATION.'controllers/');
define('HELPERS', APPLICATION.'helpers/');
define('LANGUAGES', APPLICATION.'languages/');
define('LIBRARIES', APPLICATION.'libraries/');
define('MODELS', APPLICATION.'models/');
define('PROCESSES', APPLICATION.'processes/');
define('VIEWS', APPLICATION.'views/');

/**
 * Autoload the framework parts
 * http://phpmaster.com/autoloading-and-the-psr-0-standard/
 */
function sinergi_auto_load($class_name) {
	static $firstController = true;
	global $controllerFile;
	
	/* Model */
	if (strtolower(substr($class_name, 0, 6))=='model\\') {
		
		$file = str_replace('\\', '/', strtolower(substr($class_name, 6)));
		if (file_exists(MODELS."{$file}.php")) {
			
			require_once MODELS."{$file}.php"; // If model exists, get model file
		
		} else {
			echo MODELS."{$file}.php";die();
			$name_space = explode('\\', strrev($class_name), 2);
			eval('namespace '.strtolower(substr($class_name, 0, -(strlen($name_space[0])+1))).'; class '.strrev($name_space[0]).' extends \Model {}'); // If model does not exists, create an empty model
						
		}
		
	}
	/* Helper */
	else if (strtolower(substr($class_name, 0, 7))=='helper\\') {
		$file = str_replace('\\', '/', strtolower(substr($class_name, 7)));
		require HELPERS."{$file}.php";
	}
	/* Mountain */
	else if (strtolower(substr($class_name, 0, 17))=='plugins\\mountain\\') {
		$file = str_replace('\\', '/', strtolower(substr($class_name, 17)));
		
		if (preg_match('/^model/i', $file)) $file = preg_replace('/^model/i', 'models', $file);
		else $file = "classes/{$file}";
		
		require PLUGINS."mountain/{$file}.php";
	}
	/* Plugin */
#	else if (substr($class_name, 0, 8)=='plugins\\') {
#		/* Make it go look into the rigth folder depending of the class Type (model,controllers..) */
#		if(stristr($class_name, 'model')) {
#			$class_name = str_replace('model', 'models', $class_name);
#			$subFolder = '';
#		} else {
#			$subFolder = 'controllers/';
#		}
#		
#		$file = str_replace('\\', '/', strtolower(substr($class_name, 8)));
#		$file = explode('/', $file, 2);
#		
#		require PLUGINS."{$file[0]}/{$subFolder}{$file[1]}.php";
#	} 
	/* Processes */
	else if (strtolower(substr($class_name, 0, 7))=='process') {
		$file = str_replace('\\', '/', strtolower(substr($class_name, 8)));
		require_once PROCESSES."{$file}.php";
	}
}
spl_autoload_register('sinergi_auto_load');

/**
 * Initialize plugins. 
 *
 */
$plugins = array();
if (is_dir(PLUGINS)) {
	$dirs = scandir(PLUGINS);
	foreach ($dirs as $dir) {
		if (substr($dir, 0, 1)!='.') {
			require PLUGINS."{$dir}/plugin.php";
			$plugins[str_replace('-', '', $dir)] = $dir;
		}
	}
	unset($dirs, $dir);
}

/**
 * Allow plugins to modify the path. 
 *
 */
foreach ($plugins as $namespace=>$plugin) { if (function_exists("\\plugins\\{$namespace}\\path")) call_user_func("\\plugins\\{$namespace}\\path"); }

/**
 * Make URLs redirections. 
 *
 */
if (!API_EXECUTION && !PROCESS_EXECUTION) {
	if (is_file(APPLICATION.'configs/redirections.php')) require APPLICATION.'configs/redirections.php';

	if (isset($redirection) and count($redirection)) {
		foreach($redirection as $item) {
			if ($item[0]==REQUEST_URN or $item[0]==REQUEST_URN."?".QUERY_STRING) {
				header('HTTP/1.1 301 Moved Permanently');
				
				$redirection = trim(REQUEST_FOLDER, '/');
							
				header('Location: '.(strlen($redirection)>0 ? "/{$redirection}/" : '/').ltrim($item[1], '/'));
				exit;
			}
		}
		unset($item);
	}
	
	foreach ($plugins as $namespace=>$plugin) { if (function_exists("\\plugins\\{$namespace}\\redirections")) call_user_func("\\plugins\\{$namespace}\\redirections"); }
	
	unset($redirection, $item);
}

/* Include functions used to access different part of */
require CORE.'architecture/controller.php';
if (!API_EXECUTION && !PROCESS_EXECUTION) { require CORE.'architecture/view.php'; }
require CORE.'architecture/model.php';
require CORE.'architecture/process.php';

require CORE.'classes/files.php';

/* Include traits */
require CORE.'traits/settings.php';

/**
 * Include routes. 
 *
 */
if (!API_EXECUTION && !PROCESS_EXECUTION) {
	if (file_exists(CONFIGS.'routes.php')) require CONFIGS.'routes.php';
	
	foreach ($plugins as $namespace=>$plugin) { if (function_exists("\\plugins\\{$namespace}\\routes")) call_user_func("\\plugins\\{$namespace}\\routes"); }
}

/**
 * Error loader. 
 *
 */
function error($error) {
	switch($error) {
		case 404: case 'not_found': // Error 404
			header("HTTP/1.1 404 Not Found");
			if (API_EXECUTION) { // API error 404
				header('Content-Type: application/json; charset=utf-8');
				if (file_exists(ERRORS."/api_not_found.json")) require ERRORS."/api_not_found.json";
				else if (file_exists(ERRORS."/api_not_found.php")) require ERRORS."/api_not_found.php";
				else if (file_exists(ERRORS."/api_not_found.html")) require ERRORS."/api_not_found.html";
				else if (file_exists(ERRORS."/not_found.html")) require ERRORS."/not_found.html";
				else if (file_exists(ERRORS."/not_found.php")) require ERRORS."/not_found.php";
			} else { // Normal error 404
				if (file_exists(ERRORS."/not_found.html")) require ERRORS."/not_found.html";
				else if (file_exists(ERRORS."/not_found.php")) require ERRORS."/not_found.php";
			}
			break;
		default: // Default behavior
			if (file_exists(ERRORS."/{$error}.html")) require ERRORS."/{$error}.html";
			else if (file_exists(ERRORS."/{$error}.php")) require ERRORS."/{$error}.php";
			break;
	}
	exit;
}

/**
 * Load static files
 * 
 */
if (!API_EXECUTION && !PROCESS_EXECUTION) {
	foreach ($plugins as $namespace=>$plugin) { if (function_exists("\\plugins\\{$namespace}\\staticFiles")) call_user_func("\\plugins\\{$namespace}\\staticFiles"); }
}

/**
 * Load Dynamic files 
 *
 */
if (!API_EXECUTION && !PROCESS_EXECUTION) {
	require CORE.'loader/dynamic.php';
}

/**
 * Load API files 
 *
 */
else if (API_EXECUTION) {
	require CORE.'loader/api.php';
}
