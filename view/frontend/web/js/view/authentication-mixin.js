/**
 * Adds an inline "Create account" form to the checkout authentication modal.
 * The login flow of the original component is left untouched.
 */
define([
    'jquery',
    'ko',
    'mage/storage',
    'mage/url',
    'mage/translate',
    'Magento_Customer/js/customer-data',
    'Magento_Checkout/js/model/authentication-messages',
    'Magento_Checkout/js/model/full-screen-loader',
    'mage/validation'
], function ($, ko, storage, urlBuilder, $t, customerData, messageContainer, fullScreenLoader) {
    'use strict';

    const REGISTER_PATH = 'checkoutregister/account/create';

    return function (Component) {
        return Component.extend({
            defaults: {
                template: 'GDMexico_CheckoutRegister/authentication'
            },

            /** @inheritdoc */
            initialize: function () {
                this._super();

                this.isRegisterMode = ko.observable(false);

                return this;
            },

            /** Show the registration form inside the modal. */
            showRegister: function () {
                messageContainer.clear();
                this.isRegisterMode(true);
            },

            /** Go back to the login form. */
            showLogin: function () {
                messageContainer.clear();
                this.isRegisterMode(false);
            },

            /**
             * Create the account and, on success, reload the checkout with the customer signed in.
             * The guest cart is merged into the customer cart server side.
             *
             * @param {HTMLElement} registerForm
             */
            register: function (registerForm) {
                const $form = $(registerForm);
                const data = {};

                messageContainer.clear();

                if (!$form.validation() || !$form.validation('isValid')) {
                    return;
                }

                $form.serializeArray().forEach(function (entry) {
                    data[entry.name] = entry.value;
                });

                fullScreenLoader.startLoader();

                storage.post(urlBuilder.build(REGISTER_PATH), JSON.stringify(data), false)
                    .done(function (response) {
                        if (response.errors) {
                            messageContainer.addErrorMessage(response);
                            fullScreenLoader.stopLoader();

                            return;
                        }

                        if (response.requiresConfirmation) {
                            messageContainer.addSuccessMessage(response);
                            fullScreenLoader.stopLoader();

                            return;
                        }

                        // Keep the loader visible until the page reloads.
                        customerData.invalidate(['customer']);
                        window.location.reload();
                    })
                    .fail(function () {
                        messageContainer.addErrorMessage({
                            message: $t('Could not create the account. Please try again later.')
                        });
                        fullScreenLoader.stopLoader();
                    });
            }
        });
    };
});
