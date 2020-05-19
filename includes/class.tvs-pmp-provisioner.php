<?php
/*/* Copyright (C) 2016-2020 Test Valley School.


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
 * A controller class for performing the provisioning of new Moodle parent
 * accounts. Typically invoke via CLI via cron.
 */

require_once( dirname( __FILE__ ) . '/../vendor/autoload.php' );

use Monolog\Logger;
use Monolog\ErrorHandler;
use Monolog\Handler\StreamHandler;
use Monolog\Handler\BrowserConsoleHandler;

class TVS_PMP_Provisioner {

	/**
	 * The base URL of the target Moodle installation.
	 */
	protected $moodle_baseurl = '';

	/** 
	 * The base path of the target Moodle installation's PHP files. (not moodledata!)
	 */
	protected $moodle_basepath = '';

	/**
	 * A mysqli object we can use to connect to the Moodle database.
	 */
	protected $dbc = NULL;

	/**
	 * The database prefix for the tables in the Moodle database 
	 */
	protected $dbprefix = 'mdl_';

	/** 
	 * The Unix user account to use for invoking various Moodle scheduled tasks to trigger the creation of 
	 * new user accounts.
	 */
	protected $sudo_account = '';

	/**
	 * The full file path to the PHP executable we shall use for invoking Moodle scheduled tasks via the CLI.
	 */
	protected $php_path = '';

	/**
	 * Monolog Logger instance for reporting information.
	 */
	protected $logger = NULL;

	/**
	 * The type of authentication plugin that is used on Moodle. Typically this will be 'db'
	 * for "external database".
	 */
	protected $auth = 'db';

	/**
	 * The Moodle mnethostid which is considered to be 'local'.
	 *
	 * We use this to ensure that newly created user accounts are considered 'local' by Moodle.
	 */
	protected $local_mnethostid = NULL;

	/**
	 * A context which is global to the whole Moodle instance.
	 */
	const CONTEXT_SYSTEM = 10;

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
	 * The role ID which is the desired role to set for the parent in the context of pupils.
	 */
	protected $parent_role_id = 8;

	/**
	 * The user ID that, for audit purposes, is considered to have modified records in the role assignments table.
	 */
	protected $modifier_id = 2; 

	/**
	 * A stream resource we use to buffer the log entries and have the whole log be accessible from this instance.
	 */
	protected $local_log_stream = NULL;

	/**
	 *
	 */
	public function __construct( $dbhost, $dbuser, $dbpass, $db, $dbprefix, $log_file_path, $parent_role_id, $modifier_id, $sudo_account, $php_path, $moodle_baseurl, $moodle_basepath ) {
		$this->logger = new Logger( 'tvs-pmp-provisioner' );

		$log_level = ( 'info' == get_option( 'tvs-moodle-parent-provisioning-log-level' ) ) ? Logger::INFO : Logger::DEBUG;
		
		$this->logger->pushHandler( new StreamHandler( $log_file_path, $log_level ) ); 
		$this->local_log_stream = fopen( 'php://memory', 'w+' );
		$this->logger->pushHandler( new StreamHandler( $this->local_log_stream ), $log_level );

		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			$this->logger->pushHandler( new BrowserConsoleHandler() );
		}

		ErrorHandler::register( $this->logger );

		$this->logger->debug( sprintf( __( 'Initalised provisioner instance at %s', 'tvs-moodle-parent-provisioning' ), $this->formatted_time() ) ); 

		$this->dbc = new mysqli( $dbhost, $dbuser, $dbpass, $db );
		$this->parent_role_id = $parent_role_id;
		$this->modifier_id = $modifier_id;
		$this->sudo_account = $sudo_account;
		$this->php_path = $php_path;

		// validate PHP path
		if ( ! file_exists( $this->php_path ) ) {
			$exception_message = sprintf( __( 'The PHP path %s does not exist or could not be accessed.', 'tvs-moodle-parent-provisioning' ), $this->php_path );
			$this->logger->error( $exception_message );
			throw new \Exception( $exception_message );
		}

		$this->moodle_baseurl = $moodle_baseurl;
		$this->moodle_basepath = $moodle_basepath;

		if ( $this->dbc->connect_error !== NULL ) {
			$this->logger->error( sprintf ( __( 'Failed to initialise the database object. Connection error %d: %s', 'tvs-moodle-parent-provisioning' ), $this->dbc->connect_errno, $this->dbc->connect_error ) );
			if ( php_sapi_name() != 'cli' ) {
				echo sprintf ( __( 'Failed to initialise the database object. Connection error %d: %s', 'tvs-moodle-parent-provisioning' ), $this->dbc->connect_errno, $this->dbc->connect_error );
			}
		}

	}

	/**
	 * Push a new log handler to the Logger to enable another log output.
	 */
	public function push_log_handler( $handler ) {
		if ( $this->logger ) {
			$this->logger->pushHandler( $handler );
		}
	}

	/**
	 * Get the current log entries that we have written so far.
	 *
	 * @return string
	 */
	protected function get_log_content() {
		rewind( $this->local_log_stream );
		$result = stream_get_contents( $this->local_log_stream );

		fseek( $this->local_log_stream, -1, SEEK_END );
	
		return $result;
	}

	/**
	 * Return a friendly representation of the current date and time.
	 * @return string
	 */
	protected function formatted_time() {
		return date_i18n( get_option( 'date_format' ) ) . ' ' . date_i18n( get_option( 'time_format' ) );
	}

	/**
	 * Get the Moodle user ID for the user with the specified properties.
	 * We are deliberately quite specific about matching all of these, as we should know the exact
	 * information about the user, having created it ourselves during the approval process!
	 *
	 * @param string auth The type of authentication plugin used at Moodle. Typically will be 'db'.
	 * @param string firstname
	 * @param string lastname
	 * @param string email
	 * @param string username
	 *
	 * @return int The user ID, or 0 upon failure to locate the Moodle user
	 */
	public function get_moodle_userid( $auth, $firstname, $lastname, $email, $username ) {
		$this->logger->debug( sprintf( __( 'Determine Moodle user ID for %s %s with email %s, username %s and auth plugin %s', 'tvs-moodle-parent-provisioning' ), $firstname, $lastname, $email, $username, $auth ) );

		$stmt = $this->dbc->prepare( "SELECT id FROM {$this->dbprefix}user WHERE auth = ? AND firstname = ? AND lastname = ? AND email = ? AND username = ?" );

		if ( ! $stmt ) {
			throw new Exception( sprintf( __( 'Failed to prepare the database statement to get user ID. Error: %s', 'tvs-moodle-parent-provisioning' ), $this->dbc->error ) );
		}

		$stmt->bind_param( 'sssss', $auth, $firstname, $lastname, $email, $username );
		$stmt->execute();
		$stmt->store_result();

		if ( $stmt->num_rows < 1 ) {
			// no results
			$this->logger->warning( sprintf( __( 'No Moodle users were found matching %s %s with email %s, username %s and auth plugin %s.', 'tvs-moodle-parent-provisioning' ), $firstname, $lastname, $email, $username, $auth ) );
			return 0;
		}

		if ( $stmt->num_rows > 1 ) {
			// too many results
			$this->logger->warning( sprintf( __( 'More than 1 result was returned for Moodle users matching %s %s with email %s, username %s and auth plugin %s. The first returned will be used. Total users matched: %d', 'tvs-moodle-parent-provisioning' ), $firstname, $lastname, $email, $username, $auth, $stmt->num_rows ) );
		}

		$stmt->bind_result( $parent_user_id );
		$stmt->fetch();
		$stmt->close();

		if ( empty( $parent_user_id ) || ! is_int( $parent_user_id ) ) {
			throw new Exception( sprintf( __( 'Returned Moodle user ID was empty or not an integer.', 'tvs-moodle-parent-provisioning' ) ) );
		}

		$this->logger->info( sprintf( __( 'Returned Moodle user ID is %d', 'tvs-moodle-parent-provisioning' ), $parent_user_id ) );
	
		return $parent_user_id;

	}

	/**
	 * Send an email to the provisioning email recipients with the specified subject and body.
	 *
	 * This is used to send success and failure messages to admins.
	 *
	 * @param string subject The email subject.
	 * @param string body The email body.
	 *
	 * @return void
	 */
	public function send_provisioning_email( $subject, $body ) {
		$this->logger->debug( sprintf( __( 'Will send an email with subject %s', 'tvs-moodle-parent-provisioning' ), $subject ) );

		$users = get_option( 'tvs-moodle-parent-provisioning-provisioning-email-recipients' );

		$users_array = explode( "\n", $users );

		if ( is_array( $users_array ) && count( $users_array ) > 0 ) {
			foreach( $users_array as $user ) {
				//$this->logger->debug( sprintf( __( 'Send to recipient %s', 'tvs-moodle-parent-provisioning' ), $user ) );
				wp_mail(
					$user,
					$subject,
					$body,
					array( 'From: ' . get_bloginfo( 'admin_email' ) )
				);
			}
		}
		else {
			$this->logger->warning( sprintf( __( 'No email recipients. Email about \'%s\' will not send.', 'tvs-moodle-parent-provisioning' ), $subject ) );
		}

	}

	/**
	 * Get user information for the pupil account, matched by the specified first name, last name and 'department' (tutor group).
	 *
	 * @param string fname First name
	 * @param string sname Surname
	 * @param string dept Optional department attribute -- typically tutor group
	 *
	 * @return stdClass of user id, auth, firstname, lastname, email department or NULL
	 */

	public function get_pupil_moodle_user( $fname, $sname, $dept = NULL ) {
		if ( $dept ) {
			$this->logger->debug( sprintf( __( 'Attempt to match pupil with name %s %s with department %s', 'tvs-moodle-parent-provisioning' ), $fname, $sname, $dept ) );

			$stmt = $this->dbc->prepare( "SELECT id, auth, firstname, lastname, email, department FROM {$this->dbprefix}user WHERE firstname = ? AND lastname = ? AND department = ? AND deleted = ?" );

			if ( ! $stmt ) {
				throw new Exception( sprintf( __( 'Failed to prepare the database statement to get pupil user data. Error: %s', 'tvs-moodle-parent-provisioning' ), $this->dbc->error ) );
			}

			$zero = 0;
			$stmt->bind_param( 'sssi', $fname, $sname, $dept, $zero );
		}
		else {
			$this->logger->debug( sprintf( __( 'Attempt to match pupil with name %s %s', 'tvs-moodle-parent-provisioning' ), $fname, $sname ) );
			$stmt = $this->dbc->prepare( "SELECT id, auth, firstname, lastname, email, department FROM {$this->dbprefix}user WHERE firstname = ? AND lastname = ? AND deleted = ?" );

			if ( ! $stmt ) {
				throw new Exception( sprintf( __( 'Failed to prepare the database statement to get pupil user data. Error: %s', 'tvs-moodle-parent-provisioning' ), $this->dbc->error ) );
			}

			$zero = 0;
			$stmt->bind_param( 'ssi', $fname, $sname, $zero );
		}
	

		$stmt->execute();
		$stmt->store_result();

		if ( $stmt->num_rows < 1 ) {
			// no results
			$this->logger->info( sprintf( __( 'No pupil match was found for %s %s in department %s.', 'tvs-moodle-parent-provisioning' ), $fname, $sname, $dept ) );
			return NULL;
		}
		if ( $stmt->num_rows > 1 ) {
			// too many results
			throw new Exception( sprintf( __( 'Matched more than one pupil user for %s %s in department %s. Refusing to match if the match is ambiguous. ', 'tvs-moodle-parent-provisioning' ), $fname, $sname, $dept ) );
		}


		$stmt->bind_result( $id, $auth, $first, $last, $email, $department );
		$stmt->fetch();
		$stmt->close();

		$output = new stdClass();

		$output->id = $id;
		$output->auth = $auth;
		$output->firstname = $first;
		$output->lastname = $last;
		$output->email = $email;
		$output->department = $department;

		return $output;

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
	public function get_context( $contextlevel, $instanceid, $depth ) {
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

		if ( empty( $context_id ) || ! is_int( $context_id ) ) {
			throw new Exception( sprintf( __( 'Returned context ID was empty or not an integer.', 'tvs-moodle-parent-provisioning' ) ) );
		}

		$this->logger->info( sprintf( __( 'Returned context ID is %d', 'tvs-moodle-parent-provisioning' ), $context_id ) );
	
		return $context_id;	
	}


	/**
	 * Check the auth table for an entry with the specified email address.
	 *
	 * Because requests and auth table entries are not the same, and not necessarily in sync,
	 * we should not provision an account where the auth table entry has been removed. This method
	 * checks for an auth table entry with the specified email address.
	 *
	 * @param string email The email address.
	 * @return WP_Object|null
	 */

	public function auth_table_entry_exists( $email ) {
		global $wpdb;

		$this->logger->debug( sprintf( __( 'Checking auth table entry exists for email %s', 'tvs-moodle-parent-provisioning' ), $email ) );

		$row = $wpdb->get_row(
			$wpdb->prepare( "SELECT * FROM {$wpdb->prefix}{TVS_PMP_Request::$table_name}_auth WHERE parent_email = %s",
					$email
			)
		);

		$this->logger->debug( sprintf( __( 'Auth table entry search for %s returned a valid object: %s', 'tvs-moodle-parent-provisioning' ), $email, ( $row ) ? 'yes' : 'no' ) );

		return $row;
	}

	/**
	 * Force the specified Moodle scheduled task to run immediately.
	 *
	 * This is used to force the syncing of users with the external DB, and to force the sending of new 
	 * user passwords. It will use the specified sudo user set in this instance's properties.
	 *
	 * @param string task Task name, such as \core\task\send_new_user_passwords_task
	 * @param int exit_code The exit code of the command will be set in this referenced variable.
	 * 
	 * @return array lines of command output
	 */
	public function run_moodle_scheduled_task( $task, &$exit_code ) {
		$output = array();

		$this->logger->info( sprintf( __( 'Running Moodle scheduled task %s', 'tvs-moodle-parent-provisioning' ), $task ) );
		
		//TODO sudo account needs to be selectable
		$command = 'sudo -u ' .
			escapeshellarg( $this->sudo_account ) .
			' ' .
			escapeshellarg( $this->php_path ) .
			' ' .
			escapeshellarg( trailingslashit( $this->moodle_basepath ) . 'admin/tool/task/cli/schedule_task.php' )
			. ' --execute='.
			escapeshellarg( $task ) .
			' --showdebugging 2>&1';

		$this->logger->info( sprintf( __( 'Will execute: %s', 'tvs-moodle-parent-provisioning' ), $command ) );

		exec( $command, $output, $exit_code ); 

		// dump all output to debug log
		if ( is_array( $output ) && count( $output ) > 0 ) {
			foreach( $output as $line_no => $line ) {
				$this->logger->debug( $line_no . ': ' . $line );
			}
		}


		$this->logger->debug( sprintf( __( 'Moodle scheduled task %s ended with exit code %d', 'tvs-moodle-parent-provisioning' ), $task, $exit_code ) );
		

		return $output;
	}

	/**
	 * Connect the pupil with the specified details to the given parent user ID.
	 *
	 * @param int parent_userid The numeric ID of the parent account.
	 * @param string pupil_fname The first name of the pupil.
	 * @param string pupil_sname The pupil surname
	 * @param string pupil_dept The department (tutor group) of the pupil for matching.
	 * 
	 * @return boolean
	 */
	public function connect_pupil( $parent_userid, $pupil_fname, $pupil_sname, $pupil_dept ) {

		$pupil_fname = trim( $pupil_fname );
		$pupil_sname = trim( $pupil_sname );
		$pupil_dept = trim( $pupil_dept );

		$this->logger->info( sprintf( __( 'Starting process to connect pupil %s %s (%s) to parent user ID %d', 'tvs-moodle-parent-provisioning' ), $pupil_fname, $pupil_sname, $pupil_dept, $parent_userid ) );

		// first, we match the pupil by name and optionally dept (tutor group)
		try {
			if ( 'firstname-surname-only' == get_option( 'tvs-moodle-parent-provisioning-match-by-fields' ) ) {
				$pupil = $this->get_pupil_moodle_user( $pupil_fname, $pupil_sname, NULL );	
			}
			else {
				$pupil = $this->get_pupil_moodle_user( $pupil_fname, $pupil_sname, $pupil_dept );	
			}
		}
		catch (Exception $e) {
			$this->logger->error( $e->getMessage() );
			return false;
		}

		if ( $pupil === NULL ) {
			$this->logger->error( sprintf( __( 'Failed to connect pupil %s %s (%s) as a match was not found. It may be necessary to check the name spelling, preferred/legal name differences and verify the tutor group before attempting provisioning again.', 'tvs-moodle-parent-provisioning' ), $pupil_fname, $pupil_sname, $pupil_dept ) );
			return false;
		}

		if ( !( $pupil instanceof stdClass ) ) {
			$this->logger->error( __( 'The pupil object received was in an unexpected format.', 'tvs-moodle-parent-provisioning' ) );
			return false;
		}
		
		// $pupil now contains pupil information ready for making the connection

		// does a context exist that we can use?
		$context = $this->get_context( TVS_PMP_Provisioner::CONTEXT_USER, $pupil->id, /* depth */ 2 );
		if ( ! $context ) {
			$context = $this->add_context( TVS_PMP_Provisioner::CONTEXT_USER, $pupil->id, /* depth */ 2 );
		}

		// does the role assignment already exist for this context?
		$role_assignment = $this->get_role_assignment( $parent_userid, $this->parent_role_id, $context );
		if ( ! $role_assignment ) {
			// create role assignment
			$role_assignment = $this->add_role_assignment( $parent_userid, $this->parent_role_id, $context, $this->modifier_id, '', 0, 0 );
		}
		else {
			$this->logger->info( sprintf( __( 'The parent user with ID %d already had the appropriate role %d assigned in the context %d for the pupil user %s %s (%s). Role assignment ID: %d', 'tvs-moodle-parent-provisioning' ), $parent_userid, $this->parent_role_id, $context, $pupil_fname, $pupil_sname, $pupil_dept, $role_assignment ) );
		}

		// $role_assignment either contains the ID of the existing role assignment, or the number of affected rows which should be >0

		return ( $role_assignment > 0 ) ? true : false;

	}

	/**
	 * Find all the "Contexts to Add Role" contexts specified in the plugin settings, and assign the role in each of these contexts to the specified user.
	 *
	 * @param int parent_userid The parent account numeric user ID
	 * 
	 * @return boolean Success or failure
	 */
	public function add_role_in_static_contexts( $parent_userid ) {
		
		// get the list of static contexts
		$contexts_raw = get_option( 'tvs-moodle-parent-provisioning-contexts-to-add-role' );


		$this->logger->info( __( 'Will now add the role assignments for all static contexts if this is not already assigned.', 'tvs-moodle-parent-provisioning' ) );

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

			// check for existing role assignment
			$role_assignment = $this->get_role_assignment( $parent_userid, $this->parent_role_id, $context_id );

			if ( ! $role_assignment ) {
				$this->logger->info( sprintf( __( 'Will add role assignment for parent %d for context %d', 'tvs-moodle-parent-provisioning' ), $parent_userid, $context_id ) );
				$this->add_role_assignment( $parent_userid, $this->parent_role_id, $context_id, $this->modifier_id, '', 0, 0 ); 
			}
			else {
				$this->logger->info( sprintf( __( 'Parent with ID %d already had a role assignment for context %d', 'tvs-moodle-parent-provisioning' ), $parent_userid, $context_id ) );
			}

		}

		$this->logger->info( __( 'Completed adding role assignments for static contexts.', 'tvs-moodle-parent-provisioning' ) );

		return true;

	}

	/**
	 * Look up the mnethostid that Moodle considers to be 'local'.
	 *
	 * We set this mnethostid on the new user accounts to ensure that they are considered local users by Moodle.
	 *
	 * @return int The mnethostid, or 0 on failure
	 */
	public function determine_local_mnethostid() {
		$this->logger->debug( __( 'Determine local mnethostid', 'tvs-moodle-parent-provisioning' ) );

		if ( $this->local_mnethostid != NULL ) {
			$this->logger->debug( sprintf( __( 'Returning locally cached mnethostid %d', 'tvs-moodle-parent-provisioning' ),  $this->local_mnethostid ) );
			return $this->local_mnethostid;
		}

		$stmt = $this->dbc->prepare( "SELECT id FROM {$this->dbprefix}mnet_host WHERE wwwroot = ?" );

		if ( ! $stmt ) {
			throw new Exception( sprintf( __( 'Failed to prepare the database statement to get mnethostid. Error: %s', 'tvs-moodle-parent-provisioning' ), $this->dbc->error ) );
		}

		$baseurl = untrailingslashit( $this->moodle_baseurl );
		$stmt->bind_param( 's', $baseurl );
		$stmt->execute();
		$stmt->store_result();

		if ( $stmt->num_rows < 1 ) {
			// no results
			$this->logger->info( sprintf( __( 'The mnethostid matching Moodle wwwroot %s was not found', 'tvs-moodle-parent-provisioning' ), $this->moodle_baseurl ) );
			return 0;
		}

		$stmt->bind_result( $mnethostid );
		$stmt->fetch();
		$stmt->close();

		if ( empty( $mnethostid ) || ! is_int( $mnethostid ) ) {
			throw new Exception( sprintf( __( 'Returned mnethostid was empty or not an integer.', 'tvs-moodle-parent-provisioning' ) ) );
		}

		$this->logger->info( sprintf( __( 'Returned local mnethost is %d', 'tvs-moodle-parent-provisioning' ), $mnethostid ) );
	
		return $mnethostid;	
	}

	/**
	 * Provision the specified account request.
	 *
	 * This requires the request 
	 * 
	 * @param TVS_PMP_Request request The request object.
	 * @param boolean complete_success Whether or not all pupils and contexts were connected successfully.
	 *
	 * @return boolean
	 */
	public function provision_request( TVS_PMP_Request $request, &$complete_success ) {

		if ( !( $request instanceof TVS_PMP_Request ) ) {
			throw new ArgumentException( __( 'The passed request object was not of the required type TVS_PMP_Request.', 'tvs-moodle-parent-provisioning' ) );
		}

		if ( 'approved' != $request->status ) {
			throw new ArgumentException( __( 'A request can only be provisioned from the \'approved\' status.', 'tvs-moodle-parent-provisioning' ) );
		}

		$complete_success = true; // will be overridden if any non-fatal failure occurs

		$this->logger->info( sprintf( __( 'Begin provisioning process for request %d (%s %s: %s).', 'tvs-moodle-parent-provisioning' ), $request->id, $request->parent_fname, $request->parent_sname, $request->parent_email ) );

		// find the mdl_user associated with this request. This will have been created at approval time

		$mdl_userid = $this->get_moodle_userid( $this->auth, $request->parent_title  . ' ' . $request->parent_fname, $request->parent_sname, $request->parent_email, $request->parent_email );

		if ( ! $mdl_userid ) {
			$this->logger->error( sprintf( __( 'Did not find the mdl_user entry for the request %d (%s %s: %s). Cannot continue provisioning this parent account.', 'tvs-moodle-parent-provisioning' ), $request->id, $request->parent_fname, $request->parent_sname, $request->parent_email ) );

			$request->append_system_comment( sprintf( __( 'Failed to provision at %s. Did not find the mdl_user entry for the request. This suggests that the auth table entry is missing, or that the Moodle scheduled task to sync users with the external database has not been run.', 'tvs-moodle-parent-provisioning' ), $this->formatted_time() ) );

			return false;
		}

		// set mnethostid
		$this->set_mnethostid( $mdl_userid, $this->determine_local_mnethostid(), $this->auth );

		// add the roles for static contexts	
		if ( ! $this->add_role_in_static_contexts( $mdl_userid ) ) {
			$this->logger->warning( sprintf( __( 'Did not succeed at adding the roles to static contexts for the request %d (%s %s: %s).', 'tvs-moodle-parent-provisioning' ), $request->id, $request->parent_fname, $request->parent_sname, $request->parent_email ) );
			$complete_success = false;
		}

		// link to pupil(s)
		$pupils = array(
			0 => array(
				'fname'  => $request->child_fname,
				'sname'  => $request->child_sname,
				'tg'     => $request->child_tg 
			),
			1 => array(
				'fname'  => $request->child2_fname,
				'sname'  => $request->child2_sname,
				'tg'     => $request->child2_tg 
			),
			2 => array(
				'fname'  => $request->child3_fname,
				'sname'  => $request->child3_sname,
				'tg'     => $request->child3_tg 
			)
		);

		$any_pupil_failed = false;

		foreach( $pupils as $pupil_number => $pupil ) {
			if ( strlen( $pupil['fname'] ) > 0 && strlen( $pupil['sname'] ) > 0 && strlen( $pupil['tg'] ) > 0 ) {
				// connect this pupil
				if ( ! $this->connect_pupil( $mdl_userid, $pupil['fname'], $pupil['sname'], $pupil['tg'] ) ) {

					$this->logger->warning( sprintf ( __( 'Failed to connect pupil #%d: %s %s (%s).', 'tvs-moodle-parent-provisioning' ), $pupil_number + 1, $pupil['fname'], $pupil['sname'], $pupil['tg'] ) );
					/*$this->send_provisioning_email(
						sprintf( __( 'TVS Parent Moodle Provisioning: Failed to connect a pupil to %s %s', 'tvs-moodle-parent-provisioning' ), $request->parent_fname, $request->parent_sname ),
						sprintf ( __( "Failed to connect pupil %d %s %s (%s) to %s %s (%s). The current log follows.\n\n%s", 'tvs-moodle-parent-provisioning' ), $pupil_number + 1, $pupil['fname'], $pupil['sname'], $pupil['tg'], $request->parent_fname, $request->parent_sname, $request->parent_email, $this->get_log_content() )
					);*/

					$complete_success = false;

				}
			}
			else {
				if ( $pupil_number === 0 ) {
					$this->logger->error( __( 'Pupil #1 did not exist in the request. This parent account cannot be provisioned with no pupils.', 'tvs-moodle-parent-provisioning' ) );
					$request->append_system_comment( sprintf( __( 'Failed to provision at %s. Pupil #1 did not exist in the request. This parent account cannot be provisioned with no pupils.', 'tvs-moodle-parent-provisioning' ), $this->formatted_time() ) );
					return false;
				}
				$this->logger->debug( sprintf( __( 'Ignoring blank pupil number %d', 'tvs-moodle-parent-provisioning' ), $pupil_number + 1 ) );
			}
		}

		if ( $complete_success ) {
			$request->append_system_comment( sprintf( __( 'Successfully provisioned at %s.', 'tvs-moodle-parent-provisioning' ), $this->formatted_time() ) );
			$request->status = 'provisioned';
		}
		else {
			$request->append_system_comment( sprintf( __( 'Provisioned with partial success at %s. One or more pupils were not connected correctly.', 'tvs-moodle-parent-provisioning' ), $this->formatted_time() ) );
		}

		$request->save();
		return true;

	}


	/**
	 * Find all approved requests and provision them, sending emails upon success and failure.
	 *
	 * @return void
	 */
	public function provision_all_approved() {
		
		$this->logger->info( __( 'Begin provisioning cycle for all approved requests.', 'tvs-moodle-parent-provisioning' ) );

		$requests = TVS_PMP_Contact::load_all_approved();

		if ( count( $requests ) < 1 ) {
			$this->logger->info( __( 'No requests currently in the approved state. Ending this provisioning cycle.', 'tvs-moodle-parent-provisioning' ) );
			return;
		}

		$this->logger->info( sprintf( __( 'Found %d requests to provision', 'tvs-moodle-parent-provisioning' ), count( $requests ) ) );

		$failed = 0;
		$succeeded = 0;

		$success_parents = '';

		$task = '\auth_db\task\sync_users';

		$this->logger->info( sprintf( __( 'Running task %s to sync new accounts in auth table with Moodle', 'tvs-moodle-parent-provisioning' ), $task ) );
		$this->run_moodle_scheduled_task( $task, $exit_code );
		//$this->logger->info( sprintf( __( 'Task %s completed with exit code %d', 'tvs-moodle-parent-provisioning' ), $task, $exit_code ) );

		if ( $exit_code != 0 ){
			$this->logger->warning( sprintf( __( 'The Moodle scheduled task %s ended with exit code %d. This implies that the task did not complete successfully. Set the logging level to debugging on the \'Settings\' screen, re-run this process and review the log entries for the task.', 'tvs-moodle-parent-provisioning' ), $task, $exit_code ) );
		}

		foreach( $requests as $request_index => $request ) {

			$complete_success = true; // passed by reference below and updated by that method; allows us to determine partial success
			$result = $this->provision_request( $request, $complete_success );	

			if ( ! $result ) {
				++$failed;

				$this->send_provisioning_email(
					sprintf( __( 'TVS Moodle Parent Provisioning: Provisioning failed for parent %s %s', 'tvs-moodle-parent-provisioning' ), $request->parent_fname, $request->parent_sname ),
					sprintf(
						__( "The parent account for %s %s (%s) could not be provisioned successfully.\n\nFor more information, review the log file for this provisioning cycle below.\n\n%s", 'tvs-moodle-parent-provisioning' ),
							$request->parent_fname,
							$request->parent_sname,
							$request->parent_email,
							$this->get_log_content()
					)
				);

			}
			else if ( $complete_success ) {
				++$succeeded;
				$success_parents .= sprintf( "%s %s (%s)\n%s %s\n%s %s\n%s %s\n\n", $request->parent_fname, $request->parent_sname, $request->parent_email, $request->child_fname, $request->child_sname, $request->child2_fname, $request->child2_sname, $request->child3_fname, $request->child3_sname ) . PHP_EOL;
			}
			// a partial success will trigger the log dump below because succeeded will not match request count

	
		}

		// summarise and send emails
		if ( $succeeded == count( $requests ) ) {

			// send out temporary passwords
			$task = '\core\task\send_new_user_passwords_task';

			$this->logger->info( sprintf( __( 'Running task %s to send out initial passwords for the new accounts', 'tvs-moodle-parent-provisioning' ), $task ) );
			$this->run_moodle_scheduled_task( $task, $exit_code );
			if ( $exit_code != 0 ){
				$this->logger->warning( sprintf( __( 'The Moodle scheduled task %s ended with exit code %d. This implies that the task did not complete successfully. Set the logging level to debugging on the \'Settings\' screen, re-run this process and review the log entries for the task.', 'tvs-moodle-parent-provisioning' ), $task, $exit_code ) );
			}

			$this->send_provisioning_email(
				sprintf( __( 'TVS Moodle Parent Provisioning: Provisioning cycle completed for %d parents', 'tvs-moodle-parent-provisioning' ), count( $requests ) ),
				sprintf(
					__( "Successfully completed a provisioning cycle for %d parents.\n\n%s\n\nFor more information, review the log file for this provisioning cycle below.\n\n%s", 'tvs-moodle-parent-provisioning' ),
						count( $requests ),
						$success_parents,
						$this->get_log_content()
				)
			);		
		}
		else if ( $succeeded == 0 ) {

			// no success
			$this->send_provisioning_email(
				sprintf( __( 'TVS Moodle Parent Provisioning: Provisioning cycle failed', 'tvs-moodle-parent-provisioning' ), count( $requests ) ),
				sprintf(
					__( "The provisioning cycle failed to complete.\n\nHolding off on sending initial password emails (however this runs periodically, so password emails may be sent at any time).\n\nFor more information, review the log file for this provisioning cycle below.\n\n%s", 'tvs-moodle-parent-provisioning' ),
						$this->get_log_content()
				)
			);	
		}
		else {

			// some were unsuccessful
			$this->send_provisioning_email(
				sprintf( __( 'TVS Moodle Parent Provisioning: Provisioning cycle partially complete', 'tvs-moodle-parent-provisioning' ), count( $requests ) ),
				sprintf(
					__( "The provisioning cycle successfully provisioned the accounts below.\n\n%s\n\nHowever, not all accounts were provisioned successfully.\n\nHolding off on sending initial password emails (however this runs periodically, so password emails may be sent at any time).\n\nFor more information, review the log file for this provisioning cycle below.\n\n%s", 'tvs-moodle-parent-provisioning' ),
						$success_parents,
						$this->get_log_content()
				)
			);		
		}

	}


}
