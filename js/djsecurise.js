function djHash(text, standard_key) {
    var bcrypt = dcodeIO.bcrypt;
    if (standard_key)
        var djkey = '$2a$12$44TFtfWIeRZ1ih0QpNgM2.';
    else
        var djkey = bcrypt.genSaltSync(12);
    return bcrypt.hashSync(text, djkey);
}

function register() {
    if(document.getElementById("i_password2").value.length < 8 || document.getElementById("i_password2").value.length > 16) {
        alert('Password must have at least 8 and at most 16 characters.');
    } else {
        // show a modal wait dialog
        document.getElementById("i_password3").value = djHash(document.getElementById("i_password2").value, true).substring(29);
        document.getElementById("djregister").submit();
        // hide the modal wait dialog
    }
}

function login() {
    if(document.getElementById("i_password").value.length < 8 || document.getElementById("i_password").value.length > 16) {
        alert('Password must have at least 8 and at most 16 characters.');
    } else {
        // show a modal wait dialog
        var hash1 = djHash(document.getElementById("i_password").value, true).substring(29);
        var hash2 = djHash(hash1.concat(document.getElementById("s_sid").value), false)
        document.getElementById("i_password4").value = hash2;
        document.getElementById("djlogin").submit();
        // hide the modal wait dialog
    }
}
