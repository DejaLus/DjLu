<?php

namespace pages;

/**
 * Display the homepage of the project
 */
class Home {

    public static function main ($f3) {
        $f3->set("content", "home.htm");
        $f3->set("js", array("bcrypt.min.js", "jquery.fittext.js", "jquery.easing.min.js", "djluHome.js"));

        echo \Template::instance()->render("layout.htm");
    }
}
