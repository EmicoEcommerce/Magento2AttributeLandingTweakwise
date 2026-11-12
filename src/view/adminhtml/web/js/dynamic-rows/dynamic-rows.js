define([
    'Magento_Ui/js/dynamic-rows/dynamic-rows',
    'uiRegistry',
    'ko',
    'jquery',
    'Magento_Ui/js/modal/alert',
    'mage/translate'
], function (DynamicRows, registry, ko, $, alert, $t) {
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
                categoryField.value.subscribe(this.refreshAvailableFacets.bind(this));
            }.bind(this));

            registry.get(`${this.parentName}.tweakwise_filter_template`, function (templateField) {
                templateField.value.subscribe(this.refreshAvailableFacets.bind(this));
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
        },

        refreshAvailableFacets: function () {
            const categoryId = this.source.get('data.category_id');
            const filterTemplate = this.source.get('data.tweakwise_filter_template');
            const storeId = this.source.get('data.store_id');
            const requestId = this.facetRequestId = (this.facetRequestId || 0) + 1;

            if (!categoryId || Array.isArray(categoryId)) {
                this.source.set('params.facetValidation', 'valid');
                return;
            }

            this.source.set('params.facetValidation', 'pending');
            return $.ajax({
                url: `${this.source.get('admin_url')}tweakwise/ajax/cleanupfacets`,
                type: 'POST',
                dataType: 'json',
                data: {
                    form_key: $('[name="form_key"]').val(),
                    category_id: categoryId,
                    filter_template: filterTemplate,
                    store_id: storeId
                }
            }).done((options) => {
                if (requestId === this.facetRequestId &&
                    categoryId === this.source.get('data.category_id') &&
                    filterTemplate === this.source.get('data.tweakwise_filter_template') &&
                    storeId === this.source.get('data.store_id')) {
                    if (!Array.isArray(options) ||
                        !options.every((option) => option && typeof option.value === 'string')) {
                        this.source.set('params.facetValidation', 'error');
                        this.showFacetError();
                        return;
                    }
                    this.removeInvalidRecords(options);
                    this.source.set('params.facetValidation', 'valid');
                }
            }).fail(() => {
                if (requestId === this.facetRequestId) {
                    this.source.set('params.facetValidation', 'error');
                    this.showFacetError();
                }
            });
        },

        showFacetError: function () {
            alert({
                content: $t('Could not check the filters for the selected category and template. Existing filters were not removed.')
            });
        },

        removeInvalidRecords: function (options) {
            const availableAttributes = options.map((option) => option.value);
            const records = this.recordData() || [];
            const remainingRecords = records.filter((record) => {
                const attribute = record.attribute === 'tw_other' ? record.attribute_other : record.attribute;
                return !attribute || availableAttributes.includes(attribute);
            });

            if (remainingRecords.length !== records.length) {
                this.recordData(remainingRecords);
                this.reload();
                this.changed(true);
            }
        }
    });
});
