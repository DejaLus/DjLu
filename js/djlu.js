$(document).ready(function() {

    'use strict';

    ///////////////////////////////////////////////////////////////////////////
    ///////////////////////////////////////////////////////////////////////////
    /////////////// RIGHT COLUMN
    ///////////////////////////////////////////////////////////////////////////
    ///////////////////////////////////////////////////////////////////////////



    ////////////////////////////////////////
    /////// MARKDOWN EDITOR
    ////////////////////////////////////////

    function renderWithMath (txt) {
        txt = txt.replace(/(^|[^\\])\$\$/g, "$1`eq2");
        txt = txt.replace(/(^|[^\\])\$/g, "$1`eq");
        var html = markdownEditor.markdown(txt);
        html = html.replace(/<\/?code>eq2/g, "$$$$");
        html = html.replace(/<\/?code>eq/g, "$$");
        html = html_entity_decode(html, "ENT_QUOTES");
        setTimeout(function() { MathJax.Hub.Typeset(); }, 300);
        return html;
    }

    function mdeEditMode () {
        if (markdownEditor.isPreviewActive()) {
            // we need not to use our renderer because it is called when we
            // switch to edit mode, and produce double call to mathjax and
            // double rendering of equations
            markdownEditor.options.previewRender = markdownEditor.markdown;
            markdownEditor.togglePreview();
            markdownEditor.options.previewRender = renderWithMath;
        }
    }

    var markdownEditor = new SimpleMDE({
        element: $("#paper-notes-editor")[0],
        spellChecker: true,
        indentWithTabs: false,
        renderingConfig: {codeSyntaxHighlighting: true, singleLineBreaks: false},
        status: false,
        tabSize: 4,
        previewRender: renderWithMath,
        toolbar: ["link", "table", "|", "preview", "side-by-side", "fullscreen", "|",
        {name: "save", action: paperSaveNotes, className: "fa fa-save", title: "Save"}]
    });



    ////////////////////////////////////////
    /////// RESIZE COLUMN
    ////////////////////////////////////////

    function pointerX (e) {
        return (e.type.indexOf("touch") === 0) ?
            (e.originalEvent.touches[0] || e.originalEvent.changedTouches[0]).pageX : e.pageX;
    }

    function rightColumnResizeHandler (e) {

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
    }

    function rightColumnClose () {
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
    }

    function rightColumnOpen () {
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
    }

    // register events
    $("#papers-col-right-handle").on("mousedown touchstart", rightColumnResizeHandler);
    $("#papers-col-right-close").on("click", rightColumnClose);
    $("#papers-col-right-open").on("click", rightColumnOpen);



    ////////////////////////////////////////
    /////// SHOW PAPER INFO
    ////////////////////////////////////////

    function getOrElse(map, key, elseVal, join) {
        var el = map[key] ? map[key] : elseVal;
        return join ? el.join(join) : el;
    }

    function paperDisplayInfo (data) {
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

    function paperDisplay () {

        $("#paper-placeholder").hide();
        $("#paper-details").hide();
        $("#paper-bibtex").hide();
        $("#paper-notes").hide();
        $("#paper-wait").show();
        $("#paper-delete").hide();
        $("#papers-table .paper").removeClass("active");
        $(this).addClass("active");

        var key = $(this).data("paper-key");

        $.get("/api/paper/"+key, function (data) {

            if (data.success === false) {
                $.notify({ message: "Failed to get paper's info: "+data.message }, { type: "danger" });
                return;
            }

            $("#paper-details").data("key", key);
            $("#paper-details .citationKey").html(key);
            paperDisplayInfo(data);

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
            $("#paper-delete").show();

            // notes
            if (data.md != undefined) {
                $("#paper-notes-content").show();
                mdeEditMode(); // needs to be in edit mode
                markdownEditor.value(data.md);
                markdownEditor.codemirror.refresh();
                markdownEditor.togglePreview();
            }
            else {
                $("#paper-notes-add").show();
                markdownEditor.value("");
            }

        }, "json");
    }



    ////////////////////////////////////////
    /////// PAPER EDIT
    ////////////////////////////////////////

    function paperEditShow () {

        // get info element
        var el = $(this).children("span[data-key]");
        var form = $("#modal-paper-edit");

        form.attr("action", form.data("base-url").replace("@key", $("#paper-details").data("key")));
        form.find('[name="field"]').val(el.data("key"));
        form.find('[name="value"]').val(el.html());
        $("#modal-paper-edit-label").html(el.data("title"));

        form.modal();
    }

    function paperEditForm () {
        $(this).modal("hide");
        showSpinner("Saving modifications...");

        ajaxFormProcess($(this), function (data) {
            hideSpinner();

            if (data.success) {
                paperDisplayInfo(data);
                $("#paper-row-"+$("#paper-details").data("key")).html(data.tr);
                $.notify({ message: "Paper edited successfully" }, { type: "success" });
            }
            else
                $.notify({ message: "Failed to edit paper: "+data.message }, { type: "danger" });
        });

        return false;
    }

    function paperAddNotes () {
        $("#paper-notes-add").hide();
        $("#paper-notes-content").show();
        markdownEditor.codemirror.refresh();
        mdeEditMode();
    }

    function paperSaveNotes() {
        if (!markdownEditor.isPreviewActive())
            markdownEditor.togglePreview();

        $.post("/api/paper/"+$("#paper-details").data("key"),
            {"file" : "md", "field" : "", "value" : markdownEditor.value()},
            function (data) {
                if (data.success) {
                    $.notify({ message: "Notes saved successfully" }, { type: "success" });
                }
                else
                    $.notify({ message: "Failed to save notes: "+data.message }, { type: "danger" });
            }, "json");
    }

    function deletePaper(key) {
        $.get("/api/paper/"+key+"/delete",
            {},
            function (data) {
                if (data.success) {
                    $("#papers-table tr.active").remove();
                    $("#paper-details").hide();
                    $("#paper-bibtex").hide();
                    $("#paper-notes").hide();
                    $("#paper-delete").hide();
                    $("#paper-placeholder").show();
                    $.notify({ message: data.message }, { type: "success" });
                }
                else
                    $.notify({ message: data.message }, { type: "danger" });
            }, "json");
    }


    // register events
    $("#paper-details > *:has(span[data-key])").on("dblclick", paperEditShow);
    $("#paper-notes-add-btn").on("click", paperAddNotes);
    $("#modal-paper-edit").on("shown.bs.modal", function () { $('#modal-paper-edit-value').focus(); });
    $("#modal-paper-edit").on("submit", paperEditForm);
    $("#paper-delete-btn").on("click", function () {
        if (confirm('Are you sure you want to delete this paper ?')) {
            deletePaper($("#paper-details").data("key"));
        }
    });


    ////////////////////////////////////////
    /////// GOOGLE DRIVE
    ////////////////////////////////////////

    var loopCount = 0;

    function driveLogin (url, callback) {
        var left = window.screenX + (window.outerWidth / 2) - (400 / 2);
        var top = window.screenY + (window.outerHeight / 2) - (500 / 2);
        var windowFeatures = "width=400,height=500,top=" + top + ",left=" + left +
                             ",location=yes,toolbar=no,menubar=no";
        var popupWindow = window.open(url, "oauth2_popup", windowFeatures);

        if (!popupWindow || popupWindow.closed || typeof popupWindow.closed == "undefined") {
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

    // register events
    $("#js_drive_fetch").on("click", function () {
        loopCount = 0;
        var key = $("#paper-details").data("key");
        driveAjaxPDF ("fetch", key, "GET");
    });

    $("#js_drive_import").on("click", function () {
        loopCount = 0;
        var key = $("#paper-details").data("key");
        driveAjaxPDF ("upload/url", key, "GET");
    });

    $(document).on("change", "#js_drive_upload :file", function(e) {
        loopCount = 0;
        var key = $("#paper-details").data("key");
        var file = e.target.files[0];
        var formData = new FormData();
        formData.append("pdf", file);
        if (file != undefined) {
            driveAjaxPDF ("upload/post", key, "POST", formData);
        }
     });









    ///////////////////////////////////////////////////////////////////////////
    ///////////////////////////////////////////////////////////////////////////
    /////////////// MIDDLE COLUMN - PAPERS TABLE
    ///////////////////////////////////////////////////////////////////////////
    ///////////////////////////////////////////////////////////////////////////



    ////////////////////////////////////////
    /////// TABLE SORT
    ////////////////////////////////////////

    new Tablesort($("#papers-table")[0]);



    ////////////////////////////////////////
    /////// PAPER TABLE BIND EVENTS
    ////////////////////////////////////////

    function initPapersTableStuff () {
        $('[data-toggle="tooltip"]').tooltip({ container: "body" });
        $("#papers-table .paper").on("click", paperDisplay);
    }

    initPapersTableStuff();









    ///////////////////////////////////////////////////////////////////////////
    ///////////////////////////////////////////////////////////////////////////
    /////////////// TAGS FILTERING
    ///////////////////////////////////////////////////////////////////////////
    ///////////////////////////////////////////////////////////////////////////

    function getTags(group, tag) {
        return $('.paper .label[data-tag="'+tag+'"][data-tag-group="'+group+'"]')
    }

    function resetFilters (tagGroup) {
        $("#papers-col-left").find('ul[data-tag-group="'+tagGroup+'"] .tag').removeClass("tag-active");
    }

    function syncFilters() {
        $(".paper").each(function() {
            var paper = $(this);

            // check if paper possess each filtered tags, if not hide paper
            var hide = false;
            $(".tag-active").each(function() {
                var tag = $(this).data("tag");
                var group = $(this).parent().data("tag-group");
                if (paper.find('.label[data-tag="'+tag+'"][data-tag-group="'+group+'"]').length == 0) {
                    hide = true;
                    return false; // break;
                }
            });

            hide ? paper.hide() : paper.show();
        });
    }

    function colorpickerHandler () {
        var leftTagLabels = $(this).parent().find(".label"); // tag and colorpicker label els
        var tag = $(this).parent().data("tag");
        var tagGroup = $(this).parent().parent().data("tag-group");

        $(".colorpicker span").unbind("click").on("click", function() {
            var color = $(this).data("color");
            leftTagLabels.css("backgroundColor", "#"+color);
            getTags(tagGroup, tag).css("backgroundColor", "#"+color);

            $.post("/api/settings/tag", {group: tagGroup, tag: tag, color: color}, function (data) {
                if (data.success)
                    $.notify({ message: "Tag color saved successfully" }, { type: "success" });
                else
                    $.notify({ message: "Failed to save tag color" }, { type: "danger" });
            }, "json");
        })
    }

    // tags toogle
    $(".tag .tag-label").on("click", function () {
        $(this).parent().toggleClass("tag-active");
        syncFilters();
    });

    // tags reset
    $("#papers-col-left .tags-reset").on("click", function () {
        resetFilters($(this).data("tag-group"));
        syncFilters();
    });

    // colorpicker
    $("#papers-col-left").mouseleave(function() { $(".tag-colorpicker").popover("hide") });
    $(".tag-colorpicker").popover({ container: $("#papers-col-left"), trigger: "focus", template: $("#colorpicker").html(), content:" ", placement: "bottom" });
    $(".tag-colorpicker").on("shown.bs.popover", colorpickerHandler);









    ///////////////////////////////////////////////////////////////////////////
    ///////////////////////////////////////////////////////////////////////////
    /////////////// NAVBAR ACTIONS
    ///////////////////////////////////////////////////////////////////////////
    ///////////////////////////////////////////////////////////////////////////




    ////////////////////////////////////////
    /////// UTILITY FUNCTIONS
    ////////////////////////////////////////


    function ajaxFormProcess(formEl, callback) {
        $.ajax({
            type: formEl.attr("method"),
            url: formEl.attr("action"),
            data: formEl.serialize(),
            dataType: "json",
            success: callback
        });
    }

    function showSpinner (message) {
        $("#modal-spinner").modal({
            keyboard: false,
            backdrop: "static",
            show: true
        });
        $("#modal-spinner-desc").html("");
        $("#modal-spinner-desc").html(message);
    }

    function hideSpinner () {
        $("#modal-spinner").modal("hide");
    }



    ////////////////////////////////////////
    /////// MODALS HANDING
    ////////////////////////////////////////

    function gitPull () {
        showSpinner("Pulling your repository...");

        $.get("/api/git/pull", function (data) {
            if (data.success)
                location.reload();
            else {
                hideSpinner();
                $.notify({ message: "Pull failed: "+data.message }, { type: "danger" });
            }
        }, "json");
    }

    function gitPushModal () {
        $("#modal-push-status").html('<p class="text-center spinner"><i class="fa fa-refresh fa-spin"></i></p>');
        $("#modal-push").modal();

        // show status
        $.get("/api/git/status", function (data) {
            if (data.success)
                $("#modal-push-status").html("<pre>"+data.message+"</pre>");
            else {
                $("#modal-push-status").html("<p>Failed to get git status: "+data.message+"</p>");
                $.notify({ message: "Failed to get git status: "+data.message }, { type: "danger", z_index: 1051 });
            }
        }, "json");
    }

    function gitPushForm () {

        $(this).modal("hide");

        ajaxFormProcess($(this), function (data) {
            if (data.success)
                $.notify({ message: "Commited & pushed with success" }, { type: "success" });
            else
                $.notify({ message: "Commit & push failed: "+data.message }, { type: "danger" });
        });

        return false;
    }

    function paperAdd () {
        var form = $(this);
        form.modal("hide");
        showSpinner("Adding your paper...");

        ajaxFormProcess(form, function (data) {
            hideSpinner();
            if (data.success == true || data.success == "partial") {
                $("#papers-table tbody").prepend(data.html);
                initPapersTableStuff();
                $.notify({ message: "Paper(s) added successfully" }, { type: "success" });
                if ($("#papers-table .paper").length <= 3)
                    $.notify({ message: "You can edit the info and tags of a paper by double-clicking on the fields in the right column of the page" }, { type: "info" });
                if (data.success == "partial")
                    $.notify({ message: "But also failed to add some papers:<br>"+data.message }, { type: "danger", z_index: 1051 });
                else
                    form[0].reset();
            }
            else
                $.notify({ message: "Fail to add paper(s): "+data.message }, { type: "danger",  z_index: 1051 });
        });

        return false;
    }

    function settings () {
        $(this).modal("hide");
        showSpinner("Saving your settings...");

        ajaxFormProcess($(this), function (data) {
            hideSpinner();
            if (data.reload)
                location.reload();
            $.notify({ message: data.message }, { type: data.success ? "success" : "danger" });
        });

        return false;
    }

    // register events
    $("#pull-btn").on("click", gitPull);
    $("#modal-push-btn").on("click", gitPushModal);
    $("#modal-push").on("submit", gitPushForm);
    $("#modal-paper-add").on("submit", paperAdd);
    $("#modal-settings").on("submit", settings);

});


