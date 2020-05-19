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
global $force_pmp_cycle;

if ( ! defined( 'ABSPATH' ) || ! function_exists( 'add_action' ) || !defined( 'TVS_PMP_REQUIRED_CAPABILITY' ) || ! current_user_can( TVS_PMP_REQUIRED_CAPABILITY ) ) {
        header('HTTP/1.1 403 Forbidden');
        die();
}

require_once( dirname( __FILE__ ) . '/../includes/class.tvs-pmp-request.php' );
TVS_PMP_Request::update_pending_count();

?>
<div class="wrap">
<h2>Parent Moodle Provisioning</h2>

<div class="error"><p><?php _e( 'When approving a parent for provisioning, please use your Management Information System to check:', 'tvs-moodle-parent-provisioning' ); ?></p>
<ul style="list-style:disc outside; margin-left: 20px;">
<li><?php _e( 'Parent details and email address match inputs given here', 'tvs-moodle-parent-provisioning' ); ?></li>
<li><?php _e( 'No opportunity for confusion with unrelated pupils with similar surname', 'tvs-moodle-parent-provisioning' ); ?></li>
<li><?php _e( 'Any &lsquo;Quick Note&rsquo; or other critical information that must be taken into account regarding a pupil', 'tvs-moodle-parent-provisioning' ); ?></li>
</ul>
<p><?php _e('Only when these checks are completed, click Provision.', 'tvs-moodle-parent-provisioning' ); ?></p></div>

<div class="hidden error" id="tvs-pmp-error"></div>
<div class="hidden updated" id="tvs-pmp-success"></div>

<?php if ( get_option( 'tvs-moodle-parent-provisioning-force-provisioning-cycle' ) ): ?>
	<div class="updated">
		<p><?php _e( 'The provisioning cycle will run within the next 2 minutes. If it does not, but this message persists, verify that the cron job is configured correctly on the server.', 'tvs-moodle-parent-provisioning' ); ?></p>
	</div>
<?php endif; ?>

<?php if ( TVS_Parent_Moodle_Provisioning::settings_are_populated() ): ?>

<?php if ( isset( $force_pmp_cycle ) && !empty( $force_pmp_cycle ) ): ?>
	<div class="updated">
		<p><?php echo nl2br( esc_html( $force_pmp_cycle ) ); ?></p>
	</div>
<?php endif; ?>

<form id="pmp-force-provisioning-cycle" method="POST" action="">
	<p style="text-align: right;">
	<?php wp_nonce_field( 'force-provisioning-cycle', 'force-provisioning-cycle' ); ?>
	<input class="button button-secondary" type="submit" value="<?php _e( 'Force Provisioning Cycle', 'tvs-moodle-parent-provisioning' ); ?>" />
	</p>
</form>

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

<?php require_once( dirname( __FILE__ ) . '/../includes/class.tvs-pmp-table.php' );

try {
	$table = new TVS_PMP_Table();
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

<?php else: ?>

<div class="error"><p><?php echo sprintf( __( 'Please complete the configuration of the plugin by going to the <a href="%s">Settings page</a> and filling the fields.', 'tvs-moodle-parent-provisioning' ), 'admin.php?page=tvs_parent_moodle_provisioning_settings' ); ?></p></div>

<?php endif; ?>

</div>
