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
 * DOM Element funtions.
 *
 * TO BE CLEANED UP IN A FUTURE RELEASE OF SINERGI.
 *
 * @category	core
 * @package		sinergi
 * @author		Sinergi Team
 * @link		https://github.com/sinergi/sinergi
 */

use sinergi\DOM;

class Element extends DOMManipulation {
	/**
	 * Create the element
	 * 
	 * @param	string	the element type to create
	 * @param	array	the properties to give the element
	 * @return	void
	 */
	public function __construct( $element, $properties = null ) {
		// Create Element
		$this->element = DOM::createElement($element);
		
		// Create attributes
		if (is_array($properties)) {
			foreach($properties as $attribute=>$value) {
				// Only value
				if (is_numeric($attribute)) $this->element->setAttribute($value, 'attribute-fixed-tmp');
				
				// Class Attributes
				else if ($attribute === 'class') $this->element->setAttribute('class-fixed-tmp', $value);
				
				// Style
				else if ($attribute === 'style' and is_array($value)) new setStyle($this, [$value]);
				
				// Normal Attributes
				else if ($attribute !== 'html' && $attribute !== 'text') $this->element->setAttribute($attribute, $value);
				
				// Insert Text content
				else $this->element->appendChild(DOM::createCode($value));
			}
		}
		
		// Img element
		if ($element=='img') {
			if (!isset($properties['width'])) $this->element->setAttribute('width', '');
			if (!isset($properties['height'])) $this->element->setAttribute('height', '');
			if (!isset($properties['alt'])) $this->element->setAttribute('alt', '');
		}
	}
	
	/**
	 * Return the element as a string
	 * 
	 * return	string
	 */
	public function __toString() {
		/*global $DOM;

		$clone = clone $this; // Clone the node
		$element = $DOM->appendChild($clone->element); // Append the clone
		 
		$doc = new DOMDocument('1.0'); // Create a new empty document
		$new_element = $doc->importNode($element, true); // Import the clone
		$new_element = $doc->appendChild($new_element); 
		
		$clone->destroy(); // Destroy the clone
		unset($clone);
		
		return $doc->saveHTML();*/
		return '';
	}
}



/**
 * Create text and comments nodes. 
 *
 */
class textNode extends Element {
	function __construct($string) { $this->element = DOM::createText($string); }
}
class commentNode extends Element {
	function __construct($string) { $this->element = DOM::createComment($string); }
}
class cdatasectionNode extends Element {
	function __construct($string) { $this->element = DOM::createCode($string); }
}

/**
 * Make node element extend to all methods. 
 *
 */
class el extends Element {
	public function __construct($element) {
		$this->element = $element;
	}
}

/**
 * Grab. 
 *
 */
class grab extends Element {
	public function __construct($element, $args) {
		// Element 2 inject in Element 1 at top 
		if (isset($args[1])&&$args[1]==top) $element->insertBefore($args[0]->element, $element->firstChild);
		// Else place at bottom 
		else $element->appendChild($args[0]->element);
	}
}

/**
 * Adopt (multiple Grab). 
 *
 */
class adopt extends Element {
	public function __construct($element, $args) {
		// Element 1 grabs all elements
		if (is_array($args[0])) {
			foreach($args[0] as $child)
				$element->appendChild($child->element);
		// Else Element 1 grabs one or two child 
		} else {
			$element->appendChild($args[0]->element);
			if (isset($args[1])) $element->appendChild($args[1]->element);
		}
	}
}

/**
 * Wraps. 
 *
 */
class wraps extends Element {
	public function __construct($element, $args) {
		// Move element 1 just before element 2 
		$args[0]->element->parentNode->insertBefore($element, $args[0]->element);
		// Element 1 grabs element 2 
		$element->appendChild($args[0]->element);
	}
}

/**
 * Empty. 
 *
 */
function emptyNode($element) {
	// Recursive for delete all children 
	while($element->hasChildNodes()) {
		emptyNode($element->firstChild);
		$element->removeChild($element->firstChild);
	}
}

/**
 * hasClass. 
 *
 */
class hasClass extends Element {
	public function __construct($element, $args) {
		// Fetch all classes 
		$classes = explode(' ', $element->getAttribute('class-fixed-tmp'));
		// Check all classes and "return" true if found 
		foreach($classes as $class) if ($args[0]==$class) $this->output = true;
		// Else "return" false 
		if (!isset($this->output)) $this->output = false;
	}
}

/**
 * addClass. 
 *
 */
class addClass extends Element {
	public function __construct($element, $args) {
		// Add class if class doesn'T already exist 
 		if ($element->hasClass($args[0])==false) $element->set('class-fixed-tmp', ($element->get('class')=='' ? $args[0] : $element->get('class').' '.$args[0]));
	}
}

/**
 * removeClass. 
 *
 */
class removeClass extends Element {
	public function __construct($element, $args) {
		// Fetch all classes 
		$classes = explode(' ', $element->getAttribute('class-fixed-tmp'));
		// Check all classes 
		$remains = null;
		foreach($classes as $pos=>$class) {
			// Keep all the classes to keep 
			if ($class!=$args[0]) {
				if (!isset($remains)) $remains = $class;
				else $remains = ' '.$class;
			}
		}
		// Reset class with remaining classes 
		$element->setAttribute('class-fixed-tmp', $remains);
	}
}

/**
 * toggleClass. 
 *
 */
class toggleClass extends Element {
	public function __construct($element, $args) {
		// Fetch all classes 
		$classes = explode(' ', $element->getAttribute('class-fixed-tmp'));
		// Check all classes 
		foreach($classes as $pos=>$class) {
			// If not the class to remove, keep it! 
			if ($class!=$args[0]) {
				if (!isset($remains)) $remains = $class;
				else $remains .= ' '.$class;
			} else {
				$found = true;
			}
		}
		// If class was not found, add it 
		if (!isset($found)) {
			if (isset($remains)) $remains .= ' '.$args[0];
			else $remains = $args[0];
		}
		// Reset class with remaining classes 
		$element->setAttribute('class-fixed-tmp', $remains);
	}
}

/**
 * Set properties. 
 *
 */
class set extends Element {
	public function __construct($element, $args) {
		// Set properties if multiple properties are passed 
		if (is_array($args[0])) foreach($args[0] as $attribute=>$value)
			// Class Attributes 
			if ($attribute=='class') $element->setAttribute('class-fixed-tmp', $value);
			// Normal Attributes 
			else if ($attribute!='html' and $attribute!='text') $element->setAttribute($attribute, $value);
			// Insert content 
			else {
				// Delete content 
				emptyNode($element);
				// Insert Text content 
				$element->appendChild(DOM::createCode($value));
			}
		// Set property if one property is passed  
		else {
			$attribute = $args[0];
			if (isset($args[1])) $value = $args[1]; else $value = 'attribute-fixed-tmp';
			// Class Attributes 
			if ($attribute=='class') $element->setAttribute('class-fixed-tmp', $value);
			// Normal Attributes 
			else if ($attribute!='html' and $attribute!='text') $element->setAttribute($attribute, $value);
			// Insert content 
			else {
				// Delete content 
				emptyNode($element);
				// Insert Text content 
				$element->appendChild(DOM::createCode($value));
			}
		}
	}
}

/**
 * Get property. 
 *
 */
class get extends Element {
	public function __construct($element, $args) {
		// Class Attributes 
		if ($args[0]=='class') $this->output = $element->hasAttribute('class-fixed-tmp') ? $element->getAttribute('class-fixed-tmp') : $element->getAttribute($args[0]);
		// Tag name 
		else if ($args[0]=='tag') $this->output = $element->nodeName;
		// Normal Attributes 
		else if ($args[0]!='html' and $args[0]!='text') $this->output = $element->getAttribute($args[0]);
		// Content 
		else $this->output = $DOM->nodeContent($element);
	}
}

/**
 * Append Text. 
 *
 */
class appendText extends Element {
	public function __construct($element, $args) {
		if (isset($args[1])&&$args[1]!=bottom) {
			switch($args[1]) {
				case top: $element->insertBefore(DOM::createCode($args[0]), $element->firstChild); break;
				case before: $element->parentNode->insertBefore(DOM::createCode($args[0]), $element); break;
				case after:
						$element->parentNode->insertBefore(DOM::createCode($args[0]), $element->nextSibling);
					break;
			}
		} else {
			$element->appendChild(DOM::createCode($args[0]));
		}
	}
}

/**
 * Erase property. 
 *
 */
class erase extends Element {
	public function __construct($element, $args) {
		// Class Attributes 
		if ($args[0]=='class') $element->removeAttribute('class-fixed-tmp');
		// Normal Attributes 
		else if ($args[0]!='html' and $args[0]!='text') $element->removeAttribute($args[0]);
		// Delete text 
		else emptyNode($element);
	}
}

/**
 * Set style. 
 *
 */
class setStyle extends Element {
	public function __construct($element, $args) {
		if (isset($element->array)) foreach($element->array as $node) $this->style($node, $args);
		else $this->style($element, $args);
	}
	public function style($element, $args) {
		// Get styles 
		foreach(array_filter(explode(';', $element->get('style'))) as $style) {
			$style = explode(':', $style);
			$styles[trim($style[0])] = trim($style[1]);
		}
		
		// Set styles 
		if (is_array($args[0])) {
			foreach($args[0] as $attribute=>$value) {
				// Automatic pixel if integer 
				if (is_integer($value)) $value .= 'px';
				// Set new style 
				$styles[$attribute] = $value;
			}
			
		// Set style 
		} else {
			// Automatic pixel if integer 
			if (is_integer($args[1])) $args[1] .= 'px';
			// Set new style 
			$styles[$args[0]] = $args[1];
		}		
		
		// Prepare string style 
		$string = '';
		foreach($styles as $attribute=>$value) $string .= ' '.$attribute.': '.$value.';';
		
		// Set style 
		$element->set('style', trim($string));
	}
}

/**
 * Get style. 
 *
 */
class getStyle extends Element {
	public function __construct($element, $args) {
		// Get all styles 
		$styles = explode(';', $element->getAttribute('style'));
		// Check all styles for match 
		foreach($styles as $style) {
			// Get property and value 
			$property = explode(':', trim($style));
			$value = isset($property[1]) ? trim($property[1]) : null;
			$property = trim($property[0]);
			// Check if property is match 
			if ($args[0]==$property)
				// Check if value is number 
				if (ctype_digit(str_replace(array('em', 'px'), null, $value))) $this->output = trim(str_replace(array('em', 'px'), null, $value));
				else $this->output = $value;
		}
	}
}

/**
 * Get style. 
 *
 */
class getStyles extends Element {
	public function __construct($element, $args) {
		// Get all styles 
		$styles = explode(';', $element->getAttribute('style'));
		// Check all styles for match 
		foreach($styles as $style) {
			// Get property and value 
			$property = explode(':', trim($style));
			$value = isset($property[1]) ? trim($property[1]) : null;
			$property = trim($property[0]);
			// Check if property is match 
			if ($args[0]==$property)
				// Check if value is number 
				if (ctype_digit(str_replace(array('em', 'px'), null, $value))) $this->output = trim(str_replace(array('em', 'px'), null, $value));
				else $this->output = $value;
		}
	}
}

/**
 * This class has been written by Emmanuel, to be checked and re-written if possible. Espeacially the comments.
 * 
 * Parse Element. 
 * How it works:
 * Seperate CSS tag and start from last to first
 * Example: 'h2 + ul > li a' get all anchors first and work your way through backward if everything is true
 * It will stop if only one child is to be found, otherwise get all possible
 * 
 * getElement and getElements search all children first
 * The other must process the first tag and then get all last tag
 * Example: 
 * 	$el->getElement('h2 + ul > li a')
 * 	- Get all anchors and check if everything else is ok
 * 	$el->getAllNext('h2 + ul > li a')
 * 	- Get all next 'h2' first
 * 	- Then get all anchors and work up to the top
 * 
 */
class parseElement extends Element {
	public function __construct($element, $hints, $method, $all) {
		
		// If needs to find different elements 
		if (strstr($hints, ',') and $all) {
			$this->array = array();
			$hints = explode(',', $hints);
			foreach($hints as $hint) $this->array = array_merge_recursive($this->array, $element->$method(trim($hint)));
		// Else find element 
		} else {
			$this->findElement($element, $hints, $method, $all);
		}
		
	}
	public function findElement($element, $hints, $method, $all) {
		// Separate CSS and start from last 
		$hints = array_reverse(array_filter(explode(' ', $hints)));
		
		// Prepare actions 
		// Fetch all child for the getElements 
		if (strstr($method, 'getElement')) $elements = $element->getElements($hints[0]);
		// For the rest, it's a little bit more particular 
		else {
			// Get first tags hint first with the method 
			$first = $element->$method($hints[count($hints)-1]);
			// Eleminate first tag from hint as it has been found 
			$hints = array_splice($hints, 0, -1);
			// Get all last tag hint 
			if (isset($first->element) or is_array($first)) {
				$elements = array();
				// If fetch many, get all matching children together 
				if (is_array($first)) foreach($first as $node) $elements = array_merge_recursive($elements, $node->getElements($hints[0]));
				// Else get only matching children for the one node found 
				else $elements = $first->getElements($hints[0]);
				
			}
			// Special case for getLast, reverse elements to search last first 
			if ($method=='getLast') $elements = array_reverse($elements);
		}
		
		// Continue only if children found 
		if (count($elements)>0)
			
			// Check all children found 
			foreach($elements as $key=>$el)
			
				// Search can continue as long as element is not found (won't affect getAll) 
				if (!isset($this->element))

					// Check through each hint 
					foreach($hints as $key2=>$hint) {
						
						// Use hint only if a tag name and not the first tag hint (we already have them if we are here) 
						if ($hint!='>' and $hint!='+' and $hint!='~' and $key2!=0 and isset($el->element)) {
						
							switch($hints[$key2-1]) {
								// Check if immediate parent is the same 
								case '>':
									$tmp = $el->getParent();
									$tmp2 = $el->getParent($hint);
									if (isset($tmp->element) and isset($tmp2->element) and $tmp->match($tmp2)) $el = $tmp;
									else $el = false;
									break;
								// Check if immediate previous is the same 
								case '+':
									$tmp = $el->getPrevious();
									$tmp2 = $el->getPrevious($hint);
									if (isset($tmp->element) and isset($tmp2->element) and $tmp->match($tmp2)) $el = $tmp;
									else $el = false;
									break;
								// Check if any next is the same 
								case '~':
									$el = $el->getNext($hint);
									break;
								// Check any parent 
								default:
									$el = $el->getParent($hint);
									break;
							}
						}
						
						// Check if found 
						if (isset($el->element) and $key2==count($hints)-1) {
							if ($all==false) $this->element = $elements[$key]->element;
							else $this->array[] = $elements[$key];
						}
						// Else stop looking for now and check other 
						if (!isset($el->element)) break;
					}
	}
}

/**
 * Get Element.
 *
 */
class getElement extends Element {
	public function __construct($element, $args) {
		$this->notFound = true;
		if ($element->hasChildNodes())
			$this->chkElement($element, $args[0], (isset($args[1]) ? $args[1] : true));
	}
	
	/**
	 * Find child. 
	 *
	 */
	public function chkElement($element, $match, $child) {
		if (!isset($this->element)) {
			foreach($element->childNodes as $node) {
				// Only check if node is an element and not a cdata 
				if ($node->nodeType==1) {
					// Check if node is our element and break foreach 
					if (selector($match, $node)) {
						$this->element = $node;
						unset($this->notFound);
						break;
					// Else check its children 
					} else if ($child===true) {
						if ($node->hasChildNodes()) $this->chkElement($node, $match, true);
					}
				}
			}
		}
	}
}

/**
 * Get Elements (same as getElement but doesn't stop at one match);. 
 *
 */
class getElements extends Element {
	public function __construct($element, $args, $method) {	
		$this->array = array();
		// Check inside element 
		if ($element->hasChildNodes()) $this->chkElement($element, $args, $method);
		$this->output = $this->array;
	}
	public function chkElement($element, $args, $method) {
		foreach($element->childNodes as $node) {
			// Only check if node is an element and not a cdata 
			if ($node->nodeType==1) {
				// Get child 
				if (!isset($args[0])) $this->array[] = (new el($node));
				// Check if node is a matched element 
				else if (selector($args[0], $node)) $this->array[] = (new el($node));
				// Keep looking 
				if ($node->hasChildNodes() and $method=='getElements') $this->chkElement($node, $args, $method);
			}
		}
	}
}

/**
 * Get On Previous/Next/Parent element. 
 *
 */
class getOne extends Element {
	public function __construct($element, $args, $method) {
		switch($method) {
			case 'getParent': $get = 'parentNode'; break;
			case 'getPrevious': $get = 'previousSibling'; break;
			case 'getNext': $get = 'nextSibling'; break;
		}
		// Fetch element 
		if (!isset($args[0])) {
			if ($element->$get)
				$this->element = $element->$get;
			else
				$this->notFound = true;
		// Else fetch matched element 
		} else {
			$this->notFound = true;
			while ($element = $element->$get and !isset($this->element))
				if (selector($args[0], $element)) {
					$this->element = $element;
					unset($this->notFound);
				}
		}
	}
}

/**
 * Get All Previous/Next/Parent element. 
 *
 */
class getAll extends Element {
	public function __construct($element, $args, $method) {
		switch($method) {
			case 'getParents': $get = 'parentNode'; break;
			case 'getAllPrevious': $get = 'previousSibling'; break;
			case 'getAllNext': $get = 'nextSibling'; break;
		}
		$this->array = array();
		// Get all elements if not match 
		if (!isset($args[0])) while ($element = $element->$get) $this->array[] = (new el($element));
		// If match, get only those you need 
		else while ($element = $element->$get) if (selector($args[0], $element)) $this->array[] = (new el($element));
	}
}

/**
 * Get First/Last child. 
 *
 */
class getFirstLast extends Element {
	public function __construct($element, $args, $method) {
		switch($method) {
			case 'getFirst': $search = 'firstChild'; $other = 'nextSibling'; break;
			case 'getLast': $search = 'lastChild'; $other = 'previousSibling'; break;
		}
		$child = $element->$search;
		// Get first child 
		if (!isset($args[0])) {
			$this->element = $child;
		// Else, check for matched starting from first child 
		} else {
			$i = 0;
			while ($element = ($i==0 ? $element->$search : $element->$other) and !isset($this->element)) {
				if (selector($args[0], $element)) $this->element = $element;
				$i++;
			}
		}
	}
}

/**
 * Has Child elements. 
 *
 */
class hasChild extends Element {
	public function __construct($element, $args) {
		// Check all children 
		$this->chkChild($element, $args);
		// Set to false if none found 
		if (!isset($this->output)) $this->output = false;
	}
	/**
	 * Recursive function to check all children. 
	 *
	 */
	public function chkChild($child, $args) {
		// Check while it is not found 
		if (!isset($this->output)) {
			// If it's this element, set output to TRUE 
			if (selector($args[0], $child)) $this->output = true;
			// Else try to find it in its children if exists 
			else if ($child->hasChildNodes()) foreach($child->childNodes as $node) $this->chkChild($node, $args);
		}
	}
}

/**
 * Match. 
 *
 */
class match extends Element {
	public function __construct($element, $args) {
		// Check node similarity 
		if (isset($args[0]->element) and $element->isSameNode($args[0]->element)) $this->output = true;
		// Else check if selector match 
		else if (!isset($args[0]->element) and selector($args[0], $element)) $this->output = true;
		// Else return false 
		else $this->output = false;
	}
}

/**
 * Retrieve. 
 *
 */
class retrieve extends Element {
	public function __construct($element, $args) {
		if (isset($element->$args[0])) $this->output = $element->$args[0];
		else $this->output = false;
	}
}

/**
 * Eleminate. 
 *
 */
function eleminate($element, $args) { unset($element->$args[0]); }
