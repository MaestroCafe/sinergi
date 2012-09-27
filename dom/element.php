<?php

use sinergi\DOM,
	sinergi\DOMManipulator;

require_once Path::$core . "dom/manipulator.php";

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
		if ($element instanceof DOMElement) {
			$this->initDOMElement($element);
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
	 * Find an element using a CSS selector
	 * 
	 * @param	string
	 * @return	object(Element)
	 */
	public function destroy() {
		$this->element->parentNode->removeChild($this->element);
	}
	
	/**
	 * 
	 * 
	 * @param		
	 * @return	
	 */
	public function addClass() {
		
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
	 * Initalize a DOMElement element
	 * 
	 * @param	object(DOMElement)
	 * @return	void
	 */
	private function initDOMElement( $element ) {
		$this->element = DOM::importNode($element);
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
}