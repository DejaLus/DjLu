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

        // sort papers by date
        $addDates = array_map(function ($x) { return $x->getDateAdded(); }, $papers);
        array_multisort($addDates, SORT_DESC, $papers);

        // gather tags
        $tags = $this->model->consolidateTags($papers);

        $this->f3->set("papers", $papers);
        $this->f3->set("tags", $tags);
        $this->f3->set("js", array("bcrypt.min.js", "bootstrap3-typeahead.min.js", "simplemde.min.js", "highlight.min.js", "tablesort.min.js", "djlu.js", "clipboard.min.js"));
        $this->f3->set("title", \lib\Formatting::formatUsername($this->user->getUsername())." library");
        $this->f3->set("content", "papers.htm");
        echo \Template::instance()->render("layout.htm");
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

            // construct paper & tags for display
            $tags = $this->model->consolidateTags(array($paper), false);
            $this->f3->set("tags", $tags);
            $this->f3->set("paper", $paper);

            // get output
            $out = $paper->getFiles();
            $out["success"] = true;
            $out["tr"] = \Template::instance()->render("paper.htm", "text/html");
            $out["tags"] = \Template::instance()->render("tagmenu.htm", "text/html");

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
            if (!empty($f3->get("POST.raw")))
                $output = \models\Paper::createShort($f3->get("POST.raw"), $f3->get("POST.citationKey"));
            elseif (!empty($f3->get("POST.id")))
                $output = \models\Paper::createFromID($f3->get("POST.id"), $f3->get("POST.citationKey"));
            else
                $output = \models\Paper::createFromBibTex($f3->get("POST.bibtex"), $f3->get("POST.citationKey"));

            // init output
            $out = array("success" => true, "html" => "");
            if ($output["errors"]) {
                $out["success"] = "partial";
                $out["message"] = implode("<br>", $output["errors"]);
            }

            // list successful papers
            $papers = $output["sucesses"];
            $addDates = array_map(function ($x) { return $x->getDateAdded(); }, $papers);
            array_multisort($addDates, SORT_DESC, $papers);
            $this->f3->set("tags", $this->model->consolidateTags($papers, true, false));

            // get HTML of the papers
            foreach ($papers as $paper) {
                $this->f3->set("paper", $paper);
                $out["html"] .= \Template::instance()->render("paper.htm", "text/html");
            }

            echo json_encode($out);
        }
        catch (\Exception $e) {
            echo json_encode(array("success" => false, "message" => nl2br($e->getMessage())));
        }
    }

    /**
     * Delete one paper
     */
    public function apiPaperDel ($f3, $args) {

        try {
            if (!$this->user->isLoggedIn())
                throw new \Exception("User not logged in");

            // short paper ref
            if (strpos($args["key"], "short_") === 0) {
                $path = $this->f3->get("DATA_PATH").$this->user->getUsername()."/".substr($args["key"], 6).".txt";
                if (!is_file($path))
                    throw new \Exception("Paper ".$args["key"]." not found");
                if (!unlink($path))
                    throw new \Exception("Fail to delete paper ".$args["key"]);
            }
            else {
                $paper = new \models\Paper($args["key"]);

                if (!$paper->delete())
                    throw new \Exception("Fail to delete paper ".$args["key"]);

                $prefs = $this->user->getPreferences();
                $drive = new \models\GoogleDrive($prefs["googleDriveRoot"]);

                if ($drive->isLoggedIn())
                    $drive->deletePaper($args['key']);

            }

            echo '{"success": true, "message": "'.$args['key'].' has been deleted."}';
        }
        catch (\Exception $e) {
            echo json_encode(array("success" => false, "message" => $e->getMessage()));
        }
    }

    /**
     * Display info for a specific paper
     */
    public function display ($f3, $args) {

        try {

            // paper info
            if (empty($args["key"]))
                throw new \Exception("No paper key given");

            $paper = new \models\Paper($args["key"], $args["user"]); // fails here if private access and not logged in

            if (!$paper->exists())
                throw new \Exception("Paper does not exist");

            // check public access
            if (!empty($args["user"]) &&
                (!$this->user->isLoggedIn() || $this->user->getUsername() != $args["user"])
                && (!isset($args["secret"]) || empty($paper->jsonField("secret"))
                || $args["secret"] != $paper->jsonField("secret"))) {
                throw new \Exception("You are not allowed to view this");
            }

            // gather tags
            if ($this->user->isLoggedIn())
                $tags = $this->model->getDeclaredTags();
            else
                $tags = $this->model->getInviteTags(array($paper));

            // display
            $this->f3->set("paper", $paper);
            $this->f3->set("tags", $tags);
            $this->f3->set("js", array("bcrypt.min.js", "simplemde.min.js", "highlight.min.js", "clipboard.min.js", "djluPaper.js"));
            $this->f3->set("title", $paper->jsonField("title"));
            $this->f3->set("content", "paperDisplay.htm");
            echo \Template::instance()->render("layout.htm");
        }
        catch (\Exception $e) {
            \lib\Flash::instance()->addMessage("Unable to display paper", "danger");
            $f3->reroute("@home");
        }
    }
}
