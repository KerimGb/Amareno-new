/**
 * UpSolution Element: Post List
 */
;( function( $ ) {
	"use strict";

	const DELETE_FILTER = null;
	const PAGINATION_PATTERN = /\/page\/?([0-9]{1,})\/?$/;

	/**
	 * @type {String} The original URL to return after closing the popup.
	 */
	var _originalURL;

	/**
	 * @param {Node} container.
	 */
	function usPostList( container ) {
		const self = this;

		// Private "variables"
		self.data = {
			paged: 1,
			max_num_pages: 1,
			pagination: 'none',
			ajaxUrl: $us.ajaxUrl,
			ajaxData: {
				us_ajax_list_pagination: 1,
			},
			facetedFilter: {},
		};
		self.xhr; // XMLHttpRequests instance

		// Elements
		self.$container = $( container );
		self.$list = $( '.w-grid-list', container );
		self.$loadmore = $( '.g-loadmore', container );
		self.$pagination = $( 'nav.pagination', container );
		self.$none = self.$container.next( '.w-grid-none' );

		// Gets element settings
		const $elmSettings = $( '.w-grid-list-json:first', container );
		if ( $elmSettings.is( '[onclick]' ) ) {
			$.extend( self.data, $elmSettings[0].onclick() || {} );
		}
		$elmSettings.remove();

		self.paginationType = $ush.toString( self.data.pagination );

		// Bondable events
		self._events = {
			addNextPage: self._addNextPage.bind( self ),
			closePostInPopup: self.closePostInPopup.bind( self ),
			loadPostInPopup: self._loadPostInPopup.bind( self ),
			navigationInPopup: self._navigationInPopup.bind( self ),
			openPostInPopup: self._openPostInPopup.bind( self ),

			usListOrder: self._usListOrder.bind( self ),
			usListSearch: self._usListSearch.bind( self ),
			usListFilter: self._usListFilter.bind( self ),
		};

		// Load posts on button click or page scroll;
		if ( self.paginationType === 'load_on_btn' ) {
			self.$loadmore.on( 'mousedown', 'button', self._events.addNextPage );

		} else if ( self.paginationType === 'load_on_scroll' ) {
			$us.waypoints.add( self.$loadmore, /* offset */'-70%', self._events.addNextPage );
		}

		// Events
		self.$container
			.add( self.$none )
			.on( 'usListSearch', self._events.usListSearch )
			.on( 'usListOrder', self._events.usListOrder )
			.on( 'usListFilter', self._events.usListFilter );

		// Open posts in popup
		if ( self.$container.hasClass( 'open_items_in_popup' ) ) {

			// Elements
			self.$popup = $( '.l-popup', container );
			self.$popupBox = $( '.l-popup-box', self.$popup );
			self.$popupPreloader = $( '.g-preloader', self.$popup );
			self.$popupFrame = $( '.l-popup-box-content-frame', self.$popup );
			self.$popupToPrev = $( '.l-popup-arrow.to_prev', self.$popup );
			self.$popupToNext = $( '.l-popup-arrow.to_next', self.$popup );

			$us.$body.append( self.$popup );

			// Events
			self.$list
				.on( 'click', '.w-grid-item:not(.custom-link) .w-grid-item-anchor', self._events.openPostInPopup );
			self.$popupFrame
				.on( 'load', self._events.loadPostInPopup );
			self.$popup
				.on( 'click', '.l-popup-arrow', self._events.navigationInPopup )
				.on( 'click', '.l-popup-closer, .l-popup-box', self._events.closePostInPopup );
		}
	};

	// Post List API
	$.extend( usPostList.prototype, {

		/**
		 * Sets the search string from "List Search".
		 *
		 * @event handler
		 * @param {Event} e The Event interface represents an event which takes place in the DOM.
		 * @param {String} name
		 * @param {String} value The search text.
		 */
		_usListSearch: function( e, name, value ) {
			this.applyFilter( name, value );
		},

		/**
		 * Sets orderby from "List Order".
		 *
		 * @event handler
		 * @param {Event} e The Event interface represents an event which takes place in the DOM.
		 * @param {String} name
		 * @param {String} value The search text.
		 */
		_usListOrder: function( e, name, value ) {
			this.applyFilter( name, value );
		},

		/**
		 * Sets values from "List Filter".
		 *
		 * @event handler
		 * @param {Event} e The Event interface represents an event which takes place in the DOM.
		 * @param {{}} values
		 */
		_usListFilter: function( e, values ) {
			const self = this;
			$.each( values, self.applyFilter.bind( self ) );
		},

		/**
		 * Adds next page.
		 *
		 * @event handler
		 */
		_addNextPage: function() {
			const self = this;
			if ( $ush.isUndefined( self.xhr ) && ! self.$none.is( ':visible' ) ) {
				self.addItems();
			}
		},

		/**
		 * Apply param to "Post/Product List".
		 *
		 * @param {String} name
		 * @param {String} value
		 */
		applyFilter: function( name, value ) {
			const self = this;
			if ( $ush.toString( value ) == '{}' ) {
				value = DELETE_FILTER;
			}

			// only save
			if ( name === 'list_filters' ) {
				self.data.ajaxData[ name ] = value;
				return;
			}

			// Reset pagination
			const pathname = location.pathname;
			if ( PAGINATION_PATTERN.test( pathname ) ) {
				history.pushState( {}, '', location.href.replace( pathname, pathname.replace( PAGINATION_PATTERN, '' ) + '/' ) );
			}
			self.data.paged = 0;

			if ( self.$container.hasClass( 'for_current_wp_query' ) ) {
				self.data.ajaxUrl = $ush
					.urlManager( self.data.ajaxUrl )
					.set( name, value )
					.toString();

			} else if ( value === DELETE_FILTER ) {
				delete self.data.ajaxData[ name ];

			} else {
				self.data.ajaxData[ name ] = value;
			}

			if ( ! $ush.isUndefined( self.xhr ) ) {
				self.xhr.abort();
			}
			self.addItems( /* filtersChanged */true );
		},

		/**
		 * Scrolls to the beginning of the list.
		 */
		scrollToList: function() {
			const self = this;

			if ( self.data.paged > 1 ) {
				return;
			}

			const listPos = $ush.parseInt( self.$container.offset().top );

			if ( ! listPos ) {
				return;
			}

			const scrollTop = $us.$window.scrollTop();

			if (
				! $ush.isNodeInViewport( self.$container[0] )
				|| listPos >= ( scrollTop + window.innerHeight )
				|| scrollTop >= listPos
			) {
				$us.$htmlBody
					.stop( true, false )
					.animate( { scrollTop: ( listPos - $us.header.getInitHeight() ) }, 500 );
			}
		},

		/**
		 * Adds items to element.
		 *
		 * @param {Boolean} applyFilter
		 */
		addItems: $ush.debounce( function( applyFilter ) {
			const self = this;

			self.data.paged += 1;
			if ( ! applyFilter && self.data.paged > self.data.max_num_pages ) {
				return;
			}

			if ( applyFilter ) {
				self.$container.addClass( 'filtering' );

				// Show spinner for filtering action only if set in options
				if ( self.$container.hasClass( 'preload_style_spinner' ) ) {
					self.$loadmore.removeClass( 'hidden' ).addClass( 'loading' );
				}

				// Always show spinner for pagination action
			} else {
				self.$loadmore.removeClass( 'hidden' ).addClass( 'loading' );
			}

			self.$container.removeClass( 'hidden' );
			self.$pagination.addClass( 'hidden' );

			// Get request link and data
			var ajaxUrl = $ush.toString( self.data.ajaxUrl ),
				ajaxData = $ush.clone( self.data.ajaxData ),
				numPage = $ush.rawurlencode( '{num_page}' );

			if ( ajaxUrl.includes( numPage ) ) {
				ajaxUrl = ajaxUrl.replace( numPage, self.data.paged );

			} else if ( ajaxData.template_vars ) {
				ajaxData.template_vars = JSON.stringify( ajaxData.template_vars ); // convert for `us_get_HTTP_POST_json()`
				ajaxData.paged = self.data.paged;
			}

			self.xhr = $.ajax( {
				type: 'post',
				url: ajaxUrl,
				dataType: 'html',
				cache: false,
				data: ajaxData,
				success: function( html ) {

					// Remove previous items when filtered
					if ( applyFilter ) {
						self.$list.html('');
						self.$none.addClass( 'hidden' );
					}

					// Reload element settings
					var $listJson = $( '.w-grid-list-json:first', html );
					if ( $listJson.is( '[onclick]' ) ) {
						$.extend( true, self.data, $listJson[0].onclick() || {} );
					}

					var $items = $( '.w-grid-list:first > *', html );

					// List items loaded
					$ush.timeout( () => {
						$us.$document.trigger( 'usPostList.itemsLoaded', [ $items, applyFilter ] );
					}, 50 );

					// Case when there are no results
					if ( ! $items.length ) {
						if ( ! self.$none.length ) {
							self.$none = $( '.w-grid-none:first', html );
							if ( ! self.$none.length ) {
								self.$none = $( html ).filter( '.w-grid-none:first' );
							}
							self.$container.after( self.$none );
						}
						self.$container.removeClass( 'filtering' );
						self.$loadmore.addClass( 'hidden' );
						self.$pagination.addClass( 'hidden' );
						self.$none.removeClass( 'hidden' );
						return
					}

					// Output of results
					if ( self.$container.hasClass( 'type_masonry' ) ) {
						self.$list
							.isotope( 'insert', $items )
							.isotope( 'reloadItems' );
					} else {
						self.$list.append( $items );
					}

					// Init animation handler for new items
					if ( window.USAnimate && self.$container.hasClass( 'with_css_animation' ) ) {
						new USAnimate( self.$list );
						$us.$window.trigger( 'scroll.waypoints' );
					}

					// Case with numbered pagination
					if ( self.paginationType == 'numbered' ) {
						const $pagination = $( 'nav.pagination', html );
						if ( $pagination.length && ! self.$pagination.length ) {
							self.$list.after( $pagination.prop( 'outerHTML' ) );
							self.$pagination = self.$list.next( 'nav.pagination' );
						}
						if ( self.$pagination.length && $pagination.length ) {
							self.$pagination.html( $pagination.html() ).removeClass( 'hidden' );

						} else {
							self.$pagination.addClass( 'hidden' );
						}
					}

					// Case when the last page is loaded
					if ( self.data.paged >= self.data.max_num_pages ) {
						self.$loadmore.addClass( 'hidden' );
						self.$none.addClass( 'hidden' );

					} else {
						self.$loadmore.removeClass( 'hidden' );
					}

					// Adds point to load the next page
					if ( self.paginationType == 'load_on_scroll' ) {
						$us.waypoints.add( self.$loadmore, /* offset */'-70%', self._events.addNextPage );
					}

					$us.$canvas.trigger( 'contentChange' );
				},
				complete: function() {
					self.$container.removeClass( 'filtering' );
					self.$loadmore.removeClass( 'loading' );
					if ( self.paginationType == 'load_on_scroll' ) {
						self.$loadmore.addClass( 'hidden' );
					}
					delete self.xhr;

					// Scroll to top of list
					self.scrollToList();
				}
			} );

		}, 1 ),

	} );

	// Functionality for popup window
	$.extend( usPostList.prototype, {

		/**
		 * Open post in popup.
		 *
		 * @event handler
		 * @param {Event} e The Event interface represents an event which takes place in the DOM.
		 */
		_openPostInPopup: function( e ) {
			const self = this;

			// If scripts are disabled on a given screen width, then exit
			if ( $us.$window.width() <= $us.canvasOptions.disableEffectsWidth ) {
				return;
			}

			e.stopPropagation();
			e.preventDefault();

			// Remember original page URL
			_originalURL = location.href;

			// Set post by index in the list
			self.setPostInPopup( $( e.target ).closest( '.w-grid-item' ).index() );

			// Show popup
			$us.$html.addClass( 'usoverlay_fixed' );
			self.$popup.addClass( 'active' );
			$ush.timeout( () => {
				self.$popupBox.addClass( 'show' );
			}, 25 );
		},

		/**
		 * Load post in popup.
		 *
		 * @event handler
		 */
		_loadPostInPopup: function() {
			const self = this;

			// Closing the post popup using escape
			function checkEscape( e ) {
				if ( $ush.toLowerCase( e.key ) === 'escape' && self.$popup.hasClass( 'active' ) ) {
					self.closePostInPopup();
				}
			}
			self.$container.on( 'keyup', checkEscape );

			$( 'body', self.$popupFrame.contents() )
				.one( 'keyup.usCloseLightbox', checkEscape );
		},

		/**
		 * Navigation in the post popup.
		 *
		 * @event handler
		 * @param {Event} e The Event interface represents an event which takes place in the DOM.
		 */
		_navigationInPopup: function( e ) {
			this.setPostInPopup( $( e.target ).data( 'index' ) );
		},

		/**
		 * Sets post by index in the list.
		 *
		 * @param {String} url The new value.
		 */
		setPostInPopup: function( index ) {
			const self = this;

			// Get current node and url
			var $node = $( '> *:eq(' + $ush.parseInt( index ) + ')', self.$list ),
				url = $ush.toString( $( '[href]:first', $node ).attr( 'href' ) );

			// If there is no href, then exit
			if ( ! url ) {
				console.error( 'No url to loaded post' );
				return;
			}

			// Gen prev / next node
			var $prev = $node.prev( ':not(.custom-link)' ),
				$next = $node.next( ':not(.custom-link)' );

			// Pagination controls switch
			self.$popupToPrev
				.data( 'index', $prev.index() )
				.attr( 'title', $( '.post_title', $prev ).text() )
				.toggleClass( 'hidden', ! $prev.length );
			self.$popupToNext
				.data( 'index', $next.index() )
				.attr( 'title', $( '.post_title', $next ).text() )
				.toggleClass( 'hidden', ! $next.length );

			// Load post by its index
			self.$popupPreloader.show();
			self.$popupFrame
				.attr( 'src', url + ( url.indexOf( '?' ) > -1 ? '&' : '?' ) + 'us_iframe=1' );

			// Set post link in URL
			history.replaceState( /* state */null, /* unused */null, url );
		},

		/**
		 * Close post in popup.
		 *
		 * @event handler
		 */
		closePostInPopup: function() {
			const self = this;
			self.$popupBox
				.removeClass( 'show' )
				.one( 'transitionend webkitTransitionEnd oTransitionEnd MSTransitionEnd', () => {
					self.$popup.removeClass( 'active' );
					self.$popupFrame.attr( 'src', 'about:blank' );
					self.$popupToPrev.addClass( 'hidden' );
					self.$popupToNext.addClass( 'hidden' );
					self.$popupPreloader.show();
					$us.$html.removeClass( 'usoverlay_fixed' );
				} );

			// Restore original URL
			if ( _originalURL ) {
				history.replaceState( /* state */null, /* unused */null, _originalURL );
			}
		}
	} );

	$.fn.usPostList = function() {
		return this.each( function() {
			$( this ).data( 'usPostList', new usPostList( this ) );
		} );
	};

	$( () => {
		$( '.w-grid.us_post_list, .w-grid.us_product_list' ).usPostList();
	} );

} )( jQuery );
