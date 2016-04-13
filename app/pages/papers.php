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
        $this->f3->set("js", array("simplemde.min.js", "highlight.min.js", "tablesort.min.js", "djlu.js"));
        $this->f3->set("content", "papers.htm");
        echo \Template::instance()->render("layout.htm");
    }

    /**
     * API call to pull the repo
     */
    public function apiPull () {
        if (!$this->user->isLoggedIn()) {
            echo '{"success": false, "message": "User not logged in"}';
            return;
        }

        $out = \models\Git::instance()->pull();
        echo json_encode($out);
    }

    /**
     * API call to get the status of the repo
     */
    public function apiStatus () {
        if (!$this->user->isLoggedIn()) {
            echo '{"success": false, "message": "User not logged in"}';
            return;
        }

        echo \models\Git::instance()->status();
    }

    /**
     * API call to commit and push the changes in the repo
     */
    public function apiPush () {
        if (!$this->user->isLoggedIn()) {
            echo '{"success": false, "message": "User not logged in"}';
            return;
        }

        $message = $this->f3->get("POST.message");
        $out = \models\Git::instance()->commitPush($message);
        echo json_encode($out);
    }

    /**
     * API call to get detail infos about a paper
     */
    public function apiPaperInfo ($f3, $args) {
        if (!$this->user->isLoggedIn()) {
            echo '{"success": false, "message": "User not logged in"}';
            return;
        }

        $paper = new \models\Paper($args['key']);
        echo json_encode($paper->getFiles());
    }

    /**
     * API call to edit details of a paper
     */
    public function apiPaperEdit ($f3, $args) {
        if (!$this->user->isLoggedIn()) {
            echo '{"success": false, "message": "User not logged in"}';
            return;
        }

        $paper = new \models\Paper($args["key"]);
        try {
            $paper->edit($f3->get("POST.file"), $f3->get("POST.field"), $f3->get("POST.value"));

            // TODO make this optional
            $out = $paper->getFiles();
            $out["success"] = true;

            // TODO optimize this and make it optional...
            // get the new tr
            // we need to get all papers just to be able to compute labels list and colors :(
            $papers = $this->model->getPapers();
            $addDates = array_map(function ($x) { return $x["date_added"]; }, $papers);
            array_multisort($addDates, SORT_DESC, $papers);
            $this->f3->set("tags", $this->model->getTags($papers));

            // get the right paper and get HTML
            $this->f3->set("paper", $papers[$args["key"]]);
            $out["tr"] = \Template::instance()->render("paper.htm", "text/html");

            echo json_encode($out);
        }
        catch (\Exception $e) {
            echo json_encode(array("success" => false, "message" => $e->getMessage()));
        }
    }

    /**
     * Parse a bibtex to extract basic infos
     */
    public function apiPaperAdd ($f3) {
        if (!$this->user->isLoggedIn()) {
            echo '{"success": false, "message": "User not logged in"}';
            return;
        }

        try {
            // save the paper(s) and get success keys
            if (!empty($f3->get("POST.id")))
                $output = \models\Paper::createFromID($f3->get("POST.id"), $f3->get("POST.citationKey"));
            else
                $output = \models\Paper::createFromBibTex($f3->get("POST.bibtex"), $f3->get("POST.citationKey"));

            // init output
            $out = array("success" => true, "html" => "");
            if ($output["errors"]) {
                $out["success"] = "partial";
                $out["message"] = implode("<br>", $output["errors"]);
            }

            // TODO optimize this...
            $papers = $this->model->getPapers();
            $addDates = array_map(function ($x) { return $x["date_added"]; }, $papers);
            array_multisort($addDates, SORT_DESC, $papers);
            $this->f3->set("tags", $this->model->getTags($papers));

            // get the right paper and get HTML
            foreach ($output["keys"] as $key) {
                $this->f3->set("paper", $papers[$key]);
                $out["html"] .= '<tr class="paper" data-paper-key="'.$key.'" id="paper-row-'.$key.'">';
                $out["html"] .= \Template::instance()->render("paper.htm", "text/html");
                $out["html"] .= '</tr>';
            }

            echo json_encode($out);
        }
        catch (\Exception $e) {
            echo json_encode(array("success" => false, "message" => nl2br($e->getMessage())));
        }
    }

}
