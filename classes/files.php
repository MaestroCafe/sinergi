<?php

class File {
	protected $ftp_connection; // FTP Connection for uploading a file
	protected $file_pointer; // Pointer in file
	protected $path; // Path of file
	public $content = ""; // File content
	
	/**
	 * 
	 * 
	 * @param $provider
	 * @var bool
	 * @access private
	 * @return const
	 */
	public function __construct($path=null) {
		if (isset($path)) {
			$path = $this->clean_path($path);
							
			if(!file_exists($path)) { // Create the file and path
				$dir = dirname($path);						
				$this->create_dir($dir); // Create directory if directory does not exists
				$this->file_pointer = fopen($path, 'w+');
			}					
		}
		$this->path = $path;
	}
	
	/**
	 * 
	 * 
	 * @param $provider
	 * @var bool
	 * @access private
	 * @return const
	 */
	public function __destruct() {
		if (isset($this->file_pointer)) {
			fclose($this->file_pointer);
		}
	}
	
	/**
	 * 
	 * 
	 * @param $provider
	 * @var bool
	 * @access private
	 * @return const
	 */
	public function __toString() {
		return $this->content;
	}
	
	/**
	 * Append
	 * 
	 * @param $provider
	 * @var bool
	 * @access private
	 * @return const
	 */
	public function append($str) {
		$this->open_file();
		/* Move the file pointer at the end of the file */
		fseek($this->file_pointer, 0, SEEK_END);
		
		fputs($this->file_pointer, $str);
		
		return $this;
	}
	
	/**
	 * Copy
	 * 
	 * @param $provider
	 * @var bool
	 * @access private
	 * @return const
	 */
	public function copy($dest) {
		$dest = $this->clean_path($dest);
		
		if(copy($this->path, $dest)) {
			$this->path = $dest;
			return $this;
		}
		
		return false;
	}
	
	/**
	 * Delete
	 * 
	 * @param $provider
	 * @var bool
	 * @access private
	 * @return const
	 */
	public function delete() {
		unlink($this->path);
	}
	
	/**
	  * Duplicate
	  * 
	  * @param $provider
	  * @var bool
	  * @access private
	  * @return const
	  */ 
	public function duplicate($new_path=null) {
		if (!isset($new_path)) {
			$sufix = 1;
			$ext = $this->get_extention();
			$new_path = '';
			
			do {
				$sufix++;
				$new_path = preg_replace('/\\' . $ext . '/', '_' . $sufix . $ext, $this->path);
			} while(file_exists($new_path));
		}
		
		$this->copy($new_path);
		
		return $this;
	}
	
	/**
	 * get_creation_date
	 * 
	 * @param $provider
	 * @var bool
	 * @access private
	 * @return const
	 */
	public function get_creation_date() {
		return stat($this->path)['ctime'];
	}
	
	/**
	 * Get_extention
	 * 
	 * @param $provider
	 * @var bool
	 * @access private
	 * @return const
	 */
	public function get_extention() {
		preg_match('/\.([a-zZ-Z0-0]*)$/', $this->path, $matches);
		return $matches[0];
	}
	
	/**
	 * Get_full_path
	 * 
	 * @param $provider
	 * @var bool
	 * @access private
	 * @return const
	 */
	public function get_full_path() {
		return $this->path;
	}
	
	/**
	 * Get_modification_date
	 * 
	 * @param $provider
	 * @var bool
	 * @access private
	 * @return const
	 */
	public function get_modification_date() {
		return stat($this->path)['mtime'];
	}
	
	/**
	 * Get_name
	 *
	 */ 
	public function get_name() {
		preg_match('/[a-zZ-Z0-0\_\-\.\,\%]*$/', $this->path, $matches);
		
		return $matches[0];
	}
	
	/**
	 * Get_size
	 *
	 */ 
	public function get_size() {
		return filesize($this->path);
	}
	
	/**
	 * Move
	 *
	 */ 
	public function move($dest) {
		
		$this->copy($dest);
		$this->delete();
		$this->path = $dest;
		
		return $this;
	}
	
	/**
	  * Move uploaded file
	  * 
	  * @param $provider
	  * @var bool
	  * @access private
	  * @return const
	  */ 
	public function move_uploaded($origin, $destination) {
		$this->create_dir(dirname($destination)); // Create directories if they don't exists
		$destination = $this->clean_path($destination); // Clean the destination path
		
		$this->path = $origin; // Remove the DOCUMENT_ROOT from the uploaded file path, because it is in the system's tmp folder
		
		if(move_uploaded_file($this->path, $destination)) {
			$this->path = $destination;
			return $this;
		}
		
		return false;
	}
	
	/**
	 * Prepend
	 *
	 */ 
	public function prepend($str) {
		$this->open_file();
		/* Move the file pointer at the begining of the file */
		fseek($this->file_pointer, 0);
		
		fputs($this->file_pointer, $str);
		
		return $this;
	}
	
	/**
	 * Read
	 *
	 */ 
	public function read() {
		$this->open_file();
		$filesize = filesize($this->path);
		if($filesize>0) {
			$this->content = fread($this->file_pointer, $filesize);
		}
		
		return $this;
	}
	
	/**
	 * Rename a file
	 */ 
	public function rename($new_name) {
		$new_name = $this->clean_path($new_name);
		rename($this->path, $new_name);
	}
	
	/**
	 * Write
	 *
	 */ 
	public function write($string) {
		if (isset($this->file_pointer)) {
			fclose($this->file_pointer);
		}
		$this->file_pointer = fopen($this->path, 'w');
		
		fwrite($this->file_pointer , $string);
		
		return $this;
	}
	
	/**
	 * Upload file to an FTP Server
	 * 
	 * @param $provider
	 * @var bool
	 * @access private
	 * @return const
	 */
	public function upload($upload_file_name, $server_settings) {
		$path = dirname($upload_file_name);
		if ($this->connect_ftp(array_merge($server_settings, ['path'=>$path]))) {
			$this->open_file();
			
			if (!ftp_fput($this->ftp_connection, basename($upload_file_name), $this->file_pointer, FTP_BINARY)) {
				$this->ftp_connection = null;
				trigger_error('Failed uploading file to depo.');
				return false;
			}
		}
	}

	/**
	 * Connect to an FTP Server
	 * 
	 * @param $provider
	 * @var bool
	 * @access private
	 * @return const
	 */
	protected function connect_ftp($settings) {
		// Clean settings, cause we tolerate messy settings : ['username'=>'user', 'server'=>'server', 'password']
		$server = $username = $password = $path = null;
		foreach($settings as $key=>$value) {
			if($key=='server') {
				$server = $value;
				continue;
			} else if ($key=='username') {
				$username = $value;
				continue;
			} else if ($key=='password') {
				$password = $value;
				continue;
			} else if ($key=='path') {
				$path = $value;
				continue;
			} else if (!isset($server) && !isset($username) && !isset($password) && !isset($path)) {
				$server = $value;
			} else if (!isset($username) && !isset($password) && !isset($path)) {
				$username = $value;
			} else if (!isset($password) && !isset($path)) {
				$password = $value;
			} else if (!isset($path)) {
				$path = $value;
			}
		}
		
		if (!isset($this->ftp_connection)) {
			if (!$this->ftp_connection = ftp_connect(($server=="localhost" ? "127.0.0.1" : $server))) { //  Try to connect to server
				$this->ftp_connection = null;
				trigger_error('Server not found.');
				return false;
			}
			
			if (!@ftp_login($this->ftp_connection, $username, $password)) { // Log into server
				$this->ftp_connection = null;
				trigger_error('Login incorrect.');
				return false;
			}
			
			if (isset($path) && !@ftp_chdir($this->ftp_connection, $path)) { // If there is a path and path does not exists
				$dirs = explode("/", str_replace('//', '/', $path));
				foreach ($dirs as $dir) {
				    if (!empty($dir) && !@ftp_chdir($this->ftp_connection, $dir)) {
				    	if (!ftp_mkdir($this->ftp_connection, $dir) || !ftp_chdir($this->ftp_connection, $dir)) {
							$this->ftp_connection = null;
				    		trigger_error('Could not change directory');
				    		return false;	
				    	}
				    }
				}
			}
			return true;
		} else {
			return true;
		}
	}
	
	/**
	 * Read the file
	 * 
	 * @param $provider
	 * @var bool
	 * @access private
	 * @return const
	 */
	protected function open_file() {
		if (!isset($this->file_pointer)) {
			$this->file_pointer = fopen($this->path, 'r+');
		}
	}
	
	/**
	 * Clean the file path and prepend the DOCUMENT_ROOT if not already prepended
	 * 
	 * @param $provider
	 * @var bool
	 * @access private
	 * @return const
	 */
	protected function clean_path($path) {
		if (substr($path, 0, strlen(DOCUMENT_ROOT))!=DOCUMENT_ROOT) {
			$path = DOCUMENT_ROOT.$path;
		}
		return str_replace('//', '/', $path);
	}
	
	/**
	 * Create all directories
	 * 
	 * @param $provider
	 * @var bool
	 * @access private
	 * @return const
	 */
	protected function create_dir($dir) {
		if (substr($dir, 0, strlen(DOCUMENT_ROOT))!=DOCUMENT_ROOT) $dir = str_replace('//', '/', DOCUMENT_ROOT.$dir); // Create real path
		if(!is_dir($dir)) { // Create directory if directory does not exists
			mkdir($dir, 0777, true);
		}
	}
}
?>