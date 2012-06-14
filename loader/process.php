<?php
namespace sinergi\processes;

/* Setup the execution environment */
define('DOCUMENT_ROOT', (str_replace('core/loader/processes.php', '', __FILE__)));
define('PROCESS_EXECUTION', true);

require_once DOCUMENT_ROOT . '/core/init.php';

/* Base path */
$base_path =  DOCUMENT_ROOT . 'application/processes';

/* Build a list of processes path from the base path */
$processes_paths = [];
if (is_dir($base_path)) {
    $i = 0;
    $dir = [$base_path];
    do {
    	if (is_file(current($dir)) and strstr(current($dir), '.php')) $processes_paths[] = current($dir);
    	else if (is_dir(current($dir))) foreach (array_slice(scandir(current($dir)), 2) as $item) $dir[] = current($dir) . "/{$item}";
    } while (next($dir));
}

/* Add plugins jobs */
foreach($plugins as $plugin) {
    $i = 0;
    $dir = [PLUGINS."{$plugin}/jobs"];
    do {
    	if (is_file(current($dir)) and strstr(current($dir), '.php')) $processes_paths[] = current($dir);
    	else if (is_dir(current($dir))) foreach (array_slice(scandir(current($dir)), 2) as $item) $dir[] = current($dir) . "/{$item}";
    } while (next($dir));
}

/* If only 1 process is called execute the process */
if(isset($_SERVER['argv'][1])) {
	foreach($processes_paths as $process_path) {
		if(strtolower(classNameFromPath($process_path))==strtolower($_SERVER['argv'][1])) {
			executeProcess(classNameFromPath($process_path));
			exit;
		}
	}
	trigger_error('the process do not exists', E_USER_ERROR);
	
/* Otherwise, go trough every processes and execute them if its interval math the timestamp */
} else {
	foreach($processes_paths as $process_path) {
		executeProcess(classNameFromPath($process_path), true);
	}
	
}

/**
 * Transforme a file name in class name
 */
function classNameFromPath($path) {
	global $base_path;
	
	if (preg_match('/^'.str_replace('/', '\/', PLUGINS).'/i', $path)) {
		return 'plugins\\' .str_replace([PLUGINS, '/jobs/', '.php', '/'], ['', '/process/', '', '\\'], $path);
	} else {
		return 'process' .str_replace([$base_path, '.php', '/'], ['', '', '\\'], $path);	
	}
}


/**
 * This function actually create the process and execute it.
 */
function executeProcess($class_name, $auto=false) {	
	$process = new $class_name();
	
	if($auto && $process->interval == null) {
		return false;
	}
	
	if(!isset($process->interval) || ($process->interval!=0 && (floor(time()/60) % $process->interval) == 0)) {
		$process->execute();
	}
	
}

exit;