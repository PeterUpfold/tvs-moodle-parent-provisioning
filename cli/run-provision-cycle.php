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
if ( php_sapi_name() != 'cli' ) {
	header('HTTP/1.1 403 Forbidden');
	die('Must be run from CLI.');
}

require( dirname( __FILE__ ) . '/../../../../wp-load.php' );

require_once( dirname( __FILE__ ) . '/../includes/class.tvs-pmp-contact.php' );
require_once( dirname( __FILE__ ) . '/../includes/class.tvs-pmp-mdl-db-helper.php' );


$logger = TVS_PMP_MDL_DB_Helper::create_logger( $local_log_stream );

$helper = new TVS_PMP_MDL_DB_Helper( $logger, TVS_PMP_MDL_DB_Helper::create_dbc( $logger ) );
$helper->provision_all_approved();

// find all 'deleting' status and if any exist, trigger the DB sync
$helper->finish_delete();

