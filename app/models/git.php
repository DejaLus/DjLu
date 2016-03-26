<?php

namespace models;

/**
 * Model class to handle the git repository of the user
 */
class Git extends \Prefab {

    private $f3;
    private $username;
    private $path;

    function __construct() {
        $this->f3 = \Base::instance();
        $this->username = \models\User::instance()->getUsername();
        $this->path = escapeshellarg($this->f3->get("DATA_PATH").$this->username);
    }

    /**
     * Pull the repository
     * @return array  log of git output and success status
     */
    public function pull () {
        exec("git -C ".$this->path." pull 2>&1", $log, $result);
        return array("log" => implode("\n", $log), "success" => ($result == 0));
    }

    /**
     * Status of the repository
     * @return string log of git status command
     */
    public function status () {
        exec("git -C ".$this->path." add -A 2>&1");
        exec("git -C ".$this->path." status 2>&1", $log);
        return implode("\n", $log);
    }

    /**
     * Commit and push the git repository
     * @param  string $commitMessage commit message
     * @return array                 log of git output and success
     */
    public function commitPush ($commitMessage) {
        exec("git -C ".$this->path." commit -m ".escapeshellarg($commitMessage)." 2>&1", $log, $result);
        if ($result == 0) {
            $log[] = "";
            $log[] = "";
            exec("git -C ".$this->path." push 2>&1", $log, $result);
        }
        return array("log" => implode("\n", $log), "success" => ($result == 0));
    }
}
