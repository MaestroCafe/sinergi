<?php

namespace sinergi;

use PDO, ArrayObject;

class ORM extends ArrayObject {
	/**
	 * Table name
	 * 
	 * @param $provider
	 * @var bool
	 * @access private
	 */
	public $table_name;
	
	/**
	 * Query and parameters
	 * 
	 * @param $provider
	 * @var bool
	 * @access private
	 */
	private $query, $binds = [], $bind_count = 0;
	
	/**
	 * Boolean that defines the get or get_all methods have been called, if so we use this boolean
	 * 
	 * @param $got_results
	 * @var bool
	 * @access private
	 */
	private $got_results = false;
	
	/**
	 * Boolean that defines if the results come from the get or a get_all method and the object for that unique result
	 * 
	 * @param $is_unique, $unique_object
	 * @var bool, Result object
	 * @access private, private
	 */
	private $is_unique = false, $unique_object;
	
	/**
	 * Logic operators
	 * 
	 * @param $provider
	 * @var bool
	 * @access private
	 */
	private $or_operator = false, $not_operator = false, $and_operator = false;
	private $add_parenthesis = true;
	
	private $slashes = [
		'mysql' => ['`', '`'],
		'access' => ['[', ']']
	];
	
	/**
	 * Update
	 * 
	 * @param $provider
	 * @var bool
	 * @access private
	 * @return const
	 */
	public function update($field, $value=null) {
		
		if($this->got_results && $this->is_unique) { // Update a unique result
			$this->unique_object->update($field, $value);
			$this->refresh_unique_object();
		
		} else if ($this->got_results) { // Update multiple results
			foreach($this as $obj) {
				$obj->update($field, $value);
			}

		} else { // Update with a query
			if (!is_array($field)) { $field = [$field=>$value]; } // Options
			
			$this->select(); // Prepare select query
					
			$this->clean_query();
			
			$query_fields = " SET ";
			
			foreach ($field as $key=>$value) {
				$this->bind_count++;
				$query_fields .= "{$this->slashes[$this->db_type][0]}{$key}{$this->slashes[$this->db_type][1]}=:value{$this->bind_count}, ";
				$this->binds["value{$this->bind_count}"] = $value;
			}
			$query_fields = substr($query_fields, 0, -2);
			
			// Replace SELECT * FROM by UPDATE and query fields
			$this->query = preg_replace(
				"/SELECT \* FROM {$this->slashes[$this->db_type][0]}([^{$this->slashes[$this->db_type][1]}.]*){$this->slashes[$this->db_type][1]}/", 
				"UPDATE {$this->slashes[$this->db_type][0]}$1{$this->slashes[$this->db_type][1]}".$query_fields, 
				$this->query
			);
			
			$sth = $this->prepare($this->query);
			$sth->execute($this->binds);
			
			$this->reset_query();
		}
				
		return $this;		
	}

	/**
	 * Decrease
	 * 
	 * @param $provider
	 * @var bool
	 * @access private
	 * @return const
	 */
	public function decrease($field, $value=1) {
		return $this->increase($field, -$value);
	}	
	
	/**
	 * Increase
	 * 
	 * @param $provider
	 * @var bool
	 * @access private
	 * @return const
	 */
	public function increase($field, $value=1) {
		
		if($this->got_results && $this->is_unique) { // Update a unique result
			$this->unique_object->increase($field, $value);
			$this->refresh_unique_object();
		
		} else if ($this->got_results) { // Update multiple results
			foreach($this as $obj) {
				$obj->increase($field, $value);
			}

		} else { // Increase with a query
			if (!is_array($field)) { $field = [$field=>$value]; } // Options
			
			$this->select(); // Prepare select query
					
			$this->clean_query();
			
			$query_fields = " SET ";
			
			foreach ($field as $key=>$value) {
				$this->bind_count++;
				$query_fields .= "{$this->slashes[$this->db_type][0]}{$key}{$this->slashes[$this->db_type][1]}={$this->slashes[$this->db_type][0]}{$key}{$this->slashes[$this->db_type][1]}+:value{$this->bind_count}, ";
				$this->binds["value{$this->bind_count}"] = $value;
			}
			$query_fields = substr($query_fields, 0, -2);
			
			// Replace SELECT * FROM by UPDATE and query fields
			$this->query = preg_replace(
				"/SELECT \* FROM {$this->slashes[$this->db_type][0]}([^{$this->slashes[$this->db_type][1]}.]*){$this->slashes[$this->db_type][1]}/", 
				"UPDATE {$this->slashes[$this->db_type][0]}$1{$this->slashes[$this->db_type][1]}".$query_fields, 
				$this->query
			);
			
			$sth = $this->prepare($this->query);
			$sth->execute($this->binds);
			
			$this->reset_query();
		}
				
		return $this;
	}
	
	/**
	 * 
	 * 
	 * @param $provider
	 * @var bool
	 * @access private
	 * @return const
	 */
	protected function refresh_unique_object() {
		foreach ($this->unique_object as $field=>$value) {
			$this->$field = $this[$field] = $value;
		}
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
		
		if($this->got_results && $this->is_unique) { // Delete a unique result
			$this->unique_object->delete();
		
		} else if ($this->got_results) { // Delete multiple results
			foreach($this as $obj) {
				$obj->delete();
			}

		} else { // Update with a query
			$this->select(); // Prepare select query
			
			$this->query = str_replace('SELECT * FROM', 'DELETE FROM', $this->query);
			
			$this->clean_query();
			
			$sth = $this->prepare($this->query);
			$sth->execute($this->binds);
			
			$this->reset_query();
		}
		
		return $this;		
	}
	
	/**
	 * Get results
	 * 
	 * @param $provider
	 * @var bool
	 * @access private
	 * @return const
	 */
	public function get_all_die($fields=null, $get_one=null) { $this->die = true; return $this->get_all($fields, $get_one); }### TO REMOVE OF COURSE 
	public function get_die($fields=null) { $this->die = true; return $this->get($fields); }### TO REMOVE OF COURSE 
	
	public function get($fields=null) {
		$this->is_unique = true;
		return $this->get_all($fields, true);
	}
	
	public function get_all($fields=null, $get_one=null) {
		if (isset($fields) && !is_array($fields)) $fields = [$fields]; // Options
		
		$this->got_results = true;
		
		$this->select(); // Prepare select query
		
		if (isset($get_one) && $get_one===true) {
			$this->limit(1);
		} 
		
		$this->clean_query();
		
		if (isset($fields)) {
			$query_fields = "";
			foreach($fields as $field) $query_fields .= "{$this->slashes[$this->db_type][0]}{$field}{$this->slashes[$this->db_type][1]}, "; // Create query fields
			$query_fields = substr($query_fields, 0, -2);
						
			$this->query = str_replace(" * FROM", " {$query_fields} FROM", $this->query); // Put query fields in query
		}
		
/* 		echo $this->query; die(); */
		if (isset($this->die) && $this->die==true) {
			print_R($this->binds);
			echo $this->query; die();
		}

		$sth = $this->prepare($this->query);
		$sth->execute($this->binds);
				
		// If there is only one result, make the fields avalaible directly after the read (without the array)
		if (isset($get_one) && $get_one===true) {
			$result = $sth->fetch(PDO::FETCH_ASSOC);
			
			$this->unique_object = new Result($this->connection, $this->table_name, $this->db_type, $this->slashes);
			
			if (is_array($result)) {
				foreach($result as $field=>$value) {
					$this->$field = $this[$field] = $this->unique_object->$field = $this->unique_object[$field] = $value;
				}
			}
		
		// Multiple results
		} else {
			$results = $sth->fetchAll(PDO::FETCH_ASSOC);
			
			if (is_array($results)) {
				foreach ($results as $row) {
					
					$obj = new Result($this->connection, $this->table_name, $this->db_type, $this->slashes);
					
					foreach($row as $field=>$value) {
						$obj->$field = $obj[$field] = $value;
					}
					$this->append($obj);
				}
			}
		}
		
		$this->reset_query();
		
		return $this;		
	}
	
	/**
	 * Clean the query
	 * 
	 * @access private
	 * @return void
	 */
	private function clean_query() {
		$this->query = str_replace(
			['  ', '( (', '  ', 'WHERE ( AND', '( AND', '( ', ' )', ' WHERE ()', ' ;'], 
			[' ', '((', ' ', 'WHERE (', '(', '(', ')', '', ';'], 
			$this->query.($this->add_parenthesis ? ")":"").";");
		
		if (substr($this->query, -10, -1)==' WHERE ()') $this->query = substr($this->query, 0, -10).";"; // Remove WHERE at the end of the query when now arguments are provided
	}
	
	/**
	 * Create
	 * 
	 * @param $provider
	 * @var bool
	 * @access private
	 * @return const
	 */
	public function create($field, $value=null) {
		if (!is_array($field)) { $field = [$field=>$value]; } // Options
		
		$this->set_database();
		$this->get_table_name(); // Get table name
		
		$query = "INSERT INTO {$this->slashes[$this->db_type][0]}{$this->table_name}{$this->slashes[$this->db_type][1]} (";
		$query_values = "";
		$binds = [];
		$bind_count = 0;

		foreach ($field as $key=>$value) {
			$bind_count++;
			$query .= "{$this->slashes[$this->db_type][0]}{$key}{$this->slashes[$this->db_type][1]}, ";
			$query_values .= ":value{$bind_count}, ";
			$binds["value{$bind_count}"] = $value;
		}
		
		$query = substr($query, 0, -2).") VALUES (".substr($query_values, 0, -2).");";
			
		$sth = $this->prepare($query);
		$sth->execute($binds);
		
		$this->reset_query();
		
		return $this;
	}
	
	/**
	 * Get last insert id
	 * 
	 * @access public
	 * @return int
	 */
	public function get_id() {
		return $this->lastInsertId();
	}
	
	/**
	 * Check if exists
	 * 
	 * @param $provider
	 * @var bool
	 * @access private
	 * @return const
	 */
	public function exists() {
#		$this->select(); // Prepare select query
#		
#		$this->query = str_replace(['  ', 'WHERE ( AND'], [' ', 'WHERE'], $this->query.";");
#		
#		$sth = $this->prepare($this->query.";");
#		$sth->execute($this->binds);
#		
#		$results = $sth->fetchAll(PDO::FETCH_ASSOC);
#						
#		$this->reset_query();
#		
#		return count($results) ? $this : false;		
	}
	
	/**
	 * Find
	 * 
	 * @param $provider
	 * @var bool
	 * @access private
	 * @return const
	 */
	public function find($field, $value=null) {
		if (!is_array($field)) { $field = [$field=>$value]; } // Options
		$this->select(); // Prepare select query
		
		foreach ($field as $key=>$value) {
			if ($value===null) {
				$this->where($key, ($this->not_operator ? ' IS NOT ' : ' IS '), $value);
			} else {
				$this->where($key, ($this->not_operator ? '!=' : '='), $value);
			}
		}
		$this->not_operator = false;
		
		return $this;
	}
	
	/**
	 * Above
	 * 
	 * @param $provider
	 * @var bool
	 * @access private
	 * @return const
	 */
	public function above($field, $value=null) {
		if (!is_array($field)) { $field = [$field=>$value]; } // Options
		$this->select(); // Prepare select query
		
		foreach ($field as $key=>$value) {
			$this->where($key, ($this->not_operator ? '<=' : '>'), $value);
		}
		$this->not_operator = false;
		
		return $this;
	}
	
	/**
	 * Below
	 * 
	 * @param $provider
	 * @var bool
	 * @access private
	 * @return const
	 */
	public function below($field, $value=null) {
		if (!is_array($field)) { $field = [$field=>$value]; } // Options
		
		$this->select(); // Prepare select query
				
		foreach ($field as $key=>$value) {
			$this->where($key, ($this->not_operator ? '>=' : '<'), $value);
		}
		$this->not_operator = false;
		
		return $this;
	}
	
	/**
	 * Contains allow you to search for a value in a field
	 * 
	 * @param $provider
	 * @var bool
	 * @access private
	 * @return const
	 */
	public function contains($field, $value=null) {
		if (!is_array($field)) { $field = [$field=>$value]; } // Options
		
		$this->select(); // Prepare select query
		
		foreach ($field as $key=>$value) {
			$this->where($key, ($this->not_operator ? ' NOT LIKE ' : ' LIKE '), '%'.$value.'%');
		}
		$this->not_operator = false;
		
		return $this;
	}
	
	/**
	 * Limit
	 * 
	 * @param $provider
	 * @var bool
	 * @access public
	 * @return const
	 */
	public function limit($offset, $row_count=null) {		
		$this->select(); // Prepare select query
		
		switch ($this->db_type) {
			case 'access':
				if (isset($row_count)) trigger_error("Microsoft Access does not support offset for limit.");
				$this->query = str_replace("SELECT ", "SELECT TOP({$offset}) ", $this->query);
				break;
			default:
				$this->query .= ($this->add_parenthesis ? ")" : "")." LIMIT {$offset}".(isset($row_count) ? ", {$row_count}" : "");
				$this->add_parenthesis = false;
				break;
		}
		return $this;
	}
	
	/**
	 * Order
	 * 
	 * @param $provider
	 * @var bool
	 * @access public
	 * @return const
	 */
	public function order($field, $asc=true) {		
		$this->select(); // Prepare select query
		
		$this->query .= ($this->add_parenthesis ? ")" : "")." ORDER BY {$this->slashes[$this->db_type][0]}{$field}{$this->slashes[$this->db_type][1]} ".(!$asc ? "DESC " : "");
		$this->add_parenthesis = false;

		return $this;
	}
	
	/**
	 * Where
	 * 
	 * @param $provider
	 * @var bool
	 * @access private
	 * @return const
	 */
	private function where($field, $operator, $value) {
		$this->bind_count++;
		$this->query .= " AND {$this->slashes[$this->db_type][0]}{$field}{$this->slashes[$this->db_type][1]}{$operator}";
		if ($value===null) {
			$this->query .= "NULL";		
		} else {
			$this->query .= ":value{$this->bind_count}";
			$this->binds[":value{$this->bind_count}"] = $value;
		}
	}
	
	/**
	 * Select
	 * 
	 * @param $provider
	 * @var bool
	 * @access private
	 * @return const
	 */
	private function select() {
		$this->set_database();
		$this->get_table_name(); // Get table name
		if (!isset($this->query) || $this->query=='') {
			$this->query = "SELECT * FROM {$this->slashes[$this->db_type][0]}{$this->table_name}{$this->slashes[$this->db_type][1]} WHERE (";
		}
		
		if ($this->or_operator) { // Apply or logic operator
			$this->query .= ") OR (";
			$this->reset_operators();
			
		} elseif ($this->and_operator) { // Apply and logic operator
			$this->query = str_replace("WHERE", "WHERE ( ", $this->query)." )";
			$this->query .= ") AND (";
			$this->reset_operators();
		}
	}
	
	/**
	 * Get table name
	 * 
	 * @param $provider
	 * @var bool
	 * @access private
	 * @return const
	 */
	private function get_table_name() {
		if (!isset($this->table_name) || $this->table_name=='') $this->table_name = strtolower(strrev(explode('\\', strrev(get_class($this)), 2)[0]));
	}
		
	/**
	 * Reset Query
	 * 
	 * @param $provider
	 * @var bool
	 * @access private
	 * @return const
	 */
	private function reset_query() {
		$this->query = null;
		$this->binds = [];
		$this->bind_count = 0;
	}	
	
	/**
	 * __get magic method is used to switch operators (or, not) 
	 * 
	 * @param $provider
	 * @var bool
	 * @access private
	 * @return const
	 */
    public function __get($name) {
		switch($name) {
			case 'or': $this->or_operator = true; break;
			case 'not': $this->not_operator = true; break;
			case 'and': $this->and_operator = true; break;
		}
		return $this;
    }
		
	/**
	 * Reset operators
	 * 
	 * @param $provider
	 * @var bool
	 * @access private
	 * @return const
	 */
	private function reset_operators() {
		$this->or_operator = $this->not_operator = $this->and_operator = false;
	}
}

class Result extends ArrayObject {
	/**
	 * 
	 * 
	 * @param $provider
	 * @var bool
	 * @access private
	 * @return const
	 */
	protected $connection, $table_name, $db_type, $slashes;
	
	/**
	 * 
	 * 
	 * @param $provider
	 * @var bool
	 * @access private
	 * @return const
	 */
	public function __construct($connection, $table_name, $db_type, $slashes) {
		$this->connection = $connection;
		$this->table_name = $table_name;
		$this->db_type = $db_type;
		$this->slashes = $slashes;
	}

	/**
	 * 
	 * 
	 * @param $provider
	 * @var bool
	 * @access private
	 * @return const
	 */
	public function update($field, $value=null) {
		if (!is_array($field)) { $field = [$field=>$value]; } // Options
		
		$bind_count = 0;
				
		// Build the "set" part of the query
		$query_set = " SET ";
		
		foreach ($field as $key=>$value) {
			$bind_count++;
			$query_set .= "{$this->slashes[$this->db_type][0]}{$key}{$this->slashes[$this->db_type][1]}=:value{$bind_count}, ";
			$binds["value{$bind_count}"] = $value;
			// Store new values to change them in the object after the update
			$new_values[$key] = $value;
		}
		$query_set = substr($query_set, 0, -2);
		
		$this->query("UPDATE", $binds, $bind_count, $query_set);
		
		return $this;
	}
	
	/**
	 * 
	 * 
	 * @param $provider
	 * @var bool
	 * @access private
	 * @return const
	 */
	public function decrease($field, $value=1) {
		return $this->increase($field, -$value);
	}

	/**
	 * 
	 * 
	 * @param $provider
	 * @var bool
	 * @access private
	 * @return const
	 */
	public function increase($field, $value=1) {
		if (!is_array($field)) { $field = [$field=>$value]; } // Options
		
		$bind_count = 0;
				
		// Build the "set" part of the query
		$query_set = " SET ";
		
		foreach ($field as $key=>$value) {
			$bind_count++;
			$query_set .= "{$this->slashes[$this->db_type][0]}{$key}{$this->slashes[$this->db_type][1]}={$this->slashes[$this->db_type][0]}{$key}{$this->slashes[$this->db_type][1]}+:value{$bind_count}, ";
			$binds["value{$bind_count}"] = $value;
			// Store new values to change them in the object after the update
			$new_values[$key] = $this->$key + $value;
		}
		$query_set = substr($query_set, 0, -2);
		
		$this->query("UPDATE", $binds, $bind_count, $query_set, $new_values);
		
		return $this;
	}
	
	/**
	 * 
	 * 
	 * @param $provider
	 * @var bool
	 * @access private
	 * @return const
	 */
	public function delete() {
		$this->query("DELETE FROM");
		
		return $this;
	}
	
	/**
	 * The query function serves the update and delete fuctions. Because both functions are almost identical,
	 * 
	 * @param $provider
	 * @var bool
	 * @access private
	 * @return const
	 */
	protected function query($query, $binds = [], $bind_count = 0, $query_set="", $new_values=null) {
		$query = strtoupper($query);
		
		// Build the "where" part of the query
		$query_where = " WHERE ";
		
		foreach($this as $key=>$value) {
			$bind_count++;
			if (!empty($value)) {
				$query_where .= "{$this->slashes[$this->db_type][0]}{$key}{$this->slashes[$this->db_type][1]}=:value{$bind_count} AND ";
				$binds["value{$bind_count}"] = $value;
			}
		}
		$query_where = substr($query_where, 0, -5);
				
		// Build query
		$query .= " {$this->slashes[$this->db_type][0]}{$this->table_name}{$this->slashes[$this->db_type][1]}{$query_set}{$query_where};";
				
		$sth = $this->connection->prepare($query);
		$sth->execute($binds);
		
		// Change values of current object to updated object
		foreach($new_values as $key=>$value) {
			$this->$key = $value;
			$this[$key] = $value;
		}
	}
}