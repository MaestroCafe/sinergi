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
 * This class provides a DOM to inject elements and views into as well as some methods to
 * create elements and other DOM related stuff.
 *
 * @category	core
 * @package		sinergi
 * @author		Sinergi Team
 * @link		https://github.com/sinergi/sinergi
 */

namespace sinergi;

use Path,
	DOMDocument,
	Request;

class DOM {
	/**
	 * The DOM element
	 * 
	 * @var	object(DOMDocument)
	 */
	public static $dom;
	
	/**
	 * The DOM's first childs
	 * 
	 * @var	array
	 */
	public static $childs;
	
	/**
	 * Creates the DOM and configure it
	 * 
	 * @return	void
	 */
	public static function createDOM() {
		self::$dom = new DOMDocument('1.0');
		self::$dom->formatOutput = true;
		self::$dom->encoding = "UTF-8";
	}
		
	/**
	 * Output the DOM
	 * 
	 * @return	string
	 */
	public static function write( $fileType = null ) {
		if (isset($fileType) && $fileType === 'xml' || !isset($fileType) && Request::$fileType === 'xml') {
			$content = self::$dom->saveXML();
			$content = str_replace('<![CDATA[ ]]>', '', $content); // We need to recheck where these come from
		} else if (isset($fileType) && $fileType === 'html' || !isset($fileType) && Request::$fileType === 'html') {
			$content = self::$dom->saveHTML();
		} else {
			trigger_error("File type does not support a DOM", E_USER_ERROR);
		}
		
		$content = mb_convert_encoding($content, 'UTF-8', 'HTML-ENTITIES');
		
		$content = str_replace(
			['[SANITIZEDDOUBLEQUOTES]', '[SANITIZEDSINGLEQUOTES]'], 
			['&#34;', '&#39;'], 
			$content
		);
		
		return self::minify($content);
	}
	
	/**
	 * Get the content of a node by importing it in a separate DOM.
	 * 
	 * @param	object(DOMNode)
	 * @return	string
	 */
	public static function nodeContent( $node ) {
		$dom = new DOMDocument('1.0'); 
		$node = $dom->importNode($node->cloneNode(true), true); 
		$dom->appendChild($node);
		$content = $dom->saveHTML();
		
		return $content; 
	}
	
	/**
	 * Import a node into the DOM
	 * 
	 * @param	object(DOMElement)
	 * @return	object(DOMElement)
	 */
	public static function importNode( $element ) {
		if (!isset(self::$dom)) self::createDOM();
		
		return self::$dom->importNode( $element, true );
	}
	
	/**
	 * Create a new DOM element
	 * 
	 * @param	string
	 * @return	object(DOMElement)
	 */
	public static function createElement( $element ) {
		if (!isset(self::$dom)) self::createDOM();
		
		return self::$dom->createElement( $element );
	}

	/**
	 * Create a text node that will be untouched
	 * 
	 * @param	string
	 * @return	object(DOMCDATASection)
	 */
	public static function createCode( $text ) {
		if (!isset(self::$dom)) self::createDOM();
		
		return self::$dom->createCDATASection( $text );
	}

	/**
	 * Create a text node
	 * 
	 * @param	string
	 * @return	object(DOMText)
	 */
	public static function createText( $text ) {
		if (!isset(self::$dom)) self::createDOM();
		
		return self::$dom->createTextNode( $text );
	}

	/**
	 * Create a comment node
	 * 
	 * @param	string
	 * @return	object(DOMComment)
	 */
	public static function createComment( $text ) {
		if (!isset(self::$dom)) self::createDOM();
		
		return self::$dom->createComment( $text );
	}
	
	/**
	 * Compress HTML output
	 *
	 * @param	string
	 * @return	string
	 */
	public static function minify($output) {
		return $output;
	}
}