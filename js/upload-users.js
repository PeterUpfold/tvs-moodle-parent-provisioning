/* Copyright (C) 2016-2017 Test Valley School.


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
jQuery(document).ready(function($) {

	var blank_data = [
		[ "Parent Title", "Parent First Name", "Parent Surname", "Parent Email Address", "Child 1 First Name", "Child 1 Surname", "Child 1 Tutor Group", "Child 2 First Name", "Child 2 Surname", "Child 2 Tutor Group", "Child 3 First Name", "Child 3 Surname", "Child 3 Tutor Group" ],
		[ "", "", "", "", "", "", "", "", "", "", "", "", "" ],
		[ "", "", "", "", "", "", "", "", "", "", "", "", "" ],
		[ "", "", "", "", "", "", "", "", "", "", "", "", "" ],
		[ "", "", "", "", "", "", "", "", "", "", "", "", "" ],
		[ "", "", "", "", "", "", "", "", "", "", "", "", "" ],
		[ "", "", "", "", "", "", "", "", "", "", "", "", "" ],
		[ "", "", "", "", "", "", "", "", "", "", "", "", "" ],
		[ "", "", "", "", "", "", "", "", "", "", "", "", "" ],
		[ "", "", "", "", "", "", "", "", "", "", "", "", "" ],
		[ "", "", "", "", "", "", "", "", "", "", "", "", "" ],
		[ "", "", "", "", "", "", "", "", "", "", "", "", "" ],
		[ "", "", "", "", "", "", "", "", "", "", "", "", "" ],
		[ "", "", "", "", "", "", "", "", "", "", "", "", "" ],
		[ "", "", "", "", "", "", "", "", "", "", "", "", "" ],
		[ "", "", "", "", "", "", "", "", "", "", "", "", "" ],
		[ "", "", "", "", "", "", "", "", "", "", "", "", "" ],
		[ "", "", "", "", "", "", "", "", "", "", "", "", "" ],
		[ "", "", "", "", "", "", "", "", "", "", "", "", "" ],
		[ "", "", "", "", "", "", "", "", "", "", "", "", "" ],
		[ "", "", "", "", "", "", "", "", "", "", "", "", "" ],
		[ "", "", "", "", "", "", "", "", "", "", "", "", "" ],
		[ "", "", "", "", "", "", "", "", "", "", "", "", "" ],
		[ "", "", "", "", "", "", "", "", "", "", "", "", "" ],
		[ "", "", "", "", "", "", "", "", "", "", "", "", "" ],
		[ "", "", "", "", "", "", "", "", "", "", "", "", "" ],
		[ "", "", "", "", "", "", "", "", "", "", "", "", "" ],
		[ "", "", "", "", "", "", "", "", "", "", "", "", "" ],
		[ "", "", "", "", "", "", "", "", "", "", "", "", "" ],
		[ "", "", "", "", "", "", "", "", "", "", "", "", "" ],
		[ "", "", "", "", "", "", "", "", "", "", "", "", "" ],
		[ "", "", "", "", "", "", "", "", "", "", "", "", "" ],
		[ "", "", "", "", "", "", "", "", "", "", "", "", "" ],
		[ "", "", "", "", "", "", "", "", "", "", "", "", "" ],
		[ "", "", "", "", "", "", "", "", "", "", "", "", "" ],
		[ "", "", "", "", "", "", "", "", "", "", "", "", "" ],
		[ "", "", "", "", "", "", "", "", "", "", "", "", "" ],
		[ "", "", "", "", "", "", "", "", "", "", "", "", "" ],
		[ "", "", "", "", "", "", "", "", "", "", "", "", "" ],
		[ "", "", "", "", "", "", "", "", "", "", "", "", "" ],

	];

	var table = new Handsontable( document.getElementById('upload-users-table'), {
		data: blank_data,
		rowHeaders: true,
		colHeaders: true,
		dropdownMenu: true,
		contextMenu: true,
		/* set top row read only */
		cells: function(row, col, prop) {
			var cellProps = {};

			if (row === 0) {
				cellProps.readOnly = true;
			}

			return cellProps;
		}
	});

	$('#upload-users-button').click(function(e) {
		e.preventDefault();
		//TODO js l10n
		if (confirm('Are you sure you want to approve this batch for provisioning?')) {
			$.post(ajaxurl,
				{ data: table.getData(),
				  action: 'tvs_pmp_upload_users',
				  _ajax_nonce: upload_users_nonce
				},
				function(data, textStatus, jqXHR) {
					var i = 0;
					if (data.information.length > 0) {
						for( i = 0; i < data.information.length; i++) {
							$('#upload-users-information').append(document.createTextNode(data.information[i]));
							$('#upload-users-information').append('<br/>');
						}
						$('#upload-users-information').show('slow');
					}
					if (data.errors.length > 0) {
						for( i = 0; i < data.errors.length; i++) {
							$('#upload-users-errors').append(document.createTextNode(data.errors[i]));
							$('#upload-users-errors').append('<br/>');
						}

						$('#upload-users-errors').show('slow');
					}
					if (data.warnings.length > 0) {
						for( i = 0; i < data.warnings.length; i++) {
							$('#upload-users-warnings').append(document.createTextNode(data.warnings[i]));
							$('#upload-users-warnings').append('<br/>');
						}

						$('#upload-users-warnings').show('slow');
					}

					// scroll to top
					$('html,body').animate({
						scrollTop: $('.wrap').offset().top
					}, 2000);

				},
				'json'
			);
		}
	});
});
