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
 * The database connection manager.
 *
 * @category	core
 * @package		sinergi
 * @author		Sinergi Team
 * @link		https://github.com/sinergi/sinergi
 */

require Path::$core . "db/manager.php";
require Path::$core . "db/result.php";

class DB extends sinergi\db\Manager {
	/**
	 * Connections are static for re-use
	 *
	 * @var	array
	 */
	static public $connections = [];
	
	/**
	 * The connection to the database
	 *
	 * @var	string
	 */
	private $connection;
	
	/**
	 * The database name
	 *
	 * @var	string
	 */
	private $databaseName = null;
	
	/**
	 * Database driver
	 *
	 * @var	string
	 */
	protected $driver;
	
	/**
	 * Database table
	 *
	 * @var	string
	 */
	protected $table;
	
	/**
	 * Database manager constructor.
	 * 
	 * @param	string	the name of the database
	 * @param	string	the name of the table
	 * @return	void
	 */
	public function __construct( $databaseName, $tableName = null ) {
		if (!isset($tableName)) {
			$tableName = $databaseName;
			$databaseName = null;
		}
		
		$this->setDatabase($databaseName);
		$this->tableName = $tableName;
	}
	
	/**
	 * Set the database.
	 *
	 * @access private
	 * @return void
	 */
	private function setDatabase( $databaseName = null ) {
		global $settings;
		
		if (isset($databaseName)) {
			$this->databaseName = $databaseName;
		} else {
			$this->databaseName = key($settings['databases']);
		}
		
		if (isset($settings['databases'][$this->databaseName])) {
			$this->driver = $settings['databases'][$this->databaseName]['type'];
		} else {
			trigger_error("The database {$databaseName} does not exists", E_USER_WARNING);
		}
	}
	
	/**
	 * PDO Connect.
	 *
	 * @access private
	 * @return void
	 */
	private function connectDatabase() {
		global $settings;
		
		if (isset(self::$connections[$this->databaseName])) { // Check if application is already connected
			$this->connection = self::$connections[$this->databaseName];
		
		} else { // Otherwise create connection
			
			$databaseAddress = explode(':', $settings['databases'][$this->databaseName]['host']); // Get database address and port
			
			// Create DNS
			switch($this->driver) {
				case 'mysql':
					$dsn = 
						"mysql:dbname={$settings['databases'][$this->databaseName]['dbname']};".
						"host={$databaseAddress[0]};".
						(!empty($databaseAddress[1]) ? "port={$databaseAddress[1]};":"");
					break;
				case 'access':
					$dsn = 
						"dblib:dbname={$settings['databases'][$this->databaseName]['dbname']};".
						"host={$databaseAddress[0]};".
						"charset=UTF-8;".
						(!empty($databaseAddress[1]) ? "port={$databaseAddress[1]};":"");
					break;
				case 'odbc':
					$dsn = 
						"odbc:{$settings['databases'][$this->databaseName]['source']}";
					break;
			}
			
			// Create driver options
			switch($this->driver) {
			    case 'mysql': // Use UTF-8 with MySQL by default
			    	$driverOptions = [PDO::MYSQL_ATTR_INIT_COMMAND=>'SET NAMES \'UTF8\''];
			    	break;
			    	
			    default:
			    	$driverOptions = [];
			    	break;
			}
			
			// Try to connect
			try {				
				$this->connection = 
			    	new PDO(
			    		$dsn, 
			    		$settings['databases'][$this->databaseName]['user'], 
			    		$settings['databases'][$this->databaseName]['password'],
			    		$driverOptions
			    	);
			    
			}
			catch(PDOException $e) {
				trigger_error($e->getMessage());
			}
			
			self::$connections[$this->databaseName] = $this->connection; // Store the database connection for other instances
		}
	}
	
	/**
	 * PDO methods shortcuts.
	 *
	 * @return void
	 */
	public function __call($method, $args) {
		switch($method) {
			case 'commit': 
			case 'errorCode': 
			case 'errorInfo': 
			case 'exec': 
			case 'getAttribute': 
			case 'getAvailableDrivers': 
			case 'inTransaction': 
			case 'lastInsertId': 
			case 'prepare': 
			case 'query': 
			case 'quote': 
			case 'rollBack': 
			case 'setAttribute':
				if (!isset($this->connection)) {
					$this->connectDatabase();
				}
				
				// Call the PDO method
				if (count($args)>1) {
					return call_user_func(array($this->connection, $method), $args);
				} else if (count($args)==1) {
					return call_user_func(array($this->connection, $method), $args[0]);
				} else {
					return call_user_func(array($this->connection, $method));
				}
				
				break;
			
			default: 
				trigger_error("Call to undefined database method {$method}", E_USER_NOTICE);
				break;
		}
	}
		
	/**
	 * Close connection with database when object is destroyed.
	 * 
	 * @return void
	 */
	public function __destruct() {
		$this->connection = null;
	}
}