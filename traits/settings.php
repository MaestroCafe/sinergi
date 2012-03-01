<?php

/* namespace sinergi; */

class Sinergi {
	static public function unlimited_memory() {
		set_time_limit(0);
		ignore_user_abort(true);
		ini_set('memory_limit', '-1');	
		ini_set('max_input_time', '-1');
	}
	
	static public function hide_console() {
		global $sinergi_hide_console;
		$sinergi_hide_console = true;
	}
}