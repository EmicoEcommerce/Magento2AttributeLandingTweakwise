define([
    'Magento_Ui/js/form/element/multiselect',
    'jquery',
    'mage/url',
    'uiRegistry'
], function (Multiselect, $, urlBuilder, registry) {
    'use strict';

    return Multiselect.extend({
        attributeFieldName: 'attribute',
        otherFieldName: 'attribute_value_other',
        otherValue: 'tw_other',

        initialize: function () {
            this._super();
            this.savedValue = this.normalizeValue(this.value());
            this.subscribeAttributeValue();

            return this;
        },

        subscribeAttributeValue: function () {
            this.value.subscribe(function (newAttributeValue) {
                this.setOtherFieldVisibility(this.normalizeValue(newAttributeValue));
            }.bind(this));
        },

        setInitialValue: function () {
            return this;
        },

        initFromAttribute: function (attribute) {
            const currentValue = this.value() ? this.normalizeValue(this.value()) : this.savedValue;

            this.fetchOptions(attribute).then(() => {
                this.restoreValue(currentValue);
                this.setOtherFieldVisibility(this.normalizeValue(this.value()));
            });
        },

        setOtherFieldVisibility: function (selectedAttributeValues, otherFieldValue = null) {
            registry.get(`${this.parentName}.${this.otherFieldName}`, function (otherField) {
                const values = this.normalizeValue(selectedAttributeValues);
                const otherFieldVisible = values.includes(this.otherValue);

                otherField.disabled(!otherFieldVisible);

                if (values.length && !otherFieldVisible) {
                    otherField.value('');
                    return;
                }

                if (otherFieldValue) {
                    otherField.value(otherFieldValue);
                }
            }.bind(this));
        },

        restoreValue: function (valueToRestore) {
            const valuesToRestore = this.normalizeValue(valueToRestore);

            if (!valuesToRestore.length) {
                this.value([]);
                return;
            }

            const optionValues = this.options().map(function (option) {
                return option.value;
            });

            const existingValues = valuesToRestore.filter(function (value) {
                return optionValues.includes(value);
            });

            const missingValues = valuesToRestore.filter(function (value) {
                return !optionValues.includes(value);
            });

            if (existingValues.length && !missingValues.length) {
                this.value(existingValues);
                return;
            }

            if (missingValues.length) {
                this.value([this.otherValue]);
                this.setOtherFieldVisibility([this.otherValue], missingValues.join(', '));
                return;
            }

            this.value([]);
        },

        normalizeValue: function (value) {
            if (!value) {
                return [];
            }

            if (Array.isArray(value)) {
                return value.filter(function (item) {
                    return item !== null && item !== undefined && item !== '';
                });
            }

            if (typeof value !== 'string') {
                return [String(value)];
            }

            try {
                const decodedValue = JSON.parse(value);

                if (Array.isArray(decodedValue)) {
                    return decodedValue.filter(function (item) {
                        return item !== null && item !== undefined && item !== '';
                    });
                }
            } catch (e) {
                return [value];
            }

            return [value];
        },

        fetchOptions: function (attribute) {
            const url = `${this.source.get('admin_url')}tweakwise/ajax/facetattributes`;
            const formKey = $('[name="form_key"]').val();
            const filterTemplate = this.source.get('data.tweakwise_filter_template');
            const categoryId = this.source.get('data.category_id');

            return $.ajax({
                url: url,
                type: 'POST',
                data: {
                    form_key: formKey,
                    category_id: categoryId,
                    filter_template: filterTemplate,
                    facet_key: attribute
                }
            }).done(function (response) {
                this.options(response);
            }.bind(this));
        }
    });
});
