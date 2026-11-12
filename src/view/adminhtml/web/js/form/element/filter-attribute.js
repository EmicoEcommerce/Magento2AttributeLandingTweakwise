define([
    'Magento_Ui/js/form/element/ui-select',
    'jquery',
    'mage/url',
    'uiRegistry'
], function (Select, $, urlBuilder, registry) {
    return Select.extend({
        attributeValueFieldName: 'value',
        otherFieldName: 'attribute_other',
        otherValue: 'tw_other',
        savedLabel: null,
        initialize: function () {
            this._super();

            this.savedValue = this.value();

            this.setOtherFieldVisibility(this.savedValue);

            this.initialOptionsPending = true;
            this.subscribeFilterTemplate();
            this.subscribeCategoryId();
            this.refreshOptions();

            return this;
        },

        setInitialValue: function () {
            return this._super();
        },

        setCaption: function () {
            const value = this.value() ?? this.savedValue;
            const label = this.options().find((option) => {
                return option.value == value;
            })?.label ?? this.savedLabel ?? value;
            if (label && this.savedLabel !== label) {
                this.savedLabel = label;
            }
            return label;
        },

        initObservable: function () {
            this._super().observe([
                'value'
            ]);
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
                    this.refreshOptions();
                });
            });
        },

        subscribeFilterTemplate: function () {
            const filterTemplatePath = 'emico_attributelanding_page_form.emico_attributelanding_page_form.general.tweakwise_filter_template';
            registry.get(filterTemplatePath, (filterTemplateField) => {
                filterTemplateField.value.subscribe((newFilterTemplate) => {
                    this.refreshOptions();
                });
            });
        },

        refreshOptions: function () {
            const currentAttributeValue = this.value();
            const filterTemplate = this.source.get('data.tweakwise_filter_template');
            const categoryId = this.source.get('data.category_id');
            const request = this.fetchOptions();
            const requestId = this.optionsRequestId;

            return request.then(() => {
                if (requestId !== this.optionsRequestId ||
                    filterTemplate !== this.source.get('data.tweakwise_filter_template') ||
                    categoryId !== this.source.get('data.category_id')) {
                    return;
                }

                if (this.initialOptionsPending) {
                    this.initialOptionsPending = false;
                    if (currentAttributeValue === this.value() && this.value() === this.savedValue) {
                        this.setRestoredValue();
                    }
                    this.subscribeAttribute();
                    this.initAttributeValueField(this.value());
                    return;
                }

                if (currentAttributeValue !== this.value()) {
                    return;
                }
                this.restoreValue(currentAttributeValue);
            });
        },

        setOtherFieldVisibility: function (selectedAttribute, otherFieldValue = null) {
            registry.get(`${this.parentName}.${this.otherFieldName}`, function (otherField) {
                const otherFieldVisible = selectedAttribute === this.otherValue
                otherField.disabled(!otherFieldVisible);
                if (!otherFieldVisible) {
                    otherField.value('');
                } else if (otherFieldValue) {
                    otherField.value(otherFieldValue);
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
                this.value(this.otherValue);
                this.setOtherFieldVisibility(this.otherValue, this.savedValue);
            }
        },

        restoreValue: function (valueToRestore) {
            const optionExists = this.options().some(function (option) {
                return option.value === valueToRestore;
            }.bind(this));

            if (optionExists) {
                this.value(valueToRestore);
            }
        },

        fetchOptions: function () {
            const url = `${this.source.get('admin_url')}tweakwise/ajax/facets`;
            const formKey = $('[name="form_key"]').val();
            const filterTemplate = this.source.get('data.tweakwise_filter_template');
            const categoryId = this.source.get('data.category_id');
            const requestId = this.optionsRequestId = (this.optionsRequestId || 0) + 1;

            return $.ajax({
                url: url,
                type: 'POST',
                data: {
                    form_key: formKey,
                    category_id: categoryId,
                    filter_template: filterTemplate
                }
            }).done(function (response) {
                if (requestId === this.optionsRequestId &&
                    filterTemplate === this.source.get('data.tweakwise_filter_template') &&
                    categoryId === this.source.get('data.category_id')) {
                    this.options(response);
                }
            }.bind(this));
        },

        initAttributeValueField: function (attribute) {
            registry.get(`${this.parentName}.${this.attributeValueFieldName}`, (attributeValueField) => {
                attributeValueField.initFromAttribute(attribute);
            });
        }
    });
});
