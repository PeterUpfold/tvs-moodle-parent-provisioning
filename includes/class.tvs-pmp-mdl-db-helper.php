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
 * A helper class for performing common actions on the Moodle database.
 */

require_once( dirname( __FILE__ ) . '/../vendor/autoload.php' );

use Monolog\Logger;
use Monolog\ErrorHandler;
use Monolog\Handler\StreamHandler;
use Monolog\Handler\BrowserConsoleHandler;

/**
 * A helper class for performing common actions on the Moodle database.
 */
class TVS_PMP_MDL_DB_Helper {
	/**
	 * A context which is confined to a particular user account.
	 */
	const CONTEXT_USER = 30;

	/**
	 * A context confined to a course category.
	 */
	const CONTEXT_COURSECAT = 40;

	/**
	 * A context confined to a particular course.
	 */
	const CONTEXT_COURSE = 50;

	/**
	 * A context confined to a particular activity module, such as a File or Questionnaire.
	 */
	const CONTEXT_MODULE = 70;

	/**
	 * A context confined to a particular block in a particular course.
	 */
	const CONTEXT_BLOCK = 80;

	/**
	 * Reference to a mysqli database object we can use to manipulate the Moodle database.
	 */
	public $dbc = NULL;

	/**
	 * Reference to a Monolog\Logger\Logger that we will use for writing log entries.
	 */
	public $logger = NULL;


	/**
	 * Create the object.
	 */
	public function __construct( $logger, $dbc ) {
		$this->logger = $logger;
		$this->dbc = $dbc;
	}

	/**
	 * Create an instance of \Monolog\Logger\Logger that we can use to log information about
	 * the execution of this program.
	 *
	 * @return \Monolog\Logger\Logger
	 */
	public static function create_logger( &$local_log_stream ) {
		$logger = new Logger( 'tvs-pmp-provisioner' );

		$log_level = ( 'info' == get_option( 'tvs-moodle-parent-provisioning-log-level' ) ) ? Logger::INFO : Logger::DEBUG;
		
		$logger->pushHandler( new StreamHandler( get_option( 'tvs-moodle-parent-provisioning-log-file-path' ) , $log_level ) ); 
		$local_log_stream = fopen( 'php://memory', 'w+' );
		$logger->pushHandler( new StreamHandler( $local_log_stream ), $log_level );

		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			$logger->pushHandler( new BrowserConsoleHandler() );
		}

		ErrorHandler::register( $logger );

		return $logger;
	}

	/**
	 * Create a mysqli object for communicating with the Moodle database.
	 *
	 * @return mysqli
	 */
	public static function create_dbc( $logger ) {
		$set_prefix = 'tvs-moodle-parent-provisioning-';

		$dbc = new mysqli(
			get_option( $set_prefix . 'moodle-dbhost' ),
			get_option( $set_prefix . 'moodle-dbuser' ),
			get_option( $set_prefix . 'moodle-dbpass' ),
			get_option( $set_prefix . 'moodle-db' )
		);

		if ( $dbc->connect_error !== NULL ) {
			$logger->error( sprintf ( __( 'Failed to initialise the database object. Connection error %d: %s', 'tvs-moodle-parent-provisioning' ), $this->dbc->connect_errno, $this->dbc->connect_error ) );
			if ( php_sapi_name() != 'cli' ) {
				//echo sprintf ( __( 'Failed to initialise the database object. Connection error %d: %s', 'tvs-moodle-parent-provisioning' ), $this->dbc->connect_errno, $this->dbc->connect_error );
			}
			return NULL;
		}

		return $dbc;
	}

	/**
	 * Determine if a context exists and return its numeric ID.
	 *
	 * @param int contextlevel The scope of the context. Pass one of the CONTEXT_ constants defined in this class.
	 * @param int instanceid The ID of the instance to which this context relates -- a user ID, course category ID, etc. Which of these identifiers this represents is depends upon the contextlevel.
	 * @param int depth How deep into the context tree to search
	 *
	 * @return int The ID of the context, or 0 if no results are found
	 */
	public static function get_context( $contextlevel, $instanceid, $depth ) {
		$this->logger->debug( sprintf( __( 'Determine context for contextlevel %d with instance ID %d and depth %d', 'tvs-moodle-parent-provisioning' ), $contextlevel, $instanceid, $depth ) );

		$stmt = $this->dbc->prepare( "SELECT id FROM {$this->dbprefix}context WHERE contextlevel = ? AND instanceid = ? AND depth = ?" );

		if ( ! $stmt ) {
			throw new Exception( sprintf( __( 'Failed to prepare the database statement to get context. Error: %s', 'tvs-moodle-parent-provisioning' ), $this->dbc->error ) );
		}

		$stmt->bind_param( 'iii', $contextlevel, $instanceid, $depth );
		$stmt->execute();
		$stmt->store_result();

		if ( $stmt->num_rows < 1 ) {
			// no results
			$this->logger->info( sprintf( __( 'A context for contextlevel %d with instance ID %d and depth %d does not currently exist.', 'tvs-moodle-parent-provisioning' ), $contextlevel, $instanceid, $depth ) );
			return 0;
		}

		$stmt->bind_result( $context_id );
		$stmt->fetch();
		$stmt->close();

	}
	/**
        * Remove the specified role assignment by its ID.
        *
        * @param int id The role assignment ID to remove.
        *
        * @return int The number of rows affected.
        */
       public function remove_role_assignment( $id ) {
               $this->logger->debug( sprintf( __( 'Remove role assignment %d', 'tvs-moodle-parent-provisioning' ), $id ) );

               $stmt = $this->dbc->prepare( "DELETE FROM {$this->dbprefix}role_assignments WHERE id = ?" );

               if ( ! $stmt ) {
                       throw new Exception( sprintf( __( 'Failed to prepare the database statement to remove a role assignment. Error: %s', 'tvs-moodle-parent-provisioning' ), $this->dbc->error ) );
               }

               $stmt->bind_param( 'i', $id );
               $stmt->execute();
               $stmt->store_result();

               $this->logger->info( sprintf( __( 'Remove role assignment %d. Affected rows: %d', 'tvs-moodle-parent-provisioning' ), $id, $stmt->affected_rows ) );

               $rows = $stmt->affected_rows;

               $stmt->close();

               return $rows;
       }

	/**
	 * Add a new context and return the new context ID.
	 *
	 * @param int contextlevel The scope of the context. Pass one of the CONTEXT_ constants defined in this class.
	 * @param int instanceid The ID of the instance to which this context relates -- a user ID, course category ID, etc. Which of these identifiers this represents is depends upon the contextlevel.
	 * @param int depth How deep into the context tree to insert
	 *
	 * @return int The ID of the new context
	 */
	public function add_context( int $contextlevel, int $instanceid, int $depth ) {
		$this->logger->debug( sprintf( __( 'Add context for contextlevel %d with instance ID %d and depth %d', 'tvs-moodle-parent-provisioning' ), $contextlevel, $instanceid, $depth ) );

		$stmt = $this->dbc->prepare( "INSERT INTO {$this->dbprefix}context (contextlevel, instanceid, depth) VALUES (?, ?, ?)" );

		if ( ! $stmt ) {
			throw new Exception( sprintf( __( 'Failed to prepare the database statement to add context. Error: %s', 'tvs-moodle-parent-provisioning' ), $this->dbc->error ) );
		}

		$stmt->bind_param( 'iii', $contextlevel, $instanceid, $depth );
		$stmt->execute();

		$new_id = $stmt->insert_id;
		$stmt->close();
		$this->logger->info( sprintf( __( 'Returned new context ID is %d', 'tvs-moodle-parent-provisioning' ), $new_id ) );
	
		return $new_id;	

	}


};

