<?php

namespace models;

/**
 * Model class to handle user related work (login, register, user preferences, etc.)
 */
class User extends \Prefab {

    private $f3;
    private $db;
    private $dbMapper;
    private $userdata;
    public static $PREFS_EDITABLE_FIELDS = array("tags", "googleDriveRoot");

    function __construct() {

        $this->f3 = \Base::instance();
        $this->db = new \DB\Jig($this->f3->get("DATA_PATH"), \DB\Jig::FORMAT_JSON);
        $this->dbMapper = new \DB\Jig\Mapper($this->db, "users.json");
        if ($this->f3->exists("SESSION.userdata"))
            $this->userdata = $this->f3->get("SESSION.userdata");
        else
            $this->userdata = array();
    }

    /**
     * Return an elemet from user data (username, git, etc.)
     * @param  string $key     array key
     * @param  string $default default value if does not exist
     * @return mixed          content of the key
     */
    private function getUserdata($key, $default = "") {
        if (!isset($this->userdata[$key]))
            return $default;
        return $this->userdata[$key];
    }

    /**
     * Edit a field of userdata array
     * @param string $key
     * @param string $value
     */
    private function setUserdata($key, $value) {
        $this->userdata[$key] = $value;
        $this->f3->set("SESSION.userdata", $this->userdata);
    }

    /**
     * Save current userdata in the DB
     */
    private function saveUserdata() {
        $userdata = $this->dbMapper->load(array("@username=?", $this->getUsername()));
        foreach ($this->userdata as $key => $val) {
            $userdata[$key] = $val;
        }
        $userdata->update();
    }

    /**
     * Get current username
     */
    public function getUsername () {
        return $this->getUserdata("username");
    }

    /**
     * Get current googleToken
     */
    public function getGoogleToken () {
        return $this->getUserdata("googleToken");
    }

    /**
     * Get current git clone command
     */
    public function getGit () {
        return $this->getUserdata("git");
    }

    public function setGit ($command) {
        if (empty($command) || $command == $this->getGit())
            return false;
        Git::instance()->cloneRepo($command);
        $this->setUserdata("git", $command);
        $this->saveUserdata();
        return true;
    }

    /**
     * Get current googleToken
     */
    public function hasRight ($right) {
        $rights = $this->getUserdata("rights", array());
        return in_array($right, $rights);
    }

    /**
     * Return user's preferences
     * @return array preferences as an array
     */
    public function getPreferences () {
        if (!$this->isLoggedIn())
            throw new \Exception("User not logged in");

        $filePath = $this->f3->get("DATA_PATH").$this->getUsername()."/preferences.json";
        if (is_file($filePath))
            return json_decode(file_get_contents($filePath), true);
        else
            return array();
    }

    /**
     * Save new user's preferences
     * @param arrat $preferences
     */
    public function setPreferences ($preferences) {
        if (!$this->isLoggedIn())
            throw new \Exception("User not logged in");

        $filePath = $this->f3->get("DATA_PATH").$this->getUsername()."/preferences.json";
        if (file_put_contents($filePath, json_encode($preferences, JSON_PRETTY_PRINT)) === false)
            throw new \Exception("Failed to save user preferences");
    }

    public function editPreferences ($field, $value) {
        if (!$this->isLoggedIn())
            throw new \Exception("User not logged in");

        $preferences = $this->getPreferences();

        $fieldTree = explode(".", $field);
        $field = $fieldTree[0];

        // general check, is field editable
        if (!$field || !in_array($field, self::$PREFS_EDITABLE_FIELDS))
            throw new \Exception("Field not editable");

        // validate / preprocess tags
        if ($field == "tags" && count($fieldTree) != 4)
            throw new \Exception("Invalid tag field");
        if ($field == "tags" && !in_array($fieldTree[1], \models\Papers::$TAGS_GROUPS))
            throw new \Exception("Invalid tag group");
        if ($field == "tags" && (!is_array($preferences[$field][$fieldTree[1]]) || !is_array($preferences[$field][$fieldTree[1]][$fieldTree[2]])))
            throw new \Exception("Tag does not exist");
        if ($field == "tags" && $fieldTree[3] != "color" && $fieldTree[3] != "pinned")
            throw new \Exception("Invalid field");
        if ($field == "tags" && $fieldTree[3] == "color" && !preg_match("/^[0-9a-f]{6}$/i", $value))
            throw new \Exception("Invalid color");
        if ($field == "tags" && $fieldTree[3] == "pinned") {
            if ($value != "true" && $value != "false")
                throw new \Exception("Invalid pin value");
            else
                $value = $value == "true";
        }

        // validate / preprocess googleDriveRoot
        if ($field == "googleDriveRoot" && count($fieldTree) != 1)
            throw new \Exception("Invalid field");

        // create tree if needed
        $preferencesRefParent = &$preferences;
        $preferencesRef = &$preferences;
        foreach ($fieldTree as $fieldEl) {
            $preferencesRefParent = &$preferencesRef;
            if (!is_array($preferencesRef[$fieldEl]))
                $preferencesRef[$fieldEl] = array();
            $preferencesRef = &$preferencesRef[$fieldEl];
        }

        // edit or erase the field
        if (!empty($value))
            $preferencesRef = $value;
        else
            unset($preferencesRefParent[$fieldEl]);

        // save
        $this->setPreferences($preferences);
    }

    /**
     * Check if the user is logged in
     */
    public function isLoggedIn () {
        if ($this->getUsername())
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
        $this->setUserdata("googleToken", $token);
        $this->saveUserdata();
    }

    /**
     * Do auto-login based on cookie
     * TODO improve the security of the autologin
     */
    public function autologin () {

        if (!$this->getUsername() && $this->f3->exists("COOKIE.username") && $this->f3->exists("COOKIE.token")) {

            // get info
            $username = $this->f3->get("COOKIE.username");
            $token = $this->f3->get("COOKIE.token");
            $userdata = $this->dbMapper->load(array("@username=?", $username));

            // try auth
            if ($userdata && sha1($this->f3->get("APP_SALT").$userdata->password) == $token) {
                $this->setUserdata("username", $username);
                $this->setUserdata("googleToken", $userdata['googleToken']);
                $this->setUserdata("git", $userdata["git"]);
                $this->setUserdata("rights", $userdata["rights"]);
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
        $this->setUserdata("username", $username);
        $this->setUserdata("git", $userdata["git"]);
        $this->setUserdata("rights", $userdata["rights"]);
        $this->setUserdata("googleToken", $userdata['googleToken']);
        $this->f3->set("COOKIE.username", $username, 60*60*24*14);
        $this->f3->set("COOKIE.token", sha1($this->f3->get("APP_SALT").$userdata['password']), 60*60*24*14);
    }

    /**
     * Change user's password
     */
    public function changePassword ($password, $newPassword) {

        $sid = $this->f3->get("COOKIE.PHPSESSID");

        $userdata = $this->dbMapper->load(array("@username=?", $this->userdata['username']));
        if (count($userdata) > 1)
            throw new \Exception("Internal auth error #ABDErr2");

        // auth ok
        if (count($userdata) < 1 || !password_verify($userdata['password'] . $sid, $password))
            throw new \Exception("Wrong password for '" . $userdata['username'] . "'");

        // set session and cookies
        $userdata['password'] = $newPassword;
        $userdata->update();
        $this->f3->set("COOKIE.username", $this->userdata['username'], 60*60*24*14);
        $this->f3->set("COOKIE.token", sha1($this->f3->get("APP_SALT").$userdata['password']), 60*60*24*14);

        return true;
    }

    /**
     * Do logout processing
     */
    public function logout () {
        $this->f3->clear("SESSION.userdata");
        $this->f3->set("COOKIE.username", "", -1);
        $this->f3->set("COOKIE.token", "", -1);
    }

    /**
     * Register a new user in the DB
     */
    public function register ($username, $password, $sshId) {

        // check input
        if (!preg_match("/^[a-zA-Z0-9]{3,50}$/", $username))
            throw new \Exception("Invalid username, it must contain 3 to 50 letters or numbers only");
        if (strlen($password) < 5)
            throw new \Exception("Password must be 5 characters at least");
        if ($this->dbMapper->count(array("@username=?", $username)) > 0)
            throw new \Exception("Username already exists");

        // create directory and SSH key
        mkdir($this->f3->get("DATA_PATH").$username);
        $this->getPubkey(true);

        // insert in DB
        $this->dbMapper->username = $username;
        $this->dbMapper->password = $password;
        $this->dbMapper->rights = array();
        $this->dbMapper->git = "";
        $this->dbMapper->googleToken = "";
        $this->dbMapper->insert();
    }

    /**
     * Generates if needed and return the public SSH key assigned to the user
     * @param  boolean $forceGenerate force to generate a new key
     * @return string                 public ssh key
     */
    public function getPubkey ($forceGenerate = false) {
        if (!$this->isLoggedIn())
            return false;

        $path = $this->f3->get("ROOT")."/".$this->f3->get("DATA_PATH").'_keys/'.$this->getUsername();
        if (!is_file($path) || $forceGenerate)
            exec('yes | ssh-keygen -t rsa -b 4096 -N "" -f '.escapeshellcmd($path));

        return file_get_contents($path.".pub");
    }
}
