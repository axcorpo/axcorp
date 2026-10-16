/**
 * Payment app.
 *
 * @author Tree Web Solutions <treewebsolutions.com@gmail.com>
 */
(function ($, window, document, undefined) {
	this.paymentApp = {
		/**
		 * @var string The form ID.
		 */
		formId: '#payment-form',

		/**
		 * Initialization.
		 */
		init: function () {
			this.cacheElements();
			this.bindEvents();
		},

		/**
		 * Caches DOM elements.
		 */
		cacheElements: function () {
			this.$window = $(window);
			this.$document = $(document);
			this.$html = $('html');
			this.$body = this.$html.children('body');
			this.$form = $(this.formId);
			this.$packages = this.$form.find('[data-package]');
			this.$paymentMethodField = this.$form.find('[name*="payment_method"]');
			this.$paymentProcessorField = this.$form.find('[name*="payment_processor"]');
			this.$tokenField = this.$form.find('[name*="token"]');
			this.$companyField = this.$form.find('[name*="company_id"]');
			this.$submitBtn = this.$form.find('#btn-submit-payment');

		},

		/**
		 * Binds events.
		 */
		bindEvents: function () {
			this.$window.on('popstate.paymentApp', this._onWindowPopState.bind(this));
			this.$document.on('click.paymentApp', '[data-package]', this._onPackageClick.bind(this));
			this.$document.on('change.paymentApp', '[name*="payment_method"]', this._onPaymentMethodChange.bind(this));
			this.$document.on('change.paymentApp', '[data-feature-price]', this._onFeaturePriceChange.bind(this));
			this.$document.on('submit.paymentApp', this.formId, this._onFormSubmit.bind(this));
		},

		/**
		 * Handles window popstate event.
		 *
		 * @param e
		 * @private
		 */
		_onWindowPopState: function (e) {
			if (this._stripeHandler) {
				this._stripeHandler.close();
			}
		},

		/**
		 * Handles package click event.
		 *
		 * @param e
		 * @private
		 */
		_onPackageClick: function (e) {
			var $target = $(e.currentTarget);

			this.$packages.removeClass('active');
			$target.addClass('active');
			$target.find(':radio').prop('checked', true).trigger('change');
			this.setSubmitButtonAmount($target.find('[data-package-price]').data('packagePrice'));
			if (this.$companyField.length) {
				mainApp.scrollToOffset(this.$companyField.offset().top - 150);
			}
		},

		/**
		 * Handles payment method change event.
		 *
		 * @param e
		 * @private
		 */
		_onPaymentMethodChange: function (e) {
			var $target = $(e.currentTarget),
				targetData = $target.data(),
				$pmHints = this.$form.find('#pm-hints').children(),
				$pmProcessorContainer = this.$form.find('#pp-container'),
				$submitBtnLabels = this.$form.find('button[type="submit"]').children();

			$pmHints.addClass('hidden').filter('[id^="pm-hint-' + $target.val() + '"]').removeClass('hidden');
			$pmProcessorContainer.toggleClass('hidden', !targetData.requiresPaymentProcessor);

			$submitBtnLabels.addClass('hidden');
			if (targetData.requiresPaymentProcessor) {
				$submitBtnLabels.filter('.btn-submit-label-payment').removeClass('hidden');
			} else {
				$submitBtnLabels.filter('.btn-submit-label-submit').removeClass('hidden');
			}
		},

		/**
		 * Handles feature price change event.
		 *
		 * @param e
		 * @private
		 */
		_onFeaturePriceChange: function (e) {
			var priceFormat = this.$submitBtn.data('priceFormat'),
				amount = 0;

			this.$form.find('[data-feature-price]').each(function (index, feature) {
				var $feature = $(feature);

				if ($feature.is(':checkbox') && !$feature.is(':checked')) {
					amount += 0;
				} else {
					amount += (+$feature.val() * (+$feature.data('featurePrice')));
				}
			});

			amount = amount.toFixed(2).replace(/\d(?=(\d{3})+\.)/g, '$&,');
			this.setSubmitButtonAmount(priceFormat.replace(/\d+,\d+|\d+\.\d+/, amount));
		},

		/**
		 * Handles form submit event.
		 *
		 * @param e
		 * @private
		 */
		_onFormSubmit: function (e) {
			if (this.$form.data('isDefaultPrevented') !== false) {
				if (this.getPaymentMethodField().data('requiresPaymentProcessor') === true) {
					var paymentProcessor = this.getPaymentProcessorField().val();
					if (typeof this['paymentVia' + paymentProcessor] === 'function') {
						this['paymentVia' + paymentProcessor].call(this);
					}
					e.preventDefault();
				}
			}
		},

		/**
		 * Gets the app name.
		 *
		 * @return {string}
		 */
		getAppName: function () {
			if (typeof this._appName === 'undefined') {
				this._appName = this.$html.children('head').find('meta[property="og:site_name"]').attr('content');
			}
			return this._appName;
		},

		/**
		 * Gets the app logo.
		 *
		 * @return {string}
		 */
		getAppLogo: function () {
			if (typeof this._appLogo === 'undefined') {
				this._appLogo = this.$html.children('head').find('[property="og:image"]').attr('content');
			}
			return this._appLogo;
		},

		/**
		 * Gets the selected package field.
		 *
		 * @return {*}
		 */
		getPackageField: function () {
			return this.$packages.filter('.active').find('[name*="package_id"]');
		},

		/**
		 * Gets the selected payment method field.
		 *
		 * @return {*}
		 */
		getPaymentMethodField: function () {
			if (this.$paymentMethodField.length > 1) {
				return this.$paymentMethodField.filter(':checked');
			}
			return this.$paymentMethodField;
		},

		/**
		 * Gets the selected payment processor field.
		 *
		 * @return {*}
		 */
		getPaymentProcessorField: function () {
			if (this.$paymentProcessorField.length > 1) {
				return this.$paymentProcessorField.filter(':checked');
			}
			return this.$paymentProcessorField;
		},

		/**
		 * Gets the selected company data.
		 *
		 * @return {*}
		 */
		getCompanyData: function () {
			if (this.$companyField.val() !== '') {
				return this.$companyField.children('option:selected').data();
			}
			return null;
		},

		/**
		 * Sets the amount to the submit button label.
		 *
		 * @param {String} amount
		 */
		setSubmitButtonAmount: function (amount) {
			var $paymentLabelTag = this.$submitBtn.find('.btn-submit-label-payment'),
				$amountTag = $paymentLabelTag.find('.btn-submit-amount');

			if (!$amountTag.length) {
				$amountTag = $('<span/>', {'class': 'btn-submit-amount'}).appendTo($paymentLabelTag);
			}

			$amountTag.text(amount);
		},

		/**
		 * Make payment using Stripe.
		 */
		paymentVia1: function () {
			this.$form.data('isDefaultPrevented', false).submit();
			this.$form.find('button[type="submit"]').prop('disabled', false);
		},

		/**
		 * Make payment using PayPal.
		 */
		paymentVia2: function () {
			alert('PayPal payment is not available right now.');
			this.$form.find('button[type="submit"]').prop('disabled', false);
		},

		/**
		 * Make payment using Twispay.
		 */
		paymentVia3: function () {
			this.$form.data('isDefaultPrevented', false).submit();
			this.$form.find('button[type="submit"]').prop('disabled', false);
		}
	};
})(jQuery, window, document);


/**
 * Document ready
 */
$(document).ready(function () {
	paymentApp.init();
});
