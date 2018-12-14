TVS Moodle Parent Provisioning
==============================

This plugin supports the provisioning of new parent Moodle accounts and provides an interface within the WordPress
admin dashboard for managing requests for accounts: for example, approving and denying those requests.

It is this plugin that manages the "external database" table -- this is one of the authentication methods Moodle uses 
to attempt to log users in. This plugin handles populating that authentication table, but Moodle internally handles
password management.

## Database

There are two database tables created when you activate this plugin in WordPress.

The `wp_tvs_parent_moodle_provisioning` table holds the *requests* that parents make. This is populated using a [Contact Form 7](https://wordpress.org/plugins/contact-form-7/) form on the WordPress website. A separate plugin hooks `wpcf7_before_send_mail` and, if the form contains an element named `parent_moodle_provisioning`, inserts the form data into this table.

The `wp_tvs_parent_moodle_provisioning_auth` table is the *external database* table that Moodle uses. This is used by Moodle to determine which users can log in. An entry in the auth table indicates the user is allowed to log in. The absence of an entry in the auth table will suspend the user on the Moodle side, but not delete the account completely.

## Approval and Provisioning Process

Once the admin user has selected to **Provision** a user from the Requests interface, it is marked as 'approved -- not provisioned'. An auth table entry is added at this stage, but Moodle will not yet have picked up this change. In this status, it is waiting for a provisioning cycle to run to set up the account fully.

Once the provisioning cycle runs (either scheduled or forced via the interface), the Moodle scheduled task to sync its users table with the auth table is run. An entry in the `mdl_user` table on the Moodle side has then been created by Moodle.

The next step is to connect the new parent account to their pupils' accounts, and give them any other roles that we have configured in Settings. This plugin achieves that by direct manipulation of the Moodle database to add the appropriate role assignments.

Once all of this is complete, the Moodle scheduled task to send out temporary passwords is run. The request is now marked as provisioned.

## Installation

This plugin has some dependencies which you must satisfy using [Composer](https://getcomposer.org) and [NPM](https://www.npmjs.com). Install Composer and run `composer update` in this folder **before attempting to activate the plugin**. Also run `npm install` in this folder.

## Configuration

Once the plugin is activated in WordPress, there are a number of configuration items to set in the **Settings** page in the **Moodle Provisioning** section of the WordPress admin dashboard.

## Licence

This software is licensed under the [GNU General Public License, version 2](https://www.gnu.org/licenses/gpl-2.0.html). Derivatives of WordPress code, such as WordPress plugins, [must be made available under this licence](https://wordpress.org/about/license/).
