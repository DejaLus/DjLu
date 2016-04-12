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

    new Tablesort($("#papers-table")[0]);

    // RIGHT COLUMN RESIZE
    function pointerX (e) {
        return (e.type.indexOf('touch') === 0) ?
            (e.originalEvent.touches[0] || e.originalEvent.changedTouches[0]).pageX : e.pageX;
    }
    $("#papers-col-right-handle").on("mousedown touchstart", function (e) {

        var middle = $("#papers-col-middle");
        var right = $("#papers-col-right");
        var handle = $("#papers-col-right-handle");
        var middleWidth = middle.width();
        var rightWidth = right.width();
        var totalWidth = middleWidth + rightWidth;
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
        var middle = $("#papers-col-middle");
        var right = $("#papers-col-right");
        var handle = $("#papers-col-right-handle");
        var totalWidth = middle.width() + right.width();

        var newMiddle = (middle.width() + right.width()) / totalWidth * 100.0;
        var newRight  =  0;

        middle[0].style.width = "" + newMiddle.toFixed(2) + "%";
        right [0].style.width = "" + newRight.toFixed(2) + "%";
        handle[0].style.right = "" + newRight.toFixed(2) + "%";

        $("#papers-col-right-open").show();
    });
    $("#papers-col-right-open").on("click", function () {
        var middle = $("#papers-col-middle");
        var right = $("#papers-col-right");
        var handle = $("#papers-col-right-handle");
        var totalWidth = middle.width() + right.width();

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

        $(".group-link").on("click", function () {
            if($(this).hasClass("group-filter-active")) {
                $(this).removeClass("group-filter-active");
            } else {
                $(this).addClass("group-filter-active");
            }
            searchPapers();
        });

        $(".no-groups").on("click", function () {
            var group = "." + $(this).attr("group-key");
            $(group).each(function(){
                if($(this).hasClass("group-filter-active")) {
                    $(this).removeClass("group-filter-active");
                }
            });
            searchPapers();
        });

        $(".all-groups").on("click", function () {
            var group = "." + $(this).attr("group-key");
            $(group).each(function(){
                if(!$(this).hasClass("group-filter-active")) {
                    $(this).addClass("group-filter-active");
                }
            });
            searchPapers();
        });
    }

    function searchPapers() {
        $(".paper").each(function(){
            $(this).show();
        });
        $(".labels-list").each(function(){
            var children = $(this).children(".labels-sublist");
            var element = $(this).parent().parent();
            $(".group-filter-active").each(function(){
                var group = $(this).attr("group-key");
                var grtag = $(this).attr("group-tag");
                var found = false;
                children.each(function(){
                    $(this).children().each(function(){
                        if($(this).attr("group-key") == group && $(this).attr("group-tag") == grtag){
                            found = true;
                        }
                    });
                });
                if(!found) {
                    element.hide();
                }
            });
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

    // GOOGLE DRIVE

    var loopCount = 0;

    function driveLogin (url, callback) {
        var left = window.screenX + (window.outerWidth / 2) - (400 / 2);
        var top = window.screenY + (window.outerHeight / 2) - (500 / 2);
        var windowFeatures = "width=400,height=500,top=" + top + ",left=" + left +
                             ",location=yes,toolbar=no,menubar=no";
        var popupWindow = window.open(url, "oauth2_popup", windowFeatures);

        if (!popupWindow || popupWindow.closed || typeof popupWindow.closed == 'undefined') {
            $.notify({ message: "You must login with your Google account, please unblock the popups for the domain and try again" }, { type: "danger" });
            return;
        }

        var oauthInterval = window.setInterval(function() {
            if (popupWindow.closed) {
                window.clearInterval(oauthInterval);
                callback();
            }
        }, 800);
    }

    function driveAjaxPDF (type, paper, ajaxMethod, formData) {
        $.ajax({
            type: ajaxMethod,
            url: "/api/drive/"+type+"/"+paper,
            data: formData,
            processData: false,
            contentType: false,
            dataType: "json",
            success: function (data) {

                if (data.success == false && data.reason == "auth") {
                    loopCount++;
                    if (loopCount < 3)
                        driveLogin(data.url, function () { return driveAjaxPDF (type, paper, ajaxMethod, formData); });
                    return;
                }

                if (data.success == false) {
                    $.notify({ message: data.message }, { type: "danger" });
                    return;
                }

                $("#paper-details .url").html(data.url);
                $(".paper.active a.pdf").attr("href", data.url);
                $.notify({ message: data.message }, { type: "success" });

            }
        });
    }

    $("#js_drive_fetch").on("click", function () {
        var key = $("#paper-details").attr("data-key");
        driveAjaxPDF ("fetch", key, "GET");
    });

    $("#js_drive_import").on("click", function () {
        var key = $("#paper-details").attr("data-key");
        driveAjaxPDF ("upload/url", key, "GET");
    });

    $(document).on("change", "#js_drive_upload :file", function(e) {
        var key = $("#paper-details").attr("data-key");
        var file = e.target.files[0];
        var formData = new FormData();
        formData.append("pdf", file);
        if (file != undefined) {
            driveAjaxPDF ("upload/post", key, "POST", formData);
        }
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
            if (data.success == true || data.success == "partial") {
                $("#js_add_modal").modal("hide");
                $("#papers-table tbody").prepend(data.html);
                initPapersTableStuff();
                $.notify({ message: "Paper(s) added successfully" }, { type: "success" });
                if (data.success == "partial")
                    $.notify({ message: "But also failed to add some papers:<br>"+data.message }, { type: "danger", z_index: 1051 });
            }
            else {
                $("#js_add_modal_input").show();
                $("#js_add_modal_wait").hide();
                $.notify({ message: "Fail to add paper(s): "+data.message }, { type: "danger",  z_index: 1051 });
            }
        }, "json");
    });

});


