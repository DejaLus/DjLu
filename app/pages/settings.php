<?php

namespace pages;

class Settings {

    private $user;

    function __construct () {
        $this->user = \models\User::instance();
        $this->git = \models\Git::instance();
    }

    /**
     * Save new settings
     */
    public function settings ($f3) {
        try {
            $this->user->setGit($f3->get("POST.git"));
            echo '{"success" : true, "message" : "Settings saved successfully"}';
        }
        catch (\Exception $e) {
            echo json_encode(array("success" => false, "message" => $e->getMessage()));
        }
    }
}
