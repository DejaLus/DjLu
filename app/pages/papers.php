<?php

namespace pages;

/**
 * This page shows the list of papers for a user
 */
class Papers {

    private $f3;

    /**
     * Check if the user is logged in
     */
    private function isLoggedIn () {
        if ($this->f3->exists("SESSION.username"))
            return true;
        $login = new Login();
        return $login->autologin();
    }

    /**
     * Show list of papers for the authed user
     */
    public function listAll ($f3) {
        $this->f3 = $f3;

        // check if logged in
        if (!$this->isLoggedIn())
            $this->f3->reroute("@home");

        // TODO list papers

        // mockup list for preview
        $papers = array(
            array(
            "author" => "Frome, Andrea and Corrado, Greg S. and Shlens, Jon and Bengio, Samy and Dean, Jeff and Ranzato, MarcAurelio and Mikolov, Tomas",
            "title" => "DeViSE: A Deep Visual-Semantic Embedding Model",
            "year" => "2013"),
            array(
            "author" => "Kawano, Yoshiyuki and Yanai, Keiji",
            "title" => "Food image recognition with deep convolutional features",
            "year" => "2014"),
            array(
            "author" => "Chatfield, Ken and Simonyan, Karen and Vedaldi, Andrea and Zisserman, Andrew",
            "title" => "Return of the Devil in the Details: Delving Deep into Convolutional Nets",
            "year" => "2014")
        );

        $f3->set("papers", $papers);
        $f3->set("content", "papers.htm");
        echo \Template::instance()->render("layout.htm");
    }

}
