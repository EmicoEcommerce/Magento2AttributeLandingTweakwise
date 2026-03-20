define([
    'Magento_Ui/js/form/element/select',
    'jquery',
    'mage/url',
    'uiRegistry'
], function (Select, $, urlBuilder, registry) {
    return Select.extend({
        attributeValueFieldName: 'value',
        otherFieldName: 'attribute_other',
        otherValue: 'tw_other',
        initialize: function () {
            this._super();

            this.savedValue = this.value();
            this.value('');

            this.setOtherFieldVisibility(this.savedValue);

            this.fetchOptions().then(function () {
                this.setRestoredValue();
                this.subscribeAttribute();
                this.subscribeFilterTemplate();
                this.subscribeCategoryId();
                this.initAttributeValueField(this.value());
            }.bind(this));

            return this;
        },

        setInitialValue: function () {
            return this;
        },

        initObservable: function () {
            this._super().observe(['value']);
            return this;
        },

        subscribeAttribute: function () {
            this.value.subscribe(function (newAttribute) {
                this.setOtherFieldVisibility(newAttribute);
                this.initAttributeValueField(newAttribute);
            }.bind(this));
        },

        subscribeCategoryId: function () {
            const categoryIdPath = 'emico_attributelanding_page_form.emico_attributelanding_page_form.general.category_id';
            registry.get(categoryIdPath, (categoryField) => {
                categoryField.value.subscribe((newCategoryId) => {
                    const currentAttributeValue = this.value();
                    this.fetchOptions().then(() => {
                        this.restoreValue(currentAttributeValue);
                    });
                });
            });
        },

        subscribeFilterTemplate: function () {
            const filterTemplatePath = 'emico_attributelanding_page_form.emico_attributelanding_page_form.general.tweakwise_filter_template';
            registry.get(filterTemplatePath, (filterTemplateField) => {
                filterTemplateField.value.subscribe((newFilterTemplate) => {
                    const currentAttributeValue = this.value();
                    this.fetchOptions().then(() => {
                        this.restoreValue(currentAttributeValue);
                    });
                });
            });
        },

        setOtherFieldVisibility: function (selectedAttribute) {
            registry.get(`${this.parentName}.${this.otherFieldName}`, function (otherField) {
                const otherFieldVisible = selectedAttribute === this.otherValue
                otherField.disabled(!otherFieldVisible);
                if (!otherFieldVisible) {
                    otherField.value('');
                }
            }.bind(this));
        },

        setRestoredValue: function () {
            if (!this.savedValue) {
                return;
            }

            const optionExists = this.options().some(function (option) {
                return option.value === this.savedValue;
            }.bind(this));

            if (optionExists) {
                this.value(this.savedValue);
            } else {
                const firstOption = this.options()[0];
                if (firstOption) {
                    this.value(firstOption.value);
                }
            }
        },

        restoreValue: function (valueToRestore) {
            const optionExists = this.options().some(function (option) {
                return option.value === valueToRestore;
            }.bind(this));

            if (optionExists) {
                this.value(valueToRestore);
            } else {
                const firstOption = this.options()[0];
                if (firstOption) {
                    this.value(firstOption.value);
                }
            }
        },

        fetchOptions: function () {
            const url = `${this.source.get('admin_url')}tweakwise/ajax/facets`;
            const formKey = $('[name="form_key"]').val();
            const filterTemplate = this.source.get('data.tweakwise_filter_template');
            const categoryId = this.source.get('data.category_id');

            return $.ajax({
                url: url,
                type: 'POST',
                data: {
                    form_key: formKey,
                    category_id: categoryId,
                    filter_template: filterTemplate
                }
            }).done(function (response) {
                this.options(response);
            }.bind(this));
        },

        initAttributeValueField: function (attribute) {
            registry.get(`${this.parentName}.${this.attributeValueFieldName}`, (attributeValueField) => {
                attributeValueField.initFromAttribute(attribute);
            });
        }
    });
});
