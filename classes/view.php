<?php

use sinergi\DOM;

class View {
	/**
	 * The element object
	 * 
	 * @var	array
	 */
	public $element;
	
	/**
	 * Load a view
	 * 
	 * @param	string	the view path to load
	 * @param	array	the arguments passed from the controller to the view
	 * @param	array	the module the view is in
	 * @return	void
	 */	
	public function __construct( $view, $args = null ) {
		// Check if view is called from a module
		$trace = debug_backtrace();
		if (isset($trace[1]) && isset($trace[1]['class']) && preg_match('/^modules\\\/i', $trace[1]['class'])) {
			$module = preg_replace('/modules\\\([^\\\]*)(.*)/', '$1', $trace[1]['class']);
		}
		
		// Get View file name
		if (!isset($module)) {
			$file = Path::$views . trim($view, ' /') . '.php';
		} else {
			$file = Path::$modules . $module . '/views/' . trim($view, ' /') . '.php';
		}
				
		// Define variables passed to the view.
		if (isset($args) && !is_array($args)) {
			trigger_error("Parameter \$args passed to view '{$view}' is not an array", E_USER_ERROR);

		} else if(isset($args)) {
			foreach ($args as $key=>$value) $$key = $value;
		}
		
		// Load the view
		if (file_exists($file)) {
			ob_start();
			require $file;
			$content = trim(ob_get_contents());
			ob_clean();
						
			// Split the doctype from the rest of the document
			$doctype = null;
			if (preg_match('/<!DOCTYPE html>/', $content, $matches)) {
				$doctype = $matches[0];
			}
				
			// Check if view has elements
			if (empty($content) || !$element = DOM::loadElement($content, $doctype)) {
				trigger_error("Failed to load view '{$view}': File is not valid HTML", E_USER_ERROR);
			} else {
				if ($element instanceof DOMNodeList) {
					$this->element = new ElementGroup($element);
					return $this->element;
				} else {
					$this->element = new Element($element);
					return $this->element;
				}
			}
		} else {
			trigger_error("Failed to load view '{$view}': No such file", E_USER_ERROR);
			
		}
	}
			
	/**
	 * This should redirect valid methods to the Object in $this->element
	 * 
	 * @param	string
	 * @param	array
	 * @return	self
	 */
	public function __call( $method, $args ) {
		if (is_object($this->element) && (get_class($this->element) === 'Element' || get_class($this->element) === 'ElementGroup')) {
			switch($method) {
				case 'inject': case 'getElement': case 'destroy':
					return call_user_func_array([$this->element, $method], $args);
					break;
				default: 
					trigger_error("Call to undefined method View::{$method}()", E_USER_ERROR);
					break;
			}
		} else {
			trigger_error("Call to method View::{$method}() on invalid View", E_USER_ERROR);
		}
		
		return $this;
	}
}
