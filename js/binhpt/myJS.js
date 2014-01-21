/**
 * Created with JetBrains PhpStorm.
 * User: smartosc
 * Date: 1/20/14
 * Time: 11:09 AM
 * To change this template use File | Settings | File Templates.
 */
jQuery.noConflict();
jQuery(document).ready(function ($) {
    $('.buttonUp_qty').click(function () {
        var cart_id = '#' + $(this).attr('id').replace('btn_', '');
        var qty = jQuery(cart_id).val();
        qty = parseInt(qty) + 1;
        $(cart_id).val(qty);
        $(cart_id).attr('value', qty);
    });
    $('.buttonDown_qty').click(function () {
        var cart_id = '#' + $(this).attr('id').replace('btn_', '');
        var qty = $(cart_id).val();
        qty = parseInt(qty) - 1;
        if (qty < 0) {
            return false;
        }
        $(cart_id).val(qty);
        $(cart_id).attr('value', qty);
    });
})(jQuery);