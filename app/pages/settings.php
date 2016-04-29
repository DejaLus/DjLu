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
            $gitChanged = $this->user->setGit($f3->get("POST.git"));
            echo json_encode(array("success" => true, "message" => "Settings saved successfully", "reload" => $gitChanged));
        }
        catch (\Exception $e) {
            echo json_encode(array("success" => false, "message" => $e->getMessage()));
        }
    }

    public function preferences ($f3) {
        try {
            $this->user->editPreferences(trim($f3->get("POST.field")), trim($f3->get("POST.value")));
            echo json_encode(array("success" => true, "message" => "Preferences saved successfully"));
        }
        catch (\Exception $e) {
            echo json_encode(array("success" => false, "message" => $e->getMessage()));
        }
    }

    /**
     * Set a fixed color to a tag
     */
    public function tagColor ($f3) {
        try {
            $tag = $f3->get("POST.tag");
            $group = $f3->get("POST.group");
            $color = $f3->get("POST.color");

            $this->user->setTagColor($tag, $group, $color);
            echo '{"success" : true}';
        }
        catch (\Exception $e) {
            echo json_encode(array("success" => false, "message" => $e->getMessage()));
        }
    }
}
