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
	 * Register the autoloader.
	 * 
	 * @return	void
	 */
	public function __construct() {
		spl_autoload_register([$this, 'load']);
	}
	
	/**
	 * Autoload the models, helpers, processes and sinergi traits.
	 * 
	 * @param	string	name of the class
	 * @return	void
	 */
	private function load( $className ) {
		$matches = [
			'model'		=> Path::$models, 
			'helper'	=> Path::$helpers, 
			'process'	=> Path::$processes, 
			'sinergi'	=> Path::$core . "traits/",
			'modules'	=> Path::$documentRoot . "modules/"
		];
		
		foreach($matches as $namespace=>$path) {
			if (preg_match("/^{$namespace}\\\/i", $className)) {
				if ($namespace === 'modules') {
					require_once $path . preg_replace('!^([^/]+)/([^/]+)!', '$1/$2s', str_replace('\\', '/', strtolower(substr($className, strlen($namespace)+1)))) . ".php";
				} else {
					require_once $path . str_replace('\\', '/', strtolower(substr($className, strlen($namespace)+1))) . ".php";
				}
				return true;
			}
		}
	}
}