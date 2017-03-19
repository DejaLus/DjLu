<?php

namespace models;

require_once(__DIR__."/../lib/Google/autoload.php");

class GoogleDrive extends \Prefab {

    private $f3;
    private $user;
    private $client;
    private $drive;
    private $root;
    private $drivePath;
    private $driveRootId;
    private $isInitialized;

    /**
     * Create the Google Drive model object
     * @param string $drivePath Path to the root folder assigned to DjLu by the user
     */
    public function __construct($drivePath = "/") {

        $this->f3 = \Base::instance();

        if(!file_exists(getcwd() . "/googleAPI.json")) {
            $this->isInitialized = false;
            return;
        }

        // init client
        $this->user = \models\User::instance();
        $this->client = new \Google_Client();
        $this->client->setApplicationName("DjLu");
        $this->client->setAuthConfigFile("googleAPI.json");
        $this->client->addScope(\Google_Service_Drive::DRIVE);
        $this->client->setRedirectUri(\lib\Utils::getServerUrl().$this->f3->alias("driveAuth"));
        $this->client->setAccessType("offline"); // offline to get refresh token

        $this->drivePath = $drivePath;
        
        $this->isInitialized = true;

        try {
            // saved token
            $token = $this->getToken();
            if (empty($token))
                return;

            // if token, set it
            $this->client->setAccessToken($token);

            // if token expired, try to get a new one with refresh token
            if ($this->client->isAccessTokenExpired()) {
                $this->client->refreshToken($this->client->getRefreshToken());
                $token = $this->client->getAccessToken();
                $this->setToken($token);
            }

            if (!$this->client->isAccessTokenExpired()) {
                $this->drive = new \Google_Service_Drive($this->client);
            }
        }

        // if it fails, the token is invalid, remove it
        catch (\Exception $e) {
            $this->removeToken();
            return;
        }
    }

    /**
     * Indicates if the Google Drive config is present
     * @return boolean status
     */
    public function isInitialized () {
        return $this->isInitialized;
    }
    //////////////////////////////
    /////// TOKEN FUNCTIONS
    //////////////////////////////

    // TODO change where token is stored, store it server side in the DB

    /**
     * Return current token
     * @return string token
     */
    private function getToken () {
        if(!$this->isInitialized()) {
            return null;
        }
        return $this->user->getGoogleToken();
    }

    /**
     * Set the token
     * @param string $token
     */
    private function setToken ($token) {
        if(!$this->isInitialized()) {
            return false;
        }
        $this->user->setGoogleToken($token);
        $this->client->setAccessToken($token);
        return true;
    }

    /**
     * Remove the Google Drive token stored for the user
     */
    public function removeToken () {
        if(!$this->isInitialized()) {
            return;
        }
        $this->user->setGoogleToken("");
    }

    //////////////////////////////
    /////// LOGIN PROCEDURE FUNCTIONS
    //////////////////////////////

    /**
     * Check if the user is logged in
     *
     * Note: We're not actually sure that the token is really valid
     * but there are high chances it does. If and error occurs somewhere,
     * we will destroy the token so that the user can properly sign in
     * again.
     *
     * @return boolean status
     */
    public function isLoggedIn () {
        if(!$this->isInitialized()) {
            return true;
        }
        return !$this->client->isAccessTokenExpired();
    }

    /**
     * Authenticate the user based on the OAuth returned code
     * @param  string $code OAuth returned code
     */
    public function authenticate ($code) {
        try {
            // auth with return code
            $this->client->authenticate($code);

            // get and save token
            $this->setToken($this->client->getAccessToken());
        }
        catch (\Exception $e) {
            $this->removeToken();
            throw new \Exception("Google auth failed.");
        }
    }

    /**
     * Get the URL to which the user should go to sign in
     * @return string url
     */
    public function getLoginURL () {
        if(!$this->isInitialized()) {
            return null;
        }
        return $this->client->createAuthUrl();
    }

    //////////////////////////////
    /////// FILE HANDLING
    //////////////////////////////

    /**
     * Get the ID of the Google Drive root folder assigned to DjLu
     *
     * NOTE could use a long term cache
     * @return string id
     */
    private function getDjLuDriveRootId () {

        if(!$this->isInitialized()) {
            return null;
        }

        if ($this->driveRootId)
            return $this->driveRootId;

        $folders = explode("/", rtrim($this->drivePath, "/"));
        $parent = "root";

        foreach ($folders as $folder) {
            if (empty($folder))
                continue;

            $response = $this->drive->files->listFiles(array(
                "q" => "'".$parent."' in parents and name='".str_replace("'", "\\'", $folder)."' and trashed = false",
                "spaces" => "drive",
                "fields" => "files(id)",
            ));

            if (count($response->files) > 0)
                $parent = $response->files[0]->id;
            else
                throw new \Exception($path." not found on Drive");
        }

        $this->driveRootId = $parent;
        return $this->driveRootId;
    }

    /**
     * Get the list of folders in the DjLu folder of the user, corresponding to papers
     * @return array(paperKey => array(name => "foo", id -> "driveId"))
     */
    public function getPapers () {
        try {
            $out = array();

            $response = $this->drive->files->listFiles(array(
                "q" => "'".$this->getDjLuDriveRootId()."' in parents and mimeType = 'application/vnd.google-apps.folder' and trashed = false",
                "spaces" => "drive",
                "fields" => "files(id, name, webViewLink)",
            ));

            foreach ($response->files as $paper) {
                $key = preg_replace('/^([^_]+)_.+$/', '\1', $paper->name);
                $out[$key] = array("name" => $paper->name, "id" => $paper->id);
            }

            return $out;
        }
        catch (\Exception $e) {
            $this->removeToken();
            throw new \Exception("Failed to fetch papers list. Check your Drive config and try again.");
        }
    }

    /**
     * Get the id of a paper given its citation key
     * @param  string $paperKey citation key
     * @return string           id
     */
    public function getPaperId ($paperKey) {
        $papers = $this->getPapers();
        if (!isset($papers[$paperKey]))
            throw new \Exception("Paper ".$paperKey." does not exists in Drive");
        return $papers[$paperKey]["id"];
    }

    /**
     * Get the id of a paper given its citation key, create it of it does not exists
     * @param  string $paperKey citation key
     * @return string           id
     */
    public function getPaperIdOrCreate ($paperKey) {
        $papers = $this->getPapers();
        if (isset($papers[$paperKey]))
            return $papers[$paperKey]["id"];

        $newFolder = new \Google_Service_Drive_DriveFile(array(
            'name' => $paperKey,
            'mimeType' => 'application/vnd.google-apps.folder',
            'parents' => array($this->getDjLuDriveRootId())));
        return $this->drive->files->create($newFolder, array('fields' => 'id'))["id"];
    }

    /**
     * Return the info about a PDF file on drive from the paper folder id
     * @param  string $paperId drive folder id
     * @return array(id => ..., name => ..., link => ...)
     */
    public function getPaperPdfFromId ($paperId) {

        if(!$this->isInitialized()) {
            return null;
        }

        $response = $this->drive->files->listFiles(array(
            'q' => "'".$paperId."' in parents and mimeType = 'application/pdf' and trashed = false",
            'spaces' => 'drive',
            'fields' => 'files(id, name, webViewLink)',
        ));

        if (count($response->files) > 0)
            return array("id" => $response->files[0]->id,
                "name" => $response->files[0]->name,
                "link" => $response->files[0]->webViewLink);
        else
            throw new \Exception("No PDF found on Google Drive");
    }

    /**
     * Save a PDF to Drive for a given paper
     * @param string $paperId  id of the drive folder of the paper
     * @param string $paperKey citation key of the paper (for the pdf name)
     * @param string $data     content of the pdf
     */
    public function setPaperPdfFromId ($paperId, $paperKey, $data) {

        if(!$this->isInitialized()) {
            return null;
        }

        // trash previous PDF
        $response = $this->drive->files->listFiles(array(
            'q' => "'".$paperId."' in parents and name = '".$paperKey.".pdf' and trashed = false",
            'spaces' => 'drive',
            'fields' => 'files(id)',
        ));
        foreach ($response->files as $file) {
            $newMetadata = new \Google_Service_Drive_DriveFile(array("trashed" => true));
            $this->drive->files->update($file->id, $newMetadata, array("fields" => "id"));
        }

        // set new PDF
        $newFile = new \Google_Service_Drive_DriveFile(array(
          "name" => $paperKey.".pdf",
          "parents" => array($paperId)
        ));
        $file = $this->drive->files->create($newFile, array(
          "data" => $data,
          "uploadType" => "multipart",
          "fields" => "id, webViewLink, name"));

        return array("id" => $file->id,
                "name" => $file->name,
                "link" => $file->webViewLink);
    }

    /**
     * Delete a paper directory containing the PDF from Drive for a given paper
     * @param string $paperKey citation key of the paper (for the pdf name)
     */
    public function deletePaper ($paperKey) {
        try {
            $paperId = $this->getPaperId($paperKey);
        }
        catch (\Exception $e) {
            return true; // paperId doesn't exist
        }
        $newMetadata = new \Google_Service_Drive_DriveFile(array("trashed" => true));
        $this->drive->files->update($paperId, $newMetadata, array("fields" => "id"));
        return true;
    }
}
