<?php

use sinergi\DOM,
	sinergi\DOMSelector,
	sinergi\DOMManipulator;

class Element extends DOMManipulator implements Serializable {
	/**
	 * The DOMElement object
	 * 
	 * @var	array
	 */
	public $element;
	
	/**
	 * Create the element
	 * 
	 * @param	string
	 * @param	array
	 * @return	self
	 */
	public function __construct( $element, $properties = null ) {
		// Initalize a DOMElement element
		if ($element instanceof DOMElement) {			
			$this->element = DOM::importNode($element);
		
		// Initialize string element (<element>)
		} else if (is_string($element) && preg_match('/^\s*<.*>\s*$/', $element)) {
			$element = trim($element);
			$this->element = DOM::importNode(DOM::loadElement($element));

		// Initialize element by tag
		} else if (is_string($element)) {
			$this->element = DOM::createElement($element);
			if (is_array($properties)) $this->setAttrHelper($this->element, $properties);
		}
	}
	
	/**
	 * Find an element using a CSS selector
	 * 
	 * @param	string
	 * @return	object(Element)
	 */
	public function getElement( $selector ) {
		if ($element = DOMSelector::findElement( $this->element, $selector )) {
			return new Element($element);
		} else {
			return false;
		}
	}
		
	/**
	 * Remove an element
	 * 
	 * @return	void
	 */
	public function remove() {
		$this->element->parentNode->removeChild($this->element);
	}
	
	/**
	 * Add a class to an element
	 * 
	 * @param	string	
	 * @return	self
	 */
	public function addClass( $class ) {
		$this->addClassHelper($this->element, $class);
 		return $this;
	}
	
	/**
	 * Check if an element has a class
	 * 
	 * @param	string
	 * @return	bool
	 */
	public function hasClass( $class ) {
 		$classes = explode(' ', $this->element->getAttribute('class'));
 		return (in_array($class, $classes) ? true : false);
	}
	
	/**
	 * Get the value of an attribute
	 * 
	 * @param	string
	 * @return	string
	 */
	public function get( $attr ) {
		switch($attr) {
			case 'tag':
				return $this->element->nodeName;
			case 'html':
				return DOM::nodeContent($this->element);
			case 'text':
				return strip_tags(DOM::nodeContent($this->element));
			default:
				return $this->element->getAttribute($attr);
		}
	}
	
	/**
	 * Set the value of an attribute
	 * 
	 * @param	mixed
	 * @param	string
	 * @return	self
	 */
	public function set( $attributes, $value = null ) {
		if (!is_array($attributes)) $attributes = [$attributes=>$value];
		
		$this->setAttrHelper($this->element, $attributes);
		
		return $this;
	}
		
	/**
	 * Inject an element somwhere in the dom. The first param is the element relative to the injection and 
	 * the second param is where in relation to this element we inject the new element.
	 *
	 * @param	object(Element)
	 * @param	string
	 * @return	self
	 */
	public function inject( $element = null, $where = "bottom" ) {
		if ($element instanceof Element) $element = $element->element;
		
		// Verify data passed
		list($element, $where) = $this->injectParser($element, $where);
		
		// Store the first level childs of the DOM in an array
		if ($element instanceof DOMDocument) {
			DOM::$childs[] = $this->element;
		}
		
		// Inject element 
		switch($where) {
			case 'top':
				$element->insertBefore($this->element, $element->firstChild);
				break;
			case 'before':
				$element->parentNode->insertBefore($this->element, $element);
				break;
			case 'after':
				$element->parentNode->insertBefore($this->element, $element->nextSibling);
				break;
			case 'bottom':
				$element->appendChild($this->element);
				break;
		}
		
		return $this;
	}
	
	/**
	 * Wraps an element into this element
	 * 
	 * @param	object(DOMElement)
	 * @return	self
	 */
	public function wraps( $element ) {
		if ($element instanceof View) $element = $element->element;
		if ($element instanceof Element) $element = $element->element;
		
		$element->parentNode->insertBefore($this->element, $element);
		$this->element->appendChild($element);
		
		return $this;
	}
	
	/**
	 * Serialize an object(Element) for caching
	 * 
	 * @return	string
	 */	
	public function serialize() {
		$dom = new DOMDocument('1.0');
		$dom->formatOutput = true;
		$dom->encoding = "UTF-8";
		
		$node = $dom->importNode($this->element, true);
		$dom->appendChild($node);
				
		$data = $dom->saveHTML();
		return $data;
	}
	
	/**
	 * Unserialize an object(Element)
	 * 
	 * @param	string
	 * @return	void
	 */	
	public function unserialize( $data ) {
		$dom = new DOMDocument('1.0');
		$dom->formatOutput = true;
		$dom->encoding = "UTF-8";
		
		$dom->loadHTML($data);
		
		$element = $dom->childNodes->item(1)->childNodes->item(0)->childNodes->item(0);
		$this->initDOMElement($element);
	}
	
	/**
	 * Shortcuts for methods
	 * 
	 * @param	string
	 * @param	mixed
	 * @return	mixed
	 */
	public function __call($method, $args) {
		switch($method) {
			case 'empty':	return $this->emptyElement($this->element); break;
			case 'destroy':	return $this->remove(); break;
		}
		trigger_error("Call to undefined method Element::{$method}()", E_USER_ERROR);
	}
}