<?php

require CORE."classes/ORM.php";

class Model extends sinergi\ORM {
	/**
	 * The PDO Connection object.
	 *
	 * @var object
	 * @access private
	 */
	private $db = null;
	protected $database = null;
	protected $db_type;
	protected $connection;
				
	/**
	 * change model Database.
	 *
	 * @access protected
	 * @return void
	 */
	protected function set_database($database=null) {
		global $config;
		
		if(!isset($this->database)) {
			$this->database = $this->get_database_name();
			$this->db_type = $config['databases'][$this->database]['type'];
		}
	}
	
	/**
	 * PDO Connect.
	 *
	 * @access private
	 * @return void
	 */
	private function connect_database($database=null) {
		global $config;
		
		$this->set_database();
		
		if (isset($this->database)) {
			$database_address = explode(':', $config['databases'][$this->database]['host']); // Get database address and port
			switch($this->db_type) {
				case 'mysql':
					$dsn = "mysql:dbname={$config['databases'][$this->database]['dbname']};host={$database_address[0]};".(isset($database_address[1]) ? "port={$database_address[1]};":"");
					break;
				case 'access':
					$dsn = "dblib:dbname={$config['databases'][$this->database]['dbname']};host={$database_address[0]};charset=UTF-8;".(isset($database_address[1]) ? "port={$database_address[1]};":"");
					break;
			}
			
			try {
				switch($this->db_type) {
					case 'mysql': // Use UTF-8 with MySQL by default
						$this->db = new PDO($dsn, $config['databases'][$this->database]['user'], $config['databases'][$this->database]['password'], [PDO::MYSQL_ATTR_INIT_COMMAND=>'SET NAMES \'UTF8\'']);
						break;
					default:
						$this->db = new PDO($dsn, $config['databases'][$this->database]['user'], $config['databases'][$this->database]['password']);
						break;
				}
				$this->connection = $this->db;
			}
			catch(PDOException $e) {
				trigger_error($e->getMessage());
			}
		}
	}
	
	/**
	 * PDO Connect.
	 *
	 * @access protected
	 * @return void
	 */
	private function get_database_name() {
		global $config;
		
		$namespaces = explode('\\', str_replace('model\\', '', get_class($this)), 2);
		
		if (!isset($namespaces[1])) { // No database selected, use the only one given in settings
			if (count($config["databases"])>1) trigger_error("No database selected"); // Trigger error if multiple databases are setup but none is selected
			else $namespaces[0] = key($config["databases"]);
		}
		
		return $namespaces[0];
	}
	
	/**
	 * PDO Shortcut.
	 *
	 * @access public
	 * @return object
	 */
	public function __call($method, $args) {
		
		switch($method) {
			case 'commit': case 'errorCode': case 'errorInfo': case 'exec': case 'getAttribute': case 'getAvailableDrivers': case 'inTransaction': case 'lastInsertId': case 'prepare': case 'query': case 'quote': case 'rollBack': case 'setAttribute':
				if (!isset($this->db)) $this->connect_database();
				
				if (count($args)>1) {
					return call_user_func(array($this->db, $method), $args);
				}
				else if (count($args)==1) {
					return call_user_func(array($this->db, $method), $args[0]);
				}
				else return call_user_func(array($this->db, $method));
				
				break;
			default: 
				trigger_error('unknown method');
				break;
		}
	}
		
	/**
	 * Close connection with database when object is destroyed.
	 * 
	 * @access public
	 * @return void
	 */
	public function __destruct() {
		$this->db = null;
	}

}