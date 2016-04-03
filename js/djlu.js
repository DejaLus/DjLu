'use strict';

$(document).ready(function() {

    var markdownEditor = new SimpleMDE({
        element: $("#paper-notes-editor")[0],
        spellChecker: true,
        indentWithTabs: false,
        renderingConfig: {codeSyntaxHighlighting: true, singleLineBreaks: false},
        status: false,
        tabSize: 4,
        toolbar: ["link", "table", "|", "preview", "side-by-side", "fullscreen", "|",
        {name: "save", action: saveNotes, className: "fa fa-save", title: "Save"}]
    });

    // RIGHT COLUMN RESIZE
    function pointerX (e) {
        return (e.type.indexOf('touch') === 0) ?
            (e.originalEvent.touches[0] || e.originalEvent.changedTouches[0]).pageX : e.pageX;
    }
    $("#papers-col-right-handle").on("mousedown touchstart", function (e) {

        var left = $("#papers-col-left");
        var middle = $("#papers-col-middle");
        var right = $("#papers-col-right");
        var handle = $("#papers-col-right-handle");
        var middleWidth = middle.width();
        var rightWidth = right.width();
        var totalWidth = left.width() + middleWidth + rightWidth;
        var middleWidthPercent = middleWidth / totalWidth * 100.0;
        var rightWidthPercent = rightWidth / totalWidth * 100.0;

        e.preventDefault();
        var startPos = pointerX(e);

        $(document).on("mousemove touchmove", function (e) {
            var endPos = pointerX(e);
            var differencePercent = (endPos - startPos) / totalWidth * 100.0;
            var newMiddle = middleWidthPercent + differencePercent;
            var newRight  =  rightWidthPercent - differencePercent;

            // apply min limits (right > 10%, middle > 30%)
            if (newRight < 10) {
                newMiddle -= 10 - newRight;
                newRight = 10;
            }
            if (newMiddle < 30) {
                newRight -= 30 - newMiddle;
                newMiddle = 30;
            }

            middle[0].style.width = "" + newMiddle.toFixed(2) + "%";
            right [0].style.width = "" + newRight.toFixed(2) + "%";
            handle[0].style.right = "" + newRight.toFixed(2) + "%";
        });

        $(document).on("mouseup touchend", function (e) {
            $(document).unbind("mousemove touchmove mouseup touchend");
        });
    });
    $("#papers-col-right-close").on("click", function () {
        var left = $("#papers-col-left");
        var middle = $("#papers-col-middle");
        var right = $("#papers-col-right");
        var handle = $("#papers-col-right-handle");
        var totalWidth = left.width() + middle.width() + right.width();

        var newMiddle = (middle.width() + right.width()) / totalWidth * 100.0;
        var newRight  =  0;

        middle[0].style.width = "" + newMiddle.toFixed(2) + "%";
        right [0].style.width = "" + newRight.toFixed(2) + "%";
        handle[0].style.right = "" + newRight.toFixed(2) + "%";

        $("#papers-col-right-open").show();
    });
    $("#papers-col-right-open").on("click", function () {
        var left = $("#papers-col-left");
        var middle = $("#papers-col-middle");
        var right = $("#papers-col-right");
        var handle = $("#papers-col-right-handle");
        var totalWidth = left.width() + middle.width() + right.width();

        var newMiddle = (middle.width() + right.width()) / totalWidth * 75.0;
        var newRight  = (middle.width() + right.width()) / totalWidth * 25.0;

        middle[0].style.width = "" + newMiddle.toFixed(2) + "%";
        right [0].style.width = "" + newRight.toFixed(2) + "%";
        handle[0].style.right = "" + newRight.toFixed(2) + "%";

        $("#papers-col-right-open").hide();
    });

    // TOOLTIPS

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
        $("#paper-details .rating").html(getOrElse(data.json, "rating", ""));
    }

    function initPapersTableStuff () {
        $('[data-toggle="tooltip"]').tooltip({ container: 'body' });

        $("#papers-table .paper").on("click", function () {

            $("#paper-placeholder").hide();
            $("#paper-details").hide();
            $("#paper-bibtex").hide();
            $("#paper-notes").hide();
            $("#paper-wait").show();
            $("#papers-table .paper").removeClass("active");
            $(this).addClass("active");

            var key = $(this).attr("data-paper-key");

            $.get("/api/paper/"+key, function (data) {

                $("#paper-details").attr("data-key", key);
                $("#paper-details .citationKey").html(key);
                displayPaperInfo(data);

                // bibtex
                if (data.bibRaw != undefined) {
                    $("#paper-bibtex-content").html(data.bibRaw);
                    $("#paper-bibtex").show();
                }

                // display all
                $("#paper-wait").hide();
                $("#paper-details").show();
                $("#paper-notes-add").hide();
                $("#paper-notes-content").hide();
                $("#paper-notes").show();

                // notes
                if (data.md != undefined) {
                    $("#paper-notes-content").show();
                    if (markdownEditor.isPreviewActive()) // needs to be in edit mode
                        markdownEditor.togglePreview();
                    markdownEditor.value(data.md);
                    markdownEditor.codemirror.refresh();
                    markdownEditor.togglePreview();
                }
                else {
                    $("#paper-notes-add").show();
                    markdownEditor.value("");
                }

            }, "json");
        });
    }

    initPapersTableStuff();

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
                {"file" : "json", "field" : field, "value" : $("#i_edit").val()},
                function (data) {
                    $("#js_edit_modal").modal("hide");
                    if (data.success) {
                        displayPaperInfo(data);
                        $("#paper-row-"+key).html(data.tr);
                        $.notify({ message: "Paper edited successfully" }, { type: "success" });
                    }
                    else
                        $.notify({ message: "Failed to edit paper: "+data.message }, { type: "danger" });

                }, "json");
        });

        $("#js_edit_modal").modal();
    });

    // ADD / SAVE PAPER NOTES
    $("#paper-notes-add-btn").on("click", function () {
        $("#paper-notes-add").hide();
        $("#paper-notes-content").show();
        markdownEditor.codemirror.refresh();
        if (markdownEditor.isPreviewActive())
            markdownEditor.togglePreview();
    });
    function saveNotes() {
        if (!markdownEditor.isPreviewActive())
            markdownEditor.togglePreview();

        $.post("/api/paper/"+$("#paper-details").attr("data-key"),
            {"file" : "md", "field" : "", "value" : markdownEditor.value()},
            function (data) {
                if (data.success) {
                    $.notify({ message: "Notes saved successfully" }, { type: "success" });
                }
                else
                    $.notify({ message: "Failed to save notes: "+data.message }, { type: "danger" });
            }, "json");
    }

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

                $("#js_push_modal_send").removeAttr("disabled");
            });
        });
    });

    // ADD A PAPER
    $("#js_add").on("click", function () {
        $("#js_add_modal_input").show();
        $("#js_add_modal_wait").hide();
        $("#js_add_modal").modal();
    });
    $("#js_add_modal_send").on("click", function () {
        $("#js_add_modal_input").hide();
        $("#js_add_modal_wait").show();

        $.post("/api/paper/add", $("#js_add_modal_input").serialize(), function (data) {
            if (data.success) {
                $("#js_add_modal").modal("hide");
                $("#papers-table-header").after(data.tr);
                initPapersTableStuff();
                $.notify({ message: "Paper added successfully" }, { type: "success" });
            }
            else {
                $("#js_add_modal_input").show();
                $("#js_add_modal_wait").hide();
                $.notify({ message: "Fail to add paper: "+data.message }, { type: "danger",  z_index: 1051 });
            }
        }, "json");
    });

});


