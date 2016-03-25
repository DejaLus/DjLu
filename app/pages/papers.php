<?php

namespace pages;

/**
 * This page shows the list of papers for a user
 */
class Papers {

    private $f3;
    private $user;

    public function __construct () {
        $this->f3 = \Base::instance();
        $this->user = \models\User::instance();
    }

    /**
     * Show list of papers for the authed user
     */
    public function listAll () {

        // check if logged in
        if (!$this->user->isLoggedIn())
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

        $this->f3->set("papers", $papers);
        $this->f3->set("content", "papers.htm");
        echo \Template::instance()->render("layout.htm");
    }

}
