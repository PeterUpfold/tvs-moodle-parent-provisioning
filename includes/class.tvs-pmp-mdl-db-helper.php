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
	 * A local log stream for the Monolog\Logger\Logger that we can use to get a "stack trace"
	 * style log of the events leading up to a provisioning failure to send in an email.
	 */
	public $local_log_stream = NULL;

	/**
	 * The database prefix for the tables in the Moodle database 
	 */
	protected $dbprefix = 'mdl_';

	/**
	 * The file path to the sudo executable. Usually /usr/bin/sudo.
	 */
	protected $sudo_path = '';

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
	 * The role ID which is the desired role to set for the parent in the context of pupils.
	 */
	protected $parent_role_id = 8;

	/**
	 * The user ID that, for audit purposes, is considered to have modified records in the role assignments table.
	 */
	protected $modifier_id = 2; 

	/**
	 * The base URL of the target Moodle installation.
	 */
	protected $moodle_baseurl = '';

	/** 
	 * The base path of the target Moodle installation's PHP files. (not moodledata!)
	 */
	protected $moodle_basepath = '';

	/**
	 * Support only the whitelisted scheduled tasks for invocation via the Moodle CLI.
	 */
	protected $moodle_tasks_whitelist = array(
		'\core\task\send_new_user_passwords_task',
		'\auth_db\task\sync_users'		
	);

	/**
	 * Create the object.
	 */
	public function __construct( $logger, $dbc ) {
		$this->logger = $logger;
		$this->dbc = $dbc;

		// load settings
		$set_prefix = 'tvs-moodle-parent-provisioning-';

		$this->parent_role_id = get_option( $set_prefix . 'moodle-parent-role' );
		$this->modifier_id = get_option( $set_prefix . 'moodle-modifier-id' );
		$this->sudo_path = get_option( $set_prefix . 'moodle-sudo-path' );
		$this->sudo_account = get_option( $set_prefix . 'moodle-sudo-account' );
		$this->php_path = get_option( $set_prefix . 'php-path' );
		$this->moodle_baseurl = get_option( $set_prefix . 'moodle-url' );
		$this->moodle_basepath = get_option( $set_prefix . 'moodle-path' );

		// validate PHP path
		if ( ! file_exists( $this->php_path ) ) {
			$exception_message = sprintf( __( 'The PHP path %s does not exist or could not be accessed.', 'tvs-moodle-parent-provisioning' ), $this->php_path );
			$this->logger->error( $exception_message );
			throw new \Exception( $exception_message );
		}

		if ( $this->dbc->connect_error !== NULL ) {
			$this->logger->error( sprintf ( __( 'Failed to initialise the database object. Connection error %d: %s', 'tvs-moodle-parent-provisioning' ), $this->dbc->connect_errno, $this->dbc->connect_error ) );
			if ( php_sapi_name() != 'cli' ) {
				echo sprintf ( __( 'Failed to initialise the database object. Connection error %d: %s', 'tvs-moodle-parent-provisioning' ), $this->dbc->connect_errno, $this->dbc->connect_error );
			}
		}

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

		if ( php_sapi_name() == 'cli' ) {
			$logger->pushHandler( new StreamHandler( fopen( 'php://stderr', 'w' ) ), $log_level );
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

		$this->logger->debug( sprintf( __( 'Found context %d for contextlevel %d with instance ID %d and depth %d', 'tvs-moodle-parent-provisioning' ), $context_id, $contextlevel, $instanceid, $depth ) );

		$stmt->close();

		return $context_id;

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
	public function add_context( $contextlevel, $instanceid, $depth ) {
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


		// if any argument is suspect, refuse to run
		if (
			NULL == $this->sudo_path ||
			empty( $this->sudo_path ) ||
			NULL == $this->sudo_account ||
			empty( $this->sudo_account ) ||
			NULL == $this->php_path ||
			empty( $this->php_path ) ||
			NULL == $this->moodle_basepath ||
			empty( $this->moodle_basepath ) ||
			NULL == $task ||
			empty( $task )
		) {
			throw new InvalidArgumentException( __( 'One or more of the required configuration settings was not loaded correctly. For security reasons, no command will be executed.', 'tvs-moodle-parent-provisioning' ) );
		}


		// support only whitelisted $tasks
		if ( ! in_array( $task, $this->moodle_tasks_whitelist ) ) {
			throw new InvalidArgumentException( sprintf( __( 'Only the following tasks can be invoked by this method: %s', 'tvs-moodle-parent-provisioning' ), implode( ',', $this->moodle_tasks_whitelist ) ) );
		}
		
		
		$command = escapeshellarg( $this->sudo_path ) . ' -u ' .
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
	 * Complete the process of removing Contacts whose status is currently 'deleting'.
	 *
	 * @return void
	 */
	public function finish_delete() {
		$this->logger->info( __( 'Begin finish delete cycle for all Contacts in \'deleting\' status.', 'tvs-moodle-parent-provisioning' ) );

		$contacts = TVS_PMP_Contact::load_all( 'deleting', $this->logger, $this->dbc );
		if ( count( $contacts ) < 1 ) {
			$this->logger->info( __( 'No Contacts currently in the \'deleting\' status. Ending this finish delete cycle.', 'tvs-moodle-parent-provisioning' ) );
			return;
		}

		$this->logger->info( sprintf( __( 'Found %d Contacts to finish deleting', 'tvs-moodle-parent-provisioning' ), count( $contacts ) ) );

		$task = '\auth_db\task\sync_users';

		$this->logger->info( sprintf( __( 'Running task %s to sync accounts in auth table with Moodle', 'tvs-moodle-parent-provisioning' ), $task ) );
		$this->run_moodle_scheduled_task( $task, $exit_code );
		$this->logger->info( sprintf( __( 'Task %s completed with exit code %d', 'tvs-moodle-parent-provisioning' ), $task, $exit_code ) );

		if ( $exit_code != 0 ){
			$this->logger->warning( sprintf( __( 'The Moodle scheduled task %s ended with exit code %d. This implies that the task did not complete successfully. Set the logging level to debugging on the \'Settings\' screen, re-run this process and review the log entries for the task.', 'tvs-moodle-parent-provisioning' ), $task, $exit_code ) );
		}

		// now that Moodle  users are suspended, let's delete the Contact entry
		foreach( $contacts as $contact ) {
			$affected = $contact->delete();	
			if ( $affected ) {
				$this->logger->info( sprintf( __( 'Deleted %d row -- removed %s.', 'tvs-moodle-parent-provisioning' ), $affected, $contact->__toString() ) );
			}
			else {
				$this->logger->warning( sprintf( __( 'Failed to remove %s when performing the database delete.', 'tvs-moodle-parent-provisioning' ), $contact->__toString() ) );
			}
		}

	}


	/**
	 * Find all approved requests and provision them.
	 *
	 * @return void
	 */
	public function provision_all_approved() {
		
		$this->logger->info( __( 'Begin provisioning cycle for all approved contacts.', 'tvs-moodle-parent-provisioning' ) );

		$contacts = TVS_PMP_Contact::load_all_approved( $this->logger, $this->dbc );

		if ( count( $contacts ) < 1 ) {
			$this->logger->info( __( 'No contacts currently in the approved state. Ending this provisioning cycle.', 'tvs-moodle-parent-provisioning' ) );
			return;
		}

		$this->logger->info( sprintf( __( 'Found %d contacts to provision', 'tvs-moodle-parent-provisioning' ), count( $contacts ) ) );

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

		foreach( $contacts as $contact_index => $contact ) {



			// look up new mdl_user ID
			try {
				$contact->load_mdl_user();
				++$succeeded;
				$success_parents .= sprintf( "%s", $contact ) . PHP_EOL;

				$contact->status = 'partial';
			       	$contact->save();
			}
			catch ( Exception $e ) {
				++$failed;

				$this->send_provisioning_email(
					sprintf( __( 'TVS Moodle Parent Provisioning: Provisioning failed for parent %s %s', 'tvs-moodle-parent-provisioning' ), $request->parent_fname, $request->parent_sname ),
					sprintf(
						__( "The parent account for %s could not be provisioned successfully.\n\nFor more information, review the log file for this provisioning cycle below.\n\n%s", 'tvs-moodle-parent-provisioning' ),
							$contact,
							$this->get_log_content()
					)
				);

			}	

			// a partial success will trigger the log dump below because succeeded will not match request count
	
		}

		// summarise and send emails
		if ( $succeeded == count( $contacts ) ) {

			// send out temporary passwords
			$task = '\core\task\send_new_user_passwords_task';

			$this->logger->info( sprintf( __( 'Running task %s to send out initial passwords for the new accounts', 'tvs-moodle-parent-provisioning' ), $task ) );
			$this->run_moodle_scheduled_task( $task, $exit_code );
			if ( $exit_code != 0 ){
				$this->logger->warning( sprintf( __( 'The Moodle scheduled task %s ended with exit code %d. This implies that the task did not complete successfully. Set the logging level to debugging on the \'Settings\' screen, re-run this process and review the log entries for the task.', 'tvs-moodle-parent-provisioning' ), $task, $exit_code ) );
			}

			$this->send_provisioning_email(
				sprintf( __( 'TVS Moodle Parent Provisioning: Provisioning cycle completed for %d parents', 'tvs-moodle-parent-provisioning' ), count( $contacts ) ),
				sprintf(
					__( "Successfully completed a provisioning cycle for %d parents.\n\n%s\n\nFor more information, review the log file for this provisioning cycle below.\n\n%s", 'tvs-moodle-parent-provisioning' ),
						count( $contacts ),
						$success_parents,
						$this->get_log_content()
				)
			);		
		}
		else if ( $succeeded == 0 ) {

			// no success
			$this->send_provisioning_email(
				sprintf( __( 'TVS Moodle Parent Provisioning: Provisioning cycle failed', 'tvs-moodle-parent-provisioning' ), count( $contacts ) ),
				sprintf(
					__( "The provisioning cycle failed to complete.\n\nHolding off on sending initial password emails (however this runs periodically, so password emails may be sent at any time).\n\nFor more information, review the log file for this provisioning cycle below.\n\n%s", 'tvs-moodle-parent-provisioning' ),
						$this->get_log_content()
				)
			);	
		}
		else {

			// some were unsuccessful
			$this->send_provisioning_email(
				sprintf( __( 'TVS Moodle Parent Provisioning: Provisioning cycle partially complete', 'tvs-moodle-parent-provisioning' ), count( $contacts ) ),
				sprintf(
					__( "The provisioning cycle successfully provisioned the accounts below.\n\n%s\n\nHowever, not all accounts were provisioned successfully.\n\nHolding off on sending initial password emails (however this runs periodically, so password emails may be sent at any time).\n\nFor more information, review the log file for this provisioning cycle below.\n\n%s", 'tvs-moodle-parent-provisioning' ),
						$success_parents,
						$this->get_log_content()
				)
			);		
		}

	}

	/**
	 * Get the current log entries that we have written so far.
	 *
	 * @return string
	 */
	protected function get_log_content() {

		// get the handlers to find the local log stream to dump
		if ( NULL === $this->local_log_stream ) {
			foreach( $this->logger->getHandlers() as $handler ) {
				if ( $handler instanceof \Monolog\Handler\StreamHandler && NULL === $handler->getUrl() && is_resource( $handler->getStream() ) && !stream_isatty( $handler->getStream() ) ) {
					$this->local_log_stream = $handler->getStream();
					break;
				}
			}
		}	

		rewind( $this->local_log_stream );
		$result = stream_get_contents( $this->local_log_stream );

		fseek( $this->local_log_stream, -1, SEEK_END );
	
		return $result;
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
	 * Determine if a role assignment exists for the specified user in the given context. If so, return the role assignment id.
	 *
	 * @param int userid
	 * @param int roleid Typically this will be the ID of the parent role
	 * @param int contextid The contextid is linked to a category, course or other entity within Moodle. It can be determined by going to assign roles and checking for the contextid in the URL.
	 *
	 * @return int role assignment id, or 0 if no results
	 */
	public function get_role_assignment( $userid, $roleid, $contextid ) {
		$this->logger->debug( sprintf( __( 'Determine role assignment for user %d with role %d in context %d', 'tvs-moodle-parent-provisioning' ), $userid, $roleid, $contextid ) );

		$stmt = $this->dbc->prepare( "SELECT id FROM {$this->dbprefix}role_assignments WHERE roleid = ? AND contextid = ? AND userid = ?" );

		if ( ! $stmt ) {
			throw new Exception( sprintf( __( 'Failed to prepare the database statement to get role assignments. Error: %s', 'tvs-moodle-parent-provisioning' ), $this->dbc->error ) );
		}

		$stmt->bind_param( 'iii', $roleid, $contextid, $userid );
		$stmt->execute();
		$stmt->store_result();

		if ( $stmt->num_rows < 1 ) {
			// no results
			$this->logger->info( sprintf( __( 'User %d does not currently have the role %d assigned in context %d.', 'tvs-moodle-parent-provisioning' ), $userid, $roleid, $contextid ) );
			$stmt->close();
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
	 * Return the PHP path.
	 *
	 * @return string
	 */
	public function get_php_path() {
		return $this->php_path;
	}
};

