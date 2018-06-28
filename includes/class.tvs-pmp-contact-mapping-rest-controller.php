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
		$this->namespace = TVS_PMP_Actions_REST_Controller::API_NAMESPACE;
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
				'methods'                            => WP_REST_Server::UPDATABLE,
				'callback'                           => array( $this, 'ensure_item' ),
				'permission_callback'                => array( $this, 'user_has_permission' ),
				'args'                               => array( $this, 'ensure_args' )
			),

			/* GET /contact-mapping ("get all contact mappings") */
			array(
				'methods'                            => WP_REST_Server::READABLE,
				'callback'                           => array( $this, 'get_items' ),
				'permission_callback'                => array( $this, 'user_has_permission' ),
			)
		) );

		register_rest_route( $this->namespace, '/contact-mapping/target/(?P<id>[\d]+)', array(
			'args' => array(
				'id' => array(
					'description'                => __( 'Moodle user ID of the target of this Contact Mapping (i.e. the user context in which the Parent role is assigned -- the pupil user ID.)', 'tvs-moodle-parent-provisioning' ),
					'type'                       => 'integer',
				)
			),
			array(
				'methods'                            => WP_REST_Server::READABLE,
				'callback'                           => array( $this, 'get_item' ),
				'permission_callback'                => array( $this, 'user_has_permission' ),
			),

			/* There is no support for 'updating' a Mapping, as the only properties that are relevant are the target user
			 * and the user who is given permissions to the target user.
			 *
			 * In the instance where a Mapping should be changed, the old mapping should be deleted and a new one created.
			 */
			array(
				'methods'                            => WP_REST_Server::DELETABLE,
				'callback'                           => array( $this, 'delete_item' ),
				'permission_callback'                => array( $this, 'user_has_permision' ),
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
		$contact->id = $request->get_param( 'contact_id' );
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
				$this->logger->warn( sprintf( __( '\'%s\' was in the internal database, but not actually mapped within Moodle. This will be corrected.', 'tvs-moodle-parent-provisioning' ), $mapping ) );
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
	}

};
