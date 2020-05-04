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
	<h2><?php _e( 'Parent Moodle Provisioning &mdash; Logs', 'tvs-moodle-parent-provisioning' ); ?></h2>


<?php
$path = get_option( 'tvs-moodle-parent-provisioning-log-file-path' );

$prefix_filter = '[' . date( 'Y-m-d' );
if ( array_key_exists( 'prefix_filter', $_GET ) && ! empty( $_GET['prefix_filter'] ) ) {
	if ( '_all' == $_GET['prefix_filter'] ) {
		$prefix_filter = '';
	}
	else if ( preg_match( '/^\[\d{4}-\d{2}-\d{2}/', $_GET['prefix_filter'] ) )  {
		$prefix_filter = $_GET['prefix_filter'];
	}
}

?>
	<form name="prefix_filter_form" method="GET" action="">
	<input type="hidden" name="page" value="tvs_parent_moodle_provisioning_logs" />
	<p class="search-box">
		<input id="search-input" type="search" name="prefix_filter" value="<?php
			if ( isset( $prefix_filter ) ) {
				echo esc_attr( $prefix_filter );
			}
		?>" />
		<input id="search-submit" type="submit" class="button" value="<?php _e( 'Filter', 'tvs-moodle-parent-provisioning' ); ?>" />
	</p>
</form>


<code>
<?php

if ( parse_url( $path, PHP_URL_SCHEME ) ) {
	throw new InvalidArgumentException( __( 'log-file-path option must be a local path.', 'tvs-moodle-parent-provisioning' ) );
}

$file = fopen( $path, 'r' );

while ( ( $line = fgets( $file ) ) !== false ) {
	if ( strpos( $line, $prefix_filter ) !== 0 ) {
		continue;
	}
	echo esc_html( $line );
	?><br><?php
}

fclose( $file );

?>
	</code>
