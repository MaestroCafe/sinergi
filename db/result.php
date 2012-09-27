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
 * The database results manager.
 *
 * @category	core
 * @package		sinergi
 * @author		Sinergi Team
 * @link		https://github.com/sinergi/sinergi
 */

namespace sinergi\db;

use PDO, 
	ArrayObject;

class Result extends ArrayObject {
	/**
	 * Connection, table name, db type and db's slashes
	 * 
	 * @var	mixed
	 */
	protected $connection, $tableName, $dbType, $slashes;
	
	/**
	 * Create a Result
	 * 
	 * @return	void
	 */
	public function __construct($connection, $tableName, $dbType, $slashes) {
		$this->connection = $connection;
		$this->tableName = $tableName;
		$this->dbType = $dbType;
		$this->slashes = $slashes;
	}
	
	/**
	 * Append a value to this object
	 * 
	 * @param	array
	 * @return	self
	 */
	public function append( $data ) {
		if (!is_array($data)) trigger_error("Variable passed to append() is not an array", E_USER_WARNING);
		$this->parseAppend($data);
		return $this;
	}
	
	/**
	 * Parse data recursively to convert an array to an ArrayObject containing both the key and object variable
	 * 
	 * @param	array
	 * @param	bool
	 * @return	mixed
	 */
	private function parseAppend( $data, $return = false ) {
		if ($return) $obj = new ArrayObject();
		else $obj = $this;
		
		foreach($data as $key => $value) {
			if (is_array($value)) {
				$obj->{$key} = $obj[$key] = $this->parseAppend($value, true);
			} else {
				$obj->{$key} = $obj[$key] = $value;
			}
		}
		
		if ($return) return $obj;
	}
	
	/**
	 * Update a result
	 * 
	 * @param	bool
	 * @return	self
	 */
	public function update($field, $value=null) {
		if (!is_array($field)) { $field = [$field=>$value]; } // Options
		
		$bindCount = 0;
				
		// Build the "set" part of the query
		$querySet = " SET ";
		
		foreach ($field as $key=>$value) {
			$bindCount++;
			$querySet .= "{$this->slashes[$this->dbType][0]}{$key}{$this->slashes[$this->dbType][1]}=:value{$bindCount}, ";
			$binds["value{$bindCount}"] = $value;
			// Store new values to change them in the object after the update
			$newValues[$key] = $value;
		}
		$querySet = substr($querySet, 0, -2);
		
		$this->query("UPDATE", $binds, $bindCount, $querySet, $newValues);
		
		return $this;
	}
	
	/**
	 * Decrease a value.
	 * 
	 * @param	string
	 * @param	int
	 * @return	self
	 */
	public function decrease($field, $value=1) {
		return $this->increase($field, -$value);
	}

	/**
	 * Increase a value.
	 * 
	 * @param	string
	 * @param	int
	 * @return	self
	 */
	public function increase($field, $value=1) {
		if (!is_array($field)) { $field = [$field=>$value]; } // Options
		
		$bindCount = 0;
				
		// Build the "set" part of the query
		$querySet = " SET ";
		
		foreach ($field as $key=>$value) {
			$bindCount++;
			$querySet .= "{$this->slashes[$this->dbType][0]}{$key}{$this->slashes[$this->dbType][1]}={$this->slashes[$this->dbType][0]}{$key}{$this->slashes[$this->dbType][1]}+:value{$bindCount}, ";
			$binds["value{$bindCount}"] = $value;
			// Store new values to change them in the object after the update
			$newValues[$key] = $this->$key + $value;
		}
		$querySet = substr($querySet, 0, -2);
		
		$this->query("UPDATE", $binds, $bindCount, $querySet, $newValues);
		
		return $this;
	}
	
	/**
	 * Delete a result.
	 * 
	 * @return	self
	 */
	public function delete() {
		$this->query("DELETE FROM");
		
		return $this;
	}
	
	/**
	 * The query function serves the update and delete fuctions. Because both functions are almost identical.
	 * 
	 * @param	string
	 * @param	array
	 * @param	int
	 * @param	string
	 * @param	array
	 * @return	void
	 */
	protected function query($query, $binds = [], $bindCount = 0, $querySet="", $newValues=null) {
		$query = strtoupper($query);
		
		// Build the "where" part of the query
		$queryWhere = " WHERE ";
		
		foreach($this as $key=>$value) {
			$bindCount++;
			if (!empty($value)) {
				$queryWhere .= "{$this->slashes[$this->dbType][0]}{$key}{$this->slashes[$this->dbType][1]}=:value{$bindCount} AND ";
				$binds["value{$bindCount}"] = $value;
			}
		}
		$queryWhere = substr($queryWhere, 0, -5);
				
		// Build query
		$query .= " {$this->slashes[$this->dbType][0]}{$this->tableName}{$this->slashes[$this->dbType][1]}{$querySet}{$queryWhere};";
				
		$sth = $this->connection->prepare($query);
		$sth->execute($binds);
		
		// Change values of current object to updated object
		if (is_array($newValues)) {
			foreach($newValues as $key=>$value) {
				$this->$key = $value;
				$this[$key] = $value;
			}
		}
	}
}