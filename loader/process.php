<?php

/**
 * Sinergi is an open source application development framework for PHP
 *
 * Requires PHP version 5.4
 *
 * LICENSE: This source file is subject to the GNU General Public License 
 * version 2.0 (GPL-2.0) that is bundled with this package in the file 
 * LICENSE-GPL.txt and is available through the world-wide-web at the 
 * following URI: http://www.opensource.org/licenses/GPL-2.0. If you did 
 * not receive a copy of the GNU General Public License version 2.0 and are 
 * unable to obtain it through the web, please send a note to admin@sinergi.co 
 * so we can mail you a copy immediately.
 *
 * @package		sinergi
 * @author		Sinergi Team
 * @copyright	2010-2012 Sinergi Team
 * @license		http://www.opensource.org/licenses/GPL-2.0 GNU General Public License version 2.0 (GPL-2.0)
 * @link		https://github.com/sinergi/sinergi
 * @since		Version 1.0
 */

/**
 * Load all processes and run them on interval
 *
 * @category	core
 * @package		sinergi
 * @author		Sinergi Team
 * @link		https://github.com/sinergi/sinergi
 */

namespace sinergi;

use Path,
	Sinergi;

class ProcessLoader {
	/**
	 * The list of all processes to run
	 * 
	 * @var	array
	 */
	private $processes = [];
	
	/**
	 * Get proccesses and execute them
	 * 
	 * @return void
	 */
	public function __construct() {
		// Get all Application processes
		$this->processes = $this->scandir(Path::$processes);
		
		// Get all Modules processes
		foreach(Sinergi::$modules as $module) {
			$this->processes = array_merge(
				$this->processes, 
				$this->scandir(Path::$modules . "{$module}/processes/")
			);
		}
		
		// Check if only 1 process is called
		if (isset($_SERVER['argv'][1])) {
			$_SERVER['argv'][1] = strtolower($_SERVER['argv'][1]);
			
			if (preg_match('{^modules/}i', $_SERVER['argv'][1])) {
				$process = preg_replace('{modules/([^/]*)/(.*)}i', Path::$modules . '\1/processes/\2.php', $_SERVER['argv'][1]);
			} else {
				$process = Path::$processes . $_SERVER['argv'][1] . '.php';
			}
			
			if (!in_array($process, $this->processes)) {
				trigger_error("The process {$_SERVER['argv'][1]} does not exists", E_USER_ERROR);
			}
			
			$this->execute($process, true);
	
		// Execute all processes
		} else {
			foreach($this->processes as $process) {
				$this->execute($process);
			}
		}
	}
	
	/**
	 * Scan a directory for PHP files
	 * 
	 * @param	string
	 * @return	array
	 */
	private function scandir( $dir ) {
		$files = [];
		if (is_dir($dir)) {
		    $i = 0;
		    $dir = [preg_replace('!/$!', '', $dir)];
		    do {
		    	if (is_file(current($dir)) && strstr(current($dir), '.php')) {
		    		$files[] = current($dir);
		    	} else if (is_dir(current($dir))) {
		    		foreach (array_slice(scandir(current($dir)), 2) as $item) {
		    			$dir[] = current($dir) . "/{$item}";
		    		}
		    	}
		    } while (next($dir));
		}
		return $files;
	}
	
	/**
	 * Convert a file name to a class name
	 * 
	 * @param	string
	 * @return	string
	 */
	private function getProcessName( $path ) {		
		if (preg_match('{^'.Path::$modules.'}', $path)) {
			return str_replace('/', '\\', preg_replace('{'.Path::$modules.'(.*).php}', 'modules/\1', $path));
		} else {
			return str_replace('/', '\\', preg_replace('{'.Path::$processes.'(.*).php}', 'processes/\1', $path));
		}
	}
	
	/**
	 * Execute a process
	 * 
	 * @param	string
	 * @param	bool
	 * @return	void
	 */
	private function execute( $process, $force = false ) {
		require_once $process;
		
		$className = $this->getProcessName($process);
		if (class_exists($className) && method_exists($className, 'execute')) {
			$object = new $className();
			
			// If process has no interval and is not being forced to execute, skip the process
			if(!$force && !isset($object->interval)) return false;
						
			if($force || ($object->interval !== 0 && (floor(time()/60) % $object->interval) === 0)) {
				$object->execute();
			}
		}
	}
}