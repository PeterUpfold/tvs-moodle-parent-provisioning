<?php
/*/* Copyright (C) 2016-2020 Test Valley School.


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
	<h2><?php _e( 'Parent Moodle Provisioning &mdash; Upload Users', 'tvs-moodle-parent-provisioning' ); ?></h2>

	<?php if ( TVS_Parent_Moodle_Provisioning::settings_are_populated() ): ?>
	<div style="clear:both;">
	<p><?php _e( 'Paste tabular data (e.g. from Excel) below to provision a batch of users at once.', 'tvs-moodle-parent-provisioning' ); ?></p>
	</div>

	<div class="notice notice-info" id="upload-users-information">
		<h3>
		<span class="dashicons dashicons-info">&nbsp;</span>
		<?php _e( 'Information', 'tvs-moodle-parent-provisioning' ); ?>
		</h3>
	</div>

	<div class="notice notice-warning" id="upload-users-warnings">
		<h3><span class="dashicons dashicons-warning">&nbsp;</span>
		<?php _e( 'Warnings', 'tvs-moodle-parent-provisioning' ); ?>
		</h3>
	</div>

	<div class="error notice notice-error" id="upload-users-errors">
		<h3><span class="dashicons dashicons-no"></span>
		<?php _e( 'Errors', 'tvs-moodle-parent-provisioning' ); ?>
		</h3>
	</div>

	<div id="upload-users-table" style="width: 100%;"></div>

	<p>
	<button class="button button-primary" id="upload-users-button"><?php _e( 'Upload and Approve Users', 'tvs-moodle-parent-provisioning' ); ?></button>
	</p>

	<script type="text/javascript">
	var upload_users_nonce = '<?php echo wp_create_nonce( sha1( 'tvs_moodle_parent_provisioning_upload_users' ) ); ?>';
	</script>

<?php else: ?>

<p><?php echo sprintf( __( 'Please complete the configuration of the plugin by going to the <a href="%s">Settings page</a> and filling the fields.', 'tvs-moodle-parent-provisioning' ), 'admin.php?page=tvs_parent_moodle_provisioning_settings' ); ?></p>

<?php endif; ?>

</div>
