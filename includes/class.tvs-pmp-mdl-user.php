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
	 * Reference to an object that we can use to make log entries.
	 */
	protected $logger = null;

	/**
	 * The user ID within the mdl_users table.
	 */
	public $id = null;

	/** 
	 * Construct the object
	 */
	public function __construct( $username, $logger, $dbc ) {
		$this->username = $username;
		
		if ( !( $dbc instanceof \mysqli ) ) {
			throw new ArgumentException( __( 'You must pass an instance of a mysqli object that can fetch the information from Moodle.', 'tvs-moodle-parent-provisioning' ) );
		}

		if ( ! $dbc ) {
			throw new ArgumentException( __( 'Unable to connect to the Moodle database.', 'tvs-moodle-parent-provisioning' ) );
		}

		$this->dbc = $dbc;
		$this->logger = $logger;

	}

	/**
	 * Load information about this Moodle user from the mdl_user into the 
	 * properties of this class.
	 *
	 * @return bool false if the load failed
	 */
	public function load() {
		return ! ($this->is_orphaned() );
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
			$this->logger->debug( sprintf( __( 'Found mdl_user ID %d to match %s', 'tvs-moodle-parent-provisioning' ), $this->id, $this->username ) );
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

	/**
	 * Set the mnethostid for the specified user.
	 * The mnethostid is a numeric ID that Moodle uses to identify different sites to allow inter-site communication. Typically we will
	 * want to set this user to be a local user (i.e. the local site's mnethostid)
	 *
	 * @param int mnethostid The target mnethostid -- 
	 * @param string auth The authentication plugin with which this user is associated. Will be 'db' for external database.
	 *
	 * @return int The number of rows affected.
	 */
	public function set_mnethostid( $mnethostid, $auth ) {

		if ( $this->id === NULL ) {
			$this->load();
		}

		$this->logger->debug( sprintf( __( 'Preparing to set mnethostid for this->id %d: mnethostid %d, auth %s', 'tvs-moodle-parent-provisioning' ), $this->id, $mnethostid, $auth ) );

		$stmt = $this->dbc->prepare( "UPDATE {$this->dbprefix}user SET mnethostid = ? WHERE auth = ? AND id = ?"this->mdl_user
		if ( ! $stmt ) {
			throw new Exception( sprintf( __( 'Failed to prepare the database statement to set mnethostid. Error: %s', 'tvs-moodle-parent-provisioning' ), $this->dbc->error ) );
		}

		$stmt->bind_param( 'isi', $mnethostid, $auth, $this->id );
		$stmt->execute();

		$this->logger->debug( sprintf( __( 'Set mnethostid to %d for user %d with auth type %s. Affected rows: %d', 'tvs-moodle-parent-provisioning' ), $mnethostid, $this->id, $auth, $stmt->affected_rows ) );

		$rows = $stmt->affected_rows;

		$stmt->close();

		return $rows;
	}


	/**
	 * Determine if a role assignment exists for the specified user in the given context. If so, return the role assignment id.
	 *
	 * @param int roleid Typically this will be the ID of the parent role
	 * @param int contextid The contextid is linked to a category, course or other entity within Moodle. It can be determined by going to assign roles and checking for the contextid in the URL.
	 *
	 * @return int role assignment id, or 0 if no results
	 */
	public function get_role_assignment( $roleid, $contextid ) {

		if ( $this->id === NULL ) {
			$this->load();
		}

		$this->logger->debug( sprintf( __( 'Determine role assignment for user %d with role %d in context %d', 'tvs-moodle-parent-provisioning' ), $this->id, $roleid, $contextid ) );

		$stmt = $this->dbc->prepare( "SELECT id FROM {$this->dbprefix}role_assignments WHERE roleid = ? AND contextid = ? AND this->id = ?" );

		if ( ! $stmt ) {
			throw new Exception( sprintf( __( 'Failed to prepare the database statement to get role assignments. Error: %s', 'tvs-moodle-parent-provisioning' ), $this->dbc->error ) );
		}

		$stmt->bind_param( 'iii', $roleid, $contextid, $this->id );
		$stmt->execute();
		$stmt->store_result();

		if ( $stmt->num_rows < 1 ) {
			// no results
			$this->logger->info( sprintf( __( 'User %d does not currently have the role %d assigned in context %d.', 'tvs-moodle-parent-provisioning' ), $this->id, $roleid, $contextid ) );
			return 0;
		}

		$stmt->bind_result( $role_assignment_id );
		$stmt->fetch();
		$stmt->close();

		if ( empty( $role_assignment_id ) || ! is_int( $role_assignment_id ) ) {
			throw new Exception( sprintf( __( 'Returned role assignment ID was empty or not an integer.', 'tvs-moodle-parent-provisioning' ) ) );
		}

		$this->logger->info( sprintf( __( 'Returned role assignment ID is %d', 'tvs-moodle-parent-provisioning' ), $role_assignment_id ) );
	
		return $role_assignment_id;

	}

	/**
	 * Add a role assignment in the specified context to the specified user.
	 *
	 * @param int roleid The role ID to assign. Usually will be the parent role ID.
	 * @param int contextid The contextid is linked to a category, course or other entity within Moodle. It can be determined by going to assign roles and checking for the contextid in the URL.
	 * @param int modifierid The user ID considered to have made the change for audit purposes.
	 * @param string component Optional string for the Moodle component that added the role assignment.
	 * @param int itemid
	 * @param int sortorder
	 *
	 * @return int The number of affected rows.
	 */
	public function add_role_assignment( $roleid, $contextid, $modifierid, $component = '', $itemid = 0, $sortorder = 0 ) {
		$this->logger->debug( sprintf( __( 'Add role assignment for user %d with role %d in context %d', 'tvs-moodle-parent-provisioning' ), $this->id, $roleid, $contextid ) );

		$stmt = $this->dbc->prepare( "INSERT INTO {$this->dbprefix}role_assignments ( roleid, contextid, this->id, timemodified, modifierid, component, itemid, sortorder ) VALUES ( ?, ?, ?, ?, ?, ?, ?, ? )" );

		if ( ! $stmt ) {
			throw new Exception( sprintf( __( 'Failed to prepare the database statement to add a role assignment. Error: %s', 'tvs-moodle-parent-provisioning' ), $this->dbc->error ) );
		}

		$time = time();
		$stmt->bind_param( 'iiiiisii', $roleid, $contextid, $this->id, $time, $modifierid, $component, $itemid, $sortorder );
		$stmt->execute();
		$stmt->store_result();

		$this->logger->info( sprintf( __( 'Added role assignment: role %d for user %d in context %d. Affected rows: %d', 'tvs-moodle-parent-provisioning' ), $roleid, $this->id, $contextid, $stmt->affected_rows ) );

		$rows = $stmt->affected_rows;

		$stmt->close();

		return $rows;
	}


};
