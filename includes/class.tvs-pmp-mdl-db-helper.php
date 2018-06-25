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
        * Add a role assignment in the specified context to the specified user.
        *
        * @param int roleid The role ID to assign. Usually will be the parent role ID.
        * @param int contextid The contextid is linked to a category, course or other entity within Moodle. It can be determined
	* by going to assign roles and checking for the contextid in the URL.
        * @param int modifierid The user ID considered to have made the change for audit purposes.
        * @param string component Optional string for the Moodle component that added the role assignment.
        * @param int itemid
        * @param int sortorder
        *
        * @return int The ID of the new role assignment.
        */
       public function add_role_assignment( $userid, $roleid, $contextid, $modifierid, $component = '', $itemid = 0, $sortorder = 0 ) {
               $this->logger->debug( sprintf( __( 'Add role assignment for user %d with role %d in context %d', 'tvs-moodle-parent-provisioning' ), $this->id, $roleid, $contextid ) );

               $stmt = $this->dbc->prepare( "INSERT INTO {$this->dbprefix}role_assignments ( roleid, contextid, userid, timemodified, modifierid, component, itemid, sortorder ) VALUES ( ?, ?, ?, ?, ?, ?, ?, ? )" );

               if ( ! $stmt ) {
                       throw new Exception( sprintf( __( 'Failed to prepare the database statement to add a role assignment. Error: %s', 'tvs-moodle-parent-provisioning' ), $this->dbc->error ) );
               }

               $time = time();
               $stmt->bind_param( 'iiiiisii', $roleid, $contextid, $userid, $time, $modifierid, $component, $itemid, $sortorder );
               $stmt->execute();
               $stmt->store_result();

               $this->logger->info( sprintf( __( 'Added role assignment %d: role %d for user %d in context %d. Affected rows: %d', 'tvs-moodle-parent-provisioning' ), $stmt->insert_id, $roleid, $userid, $contextid, $stmt->affected_rows ) );

               $rows = $stmt->affected_rows;
	       $new_id = $stmt->insert_id;

               $stmt->close();

               return $new_id;
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
        * Determine if a role assignment exists for the specified user in the given context. If so, return the role assignment id.
	*
	* @param int userid The Moodle user ID
        * @param int roleid Typically this will be the ID of the parent role
        * @param int contextid The contextid is linked to a category, course or other entity within Moodle. It can be determined by going to assign roles and checking for the contextid in the URL.
        *
        * @return int role assignment id, or 0 if no results
        */
       public function get_role_assignment( $userid, $roleid, $contextid ) {

               $this->logger->debug( sprintf( __( 'Determine role assignment for user %d with role %d in context %d', 'tvs-moodle-parent-provisioning' ), $this->id, $roleid, $contextid ) );

               $stmt = $this->dbc->prepare( "SELECT id FROM {$this->dbprefix}role_assignments WHERE roleid = ? AND contextid = ? AND userid = ?" );

               if ( ! $stmt ) {
                       throw new Exception( sprintf( __( 'Failed to prepare the database statement to get role assignments. Error: %s', 'tvs-moodle-parent-provisioning' ), $this->dbc->error ) );
               }

               $stmt->bind_param( 'iii', $roleid, $contextid, $userid );
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
	

};

