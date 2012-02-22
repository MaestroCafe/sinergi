<?php

namespace sinergi;

/* API error handler */
function api_error_handler($errno, $errstr, $errfile, $errline) {		
    return true;
}
set_error_handler('sinergi\api_error_handler');

/**
 * Remove json extension from
 *
 */
$filetype = 'json';
header('Content-Type: application/json; charset=utf-8');

/* Get file extension if URN is file */
preg_match("/.*\.+([{$filetype}]+)$/i", REQUEST_URN, $matches);
if (!isset($matches[1])) {
	error(404);
}

$requesturi = substr(preg_replace("/\.{$filetype}$/i", '', REQUEST_URN), (strlen(rtrim(REQUEST_FOLDER, '/'))));

/**
 * Get a list of API controllers
 * 
 */ 
$api_controllers = [];

if (is_dir(API)) {
	$i = 0;
	$dir = [API];
	do {
		if (is_file(current($dir)) and strstr(current($dir), '.php')) {
			$api_controllers[] = ltrim(str_replace([API, '.php'], '', current($dir)), '/');
		}
		else if (is_dir(current($dir))) foreach (array_slice(scandir(current($dir)), 2) as $item) $dir[] = current($dir) . "/{$item}";
	} while (next($dir));
}

/**
 * Check for a match with URI 
 *
 */
$controllers = [];

$needle = ltrim($requesturi, '/');
do { $needle = str_replace('//', '/', $needle); } while(strstr($needle, '//'));

/* This needle is the needle that consider the last part of the URI to be a method inside the controller */
$needle_method = preg_replace("/(\/[^\/]*)\/?$/", "", $needle);

$i = 0;
foreach ($api_controllers as $haystack) {
	if($needle==$haystack) {
		$controllers[$i][0] = $haystack;
		$controllers[$i][1] = 'index';

		$i++;
	}
	
	/*  */
	$method = strrev(preg_replace("/^(\/?)([^\/]*)(.*)$/", "$2", strrev($needle)));

	if($needle_method==$haystack && $method!=='index') {
		$controllers[$i][0] = $haystack;
		$controllers[$i][1] = $method;

		$i++;
	} 
}

/**
 * Check for loader overwrite in plugins. 
 *
 */
foreach ($plugins as $namespace=>$plugin) { if (function_exists("\\plugins\\{$namespace}\\api_loader")) call_user_func("\\plugins\\{$namespace}\\api_loader"); }

/**
 * This function loads the page's controller.
 *
 * @return void
 */
function api_loader() {
	global $controllers;
		
	foreach ($controllers as $controller) {
	    /* Try to match controller file */
	    $filename = preg_replace("/.*\/(.*)$/", "$1", strtolower($controller[0]));
	    	    
	    $files = [
	    	strtolower($controller[0]),
	    	strtolower($controller[0])."/index",
	    	strtolower($controller[0])."/{$filename}"
	    ];
	    foreach($files as $file) {
	    	if (file_exists(API."{$file}.php")) {
	    		require_once API."{$file}.php";
	    			    			
	    		if (method_exists($filename, $controller[1])) {
	    			$api_controller = new $filename;
	    			
	    			$api_controller->$controller[1]();
	    			return true;
	    		}
	    	}
	    }
	}
	
	error(404);
}

api_loader();