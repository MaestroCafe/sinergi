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
 * Send emails using Postmark API
 *
 * @category	core
 * @package		sinergi
 * @author		Sinergi Team
 * @link		https://github.com/sinergi/sinergi
 */

namespace sinergi;

use Path,
	POSTMARKAPP_API_KEY,
	Mail_Postmark;

global $settings;

if (!file_exists(Path::$libraries . "Postmark/Postmark.php")) trigger_error("You need to add postmark-php as a submodule of your application in application/libraries/Postmark", E_USER_ERROR);
if (!isset($settings['postmark']['api_key'])) trigger_error('You need to define a Postmark API Key in your settings as $settings["postmark"]["api_key"]', E_USER_ERROR);

require_once Path::$libraries . "Postmark/Postmark.php";
if (!defined('POSTMARKAPP_API_KEY')) define('POSTMARKAPP_API_KEY', $settings['postmark']['api_key']);

trait Email {
	/**
	 * Compose a new email
	 * 
	 * @param	string
	 * @param	string
	 * @param	string
	 * @param	string
	 * @param	string
	 * @param	array
	 * @return	bool
	 */
	private function compose( $fromName, $fromEmail, $toName, $toEmail, $file, $args=[] ) {		
		// Prepend path to file if it is not already
		if (!preg_match('{^'.Path::$documentRoot.'}', $file)) {
			$file = Path::$application . $file;
		}
		
		// Get file content
		if (!file_exists($file)) trigger_error('Email ('.$file.') file does not exists', E_USER_ERROR);
		$body = str_replace('’', '\'', file_get_contents($file));
		
		// Replace tags by values
		if (count($args)) {
			$body = $this->_vsprintf($body, $args);
		}
		
		// Get title
		preg_match('/<title>(.*)<\/title>/', $body, $subject);
		$subject = mb_convert_encoding(html_entity_decode(strip_tags($subject[0])), 'ISO-8859-1', 'HTML-ENTITIES');
				
		// Create a message and send it
		if(
			Mail_Postmark::compose()
				->from($fromEmail, $fromName)
				->addTo($toEmail, $toName)
				->subject($subject)
				->messageHtml($body)
				->send()
		) {	
			return true;
		} else {
			return false;
		}
	}
	
	/**
	 * Same as vsprintf but compatible with HTML, it search and replace %s
	 * 
	 * @param	string
	 * @param	array
	 * @return	string
	 */
	private function _vsprintf($format, $args) {
		$search = '%s';
		foreach ($args as $replace) {
			$format = $this->_str_replace_once($search, $replace, $format);
		}
		return $format;
	}
		
	/**
	 * Search and replace only once
	 * 
	 * @param	string
	 * @param	string
	 * @param	string
	 * @return	string
	 */
	private function _str_replace_once($search, $replace, $subject) {
		$res = strpos($subject, $search);
		if ($res === false) {
			return $subject;
		} else {
			// There is data to be replaces
			$left_seg = substr($subject, 0, strpos($subject, $search));
			$right_seg = substr($subject, (strpos($subject, $search) + strlen($search)));
			return $left_seg . $replace . $right_seg;
		}
	}
}