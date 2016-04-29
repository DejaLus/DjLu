$(document).ready(function() {

    'use strict';

    // render markdown
    var markdownEditor = new SimpleMDE({
        element: null,
        renderingConfig: { codeSyntaxHighlighting: true, singleLineBreaks: false }
    });

    if ($("#paper-notes-raw")) {
        var txt = $("#paper-notes-raw").html();
        txt = txt.replace(/(^|[^\\])\$\$/g, "$1`eq2");
        txt = txt.replace(/(^|[^\\])\$/g, "$1`eq");
        var html = markdownEditor.markdown(txt);
        html = html.replace(/<\/?code>eq2/g, "$$$$");
        html = html.replace(/<\/?code>eq/g, "$$");
        html = html_entity_decode(html, "ENT_QUOTES");
        $("#paper-notes-content").html(html);
        setTimeout(function() { MathJax.Hub.Typeset(); }, 750);
    }

    // initiate clipboard js
    new Clipboard('.clipboard');

});


