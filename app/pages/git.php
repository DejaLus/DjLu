<?php

namespace pages;

/**
 * This page handle git api calls
 */
class Git {

    private $f3;
    private $user;
    private $model;

    public function __construct () {
        $this->f3 = \Base::instance();
        $this->user = \models\User::instance();
        $this->model = \models\Git::instance();
    }

    /**
     * Check if the API call is legit, precondition to any of the calls here
     */
    private function isPossible () {
        if (!$this->user->isLoggedIn()) {
            echo '{"success": false, "message": "User not logged in"}';
            return false;
        }
        if (!$this->user->getGit()) {
            echo '{"success": false, "message": "No git backend for this user"}';
            return false;
        }
        return true;
    }

    /**
     * API call to pull the repo
     */
    public function pull () {
        if (!$this->isPossible())
            return;

        $out = $this->model->pull();
        echo json_encode(array("success" => $out["success"], "message" => $out["log"]));
    }

    /**
     * API call to get the status of the repo
     */
    public function status () {
        if (!$this->isPossible())
            return;

        $log = $this->model->status();
        echo json_encode(array("success" => true, "message" => $log));
    }

    /**
     * API call to commit and push the changes in the repo
     */
    public function push () {
        if (!$this->isPossible())
            return;

        $message = $this->f3->get("POST.message");
        $out = $this->model->commitPush($message);
        echo json_encode(array("success" => $out["success"], "message" => $out["log"]));
    }
}
