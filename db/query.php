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

class Query extends sinergi\db\Manager {
	/**
	 * Connections are static for re-use
	 *
	 * @var	array
	 */
	public static $connections = [];
	
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
	 * @param	string
	 * @param	string
	 * @return	void
	 */
	public function __construct( $databaseName, $tableName = null ) {
		$this->setDatabase($databaseName);
		$this->tableName = $tableName;
	}
	
	/**
	 * Set the database.
	 *
	 * @param	string
	 * @return	bool
	 */
	private function setDatabase( $databaseName ) {
		global $settings;
				
		if (isset($settings['databases'][$databaseName])) {
			$this->databaseName = $databaseName;
			$this->driver = $settings['databases'][$databaseName]['type'];
			
			return true;
		} else {
			trigger_error("The database {$databaseName} does not exists", E_USER_WARNING);
			return false;
		}
	}
		
	/**
	 * PDO Connect.
	 *
	 * @param	string
	 * @return	void
	 */
	private static function connectDatabase( $databaseName ) {
		global $settings;
		
		if (isset(self::$connections[$databaseName])) { // Check if application is already connected
			return self::$connections[$databaseName];
		
		} else { // Otherwise create connection
			
			$databaseAddress = explode(':', $settings['databases'][$databaseName]['host']); // Get database address and port
			
			// Create DNS
			switch($settings['databases'][$databaseName]['type']) {
				
				case 'mysql':
					$dsn = 
						"mysql:dbname={$settings['databases'][$databaseName]['dbname']};".
						"host={$databaseAddress[0]};".
						(!empty($databaseAddress[1]) ? "port={$databaseAddress[1]};":"");
					
					// Use UTF-8 with MySQL by default
					$driverOptions = [PDO::MYSQL_ATTR_INIT_COMMAND=>'SET NAMES \'UTF8\''];
					break;
				
				case 'sqlserver':
					$dsn = 
						"dblib:dbname={$settings['databases'][$databaseName]['dbname']};".
						"host={$databaseAddress[0]};".
						"charset=UTF-8;".
						(!empty($databaseAddress[1]) ? "port={$databaseAddress[1]};":"");
					
					$driverOptions = [];
					break;
				
				case 'odbc':
					$dsn = 
						"odbc:{$settings['databases'][$databaseName]['source']}";
					
					$driverOptions = [];
					break;
			}
			
			// Persistent connections
			if (isset($settings['databases'][$databaseName]['persistent']) && $settings['databases'][$databaseName]['persistent']) $driverOptions[PDO::ATTR_PERSISTENT] = true;
			
			// Try to connect
			try {				
				$connection = 
					new PDO(
						$dsn, 
						$settings['databases'][$databaseName]['user'], 
						$settings['databases'][$databaseName]['password'],
						$driverOptions
					);
			}
			catch(PDOException $e) {
				trigger_error($e->getMessage());
				return false;
			}
			
			self::$connections[$databaseName] = $connection; // Store the database connection for other instances
			
			return $connection;
		}
	}
	
	/**
	 * Check if connection was successful
	 * 
	 * @param	string
	 * @return	self
	 */
	public static function connected( $databaseName ) {
		global $settings;
		
		if (isset(self::$connections[$databaseName])) {
			return true;
		} else {
			if (isset($settings['databases'][$databaseName])) {
				if (self::connectDatabase( $databaseName )) {
					return true;
				}
			} else {
				trigger_error("The database {$databaseName} does not exists", E_USER_WARNING);
			}
		}
		return false;
	}
	
	/**
	 * PDO methods shortcuts.
	 *
	 * @param	string
	 * @param	array
	 * @return	void
	 */
	public function __call( $method, $args ) {
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
					$this->connection = self::connectDatabase( $this->databaseName );
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
	 * @return	void
	 */
	public function __destruct() {
		$this->connection = null;
	}
}