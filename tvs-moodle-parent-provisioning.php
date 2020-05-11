<?php
/*
Plugin Name: Test Valley School Parent Moodle Provisioning
Plugin URI: https://www.testvalley.hants.sch.uk/
Description: This plugin allows captured form submissions for Moodle Parent Accounts to be stored, validated by a staff member and provisioned within Moodle.
Version: 1.0
Author: Mr P Upfold
License: GPLv2
License URI: https://www.gnu.org/licenses/gpl-2.0.html
*/
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

if ( ! defined( 'ABSPATH' ) || ! function_exists( 'add_action' ) ) {
	header('HTTP/1.1 403 Forbidden');
	die();
}

define( 'TVS_PMP_DBVERSION', '1.5' );
define( 'TVS_PMP_REQUIRED_CAPABILITY', 'manage_parent_moodle_account_requests' );

// autoload our vendor classes
// NOTE: If something is not working here, make sure you run 'composer install' to install the packages. Composer is available at https://getcomposer.org/doc/00-intro.md
require_once( dirname( __FILE__ ) . '/vendor/autoload.php' );

/**
 * The main class for this plugin.
 */
class TVS_Parent_Moodle_Provisioning {

	/**
	 * Hook into WordPress actions, other general initialisation tasks
	 */
	public function __construct() {
		add_action( 'wpcf7_before_send_mail', array( $this, 'store_form_submission' ) );
		register_activation_hook( __FILE__, array( $this, 'create_tables' ) );
		add_action( 'admin_menu', array( $this, 'add_menus' ) ) ;
		add_action( 'admin_head', array( $this, 'print_table_css' ) );
		add_action( 'admin_head', array( $this, 'print_auth_table_css' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_scripts' ) );
		add_action( 'wp_ajax_tvs_moodle_parent_provisioning_upload_users', array( $this, 'handle_upload_users' ) );
		
		require_once( dirname( __FILE__ ) . '/includes/class.tvs-pmp-actions-rest-controller.php' );
		$rest_controller = new TVS_PMP_Actions_REST_Controller();
		add_action( 'rest_api_init', array( $rest_controller, 'register_routes' ) );

		if ( ! get_option( 'tvs_parent_moodle_provisioning_dbversion' ) !== TVS_PMP_DBVERSION ) {
			$this->create_tables();
		}

	}

	/**
	 * Create the database tables for our storage of requests.
	 */
	public function create_tables() {

		global $wpdb;

		$charset_collate = $wpdb->get_charset_collate();
		$table_name = $wpdb->prefix . 'tvs_parent_moodle_provisioning';

		$sql = "CREATE TABLE $table_name (
			id bigint(20) NOT NULL AUTO_INCREMENT  PRIMARY KEY,
			parent_title varchar(12) NOT NULL,
			parent_fname varchar(255) NOT NULL,
			parent_sname varchar(255) NOT NULL,
			child_fname varchar(255) NOT NULL,
			child_sname varchar(255) NOT NULL,
			child_tg varchar(16) NOT NULL,
			parent_email varchar(255) NOT NULL,
			child2_fname varchar(255),
			child2_sname varchar(255) NOT NULL,
			child2_tg varchar(16) NOT NULL,
			child3_fname varchar(255),
			child3_sname varchar(255) NOT NULL,
			child3_tg varchar(16) NOT NULL,
			status varchar(16) NOT NULL,
			parent_comment mediumtext,
			staff_comment mediumtext,
			system_comment mediumtext,
			date_created datetime DEFAULT '0000-00-00 00:00:00' NOT NULL,
			date_updated datetime,
			date_approved datetime,
			remote_ip_addr varchar(255) NOT NULL,
			provisioned_username varchar(255),
			provisioned_initialpass varchar(255),
			request_type varchar(255),
			mis_id bigint(20) NULL,
			external_mis_id varchar(128) DEFAULT NULL
		) $charset_collate;";

		require_once ( ABSPATH . 'wp-admin/includes/upgrade.php' );
		dbDelta( $sql );

		$table_name = $wpdb->prefix . 'tvs_parent_moodle_provisioning_auth';

		$sql = "CREATE TABLE $table_name (
			id bigint(20) NOT NULL AUTO_INCREMENT  PRIMARY KEY,
			username varchar(255) NOT NULL,
			parent_title varchar(12) NOT NULL,
			parent_fname varchar(255) NOT NULL,
			parent_sname varchar(255) NOT NULL,
			parent_email varchar(255) NOT NULL,
			description varchar(255) NOT NULL,
			request_id bigint(20) NOT NULL
		) $charset_collate;";

		dbDelta( $sql );

		// ensure the custom capability is set on the Administrator role
		$role = get_role( 'administrator' );
		$role->add_cap( TVS_PMP_REQUIRED_CAPABILITY );

		update_option( 'tvs_parent_moodle_provisioning_dbversion', TVS_PMP_DBVERSION );
	
	}

	/**
	 * Hooking admin_menu, this adds an option to the WP-Admin interface for PMP.
	 */
	public function add_menus() {

		// get the number of requests in pending status
		$pending_count = get_transient( 'tvs-moodle-parent-provisioning-pending-requests' );
		if ( $pending_count === false ) {
			// determine number of requests
			require_once( dirname( __FILE__ ) . '/includes/class.tvs-pmp-request.php' );
			TVS_PMP_Request::update_pending_count();
			$pending_count = intval( get_transient( 'tvs-moodle-parent-provisioning-pending-requests' ) );
		}
		else {
			$pending_count = intval( $pending_count );
		}

		add_menu_page(
			__( 'Parent Moodle Provisioning', 'tvs-moodle-parent-provisioning' ),
			__( 'Moodle Provisioning', 'tvs-moodle-parent-provisioning' ) . '<span class="awaiting-mod count-' . $pending_count . '" style="padding:4px;">' . $pending_count . '</span>',
			TVS_PMP_REQUIRED_CAPABILITY,
			'tvs_parent_moodle_provisioning',
			array( $this, 'print_admin_page_main' ),
			'dashicons-admin-users'					
		);

		add_submenu_page(
			'tvs_parent_moodle_provisioning',
			__( 'Moodle Provisioning', 'tvs-moodle-parent-provisioning' ),
			__( 'Requests', 'tvs-moodle-parent-provisioning' ),
			TVS_PMP_REQUIRED_CAPABILITY,
			'tvs_parent_moodle_provisioning',
			array( $this, 'print_admin_page_main' )
		);


		add_submenu_page(
			'tvs_parent_moodle_provisioning',
			__( 'Moodle Provisioning &mdash; Authorised Users', 'tvs-moodle-parent-provisioning' ),
			__( 'Authorised Users', 'tvs-moodle-parent-provisioning' ),
			TVS_PMP_REQUIRED_CAPABILITY,
			'tvs_parent_moodle_provisioning_auth_table',
			array( $this, 'print_admin_page_auth_table' )
		);

		add_submenu_page(
			'tvs_parent_moodle_provisioning',
			__( 'Moodle Provisioning &mdash; Upload Users', 'tvs-moodle-parent-provisioning' ),
			__( 'Upload Users', 'tvs-moodle-parent-provisioning' ),
			TVS_PMP_REQUIRED_CAPABILITY,
			'tvs_parent_moodle_provisioning_upload_users',
			array( $this, 'print_admin_page_upload_users' )
		);
		
		add_submenu_page(
			'tvs_parent_moodle_provisioning',
			__( 'Moodle Provisioning Settings', 'tvs-moodle-parent-provisioning' ),
			__( 'Settings', 'tvs-moodle-parent-provisioning' ),
			TVS_PMP_REQUIRED_CAPABILITY,
			'tvs_parent_moodle_provisioning_settings',
			array( $this, 'print_admin_page_settings' )
		);

		add_submenu_page(
			'tvs_parent_moodle_provisioning',
			__( 'Moodle Provisioning &mdash; Batch Logs', 'tvs-moodle-parent-provisioning' ),
			__( 'Batch Logs', 'tvs-moodle-parent-provisioning' ),
			TVS_PMP_REQUIRED_CAPABILITY,
			'tvs_parent_moodle_provisioning_batch_logs',
			array( $this, 'print_admin_page_batch_logs' )
		);
	}

	/**
	 * Print the CSS for the auth table.
	 */
	public function print_auth_table_css() {
		if ( array_key_exists( 'page', $_GET ) && 'tvs_parent_moodle_provisioning_auth_table' == $_GET['page'] ) {
	?>

		<style type="text/css">
		.wp-list-table .column-parent_title { width: 5% }
		.wp-list-table .column-parent_fname, .wp-list-table .column-parent_sname { width: 10% }
		.wp-list-table .column-status { width: 5% }
		.wp-list-table .column-child_fname { width: 20%; }
		.wp-list-table .column-request_id { width: 5%; }
		.wp-list-table .column-description { width: 45%; }
		</style>
	<?php
		}
	}

	/**
	 * Print the CSS for the table.
	 */
	public function print_table_css() {
		if ( array_key_exists( 'page', $_GET ) && 'tvs_parent_moodle_provisioning' == $_GET['page'] ) {
		?>
		<style type="text/css">
		.wp-list-table .column-parent_title { width: 5% }
		.wp-list-table .column-parent_fname, .wp-list-table .column-parent_sname { width: 10% }
		.wp-list-table .column-status { width: 5% }
		.wp-list-table .column-child_fname { width: 20%; }
		.wp-list-table .column-date_created { width: 20%; }
		.wp-list-table .column-parent_comment { width: 35%; }

		/* classes for statuses */
		p.request-type, p.new-account, p.login-issue, p.missing-pupils, p.unspecified {
			padding: 20px;
			font-size: 113% !important;
		}
		p.login-issue {
			background-color: #f8e4be;
		}
		p.missing-pupils {
			background-color: #eef8be;
		}
		p.unspecified {
			background-color: #fbe1e8;
		}
		p.batch {
			background-color: #d8dbec;
		}
		p.new-account {
			background-color: #ceecce;
		}
		
		</style>
		<?php
		}
	}


	/**
	 * Prints to output the main admin page interface for the TVS PMP system.
	 */
	public function print_admin_page_main() {
		if ( ! current_user_can( TVS_PMP_REQUIRED_CAPABILITY ) ) {
			echo 'You are not permitted to access this page.';
		}
		else {

			if ( array_key_exists( 'force-provisioning-cycle', $_POST ) ) {
				// possibly set the option to force a provisioning cycle
				if ( wp_verify_nonce( $_POST['force-provisioning-cycle'], 'force-provisioning-cycle' ) ) {
					update_option( 'tvs-moodle-parent-provisioning-force-provisioning-cycle', true );
					$force_pmp_cycle = __( 'The provisioning cycle will run within the next 2 minutes. If it does not, verify that the cron job is configured correctly on the server.', 'tvs-moodle-parent-provisioning' );

				}
			}

			require( dirname( __FILE__ ) . '/admin/main.php' );
		}
	}

	/**
	 * Prints to output the settings admin page interface for the TVS PMP system.
	 */
	public function print_admin_page_settings() {
		if ( ! current_user_can( TVS_PMP_REQUIRED_CAPABILITY ) ) {
			echo 'You are not permitted to access this page.';
		}
		else {
			// process settings form submission

			if ( array_key_exists( '_wpnonce', $_POST ) && wp_verify_nonce( $_POST['_wpnonce'], 'tvs-moodle-parent-provisioning-settings' ) ) {
			
				 $result =  $this->process_email_template_forms( 'forgotten-password' );
				 if ( $result ) {
					$success = $result;
				 }

			 	 $result =  $this->process_email_template_forms( 'details-not-on-file' );
				 if ( $result ) {
					$success = $result;
				 }

			 	 $result =  $this->process_email_template_forms( 'generic-fixed' );
				 if ( $result ) {
					$success = $result;
				 }

				// update the following simple scalar wp_options from the form
				 $settings_to_update = array(
					'moodle-url',
					'moodle-path',
					'moodle-dbuser',
					'moodle-dbpass',
					'moodle-db',
					'moodle-dbhost',
					'moodle-dbprefix',
					'moodle-sudo-account',
					'php-path',
					'moodle-modifier-id',
					'contexts-to-add-role',
					'contexts-notes',
					'match-by-fields',
					'moodle-parent-role',
					'smtp-server',
					'smtp-username',
					'log-file-path',
					'smtp-password',
					'provisioning-email-recipients',
					'log-level',
				 );

				 foreach( $settings_to_update as $setting ) {
				 	$this->update_settings_option( $setting, $success );
				 }
			}

			require( dirname( __FILE__ ) . '/admin/settings.php' );
		}
	}

	/**
	 * Prints to output the admin page for uploading users from a spreadsheet for the TVS PMP system.
	 */
	public function print_admin_page_upload_users() {
		if ( ! current_user_can( TVS_PMP_REQUIRED_CAPABILITY ) ) {
			echo 'You are not permitted to access this page.';
		}
		else {

			

			require( dirname( __FILE__ ) . '/admin/upload-users.php' );
		}
	}

	/**
	 * Prints to output the admin page for showing the currently authorised users in the auth table.
	 */
	public function print_admin_page_auth_table() {
		if ( ! current_user_can( TVS_PMP_REQUIRED_CAPABILITY ) ) {
			echo 'You are not permitted to access this page.';
		}
		else {
	
			// handle bulk actions
			if ( array_key_exists( 'action', $_POST ) ) {
				if ( ! array_key_exists( '_wpnonce', $_REQUEST ) || ! wp_verify_nonce( $_REQUEST['_wpnonce'], 'bulk-' . __( 'entries', 'tvs-moodle-parent-provisioning' ) ) ) {
					wp_die( __( 'The verification had expired on the action you tried to attempt.', 'tvs-moodle-parent-provisioning' ) );
					return;
				}

				
				require_once( dirname( __FILE__ ) . '/includes/class.tvs-pmp-mdl-user.php' );

				TVS_PMP_mdl_user::delete_bulk();

			}
			

			require( dirname( __FILE__ ) . '/admin/auth-table.php' );
		}
	}

	/**
	 * Prints to output the admin page for showing batch logs.
	 */
	public function print_admin_page_batch_logs() {
		if ( ! current_user_can( TVS_PMP_REQUIRED_CAPABILITY ) ) {
			echo 'You are not permitted to access this page.';
		}
		else {
			require( dirname( __FILE__ ) . '/admin/batch-logs.php' );
		}
	}


	/**
	 * Handle any changes made to the email template specified by $internal_name
	 */
	protected function process_email_template_forms( $internal_name ) {

    		 $success = null;

		 if ( array_key_exists( $internal_name . '-email-template', $_POST ) ) {
			update_option( 'tvs-moodle-parent-provisioning-' . $internal_name . '-email', $_POST[$internal_name . '-email-template'] );
			$success = __( 'Settings saved.', 'tvs-moodle-parent-provisioning' );
		 }
		 if ( array_key_exists( $internal_name . '-email-subject', $_POST ) ) {
			update_option( 'tvs-moodle-parent-provisioning-' . $internal_name . '-email-subject', $_POST[$internal_name . '-email-subject'] );
			$success = __( 'Settings saved.', 'tvs-moodle-parent-provisioning' );
		 }

		 return $success;
	}

	/**
	 * Hooking wpcf7_before_send_mail, this function determines if this form submission
	 * is that of a Moodle Parent Account form, and, if so, takes the submitted data and 
	 * adds it to the database table.
	 *
	 * DEPRECATED: there is now separation between the form submission on the public website, and this plugin, whihc
	 * is only on "The Hub", an installation of WordPress specifically for 'middleware' purposes (REST API).
	 */
	public function store_form_submission( $data ) {

		global $wpdb;

		$table_name = $wpdb->prefix . 'tvs_parent_moodle_provisioning';
		
                $sub = WPCF7_Submission::get_instance();
                $data = $sub->get_posted_data();

		if ( array_key_exists( 'parent_moodle_provisioning', $data ) ){
			// process this form, as it is a PMP form

			$wpdb->insert(
				$table_name,
				array(
					'parent_title'    => stripslashes( trim( $data['parent_title'] ) ),
					'parent_fname'    => stripslashes( trim( $data['parent_fname'] ) ),
					'parent_sname'    => stripslashes( trim( $data['parent_sname'] ) ),
					'child_fname'     => stripslashes( trim( $data['child_fname'] ) ),
					'child_sname'     => stripslashes( trim( $data['child_sname'] ) ),
					'child_tg'        => stripslashes( substr( trim( $data['child_tg'] ), 0, 15 ) ),
					'parent_email'    => stripslashes( strtolower( trim( $data['parent_email'] ) ) ),
					'child2_fname'    => stripslashes( trim( $data['child2_fname'] ) ),
					'child2_sname'    => stripslashes( trim( $data['child2_sname'] ) ),
					'child2_tg'       => stripslashes( substr( trim( $data['child2_tg'] ), 0, 15 ) ),
					'child3_fname'    => stripslashes( trim( $data['child3_fname'] ) ),
					'child3_sname'    => stripslashes( trim( $data['child3_sname'] ) ),
					'child3_tg'       => stripslashes( substr( trim( $data['child3_tg'] ), 0, 15 ) ),
					'parent_comment'  => stripslashes( $data['parent_comment'] ),
					'date_created'    => gmdate( 'Y-m-d H:i:s' ),
					'status'          => 'pending',
					'remote_ip_addr'  => stripslashes( $_SERVER['REMOTE_ADDR'] ),
					'request_type'    => stripslashes( trim( $data['request-type'] ) ),
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

		}
	}


	/** 
	 * Register and enqueue any scripts and styles that we may need for this plugin on the admin side.
	 */
	public function enqueue_admin_scripts( $hook ) { 
		if ( strpos( $hook, 'page_tvs_parent_moodle_provisioning_upload_users' ) === false ) {
			return;
		}

		$handsontable_base_src = plugins_url( '/node_modules/handsontable/dist/', __FILE__ );
		$handsontable_base_path = dirname( __FILE__ ) . '/node_modules/handsontable/dist/';

		$maybe_min = ( defined( 'WP_DEBUG' ) && WP_DEBUG ) ? '' : '.min';


		// enqueue javascript for spreadsheet paste control

		wp_register_script(
			'tvs-pmp-handsontable-moment',
			$handsontable_base_src . "moment/moment.js",
			array( 'jquery' ),
			hash( 'sha256', @filemtime( $handsontable_base_path . 'moment/moment.js' ) ),
			true
		);

		wp_enqueue_script( 'tvs-pmp-handsontable-moment' );

		wp_register_script(
			'tvs-pmp-handsontable-numbro',
			$handsontable_base_src . "numbro/numbro.js",
			array( 'jquery' ),
			hash( 'sha256', @filemtime( $handsontable_base_path . 'numbro/numbro.js' ) ),
			true
		);

		wp_register_script(
			'tvs-pmp-handsontable-pikaday',
			$handsontable_base_src . "pikaday/pikaday.js",
			array( 'jquery' ),
			hash( 'sha256', @filemtime( $handsontable_base_path . 'pikaday/pikaday.js' ) ),
			true
		);

		wp_enqueue_script( 'tvs-pmp-handsontable-pikaday' );

		wp_enqueue_script( 'tvs-pmp-handsontable-numbro' );

		wp_register_script(
			'tvs-pmp-handsontable-zeroclipboard',
			plugins_url( '/node_modules/zeroclipboard/dist/ZeroClipboard.js', __FILE__ ),
			array( 'jquery' ),
			hash( 'sha256', @filemtime( dirname( __FILE__ ) . "/node_modules/zeroclipboard/dist/ZeroClipboard.js" ) ),
			true
		);

		wp_enqueue_script( 'tvs-pmp-handsontable-zeroclipboard' );

		wp_register_script(
			'tvs-pmp-handsontable',
			$handsontable_base_src . "handsontable.full{$maybe_min}.js",
			array( 'jquery', 'tvs-pmp-handsontable-moment', 'tvs-pmp-handsontable-numbro', 'tvs-pmp-handsontable-pikaday', 'tvs-pmp-handsontable-zeroclipboard' ),
			hash( 'sha256', @filemtime( $handsontable_base_path . 'handsontable.full.min.js' ) ),
			true
		);

		wp_enqueue_script( 'tvs-pmp-handsontable' );

		wp_register_style(
			'tvs-pmp-handsontable',
			$handsontable_base_src . "handsontable.full{$maybe_min}.css",
			array(),
			hash( 'sha256', @filemtime( $handsontable_base_path . 'handsontable.full.min.css' ) ),
			'all'
		);

		wp_enqueue_style( 'tvs-pmp-handsontable' );

		wp_register_script(
			'tvs-pmp-upload-users',
			plugins_url( '/js/upload-users.js', __FILE__ ),
			array( 'jquery', 'tvs-pmp-handsontable' ),
			hash( 'sha256', @filemtime( dirname( __FILE__ ) . '/js/upload-users.js' ) ),
			true
		);

		wp_enqueue_script( 'tvs-pmp-upload-users' );

		wp_register_style(
			'tvs-pmp-upload-users',
			plugins_url( '/css/upload-users.css', __FILE__ ),
			array(),
			hash( 'sha256', @filemtime( dirname( __FILE__ ) . '/css/upload-users.css' ) ),
			'all'
		);

		wp_enqueue_style( 'tvs-pmp-upload-users' );

	}


	/**
	 * Used internally by settings.php to add additional fields to the settings page for email template types.
	 */
	public static function add_settings_for_email_template( $internal_name, $friendly_name ) {
		?>
		<tr>
			<th scope="row"><?php _e( $friendly_name . ' Email Subject', 'tvs-moodle-parent-provisioning' ); ?></th>
			<td>
				<input class="regular-text" name="<?php echo $internal_name; ?>-email-subject" id="<?php echo $internal_name; ?>-email-subject" type="text" value="<?php echo esc_attr( stripslashes( get_option( 'tvs-moodle-parent-provisioning-' . $internal_name . '-email-subject' ) ) ) ; ?>" />
			</td>
		</tr>
		<tr>
			<th scope="row"><?php _e( $friendly_name . ' Email Template', 'tvs-moodle-parent-provisioning' ); ?></th>
			<td>
				<textarea name="<?php echo $internal_name; ?>-email-template" id="<?php echo $internal_name; ?>-email-template" class="large-text" rows="7"><?php echo esc_html( stripslashes( get_option( 'tvs-moodle-parent-provisioning-' . $internal_name . '-email' ) ) ); ?></textarea>
				<p><em><?php _e( 'You can use the fields <code>{{parent_title}}</code>, <code>{{parent_fname}}</code>, <code>{{parent_sname}}</code> and <code>{{parent_email}}</code>.' , 'tvs-moodle-parent-provisioning' ); ?></em></p>
				
			</td>
		</tr>
		<?php
	}

	/**
	 * Used internally by the settings page handler to update a particular scalar wp_option.
	 */
	protected function update_settings_option( $name, &$success ) {

		if ( array_key_exists( $name, $_POST ) ) {

			if ( strpos( $name, 'pass' ) !== false ) {
				// don't update all the passwords to "dummypass"!
				if ( 'dummypass' == $_POST[ $name ] ) {
					return;
				}
			}

			if ( 'match-by-fields' == $name && ! $this->validate_match_by_setting( $_POST[ $name ] ) ) {
				$success = sprintf( __( 'Failed to update setting \'%s\', as \'%s\' is not a valid option.', 'tvs-moodle-parent-provisioning' ), $name, esc_html( $_POST[ $name ] ) );
				return;
			}

			update_option( 'tvs-moodle-parent-provisioning-' . $name, stripslashes( $_POST[ $name ] ) );
			$success = __( 'Settings saved.', 'tvs-moodle-parent-provisioning' );
		}
		
	}

	/**
	 * Return true if the input string is a valid choice for our 'match-by' setting.
	 *
	 * @return bool
	 */
	protected function validate_match_by_setting( $option ) {
		return ( 'firstname-surname-departmentnumber' == $option || 'firstname-surname-only' == $option );
	}

	/**
	 * If the specified option matches the value to seek, print the selected="selected" attribute
	 * to output to pre-select a given drop-down menu item.
	 *
	 * @param $option_name string The wp_option we are querying, without prefix.
	 * @param $value_to_seek string The value which, if set, should cause us to output a selected HTML attribute.
	 */
	public static function print_selected_attribute( $option_name, $value_to_seek ) {
		if ( get_option( 'tvs-moodle-parent-provisioning-' . $option_name ) == $value_to_seek ) {
			echo ' selected="selected"';
		}
	}

	/**
	 * Return whether or not all the required settings have been populated with values. Prevents database errors
	 * etc. when trying to render content when the plugin has not been fully configured.
	 *
	 * @return bool
	 */
	protected static function settings_are_populated() {
		// we only require some essential settings
		$settings_to_require = array(
			'moodle-url',
			'moodle-path',
			'moodle-dbuser',
			'moodle-dbpass',
			'moodle-db',
			'moodle-dbhost',
			'moodle-dbprefix',
			'moodle-sudo-account',
			'moodle-modifier-id',
			'log-file-path',
			'log-level',
	 	);

		foreach( $settings_to_require as $setting ) {
			if ( !get_option( 'tvs-moodle-parent-provisioning-' . $setting ) ) {
				return false;
			}
		}
		
		return true;
	
	}

};

require_once( dirname( __FILE__ ) . '/includes/tvs-pmp-ajax-actions.php' );

$tvs_pmp = new TVS_Parent_Moodle_Provisioning();

