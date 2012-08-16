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
 * Generate a token.
 *
 * @category	core
 * @package		sinergi
 * @author		Sinergi Team
 * @link		https://github.com/sinergi/sinergi
 */

class Token {
	/**
	 * Generate a token
	 * 
	 * @param	int
	 * @return	bool
	 */
	public static function generate( $length ) {
		$characters = array('a','b','c','d','e','f','g','h','o','j','k','l',
		'm','n','o','p','q','r','s','t','u','v','w','x','y','z','A','B','C','D',
		'E','F','G','H','O','J','K','L','M','N','O','P','Q','R','S','T','U',
		'V','W','X','Y','Z','0','1','2','3','4','5','6','7','8','9');
		srand((float) microtime() * 1000000);
		shuffle($characters);
		
		$token = '';
		
		do { $token .= $characters[mt_rand(0, (count($characters)-1))]; }
		while (strlen($token) < $length);
		    	
		return $token;
	}
}