<?php

namespace sinergi;

use sinergi\DOM,
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
}
