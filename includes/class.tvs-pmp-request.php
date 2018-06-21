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
 * Class: represents a single TVS Parent Moodle Account request
 */

class TVS_PMP_Request {

	/**
	 * The unique ID for this request.
	 */
	public $id;

	/**
	 * The parent's title.
	 */
	public $parent_title;

	/**
	 * The parent's first name.
	 */
	public $parent_fname;

	/**
	 * The parent's surname.
	 */
	public $parent_sname;

	/**
	 * The child's first name.
	 */
	public $child_fname;

	/**
	 * The child's surname.
	 */
	public $child_sname;

	/**
	 * The child's tutor group.
	 */
	public $child_tg;

	/**
	 * The parent's email address.
	 */
	public $parent_email;

	/**
	 * The second child's first name.
	 */
	public $child2_fname;

	/**
	 * The second child's surname.
	 */
	public $child2_sname;

	/**
	 * The second child's tutor group.
	 */
	public $child2_tg;

	/**
	 * The third child's first name.
	 */
	public $child3_fname;

	/**
	 * The third child's surname.
	 */
	public $child3_sname;

	/**
	 * The third child's tutor group.
	 */
	public $child3_tg;

	/**
	 * The status of the request -- pending, approved, provisioned, rejected, duplicate, unknown, bogus
	 */
	public $status;

	/**
	 * The parent comment, as provided on the form.
	 */
	public $parent_comment;

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
	 * The IP address from where the request originated.
	 */
	public $remote_ip_addr;

	/**
	 * The Moodle username that has been provisioned.
	 */
	public $provisioned_username;

	/**
	 * The initial generated password that was provisioned.
	 */
	public $provisioned_initialpass;

	/** 
	 * The nature of the request from the form.
	 */
	public $request_type;


	/**
	 * The name of the database table containing records.
	 */
	public static $table_name = 'tvs_parent_moodle_provisioning';

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
		'unknown'
	);


	/**
 	 * Set up the object.
	 */
	public function __construct() {
		
	}

	/**
	 * Given a record with the id in $this->id, load it from the database
	 *
	 * @return bool Whether or not the data was loaded.
	 */
	public function load() {

		global $wpdb;

		if ( empty( $this->id ) || ! is_int( $this->id ) ) {
			throw new InvalidArgumentException( 'The $id variable must be set to a non-zero integer.' );
		}

		$table_name = TVS_PMP_Request::$table_name;

		$row = $wpdb->get_row(
			$wpdb->prepare( "SELECT * FROM {$wpdb->prefix}{$table_name} WHERE id = %d",
					$this->id
			)
		);

		if ( $row ) {
			$this->id = (int) $row->id;
			$this->parent_title = $row->parent_title;
			$this->parent_fname = $row->parent_fname;
			$this->parent_sname = $row->parent_sname;
			$this->child_fname = $row->child_fname;
			$this->child_sname = $row->child_sname;
			$this->child_tg = $row->child_tg;
			$this->parent_email = $row->parent_email;
			$this->child2_fname = $row->child2_fname;
			$this->child2_sname = $row->child2_sname;
			$this->child2_tg = $row->child2_tg;
			$this->child3_fname = $row->child3_fname;
			$this->child3_sname = $row->child3_sname;
			$this->child3_tg = $row->child3_tg;
			$this->status = $row->status;
			$this->parent_comment = $row->parent_comment;
			$this->staff_comment = $row->staff_comment;
			$this->system_comment = $row->system_comment;
			$this->date_created = $row->date_created;
			$this->date_updated = $row->date_updated;
			$this->date_approved = $row->date_approved;
			$this->remote_ip_addr = $row->remote_ip_addr;
			$this->provisioned_username = $row->provisioned_username;
			$this->provisioned_initialpass = $row->provisioned_initialpass;
			$this->request_type = $row->request_type;
			return true;
		}

		return false;

	}

	/**
	 * Load all requests currently in the 'approved' state and return an array of objects of this type.
	 *
	 * @return array of TVS_PMP_Request
	 */
	public static function load_all_approved() {
		return TVS_PMP_Request::load_all( 'approved' );
	}

	/**
	 * Load all requests currently in the specified state and return an array of objects of this type.
	 * @param $state The status of the requests
	 *
	 * @return array of TVS_PMP_Request
	 */
	public static function load_all( $state ) {
		global $wpdb;

		$requests_raw = $wpdb->get_results( $wpdb->prepare(
			'SELECT id, parent_title, parent_fname, parent_sname, child_fname, child_sname, child_tg, parent_email, child2_fname, child2_sname, child2_tg, child3_fname, child3_sname, child3_tg, status, parent_comment, staff_comment, system_comment, date_created, date_updated, date_approved, remote_ip_addr, provisioned_username, provisioned_initialpass, request_type FROM ' . $wpdb->prefix .'tvs_parent_moodle_provisioning WHERE status = %s',
			$state
		) );

		if ( count( $requests_raw ) < 1 ) {
			return array();
		}

		$request_objs = array();

		foreach( $requests_raw as $request_raw ) {
			$request = new TVS_PMP_Request();
			$request->id = (int) $request_raw->id;
			$request->parent_title = $request_raw->parent_title;
			$request->parent_fname = $request_raw->parent_fname;
			$request->parent_sname = $request_raw->parent_sname;
			$request->child_fname = $request_raw->child_fname;
			$request->child_sname = $request_raw->child_sname;
			$request->child_tg = $request_raw->child_tg;
			$request->parent_email = $request_raw->parent_email;
			$request->child2_fname = $request_raw->child2_fname;
			$request->child2_sname = $request_raw->child2_sname;
			$request->child2_tg = $request_raw->child2_tg;
			$request->child3_fname = $request_raw->child3_fname;
			$request->child3_sname = $request_raw->child3_sname;
			$request->child3_tg = $request_raw->child3_tg;
			$request->status = $request_raw->status;
			$request->parent_comment = $request_raw->parent_comment;
			$request->staff_comment = $request_raw->staff_comment;
			$request->system_comment = $request_raw->system_comment;
			$request->date_created = $request_raw->date_created;
			$request->date_updated = $request_raw->date_updated;
			$request->date_approved = $request_raw->date_approved;
			$request->remote_ip_addr = $request_raw->remote_ip_addr;
			$request->provisioned_username = $request_raw->provisioned_username;
			$request->provisioned_initialpass = $request_raw->provisioned_initialpass;
			$request->request_type = $request_raw->request_type;
			$request_objs[] = $request;
		}

		return $request_objs;

	}


	/** 
	 * Save the data in this object to the database, either creating a new request or updating an existing one.
	 * New objects are **always** created with the status 'pending'.
	 */
	public function save() {
		global $wpdb;

		if ( empty( $this->id ) || ! is_int( $this->id ) ) {
			$affected_rows = $wpdb->insert(
				( $wpdb->prefix . TVS_PMP_Request::$table_name ),
				array(
					'parent_title'    => stripslashes( $this->parent_title ),
					'parent_fname'    => stripslashes( $this->parent_fname ),
					'parent_sname'    => stripslashes( $this->parent_sname ),
					'child_fname'     => stripslashes( $this->child_fname ),
					'child_sname'     => stripslashes( $this->child_sname ),
					'child_tg'        => stripslashes( substr( $this->child_tg, 0, 15 ) ),
					'parent_email'    => stripslashes( strtolower( $this->parent_email ) ),
					'child2_fname'    => stripslashes( $this->child2_fname ),
					'child2_sname'    => stripslashes( $this->child2_sname ),
					'child2_tg'       => stripslashes( substr( $this->child2_tg, 0, 15 ) ),
					'child3_fname'    => stripslashes( $this->child3_fname ),
					'child3_sname'    => stripslashes( $this->child3_sname ),
					'child3_tg'       => stripslashes( substr( $this->child3_tg, 0, 15 ) ),
					'parent_comment'  => stripslashes( $this->parent_comment ),
					'date_created'    => date( 'Y-m-d H:i:s' ),
					'status'          => 'pending',
					'remote_ip_addr'  => stripslashes( $_SERVER['REMOTE_ADDR'] ), //TODO this is internal -- do we want this??
					'request_type'    => stripslashes( $this->request_type ),
				),
				array(
					'%s',               // parent_title
					'%s',               // parent_fname
					'%s',               // parent_sname
					'%s',               // child_fname
					'%s',               // child_sname
					'%s',               // child_tg
					'%s',               // parent_email
					'%s',               // child2_fname
					'%s',               // child2_sname
					'%s',               // child2_tg
					'%s',               // child3_fname
					'%s',               // child3_sname
					'%s',               // child3_tg
					'%s',               // parent_comment
					'%s',               // date_created
					'%s',               // status
					'%s',               // remote_ip_addr
					'%s'		    // request_type	
				)
			);

			if ( $affected_rows !== false ) {
				$this->id = $wpdb->insert_id;
			}

			$this->status = 'pending';

		}
		else {
			$affected_rows = $wpdb->update(
				( $wpdb->prefix . TVS_PMP_Request::$table_name ),
				array(
					'parent_title'    => stripslashes( $this->parent_title ),
					'parent_fname'    => stripslashes( $this->parent_fname ),
					'parent_sname'    => stripslashes( $this->parent_sname ),
					'child_fname'     => stripslashes( $this->child_fname ),
					'child_sname'     => stripslashes( $this->child_sname ),
					'child_tg'        => stripslashes( substr( $this->child_tg, 0, 15 ) ),
					'parent_email'    => stripslashes( strtolower( $this->parent_email ) ),
					'child2_fname'    => stripslashes( $this->child2_fname ),
					'child2_sname'    => stripslashes( $this->child2_sname ),
					'child2_tg'       => stripslashes( substr( $this->child2_tg, 0, 15 ) ),
					'child3_fname'    => stripslashes( $this->child3_fname ),
					'child3_sname'    => stripslashes( $this->child3_sname ),
					'child3_tg'       => stripslashes( substr( $this->child3_tg, 0, 15 ) ),
					'parent_comment'  => stripslashes( $this->parent_comment ),
					'date_created'    => stripslashes( $this->date_created ),
					'date_updated'    => date( 'Y-m-d H:i:s' ),
					'status'          => stripslashes( $this->status ),
					'remote_ip_addr'  => stripslashes( $this->remote_ip_addr ),
					'request_type'    => stripslashes( $this->request_type ),
				),
				array(
					'id'		  => $this->id
				),
				array(
					'%s',               // parent_title
					'%s',               // parent_fname
					'%s',               // parent_sname
					'%s',               // child_fname
					'%s',               // child_sname
					'%s',               // child_tg
					'%s',               // parent_email
					'%s',               // child2_fname
					'%s',               // child2_sname
					'%s',               // child2_tg
					'%s',               // child3_fname
					'%s',               // child3_sname
					'%s',               // child3_tg
					'%s',               // parent_comment
					'%s',               // date_created
					'%s',		    // date_updated
					'%s',               // status
					'%s',               // remote_ip_addr
					'%s'		    // request_type	
				),
				array(
					'%d'		    // id
				)
			);

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
			throw new InvalidArgumentException( 'The $id variable must be set to a non-zero integer.' );
		}

		if ( empty( $this->status ) || $this->status != 'pending' ) {
			throw new InvalidArgumentException( __( 'An account request can only be approved for provisioning from the \'pending\' status.', 'tvs_moodle_parent_provisioning' ) );
		}

		$new_status = 'approved';
		$new_sys_comment = $this->system_comment . PHP_EOL . sprintf( __( 'Approved for provisioning at %s -- awaiting next provision cycle', 'tvs-moodle-parent-provisioning' ), date('j F Y H:i:s T'));

		$wpdb->update( 	$wpdb->prefix .'tvs_parent_moodle_provisioning',
			array(
				'system_comment'		=> $new_sys_comment,
				'status'			=> $new_status,
				'date_updated'			=> date('Y-m-d H:i:s'),
				'date_approved'			=> date('Y-m-d H:i:s')
			), array(
				'id'				=> $this->id
			), array(
				'%s',
				'%s',
				'%s',
				'%s'
			), array(
				'%d'
			)	
		 );

		// check for pre-existence of a parent with this email
		$exists = $wpdb->get_results( $wpdb->prepare(
			'SELECT id FROM ' . $wpdb->prefix . 'tvs_parent_moodle_provisioning_auth WHERE parent_email = %s',
			array(
				$this->parent_email
			)
		) );

		if ( count( $exists ) > 0 ) {
				$new_status = 'duplicate';

			$new_sys_comment .= PHP_EOL . __( 'Unable to provision, as email address already exists in external users table -- ', 'tvs-moodle-parent-provisioning' ) . date( 'j F Y H:i:s T');

			$wpdb->update( 	$wpdb->prefix .'tvs_parent_moodle_provisioning',
				array(
					'system_comment'		=> $new_sys_comment,
					'status'			=> 'duplicate',
					'date_updated'			=> date('Y-m-d H:i:s')
				), array(
					'id'				=> $this->id
				), array(
					'%s',
					'%s',
					'%s'
				), array(
					'%d'
				)	
			 );
			
			throw new TVS_PMP_Parent_Account_Duplicate_Exception(
				__( 'Unable to provision, as email address already exists in external users table. Marked as duplicate.', 'tvs-moodle-parent-provisioning' )
				);
	
		}

                // add to external Moodle auth table and wait there until the next cron-initiated provision cycle
                $username = strtolower( $this->parent_email );
                $parent_title = $this->parent_title;
                $parent_fname = $this->parent_title . ' ' . $this->parent_fname;
                $parent_sname = $this->parent_sname;
                $parent_email = strtolower( $this->parent_email );
                $description = __( 'Parent Moodle Account', 'tvs-moodle-parent-provisioning' );

                // add to external Moodle table

                $response = $wpdb->insert( $wpdb->prefix . 'tvs_parent_moodle_provisioning_auth',
                        array(
                                'username'        =>  $username,
                                'parent_title'    =>  $parent_title,
                                'parent_fname'    =>  $parent_fname,
                                'parent_sname'    =>  $parent_sname,
                                'parent_email'    =>  $parent_email,
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

		return $wpdb->update( 	$wpdb->prefix .'tvs_parent_moodle_provisioning',
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
		set_transient( 'tvs-moodle-parent-provisioning-pending-requests', TVS_PMP_Request::get_pending_count(), 3600 );
	}


};

/**
 * Allow us to throw a custom exception for duplicate parent accounts.
 */
class TVS_PMP_Parent_Account_Duplicate_Exception extends Exception {
	public function __construct( $message, $code = 0, Exception $previous = null ) {
		parent::__construct( $message, $code, $previous );
	}
}
