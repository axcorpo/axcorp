(function ($, window, document, undefined) {
	/**
	 * Constants
	 * @constant {String} PLUGIN_NAME
	 * @constant {String} PLUGIN_VERSION
	 * @constant {String} DATA_KEY
	 * @constant {Object} DEFAULTS
	 */
	var PLUGIN_NAME = 'notification',
		PLUGIN_VERSION = '0.0.1',
		EVENT_NS = '.' + PLUGIN_NAME,
		DATA_KEY = 'plugin_' + PLUGIN_NAME,
		DEFAULTS = {
			url: '/notification-manager/notification/list',
			method: 'GET',
			refreshInterval: 60 * 1000,
			refreshOnInit: false,
			notificationSoundFile: '//' + window.location.hostname + '/audio/notification.mp3',

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
		// Setup the refresh interval
		this._setupRefreshInterval();
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
		this.notificationSound = new Audio(this.options.notificationSoundFile);
	};

	/**
	 * Binds Events.
	 *
	 * @private
	 */
	Plugin.prototype._bindEvents = function () {
		this.$element.on('click' + EVENT_NS, '[data-action]', this._onActionClick.bind(this));
	};

	/**
	 * Handles the action click.
	 * 
	 * @private
	 */
	Plugin.prototype._onActionClick = function (e) {
		var me = this,
			$target = $(e.currentTarget),
			$dropdownMenuList = this.$element.find('.dropdown-menu-list'),
			xhrData = {};

		$dropdownMenuList.overlay();

		$.ajax({
			url: $target.attr('href'),
			method: 'POST',
			data: xhrData
		}).done(function (response) {
			if (response.success) {
				me.refresh();
				if ($target.data('bs.tooltip')) {
					$target.tooltip('destroy');
				}
			}
		}).always(function () {
			$dropdownMenuList.overlay('remove');
		});

		e.preventDefault();
		e.stopPropagation();
	};

	/**
	 * Sets up the refresh interval.
	 *
	 * @private
	 */
	Plugin.prototype._setupRefreshInterval = function () {
		if (this.options.refreshOnInit) {
			this.refresh();
		}
		if (this.refreshInterval) {
			clearInterval(this.refreshInterval);
		}
		this.refreshInterval = setInterval(this.refresh.bind(this), this.options.refreshInterval);
	};

	/**
	 * Loads notifications from the server.
	 */
	Plugin.prototype.refresh = function () {
		var me = this,
			xhrData = {};

		$.ajax({
			url: this.options.url,
			method: this.options.method,
			data: xhrData
		}).done(function (response) {
			if (response.success) {
				me.$element.html(response.data);
				// Play a sound if there are unseen notifications
				if (response.unseenNotifications && response.unseenNotifications !== me.unseenNotifications) {
					var notificationSoundPromise = me.notificationSound.play().then(function () {
						Promise.resolve(notificationSoundPromise);
					}).catch(function () {
						me.notificationSound.pause();
					});
					// Cache the number of the unseen notifications to prevent playing the sound each time a request is made
					me.unseenNotifications = response.unseenNotifications;
				}
			}
		});
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
	 * @function external "jQuery.fn".notification
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
