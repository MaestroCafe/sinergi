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
 * Initialize the application.
 *
 * @category	core
 * @package		sinergi
 * @author		Sinergi Team
 * @link		https://github.com/sinergi/sinergi
 */

header_remove('X-Powered-By'); // Remove PHP from the document's header.

use sinergi\classes\AutoLoader,
	sinergi\classes\Hooks,
	sinergi\DOM;

new Sinergi; // Instantiate Sinergi

class Sinergi {
	/**
	 * The mode the application is running as (request|api|process).
	 * 
	 * @var	string
	 */
	public static $mode = 'request';
	
	/**
	 * Determines the environment the application is running in (production|development).
	 * 
	 * @var	string
	 */
	public static $environment = 'production';
	
	/**
	 * These are also used to determine the environment the application is running. 
	 * 
	 * @var	bool
	 * @var	bool
	 */
	public static $production = true, $development = false;
		
	/**
	 * Track if all methods have been successfully executed before __destruct so we know 
	 * __desctruct is not being called from a die() or exit() somewhere else in the application
	 * 
	 * @var	bool
	 */
	private $complete = false;
	
	/**
	 * Initialize the application.
	 * 
	 * @param	bool	checks process mode
	 * @return	void
	 */
	public function __construct() {		
		$this->checkMode();

		$core = str_replace('/init/init.php', '', $_SERVER['SCRIPT_FILENAME']);

		require "{$core}/classes/path.php";				// Get the path class
		new Path; // Defines the default paths.
		
		$this->loadClasses(); // Load all required classes
		
		$this->loadSettings();
		
		$this->defaults();

		$this->loadModules(); // Load modules after the path class because we need the path to load the modules
		
		new Request; // Defines the default paths.
		
		new AutoLoader; // Register the autoloader.
		
		Hooks::run('configs'); // Run all the configs hooks
		
		// Load request file
		switch($this::$mode) {
			case 'request':
				Hooks::run('request'); // Run all reqests hooks
				
				require Path::$core."loader/request.php";
				new sinergi\RequestLoader;
				break;
			case 'api':
				#require Path::$core."loader/api.php";
				break;
			case 'process':
				#require Path::$core."loader/process.php";
				break;
		}
		
		$this->complete = true;
	}
	
	/**
	 * Define the default settings.
	 * 
	 * @return void
	 */
	protected function defaults() {		
		global $settings;
		
		// Get the settings file and set the default timezone. 
		if (isset($settings['time_zone'])) {
			date_default_timezone_set($settings['time_zone']);
		}
		
		// Define if the application is running in development mode.
		if (stristr($settings['environment'], 'dev')) {
			$this::$environment = 'development';
			$this::$production = false;
			$this::$development = true;
		}
		
		// Report error in the application/debugger if the application is running in development environment.
		if ($this::$environment === 'development') {
			error_reporting(E_ALL);
			ini_set('display_errors', 'On');
		}
	}
	
	/**
	 * Determines the mode the application is running as.
	 * 
	 * @return void
	 */
	private function checkMode() {
		// Checks if the variable OAUTH_SERVER is passed from the server
		if(!empty($_SERVER['OAUTH_SERVER'])) {
			$this::$mode = 'api';
		} else if (!empty($_SERVER['argv'])) {
			$this::$mode = 'process';
		}
	}
	
	/**
	 * Load the required classes.
	 * 
	 * @return void
	 */
	private function loadClasses() {
		require Path::$core . "classes/request.php";			// Get the request class
		require Path::$core . "classes/autoloader.php";		// Get the autoloader class
		
		if ($this::$mode=='request') { // Get the classes that are only available in request mode
			require Path::$core . "classes/controller.php";	// Get the controller class
		}
		
		require Path::$core . "classes/hooks.php";				// Get the hook class

		require Path::$core . "classes/process.php";			// Get the process class	
				
		require Path::$core . "classes/view.php";			// Get the view class
				
		require Path::$core . "classes/static_file.php";			// Get the static class

		require Path::$core . "db/db.php";			// Get the DB classes

		require Path::$core . "files/files.php";			// Get the DB classes
		
		require Path::$core . "functions/token.php";			// Get the token function
	}
	
	/**
	 * Load the application settings.
	 * 
	 * @return void
	 */
	private function loadSettings() {
		global $settings;
		
		$settings = [];
		
		// Find and require all files in the settings directory.
		$files = [];
		if (is_dir(Path::$settings)) {
			$i = 0;
			$dir = [rtrim(Path::$settings, '/')];
			do {
				if (is_file(current($dir)) && preg_match("/\.php$/i", current($dir))) {
					$settings = array_merge($settings, require current($dir));
				} else if (is_dir(current($dir))) {
					foreach (array_slice(scandir(current($dir)), 2) as $item) {
						$dir[] = current($dir) . "/{$item}";
					}
				}
			} while (next($dir));
		}
	}
	
	/**
	 * Load modules.
	 * 
	 * @return void
	 */
	private function loadModules() {
		if (is_dir(Path::$modules)) {
			$dirs = scandir(Path::$modules);
			foreach ($dirs as $dir) {
				if (substr($dir, 0, 1)!='.' && is_file(Path::$modules . "{$dir}/module.php")) {
					require Path::$modules . "{$dir}/module.php"; // Get module
					$moduleMooks = "modules\\{$dir}\\Module_hooks";
					(new $moduleMooks)->_init(); // Register module's hooks
				}
			}
		}
	}
	
	/**
	 * Print the DOM if it is not empty
	 * 
	 * @return void
	 */
	public function __destruct() {
		if ($this->complete && $this::$mode === 'request' && !empty(DOM::$dom)) {
			Hooks::run('dom');
			echo Hooks::run('output', DOM::write()); // Run all output hooks
		}
	}
	
}

exit;