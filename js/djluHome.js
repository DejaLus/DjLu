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

    function hash(text, standard_key) {
        var bcrypt = dcodeIO.bcrypt;
        if (standard_key)
            var djkey = '$2a$12$44TFtfWIeRZ1ih0QpNgM2.';
        else
            var djkey = bcrypt.genSaltSync(12);
        return bcrypt.hashSync(text, djkey);
    }

    $("#register-btn").on("click", function () {
        $("#register").modal("show");
        if ($("#ssh-id").val().length == 0) {
            $.get("/api/sshkey", function (data) {
                $("#ssh-key").html(data.key).show();
                $("#ssh-id").val(data.id);
                $("#register-submit").removeAttr("disabled");
                $.notify({ message: "You must give access to your repository for this key." }, { type: "info", z_index: 1051 });
            }, "json");
        }
        else
            $.notify({ message: "You must give access to your repository for the key." }, { type: "info", z_index: 1051 });
    })

    // REGISER
    $("#register-form").on("submit", function () {
        if ($("#ssh-id").val().length == 0) {
            $.notify({ message: "You must generate an SSH key and add it to your repository." }, { type: "danger", z_index: 1051 });
            return false;
        }
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
            var hash2 = hash(hash1.concat($("#s_sid").val()), false)
            $("#i_password4").val(hash2);
            return true;
        }
    });
});
