<?php

namespace sinergi;

use sinergi\DOM,
	sinergi\DOMSelector,
	View,
	DOMDocument,
	DOMElement;

class DOMManipulator {
	/**
	 * Verify data passed to inject method
	 *
	 * @param	object(Element)
	 * @param	string
	 * @return	array
	 */
	protected function injectParser( $element, $where ) {
		// Verify that the where parameter is valid
		$where = strtolower(trim($where));
		if ($where !== "bottom" && $where !== "top" && $where !== "before" && $where !== "after" ) {
			trigger_error("Failed to inject element group: parameter \$where is not valid", E_USER_NOTICE);
			return $this;
			
		}
		
		// If no element parameter is provided, the element is injected directly in the DOM
		if (!isset($element)) {
			$element = DOM::$dom;
		
		// If element provided is view, use View::$element as the element
		} else if( $element instanceof View ) {
			if (!isset($element->element->element)) {
				trigger_error("Failed to inject element group: cannot inject into an element group", E_USER_NOTICE);
				return $this;
			} else {
				$element = $element->element->element;
			}
		}
		
		// Validate the element parameter
		if ( !$element instanceof DOMDocument && !$element instanceof DOMElement ) {
			trigger_error("Failed to inject element group: parameter \$element is not a valid resource", E_USER_NOTICE);
			return $this;
		}
				
		return [$element, $where];
	}
	
	/**
	 * Set attribute helper
	 * 
	 * @param	object(DOMElement)
	 * @param	array
	 * @return	void
	 */
	protected function setAttrHelper( $element, $attributes ) {
		if ($element instanceof DOMElement) {
			foreach($attributes as $attr=>$value) {
				switch($attr) {
					case 'html':
						$this->emptyElement($element);
						$element->appendChild(DOM::createCode($value));
						break;
					case 'text':
						$this->emptyElement($element);
						$element->appendChild(DOM::createText(strip_tags($value)));
						break;
					default:
						$element->setAttribute($attr, $value);
						break;
				}
			}
		}
	}
	
	/**
	 * Empty an element
	 * 
	 * @param	object(DOMElement)
	 * @return	void
	 */
	protected function emptyElement( $element ) {
		while($element->hasChildNodes()) {
			$element->removeChild($element->firstChild);
		}
	}
	
	/**
	 * Add class helper
	 * 
	 * @param	object(DOMElement)
	 * @param	string
	 * @return	void
	 */
	protected function addClassHelper( $element, $class ) {
		if ($element instanceof DOMElement) {
 			$classes = explode(' ', $element->getAttribute('class'));
 			if (!in_array($class, $classes)) {
	 			$currentClasses = $element->getAttribute('class');
	 			$element->setAttribute('class', (empty($currentClasses) ? $class : "{$currentClasses} {$class}"));
 			}
 		}
	}
	
}
