<?php

namespace models;

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
     * Check if the user is logged in
     */
    public function isLoggedIn () {
        if ($this->f3->exists("SESSION.username"))
            return true;
        return $this->autologin();
    }

    /**
     * Do auto-login based on cookie
     */
    public function autologin () {

        if (!$this->f3->exists("SESSION.username") && $this->f3->exists("COOKIE.username") && $this->f3->exists("COOKIE.token")) {

            // get info
            $username = $this->f3->get("COOKIE.username");
            $token = $this->f3->get("COOKIE.token");
            $userdata = $this->dbMapper->load(array("@username=?", $username));

            // try auth
            if ($userdata && sha1($this->f3->get("APP_SALT").$userdata->password) == $token) {
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

        // hash password
        $password = sha1($this->f3->get("APP_SALT").$username.$password);

        // auth ok
        if (!$this->dbMapper->count(array("@username=? and @password", $username, $password)) > 0)
            throw new \Exception("Bad account or password");

        // set session and cookies
        $this->f3->set("SESSION.username", $username);
        $this->f3->set("COOKIE.username", $username);
        $this->f3->set("COOKIE.token", sha1($this->f3->get("APP_SALT").$password), 60*60*24*14);
    }

    /**
     * Register a new user in the DB
     */
    public function register ($username, $password, $git) {

        // check input
        if (!preg_match("/^[a-zA-Z0-9-_.]{3,50}$/", $username))
            throw new \Exception("Invalid username, regex to match is [a-zA-Z0-9-_.]{3,50}");
        if (strlen($password) < 5)
            throw new \Exception("Password too short");
        if (!preg_match("#^(git@([\w\.]+))(:(//)?)([\w\.@\:/\-~]+)(\.git)(/)?$#", $git, $match))
            throw new \Exception("Invalid git SSH clone path, regex to match is (git@[\w\.]+)(:(//)?)([\w\.@\:/\-~]+)(\.git)(/)?");
        if ($this->dbMapper->count(array("@username=?", $username)) > 0)
            throw new \Exception("Username already exists");

        // clone git
        $host = escapeshellarg($match[2]);
        exec("grep ".$host." ~/.ssh/known_hosts", $dummy, $knownHost);
        if ($knownHost != 0)
            exec("ssh-keyscan ".$host." >> ~/.ssh/known_hosts");
        exec("git clone ".escapeshellarg($git)." ".escapeshellarg($this->f3->get("DATA_PATH").$username)." 2>&1", $gitOut, $gitOutCode);

        if ($gitOutCode != 0)
            throw new \Exception("Git clone failed. Output:\n\n".implode("\n", $gitOut));

        // insert in DB
        $password = sha1($this->f3->get("APP_SALT").$username.$password);
        $this->dbMapper->username = $username;
        $this->dbMapper->password = $password;
        $this->dbMapper->git = $git;
        $this->dbMapper->insert();
    }

}