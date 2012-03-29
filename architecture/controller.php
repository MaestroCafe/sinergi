<?php

/**
 * Abstract controller. 
 *
 */
class Controller {	
	protected $controller;
	
	public function __construct($controller, $method=null, $args=[]) {
		$controller_name = preg_replace("/.*\/(.*)$/", "$1", strtolower($controller)); // Get controller name
				
		$this->controller = new $controller_name;
				
		$this->traits_constructors();
		
		if (method_exists($this->controller, 'base')) {
			$this->controller->base();
		}
		
		if (isset($method)) {
			call_user_func_array(array(&$this->controller, $method), $args); 
		}
   }
   
   /**
    * Sinergi uses the helper name as the helper constructor (like PHP with normal classes). So we need to get a list of helpers that the controller uses.
	* Note that there is no way to call the function in the specific helper, so there could be a mix up if a helper has the same name as another method in
	* another helper or in the controller.
    * 
    * @access protected
    * @return void
    */
   protected function traits_constructors() {
		foreach(class_uses($this->controller) as $helper) {
			$helper_name = preg_replace("/.*\\\(.*)$/", "$1", strtolower($helper)); // Get helper name
			
			if(method_exists($this->controller, $helper_name)) { // Check if helper constructor exists
				$this->controller->$helper_name();
			}
		}
   
   }
}