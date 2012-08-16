<?php

/**
 * Sinergi is an open source application development framework for PHP
 *
 * Requires PHP version 5.4
 *
 * LICENSE: This source file is subject to the GNU General Public License 
 * version 2.0 (GPL-2.0) that is bundled with this package in the file 
 * LICENSE-GPL.txt and is available through the world-wide-web at the 
 * following URI: http://www.opensource.org/licenses/GPL-2.0. If you did 
 * not receive a copy of the GNU General Public License version 2.0 and are 
 * unable to obtain it through the web, please send a note to admin@sinergi.co 
 * so we can mail you a copy immediately.
 *
 * @package		sinergi
 * @author		Sinergi Team
 * @copyright	2010-2012 Sinergi Team
 * @license		http://www.opensource.org/licenses/GPL-2.0 GNU General Public License version 2.0 (GPL-2.0)
 * @link		https://github.com/sinergi/sinergi
 * @since		Version 1.0
 */
 
/**
 * File manager
 *
 * @category	core
 * @package		sinergi
 * @author		Sinergi Team
 * @link		https://github.com/sinergi/sinergi
 */

class File {
	/**
	 * FTP Connection for uploading a file
	 * 
	 * @var	FTP stream
	 */
	private $ftpConnection;
	
	/**
	 * Pointer in file
	 * 
	 * @var	file pointer resource
	 */
	private $filePointer;
	
	/**
	 * Path of file
	 * 
	 * @var	string
	 */
	private $path;
	
	/**
	 * File content
	 * 
	 * @var	string
	 */
	public $content = "";
	
	/**
	 * 
	 * 
	 * @param $provider
	 * @var bool
	 * @access private
	 * @return const
	 */
	public function __construct($path=null, $create=true) {
		if (isset($path)) {
			$path = $this->cleanPath($path);
							
			if(!file_exists($path) && $create) { // Create the file and path
				$dir = dirname($path);						
				$this->createDir($dir); // Create directory if directory does not exists
				$this->filePointer = fopen($path, 'w+');
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
		if (isset($this->filePointer)) {
			fclose($this->filePointer);
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
		$this->openFile();
		/* Move the file pointer at the end of the file */
		fseek($this->filePointer, 0, SEEK_END);
		
		fputs($this->filePointer, $str);
		
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
		$dest = $this->cleanPath($dest);
				
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
	public function duplicate($newPath=null) {
		if (!isset($newPath)) {
			$sufix = 1;
			$ext = $this->getExtention();
			$newPath = '';
			
			do {
				$sufix++;
				$newPath = preg_replace('/\\' . $ext . '/', '_' . $sufix . $ext, $this->path);
			} while(file_exists($newPath));
		}
		$newPath = $this->cleanPath($newPath);
		
		$this->createDir(dirname($newPath));
		
		$this->copy($newPath);
		
		return $this;
	}
	
	/**
	 * getCreationDate
	 * 
	 * @param $provider
	 * @var bool
	 * @access private
	 * @return const
	 */
	public function getCreationDate() {
		return stat($this->path)['ctime'];
	}
	
	/**
	 * getExtention
	 * 
	 * @param $provider
	 * @var bool
	 * @access private
	 * @return const
	 */
	public function getExtention() {
		preg_match('/\.([a-zZ-Z0-0]*)$/', $this->path, $matches);
		return $matches[0];
	}
	
	/**
	 * getFullPath
	 * 
	 * @param $provider
	 * @var bool
	 * @access private
	 * @return const
	 */
	public function getFullPath() {
		return $this->path;
	}
	
	/**
	 * getModificationDate
	 * 
	 * @param $provider
	 * @var bool
	 * @access private
	 * @return const
	 */
	public function getModificationDate() {
		return stat($this->path)['mtime'];
	}
	
	/**
	 * getName
	 *
	 */ 
	public function getName() {
		preg_match('/[a-zZ-Z0-0\_\-\.\,\%]*$/', $this->path, $matches);
		
		return $matches[0];
	}
	
	/**
	 * getSize
	 *
	 */ 
	public function getSize() {
		return file_exists($this->path) ? filesize($this->path) : 0;
	}
	
	/**
	 * Move
	 *
	 */ 
	public function move( $dest ) {
		$dest = $this->cleanPath($dest);
		$origin = $this->path;
		
		$this->copy($dest);
		$this->path = $origin;
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
	public function moveUploaded($origin, $destination) {
		$this->createDir(dirname($destination)); // Create directories if they don't exists
		$destination = $this->cleanPath($destination); // Clean the destination path
		
		$this->path = $origin; // Remove the Path::$documentRoot from the uploaded file path, because it is in the system's tmp folder
		
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
		$this->openFile();
		/* Move the file pointer at the begining of the file */
		fseek($this->filePointer, 0);
		
		fputs($this->filePointer, $str);
		
		return $this;
	}
	
	/**
	 * Read
	 *
	 */ 
	public function read() {
		$this->openFile();
		$filesize = filesize($this->path);
		if($filesize>0) {
			$this->content = fread($this->filePointer, $filesize);
		}
		
		return $this;
	}
	
	/**
	 * Rename a file
	 */ 
	public function rename($new_name) {
		$new_name = $this->cleanPath($new_name);
		rename($this->path, $new_name);
	}
	
	/**
	 * Write
	 *
	 */ 
	public function write($string) {
		if (isset($this->filePointer)) {
			fclose($this->filePointer);
		}
		$this->filePointer = fopen($this->path, 'w');
		
		fwrite($this->filePointer , $string);
		
		return $this;
	}
			
	/**
	 * Read the file
	 * 
	 * @param $provider
	 * @var bool
	 * @access private
	 * @return const
	 */
	private function openFile() {
		if (!isset($this->filePointer)) {
			$this->filePointer = fopen($this->path, 'r+');
		}
	}
	
	/**
	 * Clean the file path and prepend the Path::$documentRoot if not already prepended
	 * 
	 * @param $provider
	 * @var bool
	 * @access private
	 * @return const
	 */
	private function cleanPath($path) {
		if (substr($path, 0, strlen(Path::$documentRoot))!=Path::$documentRoot) {
			$path = Path::$documentRoot.$path;
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
	private function createDir($dir) {
		if (substr($dir, 0, strlen(Path::$documentRoot))!=Path::$documentRoot) $dir = str_replace('//', '/', Path::$documentRoot.$dir); // Create real path
		if(!is_dir($dir)) { // Create directory if directory does not exists
			mkdir($dir, 0777, true);
		}
	}
}