function uiConst () {

  var ui = {
    init: init,
    refreshTable: refreshTable
  };

  var modals = {
    edit: $("#modal-edit"),
  };
  ui.modals = modals;

  function init () {
    $("#paper-details > *:has(span[data-key])").on("dblclick", djlu.paper.editField);

    modals.edit.find("form").on("submit", function () {
      modals.edit.modal("hide");
      djlu.modals.ajaxFormProcess($(this), djlu.paper.editFieldCallback);
      return false;
    });

    refreshTable();
  }

  function refreshTable () {
    $('[data-toggle="tooltip"]').tooltip({ container: 'body' });
    $("#papers-table .paper").on("click", djlu.paper.details);
  }

  return ui;
}