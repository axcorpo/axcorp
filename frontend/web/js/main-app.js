/**
 * Overlay
 *
 * @author TreeWebSolutions Team <treewebsolutions.com@gmail.com>
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
			template: '<div class="alert alert-{type} alert-icon fade in"><button type="button" class="close" data-dismiss="alert" aria-hidden="true">×</button>{title}{message}</div>'
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

			if (!data.title && !data.message) {
				return;
			}
			options.template = options.template
				.replace('{type}', alertType.type)
				.replace('{title}', data.title || '')
				.replace('{message}', data.message || '');

			$(document.body).children('.alert-' + data.type).remove();

			if (!$container) {
				$(options.template).insertBefore('#page-header');
				return;
			}
			$container.prepend(options.template);
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
		// Bootstrap 5's jQuery interface only creates the instance when it is given an
		// options object; the `show` option is honoured by Bootstrap 3 alone. Showing
		// explicitly works with both.
		this.$modal
			.html(content)
			.appendTo(this.$body)
			.modal({
				backdrop: 'static',
				keyboard: false,
				// Bootstrap 5 traps focus inside the modal. Select2's search box and
				// TinyMCE's dialogs live in <body>, so with the trap on every click into
				// them bounced the focus back to the first control in the modal (the
				// close button) and the dropdown never opened.
				focus: false
			})
			.modal('show');
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
 * Main app
 *
 * @author TreeWebSolutions Team <treewebsolutions.com@gmail.com>
 */
(function ($, window, document, undefined) {
	this.mainApp = {
		preventPageOverlay: false,

		/**
		 * Initialization
		 */
		init: function () {
			this._cacheElements();
			this._initPlugins();
			this._bindEvents();

			this.$window.trigger('scroll.mainApp');
			if (this.$body.hasClass('home-page')) {
				this.removeWindowHash();
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
			this.$scrollElement = this.$html;
			this.$scrollElement = this.$scrollElement.add(this.$body);
			this.$btnScrollTop = this.$body.find('#btn-scroll-top');
			this.$formInputWatch = this.$body.find('.form-input-watch');
			this.$navTabsHash = this.$body.find('.nav-tabs-hash');
			this.$navbarMenu = this.$body.find('#navbar-menu-collapse');
			this.$navbarMenuItems = this.$navbarMenu.find('.nav a').map(function (index, a) {
				var $a = $(a);
				return {
					$a: $a,
					$li: $a.closest('li'),
					$target: $(a.hash)
				};
			});
		},

		/**
		 * Initializes third party plugins.
		 *
		 * @private
		 */
		_initPlugins: function () {
			if ($.fn.tooltip) {
				// Check if Bootstrap 5 is available (has bootstrap.Tooltip)
				var isBootstrap5 = typeof bootstrap !== 'undefined' && typeof bootstrap.Tooltip !== 'undefined';

				if (isBootstrap5) {
					// For Bootstrap 5, manually initialize only elements with data-toggle (not data-bs-toggle)
					// and check for existing instances to prevent duplicates
					var tooltipElements = this.$body.find('[data-toggle="tooltip"], [data-toggle-extend="tooltip"]').not('[data-bs-toggle="tooltip"]');
					tooltipElements.each(function() {
						// Check if Bootstrap 5 tooltip instance already exists
						var existingInstance = bootstrap.Tooltip.getInstance(this);
						if (!existingInstance) {
							// Initialize using Bootstrap 5 native API
							new bootstrap.Tooltip(this, {
								container: 'body',
								placement: 'auto',
								trigger: 'hover',
								html: true,
								delay: {
									show: 100,
									hide: 50
								}
							});
						}
					});
				} else {
					// For Bootstrap 3/4, use jQuery plugin
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
			}
			if ($.fn.popover) {
				// Check if Bootstrap 5 is available (has bootstrap.Popover)
				var isBootstrap5 = typeof bootstrap !== 'undefined' && typeof bootstrap.Popover !== 'undefined';

				if (isBootstrap5) {
					// For Bootstrap 5, manually initialize only elements with data-toggle (not data-bs-toggle)
					// and check for existing instances to prevent duplicates
					var popoverElements = this.$body.find('[data-toggle="popover"], [data-toggle-extend="popover"]').not('[data-bs-toggle="popover"]');
					popoverElements.each(function() {
						// Check if Bootstrap 5 popover instance already exists
						var existingInstance = bootstrap.Popover.getInstance(this);
						if (!existingInstance) {
							// Initialize using Bootstrap 5 native API
							new bootstrap.Popover(this, {
								container: 'body',
								placement: 'auto',
								trigger: 'focus',
								html: true,
								delay: {
									show: 100,
									hide: 50
								}
							});
						}
					});
				} else {
					// For Bootstrap 3/4, use jQuery plugin
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
			}
			if ($.fn.select2) {
				$.fn.select2.defaults.set('escapeMarkup', function (markup) { return markup; });
			}
			if ($.fn.dataTable) {
				if ($.fn.dataTable.ext) {
					$.fn.dataTable.ext.errMode = 'none';
				}
			}
		},

		/**
		 * Binds global events.
		 *
		 * @private
		 */
		_bindEvents: function () {
			this.$window.on('beforeunload', this._onWindowBeforeUnload.bind(this));
			this.$window.on('scroll.mainApp', this._onWindowScroll.bind(this));
			this.$window.on('hashchange.mainApp', this._onWindowHashChange.bind(this)).trigger('hashchange.mainApp');
			this.$document.on('click.mainApp', '[data-prevent-page-overlay]', this._onPreventPageOverlayClick.bind(this));
			this.$document.on('submit.mainApp', 'form', this._onFormSubmit.bind(this));
			this.$document.on('show.bs.tab', '.nav-tabs-hash [data-toggle="tab"]', this._onHashTabShow.bind(this));
			this.$document.on('show.bs.collapse hide.bs.collapse', '#navbar-menu-collapse', this._onNavMenuToggle.bind(this));
			this.$document.on('click.mainApp touch.mainApp', '.navbar-backdrop', this._onNavBackdropClick.bind(this));
			this.$document.on('click.mainApp', '.skip-link, [data-jump-to]', this._onClickSkipLink.bind(this));
			this.$navbarMenu.on('click.mainApp', 'a', this._onNavBarLinkClick.bind(this));
			this.$document.on('dp.show', '.datetimepicker', this._onDateTimePickerShow.bind(this));
			this.$document.on('click.mainApp', '[data-pricing-package]', this._onPricingPackageClick.bind(this));
			this.$document.on('filebeforedelete', '[data-krajee-fileinput]', this._onFileBeforeDelete.bind(this));
			this.$document.on('click.mainApp', '.btn-subtree-toggle', this._onhandleSubtreeToggle.bind(this));

			// Disable form duplicate submit
			this.$document.on('beforeValidate', 'form', function (e, messages, deferreds) {
				// TinyMCE keeps what was typed inside its own iframe and only writes it back
				// to the textarea on a real form submit. Yii's client validation runs before
				// that, so a required rich-text field read as empty and the form never left
				// the page. Flush every editor into its textarea first.
				if (window.tinymce) {
					tinymce.triggerSave();
				}
				$(e.currentTarget).find(':submit').attr('disabled', true);
			});
			this.$document.on('afterValidate', 'form', function (e, messages, errorAttributes) {
				if (errorAttributes.length > 0) {
					$(e.currentTarget).find(':submit').attr('disabled', false);
				}
			});

			this.$document.on('change', '[data-toggle-visibility]', this._onChangeToggleVisibility.bind(this));
			this.$body.find(':radio[data-toggle-visibility]').each(function(index, radio) {
				var $radio = $(radio),
					data = $radio.data();
				$(':radio[name="' + $radio.attr('name') + '"]').attr({
					'data-toggle-visibility': data.toggleVisibility,
					'data-toggle-visibility-val': data.toggleVisibilityVal,
					'data-toggle-visibility-invalidate-target': data.toggleVisibilityInvalidateTarget,
					'data-toggle-visibility-check-field': data.toggleVisibilityCheckField
				});
			});

			if (this.$formInputWatch.length) {
				this.$formInputWatch.on('input.formInputWatch.mainApp', ':input', this._onFormInputWatch.bind(this));
				this.$formInputWatch.find(':input').trigger('input.formInputWatch.mainApp');
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
		 * Handles window scroll event.
		 *
		 * @param e
		 * @private
		 */
		_onWindowScroll: function (e) {
			this.$body.toggleClass('scroll', this.$window.scrollTop() > 0);
			if (this.$btnScrollTop.length) {
				this.$btnScrollTop.toggleClass('visible', this.$window.scrollTop() > 100);
			}
			if (this.$body.hasClass('home-page')) {
				this.spyMainNav();
			}
		},

		/**
		 * Handles window hash change event.
		 *
		 * @param e
		 * @private
		 */
		_onWindowHashChange: function (e) {
			var hash = window.location.hash;

			if (this.$navTabsHash.length) {
				hash = hash || this.$navTabsHash.children('li').eq(0).children('a').attr('href');
				var $tab = this.$navTabsHash.find('a[href="' + hash + '"]');
				if (!$tab.parent('li').hasClass('active')) {
					$tab.tab('show');
				}
			}
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
		 * Handles form submit global event.
		 *
		 * @param e
		 * @private
		 */
		_onFormSubmit: function (e) {
			this.preventPageOverlay = $(e.currentTarget).css('display') === 'none';
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

			window.location.hash = e.target.hash;

			if ($dataTables.length) {
				$dataTables.DataTable().draw();
			}
		},

		/**
		 * Handles collapse show event for navbar menu.
		 *
		 * @param e
		 * @private
		 */
		_onNavMenuToggle: function (e) {
			this.$body.toggleClass('navbar-menu-open', e.type === 'show');
		},

		/**
		 * Handles navbar backdrop click event.
		 *
		 * @param e
		 * @private
		 */
		_onNavBackdropClick: function (e) {
			this.$navbarMenu.collapse('toggle');
		},

		/**
		 * Handles skip link item click event.
		 *
		 * @param e
		 * @private
		 */
		_onClickSkipLink: function (e) {
			var $source = $(e.currentTarget),
				$target = $($source.prop('hash') || $source.data('jumpTo')),
				targetOffset = $target.position();

			mainApp.$scrollElement.animate({
				scrollTop: targetOffset.top
			}, {
				duration: 800,
				complete: function () {
					// Preserve focus to target container
					$target.attr('tabindex', 0).focus().one('blur', function () {
						// Remove tabindex attribute
						$(this).removeAttr('tabindex');
					});
				}
			});

			e.preventDefault();
		},

		/**
		 * Handles form control watch input event.
		 *
		 * @param e
		 * @private
		 */
		_onFormInputWatch: function (e) {
			var $target = $(e.currentTarget);

			$target.toggleClass('has-value', $target.val() !== '');

			if ($target.is('textarea')) {
				$target.outerHeight(0).outerHeight($target.prop('scrollHeight'));
			}
		},

		/**
		 * Handles main nav anchor click event.
		 *
		 * @param e
		 * @private
		 */
		_onNavBarLinkClick: function (e) {
			var $target = $(e.currentTarget),
				$hashTarget = this.$body.find(e.currentTarget.hash),
				$li = $target.closest('li');

			if ($target.attr('href') === window.location.pathname && this.$body.hasClass('home-page')) {
				$hashTarget = this.$body.find('#section-carousel');
			}
			if ($hashTarget.length) {
				this.scrollTo($hashTarget);
				// $li.siblings('li').removeClass('active').end().addClass('active');
				e.preventDefault();
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
		 * Handles pricing package click event.
		 *
		 * @param e
		 * @private
		 */
		_onPricingPackageClick: function (e) {
			var $target = $(e.currentTarget),
				$form = $target.closest('form'),
				$packages = $form.find('[data-pricing-package]'),
				$customPackageFieldsContainer = $form.find('#custom-package-fields-container'),
				$radioField = $target.find(':radio');

			$packages.removeClass('active');
			$target.addClass('active');
			$radioField.prop('checked', true).trigger('change');
			if ($radioField.data('custom')) {
				$customPackageFieldsContainer.removeClass('hidden');
			} else {
				$customPackageFieldsContainer.addClass('hidden');
			}
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

		_onhandleSubtreeToggle: function (e) {
			var $target = $(e.currentTarget),
				$hasSubtree = $target.closest('.has-subtree');

			$hasSubtree.toggleClass('subtree-open');
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
				invalidateField = sourceData.toggleVisibilityInvalidateField,
				checkField = sourceData.toggleVisibilityCheckField,
				$scope = $(sourceData.toggleVisibility),
				$allTargets = $(),
				$targets = $(),
				state = false;

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
					state = sourceVal !== '';
				} else if (toggleVisibilityVal === ':empty') {
					state = sourceVal === '';
				} else if ($source.is(':checkbox')) {
					state = !$source.prop('checked');
				} else {
					state = sourceVal !== toggleVisibilityVal;
				}
				$targets.toggleClass('hidden', state);
				if (typeof invalidateField !== 'undefined') {
					var $invalidateField = $(invalidateField);
					if (state) {
						$invalidateField.data('invalidateFieldValue', $invalidateField.val()).val('');
					} else {
						$invalidateField.val($invalidateField.data('invalidateFieldValue') || '');
					}
				}
				if (typeof checkField !== 'undefined') {
					var $checkField = $(checkField);
					if ($checkField.is(':checkbox') || $checkField.is(':radio')) {
						$checkField.prop('checked', state);
					} else {
						$checkField.val(+(!state));
					}
				}
			}
		},

		/**
		 * Animates the scroll to a specific offset.
		 *
		 * @param offset
		 * @param speed
		 */
		scrollToOffset: function (offset, speed) {
			this.$scrollElement.animate({
				scrollTop: offset || 0
			}, speed || 800);
		},

		/**
		 * Animates the scroll to a specific element top offset.
		 *
		 * @param $target
		 * @param speed
		 */
		scrollTo: function ($target, speed) {
			this.scrollToOffset($target.offset().top, speed);
		},

		/**
		 * Removes the window hash.
		 *
		 * @link https://stackoverflow.com/a/5298684
		 */
		removeWindowHash: function () {
			var scrollV,
				scrollH,
				loc = window.location;

			if ('pushState' in window.history) {
				history.replaceState('', document.title, loc.pathname + loc.search);
			} else {
				scrollV = document.body.scrollTop;
				scrollH = document.body.scrollLeft;

				loc.hash = '';

				document.body.scrollTop = scrollV;
				document.body.scrollLeft = scrollH;
			}
		},

		/**
		 * Toggles active CSS class for menu items based on the window scroll.
		 *
		 * @return {*}
		 */
		spyMainNav: function () {
			var offset = 150,
				scrollTop = this.$html.scrollTop() + offset;

			if ((scrollTop - offset) <= offset) {
				return this.$navbarMenuItems[0].$li
					.siblings('li')
					.removeClass('active')
					.end()
					.addClass('active');
			}

			this.$navbarMenuItems.each(function (index, item) {
				if (item.$target.length && (scrollTop >= item.$target.position().top)) {
					item.$li
						.siblings('li')
						.removeClass('active')
						.end()
						.addClass('active');
				}
			});
		},

		/**
		 * Checks for sticky objects.
		 */
		checkForStickyObjects: function () {
			var me = this,
				windowScrollTop = me.$window.scrollTop();

			this.$body.find('[data-sticky-on-scroll="true"]').each(function (index, item) {
				var $item = $(item),
					data = $item.data(),
					isSticky = (windowScrollTop + 90) >= $(data.stickyTopObject).offset().top;

				if (me.$window.outerWidth() < 992 || !isSticky) {
					$item.removeClass('on').css({
						width: 'auto',
						top: 'auto'
					});
					return;
				}

				// https://stackoverflow.com/a/8886696
				var navbarTopHeight = me.$navbarTop.outerHeight() + 16,
					stickySidebarHeight = $item.outerHeight(),
					topOfFooter = me.$pageFooter.position().top,
					scrollDistanceFromTopOfDoc = windowScrollTop + navbarTopHeight + stickySidebarHeight,
					scrollDistanceFromTopOfFooter = (scrollDistanceFromTopOfDoc - topOfFooter) + 16,
					top = navbarTopHeight;

				if (scrollDistanceFromTopOfDoc > topOfFooter) {
					top -= scrollDistanceFromTopOfFooter;
				}

				$item.toggleClass('on', isSticky).css({
					width: $item.closest('.sticky-sidebar-container').width(),
					top: top
				});
			});
		},

		/**
		 * App formatter.
		 */
		formatter: {
			asCurrency: function (value, format) {
				if (typeof value === 'undefined' || typeof format === 'undefined') {
					return '';
				}
				return format.replace(/\d+,\d+|\d+\.\d+/, (+value).toFixed(2).replace(/\d(?=(\d{3})+\.)/g, '$&,'));
			}
		}
	};
})(jQuery, window, document);

/**
 * Document ready
 */
$(document).ready(function () {
	autoFillApp.init();
	mainApp.init();
});

/**
 * Honeypot (.work-email): Chrome's autofill and password manager fill it with the user's
 * saved email even though it is off screen, and the server then rejects the
 * form as a bot. Browsers mark their own fills with :autofill; a bot setting the value
 * never gets that mark, so only what the browser filled is cleared, right before submit.
 */
(function (document) {
	function isAutofilled(input) {
		try {
			return input.matches(':autofill');
		} catch (e) {
		}
		try {
			return input.matches(':-webkit-autofill');
		} catch (e) {
		}
		return false;
	}

	// Capture phase, so it runs before yiiActiveForm serializes the form.
	document.addEventListener('submit', function (event) {
		var inputs = event.target.querySelectorAll('.work-email input');
		for (var i = 0; i < inputs.length; i++) {
			if (isAutofilled(inputs[i])) {
				inputs[i].value = '';
			}
		}
	}, true);
})(document);
