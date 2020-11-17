import './components/lamps-cryptogate';

import deDE from './snippet/de-DE.json';
import enGB from './snippet/en-GB.json';

const { Module } = Shopware;

Module.register('lamps-cryptogate', {
    type: 'plugin',
    name: 'LampSCryptoGate6',
    title: 'lamps-cryptogate.general.mainMenuItemGeneral',
    description: 'lamps-cryptogate.general.descriptionTextModule',
    version: '1.0.0',
    targetVersion: '1.0.0',
    color: '#9AA8B5',
    icon: 'default-action-settings',

    snippets: {
        'de-DE': deDE,
        'en-GB': enGB
    },

    routes: {
        index: {
            component: 'lamps-cryptogate',
            path: 'index'
        }
    },

    settingsItem: [{ /* this can be a single object if no collection is needed */
        to: 'lamps.cryptogate.index', /* route to anything */
        group: 'plugins', /* either system, shop or plugin */
        icon: 'default-action-settings',

    }]
});
