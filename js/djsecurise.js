'use strict';

$(document).ready(function () {

    function djHash(text, standard_key) {
        var bcrypt = dcodeIO.bcrypt;
        if (standard_key)
            var djkey = '$2a$12$44TFtfWIeRZ1ih0QpNgM2.';
        else
            var djkey = bcrypt.genSaltSync(12);
        return bcrypt.hashSync(text, djkey);
    }

    // REGISER
    $("#djregister").on("submit", function () {
        if ($("#sshId").val() == undefined || $("#sshId").val().length == 0) {
            $.notify({ message: "You must generate an SSH key and add it to your repository." }, { type: "danger" });
            return false;
        }
        if($("#i_password2").val().length < 8 || $("#i_password2").val().length > 16) {
            $.notify({ message: "Password must have at least 8 and at most 16 characters." }, { type: "danger" });
            return false;
        } else {
            $("#i_password3").val(djHash($("#i_password2").val(), true).substring(29));
            return true;
        }
    });

    // SSH KEYGEN
    $("#sshBtn").on("click", function () {
        $.get("/api/sshkey", function (data) {
            $("#sshBtn").hide();
            $("#sshKey").html(data.key).show();
            $("#sshId").val(data.id);
            $("#registerBtn").removeAttr("disabled");
            $.notify({ message: "You must give access to your repository for this key." }, { type: "info" });
        }, "json");
    });

    // LOGIN
    $("#djlogin").on("submit", function () {
        if($("#i_password").val().length < 8 || $("#i_password").val().length > 16) {
            $.notify({ message: "Password must have at least 8 and at most 16 characters." }, { type: "danger" });
            return false;
        } else {
            var hash1 = djHash($("#i_password").val(), true).substring(29);
            var hash2 = djHash(hash1.concat($("#s_sid").val()), false)
            $("#i_password4").val(hash2);
            return true;
        }
    });
});
