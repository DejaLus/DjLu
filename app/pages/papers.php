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
        if (!$this->user->isLoggedIn()) {
            \lib\Flash::instance()->addMessage("You need to be logged in to access your library", "danger");
            $this->f3->reroute("@home");
        }

        // get papers list
        $papers = $this->model->getPapers();
        $addDates = array_map(function ($x) { return $x["date_added"]; }, $papers);
        array_multisort($addDates, SORT_DESC, $papers);

        // gather tags
        $tags = $this->model->getTags($papers);

        $this->f3->set("papers", $papers);
        $this->f3->set("tags", $tags);
        $this->f3->set("js", "djlu.js");
        $this->f3->set("content", "papers.htm");
        echo \Template::instance()->render("layout.htm");
    }

    /**
     * API call to pull the repo
     */
    public function apiPull () {
        $out = \models\Git::instance()->pull();
        echo json_encode($out);
    }

    /**
     * API call to get the status of the repo
     */
    public function apiStatus () {
        echo \models\Git::instance()->status();
    }

    /**
     * API call to commit and push the changes in the repo
     */
    public function apiPush () {
        $message = $this->f3->get("POST.message");
        $out = \models\Git::instance()->commitPush($message);
        echo json_encode($out);
    }
}
