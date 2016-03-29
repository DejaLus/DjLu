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
    public static $JSON_EDITABLE_FIELDS = array("title", "authors", "year", "url", "date_added", "tags_content", "tags_reading", "tags_notes");

    function __construct($key = "") {
        $this->f3 = \Base::instance();
        $user = \models\User::instance();

        if (!$user->isLoggedIn())
            throw new \Exception("User not logged in");

        $this->dataPath = $this->f3->get("DATA_PATH").$user->getUsername();
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
     * Create the paper entry in the repository from the bibtex
     * @param  string $bibtexRaw      Bibtex record of the paper
     */
    public function createFromBibTex ($bibtexRaw) {
        $bibtex = new \models\BibTex(array('removeCurlyBraces' => true, 'extractAuthors' => false));
        $bibtex->content = $bibtexRaw;
        $bibtex->parse();

        // if bibtex invalid
        if (!is_array($bibtex->data) || count($bibtex->data) == 0)
            throw new \Exception("Invalid bibtex data");

        // extract data
        $data = $bibtex->data[0];
        $this->key = $data["cite"];

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

        $this->writeJSON($json);

        // save bibtex
        file_put_contents($this->path."/".$this->key.".bib", $bibtexRaw);
    }

    /**
     * Edit a field about the paper
     * @param  string $field
     * @param  string $value
     * @return boolean        success
     */
    public function edit ($field, $value) {

        if (!$field || !in_array($field, self::$JSON_EDITABLE_FIELDS))
            return false;

        $value = trim($value);

        // check valid value
        if (($field == "title" || $field == "authors") && empty($value))
            return false;
        if ($field == "date_added" && !preg_match("/([0-9]{4})-([0-9]{2})-([0-9]{2}) ([0-9]{2}):([0-9]{2})/", $value, $matches)) {
            if (!checkdate($matches[2], $matches[3], $matches[1]) ||
                (int) $matches[4] < 0 || (int) $matches[4] > 23 ||
                (int) $matches[5] < 0 || (int) $matches[4] > 59)
                return false;
        }

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
        return $this->writeJSON($json);
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
                    $bibtex = new \models\BibTex(array('removeCurlyBraces' => true, 'extractAuthors' => true));
                    $bibtex->content = $out["bibRaw"];
                    $bibtex->parse();
                    if (is_array($bibtex->data) && count($bibtex->data) > 0)
                        $out["bib"] = $bibtex->data[0];
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
}
