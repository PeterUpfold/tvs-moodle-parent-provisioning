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
	protected $orphaned = NULL;

	/**
	 * A connection to the Moodle database.
	 */
	protected $dbc = NULL;

	/**
	 * Reference to an object that we can use to make log entries.
	 */
	protected $logger = NULL;

	/**
	 * The user ID within the mdl_users table.
	 */
	public $id = NULL;

	/**
	 * The idnumber field within Moodle, which will map to an Admissions Number (Adno).
	 */
	public $idnumber = NULL;

	/**
	 * Whether or not the Moodle user account is currently suspended and unable to log in.
	 */
	public $suspended;

	/**
	 * The prefix used for the Moodle database tables.
	 */
	public $dbprefix = 'mdl_';

	/** 
	 * Construct the object
	 */
	public function __construct( $logger, $dbc ) {
		
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
	 * @param string $property The property to use to match the username to the database, i.e. 'username' or 'id' or 'idnumber'
	 *
	 * @return bool false if the load failed
	 */
	public function load( $property ) {

		switch( $property ) {

		case 'idnumber':
			if ( ! $stmt = $this->dbc->prepare( "SELECT id, username, idnumber, suspended FROM {$this->dbprefix}user WHERE idnumber = ?" ) ) {

				throw new Exception( __( 'Unable to prepare query to load a Moodle user', 'tvs-moodle-parent-provisioning' ) );
			}	

				
			$stmt->bind_param( 's', $this->idnumber );
			$stmt->execute();
			$stmt->bind_result( $id, $username, $idnumber, $suspended );
			$stmt->store_result();

			if ( $stmt->num_rows > 0 ) {
				$stmt->fetch();
				$this->id = $id;
				$this->username = $username;
				$this->idnumber = $idnumber;
				$this->suspended = $suspended;
				$this->logger->debug( sprintf( __( 'Found mdl_user ID %d to match %s', 'tvs-moodle-parent-provisioning' ), $this->id, $this->idnumber ) );
				return true;
			}
			else {
				$this->logger->debug( sprintf( __( 'Did not find a user to match idnumber %s.', 'tvs-moodle-parent-provisioning' ), $this->idnumber ) );
				return false;
			}

			break;

		case 'id':
			if ( ! $stmt = $this->dbc->prepare( "SELECT id, username, idnumber, suspended FROM {$this->dbprefix}user WHERE id = ?" ) ) {

				throw new Exception( __( 'Unable to prepare query to load a Moodle user', 'tvs-moodle-parent-provisioning' ) );
			}	

				
			$stmt->bind_param( 'i', $this->id );
			$stmt->execute();
			$stmt->bind_result( $id, $username, $idnumber, $suspended );
			$stmt->store_result();

			if ( $stmt->num_rows > 0 ) {
				$stmt->fetch();
				$this->id = $id;
				$this->username = $username;
				$this->idnumber = $idnumber;
				$this->suspended = $suspended;
				$this->logger->debug( sprintf( __( 'Found mdl_user ID %d to match %s', 'tvs-moodle-parent-provisioning' ), $this->id, $this->username ) );
				return true;
			}
			else {
				$this->logger->debug( sprintf( __( 'Did not find a user to match internal ID %d.', 'tvs-moodle-parent-provisioning' ), $this->id ) );
				return false;
			}

			break;

		case 'username':
			if ( ! $stmt = $this->dbc->prepare( "SELECT id, username, idnumber, suspended FROM {$this->dbprefix}user WHERE username = ?" ) ) {

				throw new Exception( __( 'Unable to prepare query to load a Moodle user', 'tvs-moodle-parent-provisioning' ) );
			}	

				
			$stmt->bind_param( 's', $this->username );
			$stmt->execute();
			$stmt->bind_result( $id, $username, $idnumber, $suspended );
			$stmt->store_result();

			if ( $stmt->num_rows > 0 ) {
				$stmt->fetch();
				$this->id = $id;
				$this->username = $username;
				$this->idnumber = $idnumber;
				$this->suspended = $suspended;
				$this->logger->debug( sprintf( __( 'Found mdl_user ID %d to match %s', 'tvs-moodle-parent-provisioning' ), $this->id, $this->username ) );
				return true;
			}
			else {
				$this->logger->debug( sprintf( __( 'Did not find a user to match username %s.', 'tvs-moodle-parent-provisioning' ), $this->username ) );
				return false;
			}

			break;

		}
	
	}

	/**
	 * Determine whether or not this user has an entry in the mdl_user table
	 * of the Moodle database.
	 * @return bool
	 */
	public function is_orphaned() {
		return ! ( $this->load( 'username' ) );
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

		$this->logger->debug( sprintf( __( 'Preparing to set mnethostid for userid %d: mnethostid %d, auth %s', 'tvs-moodle-parent-provisioning' ), $this->id, $mnethostid, $auth ) );

		$stmt = $this->dbc->prepare( "UPDATE {$this->dbprefix}user SET mnethostid = ? WHERE auth = ? AND id = ?" );
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

		if ( NULL === $this->id ) {
			throw new LogicException( __( 'Attempting to get role assignment for a mdl_user object that has not been loaded yet, or does not exist.', 'tvs-moodle-parent-provisioning' ) );
		}

		$this->logger->debug( sprintf( __( 'Determine role assignment for user %d with role %d in context %d', 'tvs-moodle-parent-provisioning' ), $this->id, $roleid, $contextid ) );

		$stmt = $this->dbc->prepare( "SELECT id FROM {$this->dbprefix}role_assignments WHERE roleid = ? AND contextid = ? AND userid = ?" );

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

		$this->logger->debug( sprintf( __( 'Returned role assignment ID is %d', 'tvs-moodle-parent-provisioning' ), $role_assignment_id ) );
	
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

		$stmt = $this->dbc->prepare( "INSERT INTO {$this->dbprefix}role_assignments ( roleid, contextid, userid, timemodified, modifierid, component, itemid, sortorder ) VALUES ( ?, ?, ?, ?, ?, ?, ?, ? )" );

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

	/**
	 * Remove the role assignment with the specified primary key ID.
	 *
	 * @param int $role_assignment_id The role assignment primary key ID.
	 *
	 * @return int The number of affected rows.
	 */
	public function remove_role_assignment( $role_assignment_id ) {
		$this->logger->debug( sprintf( __( 'Remove role assignment %d', 'tvs-moodle-parent-provisioning' ), $role_assignment_id ) );

		$stmt = $this->dbc->prepare( "DELETE FROM {$this->dbprefix}role_assignments WHERE id = ?" );

		if ( ! $stmt ) {
			throw new Exception( sprintf( __( 'Failed to prepare the database statement to remove a role assignment. Error: %s', 'tvs-moodle-parent-provisioning' ), $this->dbc->error ) );
		}

		$stmt->bind_param( 'i', $role_assignment_id );
		$stmt->execute();

		$this->logger->info( sprintf( __( 'Removed role assignment: %d. Affected rows: %d', 'tvs-moodle-parent-provisioning' ), $role_assignment_id, $stmt->affected_rows ) );

		$rows = $stmt->affected_rows;

		$stmt->close();

		return $rows;
	}


	/**
	 * Set the email address and username of the target mdl_user.
	 *
	 * @param string $new_email The new email address
	 *
	 * @return int The number of affected rows.
	 */
	public function set_email_address_and_username( $new_email ) {
		$this->logger->debug( sprintf( __( 'Update email address and username to \'%s\' (%s)', 'tvs-moodle-parent-provisioning' ), $new_email, $this->__toString() ) );

		if ( ! $this->id ) {
			throw new InvalidArgumentException( __( 'The mdl_user must be successfully loaded from the database before attempting to set the email address and username.', 'tvs-moodle-parent-provisioning' ) );
		}

		$stmt = $this->dbc->prepare( "UPDATE {$this->dbprefix}user SET username = ?, email = ? WHERE id = ?" );
		if ( ! $stmt ) {
			throw new Exception( sprintf( __( 'Failed to prepare the database statement to update email address. Error: %s', 'tvs-moodle-parent-provisioning' ), $this->dbc->error ) );
		}

		$stmt->bind_param( 'ssi', $new_email, $new_email, $this->id );
		$stmt->execute();

		$this->logger->info( sprintf( __( 'Updated %s email address to \'%s\'. Affected rows: %d', 'tvs-moodle-parent-provisioning' ), $this->__toString(), $new_email, $stmt->affected_rows ) );
		$rows = $stmt->affected_rows;

		$stmt->close();

		// update internal object. Other mdl_user derived objects may need reloading by callers to see new data
		$this->username = $new_email;

		return $rows;

	}

	/**
	 * Return a string representation of the object.
	 *
	 * @return string
	 */
	public function __toString() {
		return "[mdl_user]" . $this->username;
	}


};
