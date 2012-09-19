<?php

namespace sinergi\db;

use Sinergi, Query, File, stdClass, ArrayObject;

trait Sync {
	/**
	 * Interval, in minutes, at which the process is executed. We use 1 minute here because the real interval is decided
	 * in the 'intervals' parameter of the synchronization process. This way, the "changes" and "deletes"
	 * synchronization can have different intervals.
	 */
	public $interval = 1;

	/**
	 * Variable used to store keys for deletes and data for changes.
	 */
	protected $diff_keys = [];
	protected $diff_data = [];

	/**
	 * Variable used to write and read synchronization status from the file defined by $sync_file.
	 */
	protected $sync_status;
	protected $new_status;

	/**
	 * Store the entries to update to the master keys.
	 */
	protected $master_keys = [];

	/**
	 * Detemines if changes were syncronised in order to know if we have to re-write changes to the file.
	 */
	protected $changed_data = false;

	/**
	 * Execute the synchronization. Do not use an execute() method in the class because it will overide this one!
	 *
	 * @access public
	 * @return void
	 */
	public function log($message) { /*(new File("/data/logs/".basename(str_replace('.json', '.log', $this->sync_file))))->append(($message=='' ? '' : date('Y-m-d H:i:s')).' '.$message.PHP_EOL);*/ }
	public function execute() {
		// Allow unlimited memory to the process
		Sinergi::unlimited_memory();
		// Check if process is already running, otherwise mark it as running
		if (file_exists(DOCUMENT_ROOT.$this->sync_file.'.running')) {
			if(date('Y-m-d H:i:s', (new File(DOCUMENT_ROOT.$this->sync_file.'.running'))->get_creation_date()) < date('Y-m-d H:i:s', (time() - (60*60)))) { // Delete file if it is older than 1 hour
				(new File(DOCUMENT_ROOT.$this->sync_file.'.running'))->delete();
			}
			return false;
		} else {
			$running = new File(DOCUMENT_ROOT.$this->sync_file.'.running');
		}
		
		// Get last synchronization status from data file
		$this->log('Initialize');
		$file_exists = file_exists(DOCUMENT_ROOT.$this->sync_file);
		$sync_status_file = (new File($this->sync_file))->read();

		$this->log('Read file');
		if (trim((string) $sync_status_file) !== "") { $this->sync_status = json_decode(trim((string) $sync_status_file)); }
		if (!is_object($this->sync_status)) { $this->sync_status = new stdClass(); }
		$this->log('Parsed file');

		// If file exists but data is not correct, stop the script
		if ($file_exists) {
			foreach ($this->tables as $table_key=>$table) {
				if (!isset($this->sync_status->$table_key)) {
					
					// Send email to admin
					$this->compose('Maestro Technologies', 'information@maestro.ca', 'admin@tangente.co', 'admin@tangente.co', 'emails/alerts/sync_down.html', [str_replace(['process\\', '\\'], ['', '/'], get_class($this))]);
					
					return false;
				}
			}
			// Otherwise backup file
			(new File($this->sync_file))->duplicate($this->sync_file.'.bak');
		}

		$this->log('Backed up file');
		// Prepare new synchronization status that will be written to file
		$this->new_status = new stdClass();
		$this->new_status->last_sync = date('Y-m-d H:i:s'); // Used as a reference only

		/**
		 * Set default options
		 */
		foreach ($this->tables as $table_key=>$table) {
			if (!isset($this->sync_status->$table_key) || !is_object($this->sync_status->$table_key)) {
				$this->sync_status->$table_key = new stdClass();
			}
			$this->new_status->$table_key = $this->sync_status->$table_key;
			/**
			 * Last row is used to track if we synced the last row of the table to sync.
			 */
			if (!isset($this->sync_status->$table_key->last_row)) {$this->sync_status->$table_key->last_row = false; }
			/**
			 * Last sync timestamp is used to track the timestamp of the last row synchronised, use only once you reached the last key
			 * to synchronise and then you don't consider the limit anymore. Otherwise, all data with the same timestamp over the limite
			 * would never be synchronised.
			 */
			if (!isset($this->sync_status->$table_key->last_sync_timestamp)) { $this->sync_status->$table_key->last_sync_timestamp = '0'; }
			/**
			 * Last sync key is used to track the last key synchronised.
			 * Last row is only used for the first synchronization of the tables, in which we use the limit.
			 */
			if (!isset($this->sync_status->$table_key->last_sync_key)) { $this->sync_status->$table_key->last_sync_key = 0; }
			/**
			 * Store the keys for each table, in order to find out which ones were deleted.
			 */
			if (!isset($this->sync_status->$table_key->keys)) { $this->sync_status->$table_key->keys = []; }
		}

		$this->log('Initialized default options');
		// Get data from tables
		foreach($this->tables as $table_key=>$table) {
			// If table does not use timestamp and as reached the end, reset the last_key to 0
			if (!isset($table['timestamp']) && $this->sync_status->$table_key->last_row) {
				$this->sync_status->$table_key->last_row = false;
				$this->sync_status->$table_key->last_sync_key = 0;
			} 
			
			$this->get_table_data($table, $table_key);
		}
		
		$this->log('Got data');
		// Resolve conflicts
		$this->resolve_conflicts($table, $table_key);
		
		$this->log('Resolved conflicts');
		// Synchronise tables
		foreach($this->tables as $table_key=>$table) {
			$this->sync_table_data($table, $table_key);
		}

		$this->log('Syncrhonized tables');
		// Update keys on tables that have been syncrhonised with master table
		$this->udpate_master_keys();

		$this->log('Updated master keys');
		
		$this->log('');
		if ($this->changed_data || (trim((string) $sync_status_file) == "")) {
			$sync_status_file->write(json_encode($this->new_status));
		}
			
		$running->delete();
	}

	/**
	 * Get data from tables.
	 *
	 * @access protected
	 * @return void
	 */
	protected function get_table_data( $table, $table_key ) {
		if (isset($table['intervals']) || isset($table['limits'])) {
			// Get deletes first
			if (isset($table['intervals']['deletes']) && (floor(time()/60) % $table['intervals']['deletes']) == 0) {
				$this->log('	Get deletes keys');
				$this->keys[$table_key] = $this->get_master_keys($table);				
				$this->log('	Get diff in keys');
				$this->diff_keys[$table_key] = array_diff($this->sync_status->$table_key->keys, $this->keys[$table_key]);
			}

			// Get changes 
			if (isset($table['intervals']['changes']) && (floor(time()/60) % $table['intervals']['changes']) == 0) {
				$this->diff_data[$table_key] = $this->get_data_changes($table, $table_key, $this->sync_status->$table_key);
			}
		}
		return false;
	}

	/**
	 * Synchronise tables.
	 *
	 * @param $table, $key
	 * @var array, int
	 * @access protected
	 * @return void
	 */
	protected function sync_table_data( $table, $table_key ) {
		// Check if table is accepting synchronizations
		if ((isset($table['sync']['changes']) && $table['sync']['changes']==true) || (isset($table['sync']['deletes']) && $table['sync']['deletes']==true)) {
			// Sync deletes
			if (isset($table['sync']['deletes']) && $table['sync']['deletes']==true) {
				foreach($this->diff_keys as $diff_table_key=>$diff_keys) {
					if ($diff_table_key!=$table_key) {
						$this->delete_data($table, $table_key, $diff_keys, $diff_table_key);
					}
				}
			}
			
			// Sync changes
			if (isset($table['sync']['changes']) && $table['sync']['changes']==true) {
				foreach($this->diff_data as $diff_table_key=>$diff_data) {
					if ($diff_table_key!=$table_key) { // Only sync other tables
						$this->sync_data($table, $table_key, $diff_data, $diff_table_key);
					}
				}
			}
		}
	}

	/**
	 * Create db manager and add rules.
	 *
	 * @param $table
	 * @var array
	 * @access protected
	 * @return object
	 */
	protected function create_db( $table ) {
		// Create db
		$db = new Query($table['database'], $table['table']);

		// Apply rules
		if (isset($table['rules']) && method_exists($this, $table['rules'])) {
			$db = call_user_func([$this, $table['rules']], $db);
		}

		return $db;
	}

	/**
	 * Get all Primary Keys from table.
	 *
	 * @param $table
	 * @var array
	 * @access protected
	 * @return array
	 */
	protected function get_master_keys( $table ) {
		if (isset($table['master']) && $table['master']) {
			$master_key = $table['primary_key'];
		} else {
			$master_key = $table['master_key'];
		}
		
		// Get all keys
		$keys = $this->create_db($table)
						->get_all($master_key);

		// Get only keys
		$return = [];
		foreach ($keys as $key) {
			$return[] = $key->$master_key;
		}
		return $return;
	}

	/**
	 * Get data to synchronise from table.
	 *
	 * @param $table
	 * @var array
	 * @access protected
	 * @return array
	 */
	protected function get_data_changes( $table, $table_key, $sync_status ) {
		// Get last sync timestamp and parse it if there is a timestamp parser
		if (isset($table['timestamp_parser']) && method_exists($this, $table['timestamp_parser'])) {
			$last_sync_date = call_user_func([$this, $table['timestamp_parser']], $sync_status->last_sync_timestamp);
		} else {
			$last_sync_date = date('Y-m-d H:i:s', $sync_status->last_sync_timestamp);
		}

		// Get data above last primary key if we are synchronising from begining or from last timestamp otherwise
		if (!$sync_status->last_row || !isset($table['timestamp'])) {
			$data = $this->create_db($table)
							->above($table['primary_key'], $sync_status->last_sync_key);
		} else {
			$data = $this->create_db($table)
							->above($table['timestamp'], $last_sync_date);
		}

		// Order the data by key if we are synchronising from the begining or by timestamp then key otherwise
		if (!$sync_status->last_row || !isset($table['timestamp'])) {
			$data->order($table['primary_key']);
		} else {
			$data->order($table['timestamp'])->order($table['primary_key']);
		}

		// Limit if there is a limit and we are synchronising from the beggining of the database
		if (!$sync_status->last_row) {
			if (isset($table['limit'])) {
				// Get greatest limit
				$limit = $table['limit'];
				$data->limit($limit);
			}
		}

		// Get fields to sync, add primary_key and master_key if table is not master table
		$fields = isset($table['fields']) ? array_merge($table['fields'], [$table['primary_key']]) : null;
		if (isset($table['fields']) && isset($table['master_key'])) { $fields = array_merge($fields, [$table['master_key']]); }
		$data->get_all($fields);

		// Put data in an array
		$return = [];
		$count = 0;
		foreach ($data as $value) {
			$return[] = (array) $value;
			
			// Store this data's key if this is the master table
			if (isset($table['master']) && $table['master'] && isset($value[$table['primary_key']]) && $value[$table['primary_key']]!=null && !in_array($value[$table['primary_key']], $this->new_status->$table_key->keys)) {
				$this->new_status->$table_key->keys[] = $value[$table['primary_key']];
			} else if ((!isset($table['master']) || !$table['master']) && isset($value[$table['master_key']]) && $value[$table['master_key']]!=null && !in_array($value[$table['master_key']], $this->new_status->$table_key->keys)) {
				$this->new_status->$table_key->keys[] = $value[$table['master_key']];
			}

			// Get last sync timestamp
			if (isset($table['timestamp'])) {
				$sync_timestamp = $value[$table['timestamp']];
			}

			if (isset($table['timestamp_parser']) && method_exists($this, $table['timestamp_parser'])) {
				// Get last sync timestamp and parse it if there is a timestamp parser
				$sync_timestamp = call_user_func([$this, $table['timestamp_parser']], $sync_timestamp);
			
				// Store this data's timestamp if it is bigger then the last one stored
				if($sync_timestamp > $this->new_status->$table_key->last_sync_timestamp) {
				 	$this->new_status->$table_key->last_sync_timestamp = $sync_timestamp;
				}
			}
			
			// Get last primary key synchronised
			if ($value[$table['primary_key']] > $this->new_status->$table_key->last_sync_key) {
				$this->new_status->$table_key->last_sync_key = $value[$table['primary_key']];
			}

			$count++;
		}

		if (!isset($limit) || (!$sync_status->last_row && $count!=$limit)) {
			$this->new_status->$table_key->last_row = true;
		}
		
		return $return;
	}

	/**
	 * Resolve duplicates changes by using the most recent one
	 *
	 * @param $provider
	 * @var bool
	 * @access private
	 * @return const
	 */
	protected function resolve_conflicts() {
		foreach($this->diff_data as $table_key=>$diff_data) { // Loop through all diff data
			if (isset($this->tables[$table_key]['timestamp'])) { // Can only resolve conflicts when using a timestamp reference
				$this->log('	Starts to resolve '.$table_key.", there is ".count($diff_data)." fields to check");
				
				$master_key = (isset($this->tables[$table_key]['master']) && $this->tables[$table_key]['master'] ? $this->tables[$table_key]['primary_key'] : $this->tables[$table_key]['master_key']); // Get table master key
				$timestamp = $this->tables[$table_key]['timestamp']; // Get table timestamp key
	
				foreach($diff_data as $data_key=>$data) { // Loop through table's diff data
					if ($match = $this->diff_data_search($data[$master_key], $this->diff_data, $table_key)) { // Check for a conflict
						$conflict_data = $this->diff_data[$match['table_key']][$match['data_key']];
	
						if($conflict_data[$match['timestamp']]>=$data[$timestamp]) { // Compare conflict with data
							unset($this->diff_data[$table_key][$data_key]); // Unset data if conflict is more recent
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
	 * @access private
	 * @return const
	 */
	protected function diff_data_search( $needle, $diff_data, $needle_table_key ) {
		foreach($diff_data as $table_key=>$table_data) { // Loop through all diff data
			if ($table_key!=$needle_table_key) { // Except for the needle's table data
				$master_key = (isset($this->tables[$table_key]['master']) && $this->tables[$table_key]['master'] ? $this->tables[$table_key]['primary_key'] : $this->tables[$table_key]['master_key']); // Get table master key
				$timestamp = $this->tables[$table_key]['timestamp']; // Get table timestamp key

				foreach($table_data as $data_key=>$data) { // Loop through table's diff data
					if($data[$master_key]==$needle) { // Chck for a match
						return ['table_key'=>$table_key, 'data_key'=>$data_key, 'master_key'=>$master_key, 'timestamp'=>$timestamp]; // Return the match's keys
					}
				}

			}
		}
		return false;
	}

	/**
	 * Synchronise changes and addition data to table (gathered using a timestamp)
	 *
	 * @param $table, $diff_data, $diff_table_key
	 * @var array, array, int
	 * @access protected
	 * @return void
	 */
	protected function sync_data( $table, $table_key, $diff_data, $diff_table_key ) {
		foreach($diff_data as $diff_row_key=>$diff_row) {
			$this->changed_data = true;
			
			// Get last sync timestamp
			if (isset($this->tables[$diff_table_key]['timestamp'])) {
				$sync_timestamp = $diff_row[$this->tables[$diff_table_key]['timestamp']];
			} else {
				$sync_timestamp = 0;
			}

			// Get last sync timestamp and parse it if there is a timestamp parser
			if (isset($this->tables[$diff_table_key]['timestamp_parser']) && method_exists($this, $this->tables[$diff_table_key]['timestamp_parser'])) {
				$sync_timestamp = call_user_func([$this, $this->tables[$diff_table_key]['timestamp_parser']], $sync_timestamp);
			}

			// Parse data if data parser is configured
			if (isset($table['data_parser']) && method_exists($this, $table['data_parser'])) {
				$data = call_user_func([$this, $table['data_parser']], $diff_row);

			// Otherwise just assign data to the key
			} else {
				foreach($table['fields'] as $field) {
					$data[$field] = $diff_row[$field];
				}
			}

			// If data is not false, sync it
			if($data!==false) {
				// If table is master and diff table has a master_key, this table's primary_key is the diff table's master_key
				if (isset($table['master']) && $table['master'] && $diff_row[$this->tables[$diff_table_key]['master_key']]!=null) {
					$data[$table['primary_key']] = $diff_row[$this->tables[$diff_table_key]['master_key']];

				// If this row has already been synchronised with master table and got a key, use key as master_key
				} else if (isset($this->master_keys[$diff_table_key][$diff_row_key])) {
					$data[$table['master_key']] = $this->master_keys[$diff_table_key][$diff_row_key]['master_key'];

				// If diff table is master, diff data primary key becomes table master key
				} else if(isset($this->tables[$diff_table_key]['master']) && $this->tables[$diff_table_key]['master']) {
					$data[$table['master_key']] = $diff_row[$this->tables[$diff_table_key]['primary_key']];

				// If diff table is not master, diff data master key becomes table master key if diff data master key exists
				} else if(!isset($this->tables[$diff_table_key]['master']) && $diff_row[$this->tables[$diff_table_key]['master_key']]!=null) {
					$data[$table['master_key']] = $diff_row[$this->tables[$diff_table_key]['master_key']];
				}

				// Create db
				$db = $this->create_db($table);

				// Get primary key for master table and master key for slaves
				if(isset($table['master']) && $table['master']) {
					$primary_key = $table['primary_key'];
				} else {
					$primary_key = $table['master_key'];
				}

				// Update if exists, create otherwise, we do not use replace here because it would erase data that is not synchronized
				if (isset($data[$primary_key]) && count($db->find($primary_key, $data[$primary_key])->get())) {
					$sync_key = $db->$table['primary_key'];
					$db->update($data);
				} else {
					$db->create($data);
					$sync_key = $db->get_id();
				}

				// If table master, store master_key for update later
				if(isset($table['master']) && $table['master'] && $diff_row[$this->tables[$diff_table_key]['master_key']]==null) {
					$master_key = $sync_key;
					$this->master_keys[$diff_table_key][$diff_row_key] = ['primary_key'=>$diff_row[$this->tables[$diff_table_key]['primary_key']], 'master_key'=>$master_key];
				}

				// Get last primary key synchronised and store it in new_status if it is bigger then the key already stored
				if ($sync_key>$this->new_status->$table_key->last_sync_key) {
					$this->new_status->$table_key->last_sync_key = $sync_key;
				}

				// Store this data's timestamp if it is bigger then the last one stored
				if($sync_timestamp>$this->new_status->$table_key->last_sync_timestamp) {
				 	$this->new_status->$table_key->last_sync_timestamp = $sync_timestamp;
				}
												
				// Store this data's key
				if (isset($table['master']) && $table['master'] && isset($sync_key) && $sync_key!=null && !in_array($sync_key, $this->new_status->$table_key->keys)) {
					$this->new_status->$table_key->keys[] = $sync_key;
				} else if((!isset($table['master']) || !$table['master']) && isset($data[$table['master_key']]) && $data[$table['master_key']]!=null && !in_array($data[$table['master_key']], $this->new_status->$table_key->keys)) {
					$this->new_status->$table_key->keys[] = $data[$table['master_key']];
				}
				
				// Callback
				if (!empty($sync_key) && $sync_key!=0 && isset($table['callback'])) {					
					call_user_func([$this, $table['callback']], $sync_key);
				}
			}
		}
	}

	/**
	 * Update the master key of the tables that have been synchronised with the master table
	 *
	 * @param $table, $diff_data, $diff_table_key
	 * @var array, array, int
	 * @access protected
	 * @return void
	 */
	protected function udpate_master_keys() {
		foreach ($this->master_keys as $table_key=>$rows) {
			$this->changed_data = true;
			
			foreach ($rows as $row) {
				$table = $this->tables[$table_key];
				$this->create_db($table)->find($table['primary_key'], $row['primary_key'])->update($table['master_key'], $row['master_key']);
				
				// Store this data's key 
				if (!in_array($row['master_key'], $this->new_status->$table_key->keys)) {
					$this->new_status->$table_key->keys[] = $row['master_key'];
				}
			}
		}
	}

	/**
	 * Delete data that comes from the diff between table's key and file's key
	 *
	 * @param $table, $diff_data
	 * @var array, array
	 * @access protected
	 * @return void
	 */
	protected function delete_data( $table, $table_key, $diff_keys, $diff_table_key ) {
		foreach ($diff_keys as $key) {
			$this->changed_data = true;
			
			if (isset($table['master']) && $table['master']) {
				$primary_key = $table['primary_key'];
			} else {
				$primary_key = $table['master_key'];
			}
						
			// Delete from table's status
			$new_keys = [];
			foreach ($this->new_status->$table_key->keys as $new_key) {
				if($new_key!=$key) {
					$new_keys[] = $new_key;
				}
			}
			$this->new_status->$table_key->keys = $new_keys;
			
			// Delete from diff table's status
			$new_keys = [];
			foreach ($this->new_status->$diff_table_key->keys as $new_key) {
				if($new_key!=$key) {
					$new_keys[] = $new_key;
				}
			}
			$this->new_status->$diff_table_key->keys = $new_keys;

			// Delete entry from table
			$entry = $this->create_db($table)->find($primary_key, $key)->get()->delete();
				
			// Callback
			if (!empty($key) && $key!=0 && isset($table['callbacks']['deletes'])) {					
			    call_user_func([$this, $table['callbacks']['deletes']], $key);
			}
		}
	}
}