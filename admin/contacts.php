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
if ( ! defined( 'ABSPATH' ) || ! function_exists( 'add_action' ) || !defined( 'TVS_PMP_REQUIRED_CAPABILITY' ) || ! current_user_can( TVS_PMP_REQUIRED_CAPABILITY ) ) {
        header('HTTP/1.1 403 Forbidden');
        die();
}

?>
<div class="wrap">
<h2><?php _e( 'Parent Moodle Provisioning &mdash; Contacts', 'tvs-moodle-parent-provisioning' ); ?></h2>


<div class="hidden error" id="tvs-pmp-error"></div>
<div class="hidden updated" id="tvs-pmp-success"></div>

<?php if ( isset( $bulk_result ) ): ?>
	<div id="message" class="updated notice is-dismissible">
		<p><?php echo esc_html( $bulk_result ); ?></p>
	</div>
<?php endif; ?>

<?php if ( TVS_Parent_Moodle_Provisioning::settings_are_populated() ): ?>

<div id="message" class="notice">
<p><?php _e( 'Contacts and Contact Mappings are managed by the synchronisation script and are therefore kept in sync with the Management Information System. Any required changes should be made in the MIS and then the sync script should be re-run.', 'tvs-moodle-parent-provisioning' ); ?></p>
<p><?php _e( 'The provisioning and delete processes are <strong>two pass</strong>. During provisioning, a Contact&rsquo;s status will be <em>partial</em> while their Moodle account has not yet been set up by the Moodle database user sync task. After this, the Contact sync must run again before the status will become <em>provisioned</em>. Similarly, a Contact being deleted will have the transitional status <em>deleting</em> until the Moodle database user sync task suspends the user and the Contact sync has re-run.', 'tvs-moodle-parent-provisioning' ); ?></p>
</div>

<form id="pmp-search" method="POST" action="">

	<p class="search-box">
		<input id="search-input" type="search" name="s" value="<?php
			if ( isset( $_REQUEST['s'] ) ) {
				echo esc_attr( $_REQUEST['s'] );
			}
		?>" />
		<input id="search-submit" type="submit" class="button" value="<?php _e( 'Search', 'tvs-moodle-parent-provisioning' ); ?>" />
	</p>

</form>

<form id="tvs-pmp-contact-form" method="POST" action="">
<?php require_once( dirname( __FILE__ ) . '/../includes/class.tvs-pmp-contact-table.php' );

try {
	$table = new TVS_PMP_Contact_Table();
	$table->prepare_items();
	$table->views();
	$table->display();
}
catch ( \Exception $e ) {
	?><div class="error"><p><?php
	echo sprintf( __( 'Unable to display the table. Please verify that your database settings are correct and that permission has been granted for the specified user. Error: %s', 'tvs-moodle-parent-provisioning' ), $e->getMessage() );
	?></p></div><?php
}



?>

</form>

<?php else: ?>

<div class="error"><p><?php echo sprintf( __( 'Please complete the configuration of the plugin by going to the <a href="%s">Settings page</a> and filling the fields.', 'tvs-moodle-parent-provisioning' ), 'admin.php?page=tvs_parent_moodle_provisioning_settings' ); ?></p></div>

<?php endif; ?>

</div>
