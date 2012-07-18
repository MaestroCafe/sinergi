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
 * Instantiate a controller.
 *
 * @category	core
 * @package		sinergi
 * @author		Sinergi Team
 * @link		https://github.com/sinergi/sinergi
 */

class Controller {
	/**
	 * The controller object
	 * 
	 * @var	string
	 */
	private $controller;
	
	/**
	 * The traits used by the controller
	 * 
	 * @var	array
	 */
	private $traits = [];
	
	/**
	 * Instantiate a controller and load the method provided with the arguments provided.
	 * 
	 * @param	string	the controller's name
	 * @param	string	the method to execute
	 * @param	array	the arguments to pass to the method
	 * @return	void
	 */
	public function __construct( $controller, $method = null, $args = [] ) {
		$controllerName = preg_replace("/.*\/(.*)$/", "$1", strtolower($controller)); // Get controller name
		
		if ($controllerName === 'index' && method_exists('index', 'index') && !method_exists('index', '__construct')) {
			_sinergi_index_controller();
			$this->controller = new _SinergiIndexController;
			$this->traits = class_uses('index');
		} else {
			$this->controller = new $controllerName;
			$this->traits = class_uses($this->controller);
		}
						
		$this->traitsConstructors();
		
		if (isset($method)) {
			call_user_func_array(array(&$this->controller, $method), $args); 
		}
   }
   
   /**
	* Sinergi uses the method _helpername_construct() as the helper constructor. So we need to get a list of helpers that the controller uses.
	* Note that there is no way to call the function in a specific helper, so there could be a mix up if a helper has the same name as another method in
	* another helper or in the controller.
	* 
	* @return	void
	*/
   private function traitsConstructors() {
		foreach($this->traits as $helper) {
			$helperName = "_" . preg_replace("/.*\\\(.*)$/", "$1", strtolower($helper)) . "Construct"; // Get helper name
			
			if(method_exists($this->controller, $helperName)) { // Check if helper constructor exists
				$this->controller->$helperName();
			}
		}
   
   }

	/**
	 * Sinergi uses the method _helpername_construct() as the helper constructor. So we need to get a list of helpers that the controller uses.
	 * Note that there is no way to call the function in a specific helper, so there could be a mix up if a helper has the same name as another method in
	 * another helper or in the controller.
	 * 
	 * @return	void
	 */
	public function __destruct() {
		if (is_object($this->controller)) {
			foreach($this->traits as $helper) {
				$helper_name = "_" . preg_replace("/.*\\\(.*)$/", "$1", strtolower($helper)) . "Destruct"; // Get helper name
				
				if(method_exists($this->controller, $helper_name)) { // Check if helper constructor exists
					$this->controller->$helper_name();
				}
			}
		}
   }
}

/**
 * Fix for controllers that are named index and have a method named index.
 * 
 * @return	void
 */
function _sinergi_index_controller() {
	class _SinergiIndexController extends Index {
		public function __construct() {}
	}
}
