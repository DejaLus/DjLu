<?php

namespace pages;

/**
 * This page shows the list of papers for a user
 */
class Papers {

    private $f3;
    private $user;
    private $model;

    public function __construct () {
        $this->f3 = \Base::instance();
        $this->user = \models\User::instance();
        $this->model = \models\Papers::instance();
    }

    /**
     * Show list of papers for the authed user
     */
    public function listAll () {

        // check if logged in
        if (!$this->user->isLoggedIn())
            $this->f3->reroute("@home");

        // get papers list
        $papers = $this->model->getPapers();
        $addDates = array_map(function ($x) { return $x["date_added"]; }, $papers);
        array_multisort($addDates, SORT_DESC, $papers);

        // gather tags
        $tags = $this->model->getTags($papers);

        $this->f3->set("papers", $papers);
        $this->f3->set("tags", $tags);
        $this->f3->set("content", "papers.htm");
        echo \Template::instance()->render("layout.htm");
    }

}
