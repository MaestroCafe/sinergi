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
 * The sync trait facilitate the synchronisation of two database tables.
 *
 * @category	core
 * @package		sinergi
 * @author		Sinergi Team
 * @link		https://github.com/sinergi/sinergi
 */

namespace sinergi\db;

use Query, 
	PersistentVars, 
	stdClass;

trait Sync {
	/**
	 * Interval, in minutes, at which the process is executed. We use 1 minute here because the real interval is decided
	 * in the 'intervals' parameter of the synchronization process. This way, the "changes" and "deletes"
	 * synchronization can have different intervals.
	 * 
	 * @var	int
	 */
	public $interval = 1;

	/**
	 * Variable used to store keys for deletes and data for changes.
	 * 
	 * @var	array
	 * @var	array
	 */
	private $diffKeys = [];
	private $diffData = [];

	/**
	 * Variable used to track the synchronization status.
	 * 
	 * @var	object(stdClass)
	 * @var	object(stdClass)
	 */
	private $syncStatus;
	private $newStatus;

	/**
	 * Store the entries to update to the master keys.
	 * 
	 * @var	array
	 */
	private $masterKeys = [];

	/**
	 * Detemines if changes were syncronised in order to know if we have to re-write changes to the file.
	 * 
	 * @var	bool
	 */
	private $changedData = false;

	/**
	 * Execute the synchronization. Do not use an execute() method in the class because it will overide this one!
	 * 
	 * @return	void
	 */
	public function sync() {
		// Allow unlimited memory to the process
		self::unlimitedMemory();
		
		// Define a unique key for persistent variable
		$key = base64_encode(__CLASS__);

		// Check if process is already running, otherwise mark it as running
		if (PersistentVars::exists($key.".lock")) {
			// Delete file if it is older than 1 hour
			if (PersistentVars::info($key.".lock")['creationTime'] < (time() - (60*60))) {
				// Replace current persitent var by backup
				if (PersistentVars::exists($key.".bak")) {
					PersistentVars::store($key, PersistentVars::fetch($key.".bak"));
				}
				PersistentVars::delete($key.".lock");
			}			
			//return false;
		} else {
			PersistentVars::store($key.".lock");
		}
		
		// Get last synchronization status from data file
		$this->syncStatus = $originalSyncStatus = PersistentVars::fetch($key);
		if (!is_object($this->syncStatus)) { $this->syncStatus = new stdClass(); }

		// If file exists but data is not correct, stop the script
		if (PersistentVars::exists($key)) {
			foreach ($this->tables as $tableKey => $table) {
				if (!isset($this->syncStatus->{$tableKey})) {
					// Send email to admin
					### SEND ALERT SCRIPT TO BE PLACED HERE ###
					return false;
				}
			}
			// Otherwise backup file
			PersistentVars::store($key.'.bak', $this->syncStatus);
		}

		// Prepare new synchronization status that will be written to file
		$this->newStatus = new stdClass();
		$this->newStatus->lastSync = date('Y-m-d H:i:s'); // Used as a reference only

		// Set default options
		foreach ($this->tables as $tableKey => $table) {
			if (!isset($this->syncStatus->{$tableKey}) || !is_object($this->syncStatus->{$tableKey})) {
				$this->syncStatus->{$tableKey} = new stdClass();
			}
			$this->newStatus->{$tableKey} = $this->syncStatus->{$tableKey};
			
			// Last row is used to track if we synced the last row of the table to sync.
			if (!isset($this->syncStatus->{$tableKey}->lastRow)) {
				$this->syncStatus->{$tableKey}->lastRow = false;
			}
			
			// Last sync timestamp is used to track the timestamp of the last row synchronised, use only once you reached the last key
			// to synchronise and then you don't consider the limit anymore. Otherwise, all data with the same timestamp over the limite
			// would never be synchronised.
			if (!isset($this->syncStatus->{$tableKey}->lastSyncTimestamp)) { $this->syncStatus->{$tableKey}->lastSyncTimestamp = '0'; }
			
			// Last sync key is used to track the last key synchronised.
			// Last row is only used for the first synchronization of the tables, in which we use the limit.
			if (!isset($this->syncStatus->{$tableKey}->lastSyncKey)) { $this->syncStatus->{$tableKey}->lastSyncKey = 0; }
			
			// Store the keys for each table, in order to find out which ones were deleted.
			if (!isset($this->syncStatus->{$tableKey}->keys)) { $this->syncStatus->{$tableKey}->keys = []; }
		}
		
		// Get data from tables
		foreach($this->tables as $tableKey => $table) {
			// If table does not use timestamp and as reached the end, reset the lastSyncKey to 0
			if (!isset($table['timestamp']) && $this->syncStatus->{$tableKey}->lastRow) {
				$this->syncStatus->{$tableKey}->lastRow = false;
				$this->syncStatus->{$tableKey}->lastSyncKey = 0;
			} 
			
			$this->getTableData($table, $tableKey);
		}
		
		// Resolve conflicts
		$this->resolveConflicts($table, $tableKey);
		
		// Synchronise tables
		foreach($this->tables as $tableKey => $table) {
			$this->syncTableData($table, $tableKey);
		}

		// Update keys on tables that have been syncrhonised with master table
		$this->udpateMasterKeys();

		if ($this->changedData || (empty($originalSyncStatus))) {
			PersistentVars::store($key, $this->newStatus);
		}
			
		PersistentVars::delete($key.".lock");
	}
	
	/**
	 * Allow syncrhonisation to run for long periods of time.
	 * 
	 * @return	void
	 */
	private function unlimitedMemory() {
		set_time_limit(0);
		ignore_user_abort(true);
		ini_set('memory_limit', '-1');	
		ini_set('max_input_time', '-1');		
	}
	
	/**
	 * Get data from tables.
	 * 
	 * @param	string
	 * @param	string
	 * @return	void
	 */
	private function getTableData( $table, $tableKey ) {
		if (isset($table['intervals']) || isset($table['limits'])) {
			// Get deletes first
			if (isset($table['intervals']['deletes']) && (floor(time()/60) % $table['intervals']['deletes']) == 0) {
				$this->keys[$tableKey] = $this->getMasterKeys($table);				
				$this->diffKeys[$tableKey] = array_diff($this->syncStatus->{$tableKey}->keys, $this->keys[$tableKey]);
			}

			// Get changes 
			if (isset($table['intervals']['changes']) && (floor(time()/60) % $table['intervals']['changes']) == 0) {
				$this->diffData[$tableKey] = $this->getDataChanges($table, $tableKey, $this->syncStatus->{$tableKey});
			}
		}
	}

	/**
	 * Synchronise tables.
	 * 
	 * @param	string
	 * @param	string
	 * @return	void
	 */
	private function syncTableData( $table, $tableKey ) {
		// Check if table is accepting synchronizations
		if ((isset($table['sync']['changes']) && $table['sync']['changes']==true) || (isset($table['sync']['deletes']) && $table['sync']['deletes']==true)) {
			// Sync deletes
			if (isset($table['sync']['deletes']) && $table['sync']['deletes']==true) {
				foreach($this->diffKeys as $diffTableKey => $diffKeys) {
					if ($diffTableKey!=$tableKey) {
						$this->deleteData($table, $tableKey, $diffKeys, $diffTableKey);
					}
				}
			}
			
			// Sync changes
			if (isset($table['sync']['changes']) && $table['sync']['changes']==true) {
				foreach($this->diffData as $diffTableKey => $diffData) {
					if ($diffTableKey!=$tableKey) { // Only sync other tables
						$this->syncData($table, $tableKey, $diffData, $diffTableKey);
					}
				}
			}
		}
	}

	/**
	 * Create query manager and add rules.
	 * 
	 * @param	string
	 * @return	object(Query)
	 */
	private function createQuery( $table ) {
		// Create db
		$query = new Query($table['database'], $table['table']);

		// Apply rules
		if (isset($table['rules']) && method_exists($this, $table['callbacks']['rules'])) {
			$query = call_user_func([$this, $table['callbacks']['rules']], $query);
		}

		return $query;
	}

	/**
	 * Get all Primary Keys from table.
	 * 
	 * @param $table
	 * @var array 
	 * @return array
	 */
	private function getMasterKeys( $table ) {
		if (isset($table['master']) && $table['master']) {
			$masterKey = $table['primaryKey'];
		} else {
			$masterKey = $table['masterKey'];
		}
		
		// Get all keys
		$keys = $this->createQuery($table)
						->getAll($masterKey);

		// Get only keys
		$return = [];
		foreach ($keys as $key) {
			$return[] = $key->{$masterKey};
		}
		return $return;
	}

	/**
	 * Get data to synchronise from table.
	 * 
	 * @param $table
	 * @var array 
	 * @return array
	 */
	private function getDataChanges( $table, $tableKey, $syncStatus ) {
		// Get last sync timestamp and parse it if there is a timestamp parser
		if (isset($table['callbacks']['timestamp']) && method_exists($this, $table['callbacks']['timestamp'])) {
			$lastSyncDate = call_user_func([$this, $table['callbacks']['timestamp']], $syncStatus->lastSyncTimestamp);
		} else {
			$lastSyncDate = date('Y-m-d H:i:s', (!is_integer($syncStatus->lastSyncTimestamp) ? strtotime($syncStatus->lastSyncTimestamp) : $syncStatus->lastSyncTimestamp));
		}

		// Get data above last primary key if we are synchronising from begining or from last timestamp otherwise
		if (!$syncStatus->lastRow || !isset($table['timestamp'])) {
			$data = $this->createQuery($table)
							->above($table['primaryKey'], $syncStatus->lastSyncKey);
		} else {
			$data = $this->createQuery($table)
							->above($table['timestamp'], $lastSyncDate);
		}

		// Order the data by key if we are synchronising from the begining or by timestamp then key otherwise
		if (!$syncStatus->lastRow || !isset($table['timestamp'])) {
			$data->order($table['primaryKey']);
		} else {
			$data->order($table['timestamp'])->order($table['primaryKey']);
		}

		// Limit if there is a limit and we are synchronising from the beggining of the database
		if (!$syncStatus->lastRow) {
			if (isset($table['limit'])) {
				// Get greatest limit
				$limit = $table['limit'];
				$data->limit($limit);
			}
		}

		// Get fields to sync, add primaryKey and masterKey if table is not master table
		$fields = isset($table['fields']) ? array_merge($table['fields'], [$table['primaryKey']]) : null;
		if (isset($table['fields']) && isset($table['masterKey'])) { $fields = array_merge($fields, [$table['masterKey']]); }
		$data->getAll($fields);

		// Put data in an array
		$return = [];
		$count = 0;
		foreach ($data as $value) {
			$return[] = (array) $value;
			
			// Store this data's key if this is the master table
			if (isset($table['master']) && $table['master'] && isset($value[$table['primaryKey']]) && $value[$table['primaryKey']]!=null && !in_array($value[$table['primaryKey']], $this->newStatus->{$tableKey}->keys)) {
				$this->newStatus->{$tableKey}->keys[] = $value[$table['primaryKey']];
			} else if ((!isset($table['master']) || !$table['master']) && isset($value[$table['masterKey']]) && $value[$table['masterKey']]!=null && !in_array($value[$table['masterKey']], $this->newStatus->{$tableKey}->keys)) {
				$this->newStatus->{$tableKey}->keys[] = $value[$table['masterKey']];
			}

			// Get last sync timestamp
			if (isset($table['timestamp'])) {
				$syncTimestamp = $value[$table['timestamp']];
			}
			
			if (isset($table['callbacks']['timestamp']) && method_exists($this, $table['callbacks']['timestamp'])) {
				// Get last sync timestamp and parse it if there is a timestamp parser
				$syncTimestamp = call_user_func([$this, $table['callbacks']['timestamp']], $syncTimestamp);
			
				// Store this data's timestamp if it is bigger then the last one stored
				if($syncTimestamp > $this->newStatus->{$tableKey}->lastSyncTimestamp) {
				 	$this->newStatus->{$tableKey}->lastSyncTimestamp = $syncTimestamp;
				}
			}
			
			// Get last primary key synchronised
			if ($value[$table['primaryKey']] > $this->newStatus->{$tableKey}->lastSyncKey) {
				$this->newStatus->{$tableKey}->lastSyncKey = $value[$table['primaryKey']];
			}

			$count++;
		}

		if (!isset($limit) || (!$syncStatus->lastRow && $count!=$limit)) {
			$this->newStatus->{$tableKey}->lastRow = true;
		}
		
		return $return;
	}

	/**
	 * Resolve duplicates changes by using the most recent one
	 * 
	 * @param $provider
	 * @var bool 
	 * @return const
	 */
	private function resolveConflicts() {
		foreach($this->diffData as $tableKey=>$diffData) { // Loop through all diff data
			if (isset($this->tables[$tableKey]['timestamp'])) { // Can only resolve conflicts when using a timestamp reference				
				$masterKey = (isset($this->tables[$tableKey]['master']) && $this->tables[$tableKey]['master'] ? $this->tables[$tableKey]['primaryKey'] : $this->tables[$tableKey]['masterKey']); // Get table master key
				$timestamp = $this->tables[$tableKey]['timestamp']; // Get table timestamp key
	
				foreach($diffData as $dataKey => $data) { // Loop through table's diff data
					if ($match = $this->diffDataSearch($data[$masterKey], $this->diffData, $tableKey)) { // Check for a conflict
						$conflictData = $this->diffData[$match['tableKey']][$match['dataKey']];
	
						if($conflictData[$match['timestamp']]>=$data[$timestamp]) { // Compare conflict with data
							unset($this->diffData[$tableKey][$dataKey]); // Unset data if conflict is more recent
						}
					}
				}
			}
		}
	}

	/**
	 * Search diff data for conflicts
	 * 
	 * @param $provider
	 * @var bool 
	 * @return const
	 */
	private function diffDataSearch( $needle, $diffData, $needleTableKey ) {
		foreach($diffData as $tableKey => $tableData) { // Loop through all diff data
			if ($tableKey!=$needleTableKey) { // Except for the needle's table data
				$masterKey = (isset($this->tables[$tableKey]['master']) && $this->tables[$tableKey]['master'] ? $this->tables[$tableKey]['primaryKey'] : $this->tables[$tableKey]['masterKey']); // Get table master key
				$timestamp = $this->tables[$tableKey]['timestamp']; // Get table timestamp key

				foreach($tableData as $dataKey => $data) { // Loop through table's diff data
					if($data[$masterKey]==$needle) { // Chck for a match
						return ['tableKey'=>$tableKey, 'dataKey'=>$dataKey, 'masterKey'=>$masterKey, 'timestamp'=>$timestamp]; // Return the match's keys
					}
				}

			}
		}
		return false;
	}

	/**
	 * Synchronise changes and addition data to table (gathered using a timestamp)
	 * 
	 * @param $table, $diffData, $diffTableKey
	 * @var array, array, int 
	 * @return	void
	 */
	private function syncData( $table, $tableKey, $diffData, $diffTableKey ) {
		foreach($diffData as $diffRowKey => $diffRow) {
			$this->changedData = true;
			
			// Get last sync timestamp
			if (isset($this->tables[$diffTableKey]['timestamp'])) {
				$syncTimestamp = $diffRow[$this->tables[$diffTableKey]['timestamp']];
			} else {
				$syncTimestamp = 0;
			}

			// Get last sync timestamp and parse it if there is a timestamp parser
			if (isset($this->tables[$diffTableKey]['callbacks']['timestamp']) && method_exists($this, $this->tables[$diffTableKey]['callbacks']['timestamp'])) {
				$syncTimestamp = call_user_func([$this, $this->tables[$diffTableKey]['callbacks']['timestamp']], $syncTimestamp);
			}

			// Parse data if data parser is configured
			if (isset($table['callbacks']['data']) && method_exists($this, $table['callbacks']['data'])) {
				$data = call_user_func([$this, $table['callbacks']['data']], $diffRow);

			// Otherwise just assign data to the key
			} else {
				foreach($table['fields'] as $field) {
					$data[$field] = $diffRow[$field];
				}
			}

			// If data is not false, sync it
			if($data!==false) {
				// If table is master and diff table has a masterKey, this table's primaryKey is the diff table's masterKey
				if (isset($table['master']) && $table['master'] && $diffRow[$this->tables[$diffTableKey]['masterKey']]!=null) {
					$data[$table['primaryKey']] = $diffRow[$this->tables[$diffTableKey]['masterKey']];

				// If this row has already been synchronised with master table and got a key, use key as masterKey
				} else if (isset($this->masterKeys[$diffTableKey][$diffRowKey])) {
					$data[$table['masterKey']] = $this->masterKeys[$diffTableKey][$diffRowKey]['masterKey'];

				// If diff table is master, diff data primary key becomes table master key
				} else if(isset($this->tables[$diffTableKey]['master']) && $this->tables[$diffTableKey]['master']) {
					$data[$table['masterKey']] = $diffRow[$this->tables[$diffTableKey]['primaryKey']];

				// If diff table is not master, diff data master key becomes table master key if diff data master key exists
				} else if(!isset($this->tables[$diffTableKey]['master']) && $diffRow[$this->tables[$diffTableKey]['masterKey']]!=null) {
					$data[$table['masterKey']] = $diffRow[$this->tables[$diffTableKey]['masterKey']];
				}

				// Create db
				$query = $this->createQuery($table);

				// Get primary key for master table and master key for slaves
				if(isset($table['master']) && $table['master']) {
					$primaryKey = $table['primaryKey'];
				} else {
					$primaryKey = $table['masterKey'];
				}

				// Update if exists, create otherwise, we do not use replace here because it would erase data that is not synchronized
				if (isset($data[$primaryKey]) && count($query->find($primaryKey, $data[$primaryKey])->get())) {
					$syncKey = $query->{$table['primaryKey']};
					$query->update($data);
				} else {
					$query->create($data);
					$syncKey = $query->getId();
				}

				// If table master, store masterKey for update later
				if(isset($table['master']) && $table['master'] && $diffRow[$this->tables[$diffTableKey]['masterKey']]==null) {
					$masterKey = $syncKey;
					$this->masterKeys[$diffTableKey][$diffRowKey] = ['primaryKey'=>$diffRow[$this->tables[$diffTableKey]['primaryKey']], 'masterKey'=>$masterKey];
				}

				// Get last primary key synchronised and store it in newStatus if it is bigger then the key already stored
				if ($syncKey>$this->newStatus->{$tableKey}->lastSyncKey) {
					$this->newStatus->{$tableKey}->lastSyncKey = $syncKey;
				}

				// Store this data's timestamp if it is bigger then the last one stored
				if($syncTimestamp>$this->newStatus->{$tableKey}->lastSyncTimestamp) {
				 	$this->newStatus->{$tableKey}->lastSyncTimestamp = $syncTimestamp;
				}
												
				// Store this data's key
				if (isset($table['master']) && $table['master'] && isset($syncKey) && $syncKey!=null && !in_array($syncKey, $this->newStatus->{$tableKey}->keys)) {
					$this->newStatus->{$tableKey}->keys[] = $syncKey;
				} else if((!isset($table['master']) || !$table['master']) && isset($data[$table['masterKey']]) && $data[$table['masterKey']]!=null && !in_array($data[$table['masterKey']], $this->newStatus->{$tableKey}->keys)) {
					$this->newStatus->{$tableKey}->keys[] = $data[$table['masterKey']];
				}
				
				// Callback
				if (!empty($syncKey) && $syncKey!=0 && isset($table['callbacks']['insert']) && method_exists($this, $table['callbacks']['insert'])) {					
					call_user_func([$this, $table['callbacks']['insert']], $syncKey);
				}
			}
		}
	}

	/**
	 * Update the master key of the tables that have been synchronised with the master table
	 * 
	 * @param $table, $diffData, $diffTableKey
	 * @var array, array, int 
	 * @return	void
	 */
	private function udpateMasterKeys() {
		foreach ($this->masterKeys as $tableKey=>$rows) {
			$this->changedData = true;
			
			foreach ($rows as $row) {
				$table = $this->tables[$tableKey];
				$this->createQuery($table)->find($table['primaryKey'], $row['primaryKey'])->update($table['masterKey'], $row['masterKey']);
				
				// Store this data's key 
				if (!in_array($row['masterKey'], $this->newStatus->{$tableKey}->keys)) {
					$this->newStatus->{$tableKey}->keys[] = $row['masterKey'];
				}
			}
		}
	}

	/**
	 * Delete data that comes from the diff between table's key and file's key
	 * 
	 * @param $table, $diffData
	 * @var array, array 
	 * @return	void
	 */
	private function deleteData( $table, $tableKey, $diffKeys, $diffTableKey ) {
		foreach ($diffKeys as $key) {
			$this->changedData = true;
			
			if (isset($table['master']) && $table['master']) {
				$primaryKey = $table['primaryKey'];
			} else {
				$primaryKey = $table['masterKey'];
			}
						
			// Delete from table's status
			$newKeys = [];
			foreach ($this->newStatus->{$tableKey}->keys as $newKey) {
				if($newKey!=$key) {
					$newKeys[] = $newKey;
				}
			}
			$this->newStatus->{$tableKey}->keys = $newKeys;
			
			// Delete from diff table's status
			$newKeys = [];
			foreach ($this->newStatus->{$diffTableKey}->keys as $newKey) {
				if($newKey!=$key) {
					$newKeys[] = $newKey;
				}
			}
			$this->newStatus->{$diffTableKey}->keys = $newKeys;

			// Delete entry from table
			$entry = $this->createQuery($table)->find($primaryKey, $key)->delete();
				
			// Callback
			if (!empty($key) && $key!=0 && isset($table['callbacks']['delete']) && method_exists($this, $table['callbacks']['delete'])) {					
			    call_user_func([$this, $table['callbacks']['delete']], $key);
			}
		}
	}
}