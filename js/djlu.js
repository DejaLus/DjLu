'use strict';

$(document).ready(function() {

    // TOOLTIPS
    $('[data-toggle="tooltip"]').tooltip({ container: 'body' });

    // GIT PULL
    $("#js_pull_modal_reload").on("click", function () { location.reload(); });
    $("#js_pull").on("click", function () {

        // show stuff
        $("#js_pull_modal_content").html('<p>We are currently pulling your git repository, please wait...</p>'+
            '<p class="text-center spinner"><i class="fa fa-refresh faa-spin animated"></i></p>');
        $("#js_pull_modal").modal({ "backdrop": "static", "keyboard": false });
        $("#js_pull_modal_close").attr("disabled", true);
        $("#js_pull_modal_reload").attr("disabled", true);

        // call API
        $.get("/api/pull", function (data) {

            // show result
            $("#js_pull_modal_content").html('<p>Git pull '+(data.success ? 'finished with success' : 'failed')+'.</p>'+
                '<p><strong>Git output log:</strong></p>'+
                '<pre>'+data.log+'</pre>');

            // activate the right button
            if (data.success)
                $("#js_pull_modal_reload").removeAttr("disabled");
            else
                $("#js_pull_modal_close").removeAttr("disabled");

        }, "json");
    });


    // GIT PUSH
    $("#js_push").on("click", function () {

        // show stuff
        $("#js_push_modal_send").unbind("click").attr("disabled", true);
        $("#js_push_modal_close").removeAttr("disabled");
        $("#js_push_modal_status_content").html('<p class="text-center spinner"><i class="fa fa-refresh faa-spin animated"></i></p>');
        $("#js_push_modal_status").show();
        $("#js_push_modal_message").show();
        $("#js_push_modal_result").hide();
        $("#js_push_modal").modal({ "backdrop": "static", "keyboard": false });

        // get status
        $.get("/api/status", function (data) {

            // show status & enable send
            $("#js_push_modal_status_content").html('<pre>'+data+'</pre>');
            $("#js_push_modal_send").removeAttr("disabled");

            // on send
            $("#js_push_modal_send").on("click", function () {

                // disable send, init result div
                $("#js_push_modal_close").attr("disabled", true);
                $("#js_push_modal_send").unbind("click").attr("disabled", true);
                $("#js_push_modal_status").hide();
                $("#js_push_modal_message").hide();
                $("#js_push_modal_result").html('<p class="text-center spinner"><i class="fa fa-refresh faa-spin animated"></i></p>').show();

                // send request
                $.post("/api/push", {"message" : $("#i_commit").val()}, function (data) {

                    // show result
                    $("#i_commit").val("");
                    $("#js_push_modal_result").html('<p>Git push '+(data.success ? 'finished with success' : 'failed')+'.</p>'+
                        '<p><strong>Git output log:</strong></p>'+
                        '<pre>'+data.log+'</pre>');
                }, "json");
            });
        });



        /*$.get("/api/pull", function (data) {
            console.log(data);

            if (data.success)
                $("#js_pull_modal .modal-body").html('<p>Git pull finished with success. Please reload the page.</p>'+
                    '<p class="text-center"><a class="btn btn-success" onclick="location.reload()"><i class="fa fa-refresh"></i> Reload the page</a></p>'+
                    '<h4>Git output log:</h4>'+
                    '<pre>'+data.log+'</pre>');
            else
                $("#js_pull_modal .modal-body").html('<p>Git pull failed.</p>'+
                    '<p class="text-center"><button type="button" class="btn btn-danger" data-dismiss="modal">Close</button></p>'+
                    '<h4>Git output log:</h4>'+
                    '<pre>'+data.log+'</pre>');
        }, "json").fail(function (data) {
            $("#js_pull_modal .modal-body").html('<p>We got an invalid JSON reponse.</p>'+
                    '<p class="text-center"><button type="button" class="btn btn-danger" data-dismiss="modal">Close</button></p>'+
                    '<h4>Reponse log:</h4>'+
                    '<pre style="white-space: normal;">'+data.responseText+'</pre>');
        });*/
    });

});


