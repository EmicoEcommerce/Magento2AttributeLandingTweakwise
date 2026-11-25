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

        /**
         * Resolves with whether rows were removed once the latest check succeeds, rejects when it fails.
         * @returns {Object}
         */
        refreshAvailableFacets: function () {
            const result = $.Deferred();
            const categoryId = this.source.get('data.category_id');
            const filterTemplate = this.source.get('data.tweakwise_filter_template');
            const storeId = this.source.get('data.store_id');
            const requestId = this.facetRequestId = (this.facetRequestId || 0) + 1;

            // Multiple categories have no single facet context, so nothing is cleaned up
            if (!categoryId || Array.isArray(categoryId)) {
                this.source.set('params.facetValidation', 'valid');
                return result.resolve(false).promise();
            }

            this.source.set('params.facetValidation', 'pending');
            $.ajax({
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
                if (requestId !== this.facetRequestId) {
                    return;
                }
                if (categoryId !== this.source.get('data.category_id') ||
                    filterTemplate !== this.source.get('data.tweakwise_filter_template') ||
                    storeId !== this.source.get('data.store_id')) {
                    this.refreshAvailableFacets().then(
                        (removed) => result.resolve(removed),
                        () => result.reject()
                    );
                    return;
                }
                if (!Array.isArray(options) ||
                    !options.every((option) => option && typeof option.value === 'string')) {
                    this.source.set('params.facetValidation', 'error');
                    this.showFacetError();
                    result.reject();
                    return;
                }
                const removed = this.removeInvalidRecords(options);
                this.source.set('params.facetValidation', 'valid');
                result.resolve(removed);
            }).fail((xhr) => {
                if (requestId === this.facetRequestId) {
                    this.source.set('params.facetValidation', 'error');
                    this.showFacetError(xhr && xhr.responseJSON && xhr.responseJSON.error);
                    result.reject();
                }
            });

            return result.promise();
        },

        /**
         * @param {String} [reason]
         */
        showFacetError: function (reason) {
            const message = $t('Could not check the filters for the selected category and template. Existing filters were not removed.');

            alert({
                content: typeof reason === 'string' && reason ? `${message} ${reason}` : message
            });
        },

        /**
         * @param {Array} options
         * @returns {Boolean} Whether rows were removed
         */
        removeInvalidRecords: function (options) {
            const availableAttributes = options.map((option) => option.value);
            const records = this.recordData() || [];
            const remainingRecords = records.filter((record) => {
                const attribute = record.attribute === 'tw_other' ? record.attribute_other : record.attribute;
                return !attribute || availableAttributes.includes(attribute);
            });

            if (remainingRecords.length === records.length) {
                return false;
            }

            this.recordData(remainingRecords);
            this.reload();
            this.checkSpinner(this.elems());
            this.changed(true);
            alert({
                content: $t('Filter attributes that are no longer available for the selected category and template have been removed.')
            });
            return true;
        }
    });
});
