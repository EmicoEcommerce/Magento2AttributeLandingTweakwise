define([
    'jquery',
    'uiRegistry',
    'mage/url',
], function($, registery, url) {
    'use strict';

    $.widget('tweakwise.filter_attributes', {
        /**
         * Bind handlers to events
         */
        _create: function(config, element) {
            this._bindEvents(this.options.name);
            this._initAttributes(this.options.name);
        },

        _initAttributes: function (name) {
            //bind to event. So it's not triggered by the user, but can be triggered in the code.
            $('select[name="' + name + '"]').on('initAttributes', function(evt) {
                var name = evt.target.name;
                var category_id = registery.get('emico_attributelanding_page_form.emico_attributelanding_page_form.general.category_id').value();
                var selectAttribute =  $('select[name="' + name + '"]');
                var inputAttribute = $('input[name="' + name.replace('[attribute-tmp]', '[attribute]') + '"]');
                var inputValue = $('input[name="' + name.replace('[attribute-tmp]', '[value]') + '"]');
                var selectedValue = inputAttribute.val();
                var templateValue = $('select[name="tweakwise_filter_template"]');
                var facetUrl = url.build('/tweakwise/ajax/facets/category/' + category_id);
                var foundSelectedValue = false;

                if (templateValue.val() !== '') {
                    facetUrl += '/filtertemplate/' + templateValue.val();
                }

                inputAttribute.hide();
                inputValue.hide();

                $.getJSON(facetUrl, function( data ) {
                    selectAttribute.empty();
                    data.data.forEach(value => {
                        if(value.value != selectedValue) {
                            selectAttribute.append($("<option></option>").attr("value", value.value).text(value.label));
                        } else {
                            foundSelectedValue = true;
                            selectAttribute.append($("<option></option>").attr("value", value.value).text(value.label).attr("selected", "selected"));
                        }
                    });

                    if (foundSelectedValue === false && selectedValue) {
                        selectAttribute.val('tw_other');
                    } else {
                        //no default value, set value to first option
                        inputAttribute.val(selectAttribute.val());
                    }
                    selectAttribute.trigger('change');
                });
            });

            $('select[name="' + name + '"]').trigger('initAttributes');
        },

        _bindEvents(name) {
            $('select[name="' + name + '"]').on('change', function(evt) {
                var name = evt.target.name;
                var facetValue = evt.target.value;
                var category_id = registery.get('emico_attributelanding_page_form.emico_attributelanding_page_form.general.category_id').value();
                var inputValue = $('input[name="' + name.replace('[attribute-tmp]', '[value]') + '"]');
                var inputAttribute = $('input[name="' + name.replace('[attribute-tmp]', '[attribute]') + '"]');
                var selectValue = $('select[name="' + name.replace('[attribute-tmp]', '[value-tmp]') + '"]');
                var templateValue = $('select[name="tweakwise_filter_template"]');
                var facetUrl = url.build('/tweakwise/ajax/facetattributes/category/' + category_id + '/facetkey/' + facetValue);
                var foundSelectedValue = false;

                if (templateValue.val() !== '') {
                    facetUrl += '/filtertemplate/' + templateValue.val();
                }

                //no value selected, load initial value
                if (!facetValue) {
                    facetValue = inputAttribute.val();
                }

                if (facetValue == 'tw_other') {
                    inputAttribute.show();
                    selectValue.val('tw_other');
                    inputValue.show();
                } else {
                    inputAttribute.hide();
                    if (selectValue.val() != 'tw_other') {
                        inputValue.hide();
                        inputAttribute.val(facetValue);
                    } else {
                        inputValue.show();
                    }
                }

                inputAttribute.change();

                $.getJSON(facetUrl, function (data) {
                    selectValue.empty();
                    data.data.forEach(value => {
                        if (value.value != inputValue.val()) {
                            selectValue.append($("<option></option>").attr("value", value.value).text(value.label));
                        } else {
                            foundSelectedValue = true;
                            selectValue.append($("<option></option>").attr("value", value.value).text(value.label).attr("selected", "selected"));
                        }
                    });

                    if (foundSelectedValue === false && inputValue.val()) {
                        selectValue.val('tw_other');
                    } else {
                        //no default value, set value to first option
                        inputValue.val(selectValue.val());
                        inputValue.change();
                    }
                });
            });

            //select different filter template
            $('select[name="tweakwise_filter_template"]').unbind('change');
            $('select[name="tweakwise_filter_template"]').on('change', function(evt) {
                $('select[name*="[attribute-tmp]"]').trigger('initAttributes');
            });
        }
    });

    return $.tweakwise.filter_attributes;
});
