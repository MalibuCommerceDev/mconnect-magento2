define([
    'Magento_Ui/js/grid/columns/column',
    'jquery',
    'underscore',
    'Magento_Ui/js/modal/modal'
], function (Column, $, _) {
    'use strict';

    return Column.extend({
        defaults: {
            bodyTmpl: 'MalibuCommerce_MConnect/grid/cell/message',
        },

        isAvailable: function (row) {
            return row[this.index];
        },

        /**
         * Build preview.
         *
         * @param {Object} row
         */
        preview: function (row) {
            var previewPopup = $('<div></div>').html('<pre style="white-space: pre-line; word-break: break-word;">' + row[this.index] + '</pre>');

            previewPopup.modal({
                title: 'Message',
                innerScroll: true,
                buttons: []
            }).trigger('openModal');
        },

        /**
         * Get field handler per row.
         *
         * @param {Object} row
         * @returns {Function}
         */
        getFieldHandler: function (row) {
            if (this.isAvailable(row)) {
                return this.preview.bind(this, row);
            }
        }
    });
});
