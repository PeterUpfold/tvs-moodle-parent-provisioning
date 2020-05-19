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


require_once( dirname( __FILE__ ) . '/../includes/class.tvs-pmp-batch.php' );

?>
<div class="wrap">
	<h2><?php _e( 'Parent Moodle Provisioning &mdash; Batch Logs', 'tvs-moodle-parent-provisioning' ); ?></h2>


	<div class="notice">
	<p><?php _e( 'Batch:', 'tvs-moodle-parent-provisioning' ); ?>&nbsp;&nbsp;<select id="batch-id" name="batch-id">
		<option value=""></option>
		<?php 
		$batches = TVS_PMP_Batch::get_batch_list();
		if ( is_array( $batches ) && count( $batches ) > 0 ):
			foreach( $batches as $batch ):

				$this_batch = ( array_key_exists( 'batch', $_GET ) && $_GET['batch'] == $batch->id ) ? true : false;
		?>
			<option value="<?php echo esc_attr( $batch->id ); ?>" <?php if ( $this_batch ) : ?>
				 selected="selected"
			<?php endif; ?>>
			<?php echo esc_html( $batch->id ); ?> (<?php echo esc_html( $batch->timestamp ); ?>)
			</option>
		<?php endforeach; endif; ?>
	</select></p>
	</div>

	<?php $batch_id = ( array_key_exists( 'batch', $_GET ) ) ? $_GET['batch'] : ''; 
	$batch = new TVS_PMP_Batch();
	$batch->id = $batch_id;
	try {
		$batch->load();
	}
	catch (Exception $e) {

	}
	?>
	
	<div class="notice notice-info" id="upload-users-information">
		<h3>
		<span class="dashicons dashicons-info">&nbsp;</span>
		<?php _e( 'Information', 'tvs-moodle-parent-provisioning' ); ?>
		</h3>
		<?php if ( is_array( $batch->information ) && count( $batch->information ) > 0 ): ?>
			<?php foreach( $batch->information as $line ): ?>
				<p>
				<?php echo esc_html( $line ); ?> 
				</p>
			<?php endforeach; ?>
		<?php endif; ?>
	</div>

	<div class="notice notice-warning" id="upload-users-warnings">
		<h3><span class="dashicons dashicons-warning">&nbsp;</span>
		<?php _e( 'Warnings', 'tvs-moodle-parent-provisioning' ); ?>
		</h3>
		<?php if ( is_array( $batch->warnings ) && count( $batch->warnings ) > 0 ): ?>
			<?php foreach( $batch->warnings as $line ): ?>
				<p>
				<?php echo esc_html( $line ); ?> 
				</p>
			<?php endforeach; ?>
		<?php endif; ?>
	</div>

	<div class="error notice notice-error" id="upload-users-errors">
		<h3><span class="dashicons dashicons-no"></span>
		<?php _e( 'Errors', 'tvs-moodle-parent-provisioning' ); ?>
		</h3>
		<?php if ( is_array( $batch->errors ) && count( $batch->errors ) > 0 ): ?>
			<?php foreach( $batch->errors as $line ): ?>
				<p>
				<?php echo esc_html( $line ); ?> 
				</p>
			<?php endforeach; ?>
		<?php endif; ?>
	</div>

	<div>
		<input type="hidden" id="delete-batch-log-nonce" value="<?php echo wp_create_nonce( 'tvs-moodle-parent-provisioning-delete-batch-log' ); ?>" />
		<button class="button button-secondary" id="delete-batch-log" data-batch="<?php echo esc_attr( $batch_id ); ?>"><?php _e( 'Delete This Batch Log', 'tvs-moodle-parent-provisioning' ); ?></button>
		<button class="button button-secondary" id="delete-all-batch-logs"><?php _e( 'Delete All Batch Logs', 'tvs-moodle-parent-provisioning' ); ?></button>
	</div>

</div>

<script type="text/javascript">
jQuery(document).ready(function($) {
	$('#batch-id').change(function(e) {
		window.location.href = 'admin.php?page=tvs_parent_moodle_provisioning_batch_logs&batch=' + $(this).val();
	});
	$('#delete-batch-log').click(function(e) {
		//TODO l10n
		if ( confirm( 'Are you sure you want to permanently delete this batch log?\n\nThis cannot be undone.' ) ) {
			$.ajax({
				type:    'POST',
				url:     ajaxurl,
				data:    {
					action:      'tvs_pmp_delete_batch_log',
					batch:       $(this).data('batch'),
					_ajax_nonce:    $('#delete-batch-log-nonce').val()
				},
				success: function(data, textStatus, jqXHR) {
					window.location.href = window.location.href;	
				}
			}).fail( function(jqXHR, textStatus, errorThrown ) {
				alert( 'Did not succeed at deleting the batch logs. ' + textStatus );
			});
		}
	});
	$('#delete-all-batch-logs').click(function(e) {
		if ( confirm( 'Are you sure you want to permanently delete ALL batch logs?\n\nThis cannot be undone.' ) ) {
			$.ajax({
				type:    'POST',
				url:     ajaxurl,
				data:    {
					action:      'tvs_pmp_delete_all_batch_logs',
					_ajax_nonce:    $('#delete-batch-log-nonce').val()
				},
				success: function(data, textStatus, jqXHR) {
					window.location.href = window.location.href;	
				}
			}).fail( function(jqXHR, textStatus, errorThrown) {
				alert( 'Did not succeed at deleting all the batch logs. ' + textStatus );
			});
		}
	});
});
</script>
