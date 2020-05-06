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
 * Class: represents a single TVS Parent Moodle Account Contact (i.e. a parent within the MIS
 * who should have an account.
 *
 */

require_once( dirname( __FILE__ ) . '/../vendor/autoload.php' );
require_once( dirname( __FILE__ ) . '/class.tvs-pmp-mdl-user.php' );

use Monolog\Logger;
use Monolog\ErrorHandler;
use Monolog\Handler\StreamHandler;
use Monolog\Handler\BrowserConsoleHandler;

class TVS_PMP_Contact {

	/**
	 * The unique internal ID for this Contact record.
	 */
	public $id;

	/**
	 * The MIS ID of the Person which is this Contact. This ID is considered
	 * internal to the MIS (primary key).
	 */
	public $mis_id;

	/*
	 * An "external ID", usually a GUID, which can be used to match this
	 * Contact within the MIS.
	 */
	public $external_mis_id;

	/**
	 * The user ID within Moodle for this Contact.
	 */
	public $mdl_user_id;

	/**
	 * The parent's title.
	 */
	public $title;

	/**
	 * The parent's first name.
	 */
	public $forename;

	/**
	 * The parent's surname.
	 */
	public $surname;

	/**
	 * The parent's email address.
	 */
	public $email;

	/**
	 * The status of the request -- pending, approved, provisioned, rejected, duplicate, unknown, bogus
	 */
	public $status;

	/**
	 * The staff comment, as provided through the backend interface.
	 */
	public $staff_comment;

	/**
	 * The system comment -- any errors, info or other messages.
	 */
	public $system_comment;

	/**
	 * The date the request was initially received.
	 */
	public $date_created;

	/**
	 * The date the request was last updated.
	 */
	public $date_updated;

	/**
	 * The date the request was approved for Moodle provisioning.
	 */
	public $date_approved;

	/**
	 * The date this object was last evaluated during a Step 2 sync.
	 */
	public $date_synced;

	/**
	 * The name of the database table containing records.
	 */
	public static $table_name = 'tvs_parent_moodle_provisioning_contact';

	/**
	 * The valid statuses for a record.
	 */
	public static $statuses = array(
		'pending',
		'approved',
		'provisioned',
		'rejected',
		'duplicate',
		'bogus',
		'unknown',
		'partial', /* deferred provisioning completed creating the Moodle account, but roles still to be assigned */
		'deleting' /* temporary status for deprovisioning when a row is being permanently deleted */
	);

	/*
	 * Valid statuses that the record can be in when the account
	 * is de-provisioned, meaning that the associated
	 * auth table entry has been deleted
	 */
	public static $deprovisioned_statuses = array(
		'pending',
		'rejected',
		'duplicate',
		'bogus',
		'unknown',
		'deleting' /* temporary status for deprovisioning when a row is being permanently deleted */
	);

	/**
	 * Monolog Logger instance for reporting information.
	 */
	protected $logger = NULL;


	/**
	 * Instance of a mysqli object that can fetch data from the Moodle database.
	 */
	protected $dbc = NULL;

	/**
	 * Instance of TVS_PMP_MDL_DB_Helper class to fetch information from Moodle database.
	 */
	protected $mdl_db_helper = NULL;


	/**
	 * The TVS_PMP_mdl_user object that represents the Moodle user associated with this Contact.
	 */
	public $mdl_user = NULL;

	/**
	 * Cache of Contact Mappings that are associated with this Contact.
	 */
	protected $contact_mappings = array();


	/**
	 * The Moodle user ID considered to be responsible for the Moodle accounts created by this process.
	 */
	public static $modifier_id = 0;

	/**
	 * These fields are in the database table and are the only strings that can be interpolated
	 * into the database query for ORDER BY
	 */
	protected static $valid_field_names = [
		'id',
		'mis_id',
		'external_mis_id',
		'mdl_user_id',
		'title',
		'forename',
		'surname',
		'email',
		'status',
		'staff_comment',
		'system_comment',
		'date_created',
		'date_updated',
		'date_approved',
		'date_synced'
	];

	/**
	 * Set up the object.
	 *
	 * @param \Monolog\Logger\Logger $logger An object used to log information 
	 * @param mysqli $dbc An instance of a mysqli object that can fetch information from Moodle.
	 */
	public function __construct( $logger, $dbc ) {
		$this->logger = $logger;
		if ( !( $dbc instanceof \mysqli ) ) {
			throw new InvalidArgumentException( __( 'You must pass an instance of a mysqli object that can fetch the information from Moodle.', 'tvs-moodle-parent-provisioning' ) );
		}
		$this->dbc = $dbc;
		$this->status = 'pending'; // this is for new objects. Any call to a load...() will overwrite this property with the saved status

		$set_prefix = 'tvs-moodle-parent-provisioning-';
		if ( ! TVS_PMP_Contact::$modifier_id ) {
			TVS_PMP_Contact::$modifier_id = get_option( $set_prefix . 'moodle-modifier-id' );
		}
	}

	/**
	 * Given a record with the id in $this->id, load it from the database
	 *
	 * @param string $property Which property to use to match the object. 'id', 'external_mis_id'
	 *
	 * @return bool Whether or not the data was loaded.
	 */
	public function load( $property = 'id' ) {

		global $wpdb;

		$table_name = TVS_PMP_Contact::$table_name;
		$row = NULL;

		switch( $property ) {

		case 'id':
			if ( empty( $this->id ) || ! is_int( $this->id ) ) {
				$error = __( 'The $id variable must be set to a non-zero integer.', 'tvs-moodle-parent-provisioning' );
				$this->logger->error( $error );
				throw new InvalidArgumentException( $error );
			}

			$this->logger->debug( sprintf( 
				__( 'Load Contact with ID %d from our database table \'%s\'.', 'tvs-moodle-parent-provisioning' ),
				$this->id, $table_name ) );
		
			$row = $wpdb->get_row(
				$wpdb->prepare( "SELECT * FROM {$wpdb->prefix}{$table_name} WHERE id = %d",
						$this->id
				)
			);
			break;
		case 'external_mis_id':
			if ( empty( $this->external_mis_id ) ) {
				$error = __( 'The $external_mis_id variable must be set.', 'tvs-moodle-parent-provisioning' );
				$this->logger->error( $error );
				throw new InvalidArgumentException( $error );
			}

			$this->logger->debug( sprintf( __( 'Load Contact with external MIS ID %s from our database table \'%s\'.', 'tvs-moodle-parent-provisioning' ), $this->external_mis_id, $table_name ) );
		
			$row = $wpdb->get_row(
				$wpdb->prepare( "SELECT * FROM {$wpdb->prefix}{$table_name} WHERE external_mis_id = %s",
						$this->external_mis_id
				)
			);
			break;
		case 'mdl_user_id':
			if ( empty( $this->mdl_user_id ) || ! is_int( $this->mdl_user_id ) ) {
				$error = __( 'The $mdl_user_id variable must be set.', 'tvs-moodle-parent-provisioning' );
				$this->logger->error( $error );
				throw new InvalidArgumentException( $error );
			}

			$this->logger->debug( sprintf( __( 'Load Contact with Moodle user ID %d from our database table \'%s\'.', 'tvs-moodle-parent-provisioning' ), $this->mdl_user_id, $table_name ) );
		
			$row = $wpdb->get_row(
				$wpdb->prepare( "SELECT * FROM {$wpdb->prefix}{$table_name} WHERE mdl_user_id = %d",
						$this->mdl_user_id
				)
			);
			break;
		}
		
	
		if ( $row ) {
			$this->load_from_row( $row );	
			return true;
		}

		$this->logger->debug( sprintf( __( 'Did not succeed at fetching a database row for Contact %d. (This is our ID, not the MIS ID).', 'tvs-moodle-parent-provisioning' ), $this->id ) );
		return false;

	}

	/**
	 * Given the values in the input stdClass $row, load these into the properties of
	 * this object.
	 *
	 * @param $row stdClass A database row
	 */
	public function load_from_row( $row ) {
		$this->id = (int) $row->id;
		$this->mis_id = $row->mis_id;
		$this->external_mis_id = $row->external_mis_id;
		$this->mdl_user_id = $row->mdl_user_id;
		$this->title = $row->title;
		$this->forename = $row->forename;
		$this->surname = $row->surname;
		$this->email = $row->email;
		$this->status = $row->status;
		$this->staff_comment = $row->staff_comment;
		$this->system_comment = $row->system_comment;
		$this->date_created = $row->date_created;
		$this->date_updated = $row->date_updated;
		$this->date_approved = $row->date_approved;
		$this->date_synced = $row->date_synced;
	
		try {
			$this->load_mdl_user();
		}
		catch ( Exception $e ) {
			$this->logger->debug( sprintf( __( 'Failed to load Moodle user for %s. This is normal if the Moodle user has not yet been provisioned. %s', 'tvs-moodle-parent-provisioning' ), $this, $e->getMessage() ) );

			/* if we were unable to load the user, but they are apparently 'provisioned',
			 * they clearly are no longer provisioned and we will update the status to
			 * 'approved' which should cause provisioning to happen again. Eventual Consistency!
			 */
			if ( 'provisioned' == $this->status || 'partial' == $this->status ) {
				$this->logger->warning( sprintf(
					__( 'The Moodle user for %s could not be loaded, but the recorded status in our database is \'%s\'. This is inconsistent, so we will attempt to approve for provisioning again.', 'tvs-moodle-parent-provisioning' ),
					$this, $this->status
				) );
				$this->append_system_comment( sprintf( __('Could not load Moodle user for %s, but the recorded status was \'%s\'', 'tvs-moodle-parent-provisioning' ), $this->status ) );

				$this->status = 'pending';
				$this->save();
				$this->approve_for_provisioning();
			}

		}

		$this->logger->debug( sprintf( __( 'Loaded record for %s', 'tvs-moodle-parent-provisioning' ), $this->__toString() ) );

	}

	/**
	 * Load information about the Moodle user with the email address $this->email.
	 *
	 */
	public function load_mdl_user() {
		$this->mdl_user = new TVS_PMP_mdl_user( $this->logger, $this->dbc );
		$this->mdl_user->username = $this->email;
		if ( ! $this->mdl_user->load( 'username' ) ) {
			throw new InvalidArgumentException( sprintf( __( 'Unable to load Moodle user with username \'%s\'', 'tvs-moodle-parent-provisioning' ), $this->email ) );
		}
		$this->mdl_user_id = $this->mdl_user->id;
		$this->logger->debug( sprintf( __( 'Loaded Moodle user %s with ID %d', 'tvs-moodle-parent-provisioning' ), $this->mdl_user, $this->mdl_user_id ) );
	}

	/**
	 * Load all requests currently in the 'approved' state and return an array of objects of this type.
	 *
	 * @return array of TVS_PMP_Contact
	 */
	public static function load_all_approved( $logger, $dbc ) {
		return TVS_PMP_Contact::load_all( 'approved', $logger, $dbc );
	}

	/**
	 * Load all requests currently in the specified state and return an array of objects of this type.
	 * @param $state The status of the requests
	 *
	 * @return array of TVS_PMP_Contact
	 */
	public static function load_all( $state, $logger, $dbc ) {
		global $wpdb;

		$requests_raw = $wpdb->get_results( $wpdb->prepare(
			'SELECT id, mis_id, external_mis_id, mdl_user_id, title, forename, surname, email, status, staff_comment, system_comment, date_created, date_updated, date_approved, date_synced FROM ' . $wpdb->prefix .'tvs_parent_moodle_provisioning_contact WHERE status = %s',
			$state
		) );

		if ( count( $requests_raw ) < 1 ) {
			//$this->logger->info( __( 'No Contacts were fetched with the status %s', 'tvs-moodle-parent-provisioning' ), $state );
			return array();
		}

		$request_objs = array();

		foreach( $requests_raw as $row ) {
			$request = new TVS_PMP_Contact( $logger, $dbc );
			$request->load_from_row( $row );
			$request_objs[] = $request;
			//$this->logger->debug( __( 'Fetched %s', 'tvs-moodle-parent-provisioning' ), $state );
		}

		//$this->logger->debug( sprintf( __( 'Fetched %d Contacts', 'tvs-moodle-parent-provisioning' ), count( $request_objs ) ) );
		return $request_objs;

	}

	/**
	 * Load all Contacts that match the specified $search, or if $search is an empty string,
	 * load all Contacts. Supports manipulation of results in terms of $orderby, $order and
	 * pagination with $offset and $limit.
	 *
	 * @param string $orderby The field name by which to order results.
	 * @param string $order 'ASC' or 'DESC'
	 * @param int $offset Number of items to skip past (for pagination)
	 * @param int $limit Maximum number of items to return
	 * @param \Monolog\Logger\Logger $logger An instance of \Monolog\Logger\Logger for logging purposes.
	 * @param \mysqli $dbc A connection to the Moodle database.
	 * @param string $search The search query. Searches across fields for name.
	 * @param string $status Limit results to results with this 'status' in the DB, or empty string for all.
	 *
	 * @return array of TVS_PMP_Contact 
	 *
	 */
	public static function load_by_query( $orderby, $order, $offset, $limit, $logger, $dbc, $search = '', $status = '' ) {
		global $wpdb;


		if ( ! in_array( $orderby, TVS_PMP_Contact::$valid_field_names, true ) ) {
			throw new InvalidArgumentException(
				sprintf(
					__( '$orderby must be one of the valid field names: %s', 'tvs-moodle-parent-provisioning' ),
					implode(',', TVS_PMP_Contact::$valid_field_names )
				)
			);
		}

		if ( strlen( $status ) > 0 && ! in_array( $status, TVS_PMP_Contact::$statuses ) ) {
			throw new InvalidArgumentException(
				sprintf(
					__( '$status must be one of the valid statuses: %s', 'tvs-moodle-parent-provisioning' ),
					implode( ',', TVS_PMP_Contact::$statuses )
				)
			);
		}

		$order = strtoupper( $order );
		if ( ! in_array( $order, [ 'ASC', 'DESC' ], true ) ) {
			throw new InvalidArgumentException( __( '$order must be ASC or DESC.', 'tvs-moodle-parent-provisioning' ) );
		}

		$offset = intval( $offset );
		$limit = intval( $limit );

		if ( $limit < 1 ) {
			throw new InvalidArgumentException( __( '$limit must be an integer greater than or equal to 1.', 'tvs-moodle-parent-provisioning' ) );
		}
		if ( abs( $limit ) !== $limit || abs( $offset ) !== $offset ) {
			throw new InvalidArgumentException( __( '$limit and $offset must be positive integers.', 'tvs-moodle-parent-provisioning' ) );
		}


		$prepared_query = NULL;
		if ( strlen( $search ) < 1 ) {
			// no search

			// status != all
			if ( strlen( $status ) > 0 ) {
				$prepared_query = $wpdb->prepare(
					'SELECT id, mis_id, external_mis_id, mdl_user_id, title, forename, surname, email, status, staff_comment, system_comment, date_created, date_updated, date_approved, date_synced FROM ' . $wpdb->prefix . 'tvs_parent_moodle_provisioning_contact WHERE status = %s ORDER BY ' . $orderby . ' ' . $order . ' LIMIT %d, %d',
					$status, $offset, $limit
				);

			}
			else { // status == all
				$prepared_query = $wpdb->prepare(
					'SELECT id, mis_id, external_mis_id, mdl_user_id, title, forename, surname, email, status, staff_comment, system_comment, date_created, date_updated, date_approved, date_synced FROM ' . $wpdb->prefix . 'tvs_parent_moodle_provisioning_contact ORDER BY ' . $orderby . ' ' . $order . ' LIMIT %d, %d',
					$offset, $limit
				);
			}
		}
		else {
			// search query
			$like = '%' . $wpdb->esc_like( $search ) . '%';

			// status != all
			if ( strlen( $status ) > 0 ) {
				$prepared_query = $wpdb->prepare(
					'SELECT id, mis_id, external_mis_id, mdl_user_id, title, forename, surname, email, status, staff_comment, system_comment, date_created, date_updated, date_approved, date_synced FROM ' . $wpdb->prefix . 'tvs_parent_moodle_provisioning_contact WHERE status = %s AND (forename LIKE %s OR surname LIKE %s) ORDER BY ' . $orderby . ' ' . $order . ' LIMIT %d, %d',
					$status, $like, $like, $offset, $limit
				);

			}
			else { // status == all
				$prepared_query = $wpdb->prepare(
					'SELECT id, mis_id, external_mis_id, mdl_user_id, title, forename, surname, email, status, staff_comment, system_comment, date_created, date_updated, date_approved, date_synced FROM ' . $wpdb->prefix . 'tvs_parent_moodle_provisioning_contact WHERE forename LIKE %s OR surname LIKE %s ORDER BY ' . $orderby . ' ' . $order . ' LIMIT %d, %d',
					$like, $like, $offset, $limit
				);
			}
		}

		$request_objs = [];
		$requests_raw = $wpdb->get_results( $prepared_query );

		foreach( $requests_raw as $row ) {
			$request = new TVS_PMP_Contact( $logger, $dbc );
			$request->load_from_row( $row );
			$request_objs[] = $request;
		}

		$logger->debug( sprintf( __( 'Fetched %d Contacts', 'tvs-moodle-parent-provisioning' ), count( $request_objs ) ) );
		return $request_objs;
	}


	/**
	 * Return a count of the total number of Contacts that match this search
	 * query.
	 *
	 * @param string $search The search query. Searches across fields for name.
	 * @param string $status Limit results to results with this 'status' in the DB, or empty string for all.
	 *
	 *
	 * @return int The count of rows.
	 */
	public static function count_by_query( $search = '', $status = '' ) {
		global $wpdb;

		if ( strlen( $status ) > 0 && ! in_array( $status, TVS_PMP_Contact::$statuses ) ) {
			throw new InvalidArgumentException(
				sprintf(
					__( '$status must be one of the valid statuses: %s', 'tvs-moodle-parent-provisioning' ),
					implode( ',', TVS_PMP_Contact::$statuses )
				)
			);
		}

		$prepared_query = NULL;
		if ( strlen( $search ) < 1 ) {
			if ( strlen( $status ) > 0 ) {
				$prepared_query = $wpdb->prepare(
					'SELECT COUNT(id) FROM ' . $wpdb->prefix . 'tvs_parent_moodle_provisioning_contact WHERE status = %s',
					$status
				);
			}
			else {
				// no search, all status -- no parameters to prepare
				$prepared_query = 'SELECT COUNT(id) FROM ' . $wpdb->prefix . 'tvs_parent_moodle_provisioning_contact';
			}
		}
		else {
			// search query
			$like = '%' . $wpdb->esc_like( $search ) . '%';
			if ( strlen( $status ) > 0 ) {
				$prepared_query = $wpdb->prepare(
					'SELECT COUNT(id) FROM ' . $wpdb->prefix . 'tvs_parent_moodle_provisioning_contact WHERE status = %s AND (forename LIKE %s OR surname LIKE %s)',
					$status, $like, $like
				);

			}
			else {
				$prepared_query = $wpdb->prepare(
					'SELECT COUNT(id) FROM ' . $wpdb->prefix . 'tvs_parent_moodle_provisioning_contact WHERE forename LIKE %s OR surname LIKE %s',
					$like, $like
				);
			}
		}

		return $wpdb->get_var( $prepared_query );
	}


	/** 
	 * Save the data in this object to the database, either creating a new request or updating an existing one.
	 * New objects are **always** created with the status 'pending'.
	 */
	public function save() {
		global $wpdb;

		$table_name = $wpdb->prefix . TVS_PMP_Contact::$table_name;

		if ( empty( $this->id ) || ! is_int( $this->id ) ) {

			$this->logger->debug( __( 'ID was not set, so creating a new Contact.', 'tvs-moodle-parent-provisioning' ) );

			$this->date_created = date( 'Y-m-d H:i:s' );

			$affected_rows = $wpdb->insert(
				$table_name,
				array(
					'mis_id'          => intval( $this->mis_id ),
					'external_mis_id' => stripslashes( $this->external_mis_id ),
					'title'           => stripslashes( $this->title ),
					'forename'        => stripslashes( $this->forename ),
					'surname'         => stripslashes( $this->surname ),
					'email'           => stripslashes( strtolower( $this->email ) ),
					'date_created'    => stripslashes( $this->date_created ),
					'status'          => 'pending',
				),
				array(
					'%d',               // mis_id
					'%s',               // external_mis_id
					'%s',               // title
					'%s',               // fname
					'%s',               // sname
					'%s',               // email
					'%s',               // date_created
					'%s'                // status
				)
			);

			$this->logger->debug( sprintf( __( 'Affected rows when creating Contact: %d', 'tvs-moodle-parent-provisioning' ), $affected_rows ) );

			if ( $affected_rows !== false ) {
				$this->id = $wpdb->insert_id;
				$this->logger->info( sprintf( __( 'Created a new Contact %s.', 'tvs-moodle-parent-provisioning' ), $this->__toString() ) );
			}

			$this->status = 'pending';

			try {
				$this->load_mdl_user();
			}
			catch ( Exception $e ) {
				// this may fail, because the user is not provisioned yet
				$this->logger->debug( sprintf( __( 'Failed to load Moodle user for %s. This is normal if the Moodle user has not yet been provisioned. %s', 'tvs-moodle-parent-provisioning' ), $this, $e->getMessage() ) );
			}

			return $affected_rows;

		}
		else {
			$this->logger->debug( sprintf( __( 'ID was set, so updating %s.', 'tvs-moodle-parent-provisioning' ), $this->__toString() ) );

			if ( !$this->mdl_user_id ) {
				$this->logger->info( sprintf( __( 'No mdl_user_id was available at the time of saving %s. This is normal if the Moodle user has not synced yet. Will try to load this information.', 'tvs-moodle-parent-provisioning' ), $this->__toString() ) );
				
				// it's possible our Contact entry exists but the Moodle user still is not provisioned (script not run?), so we must catch this
				try {
					$this->load_mdl_user();
				}
				catch ( Exception $e ) {
					$this->logger->debug( sprintf( __( 'Failed to load Moodle user for %s. This is normal if the Moodle user has not yet been provisioned. %s', 'tvs-moodle-parent-provisioning' ), $this, $e->getMessage() ) );
				}
			}

			$this->date_synced = date( 'Y-m-d H:i:s' );
			$this->date_updated = date( 'Y-m-d H:i:s' ); //TODO determine if any property of substance actually changed and only update if so

			$this->logger->debug( sprintf( 'date_created: %s', $this->date_created ) );

			$affected_rows = $wpdb->update(
				( $wpdb->prefix . TVS_PMP_Contact::$table_name ),
				array(
					'mis_id'          => intval( $this->mis_id ),
					'external_mis_id' => stripslashes( $this->external_mis_id ),
					'mdl_user_id'     => intval( $this->mdl_user_id ),
					'title'           => stripslashes( $this->title ),
					'forename'        => stripslashes( $this->forename ),
					'surname'         => stripslashes( $this->surname ),
					'email'           => stripslashes( strtolower( $this->email ) ),
					'date_created'    => stripslashes( $this->date_created ),
					'date_updated'    => stripslashes( $this->date_updated ),
					'date_approved'   => stripslashes( $this->date_approved ),
					'date_synced'     => stripslashes( $this->date_synced ),
					'status'          => stripslashes( $this->status ),
				),
				array(
					'id'		  => $this->id
				),
				array(
					'%d',               // mis_id
					'%s',               // external_mis_id
					'%d',               // mdl_user_id
					'%s',               // title
					'%s',               // forename
					'%s',               // surname
					'%s',               // email
					'%s',               // date_created
					'%s',               // date_updated
					'%s',               // date_approved
					'%s',               // date_synced
					'%s'                // status
				),
				array(
					'%d'		    // id
				)
			);

			if ( NULL === $this->mdl_user ) {

				try {
					$this->load_mdl_user();
				}
				catch ( Exception $e ) {
					// this may fail, because the user is not provisioned yet
					$this->logger->debug( sprintf( __( 'Failed to load Moodle user for %s. This is normal if the Moodle user has not yet been provisioned. %s', 'tvs-moodle-parent-provisioning' ), $this, $e->getMessage() ) );
				}

			}

			$this->logger->debug( sprintf( __( 'Updated %s. Affected rows: %d', 'tvs-moodle-parent-provisioning' ), $this->__toString(), $affected_rows ) );
			return $affected_rows;

		}
	}


	/** 
	 * Approve this request for provisioning, or approve it for processing to update the attached pupil connections. The object
	 * must already exist in the database with a status of 'pending'.
	 */
	public function approve_for_provisioning() {
		global $wpdb;

		if ( empty( $this->id ) || ! is_int( $this->id ) ) {
			throw new InvalidArgumentException( __( 'The $id variable must be set to a non-zero integer.', 'tvs-moodle-parent-provisioning' ) );
		}

		if ( empty( $this->status ) || $this->status != 'pending' ) {
			throw new InvalidArgumentException( __( 'An account request can only be approved for provisioning from the \'pending\' status.', 'tvs_moodle_parent_provisioning' ) );
		}

		$new_status = 'approved';
		$approved_text = sprintf( __( '%s Approved for provisioning at %s -- awaiting next provision cycle', 'tvs-moodle-parent-provisioning' ), $this->__toString(), date('j F Y H:i:s T'));
		$this->logger->info( $approved_text );
		$this->append_system_comment( $approved_text );
		$this->status = $new_status;
		$this->save();

		// check for pre-existence of a parent with this email
		$exists = $wpdb->get_results( $wpdb->prepare(
			'SELECT id FROM ' . $wpdb->prefix . 'tvs_parent_moodle_provisioning_auth WHERE parent_email = %s',
			array(
				$this->email
			)
		) );

		if ( count( $exists ) > 0 ) {
			$new_status = 'duplicate';
	
			$dupl_comment = sprintf( __( 'Unable to provision %s, as email address \'%s\' already exists in external users table -- %s', 'tvs-moodle-parent-provisioning' ), $this->__toString(), $this->email, date( 'j F Y H:i:s T') );
			$this->status = $new_status;
			$this->save();
			$this->append_system_comment( $dupl_comment );
			      
			
			throw new TVS_PMP_Duplicate_Exception(
				sprintf( __( 'Unable to provision %s, as email address already exists in external users table. Marked as duplicate.', 'tvs-moodle-parent-provisioning' ), $this->__toString() )
				);
	
		}

                // add to external Moodle auth table and wait there until the next cron-initiated provision cycle
                $username = strtolower( $this->email );
                $title = $this->title;
		if ( 'forename-contains-title' == get_option( 'tvs-moodle-parent-provisioning-moodle-name-format' ) ) {
			$forename = $this->title . ' ' . $this->forename;
		}
		else {
			$forename = $this->forename;
		}
                $surname = $this->surname;
                $email = strtolower( $this->email );
                $description = __( 'Parent Moodle Account', 'tvs-moodle-parent-provisioning' );

                // add to external Moodle table

                $response = $wpdb->insert( $wpdb->prefix . 'tvs_parent_moodle_provisioning_auth',
                        array(
                                'username'        =>  $username,
                                'parent_title'    =>  $title,
                                'parent_fname'    =>  $forename,
                                'parent_sname'    =>  $surname,
                                'parent_email'    =>  $email,
                                'description'     =>  $description,
				'request_id'      =>  $this->id,
                        ),
                        array(
                                '%s',
                                '%s',
                                '%s',
                                '%s',
                                '%s',
                                '%s',
				'%d'
                        )
                );

		$this->logger->debug( sprintf( __( 'Added %d row to the auth table for %s', 'tvs-moodle-parent-provisioning' ), $response, $this->__toString() ) );

		return $response;

	}

	/** 
	 * Append the specified string to the system_comment field of this request.
	 */
	public function append_system_comment( $comment ) {
		global $wpdb;

		if ( ! $this->id ) {
			throw new Exception( __( 'Cannot append system_comment when the object is not yet initialised. Use the load() method.', 'tvs-moodle-parent-provisioning' ) );
		}

		$new_sys_comment = $this->system_comment . PHP_EOL . $comment;

		$this->system_comment = $new_sys_comment;

		return $wpdb->update( 	$wpdb->prefix .'tvs_parent_moodle_provisioning_contact',
			array(
				'system_comment'		=> $new_sys_comment,
				'date_updated'			=> date('Y-m-d H:i:s')
			), array(
				'id'				=> $this->id
			), array(
				'%s',
				'%s'
			), array(
				'%d'
			)	
		);

	}

	/*
	 * De-provision this account request, marking it with the specified status and
	 * removing the entry from the auth table. This will have the effect of preventing
	 * login to the account, but does not remove any data.
	 *
	 * @param string $status The new status to set for the request.
	 */
	public function deprovision( $status ) {
		global $wpdb;

		if ( ! $this->id ) {
			throw new Exception( __( 'Cannot deprovision when the object is not yet initialised. Use the load() method.', 'tvs-moodle-parent-provisioning' ) );
		}

		if ( ! in_array( $status, TVS_PMP_Contact::$deprovisioned_statuses ) ) {
			throw new InvalidArgumentException(
				sprintf( __( 'The provided status %s must be one of the statuses that are valid for deprovisioned accounts. Valid statuses are: %s', 'tvs-moodle-parent-provisioning' ),
				$status,
				implode( ';', TVS_PMP_Contact::$deprovisioned_statuses )
			) );
		}

                $response = $wpdb->delete( $wpdb->prefix . 'tvs_parent_moodle_provisioning_auth',
                        array(
				'request_id'      =>  $this->id,
                        ),
                        array(
                                '%d',
                        )
                );

		if ( $response === false ) {
			throw new Exception( __( 'Failed to delete the row from the auth table.', 'tvs-moodle-parent-provisioning' ) );
		}
		if ( $response < 1 )  {
			throw new Exception( sprintf( __( '%d rows were affected when trying to delete the row from the auth table.', 'tvs-moodle-parent-provisioning' ), $response ) );
		}

		// removed from auth table successfully

		$before_tz = "";
		$this->set_timezone_for_wp( $before_tz );
		$this->append_system_comment( sprintf(
			__( 'De-provisioned at %s %s. Status set to \'%s\'.', 'tvs-moodle-parent-provisioning' ),
			date_i18n( get_option( 'date_format' ), time() ),
			date_i18n( get_option( 'time_format' ), time() ),
			$status
		) );
		$this->unset_timezone_for_wp( $before_tz );
		
		$this->status = $status;
		$this->save();

	}


	/**
	 * Determine whether or not an associated mdl_user exists for this Contact.
	 *
	 * @return bool
	 */
	public function does_mdl_user_exist() {
		return !( $this->mdl_user->is_orphaned() );
	}

	/**
	 * Retrieve all the Contact Mappings associated with this Contact.
	 *
	 * @param $force_reload bool Whether or not to force reloading the Mappings from the database, ignoring the cache.
	 *
	 * @return array of TVS_PMP_Contact_Mapping
	 */
	public function get_contact_mappings( $force_reload = false ) {
		global $wpdb;

		if ( count( $this->contact_mappings ) > 0 && ! $force_reload ) {
			$this->logger->debug( sprintf( __( 'Return %d cached Contact Mappings', 'tvs-moodle-parent-provisioning' ), count( $this->contact_mappings ) ) );
			return $this->contact_mappings;
		}
		$this->contact_mappings = [];

		$table_name = TVS_PMP_Contact_Mapping::$table_name;
		$query = $wpdb->prepare(
			"SELECT * FROM {$wpdb->prefix}{$table_name} WHERE contact_id = %d",
			$this->id
		);	

		$results = $wpdb->get_results( $query );

		if ( count( $results ) > 0) {
			$this->logger->debug( sprintf( __( 'Fetched %d Contact Mappings associated with %s', 'tvs-moodle-parent-provisioning' ), count( $results ), $this->__toString() ) );

			foreach( $results as $result ) {
				try {
					$contact_mapping = new TVS_PMP_Contact_Mapping( $this->logger, $this->dbc, $this );
					$contact_mapping->load_from_row( $result );
					$this->contact_mappings[] = $contact_mapping;
				}
				catch ( Exception $e )  {
					$this->logger->warning(
						sprintf( __( 'Could not load Contact Mapping %d. Exception was \'%s\'', 'tvs-moodle-parent-provisioning' ), $result->id, $e->getMessage() )
					);
				}
			}

			return $this->contact_mappings;
		}

		$this->logger->debug( sprintf( __( 'No Contact Mappings for %s', 'tvs-moodle-parent-provisioning' ), $this->__toString() ) ) ;
		return $this->contact_mappings;

	}


	/**
	 * Add a new Contact Mapping to connect this Contact with a Moodle user (pupil).
	 * 
	 * @param string $adno The Admissions Number, or Moodle idnumber, for which to add the Mapping
	 *
	 * @return bool Success
	 */
	public function add_contact_mapping_by_adno( $adno, $mis_id, $mis_external_id, $username ) {
		// try and get a Moodle user by adno

		$user = new TVS_PMP_mdl_user();
		$user->idnumber = $adno;

		if ( ! $user->load( 'idnumber' ) ) {
			throw new InvalidArgumentException( sprintf( __( 'Unable to find a matching Moodle user with idnumber (Adno) %d', 'tvs-moodle-parent-provisioning' ), $adno ) );
		}

		// find any existing contact mapping with this Adno
		foreach( $this->get_contact_mappings() as $mapping ) {
			if ( $adno == $mapping->get_adno() ) {
				$this->logger->debug(
					sprintf(
						__( 'Contact Mapping for %d already exists: %s', 'tvs-moodle-parent-provisioning' ), $adno, $mapping 
					)
				);

				return $mapping->map();

			}
		}

		$mapping = new TVS_PMP_Contact_Mapping( $this->logger, $this->dbc, $this );
		$mapping->contact_id = $this->id;
		$mapping->mis_id = $mis_id;
		$mapping->external_mis_id = $external_mis_id;
		$mapping->adno = $adno;
		$mapping->username = $username;
		$mapping->mdl_user = $user;
		$mapping->mdl_user->idnumber = $adno;
		$mapping->mdl_user_id = $mapping->mdl_user->id;

		if ( ! $mapping->save() ) {
			throw new Exception( sprintf( __( 'Unable to save the Contact Mapping for %d (%s). The save() to the internal database failed', 'tvs-moodle-parent-provisioning' ), $adno, $username ) );
		}
	
		return $mapping->map();
	}

	/**
	 * Remove the Contact Mapping associated with this Contact, using the Admissions Number
	 * (idnumber) of the target user.
	 */
	public function remove_contact_mapping_by_adno( $adno ) {
		// find existing contact mapping with this Adno
		foreach( $this->get_contact_mappings() as $mapping ) {
			if ( $adno == $mapping->get_adno() ) {
				$this->logger->debug( sprintf( __( 'Found mapping \'%s\'. Will unmap and delete.', 'tvs-moodle-parent-provisioning' ), $mapping ) );
				$mapping->unmap();
				return $mapping->delete();
			}
		}

		$this->logger->warning( sprintf( __( 'Did not find a Contact Mapping to match Admissions Number (idnumber) %s', 'tvs-moodle-parent-provisioning' ), $adno ) );
	}

	/*
	 * Permanently delete the request entirely from the system. Note that this should not be
	 * used for merely deprovisioning an account.
	 */
	public function delete() {
		global $wpdb;

		if ( count( $this->get_contact_mappings( true ) ) > 0 ) {
			throw new LogicException( sprintf( __( 'Cannot delete a Contact \'%s\' when Contact Mappings still exist for it.',  'tvs-moodle-parent-provisioning' ), $this->__toString() ) );
		}

		// reload mdl user for status
		$this->load_mdl_user();

		// refuse to operate if still provisioned
		if ( $this->is_provisioned_and_enabled() ) {
			throw new LogicException( sprintf( __( 'Cannot delete a Contact \'%s\' as the user is still provisioned and enabled within Moodle, or because the status of the Contact within this database is considered approved or provisioned.', 'tvs-moodle-parent-provisioning' ), $this->__toString() ) );
		}


		// will have been removed from auth already by deprovisioning
		//
                $response = $wpdb->delete( $wpdb->prefix . 'tvs_parent_moodle_provisioning_contact',
                        array(
				'id'      =>  $this->id,
                        ),
                        array(
                                '%d',
                        )
                );

		return $response;

	}

	/**
	 * Determine whether the associated Moodle user is provisioned and enabled.
	 *
	 * @return bool
	 */
	public function is_provisioned_and_enabled() {
		if ( $this->does_mdl_user_exist() && 1 != $this->mdl_user->suspended )	 {
			$this->logger->debug( sprintf( __( 'Moodle user for %s exists and is not suspended.', 'tvs-moodle-parent-provisioning' ), $this->__toString() ) );
			return true;
		}
		// or if in any non-de-provisioned status
		
		if ( 'approved' == $this->status || 'provisioned' == $this->status ) {
			$this->logger->debug( sprintf( __( 'Status of %s is %s', 'tvs-moodle-parent-provisioning' ), $this->__toString(), $this->status ) );
			return true;
		}

		return false;
	}

	/**
	 * Find all the "Contexts to Add Role" contexts specified in the plugin settings,
	 * and assign the role in each of these contexts to the specified user.
	 *
	 * @return boolean Success or failure
	 */
	public function ensure_role_in_static_contexts() {
		
		// get the list of static contexts
		$contexts_raw = get_option( 'tvs-moodle-parent-provisioning-contexts-to-add-role' );


		$this->logger->debug( __( 'Will now ensure the role assignments for all static contexts are created.', 'tvs-moodle-parent-provisioning' ) );

		if ( ! $contexts_raw ) {
			$this->logger->warning( __( 'There were no "Contexts to Add Role" found from the plugin settings. Therefore, we have no static contexts to set. Review the plugin Settings to ensure this is correct.', 'tvs-moodle-parent-provisioning' ) );
			return true;
		}

		// split string by newlines
		$contexts = explode( "\n", $contexts_raw );

		if ( ! is_array( $contexts ) || count( $contexts ) < 1 ) {
			$this->logger->warning( __( 'There were no "Contexts to Add Role" found from the plugin settings, although the option was found. Therefore, we have no static contexts to set. Review the plugin Settings to ensure this is correct.', 'tvs-moodle-parent-provisioning' ) );
			return true;
		}

		foreach( $contexts as $context ) {

			$context = trim( $context );
			
			if ( ! ctype_digit( $context ) ) {
				$this->logger->warning( sprintf( __( 'Ignoring %s as it contains extraneous non-numeric characters. The context IDs must be integer values only.', 'tvs-moodle-parent-provisioning' ), $context ) );
				continue;
			}

			$context_id = (int) $context;

			if ( ! $context_id ) {
				$this->logger->warning( __( 'Ignoring %s as it evaluates to false after casting to an integer.', 'tvs-moodle-parent-provisioning' ), $context );
				continue;

			}

			// ensure the lazy-loaded target_role_id is accessible from TVS_PMP_Contact_Mapping class
			$blank_mapping = new TVS_PMP_Contact_Mapping( $this->logger, $this->dbc, $this );

			// check for existing role assignment
			$role_assignment = $this->mdl_user->get_role_assignment( TVS_PMP_Contact_Mapping::$target_role_id, $context_id );

			if ( ! $role_assignment ) {
				$this->logger->info( sprintf( __( 'Will add role assignment for parent %d for context %d', 'tvs-moodle-parent-provisioning' ), $this->mdl_user->id, $context_id ) );
				$this->mdl_user->add_role_assignment( TVS_PMP_Contact_Mapping::$target_role_id, $context_id, TVS_PMP_Contact::$modifier_id, '', 0, 0 ); 
			}
			else {
				$this->logger->debug( sprintf( __( 'Parent with ID %d already had a role assignment for context %d', 'tvs-moodle-parent-provisioning' ), $this->mdl_user->id, $context_id ) );
			}

		}

		$this->logger->debug( __( 'Completed ensuring role assignments for static contexts.', 'tvs-moodle-parent-provisioning' ) );

		return true;

	}

	/*
	 * Set the current timezone in PHP to the WordPress timezone so that we are able
	 * to add accurate timestamps to comments and modified dates etc.
	 *
	 * Please call unset_timezone_for_wp() when done.
	 */
	protected function set_timezone_for_wp( &$before_tz ) {
		$before_tz = @date_default_timezone_get(); 
		$wp_tz = get_option( 'timezone_string' );
		if ( strlen( $wp_tz ) > 0 ) {
			date_default_timezone_set( $wp_tz );
		}
	}

	/*
	 * Restore timezone settings to normal after using set_timezone_for_wp().
	 */
	protected function unset_timezone_for_wp( $before_tz ) {
		date_default_timezone_set( $before_tz );
	}


	/**
	 * Get the current count of how many requests are in the 'pending' status.
	 *
	 * @return int The number of requests in pending status
	 */
	public static function get_pending_count() {
		global $wpdb;

		$output = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(id) FROM {$wpdb->prefix}tvs_parent_moodle_provisioning WHERE status = %s",	
			'pending')
		);

		return $output;
	}

	/**
	 * Update the transient that stores the current pending request count.
	 */
	public static function update_pending_count() {
		set_transient( 'tvs-moodle-parent-provisioning-pending-requests', TVS_PMP_Contact::get_pending_count(), 3600 );
	}

	/**
	 * Lazily load a TVS_PMP_MDL_DB_Helper instance as soon as we need one.
	 */
	protected function get_mdl_db_helper() {
		if ( $this->mdl_db_helper instanceof TVS_PMP_MDL_DB_Helper ) {
			return $this->mdl_db_helper;
		}
		$this->mdl_db_helper = new TVS_PMP_MDL_DB_Helper( $this->logger, $this->dbc );
		return $this->mdl_db_helper;
	}


	/**
	 * Update the email address and associated username of the Moodle user associated with
	 * this Contact
	 */
	public function update_email_address( $new_email ) {
		global $wpdb;
		$this->load_mdl_user();

		$new_email = strtolower( $new_email );

		$rows = $this->mdl_user->set_email_address_and_username( $new_email );		

		if ( $rows !== 1 ) {
			throw new Exception( sprintf( __( 'Unexpected result from TVS_PMP_mdl_user::set_email_address_and_username. Affected rows was %d and should have been 1.', 'tvs-moodle-parent-provisioning' ), $rows ) );
		}

		// must update the auth table so that Moodle does not suspend the user
		$rows = $wpdb->update( 
			$wpdb->prefix . 'tvs_parent_moodle_provisioning_auth',
			[ /* data */
				'username'      => $new_email,
				'parent_email'  => $new_email
			],
			[ /* where */
				'request_id'    => $this->id
			],
			[ /* data_format */
				'%s',
				'%s'
			],
			[ /* where_format */
				'%d'
			]
		);

		if ( $rows !== 1 ) {

			$this->logger->error( __( 'Rolling back mdl_user email address change since the auth table update failed.', 'tvs-moodle-parent-provisioning' ) );
			// roll back the email address change
			$this->mdl_user->set_email_address_and_username( $this->email );

			throw new Exception( sprintf( __( 'Unexpected result from updating the auth table. Affected rows %d and should have been 1.', 'tvs-moodle-parent-provisioning' ), $rows ) );
		}

		$this->logger->info( sprintf( __( 'Updated auth table with new email address \'%s\' for %s. Affected rows: %d', 'tvs-moodle-parent-provisioning' ), $new_email, $this->__toString(), $rows ) );
		$this->append_system_comment( sprintf( __( 'Updated email address and Moodle username from \'%s\' to \'%s\'. [%s]', 'tvs-moodle-parent-provisioning' ), $this->email, $new_email, date( 'Y-m-d H:i:s' ) ) );

		// must reload the mdl_user to see the changes
		$this->email = $new_email;


		$this->load_mdl_user();
		$this->save(); // save Contact record with the new email address
	}


	/**
	 * Return a string representation of the object.
	 */
	public function __toString() {
		return sprintf( __( '[Contact]%d: MIS ID: %d, external MIS ID: %s, %s %s %s', 'tvs-moodle-parent-provisioning' ), $this->id, $this->mis_id, $this->external_mis_id, $this->title, $this->forename, $this->surname );
	}


};

/**
 * Allow us to throw a custom exception for duplicate parent accounts.
 */
class TVS_PMP_Duplicate_Exception extends Exception {
	public function __construct( $message, $code = 0, Exception $previous = null ) {
		parent::__construct( $message, $code, $previous );
	}
}
