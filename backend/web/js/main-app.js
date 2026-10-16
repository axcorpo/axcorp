/**
 * JavaScript Templating
 *
 * @author John RESIG - http://ejohn.org/
 * @license MIT Licensed
 * @access public
 */
(function (window, document, undefined) {
	this.tpl = {
		compile: function (template, data) {
			var cache = {},
				fn = !/\W/.test(template) ?
					cache[template] = cache[template] ||
						tpl.compile(document.getElementById(template).innerHTML) :
					new Function("obj",
						"var p=[],print=function(){p.push.apply(p,arguments);};" +
						"with(obj){p.push('" +
						template
							.replace(/[\r\t\n]/g, " ")
							.split("<%").join("\t")
							.replace(/((^|%>)[^\t]*)'/g, "$1\r")
							.replace(/\t=(.*?)%>/g, "',$1,'")
							.split("\t").join("');")
							.split("%>").join("p.push('")
							.split("\r").join("\\'") + "');}return p.join('');");
			return data ? fn(data) : fn;
		}
	};
})(window, document);

/**
 * Serializes a form to Object
 *
 * @link https://stackoverflow.com/a/17488875
 */
(function (window, document, undefined) {
	/**
	 * jQuery serializeObject
	 * @requires jQuery
	 * @memberof jQuery.prototype
	 * @access public
	 */
	$.fn.serializeObject = function() {
		var o = {};
		var a = this.serializeArray();
		$.each(a, function() {
			if (o[this.name] !== undefined) {
				if (!o[this.name].push) {
					o[this.name] = [o[this.name]];
				}
				o[this.name].push(this.value || '');
			} else {
				o[this.name] = this.value || '';
			}
		});
		return o;
	};
})(window, document);

/**
 * Overlay
 *
 * @author Alin HORT <alinhort@gmail.com>
 */
(function ($, window, document, undefined) {
	/**
	 * jQuery overlay
	 * @param message
	 * @requires jQuery
	 * @memberof jQuery.prototype
	 * @access public
	 */
	$.fn.overlay = function (message) {
		var $this = $(this),
			$existingOverlay = $this.children('.overlay-wrapper'),
			$overlayWrapper, $overlayBody;

		if (!message || message !== 'remove') {
			if ($existingOverlay.length === 0) {
				$overlayWrapper = $('<div/>', {
					class: 'overlay-wrapper'
				});
				$overlayBody = $('<div/>', {
					class: 'overlay-body',
					text: message || ''
				});
				$this.addClass('overlay-context');
				$overlayWrapper.append($overlayBody).appendTo($this);
			} else {
				$existingOverlay.find('.overlay-body').text(message || '');
			}
		} else {
			if (message === 'remove') {
				$this.removeClass('overlay-context');
				$existingOverlay.remove();
			} else if (message === 'hide') {
				$this.removeClass('overlay-context');
			}
		}
	};
})(jQuery, window, document);

/*
 * @name DoubleScroll
 * @desc displays scroll bar on top and on the bottom of the div
 * @requires jQuery
 *
 * @author Pawel Suwala - http://suwala.eu/
 * @author Antoine Vianey - http://www.astek.fr/
 * @version 0.5 (11-11-2015)
 *
 * Dual licensed under the MIT and GPL licenses:
 * http://www.opensource.org/licenses/mit-license.php
 * http://www.gnu.org/licenses/gpl.html
 *
 * Usage:
 * https://github.com/avianey/jqDoubleScroll
 */
(function( $ ) {

	jQuery.fn.doubleScroll = function(userOptions) {

		// Default options
		var options = {
			contentElement: undefined, // Widest element, if not specified first child element will be used
			scrollCss: {
				'overflow-x': 'auto',
				'overflow-y': 'hidden',
				'height': '20px'
			},
			contentCss: {
				'overflow-x': 'auto',
				'overflow-y': 'hidden'
			},
			onlyIfScroll: true, // top scrollbar is not shown if the bottom one is not present
			resetOnWindowResize: false, // recompute the top ScrollBar requirements when the window is resized
			timeToWaitForResize: 30 // wait for the last update event (usefull when browser fire resize event constantly during ressing)
		};

		$.extend(true, options, userOptions);

		// do not modify
		// internal stuff
		$.extend(options, {
			topScrollBarMarkup: '<div class="doubleScroll-scroll-wrapper"><div class="doubleScroll-scroll"></div></div>',
			topScrollBarWrapperSelector: '.doubleScroll-scroll-wrapper',
			topScrollBarInnerSelector: '.doubleScroll-scroll'
		});

		var _showScrollBar = function($self, options) {

			if (options.onlyIfScroll && $self.get(0).scrollWidth <= Math.round($self.width())) {
				// content doesn't scroll
				// remove any existing occurrence...
				$self.prev(options.topScrollBarWrapperSelector).remove();
				return;
			}

			// add div that will act as an upper scroll only if not already added to the DOM
			var $topScrollBar = $self.prev(options.topScrollBarWrapperSelector);

			if ($topScrollBar.length == 0) {

				// creating the scrollbar
				// added before in the DOM
				$topScrollBar = $(options.topScrollBarMarkup);
				$self.before($topScrollBar);

				// apply the css
				$topScrollBar.css(options.scrollCss);
				$(options.topScrollBarInnerSelector).css("height", "20px");
				$self.css(options.contentCss);

				var scrolling = false;

				// bind upper scroll to bottom scroll
				$topScrollBar.bind('scroll.doubleScroll', function() {
					if (scrolling) {
						scrolling = false;
						return;
					}
					scrolling = true;
					$self.scrollLeft($topScrollBar.scrollLeft());
				});

				// bind bottom scroll to upper scroll
				var selfScrollHandler = function() {
					if (scrolling) {
						scrolling = false;
						return;
					}
					scrolling = true;
					$topScrollBar.scrollLeft($self.scrollLeft());
				};
				$self.bind('scroll.doubleScroll', selfScrollHandler);
			}

			// find the content element (should be the widest one)
			var $contentElement;

			if (options.contentElement !== undefined && $self.find(options.contentElement).length !== 0) {
				$contentElement = $self.find(options.contentElement);
			} else {
				$contentElement = $self.find('>:first-child');
			}

			// set the width of the wrappers
			$(options.topScrollBarInnerSelector, $topScrollBar).width($contentElement.outerWidth());
			$topScrollBar.width($self.width());
			$topScrollBar.scrollLeft($self.scrollLeft());

		}

		return this.each(function() {

			var $self = $(this);

			_showScrollBar($self, options);

			// bind the resize handler
			// do it once
			if (options.resetOnWindowResize) {

				var id;
				var handler = function(e) {
					_showScrollBar($self, options);
				};

				$(window).bind('resize.doubleScroll', function() {
					// adding/removing/replacing the scrollbar might resize the window
					// so the resizing flag will avoid the infinite loop here...
					clearTimeout(id);
					id = setTimeout(handler, options.timeToWaitForResize);
				});

			}

		});

	}

}( jQuery ));

/**
 * Notify
 *
 * @author Alin HORT <alinhort@gmail.com>
 * @access public
 * @requires jQuery
 */
(function ($, window, document, undefined) {
	this.notify = {
		alertTypes: {
			info: {
				type: 'info',
				icon: 'glyphicon glyphicon-info-sign'
			},
			success: {
				type: 'success',
				icon: 'glyphicon glyphicon-ok-sign'
			},
			warning: {
				type: 'warning',
				icon: 'glyphicon glyphicon-exclamation-sign'
			},
			danger: {
				type: 'danger',
				icon: 'glyphicon glyphicon-remove-sign'
			},
			error: {
				type: 'danger',
				icon: 'glyphicon glyphicon-remove-sign'
			}
		},
		defaultOptions: {
			z_index: 9996,
			delay: 3000,
			timer: 1000,
			showProgressbar: true,
			newest_on_top: true,
			allow_dismiss: true,
			mouse_over: 'pause',
			placement: {
				from: 'top',
				align: 'right'
			},
			animate: {
				enter: 'animated fadeInDown',
				exit: 'animated fadeOutUp'
			},
			offset: {
				x: 20,
				y: 60
			},
			template: '<div data-notify="container" class="col-xs-11 col-sm-6 col-md-5 col-lg-4 alert alert-{0}" role="alert">' +
				'<button type="button" aria-hidden="true" class="close" data-notify="dismiss">×</button>' +
				'<span data-notify="icon"></span> ' +
				'<span data-notify="title">{1}</span> ' +
				'<span data-notify="message">{2}</span>' +
				'<div class="progress kv-progress-bar" data-notify="progressbar">' +
					'<div class="progress-bar progress-bar-{0}" role="progressbar" aria-valuenow="0" aria-valuemin="0" aria-valuemax="100" style="width: 0%;"></div>' +
				'</div>' +
				'<a href="{3}" target="{4}" data-notify="url"></a>' +
			'</div>'
		},

		/**
		 * Builds message.
		 *
		 * @param message
		 * @returns {string}
		 */
		buildMessage: function (message) {
			var msg = '';

			if ($.isPlainObject(message) && message.hasOwnProperty('body')) {
				if (typeof message.body === 'string') {
					msg = message.body;
				} else if (message.body instanceof Array) {
					for (var i = 0, len = message.body.length; i < len; i++) {
						msg += ('<div>' + message.body[i] + '</div>');
					}
				}
			} else if (typeof message === 'string') {
				msg = message;
			}

			return msg;
		},

		/**
		 * Shows a custom type of bootstrap alert.
		 *
		 * @param data
		 * @param $container
		 */
		render: function (data, $container) {
			var alertType = this.alertTypes[data.type] || this.alertTypes['info'],
				options = $.extend({}, this.defaultOptions, data);

			// Exit if the plugin or the message is not defined
			if (!$.notify || (!data.title && !data.message)) {
				return;
			}
			// Add a separator between title and message
			if (data.title && data.message) {
				var $template = $(options.template);
				$('<hr class="kv-alert-separator">').insertAfter($template.find('[data-notify="title"]'));
				options.template = $template.prop('outerHTML');
			}
			// Add notification to a specific container
			if ($container) {
				options.element = $container;
				options.position = 'static';
			}

			// Show the notification
			$.notify({
				icon: alertType.icon,
				message: data.message,
				title: data.title
			}, options);
		},

		/**
		 * Renders info notification.
		 *
		 * @param message
		 * @param $container
		 */
		info: function (message, $container) {
			this.render({
				type: 'info',
				message: this.buildMessage(message)
			}, $container);
		},

		/**
		 * Renders success notification.
		 *
		 * @param message
		 * @param $container
		 */
		success: function (message, $container) {
			this.render({
				type: 'success',
				message: this.buildMessage(message)
			}, $container);
		},

		/**
		 * Renders warning notification.
		 *
		 * @param message
		 * @param $container
		 */
		warning: function (message, $container) {
			this.render({
				type: 'warning',
				message: this.buildMessage(message)
			}, $container);
		},

		/**
		 * Renders error notification.
		 *
		 * @param message
		 * @param $container
		 */
		error: function (message, $container) {
			this.render({
				type: 'danger',
				message: this.buildMessage(message)
			}, $container);
		},

		/**
		 * Renders a custom notification based on data argument.
		 *
		 * @param data
		 * @param $container
		 */
		show: function (data, $container) {
			if (!data) {
				return;
			}

			if (!data.hasOwnProperty('message')) {
				data.message = {};
			}

			this.render({
				type: data.message.type ? data.message.type : (data.success === true) ? 'success' : 'danger',
				title: data.message.title,
				message: this.buildMessage(data.message)
			}, $container);
		}
	};
})(jQuery, window, document);

/**
 * AutoFill Application
 *
 * @access public
 * @requires jQuery
 */
(function ($, window, document, undefined) {
	this.autoFillApp = {
		/**
		 * Initialization
		 */
		init: function () {
			this._cacheElements();
			this._bindEvents();
		},

		/**
		 * Caches DOM elements.
		 *
		 * @private
		 */
		_cacheElements: function () {
			this.$document = $(document);
			this.$body = $(document.body);
		},

		/**
		 * Binds global events.
		 *
		 * @private
		 */
		_bindEvents: function () {
			this.$document.on('click.autoFillApp', '[data-autofill-target]', this._onAutofillTargetClick.bind(this));
			this.$document.on('change.autoFillApp', '[data-autofill-target]', this._onAutofillTargetChange.bind(this));
		},

		/**
		 * Handles global auto-fill click event.
		 *
		 * @param e
		 * @private
		 */
		_onAutofillTargetClick: function (e) {
			var $source = $(e.currentTarget),
				sourceData = $source.data(),
				$target = sourceData.autofillTarget ? $(sourceData.autofillTarget) : this.$body;
			// Exit if the autofill target is not found or the source is an input
			if (!$target.length || ($source.is(':input') && !$source.is('button'))) {
				return;
			}
			// Check for AJAX url
			if ($source.attr('href') || sourceData.autofillUrl) {
				this.getAjaxData($source, $target);
			} else {
				this.getData($source, $target);
			}
			e.preventDefault();
		},

		/**
		 * Handles global auto-fill input change event.
		 *
		 * @param e
		 * @private
		 */
		_onAutofillTargetChange: function (e) {
			var $source = $(e.currentTarget),
				sourceData = $source.data(),
				$target = sourceData.autofillTarget ? $(sourceData.autofillTarget) : this.$body;
			// Exit if the autofill target is not found
			if (!$target.length) {
				return;
			}
			// Check for AJAX url
			if (sourceData.autofillUrl) {
				this.getAjaxData($source, $target);
			} else {
				this.getData($source, $target);
			}
		},

		/**
		 * Sets the target value.
		 *
		 * @param $source
		 * @param $target
		 * @param response
		 */
		setTargetValue: function ($source, $target, response) {
			var me = this,
				sourceData = $source.data(),
				sourceValue = $source.val();

			// Ensure that the response is always an Object
			if (!$.isPlainObject(response)) {
				response = {};
			}
			// Check if the autofill target is a form control
			if ($target.is(':input')) {
				// Set the value for the form field
				$target.val(response[sourceData.autofillData] || '');
				// Trigger custom events
				if (sourceData.autofillTriggerEvent) {
					$target.trigger(sourceData.autofillTriggerEvent);
				}
				if ($target.is('[data-krajee-select2]')) {
					$target.trigger('change.select2');
				} else if ($target.is('[data-krajee-numbercontrol]')) {
					var cfg = window[$target.data('krajeeNumbercontrol')];
					if ($.isPlainObject(cfg) && cfg.hasOwnProperty('displayId')) {
						$('#' + cfg.displayId).val(response[sourceData.autofillData] || '');
					}
				}
			} else {
				// Loop through the requested attributes
				if ($.isPlainObject(sourceData.autofillData)) {
					$.each(sourceData.autofillData, function (attribute, fieldSelector) {
						var responseValue = response[attribute];
						if (attribute.indexOf('.') !== -1) {
							responseValue = attribute.split('.').reduce(function(a, b) {
								if ($.isPlainObject(a) && a.hasOwnProperty(b)) {
									return a[b];
								}
							}, response);
						}
						if (sourceValue !== '' || (sourceData.hasOwnProperty('autofillClearEmpty') && !sourceData.autofillClearEmpty)) {
							if (typeof responseValue === 'undefined' && (sourceData.hasOwnProperty('autofillClearInvalid') && !sourceData.autofillClearInvalid)) {
								return;
							}
						}
						var $fieldSelector = $(fieldSelector);
						// Set the value for the form field
						if ($fieldSelector.is(':input')) {
							$fieldSelector.val(responseValue || '');
						} else {
							if (responseValue) {
								$fieldSelector = $fieldSelector.find(':radio, :checkbox').filter('[value="' + responseValue + '"]');
								$fieldSelector.prop('checked', true);
							} else {
								$fieldSelector = $fieldSelector.find(':radio, :checkbox');
								$fieldSelector.prop('checked', false);
							}
						}
						// Trigger custom events
						if (sourceData.autofillTriggerEvent) {
							$fieldSelector.trigger(sourceData.autofillTriggerEvent);
						}
						if ($fieldSelector.is('[data-krajee-select2]')) {
							$fieldSelector.trigger('change.select2');
						} else if ($fieldSelector.is('[data-krajee-numbercontrol]')) {
							var cfg = window[$fieldSelector.data('krajeeNumbercontrol')];
							if ($.isPlainObject(cfg) && cfg.hasOwnProperty('displayId')) {
								$('#' + cfg.displayId).val(responseValue || '');
							}
						}
					});
				} else {
					// Set the target element text node
					$target.text(response[sourceData.autofillData] || $target.text());
				}
			}
		},

		/**
		 * Makes an AJAX request to get the requested data and then fills the indicated fields.
		 *
		 * @param $source
		 * @param $target
		 */
		getAjaxData: function ($source, $target) {
			var me = this,
				sourceData = $source.data(),
				sourceValue = $source.val(),
				$loadingContainer = $target.is(':input') ? $target.parent() : $target;

			// Exit if the source value is empty
			if (sourceValue === '') {
				// Clear the target value
				if (!sourceData.hasOwnProperty('autofillClearEmpty') || sourceData.autofillClearEmpty) {
					this.setTargetValue($source, $target, {});
				}
				return;
			}
			// Set the loading overlay
			$loadingContainer.overlay();
			// Cancel previous XHR
			if (this.xhr) {
				this.xhr.abort();
			}
			var xhrData = {
				name: $source.attr('name'),
				value: sourceValue,
				data: $.isPlainObject(sourceData.autofillData) ?
					Object.keys(sourceData.autofillData) :
					(sourceData.autofillData || '').split()
			};
			if (sourceData.autofillParam) {
				xhrData[sourceData.autofillParam] = sourceValue;
			}
			// Make the AJAX request
			this.xhr = $.ajax({
				url: sourceData.autofillUrl || window.location.href,
				method: sourceData.autofillMethod || 'GET',
				data: xhrData
			}).done(function (response) {
				me.setTargetValue($source, $target, response);
			}).always(function() {
				// Unset the loading overlay
				$loadingContainer.overlay('remove');
			});
		},

		/**
		 * Fills the form control fields with the values from a source DOM element data attributes.
		 *
		 * @param $source
		 * @param $target
		 */
		getData: function ($source, $target) {
			var sourceData = $source.data(),
				sourceValue = $source.val(),
				response = $.extend({}, sourceData);

			// Exit if the source value is empty
			if (($source.is(':input') && !$source.is('button')) && sourceValue === '') {
				// Clear the target value
				if (!sourceData.hasOwnProperty('autofillClear') || sourceData.autofillClear) {
					this.setTargetValue($source, $target, {});
				}
				return;
			}
			// Find the selected option in case of select form control type
			if ($source.is('select')) {
				// Set response as the selected option data
				response = $.extend({}, $source.find('option:selected').data());
			}
			// Set the source value attribute if no data attribute is set
			if (!sourceData.hasOwnProperty('autofillData')) {
				sourceData.autofillData = ':value';
			}
			// Set the source value
			if (sourceData.autofillData === ':text') {
				if ($source.is('select')) {
					response[':text'] = $source.find('option:selected').text().trim();
				} else {
					response[':text'] = $source.text().trim();
				}
			} else {
				response[':value'] = $source.val();
			}

			this.setTargetValue($source, $target, response);
		}
	};
})(jQuery, window, document);

/**
 * Popup Action
 *
 * @author Alin HORT <alinhort@gmail.com>
 */
(function ($, window, document, undefined) {
	/**
	 * Constants
	 * @constant {String} PLUGIN_NAME
	 * @constant {String} PLUGIN_VERSION
	 * @constant {String} DATA_KEY
	 * @constant {Object} DEFAULTS
	 */
	var PLUGIN_NAME = 'yiiPopupAction',
		PLUGIN_VERSION = '0.0.1',
		EVENT_NS = '.' + PLUGIN_NAME,
		DATA_KEY = 'plugin_' + PLUGIN_NAME,
		DEFAULTS = {
			tooltip: false
		};

	/**
	 * Plugin
	 *
	 * @param element
	 * @param options
	 * @param metadata
	 * @constructor
	 */
	var Plugin = function (element, options, metadata) {
		// Exit if DOM element does not exist
		if (!element) {
			console.error('[' + PLUGIN_NAME + ']: DOM element is missing');
			return;
		}
		// Set the DOM element
		this.element = element;
		// Set options
		this.options = $.extend({}, DEFAULTS, options, metadata);
		this.id = parseInt(Date.now() * Math.random());
		// Initialization
		this.init();
	};

	/**
	 * Initialization
	 */
	Plugin.prototype.init = function () {
		this._cacheElements();
		this._bindEvents();
		this.renderInitialContent();
		this._hook('onInit');
	};

	/**
	 * Caches DOM Elements.
	 *
	 * @private
	 */
	Plugin.prototype._cacheElements = function () {
		this.$window = $(window);
		this.$document = $(document);
		this.$body = $(document.body);
		this.$element = $(this.element);
		this.$modal = $('<div/>', {
			'id': 'modal-' + this.id,
			'class': 'modal fade ' + this.options.popupCssClass,
			'role': 'dialog'
		});
	};

	/**
	 * Binds Events.
	 *
	 * @private
	 */
	Plugin.prototype._bindEvents = function () {
		this.$modal.on('submit' + EVENT_NS, 'form', this._onFormSubmit.bind(this));
		this.$modal.on('hidden.bs.modal', this._onHidePopup.bind(this));
	};

	/**
	 * Handles the popup form submit.
	 *
	 * @param e
	 * @private
	 */
	Plugin.prototype._onFormSubmit = function (e) {
		var me = this,
			$form = $(e.currentTarget),
			xhrConfig = {},
			xhrData = null;

		// Submit the form traditionally if the target attribute is _blank.
		if ($form.attr('target') === '_blank') {
			return true;
		}
		// Set loading state
		$form.children('.modal-content').overlay();
		// Use FormData or serialize the form based on the enctype attribute
		if ($form.attr('enctype') === 'multipart/form-data') {
			xhrData = new FormData($form.get(0));
			xhrConfig.processData = false;
			xhrConfig.contentType = false;
		} else {
			xhrData = $form.serializeArray();
		}
		// Make the XHR
		$.ajax($.extend({
			url: $form.attr('action'),
			method: $form.attr('method'),
			data: xhrData
		}, xhrConfig)).done(function (response) {
			notify.show(response);
			// Check the response
			if (response.success) {
				if (response.redirectUrl) {
					window.location.href = response.redirectUrl;
					return;
				}
				me.executeCallbacks();
				me.$document.trigger('done.popupAction', [me.$element, me.$modal, response]);
				me.$element.data('popupActionResponseId', response.id);
				me.$modal.modal('hide');
			} else {
				me.render(response.data);
			}
		}).always(function () {
			// Unset loading state
			$form.children('.modal-content').overlay('remove');
		});
		// Prevent default form submission
		e.preventDefault();
	};

	/**
	 * Handles the popup hide event.
	 *
	 * @param e
	 */
	Plugin.prototype._onHidePopup = function (e) {
		this.$modal.remove();
		this.destroy();
	};

	/**
	 * Renders the popup with the initial content.
	 */
	Plugin.prototype.renderInitialContent = function () {
		var me = this,
			elementData = this.$element.data(),
			url = this.$element.attr('href') || elementData.popupAction,
			method = (elementData.popupMethod || 'GET').toUpperCase(),
			xhrData = null;
		// Exit if the url is not set
		if (!url || url === '#') {
			return false;
		}
		// Set the XHR data
		if (elementData.popupParams) {
			if (typeof elementData.popupParams === 'string') {
				xhrData = this.$body.find(elementData.popupParams).serializeArray();
			} else {
				xhrData = elementData.popupParams;
			}
		}
		// Set loading state
		this.$body.overlay();
		// Cancel previous request if is set
		if (this.xhr) {
			this.xhr.abort();
		}
		// Make the XHR
		this.xhr = $.ajax({
			url: url,
			method: method,
			data: xhrData
		})
			.done(function (response) {
				if (method === 'GET') {
					me.render(response.data);
					me.$document.trigger('show.popupAction', [me.$element, me.$modal]);
				} else {
					if (elementData.popupForceRender) {
						me.render(response.data);
						me.$document.trigger('show.popupAction', [me.$element, me.$modal]);
					} else {
						notify.show(response);
						me.executeCallbacks();
					}
				}
			})
			.always(function () {
				me.$body.overlay('remove');
			});
	};

	/**
	 * Renders the popup with a custom content set.
	 *
	 * @param content
	 */
	Plugin.prototype.render = function (content) {
		this.$modal
			.html(content)
			.appendTo(this.$body)
			.modal({
				backdrop: 'static',
				keyboard: false,
				show: true
			});
	};

	/**
	 * Executes the element provided callbacks.
	 */
	Plugin.prototype.executeCallbacks = function () {
		var me = this;

		if (!this.$element || !this.$element.length) {
			return;
		}

		var callbacks = this.$element.data('popupDone');
		if (!callbacks) {
			return;
		}

		$.each(callbacks, function (method, argument) {
			if (typeof me[method] === 'function') {
				me[method].call(me, argument);
			}
		});
	};

	/**
	 * Redraws the DataTable.
	 *
	 * @param dataTableId
	 */
	Plugin.prototype.redrawDataTable = function (dataTableId) {
		var $dataTable = $(dataTableId);

		if ($dataTable.length) {
			$dataTable.DataTable().draw();
		}
	};

	/**
	 * Redirects the browser to a specific url.
	 *
	 * @param url
	 */
	Plugin.prototype.redirect = function (url) {
		window.location.href = url;
	};

	/**
	 * Reloads the current browser page, a specific or a collection of DOM elements.
	 *
	 * @param target
	 */
	Plugin.prototype.reload = function (target) {
		if (target === 'window') {
			window.location.reload();
		}

		// Find the target by the used selector
		var me = this,
			$targets = this.$body.find(target),
			targetsLen = $targets.length;

		// Get the requested page content if the target exists
		if (targetsLen) {
			// Set loading state
			this.$body.overlay();
			// Make the XHR
			$.get($targets.eq(0).closest('form').attr('action')).done(function (response) {
				// Replace the target element innerHTML
				if (response && ($.isPlainObject(response) || response.length)) {
					var $responseData = $(response.data || response);
					$.each(target.split(','), function (index, currentTarget) {
						var $currentTarget = $targets.filter(currentTarget).html($responseData.find(currentTarget).html());
						if (targetsLen === 1 && me.$element.data('popupActionResponseId')) {
							$currentTarget.val(me.$element.data('popupActionResponseId')).trigger('change.select2');
						}
					});
				}
			}).always(function () {
				me.$body.overlay('remove');
			});
		}
	};

	/**
	 * Hooks callbacks.
	 *
	 * @access private
	 * @param [arguments]
	 */
	Plugin.prototype._hook = function () {
		var args = Array.prototype.slice.call(arguments),
			hookName = args.shift(),
			eventName = hookName.split(/(?=[A-Z])/)[1].toLowerCase() + EVENT_NS;

		if (typeof this.options[hookName] !== 'undefined') {
			// Callback
			this.options[hookName].apply(this.element, args);
			// Create a new event
			var event = $.Event(eventName, {
				target: this.element
			});
			// Trigger the event
			this.$element.trigger(event, args);
		}
	};

	/**
	 * Gets or sets a property.
	 *
	 * @access public
	 * @param {String} key
	 * @param {String} val
	 */
	Plugin.prototype.option = function (key, val) {
		if (val) {
			this.options[key] = val;
		} else {
			return this.options[key];
		}
	};

	/**
	 * Destroys the plugin instance.
	 *
	 * @public
	 */
	Plugin.prototype.destroy = function () {
		this._hook('onDestroy');
		this.$window.off(EVENT_NS);
		this.$document.off(EVENT_NS);
		this.$element.off(EVENT_NS);
		this.$element.removeData(DATA_KEY);
	};

	/**
	 * Plugin definition
	 * @function external "jQuery.fn".yiiClipboard
	 */
	$.fn[PLUGIN_NAME] = function (options) {
		var args = arguments;

		if (!options || typeof options === 'object') {
			return this.each(function () {
				if (!$.data(this, DATA_KEY)) {
					var metadata = $(this).data();
					$.data(this, DATA_KEY, new Plugin(this, options, metadata));
				}
			});
		} else if (typeof args[0] === 'string') {
			var methodName = args[0].replace('_', ''),
				returnVal;

			this.each(function () {
				var instance = $.data(this, DATA_KEY);

				if (instance && typeof instance[methodName] === 'function') {
					returnVal = instance[methodName].apply(instance, Array.prototype.slice.call(args, 1));
				} else {
					throw new Error('Could not call method "' + methodName + '" on jQuery.fn.' + PLUGIN_NAME);
				}
			});

			return (typeof returnVal !== 'undefined') ? returnVal : this;
		}
	};

	/**
	 * Expose global
	 */
	this[PLUGIN_NAME] = Plugin;

	/**
	 * Data API delegation
	 */
	$(document).on('click', '[data-popup-action]', function (e) {
		var $target = $(e.currentTarget),
			targetData = $target.data();

		if (targetData.hasOwnProperty('popupConfirm') && (typeof window.appDialog !== 'undefined')) {
			appDialog.confirm(targetData.popupConfirm, function (isConfirmed) {
				if (isConfirmed) {
					$target[PLUGIN_NAME]();
				}
			});
		} else {
			$target[PLUGIN_NAME]();
		}

		e.preventDefault();
	});

	/**
	 * Let TinyMCE dialogs work inside Bootstrap modals.
	 *
	 * Bootstrap's modal enforceFocus() pulls the focus back into the modal on every
	 * focusin, but TinyMCE attaches its dialogs (.mce-window) and inline popups to the
	 * document body, OUTSIDE the modal — so their inputs lose focus instantly and the
	 * toolbar buttons that open dialogs (link, table, etc.) appear dead. Stop the
	 * focusin event from reaching Bootstrap when it originates inside a TinyMCE UI
	 * element.
	 */
	$(document).on('focusin', function (e) {
		if ($(e.target).closest('.mce-window, .mce-panel, .moxman-window, .tam-assetmanager-root').length) {
			e.stopImmediatePropagation();
		}
	});

})(jQuery, window, document);

/**
 * Seo Helper App
 *
 * @access public
 * @requires jQuery
 */
(function ($, window, document, undefined) {
	this.seoHelperApp = {
		metaTitleSelector: '[data-seo-meta="title"]',
		metaKeywordsSelector: '[data-seo-meta="keywords"]',
		metaDescriptionSelector: '[data-seo-meta="description"]',
		cache: {},

		/**
		 * Initialization
		 */
		init: function () {
			this.cacheElements();
			this.bindEvents();
		},

		/**
		 * Caches DOM elements.
		 */
		cacheElements: function () {
			this.$document = $(document);
			this.$body = $(document.body);
			this.$seoMetaFields = $('[data-seo-meta]');
			// Cache the original helper text
			this.cache = {
				title: this.$seoMetaFields.filter(this.metaTitleSelector).next('.help-block').html(),
				keywords: this.$seoMetaFields.filter(this.metaKeywordsSelector).next('.help-block').html(),
				description: this.$seoMetaFields.filter(this.metaDescriptionSelector).next('.help-block').html()
			};
		},

		/**
		 * Binds global events.
		 */
		bindEvents: function () {
			this.$document.off('.seoHelperApp');
			this.$document.on('input.seoHelperApp', this.metaTitleSelector, this._onMetaTitleInput.bind(this));
			this.$document.on('change.seoHelperApp', this.metaKeywordsSelector, this._onMetaKeywordsChange.bind(this));
			this.$document.on('input.seoHelperApp', this.metaDescriptionSelector, this.onMetaDescriptionInput.bind(this));

			this.$seoMetaFields.trigger('input.seoHelperApp');
			this.$seoMetaFields.filter(this.metaKeywordsSelector).trigger('change.seoHelperApp');
		},

		/**
		 * Handles window beforeunload event.
		 *
		 * @param e
		 * @private
		 */
		_onMetaTitleInput: function (e) {
			var $target = $(e.currentTarget),
				length = $target.val().length;

			this.displayLengthHints($target, length, 'title');
		},

		/**
		 * Handles window beforeunload event.
		 *
		 * @param e
		 * @private
		 */
		_onMetaKeywordsChange: function (e) {
			var $target = $(e.currentTarget),
				keywords = $target.val(),
				length = keywords[0] === '' ? 0 : keywords.length;

			this.displayLengthHints($target, length, 'keywords');
		},

		/**
		 * Handles window beforeunload event.
		 *
		 * @param e
		 * @private
		 */
		onMetaDescriptionInput: function (e) {
			var $target = $(e.currentTarget),
				data = $target.data(),
				val = $target.val(),
				length = val.length,
				keywords = $target.closest('.tab-pane').find(this.metaKeywordsSelector).val(),
				canDisplayHint = keywords[0] === '' ? false : true,
				countedKeywords = [];

			for (var i = 0, len = keywords.length; i < len; i++) {
				if (val.toLowerCase().indexOf(keywords[i].toLowerCase()) !== -1) {
					countedKeywords.push(keywords[i]);
				}
			}

			this.displayLengthHints($target, length, 'description');

			if (canDisplayHint && countedKeywords.length < 2) {
				this.displayHint($target, {
					type: 'info',
					icon: 'fa-info-circle',
					message: data.seoKeyscountHint.replace('%s', countedKeywords.length).replace('%s', countedKeywords.length ? '(' + countedKeywords.join(', ') + ')' : '')
				}, true);
				$target.addClass('seo-hint-warning');
			} else {
				$target.closest('.form-group').find('.seo-hint-extra').remove();
				this.displayLengthHints($target, length, 'description');
			}
		},

		/**
		 * Builds SEO hint HTML.
		 */
		buildSeoHintHtml: function (data) {
			var $icon = $('<span/>', {
					'class': 'fa ' + data.icon,
				}),
				$message = $('<span/>', {
					'html': data.message
				});

			return $('<div/>', {
				'class': 'seo-hint text-' + data.type,
				'html': $icon.prop('outerHTML') + $message.prop('outerHTML')
			}).prop('outerHTML');
		},

		/**
		 * Displays SEO hint.
		 *
		 * @param $element
		 * @param data
		 * @param extra
		 */
		displayHint: function ($element, data, extra) {
			var seoHintHtml = this.buildSeoHintHtml(data),
				$placeholder = $element.closest('.form-group').find('.help-block').eq(0);

			if (extra) {
				if ($placeholder.next('.seo-hint-extra').length > 0) {
					$placeholder.next('.seo-hint-extra').html(seoHintHtml);
				} else {
					$('<div/>', {
						class: 'seo-hint-extra',
						html: seoHintHtml
					}).insertAfter($placeholder);
				}
			} else {
				$placeholder.html(seoHintHtml);
			}
		},

		/**
		 * Displays SEO length hints.
		 *
		 * @param $element
		 * @param length
		 * @param cacheKey
		 */
		displayLengthHints: function ($element, length, cacheKey) {
			var data = $element.data(),
				$placeholder = $element.closest('.form-group').find('.help-block').eq(0);

			$element.removeClass('seo-hint-warning');

			if (length < data.seoMinlength) {
				this.displayHint($element, {
					type: 'warning',
					icon: 'fa-exclamation-circle',
					message: data.seoMinlengthHint.replace('%s', length).replace('%s', data.seoMinlength)
				});
				$element.addClass('seo-hint-warning');
			} else if (length > data.seoMaxlength) {
				this.displayHint($element, {
					type: 'warning',
					icon: 'fa-exclamation-circle',
					message: data.seoMaxlengthHint.replace('%s', length).replace('%s', data.seoMaxlength)
				});
				$element.addClass('seo-hint-warning');
			} else {
				$placeholder.text(this.cache[cacheKey]);
			}
		}
	};
})(jQuery, window, document);

/**
 * Main app
 *
 * @access public
 * @requires jQuery
 */
(function ($, window, document, undefined) {
	this.mainApp = {
		isWebkit: ('WebkitAppearance' in document.documentElement.style),
		isMobile: (typeof window.orientation !== 'undefined'),
		preventPageOverlay: false,
		/**
		 * Initialization
		 */
		init: function () {
			this._cacheElements();
			this._initPlugins();
			this._bindEvents();
		},

		/**
		 * Plugins initialization.
		 *
		 * @private
		 */
		_initPlugins: function () {
			// Prevent jQuery UI plugins conflict with bootstrap plugins
			if ($.ui && $.fn.widget) {
				$.widget.bridge('uitooltip', $.ui.tooltip);
			}
			// Tooltip
			if ($.fn.tooltip) {
				// Enable bootstrap tooltip
				this.$body.tooltip({
					container: 'body',
					selector: '[data-toggle="tooltip"], [data-toggle-extend="tooltip"]',
					placement: 'auto',
					trigger: 'hover',
					html: true,
					delay: {
						show: 100,
						hide: 50
					}
				});
			}
			// Popover
			if ($.fn.popover) {
				this.$body.popover({
					container: 'body',
					selector: '[data-toggle="popover"], [data-toggle-extend="popover"]',
					placement: 'auto',
					trigger: 'focus',
					html: true,
					delay: {
						show: 100,
						hide: 50
					}
				});
			}
			if ($.fn.select2) {
				$.fn.select2.defaults.set('escapeMarkup', function (markup) { return markup; });
			}
			// Growl
			if ($.notifyDefaults) {
				$.notifyDefaults(notify.defaults);
			}
		},

		/**
		 * Caches DOM elements.
		 *
		 * @private
		 */
		_cacheElements: function () {
			this.$window = $(window);
			this.$document = $(document);
			this.$html = $('html');
			this.$body = this.$html.children('body');
			// Setup the scroll element (html, body)
			this.$scrollElement = this.$html;
			this.$scrollElement = this.$scrollElement.add(this.$body);
		},

		/**
		 * Binds global events.
		 *
		 * @private
		 */
		_bindEvents: function () {
			this.$window.on('beforeunload', this._onWindowBeforeUnload.bind(this));
			this.$document.on('click', '[data-prevent-page-overlay]', this._onPreventPageOverlayClick.bind(this));
			this.$document.on('reset', 'form', this._onFormReset.bind(this));
			this.$document.on('show.bs.tab', '.nav-tabs-hash [data-toggle="tab"]', this._onHashTabShow.bind(this));
			this.$document.on('dp.show', '.datetimepicker', this._onDateTimePickerShow.bind(this));
			this.$document.on('select2:open', '.select2-fullheight', this._onSelect2Open.bind(this));
			this.$document.on('filebeforedelete', '[data-krajee-fileinput]', this._onFileBeforeDelete.bind(this));
			this.$document.on('typeahead:select', '[data-typeahead-autocomplete-container]', this._onTypeaheadAutocompleteSelected.bind(this));
			this.$document.on('show.bs.popover', this._onPopoverShow.bind(this));
			this.$document.on('click', '[data-insert-target]', this._onClickInsertText.bind(this));
			this.$document.on('input', '[data-copy-value]', this._onInputCopyValue.bind(this));
			this.$document.on('change', '[data-toggle-visibility]', this._onChangeToggleVisibility.bind(this));
			this.$document.on('change dp.change', '[data-invalidate-field]', this._onChangeInvalidateField.bind(this));
			this.$document.on('change', '[data-mark-all], [data-mark-all-child]', this._onChangeMarkAll.bind(this));
			this.$document.on('change', '[data-process-pin]', this._onProcessPin.bind(this));
			this.$document.on('click', '[data-scroll-to]', this._onScrollToClick.bind(this));

			// Disable form duplicate submit
			this.$document.on('beforeValidate', 'form', function (e, messages, deferreds) {
				$(e.currentTarget).find(':submit').attr('disabled', true);
			});
			this.$document.on('afterValidate', 'form', function (e, messages, errorAttributes) {
				if (errorAttributes.length > 0) {
					$(e.currentTarget).find(':submit').attr('disabled', false);
				}
			});

			// Tinymce
			var $insertTargetScopes = this.$body.find('[data-insert-target-scope]');
			if ($insertTargetScopes.length) {
				this.$document.on('click', function (e) {
					var $target = $(e.target);
					if ($target.is('[data-insert-target-scope]')) {
						$target.attr('data-insert-target-scope', true);
					} else if (!$target.is('[data-insert-target]')) {
						$insertTargetScopes.attr('data-insert-target-scope', false);
						// TODO: Unset all active tinyMCE editors
					}
				});
				if (typeof tinyMCE !== 'undefined') {
					tinyMCE.on('AddEditor', function (e) {
						e.editor.on('focus', function () {
							$insertTargetScopes.attr('data-insert-target-scope', false);
						});
					});
				}
			}
		},

		/**
		 * Handles window beforeunload event.
		 *
		 * @param e
		 * @private
		 */
		_onWindowBeforeUnload: function (e) {
			// Show overlay before each window unload if is not manually prevented
			if (this.preventPageOverlay === false) {
				this.$body.overlay();
			}
			this.preventPageOverlay = false;
		},

		/**
		 * Handles prevent page overlay element click event.
		 *
		 * @param e
		 * @private
		 */
		_onPreventPageOverlayClick: function (e) {
			var $target = $(e.currentTarget);

			this.preventPageOverlay = !!$target.data('preventPageOverlay');
		},

		/**
		 * Handles form reset event.
		 *
		 * @param e
		 * @private
		 */
		_onFormReset: function (e) {
			var $form = $(e.currentTarget),
				$selects2 = $form.find('[data-krajee-select2]');
			// Wait for reset
			setTimeout(function () {
				// Loop through all selects of the form
				$selects2.each(function (index, select2) {
					var $select2 = $(select2);
					// Force update the select2 plugin value
					if ($select2.data('select2')) {
						$select2.trigger('change.select2');
					}
				});
			});
		},

		/**
		 * Handles hash tab show event.
		 *
		 * @param e
		 * @private
		 */
		_onHashTabShow: function (e) {
			var $target = $(e.target.hash),
				$dataTables = $target.find('.dataTable');

			// Set the window location has as the target hash
			window.location.hash = e.target.hash;

			if ($dataTables.length) {
				$dataTables.DataTable().draw();
			}
		},

		/**
		 * Handles datetimepicker plugin show event.
		 *
		 * @param e
		 * @private
		 */
		_onDateTimePickerShow: function (e) {
			var $target = $(e.target),
				targetOffset = $target.offset(),
				$datetimepicker = this.$body.children('.bootstrap-datetimepicker-widget').last();
			// Exit if the widget is not appended to the body
			if (!$datetimepicker.length) {
				return;
			}
			// Check if the widget is displayed on the bottom or on the top
			if ($datetimepicker.hasClass('bottom')) {
				$datetimepicker.css({
					top: (targetOffset.top + $target.outerHeight()) + 'px',
					bottom: 'auto',
					left: targetOffset.left + 'px'
				});
			} else if ($datetimepicker.hasClass('top')) {
				$datetimepicker.css({
					top: (targetOffset.top - $datetimepicker.outerHeight()) + 'px',
					bottom: 'auto',
					left: targetOffset.left + 'px'
				});
			}
		},

		/**
		 * Handles select2 plugin show event.
		 *
		 * @param e
		 * @private
		 */
		_onSelect2Open: function (e) {
			var $container = $(e.currentTarget),
				topOffset = $container.offset().top,
				availableHeight = mainApp.$window.height() - topOffset - $container.outerHeight(),
				bottomPadding = 100;
			// Set the result options max height
			mainApp.$body
				.children('.select2-container')
				.find('.select2-results__options')
				.css('max-height', (availableHeight - bottomPadding) + 'px');
		},

		/**
		 * Handles global filebeforedelete event.
		 *
		 * @param e
		 * @private
		 */
		_onFileBeforeDelete: function (e) {
			var $target = $(e.currentTarget),
				targetData = $target.data();

			return new Promise(function (resolve, reject) {
				appDialog.confirm(targetData.operationConfirm, function (isConfirmed) {
					if (isConfirmed) {
						resolve();
					}
				});
			});
		},

		/**
		 * Handles typeahead autocomplete selected event.
		 *
		 * @param e
		 * @param suggestion
		 * @private
		 */
		_onTypeaheadAutocompleteSelected: function (e, suggestion) {
			var $target = $(e.currentTarget),
				targetData = $target.data(),
				$form = $target.closest('form'),
				$typeaheadAutocompleteContainer = targetData.typeaheadAutocompleteContainer ?
					$form.find(targetData.typeaheadAutocompleteContainer) :
					$form,
				$formControls = $typeaheadAutocompleteContainer.find(':input');

			// Autocomplete the fields
			$.each(suggestion, function (key, val) {
				var $formControl = $formControls.filter('[name*=\"[' + key + ']\"]');
				// Check the form control type
				if ($formControl.is(':checkbox') || $formControl.is(':radio')) {
					$formControl.filter('[value="' + val + '"]').prop('checked', true);
				} else if ($.fn.typeahead && $formControl.is('[data-krajee-typeahead]')) {
					$formControl.typeahead('val', val);
				} else {
					if ($formControl.is('[data-datetimepicker-options]')) {
						val = val ?
							moment(val).format(window[$formControl.attr('data-datetimepicker-options')].format) :
							null;
					} else if ($formControl.is('[data-krajee-numbercontrol]')) {
						var cfg = window[$formControl.data('krajeeNumbercontrol')];
						if ($.isPlainObject(cfg) && cfg.hasOwnProperty('displayId')) {
							$('#' + cfg.displayId).val(val);
						}
					} else if ($target.is('[data-krajee-select2]')) {
						$target.trigger('change.select2');
					} else if ($formControl.is('[data-krajee-depdrop]')) {
						var ddCfg = window[$formControl.data('krajeeDepdrop')];
						if ($.isPlainObject(ddCfg) && ddCfg.hasOwnProperty('depends')) {
							setTimeout(function () {
								$('#' + ddCfg.depends.join(', #')).trigger('depdrop:change');
							});
						}
						$formControl.one('depdrop:afterChange', function () {
							$formControl.val(val);
						});
					}
					$formControl.val(val);
				}
				$formControl.trigger('change');
			});
		},

		/**
		 * Handles popover show event.
		 *
		 * @param e
		 * @private
		 */
		_onPopoverShow: function (e) {
			var $target = $(e.target),
				targetData = $target.data(),
				popover = targetData['bs.popover'];

			if (popover && popover.hasOwnProperty('options')) {
				if (targetData.placement) {
					popover.options.placement = targetData.placement;
				}
				if (targetData.maxWidth) {
					popover.$tip.css('max-width', targetData.maxWidth);
				}
			}
		},

		/**
		 * Handles insert text to a target click event.
		 *
		 * @param e
		 * @private
		 */
		_onClickInsertText: function (e) {
			var $source = $(e.currentTarget),
				sourceData = $source.data(),
				sourceText = sourceData.insertText || ($source.is(':input:not(:button)') ? $source.val() : $source.text()),
				target = (sourceData.insertTarget instanceof Array ?
					sourceData.insertTarget.join(',') :
					sourceData.insertTarget),
					$target = $(target);
			// Exit if the target is not an existing DOM element
			if ($target.length > 1) {
				var $currentTarget = this.$body.find('[data-insert-target-scope="true"]');
				if ($currentTarget.length) {
					$target = $currentTarget;
				}
			}
			if (!$target) {
				return;
			}
			// Get the text from a specific source element
			if (sourceData.insertSource) {
				var $specificSource = $(sourceData.insertSource);
				sourceText = $specificSource.is(':input:not(:button)') ? $specificSource.val() : $specificSource.text();
			}
			// Handle TinyMCE or default form input control
			if ($target.is('[data-tinymce-options]')) {
				tinyMCE.activeEditor.execCommand('mceInsertContent', false, sourceText);
			} else {
				var targetText = $target.val(),
					caretPos = $target.get(0).selectionStart;

				if (sourceData.insertReplace === true) {
					$target.val(sourceText);
				} else {
					$target.val(targetText.substring(0, caretPos) + sourceText + targetText.substring(caretPos));
				}
			}
			// Trigger custom events
			if (sourceData.insertTriggerEvent) {
				$target.trigger(sourceData.insertTriggerEvent);
			}
		},

		/**
		 * Handles copying the value to a target on input event.
		 *
		 * @param e
		 * @private
		 */
		_onInputCopyValue: function (e) {
			var $source = $(e.currentTarget),
				sourceData = $source.data(),
				sourceValue = $source.val(),
				copyValues = $.isPlainObject(sourceData.copyValue) ? sourceData.copyValue : {original: sourceData.copyValue};

			$.each(copyValues, function (type, target) {
				var $target = $(target);

				if (type === 'original') {
					$target.val(sourceValue);
				} else if (type === 'hyphenate') {
					$target.val(mainApp.removeDiacritics(sourceValue).toLowerCase().replace(/[^a-zA-Z0-9]+/g, '-'));
				}
			});
		},

		/**
		 * Handles global toggle visibility input change event.
		 *
		 * @param e
		 * @private
		 */
		_onChangeToggleVisibility: function (e) {
			var $source = $(e.currentTarget),
				sourceData = $source.data(),
				sourceVal = $source.val(),
				toggleVisibilityVal = sourceData.toggleVisibilityVal,
				$scope = $(sourceData.toggleVisibility),
				$allTargets = $(),
				$targets = $();

			if ($.isArray(toggleVisibilityVal) || $.isPlainObject(toggleVisibilityVal)) {
				if (!$scope.length) {
					$scope = this.$body;
				}
				$targets = $scope.find(toggleVisibilityVal[sourceVal]);
				$.each(toggleVisibilityVal, function (value, target) {
					$allTargets = $allTargets.add($scope.find(target));
				});
				$allTargets.addClass('hidden');
				$targets.removeClass('hidden');
			} else {
				$targets = $scope;
				if (toggleVisibilityVal === ':isset') {
					$targets.toggleClass('hidden', sourceVal !== '');
				} else if (toggleVisibilityVal === ':empty') {
					$targets.toggleClass('hidden', sourceVal === '');
				} else if ($source.is(':checkbox')) {
					$targets.toggleClass('hidden', !$source.prop('checked'));
				} else {
					$targets.toggleClass('hidden', sourceVal != toggleVisibilityVal);
				}
			}
		},

		/**
		 * Handles global invalidate field value change event.
		 *
		 * @param e
		 * @private
		 */
		_onChangeInvalidateField: function (e) {
			var $source = $(e.currentTarget),
				sourceData = $source.data(),
				$targets = $(sourceData.invalidateField);

			$targets.each(function (index, target) {
				var $target = $(target);

				if ($target.is(':checkbox') || $target.is(':radio')) {
					$target.prop('checked', false);
				} else {
					$target.val('');
				}
			});
		},

		/**
		 * Handles checkbox multiple selection change event.
		 *
		 * @param e
		 * @private
		 */
		_onChangeMarkAll: function (e) {
			var $target = $(e.currentTarget),
				targetData = $target.data(),
				selector = targetData.markAllChild || targetData.markAll,
				$parent = $('[data-mark-all="' + selector + '"]'),
				$children = $(selector);
			// Find the children by data attribute
			if (!$children.length) {
				$children = $('[data-mark-all-child="' + selector + '"]');
			}
			// Exit if parent or children does not exist
			if (!$parent.length || !$children.length) {
				return;
			}
			// If the target is the check all control
			if ($target.is($parent)) {
				// Set all children checkboxes checked state
				$children.prop('checked', $target.prop('checked'));
			} else {
				// Set the check all control checked state based on the number of the children selected checkboxes
				$parent.prop('checked', $children.length === $children.filter(':checked').length);
				// Check if there is a root checkbox
				if (targetData.markAllRoot) {
					$parent = $('[data-mark-all="' + targetData.markAllRoot + '"]');
					$children = $('[data-mark-all-root="' + targetData.markAllRoot + '"]');
					$parent.prop('checked', $children.length === $children.filter(':checked').length);
				}
			}
		},

		/**
		 * Handles global PIN processing to obtain date of birth, age and gender.
		 *
		 * @param e
		 * @private
		 */
		_onProcessPin: function (e) {
			var $source = $(e.currentTarget),
				sourceData = $source.data('processPin'),
				sourceValue = $source.val(),
				$dateOfBirth = this.$body.find(sourceData.dateOfBirth),
				$gender = this.$body.find(sourceData.gender),
				$age = this.$body.find(sourceData.age),
				gender, year, month, day, dateOfBirth, age;

			if (!sourceValue.length || sourceValue.length < 7) {
				return;
			}

			year = +sourceValue.substr(1, 2);
			month = sourceValue.substr(3, 2);
			day = sourceValue.substr(5, 2);
			dateOfBirth = moment([year, month, day], 'YY-MM-DD');
			age = moment().diff(dateOfBirth, 'years');
			gender = +sourceValue[0];

			if ($dateOfBirth.length && dateOfBirth.isValid()) {
				if ($dateOfBirth.is('[data-datetimepicker-options]')) {
					dateOfBirth = dateOfBirth.format(window[$dateOfBirth.attr('data-datetimepicker-options')].format);
				}
				if ($dateOfBirth.is(':input')) {
					$dateOfBirth.val(dateOfBirth).trigger('change');
				} else {
					$dateOfBirth.text(dateOfBirth);
				}
			}

			if ($gender.length) {
				if ($gender.length > 1 && $gender.is(':radio')) {
					// Male
					if ([1, 3, 5, 7].indexOf(gender) !== -1) {
						gender = 1;
					}
					// Female
					if ([2, 4, 6, 8].indexOf(gender) !== -1) {
						gender = 2;
					}
					$gender.filter('[value="' + gender +'"]').prop('checked', true);
				} else {
					$gender.val(gender).trigger('change');
				}
			}

			if ($age.length) {
				if ($age.is(':input')) {
					$age.val(age).trigger('change');
				} else {
					$age.text(age);
				}
			}
		},

		/**
		 * Handles scroll to target click event.
		 *
		 * @param e
		 * @private
		 */
		_onScrollToClick: function (e) {
			var $target = $(e.currentTarget),
				targetData = $target.data();

			this.scrollTo(targetData.scrollTo, targetData.scrollOffset);
		},

		/**
		 * Scrolls the window to a target with a provided speed.
		 *
		 * @param target
		 * @param offset
		 * @param speed
		 */
		scrollTo: function (target, offset, speed) {
			var offsetTop = 0;
			// Try to find the DOM element based on a string
			if (typeof target === 'string') {
				var $target = $(target);
				if ($target.length) {
					target = $target;
				}
			}
			// Handle number and jQuery element for the target
			if (typeof target === 'number') {
				offsetTop = target;
			} else if (target instanceof jQuery) {
				offsetTop = target.offset().top;
			}
			// Scroll to the specified target
			this.$scrollElement.animate({
				scrollTop: offset ? (offsetTop - offset) : offsetTop
			}, speed || 1000);
		},

		/**
		 * Removes the diacritics from a source string.
		 *
		 * @param str
		 * @return string
		 */
		removeDiacritics: function (str) {
			var diacriticsMap = [
				{'base':'A', 'letters':/[\u0041\u24B6\uFF21\u00C0\u00C1\u00C2\u1EA6\u1EA4\u1EAA\u1EA8\u00C3\u0100\u0102\u1EB0\u1EAE\u1EB4\u1EB2\u0226\u01E0\u00C4\u01DE\u1EA2\u00C5\u01FA\u01CD\u0200\u0202\u1EA0\u1EAC\u1EB6\u1E00\u0104\u023A\u2C6F]/g},
				{'base':'AA','letters':/[\uA732]/g},
				{'base':'AE','letters':/[\u00C6\u01FC\u01E2]/g},
				{'base':'AO','letters':/[\uA734]/g},
				{'base':'AU','letters':/[\uA736]/g},
				{'base':'AV','letters':/[\uA738\uA73A]/g},
				{'base':'AY','letters':/[\uA73C]/g},
				{'base':'B', 'letters':/[\u0042\u24B7\uFF22\u1E02\u1E04\u1E06\u0243\u0182\u0181]/g},
				{'base':'C', 'letters':/[\u0043\u24B8\uFF23\u0106\u0108\u010A\u010C\u00C7\u1E08\u0187\u023B\uA73E]/g},
				{'base':'D', 'letters':/[\u0044\u24B9\uFF24\u1E0A\u010E\u1E0C\u1E10\u1E12\u1E0E\u0110\u018B\u018A\u0189\uA779]/g},
				{'base':'DZ','letters':/[\u01F1\u01C4]/g},
				{'base':'Dz','letters':/[\u01F2\u01C5]/g},
				{'base':'E', 'letters':/[\u0045\u24BA\uFF25\u00C8\u00C9\u00CA\u1EC0\u1EBE\u1EC4\u1EC2\u1EBC\u0112\u1E14\u1E16\u0114\u0116\u00CB\u1EBA\u011A\u0204\u0206\u1EB8\u1EC6\u0228\u1E1C\u0118\u1E18\u1E1A\u0190\u018E]/g},
				{'base':'F', 'letters':/[\u0046\u24BB\uFF26\u1E1E\u0191\uA77B]/g},
				{'base':'G', 'letters':/[\u0047\u24BC\uFF27\u01F4\u011C\u1E20\u011E\u0120\u01E6\u0122\u01E4\u0193\uA7A0\uA77D\uA77E]/g},
				{'base':'H', 'letters':/[\u0048\u24BD\uFF28\u0124\u1E22\u1E26\u021E\u1E24\u1E28\u1E2A\u0126\u2C67\u2C75\uA78D]/g},
				{'base':'I', 'letters':/[\u0049\u24BE\uFF29\u00CC\u00CD\u00CE\u0128\u012A\u012C\u0130\u00CF\u1E2E\u1EC8\u01CF\u0208\u020A\u1ECA\u012E\u1E2C\u0197]/g},
				{'base':'J', 'letters':/[\u004A\u24BF\uFF2A\u0134\u0248]/g},
				{'base':'K', 'letters':/[\u004B\u24C0\uFF2B\u1E30\u01E8\u1E32\u0136\u1E34\u0198\u2C69\uA740\uA742\uA744\uA7A2]/g},
				{'base':'L', 'letters':/[\u004C\u24C1\uFF2C\u013F\u0139\u013D\u1E36\u1E38\u013B\u1E3C\u1E3A\u0141\u023D\u2C62\u2C60\uA748\uA746\uA780]/g},
				{'base':'LJ','letters':/[\u01C7]/g},
				{'base':'Lj','letters':/[\u01C8]/g},
				{'base':'M', 'letters':/[\u004D\u24C2\uFF2D\u1E3E\u1E40\u1E42\u2C6E\u019C]/g},
				{'base':'N', 'letters':/[\u004E\u24C3\uFF2E\u01F8\u0143\u00D1\u1E44\u0147\u1E46\u0145\u1E4A\u1E48\u0220\u019D\uA790\uA7A4]/g},
				{'base':'NJ','letters':/[\u01CA]/g},
				{'base':'Nj','letters':/[\u01CB]/g},
				{'base':'O', 'letters':/[\u004F\u24C4\uFF2F\u00D2\u00D3\u00D4\u1ED2\u1ED0\u1ED6\u1ED4\u00D5\u1E4C\u022C\u1E4E\u014C\u1E50\u1E52\u014E\u022E\u0230\u00D6\u022A\u1ECE\u0150\u01D1\u020C\u020E\u01A0\u1EDC\u1EDA\u1EE0\u1EDE\u1EE2\u1ECC\u1ED8\u01EA\u01EC\u00D8\u01FE\u0186\u019F\uA74A\uA74C]/g},
				{'base':'OI','letters':/[\u01A2]/g},
				{'base':'OO','letters':/[\uA74E]/g},
				{'base':'OU','letters':/[\u0222]/g},
				{'base':'P', 'letters':/[\u0050\u24C5\uFF30\u1E54\u1E56\u01A4\u2C63\uA750\uA752\uA754]/g},
				{'base':'Q', 'letters':/[\u0051\u24C6\uFF31\uA756\uA758\u024A]/g},
				{'base':'R', 'letters':/[\u0052\u24C7\uFF32\u0154\u1E58\u0158\u0210\u0212\u1E5A\u1E5C\u0156\u1E5E\u024C\u2C64\uA75A\uA7A6\uA782]/g},
				{'base':'S', 'letters':/[\u0053\u24C8\uFF33\u1E9E\u015A\u1E64\u015C\u1E60\u0160\u1E66\u1E62\u1E68\u0218\u015E\u2C7E\uA7A8\uA784]/g},
				{'base':'T', 'letters':/[\u0054\u24C9\uFF34\u1E6A\u0164\u1E6C\u021A\u0162\u1E70\u1E6E\u0166\u01AC\u01AE\u023E\uA786]/g},
				{'base':'TZ','letters':/[\uA728]/g},
				{'base':'U', 'letters':/[\u0055\u24CA\uFF35\u00D9\u00DA\u00DB\u0168\u1E78\u016A\u1E7A\u016C\u00DC\u01DB\u01D7\u01D5\u01D9\u1EE6\u016E\u0170\u01D3\u0214\u0216\u01AF\u1EEA\u1EE8\u1EEE\u1EEC\u1EF0\u1EE4\u1E72\u0172\u1E76\u1E74\u0244]/g},
				{'base':'V', 'letters':/[\u0056\u24CB\uFF36\u1E7C\u1E7E\u01B2\uA75E\u0245]/g},
				{'base':'VY','letters':/[\uA760]/g},
				{'base':'W', 'letters':/[\u0057\u24CC\uFF37\u1E80\u1E82\u0174\u1E86\u1E84\u1E88\u2C72]/g},
				{'base':'X', 'letters':/[\u0058\u24CD\uFF38\u1E8A\u1E8C]/g},
				{'base':'Y', 'letters':/[\u0059\u24CE\uFF39\u1EF2\u00DD\u0176\u1EF8\u0232\u1E8E\u0178\u1EF6\u1EF4\u01B3\u024E\u1EFE]/g},
				{'base':'Z', 'letters':/[\u005A\u24CF\uFF3A\u0179\u1E90\u017B\u017D\u1E92\u1E94\u01B5\u0224\u2C7F\u2C6B\uA762]/g},
				{'base':'a', 'letters':/[\u0061\u24D0\uFF41\u1E9A\u00E0\u00E1\u00E2\u1EA7\u1EA5\u1EAB\u1EA9\u00E3\u0101\u0103\u1EB1\u1EAF\u1EB5\u1EB3\u0227\u01E1\u00E4\u01DF\u1EA3\u00E5\u01FB\u01CE\u0201\u0203\u1EA1\u1EAD\u1EB7\u1E01\u0105\u2C65\u0250]/g},
				{'base':'aa','letters':/[\uA733]/g},
				{'base':'ae','letters':/[\u00E6\u01FD\u01E3]/g},
				{'base':'ao','letters':/[\uA735]/g},
				{'base':'au','letters':/[\uA737]/g},
				{'base':'av','letters':/[\uA739\uA73B]/g},
				{'base':'ay','letters':/[\uA73D]/g},
				{'base':'b', 'letters':/[\u0062\u24D1\uFF42\u1E03\u1E05\u1E07\u0180\u0183\u0253]/g},
				{'base':'c', 'letters':/[\u0063\u24D2\uFF43\u0107\u0109\u010B\u010D\u00E7\u1E09\u0188\u023C\uA73F\u2184]/g},
				{'base':'d', 'letters':/[\u0064\u24D3\uFF44\u1E0B\u010F\u1E0D\u1E11\u1E13\u1E0F\u0111\u018C\u0256\u0257\uA77A]/g},
				{'base':'dz','letters':/[\u01F3\u01C6]/g},
				{'base':'e', 'letters':/[\u0065\u24D4\uFF45\u00E8\u00E9\u00EA\u1EC1\u1EBF\u1EC5\u1EC3\u1EBD\u0113\u1E15\u1E17\u0115\u0117\u00EB\u1EBB\u011B\u0205\u0207\u1EB9\u1EC7\u0229\u1E1D\u0119\u1E19\u1E1B\u0247\u025B\u01DD]/g},
				{'base':'f', 'letters':/[\u0066\u24D5\uFF46\u1E1F\u0192\uA77C]/g},
				{'base':'g', 'letters':/[\u0067\u24D6\uFF47\u01F5\u011D\u1E21\u011F\u0121\u01E7\u0123\u01E5\u0260\uA7A1\u1D79\uA77F]/g},
				{'base':'h', 'letters':/[\u0068\u24D7\uFF48\u0125\u1E23\u1E27\u021F\u1E25\u1E29\u1E2B\u1E96\u0127\u2C68\u2C76\u0265]/g},
				{'base':'hv','letters':/[\u0195]/g},
				{'base':'i', 'letters':/[\u0069\u24D8\uFF49\u00EC\u00ED\u00EE\u0129\u012B\u012D\u00EF\u1E2F\u1EC9\u01D0\u0209\u020B\u1ECB\u012F\u1E2D\u0268\u0131]/g},
				{'base':'j', 'letters':/[\u006A\u24D9\uFF4A\u0135\u01F0\u0249]/g},
				{'base':'k', 'letters':/[\u006B\u24DA\uFF4B\u1E31\u01E9\u1E33\u0137\u1E35\u0199\u2C6A\uA741\uA743\uA745\uA7A3]/g},
				{'base':'l', 'letters':/[\u006C\u24DB\uFF4C\u0140\u013A\u013E\u1E37\u1E39\u013C\u1E3D\u1E3B\u017F\u0142\u019A\u026B\u2C61\uA749\uA781\uA747]/g},
				{'base':'lj','letters':/[\u01C9]/g},
				{'base':'m', 'letters':/[\u006D\u24DC\uFF4D\u1E3F\u1E41\u1E43\u0271\u026F]/g},
				{'base':'n', 'letters':/[\u006E\u24DD\uFF4E\u01F9\u0144\u00F1\u1E45\u0148\u1E47\u0146\u1E4B\u1E49\u019E\u0272\u0149\uA791\uA7A5]/g},
				{'base':'nj','letters':/[\u01CC]/g},
				{'base':'o', 'letters':/[\u006F\u24DE\uFF4F\u00F2\u00F3\u00F4\u1ED3\u1ED1\u1ED7\u1ED5\u00F5\u1E4D\u022D\u1E4F\u014D\u1E51\u1E53\u014F\u022F\u0231\u00F6\u022B\u1ECF\u0151\u01D2\u020D\u020F\u01A1\u1EDD\u1EDB\u1EE1\u1EDF\u1EE3\u1ECD\u1ED9\u01EB\u01ED\u00F8\u01FF\u0254\uA74B\uA74D\u0275]/g},
				{'base':'oi','letters':/[\u01A3]/g},
				{'base':'ou','letters':/[\u0223]/g},
				{'base':'oo','letters':/[\uA74F]/g},
				{'base':'p','letters':/[\u0070\u24DF\uFF50\u1E55\u1E57\u01A5\u1D7D\uA751\uA753\uA755]/g},
				{'base':'q','letters':/[\u0071\u24E0\uFF51\u024B\uA757\uA759]/g},
				{'base':'r','letters':/[\u0072\u24E1\uFF52\u0155\u1E59\u0159\u0211\u0213\u1E5B\u1E5D\u0157\u1E5F\u024D\u027D\uA75B\uA7A7\uA783]/g},
				{'base':'s','letters':/[\u0073\u24E2\uFF53\u00DF\u015B\u1E65\u015D\u1E61\u0161\u1E67\u1E63\u1E69\u0219\u015F\u023F\uA7A9\uA785\u1E9B]/g},
				{'base':'t','letters':/[\u0074\u24E3\uFF54\u1E6B\u1E97\u0165\u1E6D\u021B\u0163\u1E71\u1E6F\u0167\u01AD\u0288\u2C66\uA787]/g},
				{'base':'tz','letters':/[\uA729]/g},
				{'base':'u','letters':/[\u0075\u24E4\uFF55\u00F9\u00FA\u00FB\u0169\u1E79\u016B\u1E7B\u016D\u00FC\u01DC\u01D8\u01D6\u01DA\u1EE7\u016F\u0171\u01D4\u0215\u0217\u01B0\u1EEB\u1EE9\u1EEF\u1EED\u1EF1\u1EE5\u1E73\u0173\u1E77\u1E75\u0289]/g},
				{'base':'v','letters':/[\u0076\u24E5\uFF56\u1E7D\u1E7F\u028B\uA75F\u028C]/g},
				{'base':'vy','letters':/[\uA761]/g},
				{'base':'w','letters':/[\u0077\u24E6\uFF57\u1E81\u1E83\u0175\u1E87\u1E85\u1E98\u1E89\u2C73]/g},
				{'base':'x','letters':/[\u0078\u24E7\uFF58\u1E8B\u1E8D]/g},
				{'base':'y','letters':/[\u0079\u24E8\uFF59\u1EF3\u00FD\u0177\u1EF9\u0233\u1E8F\u00FF\u1EF7\u1E99\u1EF5\u01B4\u024F\u1EFF]/g},
				{'base':'z','letters':/[\u007A\u24E9\uFF5A\u017A\u1E91\u017C\u017E\u1E93\u1E95\u01B6\u0225\u0240\u2C6C\uA763]/g}
			];

			for (var i = 0, len = diacriticsMap.length; i < len; i++) {
				str = str.replace(diacriticsMap[i].letters, diacriticsMap[i].base);
			}

			return str;
		}
	};
})(jQuery, window, document);

/**
 * Document ready
 */
$(document).ready(function () {
	autoFillApp.init();
	seoHelperApp.init();
	mainApp.init();

	$(document).on('click', '.toggle-secret', function () {
		var input = $(this).siblings('input, textarea');
		var isHidden = input.is('input') ? input.attr('type') === 'password' : input.hasClass('secret-hidden');
		if (isHidden) {
			input.is('input') ? input.attr('type', 'text') : input.removeClass('secret-hidden');
			$(this).removeClass('fa-eye-slash').addClass('fa-eye');
		} else {
			input.is('input') ? input.attr('type', 'password') : input.addClass('secret-hidden');
			$(this).removeClass('fa-eye').addClass('fa-eye-slash');
		}
	});
});
