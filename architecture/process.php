<?php

class Process {
	
	/**
	 * Interval to which the process should be executed
	 */
	public $interval = null;
	
	/**
	 * Run the current process in background if found.
	 */
	public function run() {
		
		$process_name = get_class($this); // Get name of the current process (child class)

		$process_name = str_replace('\\', '\\\\', $process_name);
				
		shell_exec('php -q '.DOCUMENT_ROOT."core/processes/execute.php {$process_name}".(DEV ? ' DEV':'').' > /dev/null &');
	}

}