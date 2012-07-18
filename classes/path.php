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
 * Object containing the different paths that compose the application.
 *
 * @category	core
 * @package		sinergi
 * @author		Sinergi Team
 * @link		https://github.com/sinergi/sinergi
 */

class Path {
	/**
	 * The different paths.
	 * 
	 * @var	string
	 */
	public static	$documentRoot,
					$application, 
					$core, 
					$modules, 
					$publicFiles, 
					$publicCache, 
					$settings, 
					$apis, 
					$configs, 
					$controllers, 
					$errors, 
					$helpers, 
					$languages, 
					$libraries, 
					$models, 
					$processes, 
					$views;
			
	/**
	 * Defines all the defaults paths.
	 * 
	 * @param	bool	checks process mode
	 * @return	void
	 */
	public function __construct() {
		self::$documentRoot = str_replace('core/init/init.php', '', $_SERVER['SCRIPT_FILENAME']);
		
		self::$application = self::$documentRoot . "application/";
		self::$core = self::$documentRoot . "core/";
		self::$modules = self::$documentRoot . "modules/";
		self::$publicFiles = self::$documentRoot . "public_files/";
		self::$publicCache = self::$documentRoot . "public_cache/";
		self::$settings = self::$documentRoot . "settings/";
		
		self::$apis = self::$application . "apis/";
		self::$configs = self::$application . "configs/";
		self::$controllers = self::$application . "controllers/";
		self::$errors = self::$application . "errors/";
		self::$helpers = self::$application . "helpers/";
		self::$languages = self::$application . "languages/";
		self::$libraries = self::$application . "libraries/";
		self::$models = self::$application . "models/";
		self::$processes = self::$application . "processes/";
		self::$views = self::$application . "views/";
	}
}