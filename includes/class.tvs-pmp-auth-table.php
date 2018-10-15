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
 * Extending the WP_List_Table class, allows for the Parent Moodle Provisioning auth table entries
 * to be displayed in a pretty WordPress table, along with appropriate actions.
 */

require_once( dirname( __FILE__ ) . '/class-tvs-wp-list-table.php' );
require_once( dirname( __FILE__ ) . '/class.tvs-pmp-mdl-user.php' );
require_once( dirname( __FILE__ ) . '/class.tvs-pmp-mdl-db-helper.php' );


if ( ! defined ( 'TVS_PMP_REQUIRED_CAPABILITY' ) ) {
	header( 'HTTP/1.0 403 Forbidden' );
	echo '<h1>Forbidden</h1>';
	die();
}

class TVS_PMP_Auth_Table extends TVS_WP_List_Table {


	/**
	 * The name of the database table containing the records.
	 */
	public static $db_table_name = 'tvs_parent_moodle_provisioning_auth';

	/**
	 * All possible database fields by which we can order.
	 */
	public static $field_names = array(
				'id', 'username', 'parent_title', 'parent_fname', 'parent_sname', 'parent_email', 'description', 'request_id'
	);

	/** 
	 * A connection to the Moodle database for checking user orphaned status.
	 */
	protected $moodle_dbc = null;

	/**
	 * A Monolog Logger object to which we push log entries.
	 */
	protected $logger = NULL;

	/**
	 * A stream containing log entries that have been emittted from the $logger.
	 */
	protected $local_log_stream = NULL;

	/**
	 * Constructor
	 */
	public function __construct( $args = array() ) {
		
		$this->moodle_dbc = new \mysqli(
			get_option( 'tvs-moodle-parent-provisioning-moodle-dbhost' ),
			get_option( 'tvs-moodle-parent-provisioning-moodle-dbuser' ),
			get_option( 'tvs-moodle-parent-provisioning-moodle-dbpass' ),
			get_option( 'tvs-moodle-parent-provisioning-moodle-db' )
		);

		if ( $this->moodle_dbc->connect_errno ) {
			throw new \Exception( $this->moodle_dbc->connect_error );
		}

		$this->logger = TVS_PMP_MDL_DB_Helper::create_logger( $this->local_log_stream );

		return parent::__construct(
			array(
				'singular'	=> __( 'entry', 'tvs-moodle-parent-provisioning' ),
				'plural'	=> __( 'entries', 'tvs-moodle-parent-provisioning' ),
				'ajax'		=> true,
			)
			/*
				Note that the plural argument is used to make wpnonces. It cannot have spaces and probably should
				not be localised, unless you also localise the plural when checking the nonce.
			*/
		);

	}

	/**
	 * The capability required to modify records in the table.
	 */
	public function ajax_user_can() {
		return current_user_can( TVS_PMP_REQUIRED_CAPABILITY );
	}

	/**
	 * Define this table's columns and their tables.
	 */
	public function get_columns() {
		return array(
			'cb'	           => '<input type="checkbox" />',
			'username'	   => __( 'Username', 'tvs-moodle-parent-provisioning' ),
			'parent_title'     => __( 'Title', 'tvs-moodle-parent-provisioning' ),
			'parent_fname'     => __( 'Forename', 'tvs-moodle-parent-provisioning' ),

			'parent_sname'     => __( 'Surname', 'tvs-moodle-parent-provisioning' ),
			'description'      => __( 'Details', 'tvs-moodle-parent-provisioning' ),
			'request_id'       => __( 'ReqID', 'tvs-moodle-parent-provisioning' )
		);
	}

	/**
	 * Display handler for the checkbox column.
	 */
	public function column_cb( $item ) {
		return sprintf(
			'<input type="checkbox" name="request_id[]" value="%s" />',
			esc_attr( $item->request_id )
		);
	}

	/**
	 * Define the table's bulk actions.
	 */
	public function get_bulk_actions() {
		return array(
			'delete'			=> __( 'Delete', 'tvs-moodle-parent-provisioning' )
		);
	}


	/**
	 * Define this table's sortable columns.'
	 */
	public function get_sortable_columns() {

                return array(
			'username'	   => __( 'Username', 'tvs-moodle-parent-provisioning' ),
			'parent_title'     => __( 'Title', 'tvs-moodle-parent-provisioning' ),
			'parent_fname'     => __( 'Forename', 'tvs-moodle-parent-provisioning' ),

			'parent_sname'     => __( 'Surname', 'tvs-moodle-parent-provisioning' ),
			'request_id'       => __( 'ReqID', 'tvs-moodle-parent-provisioning' )

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
	 * Description/details column will contain most of the data about this auth table entry, along with actions.
	 */
	public function column_description( $item ) {

		?><p><?php
		$profile_available = false;

		if ( property_exists( $item, 'is_orphaned' ) ) {
			if ( $item->is_orphaned ) {
				?><span class="dashicons dashicons-no"></span><?php
				_e( 'Not matched to a Parent Moodle user.', 'tvs-moodle-parent-provisioning' );
			}
			else {
				?><span class="dashicons dashicons-update"></span><?php
				_e( 'Matched to a Parent Moodle user.', 'tvs-moodle-parent-provisioning' );
				$profile_available = true;
			}
		}
		else {
			_e( 'Unable to determine if connected to a Moodle user.', 'tvs-moodle-parent-provisioning' );
		}

		$profile_url = trailingslashit( get_option( 'tvs-moodle-parent-provisioning-moodle-url' ) ) . 'user/profile.php?id=' . intval( $item->user->id );
		$role_assignments_url = trailingslashit( get_option( 'tvs-moodle-parent-provisioning-moodle-url' ) ) . 'admin/roles/usersroles.php?courseid=1&amp;userid=' . intval( $item->user->id );
		$logs_url = trailingslashit( get_option( 'tvs-moodle-parent-provisioning-moodle-url' ) ) . 'report/log/user.php?course=1&amp;mode=all&amp;id=' . intval( $item->user->id );

		?></p><div class="row-actions visible">
		
		<?php if ( $profile_available ): ?>

		<span class="view_profile"><a href="<?php echo esc_url( $profile_url ); ?>" target="_blank"><?php _e( 'View Profile', 'tvs-moodle-parent-provisioning' ); ?></a></span> |
		<span class="role_assignments"><a href="<?php echo esc_url( $role_assignments_url ); ?>" target="_blank"><?php _e( 'Role Assignments', 'tvs-moodle-parent-provisioning' ); ?></a></span> |
		<span class="logs"><a href="<?php echo esc_url( $logs_url ); ?>" target="_blank"><?php _e( 'Logs', 'tvs-moodle-parent-provisioning' ); ?></a></span> |
		<span class="delete"><a href="" class="delete-current-moodle-user" data-request-id="<?php echo intval( $item->request_id ); ?>"><?php _e( 'Delete', 'tvs-moodle-parent-provisioning' ); ?></a></span>

		<?php else: ?>
		<?php
		?>
		<span class="delete"><a href="" class="delete-orphaned" data-request-id="<?php echo intval( $item->request_id ); ?>"><?php _e( 'Delete', 'tvs-moodle-parent-provisioning' ); ?></a></span>
		<?php endif; ?>


		</div><?php
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
	public function get_data( $orderby, $order, $offset, $limit, $search = '' ) {
		global $wpdb;
		
		$tn = TVS_PMP_Auth_Table::$db_table_name;

		if ( $order != 'DESC' && $order != 'ASC' ) {
			$order = 'DESC';
		}
		if ( ! in_array( $orderby, TVS_PMP_Auth_Table::$field_names ) ) {
			$orderby = 'request_id';
		}

		if ( strlen( $search ) < 1 ) {

			$query = $wpdb->prepare(
				"SELECT 
					id, username, parent_title, parent_fname, parent_sname, parent_email, description, request_id
				FROM {$wpdb->prefix}{$tn}
				ORDER BY {$orderby} {$order}
				LIMIT %d, %d",

				$offset,
				$limit

			);
		}
		else {

			$search = $wpdb->esc_like( $search );
			$search = '%' . $search . '%';

			$query = $wpdb->prepare(
			"SELECT 
					id, username, parent_title, parent_fname, parent_sname, parent_email, description, request_id
			FROM {$wpdb->prefix}{$tn}
			WHERE
				parent_fname LIKE %s OR
				parent_sname LIKE %s OR
				parent_email LIKE %s
			ORDER BY {$orderby} {$order}
			LIMIT %d, %d",

			$search,
			$search,
			$search,
			$offset,
			$limit			
		);

		}

		$results = $wpdb->get_results( $query );

		// for each result, we look up whether or not a Moodle account exists by this email address
		if ( is_array( $results ) && count( $results ) > 0 ) {

			if ( $this->moodle_dbc ) {
				foreach( $results as $result ) {
					$mdl_user = new TVS_PMP_mdl_user( $this->logger, $this->moodle_dbc );
					$mdl_user->username = $parent_email;
					$mdl_user->load( 'username' );
					$result->is_orphaned = $mdl_user->is_orphaned();
					$result->user = $mdl_user;
				}
			}
		}

		return $results;

	}

	/**
	 * Return the count of items that match the given criteria. Used for pagination.
	 */
	public function get_total_items( $search = '' ) {
		global $wpdb;
		
		$tn = TVS_PMP_Auth_Table::$db_table_name;

		/*if ( $order != 'DESC' && $order != 'ASC' ) {
			$order = 'DESC';
		}
		if ( ! in_array( $orderby, $this->field_names ) ) {
			$orderby = 'date_created';
		}*/ //TODO complete or scrap this -- pagination calculations by order? Shouldn't matter, should it?

		if ( strlen( $search ) < 1 ) {

			$query = 
				"SELECT COUNT(id) 
				FROM {$wpdb->prefix}{$tn}"; // cannot wpdb->prepare without at least one substitution
		}
		else {
			$query = $wpdb->prepare(
				"SELECT COUNT(id) 
				FROM {$wpdb->prefix}{$tn}
				WHERE 
					parent_fname LIKE %s OR
					parent_sname LIKE %s OR
					parent_email LIKE %s
				",
				$search,
				$search,
				$search
			);
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


		// get search query
		$search = '';
		if ( array_key_exists( 's', $_POST ) ) {
			$search = $_POST['s'];
		}

		$total_items = $this->get_total_items( $search );

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
				case 'R':
				default:
					$orderby = 'request_id';
				break;
			}
		}

		if ( '' == $orderby ) {
			$orderby = 'request_id';
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

		$data = $this->get_data( $orderby, $order, $offset, $per_page, $search );

		$this->items = $data;

		$this->set_pagination_args( array( 
			'total_items'		=>	$total_items,
			'per_page'		=>	$per_page,
			'total_pages'		=>	ceil( $total_items / $per_page )
		) );
	}


};
