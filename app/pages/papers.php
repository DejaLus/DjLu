<?php

namespace pages;

/**
 * This page shows the list of papers for a user
 */
class Papers {

    /**
     * Show list
     */
    public static function main ($f3) {

        // TODO

        $f3->set('content','papers.htm');
        echo \Template::instance()->render('layout.htm');
    }

}
