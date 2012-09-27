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
 * Cache library helps cache using APC different Sinergi objects.
 *
 * @category	core
 * @package		sinergi
 * @author		Sinergi Team
 * @link		https://github.com/sinergi/sinergi
 */

class Cache {
	/**
	 * Cache a variable in the data store
	 * 
	 * @param	mixed
	 * @return	bool
	 */
	public static function store( $var, $ttl = 0 ) {
		return apc_store(self::name(), self::makeSerializable($var), $ttl);
	}
	
	/**
	 * Fetch a stored variable from the cache
	 * 
	 * @return	bool
	 */
	public static function fetch() {
		return apc_fetch(self::name());
	}
	
	/**
	 * Checks if a cache exists
	 * 
	 * @return	bool
	 */
	public static function exists() {
		return apc_exists(self::name());
	}
	
	/**
	 * Removes a stored variable from the cache
	 * 
	 * @return	bool
	 */
	public static function delete() {
		return apc_delete(self::name());
	}
	
	/**
	 * Convert object that are not serializable into objects that are.
	 * 
	 * @param	mixed
	 * @return	mixed
	 */
	private static function makeSerializable( $var ) {
		// Query/PDO objects
		if ($var instanceof Query) {
			$newVar = new ArrayObject();
			foreach($var as $item) {
				$newItem = new stdClass();
				foreach($item as $key=>$value) {
					$newItem->{$key} = $value;
				}
				$newVar->append($newItem);
			}
			$var = $newVar;
		
		// Views
		} else if($var instanceof View) {
			$var = $var->element;
		}
		
		return $var;
	}
		
	/**
	 * Get the name of the class and method that called the Cache
	 * 
	 * @return	string
	 */
	private static function name() {
		$trace = debug_backtrace();
		return base64_encode($trace[2]['class'].'\\'.$trace[2]['function']); die();
 	}
}