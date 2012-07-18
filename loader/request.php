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
 * Search for the right contoller and load the page
 *
 * @category	core
 * @package		sinergi
 * @author		Sinergi Team
 * @link		https://github.com/sinergi/sinergi
 */

namespace sinergi;

use Request,
	Path,
	sinergi\classes\Hooks;

class RequestLoader {
	/**
	 * The request urn without the file extension
	 * 
	 * @var	string
	 */
	private $requestURN;
	
	/**
	 * A merge of the routes and controllers
	 * 
	 * @var	array
	 */
	public $routes = [];
	
	/**
	 * Controllers that matched a route
	 * 
	 * @var	array
	 */
	private $controllers = [];
	
	/**
	 * Search for the right contoller and load the page
	 * 
	 * @return void
	 */
	public function __construct() {
		global $routes;
		
		//$this->redirectURN(); // Redirect URN (See comment about this method)
		
		$this->requestURN = preg_replace("/\.".Request::$fileType."$/i", '', Request::$urn);
		if ($this->requestURN === '/') $this->requestURN .= 'index';
		
		// Get routes
		if (file_exists(Path::$configs . "routes.php")) {
			require Path::$configs . "routes.php";
			
			Hooks::run('routes'); // Run all the routes hooks
			
			if (is_array($routes)) {
				$this->routes = $routes;
			}
		}
		
		$this->routes = array_merge($routes, $this->controllersRoutes());
		
		$this->controllers = $this->matchRoutes();
		$this->loadController();
	}
	
	/**
	 * Redirect the URN to a directory URN if there is no extension and the URN doesn't end with a slash
	 * THIS METHOD IS NOT CURRENTLY USED AS THERE IS NO PERFECT SOLUTION TO THIS PROBLEM. IT HAS TO BE REVIEWED LATER.
	 * 
	 * @return void
	 */
	private function redirectURN() {
		if(!preg_match("/.*\.".Request::$fileType."$/i", Request::$urn) && substr(Request::$urn, -1) !== '/') {
			header('Location: ' . Request::$urn . '/');
			exit;
		}
	}
	
	/**
	 * Create a routes array from the controllers that have no matching route
	 * 
	 * @return array
	 */
	private function controllersRoutes() {
		if (is_dir(Path::$controllers)) {
			
			function search( $needle, $haystack ) {
				foreach ($haystack as $route) {
					if (strtolower($route[1]) === strtolower($needle)) {
					    return true;
					}
				}
				   
				return false;
			}
 
			$routes = [];
			$dir = [Path::$controllers];
			
			do {
				if (file_exists(current($dir)) && strtolower(substr(current($dir), -4)) === '.php') {
					$route = substr(current($dir), strlen(Path::$controllers) + 1, -4);
					
					if (!search($route, $this->routes)) {
						$routes[] = [$route.'/', $route];
					}
				} else if (is_dir(current($dir))) {
					foreach (array_slice(scandir(current($dir)), 2) as $item) {
						$dir[] = current($dir) . "/{$item}";
					}
				}
			} while (next($dir));
			
			return $routes;
		}
		
		return [];
	}
	
	/**
	 * Try to match the request to routes
	 * 
	 * @return void
	 */
	private function matchRoutes() {
		$controllers = [];
		
		$needle = ltrim($this->requestURN, '/');
		while(strstr($needle, '//')) { $needle = str_replace('//', '/', $needle); }
		
		// The needle method is a needle that considers the last part of the request path to be the name of the method used
		// in the controller, thus, it uses the dirname as the needle.
		$needle_method = dirname($needle).'/';
		
		// The needle path is a needle that will match the controller by path. If the request path is controller_name
		// it needs to match controller_name/controller_name
		if (basename($needle)!='index') {
			if (preg_match('/\/$/', $needle)) {
				$needle_path = $needle.basename($needle);
			} else {
				$needle_path = $needle.'/'.basename($needle);
			}
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
		foreach ($this->routes as $route) {
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
					// Match a method name instead of index
					$method = basename($route[1]);
					
					$controllers[$i][0] = substr($route[1], 0, -(strlen($method)+1));
					$controllers[$i][1] = $method;
					foreach ($route[2] as $key=>$variable) {
						$controllers[$i][2][$variable] = isset($matches[$key+1]) ? $matches[$key+1] : null;
					}
					
					// Match index
					$i++;
					$controllers[$i][0] = $route[1];
					$controllers[$i][1] = 'index';
					
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
		
		return $controllers;
	}
	
	/**
	 * Load the first working controller in the controllers matches
	 * 
	 * @return 
	 */
	private function loadController() {
		Hooks::run('loader'); // Run all the loader hooks
			
		// Add each plugin in an array
		/*$plugins_paths = [];
		foreach ($plugins as $plugin) {
			$plugins_paths[] = "plugins/{$plugin}";
		}*/
		
		// Check each controllers
		foreach ($this->controllers as $controller) {
			// Try to match the controller to a controller file
			$filename = preg_replace("/.*\/(.*)$/", "$1", strtolower($controller[0]));
			$files = [
				strtolower($controller[0]),
				strtolower($controller[0])."/index",
				strtolower($controller[0])."/{$filename}"
			];
						
			foreach($files as $file) {
				// Plugin controller
				/*foreach($plugins_paths as $plugin_path) {
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
				}*/
				// Application controller
				if (file_exists(Path::$controllers."{$file}.php")) {
					require_once Path::$controllers."{$file}.php";
										
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