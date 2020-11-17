import template from './index.html.twig';

const { Component } = Shopware;

Component.register('lamps-cryptogate', {
    template,

    mixins: [
        'notification',
    ],

    data() {
        return {
            isTestLiveSuccessful: false,
        };
    },

    methods: {
        onTest() {
            this.CryptoPayment.createPaymentUrl(
            ).then((response) => {
                const credentialsValid = response.credentialsValid;

                if (credentialsValid) {
                    this.isTestLiveSuccessful = true;

                }
            }).catch((errorResponse) => {
                if (errorResponse.response.data && errorResponse.response.data.errors) {
                    let message = `<b>${this.$tc('swag-paypal.settingForm.messageTestError')}</b> `;
                    message += errorResponse.response.data.errors.map((error) => {
                        return error.detail;
                    }).join(' / ');

                    this.createNotificationError({
                        title: this.$tc('global.default.error'),
                        message: message
                    });

                    this.isTestLiveSuccessful = false;

                }
            });

        }
    }

    });
