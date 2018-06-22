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
	 * The name of the database table containing records.
	 */
	public static $table_name = 'tvs_parent_moodle_provisioning_contact_mapping';

	/**
	 * Set up the object.
	 *
	 * @param Monolog\Logger\Logger $logger Reference to an object we can use to log messages.
	 */
	public function __construct( $logger, $dbc ) {
		$this->logger = $logger;
		if ( !( $dbc instanceof \mysqli ) ) {
			throw new ArgumentException( __( 'You must pass an instance of a mysqli object that can fetch the information from Moodle.', 'tvs-moodle-parent-provisioning' ) );
		}

		$this->dbc = $dbc;
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
		$this->logger->debug( sprintf( __( 'Loaded record for %s', 'tvs-moodle-parent-provisioning' ), $this->__toString() ) );
	}

	/**
	 * Determine whether or not this Contact Mapping exists within the Moodle database
	 * tables.
	 *
	 * @return bool true if the Mapping is already present
	 */
	public function is_mapped() {
		$context = $this->mdl_db_helper->get_context( TVS_PMP_MDL_DB_Helper::CONTEXT_USER, $pupil->id, /* depth */ 2 );

		$role_assignment = $this->mdl_user->get_role_assignment( $parent_userid, $this->parent_role_id, $context );
		return ( $role_assignment > 0 );
	}


	/**
	 * Return a string representation of the object.
	 */
	public function __construct() {
		return sprintf( __( '[Contact Mapping]%d: MIS ID: %d, external MIS ID: %s, adno: %s, %s', 'tvs-moodle-parent-provisioning' ), $this->id, $this->mis_id, $this->external_mis_id, $this->adno, $this->username );
	}

};
