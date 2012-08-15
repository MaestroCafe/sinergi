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
 * DOM Manipulation functions.
 *
 * TO BE CLEANED UP IN A FUTURE RELEASE OF SINERGI.
 *
 * @category	core
 * @package		sinergi
 * @author		Sinergi Team
 * @link		https://github.com/sinergi/sinergi
 */

use sinergi\DOM;

/**
 * Function to create a node.
 *
 * @return element
 */
function node($string, $node) {
	$node .= 'Node';
	$return = new $node($string);
	return $return; 
}

/**
 * Function to create a text element.
 *
 * @return element
 */
function textNode($string) { $return = new textNode($string); return $return; }

/**
 * Function to create a comment element.
 *
 * @return element
 */
function commentNode($string) { $return = new commentNode($string); return $return; }


/**
 * This function reverse the order of elements or gruops of elements inside a group. 
 *
 * @return elements
 */
function elements_reverse($elements) {
 	$neworder = array();
 	
 	foreach ($elements as $key=>$element) {
 		if (is_array($element)) {
 			$element = elements_reverse($element);
 		}
 		$neworder[count($elements)-$key-1] = $element;
 	}
 	ksort($neworder);
 	return $neworder;
}

/**
 * Element class.
 * This class contains all of the functions to manipulate DOM elements.
 * 
 * @extends DOMDocument
 */
class DOMManipulation {
	
	/**
	 * Inject Element. 
	 *
	 * @param	$element	the element to inject the view into
	 * @param	$where		where to inject the group (before|after|top|bottom)
	 * @return self
	 */
	public function inject( $element = null, $where = null ) {
		if ( isset($where) && $where !== "bottom" && $where !== "top" && $where !== "before" && $where !== "after" ) {
			trigger_error("Supplied argument is not a valid string", E_USER_NOTICE);
			return $this;
		}
		
		if (!isset($element)) {
			$element = new stdClass();
			$element->element = DOM::$dom;
		}
		
		if ( !is_object($element) || (get_class($element) !== 'DOMDocument' && !isset($element->element))) {
			trigger_error("Supplied argument is not a valid Element resource", E_USER_NOTICE);
			return $this;
		}
		
		if (get_class($element->element) === 'DOMDocument') {
			DOM::$childs[] = $this;
		}
		
		if (isset($where) and $where!='bottom') {
			switch($where) {
				case 'top': $element->element->insertBefore($this->element, $element->element->firstChild); break;
				case 'before': $element->element->parentNode->insertBefore($this->element, $element->element); break;
				case 'after': $element->element->parentNode->insertBefore($this->element, $element->element->nextSibling); break;
			}
		} else {			
			$element->element->appendChild($this->element);
		}
		
		return $this;
	}
	
	/**
	 * Any method called to the element pass through this method. 
	 * This method should be deprecated if we can insert all these methods as real methods.
	 * 
	 * @return void
	 */
	public function __call($method, $args) {
		global $DOM;
		switch ($method) {
			/**
			 * Place element. 
			 * Inject has been replaced by a method directly into the class.
			 * This should be applied also to all methods here.
			 *
			 */
			case 'inject': $this->inject((isset($args[0]) ? $args[0]:null), (isset($args[1]) ? $args[1]:null)); break;
			case 'grab': new grab($this->element, $args); break;
			case 'adopt': new adopt($this->element, $args); break;
			case 'wraps': new wraps($this->element, $args); break;
			case 'replaces': $args[0]->element->parentNode->replaceChild($this->element, $args[0]->element); break;
			/**
			 * Clones or destroy. 
			 *
			 */
			case 'clone': $clone = clone $this; return $clone; break;
			case 'dispose': $clone = clone $this; $this->element->parentNode->removeChild($this->element); return $clone; break;
			case 'destroy': if ($this->element->parentNode) $this->element->parentNode->removeChild($this->element); return null; break;
			case 'empty': emptyNode($this->element); break;
			/**
			 * Class. 
			 *
			 */
			case 'hasClass': return (new hasClass($this->element, $args))->output; break;
			case 'addClass': new addClass($this, $args); break;
			case 'removeClass': new removeClass($this->element, $args); break;
			case 'toggleClass': new toggleClass($this->element, $args); break;
			/**
			 * Properties. 
			 *
			 */
			case 'set': new set($this->element, $args); break;
			case 'get': return (new get($this->element, $args))->output; break;
			case 'appendText': new appendText($this->element, $args); break;
			case 'erase': new erase($this->element, $args); break;
			case 'setStyle':
			case 'setStyles': new setStyle($this, $args); break;
			case 'getStyle': return (new getStyle($this->element, $args))->output; break;
			case 'getStyles': return (new getStyles($this->element, $args))->output; break;
			case 'clear': (new element('div', array('class'=>'clear', 'style'=>'float: none; clear: '.(isset($args[0]) ? $args[0] : 'left').';')))->inject($this); break;
			/**
			 * Get element. 
			 *
			 */
			case 'getElement': 
				if (isset($args[0]) and strstr($args[0], " ")) return new parseElement($this, $args[0], $method, false);
				else {
					$return = new getElement($this->element, $args);
					if (isset($return->notFound)) {
						return false;
					} else return $return;
				}
				break;
			case 'getElements':
			case 'getChildren':
				if (isset($args[0]) and strstr($args[0], " ")) return (new parseElement($this, $args[0], $method, true))->array;
				else return (new getElements($this->element, $args, $method))->array;
				break;
			case 'getParent': 
			case 'getPrevious': 
			case 'getNext':
				if (isset($args[0]) and strstr($args[0], " ")) return new parseElement($this, $args[0], $method, false);
				else {
					$return = new getOne($this->element, $args, $method);
					if (isset($return->notFound)) return false;
					else return $return;
				}
				break;
			case 'getParents':
			case 'getAllPrevious':
			case 'getAllNext':
				if (isset($args[0]) and strstr($args[0], " ")) return (new parseElement($this, $args[0], $method, true))->array;
				else return (new getAll($this->element, $args, $method))->array;
				break;
			case 'getSiblings': return array_merge_recursive($this->getAllPrevious((isset($args[0]) ? $args[0] : null)), $this->getAllNext((isset($args[0]) ? $args[0] : null))); break;
			case 'getFirst':
			case 'getLast':
				if (isset($args[0]) and strstr($args[0], " ")) return new parseElement($this, $args[0], $method, false);
				else return new getFirstLast($this->element, $args, $method);
				break;
			/**
			 * True or false. 
			 *
			 */
			case 'hasChild': return (new hasChild($this->element, $args))->output; break;
			case 'match' : return (new match($this->element, $args))->output; break;
			/**
			 * Data (this->newKey->value). 
			 *
			 */
			case 'store' : $this->$args[0] = $args[1]; break;
			case 'retrieve' : return (new retrieve($this, $args))->output; break;
			case 'eleminate' : (isset($this->$args[0]) ? eleminate($this, $args) : null); break;
			/**
			 * HTML Loader. 
			 *
			 */
			case 'import': break;
		}
		return $this;
	}

	/**
	 * Gives a string instead of an object where necessary. 
	 *
	 * @return string
	 */
	public function __toString() { return isset($this->output) ? $this->output : ""; }
	
	/**
	 * This is necessary for proper cloning. 
	 *
	 * @return void
	 */
	public function __clone() { $this->element = clone $this->element; }
}
