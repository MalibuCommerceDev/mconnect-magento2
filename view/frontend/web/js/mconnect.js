var mconnect;

require([
    'jquery'
], function ($) {
    function MConnect() {
        this.init();
    }

    MConnect.prototype.init = function() {
        this.auth       = $('[name=auth]').val();
        this.identifier = $('[name=identifier]').val();
        this.type       = $('[name=type]').val();
        this.base_url   = $('[name=base_url]').val();
        this.console    = $('#console');
        this.checkType();
    }

    MConnect.prototype.checkType = function() {
        switch (this.type) {
            case 'product-import':
                this.productImport(this.identifier);
                break;
            default:
                this.message('danger', 'No operation was specified.');
        }
    }

    MConnect.prototype.message = function(type, message, classes) {
        this.addToConsole($('<div>', {
            class: 'alert alert-' + type + (classes ? ' ' + classes : ''),
            role: 'alert',
            html: message
        }));
    }

    MConnect.prototype.addToConsole = function(elem) {
        $(elem).appendTo(this.console);
    }

    MConnect.prototype.productImport = function(sku) {
        this.message('info', 'Staring import of product: <strong>' + sku + '</strong>...');
        this.startLoader();
        var that = this;
        this.get('mconnect/sync/productsync/id/' + this.identifier, function(response) {
            that.stopLoader();
            if (response.success) {
                that.message('success', response.message + (response.url ? '<div class="center"><a class="btn btn-lg btn-success" target="_blank" href="' + response.url + '">Continue to Magento Admin</a></div>' : ''));
            } else {
                that.message('danger', response.message || 'Something went wrong while syncing the product');
                if (!response.message && response.length) {
                    that.message('warning', response);
                }
                if (response.detail && response.detail.length) {
                    that.message('warning', response.detail);
                    $('.malibucommerce-mconnect-parsed h3').each(function() {
                        if ($(this).text() == 'Body' || $(this).text() == 'Request XML' || $(this).text() == 'Response XML') {
                            var code = $(this).next().children(),
                                formatted = vkbeautify.xml(code.text());
                            if (formatted) {
                                code.text(formatted);
                            }
                        }
                    })
                }
            }
        });
    }

    MConnect.prototype.startLoader = function() {
        this.addToConsole($('<div>', {
            class: 'loader-wrapper',
            html: $('<div>', {
                class: 'loader',
                html: $('<span>')
            })
        }));
    }

    MConnect.prototype.stopLoader = function() {
        $('#console .loader-wrapper').slideUp(400, function() {
            $(this).remove();
        });
    }

    MConnect.prototype.get = function(url, success) {
        var that = this;
        $.ajax(this.base_url + url + '/' + 'auth/' + this.auth).done(success).fail(function() {
            that.stopLoader();
            that.message('danger', 'Something went wrong... Please try again.');
        });
    }

    $(document).ready(function() {
        mconnect = new MConnect;
    });
});
