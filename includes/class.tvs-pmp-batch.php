<?php
/*/* Copyright (C) 2016-2018 Test Valley School.


    This program is free software; you can redistribute it and/or
    modify it under the terms of the GNU General Public License version 2
    as published by the Free Software Foundation.
    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.
    You should have received a copy of the GNU General Public License
    along with this program; if not, write to the Free Software
    Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA

*/

/**
 * Class: represents an uploaded batch of TVS Parent Moodle Account requests for the purpose of displaying the batch logs.
 */

class TVS_PMP_Batch {

	/** 
	 * The randomly generated batch id.
	 */
	public $id;

	/**
	 * When the batch was started.
	 */
	public $timestamp;
	
	/** 
	 * Any informational messages recorded in the batch log.
	 */
	public $information;

	/**
	 * Any warning messages recorded in the batch log.
	 */
	public $warnings;

	/**
	 * Any errors recorded in the batch log.
	 */
	public $errors;

	/**
	 * Determine the list of batches from the wp_options table and return them in date order.
	 */
	public static function get_batch_list() {
		global $wpdb;

		$batch_options = $wpdb->get_results(
			"SELECT option_name FROM $wpdb->options WHERE option_name LIKE '_transient_tvs_pmp_batch_log_information_%' ORDER BY option_name DESC" /* would use prepare if we had any variables, but we don't */

		);

		$batches = array();

		foreach( $batch_options as $batch_option ) {
			// extract batch name
			$batch_name = substr( $batch_option->option_name, strrpos( $batch_option->option_name, '_' ) + 1 );
			$batch_time = substr( $batch_option->option_name,
				strpos( $batch_name, '_transient_tvs_pmp_batch_log_information_' ) + strlen ( '_transient_tvs_pmp_batch_log_information_' ),
				strlen( date( 'Y-m-d_H-i-s' ) )
			);

			$batches[] = new TVS_PMP_Batch();
			$batches[ count( $batches )-1 ]->id = $batch_name;
			$batches[ count( $batches )-1 ]->timestamp = $batch_time;
		}

		return $batches;

	}

	/**
	 * If this object's ID field is populated, load the batch logs into
	 * this object's other properties.
	 */
	public function load() {
		global $wpdb;

		if ( ! $this->id ) {
			throw new InvalidArgumentException( __( 'You cannot load the details of a batch log without providing the ID.', 'tvs-moodle-parent-provisioning' ) );
		}

		$options = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT option_id, option_name, option_value FROM $wpdb->options WHERE option_name LIKE %s",
				'_transient_tvs_pmp_batch_log_%' . $wpdb->esc_like( $this->id ) . '%'
			)
		);

		foreach( $options as $option ) {
			if ( strpos( $option->option_name, '_transient_tvs_pmp_batch_log_information_' ) !== false ) {
				// load information
				$this->information = get_option( $option->option_name );
			}
			if ( strpos( $option->option_name, '_transient_tvs_pmp_batch_log_warnings_' ) !== false ) {
				// load warnings
				$this->warnings = get_option( $option->option_name );
			}
			if ( strpos( $option->option_name, '_transient_tvs_pmp_batch_log_errors_' ) !== false ) {
				// load errors
				$this->errors = get_option( $option->option_name );
			}
		}

	}


	/**
	 * Remove the batch logs associated with this batch.
	 */
	public function delete_logs() {
		global $wpdb;
		if ( ! $this->id ) {
			throw new InvalidArgumentException( __( 'You cannot delete a batch log without supplying the ID.', 'tvs-moodle-parent-provisioning' ) );
		}

		$wpdb->query( $wpdb->prepare( "
				DELETE FROM $wpdb->options
				 WHERE option_name LIKE %s
			",
			'_transient_tvs_pmp_batch_log_%' . $wpdb->esc_like( $this->id ) . '%'
			)
		);
	}
	
	/**
	 * Remove all batch logs.
	 */
	public function delete_all_logs() {
		global $wpdb;

		$wpdb->query( $wpdb->prepare( "
				DELETE FROM $wpdb->options
				WHERE option_name LIKE %s
			",
			'_transient_tvs_pmp_batch_log_%'
			)
		);
	}


};
