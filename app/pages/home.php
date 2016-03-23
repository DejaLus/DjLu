<?php

namespace pages;

/**
 * Display the homepage of the project
 */
class Home {

    public static function main ($f3) {
        $f3->set('content', 'home.htm');
        echo \Template::instance()->render('layout.htm');
    }
}
