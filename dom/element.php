<?php

class Element extends DOMManipulation {
	
	/**
	 * Create the element.
	 * 
	 */
	public function __construct($element, $properties=null) {
		global $DOM;
		
		/**
		 * Create Element. 
		 *
		 */
		 
		$this->element = $DOM->createElement($element);
		/**
		 * Create attributes. 
		 *
		 */
		if (is_array($properties)) foreach($properties as $attribute=>$value) 
			/**
			 * Only value. 
			 *
			 */
			if (is_numeric($attribute)) $this->element->setAttribute($value, 'attribute-fixed-tmp');
			/**
			 * Class Attributes. 
			 *
			 */
			else if ($attribute=='class') $this->element->setAttribute('class-fixed-tmp', $value);
			/**
			 * Style. 
			 *
			 */
			else if ($attribute=='style' and is_array($value)) new setStyle($this, array($value));
			/**
			 * Normal Attributes. 
			 *
			 */
			else if ($attribute!='html'&&$attribute!='text') $this->element->setAttribute($attribute, $value);
			/**
			 * Insert Text content. 
			 *
			 */
			else $this->element->appendChild($DOM->createCDATASection($value));
		/**
		 * Img element. 
		 *
		 */
		if ($element=='img') {
			if (!isset($properties['width'])) $this->element->setAttribute('width', '');
			if (!isset($properties['height'])) $this->element->setAttribute('height', '');
			if (!isset($properties['alt'])) $this->element->setAttribute('alt', '');
		}
	}
	
	/**
	 * Return the element as a string.
	 * 
	 * return string
	 */
	public function __toString() {
		global $DOM;

		$clone = clone $this; // Clone the node
		$element = $DOM->appendChild($clone->element); // Append the clone
		 
		$doc = new DOMDocument('1.0'); // Create a new empty document
		$new_element = $doc->importNode($element, true); // Import the clone
		$new_element = $doc->appendChild($new_element); 
		
		$clone->destroy(); // Destroy the clone
		unset($clone);
		
		return $doc->saveHTML();
	}
}