(function ($, window, document, undefined) {
	/**
	 * Constants
	 * @constant {String} PLUGIN_NAME
	 * @constant {String} PLUGIN_VERSION
	 * @constant {String} DATA_KEY
	 * @constant {Object} DEFAULTS
	 */
	var PLUGIN_NAME = 'export',
		PLUGIN_VERSION = '0.0.1',
		EVENT_NS = '.' + PLUGIN_NAME,
		DATA_KEY = 'plugin_' + PLUGIN_NAME,
		DEFAULTS = {
			url: '/export-manager/export',
			method: 'POST',
			dataTable: null,
			model: null,
			documentTitle: 'Export',
			allowHtml: false,

			onInit: function () {},
			onDestroy: function () {}
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
		// Initialization
		this.init();
	};

	/**
	 * Initialization
	 */
	Plugin.prototype.init = function () {
		// Cache elements
		this._cacheElements();
		// Bind events
		this._bindEvents();
		// Hook event
		this._hook('onInit');
	};

	/**
	 * Caches DOM Elements.
	 *
	 * @private
	 */
	Plugin.prototype._cacheElements = function () {
		this.$document = $(document);
		this.$body = $(document.body);
		this.$element = $(this.element);
		this.$metaCsrfParam = $('meta[name="csrf-param"]');
		this.$metaCsrfToken = $('meta[name="csrf-token"]');
	};

	/**
	 * Binds Events.
	 *
	 * @private
	 */
	Plugin.prototype._bindEvents = function () {
		this.$element.on('click' + EVENT_NS, '[data-export-format]', this._onExportClick.bind(this));
	};

	/**
	 * Downloads a file from a specified url.
	 *
	 * @param url
	 */
	Plugin.prototype.downloadFile = function (url) {
		var $a = $('<a/>', {
			style: 'display: none;',
			target: '_blank',
			download: '',
			href: url
		}).appendTo(this.$body);

		$a.get(0).click();

		// Remove the anchor from the DOM
		setTimeout(function () {
			$a.remove();
		}, 16);

		return false;
	};

	/**
	 * Gets the DataTable AJAX request parameters.
	 *
	 * @return {*}
	 */
	Plugin.prototype.getDataTableParams = function () {
		var dataTable = this.$body.find(this.options.dataTable).DataTable(),
			ajaxParams = dataTable.ajax.params(),
			columns = dataTable.settings().init().columns,
			columnsOrder = [],
			visibleColumns = [];

		// Try to get the column order from the extension or fallback to the default order
		try {
			columnsOrder = dataTable.colReorder.order();
		} catch (e) {
			columnsOrder = Object.keys(columns);
		}
		visibleColumns = dataTable.columns(columnsOrder).visible();

		// Set custom properties to the columns
		$.each(columnsOrder, function (colOrder, colIndex) {
			ajaxParams.columns[colOrder].title = $('<div/>', {html: columns[colIndex].title}).text().trim();
			ajaxParams.columns[colOrder].visible = visibleColumns[colIndex];
		});

		return ajaxParams;
	};

	/**
	 * Handles export button click event.
	 *
	 * @param e
	 * @private
	 */
	Plugin.prototype._onExportClick = function (e) {
		var me = this,
			$target = $(e.currentTarget),
			targetData = $target.data(),
			xhrData = {
				dataTable: this.getDataTableParams(),
				model: this.options.model,
				title: this.options.title,
				format: targetData.exportFormat,
				config: this.options[targetData.exportFormat]
			};
		xhrData[this.$metaCsrfParam.attr('content')] = this.$metaCsrfToken.attr('content');

		this.$body.overlay();

		$.ajax({
			url: this.options.url,
			method: this.options.method,
			data: xhrData
		}).done(function (response) {
			notify.show(response);
			if (response.success && response.returnUrl) {
				me.downloadFile(response.returnUrl);
			}
		}).always(function () {
			me.$body.overlay('remove');
		});

		e.preventDefault();
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
		this.$element.off(EVENT_NS);
		this.$element.removeData(DATA_KEY);
	};

	/**
	 * Plugin definition
	 * @function external "jQuery.fn".timeslots
	 */
	$.fn[PLUGIN_NAME] = function (options) {
		var args = arguments;

		if (!options || typeof options === "object") {
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

})(jQuery, window, document);
