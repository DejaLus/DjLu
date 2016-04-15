<?php

namespace pages;

/**
 * This page handles register / login processes
 */
class Login {

    private $db;
    private $dbMapper;

    /**
     * Do login processing based on POST data
     */
    public function login ($f3) {

        try {
            // get post
            $username = $f3->get("POST.username");
            $password = $f3->get("POST.password");

            \models\User::instance()->login($username, $password);

            // redirect to papers
            $f3->reroute("@papers");
        }
        catch (\Exception $e) {
            \lib\Flash::instance()->addMessage($e->getMessage(), "danger");
            $f3->reroute("@home");
        }
    }

    /**
     * Do logout processing
     */
    public function logout ($f3) {

        \models\User::instance()->logout();
        \lib\Flash::instance()->addMessage("Logout successful", "info");
        $f3->reroute("@home");
    }

    /**
     * Register a new user in the DB
     */
    public function register ($f3) {

        try {
            // get post
            $username = $f3->get("POST.username");
            $password = $f3->get("POST.password");
            $sshId = $f3->get("POST.sshId");
            $git = $f3->get("POST.git");

            \models\User::instance()->register($username, $password, $sshId, $git);

            \lib\Flash::instance()->addMessage("Hi ".$username."! You account has been created. You can now login.", "success");
            $f3->reroute("@home");
        }
        catch (\Exception $e) {
            \lib\Flash::instance()->addMessage($e->getMessage(), "danger");
            $f3->reroute("@home");
        }
    }
}
