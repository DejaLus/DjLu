function djluConst() {

  var djlu = {
    utils: utilsConst(),
    paper: paperConst(),
    //papers: papersConst(),
    ui: uiConst()
  };

  // import "utils.js"
  // import "ui.js"
  // import "papers.js"
  // import "paper.js"

  //djlu.papers.init();
  djlu.ui.init();

  return djlu;
}