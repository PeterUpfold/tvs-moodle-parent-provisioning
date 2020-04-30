<?php
/* Copyright (C) 2016-2020 Test Valley School.


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
 * Extending the WP_List_Table class, allows for the Parent Moodle Provisioning Contact table entries
 * to be displayed in a pretty WordPress table, along with appropriate actions.
 */

require_once( dirname( __FILE__ ) . '/class-tvs-wp-list-table.php' );
require_once( dirname( __FILE__ ) . '/class.tvs-pmp-contact.php' );
require_once( dirname( __FILE__ ) . '/class.tvs-pmp-mdl-user.php' );
require_once( dirname( __FILE__ ) . '/class.tvs-pmp-mdl-db-helper.php' );


if ( ! defined ( 'TVS_PMP_REQUIRED_CAPABILITY' ) ) {
	header( 'HTTP/1.0 403 Forbidden' );
	echo '<h1>Forbidden</h1>';
	die();
}

class TVS_PMP_Contact_Table extends TVS_WP_List_Table {


	/**
	 * The name of the database table containing the records.
	 */
	public static $db_table_name = 'tvs_parent_moodle_provisioning_contact';

	/**
	 * All possible database fields by which we can order.
	 */
	public static $field_names = [
		'id',
		'mis_id',
		'surname',
		'email',
		'status',
		'date_created',
		'date_updated',
		'date_synced'
	];

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
				'singular'	=> __( 'contact', 'tvs-moodle-parent-provisioning' ),
				'plural'	=> __( 'contacts', 'tvs-moodle-parent-provisioning' ),
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
		return [ 
			'cb'	           => '<input type="checkbox" />',
			'id'               => __('ID', 'tvs-moodle-parent-provisioning' ),
			'mis_id'           => __('MIS ID', 'tvs-moodle-parent-provisioning' ),
			'username'	   => __( 'Username/Email', 'tvs-moodle-parent-provisioning' ),
			'title'            => __( 'Title', 'tvs-moodle-parent-provisioning' ),
			'forename'         => __( 'Forename', 'tvs-moodle-parent-provisioning' ),

			'surname'          => __( 'Surname', 'tvs-moodle-parent-provisioning' ),
			'description'      => __( 'Details', 'tvs-moodle-parent-provisioning' ),
			'status'           => __( 'Status', 'tvs-moodle-parent-provisioning' ),
			'date_updated'     => __( 'Updated', 'tvs-moodle-parent-provisioning' )
		];
	}

	/**
	 * Display handler for the checkbox column.
	 */
	public function column_cb( $item ) {
		return sprintf(
			'<input type="checkbox" name="id[]" value="%s" />',
			esc_attr( $item->id )
		);
	}

	/**
	 * Define the table's bulk actions.
	 */
	public function get_bulk_actions() {
		return [];
/*		return array(
			'delete'			=> __( 'Delete', 'tvs-moodle-parent-provisioning' )
);*/
	}


	/**
	 * Define this table's sortable columns.'
	 */
	public function get_sortable_columns() {

 		/*return [ 
			'id'               => __( 'ID', 'tvs-moodle-parent-provisioning' ),
			'mis_id'           => __( 'MIS ID', 'tvs-moodle-parent-provisioning' ),
			'username'	   => __( 'Username/Email', 'tvs-moodle-parent-provisioning' ),
			'surname'          => __( 'Surname', 'tvs-moodle-parent-provisioning' ),
			'status'           => __( 'Status', 'tvs-moodle-parent-provisioning' )
		]; /* TODO date to be separate column for sorting ? */

		/* format changed in some WP version -- now requires array with keys = column name, value = array
		 * containing column name as 0th entry and 'descfirst' bool as 1st entry
		 */
		return [
			'id'               => [ 'id', false ],
			'mis_id'           => [ 'mis_id', false ],
			'username'         => [ 'username', false ],
			'surname'          => [ 'surname', false ],
			'status'           => [ 'status', false ],
			'date_updated'     => [ 'date_updated', false ]
		];

	}

	/**
	 * Display handler for most columns.
	 *
	 */
	public function column_default( $item, $column_name ) {
		echo esc_html( $item->$column_name );	
	}


	/**
	 * Display handler for username/email column
	 */
	public function column_username( $item ) {
		echo esc_html( $item->email );
	}

	/**
	 * Display handler for date column.
	 */
	public function column_date_updated( $item ) {
	
		$before_tz = @date_default_timezone_get();
		date_default_timezone_set( get_option( 'timezone_string' ) );
	
		?><p><strong><?php _e( 'Updated:', 'tvs-moodle-parent-provisioning' ); ?></strong>&nbsp;&nbsp;<?php

		if ( strtotime( $item->date_updated ) > 0 ) {
			echo esc_html( date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( $item->date_updated . ' UTC' ) ) );
		}
		else {
			_e( 'Not yet updated.', 'tvs-moodle-parent-provisioning' ); 
		}

		?></p><p><strong><?php _e( 'Created:', 'tvs-moodle-parent-provisioning' ); ?></strong>&nbsp;&nbsp;<?php

		echo esc_html( date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( $item->date_created . ' UTC' ) ) );

	
		?></p><?php

		?></p><p><strong><?php _e( 'Synced:', 'tvs-moodle-parent-provisioning' ); ?></strong>&nbsp;&nbsp;<?php

		echo esc_html( date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( $item->date_synced . ' UTC' ) ) );

	
		?></p><?php

		// reset timezone again
		date_default_timezone_set( $before_tz );	
	}

	/**
	 * Description/details column will contain most of the data about this auth table entry, along with actions.
	 */
	public function column_description( $item ) {

		?><p><?php
		$profile_available = false;

		$item->load_mdl_user();

		if ( ! $item->mdl_user_id ) {
			?><span class="dashicons dashicons-no"></span><?php
			_e( 'Not matched to a Parent Moodle user.', 'tvs-moodle-parent-provisioning' );
		}
		else {
			?><span class="dashicons dashicons-update"></span><?php
			_e( 'Matched to a Parent Moodle user.', 'tvs-moodle-parent-provisioning' );
			$profile_available = true;
		}


		$profile_url = trailingslashit( get_option( 'tvs-moodle-parent-provisioning-moodle-url' ) ) . 'user/profile.php?id=' . intval( $item->mdl_user->id );
		$role_assignments_url = trailingslashit( get_option( 'tvs-moodle-parent-provisioning-moodle-url' ) ) . 'admin/roles/usersroles.php?courseid=1&amp;userid=' . intval( $item->mdl_user->id );
		$logs_url = trailingslashit( get_option( 'tvs-moodle-parent-provisioning-moodle-url' ) ) . 'report/log/user.php?course=1&amp;mode=all&amp;id=' . intval( $item->mdl_user->id );

		?></p><div class="row-actions visible">
		
		<?php if ( $profile_available ): ?>

		<span class="view_profile"><a href="<?php echo esc_url( $profile_url ); ?>" target="_blank"><?php _e( 'View Profile', 'tvs-moodle-parent-provisioning' ); ?></a></span> |
		<span class="role_assignments"><a href="<?php echo esc_url( $role_assignments_url ); ?>" target="_blank"><?php _e( 'Role Assignments', 'tvs-moodle-parent-provisioning' ); ?></a></span> |
		<span class="logs"><a href="<?php echo esc_url( $logs_url ); ?>" target="_blank"><?php _e( 'Logs', 'tvs-moodle-parent-provisioning' ); ?></a></span> |
		<span class="delete"><a href="" class="delete-current-moodle-user" data-request-id="<?php echo intval( $item->request_id ); ?>"><?php _e( 'Delete', 'tvs-moodle-parent-provisioning' ); ?></a></span>

		<?php else: ?>
		<?php
		?>
		<span class="delete"><a href="" class="delete-orphaned" data-contact-id="<?php echo intval( $item->id ); ?>"><?php _e( 'Delete', 'tvs-moodle-parent-provisioning' ); ?></a></span>
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

		
		$tn = TVS_PMP_Contact_Table::$db_table_name;

		if ( $order != 'DESC' && $order != 'ASC' ) {
			$order = 'DESC';
		}
		if ( ! in_array( $orderby, TVS_PMP_Contact_Table::$field_names ) ) {
			$orderby = 'id';
		}

		$results = TVS_PMP_Contact::load_by_query( $orderby, $order, $offset, $limit, $this->logger, $this->moodle_dbc, $search );

		return $results;

	}

	/**
	 * Return the count of items that match the given criteria. Used for pagination.
	 */
	public function get_total_items( $search = '' ) {
		
		$results = TVS_PMP_Contact::count_by_query( $search );

		return $results;
	}



	/**
	 * Prepare data for the table
	 */
	public function prepare_items() {
		$per_page = 24;

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

		$orderby = $_GET['orderby'];

		if ( ! in_array( $orderby, TVS_PMP_Contact_Table::$field_names, true ) ) {
			$orderby = 'id';
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
		$order = strtoupper( $order );

		$data = $this->get_data( $orderby, $order, $offset, $per_page, $search );

		$this->items = $data;

		$this->set_pagination_args( array( 
			'total_items'		=>	$total_items,
			'per_page'		=>	$per_page,
			'total_pages'		=>	ceil( $total_items / $per_page )
		) );
	}


};
