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
if ( ! defined( 'ABSPATH' ) || ! function_exists( 'add_action' ) || !defined( 'TVS_PMP_REQUIRED_CAPABILITY' ) || ! current_user_can( TVS_PMP_REQUIRED_CAPABILITY ) ) {
        header('HTTP/1.1 403 Forbidden');
        die();
}

?>
<div class="wrap">
<h2><?php _e( 'Parent Moodle Provisioning &mdash; Authorised Users', 'tvs-moodle-parent-provisioning' ); ?></h2>

<div class="notice notice-info">
	<p>
		<?php _e( 'The authentication table is used by Moodle to determine which users are authorised to log in. This table is populated when you provision an account from the \'Requests\' screen. The table holds the username, email address and first and last names of users. Moodle handles password management internally.', 'tvs-moodle-parent-provisioning' ); ?>
	</p>
	<ul style="list-style:disc; margin-left: 20px"><li>
		<?php _e( 'To remove a Parent Moodle Account completely and cleanly, <strong>delete the user from Moodle first</strong>, reload this page and then immediately delete the corresponding entry from the auth table.', 'tvs-moodle-parent-provisioning' ); ?>
	</li>
	<li>
		<?php _e( 'To revoke access to an account while leaving the data within Moodle intact, find the parent\'s original request on the \'Requests\' screen and \'Reject\' it.', 'tvs-moodle-parent-provisioning' ); ?>
	</li>
	<li>
		<?php _e( 'Quickly verify that pupils are connected correctly by clicking <strong>Role Assignments</strong>. Additional pupils can be connected by finding the pupil user profile page in Moodle first, and using <strong>Preferences</strong> &gt; <strong>Assign roles relative to this user</strong>.', 'tvs-moodle-parent-provisioning' ); ?>
	</li></ul>


</div>

<div class="hidden error" id="tvs-pmp-error"></div>
<div class="hidden updated" id="tvs-pmp-success"></div>

<?php if ( isset( $bulk_result ) ): ?>
	<div id="message" class="updated notice is-dismissible">
		<p><?php echo esc_html( $bulk_result ); ?></p>
	</div>
<?php endif; ?>

<?php if ( TVS_Parent_Moodle_Provisioning::settings_are_populated() ): ?>

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

<form id="tvs-pmp-auth-form" method="POST" action="">
<?php require_once( dirname( __FILE__ ) . '/../includes/class.tvs-pmp-auth-table.php' );

try {
	$table = new TVS_PMP_Auth_Table();
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
<script type="text/javascript">
document.getElementById('tvs-pmp-auth-form').onsubmit = function() {
	return confirm( '<?php _e( 'Are you sure you want to delete these entries?\n\nIf any of the entries you have chosen to delete are currently connected to a Moodle user, that Moodle user will be denied access.', 'tvs-moodle-parent-provisioning' ); ?>' );
}
</script>

<?php else: ?>

<div class="error"><p><?php echo sprintf( __( 'Please complete the configuration of the plugin by going to the <a href="%s">Settings page</a> and filling the fields.', 'tvs-moodle-parent-provisioning' ), 'admin.php?page=tvs_parent_moodle_provisioning_settings' ); ?></p></div>

<?php endif; ?>

</div>
