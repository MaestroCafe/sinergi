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
	DOMDocument;

require_once Path::$core . 'dom/manipulation.php';
require_once Path::$core . 'dom/selectors.php';
require_once Path::$core . 'dom/element.php';

class DOM {
	/**
	 * The DOM element
	 * 
	 * @var	DOMDocument
	 */
	public static $dom;
		
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
	public static function write() {
		$content = self::$dom->saveHTML();
		$content = str_replace(['class-fixed-tmp', '="attribute-fixed-tmp"'], ['class', ''], $content);
		$content = mb_convert_encoding($content, 'UTF-8', 'HTML-ENTITIES');
		
		return $content;
	}
	
	/**
	 * Get the content of a node by importing it in a separate DOM.
	 * 
	 * @param	DOMNode	the element get the content from
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
	 * Create a new DOM element
	 * 
	 * @param	string	the element type to create
	 * @return	DOMElement
	 */
	public static function createElement( $element ) {
		if (!isset(self::$dom)) self::createDOM();
		
		return self::$dom->createElement( $element );
	}

	/**
	 * Create a text node that will be untouched
	 * 
	 * @param	string	the string of text to transform into a text node
	 * @return	DOMCDATASection
	 */
	public static function createCode( $text ) {
		if (!isset(self::$dom)) self::createDOM();
		
		return self::$dom->createCDATASection( $text );
	}

	/**
	 * Create a text node
	 * 
	 * @param	string	the string of text to transform into a text node
	 * @return	DOMText
	 */
	public static function createText( $text ) {
		if (!isset(self::$dom)) self::createDOM();
		
		return self::$dom->createTextNode( $text );
	}

	/**
	 * Create a comment node
	 * 
	 * @param	string	the string of text to transform into a comment node
	 * @return	DOMComment
	 */
	public static function createComment( $text ) {
		if (!isset(self::$dom)) self::createDOM();
		
		return self::$dom->createComment( $text );
	}
}