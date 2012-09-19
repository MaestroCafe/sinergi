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
 * Autoload the models, the helpers, the processes and the sinergi traits into the application.
 *
 * @category	core
 * @package		sinergi
 * @author		Sinergi Team
 * @link		https://github.com/sinergi/sinergi
 */

namespace sinergi\classes;

use Path;

class AutoLoader {
	/**
	 * List of sinergi classes
	 * 
	 * @var	
	 */
	public $sinergiClasses = [
		'Controller'		=> 'classes/controller.php',
		'Process'			=> 'classes/process.php',
		'View'				=> 'classes/view.php',
		'StaticFile'		=> 'classes/static_file.php',
		'File'				=> 'classes/files.php',
		'Token'				=> 'classes/token.php',
		'PersistentVars'	=> 'classes/persistent_vars.php',
		'Query'				=> 'db/query.php',
		'sinergi\DOM'		=> 'dom/dom.php',
	];
	
	/**
	 * Register the autoloader.
	 * 
	 * @return	void
	 */
	public function __construct() {
		spl_autoload_register([$this, 'load']);
	}
	
	/**
	 * Autoload classes
	 * 
	 * @param	string	name of the class
	 * @return	void
	 */
	private function load( $className ) {
		// Sinergi Classes
		if (isset($this->sinergiClasses[$className])) {
			require_once Path::$core . $this->sinergiClasses[$className];
		
		// Other classes
		} else {
			// Modules classes
			if (preg_match("/^modules\\\/i", $className)) {
				// Load classes in directories
				if (preg_match('!^([^\\\]*)\\\([^\\\]*)\\\([^\\\]*)\\\([^\\\]*)!', $className)) {
					require_once 
						Path::$documentRoot . "modules/" . 
						preg_replace('!^([^/]+)/([^/]+)/([^/]+)!', '$1/$2/$3', str_replace('\\', '/', strtolower(substr($className, 8)))) . ".php";
				// Load default classes
				} else {
					require_once 
						Path::$documentRoot . "modules/" . 
						preg_replace('!^([^/]+)/([^/]+)!', '$1/classes/$2', str_replace('\\', '/', strtolower(substr($className, 8)))) . ".php";
				}
				return true;
			}
			
			// Sinergi traits
			else if (preg_match("/^sinergi\\\/i", $className)) {
				require_once Path::$core . "traits/" . str_replace('\\', '/', strtolower(substr($className, 8))) . ".php";
				return true;
			}
			
			// Application classes
			else {
				$namespace = preg_replace('!^([^\\\]*)\\\.*!', '$1', $className);
				require_once Path::$application . "{$namespace}/" . str_replace('\\', '/', strtolower(substr($className, strlen($namespace)+1))) . ".php";
				return true;
			}
		}
	}
}