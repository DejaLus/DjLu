<?php

namespace models;

/**
 * Model to handle stuff related to a single paper, particularly editing
 */
class Paper {

    private $f3;
    private $dataPath;
    private $key;
    private $folder;
    private $path;

    /**
     * Array of editable fields
     * @var array
     */
    public static $JSON_EDITABLE_FIELDS = array("title", "authors", "in", "rating", "year", "url", "date_added", "tags_content", "tags_reading", "tags_notes", "secret");

    function __construct($key = "", $username = "") {
        $this->f3 = \Base::instance();
        $user = \models\User::instance();

        if (!$user->isLoggedIn() && !$username)
            throw new \Exception("User not logged in");
        if (empty($username))
            $username = $user->getUsername();

        $this->dataPath = $this->f3->get("DATA_PATH").$username;
        if (!is_dir($this->dataPath))
            throw new \Exception("User directory missing");

        if ($key != "") {
            $this->key = $key;
            $this->folder = $this->getPaperFolder();
            if ($this->folder)
                $this->path = $this->dataPath."/".$this->folder;
        }
    }

    /**
     * Get the folder name corresponding to a paper
     * @param  string $key key of the paper
     * @return mixed       string if found, false if not
     */
    private function getPaperFolder () {
        foreach (scandir($this->dataPath) as $fname) {
            $keyI = preg_replace('/^([^_]+)_.+$/', '\1', $fname);
            $dirPath = $this->dataPath."/".$fname;
            $jsonPath = $this->dataPath."/".$fname."/".$this->key.".json";
            if ($fname != "." && $fname != ".." && $keyI == $this->key && is_dir($dirPath) && is_file($jsonPath))
                return $fname;
        }
        return null;
    }

    /**
     * Check if the paper already exists
     * @return boolean
     */
    public function exists () {
        return $this->folder != null;
    }

    /**
     * Get the key of the paper
     * @return string
     */
    public function getKey() {
        return $this->key;
    }

    /**
     * Get the bibtex from arXiv ID
     * @param  string $id arXiv ID
     * @return string     bibtex
     */
    private static function bibTexFromArXiv ($id) {
        $id = preg_replace("#^arXiv:(.+)$#", '\1', $id);
        $xml = @file_get_contents("http://export.arxiv.org/api/query?id_list=".$id);
        if (!$xml)
            throw new \Exception("Error while loading, the ID probably do not exist");

        $xml = json_decode(json_encode(simplexml_load_string($xml)), true);

        if ($xml["entry"]["title"] == "Error")
            throw new \Exception("No paper found for arXiv id ".$id);

        $time = strtotime($xml["entry"]["published"]);
        $bib = array(
            "cite" => $xml["entry"]["author"][0]["name"].date("Y", $time),
            "entryType" => "article",
            "title" => trim($xml["entry"]["title"]),
            "author" => implode(" and ", array_map(function ($x) { return $x["name"]; }, $xml["entry"]["author"])),
            "booktitle" => "arXiv",
            "year" => date("Y", $time),
            "month" => date("M", $time),
            "archivePrefix" => "arXiv",
            "arxivId" => $id,
            "eprint" => $id,
            "url" => call_user_func(function($links) {foreach ($links as $y) { if($y["@attributes"]["title"] == "pdf") { return $y["@attributes"]["href"]; } }}, $xml["entry"]["link"]),
            "abstract" => trim($xml["entry"]["summary"])
            );

        $bibtex = new \models\BibTex(array("removeCurlyBraces" => true, "extractAuthors" => false));
        $bibtex->addEntry($bib);
        return $bibtex->toBibTex();
    }

    /**
     * Get the bibtex from DOI
     * @param  string $id DOI
     * @return string     bibtex
     */
    private static function bibTexFromDOI ($id) {
        $id = preg_replace("#^doi:(.+)$#", '\1', $id);

        $context = stream_context_create(array(
            "http" => array(
                "method" => "GET",
                "header" => "Accept: text/bibliography; style=bibtex\r\n"
            )
        ));

        $id = preg_replace("#^arXiv:(.+)$#", '\1', $id);
        $data = @file_get_contents("http://dx.doi.org/".$id, false, $context);

        if (!$data)
            throw new \Exception("Error while loading, the ID probably do not exist");

        return $data;
    }

    /**
     * Create a paper record from a standard ID (DOI, arXiv)
     * @param  string $id
     * @param  string $citationKey citation key
     */
    public static function createFromId ($id, $citationKey = "") {

        $id = trim($id);

        // arvix
        if (strpos($id, "arXiv:") === 0)
            $bibtex = self::bibTexFromArXiv($id);
        elseif (strpos($id, "doi:") === 0)
            $bibtex = self::bibTexFromDOI($id);
        else
            throw new \Exception("ID not supported");

        return self::createFromBibTex($bibtex, $citationKey);
    }

    /**
     * Create papers entry from a bibtex
     * @param  string $bibtexData  bibtex
     * @param  string $citationKey citation key to override bibtex
     * @return array              success keys and error messages
     */
    public static function createFromBibTex ($bibtexData, $citationKey = "") {

        // parse bibtex
        $bibtex = new \models\BibTex(array('removeCurlyBraces' => true, 'extractAuthors' => false));
        $bibtex->content = $bibtexData;
        $bibtex->parse();

        // init
        $successKeys = array();
        $errors = array();

        // if bibtex invalid
        if (!is_array($bibtex->data) || count($bibtex->data) == 0)
            throw new \Exception("Invalid bibtex data");

        // loop through bibtex entries
        foreach ($bibtex->data as $data) {
            $authors = $bibtex->_extractAuthors($data["author"]);
            if ($citationKey && count($bibtex->data) == 1)
                $key = $citationKey;
            elseif (!empty($authors[0]["last"])) {
                $key = $authors[0]["last"];
                if ($data["year"])
                    $key .= $data["year"];
            }
            else {
                $key = $data["cite"];
            }
            $key = preg_replace("[^a-zA-Z0-9]", "", \lib\Utils::remove_accents($key));
            $key = \models\Papers::instance()->getNextAvailableKey($key);

            // try to create paper, if not catch and record error
            try {
                $paper = new \models\Paper($key);
                $paper->createFromBibTexData($data);
                $successKeys[] = $key;
            }
            catch (\Exception $e) {
                $errors[] = "Error for paper with key ".$key.": ".$e->getMessage();
            }
        }

        // return
        if (count($successKeys) == 0)
            throw new \Exception(implode("\n", $errors));
        else
            return array("keys" => $successKeys, "errors" => $errors);
    }

    /**
     * Create the paper entry in the repository from the bibtex
     * @param  string $bibtexRaw      Bibtex record of the paper
     */
    public function createFromBibTexData ($data) {

        // check key
        if (!$this->key)
            $this->key = $data["cite"];
        else
            $data["cite"] = $this->key;

        // check key, title, author
        if (!preg_match("/^[a-zA-Z0-9]+$/", $this->key))
            throw new \Exception("Key can only contain letters and numbers");
        if ($this->getPaperFolder()) // paper already exists
            throw new \Exception("A paper with this key already exists");
        if (empty($data["title"]) || empty($data["author"]))
            throw new \Exception("Empty title or author");

        // create folder
        $folderTitle = preg_replace("/[^a-zA-Z]/", " ", $data["title"]);
        $folderTitle = preg_replace("/ +/", "_", $folderTitle);
        $folderTitle = substr($folderTitle, 0, 50);

        mkdir($this->dataPath."/".$this->key."_".$folderTitle, 0755);
        $this->folder = $this->key."_".$folderTitle;
        $this->path = $this->dataPath."/".$this->folder;

        // create JSON data
        $json = array(
            "title" => $data["title"],
            "authors" => explode(" and ", $data["author"]),
            "year" => $data["year"] ? $data["year"] : "",
            "date_added" => date("Y-m-d H:i"),
            "in" => $data["booktitle"] ? $data["booktitle"] : "",
            "tags_reading" => array("new"),
            "url" => $data["url"] ? $data["url"] : "");

        if ($this->writeJSON($json) === false)
            throw new \Exception("Failed to write JSON file");

        // save bibtex
        $bibtex = new \models\BibTex(array('removeCurlyBraces' => true, 'extractAuthors' => false));
        $bibtex->addEntry($data);
        if (file_put_contents($this->path."/".$this->key.".bib", $bibtex->toBibTex()) === false)
            throw new \Exception("Failed to write bibtex file");
    }

    /**
     * Add a raw text reference
     * @param  string $raw         Raw text
     * @param  string $citationKey
     * @return array              success keys and error messages
     */
    public static function createFromRawRef ($raw, $citationKey = "") {
        if (empty(trim($raw)))
            throw new \Exception("No string reference received");

        // citation key
        if (!is_string($citationKey))
            $citationKey = "";
        $citationKey = preg_replace("[^a-zA-Z0-9]", "", \lib\Utils::remove_accents($citationKey));
        if (empty($citationKey)) {
            $words = explode(" ", preg_replace("#[-_;:,./?! ]+#", " ", $raw));
            preg_match("/(^|[^0-9-])((17|18|19|20|21)[0-9]{2})([^0-9-]|$)/", $raw, $matches);
            for ($i = 0; $i < min(count($words), 2); $i++)
                $citationKey .= $words[$i];
            if (count($matches) > 3)
                $citationKey .= $matches[2];
            $citationKey = preg_replace("[^a-zA-Z0-9]", "", \lib\Utils::remove_accents($citationKey));
        }

        if (empty($citationKey))
            $citationKey = substr(sha1($raw), 0, 16);

        $f3 = \Base::instance();
        $user = \models\User::instance();
        if (!$user->isLoggedIn())
            throw new \Exception("User not logged in");

        $path = $f3->get("DATA_PATH").$user->getUsername()."/".$citationKey.".txt";

        if (file_put_contents($path, $raw) === false)
            throw new \Exception("Failed to save reference at ".$path);

        return array("keys" => array("short_".$citationKey), "errors" => array());
    }

    /**
     * Edit a field in the JSON of the paper
     * @param  string $field
     * @param  string $value
     */
    private function editJSON ($field, $value) {
        if (!$field || !in_array($field, self::$JSON_EDITABLE_FIELDS))
            throw new \Exception("Field not editable");

        $value = trim($value);

        // check valid value
        if (($field == "title" || $field == "authors") && empty($value))
            throw new \Exception("Field cannot be empty");
        if ($field == "date_added" && !preg_match("/([0-9]{4})-([0-9]{2})-([0-9]{2}) ([0-9]{2}):([0-9]{2})/", $value, $matches)) {
            if (!checkdate($matches[2], $matches[3], $matches[1]) ||
                (int) $matches[4] < 0 || (int) $matches[4] > 23 ||
                (int) $matches[5] < 0 || (int) $matches[4] > 59)
                throw new \Exception("Invalid date / time");
        }
        if ($field == "secret" && $value != "" && !preg_match("/^[a-zA-Z0-9]{5,50}$/", $value))
            throw new \Exception("Invalid secret key");

        // preprocess value
        if ($field == "tags_notes" || $field == "tags_reading" || $field == "tags_content" || $field == "authors") {
            $value = explode(";", $value);
            $value = array_map("trim", $value);
        }
        if ($field == "year")
            $value = (int) $value;

        // edit JSON
        $json = $this->getJSON();
        $json[$field] = $value;
        if ($this->writeJSON($json) === false)
            throw new \Exception("Failed to write file");
    }

    /**
     * Edit the markdown file
     * @param  string $content Content of the file to write
     */
    private function editMD ($content) {

        $content = trim($content);
        $mdPath = $this->path."/".$this->key.".md";

        if (empty($content) && is_file($mdPath))
            if (!unlink($mdPath))
                throw new \Exception("Failed to remove notes");

        if (!empty($content))
            if (file_put_contents($mdPath, $content) === false)
                throw new \Exception("Failed to save file");
    }

    /**
     * Edit a field about the paper
     * @param  string $file
     * @param  string $field
     * @param  string $value
     * @return boolean        success
     */
    public function edit ($file, $field, $value) {

        if ($file == "json")
            return $this->editJSON($field, $value);
        if ($file == "md")
            return $this->editMD($value);
        throw new \Exception("File not editable");
    }

    /**
     * Return different elements related to a given paper
     * @param  array  $els elements to get
     * @return array       list of requested elements (if exists)
     */
    public function getFiles ($els = array("json", "bib", "md")) {

        if (!$this->exists())
            return array();

        foreach ($els as $ext) {
            $fpath = $this->path."/".$this->key.".".$ext;
            if (is_file($fpath)) {
                if ($ext == "json")
                    $out["json"] = json_decode(file_get_contents($fpath), true);
                elseif ($ext == "bib") {
                    $out["bibRaw"] = file_get_contents($fpath);
                    $bibtex = new \models\BibTex(array('removeCurlyBraces' => true, 'extractAuthors' => false));
                    $bibtex->content = $out["bibRaw"];
                    $bibtex->parse();
                    if (is_array($bibtex->data) && count($bibtex->data) > 0) {
                        $out["bib"] = $bibtex->data[0];
                        $out["bib"]["html"] = $bibtex->html();
                    }
                }
                else
                    $out[$ext] = file_get_contents($fpath);
            }
        }

        return $out;
    }

    /**
     * Get the JSON associated with the paper
     * @return array
     */
    public function getJSON () {
        return $this->getFiles(array("json"))["json"];
    }

    /**
     * Save the input JSON as the info for the paper
     * @param  array $json
     * @return int number of bytes written or FALSE if error
     */
    public function writeJSON ($json) {
        return file_put_contents($this->path."/".$this->key.".json", json_encode($json, JSON_PRETTY_PRINT));
    }

    public function delete () {
        if (!$this->exists())
            return true;
        return \lib\Utils::rrmdir($this->path);
    }
}
