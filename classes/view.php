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
 * Load a view and transform it into a DOM element
 *
 * @category	core
 * @package		sinergi
 * @author		Sinergi Team
 * @link		https://github.com/sinergi/sinergi
 */

use sinergi\DOM;

require_once Path::$core . 'dom/dom.php';

class View {
	/**
	 * Complete tree of elements
	 * 
	 * @var	array
	 */
	private $elements = [];
	
	/**
	 * True if a view is being loaded. This way, we can track if a view is called from within another view.
	 * 
	 * @var	bool
	 */
	public static $loading = false;
		
	/**
	 * Load a view
	 * 
	 * @param	string	the view path to load
	 * @param	array	the arguments passed from the controller to the view
	 * @param	array	the module the view is in
	 * @return	void
	 */	
	public function __construct( $view, $args = null, $module = null ) {
		// Get View file name
		if (!isset($module)) {
			$file = Path::$views . trim($view, ' /') . '.php';
		} else {
			$file = Path::$modules . $module . '/views/' . trim($view, ' /') . '.php';
		}
				
		// Define variables passed to the view.
		if ($args != null) foreach ($args as $key=>$value) $$key = $value;
		
		// Load the view
		ob_start();
		require $file;
		$content = trim(ob_get_contents());
		ob_clean();
					
		// Split the doctype from the rest of the document
		$views = [];
		$doctype = null;
		if (!isset($html)) {
			$views = explode(PHP_EOL, $content, 2);
			if (
				preg_match('/doctype/', $views[0])
			) {
				$doctype = $views[0];
			} else {
				$views[1] = $content;
			}
			
			$content = $views[1];
		}
		
		// Check if view has elements
		if (!empty($content)) {
			// Create a document tree
			$this->elements = $this->createTree($content, $doctype);
		} else {
			trigger_error("Loaded the empty view {$view}", E_USER_ERROR);
		}
	}
	
	/**
	 * Create an element tree from the view
	 * 
	 * @param	string	the view content to create a tree from
	 * @param	string	the doctype of the view
	 * @return	void
	 */ 
	private function createTree( $view, $doctype = null ) {		
		$elements = [];
		
		// Load view content into a DOMDocument object
		$tree = new DOMDocument();		
		
		if (Request::$fileType === 'xml') {
			$tree->preserveWhiteSpace = false;
			$view = trim($view);
			@$tree->loadXML($view);
		} else {
			$view = mb_convert_encoding(trim($view), 'HTML-ENTITIES', 'UTF-8');
			@$tree->loadHTML($view);
		}
		
		// Append doctype to DOM
		if (!empty($doctype)) {
			$node = $tree->createCDATASection($doctype);
			$tree->firstChild->parentNode->insertBefore($node, $tree->firstChild);
		}
				
		// Load XML
		if (Request::$fileType === 'xml') {
			foreach($tree->childNodes as $child) {
				$elements[] = $this->createElement($child);
			}
		// Load HTML
		} else {
			// Check if view has HTML tags
			if (preg_match('{<html.+</html>}msU', $view)) {		
				foreach ($tree->childNodes as $child) {
					// Avoid empty html nodes
					if ($child->nodeName !== 'html' || $child->hasChildNodes()) { 
						
						// Add to elements					
						if ($child->nodeName==='html') $elements[] = $this->createElement($child); 
						else $elements[] = $this->createElement($child);
					}
				}
			} else {
				// Create Elements object from each nodes from the tree
				foreach ($tree->childNodes as $child) {
					if ($child->nodeName === 'html' && $child->hasChildNodes()) {
				    	foreach ($child->childNodes as $child2) {
							if ( $child2->nodeName === 'body' && $child2->hasChildNodes() ) {
				    			foreach ($child2->childNodes as $child3) {
									$elements[] = $this->createElement($child3);
				    			}
							}
				    	}
					}
				}
			}
		}
		
		$elements = array_filter($elements);		
		return $elements;
	}
	
	
	/**
	 * Create childs element in DOM. 
	 *
	 * @param	DOMNode	the view content to create a tree from
	 * @param	Element	the parent element
	 * @return	Element
	 */
	private function createElement( $item, $parent = null ) {
		// Append text to current element
		if ( $item->nodeName === '#cdata-section' && trim($item->textContent) !== '' ) {
			$element = node(trim($item->textContent), 'cdatasection');
		} else if ( $item->nodeName === '#comment' && trim($item->textContent) !== '' ) {
			$element = node(trim($item->textContent), 'comment');
		} else if ( $item->nodeName === '#text' && trim($item->textContent) !== '' ) {
			$element = node(trim($item->textContent), 'text');
		} else if ( substr($item->nodeName, 0, 1) !== '#' ) {
			$element = new Element($item->nodeName);
		}
			    
		// Inject element into parent if element has parent
		if ( isset($element) && isset($parent) ) {
			// For quick fix on spaces in text nodes being removed when injecting object (TO BE CHANGED)
			if ( substr($this->prevElement, 0, 1) == '#' && substr($item->nodeName, 0, 1) !== '#' ) $parent->appendText(' ');
			
			$element->inject($parent);
			
			// For quick fix on spaces in text nodes being removed when injecting object (TO BE CHANGED)
			if ( substr($this->prevElement, 0, 1) == '#' && substr($item->nodeName, 0, 1) !== '#' ) $parent->appendText(' ');
		}
		
		// For quick fix on spaces in text nodes being removed when injecting object (TO BE CHANGED)
		$this->prevElement = $item->nodeName;
		
		// Check if $item has attributes
		if ($item->hasAttributes()) {
			// Loop through attributes and apply them to the element
			foreach ($item->attributes as $attr) {
				$element->set($attr->name, $attr->value);
			}
		}
		
	    // Check if element has childs
	    if ($item->hasChildNodes()) {
	    	foreach ($item->childNodes as $child) {
				// Insert element in DOM
	    		$this->createElement($child, $element);
	    	}
	    }
		
		if (isset($element)) {
			return $element;
		}
	}
	
	/**
	 * Get an element from a group of elements
	 * 
	 * @param	Element	the element to inject the view into
	 * @param	string	where to inject the group (before|after|top|bottom)
	 * @return	self
	 */
	private function getElementGroup( $selector ) {
		foreach($this->elements as $element) {
			$nodeType = get_class($element);
			if ($nodeType === 'Element') {
				if (selector($selector, $element->element)) {
					return $element;
				} else if ($element->element->hasChildNodes()) {
					$child = $element->getElement($selector);
					if ($child) return $child;
				}
			}
		}
	}
	
	/**
	 * Inject group of elements
	 * 
	 * @param	Element	the element to inject the view into
	 * @param	string	where to inject the group (before|after|top|bottom)
	 * @return	self
	 */
	private function injectGroup( $element = null, $where = null ) {
		if ( isset($where) && $where !== "bottom" && $where !== "top" && $where !== "before" && $where !== "after" ) {
			trigger_error("Supplied argument is not a valid string", E_USER_NOTICE);
			return $this;
		}
				
		// If no element is given, inject element directly into the DOM
		if (!isset($element)) {
			$element = new stdClass();
			$element->element = DOM::$dom;
		
		} else if ((!is_object($element) || !isset($element->element))) {
			trigger_error("Supplied argument is not a valid Element resource", E_USER_NOTICE);
			
			return $this;
		}
		
		// If injecting to top or after, invert order of objects to end up having the original order
		if (isset($where) and ($where=="top" || $where=="after")) {
			$neworder = elements_reverse($this->elements); // Check for php aray_reverse, we had a bug so we implemented our own function
			unset($this->elements);
			$this->elements = array();
			$this->elements = $neworder;
		}
		
		foreach ($this->elements as $item) $item->inject($element, $where);

		return $this;
	}
		
	/**
	 * Any method called to the group pass through this method. 
	 * This method has not been tested with other callss than inject, please test before using.
	 * This method should trigger an error if a call is made to an unknown method. 
	 * 
	 * @param	string	the method being called
	 * @param	array	the arguments of the method
	 * @return	self
	 */
	public function __call( $method, $args ) {
		if(count($this->elements) === 1) {
			return current($this->elements)->__call($method, $args);
		} else {
			switch($method) {
				case 'inject':
					call_user_func_array([$this, 'injectGroup'], $args);
					break;
				case 'getElement':
					return call_user_func_array([$this, 'getElementGroup'], $args);
					break;
				default: 
					foreach ($this->elements as $element) { 
						$element->__call($method, $args);
					}
					break;
			}
		}
		
		return $this;
	}
}