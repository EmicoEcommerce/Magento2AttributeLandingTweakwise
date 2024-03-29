define([
    'jquery',
    'uiRegistry',
    'mage/url',
], function($, registery, url) {
    'use strict';

    $.widget('tweakwise.filter_attribute_values', {
        /**
         * Bind handlers to events
         */
        _create: function(config, element) {
            this._bindEvents(this.options.name);
        },

        _bindEvents(name) {
            var input = $('input[name="' + name.replace('[value-tmp]', '[value]') + '"]');
            input.hide();

            $('select[name="' + name + '"]').on('change', function(evt) {
                var name = evt.target.name;
                var facetValue = evt.target.value;
                var category_id = registery.get('emico_attributelanding_page_form.emico_attributelanding_page_form.general.category_id').value();
                var select = $('select[name="' + name + '"]');
                var input = $('input[name="' + name.replace('[value-tmp]', '[value]') + '"]');

                if (select.val() == 'tw_other') {
                    input.show();
                } else {
                    input.hide();
                    input.val(select.val());
                    input.change();
                }
            });
        }
    });

    return $.tweakwise.filter_attribute_values;
});
