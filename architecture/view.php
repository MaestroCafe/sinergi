<?php

require CORE . 'classes/DOM/DOM.php';

class View {

	/**
	 *	complete tree of elements
	 */
	private $elements = [];
	
	
	static $loading = false, $file = null;
	
	public function __construct($file, $args = null) {
		global $DOM, $outputBufferDebug, $outputBuffer, $html, $doctype;
		
		/**
		 * Get View file name.
		 * 
		 */
		$file = VIEWS.strtolower(str_replace('\\', '/', $file)).'.php';
		
		/**
		 * Save and empty the output buffer. 
		 *
		 */
		$outputBufferDebug .= ob_get_contents(); ob_clean();
		
		/**
		 * Define variables passed to the view. 
		 *
		 */
		
		if ($args != null) foreach ($args as $key=>$value) $$key = $value;
		
		
		/**
		 * Load the view. 
		 *
		 */
		require $file;
		
		/**
		 * Save file content in view var. 
		 *
		 */
		$outputBuffer .= $view = trim(ob_get_contents()); ob_clean();
					
		/**
		 * Divise le document en DOCTYPE et le reste. 
		 *
		 */
		$views = array();
		if (!isset($html)) {
			$views = explode(PHP_EOL, $view, 2);
			if (stristr($views[0], 'doctype')) $doctype = $views[0];
			else $views[1] = $view;
			
			$view = $views[1];
		}
		
		/**
		 * Continue s'il y a du contenu. 
		 *
		 */
		if (trim($view)!=="") {
			
			/* Create a document tree */
			$this->elements = $this->create_tree($view, $doctype);
		}
	}
	
	/**
	 * Create an element tree from the view
	 *
	 */ 
	private function create_tree($view, $doctype) {
		global $html;
		
		$view = mb_convert_encoding(trim($view), 'HTML-ENTITIES', 'UTF-8');
		
		/**
		 * Transforme le contenu en object DOM. 
		 *
		 */
		$tree = new DOMDocument();
		@$tree->loadHTML($view);
				
		/**
		 * Lit l'arbre entier. 
		 *
		 */
		$elements = array();
		if (!isset($html) and isset($doctype)) {
			foreach ($tree->childNodes as $child) {
				/**
				 * Évite les noeuds html vides (d'où viennent t'ils ?). 
				 *
				 */
				if ($child->nodeName!=='html' or $child->hasChildNodes()) { 
					/**
					 * Insère dans le DOM. 
					 *
					 */
					if ($child->nodeName==='html') $html = $elements[] = $this->create_element($child); 
					else $elements[] = $this->create_element($child);
				}
			}
		} else {
			foreach ($tree->childNodes as $child) {
				if ($child->nodeName=='html' and $child->hasChildNodes()) {
			    	foreach ($child->childNodes as $child2) {
						if ($child2->nodeName=='body' and $child2->hasChildNodes()) {
			    			foreach ($child2->childNodes as $child3) {
								$elements[] = $this->create_element($child3);
			    			}
						}
			    	}
				}
			}
		}
		$elements = array_filter($elements);		
		return $elements;
	}
	
	
	/**
	 * Create childs in DOM. 
	 *
	 */
	private function create_element($item, $parent=false) {
		/**
		 * Append text to current element. 
		 *
		 */
		if ($item->nodeName==='#cdata-section' and trim($item->textContent)!=='') $element = node(trim($item->textContent), 'cdatasection');
		else if ($item->nodeName==='#comment' and trim($item->textContent)!=='') $element = node(trim($item->textContent), 'comment');
		else if ($item->nodeName==='#text' and trim($item->textContent)!=='') $element = node(trim($item->textContent), 'text');
		else if (substr($item->nodeName, 0, 1)!=='#') $element = new Element($item->nodeName);
			    
		/**
		 * Inject element if element has parent. 
		 *
		 */
		if (isset($element) and $parent!==false) {
			/**
			 * For quick fix on spaces in text nodes being removed when injecting object (TO BE CHANGED). 
			 *
			 */
			if (substr($this->prevElement, 0, 1)=='#' and substr($item->nodeName, 0, 1)!=='#') $parent->appendText(' ');
			
			$element->inject($parent);
			/**
			 * For quick fix on spaces in text nodes being removed when injecting object (TO BE CHANGED). 
			 *
			 */
			if (substr($this->prevElement, 0, 1)=='#' and substr($item->nodeName, 0, 1)!=='#') $parent->appendText(' ');
		}
		/**
		 * For quick fix on spaces in text nodes being removed when injecting object (TO BE CHANGED). 
		 *
		 */
		$this->prevElement = $item->nodeName;
		
		/**
		 * Check if $item has attributes. 
		 *
		 */
		if ($item->hasAttributes()) {
			/**
			 * Loop through attributes and apply them to the element. 
			 *
			 */
			foreach ($item->attributes as $attr) $element->set($attr->name, $attr->value);
		}
		
	    /**
		 * Check if element has childs. 
		 *
		 */
	    if ($item->hasChildNodes()) {
	    	foreach ($item->childNodes as $child) {
				/**
				 * Insert element in DOM. 
				 *
				 */
	    		$this->create_element($child, $element);
	    	}
	    }
		
		if (isset($element)) return $element;
	}
	
	/**
	 * Inject group of elements. 
	 * 
	 * @return group
	 */
	public function inject_group($element=null, $where=null) {
		if (isset($where) and $where!="bottom" and $where!="top" and $where!="before" and $where!="after") {
			trigger_error("inject where");
			return $this;
		}
		
		/**
		 * If no element is given, inject element directly into the DOM. 
		 *
		 */
		if (!isset($element)) { global $DOM; $element = $DOM; }
		
		else if (!is_object($element) or !isset($element->element)) {
			trigger_error("inject");
			return $this;
		}
		
		/**
		 * If injecting to top or after, invert order of objects to end up having the original order. 
		 *
		 */
		if (isset($where) and ($where=="top" or $where=="after")) {
			$neworder = elements_reverse($this->elements); // Check for php aray_reverse, we had a bug so we implemented our own function
			unset($this->elements);
			$this->elements = array();
			$this->elements = $neworder;
		}
		
		foreach ($this->elements as $item) $item->inject($element, $where);

		return $this;
	}
	
	/**
	 * Any method called to the group pass through this method. 
	 * This method has not been tested with other callss than inject, please test before using.
	 * This method should trigger an error if a call is made to an unknown method. 
	 * 
	 * @return void
	 */
	public function __call($method, $args) {		
		if(count($this->elements) == 1) {
			return current($this->elements)->__call($method, $args);
		} else {
			switch($method) {
				case 'inject':
					call_user_func_array([$this, 'inject_group'], $args);
					break;
				default: 
					foreach ($this->elements as $element) { 
						$element->__call($method, $args);
					}
					break;
			}
		}
		
		return $this;
	}
}