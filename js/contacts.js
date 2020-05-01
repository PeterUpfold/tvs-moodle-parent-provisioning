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
jQuery(document).ready(function($) {

	$('span.contact-mappings > a').click(function(e) {
		e.preventDefault();
		let contactId = $(this).parent().data('contact-id');

		let target = $('span.contact-mapping-list[data-contact-id="' + contactId + '"]');
		let callback = function(response) {
			let list = document.createElement('ul');
			for ( const mapping in response ) {
				let mappingElement = document.createElement('li');
				$(mappingElement).text(response[mapping].username);
				$(list).append(mappingElement);
			}
			$(target).append(list);
		};
		$.fn.getMappings(contactId, callback);

	});
	
	$.fn.getMappings = function(contact, callback) {
		$.ajax( {
			url: wpApiSettings.root + 'testvalleyschool/v1/contact/' + encodeURIComponent(contact) + '/mappings',
			method: 'GET',
			beforeSend: function ( xhr ) {
				xhr.setRequestHeader( 'X-WP-Nonce', wpApiSettings.nonce )
			},
		} ).done( callback 
		);	
	}
});
