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
 * Hooks allows modules to extend sinergi.
 *
 * @category	core
 * @package		sinergi
 * @author		Sinergi Team
 * @link		https://github.com/sinergi/sinergi
 */

namespace sinergi\classes;

abstract class Hooks {
	const NAME = '';
	
	public static $registered_hooks = [];
	
	/**
	 * List of hooks avalaible.
	 * 
	 * @var	array
	 */
	private $hooks = [
		'path',			// Hooks the path object
#		'configs',		// Hooks the config loader
		'routes',		// Hooks the routes
		'loader',		// Hooks the page/file loader
#		'processes'		// Hooks the processes execution
	];
	
	/**
	 * Register all the plugin's hooks.
	 * 
	 * @return	void
	 */
	public function _init() {
		foreach(get_class_methods($this) as $method) {
			if(in_array($method, $this->hooks)) {
				$this->register($method);
			}
		}
	}
	
	/**
	 * Register a hook.
	 * 
	 * @return	void
	 */
	private function register($method) {
		$this::$registered_hooks[$this::NAME][$method] = true;
	}
	
	/**
	 * Run all hooks.
	 * 
	 * @return	void
	 */
	public static function run($method) {
		foreach(Hooks::$registered_hooks as $plugin_name=>$registered_hooks) {
			if (array_key_exists($method, $registered_hooks)) {
				$obj = "\\modules\\{$plugin_name}\\Module_hooks";
				$obj::$method();
			}
		}
	}
}