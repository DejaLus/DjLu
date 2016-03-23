<?php

namespace pages;

/**
 * This homepage redirect users either to the user's papers list or to the login page if
 * no user is logged in.
 */
class Home {

    public static function main ($f3) {

        // TODO

        $f3->reroute("@login");
    }
}