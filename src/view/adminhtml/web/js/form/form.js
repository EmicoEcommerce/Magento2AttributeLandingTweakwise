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
            const rows = state === 'error' ? registry.get(`${this.name}.general.filter_attributes`) : null;

            if (rows) {
                // A failed retry or removed filters show their own alert, so only continue saving on a clean pass
                rows.refreshAvailableFacets().done((removed) => {
                    if (!removed) {
                        this.save(redirect, data);
                    }
                });
                return;
            }

            if (state === 'pending' || state === 'error') {
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
