'use strict';

$(document).ready(function () {

    // jQuery for page scrolling feature - requires jQuery Easing plugin
    $('a.page-scroll').bind('click', function(event) {
        var $anchor = $(this);
        $('html, body').stop().animate({
            scrollTop: ($($anchor.attr('href')).offset().top - 50)
        }, 1250, 'easeInOutExpo');
        event.preventDefault();
    });

    // Highlight the top nav as scrolling occurs
    $('body').scrollspy({
        target: '.navbar-fixed-top',
        offset: 51
    })

    // Closes the Responsive Menu on Menu Item Click
    $('.navbar-collapse ul li a').click(function() {
        $('.navbar-toggle:visible').click();
    });

    // Offset for Main Navigation
    $('#navbar-home').affix({
        offset: {
            top: 100
        }
    });

    function encode_utf8(val) {
        return unescape(encodeURIComponent(val));
    }

    function hash(text, standard_key) {
        var bcrypt = dcodeIO.bcrypt;
        var ntext = encode_utf8(text);
        if (standard_key)
            var djkey = '$2a$10$Ex3s.i/XW9efb/61f5mB8e';
        else
            var djkey = bcrypt.genSaltSync(15);
        return bcrypt.hashSync(text, djkey);
    }

    // REGISER
    $("#register-form").on("submit", function () {
        if($("#i_password2").val().length < 8 || $("#i_password2").val().length > 16) {
            $.notify({ message: "Password must have at least 8 and at most 16 characters." }, { type: "danger", z_index: 1051 });
            return false;
        }
        $("#i_password3").val(hash($("#i_password2").val(), true).substring(29));
        return true;
    });

    // LOGIN
    $("#login-form").on("submit", function () {
        if($("#i_password").val().length < 8 || $("#i_password").val().length > 16) {
            $.notify({ message: "Password must have at least 8 and at most 16 characters." }, { type: "danger", z_index: 1051 });
            return false;
        }
        else {
            var hash1 = hash($("#i_password").val(), true).substring(29);
            var hash2 = hash(hash1.concat($("#s_sid").val()), false);
            $("#i_password4").val(hash2);
            return true;
        }
    });
});
