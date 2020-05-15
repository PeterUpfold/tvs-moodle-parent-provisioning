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
 * Extending the WP_List_Table class, allows for the Parent Moodle Provisioning requests
 * to be displayed in a pretty WordPress table.
 */

require_once( dirname( __FILE__ ) . '/class-tvs-wp-list-table.php' );

class TVS_PMP_Table extends TVS_WP_List_Table {

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
	 * The name of the database table containing the records.
	 */
	public static $db_table_name = 'tvs_parent_moodle_provisioning';

	/**
	 * All possible database fields by which we can order.
	 */
	public static $field_names = array(
				'id', 'parent_title', 'parent_fname', 'parent_sname', 'child_fname', 'child_sname',
				'child_tg', 'parent_email', 'child2_fname', 'child2_sname', 'child2_tg', 'child3_fname',
				'child3_sname', 'child3_tg', 'status', 'parent_comment', 'staff_comment', 'system_comment',
				'date_created', 'date_updated', 'date_approved', 'remote_ip_addr', 'provisioned_username',
				'provisioned_initialpass'
	);

	/** 
	 * A connection to the Moodle database to check for existing accounts.
	 */
	public $moodle_db_conn = null;

	/**
	 * Whether or not to dipsplay the request status in the table. Set to true when you are in a view
	 * where the table is showing 'all' statuses, rather than one specific status.
	 */
	public $should_display_status = false;

	public function __construct( $args = array() ) {

		require_once( dirname( __FILE__ ) . '/class.tvs-pmp-mdl-user.php' );

		$this->moodle_db_conn = new mysqli(
			get_option( 'tvs-moodle-parent-provisioning-moodle-dbhost' ),
			get_option( 'tvs-moodle-parent-provisioning-moodle-dbuser' ),
			get_option( 'tvs-moodle-parent-provisioning-moodle-dbpass' ),
			get_option( 'tvs-moodle-parent-provisioning-moodle-db' )
		);

		if ( $this->moodle_db_conn->connect_errno ) {
			throw new \Exception( $this->moodle_db_conn->connect_error );
		}

		return parent::__construct(
			array(
				'singular'	=> 'request',
				'plural'	=> 'requests',
				'ajax'		=> true,
			)
		);

	}

	/**
	 * The capability required to modify records in the table.
	 */
	public function ajax_user_can() {
		return current_user_can( 'activate_plugins' );
	}

	/**
	 * Define the filterable views at the top of the table.
	 */
	public function get_views() {


		if ( array_key_exists( 'status', $_GET ) && in_array( $_GET['status'], TVS_PMP_Table::$statuses ) ) {
			$status = $_GET['status'];
		}
		else if ( array_key_exists( 'status', $_GET ) && 'all' == $_GET['status'] ) {
			$status = 'all';
			$this->should_display_status = true;
		}
		else {
			$status = 'pending';
		}

		foreach( TVS_PMP_Table::$statuses as $stat ) {
			if ( $stat == $status ) { //current status, set class
				$class = ' class="current"';
			}
			else {
				$class = '';
			}

			$stat_titlecase = strtoupper( substr( $stat, 0, 1 ) ) . substr( $stat, 1 ) ;

			if ( __( 'Approved', 'tvs-moodle-parent-provisioning' ) == $stat_titlecase ) {
				 $stat_titlecase = __( 'Approved &ndash; Not Provisioned', 'tvs-moodle-parent-provisioning' );
			}


			$views[$stat] = "<a href='" . esc_url( add_query_arg( 'status', $stat, 'admin.php?page=tvs_parent_moodle_provisioning' ) ) . "'$class>$stat_titlecase</a>";
		}

		if ( 'all' == $status ) {
			$class = ' class="current"';
		}
		else {
			$class = '';
		}
		$views['all'] = "<a href='" . esc_url( add_query_arg( 'status', 'all', 'admin.php?page=tvs_parent_moodle_provisioning' ) ) . "'$class><em>All</em></a>";

		return $views;

	}

	/**
	 * Define this table's columns and their tables.
	 */
	public function get_columns() {
		return array(

			'parent_title'     => __( 'Title', 'tvs-moodle-parent-provisioning' ),
			'parent_fname'     => __( 'Forename', 'tvs-moodle-parent-provisioning' ),

			'parent_sname'     => __( 'Surname', 'tvs-moodle-parent-provisioning' ),

			'child_fname'      => __( 'Children', 'tvs-moodle-parent-provisioning' ),
			'parent_comment'   => __( 'Comments', 'tvs-moodle-parent-provisioning' ),
			'date_created'     => __( 'Date/Time', 'tvs-moodle-parent-provisioning' ),
	
		);
	}


	/**
	 * Define this table's sortable columns.'
	 */
	public function get_sortable_columns() {

                return array(

			'parent_title'     => __( 'Title', 'tvs-moodle-parent-provisioning' ),
			'parent_fname'     => __( 'Forename', 'tvs-moodle-parent-provisioning' ),

			'parent_sname'     => __( 'Surname', 'tvs-moodle-parent-provisioning' ),

			'date_created'     => __( 'Date/Time', 'tvs-moodle-parent-provisioning' ),

                );

	}

	/**
	 * Display handler for most columns.
	 *
	 */
	public function column_default( $item, $column_name ) {
		echo esc_html( $item->$column_name );	
	}

	/**
	 * Display handler for this column.
	 */
	public function column_status( $item ) {
		$stat_titlecase = strtoupper( substr( $item->status, 0, 1 ) ) . substr( $item->status, 1 ) ;
		echo esc_html( $stat_titlecase );
	}


	/**
	 * Display handler for this column.
	 */
	public function column_parent_comment( $item ) {

		if ( $this->should_display_status ) {

			?><h4 style="margin:0"><?php _e( 'Status: ', 'tvs-moodle-parent-provisioning' ); ?> <em><?php echo esc_html(  strtoupper( substr( $item->status, 0, 1 ) ) . substr( $item->status, 1 ) ); ?></em></h4><?php

		}
		
		?><h4 style="margin:0;"><?php _e( 'Nature of Request:', 'tvs-moodle-parent-provisioning' ); ?></h4>

		<?php
			// get class name of request type
			if ( property_exists( $item, 'request_type' ) && strlen( $item->request_type ) > 0 ) {
				switch ( $item->request_type ) {
					//TODO l10n -- also should we be tracking form entries?
					case 'I do not currently have a Moodle Account.':
						$request_type_class = 'new-account';
						$request_type_dashicon = 'dashicons-plus-alt';
					break;
					case 'I have a Moodle account, but cannot log in.':
						$request_type_class = 'login-issue';
						$request_type_dashicon = 'dashicons-admin-network';
					break;
					case 'One or more of my children is not linked correctly to my account (please provide details below).':
						$request_type_class = 'missing-pupils';
						$request_type_dashicon = 'dashicons-admin-users';
					break;
					case 'Uploaded in a batch via \'Upload Users\'':
						$request_type_class = 'batch';
						$request_type_dashicon = 'dashicons-list-view';
					break;
					default:
						$request_type_class = 'unspecified';
						$request_type_dashicon = 'dashicons-info';
					break;
				}
			}
			else {
				$request_type_class = 'unspecified';
				$request_type_dashicon = 'dashicons-info';
			}
		?>

		<p class="request-type <?php echo $request_type_class; ?>"><span class="dashicons <?php echo $request_type_dashicon; ?>"></span> <?php if (property_exists( $item, 'request_type' ) && strlen( $item->request_type ) > 0 ):
			echo esc_html( $item->request_type ); else :
			_e( 'Not specified', 'tvs-moodle-parent-provsioning' );
		endif; ?></p>

		<?php if ( 'pending' == $item->status && $this->moodle_db_conn ) :
		
			$moodle_user = new TVS_PMP_mdl_user(  $item->parent_email, $this->moodle_db_conn );
			if ( ! $moodle_user->is_orphaned() ) {
				?>
				<span class="dashicons dashicons-warning"></span> <?php _e( 'An existing Moodle account with this email address was detected.', 'tvs-moodle-parent-provisioning' ); ?>
			<?php
			}
		endif; ?>

		<h4 style="margin:0;"><?php _e( 'Parent Comment:', 'tvs-moodle-parent-provisioning' ); ?></h4><p><span id="tvs-pmp-parent-comment-<?php echo intval( $item->id ); ?>">
		<textarea disabled style="width: 100%;" class="tvs-pmp-parent-comment-textarea" id="tvs-pmp-parent-comment-textarea-<?php echo intval( $item->id ); ?>"><?php
		echo esc_html( $item->parent_comment );

		?></textarea></span></p><h4 style="margin:0;"><?php _e( 'Staff Comment:', 'tvs-moodle-parent-provisioning' ); ?></h4><p><span id="tvs-pmp-staff-comment-<?php echo intval( $item->id ); ?>">
		<textarea data-parent-id="<?php echo intval( $item->id ); ?>" style="width: 100%;" class="tvs-pmp-staff-comment-textarea" id="tvs-pmp-staff-comment-textarea-<?php echo intval( $item->id ); ?>" style="width: 100%;"><?php
		echo esc_html( $item->staff_comment );
		?></textarea></span></p>
		<p style="color:#777; font-size: 90%;" id="tvs-pmp-staff-comment-saving-<?php echo intval( $item->id ); ?>"></p>
		<h4 style="margin:0;"><?php _e( 'System Comment:', 'tvs-moodle-parent-provisioning' ); ?></h4><p><span id="tvs-pmp-system-comment-<?php echo intval( $item->id ); ?>"><textarea disabled class="tvs-pmp-system-comment-textarea" id="tvs-pmp-system-comment-textarea-<?php echo intval( $item->id ); ?>" style="width: 100%;"><?php
		echo esc_html( $item->system_comment ); 
		?></textarea></span></p>

		<hr style="margin: 30px 0 10px 0;" />

		<h4 style="margin: 0 0 10px 0;"><?php _e( 'Send Automated Email:', 'tvs-moodle-parent-provisioning' ); ?></h4>
		<div class="row-actions visible" style="margin-bottom:1.33em">
			<span class="send_forgotten_password_email"><a href="" data-request-id="<?php echo $item->id; ?>"><?php _e( 'Forgotten Password Instructions', 'tvs-moodle-parent-provisioning' ); ?></a></span> |

			<span class="send_details_not_on_file_email"><a href="" data-request-id="<?php echo $item->id; ?>"><?php _e( 'Details Not On File', 'tvs-moodle-parent-provisioning' ); ?></a></span> |
			<span class="send_generic_fixed_email"><a href="" data-request-id="<?php echo $item->id; ?>"><?php _e( 'Generic Fixed', 'tvs-moodle-parent-provisioning' ); ?></a></span> <!--|-->
		</div>

		<p style="color:#777; font-size: 90%;" data-request-id="<?php echo intval( $item->id ); ?>" class="tvs-pmp-email-sending"></p>

		<hr style="margin: 30px 0 10px 0;" />

		<h4 style="margin: 0 0 10px 0;"><?php _e( 'Provision / Change Status:', 'tvs-moodle-parent-provisioning' ); ?></h4>

		<div class="row-actions visible">
			<span class="provision"><a href="" data-parent-id="<?php echo intval( $item->id ); ?>"><?php _e( 'Provision', 'tvs-moodle-parent-provisioning' ); ?></a> |</span>
			<span class="reject"><a href="" data-parent-id="<?php echo intval( $item->id ); ?>"><?php _e( 'Reject', 'tvs-moodle-parent-provisioning' ); ?></a> |</span>
			<span class="markasduplicate"><a href="" data-parent-id="<?php echo intval( $item->id ); ?>"><?php _e( 'Mark as Duplicate', 'tvs-moodle-parent-provisioning' ); ?></a> |</span>
			<span class="markasbogus"><a href="" data-parent-id="<?php echo intval( $item->id ); ?>"><?php _e( 'Mark as Bogus', 'tvs-moodle-parent-provisioning' ); ?></a></span> |
			<span class="pending"><a href="" data-parent-id="<?php echo intval( $item->id ); ?>"><?php _e( 'Mark as Pending', 'tvs-moodle-parent-provisioning' ); ?></a></span>
		</div>



		<?php
	}

	/**
	 * Display handler for this column.
	 */
	public function column_date_created( $item ) {

		$before_tz = @date_default_timezone_get();
		date_default_timezone_set( get_option( 'timezone_string' ) );

		?><p><strong><?php _e( 'Submitted:', 'tvs-moodle-parent-provisioning' ); ?></strong>&nbsp;&nbsp;<?php

		echo esc_html( date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( $item->date_created . ' UTC' ) ) );

		?></p><p><strong><?php _e( 'Updated:', 'tvs-moodle-parent-provisioning' ); ?></strong>&nbsp;&nbsp;<span id="tvs-pmp-date-updated-<?php echo intval( $item->id ); ?>"><?php

		if ( strtotime( $item->date_updated ) > 0 ) {
			echo esc_html( date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( $item->date_updated . ' UTC' ) ) );
		}
		else {
			_e( 'Not yet updated.', 'tvs-moodle-parent-provisioning' ); 
		}

		?></span></p><?php

		// reset timezone again
		date_default_timezone_set( $before_tz );

	}

	/**
	 * Display handler for this column.
	 */
	public function column_child_fname( $item ) {

		?><span class="child_fname" data-request-id="<?php echo $item->id; ?>">
		<?php echo esc_html( $item->child_fname ); ?>
		</span> <span class="child_sname" data-request-id="<?php echo $item->id; ?>">
		<?php echo esc_html( $item->child_sname ); ?>
		</span>

		<span class="child_tg" data-request-id="<?php echo $item->id; ?>">
		<?php echo $item->child_tg; ?>
		</span><br /><?php

		

		if ( ! empty( $item->child2_fname ) ) {
			?><span class="child2_fname" data-request-id="<?php echo $item->id; ?>">
			<?php echo esc_html( $item->child2_fname ); ?>
			</span> <span class="child2_sname" data-request-id="<?php echo $item->id; ?>">
			<?php echo esc_html( $item->child2_sname ); ?>
			</span>

			<span class="child2_tg" data-request-id="<?php echo $item->id; ?>">
			<?php echo $item->child2_tg; ?>
			</span><br /><?php
		}

		if ( ! empty( $item->child3_fname ) ) {
			?><span class="child3_fname" data-request-id="<?php echo $item->id; ?>">
			<?php echo esc_html( $item->child3_fname ); ?>
			</span> <span class="child3_sname" data-request-id="<?php echo $item->id; ?>">
			<?php echo esc_html( $item->child3_sname ); ?>
			</span>

			<span class="child3_tg" data-request-id="<?php echo $item->id; ?>">
			<?php echo $item->child3_tg; ?>
			</span><br /><?php
		}

		?><span class="childnames-end" data-request-id="<?php echo $item->id; ?>"></span><br/><br/><strong><?php _e( 'Email:', 'tvs-moodle-parent-provisioning' ); ?></strong><br/><?php
		echo esc_html( $item->parent_email );

?>
<br/>
<strong>MIS ID:</strong>
<?php
		echo ( $item->mis_id != NULL ) ? esc_html( $item->mis_id ) : 'not set';
?><br />
<strong>External MIS ID:</strong>
<?php
		echo ( $item->external_mis_id != NULL ) ? esc_html( $item->external_mis_id ) : 'not set';
?>
<div class="row-actions">
			<span class="adjustchildname"><a href="" data-request-id="<?php echo $item->id; ?>"><?php _e ( 'Adjust Childrens&rsquo; Names to Match Moodle', 'tvs-moodle-parent-provisioning' ); ?></a></span>
		</div>
		<?php

	}

	/**
	 * Display handler for the actions column.
	 */
	public function column_actions( $item ) {
		?><a href="#"><?php _e( 'Edit' ); ?></a><?php
	}


	/**
	 * Fetch data from table
	 */
	public function get_data( $status, $orderby, $order, $offset, $limit, $search = '' ) {
		global $wpdb;
		
		$tn = TVS_PMP_Table::$db_table_name;

		if ( $order != 'DESC' && $order != 'ASC' ) {
			$order = 'DESC';
		}
		if ( ! in_array( $orderby, TVS_PMP_Table::$field_names ) ) {
			$orderby = 'date_created';
		}

		if ( strlen( $search ) < 1 ) {

			if ( 'all' != $status ) {

				$query = $wpdb->prepare(
					"SELECT 
						id, parent_title, parent_fname, parent_sname, child_fname, child_sname,
						child_tg, parent_email, child2_fname, child2_sname, child2_tg, child3_fname,
						child3_sname, child3_tg, status, parent_comment, staff_comment, system_comment,
						date_created, date_updated, date_approved, remote_ip_addr, provisioned_username,
						provisioned_initialpass, request_type, mis_id, external_mis_id
					FROM {$wpdb->prefix}{$tn}
					WHERE status = %s
					ORDER BY {$orderby} {$order}
					LIMIT %d, %d",

					$status,
					$offset,
					$limit

				);

			}
			else {
				$query = $wpdb->prepare(
					"SELECT 
						id, parent_title, parent_fname, parent_sname, child_fname, child_sname,
						child_tg, parent_email, child2_fname, child2_sname, child2_tg, child3_fname,
						child3_sname, child3_tg, status, parent_comment, staff_comment, system_comment,
						date_created, date_updated, date_approved, remote_ip_addr, provisioned_username,
						provisioned_initialpass, request_type, mis_id, external_mis_id
					FROM {$wpdb->prefix}{$tn}
					ORDER BY {$orderby} {$order}
					LIMIT %d, %d",

					$offset,
					$limit

				);
			}

		}
		else {

			$search = $wpdb->esc_like( $search );
			$search = '%' . $search . '%';

			if ( 'all' != $status ) {

				$query = $wpdb->prepare(
					"SELECT 
						id, parent_title, parent_fname, parent_sname, child_fname, child_sname,
						child_tg, parent_email, child2_fname, child2_sname, child2_tg, child3_fname,
						child3_sname, child3_tg, status, parent_comment, staff_comment, system_comment,
						date_created, date_updated, date_approved, remote_ip_addr, provisioned_username,
						provisioned_initialpass, request_type, mis_id, external_mis_id
					FROM {$wpdb->prefix}{$tn}
					WHERE status = %s AND
					(
						parent_fname LIKE %s OR
						parent_sname LIKE %s OR
						child_fname LIKE %s OR
						child_sname LIKE %s OR
						parent_email LIKE %s OR
						child2_fname LIKE %s OR
						child2_sname LIKE %s OR
						child3_fname LIKE %s OR
						child3_sname LIKE %s OR
						parent_comment LIKE %s OR
						staff_comment LIKE %s
					)
					ORDER BY {$orderby} {$order}
					LIMIT %d, %d",

					$status,
					$search,
					$search,
					$search,
					$search,
					$search,
					$search,
					$search,
					$search,
					$search,
					$search,
					$search,
					$offset,
					$limit			
				);

			}
			else {
				$query = $wpdb->prepare(
					"SELECT 
						id, parent_title, parent_fname, parent_sname, child_fname, child_sname,
						child_tg, parent_email, child2_fname, child2_sname, child2_tg, child3_fname,
						child3_sname, child3_tg, status, parent_comment, staff_comment, system_comment,
						date_created, date_updated, date_approved, remote_ip_addr, provisioned_username,
						provisioned_initialpass, request_type, mis_id, external_mis_id
					FROM {$wpdb->prefix}{$tn}
					WHERE
						parent_fname LIKE %s OR
						parent_sname LIKE %s OR
						child_fname LIKE %s OR
						child_sname LIKE %s OR
						parent_email LIKE %s OR
						child2_fname LIKE %s OR
						child2_sname LIKE %s OR
						child3_fname LIKE %s OR
						child3_sname LIKE %s OR
						parent_comment LIKE %s OR
						staff_comment LIKE %s
					ORDER BY {$orderby} {$order}
					LIMIT %d, %d",

					$search,
					$search,
					$search,
					$search,
					$search,
					$search,
					$search,
					$search,
					$search,
					$search,
					$search,
					$offset,
					$limit			
				);

			}
		}

		$results = $wpdb->get_results( $query );

		return $results;

	}

	/**
	 * Return the count of items that match the given criteria. Used for pagination.
	 */
	public function get_total_items( $status, $search = '' ) {
		global $wpdb;
		
		$tn = TVS_PMP_Table::$db_table_name;

		/*if ( $order != 'DESC' && $order != 'ASC' ) {
			$order = 'DESC';
		}
		if ( ! in_array( $orderby, $this->field_names ) ) {
			$orderby = 'date_created';
		}*/ //TODO complete or scrap this -- pagination calculations by order? Shouldn't matter, should it?

		if ( strlen( $search ) < 1 ) {
			if ( 'all' != $status ) {
				$query = $wpdb->prepare(
					"SELECT COUNT(id) 
					FROM {$wpdb->prefix}{$tn}
					WHERE status = %s",

					$status
				);
			}
			else {
				$query = 
					"SELECT COUNT(id) 
					FROM {$wpdb->prefix}{$tn}"; // cannot wpdb->prepare without at least one substitution
			}
		}
		else {
			if ( 'all' != $status ) {
				$query = $wpdb->prepare(
					"SELECT COUNT(id) 
					FROM {$wpdb->prefix}{$tn}
					WHERE status = %s AND
					(
						parent_fname LIKE %s OR
						parent_sname LIKE %s OR
						child_fname LIKE %s OR
						child_sname LIKE %s OR
						parent_email LIKE %s OR
						child2_fname LIKE %s OR
						child2_sname LIKE %s OR
						child3_fname LIKE %s OR
						child3_sname LIKE %s OR
						parent_comment LIKE %s OR
						staff_comment LIKE %s
					)",
					$status,
					$search,
					$search,
					$search,
					$search,
					$search,
					$search,
					$search,
					$search,
					$search,
					$search,
					$search
				);
			}
			else {
				$query = $wpdb->prepare(
					"SELECT COUNT(id) 
					FROM {$wpdb->prefix}{$tn}
					WHERE 
					
						parent_fname LIKE %s OR
						parent_sname LIKE %s OR
						child_fname LIKE %s OR
						child_sname LIKE %s OR
						parent_email LIKE %s OR
						child2_fname LIKE %s OR
						child2_sname LIKE %s OR
						child3_fname LIKE %s OR
						child3_sname LIKE %s OR
						parent_comment LIKE %s OR
						staff_comment LIKE %s
					",
					$search,
					$search,
					$search,
					$search,
					$search,
					$search,
					$search,
					$search,
					$search,
					$search,
					$search
				);
			}
		}

		$results = $wpdb->get_var( $query );

		return $results;
	}


	/**
	 * Prepare data for the table
	 */
	public function prepare_items() {
		$per_page = 12;

		$columns = $this->get_columns();
		$hidden = array();
		$sortable_columns = $this->get_sortable_columns();

		$this->_column_headers = array( $columns, $hidden, $sortable_columns );

		$current_page = $this->get_pagenum();
		// determine offset
		$offset = ( $current_page - 1 ) * $per_page;


		// determine status
		$status = '';
		if ( array_key_exists( 'status', $_GET ) ) {
			if ( in_array( $_GET['status'], TVS_PMP_Table::$statuses ) ) {
				$status = $_GET['status'];
			}
			if ( 'all' == $_GET['status'] ) {
				$status = 'all';
			}
		}	
		if ( '' == $status ) {
			$status = 'pending';
		}

		// get search query
		$search = '';
		if ( array_key_exists( 's', $_POST ) ) {
			$search = $_POST['s'];
		}

		$total_items = $this->get_total_items( $status, $search );

		$orderby = '';

		if ( array_key_exists( 'orderby', $_GET ) ) {
			switch ( $_GET['orderby'] ) {
				case 'T':
					$orderby = 'parent_title';
				break;
				case 'F':
					$orderby = 'parent_fname';
				break;
				case 'S':
					$orderby = 'parent_sname';
				break;
				case 'D':
				default:
					$orderby = 'date_created';
				break;
			}
		}

		if ( '' == $orderby ) {
			$orderby = 'date_created';
		}


		$order = '';
		if ( array_key_exists( 'order', $_GET ) ) {
			if ( $_GET['order'] == 'asc' ) {
				$order = 'asc';
			}
			else {
				$order = 'desc';
			}
		}

		if ( '' == $order ) {
			$order = 'desc';
		}

		$data = $this->get_data( $status, $orderby, $order, $offset, $per_page, $search );

		$this->items = $data;

		$this->set_pagination_args( array( 
			'total_items'		=>	$total_items,
			'per_page'		=>	$per_page,
			'total_pages'		=>	ceil( $total_items / $per_page )
		) );
	}


};
