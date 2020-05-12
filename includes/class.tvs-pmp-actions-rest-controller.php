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

if (  class_exists( 'WP_REST_Controller' ) ) {

	/**
	 * A custom WP_REST_Controller class that supports our REST methods for  
	 * managing Parent Account requests.
	 */
	class TVS_PMP_Actions_REST_Controller extends WP_REST_Controller {

		/**
		 * The version number of the WP-JSON Parent Moodle Provisioning API.
		 */
		const API_VERSION = '1.0';

		/**
		 * The namespace of the WP-JSON Parent Moodle Provisioning API.
		 */
		const API_NAMESPACE = 'testvalleyschool/v1';

		/**
		 * Create a new object of this type.
		 */
		public function __construct() {
			$this->namespace = TVS_PMP_Actions_REST_Controller::API_NAMESPACE;
		}

		/**
		 * Return whether or not the input parameter is a string and has a length
		 * greater than zero.
		 *
		 * @return bool
		 */
		protected function string_valid_and_not_empty( $param ) {
			return (is_string( $param ) && strlen( trim( $param ) ) > 0);
		}

		/**
		 * Based on which parameter $key this has (child_, child2_, child3_), check
		 * that the $request object also contains valid data for the other fields relating
		 * to that child number. i.e. if you supply a child2_fname, you must also supply
		 * a child2_sname and child2_tg.
		 *
		 * @return bool
		 */
		protected function check_other_child_fields( $param, $request, $key ) {
			if ( preg_match( '/child([2-3])_/', $key, $matches ) !== 1 ) {
				return false;
			}
			$prefix = $matches[1];

			$other_fields = array(
					'fname',
				'sname',
				'tg'
			);

			foreach( $other_fields as $other_field ) {
				if ( ! $this->string_valid_and_not_empty( $request->get_param( 'child' . $prefix . '_' . $other_field ) ) ) {
					return false;
				}
			}

			return true;
		}

		
		/**
		 * Register our Ajax actions which use the WP-JSON API for external access by the
		 * PowerShell script.
		 */
		public function register_routes() {
			
			register_rest_route( $this->namespace, '/parent-account-request', array(
				array(
					'methods'		=> WP_REST_Server::CREATABLE,
					'callback'		=> array( $this, 'create_item' ),
					'permission_callback'   => function() {
									return current_user_can( TVS_PMP_REQUIRED_CAPABILITY );
								},
					'args' 			=> $this->get_endpoint_args()	
				)
			) );

			register_rest_route( $this->namespace, '/parent-account-request/(?P<id>[\d]+)', array(
				'args' => array(
					'id' => array(
						'description' => __( 'Unique identifier for the object.' ),
						'type'        => 'integer',
					),
				),
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_item' ),
					'permission_callback' => array( $this, 'get_item_permissions_check' ),
				),
				array(
					'methods'             => WP_REST_Server::EDITABLE,
					'callback'            => array( $this, 'update_item' ),
					'permission_callback' => function() {
									return current_user_can( TVS_PMP_REQUIRED_CAPABILITY );
								},
					'args'                => $this->get_endpoint_args()
				)
			) );
			
			register_rest_route( $this->namespace, '/parent-account-request/(?P<email>[a-ZA-Z0-9@\.-_]+)', [
				'args' => [
					'email' => [
						'description' => 'Email address of parent request',
						'type'        => 'string'
					]
				],
				[
					/* readable */
					'methods' => WP_REST_Server::READABLE,
					'callback' => [ $this, 'get_by_email' ],
					'permission_callback' => [ $this, 'get_item_permissions_check' ]
				],
				[
					/*editable*/
					'methods' => WP_REST_Server::EDITABLE,
					'callback' => [ $this, 'update_by_email' ],
					'permission_callback' => [ $this, 'get_item_permissions_check' ],
					'args'  => [
						'mis_id' => [
							'validate_callback' => function( $param, $request, $key) {
								return ctype_digit( $param );
							},
							'required' => true
						],
						'external_mis_id' => [
							'validate_callback' => function( $param, $request, $key) {
								return ( preg_match( '/^([a-f0-9]){8}-([a-f0-9]){4}-([a-f0-9]){4}-([a-f0-9]){4}-([a-f0-9]){12}$/', $param ) === 1 );
							},
							'required' => true

						]
					]
				]
			]
			);

		}

		public function get_by_email( $request ) {

		}


		/**
		 * Update the mis_id and external_mis_id for the request that matches this email address.
		 */
		public function update_by_email( $request ) {
			global $wpdb;

			// UPDATE {$wpdb->prefix}tvs_parent_moodle_provisioning SET mis_id = %d, external_mis_id = %s WHERE status = %s AND parent_email = %s

			return rest_ensure_response( $wpdb->update( 'tvs_parent_moodle_provisioning',
				[ /* data */
					'mis_id' => $request->get_param( 'mis_id' ),
					'external_mis_id' => $request->get_param( 'external_mis_id' )
				],
				[ /* where */
					'status' => 'provisioned',
					'parent_email' => $request->get_param( 'email' )
				],
				[ /* data format */
					'%d',
					'%s'
				],
				[ /* where format */
					'%s',
					'%s'
				]
			) );

		}

		/**
		 * Return the list of arguments that the REST API methods expect.
		 *
		 * @return array
		 */
		protected function get_endpoint_args() {
			return array(
				'parent_title' => array(
					'validate_callback' => function( $param, $request, $key ) {
						return $this->string_valid_and_not_empty( $param );
					},
					'required' => true
				),
				'parent_fname' => array(
					'validate_callback' => function( $param, $request, $key ) {
						return $this->string_valid_and_not_empty( $param );
					},
					'required' => true
				),
				'parent_sname' => array(
					'validate_callback' => function( $param, $request, $key ) {
						return $this->string_valid_and_not_empty( $param );
					},
					'required' => true
				),
				'child_fname' => array(
					'validate_callback' => function( $param, $request, $key ) {
						return $this->string_valid_and_not_empty( $param );
					},
					'required' => true
				),
				'child_sname' => array(
					'validate_callback' => function( $param, $request, $key ) {
						return $this->string_valid_and_not_empty( $param );
					},
					'required' => true
				),
				'child_tg' => array(
					'validate_callback' => function( $param, $request, $key ) {
						return $this->string_valid_and_not_empty( $param );
					},
					'required' => true
				),
				'parent_email' => array(
					'validate_callback' => function( $param, $request, $key ) {
						return filter_var( $param, FILTER_VALIDATE_EMAIL );
					},
					'required' => true
				),
				'child2_fname' => array(
					'validate_callback' => function( $param, $request, $key ) {
						return $this->check_other_child_fields( $param, $request, $key );
					},
				),
				'child2_sname' => array(
					'validate_callback' => function( $param, $request, $key ) {
						return $this->check_other_child_fields( $param, $request, $key );
					},
				),
				'child2_tg' => array(
					'validate_callback' => function( $param, $request, $key ) {
						return $this->check_other_child_fields( $param, $request, $key );
					},
				),
				'child3_fname' => array(
					'validate_callback' => function( $param, $request, $key ) {
						return $this->check_other_child_fields( $param, $request, $key );
					},
				),
				'child3_sname' => array(
					'validate_callback' => function( $param, $request, $key ) {
						return $this->check_other_child_fields( $param, $request, $key );
					},
				),
				'child3_tg' => array(
					'validate_callback' => function( $param, $request, $key ) {
						return $this->check_other_child_fields( $param, $request, $key );
					},
				),
				'status' => array(
					'validate_callback' => function( $param, $request, $key ) {
						return in_array( $param, TVS_PMP_Request::$statuses, true );
					},
					'required' => true
				),
				'parent_comment' => array(
					'validate_callback' => function( $param, $request, $key ) {
						return is_string( $param );
					},
				),
				'staff_comment' => array(
					'validate_callback' => function( $param, $request, $key ) {
						return is_string( $param );
					},
				),
				'system_comment' => array(
					'validate_callback' => function( $param, $request, $key ) {
						return $this->string_valid_and_not_empty( $param );
					},
					'required' => true
				),
				'date_created' => array(
					'validate_callback' => function( $param, $request, $key ) {
						return ( strtotime( $param ) !== false );
					},
					'required' => true
				),
				'date_updated' => array(
					'validate_callback' => function( $param, $request, $key ) {
						return ( strtotime( $param ) !== false );
					},
					'required' => true
				),
				'date_approved' => array(
					'validate_callback' => function( $param, $request, $key ) {
						return ( strtotime( $param ) !== false );
					},
					'required' => true
				),
				'remote_ip_addr' => array(
					'validate_callback' => function( $param, $request, $key ) {
						return $this->string_valid_and_not_empty( $param );
					},
					'required' => true
				),
				'request_type' => array(
					'validate_callback' => function( $param, $request, $key ) {
						return $this->string_valid_and_not_empty( $param );
					},
					'required' => true
				)
			);

		}

		/**
		 * Handle a WP-JSON REST API request to create ('POST') a 
		 * parent account request.
		 */
		public function create_item( $request ) {
			// create a new request based on the passed parameters, pre-validated by the callbacks
			if ( ! current_user_can( TVS_PMP_REQUIRED_CAPABILITY ) ) {
				return new WP_Error( 403, __( 'You do not have permission to perform this action.', 'tvs-moodle-parent-provisioning' ) ); 
			}

			$parent_account_request = new TVS_PMP_Request();
			$parent_account_request->request_type = __( 'Uploaded in a batch via the REST API', 'tvs-moodle-parent-provisioning' );
			$parent_account_request->parent_title = $request->get_param( 'parent_title' );
			$parent_account_request->parent_fname = $request->get_param( 'parent_fname' );
			$parent_account_request->parent_sname = $request->get_param( 'parent_sname' );
			$parent_account_request->child_fname = $request->get_param( 'child_fname' );
			$parent_account_request->child_sname = $request->get_param( 'child_sname' );
			$parent_account_request->child_tg = $request->get_param( 'child_tg' );
			$parent_account_request->parent_email = $request->get_param( 'parent_email' );
			$parent_account_request->child2_fname = $request->get_param( 'child2_fname' );
			$parent_account_request->child2_sname = $request->get_param( 'child2_sname' );
			$parent_account_request->child2_tg = $request->get_param( 'child2_tg' );
			$parent_account_request->child3_fname = $request->get_param( 'child3_fname' );
			$parent_account_request->child3_sname = $request->get_param( 'child3_sname' );
			$parent_account_request->child3_tg = $request->get_param( 'child3_tg' );
			$parent_account_request->status = $request->get_param( 'status' );
			$parent_account_request->parent_comment = $request->get_param( 'parent_comment' );
			$parent_account_request->staff_comment = $request->get_param( 'staff_comment' );
			$parent_account_request->system_comment = $request->get_param( 'system_comment' );
			$parent_account_request->date_created = strtotime( $request->get_param( 'date_created' ) );
			$parent_account_request->date_updated = strtotime( $request->get_param( 'date_updated' ) );
			$parent_account_request->date_approved = strtotime( $request->get_param( 'date_approved' ) );
			$parent_account_request->remote_ip_addr = $request->get_param( 'remote_ip_addr' );
			$parent_account_request->provisioned_username = $request->get_param( 'provisioned_username' );
			$parent_account_request->provisioned_initialpass = $request->get_param( 'provisioned_initialpass' );

			$parent_account_request->save();

			$response_data = array(
				'id'           => $parent_account_request->id
			);

			$response = new WP_REST_Response( $response_data );
			$response->set_status( 201 );

			return $response;
		}

		/**
		 * Checks if a given request has access to get items.
		 *
		 * @return WP_Error|bool True if the request has read access, WP_Error object otherwise.
		 */
		public function get_items_permissions_check( $request ) {
			// all parent request items simply require TVS_PMP_REQUIRED_CAPABILITY
			if ( current_user_can( TVS_PMP_REQUIRED_CAPABILITY ) ) {
				return true;
			}
			return new WP_Error( 'tvs_pmp_no_cap', __( 'You do not have permission to perform this action.', 'tvs-moodle-parent-provisioning' ) ); 
		}

		/**
		 * Checks if a given request has access to get a specific item.
		 *
		 * @return WP_Error|bool True if the request has read access, WP_Error object otherwise.
		 */
		public function get_item_permissions_check( $request ) {
			return $this->get_items_permissions_check( $request );
		}

		/**
		 * Checks if a given request has access to create items.
		 *
		 * @return WP_Error|bool True if the request has read access, WP_Error object otherwise.
		 */	
		public function create_item_permissions_check( $request ) {
			return $this->get_item_permissions_check( $request );
		}

		/**
		 * Checks if a given request has access to update a specific item.
		 *
		 * @return WP_Error|bool True if the request has read access, WP_Error object otherwise.
		 */	
		public function update_item_permissions_check( $request ) {
			return $this->get_item_permissions_check( $request );
		}

		/**
		 * Checks if a given request has access to delete a specific item.
		 *
		 * @return WP_Error|bool True if the request has read access, WP_Error object otherwise.
		 */	
		public function delete_item_permissions_check( $request ) {
			return $this->get_item_permissions_check( $request );
		}

		/**
		 * Retrieves a collection of Parent Moodle requests.
		 *
		 * @param WP_REST_Request Full details about the request.
		 * @return WP_REST_Response|WP_Error Response object on success, or WP_Error object on failure.
		 */
		public function get_items( $request ) {
			//TODO stub

			return new WP_REST_Response( array() );
		}

		/**
		 * Retrieves one item from the collection.
		 *
		 * @param WP_REST_Request Full details about the request.
		 * @return WP_REST_Response|WP_Error Response object on success, or WP_Error object on failure.
		 */
		public function get_item( $request ) {
			
			$id = $request->get_param( 'id' );

			if ( $id === null ) {
				return new WP_Error( 'tvs_pmp_no_item_id',
					__( 'An \'id\' parameter was not supplied or was not valid.', 'tvs-moodle-parent-provisioning' )
				);
			}

			$parent_account_request = new TVS_PMP_Request();
			$parent_account_request->id = $id;

			if ($parent_account_request->load()) {
				return new WP_REST_Response( $parent_account_request );
			}
			
			return new WP_Error( 'rest_post_invalid_id', __( 'Invalid post ID.' ) );
		}

		/**
		 * Update a single Parent Moodle Account request object.
		 * 
		 * @param WP_REST_Request $request Full details about the request.
		 * @return WP_REST_Response|WP_Error Response object on success, or WP_Error object on failure.
		 */
		public function update_item( $request ) {
			
			$id = $request->get_param( 'id' );

			if ( $id === null ) {
				return new WP_Error( 'tvs_pmp_no_item_id',
					__( 'An \'id\' parameter was not supplied or was not valid.', 'tvs-moodle-parent-provisioning' )
				);
			}

			$parent_account_request = new TVS_PMP_Request();
			$parent_account_request->id = $id;

			if ( ! $parent_account_request->load() ) {
				return new WP_Error( 'rest_post_invalid_id', __( 'Invalid post ID.' ) );
			}
			
			// update fields
			$parent_account_request->parent_title = $request->get_param('parent_title');
			$parent_account_request->parent_fname = $request->get_param('parent_fname');
			$parent_account_request->parent_sname = $request->get_param('parent_sname');
			$parent_account_request->child_fname = $request->get_param('child_fname');
			$parent_account_request->child_sname = $request->get_param('child_sname');
			$parent_account_request->child_tg = $request->get_param('child_tg');
			$parent_account_request->parent_email = $request->get_param('parent_email');
			$parent_account_request->child2_fname = $request->get_param('child2_fname');
			$parent_account_request->child2_sname = $request->get_param('child2_sname');
			$parent_account_request->child2_tg = $request->get_param('child2_tg');
			$parent_account_request->child3_fname = $request->get_param('child3_fname');
			$parent_account_request->child3_sname = $request->get_param('child3_sname');
			$parent_account_request->child3_tg = $request->get_param('child3_tg');
			$parent_account_request->status = $request->get_param('status');
			$parent_account_request->parent_comment = $request->get_param('parent_comment');
			$parent_account_request->staff_comment = $request->get_param('staff_comment');
			$parent_account_request->system_comment = $request->get_param('system_comment');
			$parent_account_request->date_created = $request->get_param('date_created');
			$parent_account_request->date_updated = $request->get_param('date_updated');
			$parent_account_request->date_approved = $request->get_param('date_approved');
			$parent_account_request->remote_ip_addr = $request->get_param('remote_ip_addr');
			$parent_account_request->provisioned_username = $request->get_param('provisioned_username');
			$parent_account_request->provisioned_initialpass = $request->get_param('provisioned_initialpass');
			$parent_account_request->request_type = $request->get_param('request_type');


			return rest_ensure_response( $parent_account_request->save() );

		}

		/**
		 * Delete a single Parent Moodle Account request. Note that this should not be used
		 * merely to deprovision an account -- this removes all trace of the request from the system.
		 * @param WP_REST_Request $request Full details about the request.
		 * @return WP_REST_Response|WP_Error Response object on success, or WP_Error object on failure.
		 */
		public function delete_item( $request ) {
			$id = $request->get_param( 'id' );

			if ( $id === null ) {
				return new WP_Error( 'tvs_pmp_no_item_id',
					__( 'An \'id\' parameter was not supplied or was not valid.', 'tvs-moodle-parent-provisioning' )
				);
			}

			$parent_account_request = new TVS_PMP_Request();
			$parent_account_request->id = $id;

			if ( ! $parent_account_request->load() ) {
				return new WP_Error( 'rest_post_invalid_id', __( 'Invalid post ID.' ) );
			}
		}


	
	};
}
