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
 * The process class is the base class for all process containing functions for the processes
 *
 * @category	core
 * @package		sinergi
 * @author		Sinergi Team
 * @link		https://github.com/sinergi/sinergi
 */

class Process {
	/**
	 * Run the process as a background process from a PHP Script
	 * 
	 * @return	void
	 */
	public function run() {
		$processName = preg_replace('{^modules/([^/]*)/(processes)}', 'modules/\1', str_replace('\\', '/', get_class($this))); // Get name of the current process (child class)
		shell_exec('php -q ' . Path::$core . "init/init.php {$processName} > /dev/null &"); // Execute process
	}
}