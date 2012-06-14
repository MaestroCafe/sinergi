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

#require CORE."classes/db/table.php";
#require CORE."classes/db/row.php";
#
#class DB extends sinergi\db\table {
#	/**
#	 * Connections are static for re-use.
#	 *
#	 * @var	array
#	 */
#	static public $connections = [];
#	
#	/**
#	 * The connection to the database.
#	 *
#	 * @var	string
#	 */
#	private $connection;
#	
#	/**
#	 * The database name.
#	 *
#	 * @var	string
#	 */
#	private $database_name = null;
#	
#	/**
#	 * Database driver.
#	 *
#	 * @var	string
#	 */
#	protected $driver;
#	
#	/**
#	 * Database table.
#	 *
#	 * @var	string
#	 */
#	protected $table;
#	
#	/**
#	 * Database manager constructor.
#	 * 
#	 * @param	string	the name of the database
#	 * @param	string	the name of the table
#	 * @return	void
#	 */
#	public function __construct( $database_name, $table_name = null ) {
#		if (!isset($table_name)) {
#			$table_name = $database_name;
#			$database_name = null;
#		}
#		
#		$this->set_database($database_name);
#		$this->table_name = $table_name;
#	}
#	
#	/**
#	 * Set the database.
#	 *
#	 * @access private
#	 * @return void
#	 */
#	private function set_database( $database_name = null ) {
#		global $config;
#		
#		if (isset($database_name)) {
#			$this->database_name = $database_name;
#		} else {
#			$this->database_name = key($config['databases']);
#		}
#		
#		$this->driver = $config['databases'][$this->database_name]['type'];
#	}
#	
#	/**
#	 * PDO Connect.
#	 *
#	 * @access private
#	 * @return void
#	 */
#	private function connect_database() {
#		global $config;
#		
#		if (isset(self::$connections[$this->database_name])) { // Check if application is already connected
#			$this->connection = self::$connections[$this->database_name];
#		
#		} else { // Otherwise create connection
#			
#			$database_address = explode(':', $config['databases'][$this->database_name]['host']); // Get database address and port
#			
#			// Create DNS
#			switch($this->driver) {
#				case 'mysql':
#					$dsn = 
#						"mysql:dbname={$config['databases'][$this->database_name]['dbname']};".
#						"host={$database_address[0]};".
#						(!empty($database_address[1]) ? "port={$database_address[1]};":"");
#					break;
#				case 'access':
#					$dsn = 
#						"dblib:dbname={$config['databases'][$this->database_name]['dbname']};".
#						"host={$database_address[0]};".
#						"charset=UTF-8;".
#						(!empty($database_address[1]) ? "port={$database_address[1]};":"");
#					break;
#				case 'odbc':
#					$dsn = 
#						"odbc:{$config['databases'][$this->database_name]['source']}";
#					break;
#			}
#			
#			// Create driver options
#			switch($this->driver) {
#			    case 'mysql': // Use UTF-8 with MySQL by default
#			    	$driver_options = [PDO::MYSQL_ATTR_INIT_COMMAND=>'SET NAMES \'UTF8\''];
#			    	break;
#			    	
#			    default:
#			    	$driver_options = [];
#			    	break;
#			}
#			
#			// Try to connect
#			try {				
#				$this->connection = 
#			    	new PDO(
#			    		$dsn, 
#			    		$config['databases'][$this->database_name]['user'], 
#			    		$config['databases'][$this->database_name]['password'],
#			    		$driver_options
#			    	);
#			    
#			}
#			catch(PDOException $e) {
#				trigger_error($e->getMessage());
#			}
#			
#			self::$connections[$this->database_name] = $this->connection; // Store the database connection for other instances
#		}
#	}
#	
#	/**
#	 * PDO methods shortcuts.
#	 *
#	 * @return void
#	 */
#	public function __call($method, $args) {
#		switch($method) {
#			case 'commit': 
#			case 'errorCode': 
#			case 'errorInfo': 
#			case 'exec': 
#			case 'getAttribute': 
#			case 'getAvailableDrivers': 
#			case 'inTransaction': 
#			case 'lastInsertId': 
#			case 'prepare': 
#			case 'query': 
#			case 'quote': 
#			case 'rollBack': 
#			case 'setAttribute':
#				if (!isset($this->connection)) {
#					$this->connect_database();
#				}
#				
#				// Call the PDO method
#				if (count($args)>1) {
#					return call_user_func(array($this->connection, $method), $args);
#				} else if (count($args)==1) {
#					return call_user_func(array($this->connection, $method), $args[0]);
#				} else {
#					return call_user_func(array($this->connection, $method));
#				}
#				
#				break;
#			
#			default: 
#				trigger_error('unknown method');
#				break;
#		}
#	}
#		
#	/**
#	 * Close connection with database when object is destroyed.
#	 * 
#	 * @return void
#	 */
#	public function __destruct() {
#		$this->connection = null;
#	}
#}