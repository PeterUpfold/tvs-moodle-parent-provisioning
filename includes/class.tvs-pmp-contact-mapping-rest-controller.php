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

require_once( dirname( __FILE__ ) . '/class.tvs-pmp-contact.php' );
require_once( dirname( __FILE__ ) . '/class.tvs-pmp-contact-mapping.php' );
require_once( dirname( __FILE__ ) . '/class.tvs-pmp-mdl-db-helper.php' );


/**
 * A custom WP_REST_Controller class that supports our REST methods
 * for managing Contact Mappings, which connect a Moodle parent account
 * to a Moodle pupil account.
 */
class TVS_PMP_Contact_Mapping_REST_Controller extends WP_REST_Controller {

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
		$this->namespace = TVS_PMP_Contact_Mapping_REST_Controller::API_NAMESPACE;
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
		register_rest_route( $this->namespace, '/contact-mapping', array(
			/* POST /contact-mapping ("ensure contact mapping") */

			array(
				'methods'                            => WP_REST_Server::EDITABLE,
				'callback'                           => array( $this, 'ensure_item' ),
				'permission_callback'                => array( $this, 'user_has_permission' ),
				'args'                               => $this->ensure_args()
			),

			/* GET /contact-mapping ("get all contact mappings") */
			array(
				'methods'                            => WP_REST_Server::READABLE,
				'callback'                           => array( $this, 'get_items' ),
				'permission_callback'                => array( $this, 'user_has_permission' ),
			),

		) );

			/* GET /contact-mapping/target/[moodleuserid] */
		register_rest_route( $this->namespace, '/contact-mapping/target/(?P<mdl_user_id>[\d]+)', array(
			'args' => array(
				'mdl_user_id' => array(
					'description'                => __( 'Moodle user ID of the target of this Contact Mapping (i.e. the user context in which the Parent role is assigned -- the pupil user ID.)', 'tvs-moodle-parent-provisioning' ),
					'type'                       => 'integer',
				)
			),
			array(
				'methods'                            => WP_REST_Server::READABLE,
				'callback'                           => array( $this, 'get_item' ),
				'permission_callback'                => array( $this, 'user_has_permission' ),
			),

			array(
				'methods'                            => WP_REST_Server::EDITABLE,
				'callback'                           => array( $this, 'ensure_item' ),
				'permission_callback'                => array( $this, 'user_has_permission' ),
				'args'                               => $this->ensure_args()
			),

			/* There is no support for 'updating' a Mapping, as the only properties that are relevant are the target user
			 * and the user who is given permissions to the target user.
			 *
			 * In the instance where a Mapping should be changed, the old mapping should be deleted and a new one created.
			 */
			array(
				'methods'                            => WP_REST_Server::DELETABLE,
				'callback'                           => array( $this, 'delete_item' ),
				'permission_callback'                => array( $this, 'user_has_permission' ),
				'args'                               => $this->get_delete_args(),
			)
		) );

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
	

	/*
	 * The expected arguments for the get_item and delete_item methods.
	 *
	 * @return array
	 */
	public function get_delete_args() {
		return array(
			'id'      => array(
				'validate_callback'          => function( $param, $request, $key ) {
					return ! empty( $param ) && is_numeric( $param );
				}
			),
			'contact_id' => array(
				'validate_callback'          => function( $param, $request, $key ) {
					return ! empty( $param ) && is_numeric( $param );
				},
			),
			'adno'       => array(
				'validate_callback'          => function( $param, $request, $key ) {
					return ! empty( $param );
				}
			)
		);
	}

	/*
	 * The expected arguments for the 'ensure' method.
	 *
	 * @return array
	 */
	public function ensure_args() {
		return array(
			'id'    => array(
				'validate_callback'          => function( $param, $request, $key ) {
					return ! empty( $param ) && is_numeric( $param );
				}
			),
			'contact_id' => array(
				'validate_callback'          => function( $param, $request, $key ) {
					return ! empty( $param ) && is_numeric( $param );
				},
				'required'                   => true
		),
			'mis_id'     => array(
				'validate_callback'          => function( $param, $request, $key ) {
					return ! empty( $param ) && is_numeric( $param );
				}
		),
			'external_mis_id' => array(
				'validate_callback'          => function( $param, $request, $key ) {
					return ! empty( $param );
				}
		),
			'adno'            => array(
				'validate_callback'          => function( $param, $request, $key ) {
					return ! empty( $param );
				},
				'required'                   => true
			),
			'username'        => array(
				'validate_callback'          => function( $param, $request, $key ) {
					return ! empty( $param );
				}
		),
			'date_synced'     => array( 
							'validate_callback'          => function( $param, $request, $key ) {
					return ! empty( $param );
							}
		),
		);
	}

	/**
	 * Retrieve all Contact Mappings matching the properties specified in the $request.
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 *
	 * @return WP_REST_Response|WP_Error
	 */
	public function get_items( $request ) {
		//TODO verify that permissions checks do not need to be added here
		$this->ensure_logger_and_dbc();

		$records = TVS_PMP_Contact_Mapping::load_all( $this->logger, $this->dbc );	

		return rest_ensure_response( $records );
	}


	/**
	 * Handle a request to ensure that the specified Contact Mapping exists. This will either create
	 * a new Contact Mapping between the source Contact and target Adno, or simply return success
	 * in the case where this Mapping already exists.
	 *
	 * @param WP_REST_Request $request The full details about the REST request.
	 *
	 * @return WP_REST_Response|WP_Error
	 */
	public function ensure_item( WP_REST_Request $request ) {

		//TODO verify that permissions checks do not need to be added here
		//	
	
		$this->ensure_logger_and_dbc();

		// look up the contact with the passed contact_id
		$contact = new TVS_PMP_Contact( $this->logger, $this->dbc );
		$contact->id = intval( $request->get_param( 'contact_id' ) );
		if  ( ! $contact->load( 'id' ) ) {
			return new WP_Error( sprintf(
				__( 'Unable to find the Contact with internal ID %d. Cannot therefore ensure that a Contact Mapping exists.', 'tvs-moodle-parent-provisioning' ),
				$request->get_param( 'contact_id' ) ),
			array(
				'status' => 404
			)
			);
		}

		// determine if mapping exists and is mapped
		$mapping = new TVS_PMP_Contact_Mapping( $this->logger, $this->dbc, $contact );
		$mapping->contact_id = $contact->id;
		$mapping->adno = $request->get_param( 'adno' );
		if ( $mapping->load( 'contact_id_and_adno' ) ) {
			$this->logger->debug( sprintf( __( 'Determined that Contact Mapping \'%s\' exists within our database. Will check if it is actually mapped.', 'tvs-moodle-parent-provisioning' ), $mapping ) );

			if ( ! $mapping->is_mapped() ) {
				$this->logger->warning( sprintf( __( '\'%s\' was in the internal database, but not actually mapped within Moodle. This will be corrected.', 'tvs-moodle-parent-provisioning' ), $mapping ) );
				if ( ! $mapping->map() ) {
					return new WP_Error(
						sprintf( __( 'Failed to actually perform the mapping of \'%s\' in the Moodle database.', 'tvs-moodle-parent-provisioning' ), $mapping ),
						array(
							'status' => 500
						)	
					);
				}
			}
			else {
				$this->logger->debug( sprintf( __( '\'%s\' is mapped correctly.', 'tvs-moodle-parent-provisioning' ), $mapping ) );
				$mapping->date_synced = date( 'Y-m-d H:i:s' );
				$mapping->save();
			}
			return new WP_REST_Response( array(
				'success'            => true,
				'record'             => $mapping
			) );
		}

		// mapping does not exist, so we should make it happen
		$mapping->external_mis_id = $request->get_param( 'external_mis_id' );
		$mapping->mis_id = $request->get_param( 'mis_id' );
		$mapping->date_synced = date( 'Y-m-d H:i:s' );
		if ( ! $mapping->save() ) {
			return new WP_Error(
				sprintf( __( 'Failed to save the Contact Mapping \'%s\' in the internal database.', 'tvs-moodle-parent-provisioning' ), $mapping ),
				array(
					'status' => 500
				)	
			);
		}

		if ( ! $mapping->map() ) {
			return new WP_Error(
				sprintf( __( 'Failed to actually perform the mapping of \'%s\' in the Moodle database.', 'tvs-moodle-parent-provisioning' ), $mapping ),
				array(
					'status' => 500
				)	
			);
		}

		return new WP_REST_Response( array(
				'success'            => true,
				'record'             => $mapping
		) );
	}

	/**
	 * Unmap and delete this Contact Mapping.
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 *
	 * @return WP_REST_Response|WP_Error
	 */
	public function delete_item( $request ) {
		$this->ensure_logger_and_dbc();

		$this->logger->debug( 'delete item ' );


		/* explicitly do not use Moodle user ID -- we are looking for the Contact, not the target
		   of the Contact Mapping
		 */

		try {
			$record = $this->try_get_record_from_request( $request, /* ignore_params */ array( 'mdl_user_id' ) );
		}
		catch ( Exception $e ) {
			$this->logger->error( $e->getMessage() );
			return new WP_Error( 'failed_get_record', __( 'Unable to look up the Contact Mapping.', 'tvs-moodle-parent-provisioning' ) );
		}

		if ( ! $record || ! $record->id ) {
			return new WP_Error( 'rest_post_invalid_id', __( 'Unable to look up the Contact Mapping.', 'tvs-moodle-parent-provisioning' ), array( 'status' => 404 ) );
		}

		if ( $record->is_mapped() ) {
			if ( ! $record->unmap() ) {
				return new WP_Error( 'failed_unmap', sprintf( __( 'Failed to unmap \'%s\'', 'tvs-moodle-parent-provisioning' ), $record ), array( 'status' => 500 ) );
			}
		}

		if ( ! $record->delete() ) {
				return new WP_Error( 'failed_delete', sprintf( __( 'Failed to delete \'%s\' from internal database', 'tvs-moodle-parent-provisioning' ), $record ), array( 'status' => 500 ) );
		}

		return rest_ensure_response( array( 'success' => true ) );

	}

	/**
	 * Get the specified Contact Mapping based on the identifier(s) supplied in
	 * $request.
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function get_item( $request ) {
		$this->ensure_logger_and_dbc();

		$record = $this->try_get_record_from_request( $request );
		if ( ! $record || ! $record->id ) {
			return new WP_Error( 'rest_post_invalid_id', __( 'Unable to look up the Contact Mapping.', 'tvs-moodle-parent-provisioning' ), array( 'status' => 404 ) );
		}

		return new WP_REST_Response( array(
			'success' => true,
			'record'  => $record
		) );
	}

	/**
	 * Attempt to look up a Contact Mapping from one of the unique identifiers or sets
	 * of unique identifiers (Contact ID->target Moodle user ID for example) that were
	 * submitted in the $request.
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @param array List of parameters to ignore when looking up the record
	 *
	 * @return TVS_PMP_Contact_Mapping
	 */
	protected function try_get_record_from_request( WP_REST_Request $request, $ignore_params = array() ) {

		$this->ensure_logger_and_dbc();

		$contact = NULL;

		if ( ! is_array( $ignore_params ) ) {
			throw new InvalidArgumentException( __( 'The $ignore_params parameter must be an array.', 'tvs-moodle-parent-provisioning' ) );
		}

		// determine if we have a unique ID to look up
		$id = $request->get_param( 'id' );

		$this->logger->debug( print_r( $ignore_params, TRUE ) );

		if ( ! in_array( 'contact_id', $ignore_params ) ) {
			$contact_id = $request->get_param( 'contact_id' );
		}
		if ( ! in_array( 'mdl_user_id', $ignore_params ) ) {
			$mdl_user_id = $request->get_param( 'mdl_user_id' );
		}

		$this->logger->debug( print_r( $request->get_params(), TRUE ) );
		$this->logger->debug( $contact_id );
		$this->logger->debug( 'mdl_user_id: ' . $mdl_user_id );

		$this->logger->debug( 'Running try_get_record_from_request' );

		if ( isset( $mdl_user_id ) && $mdl_user_id > 0 ) {
			// look up Contact ID from Moodle user
			$contact = new TVS_PMP_Contact( $this->logger, $this->dbc );
			$contact->mdl_user_id = intval( $mdl_user_id );
			if ( $contact->load( 'mdl_user_id' ) ) {
				$this->logger->debug( sprintf( __( 'Found Contact \'%s\' to match Moodle user ID %d', 'tvs-moodle-parent-provisioning' ), $contact, $contact->mdl_user_id ) );
				$contact_id = $contact->id;
			}
		}

		if ( ! $contact && $contact_id ) {
			$contact = new TVS_PMP_Contact( $this->logger, $this->dbc );
			$contact->id = intval( $contact_id );

			$this->logger->debug( 'contact_id ' . $contact_id );

			if ( $contact->load( 'id' ) ) {
				$this->logger->debug( sprintf( __( 'Found Contact \'%s\'.', 'tvs-moodle-parent-provisioning' ), $contact ) );
			}
		}

		$adno = $request->get_param( 'adno' );


		$record = new TVS_PMP_Contact_Mapping( $this->logger, $this->dbc, $contact ); // we will 'load' to see if it exists

		if ( NULL != $id ) {
			$record->id = $id;
			if ( ! $record->load( 'id' ) ) {
				$this->logger->warning( sprintf( __( 'Unable to load Contact Mapping with internal ID %d', 'tvs-moodle-parent-provisioning' ), $id ) );
				$record = NULL;
			}
		}
		else if ( NULL != $contact_id && NULL != $adno ) {
			// look up by internal Contact ID and Adno combination
			$this->logger->debug( __( 'Try to load Mapping by Contact ID and Adno', 'tvs-moodle-parent-provisioning' ) );
			$record->contact_id = intval( $contact_id );
			$record->adno = $adno;
			if ( ! $record->load( 'contact_id_and_adno' ) ) {
				$this->logger->warning( sprintf( __( 'Unable to load Contact Mapping with contact ID %d and Adno \'%s\'', 'tvs-moodle-parent-provisioning' ), $contact_id, $adno ) );
				$record = NULL;
			}
		}
		else {
			// cannot create a new Contact Mapping here without access to full contact object
			$this->logger->debug( __( 'Unable to match Contact Mapping. Neither the ID was specified nor a Contact ID and Adno.', 'tvs-moodle-parent-provisioning' ) );
			return NULL;
		}

		return $record;
	}

};
