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
 * The database query manager.
 *
 * @category	core
 * @package		sinergi
 * @author		Sinergi Team
 * @link		https://github.com/sinergi/sinergi
 */

namespace sinergi\db;

use PDO, ArrayObject;

class Table extends ArrayObject {
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
	 * @var bool, Row object
	 * @access private, private
	 */
	private $is_unique = false, $unique_object;
	
	/**
	 * Boolean used to track if order by has already been started, in which case we use a comma to separate them
	 * 
	 * @param $provider
	 * @var bool
	 * @access private
	 * @return const
	 */
	private $order_by = false;
	
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
				$query_fields .= "{$this->slashes[$this->driver][0]}{$key}{$this->slashes[$this->driver][1]}=:value{$this->bind_count}, ";
				$this->binds["value{$this->bind_count}"] = $value;
			}
			$query_fields = substr($query_fields, 0, -2);
			
			// Replace SELECT * FROM by UPDATE and query fields
			$this->query = preg_replace(
				"/SELECT \* FROM {$this->slashes[$this->driver][0]}([^{$this->slashes[$this->driver][1]}.]*){$this->slashes[$this->driver][1]}/", 
				"UPDATE {$this->slashes[$this->driver][0]}$1{$this->slashes[$this->driver][1]}".$query_fields, 
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
				$query_fields .= "{$this->slashes[$this->driver][0]}{$key}{$this->slashes[$this->driver][1]}={$this->slashes[$this->driver][0]}{$key}{$this->slashes[$this->driver][1]}+:value{$this->bind_count}, ";
				$this->binds["value{$this->bind_count}"] = $value;
			}
			$query_fields = substr($query_fields, 0, -2);
			
			// Replace SELECT * FROM by UPDATE and query fields
			$this->query = preg_replace(
				"/SELECT \* FROM {$this->slashes[$this->driver][0]}([^{$this->slashes[$this->driver][1]}.]*){$this->slashes[$this->driver][1]}/", 
				"UPDATE {$this->slashes[$this->driver][0]}$1{$this->slashes[$this->driver][1]}".$query_fields, 
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
			foreach($fields as $field) $query_fields .= "{$this->slashes[$this->driver][0]}{$field}{$this->slashes[$this->driver][1]}, "; // Create query fields
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
			
			$this->unique_object = new Row($this->connection, $this->table_name, $this->driver, $this->slashes);
			
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
					
					$obj = new Row($this->connection, $this->table_name, $this->driver, $this->slashes);
					
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
	public function create_die($field, $value=null) { $this->die = true; return $this->create($field, $value); }### TO REMOVE OF COURSE 
	public function create($field, $value=null) {
		if (!is_array($field)) { $field = [$field=>$value]; } // Options
		
		$query = "INSERT INTO {$this->slashes[$this->driver][0]}{$this->table_name}{$this->slashes[$this->driver][1]} (";
		$query_values = "";
		$binds = [];
		$bind_count = 0;

		foreach ($field as $key=>$value) {
			$bind_count++;
			$query .= "{$this->slashes[$this->driver][0]}{$key}{$this->slashes[$this->driver][1]}, ";
			$query_values .= ":value{$bind_count}, ";
			$binds["value{$bind_count}"] = $value;
		}
		
		$query = substr($query, 0, -2).") VALUES (".substr($query_values, 0, -2).");";
			
		if (isset($this->die) && $this->die==true) {
			print_R($binds);
			echo $query; die();
		}
		
		$sth = $this->prepare($query);
		$sth->execute($binds);
		
		$this->reset_query();
		
		return $this;
	}
	
	/**
	 * Replace
	 * 
	 * @param $provider
	 * @var bool
	 * @access private
	 * @return const
	 */
	public function replace_die($field, $value=null) { $this->die = true; return $this->replace($field, $value); }### TO REMOVE OF COURSE 
	public function replace($field, $value=null) {
		if (!is_array($field)) { $field = [$field=>$value]; } // Options
		
		$query = "REPLACE INTO {$this->slashes[$this->driver][0]}{$this->table_name}{$this->slashes[$this->driver][1]} (";
		$query_values = "";
		$binds = [];
		$bind_count = 0;

		foreach ($field as $key=>$value) {
			$bind_count++;
			$query .= "{$this->slashes[$this->driver][0]}{$key}{$this->slashes[$this->driver][1]}, ";
			$query_values .= ":value{$bind_count}, ";
			$binds["value{$bind_count}"] = $value;
		}
		
		$query = substr($query, 0, -2).") VALUES (".substr($query_values, 0, -2).");";
			
		if (isset($this->die) && $this->die==true) {
			print_R($binds);
			echo $query; die();
		}
		
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
		
		switch ($this->driver) {
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
		
		if ($this->order_by==false) { // First order by
			$this->query .= ($this->add_parenthesis ? ")" : "")." ORDER BY ";
			$this->add_parenthesis = false;
			$this->order_by = true;
		} else {
			$this->query .= ", ";
		}
		
		$this->query .= "{$this->slashes[$this->driver][0]}{$field}{$this->slashes[$this->driver][1]} ".(!$asc ? "DESC " : "");

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
		$this->query .= " AND {$this->slashes[$this->driver][0]}{$field}{$this->slashes[$this->driver][1]}{$operator}";
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
		if (!isset($this->query) || $this->query=='') {
			$this->query = "SELECT * FROM {$this->slashes[$this->driver][0]}{$this->table_name}{$this->slashes[$this->driver][1]} WHERE (";
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
