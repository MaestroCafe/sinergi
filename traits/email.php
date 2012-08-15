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
	POSTMARKAPP_MAIL_FROM_ADDRESS,
	POSTMARKAPP_MAIL_FROM_NAME,
	Mail_Postmark;

global $settings;

if (!file_exists(Path::$libraries . "Postmark/Postmark.php")) trigger_error("You need to add postmark-php as a submodule of your application", E_USER_ERROR);
if (!isset($settings['postmark']['api_key'])) trigger_error("You need to define a Postmark API Key", E_USER_ERROR);

require_once Path::$libraries . "Postmark/Postmark.php";
if (!defined('POSTMARKAPP_API_KEY')) define('POSTMARKAPP_API_KEY', $settings['postmark']['api_key']);

trait Email {
	protected function compose($from_name, $from_email, $to_name, $to_email, $file, $args=[]) {		
		$body = str_replace('’', '\'', file_get_contents(APPLICATION.$file));
				
		if (count($args)) {
			$body = $this->_vsprintf($body, $args);
		}
		
		preg_match('/<title>(.*)<\/title>/', $body, $subject);
		$subject = mb_convert_encoding(html_entity_decode(strip_tags($subject[0])), 'ISO-8859-1', 'HTML-ENTITIES');
				
		// Create a message and send it
		if(Mail_Postmark::compose()
		    ->from($from_email, $from_name)
		    ->addTo($to_email, $to_name)
		    ->subject($subject)
		    ->messageHtml($body)
		    ->send()) 
		    	{ return true; }
		
		return false;
	}
	
	/**
	 * Same as vsprintf but compatible with HTML, in other words, it search and replace %s
	 * 
	 * @param $provider
	 * @var bool
	 * @access private
	 * @return const
	 */
	protected function _vsprintf($format, $args) {
		$search = '%s';
		foreach ($args as $replace) {
			$format = $this->_str_replace_once($search, $replace, $format);
		}
		return $format;
	}
		
	/**
	 * Search and replace only once
	 * 
	 * @param $provider
	 * @var bool
	 * @access private
	 * @return const
	 */
	protected function _str_replace_once($search, $replace, $subject) {
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