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

require_once( dirname( __FILE__ ) . '/../includes/class.tvs-pmp-request.php' );
require_once( dirname( __FILE__ ) . '/../includes/class.tvs-pmp-provisioner.php' );


$set_prefix = 'tvs-moodle-parent-provisioning-';
$provisioner = new TVS_PMP_Provisioner(
	get_option( $set_prefix . 'moodle-dbhost' ), 
	get_option( $set_prefix . 'moodle-dbuser' ),
	get_option( $set_prefix . 'moodle-dbpass' ),
	get_option( $set_prefix . 'moodle-db' ),
	get_option( $set_prefix . 'moodle-dbprefix' ),
	get_option( $set_prefix . 'log-file-path' ),
	get_option( $set_prefix . 'moodle-parent-role' ),
	get_option( $set_prefix . 'moodle-modifier-id' ),
	get_option( $set_prefix . 'moodle-sudo-account' ),
	get_option( $set_prefix . 'php-path' ),
	get_option( $set_prefix . 'moodle-url' ),
	get_option( $set_prefix . 'moodle-path' )
);


$provisioner->provision_all_approved();

