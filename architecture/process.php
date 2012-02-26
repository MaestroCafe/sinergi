<?php

class Process {
	/**
	 * Run function will run the process as a background process from a PHP Script.
	 * 
	 * @param $provider
	 * @var bool
	 * @access private
	 * @return const
	 */
	public function run() {
		$process_name = str_replace('\\', '\\\\', get_class($this)); // Get name of the current process (child class)				
		shell_exec('php -q '.DOCUMENT_ROOT."core/loader/processes.php {$process_name}".(DEV ? ' DEV':'').' > /dev/null &'); // Execute process
	}
}