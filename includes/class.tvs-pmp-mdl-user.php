<?php
/* Copyright (C) 2016-2017 Test Valley School.


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
 * Represents a possible match from an auth table entry
 * to a mdl_user table entry in the Moodle database. Used
 * to determine if an auth table entry is orphaned and
 * not connected to a mdl_user table entry.
 */


class TVS_PMP_mdl_user {

	/** 
	 * The username which should match the mdl_user.
	 */
	public $username; 

	/**
	 * Boolean or null: Whether or not the username in this object
	 * is not connected to a mdl_user entry in the Moodle database.
	 * Will be null if not yet determined.
	 */
	protected $orphaned = null;

	/**
	 * A connection to the Moodle database.
	 */
	protected $dbc = null;

	/**
	 * The user ID within the mdl_users table.
	 */
	public $id = null;

	/** 
	 * Construct the object
	 */
	public function __construct( $username, $dbc ) {
		$this->username = $username;
		
		if ( !( $dbc instanceof \mysqli ) ) {
			throw new ArgumentException( __( 'You must pass an instance of a mysqli object that can fetch the information from Moodle.', 'tvs-moodle-parent-provisioning' ) );
		}

		if ( ! $dbc ) {
			throw new ArgumentException( __( 'Unable to connect to the Moodle database.', 'tvs-moodle-parent-provisioning' ) );
		}

		$this->dbc = $dbc;

	}

	/**
	 * Determine whether or not this user has an entry in the mdl_user table
	 * of the Moodle database.
	 * @return bool
	 */
	public function is_orphaned() {
		
		if ( ! $stmt = $this->dbc->prepare( 'SELECT id FROM mdl_user WHERE username = ?' ) ) {
			throw new Exception( __( 'Unable to prepare query to determine if user is orphaned', 'tvs-moodle-parent-provisioning' ) );
		}	

			
		$stmt->bind_param( 's', $this->username );
		$stmt->execute();
		$stmt->bind_result( $id );
		$stmt->store_result();

		if ( $stmt->num_rows > 0 ) {
			$stmt->fetch();
			$this->id = $id; 
			return false;
		}
		else {
			return true;
		}
	}

	/**
	 * Delete a number of entries in the auth table based on the array of passed
	 * request IDs which these auth entries match.
	 */
	public static function delete_bulk() {
		global $wpdb;

		$tn = $wpdb->prefix . 'tvs_parent_moodle_provisioning_auth';

		if ( ! array_key_exists( 'request_id', $_REQUEST ) || ! is_array( $_REQUEST['request_id'] ) || count( $_REQUEST['request_id'] ) < 1 ) {
			return __( 'No auth table entries were selected to delete.', 'tvs-moodle-parent-provisioning' );
		}

		$statement = "DELETE FROM $tn WHERE request_id IN (" . implode( ', ', array_fill(0, count( $_REQUEST['request_id'] ), '%d' ) ) . ")";

		$query = call_user_func_array(
			array( $wpdb, 'prepare' ),
			array_merge( array( $statement ), $_REQUEST['request_id'] )
		);

		$wpdb->query( $query );

	}


};
