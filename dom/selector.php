<?php

class DOMSelector {	
	/**
	 * Store the last matched node name in case of :nth-of-type, :nth-last-of-type, :first-of-type or :last-of-type pseudo selectors
	 * 
	 * @var	string
	 */
	private static $nodeName = '*';
	
	/**
	 * Split selector into parts
	 * 
	 * @param	string
	 * @return	array
	 */
	private static function splitSelector( $selector ) {
		if (empty($selector) || !is_string($selector)) {
			trigger_error("Selector is not a valid string resource", E_USER_NOTICE);
			return false;
		}
		
		// Clean selector
		$selector = trim(preg_replace('/\s{2,}|\t/', ' ', $selector), '& ');
		$selector = preg_replace('/ ?([=\[,\(#:-]|[\*\|\^~\$]=|\"|\') ?/', '$1', $selector);
		$selector = preg_replace('/^&/', '', $selector);
		
		// Sanitize values in parenthesis
		$selector = preg_replace('/\(([^\)]*)\)/e', '"(".rawurlencode("$1").")"', $selector);
		
		// Split separators
		return preg_split("/( ?> ?| ?\+ ?| ?~[^=] ?| )/", $selector, -1, PREG_SPLIT_NO_EMPTY|PREG_SPLIT_DELIM_CAPTURE); // Split parts of the selector
	}
			
	/**
	 * Find an element using a CSS selector
	 * 
	 * @param	mixed
	 * @param	string
	 * @return	object(DOMElement)
	 */
	public static function findElement( $elements, $selector ) {
		$parts = self::splitSelector( $selector );
				
		$match = null;
		$separator = null;
		do {
			if (preg_match('/>|\+|~[^=]| /', current($parts))) {
				$separator = current($parts);
			} else {				
				$current = $match;
				$match = null;
				$selector = current($parts);
				
				// Single element
				if (isset($current) && $current) {
					$match = self::loopElements( $current, $selector, $separator );
				} else if (is_object($elements)) {
					if ($elements instanceof DOMElement) {
						$match = self::loopElements( $elements, $selector, $separator );
					}
				} else if (is_array($elements)) {
					foreach($elements as $element) {
						if ($element instanceof DOMElement) {
							if ($match = self::loopElements( $element, $selector, $separator, true )) {
								break;
							}
						}
					}
				}
							
				if (next($parts)) {
					$separator = current($parts);
				} else {
					$separator = null;
				}
			}
		} while(next($parts));

		return $match;
	}
	
	/**
	 * Loop through elements
	 * 
	 * @param	object(DOMElement)
	 * @param	string
	 * @param	string
	 * @param	bool
	 * @return	object(DOMElement)
	 */
	private static function loopElements( $element, $selector, $separator, $matchSelf = false ) {
		if (!$element instanceof DOMElement) return false;
		
		switch($separator) {
			// Search 
			case '>':
				$conditions = self::conditionsParser($selector);
				
				// Try to match itself 
				if ($matchSelf) {
					if (self::selectorMatch($element, $conditions)) {
						return $element;
					}
				
				// Match element's childs
				} else {
					foreach($element->childNodes as $node) {
						if (self::selectorMatch($node, $conditions)) {
							return $node;
						}
					}
				}
				
				break;
				
			// Search 
			case '+':
				
				break;
			
			// Search 
			case '~':
				
				break;
				
			// Search all elements
			case ' ': default: 
				$conditions = self::conditionsParser($selector);
				// Try to match itself 
				if ($matchSelf && self::selectorMatch($element, $conditions)) {
					return $element;
				
				// Match element's childs
				} else {
					foreach($element->childNodes as $node) {
						if (self::selectorMatch($node, $conditions)) {
							return $node;
						} else if ($node->hasChildNodes()) {
							// Loop through childs
							if ($match = self::loopElements($node, $selector, $separator)) {
								return $match;
							}
						}
					}
				}
				break;
		}		
	}
	
	/**
	 * Try to match a node to a selector.
	 * 
	 * @param	object(mixed)
	 * @param	array
	 * @return	bool
	 */
	private static function selectorMatch( $node, $conditions ) {				
		foreach($conditions as $condition) {
			$attribute = $condition[0];
			$value = $condition[1];
			$negation = $condition[2];
			
			switch($attribute) {
				// Match an element tag
				case 'element':
					// Normal match
					if (!$negation && $value !== '*' && strtolower($node->nodeName) !== strtolower($value)) {
						return false;
					
					// Match with negation
					} else if ($negation && ($value === '*' || strtolower($node->nodeName) === strtolower($value))) {
						return false;
						
					} else {
						self::$nodeName = $node->nodeName;
					}
					break;
				
				// Match an element id
				case 'id':
					if (!$negation && (!$node->hasAttribute($attribute) || $node->getAttribute($attribute) !== $value)) {
						return false;
					} else if ($negation &&  $node->getAttribute($attribute) === $value) {
						return false;
					}
					break;
				
				// Match one of the element's classes
				case 'class':
					if(!$negation && !$node->hasAttribute($attribute)) {
						return false;
					} else {
						$classes = explode(' ', $node->getAttribute($attribute));
						if (!$negation && !in_array($value, $classes)) {
							return false;
						} else if ($negation && in_array($value, $classes)) {
							return false;
						}
					}
					break;
				
				// Match an attribute
				case 'attr':
					if (!self::matchAttribute( $node, $value, $negation )) {
						return false;
					}
					break;
				
				// Match an attribute
				case 'pseudo':
					if (!self::matchPseudo( $node, $value, $negation )) {
						return false;
					}
					break;
			}
		}
		
		return true;
	}
	
	/**
	 * Try to match an element attribute to a selector
	 * 
	 * @param	object(mixed)
	 * @param	string
	 * @param	bool
	 * @return	bool
	 */
	private static function matchAttribute( $node, $selector, $negation = false ) {
		$parts = preg_split('/([\~\^\$\*\|]?=)/', $selector, -1, PREG_SPLIT_NO_EMPTY|PREG_SPLIT_DELIM_CAPTURE); // Split parts of the selector
		$attribute = $parts[0];
		
		// Attribute exists
		if (!isset($parts[1]) && $node->hasAttribute($attribute)) return ($negation ? false : true);
		
		// Try to match the attribute
		else if (isset($parts[2]) && $node->hasAttribute($attribute)) {
			$operator = $parts[1];
			$string = trim($parts[2], '\'" ');
			
			switch($operator) {
				// Attribute value is exactly equal to the string
				case '=':
					if ($node->getAttribute($attribute) === $string) {
						return ($negation ? false : true);
					}
					break;
				
				// Attribute value is a list of whitespace-separated values, one of which is exactly equal to the string
				case '~=':
					$list = explode(' ', $node->getAttribute($attribute));
					if (in_array($string, $list)) {
						return ($negation ? false : true);
					}
					break;
				
				// Attribute value begins exactly with the string
				case '^=':
					if (preg_match('/^'.$string.'/', $node->getAttribute($attribute))) {
						return ($negation ? false : true);
					}
					break;
				
				// Attribute value ends exactly with the string
				case '$=':
					if (preg_match('/'.$string.'$/', $node->getAttribute($attribute))) {
						return ($negation ? false : true);
					}
					break;
				
				// Attribute value value contains the string
				case '*=':
					if (preg_match('/'.$string.'/', $node->getAttribute($attribute))) {
						return ($negation ? false : true);
					}
					break;
				
				// Attribute has a hyphen-separated list of values beginning (from the left) with string
				case '|=':
					$list = explode('-', $node->getAttribute($attribute));
					if (isset($list[0]) && $list[0] === $string) {
						return ($negation ? false : true);
					}
					break;
				
			}
		}

		// No match has been found
		return ($negation ? true : false);
	}
	
	/**
	 * Try to match an element pseudo to a selector
	 * 
	 * @param	object(mixed)
	 * @param	string
	 * @param	bool
	 * @return	bool
	 */
	private static function matchPseudo( $node, $pseudo, $negation = false ) {
		$math = preg_replace('/.*\(([^\)]*)\)/', '$1', $pseudo);
		$pseudo = preg_replace('/(\(.*\))/', '', $pseudo);
		
		$calculate = function( $string ) {
			if (empty($string)) return 0;
			$math = create_function("", "return (" . $string . ");");
			return 0 + $math();
		};
		
		switch($pseudo) {
			// Element is at root of the document
			case 'root':
				if (isset($node->parentNode) && $node->parentNode instanceof DOMDocument) {
					return ($negation ? false : true);
				}
				break;
			
			// Element is n-th child of its parent
			case 'nth-child': 
			// Element is the n-th child of its parent, counting from the last one
			case 'nth-last-child':
			// Element is the the n-th sibling of its type
			case 'nth-of-type': 
			// Element is the n-th sibling of its type, counting from the last one
			case 'nth-last-of-type': 
				if (isset($node->parentNode)) {
					// Match "n" and return true
					if (preg_match('/^ *(n|n *\+ *0|1n|1n *\+ *0) *$/', $math)) return ($negation ? false : true);
					
					$parent = $node->parentNode;
					
					$count = 0;
					$position = 0;
					foreach($parent->childNodes as $child) {
						if(
							$child instanceof DOMElement && 
								($pseudo == 'nth-child' || $pseudo == 'nth-last-child') ||
								(($pseudo == 'nth-of-type' || $pseudo == 'nth-last-of-type') && ($child->nodeName == self::$nodeName || self::$nodeName == '*'))
						) {
							$count++;
							if ($child->isSameNode($node)) $position = $count;
						}
					}
											
					// If counting from the last one, inverse the element's position
					if ($pseudo == 'nth-last-child' || $pseudo == 'nth-last-of-type') {
						$position = ($count-$position+1);
					}

					$math = trim(str_replace('n', $position-1, preg_replace('/(\-|\+)?n/', '*$1n', $math)), '*');
					$value = $calculate($math);
											
					if ($position === $value) {
						return ($negation ? false : true);
					}
				}
				break;
			
			// Element is the first child of its parent
			case 'first-child':
			// Element is the first sibling of its type
			case 'first-of-type': 
				if (isset($node->parentNode)) {
					foreach($node->parentNode->childNodes as $child) {
						if (
							$child instanceof DOMElement &&
							($pseudo == 'first-child') || 
							($pseudo == 'first-of-type' && $child->nodeName == self::$nodeName)
						) break;
					}
					
					if ($child->isSameNode($node)) {
						return ($negation ? false : true);
					}
				}
				break;
			
			// Element is the last child of its parent
			case 'last-child':
			// Element is the last sibling of its type
			case 'last-of-type':
				if (isset($node->parentNode)) {
					$child = $node->parentNode->lastChild;
					
					do {
						if (
							$child instanceof DOMElement && 
							($pseudo == 'last-child') || 
							($pseudo == 'last-of-type' && $child->nodeName == self::$nodeName)
						) break;
					} while($child = $child->previousSibling);
											
					if ($child->isSameNode($node)) {
						return ($negation ? false : true);
					}
				}
				break;
				
			// Element is the only child of its parent
			case 'only-child':
			// Element is the only sibling of its type
			case 'only-of-type':
				if (isset($node->parentNode)) {
					$count = 0;
					foreach($node->parentNode->childNodes as $child) {
						if (
							$child instanceof DOMElement && 
							($pseudo == 'only-child') || 
							($pseudo == 'only-of-type' && $child->nodeName == self::$nodeName)
						) {
							$count++;
							if ($count > 1) break;
						}
					}
					
					if ($count === 1) return ($negation ? false : true);
				}
				break;
			
			// Element has no children (including text nodes)
			case 'empty':
				if (!$node->hasChildNodes()) return ($negation ? false : true);
				break;
			
		}
		return ($negation ? true : false);
	}
		
	/**
	 * Parse CSS Selector conditions and covert them to an array
	 * 
	 * @param	string
	 * @return	array
	 */
	private static function conditionsParser( $selector ) {
		$coditions = [];
		
		$selector = rawurldecode($selector);
		
		// Match negation pseudo-classes
		preg_match_all('/:not\(([^\)]*)\)/i', $selector, $matches);
		$negations = [];
		foreach($matches[1] as $negation) {
			$parts = explode(',', $negation);
			if (count($parts) > 1) {
				// Fix multiple attributes separator being only on first part
				$attr = false;
				foreach($parts as $key=>$part) {
					if ($attr) $parts[$key] = '[' . $part;
					
					if (preg_match('/^\[/', $part)) $attr = true;
					else if (preg_match('/\]$/', $part)) $attr = false;
				}

				$negations = array_merge($negations, $parts);
			} else {
				$negations[] = $negation;
			}
		}
						
		$selector = preg_replace('/:not\([^\)]*\)/', '', $selector);
		
		// Split parts of the selector
		$separators = '(:|,|\[|#|\.)';
		$parts = preg_split('/'.$separators.'/', $selector, -1, PREG_SPLIT_NO_EMPTY|PREG_SPLIT_DELIM_CAPTURE); // Split parts of the selector
		
		// Build conditions
		$separator = null;
		do {
			if (preg_match('/^'.$separators.'/', current($parts))) {
				$separator = current($parts);
			} else {
				$selector = current($parts);
				switch($separator) {
					case "#":
						$coditions[] = ['id', $selector, false];
						break;
					case ".":
						$coditions[] = ['class', $selector, false];
						break;
					case ':':
						$coditions[] = ['pseudo', $selector, false];
						break;
					case '[': case ',':
						$selector = trim($selector, '[]');
						$coditions[] = ['attr', $selector, false];
						break;
					default:
						if (!empty($selector)) {
							$coditions[] = ['element', $selector, false];
						}
						break;
				}
			}
		} while(next($parts));
		
		// Add negation pseudo-class conditions
		foreach($negations as $negation) {
			$separator = preg_replace('/^'.$separators.'(.*)/', '$1', $negation);
			
			switch($separator) {
				case "#":
					$negation = preg_replace('/^#/', '', $negation);
					$coditions[] = ['id', $negation, true];
					break;
				case ".":
					foreach(explode('.', $negation) as $negation) {
						if (!empty($negation)) {
							$coditions[] = ['class', $negation, true];
						}
					}
					break;
				case ':':
					foreach(explode(':', $negation) as $negation) {
						if (!empty($negation)) {
							$coditions[] = ['pseudo', $negation, true];
						}
					}
					break;
				case '[': case ',':
					$negation = trim($negation, '[]');
					$coditions[] = ['attr', $negation, true];
					break;
				default:
					if (!empty($negation)) {
						$coditions[] = ['element', $negation, true];
					}
					break;
			}
		}
		
		return $coditions;
	}
}