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
 * Object containing the client request information.
 *
 * @category	core
 * @package		sinergi
 * @author		Sinergi Team
 * @link		https://github.com/sinergi/sinergi
 */

class Request {
	/**
	 * The different paths.
	 * 
	 * @var	string
	 * @var	string
	 * @var	string
	 * @var	bool
	 * @var	string
	 * @var	string
	 * @var	string
	 */
	public static	$uri 			= '',
					$url 			= '', 
					$urn 			= '', 
					$secure 		= false, 
					$domain_name 	= '', 
					$query_string 	= '',
					$file_type		= '';
			
	/**
	 * Defines all the defaults paths.
	 * 
	 * @param	bool	checks process mode
	 * @return	void
	 */
	public function __construct() {
		// Determines if the connection with the client is secure.
		if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS']) {
		    $this::$secure = true;
		}
		
		// Define the URL, URI, URN and query string of the request.
		if (Sinergi::$mode == 'request' || Sinergi::$mode == 'api') {
			$this::$url = rtrim(
				$this::$uri = (isset($_SERVER['HTTPS']) ? 'https://' : 'http://') . "{$_SERVER['SERVER_NAME']}{$_SERVER['REQUEST_URI']}", 
				'?'	
			);
			
			$this::$urn = rtrim($_SERVER['REQUEST_URI'], '?');
			
			if (!empty($_SERVER['QUERY_STRING'])) {
				$this::$url = substr($this::$url, 0, -(strlen("{$_SERVER['QUERY_STRING']}") + 1));
				$this::$urn = substr($this::$urn, 0, -(strlen("{$_SERVER['QUERY_STRING']}") + 1));
			}
			
			$this::$query_string = $_SERVER['QUERY_STRING'];
		}
		
		// Define the URL or the request.
		if (isset($_SERVER['HTTP_HOST'])) {
		    $this::$domain_name = $_SERVER['HTTP_HOST'];
		}
		
		// Get the file type
		$this::$file_type = $this->get_file_type();
	}
	
	/**
	 * Detects file type by its extension. 
	 * 
	 * @return string
	 */
	private function get_file_type() {
		/* Get file extension if URN is file */
		preg_match("/.*\.+(.{2,4})$/i", $this::$urn, $matches);
		
		if (isset($matches[1])) {
			return strtolower($matches[1]);
		} else {
			return 'html';
		}
	}
}
