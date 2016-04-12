<?php

namespace pages;

class GoogleDrive {

    private $f3;
    private $model;
    private $user;

    public function __construct() {
        $this->f3 = \Base::instance();
        $this->user = \models\User::instance();

        // check user login
        if (!$this->user->isLoggedIn()) {
            echo json_encode(array("success" => false, "message" => "User not logged in"));
            die();
        }
        // get root path and instanciate model
        $prefs = $this->user->getPreferences();
        if (!empty($prefs["googleDriveRoot"]))
            $this->model = new \models\GoogleDrive($prefs["googleDriveRoot"]);
        else
            $this->model = new \models\GoogleDrive();
    }

    /**
     * Auth the user to Drive, if success, close the popup window
     */
    public function auth() {
        try {
            $this->model->authenticate($this->f3->get('GET.code'));
            echo '<html><script type="text/javascript">window.close();</script>';
        }
        catch (\Exception $e) {
            echo "Google authentification failed. Try again.";
        }
    }

    /**
     * Logout from Google Drive
     */
    public function logout () {
        $this->model->removeToken();
    }

    /**
     * Fetch the Google Drive URL of a paper's PDF stored there and save it as paper's URL
     */
    public function fetch ($f3, $args) {

        if (!$this->model->isLoggedIn()) {
            echo json_encode(array("success" => false,
                "reason" => "auth",
                "url" => $this->model->getLoginURL()));
            return;
        }

        try {
            $paperKey = $args["key"];
            $paper = new \models\Paper($paperKey);

            // check paper existence
            if (!$paper->exists())
                throw new \Exception("Paper ".$paperKey." does not exist in DjLu");

            // get key, if not exists, we get an exception
            $paperId = $this->model->getPaperId($paperKey);

            // look for PDF file for paper, if not exists, we get an exception
            $pdf = $this->model->getPaperPdfFromId($paperId);

            // edit DjLu's JSON
            $paper->edit("json", "url", $pdf["link"]);

            // send feedback
            echo json_encode(array("success" => true, "message" => "URL updated with success", "url" => $pdf["link"]));
        }
        catch (\Exception $e) {
            echo json_encode(array("success" => false, "reason" => "fail", "message" => $e->getMessage()));
        }
    }

    /**
     * Upload a PDF for a paper, save it to Drive. Upload can come from the user
     * or we can download the PDF from the current paper's URL and save it to
     * Drive for the user for convenience.
     */
    public function upload ($f3, $args) {

        if (!$this->model->isLoggedIn()) {
            echo json_encode(array("success" => false,
                "reason" => "auth",
                "url" => $this->model->getLoginURL()));
            return;
        }

        try {
            // select paper and check existence
            $paperKey = $args["key"];
            $paper = new \models\Paper($paperKey);
            if (!$paper->exists())
                throw new \Exception("Paper ".$paperKey." does not exist in DjLu");

            // get and download url or get post
            if ($args["method"] == "url") {
                $paperJSON = $paper->getJSON();
                if (empty($paperJSON["url"]))
                    throw new \Exception("Paper ".$paperKey." does not have an URL to import");
                $url = $paperJSON["url"];

                $context = stream_context_create(array(
                  "http" => array(
                    "method" => "GET",
                    "header" => "Accept: text/html,*/*;q=0.8\r\n".
                                "Accept-Encoding: deflate\r\n".
                                "Accept-Language: en-US,en;q=0.8\r\n",
                    "user_agent" => "Mozilla/5.0 (Macintosh; Intel Mac OS X 10_11_3) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/49.0.2623.110 Safari/537.36\r\n"
                    )
                ));
                $data = @file_get_contents($url, false, $context);
                if (!$data)
                    throw new \Exception("Failed to download URL ".$url);
            }
            elseif ($args["method"] == "post") {
                $file = $this->f3->get("FILES.pdf");
                if (!is_array($file))
                    throw new \Exception("No PDF received");
                switch ($file["error"]) {
                    case UPLOAD_ERR_INI_SIZE:
                    case UPLOAD_ERR_FORM_SIZE:
                        throw new \Exception("File is too big");
                    case UPLOAD_ERR_PARTIAL:
                    case UPLOAD_ERR_NO_FILE:
                        throw new \Exception("Error during upload, try again");
                    case UPLOAD_ERR_NO_TMP_DIR:
                    case UPLOAD_ERR_CANT_WRITE:
                    case UPLOAD_ERR_EXTENSION:
                        throw new \Exception("Error on the server side handling of uploads, please report");
                }

                if (empty($file["tmp_name"]))
                    throw new \Exception("Unable to locate uploaded file");

                $data = file_get_contents($file["tmp_name"]);
                unlink($file["tmp_name"]);

                if (!$data)
                    throw new \Exception("Uploaded file not found or empty");
            }
            else
                throw new \Exception("This PDF saving method does not exist");

           // get or create paper folder
            $paperId = $this->model->getPaperIdOrCreate($paperKey);

            // look for PDF file for paper
            $pdf = $this->model->setPaperPdfFromId($paperId, $paperKey, $data);

            // edit DjLu's JSON
            $paper->edit("json", "url", $pdf["link"]);

            // send feedback
            echo json_encode(array("success" => true, "message" => "PDF saved to Drive", "url" => $pdf["link"]));
        }
        catch (\Exception $e) {
            echo json_encode(array("success" => false, "reason" => "fail", "message" => $e->getMessage()));
        }
    }

}
