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

