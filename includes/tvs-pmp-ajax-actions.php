<?php
/* Copyright (C) 2016-2017 Test Valley School.


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
require_once( dirname( __FILE__ ) . '/class.tvs-pmp-request.php' );



class TVS_PMP_Ajax_Actions {

	/**
	 * Handle the Ajax event to provision a parent
	 */
	public function provision() {
		global $wpdb;

		check_ajax_referer( sha1( 'tvs-pmp-moodle-provisioning-ajax-request'), 'security', true );

		if ( ! current_user_can( TVS_PMP_REQUIRED_CAPABILITY ) ) {
			header( 'HTTP/1.0 403 Forbidden' );
			wp_send_json_error( __( 'You do not have permission to perform this action.', 'tvs-moodle-parent-provisioning' ) );
			die();
		}

		// look up data for this ID
		if ( ! array_key_exists( 'id', $_POST ) || empty( $_POST['id'] ) ) {
			wp_die( __( 'No parent request ID supplied.', 'tvs-moodle-parent-provisioning' ) );
		}	

		$row = $wpdb->get_row(
			$wpdb->prepare(
				'SELECT id, parent_title, parent_fname, parent_sname, child_fname, child_sname, child_tg, parent_email, child2_fname, child2_sname, child2_tg, child3_fname, child3_sname, child3_tg, status, parent_comment, staff_comment, system_comment, date_created, date_updated, date_approved, remote_ip_addr, provisioned_username, provisioned_initialpass FROM ' . $wpdb->prefix .'tvs_parent_moodle_provisioning WHERE id = %d',
				$_POST['id']
			)
		);

		if ( ! $row ) {
			wp_send_json( array( 'error' =>  __( 'Unable to get this request to determine its status. Does it still exist?', 'tvs-moodle-parent-provisioning' ) ) );

			wp_die( );
		}

		if ( empty( $row->parent_title ) || empty( $row->parent_fname ) || empty( $row->parent_sname) ) {
			wp_send_json( array( 'error' =>  __( 'The request was missing essential username or parent detail fields when fetched.', 'tvs-moodle-parent-provisioning' ) ) );
			wp_die( );
		}

		
		if ( $row->status != 'pending' ) {
			wp_send_json( array('error' => __( 'An account can only be provisioned from \'pending\' status.', 'tvs-moodle-parent-provisioning' ) ) );
			wp_die();
		}

		// write back system comment
		$new_sys_comment = $row->system_comment . PHP_EOL . __( 'Approved for provisioning at ' . date('j F Y H:i:s T') . ' -- awaiting next provision cycle', 'tvs-moodle-parent-provisioning' );

		// add to queue to provision by setting status to approved
		$new_status = 'approved';

		$wpdb->update( 	$wpdb->prefix .'tvs_parent_moodle_provisioning',
			array(
				'system_comment'		=> $new_sys_comment,
				'status'			=> 'approved',
				'date_updated'			=> gmdate('Y-m-d H:i:s'),
				'date_approved'			=> gmdate('Y-m-d H:i:s')
			), array(
				'id'				=> $row->id
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
				$row->parent_email
			)
		) );

		if ( count( $exists ) > 0 ) {
			$new_status = 'duplicate';

			$new_sys_comment .= PHP_EOL . __( 'Unable to provision, as email address already exists in auth table -- ', 'tvs-moodle-parent-provisioning' ) . date( 'j F Y H:i:s T');

			$wpdb->update( 	$wpdb->prefix .'tvs_parent_moodle_provisioning',
				array(
					'system_comment'		=> $new_sys_comment,
					'status'			=> 'duplicate',
					'date_updated'			=> gmdate('Y-m-d H:i:s')
				), array(
					'id'				=> $row->id
				), array(
					'%s',
					'%s',
					'%s'
				), array(
					'%d'
				)	
			 );
			

			TVS_PMP_Request::update_pending_count();

			wp_send_json( array( 'error' => __( 'Unable to provision, as email address already exists in auth table. Marked as duplicate. You can use the \'Auth Users\' view to delete the entry in the auth table for this email address if the account is orphaned.', 'tvs-moodle-parent-provisioning' ) ) );
			wp_die();
		
		}

                // add to external Moodle auth table and wait there until the next cron-initiated provision cycle
                $username = strtolower( $row->parent_email );
                $parent_title = $row->parent_title;
                $parent_fname = $row->parent_title . ' ' . $row->parent_fname;
                $parent_sname = $row->parent_sname;
                $parent_email = strtolower( $row->parent_email );
                $description = 'Parent Moodle Account';



                // add to external Moodle table

                $response = $wpdb->insert( $wpdb->prefix . 'tvs_parent_moodle_provisioning_auth',
                        array(
                                'username'        =>  stripslashes( trim( $username ) ),
                                'parent_title'    =>  stripslashes( trim( $parent_title ) ),
                                'parent_fname'    =>  stripslashes( trim( $parent_fname ) ),
                                'parent_sname'    =>  stripslashes( trim( $parent_sname ) ),
                                'parent_email'    =>  stripslashes( trim( $parent_email ) ),
                                'description'     =>  stripslashes( trim( $description ) ),
				'request_id'      =>  $row->id,
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


		TVS_PMP_Request::update_pending_count();

		wp_send_json( array('success' => $response, 'id' => intval( $_POST['id'] ) ) );


		wp_die();

	}

	/**
	 * Handle the onchange event for the staff comment field.
	 */
	public function update_staff_comment() {

		global $wpdb;

		check_ajax_referer( sha1( 'tvs-pmp-moodle-provisioning-ajax-request'), 'security', true );

		if ( ! current_user_can( TVS_PMP_REQUIRED_CAPABILITY ) ) {
			header( 'HTTP/1.0 403 Forbidden' );
			wp_send_json_error( __( 'You do not have permission to perform this action.', 'tvs-moodle-parent-provisioning' ) );
			die();
		}

		if ( ! array_key_exists( 'id', $_POST ) || empty( $_POST['id'] ) ) {
			wp_die( 'No parent request ID supplied.' );
		}	

		$response = $wpdb->update( $wpdb->prefix . 'tvs_parent_moodle_provisioning',
			array(
				'staff_comment'  => stripslashes( $_POST['staff_comment'] ),
				'date_updated'   => gmdate('Y-m-d H:i:s')
			),
			array(
				'id'             => $_POST['id']
			),
			array(
				'%s',
				'%s'
			),
			array(
				'%d'
			)
		);

		wp_send_json( array('success' => $response, 'id' => intval( $_POST['id'] ) ) );

	}


	/**
	 * Handle a reject request
	 */
	public function reject() {
	
		if ( ! current_user_can( TVS_PMP_REQUIRED_CAPABILITY ) ) {
			header( 'HTTP/1.0 403 Forbidden' );
			wp_send_json_error( __( 'You do not have permission to perform this action.', 'tvs-moodle-parent-provisioning' ) );
			die();
		}
		// remove from auth table to disable account
		$this->deprovision( $_POST['id'] );
		$this->set_generic_status( 'rejected' );
	}

	/**
	 * Handle a duplicate request
	 */
	public function duplicate() {
		$this->set_generic_status( 'duplicate' );
	}

	/**
	 * Handle a bogus request
	 */
	public function bogus() {
		$this->set_generic_status( 'bogus' );
	}

	/**
	 * Handle a pending request
	 */
	public function pending() {
		$this->set_generic_status( 'pending' );
	}

	/**
	 * Remove the specified parent from the auth table, thus disabling their Moodle access.
	 */
	protected function deprovision( $id ) {

		global $wpdb;

		if ( ! current_user_can( TVS_PMP_REQUIRED_CAPABILITY ) ) {
			header( 'HTTP/1.0 403 Forbidden' );
			wp_send_json_error( __( 'You do not have permission to perform this action.', 'tvs-moodle-parent-provisioning' ) );
			die();
		}	

                $response = $wpdb->delete( $wpdb->prefix . 'tvs_parent_moodle_provisioning_auth',
                        array(
				'request_id'      =>  $id,
                        ),
                        array(
                                '%d',
                        )
                );

		return $repsonse;
	}


	/**
	 * Send the templated forgotten password email to the parent email address.
	 */
	public function send_forgotten_password_email() {

		if ( ! current_user_can( TVS_PMP_REQUIRED_CAPABILITY ) ) {
			header( 'HTTP/1.0 403 Forbidden' );
			wp_send_json_error( __( 'You do not have permission to perform this action.', 'tvs-moodle-parent-provisioning' ) );
			die();
		}
		
		check_ajax_referer( sha1( 'tvs-pmp-moodle-provisioning-ajax-request'), 'security', true );


		// look up data for this ID
		if ( ! array_key_exists( 'id', $_POST ) || empty( $_POST['id'] ) ) {
			wp_die( 'No parent request ID supplied.' );
		}	

		
		$email_template = get_option( 'tvs-moodle-parent-provisioning-forgotten-password-email' );
		$subject = get_option( 'tvs-moodle-parent-provisioning-forgotten-password-email-subject' );

		$this->send_automated_email( $_POST['id'], $email_template, $subject, __( 'Forgotten Password', 'tvs-moodle-parent-provisioning' ) );
	}

	/**
	 * Send the templated details not on file email to the parent email address.
	 */
	public function send_details_not_on_file_email() {

		if ( ! current_user_can( TVS_PMP_REQUIRED_CAPABILITY ) ) {
			header( 'HTTP/1.0 403 Forbidden' );
			wp_send_json_error( __( 'You do not have permission to perform this action.', 'tvs-moodle-parent-provisioning' ) );
			die();
		}
		
		check_ajax_referer( sha1( 'tvs-pmp-moodle-provisioning-ajax-request'), 'security', true );


		// look up data for this ID
		if ( ! array_key_exists( 'id', $_POST ) || empty( $_POST['id'] ) ) {
			wp_die( 'No parent request ID supplied.' );
		}	

		
		$email_template = get_option( 'tvs-moodle-parent-provisioning-details-not-on-file-email' );
		$subject = get_option( 'tvs-moodle-parent-provisioning-details-not-on-file-email-subject' );

		$this->send_automated_email( $_POST['id'], $email_template, $subject, __( 'Details Not On File', 'tvs-moodle-parent-provisioning' ) );
	}

	/**
	 * Send the generic fixed email to the parent email address.
	 */
	public function send_generic_fixed_email() {

		if ( ! current_user_can( TVS_PMP_REQUIRED_CAPABILITY ) ) {
			header( 'HTTP/1.0 403 Forbidden' );
			wp_send_json_error( __( 'You do not have permission to perform this action.', 'tvs-moodle-parent-provisioning' ) );
			die();
		}
		
		check_ajax_referer( sha1( 'tvs-pmp-moodle-provisioning-ajax-request'), 'security', true );


		// look up data for this ID
		if ( ! array_key_exists( 'id', $_POST ) || empty( $_POST['id'] ) ) {
			wp_die( 'No parent request ID supplied.' );
		}	

		
		$email_template = get_option( 'tvs-moodle-parent-provisioning-generic-fixed-email' );
		$subject = get_option( 'tvs-moodle-parent-provisioning-generic-fixed-email-subject' );

		$this->send_automated_email( $_POST['id'], $email_template, $subject, __( 'Generic Fixed', 'tvs-moodle-parent-provisioning' ) );

	}

	/**
	 * Send an automated email response to the parent email address for a given request ID.
	 *
	 * @param int $id The account request ID.
	 * @param string $email_template The text of the email template to send. Can contain {{parent_title}}, {{parent_fname}}, {{parent_sname}}, {{parent_email}} fields.
	 * @param string $subject The subject of the email to send.
	 * @param string $email_type The type of email that is being sent. (e.g. "forgotten password"). Should be in a friendly, human-readable format as this string is added to the system comment of the request.
	 * 
	 */
	protected function send_automated_email( $id, $email_template, $subject, $email_type ) {
		global $wpdb;

		$before_tz = @date_default_timezone_get();
		date_default_timezone_set( get_option( 'timezone_string' ) );

		$row = $wpdb->get_row(
			$wpdb->prepare(
				'SELECT id, parent_title, parent_fname, parent_sname, child_fname, child_sname, child_tg, parent_email, child2_fname, child2_sname, child2_tg, child3_fname, child3_sname, child3_tg, status, parent_comment, staff_comment, system_comment, date_created, date_updated, date_approved, remote_ip_addr, provisioned_username, provisioned_initialpass FROM ' . $wpdb->prefix .'tvs_parent_moodle_provisioning WHERE id = %d',
				$id
			)
		);

		if ( ! $row ) {
			wp_send_json( array( 'error' =>  'Unable to get this request to determine its status. Does it still exist?' ) );

			wp_die( );
		}

		$email = str_replace( '{{parent_title}}', $row->parent_title, $email_template );
		$email = str_replace( '{{parent_fname}}', $row->parent_fname, $email );
		$email = str_replace( '{{parent_sname}}', $row->parent_sname, $email );
		$email = str_replace( '{{parent_email}}', $row->parent_email, $email );

		$pm = new \PHPMailer\PHPMailer\PHPMailer();

		$pm->isSMTP();
		$pm->Host = get_option( 'tvs-moodle-parent-provisioning-smtp-server' );
		$pm->Port = 587;
		$pm->SMTPSecure = 'tls';
		$pm->SMTPAuth = true;

		$pm->Username = get_option( 'tvs-moodle-parent-provisioning-smtp-username' );
		$pm->Password = get_option( 'tvs-moodle-parent-provisioning-smtp-password' );
		$pm->setFrom( get_option( 'tvs-moodle-parent-provisioning-smtp-username' ), get_bloginfo( 'name' ) );
		$pm->addAddress( $row->parent_email, $row->parent_title . ' ' . $row->parent_fname . ' ' . $row->parent_sname );
		$pm->Subject = stripslashes( $subject );

		$pm->Body = stripslashes( $email );

		if (!$pm->send()) {
			$this->append_sys_comment( $row->id, sprintf( __( 'Attempted to send %s email to %s at %s. Failed to send the email with the error \'%s\'.', 'tvs-moodle-parent-provisioning' ) , $email_type, esc_html( $row->parent_email ), date( 'd/m/Y H:i:s' ), $pm->ErrorInfo ), $row->system_comment );

			wp_send_json( array( 'error' => $pm->ErrorInfo, 'id' => $id ) );
		}
		else {
			$appended_comment = $this->append_sys_comment( $row->id, sprintf( __( 'Sent %s email to %s at %s.', 'tvs-moodle-parent-provisioning' ) , $email_type, esc_html( $row->parent_email ), date( 'd/m/Y H:i:s' ) ), $row->system_comment );
			wp_send_json_success( array( 'system_comment' => $appended_comment, 'id' => $id ) );
		}

	}

	/**
	 * JavaScript action handler for sending a forgotten password email.
	 */
	protected function send_automated_email_js( $action, $friendly_name ) {
		//TODO JS L10n
		?>
		<script type="text/javascript">
		jQuery(document).ready(function($) {
			$('.row-actions .<?php echo $action; ?> > a').click(function(e) {
				e.preventDefault();

				if (confirm( 'Please confirm that you want to send the \'<?php echo esc_attr( $friendly_name ); ?>\' email to this parent.' ) ) {

					$('.tvs-pmp-email-sending[data-request-id="' + $(this).data('request-id') + '"]').html('<span class="spinner is-active"></span>Sending email&hellip;');

					$.post(ajaxurl, {
						security:    '<?php echo wp_create_nonce( sha1( 'tvs-pmp-moodle-provisioning-ajax-request' ) ); ?>',
						action:      'tvs_pmp_<?php echo $action; ?>',
						id:          $(this).data('request-id')
					}, function(response) {
						if (response.success == 1 ) {
							$('#tvs-pmp-system-comment-textarea-' + response.data.id ).val( response.data.system_comment );
							$('.tvs-pmp-email-sending[data-request-id="' + response.data.id + '"]').html('<span class="dashicons dashicons-yes"></span> Email sent.');

							// scroll system comment down
							$('#tvs-pmp-system-comment-textarea-' + response.data.id ).animate({
								scrollTop: $('#tvs-pmp-system-comment-textarea-' + response.data.id ).prop('scrollHeight') - $('#tvs-pmp-system-comment-textarea-' + response.data.id ).height()
							}, 1000);

						}
						else if (response.error.length > 0) {
							alert(response.error);
							$('#tvs-pmp-error').html('<p>' + response.error + '</p>');
							$('#tvs-pmp-error').show('slow');
                		                        $('#tvs-pmp-error').delay(3000).fadeOut(1000);
							$('.tvs-pmp-email-sending[data-request-id="' + response.id + '"]').html(response.error);
						}
					}).fail(function() {
						$('#tvs-pmp-error').html('<p>Unable to make the request. Please use Developer Tools to investigate.</p>');
						$('#tvs-pmp-error').show('slow');
                	                        $('#tvs-pmp-error').delay(3000).fadeOut(1000);
						$('.tvs-pmp-email-sending[data-request-id="' + response.id + '"]').html(response.error);
					});
				}
			});
		});
		</script>
		<?php
	}

	/**
	 * Print the JavaScript for handling the click of the Send Forgotten Password Email button.
	 */
	public function send_forgotten_password_email_js() {
		$this->send_automated_email_js( 'send_forgotten_password_email', __( 'Forgotten Password', 'tvs-moodle-parent-provisioning' ) );
	}

	/**
	 * Print the JavaScript for handling the click of the Send Details Not On File Email button.
	 */
	public function send_details_not_on_file_email_js() {
		$this->send_automated_email_js( 'send_details_not_on_file_email', __( 'Details Not On File', 'tvs-moodle-parent-provisioning' ) );
	}

	/**
	 * Print the JavaScript for handling the click of the Send Generic Fixed Email button.
	 */
	public function send_generic_fixed_email_js() {
		$this->send_automated_email_js( 'send_generic_fixed_email', __( 'Generic Fixed', 'tvs-moodle-parent-provisioning' ) );
	}

	/** 
	 * Append to the current sys_comment field of the given request, so the system keeps a log of all actions taken.
	 */
	protected function append_sys_comment( $id, $comment, $current_comment ) {
		global $wpdb;
		$before_tz = @date_default_timezone_get();
		date_default_timezone_set( get_option( 'timezone_string' ) );

		$wpdb->update( 	$wpdb->prefix .'tvs_parent_moodle_provisioning',
			array(
				'system_comment'		=> stripslashes( $current_comment . PHP_EOL . $comment ),
				'date_updated'			=> gmdate('Y-m-d H:i:s')
			), array(
				'id'				=> $id
			), array(
				'%s',
				'%s',
			), array(
				'%d'
			)	
		 );

		 return $current_comment . PHP_EOL . $comment;
	}


	/**
	 * Set the request status to the specified status.
	 */
	protected function set_generic_status( $status = 'rejected' ) {
		global $wpdb;
		$before_tz = @date_default_timezone_get();
		date_default_timezone_set( get_option( 'timezone_string' ) );


		check_ajax_referer( sha1( 'tvs-pmp-moodle-provisioning-ajax-request'), 'security', true );

		if ( ! current_user_can( TVS_PMP_REQUIRED_CAPABILITY ) ) {
			header( 'HTTP/1.0 403 Forbidden' );
			wp_send_json_error( __( 'You do not have permission to perform this action.', 'tvs-moodle-parent-provisioning' ) );
			die();
		}


		// look up data for this ID
		if ( ! array_key_exists( 'id', $_POST ) || empty( $_POST['id'] ) ) {
			wp_die( 'No parent request ID supplied.' );
		}	

		$row = $wpdb->get_row(
			$wpdb->prepare(
				'SELECT id, parent_title, parent_fname, parent_sname, child_fname, child_sname, child_tg, parent_email, child2_fname, child2_sname, child2_tg, child3_fname, child3_sname, child3_tg, status, parent_comment, staff_comment, system_comment, date_created, date_updated, date_approved, remote_ip_addr, provisioned_username, provisioned_initialpass FROM ' . $wpdb->prefix .'tvs_parent_moodle_provisioning WHERE id = %d',
				$_POST['id']
			)
		);

		if ( ! $row ) {
			wp_send_json( array( 'error' =>  'Unable to get this request to determine its status. Does it still exist?' ) );

			wp_die( );
		}

		if ( empty( $row->parent_title ) || empty( $row->parent_fname ) || empty( $row->parent_sname) ) {
			wp_send_json( array( 'error' =>  'The request was missing essential username or parent detail fields when fetched.' ) );
			wp_die( );
		}

		if ( $row->status == 'provisioned' && $status != 'rejected' ) { 
			wp_send_json( array( 'error' => 'Unable to set this status -- user has already been provisioned. Use the Moodle Administration Users panel.' ) );
			wp_die();
		}

		// if the row status is currently approved, and we are setting back to 'pending', de-provision them
		$deprovision_statuses = array(
			'provisioned',
			'approved',
			'duplicate'
		);
		if ( in_array( $row->status, $deprovision_statuses ) && 'pending' == $status ) {
			$this->deprovision( $row->id );
		}

		// write back system comment
		$new_sys_comment = $row->system_comment . PHP_EOL . 'Status set to ' . $status .' by user at ' . date('j F Y H:i:s T') . '';

		// add to queue to provision by setting status to approved
		$new_status = $status;

		$wpdb->update( 	$wpdb->prefix .'tvs_parent_moodle_provisioning',
			array(
				'system_comment'		=> stripslashes( $new_sys_comment ),
				'status'			=> $new_status,
				'date_updated'			=> gmdate('Y-m-d H:i:s')
			), array(
				'id'				=> $row->id
			), array(
				'%s',
				'%s',
				'%s'
			), array(
				'%d'
			)	
		 );

		TVS_PMP_Request::update_pending_count();

		wp_send_json( array( 'success' => 1, 'id' => $row->id ) );
	}

	/**
	 * Print the client-side code for updating the staff comment
	 */
	public function update_staff_comment_js() {
		if ( array_key_exists( 'page', $_GET ) && 'tvs_parent_moodle_provisioning' == $_GET['page'] ) {
		?>
		<script type="text/javascript">
		jQuery(document).ready(function($) {
			$('.tvs-pmp-staff-comment-textarea').change( function(e) {
				$('#tvs-pmp-staff-comment-saving-' + $(this).data('parent-id') ).show();
				$('#tvs-pmp-staff-comment-saving-' + $(this).data('parent-id') ).html('Saving&hellip;');
				e.preventDefault();
				$.post(ajaxurl, {
					security:  '<?php echo wp_create_nonce( sha1( 'tvs-pmp-moodle-provisioning-ajax-request' ) ); ?>',
					action:    'tvs_pmp_update_staff_comment',
					id:        $(this).data('parent-id'),
					staff_comment: $(this).val()
				}, function(response) {
					if (parseInt(response.success) == 1 ) {
						$('#tvs-pmp-staff-comment-saving-' + response.id ).html('Saved comment.');
						$('#tvs-pmp-staff-comment-saving-' + response.id ).delay( 2000 ).hide('slow');
					}
					else {
						$('#tvs-pmp-staff-comment-saving-' + response.id ).html('Unable to save. ' + response.success);
					}
				});		
			});
		});
		</script>
		<?php
		}
	}


	/**
	 * Print the client-side code for provisioning a Moodle user
	 */
	public function provision_js() {
		if ( array_key_exists( 'page', $_GET ) && 'tvs_parent_moodle_provisioning' == $_GET['page'] ) { 
			// does provisioning make sense in this status
			if ( array_key_exists( 'status', $_GET ) && ( 'provisioned' == $_GET['status'] || 'approved' == $_GET['status'] ) ) {
				?>
				<script type="text/javascript">
				jQuery(document).ready(function($) {
					$('.provision > a').click(function(e) {
						$('#tvs-pmp-error').html('<p>Cannot approve this parent for provisioning if they are already approved or provisioned.</p>');
						$('#tvs-pmp-error').show('slow');
						$('#tvs-pmp-error').delay(3000).fadeOut(1000);
						e.preventDefault();
					});
				});
				</script>
				<?php
			} else {

		?>
		<script type="text/javascript">
		jQuery(document).ready(function($) {
			$('.provision > a').click( function(e) {
				e.preventDefault();
				$.post(ajaxurl, {
					security:  '<?php echo wp_create_nonce( sha1( 'tvs-pmp-moodle-provisioning-ajax-request' ) ); ?>',
					action:    'tvs_pmp_provision',
					id:        $(this).data('parent-id')
				}, function(response) {
					if ( response.success == 1 ) {
						$('.provision a[data-parent-id="' + response.id.toString() + '"]').parent().parent().parent().parent().hide( 'slow' );
						$('#tvs-pmp-success').html('<p>Approved for provisioning.</p>');
						$('#tvs-pmp-success').show('slow');
						$('#tvs-pmp-success').delay(3000).fadeOut(1000);
					}
					else {
						$('#tvs-pmp-error').html('<p>' + response.error + '</p>');
						$('#tvs-pmp-error').show('slow');
						$('#tvs-pmp-error').delay(17000).fadeOut(1000);
					}
				}).fail(function() { 
					$('#tvs-pmp-error').html('<p>Unable to make the request. Please use Developer Tools to investigate.</p>');
					$('#tvs-pmp-error').show('slow');
                                        $('#tvs-pmp-error').delay(3000).fadeOut(1000);
				});		
			});
		});
		</script>
		<?php
		} // end else
		}
	}

	/**
	 * A JavaScript handler for various similar parent request actions
	 */
	protected function generic_action_js( $action ) {
		
		?><script type="text/javascript">
		jQuery(document).ready(function($) {
			$('.row-actions .<?php echo $action; ?> > a, .row-actions .markas<?php echo $action;?> > a').click( function(e) {

				e.preventDefault();
				<?php
				/* If attempted action is to set back to pending, pop a warning about setting back to pending from duplicate
				status, which may de-provision an existing user.
				*/
				if ( 'pending' == $action ) :
				?>
				if ( confirm( 'Please note that if you set a status back to \'pending\', it will de-provision any Moodle account for this user, and disallow authentication with it.\n\nOnly take this action if you intend to re-issue access with a new Moodle account, or if the account had not been successfully provisioned in the first place.' ) ) {
				<?php endif; ?>

				$.post(ajaxurl, {
					security:  '<?php echo wp_create_nonce( sha1( 'tvs-pmp-moodle-provisioning-ajax-request' ) ); ?>',
					action:    'tvs_pmp_<?php echo $action; ?>',
					id:        $(this).data('parent-id')
				}, function(response) {
					if ( response.success == 1 ) {
						$('.provision a[data-parent-id="' + response.id.toString() + '"]').parent().parent().parent().parent().hide( 'slow' );
						<?php if ( 'reject' == $action ): ?>
						$('#tvs-pmp-success').html('<p>User request was rejected. If this user is already provisioned, their account will be disabled if Moodle\'s sync user script is run (<em>/var/www/moodle/moodle/auth/db/cli/sync_users.php</em>).');
						<?php else: ?>
						$('#tvs-pmp-success').html('<p>Status changed successfully.</p>');
						<?php endif; ?>
						$('#tvs-pmp-success').show('slow');
						$('#tvs-pmp-success').delay(3000).fadeOut(1000);
					}
					else {
						$('#tvs-pmp-error').html('<p>' + response.error + '</p>');
						$('#tvs-pmp-error').show('slow');
						$('#tvs-pmp-error').delay(3000).fadeOut(1000);
					}
				}).fail(function() { 
					$('#tvs-pmp-error').html('<p>Unable to make the request. Please use Developer Tools to investigate.</p>');
					$('#tvs-pmp-error').show('slow');
                                        $('#tvs-pmp-error').delay(3000).fadeOut(1000);
				});		
				<?php if ( 'pending' == $action ) : ?>
				}	
				<?php endif; ?>
			});
		});
		</script><?php
	
	}


	/** 
	 * Update the account request to correct any child names that are incorrect.
	 */
	public function adjust_childnames() {
		global $wpdb;

		check_ajax_referer( sha1( 'tvs-pmp-moodle-provisioning-ajax-request'), 'security', true );

		if ( ! current_user_can( TVS_PMP_REQUIRED_CAPABILITY ) ) {
			header( 'HTTP/1.0 403 Forbidden' );
			wp_send_json_error( __( 'You do not have permission to perform this action.', 'tvs-moodle-parent-provisioning' ) );
			die();
		}

		if ( ! array_key_exists( 'id', $_POST ) || empty( $_POST['id'] ) ) {
			wp_die( 'No parent request ID supplied.' );
		}	

		// strict fixes
		if ( ! array_key_exists( 'child2_fname', $_POST ) ) {
			$_POST['child2_fname'] = '';
		}
		if ( ! array_key_exists( 'child2_sname', $_POST ) ) {
			$_POST['child2_sname'] = '';
		}
		if ( ! array_key_exists( 'child2_tg', $_POST ) ) {
			$_POST['child2_tg'] = '';
		}
		if ( ! array_key_exists( 'child3_fname', $_POST ) ) {
			$_POST['child3_fname'] = '';
		}
		if ( ! array_key_exists( 'child3_sname', $_POST ) ) {
			$_POST['child3_sname'] = '';
		}
		if ( ! array_key_exists( 'child3_tg', $_POST ) ) {
			$_POST['child3_tg'] = '';
		}

		$response = $wpdb->update( $wpdb->prefix . 'tvs_parent_moodle_provisioning',
			array(
				'child_fname'    => stripslashes( trim( $_POST['child_fname'] ) ),
				'child_sname'    => stripslashes( trim( $_POST['child_sname'] ) ),
				'child_tg'    => stripslashes( trim( $_POST['child_tg'] ) ),
				'child2_fname'    => stripslashes( trim( $_POST['child2_fname'] ) ),
				'child2_sname'    => stripslashes( trim( $_POST['child2_sname'] ) ),
				'child2_tg'    => stripslashes( trim( $_POST['child2_tg'] ) ),
				'child3_fname'    => stripslashes( trim( $_POST['child3_fname'] ) ),
				'child3_sname'    => stripslashes( trim( $_POST['child3_sname'] ) ),
				'child3_tg'    => stripslashes( trim( $_POST['child3_tg'] ) ),
				'date_updated'   => gmdate('Y-m-d H:i:s')
			),
			array(
				'id'             => $_POST['id']
			),
			array(
				'%s',
				'%s'
			),
			array(
				'%d'
			)
		);

		wp_send_json(
			 array(
				'success'      => $response,
				'id'           => intval( $_POST['id'] ),
				'child_fname'  => stripslashes( trim( $_POST['child_fname'] ) ),
				'child_sname'  => stripslashes( trim( $_POST['child_sname'] ) ),
				'child_tg'  => stripslashes( trim( $_POST['child_tg'] ) ),
				'child2_fname' => stripslashes( trim( $_POST['child2_fname'] ) ),
				'child2_sname' => stripslashes( trim( $_POST['child2_sname'] ) ),
				'child2_tg' => stripslashes( trim( $_POST['child2_tg'] ) ),
				'child3_fname' => stripslashes( trim( $_POST['child3_fname'] ) ),
				'child3_sname' => stripslashes( trim( $_POST['child3_sname'] ) ),
				'child3_tg' => stripslashes( trim( $_POST['child3_tg'] ) ),
			)
		);


	}

	/**
	 * A JavaScript handler for editing pupil details to match Moodle records
	 */
	public function adjust_childnames_js() {
		if ( array_key_exists( 'page', $_GET ) && 'tvs_parent_moodle_provisioning' == $_GET['page'] ) {
		?>
		<script type="text/javascript">
		var adjusting_names = false;
		jQuery(document).ready(function($) {
			$('.adjustchildname > a').click(function(e) {
				e.preventDefault();

				var child1_fname = $('span.child_fname[data-request-id="' + $(this).data('request-id') + '"]').text().trim();
				var child1_sname = $('span.child_sname[data-request-id="' + $(this).data('request-id') + '"]').text().trim();
				var child1_tg = $('span.child_tg[data-request-id="' + $(this).data('request-id') + '"]').text().trim();
				var child2_fname = $('span.child2_fname[data-request-id="' + $(this).data('request-id') + '"]').text().trim();
				var child2_sname = $('span.child2_sname[data-request-id="' + $(this).data('request-id') + '"]').text().trim();
				var child2_tg = $('span.child2_tg[data-request-id="' + $(this).data('request-id') + '"]').text().trim();
				var child3_fname = $('span.child3_fname[data-request-id="' + $(this).data('request-id') + '"]').text().trim();
				var child3_sname = $('span.child3_sname[data-request-id="' + $(this).data('request-id') + '"]').text().trim();
				var child3_tg = $('span.child3_tg[data-request-id="' + $(this).data('request-id') + '"]').text().trim();

				$('span.child_fname[data-request-id="' + $(this).data('request-id') + '"]').html('');
				$('span.child_fname[data-request-id="' + $(this).data('request-id') + '"]').append("<input type='text' value='" + child1_fname + "' data-request-id='" + $(this).data('request-id') + "' data-name='child_fname' />");

				$('span.child_sname[data-request-id="' + $(this).data('request-id') + '"]').html('');
				$('span.child_sname[data-request-id="' + $(this).data('request-id') + '"]').append("<input type='text' value='" + child1_sname + "' data-request-id='" + $(this).data('request-id') + "' data-name='child_sname' />");

				$('span.child_tg[data-request-id="' + $(this).data('request-id') + '"]').html('');
				$('span.child_tg[data-request-id="' + $(this).data('request-id') + '"]').append("<input type='text' value='" + child1_tg + "' data-request-id='" + $(this).data('request-id') + "' data-name='child_tg' />");

				$('span.child2_fname[data-request-id="' + $(this).data('request-id') + '"]').html('');
				$('span.child2_fname[data-request-id="' + $(this).data('request-id') + '"]').append("<input type='text' value='" + child2_fname + "' data-request-id='" + $(this).data('request-id') + "' data-name='child2_fname' />");

				$('span.child2_sname[data-request-id="' + $(this).data('request-id') + '"]').html('');
				$('span.child2_sname[data-request-id="' + $(this).data('request-id') + '"]').append("<input type='text' value='" + child2_sname + "' data-request-id='" + $(this).data('request-id') + "' data-name='child2_sname' />");

				$('span.child2_tg[data-request-id="' + $(this).data('request-id') + '"]').html('');
				$('span.child2_tg[data-request-id="' + $(this).data('request-id') + '"]').append("<input type='text' value='" + child2_tg + "' data-request-id='" + $(this).data('request-id') + "' data-name='child2_tg' />");



				$('span.child3_fname[data-request-id="' + $(this).data('request-id') + '"]').html('');
				$('span.child3_fname[data-request-id="' + $(this).data('request-id') + '"]').append("<input type='text' value='" + child3_fname + "' data-request-id='" + $(this).data('request-id') + "' data-name='child3_fname' />");

				$('span.child3_sname[data-request-id="' + $(this).data('request-id') + '"]').html('');
				$('span.child3_sname[data-request-id="' + $(this).data('request-id') + '"]').append("<input type='text' value='" + child3_sname + "' data-request-id='" + $(this).data('request-id') + "' data-name='child3_sname' />");

				$('span.child3_tg[data-request-id="' + $(this).data('request-id') + '"]').html('');
				$('span.child3_tg[data-request-id="' + $(this).data('request-id') + '"]').append("<input type='text' value='" + child3_tg + "' data-request-id='" + $(this).data('request-id') + "' data-name='child3_tg' />");


				$('span.childnames-end[data-request-id="' + $(this).data('request-id') + '"]').append("<input type='button' class='button action adjust-names' data-request-id='" + $(this).data('request-id') + "' value='Adjust Names'>");


				$('input.adjust-names').click(function(e) {
						e.preventDefault();

						var request_id = $(this).data('request-id');
						
						$.post(ajaxurl, {
							security:    '<?php echo wp_create_nonce( sha1( 'tvs-pmp-moodle-provisioning-ajax-request' ) ); ?>',
							action:      'tvs_pmp_adjust_childnames',
							id:          request_id,
							child_fname: $('input[data-name="child_fname"][data-request-id="' + request_id + '"]').val(),
							child_sname: $('input[data-name="child_sname"][data-request-id="' + request_id + '"]').val(),
							child_tg: $('input[data-name="child_tg"][data-request-id="' + request_id + '"]').val(),
							child2_fname: $('input[data-name="child2_fname"][data-request-id="' + request_id + '"]').val(),
							child2_sname: $('input[data-name="child2_sname"][data-request-id="' + request_id + '"]').val(),
							child2_tg: $('input[data-name="child2_tg"][data-request-id="' + request_id + '"]').val(),
							child3_fname: $('input[data-name="child3_fname"][data-request-id="' + request_id + '"]').val(),
							child3_sname: $('input[data-name="child3_sname"][data-request-id="' + request_id + '"]').val(),
							child3_tg: $('input[data-name="child3_tg"][data-request-id="' + request_id + '"]').val(),
						}, function(response) {
							if ( response.success == 1 ) {
								$('input[data-name="child_fname"][data-request-id="' + response.id + '"]').remove();
								$('input[data-name="child_sname"][data-request-id="' + response.id + '"]').remove();
								$('input[data-name="child_tg"][data-request-id="' + response.id + '"]').remove();
								$('input[data-name="child2_fname"][data-request-id="' + response.id + '"]').remove();
								$('input[data-name="child2_sname"][data-request-id="' + response.id + '"]').remove();
								$('input[data-name="child2_tg"][data-request-id="' + response.id + '"]').remove();
								$('input[data-name="child3_fname"][data-request-id="' + response.id + '"]').remove();
								$('input[data-name="child3_sname"][data-request-id="' + response.id + '"]').remove();
								$('input[data-name="child3_tg"][data-request-id="' + response.id + '"]').remove();
								$('input.adjust-names[data-request-id="' + response.id + '"]').remove();

								// put spans back
								$('span.child_fname[data-request-id="' + response.id + '"]').html(response.child_fname);
								$('span.child_sname[data-request-id="' + response.id + '"]').html(response.child_sname);
								$('span.child_tg[data-request-id="' + response.id + '"]').html(response.child_tg);
								$('span.child2_fname[data-request-id="' + response.id + '"]').html(response.child2_fname);
								$('span.child2_sname[data-request-id="' + response.id + '"]').html(response.child2_sname);
								$('span.child2_tg[data-request-id="' + response.id + '"]').html(response.child2_tg);
								$('span.child3_fname[data-request-id="' + response.id + '"]').html(response.child3_fname);
								$('span.child3_sname[data-request-id="' + response.id + '"]').html(response.child3_sname);
								$('span.child3_tg[data-request-id="' + response.id + '"]').html(response.child3_tg);


							}
						}).fail(function() { 
							$('#tvs-pmp-error').html('<p>Unable to make the request. Please use Developer Tools to investigate.</p>');
							$('#tvs-pmp-error').show('slow');
							$('#tvs-pmp-error').delay(3000).fadeOut(1000);
						});		


					});
				});	
		

			});
	</script>
		<?php
		}
	}

	/**
	 * JavaScript handler for reject request
	 */
	public function reject_js() {
		$this->generic_action_js( 'reject' );
	}

	/**
	 * JavaScript handler for duplicate request
	 */
	public function duplicate_js() {
		$this->generic_action_js( 'duplicate' );
	}

	/**
	 * JavaScript handler for bogus request
	 */
	public function bogus_js() {
		$this->generic_action_js( 'bogus' );
	}

	/**
	 * JavaScript handler for pending request
	 */
	public function pending_js() {
		$this->generic_action_js( 'pending' );
	}

	/**
	 * Action handler for uploading a batch of users at once. Expects data to be in $_POST['data'] as a multi-dimensional
	 * array of cells. The first column is column headers.
	 */
	public function upload_users() {

		global $wpdb;
		$before_tz = @date_default_timezone_get();
		date_default_timezone_set( get_option( 'timezone_string' ) );

		check_ajax_referer( sha1( 'tvs_moodle_parent_provisioning_upload_users' ), '_ajax_nonce', true );

		if ( ! current_user_can( TVS_PMP_REQUIRED_CAPABILITY ) ) {
			header( 'HTTP/1.0 403 Forbidden' );
			wp_send_json_error( __( 'You do not have permission to perform this action.', 'tvs-moodle-parent-provisioning' ) );
			wp_die();
		}

		if ( ! array_key_exists( 'data', $_POST ) ) {
			wp_send_json_error( __( 'The \'data\' key was not supplied in the POST request.', 'tvs-moodle-parent-provisioning' ) );
			wp_die();
		}

		if ( ! is_array( $_POST['data'] ) ) {
			wp_send_json_error( __( 'The \'data\' key in the POST request is not an array.', 'tvs-moodle-parent-provisioning' ) );
		}

		$data = $_POST['data'];

		$errors = array();

		// generate an ID we can use to refer to the batch later
		if ( function_exists( 'openssl_random_pseudo_bytes' ) ) {
			$batch_id = bin2hex( openssl_random_pseudo_bytes( 8 ) ) . '-' . bin2hex( openssl_random_pseudo_bytes( 8 ) );
		}
		else {
			$batch_id = uniqid( '', true );
		}
		
		// build a column map from the headings in the first column
		$column_map = array();

		foreach( $data[0] as $col_no => $col_heading ) {
			switch( $col_heading ) {
				case 'Parent Title':
					$column_map[ $col_no ] = 'parent_title';
				break;
				case 'Parent First Name':
					$column_map[ $col_no ] = 'parent_fname';
				break;
				case 'Parent Surname':
					$column_map[ $col_no ] = 'parent_sname';
				break;
				case 'Parent Email Address':
					$column_map[ $col_no ] = 'parent_email';
				break;
				case 'Child 1 First Name':
					$column_map[ $col_no ] = 'child_fname';
				break;
				case 'Child 1 Surname':
					$column_map[ $col_no ] = 'child_sname';
				break;
				case 'Child 1 Tutor Group':
					$column_map[ $col_no ] = 'child_tg';
				break;
				case 'Child 2 First Name':
					$column_map[ $col_no ] = 'child2_fname';
				break;
				case 'Child 2 Surname':
					$column_map[ $col_no ] = 'child2_sname';
				break;
				case 'Child 2 Tutor Group':
					$column_map[ $col_no ] = 'child2_tg';
				break;
				case 'Child 3 First Name':
					$column_map[ $col_no ] = 'child3_fname';
				break;
				case 'Child 3 Surname':
					$column_map[ $col_no ] = 'child3_sname';
				break;
				case 'Child 3 Tutor Group':
					$column_map[ $col_no ] = 'child3_tg';
				break;

			}
		}

		$column_count = count( $column_map );

		$warnings = array(); // hold warnings for the processing of each row, if applicable
		$errors = array();
		$information = array();

		$information[] = sprintf( __( 'Batch ID: %s Started at %s.', 'tvs-moodle-parent-provisioning' ), $batch_id, date( 'd M Y H:i:s T' ) );

		// for each row below the column headers, match the columns and add a row to the DB table
		foreach( $data as $i => $item ) {
			if ( 0 == $i ) {
				continue;
			}

			if ( is_array( $item ) && count( $item ) == $column_count ) {
				// a new object to hold properties for each of the cells in this row
				$new_parent_request = new TVS_PMP_Request();

				foreach( $item as $col_no => $column ) {
					$new_parent_request->{ $column_map[ $col_no ] } = stripslashes( $column );
				}

				// now we have an object which should have all mandatory properties from the column list, we can try to add it
				$new_parent_request->request_type = 'Uploaded in a batch via \'Upload Users\'';	
				$new_parent_request->parent_comment = sprintf( __( 'Uploaded in a batch at %s. Batch ID %s', 'tvs-moodle-parent-provisioning' ), date( 'd M Y H:i:s T' ), $batch_id );
				$new_parent_request->system_comment = sprintf( __( 'Uploaded in a batch at %s. Batch ID %s', 'tvs-moodle-parent-provisioning' ), date( 'd M Y H:i:s T' ), $batch_id );

				// skip if missing a mandatory property or a mandatory property's content is empty
				if ( 	empty( $new_parent_request->parent_title ) ||
					empty( $new_parent_request->parent_fname ) ||
					empty( $new_parent_request->parent_sname ) ||
					empty( $new_parent_request->parent_email ) ||
					empty( $new_parent_request->child_fname ) ||
					empty( $new_parent_request->child_sname ) ||
					empty( $new_parent_request->child_tg )
				) {
					continue;
				}

				// create the request -- will have 'pending' status
				$new_parent_request->save();

				if ( ! $new_parent_request->id ) {
					$errors[] = sprintf( __( 'Did not succeed at saving the new parent request with the \'pending\' state for %s %s %s.', 'tvs-moodle-parent-provisioning' ), $new_parent_request->parent_title, $new_parent_request->parent_fname, $new_parent_request->parent_sname );
					continue;
				}
					
				$information[] = sprintf( __( 'Created new request with \'pending\' status for %s %s %s -- request id %d', 'tvs-moodle-parent-provisioning' ), $new_parent_request->parent_title, $new_parent_request->parent_fname, $new_parent_request->parent_sname, $new_parent_request->id );

				// now set the status to approved
				try {
					$new_parent_request->approve_for_provisioning();
				}
				catch ( TVS_PMP_Parent_Account_Duplicate_Exception $e ) {
					$warnings[] = sprintf( __( 'Will not provision %s %s %s as there is an account existing for this email address: %s. Exception details: %s', 'tvs-moodle-parent-provisioning' ), $new_parent_request->parent_title, $new_parent_request->parent_fname, $new_parent_request->parent_sname, $new_parent_request->parent_email, $e->getMessage() );
					$information[] =  sprintf( __( 'Request changed to \'duplicate\' status for %s %s %s -- request id %d', 'tvs-moodle-parent-provisioning' ), $new_parent_request->parent_title, $new_parent_request->parent_fname, $new_parent_request->parent_sname, $new_parent_request->id );

					continue;
				}

				$information[] = sprintf( __( 'Set status to \'approved\' for %s %s %s -- request id %d (pupil #1 %s %s [%s])', 'tvs-moodle-parent-provisioning' ), $new_parent_request->parent_title, $new_parent_request->parent_fname, $new_parent_request->parent_sname, $new_parent_request->id, $new_parent_request->child_fname, $new_parent_request->child_sname, $new_parent_request->child_tg );

			}
			else {
				$errors[] = sprintf( __ ('Unable to provision parent on row %d (starting counting at 1), as their row did not have the correct number of columns.', 'tvs-moodle-parent-provisioning' ), ($i+1) );
			}
		}

		// completed processing -- dump the logs of errors, warnings and information

		update_option( '_transient_tvs_pmp_batch_log_errors_' . gmdate( 'Y-m-d_H-i-s' ) . '_' . $batch_id, $errors, false );
		update_option( '_transient_tvs_pmp_batch_log_warnings_' . gmdate( 'Y-m-d_H-i-s' ) . '_' . $batch_id, $warnings, false );
		update_option( '_transient_tvs_pmp_batch_log_information_' . gmdate( 'Y-m-d_H-i-s' ) . '_' . $batch_id, $information, false );

		// return the errors, warnings and information
		wp_send_json( array( 'errors' => $errors, 'warnings' => $warnings, 'information' => $information ) );

		wp_die();

	}


	/**
	 * Delete the logs from a specific batch of uploaded users.
	 */
	public function delete_batch_logs() {
		require_once( dirname( __FILE__ ) . '/class.tvs-pmp-batch.php' );
		
		check_ajax_referer( 'tvs-moodle-parent-provisioning-delete-batch-log', '_ajax_nonce', true );

		if ( ! current_user_can( TVS_PMP_REQUIRED_CAPABILITY ) ) {
			header( 'HTTP/1.0 403 Forbidden' );
			wp_send_json_error( __( 'You do not have permission to perform this action.', 'tvs-moodle-parent-provisioning' ) );
			wp_die();
		}

		if ( ! array_key_exists( 'batch', $_POST ) || empty( $_POST['batch'] ) ) {
			header( 'HTTP/1.0 400 Bad Request' );
			wp_send_json_error( __( 'You must send the batch ID for which you want to delete logs.', 'tvs-moodle-parent-provisioning' ) );
			wp_die();
		}

		$batch = new TVS_PMP_Batch();
		$batch->id = $_POST['batch'];
		$batch->delete_logs();

		wp_send_json_success();
		wp_die();

	}

	/**
	 * Delete the logs from ALL batches of uploaded users.
	 */
	public function delete_all_batch_logs() {
		require_once( dirname( __FILE__ ) . '/class.tvs-pmp-batch.php' );

		check_ajax_referer( 'tvs-moodle-parent-provisioning-delete-batch-log', '_ajax_nonce', true );

		if ( ! current_user_can( TVS_PMP_REQUIRED_CAPABILITY ) ) {
			header( 'HTTP/1.0 403 Forbidden' );
			wp_send_json_error( __( 'You do not have permission to perform this action.', 'tvs-moodle-parent-provisioning' ) );
			wp_die();
		}

		$batch = new TVS_PMP_Batch();
		$batch->delete_all_logs();

		wp_send_json_success();
		wp_die();

	}

	/**
	 * Delete an entry from the auth table to clean up an orphaned account.
	 */
	public function delete_auth_entry() {

		check_ajax_referer( 'tvs-moodle-parent-provisioning-delete-auth-entry', '_ajax_nonce', true );

		if ( ! current_user_can( TVS_PMP_REQUIRED_CAPABILITY ) ) {
			header( 'HTTP/1.0 403 Forbidden' );
			wp_send_json_error( __( 'You do not have permission to perform this action.', 'tvs-moodle-parent-provisioning' ) );
			wp_die();
		}

		if ( ! array_key_exists( 'request_id', $_POST ) || empty( $_POST['request_id'] ) ) {
			header( 'HTTP/1.0 400 Bad Request' );
			wp_send_json_error( __( 'You must send the auth table entry request ID which you want to delete.', 'tvs-moodle-parent-provisioning' ) );
			wp_die();
		}

		wp_send_json( array(
			'affected'   => $this->deprovision( $_POST['request_id'] ),
			'request_id' => $_POST['request_id']
		) );

	}


	/**
	 * Print the client-side code for deleting an entry in the auth table
	 */
	public function delete_auth_entry_js() {
		if ( array_key_exists( 'page', $_GET ) && 'tvs_parent_moodle_provisioning_auth_table' == $_GET['page'] ) { 
			?>
			<script type="text/javascript">
		jQuery(document).ready(function($) {
			$('.delete a.delete-orphaned').click( function(e) {
				e.preventDefault();

				if (!confirm(  '<?php _e( 'Are you sure you want to delete this entry in the table?', 'tvs-moodle-parent-provisioning' ); ?>') ) {
					return;
				}

				$.post(ajaxurl, {
					_ajax_nonce:  '<?php echo wp_create_nonce( 'tvs-moodle-parent-provisioning-delete-auth-entry' ); ?>',
					action:    'tvs_pmp_delete_auth_entry',
					request_id:        $(this).data('request-id'),
				}, function(response, textStatus, jqXHR) {
					$('.delete a[data-request-id=' + response.request_id + ']').parent().parent().parent().parent().hide('slow');
				});		
			});
			$('.delete a.delete-current-moodle-user').click( function(e) {
				e.preventDefault();

				alert('<?php _e( 'Please first go to Moodle and delete the Moodle user connected to this auth table entry.', 'tvs-moodle-parent-provisioning' ); ?>');
	
			});
		});
			</script>
			<?php
		}
	}


};

$tvs_pmp_ajax_actions = new TVS_PMP_Ajax_Actions();

add_action( 'wp_ajax_tvs_pmp_provision', array( $tvs_pmp_ajax_actions, 'provision' ) );
add_action( 'wp_ajax_tvs_pmp_reject', array( $tvs_pmp_ajax_actions, 'reject' ) );
add_action( 'wp_ajax_tvs_pmp_duplicate', array( $tvs_pmp_ajax_actions, 'duplicate' ) );
add_action( 'wp_ajax_tvs_pmp_bogus', array( $tvs_pmp_ajax_actions, 'bogus' ) );
add_action( 'wp_ajax_tvs_pmp_pending', array( $tvs_pmp_ajax_actions, 'pending' ) );

add_action( 'wp_ajax_tvs_pmp_update_staff_comment', array( $tvs_pmp_ajax_actions, 'update_staff_comment' ) );
add_action( 'wp_ajax_tvs_pmp_adjust_childnames', array( $tvs_pmp_ajax_actions, 'adjust_childnames' ) );
add_action( 'wp_ajax_tvs_pmp_send_forgotten_password_email', array( $tvs_pmp_ajax_actions, 'send_forgotten_password_email' ) );
add_action( 'wp_ajax_tvs_pmp_send_details_not_on_file_email', array( $tvs_pmp_ajax_actions, 'send_details_not_on_file_email' ) );
add_action( 'wp_ajax_tvs_pmp_send_generic_fixed_email', array( $tvs_pmp_ajax_actions, 'send_generic_fixed_email' ) );
add_action( 'wp_ajax_tvs_pmp_upload_users', array( $tvs_pmp_ajax_actions, 'upload_users' ) );
add_action( 'wp_ajax_tvs_pmp_delete_batch_log', array( $tvs_pmp_ajax_actions, 'delete_batch_logs' ) );
add_action( 'wp_ajax_tvs_pmp_delete_all_batch_logs', array( $tvs_pmp_ajax_actions, 'delete_all_batch_logs' ) );

add_action( 'wp_ajax_tvs_pmp_delete_auth_entry', array( $tvs_pmp_ajax_actions, 'delete_auth_entry' ) );

add_action( 'admin_footer', array( $tvs_pmp_ajax_actions, 'provision_js' ) );
add_action( 'admin_footer', array( $tvs_pmp_ajax_actions, 'reject_js' ) );
add_action( 'admin_footer', array( $tvs_pmp_ajax_actions, 'duplicate_js' ) );
add_action( 'admin_footer', array( $tvs_pmp_ajax_actions, 'bogus_js' ) );
add_action( 'admin_footer', array( $tvs_pmp_ajax_actions, 'pending_js' ) );
add_action( 'admin_footer', array( $tvs_pmp_ajax_actions, 'update_staff_comment_js' ) );
add_action( 'admin_footer', array( $tvs_pmp_ajax_actions, 'adjust_childnames_js' ) );
add_action( 'admin_footer', array( $tvs_pmp_ajax_actions, 'send_forgotten_password_email_js' ) );
add_action( 'admin_footer', array( $tvs_pmp_ajax_actions, 'send_details_not_on_file_email_js' ) );
add_action( 'admin_footer', array( $tvs_pmp_ajax_actions, 'send_generic_fixed_email_js' ) );
add_action( 'admin_footer', array( $tvs_pmp_ajax_actions, 'delete_auth_entry_js' ) );

?>
