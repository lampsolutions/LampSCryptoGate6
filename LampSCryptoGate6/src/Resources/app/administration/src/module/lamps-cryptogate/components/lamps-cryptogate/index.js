import template from './index.html.twig';
import LampsCryptogateApiCredentialsService from "../../../../init/api-service";

const { Component } = Shopware;

Component.register('lamps-cryptogate', {
    template,

    inject: [
        'LampsCryptogateApiCredentialsService'
    ],

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
            this.LampsCryptogateApiCredentialsService.checkCredentials(
            ).then((response) => {
                const credentialsValid = response.credentialsValid;

                if (credentialsValid) {
                    let message = `<b>${this.$tc('lamps-cryptogate.settingForm.messageSuccess')}</b> `;
                    document.getElementById("result").innerHTML="<h2>"+message+"</h2>";
                }
                else{
                    let message = `<b>${this.$tc('lamps-cryptogate.settingForm.errorMessage')}</b> `;
                    document.getElementById("result").innerHTML="<h2>"+message+"</h2>";
                }
            }).catch((errorResponse) => {
                if (errorResponse.response.data && errorResponse.response.data.errors) {
                    let message = `<b>${this.$tc('lamps-cryptogate.settingForm.errorMessage')}</b> `;
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
