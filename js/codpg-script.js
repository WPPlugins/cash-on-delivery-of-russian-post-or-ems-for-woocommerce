(function($) {
    $(document).ready(function() {

        $.cookie("codpg-country", $('#billing_country').val());

        $('#billing_country').on('change', function() {
            $.cookie("codpg-country", $(this).val());
        });

        if($('#payment_method_codpg_russian_post').is(':checked')) {
            $.cookie("codpg-rp", 1);
            $('body').trigger('update_checkout');
        } else {
            $.removeCookie("codpg-rp");
            $('body').trigger('update_checkout');
        }
        $(document.body).on('change', 'input[name="payment_method"]', function() {
            if($('#payment_method_codpg_russian_post').is(':checked')) {    
                $.cookie("codpg-rp", 1);
                $('body').trigger('update_checkout');
            } else {
                $.removeCookie("codpg-rp");
                $('body').trigger('update_checkout');
            }
        });

        function codpg_cookie() { 
            // if Cookie is set and input does not exist
            if ( typeof $.cookie('codpg-rp') !== 'undefined' && $( "#payment_method_codpg_russian_post" ).length == 0 ) { 
                $.removeCookie("codpg-rp");
                $('body').trigger('update_checkout');
            // if Cookie is not set but input exists and checked
            } else if ( typeof $.cookie('codpg-rp') === 'undefined' && $( "#payment_method_codpg_russian_post" ).length ) {
                if($('#payment_method_codpg_russian_post').is(':checked')) {
                    $.cookie("codpg-rp", 1);
                    $('body').trigger('update_checkout');
                }
            }
        }
        setInterval(codpg_cookie, 1000);
    });
})(jQuery);