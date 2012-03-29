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
}