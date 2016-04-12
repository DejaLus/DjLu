<?php

namespace models;

/**
 * Model class to handle user related work (login, register, user preferences, etc.)
 */
class User extends \Prefab {

    private $f3;
    private $db;
    private $dbMapper;

    function __construct() {

        $this->f3 = \Base::instance();
        $this->db = new \DB\Jig($this->f3->get("DATA_PATH"), \DB\Jig::FORMAT_JSON);
        $this->dbMapper = new \DB\Jig\Mapper($this->db, "users.json");
    }

    /**
     * Get current username
     */
    public function getUsername () {
        return $this->f3->get("SESSION.username");
    }

    /**
     * Get current googleToken
     */
    public function getGoogleToken () {
        return $this->f3->get("SESSION.googleToken");
    }

    /**
     * Return user's preferences
     * @return array preferences as an array
     */
    public function getPreferences () {
        if (!$this->isLoggedIn())
            return array();

        $filePath = $this->f3->get("DATA_PATH").$this->getUsername()."/preferences.json";
        if (is_file($filePath))
            return json_decode(file_get_contents($filePath), true);
        else
            return array();
    }

    /**
     * Check if the user is logged in
     */
    public function isLoggedIn () {
        if ($this->f3->exists("SESSION.username"))
            return true;
        return $this->autologin();
    }

    /**
     * Get current googleToken
     */
    public function setGoogleToken ($token) {
        if (!$this->isLoggedIn())
            return;

        $userdata = $this->dbMapper->load(array("@username=?", $this->getUsername()));
        if (!$userdata)
            return;

        $userdata->googleToken = $token;
        $userdata->update();
        $this->f3->set("SESSION.googleToken", $token);
    }

    /**
     * Do auto-login based on cookie
     * TODO improve the security of the autologin
     */
    public function autologin () {

        if (!$this->f3->exists("SESSION.username") && $this->f3->exists("COOKIE.username") && $this->f3->exists("COOKIE.token")) {

            // get info
            $username = $this->f3->get("COOKIE.username");
            $token = $this->f3->get("COOKIE.token");
            $userdata = $this->dbMapper->load(array("@username=?", $username));

            // try auth
            if ($userdata && sha1($this->f3->get("APP_SALT").$userdata->password) == $token) {
                $this->f3->set("SESSION.googleToken", $userdata['googleToken']);
                $this->f3->set("SESSION.username", $username);
                return true;
            }

            // auth fail, remove cookie
            $this->f3->set("COOKIE.username", "", -1);
            $this->f3->set("COOKIE.token", "", -1);
        }

        return false;
    }


    /**
     * Do login processing based on POST data
     */
    public function login ($username, $password) {

        $userdata = $this->dbMapper->load(array("@username=?", $username));
        if (count($userdata) > 1)
            throw new \Exception("Internal auth error #ABDErr1");

        $sid = $this->f3->get("COOKIE.PHPSESSID");
        // auth ok
        if (count($userdata) < 1 || !password_verify($userdata['password'] . $sid, $password))
            throw new \Exception("Bad account or password for '" . $username . "'");

        // set session and cookies
        $this->f3->set("SESSION.username", $username);
        $this->f3->set("SESSION.googleToken", $userdata['googleToken']);
        $this->f3->set("COOKIE.username", $username);
        $this->f3->set("COOKIE.token", sha1($this->f3->get("APP_SALT").$password), 60*60*24*14);
    }

    /**
     * Do logout processing
     */
    public function logout () {
        $this->f3->clear("SESSION.username");
        $this->f3->clear("SESSION.googleToken");
        $this->f3->set("COOKIE.username", "", -1);
        $this->f3->set("COOKIE.token", "", -1);
    }

    /**
     * Register a new user in the DB
     */
    public function register ($username, $password, $sshId, $git) {

        // check input
        if (!preg_match("/^[a-zA-Z0-9]{3,50}$/", $username))
            throw new \Exception("Invalid username, regex to match is [a-zA-Z0-9]{3,50}");
        if (strlen($password) < 5)
            throw new \Exception("Password too short");
        if (!preg_match("/^[a-f0-9]{3,50}$/", $sshId))
            throw new \Exception("Invalid SSH key");
        if (!preg_match("#^(git://|ssh://)?([\w\.]+)@([\w\.]+):([0-9]*)([\w\.@\:/\-~]+)\.git/?$#", $git, $match))
            throw new \Exception("Invalid git SSH clone path, regex to match is (git://|ssh://)?([\w\.]+)@([\w\.]+):([0-9]*)([\w\.@\:/\-~]+\.git/?)");
        if ($this->dbMapper->count(array("@username=?", $username)) > 0)
            throw new \Exception("Username already exists");

        // save ssh key
        $sshPath = $this->f3->get("ROOT")."/".$this->f3->get("DATA_PATH").'_tempKeys/'.$sshId;
        $sshNewPath = $this->f3->get("ROOT")."/".$this->f3->get("DATA_PATH").'_keys/'.$username;
        rename($sshPath, $sshNewPath);
        rename($sshPath.".pub", $sshNewPath.".pub");

        // set ssh config to use key
        exec("echo \"Host djlu-".$username."\n".
        "\tHostName ".$match[3]."\n".
        "\tUser ".$match[2]."\n".
        ($match[4] ? "\t".$match[4]."\n" : "").
        "\tIdentityFile ".$sshNewPath."\n\n\" >> ~/.ssh/config");
        $clonePath = "djlu-".$username.":".$match[5];

        // clone git
        $host = escapeshellarg($match[3]);
        exec("grep ".$host." ~/.ssh/known_hosts", $dummy, $knownHost);
        if ($knownHost != 0)
            exec("ssh-keyscan ".$host." >> ~/.ssh/known_hosts");
        exec("git clone ".escapeshellarg($clonePath)." ".escapeshellarg($this->f3->get("DATA_PATH").$username)." 2>&1", $gitOut, $gitOutCode);

        if ($gitOutCode != 0)
            throw new \Exception("Git clone failed. Output:\n\n".implode("\n", $gitOut));

        // insert in DB
        $this->dbMapper->username = $username;
        $this->dbMapper->password = $password;
        $this->dbMapper->git = $git;
        $this->dbMapper->googleToken = "";
        $this->dbMapper->insert();
    }

}