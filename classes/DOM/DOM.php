<?php

require CORE.'classes/DOM/selectors.php';
require CORE.'classes/DOM/element.php';

/**
 * DOM class.
 * This class creates the DOM.
 * 
 * @extends DOMDocument
 */
class DOMDocumentExtended extends DOMDocument {
	/**
	 * Creates the DOM and configure it.
	 * 
	 * @return void
	 */
	function __construct() {
		parent::__construct('1.0');
		$this->formatOutput = true;
		$this->encoding = "UTF-8";
		$this->element = $this;
	}
	
	/**
	 * Get the content of a node by importing it in a separate DOM.
	 * Is this function necessary, seems a little out of place and complicated
	 * 
	 * @return string
	 */
	function nodeContent($n, $outer=false) { 
			$d = new DOMDocument('1.0'); 
			$b = $d->importNode($n->cloneNode(true),true); 
			$d->appendChild($b); $h = $d->saveHTML(); 
			if (!$outer) $h = substr($h,strpos($h,'>')+1,-(strlen($n->nodeName)+4)); 
			return $h; 
	}
}

/**
 * Function to create a node.
 *
 * @return element
 */
function node($string, $node) {
	$node .= 'Node'; $return = new $node($string); return $return; 
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
	 * @return element
	 */
	public function inject($element=null, $where=null) {
		if (isset($where) and $where!='bottom' and $where!='top' and $where!='before' and $where!='after') {
			trigger_error('inject where');
			return $this;
		}
		
		/**
		 * If no element is given, inject element directly into the DOM. 
		 *
		 */
		if (!isset($element)) { global $DOM; $element = $DOM; }
		
		else if (!is_object($element) or !isset($element->element)) {
			trigger_error('inject');
			return $this;
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


/**
 * Create text and comments nodes. 
 *
 */
class textNode extends Element {
	function __construct($string) { global $DOM; $this->element = $DOM->createTextNode($string); }
}
class commentNode extends Element {
	function __construct($string) { global $DOM; $this->element = $DOM->createComment($string); }
}
class cdatasectionNode extends Element {
	function __construct($string) { global $DOM; $this->element = $DOM->createCDATASection($string); }
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
		/**
		 * Element 2 inject in Element 1 at top. 
		 *
		 */
		if (isset($args[1])&&$args[1]==top) $element->insertBefore($args[0]->element, $element->firstChild);
		/**
		 * Else place at bottom. 
		 *
		 */
		else $element->appendChild($args[0]->element);
	}
}

/**
 * Adopt (multiple Grab). 
 *
 */
class adopt extends Element {
	public function __construct($element, $args) {
		/**
		 * Element 1 grabs all elements. 
		 *
		 */
		if (is_array($args[0])) {
			foreach($args[0] as $child)
				$element->appendChild($child->element);
		/**
		 * Else Element 1 grabs one or two child. 
		 *
		 */
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
		/**
		 * Move element 1 just before element 2. 
		 *
		 */
		$args[0]->element->parentNode->insertBefore($element, $args[0]->element);
		/**
		 * Element 1 grabs element 2. 
		 *
		 */
		$element->appendChild($args[0]->element);
	}
}

/**
 * Empty. 
 *
 */
function emptyNode($element) {
	/**
	 * Recursive for delete all children. 
	 *
	 */
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
		/**
		 * Fetch all classes. 
		 *
		 */
		$classes = explode(' ', $element->getAttribute('class-fixed-tmp'));
		/**
		 * Check all classes and "return" true if found. 
		 *
		 */
		foreach($classes as $class) if ($args[0]==$class) $this->output = true;
		/**
		 * Else "return" false. 
		 *
		 */
		if (!isset($this->output)) $this->output = false;
	}
}

/**
 * addClass. 
 *
 */
class addClass extends Element {
	public function __construct($element, $args) {
		/**
		 * Add class if class doesn'T already exist. 
		 *
		 */
 		if ($element->hasClass($args[0])==false) $element->set('class-fixed-tmp', ($element->get('class')=='' ? $args[0] : $element->get('class').' '.$args[0]));
	}
}

/**
 * removeClass. 
 *
 */
class removeClass extends Element {
	public function __construct($element, $args) {
		/**
		 * Fetch all classes. 
		 *
		 */
		$classes = explode(' ', $element->getAttribute('class-fixed-tmp'));
		/**
		 * Check all classes. 
		 *
		 */
		$remains = null;
		foreach($classes as $pos=>$class) {
			/**
			 * Keep all the classes to keep. 
			 *
			 */
			if ($class!=$args[0]) {
				if (!isset($remains)) $remains = $class;
				else $remains = ' '.$class;
			}
		}
		/**
		 * Reset class with remaining classes. 
		 *
		 */
		$element->setAttribute('class-fixed-tmp', $remains);
	}
}

/**
 * toggleClass. 
 *
 */
class toggleClass extends Element {
	public function __construct($element, $args) {
		/**
		 * Fetch all classes. 
		 *
		 */
		$classes = explode(' ', $element->getAttribute('class-fixed-tmp'));
		/**
		 * Check all classes. 
		 *
		 */
		foreach($classes as $pos=>$class) {
			/**
			 * If not the class to remove, keep it!. 
			 *
			 */
			if ($class!=$args[0]) {
				if (!isset($remains)) $remains = $class;
				else $remains .= ' '.$class;
			} else {
				$found = true;
			}
		}
		/**
		 * If class was not found, add it. 
		 *
		 */
		if (!isset($found)) {
			if (isset($remains)) $remains .= ' '.$args[0];
			else $remains = $args[0];
		}
		/**
		 * Reset class with remaining classes. 
		 *
		 */
		$element->setAttribute('class-fixed-tmp', $remains);
	}
}

/**
 * Set properties. 
 *
 */
class set extends Element {
	public function __construct($element, $args) {
		global $DOM;
		/**
		 * Set properties if multiple properties are passed. 
		 *
		 */
		if (is_array($args[0])) foreach($args[0] as $attribute=>$value)
			/**
			 * Class Attributes. 
			 *
			 */
			if ($attribute=='class') $element->setAttribute('class-fixed-tmp', $value);
			/**
			 * Normal Attributes. 
			 *
			 */
			else if ($attribute!='html' and $attribute!='text') $element->setAttribute($attribute, $value);
			/**
			 * Insert content. 
			 *
			 */
			else {
				/**
				 * Delete content. 
				 *
				 */
				emptyNode($element);
				/**
				 * Insert Text content. 
				 *
				 */
				$element->appendChild($DOM->createCDATASection($value));
			}
		/**
		 * Set property if one property is passed . 
		 *
		 */
		else {
			$attribute = $args[0];
			if (isset($args[1])) $value = $args[1]; else $value = 'attribute-fixed-tmp';
			/**
			 * Class Attributes. 
			 *
			 */
			if ($attribute=='class') $element->setAttribute('class-fixed-tmp', $value);
			/**
			 * Normal Attributes. 
			 *
			 */
			else if ($attribute!='html' and $attribute!='text') $element->setAttribute($attribute, $value);
			/**
			 * Insert content. 
			 *
			 */
			else {
				/**
				 * Delete content. 
				 *
				 */
				emptyNode($element);
				/**
				 * Insert Text content. 
				 *
				 */
				$element->appendChild($DOM->createCDATASection($value));
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
		global $DOM;
		/**
		 * Class Attributes. 
		 *
		 */
		if ($args[0]=='class') $this->output = $element->hasAttribute('class-fixed-tmp') ? $element->getAttribute('class-fixed-tmp') : $element->getAttribute($args[0]);
		/**
		 * Tag name. 
		 *
		 */
		else if ($args[0]=='tag') $this->output = $element->nodeName;
		/**
		 * Normal Attributes. 
		 *
		 */
		else if ($args[0]!='html' and $args[0]!='text') $this->output = $element->getAttribute($args[0]);
		/**
		 * Content. 
		 *
		 */
		else $this->output = $DOM->nodeContent($element);
	}
}

/**
 * Append Text. 
 *
 */
class appendText extends Element {
	public function __construct($element, $args) {
		global $DOM;
		if (isset($args[1])&&$args[1]!=bottom) {
			switch($args[1]) {
				case top: $element->insertBefore($DOM->createCDATASection($args[0]), $element->firstChild); break;
				case before: $element->parentNode->insertBefore($DOM->createCDATASection($args[0]), $element); break;
				case after:
						$element->parentNode->insertBefore($DOM->createCDATASection($args[0]), $element->nextSibling);
					break;
			}
		} else {
			$element->appendChild($DOM->createCDATASection($args[0]));
		}
	}
}

/**
 * Erase property. 
 *
 */
class erase extends Element {
	public function __construct($element, $args) {
		/**
		 * Class Attributes. 
		 *
		 */
		if ($args[0]=='class') $element->removeAttribute('class-fixed-tmp');
		/**
		 * Normal Attributes. 
		 *
		 */
		else if ($args[0]!='html' and $args[0]!='text') $element->removeAttribute($args[0]);
		/**
		 * Delete text. 
		 *
		 */
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
		/**
		 * Get styles. 
		 *
		 */
		foreach(array_filter(explode(';', $element->get('style'))) as $style) {
			$style = explode(':', $style);
			$styles[trim($style[0])] = trim($style[1]);
		}
		
		/**
		 * Set styles. 
		 *
		 */
		if (is_array($args[0])) {
			foreach($args[0] as $attribute=>$value) {
				/**
				 * Automatic pixel if integer. 
				 *
				 */
				if (is_integer($value)) $value .= 'px';
				/**
				 * Set new style. 
				 *
				 */
				$styles[$attribute] = $value;
			}
			
		/**
		 * Set style. 
		 *
		 */
		} else {
			/**
			 * Automatic pixel if integer. 
			 *
			 */
			if (is_integer($args[1])) $args[1] .= 'px';
			/**
			 * Set new style. 
			 *
			 */
			$styles[$args[0]] = $args[1];
		}		
		
		/**
		 * Prepare string style. 
		 *
		 */
		$string = '';
		foreach($styles as $attribute=>$value) $string .= ' '.$attribute.': '.$value.';';
		
		/**
		 * Set style. 
		 *
		 */
		$element->set('style', trim($string));
	}
}

/**
 * Get style. 
 *
 */
class getStyle extends Element {
	public function __construct($element, $args) {
		/**
		 * Get all styles. 
		 *
		 */
		$styles = explode(';', $element->getAttribute('style'));
		/**
		 * Check all styles for match. 
		 *
		 */
		foreach($styles as $style) {
			/**
			 * Get property and value. 
			 *
			 */
			$property = explode(':', trim($style));
			$value = isset($property[1]) ? trim($property[1]) : null;
			$property = trim($property[0]);
			/**
			 * Check if property is match. 
			 *
			 */
			if ($args[0]==$property)
				/**
				 * Check if value is number. 
				 *
				 */
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
		/**
		 * Get all styles. 
		 *
		 */
		$styles = explode(';', $element->getAttribute('style'));
		/**
		 * Check all styles for match. 
		 *
		 */
		foreach($styles as $style) {
			/**
			 * Get property and value. 
			 *
			 */
			$property = explode(':', trim($style));
			$value = isset($property[1]) ? trim($property[1]) : null;
			$property = trim($property[0]);
			/**
			 * Check if property is match. 
			 *
			 */
			if ($args[0]==$property)
				/**
				 * Check if value is number. 
				 *
				 */
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
		
		/**
		 * If needs to find different elements. 
		 *
		 */
		if (strstr($hints, ',') and $all) {
			$this->array = array();
			$hints = explode(',', $hints);
			foreach($hints as $hint) $this->array = array_merge_recursive($this->array, $element->$method(trim($hint)));
		/**
		 * Else find element. 
		 *
		 */
		} else {
			$this->findElement($element, $hints, $method, $all);
		}
		
	}
	public function findElement($element, $hints, $method, $all) {
		/**
		 * Separate CSS and start from last. 
		 *
		 */
		$hints = array_reverse(array_filter(explode(' ', $hints)));
		
		/**
		 * Prepare actions. 
		 *
		 */
		/**
		 * Fetch all child for the getElements. 
		 *
		 */
		if (strstr($method, 'getElement')) $elements = $element->getElements($hints[0]);
		/**
		 * For the rest, it's a little bit more particular. 
		 *
		 */
		else {
			/**
			 * Get first tags hint first with the method. 
			 *
			 */
			$first = $element->$method($hints[count($hints)-1]);
			/**
			 * Eleminate first tag from hint as it has been found. 
			 *
			 */
			$hints = array_splice($hints, 0, -1);
			/**
			 * Get all last tag hint. 
			 *
			 */
			if (isset($first->element) or is_array($first)) {
				$elements = array();
				/**
				 * If fetch many, get all matching children together. 
				 *
				 */
				if (is_array($first)) foreach($first as $node) $elements = array_merge_recursive($elements, $node->getElements($hints[0]));
				/**
				 * Else get only matching children for the one node found. 
				 *
				 */
				else $elements = $first->getElements($hints[0]);
				
			}
			/**
			 * Special case for getLast, reverse elements to search last first. 
			 *
			 */
			if ($method=='getLast') $elements = array_reverse($elements);
		}
		
		/**
		 * Continue only if children found. 
		 *
		 */
		if (count($elements)>0)
			
			/**
			 * Check all children found. 
			 *
			 */
			foreach($elements as $key=>$el)
			
				/**
				 * Search can continue as long as element is not found (won't affect getAll). 
				 *
				 */
				if (!isset($this->element))

					/**
					 * Check through each hint. 
					 *
					 */
					foreach($hints as $key2=>$hint) {
						
						/**
						 * Use hint only if a tag name and not the first tag hint (we already have them if we are here). 
						 *
						 */
						if ($hint!='>' and $hint!='+' and $hint!='~' and $key2!=0 and isset($el->element)) {
						
							switch($hints[$key2-1]) {
								/**
								 * Check if immediate parent is the same. 
								 *
								 */
								case '>':
									$tmp = $el->getParent();
									$tmp2 = $el->getParent($hint);
									if (isset($tmp->element) and isset($tmp2->element) and $tmp->match($tmp2)) $el = $tmp;
									else $el = false;
									break;
								/**
								 * Check if immediate previous is the same. 
								 *
								 */
								case '+':
									$tmp = $el->getPrevious();
									$tmp2 = $el->getPrevious($hint);
									if (isset($tmp->element) and isset($tmp2->element) and $tmp->match($tmp2)) $el = $tmp;
									else $el = false;
									break;
								/**
								 * Check if any next is the same. 
								 *
								 */
								case '~':
									$el = $el->getNext($hint);
									break;
								/**
								 * Check any parent. 
								 *
								 */
								default:
									$el = $el->getParent($hint);
									break;
							}
						}
						
						/**
						 * Check if found. 
						 *
						 */
						if (isset($el->element) and $key2==count($hints)-1) {
							if ($all==false) $this->element = $elements[$key]->element;
							else $this->array[] = $elements[$key];
						}
						/**
						 * Else stop looking for now and check other. 
						 *
						 */
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
				/**
				 * Only check if node is an element and not a cdata. 
				 *
				 */
				if ($node->nodeType==1) {
					/**
					 * Check if node is our element and break foreach. 
					 *
					 */
					if (selector($match, $node)) {
						$this->element = $node;
						unset($this->notFound);
						break;
					/**
					 * Else check its children. 
					 *
					 */
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
		/**
		 * Check inside element. 
		 *
		 */
		if ($element->hasChildNodes()) $this->chkElement($element, $args, $method);
		$this->output = $this->array;
	}
	public function chkElement($element, $args, $method) {
		foreach($element->childNodes as $node) {
			/**
			 * Only check if node is an element and not a cdata. 
			 *
			 */
			if ($node->nodeType==1) {
				/**
				 * Get child. 
				 *
				 */
				if (!isset($args[0])) $this->array[] = (new el($node));
				/**
				 * Check if node is a matched element. 
				 *
				 */
				else if (selector($args[0], $node)) $this->array[] = (new el($node));
				/**
				 * Keep looking. 
				 *
				 */
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
		/**
		 * Fetch element. 
		 *
		 */
		if (!isset($args[0])) {
			if ($element->$get)
				$this->element = $element->$get;
			else
				$this->notFound = true;
		/**
		 * Else fetch matched element. 
		 *
		 */
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
		/**
		 * Get all elements if not match. 
		 *
		 */
		if (!isset($args[0])) while ($element = $element->$get) $this->array[] = (new el($element));
		/**
		 * If match, get only those you need. 
		 *
		 */
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
		/**
		 * Get first child. 
		 *
		 */
		if (!isset($args[0])) {
			$this->element = $child;
		/**
		 * Else, check for matched starting from first child. 
		 *
		 */
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
		/**
		 * Check all children. 
		 *
		 */
		$this->chkChild($element, $args);
		/**
		 * Set to false if none found. 
		 *
		 */
		if (!isset($this->output)) $this->output = false;
	}
	/**
	 * Recursive function to check all children. 
	 *
	 */
	public function chkChild($child, $args) {
		/**
		 * Check while it is not found. 
		 *
		 */
		if (!isset($this->output)) {
			/**
			 * If it's this element, set output to TRUE. 
			 *
			 */
			if (selector($args[0], $child)) $this->output = true;
			/**
			 * Else try to find it in its children if exists. 
			 *
			 */
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
		/**
		 * Check node similarity. 
		 *
		 */
		if (isset($args[0]->element) and $element->isSameNode($args[0]->element)) $this->output = true;
		/**
		 * Else check if selector match. 
		 *
		 */
		else if (!isset($args[0]->element) and selector($args[0], $element)) $this->output = true;
		/**
		 * Else return false. 
		 *
		 */
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

