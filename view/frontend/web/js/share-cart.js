/**
 * Share-cart control.
 *
 * @copyright Copyright (c) the Commerce modules authors
 * @license   OSL-3.0 https://opensource.org/licenses/OSL-3.0
 */
define([
    'jquery',
    'mage/translate',
    'mage/storage',
    'Magento_Ui/js/modal/modal',
], function ($, $t, storage) {
    'use strict';

    $.widget('commerce.shareCart', {
        options: {
            generateUrl: '',
            // Milliseconds the "Copied!" acknowledgement stays visible.
            copyFeedbackDuration: 1500,
            selectors: {
                trigger: '[data-share-cart="trigger"]',
                modal: '[data-share-cart="modal"]',
                generate: '[data-share-cart="generate"]',
                copy: '[data-share-cart="copy"]',
                url: '[data-share-cart="url"]',
                error: '[data-share-cart="error"]',
            },
        },

        /** @private */
        _create: function () {
            var selectors = this.options.selectors;

            this.$modal = this.element.find(selectors.modal);
            this.$generate = this.element.find(selectors.generate);
            this.$copy = this.element.find(selectors.copy);
            this.$url = this.element.find(selectors.url);
            this.$error = this.element.find(selectors.error);

            // Guards against double submission: the request is in flight, or a
            // link has already been issued for this cart.
            this.requestInFlight = false;
            this.token = null;

            this._initModal();
            this._on(this.element.find(selectors.trigger), { click: '_openModal' });
            this._on(this.$generate, { click: '_generate' });
            this._on(this.$copy, { click: '_copy' });
        },

        /** @private */
        _initModal: function () {
            this.$modal.modal({
                type: 'popup',
                modalClass: 'share-cart-modal',
                title: $t('Share My Cart'),
                responsive: true,
                buttons: [],
            });
        },

        /** @private */
        _openModal: function (event) {
            event.preventDefault();
            this.$modal.modal('openModal');
        },

        /**
         * Ask the backend for a share link.
         *
         * @private
         */
        _generate: function (event) {
            var self = this;

            event.preventDefault();

            if (this.requestInFlight) {
                return;
            }

            // A link already exists for this cart; re-issuing would orphan a
            // quote snapshot on every click.
            if (this.token !== null) {
                return;
            }

            this.requestInFlight = true;
            this.$generate.prop('disabled', true);
            this._clearError();

            storage
                .post(
                    this.options.generateUrl,
                    JSON.stringify({ form_key: $.mage.cookies.get('form_key') }),
                    true
                )
                .done(function (response) {
                    if (response.error) {
                        self._showError(response.message);

                        return;
                    }

                    self.token = response.token;
                    self.$url.val(response.url);
                    self.$generate.hide();
                    self.$copy.show();
                })
                .fail(function (xhr) {
                    // Surface the server's own message when it sent one, so an
                    // expired form key reads as such rather than as a generic
                    // failure.
                    var message = null;

                    try {
                        message = JSON.parse(xhr.responseText).message;
                    } catch (e) {
                        message = null;
                    }

                    self._showError(message || $t('We could not create a share link. Please try again.'));
                })
                .always(function () {
                    self.requestInFlight = false;
                    self.$generate.prop('disabled', false);
                });
        },

        /**
         * Copy the link to the clipboard.
         *
         * @private
         */
        _copy: function (event) {
            var self = this,
                value = this.$url.val();

            event.preventDefault();

            if (!value) {
                return;
            }

            this._writeToClipboard(value)
                .then(function () {
                    self._acknowledgeCopy();
                })
                .catch(function () {
                    // Selecting the text is a workable fallback: the shopper
                    // can still press Ctrl+C.
                    self.$url.trigger('select');
                    self._showError($t('Press Ctrl+C (Cmd+C on a Mac) to copy the link.'));
                });
        },

        /**
         * @private
         * @return {Promise}
         */
        _writeToClipboard: function (value) {
            // navigator.clipboard is unavailable on insecure origins and in
            // older browsers, so fall back to the legacy command.
            if (window.navigator.clipboard && window.isSecureContext) {
                return window.navigator.clipboard.writeText(value);
            }

            return new Promise(function (resolve, reject) {
                var succeeded;

                this.$url.trigger('select');
                succeeded = window.document.execCommand('copy');

                if (succeeded) {
                    resolve();
                } else {
                    reject(new Error('execCommand("copy") was rejected'));
                }
            }.bind(this));
        },

        /** @private */
        _acknowledgeCopy: function () {
            var self = this,
                original = this.$copy.text();

            this._clearError();
            this.$copy.text($t('Copied!'));

            window.setTimeout(function () {
                self.$copy.text(original);
            }, this.options.copyFeedbackDuration);
        },

        /** @private */
        _showError: function (message) {
            this.$error.text(message).show();
        },

        /** @private */
        _clearError: function () {
            this.$error.text('').hide();
        },
    });

    return $.commerce.shareCart;
});
