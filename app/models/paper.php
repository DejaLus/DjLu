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

    function __construct($key) {
        $this->f3 = \Base::instance();
        $user = \models\User::instance();

        if (!$user->isLoggedIn())
            throw new \Exception("User not logged in");

        $this->dataPath = $this->f3->get("DATA_PATH").$user->getUsername();
        $this->key = $key;
        $this->folder = $this->getPaperFolder();
        if ($this->folder)
            $this->path = $this->dataPath."/".$this->folder;
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
     * TODO Create the paper entry in the repository
     * @param  [type] $folderTitle Title of the folder to add after the paper key (can be empty)
     * @param  [type] $bibtex      Bibtex record of the paper
     * @param  [type] $json        Initial info for the JSON, probably tags (optional)
     * @param  [type] $notes       Notes about the paper (optional)
     */
    public function create ($folderTitle, $bibtex, $notes) {
        return;
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
