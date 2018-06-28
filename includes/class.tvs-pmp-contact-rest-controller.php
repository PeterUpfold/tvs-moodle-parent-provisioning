<?php
/* Copyright (C) 2016-2018 Test Valley School.


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

if ( ! defined ( 'TVS_PMP_REQUIRED_CAPABILITY' ) ) {
	header( 'HTTP/1.0 403 Forbidden' );
	echo '<h1>Forbidden</h1>';
	die();
}

if ( ! class_exists( 'WP_REST_Controller' ) ) {
	header( 'HTTP/1.0 403 Forbidden' );
	echo '<h1>Forbidden</h1>';
	die();
}

/**
 * A custom WP_REST_Controller class that supports our REST methods for  
 * managing Parent Account Contacts, which are associated with Parent
 * Accounts.
 */
class TVS_PMP_Contact_REST_Controller extends WP_REST_Controller {

	/**
	 * The version number of the WP-JSON Parent Moodle Provisioning API.
	 */
	const API_VERSION = '1.0';

	/**
	 * The namespace of the WP-JSON Parent Moodle Provisioning API.
	 */
	const API_NAMESPACE = 'testvalleyschool/v1';

	/**
	 * @var \Monolog\Logger\Logger
	 *
	 * Reference to a logging instance.
	 */
	protected $logger = NULL;

	/*
	 * @var mysqli
	 *
	 * Reference to an object that allows us to manipulate the Moodle database.
	 */
	protected $dbc = NULL;

	/**
	 * A memory stream containing the current log entries.
	 */
	protected $local_log_stream = NULL;

	/**
	 * Create a new object of this type.
	 */
	public function __construct() {
		$this->namespace = TVS_PMP_Contact_REST_Controller::API_NAMESPACE;
	}


	/**
	 * Create objects for logging and Moodle database connectivity if they do not already exist.
	 */
	protected function ensure_logger_and_dbc() {
		if ( ! $this->logger ) {
			$this->logger = TVS_PMP_MDL_DB_Helper::create_logger( $this->local_log_stream ); 
		}
		if ( ! $this->dbc ) {
			$this->dbc = TVS_PMP_MDL_DB_Helper::create_dbc( $this->logger );
		}
	}

	/**
	 * Register our REST actions for external access.
	 */
	public function register_routes() {
		register_rest_route( $this->namespace, '/contact', array(
			/* POST /contact  ("ensure contact") */

			/* We diverge from the WordPress pattern of having separate CREATABLE and UPDATABLE
			 * endpoints. This is because any client (e.g. PowerShell script) should not need
			 * to care whether or not a Contact with a specific set of attributes
			 * exists already in order to ensure that it is in the correct state.
			 *
			 * Assuming a success is returned by this REST method, the script can be oblivious
			 * to whether or not a new object was created or an existing object was updated. This
			 * avoids a lot of unnecessary checking for object existence and branching on the 
			 * "frontend".
			 */
			array(
				'methods'                            => WP_REST_Server::UPDATABLE,
				'callback'                           => array( $this, 'ensure_item' ),
				'permission_callback'                => array( $this, 'user_has_permission' ),
				'args'                               => array( $this, 'ensure_args' )
			),

			/* GET /contact  ("get all contacts") */
			array(
				'methods'                            => WP_REST_Server::READABLE,
				'callback'                           => array( $this, 'get_items' ),
				'permission_callback'                => array( $this, 'user_has_permission' ),
				'args'                               => array( $this, 'get_items_args' )
			),
		) );

		register_rest_route( $this->namespace, '/contact/(?P<id>[\d]+)', array(
			'args' => array(
				'id' => array(
					'description'                => __( 'Unique identifier for the object.' ),
					'type'                       => 'integer',
				),
			),
			/* GET /contact/[id]      ("get contact") */
			array(
				'methods'                            => WP_REST_Server::READABLE,
				'callback'                           => array( $this, 'get_item' ),
				'permission_callback'                => array( $this, 'user_has_permission' )
			),
			/* POST /contact/[id]     ("update specific contact") */
			array(
				'methods'                            => WP_REST_Server::EDITABLE,
				'callback'                           => array( $this, 'ensure_item' ),
				'permission_callback'                => array( $this, 'user_has_permission' ),
				'args'                               => array( $this, 'ensure_args' )
			),
			/* DELETE /contact/[id]   ("delete contact") */
			array(
				'methods'                            => WP_REST_Server::DELETABLE,
				'callback'                           => array( $this, 'delete_item' ),
				'permission_callback'                => array( $this, 'user_has_permission' )
			)
		) );

		register_rest_route( $this->namespace, '/contact/(?P<id>[\d]+)/mappings', array(
			'args' => array(
				'id' => array(
					'description'                => __( 'Unique identifier for the object.' ),
					'type'                       => 'integer',
				),
			),
			/* GET /contact/[id]/mappings/ ("get all Contact's mappings") */
			array(
				'methods'                            => WP_REST_Server::READABLE,
				'callback'                           => array( $this, 'get_item_mappings' ),
				'permission_callback'                => array( $this, 'user_has_permission' )
			)
		) );

		register_rest_route( $this->namespace, '/contact/external-mis-id/(?P<external_mis_id>[\w-]+)', array(
			'args' => array(
				'external_mis_id' => array(
					'description'                => __( 'The MIS\'s "external ID" that is unique and can be used to match a specific entity.', 'tvs-moodle-parent-provisioning' ),
					'type'                       => 'string',
				),
			),
			/* GET /contact/[id]      ("get contact") */
			array(
				'methods'                            => WP_REST_Server::READABLE,
				'callback'                           => array( $this, 'get_item' ),
				'permission_callback'                => array( $this, 'user_has_permission' )
			),
			/* POST /contact/[id]     ("update specific contact") */
			array(
				'methods'                            => WP_REST_Server::EDITABLE,
				'callback'                           => array( $this, 'update_item' ),
				'permission_callback'                => array( $this, 'user_has_permission' ),
				'args'                               => array( $this, 'ensure_args' )
			),
			/* DELETE /contact/[id]   ("delete contact") */
			array(
				'methods'                            => WP_REST_Server::DELETABLE,
				'callback'                           => array( $this, 'delete_item' ),
				'permission_callback'                => array( $this, 'user_has_permission' )
			)
		) );

	}


	/**
	 * The list of arguments that "ensure" methods expect.
	 */
	protected function ensure_args() {
		return array(
			'id'     => array(
				'validate_callback'        => function( $param, $request, $key ) {
					return ! empty( $param ) && is_numeric( $param );
				}
			),
			'mis_id' => array(
				'validate_callback'        => function( $param, $request, $key ) {
					return ! empty( $param );
				},
				'required'                 => true,
			),
			'external_mis_id' => array(
				'validate_callback'        => function( $param, $request, $key ) {
					return ! empty( $param );
				}
			),
			'mdl_user_id'     => array(
				'validate_callback' => function( $param, $request, $key ) {
					return ! empty( $param ) && is_numeric( $param );
				}
			),
			'title' => array(
				'validate_callback'        => function( $param, $request, $key ) {
					return ! empty( $param ) && strlen( $param ) <= 12;
				}
			),
			'forename' => array(
				'validate_callback'        => function( $param, $request, $key ) {
					return ! empty( $param ) && strlen( $param ) <= 255;
				}
			),
			'surname' => array(
				'validate_callback'        => function( $param, $request, $key ) {
					return ! empty( $param ) && strlen( $param ) <= 255;
				}
			),
			'email' => array(
				'validate_callback'        => function( $param, $request, $key ) {
					return ( filter_var( $param, FILTER_EMAIL ) ) !== NULL;
				},
				'sanitize_callback'        => function( $param, $request, $key ) {
					return filter_var( $param, FILTER_EMAIL );
				}
			),
			'status' => array (
				'validate_callback'        => function( $param, $request, $key ) {
					return in_array( $param, TVS_PMP_Contact::$statuses );
				}
			),
			'staff_comment' => array(
				'validate_callback'        => function( $param, $request, $key ) {
					return strlen( $param ) < 65536;
				}
			),
			'system_comment' => array(
				'validate_callback'        => function( $param, $request, $key ) {
					return strlen( $param ) < 65536;
				}
			),
			'date_created' => array(
				'validate_callback'        => function( $param, $request, $key ) {
					return strtotime( $param ) !== false;
				},
				'sanitize_callback'        => function( $param, $request, $key ) {
					return date( 'Y-m-d H:i:s', strtotime( $param ) );
				}
			),
			'date_updated' => array(
				'validate_callback'        => function( $param, $request, $key ) {
					return strtotime( $param ) !== false;
				},
				'sanitize_callback'        => function( $param, $request, $key ) {
					return date( 'Y-m-d H:i:s', strtotime( $param ) );
				}
			),
			'date_approved' => array(
				'validate_callback'        => function( $param, $request, $key ) {
					return strtotime( $param ) !== false;
				},
				'sanitize_callback'        => function( $param, $request, $key ) {
					return date( 'Y-m-d H:i:s', strtotime( $param ) );
				}
			),
			'date_synced' => array(
				'validate_callback'        => function( $param, $request, $key ) {
					return strtotime( $param ) !== false;
				},
				'sanitize_callback'        => function( $param, $request, $key ) {
					return date( 'Y-m-d H:i:s', strtotime( $param ) );
				}
			),
		);
	}


	/**
	 * Attempt to look up a Contact record from one of the unique identifiers submitted
	 * in the $request.
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 *
	 * @return TVS_PMP_Contact
	 */
	protected function try_get_record_from_request( WP_REST_Request $request ) {
		// determine if we have a unique ID in the request to look up an record record
		$id = $request->get_param( 'id' );
		$external_mis_id =  $request->get_param( 'external_mis_id' );
		$record = new TVS_PMP_Contact( $this->logger, $this->dbc ); // we will 'load' this momentarily to see if it exists

		if ( NULL != $id ) {
			$record->id = $id;
			if ( ! $record->load() ) {
				$this->logger->warn( sprintf( __( 'Unable to load Contact with internal ID %d', 'tvs-moodle-parent-provisioning' ), $id ) );
				$record = NULL;	
			}
		}
		else if ( NULL != $external_mis_id ) {
			$record->external_mis_id = $external_mis_id;
			if ( ! $record->load() ) {
				$this->logger->warn( sprintf( __( 'Unable to load Contact with external MIS ID %s', 'tvs-moodle-parent-provisioning' ), $external_mis_id ) );
				$record = NULL;	
			}
		}
		else {
			// must be a new item
			$record = new TVS_PMP_Contact( $this->logger, $this->dbc ); 
		}
		return $record;
	}


	/**
	 * Handle a request to ensure that the specified Contact exists. This will either create
	 * a new Contact with the passed parameters, or update an existing one that matches the
	 * unique ID(s) provided in the parameters.
	 *
	 * @param WP_REST_Request $request The full details about the REST request.
	 *
	 * @return WP_REST_Response|WP_Error
	 */
	public function ensure_item( WP_REST_Request $request ) {
		//TODO verify that permissions checks do not need to be added here
		//

		$this->ensure_logger_and_dbc();

		$record = $this->try_get_record_from_request( $request );


		if ( $request->get_param( 'mis_id' ) ) {
			$record->mis_id = $request->get_param( 'mis_id' );
		}
		if ( $request->get_param( 'external_mis_id' ) ) {
			$record->external_mis_id = $request->get_param( 'external_mis_id' );
		}
		if ( $request->get_param( 'mdl_user_id' ) ) {
			$record->mdl_user_id = $request->get_param( 'mdl_user_id' );
		}
		if ( $request->get_param( 'title' ) ) {
			$record->title = $request->get_param( 'title' );
		}
		if ( $request->get_param( 'forename' ) ) {
			$record->forename = $request->get_param( 'forename' );
		}
		if ( $request->get_param( 'surname' ) ) {
			$record->surname = $request->get_param( 'surname' );
		}
		if ( $request->get_param( 'email' ) ) {
			$record->email = $request->get_param( 'email' );
		}
		if ( $request->get_param( 'status' ) ) {
			$record->status = $request->get_param( 'status' );
		}
		if ( $request->get_param( 'staff_comment' ) ) {
			$record->staff_comment = $request->get_param( 'staff_comment' );
		}
		if ( $request->get_param( 'date_created' ) ) {
			$record->date_created = $request->get_param( 'date_created' );
		}
		if ( $request->get_param( 'date_updated' ) ) {
			$record->date_updated = $request->get_param( 'date_updated' );
		}
		if ( $request->get_param( 'date_approved' ) ) {
			$record->date_approved = $request->get_param( 'date_approved' );
		}
		if ( $request->get_param( 'date_synced' ) ) {
			$record->date_synced = $request->get_param( 'date_synced' );
		}

		$result = $record->save();

		$record->ensure_role_in_static_contexts();

		$this->logger->debug( sprintf( __( 'Result of saving record for %d was %d affected rows.', 'tvs-moodle-parent-provisioning' ), $record->id, $result ) );

		return new WP_REST_Response(
			array(
				'affected_rows'  => $result,
				$record
			)
		);

	}


	/**
	 * Retrieve an individual Contact from the database.
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 *
	 * @return WP_REST_Response|WP_Error
	 */
	public function get_item( WP_REST_Request $request ) {
		//TODO verify that permissions checks do not need to be added here
		//

		$this->ensure_logger_and_dbc();

		$record = $this->try_get_record_from_request( $request );

		if ( ! $record->id ) {
			return new WP_Error( 'rest_post_invalid_id', __( 'Unable to look up the Contact.', 'tvs-moodle-parent-provisioning' ), array( 'status' => 404 ) );
		}

		return rest_ensure_response( $record );

	}

	/**
	 * Retrieve all the Contact Mappings that this Contact has.
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 *
	 * @return WP_REST_Response|WP_Error
	 */
	public function get_item_mappings( WP_REST_Request $request ) {
		//TODO verify that permissions checks do not need to be added here

		$this->ensure_logger_and_dbc();

		$record = $this->try_get_record_from_request( $request );

		if ( ! $record->id ) {
			return new WP_Error( 'rest_post_invalid_id', __( 'Unable to look up the Contact.', 'tvs-moodle-parent-provisioning' ), array( 'status' => 404 ) );
		}

		$mappings = $record->get_contact_mappings();

		return rest_ensure_response( $mappings );
	}

	/**
	 * Retrieve all Contacts with the status specified in the $request.
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 *
	 * @return WP_REST_Response|WP_Error
	 */
	public function get_items( WP_REST_Request $request ) {
		//TODO verify that permissions checks do not need to be added here
		//

		$this->ensure_logger_and_dbc();

		$records = TVS_PMP_Contact::load_all( $request->get_param( 'status' ) );	

		return rest_ensure_response( $records );

	}

	/**
	 * The expected arguments for retrieving all Contacts.
	 *
	 * @return array
	 */
	public function get_items_args() {
		return array(
			'status' => array (
				'validate_callback'        => function( $param, $request, $key ) {
					return in_array( $param, TVS_PMP_Contact::$statuses );
				},
				'required'                 => true
			)
		);
	}

	/**
	 * Completely delete the Contact from our internal databases, ensuring that it does not
	 * exist within Moodle as well.
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 *
	 * @return WP_REST_Response|WP_Error
	 */
	public function delete_item( WP_REST_Request $request ) {
		//TODO verify that permissions checks do not need to be added here
		//
		
		$this->ensure_logger_and_dbc();

		$record = $this->try_get_record_from_request( $request );

		if ( ! $record || ! $record->id ) {
			return new WP_Error( 'rest_post_invalid_id', __( 'Unable to look up the Contact.', 'tvs-moodle-parent-provisioning' ), array( 'status' => 404 ) );
		}

		if ( $record->does_mdl_user_exist() ) {
			$mappings = $record->get_contact_mappings( true );
			if ( is_array( $mappings ) && count( $mappings ) > 0 ) {
				// loop through mappings and unmap
				foreach( $mappings as $mapping ) {
					$this->logger->debug( sprintf( __( 'Will unmap and delete \'%s\'', 'tvs-moodle-parent-provisioning' ), $mapping ) );
					$mapping->unmap();
					$mapping->delete();
				}
			}
			$record->deprovision( 'deleting' );
			$record->delete();
		}
	}


	/**
	 * Determine whether or not the currently logged in user is permitted to access
	 * these entities.
	 *
	 * @return bool
	 */
	public function user_has_permission() {
		return current_user_can( TVS_PMP_REQUIRED_CAPABILITY );
	}


};

?>
