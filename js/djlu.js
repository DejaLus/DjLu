$(document).ready(function() {

    'use strict';
    $.notifyDefaults({
        placement: {
            from: "bottom"
        },
        animate:{
            enter: "animated fadeInUp",
            exit: "animated fadeOutDown"
        }
    });

    ///////////////////////////////////////////////////////////////////////////
    ///////////////////////////////////////////////////////////////////////////
    /////////////// RIGHT COLUMN
    ///////////////////////////////////////////////////////////////////////////
    ///////////////////////////////////////////////////////////////////////////


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


    ////////////////////////////////////////
    /////// MARKDOWN EDITOR
    ////////////////////////////////////////

    var mathTimeout;

    function renderWithMath (txt) {
        txt = txt.replace(/(^|[^\\])\$\$/g, "$1`eq2");
        txt = txt.replace(/(^|[^\\])\$/g, "$1`eq");
        var html = markdownEditor.markdown(txt);
        html = html.replace(/<\/?code>eq2/g, "$$$$");
        html = html.replace(/<\/?code>eq/g, "$$");
        html = html_entity_decode(html, "ENT_QUOTES");
        clearTimeout(mathTimeout);
        mathTimeout = setTimeout(function() { MathJax.Hub.Typeset(); }, 750);
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
        $("#paper-details .url").attr("href", getOrElse(data.json, "url", "")).html(getOrElse(data.json, "url", ""));
        $("#paper-details .rating").html(getOrElse(data.json, "rating", ""));
        var secret = getOrElse(data.json, "secret", "");
        $("#paper-details .secret").html(secret)
            .attr("href", $("#paper-details .secret").data("base-url")
            .replace("@secret", secret).replace("@key", $("#paper-details").data("key")));
        $("#paper-secret-url").attr("data-clipboard-text", $("#paper-details .secret").attr("href"));

        // bibtex
        if (data.bibRaw !== undefined) {
            $("#paper-bibtex-content").html(data.bibRaw);
            $("#paper-bibtex").show();
        }
        if (typeof data.bib == 	"object" && data.bib !== null && data.bib.html !== undefined) {
            $("#paper-citation").html(data.bib.html);
            $("#paper-bibtex").show();
        }
    }

    function unblockSide() {
        // display all
        $("#paper-wait").hide();
        $("#paper-details").show();
        $("#paper-notes-add").hide();
        $("#paper-notes-content").hide();
        $("#paper-notes").show();
        $("#paper-delete").show();
    }

    function ajaxError() {
        $("#papers-table .paper").removeClass("active");
        $("#paper-wait").hide();
        $("#paper-placeholder").show();
        $.notify({ message: "Oops. This paper did not load well.<br/>Please check your network connection. If everything is OK, report this bug with information about the paper" }, { type: "danger" });
    }

    function ajaxSuccess(data, key) {
        if (data.success === false) {
            $.notify({ message: "Failed to get paper's info: "+data.message }, { type: "danger" });
            return;
        }

        $("#paper-details").data("key", key);
        $("#paper-details .citationKey").html(key);
        paperDisplayInfo(data);

        unblockSide();

        // notes
        if (data.md !== undefined) {
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

        $.ajax({
            url: "/api/paper/"+key,
            success: function(data){ajaxSuccess(data,key);},
            error: function(){ajaxError(key);},
            timeout: 2000,
            dataType: 'JSON'
        });
    }



    ////////////////////////////////////////
    /////// PAPER EDIT
    ////////////////////////////////////////

    function enableAutocomplete (el, sourceData) {

        var source = Object.keys(sourceData);

        function tagsEntered(query) {
            var tags = query.split(";")
                .map(function(x) { return x.trim(); });
            tags.pop();
            return tags;
        }

        function extractor(query) {
            var result = /([^;]+)$/.exec(query);
            if(result && result[1])
                return result[1].trim();
            return '';
        }

        el.attr("autocomplete", "off").typeahead("destroy").typeahead({
            source: source,
            updater: function(item) {
                return this.$element.val().replace(/[^;]*$/,'')+" "+item+'; ';
            },
            showHintOnFocus: true,
            matcher: function (item) {
                // match if not already in the entered tags
                var tags = tagsEntered(this.query);
                tags = tags.map(function (x) { return x.toLowerCase(); });
                return (tags.indexOf(item.toLowerCase()) == -1);
            },
            sorter: function (items) {
                // sort by beginWith > contains > others, and by popularity in each category
                var beginswith = [], contains = [], others = [], item;
                var tquery = extractor(this.query).toLowerCase();

                items.sort(function (a, b) {
                    return sourceData[b]["count"] - sourceData[a]["count"];
                });

                while (item = items.shift()) {
                    var it = this.displayText(item).toLowerCase();
                    if (it.indexOf(tquery) == 0)
                        beginswith.push(item);
                    else if (it.indexOf(tquery) == -1)
                        others.push(item);
                    else
                        contains.push(item);
                }

                return beginswith.concat(contains);
            },
            highlighter: function (item) {
                var query = extractor(this.query).replace(/[\-\[\]{}()*+?.,\\\^$|#\s]/g, '\\$&')
                return item.replace(new RegExp('(' + query + ')', 'ig'), function ($1, match) {
                    return '<strong>' + match + '</strong>'
                })
            }
        });
    }

    function disableAutocomplete (el) {
        el.removeAttr("autocomplete").typeahead("destroy");
    }

    function paperEditShow () {

        var el = ($(this).attr("data-key")) ? $(this) : $(this).children("[data-key]")

        // get info element
        var form = $("#modal-paper-edit");
        var autocomplete = el.data("autocomplete");

        // handle autocomplete
        if (autocomplete)
            enableAutocomplete(form.find('[name="value"]'), tags[autocomplete]);
        else
            disableAutocomplete(form.find('[name="value"]'));

        form.attr("action", form.data("base-url").replace("@key", $("#paper-details").data("key")));
        form.find('[name="field"]').val(el.data("key"));
        form.find('[name="file"]').val(el.data("file") ? el.data("file") : "json");
        if (el.data("type") == "textarea") {
            form.find('#modal-paper-edit-textarea-value').val(el.html()).attr("name", "value").show();
            form.find('#modal-paper-edit-value').attr("name", "").hide();
        }
        else {
            form.find('#modal-paper-edit-textarea-value').attr("name", "").hide();
            form.find('#modal-paper-edit-value').attr("name", "value").val(el.html()).show();
        }
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
                $("#paper-row-"+$("#paper-details").data("key")).replaceWith(data.tr);
                $("#paper-row-"+$("#paper-details").data("key")).addClass("active");
                $("#papers-col-left-container").html(data.tags);
                initPapersTableStuff();
                initLeftCol();
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
            function (data) {
                if (data.success) {
                    $("#papers-table #paper-row-"+key).remove();
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
    $("#paper-details > *:has([data-key])").on("dblclick", paperEditShow);
    $("#paper-bibtex-content").on("dblclick", paperEditShow);
    $("#paper-notes-add-btn").on("click", paperAddNotes);
    $("#modal-paper-edit").on("shown.bs.modal", function () { $('#modal-paper-edit-value').focus(); });
    $("#modal-paper-edit").on("submit", paperEditForm);
    $("#paper-delete-btn").confirmation({
        btnOkIcon: "",
        btnCancelIcon: "",
        popout: true,
        btnOkClass: "btn-sm btn-danger",
        btnCancelClass: "btn-sm btn-default",
        btnOkLabel: 'Yes, delete it',
        btnCancelLabel: 'No, cancel',
        title: "Are you sure?",
        onConfirm: function () {
            deletePaper($("#paper-details").data("key"));
        }
    });

    $("#js-remove-sharing-key").on("click", function () {
        $("#paper-details .secret").html("");
        paperEditShow.call($(this).parent().parent());
    });
    $("#js-generate-sharing-key").on("click", function () {
        $("#paper-details .secret").html(generateString(30));
        paperEditShow.call($(this).parent().parent());
    });


    ////////////////////////////////////////
    /////// GOOGLE DRIVE
    ////////////////////////////////////////

    var loopCount = 0;

    function checkGDriveAvailability() {
        return $("#has_gdrive").data("return") == true;
    }

    function driveLogin (url, callback) {
        if(checkGDriveAvailability()) {
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
    }

    function driveAjaxPDF (obj, type, paper, ajaxMethod, formData) {
        if(checkGDriveAvailability()) {
            var l = $(obj).ladda();
            l.ladda('start');
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
                            driveLogin(data.url, function () { return driveAjaxPDF (obj, type, paper, ajaxMethod, formData); });
                        return;
                    }

                    if (data.success == false) {
                        $.notify({ message: data.message }, { type: "danger" });
                        l.ladda('stop');
                        return;
                    }

                    $("#paper-details .url").html(data.url).attr("href", data.url);
                    $(".paper.active a.pdf").attr("href", data.url);
                    $.notify({ message: data.message }, { type: "success" });
                    l.ladda('stop');
                },
                fail: function () {
                    l.ladda('stop');
                }
            });
        }
    }

    // register events
    if(checkGDriveAvailability()) {
        $("#js_drive_fetch").on("click", function () {
            loopCount = 0;
            var key = $("#paper-details").data("key");
            driveAjaxPDF (this, "fetch", key, "GET");
        });

        $("#js_drive_import").on("click", function () {
            loopCount = 0;
            var key = $("#paper-details").data("key");
            driveAjaxPDF (this, "upload/url", key, "GET");
        });

        $(document).on("change", "#js_drive_upload :file", function(e) {
            loopCount = 0;
            var key = $("#paper-details").data("key");
            var file = e.target.files[0];
            var formData = new FormData();
            formData.append("pdf", file);
            if (file != undefined) {
                driveAjaxPDF ($("#js_drive_upload"), "upload/post", key, "POST", formData);
            }
         });
    } else {
        $("#js_drive_fetch").attr("disabled", true);
        $("#js_drive_import").attr("disabled", true);
        $("#js_drive_upload").attr("disabled", true);
    }







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
        $("#papers-table .paper-full").on("click", paperDisplay);
        $("#papers-table .paper-short .delete").confirmation({
            container: "body",
            popout: true,
            btnOkIcon: "",
            btnCancelIcon: "",
            btnOkClass: "btn-sm btn-danger",
            btnCancelClass: "btn-sm btn-default",
            btnOkLabel: 'Yes, delete it',
            btnCancelLabel: 'No, cancel',
            title: "Are you sure?",
            onConfirm: function () {
                deletePaper($(this).parent().parent().data("paper-key"));
            }
        });
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

    function sortTags (tagGroup, currentSort=0) {
        var sortButton = $("#papers-col-left").find('a[data-tag-group="'+tagGroup+'"] .isort');
        var ul = $("#papers-col-left").find('ul[data-tag-group="'+tagGroup+'"]');
        var tags = $.makeArray(ul.children());
        if (currentSort == 1) {
            sortButton.removeClass("fa-sort-amount-asc");
            sortButton.addClass("fa-sort-amount-desc");
        } else if (currentSort == 2) {
            sortButton.removeClass("fa-sort-amount-desc");
            sortButton.addClass("fa-bars");
        } else {
            currentSort = 0;
            sortButton.removeClass("fa-bars");
            sortButton.addClass("fa-sort-amount-asc");
        }
        Cookies.set("sort-state-" + tagGroup, currentSort);
        tags.sort(function(a, b) {
            if (currentSort == 0) {
                var vA = String($(a).data("tag")).toLowerCase();
                var vB = String($(b).data("tag")).toLowerCase();
            } else if (currentSort == 1) {
                var vA = String($(b).data("tag")).toLowerCase();
                var vB = String($(a).data("tag")).toLowerCase();
            } else {
                var vA = +$(a).data("pos");
                var vB = +$(b).data("pos");
            }
            if (vA < vB) return -1;
            if (vA > vB) return 1;
            return 0;
        });
        ul.empty();
        $.each(tags, function() {
            ul.append(this);
        });
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
            var oldColor = leftTagLabels.css("backgroundColor");
            leftTagLabels.css("backgroundColor", "#"+color);
            getTags(tagGroup, tag).css("backgroundColor", "#"+color);

            $.post("/api/preferences", {field: "tags."+tagGroup+"."+tag+".color", value: color}, function (data) {
                $.notify({ message: data.message }, { type: data.success ? "success" : "danger" });
                if (!data.success) {
                    leftTagLabels.css("backgroundColor", oldColor);
                    getTags(tagGroup, tag).css("backgroundColor", oldColor);
                }
            }, "json");
        })
    }

    function toggleTagPin () {
        var el = $(this);
        el.toggleClass("pinned");
        var tag = el.parent().data("tag");
        var tagGroup = el.parent().parent().data("tag-group");
        var val = el.hasClass("pinned");

        $.post("/api/preferences", {field: "tags."+tagGroup+"."+tag+".pinned", value: val}, function (data) {
            $.notify({ message: data.message }, { type: data.success ? "success" : "danger" });
            if (!data.success)
                el.toggleClass("pinned");
        }, "json");
    }

    function fixColLeft () {
        $(this).toggleClass("pinned");
        if ($(this).hasClass("pinned")) {
            $('#papers-container').addClass("fixed-col-left");
            Cookies.set("fix-col-left", true);
        } else {
            $('#papers-container').removeClass("fixed-col-left");
            Cookies.remove("fix-col-left");
        }
    }

    function activateTags() {
        $(".tag .tag-label").on("click", function () {
            $(this).parent().toggleClass("tag-active");
            syncFilters();
        });

        // colorpicker
        $("#papers-col-left").mouseleave(function() { $(".tag-colorpicker").popover("hide") });
        $(".tag-colorpicker").popover({ container: $("#papers-col-left"), trigger: "focus", template: $("#colorpicker").html(), content:" ", placement: "bottom" });
        $(".tag-colorpicker").on("shown.bs.popover", colorpickerHandler);

        // pin toggle
        $(".tag .tag-pin").on("click", toggleTagPin);


    }

    function initLeftCol () {
        for (var cookie in Cookies.get()) {
            if (cookie.startsWith("sort-state-")) {
                sortTags(cookie.substring(11), Cookies.get(cookie));
            }
        }

        // fix col left
        if (Cookies.get("fix-col-left")) {
            $("#fix-col-left").toggleClass("pinned");
            $('#papers-container').addClass("fixed-col-left");
        }
        $("#fix-col-left").on("click", fixColLeft);

        // sort tags
        $("#papers-col-left .sort").on("click", function () {
            var tagGroup = $(this).data("tag-group");
            if (Cookies.get("sort-state-" + tagGroup) !== undefined) {
                sortTags(tagGroup, +Cookies.get("sort-state-" + tagGroup)+1);
            } else {
                sortTags(tagGroup);
            }
            activateTags();
        });

        // tags reset
        $("#papers-col-left .tags-reset").on("click", function () {
            resetFilters($(this).data("tag-group"));
            syncFilters();
        });

        activateTags();
    }

    initLeftCol();






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

    function bibtexInit () {
        $("#i_bibtex").val(
            "@article{,\n"+
            "  author = {},\n"+
            "  year = {},\n"+
            "  title = {},\n"+
            "  booktitle = {},\n"+
            "  journal = {},\n"+
            "  volume = {}\n"+
            "}");
    }

    function settingsSubmit () {

        var input = $(this).find("input");
        var btn = $(this).find("button[type=submit]")

        btn.attr("disabled", "");
        btn.find("i").removeClass("fa-check").addClass("fa-refresh fa-spin");

        ajaxFormProcess($(this), function (data) {
            input.removeAttr("disabled");
            btn.find("i").addClass("fa-check").removeClass("fa-refresh fa-spin");
            if (data.reload)
                location.reload();
            $.notify({ message: data.message }, { type: data.success ? "success" : "danger", z_index: 1051 });
        });

        input.attr("disabled", "");

        return false;
    }

    function settingsEdit () {
        $(this).parents("form.single-field-edit")
            .find("button[type=submit]").removeAttr("disabled");
    }

    function driveLogout () {
        var el = $(this);
        $.get(el.attr("href"), function () {
            el.replaceWith("DjLu is logged out from your Google Drive");
        });
        return false;
    }

    function hideAllSettings () {
        $("#change-password-div").hide();
        $("#btn-change-password-confirm").hide();
        $("#i_old_password").val('');
        $("#i_old_password4").val('');
        $("#i_new_password").val('');
        $("#i_new_password4").val('');
        $("#i_confirm_password").val('');
        $("#i_confirm_password4").val('');
        $("#btn-change-password").text('Change Password');
    }

    $("#btn-change-password").on("click", function () {
        if ($('#change-password-div').is(':visible')) {
            hideAllSettings();
        } else {
            $("#change-password-div").show();
            $("#btn-change-password-confirm").show();
            $("#btn-change-password").text('Cancel');
        }
    });

    $("#btn-change-password-confirm").on("click", function () {
        if($("#i_old_password").val().length < 8 || $("#i_old_password").val().length > 16 || $("#i_new_password").val().length < 8 || $("#i_new_password").val().length > 16) {
            $.notify({ message: "Password must have at least 8 and at most 16 characters." }, { type: "danger", z_index: 1051 });
            return false;
        }
        if($("#i_new_password").val() != $("#i_confirm_password").val()) {
            $.notify({ message: "New password and its confirmation must match." }, { type: "danger", z_index: 1051 });
            return false;
        }
        
        var hash1 = hash($("#i_old_password").val(), true).substring(29);
        var hash2 = hash(hash1.concat($("#s_sid_c").val()), false);
        $("#i_old_password4").val(hash2);

        $("#i_new_password4").val(hash($("#i_new_password").val(), true).substring(29));
        $("#change-form").submit();
    });
    
    $('#modal-settings').on('hidden.bs.modal', function () {
        hideAllSettings();
    });

    // register events
    $("#pull-btn").on("click", gitPull);
    $("#modal-push-btn").on("click", gitPushModal);
    $("#modal-push").on("submit", gitPushForm);
    $("#modal-paper-add").on("submit", paperAdd);
    $("#bibtex-init").on("click", bibtexInit);
    $("#modal-settings form.single-field-edit input").on("input", settingsEdit);
    $("#modal-settings form.single-field-edit").on("submit", settingsSubmit);
    $("#driveLogout").on("click", driveLogout);

    // initiate clipboard js
    new Clipboard('.clipboard');

});
