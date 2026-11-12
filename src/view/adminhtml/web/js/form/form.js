define([
    'Magento_Ui/js/form/form',
    'Magento_Ui/js/modal/alert',
    'mage/translate',
    'uiRegistry'
], function (Form, alert, $t, registry) {
    'use strict';

    return Form.extend({
        save: function (redirect, data) {
            const state = this.source.get('params.facetValidation');
            if (state === 'pending' || state === 'error') {
                if (state === 'error') {
                    const rows = registry.get(`${this.name}.general.filter_attributes`);
                    if (rows) {
                        rows.refreshAvailableFacets();
                    }
                }
                alert({
                    content: state === 'pending'
                        ? $t('Wait until the filter check has finished before saving.')
                        : $t('The filter check failed. Retry the check and save again when it succeeds.')
                });
                return;
            }

            return this._super(redirect, data);
        }
    });
});
