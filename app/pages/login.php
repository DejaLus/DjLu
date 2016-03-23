<?php

namespace pages;

/**
 * This page shows login form or process login
 */
class Login {

    /**
     * Process login
     */
    public static function login ($f3, $args) {
        // TODO
    }

    public static function show ($f3) {
        $f3->set('content', 'login.htm');
        echo \Template::instance()->render('layout.htm');
    }
}
