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
        $this->path = $this->f3->get("ROOT")."/".$this->f3->get("DATA_PATH").$this->username;
        $this->pathCmd = escapeshellarg($this->path);
    }

    /**
     * Check if the user's data folder is a git repo
     * @return boolean
     */
    public function isGitRepo () {
        return is_dir($this->path."/.git");
    }

    /**
     * Clone a git repo and use it as user's data folder
     * @param  string $command git clone command
     */
    public function cloneRepo ($command) {

        if (!\models\User::instance()->hasRight("git"))
            throw new \Exception("You are not allowed to use git.");

        // check
        if (!preg_match("#^(git://|ssh://)?([\w\.]+)@([\w\.]+):([0-9]*)([\w\.@\:/\-~]+)\.git/?$#", $command, $match))
            throw new \Exception("Invalid git SSH clone path, regex to match is (git://|ssh://)?([\w\.]+)@([\w\.]+):([0-9]*)([\w\.@\:/\-~]+\.git/?)");


        ////////
        // set ssh config to use key
        $fakeHost = "djlu-".$this->username;
        $clonePath = $fakeHost.":".$match[5];
        $sshPath = $this->f3->get("ROOT")."/".$this->f3->get("DATA_PATH").'_keys/'.$this->username;

        $sshConfigPath = getenv("HOME")."/.ssh/config";
        $sshConfig = file_get_contents($sshConfigPath);

        // filter out host if already in the file
        $sshConfigArray = explode("Host ", $sshConfig);
        $sshConfigArray = array_filter($sshConfigArray, function ($host) use ($fakeHost) { return trim(explode("\n", $host)[0]) != $fakeHost; });
        $sshConfig = join("Host ", $sshConfigArray);

        // add new host
        $sshConfig .= "\nHost ".$fakeHost."\n".
        "\tHostName ".$match[3]."\n".
        "\tUser ".$match[2]."\n".
        ($match[4] ? "\tPort ".$match[4]."\n" : "").
        "\tStrictHostKeyChecking no\n".
        "\tIdentityFile ".$sshPath."\n";
        file_put_contents($sshConfigPath, $sshConfig);

        /////
        // move old folder to temp name
        rename($this->path, $this->path."_old");

        // clone git
        exec("git clone ".escapeshellarg($clonePath)." ".$this->pathCmd." 2>&1", $gitOut, $gitOutCode);

        // merge with old data or restore all data
        if ($gitOutCode != 0) {
            \lib\Utils::rrmdir($this->path);
            rename($this->path."_old", $this->path);
            throw new \Exception("Git clone failed.");
        }
        else {
            \lib\Utils::rrmdir($this->path."_old/.git");
            \lib\Utils::mv_merge($this->path."_old", $this->path);
        }
    }

    /**
     * Pull the repository
     * @return array  log of git output and success status
     */
    public function pull () {
        exec("git -C ".$this->pathCmd." pull 2>&1", $log, $result);
        return array("log" => implode("\n", $log), "success" => ($result == 0));
    }

    /**
     * Status of the repository
     * @return string log of git status command
     */
    public function status () {
        exec("git -C ".$this->pathCmd." add -A 2>&1");
        exec("git -C ".$this->pathCmd." status 2>&1", $log);
        return implode("\n", $log);
    }

    /**
     * Commit and push the git repository
     * @param  string $commitMessage commit message
     * @return array                 log of git output and success
     */
    public function commitPush ($commitMessage) {
        exec("git -C ".$this->pathCmd." commit -m ".escapeshellarg($commitMessage)." 2>&1", $log, $result);
        if ($result == 0) {
            $log[] = "";
            $log[] = "";
            exec("git -C ".$this->pathCmd." push 2>&1", $log, $result);
        }
        return array("log" => implode("\n", $log), "success" => ($result == 0));
    }
}
