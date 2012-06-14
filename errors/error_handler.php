<?php

if (DEV) {
	$sinergiFrameworkErrors = array('inject', 'inject where', 'model unsanitize', 'unknown method');
	
	function myErrorHandler($errno, $errstr, $errfile, $errline) {		
		if (!(error_reporting() & $errno)) return;
		global $sinergiErrorHandling, $sinergiFrameworkErrors;
		
		$backtrace = debug_backtrace();
		
		if (in_array($errstr, $sinergiFrameworkErrors)) {			
			if ($errstr=='unknown method') {
				
				$next = false;
				foreach ($backtrace as $step) {
					if ($next) {
						$errmethod = $step['function'];
						$errclass = $step['class'];
						$errfile = $step['file'];
						$errline = $step['line'];						
						break;
					}
					if ($step['function']=='__call') $next = true;
				}
			} else {
				$backtrace = array_reverse($backtrace);
				
				foreach ($backtrace as $step) {
					if ($step['function']==$errstr) {
						$errfile = $step['file'];
						$errline = $step['line'];						
						break;
					}
				}
			}

			switch($errstr) {
				case 'inject': $errstr = 'Cannot inject because argument 0 is not a valid element'; break;
				case 'inject where': $errstr = 'Cannot inject because argument 1 is not a valid term, please use bottom, top, after or before'; break;
				case 'model unsanitize': $errstr = 'Cannot unsanitize string because key does not exists'; break;
				case 'unknown method': $errstr = "Call to undefined method {$errclass}::{$errmethod}()"; break;
			}
		} 		
		
		switch ($errno) {		
			case E_USER_WARNING:
				$sinergiErrorHandling .= "<p><b>Warning:</b>&nbsp;{$errstr}<br />\n<b>File:</b>&nbsp;".str_replace(DOCUMENT_ROOT, "/", $errfile)."<br />\n<b>Line:</b>&nbsp;{$errline}</p>\n";
				break;
			
			case E_USER_NOTICE:
				$sinergiErrorHandling .= "<p><b>Notice:</b>&nbsp;{$errstr}<br />\n<b>File:</b>&nbsp;".str_replace(DOCUMENT_ROOT, "/", $errfile)."<br />\n<b>Line:</b>&nbsp;{$errline}</p>\n";
				break;
			
			default:
				$sinergiErrorHandling .= "<p><b>Notice:</b>&nbsp;{$errstr}<br />\n<b>File:</b>&nbsp;".str_replace(DOCUMENT_ROOT, "/", $errfile)."<br />\n<b>Line:</b>&nbsp;{$errline}</p>\n";
				break;
		}
	
		return true;
	}
	
	set_error_handler('myErrorHandler');
}