<?php

namespace sinergi;

/**
 * Detects file type by its extension. 
 * (.html .xml .json .rss .atom)
 * Remove the file's extension from the path.
 *
 */
$filetype = 'html';

/* Get file extension if URN is file */
preg_match("/.*\.+(.{2,4})$/i", REQUEST_URN, $matches);

if (isset($matches[1])) {
	$filetype = strtolower($matches[1]);
	
/* Redirect URN if URN is a directory and doesn't end with a slash */
} else {
	if(substr(REQUEST_URN, -1)!=='/') {
		header('Location: ' . REQUEST_URN . '/');
		exit;
	}
}

$requesturi = substr(preg_replace("/\.{$filetype}$/i", '', REQUEST_URN), (strlen(rtrim(REQUEST_FOLDER, '/'))));

if ($requesturi=='/') $requesturi .= 'index';

/**
 * Add controllers with no route to routes
 * 
 */
function search($array, $value) {
	$result = false;
	
	foreach ($array as $route) {
		if (strtolower($route[1])==strtolower($value)) {
			$result = true;
		}
	}
	
	return $result;
}
 
if (!isset($routes) || !is_array($routes)) $routes = [];

if (is_dir(CONTROLLERS)) {
	$i = 0;
	$dir = [CONTROLLERS];
	do {
		if (is_file(current($dir)) and strstr(current($dir), '.php')) {
			$route = ltrim(str_replace([CONTROLLERS, '.php'], '', current($dir)), '/');
			
			if (!search($routes, $route)) {
				$routes[] = [$route.'/', $route];
			}
		}
		else if (is_dir(current($dir))) foreach (array_slice(scandir(current($dir)), 2) as $item) $dir[] = current($dir) . "/{$item}";
	} while (next($dir));
}

/**
 * Check for a match in routes. 
 *
 */
$controllers = [];

$needle = ltrim($requesturi, '/');
do { $needle = str_replace('//', '/', $needle); } while(strstr($needle, '//'));

// The needle method is a needle that considers the last part of the request path to be the name of the method used
// in the controller, thus, it uses the dirname as the needle.
$needle_method = dirname($needle).'/';

// The needle path is a needle that will match the controller by path. If the request path is controller_name
// it needs to match controller_name/controller_name
if (basename($needle)!='index') {
	$needle_path = $needle.basename($needle);
} else {
	$needle_path = dirname($needle)."/".basename(dirname($needle));
}

// The needle path method is a needle that will match the controller by path and consider the last part to be 
// the method used in the controller If the request path is controller_name/method it needs to match 
// controller_name/controller_name
$needle_path_method = $needle_method.basename($needle_method);

// In the case where needle_method or needle_path_method would match, this would be the method used in the
// controller 
$method = basename($needle);

$i = 0;
foreach ($routes as $route) {
	// Replace un-escaped slashesby escaped slashes
	$haystack = str_replace(['\/', '/'], ['/', '\/'], ltrim($route[0], '/'));
					
	// If route ends with a slash, we add the (index)? part at the end of the route because it can match an index file
	if(substr($route[0], -1)==='/') {
		$haystack = substr($haystack, 0, -2).'\/?(index)?';
	}
	
	// Try to match the normal needle or the needle_path 
	if(preg_match("/^{$haystack}$/i", $needle, $matches) || preg_match("/^{$haystack}$/i", $needle_path, $matches)) {
		$controllers[$i][0] = $route[1];
		$controllers[$i][1] = 'index';
		$controllers[$i][2] = [];
		
		if (isset($route[2]) && is_array($route[2])) {
			// Match a method name instead of scope
			$method = basename($route[1]);
			
			$controllers[$i][0] = substr($route[1], 0, -(strlen($method)+1));
			$controllers[$i][1] = $method;
			foreach ($route[2] as $key=>$variable) {
				$controllers[$i][2][$variable] = isset($matches[$key+1]) ? $matches[$key+1] : null;
			}
			
			// Match a scope
			$i++;
			$controllers[$i][0] = $route[1];
			$controllers[$i][1] = 'scope';
			
			foreach ($route[2] as $key=>$variable) {
				$controllers[$i][2][$variable] = isset($matches[$key+1]) ? $matches[$key+1] : null;
			}
		} else { // Also match last part as method name instead of index 
			$i++;
			
			$method = basename($route[1]);
			
			$controllers[$i][0] = substr($route[1], 0, -(strlen($method)+1));
			$controllers[$i][1] = $method;
			$controllers[$i][2] = [];
		}
						
		$i++;
	}
	
	// Try to match the needle_method and needle_path_method
	else if((preg_match("/^{$haystack}$/i", $needle_method, $matches) || preg_match("/^{$haystack}$/i", $needle_path_method, $matches)) && $method!=='index') {
		$controllers[$i][0] = $route[1];
		$controllers[$i][1] = $method;
		$controllers[$i][2] = [];
		
		$i++;
	} 
}

/**
 * Create file header. 
 *
 */
switch ($filetype) {
	case 'xml': header('Content-Type: text/xml; charset=utf-8'); break;
	case 'json': header('Content-Type: application/json; charset=utf-8'); break;
	case 'rss': header('Content-Type: application/rss+xml; charset=utf-8'); break;
	case 'atom': header('Content-Type: application/atom+xml; charset=utf-8'); break;
	case 'html': header('Content-Type: text/html; charset=utf-8'); break;
}

/**
 * Creates the document. 
 *
 * @var object
 */
$DOM = new \DOMDocumentExtended('1.0');

/**
 * Get the error handler. 
 *
 */
require CORE.'errors/error_handler.php';

/**
 * Start the output buffer. 
 *
 */
$outputBufferDebug = $outputBuffer = '';
ob_start();

/**
 * Check for loader overwrite in plugins. 
 *
 */
foreach ($plugins as $namespace=>$plugin) { if (function_exists("\\plugins\\{$namespace}\\loader")) call_user_func("\\plugins\\{$namespace}\\loader"); }

/**
 * This function loads the page's controller.
 *
 * @return void
 */
function loader() {
	global $controllers, $plugins;
	static $loaded=false;
		
	if (!$loaded) {
		// Add each plugin in an array
		$plugins_paths = [];
		foreach ($plugins as $plugin) {
			$plugins_paths[] = "plugins/{$plugin}";
		}
		
		// Check each controllers
		foreach ($controllers as $controller) {
			/* Try to match controller file */
			$filename = preg_replace("/.*\/(.*)$/", "$1", strtolower($controller[0]));
			$files = [
				strtolower($controller[0]),
				strtolower($controller[0])."/index",
				strtolower($controller[0])."/{$filename}"
			];
			foreach($files as $file) {
				// Plugin controller
				foreach($plugins_paths as $plugin_path) {
					if (substr($file, 0, strlen($plugin_path))==$plugin_path) {
						// Check if controller exists
						if (file_exists(DOCUMENT_ROOT."{$file}.php")) {
							require_once DOCUMENT_ROOT."{$file}.php";
							
							if (method_exists($filename, $controller[1])) {
								new \Controller($file, $controller[1], $controller[2]);
								return true;
							}
						}
					}
				}
				// Application controller
				if (file_exists(CONTROLLERS."{$file}.php")) {
					require_once CONTROLLERS."{$file}.php";
										
					if (method_exists($filename, $controller[1])) {
						new \Controller($file, $controller[1], $controller[2]);
						return true;
					}
				}
			}
		}
		
		error(404);
	}
}

loader();

/**
 * Check for dump overwrite in plugins. 
 *
 */
foreach ($plugins as $namespace=>$plugin) { if (function_exists("\\plugins\\{$namespace}\\dump")) call_user_func("\\plugins\\{$namespace}\\dump"); }

/**
 * This function outputs the file. 
 *
 * @return void
 */
function dump ($ajax=true) {
	global $outputBufferDebug, $config, $DOM, $html, $doctype, $plugins, $sinergiErrorHandling, $sinergi_hide_console;
	
	$outputBufferDebug = (isset($outputBufferDebug) ? $outputBufferDebug : '') . ob_get_clean();
	
	/**
	 * Creates the debugger if errors have been triggered. 
	 *
	 */
	if (DEV and !$ajax and (trim($outputBufferDebug)!="" or $sinergiErrorHandling!="") && (!isset($sinergi_hide_console) || $sinergi_hide_console==false)) {
		if (is_object($html)) {
			$body = $html->getElement('body');
		}
		if (isset($body)) {
			$outputBufferDebug = nl2br(str_replace(' ', '&nbsp;', $outputBufferDebug));
			(new \Element('div', array('class'=>'debug', 'id'=>'debugger', 'html'=>"<div><p>PHP Debugger</p><p>{$outputBufferDebug}{$sinergiErrorHandling}</p></div>")))->inject($body, 'top');
			(new \Element('style', array('html'=>file_get_contents(CORE.'errors/debugger.css'))))->inject($body);
			(new \Element('script', array('html'=>file_get_contents(CORE.'errors/debugger.js'))))->inject($body);
		}
	}
	
	/**
	 * Stops the output buffer. 
	 *
	 */
	ob_clean();
	

	/**
	 * Creates the output from the document.
	 * Replace "class-fixed-tmp" with "class" because using "class" in the DOM Object creates problems.
	 *
	 */
	$output = ((isset($doctype) ? $doctype : '') . str_replace(['class-fixed-tmp', '="attribute-fixed-tmp"'], ['class', ''], $DOM->saveHTML()));
	if (!isset($config['entities']) or $config['entities'] == false) $output = mb_convert_encoding($output, 'UTF-8', 'HTML-ENTITIES');

	/**
	 * Gzip compression
	 * 
	 */
	#if(preg_match('/(gzip|deflate).*(gzip|deflate)/i', $_SERVER['HTTP_ACCEPT_ENCODING'])) {		
	#	$output = gzencode($output);
	#	header('Content-Encoding: gzip');
	#}
		
	/**
	 * Write the output. 
	 *
	 */
	header('Connection: keep-alive');
	header('Content-Length: '.strlen($output));
	print($output);
	
	exit;
}

dump(false);
