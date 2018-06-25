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
 * Class: Represents a mapping between a Contact (i.e. a parent) and another user that exists
 * within Moodle (i.e. a pupil). Each Contact may have any number of mappings.
 */

require_once( dirname( __FILE__ ) . '/../vendor/autoload.php' );

use Monolog\Logger;
use Monolog\ErrorHandler;
use Monolog\Handler\StreamHandler;
use Monolog\Handler\BrowserConsoleHandler;

/**
 * Class: Represents a mapping between a Contact (i.e. a parent) and another user that exists
 * within Moodle (i.e. a pupil). Each Contact may have any number of mappings.
 */
class TVS_PMP_Contact_Mapping {

	/**
	 * The unique internal ID for this Contact Mapping.
	 */
	public $id;

	/**
	 * The internal ID of the Contact that this Mapping is associated with.
	 */
	public $contact_id;

	/**
	 * The MIS internal ID of the Person to which this Mapping relates -- i.e.
	 * the target of the Mapping.
	 */
	public $mis_id;

	/**
	 * An "external ID", usually a GUID, of the Person in the MIS to which this
	 * Mapping relates -- i.e. the target of this Mapping.
	 */
	public $external_mis_id;

	/**
	 * The user ID within Moodle for the target of this Contact Mapping (i.e. a pupil user).
	 */
	public $mdl_user_id;

	/**
	 * The Admissions Number of this Mapped Person.
	 */
	public $adno;

	/**
	 * The target username within Moodle that this Mapping is associated with.
	 */
	public $username;

	/**
	 * The date and time that this Mapping was last evaluated during a Stage 2
	 * sync. This can be used to verify that the object has been evaluated correctly.
	 */
	public $date_synced;

	/**
	 * The role assignment within the Moodle tables that represents this Contact Mapping.
	 * This is the actual row which connects the Contact to the target user.
	 */
	public $role_assignment_id;

	/**
	 * Reference to an object we can use to log messages.
	 */
	protected $logger = NULL;


	/**
	 * Instance of a mysqli object that can fetch data from the Moodle database.
	 */
	protected $dbc = NULL;

	/**
	 * The TVS_PMP_mdl_user object that represents the Moodle user associated with the target
	 * of this Contact Mapping.
	 */
	protected $mdl_user = NULL;

	/**
	 * @var TVS_PMP_Contact
	 *
	 * The Contact that this Contact Mapping should map to -- i.e. the parent.
	 */
	protected $contact = NULL;

	/**
	 * The name of the database table containing records.
	 */
	public static $table_name = 'tvs_parent_moodle_provisioning_contact_mapping';

	/**
	 * The target role ID to set to create the Mapping. This is typically
	 * the 'Parent' role.
	 */
	public static $target_role_id = 8;


	/**
	 * The Moodle user ID of the user who will be considered to be responsible
	 * for creating this Contact Mapping. Typically an administrative user.
	 */
	public static $modifier_id = 2;

	/**
	 * Set up the object.
	 *
	 * @param Monolog\Logger\Logger $logger Reference to an object we can use to log messages.
	 */
	public function __construct( $logger, $dbc, TVS_PMP_Contact $contact ) {
		$this->logger = $logger;
		if ( !( $dbc instanceof \mysqli ) ) {
			throw new ArgumentException( __( 'You must pass an instance of a mysqli object that can fetch the information from Moodle.', 'tvs-moodle-parent-provisioning' ) );
		}

		if ( !( $contact instanceof TVS_PMP_Contact ) ) {
			throw new ArgumentException( __( 'You must pass an instance of TVS_PMP_Contact when setting up a TVS_PMP_Contact_Mapping.', 'tvs-moodle-parent-provisioning' ) );
		}

		$this->dbc = $dbc;
		$this->contact = $contact;
	}

	/**
	 * Given a record with the id in $this->id, load it from the database
	 *
	 * @return bool Whether or not the data was loaded.
	 */
	public function load() {
		global $wpdb;

		if ( empty( $this->id ) || ! is_int( $this->id ) ) {
			$error = __( 'The $id variable must be set to a non-zero integer.', 'tvs-moodle-parent-provisioning' );
			$this->logger->error( $error );
			throw new InvalidArgumentException( $error );
		}
		
		$table_name = TVS_PMP_Contact_Mapping::$table_name;
		
		$this->logger->debug( sprintf( __( 'Load Contact Mapping with ID %d from our database table \'%s\'.', 'tvs-moodle-parent-provisioning' ), $this->id, $table_name ) );


		$row = $wpdb->get_row(
			$wpdb->prepare( "SELECT * FROM {$wpdb->prefix}{$table_name} WHERE id = %d",
					$this->id
			)
		);

		// load 
		if ( $row ) {
			$this->load_from_row( $row );	
			return true;
		}

		$this->logger->warning( sprintf( __( 'Did not succeed at fetching a database row for Contact Mapping %d. (This is our ID, not the MIS ID).', 'tvs-moodle-parent-provisioning' ), $this->id ) );
		return false;

	}


	/**
	 * Save this Contact Mapping in our internal database tables. Note that this method **does not** perform
	 * the mapping or unmapping to make Moodle match our tables.
	 * 
	 * @return int|bool The ID of the (new) Contact Mapping, or false if there was an error running the database query. 
	 */
	public function save() {
		global $wpdb;

		$table_name = TVS_PMP_Contact_Mapping::$table_name;

		if ( empty( $this->id ) || ! is_int( $this->id ) ) {
			// create
			$result = $wpdb->insert( $wpdb->prefix . $table_name, array(
				'contact_id'                         => $this->contact_id,
				'mis_id'                             => $this->mis_id,
				'external_mis_id'                    => $this->external_mis_id,
				'mdl_user_id'                        => $this->mdl_user->id,
				'adno'                               => $this->adno,
				'username'                           => $this->username,
				'date_synced'                        => $this->date_synced
			),
			array(
				'%d',
				'%s',
				'%s',
				'%d',
				'%s',
				'%s',
				'%s'
			) );

			if ( $result === false ) {
				$this->logger->warn( __( 'Failed to run the insert query. WordPress returned (bool)false.', 'tvs-moodle-parent-provisioning' ) );
				return false;
			}

			$this->id = $wpdb->insert_id;
			return $this->id;
		}

		// update
		$update_result = $wpdb->update( $wpdb->prefix . $table_name, array(
			'contact_id'                         => $this->contact_id,
			'mis_id'                             => $this->mis_id,
			'external_mis_id'                    => $this->external_mis_id,
			'mdl_user_id'                        => $this->mdl_user->id,
			'adno'                               => $this->adno,
			'username'                           => $this->username,
			'date_synced'                        => $this->date_synced
		),
		array(
			'%d',
			'%s',
			'%s',
			'%d',
			'%s',
			'%s',
			'%s'
		), array(
			'id'                                 => $this->id,
		), array(
			'%d'
		)
		);		

		if ( $update_result === false ) {
			$this->logger->warn( __( 'Failed to run the update query. WordPress returned (bool)false.', 'tvs-moodle-parent-provisioning' ) );
			return false;
		}
		return $this->id;
		
	}

	/**
	 * Load the properties of this Contact Mapping from the specified database $row
	 * array.
	 *
	 * @param $row Array Data retrieved from $wpdb for this Contact Mapping
	 */
	public function load_from_row( $row ) {
		$this->id              = $row->id;
		$this->contact_id      = $row->contact_id;
		$this->mis_id          = $row->mis_id;
		$this->external_mis_id = $row->external_mis_id;
		$this->adno            = $row->adno;
		$this->username        = $row->username;
		$this->date_synced     = $row->date_synced;
		$this->mdl_user        = new TVS_PMP_mdl_user( $this->username, $this->logger, $this->dbc );
		if ( ! $this->mdl_user->load() ) {
			throw new Exception( sprintf( __( 'Failed to load Moodle user for %s. Does this user still exist?', 'tvs-moodle-parent-provisioning' ), $this->__toString() ) );
		}
		$this->mdl_user_id     = $this->mdl_user->id;
		$this->logger->debug( sprintf( __( 'Loaded record for %s', 'tvs-moodle-parent-provisioning' ), $this->__toString() ) );
	}

	/**
	 * Determine whether or not this Contact Mapping exists within the Moodle database
	 * tables.
	 *
	 * @return bool true if the Mapping is already present
	 */
	public function is_mapped() {
		$context = $this->mdl_db_helper->get_context( TVS_PMP_MDL_DB_Helper::CONTEXT_USER, $this->mdl_user->id, /* depth */ 2 );

		$role_assignment = $this->get_role_assignment();
		return ( $role_assignment > 0 );
	}

	/**
	 * Get the role assignment ID that represents this connection between the two users
	 * within the Moodle users table.
	 *
	 * @return int The role assignment ID, or 0.
	 */
	public function get_role_assignment() {
		if ( $this->role_assignment ) {
			return $this->role_assignment;
		}

		$this->role_assignment = $this->mdl_db_helper->get_role_assignment( $this->contact->mdl_user->id, TVS_PMP_Contact_Mapping::$target_role_id, $context );
		return $this->role_assignment;
	}

	/**
	 * Connect the Moodle account for the Contact associated with this object to the
	 * Moodle account which is the target.
	 *
	 * @return int The ID of the role assignment that has been created, or that already existed.
	 */
	public function map() {
		if ( $this->is_mapped() ) {
			$this->logger->info( sprintf( __( '%s is already mapped to %s', 'tvs-moodle-parent-provisioning' ), $this->__toString(), $this->contact ) );
			return true;
		}

		if ( ! $this->contact->mdl_user->id ) {
			$message = sprintf_( __( 'Unable to map %s, as the associated Contact %s has an invalid mdl_user ID.', 'tvs-moodle-parent-provisioning' ), $this->__toString(), $this->contact->__toString() );
			$this->logger->warn( $message );
			throw new InvalidArgumentException( $message );
		}

		// does a context exist that we can use?
		$context = $this->mdl_db_helper->get_context( TVS_PMP_MDL_DB_Helper::CONTEXT_USER, $this->id, /* depth */ 2 );
		if ( ! $context ) {
			$context = $this->mdl_db_helper->add_context( TVS_PMP_MDL_DB_Helper::CONTEXT_USER, $this->id, /* depth */ 2 );
		}

		// does the role assignment already exist for this context?
		$role_assignment = $this->get_role_assignment();
		if ( ! $role_assignment ) {
			// create role assignment
			$role_assignment = $this->mdl_db_helper->add_role_assignment( $this->contact->mdl_user->id, TVS_PMP_Contact_Mapping::$target_role_id, $context, TVS_PMP_Contact_Mapping::$modifier_id, '', 0, 0 );
			return $role_assignment;
		}
		else {
			$this->logger->info( sprintf( __( 'The Contact user %s already had the appropriate role %d assigned in the context %d for the target Contact Mapping user %s. Role assignment ID: %d', 'tvs-moodle-parent-provisioning' ), $this->contact, TVS_PMP_Contact_Mapping::$parent_role_id, $context, $this->__toString(), $role_assignment ) );
			return $role_assignment;
		}
	}

	/**
	 * Remove the mapping between the Contact and the target user.
	 * 
	 * @return int|bool The number of rows affected if the removal was successful, or bool true if the mapping did not exist in the first place.
	 */
	public function unmap() {
		if ( ! $this->is_mapped() ) {
			$this->logger->info( sprintf( __( '%s is already not mapped to %s', 'tvs-moodle-parent-provisioning' ), $this->__toString(), $this->contact ) );
			return true;
		}

		
		if ( ! $this->contact->mdl_user->id ) {
			$message = sprintf_( __( 'Unable to unmap %s, as the associated Contact %s has an invalid mdl_user ID.', 'tvs-moodle-parent-provisioning' ), $this->__toString(), $this->contact->__toString() );
			$this->logger->warn( $message );
			throw new InvalidArgumentException( $message );
		}

		$role_assignment = $this->get_role_assignment();
		if ( $role_assignment ) {
			// remove the assignment
			return $this->mdl_db_helper->remove_role_assignment( $role_assignment );
		}
		else {
			$this->logger->info( sprintf( __( 'The Contact user %s was missing the appropriate role %d assigned in the context %d for the target Contact Mapping user %s, so it could not be deleted. Role assignment ID: %d', 'tvs-moodle-parent-provisioning' ), $this->contact, TVS_PMP_Contact_Mapping::$parent_role_id, $context, $this->__toString(), $role_assignment ) );
			return true;
		}
	}

	/**
	 * Return a string representation of the object.
	 */
	public function __toString() {
		return sprintf( __( '[Contact Mapping]%d: MIS ID: %d, external MIS ID: %s, adno: %s, %s', 'tvs-moodle-parent-provisioning' ), $this->id, $this->mis_id, $this->external_mis_id, $this->adno, $this->username );
	}

};
