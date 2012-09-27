<?php

use sinergi\DOM,
	sinergi\DOMManipulator;

require_once Path::$core . "dom/manipulator.php";

class ElementGroup extends DOMManipulator implements Serializable {
	/**
	 * An array of DOMElement objects
	 * 
	 * @var	array
	 */
	public $elements = [];
	
	/**
	 * Create the element
	 * 
	 * @param	object(DOMNodeList)
	 * @return	self
	 */
	public function __construct( $elements ) {
		foreach($elements as $element) {
			$this->elements[] = DOM::importNode($element);
		}
	}
	
	/**
	 * Find an element using a CSS selector
	 * 
	 * @param	string
	 * @return	object(Element)
	 */
	public function getElement( $selector ) {
		if ($element = DOMSelector::findElement( $this->elements, $selector )) {
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
		foreach($this->elements as $element) {
			$element->parentNode->removeChild($element);			
		}
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
			foreach($this->elements as $node) {
				DOM::$childs[] = $node;
			}
		}
		
		// Inject element 
		switch($where) {
			case 'top':
				foreach(array_reverse($this->elements) as $node) {
					$element->insertBefore($node, $element->firstChild);
				}
				break;
			case 'before':
				foreach($this->elements as $node) {
					$element->parentNode->insertBefore($node, $element);
				}
				break;
			case 'after':
				foreach(array_reverse($this->elements) as $node) {
					$element->parentNode->insertBefore($node, $element->nextSibling);
				}
				break;
			case 'bottom':
				foreach($this->elements as $node) {
					$element->appendChild($node);
				}
				break;
		}
		
		return $this;
	}
	
	/**
	 * Serialize an object(ElementGroup) for caching
	 * 
	 * @return	string
	 */	
	public function serialize() {
		$dom = new DOMDocument('1.0');
		$dom->formatOutput = true;
		$dom->encoding = "UTF-8";
		
		foreach($this->elements as $element) {
			$node = $dom->importNode($element, true);
			$dom->appendChild($node);
		}
				
		$data = $dom->saveHTML();
		return $data;
	}
	
	/**
	 * Unserialize an object(ElementGroup)
	 * 
	 * @param	string
	 * @return	void
	 */	
	public function unserialize( $data ) {
		$dom = new DOMDocument('1.0');
		$dom->formatOutput = true;
		$dom->encoding = "UTF-8";
		
		$dom->loadHTML($data);
		
		foreach($dom->childNodes->item(1)->childNodes->item(0)->childNodes as $node) {
			$this->elements[] = DOM::importNode($node);
		}
	}
}