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

use PDO, ArrayObject;

class Row extends ArrayObject {
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
		if (is_array($new_values)) {
			foreach($new_values as $key=>$value) {
				$this->$key = $value;
				$this[$key] = $value;
			}
		}
	}
}