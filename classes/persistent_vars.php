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
 * Enable the storing of variables between executions.
 *
 * @category	core
 * @package		sinergi
 * @author		Sinergi Team
 * @link		https://github.com/sinergi/sinergi
 */

class PersistentVars {
	/**
	 * Add a variable to the persistent variables if it does not exists already
	 * 
	 * @param	string
	 * @param	mixed
	 * @return	bool
	 */
	public static function add( $key, $var ) {
		if (file_exists(Path::$core . ".cache/{$key}")) {
			return false;
		} else {
			(new File('core/.cache/'.$key))->write(serialize($var));
			return true;
		}
	}
	
	/**
	 * Add a variable to the persistent variables and replace the old one if it exists
	 * 
	 * @param	string
	 * @param	mixed
	 * @return	bool
	 */
	public static function store( $key, $var ) {
		(new File('core/.cache/'.$key))->write(serialize($var));
		return true;
	}
	
	/**
	 * Extends an existing array
	 * 
	 * @param	string
	 * @param	mixed
	 * @return	bool
	 */
	public static function extend( $key, $var ) {
		$file = (new File('core/.cache/'.$key));
		$array = unserialize($file->read());
		
		$file->write(
			serialize(
				array_merge(
					(!is_array($array) ? [] : $array), 
					[$var]
				)
			)
		);
		return true;
	}
	
	/**
	 * Fetch a variable from the persistent variables
	 * 
	 * @param	mixed
	 * @return	mixed
	 */
	public static function fetch( $key ) {
		// Fetch multiple keys
		if (is_array($key)) {
			$output = [];
			foreach($key as $item) {
				if (file_exists(Path::$core . ".cache/{$item}")) {
					$output[$item] = unserialize(file_get_contents(Path::$core . ".cache/{$item}"));
				} else {
					$output[$item] = false;
				}
			}
			
		// Fetch one key
		} else {
			if (file_exists(Path::$core . ".cache/{$key}")) {
				$output = unserialize(file_get_contents(Path::$core . ".cache/{$key}"));
			} else {
				$output = false;
			}
		}
		
		return $output;
	}
	
	/**
	 * Verify if a variable is stocked in the persistent variables
	 * 
	 * @param	mixed
	 * @return	mixed
	 */
	public static function exists( $key ) {
		if (file_exists(Path::$core . ".cache/{$key}")) {
			return true;
		} else {
			return false;
		}
	}
	
	/**
	 * Delete a variable from the persistent variables
	 * 
	 * @param	string	
	 * @return	bool
	 */
	public static function delete( $key ) {
		(new File('core/.cache/'.$key))->delete();
	}
}