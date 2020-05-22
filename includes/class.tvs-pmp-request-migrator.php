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

/**
 * Class: utility class for migrating all requests to the new Contacts
 * format.
 * 
 */
class TVS_PMP_Request_Migrator {

	/**
	 * Migrate all requests to the new Contacts form.
	 */
	public static function migrate_all() {
		global $wpdb;
		require_once( __DIR__ . '/tvs-pmp-contact.php' );
		require_once( __DIR__ . '/tvs-pmp-mdl-db-helper.php' );

		$logger = TVS_PMP_MDL_DB_Helper::create_logger( $local_log_stream );
		$dbc = TVS_PMP_MDL_DB_Helper::create_dbc( $logger );

		$results = $wpdb->get_results(
			"SELECT id, parent_title, parent_fname, parent_sname, child_fname, child_sname, child_tg, parent_email, child2_fname, child2_sname, child2_tg, child3_fname, child3_sname, child3_tg, status, parent_comment, staff_comment, system_comment, date_created, date_updated, date_approved, remote_ip_addr, provisioned_username, provisioned_initialpass, request_type, mis_id, external_mis_id FROM {$wpdb->prefix}tvs_parent_moodle_provisioning WHERE status = 'provisioned' AND mis_id IS NOT NULL AND external_mis_id IS NOT NULL"
		);


		$migrated_contacts = 0;
		foreach( $results as $result ) {
			$migrated_contacts = $migrated_contacts + (int)TVS_PMP_Contact::create_migrated_contact_from_request( $result, $logger, $dbc );
		}

		return $migrated_contacts;
	}	
};
