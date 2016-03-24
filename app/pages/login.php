<?php

namespace pages;

/**
 * This page handles register / login processes
 */
class Login {

    private $db;
    private $dbMapper;

    function __construct() {

        $this->db = new \DB\Jig(\Base::instance()->get("DATA_PATH"), \DB\Jig::FORMAT_JSON);
        $this->dbMapper = new \DB\Jig\Mapper($this->db, "users.json");
    }

    /**
     * Do auto-login based on cookie
     */
    public function autologin () {

        $f3 = \Base::instance();

        if (!$f3->exists("SESSION.username") && $f3->exists("COOKIE.username") && $f3->exists("COOKIE.token")) {

            // get info
            $username = $f3->get("COOKIE.username");
            $token = $f3->get("COOKIE.token");
            $userdata = $this->dbMapper->load(array("@username=?", $username));

            // try auth
            if ($userdata && sha1($f3->get("APP_SALT").$userdata->password) == $token) {
                $f3->set("SESSION.username", $username);
                return true;
            }

            // auth fail, remove cookie
            $f3->set("COOKIE.username", "", -1);
            $f3->set("COOKIE.token", "", -1);
        }

        return false;
    }


    /**
     * Do login processing based on POST data
     */
    public function login ($f3) {

        try {
            // get post
            $username = $f3->get("POST.username");
            $password = $f3->get("POST.password");
            $password = sha1($f3->get("APP_SALT").$username.$password);

            // auth ok
            if (!$this->dbMapper->count(array("@username=? and @password", $username, $password)) > 0)
                throw new \Exception("Bad account or password");

            // set session and cookies
            $f3->set("SESSION.username", $username);
            $f3->set("COOKIE.username", $username);
            $f3->set("COOKIE.token", sha1($f3->get("APP_SALT").$password), 60*60*24*14);

            // redirect to papers
            $f3->reroute("@papers");
        }
        catch (\Exception $e) {
            \lib\Flash::instance()->addMessage($e->getMessage(), "danger");
            $f3->reroute("@home");
        }
    }

    /**
     * Register a new user in the DB
     */
    public function register ($f3) {

        try {
            // get post
            $username = $f3->get("POST.username");
            $password = $f3->get("POST.password");
            $git = $f3->get("POST.git");

            // check input
            if (!preg_match("/^[a-zA-Z0-9-_.]{3,50}$/", $username))
                throw new \Exception("Invalid username, regex to match is [a-zA-Z0-9-_.]{3,50}");
            if (strlen($password) < 5)
                throw new \Exception("Password too short");
            if (!preg_match("#^(git@[\w\.]+)(:(//)?)([\w\.@\:/\-~]+)(\.git)(/)?$#", $git))
                throw new \Exception("Invalid git SSH clone path, regex to match is (git@[\w\.]+)(:(//)?)([\w\.@\:/\-~]+)(\.git)(/)?");
            if ($this->dbMapper->count(array("@username=?", $username)) > 0)
                throw new \Exception("Username already exists");

            // clone git
            exec("git clone ".escapeshellarg($git)." ".escapeshellarg($f3->get("DATA_PATH").$username)." 2>&1", $gitOut, $gitOutCode);

            if ($gitOutCode != 0)
                throw new \Exception("Git clone failed. Output:\n\n".$gitOut);

            // insert in DB
            $password = sha1($f3->get("APP_SALT").$username.$password);
            $this->dbMapper->username = $username;
            $this->dbMapper->password = $password;
            $this->dbMapper->git = $git;
            $this->dbMapper->insert();

            \lib\Flash::instance()->addMessage("Hi ".$username."! You account has been created. You can now login.", "success");
            $f3->reroute("@home");
        }
        catch (\Exception $e) {
            \lib\Flash::instance()->addMessage($e->getMessage(), "danger");
            $f3->reroute("@home");
        }
    }
}
