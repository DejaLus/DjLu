'use strict';

$(document).ready(function() {

    // TOOLTIPS
    $('[data-toggle="tooltip"]').tooltip({ container: 'body' });

    function getOrElse(map, key, elseVal, join) {
        var el = map[key] ? map[key] : elseVal;
        return join ? el.join(join) : el;
    }

    // DISPLAY PAPER INFOS
    function displayPaperInfo (data) {
        $("#paper-details .title").html(getOrElse(data.json, "title", ""));
        $("#paper-details .authors").html(getOrElse(data.json, "authors", [], "; "));
        $("#paper-details .in").html(getOrElse(data.json, "in", ""));
        $("#paper-details .year").html(getOrElse(data.json, "year", ""));
        $("#paper-details .tags_content").html(getOrElse(data.json, "tags_content", [], "; "));
        $("#paper-details .tags_reading").html(getOrElse(data.json, "tags_reading", [], "; "));
        $("#paper-details .tags_notes").html(getOrElse(data.json, "tags_notes", [], "; "));
        $("#paper-details .date_added").html(getOrElse(data.json, "date_added", ""));
        $("#paper-details .url").html(getOrElse(data.json, "url", ""));
    }
    $("#papers-table .paper").on("click", function () {

        $("#paper-placeholder").hide();
        $("#paper-details").hide();
        $("#paper-wait").show();
        $("#papers-table .paper").removeClass("active");
        $(this).addClass("active");

        var key = $(this).attr("data-paper-key");

        $.get("/api/paper/"+key, function (data) {

            $("#paper-details").attr("data-key", key);
            displayPaperInfo(data);

            $("#paper-wait").hide();
            $("#paper-details").show();
        }, "json");
    });

    // EDIT PAPER FIELD
    $("#paper-details > *:has(span[data-key])").on("dblclick", function () {

        // get info element
        var el = $(this).children("span[data-key]");
        var key = $("#paper-details").attr("data-key");
        var field = el.attr("data-key");

        $("#js_edit_modal_form").show();
        $("#js_edit_modal_wait").hide();

        $("#i_edit").val(el.html());
        $("#i_edit_label").html(el.attr("data-title"));

        // on click send
        $("#js_edit_modal_send").unbind("click").on("click", function () {

            // send request
            $.post("/api/paper/"+key,
                {"field" : field, "value" : $("#i_edit").val()},
                function (data) {
                    $("#js_edit_modal").modal("hide");
                    if (data.success) {
                        displayPaperInfo(data);
                        $("#paper-row-"+key).html(data.tr);
                        $.notify({ message: "Paper edited successfully" }, { type: "success" });
                    }
                    else
                        $.notify({ message: "Failed to edit paper" }, { type: "danger" });

                }, "json");
        });

        $("#js_edit_modal").modal();
    });

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
    });

});


