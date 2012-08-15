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

use PDO, 
	ArrayObject;

class Manager extends ArrayObject {
	/**
	 * Query and parameters
	 * 
	 * @param $provider
	 * @var bool
	 * @access private
	 */
	private $query, $binds = [], $bindCount = 0;
	
	/**
	 * Boolean that defines the get or getAll methods have been called, if so we use this boolean
	 * 
	 * @param $gotResults
	 * @var bool
	 * @access private
	 */
	private $gotResults = false;
	
	/**
	 * Boolean that defines if the results come from the get or a getAll method and the object for that unique result
	 * 
	 * @param $isUnique, $uniqueObject
	 * @var bool, Result object
	 * @access private, private
	 */
	private $isUnique = false, $uniqueObject;
	
	/**
	 * Boolean used to track if order by has already been started, in which case we use a comma to separate them
	 * 
	 * @param $provider
	 * @var bool
	 * @access private
	 * @return const
	 */
	private $orderBy = false;
	
	/**
	 * Logic operators
	 * 
	 * @param $provider
	 * @var bool
	 * @access private
	 */
	private $orOperator = false, $notOperator = false, $andOperator = false;
	private $addParenthesis = true;
	
	private $slashes = [
		'mysql' => ['`', '`'],
		'access' => ['[', ']']
	];
	
	/**
	 * Table's fields
	 * 
	 * @var	array
	 */
	private $fields;
	
	/**
	 * List the table's fields
	 * 
	 * @return	array
	 */
	public function listFields() {
		if (isset($this->fields)) {
			return $this->fields;
		} else {
			$sth = $this->prepare("SHOW COLUMNS FROM {$this->slashes[$this->driver][0]}{$this->tableName}{$this->slashes[$this->driver][1]};");
			$sth->execute();
			$results = $sth->fetchAll(PDO::FETCH_ASSOC);
			
			$this->fields = [];
			foreach($results as $field) {
				$this->fields[$field['Field']] = $field;
			}
			
			return $this->fields;
		}
	}
	
	/**
	 * Update
	 * 
	 * @param $provider
	 * @var bool
	 * @access private
	 * @return const
	 */
	public function update($field, $value=null) {
		
		if($this->gotResults && $this->isUnique) { // Update a unique result
			$this->uniqueObject->update($field, $value);
			$this->refreshUniqueObject();
		
		} else if ($this->gotResults) { // Update multiple results
			foreach($this as $obj) {
				$obj->update($field, $value);
			}

		} else { // Update with a query
			if (!is_array($field)) { $field = [$field=>$value]; } // Options
			
			$this->select(); // Prepare select query
					
			$this->cleanQuery();
			
			$queryFields = " SET ";
			
			foreach ($field as $key=>$value) {
				$this->bindCount++;
				$queryFields .= "{$this->slashes[$this->driver][0]}{$key}{$this->slashes[$this->driver][1]}=:value{$this->bindCount}, ";
				$this->binds["value{$this->bindCount}"] = $value;
			}
			$queryFields = substr($queryFields, 0, -2);
			
			// Replace SELECT * FROM by UPDATE and query fields
			$this->query = preg_replace(
				"/SELECT \* FROM {$this->slashes[$this->driver][0]}([^{$this->slashes[$this->driver][1]}.]*){$this->slashes[$this->driver][1]}/", 
				"UPDATE {$this->slashes[$this->driver][0]}$1{$this->slashes[$this->driver][1]}".$queryFields, 
				$this->query
			);
			
			$sth = $this->prepare($this->query);
			$sth->execute($this->binds);
			
			$this->resetQuery();
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
		
		if($this->gotResults && $this->isUnique) { // Update a unique result
			$this->uniqueObject->increase($field, $value);
			$this->refreshUniqueObject();
		
		} else if ($this->gotResults) { // Update multiple results
			foreach($this as $obj) {
				$obj->increase($field, $value);
			}

		} else { // Increase with a query
			if (!is_array($field)) { $field = [$field=>$value]; } // Options
			
			$this->select(); // Prepare select query
					
			$this->cleanQuery();
			
			$queryFields = " SET ";
			
			foreach ($field as $key=>$value) {
				$this->bindCount++;
				$queryFields .= "{$this->slashes[$this->driver][0]}{$key}{$this->slashes[$this->driver][1]}={$this->slashes[$this->driver][0]}{$key}{$this->slashes[$this->driver][1]}+:value{$this->bindCount}, ";
				$this->binds["value{$this->bindCount}"] = $value;
			}
			$queryFields = substr($queryFields, 0, -2);
			
			// Replace SELECT * FROM by UPDATE and query fields
			$this->query = preg_replace(
				"/SELECT \* FROM {$this->slashes[$this->driver][0]}([^{$this->slashes[$this->driver][1]}.]*){$this->slashes[$this->driver][1]}/", 
				"UPDATE {$this->slashes[$this->driver][0]}$1{$this->slashes[$this->driver][1]}".$queryFields, 
				$this->query
			);
			
			$sth = $this->prepare($this->query);
			$sth->execute($this->binds);
			
			$this->resetQuery();
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
	protected function refreshUniqueObject() {
		foreach ($this->uniqueObject as $field=>$value) {
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
		
		if($this->gotResults && $this->isUnique) { // Delete a unique result
			$this->uniqueObject->delete();
		
		} else if ($this->gotResults) { // Delete multiple results
			foreach($this as $obj) {
				$obj->delete();
			}

		} else { // Update with a query
			$this->select(); // Prepare select query
			
			$this->query = str_replace('SELECT * FROM', 'DELETE FROM', $this->query);
			
			$this->cleanQuery();
			
			$sth = $this->prepare($this->query);
			$sth->execute($this->binds);
			
			$this->resetQuery();
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
	public function getAllDie($fields=null, $getOne=null) { $this->die = true; return $this->getAll($fields, $getOne); }### TO REMOVE OF COURSE AND CHANGE FOR APPROPRIATE DEBUG OPTION
	public function getDie($fields=null) { $this->die = true; return $this->get($fields); }### TO REMOVE OF COURSE AND CHANGE FOR APPROPRIATE DEBUG OPTION 
	
	public function get($fields=null) {
		$this->isUnique = true;
		return $this->getAll($fields, true);
	}
	
	public function getAll($fields=null, $getOne=null) {
		if (isset($fields) && !is_array($fields)) $fields = [$fields]; // Options
		
		$this->gotResults = true;
		
		$this->select(); // Prepare select query
		
		if (isset($getOne) && $getOne===true) {
			$this->limit(1);
		} 
		
		$this->cleanQuery();
		
		if (isset($fields)) {
			$queryFields = "";
			foreach($fields as $field) $queryFields .= "{$this->slashes[$this->driver][0]}{$field}{$this->slashes[$this->driver][1]}, "; // Create query fields
			$queryFields = substr($queryFields, 0, -2);
						
			$this->query = str_replace(" * FROM", " {$queryFields} FROM", $this->query); // Put query fields in query
		}
		
/* 		echo $this->query; die(); */
		if (isset($this->die) && $this->die==true) {
			print_r($this->binds);
			echo $this->query; die();
		}

		$sth = $this->prepare($this->query);
		$sth->execute($this->binds);
				
		// If there is only one result, make the fields avalaible directly after the read (without the array)
		if (isset($getOne) && $getOne===true) {
			$result = $sth->fetch(PDO::FETCH_ASSOC);
			
			$this->uniqueObject = new Result($this->connection, $this->tableName, $this->driver, $this->slashes);
			
			if (is_array($result)) {
				foreach($result as $field=>$value) {
					$this->$field = $this[$field] = $this->uniqueObject->$field = $this->uniqueObject[$field] = $value;
				}
			}
		
		// Multiple results
		} else {
			$results = $sth->fetchAll(PDO::FETCH_ASSOC);
			
			if (is_array($results)) {
				foreach ($results as $row) {
					
					$obj = new Result($this->connection, $this->tableName, $this->driver, $this->slashes);
					
					foreach($row as $field=>$value) {
						$obj->$field = $obj[$field] = $value;
					}
					$this->append($obj);
				}
			}
		}
		
		$this->resetQuery();
		
		return $this;		
	}
	
	/**
	 * Clean the query
	 * 
	 * @access private
	 * @return void
	 */
	private function cleanQuery() {
		$this->query = str_replace(
			['  ', '( (', '  ', 'WHERE ( AND', '( AND', '( ', ' )', ' WHERE ()', ' ;'], 
			[' ', '((', ' ', 'WHERE (', '(', '(', ')', '', ';'], 
			$this->query.($this->addParenthesis ? ")":"").";");
		
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
	public function createDie($field, $value=null) { $this->die = true; return $this->create($field, $value); }### TO REMOVE OF COURSE 
	public function create($field, $value=null) {
		if (!is_array($field)) { $field = [$field=>$value]; } // Options
		
		$query = "INSERT INTO {$this->slashes[$this->driver][0]}{$this->tableName}{$this->slashes[$this->driver][1]} (";
		$queryValues = "";
		$binds = [];
		$bindCount = 0;

		foreach ($field as $key=>$value) {
			$bindCount++;
			$query .= "{$this->slashes[$this->driver][0]}{$key}{$this->slashes[$this->driver][1]}, ";
			$queryValues .= ":value{$bindCount}, ";
			$binds["value{$bindCount}"] = $value;
		}
		
		$query = substr($query, 0, -2).") VALUES (".substr($queryValues, 0, -2).");";
			
		if (isset($this->die) && $this->die==true) {
			print_r($binds);
			echo $query; die();
		}
		
		$sth = $this->prepare($query);
		$sth->execute($binds);
		
		$this->resetQuery();
		
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
	public function replaceDie($field, $value=null) { $this->die = true; return $this->replace($field, $value); }### TO REMOVE OF COURSE 
	public function replace($field, $value=null) {
		if (!is_array($field)) { $field = [$field=>$value]; } // Options
		
		$query = "REPLACE INTO {$this->slashes[$this->driver][0]}{$this->tableName}{$this->slashes[$this->driver][1]} (";
		$queryValues = "";
		$binds = [];
		$bindCount = 0;

		foreach ($field as $key=>$value) {
			$bindCount++;
			$query .= "{$this->slashes[$this->driver][0]}{$key}{$this->slashes[$this->driver][1]}, ";
			$queryValues .= ":value{$bindCount}, ";
			$binds["value{$bindCount}"] = $value;
		}
		
		$query = substr($query, 0, -2).") VALUES (".substr($queryValues, 0, -2).");";
			
		if (isset($this->die) && $this->die==true) {
			print_r($binds);
			echo $query; die();
		}
		
		$sth = $this->prepare($query);
		$sth->execute($binds);
		
		$this->resetQuery();
		
		return $this;
	}
	
	/**
	 * Get last insert id
	 * 
	 * @access public
	 * @return int
	 */
	public function getId() {
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
#		$this->resetQuery();
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
	public function find($field, $value=true) {
		if (!is_array($field)) { $field = [$field=>$value]; } // Options
		$this->select(); // Prepare select query
		
		foreach ($field as $key=>$value) {
			if ($value===null) {
				$this->where($key, ($this->notOperator ? ' IS NOT ' : ' IS '), $value);
			} else {
				$this->where($key, ($this->notOperator ? '!=' : '='), $value);
			}
		}
		$this->notOperator = false;
		
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
			$this->where($key, ($this->notOperator ? '<=' : '>'), $value);
		}
		$this->notOperator = false;
		
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
			$this->where($key, ($this->notOperator ? '>=' : '<'), $value);
		}
		$this->notOperator = false;
		
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
			$this->where($key, ($this->notOperator ? ' NOT LIKE ' : ' LIKE '), '%'.$value.'%');
		}
		$this->notOperator = false;
		
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
	public function limit($offset, $rowCount=null) {		
		$this->select(); // Prepare select query
		
		switch ($this->driver) {
			case 'access':
				if (isset($rowCount)) trigger_error("Microsoft Access does not support offset for limit.");
				$this->query = str_replace("SELECT ", "SELECT TOP({$offset}) ", $this->query);
				break;
			default:
				$this->query .= ($this->addParenthesis ? ")" : "")." LIMIT {$offset}".(isset($rowCount) ? ", {$rowCount}" : "");
				$this->addParenthesis = false;
				break;
		}
		return $this;
	}
	
	/**
	 * Group by
	 * 
	 * @param $provider
	 * @var bool
	 * @access public
	 * @return const
	 */
	public function group( $field ) {		
		$this->select(); // Prepare select query
		
		$this->query .= ($this->addParenthesis ? ")" : "")." GROUP BY {$this->slashes[$this->driver][0]}{$field}{$this->slashes[$this->driver][1]}";
		$this->addParenthesis = false;

		return $this;
	}
	
	/**
	 * Order method for queries
	 * 
	 * @param	string|array	the field to order by or an array containing the fields and the orders
	 * @param	string|bool		the order to order the fields by
	 * @return	self
	 */
	public function order( $field, $order = 'asc' ) {
		$this->select(); // Prepare select query
		
		// Process multiple orders at the same time
		if (is_array($field)) {
			foreach($field as $key=>$value) {
				if (is_string($key)) {
					$this->order($key, $value);
				} else {
					$this->order($value);
				}
			}
			
			return $this;
		}
		
		if ($this->orderBy==false) { // First order by
			$this->query .= ($this->addParenthesis ? ")" : "")." ORDER BY ";
			$this->addParenthesis = false;
			$this->orderBy = true;
		} else {
			$this->query .= ", ";
		}
		
		$this->query .= "{$this->slashes[$this->driver][0]}{$field}{$this->slashes[$this->driver][1]} ".(!$order || strtolower($order) === 'desc' ? "DESC " : "");

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
		$this->bindCount++;
		$this->query .= " AND {$this->slashes[$this->driver][0]}{$field}{$this->slashes[$this->driver][1]}{$operator}";
		if ($value===null) {
			$this->query .= "NULL";		
		} else {
			$this->query .= ":value{$this->bindCount}";
			$this->binds[":value{$this->bindCount}"] = $value;
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
			$this->query = "SELECT * FROM {$this->slashes[$this->driver][0]}{$this->tableName}{$this->slashes[$this->driver][1]} WHERE (";
		}
		
		if ($this->orOperator) { // Apply or logic operator
			$this->query .= ") OR (";
			$this->resetOperators();
			
		} elseif ($this->andOperator) { // Apply and logic operator
			$this->query = str_replace("WHERE", "WHERE ( ", $this->query)." )";
			$this->query .= ") AND (";
			$this->resetOperators();
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
	private function resetQuery() {
		$this->query = null;
		$this->binds = [];
		$this->bindCount = 0;
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
			case 'or': $this->orOperator = true; break;
			case 'not': $this->notOperator = true; break;
			case 'and': $this->andOperator = true; break;
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
	private function resetOperators() {
		$this->orOperator = $this->notOperator = $this->andOperator = false;
	}
}
