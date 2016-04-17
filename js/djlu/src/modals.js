function modalsConst() {
  var modals = {
    ajaxFormProcess: ajaxFormProcess,
  };

  function ajaxFormProcess(formEl, callback) {
    $.ajax({
      type: formEl.attr("method"),
      url: formEl.attr("action"),
      data: formEl.serialize(),
      dataType: "json",
      success: callback
    });
  }


  return modals;
}