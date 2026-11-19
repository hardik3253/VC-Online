;( function( $ ) {

	'use strict';

	// Global settings access.
	var settings = {
		iconActivate: '<i class="fa fa-toggle-on fa-flip-horizontal" aria-hidden="true"></i>',
		iconDeactivate: '<i class="fa fa-toggle-on" aria-hidden="true"></i>',
		iconInstall: '<i class="fa fa-cloud-download" aria-hidden="true"></i>'
	};

	var HFEAdmin = {

		/**
		 * Start the engine.
		 *
		 * @since 1.3.9
		 */
		_init: function() {

			var ehf_hide_shortcode_field = function() {
				var selected = $('#ehf_template_type').val() || 'none';
				$( '.hfe-options-table' ).removeClass().addClass( 'hfe-options-table widefat hfe-selected-template-type-' + selected );
			}

			var $document = $( document );
		
			$document.on( 'change', '#ehf_template_type', function( e ) {
				ehf_hide_shortcode_field();
			});
		
			ehf_hide_shortcode_field();

			// About us - addons functionality.
			if ( $( '.hfe-admin-addons' ).length ) {
	
				$document.on( 'click', '.hfe-admin-addons .addon-item button', function( event ) {
					event.preventDefault();
		
					if ( $( this ).hasClass( 'disabled' ) ) {
						return false;
					}
		
					HFEAdmin._addons( $( this ) );

				} );
		
			}

			if( "yes" === hfe_admin_data.show_all_hfe ) {
				// Add the "View All" button next to the "Add New" button.
				const addNewButton = document.querySelector('.page-title-action');
				if ( addNewButton ) {
					// Create the new button.
					const customButton = document.createElement('a');
					customButton.href = hfe_admin_data.hfe_edit_url;
					customButton.textContent = hfe_admin_data.view_all_text;
					customButton.className = 'page-title-action';
					customButton.style.marginLeft = '10px';
					addNewButton.insertAdjacentElement('afterend', customButton);
				}
			}
			
		},

		/**
		 * Toggle addon state.
		 */
		 _addons: function( $button ) {

			var $addon = $button.closest( '.addon-item' ),
				plugin = $button.attr( 'data-plugin' ),
				addonType = $button.attr( 'data-type' ),
				addonSlug = $button.attr( 'data-slug' ),
				addonFile = $button.attr( 'data-file' ),
				state,
				cssClass,
				stateText,
				buttonText,
				errorText,
				successText;
	
			if ( $button.hasClass( 'status-go-to-url' ) ) {
	
				// Open url in new tab.
				window.open( $button.attr( 'data-site' ), '_blank' );
				return;
			}
	
			$button.prop( 'disabled', true ).addClass( 'loading' );
			$button.html( '<span class="dashicons dashicons-update hfe-loader"></span>' );
	
			if ( $button.hasClass( 'status-active' ) ) {
	
				// Deactivate.
				state = 'deactivate';
				cssClass = 'status-inactive';
				cssClass += ' button button-secondary';
				stateText = hfe_admin_data.addon_inactive;
				buttonText = hfe_admin_data.addon_activate;
				errorText  = hfe_admin_data.addon_deactivate;
	
			} else if ( $button.hasClass( 'status-inactive' ) ) {
	
				// Activate.
				state = 'activate';
				cssClass = 'status-active';
				cssClass += ' button button-secondary disabled';
				stateText = hfe_admin_data.addon_active;
				buttonText = hfe_admin_data.addon_deactivate;
				buttonText = hfe_admin_data.addon_activated;
				errorText  = hfe_admin_data.addon_activate;
	
			} else if ( $button.hasClass( 'status-download' ) ) {
	
				// Install & Activate.
				state = 'install';
				cssClass = 'status-active';
				cssClass += ' button disabled';
				stateText = hfe_admin_data.addon_active;
				buttonText = hfe_admin_data.addon_activated;
				errorText  = settings.iconInstall;
	
			} else {
				return;
			}
	
			HFEAdmin._set_addon_state( plugin, state, addonType, addonSlug, function( res ) {
	
				if ( res.success ) {
					if ( 'install' === state ) {
						successText = res.msg;
						$button.attr( 'data-plugin', addonFile );
						
						stateText  = hfe_admin_data.addon_inactive;
						buttonText = ( addonType === 'theme' || addonType === 'plugin' ) ? hfe_admin_data.addon_activate : settings.iconActivate + hfe_admin_data.addon_activate;
						cssClass   = ( addonType === 'theme' || addonType === 'plugin' ) ? 'status-inactive button button-secondary' : 'status-inactive';
					} else {
						successText = res.data;
					}
					$addon.find( '.actions' ).append( '<div class="msg success">' + successText + '</div>' );
					$addon.find( 'span.status-label' )
						.removeClass( 'status-active status-inactive status-download' )
						.addClass( cssClass )
						.removeClass( 'button button-primary button-secondary disabled' )
						.text( stateText );
					$button
						.removeClass( 'status-active status-inactive status-download' )
						.removeClass( 'button button-primary button-secondary disabled' )
						.addClass( cssClass ).html( buttonText );
				} else {
					
					if ( 'install' === state && ( addonType === 'theme' || addonType === 'plugin' ) ) {
						$addon.find( '.actions' ).append( '<div class="msg error">' + res.msg + '</div>' );
						$button.addClass( 'status-go-to-url' ).removeClass( 'status-download' );
					} else {
						var error_msg = ( 'object' === typeof res.data ) ? hfe_admin_data.plugin_error : res.data;
						$addon.find( '.actions' ).append( '<div class="msg error">' + error_msg + '</div>' );
					}

					if( 'ultimate-elementor' === addonSlug ) {
						$button.addClass( 'status-go-to-url' );
						$button.html( hfe_admin_data.visit_site );
					} else {
						$button.html( hfe_admin_data.addon_download );
					}
				}
	
				$button.prop( 'disabled', false ).removeClass( 'loading' );
	
				// Automatically clear the messages after 3 seconds.
				setTimeout( function() {	
					$( '.addon-item .msg' ).remove();
				}, 3000 );
	
			} );
		},

		/**
		 * Change plugin/addon state.
		 *
		 * @since 1.6.0
		 *
		 * @param {string}   plugin     Plugin/Theme URL for download.
		 * @param {string}   state      State status activate|deactivate|install.
		 * @param {string}   addonType Plugin/Theme type addon or plugin.
		 * @param {string}   addonSlug Plugin/Theme slug addon or plugin.
		 * @param {Function} callback   Callback for get result from AJAX.
		 */
		 _set_addon_state: function( plugin, state, addonType, addonSlug, callback ) {

			var actions = {
					'activate': 'hfe_activate_addon',
					'install': '',
				},
				action = actions[ state ];

			if ( ! action && 'install' !== state ) {
				return;
			}

			var data_result = {
				success : false,
				msg : hfe_admin_data.subscribe_error,
			};

			if( 'install' === state ) {

				if ( wp.updates.shouldRequestFilesystemCredentials && ! wp.updates.ajaxLocked ) {
					wp.updates.requestFilesystemCredentials();
				}

				if( 'theme' === addonType ) {

					wp.updates.installTheme ( {
						slug: addonSlug,
						success: function() {
							data_result.success = true;
							data_result.msg = hfe_admin_data.theme_installed;
							
						},
						error: function( xhr ) {
							console.log( xhr.errorCode );							
							if ( 'folder_exists' === xhr.errorCode ) {
								data_result.success = true;
								data_result.msg = hfe_admin_data.addon_exists;
							} else {
								data_result.success = false;
								data_result.msg = hfe_admin_data.plugin_error;
							}
						},
					}).always( function () {
						callback( data_result );
					});

				} else if( 'plugin' === addonType ) {
					
					wp.updates.installPlugin ( {
						slug: addonSlug,
						success: function() {
							data_result.success = true;
							data_result.msg = hfe_admin_data.plugin_installed;
						},
						error: function( xhr ) {
							console.log( xhr.errorCode );							
							if ( 'folder_exists' === xhr.errorCode ) {
								data_result.success = true;
								data_result.msg = hfe_admin_data.addon_exists;
							} else {
								data_result.success = false;
								data_result.msg = hfe_admin_data.plugin_error;
							}
						},
					}).always( function () {
						callback( data_result );
					});
				}

			} else if( 'activate' === state )  {

				var data = {
					action: action,
					nonce: hfe_admin_data.nonce,
					plugin: plugin,
					type: addonType,
					slug: addonSlug
				};
		
				$.post( hfe_admin_data.ajax_url, data, function( res ) {
					callback( res );
				} ).fail( function( xhr ) {
					console.log( xhr.responseText );
				} );
			}
		}
	};

	$( document ).ready( function( e ) {
		HFEAdmin._init();
	});

	window.HFEAdmin = HFEAdmin;

} )( jQuery );
