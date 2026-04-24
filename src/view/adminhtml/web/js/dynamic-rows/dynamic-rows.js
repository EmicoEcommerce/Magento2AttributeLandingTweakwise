define([
    'Magento_Ui/js/dynamic-rows/dynamic-rows',
    'uiRegistry',
    'ko'
], function (DynamicRows, registry, ko) {
    'use strict';

    return DynamicRows.extend({
        /**
         * @returns {Object}
         */
        initialize: function () {
            this._super();

            this.visible = ko.observable(false);

            registry.get(`${this.parentName}.category_id`, function (categoryField) {
                this.updateVisibility(categoryField.value());

                categoryField.value.subscribe(this.updateVisibility.bind(this));
            }.bind(this));

            return this;
        },

        /**
         * Only show filter_attributes rows when a category is selected
         * @param {Array|String} categoryValue
         */
        updateVisibility: function (categoryValue) {
            if (Array.isArray(categoryValue)) {
                this.visible(false);
                return;
            }

            this.visible(true);
        }
    });
});
