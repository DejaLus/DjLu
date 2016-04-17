function paperConst () {

  var paper = {
    displayInfo : displayInfo,
    details : details,
    editField : editField,
  };

  function details () {

    $("#paper-placeholder").hide();
    $("#paper-details").hide();
    $("#paper-bibtex").hide();
    $("#paper-notes").hide();
    $("#paper-wait").show();
    $("#papers-table .paper").removeClass("active");
    $(this).addClass("active");

    var key = $(this).data("paper-key");

    $.get("/api/paper/"+key, function (data) {

      $("#paper-details").data("key", key);
      $("#paper-details .citationKey").html(key);
      djlu.paper.displayInfo(data.json);

      // bibtex
      if (data.bibRaw !== undefined) {
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
      if (data.md !== undefined) {
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
  }

  function displayInfo (data) {
    $("#paper-details .title").html(djlu.utils.getOrElse(data, "title", ""));
    $("#paper-details .authors").html(djlu.utils.getOrElse(data, "authors", [], "; "));
    $("#paper-details .in").html(djlu.utils.getOrElse(data, "in", ""));
    $("#paper-details .year").html(djlu.utils.getOrElse(data, "year", ""));
    $("#paper-details .tags_content").html(djlu.utils.getOrElse(data, "tags_content", [], "; "));
    $("#paper-details .tags_reading").html(djlu.utils.getOrElse(data, "tags_reading", [], "; "));
    $("#paper-details .tags_notes").html(djlu.utils.getOrElse(data, "tags_notes", [], "; "));
    $("#paper-details .date_added").html(djlu.utils.getOrElse(data, "date_added", ""));
    $("#paper-details .url").html(djlu.utils.getOrElse(data, "url", ""));
    $("#paper-details .rating").html(djlu.utils.getOrElse(data, "rating", ""));
  }

  var modalEdit = $("#modal-edit");

  function editField () {

    // get info element
    var el = $(this).children("span[data-key]");
    var key = $("#paper-details").data("key");
    var field = el.data("key");

    $("#i_edit_label").html(el.data("title"));
    modalEdit.find("input[name=file]").val("json");
    modalEdit.find("input[name=field]").val(field);
    modalEdit.find("textarea[name=value]").val(el.html());

    modalEdit.modal("show");
  }

  function editFieldCallback(data) {
    if (data.success) {
      displayInfo(data);
      var key = $("#paper-details").data("key");
      $("#paper-row-"+key).html(data.tr);
      $.notify({ message: "Paper edited successfully" }, { type: "success" });
    }
    else
      $.notify({ message: "Failed to edit paper: "+data.message }, { type: "danger" });
  }

  return paper;
}