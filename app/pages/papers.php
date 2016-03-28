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

    /**
     * API call to get detail infos about a paper
     */
    public function apiPaperInfo ($f3, $args) {
        $paper = new \models\Paper($args['key']);
        echo json_encode($paper->getFiles());
    }

    /**
     * API call to edit details of a paper
     */
    public function apiPaperEdit ($f3, $args) {

        $paper = new \models\Paper($args["key"]);
        $success = $paper->edit($f3->get("POST.field"), $f3->get("POST.value")) !== false;
        $out = $success ? $paper->getFiles() : array();
        $out["success"] = $success;

        // TODO optimize this...
        // get the new tr
        if ($success) {
            // we need to get all papers just to be able to compute labels list and colors :(
            $papers = $this->model->getPapers();
            $addDates = array_map(function ($x) { return $x["date_added"]; }, $papers);
            array_multisort($addDates, SORT_DESC, $papers);
            $this->f3->set("tags", $this->model->getTags($papers));

            // get the right paper and get HTML
            $this->f3->set("paper", $papers[$args["key"]]);
            $out["tr"] = \Template::instance()->render("paper.htm", "text/html");
        }

        echo json_encode($out);
    }

    /**
     * Parse a bibtex to extract basic infos
     */
    public function apiPaperAdd ($f3) {
        $paper = new \models\Paper();
        try {
            $paper->createFromBibTex($f3->get("POST.bibtex"));

            $out = array("success" => true, "tr" => "");

            // TODO optimize this...
            $papers = $this->model->getPapers();
            $addDates = array_map(function ($x) { return $x["date_added"]; }, $papers);
            array_multisort($addDates, SORT_DESC, $papers);
            $this->f3->set("tags", $this->model->getTags($papers));

            // get the right paper and get HTML
            $key = $paper->getKey();
            $this->f3->set("paper", $papers[$key]);
            $out["tr"] .= '<tr class="paper" data-paper-key="'.$key.'" id="paper-row-'.$key.'">';
            $out["tr"] .= \Template::instance()->render("paper.htm", "text/html");
            $out["tr"] .= '</tr>';

            echo json_encode($out);
        }
        catch (\Exception $e) {
            echo json_encode(array("success" => false, "message" => $e->getMessage()));
        }
    }

}
