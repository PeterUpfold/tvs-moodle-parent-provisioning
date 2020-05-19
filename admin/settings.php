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
<h2><?php _e( 'Parent Moodle Provisioning &mdash; Settings', 'tvs-moodle-parent-provisioning' ); ?></h2>

<?php if ( isset( $success ) ): ?>
<div class="notice updated notice-is-dismissable"><p><?php echo $success; ?></p></div>
<?php endif; ?>

<form method="POST" action="">
<?php wp_nonce_field( 'tvs-moodle-parent-provisioning-settings' ); ?>
<table class="form-table">
<tbody>
<tr>
	<th scope="row" colspan="2">
		<h3><?php _e( 'Moodle Configuration', 'tvs-moodle-parent-provisioning' ); ?></h3>
		<p class="description"><?php _e( 'These settings are used to connect to the Moodle database. This connection is used to set permissions and roles on the newly created users.', 'tvs-moodle-parent-provisioning' ); ?></p>
	</th>
</tr>
<tr>
	<th scope="row"><?php _e( 'Moodle URL', 'tvs-moodle-parent-provisioning' ); ?></th>
	<td>
		<input class="regular-text" name="moodle-url" id="moodle-url" type="text" value="<?php echo esc_attr( get_option( 'tvs-moodle-parent-provisioning-moodle-url' ) ) ; ?>" />
		<p class="description"><?php _e( 'The base URL of your Moodle installation, e.g. <strong>https://example.com/moodle</strong>', 'tvs-moodle-parent-provisioning' ); ?></p>
	</td>
</tr>
<tr>
	<th scope="row"><?php _e( 'Moodle Base Path', 'tvs-moodle-parent-provisioning' ); ?></th>
	<td>
		<input class="regular-text" name="moodle-path" id="moodle-path" type="text" value="<?php echo esc_attr( get_option( 'tvs-moodle-parent-provisioning-moodle-path' ) ) ; ?>" />
		<p class="description"><?php _e( 'The base path on the filesystem where the Moodle PHP files are stored, e.g. <strong>/var/www/moodle</strong>', 'tvs-moodle-parent-provisioning' ); ?></p>
	</td>
</tr>
<tr>
	<th scope="row"><?php _e( 'Moodle Database Host', 'tvs-moodle-parent-provisioning' ); ?></th>
	<td>
		<input class="regular-text" name="moodle-dbhost" id="moodle-dbhost" type="text" value="<?php echo esc_attr( get_option( 'tvs-moodle-parent-provisioning-moodle-dbhost' ) ) ; ?>" />
		<p class="description"><?php _e( 'The hostname or IP address of your Moodle database, e.g. <strong>localhost</strong>', 'tvs-moodle-parent-provisioning' ); ?></p>
	</td>
</tr>
<tr>
	<th scope="row"><?php _e( 'Moodle Database Username', 'tvs-moodle-parent-provisioning' ); ?></th>
	<td>
		<input class="regular-text" name="moodle-dbuser" id="moodle-dbuser" type="text" value="<?php echo esc_attr( get_option( 'tvs-moodle-parent-provisioning-moodle-dbuser' ) ) ; ?>" />
		<p class="description"><?php _e( 'The name of the database user with permission to connect to the Moodle database.', 'tvs-moodle-parent-provisioning' ); ?></p>
	</td>
</tr>
<tr>
	<th scope="row"><?php _e( 'Moodle Database Password', 'tvs-moodle-parent-provisioning' ); ?></th>
	<td>
		<input class="regular-text" name="moodle-dbpass" id="moodle-dbpass" type="password" value="<?php if( get_option( 'tvs-moodle-parent-provisioning-moodle-dbpass' ) ): echo 'dummypass'; endif; ?>" />
		<p class="description"><?php _e( 'The database password with which to connect to the Moodle database.', 'tvs-moodle-parent-provisioning' ); ?></p>
	</td>
</tr>
<tr>
	<th scope="row"><?php _e( 'Moodle Database Name', 'tvs-moodle-parent-provisioning' ); ?></th>
	<td>
		<input class="regular-text" name="moodle-db" id="moodle-db" type="text" value="<?php echo esc_attr( get_option( 'tvs-moodle-parent-provisioning-moodle-db' ) ) ; ?>" />
		<p class="description"><?php _e( 'The name of the Moodle database on the database server.', 'tvs-moodle-parent-provisioning' ); ?></p>
	</td>
</tr>
<tr>
	<th scope="row"><?php _e( 'Moodle Database Prefix', 'tvs-moodle-parent-provisioning' ); ?></th>
	<td>
		<input class="regular-text" name="moodle-dbprefix" id="moodle-dbprefix" type="text" value="<?php echo esc_attr( get_option( 'tvs-moodle-parent-provisioning-moodle-dbprefix' ) ) ; ?>" />
		<p class="description"><?php _e( 'The database prefix for the Moodle tables, e.g. <strong>mdl_</strong>', 'tvs-moodle-parent-provisioning' ); ?></p>
	</td>
</tr>
<tr>
	<th scope="row"><?php _e( 'Path to Sudo Executable', 'tvs-moodle-parent-provisioning' ); ?></th>
	<td>
		<input class="regular-text" name="moodle-sudo-path" id="moodle-sudo-path" type="text" value="<?php echo esc_attr( get_option( 'tvs-moodle-parent-provisioning-moodle-sudo-path' ) ) ; ?>" />
		<p class="description"><?php _e( 'The path to the <code>sudo</code> executable. <code>sudo</code> is used to invoke various Moodle scheduled tasks as the Moodle Unix user account. On Linux, usually <code>/usr/bin/sudo</code>.', 'tvs-moodle-parent-provisioning' ); ?></p>
	</td>

</tr>
<tr>
	<th scope="row"><?php _e( 'Sudo User Account', 'tvs-moodle-parent-provisioning' ); ?></th>
	<td>
		<input class="regular-text" name="moodle-sudo-account" id="moodle-sudo-account" type="text" value="<?php echo esc_attr( get_option( 'tvs-moodle-parent-provisioning-moodle-sudo-account' ) ) ; ?>" />
		<p class="description"><?php _e( 'The user account on the operating system that will be used to invoke various Moodle scheduled tasks to trigger the creation of new user accounts. The user configured to run the provisioning cron job should have the right to use <code>sudo</code> to run the tasks as this user.', 'tvs-moodle-parent-provisioning' ); ?></p>
	</td>
</tr>
<tr>
	<th scope="row"><?php _e( 'Path to PHP Executable', 'tvs-moodle-parent-provisioning' ); ?></th>
	<td>
		<input class="regular-text" name="php-path" id="php-path" type="text" value="<?php echo esc_attr( get_option( 'tvs-moodle-parent-provisioning-php-path' ) ) ; ?>" />
		<p class="description"><?php _e( 'The full file path to the PHP executable to use to run the Moodle scheduled tasks from the command line.', 'tvs-moodle-parent-provisioning' ); ?></p>
	</td>
</tr>
<tr>
	<th scope="row"><?php _e( 'Provisioning Log File Path', 'tvs-moodle-parent-provisioning' ); ?></th>
	<td>
		<input class="regular-text" name="log-file-path" id="log-file-path" type="text" value="<?php echo esc_attr( get_option( 'tvs-moodle-parent-provisioning-log-file-path' ) ) ; ?>" />
		<p class="description"><?php _e( 'Path to a log file that the provisioning process will write to.', 'tvs-moodle-parent-provisioning' ); ?></p>
	</td>
</tr>
<tr>
	<th scope="row"><?php _e( 'Provisioning Logging Level', 'tvs-moodle-parent-provisioning' ); ?></th>
	<td>
<!--		<input class="regular-text" name="log-level" id="log-file-path" type="text" value="<?php echo esc_attr( get_option( 'tvs-moodle-parent-provisioning-log-file-path' ) ) ; ?>" />-->

		<?php $log_level = get_option( 'tvs-moodle-parent-provisioning-log-level'); ?>

		<select name="log-level" id="log-level">
			<option value="debug"<?php if ( 'debug' == $log_level ) : ?> selected="selected"<?php endif; ?>><?php _e( 'Debugging &mdash; highest level of detail', 'tvs-moodle-parent-provisioning' ); ?></option>
			<option value="info"<?php if ( 'info' == $log_level ) : ?> selected="selected"<?php endif; ?>><?php _e( 'Information &mdash; successful actions', 'tvs-moodle-parent-provisioning' ); ?></option>
		</select>

		<p class="description"><?php _e( 'The <strong>maximum</strong> level of detail that should be written to logs. Warnings, errors and critical log entries are always written to the log and sent in status emails.', 'tvs-moodle-parent-provisioning' ); ?></p>
	</td>
</tr>
<tr>
	<th scope="row" colspan="2">
		<h3><?php _e( 'Moodle Roles and Contexts', 'tvs-moodle-parent-provisioning' ); ?></h3>
		<p class="description"><?php _e( 'These settings determine which roles are granted to newly provisioned users and the contexts in which those roles are granted.', 'tvs-moodle-parent-provisioning' ); ?></p>
	</th>
</tr>
<tr>
	<th scope="row"><?php _e( 'Parent Role', 'tvs-moodle-parent-provisioning' ); ?></th>
	<td>
		<input class="regular-text" name="moodle-parent-role" id="moodle-parent-role" type="text" value="<?php echo esc_attr( get_option( 'tvs-moodle-parent-provisioning-moodle-parent-role' ) ) ; ?>" />
		<p class="description"><?php _e( 'The numeric role ID that represents the <strong>parent</strong> role. Newly created users will be assigned this role in the context of the connected pupil users and in the contexts below. Determine the role ID by viewing the role in Moodle&rsquo;s <strong>Site administration</strong> &gt; <strong>Users</strong> &gt; <strong>Permissions</strong> &gt; <strong>Define roles</strong>; look for the <code>roleid</code> in the URL.', 'tvs-moodle-parent-provisioning' ); ?></p>
	</td>
</tr>
<tr>
	<th scope="row"><?php _e( 'Contexts to Add Role', 'tvs-moodle-parent-provisioning' ); ?></th>
	<td>
		<textarea id="contexts-to-add-role" name="contexts-to-add-role" rows="5" cols="15"><?php echo esc_html( get_option( 'tvs-moodle-parent-provisioning-contexts-to-add-role' ) ); ?></textarea>
		<p class="description"><?php _e( 'Provide numeric Moodle context IDs, one per line, in which you want to set the Parent role for newly provisioned users. You can determine context IDs by going to the target category or area within Moodle, clicking <strong>Assign roles</strong> under <strong>Administration</strong>; look for the <code>contextid</code> in the URL.', 'tvs-moodle-parent-provisioning' ); ?></p>
	</td>
</tr>
<tr>
	<th scope="row"><?php _e( 'Role Modifier User ID', 'tvs-moodle-parent-provisioning' ); ?></th>
	<td>
		<input class="regular-text" name="moodle-modifier-id" id="moodle-modifier-id" type="text" value="<?php echo esc_attr( get_option( 'tvs-moodle-parent-provisioning-moodle-modifier-id' ) ) ; ?>" />
		<p class="description"><?php _e( 'This Moodle user ID will be considered to have been the &lsquo;modifier&rsquo; of the role assignments for audit purposes. Normally this can be left to the numeric ID of a generic admin user, e.g. <strong>2</strong>.', 'tvs-moodle-parent-provisioning' ); ?></p>
	</td>
</tr>
<tr>
	<th scope="row"><?php _e( 'Notes', 'tvs-moodle-parent-provisioning' ); ?></th>
	<td>
		<textarea id="contexts-notes" name="contexts-notes" rows="5" cols="120"><?php echo esc_html( stripslashes( get_option( 'tvs-moodle-parent-provisioning-contexts-notes' ) ) ); ?></textarea>
		<p class="description"><?php _e( 'This field has no effect and can be used to store explanatory notes about the contexts and roles above.', 'tvs-moodle-parent-provisioning' ); ?></p>
	</td>
</tr>

<tr>
	<th scope="row" colspan="2">
		<h3><?php _e( 'User Matching', 'tvs-moodle-parent-provisioning' ); ?></h3>
		<p class="description"><?php _e( 'These settings determine how to handle the matching of Moodle user accounts when a new parent account is being connected to any associated pupil accounts.', 'tvs-moodle-parent-provisioning' ); ?></p>
	</th>

<tr>

<tr>
	<th scope="row"><?php _e( 'Match by Fields', 'tvs-moodle-parent-provisioning' ); ?></th>
	<td>
		<select id="match-by-fields" name="match-by-fields">
			<option value="firstname-surname-departmentnumber"<?php TVS_Parent_Moodle_Provisioning::print_selected_attribute( 'match-by-fields', 'firstname-surname-departmentnumber' ); ?>>
				<?php _e( 'First Name, Surname &amp; Department Number (Tutor Group)', 'tvs-moodle-parent-provisioning' ); ?>
			</option> 
			<option value="firstname-surname-only"<?php TVS_Parent_Moodle_Provisioning::print_selected_attribute( 'match-by-fields', 'firstname-surname-only' ); ?>>
				<?php _e( 'First Name &amp; Surname only', 'tvs-moodle-parent-provisioning' ); ?>
			</option> 

		</select>
		<p class="description"><?php _e( 'Matching on <em>First Name, Surname &amp; Department Number</em> requires that your directory is accurately populated with Tutor Group information. If you choose to match on <em>First Name &amp; Surname only</em>, this is not required, but there is a greater risk of a name collision when trying to provision accounts. In that case, the provisioner will refuse to connect parents to pupils and manual intervention will be required.', 'tvs-moodle-parent-provisioning' ); ?></p>
	</td>
</tr>

<tr>
	<th scope="row"><?php _e( 'Moodle Name Format', 'tvs-moodle-parent-provisioning' ); ?></th>
	<td>
		<select id="moodle-name-format" name="moodle-name-format">
			<option value="forename-only"<?php TVS_Parent_Moodle_Provisioning::print_selected_attribute( 'moodle-name-format', 'forename-only' ); ?>>
				<?php _e( 'Forename contains forename only', 'tvs-moodle-parent-provisioning' ); ?>
			</option>
			<option value="forename-contains-title"<?php TVS_Parent_Moodle_Provisioning::print_selected_attribute( 'moodle-name-format', 'forename-contains-title' ); ?>>
				<?php _e( 'Forename contains title', 'tvs-moodle-parent-provisioning' ); ?>
			</option>

		</select>
		<p class="description"><?php _e( 'Determines whether the Moodle user is created with a forename that includes the Parent Title ("Mr Demonstration", "Parent") or just the forename ("Demonstration Parent").', 'tvs-moodle-parent-provisioning' ); ?></p>
	</td>
</tr>

<tr>
	<th scope="row" colspan="2">
		<h3><?php _e( 'Email Configuration', 'tvs-moodle-parent-provisioning' ); ?></h3>
	</th>
</tr>
<tr>
	<th scope="row"><?php _e( 'Email SMTP Server', 'tvs-moodle-parent-provisioning' ); ?></th>
	<td>
		<input class="regular-text" name="smtp-server" id="smtp-server" type="text" value="<?php echo esc_attr( get_option( 'tvs-moodle-parent-provisioning-smtp-server' ) ) ; ?>" />
		<p class="description"><?php _e( 'These SMTP server credentials will be used to send the templated emails below.', 'tvs-moodle-parent-provisioning' ); ?></p>
	</td>
</tr>
<tr>
	<th scope="row"><?php _e( 'Email SMTP Username', 'tvs-moodle-parent-provisioning' ); ?></th>
	<td>
		<input class="regular-text" name="smtp-username" id="smtp-username" type="email" value="<?php echo esc_attr( get_option( 'tvs-moodle-parent-provisioning-smtp-username' ) ) ; ?>" />
	</td>
</tr>
<tr>
	<th scope="row"><?php _e( 'Email SMTP Password', 'tvs-moodle-parent-provisioning' ); ?></th>
	<td>
		<input class="regular-text" name="smtp-password" id="smtp-password" type="password" value="<?php if( get_option( 'tvs-moodle-parent-provisioning-smtp-password' ) ): echo 'dummypass'; endif; ?>" />
	</td>
</tr>
<tr>
	<th scope="row"><?php _e( 'Provisioning Email Recipients', 'tvs-moodle-parent-provisioning' ); ?></th>
	<td>
		<textarea id="provisioning-email-recipients" name="provisioning-email-recipients" rows="5" cols="50"><?php echo esc_html( get_option( 'tvs-moodle-parent-provisioning-provisioning-email-recipients' ) ); ?></textarea>
		<p class="description"><?php _e( 'Enter email addresses, one per line, that should receive email messages relating to the provisioning process, such as errors and summaries.', 'tvs-moodle-parent-provisioning' ); ?></p>
	</td>
</tr>

	<?php TVS_Parent_Moodle_Provisioning::add_settings_for_email_template( 'forgotten-password', __( 'Forgotten Password', 'tvs-moodle-parent-provisioning' ) ); ?>

	<?php TVS_Parent_Moodle_Provisioning::add_settings_for_email_template( 'details-not-on-file', __( 'Details Not on File', 'tvs-moodle-parent-provisioning' ) ); ?>
	<?php TVS_Parent_Moodle_Provisioning::add_settings_for_email_template( 'generic-fixed', __( 'Generic Fixed', 'tvs-moodle-parent-provisioning' ) ); ?>

</tbody>
</table>

<p class="submit">
	<input id="submit" class="button button-primary" name="submit" value="<?php _e( 'Save Changes' ); ?>" type="submit" />
</p>

</form>
