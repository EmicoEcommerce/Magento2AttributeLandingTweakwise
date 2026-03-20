define([
    'Magento_Ui/js/form/element/select',
    'jquery',
    'mage/url',
    'uiRegistry'
], function (Select, $, urlBuilder, registry) {
    return Select.extend({
        attributeFieldName: 'attribute',
        otherFieldName: 'attribute_value_other',
        otherValue: 'tw_other',
        initialize: function () {
            this._super();
            this.savedValue = this.value();
            this.subscribeAttributeValue();

            return this;
        },

        subscribeAttributeValue: function () {
            this.value.subscribe(function (newAttributeValue) {
                this.setOtherFieldVisibility(newAttributeValue);
            }.bind(this));
        },

        setInitialValue: function () {
            return this;
        },

        initFromAttribute: function (attribute) {
            const currentValue = this.value() ? this.value() : this.savedValue;
            this.fetchOptions(attribute).then(() => {
                this.restoreValue(currentValue);
                this.setOtherFieldVisibility(this.value())
            });
        },

        setOtherFieldVisibility: function (selectedAttributeValue) {
            registry.get(`${this.parentName}.${this.otherFieldName}`, function (otherField) {
                const otherFieldVisible = selectedAttributeValue === this.otherValue
                otherField.disabled(!otherFieldVisible);
                if (selectedAttributeValue && !otherFieldVisible) {
                    otherField.value('');
                }
            }.bind(this));
        },

        restoreValue: function (valueToRestore) {
            const optionExists = this.options().some(function (option) {
                return option.value === valueToRestore;
            }.bind(this));

            if (optionExists) {
                this.value(valueToRestore);
            } else {
                const firstOption = this.options()[0];
                if(firstOption) {
                    this.value(firstOption.value);
                }
            }
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
