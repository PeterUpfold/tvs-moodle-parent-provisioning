<?php
/* Copyright (C) 2016-2018 Test Valley School.


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
if ( php_sapi_name() != 'cli' ) {
	header('HTTP/1.1 403 Forbidden');
	die('Must be run from CLI.');
}

require( dirname( __FILE__ ) . '/../../../../wp-load.php' );

require_once( dirname( __FILE__ ) . '/../includes/class.tvs-pmp-mdl-db-helper.php' );

$logger = TVS_PMP_MDL_DB_Helper::create_logger( $local_log_stream );

$exit_code = NULL;
$helper = new TVS_PMP_MDL_DB_Helper( $logger, TVS_PMP_MDL_DB_Helper::create_dbc( $logger ) );
$logger->info( __( 'Invoking synchronisation of users with external DB table.', 'tvs-moodle-parent-provisioning' ) );
$helper->run_moodle_scheduled_task( '\auth_db\task\sync_users', $exit_code );

$logger->info( sprintf( __( 'Completed auth_db\task\sync_users with exit code %d', 'tvs-moodle-parent-provisioning' ), $exit_code ) );

if ( $exit_code !== 0 ) {
	$logger->warn( sprintf( __( 'auth_db\task\sync_users exited with non-zero exit code %d', 'tvs-moodle-parent-provisioning' ), $exit_code ) );
}

if ( is_int( $exit_code ) ) {
	exit( $exit_code );
}
else {
	$logger->warn( sprintf( __('Exit code %s was not an integer. Unable to determine task success.', 'tvs-moodle-parent-provisioning' ), $exit_code ) );
	exit( 254 );
}
