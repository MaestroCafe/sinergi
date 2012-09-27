<?php

use sinergi\DOM;

class View {
	/**
	 * The element object
	 * 
	 * @var	array
	 */
	public $element;
	
	/**
	 * Load a view
	 * 
	 * @param	string	the view path to load
	 * @param	array	the arguments passed from the controller to the view
	 * @param	array	the module the view is in
	 * @return	void
	 */	
	public function __construct( $view, $args = null ) {
		// Check if view is called from a module
		$trace = debug_backtrace();
		if (isset($trace[1]) && isset($trace[1]['class']) && preg_match('/^modules\\\/i', $trace[1]['class'])) {
			$module = preg_replace('/modules\\\([^\\\]*)(.*)/', '$1', $trace[1]['class']);
		}
		
		// Get View file name
		if (!isset($module)) {
			$file = Path::$views . trim($view, ' /') . '.php';
		} else {
			$file = Path::$modules . $module . '/views/' . trim($view, ' /') . '.php';
		}
				
		// Define variables passed to the view.
		if (isset($args) && !is_array($args)) {
			trigger_error("Parameter \$args passed to view '{$view}' is not an array", E_USER_ERROR);

		} else if(isset($args)) {
			foreach ($args as $key=>$value) $$key = $value;
		}
		
		// Load the view
		if (file_exists($file)) {
			ob_start();
			require $file;
			$content = trim(ob_get_contents());
			ob_clean();
						
			// Split the doctype from the rest of the document
			$doctype = null;
			if (preg_match('/<!DOCTYPE html>/', $content, $matches)) {
				$doctype = $matches[0];
			}
				
			// Check if view has elements
			if (empty($content) || !$this->element = $this->createElement($content, $doctype)) {
				trigger_error("Failed to load view '{$view}': File is not valid HTML", E_USER_ERROR);
			}
		} else {
			trigger_error("Failed to load view '{$view}': No such file", E_USER_ERROR);
			
		}
	}
	
	/**
	 * Create an from the view
	 * 
	 * @param	string	the view content to create a tree from
	 * @param	string	the doctype of the view
	 * @return	object(Element)|object(ElementGroup)
	 */ 
	public function createElement( $view, $doctype = null ) {				
		// Load view content into a DOMDocument object
		$tree = new DOMDocument();
		
		if (Request::$fileType === 'xml') {
			$tree->preserveWhiteSpace = false;
			$view = trim($view);
			@$tree->loadXML($view);
		} else {
			$view = str_replace(['&#34;', '&#39;'], ['[SANITIZEDDOUBLEQUOTES]', '[SANITIZEDSINGLEQUOTES]'], $view);
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
			if (count($tree->childNodes) === 1) {
				return new Element( $tree->childNodes->item(0) );
			} else {
				return new ElementGroup( $tree->childNodes );
			}
		
		// Load HTML
		} else {
			// Check if view has HTML tags
			if (preg_match('{<html.+</html>}msU', $view)) {
				// Remove double HTML tags created by DOMDocument and count childrens
				$html = null;
				$count = 0;
				foreach ($tree->childNodes as $node) {
					if ($node->nodeName === 'html') { 
						if (isset($html)) {
							$html->parentNode->removeChild($html);
							$count--;
						}
						$html = $node;
					}
					$count++;
				}

				if ($count === 1) {
    				return new Element( $tree->childNodes->item(0) );
				} else {
					return new ElementGroup( $tree->childNodes );
				}
				
			// Otherwise use DOM inside body tag
			} else {
				// Create Elements object from each nodes from the tree
				foreach ($tree->childNodes as $htmlNode) {
					if ($htmlNode->nodeName === 'html' && $htmlNode->hasChildNodes()) {
				    	foreach ($htmlNode->childNodes as $bodyNode) {
							if ( $bodyNode->nodeName === 'body' && $bodyNode->hasChildNodes() ) {
								// Count childrens
								$count = 0;
								foreach($bodyNode->childNodes as $node) $count++;
								
								if ($count === 1) {
				    				return new Element( $bodyNode->childNodes->item(0) );
								} else {
									return new ElementGroup( $bodyNode->childNodes );
								}
							}
				    	}
				    	break;
					}
				}
			}
		}
		
		return false;
	}
		
	/**
	 * This should redirect valid methods to the Object in $this->element
	 * 
	 * @param	string
	 * @param	array
	 * @return	self
	 */
	public function __call( $method, $args ) {
		if (is_object($this->element) && (get_class($this->element) === 'Element' || get_class($this->element) === 'ElementGroup')) {
			switch($method) {
				case 'inject': case 'getElement': case 'destroy':
					return call_user_func_array([$this->element, $method], $args);
					break;
				default: 
					trigger_error("Call to undefined method View::{$method}()", E_USER_ERROR);
					break;
			}
		} else {
			trigger_error("Call to method View::{$method}() on invalid View", E_USER_ERROR);
		}
		
		return $this;
	}
}
