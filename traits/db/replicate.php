<?php

namespace sinergi\db;

trait Replicate {
	/**
	 * Replicate a database table to another table
	 * 
	 * @param $master	the master table
	 * @param $slave	the slave table
	 * @return bool
	 */
	private function replicate( $master, $slave, $parser = null ) {
		
		/* Sync new items */
		$master_items = $master->get_all();
		
		$count = 0;
		foreach($master_items as $item) {
			if(isset($parser)) {
				$item_data = $parser($item);
			} else {
				$item_data = $item;
			}
			$slave->replace((array) $item_data);
		}
		
		/* Delete old items */
		$slave_items = $slave->get_all();
		
		$count = 0;
		foreach($slave_items as $item) {
			if (!$this->_match_item($item, $master_items, $parser)) {
				$item->delete();
			}
		}
	}
	
	/**
	 * Check if result match array of results
	 * 
	 * @param $needle	result to match
	 * @param $haystack	array of results to test against
	 * @return bool
	 */
	protected function _match_item( $needle, $haystack, $parser ) {
		foreach($haystack as $candidate) {
			
			if(isset($parser)) {
				$candidate_data = $parser($candidate);
			} else {
				$candidate_data = $candidate;
			}
			
			$match = true;
			foreach($needle as $key=>$value) {
				if ($candidate_data[$key] != $value) {
					$match = false;
					break;
				}
			}
			if ($match) return true;
		}
		return false;
	}
}