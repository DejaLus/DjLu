<?php

namespace models;

/**
 * Model to handle stuff related to a single paper, particularly editing
 */
class Paper {

    private $f3;
    private $dataPath;
    private $key;
    private $filename;
    private $type;
    private $filesCache = array();

    const TYPE_FULL = "full";
    const TYPE_SHORT = "short";

    /**
     * Array of editable fields in JSON
     * @var array
     */
    public static $JSON_EDITABLE_FIELDS = array("title", "authors", "in", "rating", "year", "url", "date_added", "tags_content", "tags_reading", "tags_notes", "secret");

    function __construct($keyOrPath, $username = "") {
        $this->f3 = \Base::instance();
        $user = \models\User::instance();

        // extract username
        if (!$user->isLoggedIn() && empty($username))
            throw new \Exception("User not logged in");
        if (empty($username))
            $username = $user->getUsername();

        // create data path
        $this->dataPath = $this->f3->get("DATA_PATH").$username;
        if (!is_dir($this->dataPath))
            throw new \Exception("User directory missing");

        // create key is given, if not, the paper doesn't exist yet
        if (empty($keyOrPath))
            throw new \Exception("No key given");

        $this->key = preg_replace('/^([^_.]+)[_.].+$/', '\1', $keyOrPath);
        $this->detectPaperFilename($keyOrPath);
    }

    /**
     * Get the folder name corresponding to a paper
     * @param  string $possibleFolder suggestion of folder
     * @return mixed       string if found, null if not
     */
    private function detectPaperFilename ($possibleFilename = "") {

        if (!empty($possibleFilename)) {
            if (is_dir($this->dataPath."/".$possibleFilename) && is_file($this->dataPath."/".$possibleFilename."/".$this->key.".json")) {
                $this->filename = $possibleFilename;
                $this->type = self::TYPE_FULL;
                return;
            }
            if (is_file($this->dataPath."/".$possibleFilename) && preg_match("/\.txt$/i", $possibleFilename)) {
                $this->filename = $possibleFilename;
                $this->type = self::TYPE_SHORT;
                return;
            }
        }

        foreach (scandir($this->dataPath) as $fname) {
            $keyI = preg_replace('/^([^_.]+)[_.].+$/', '\1', $fname);

            if ($fname == "." || $fname == ".." || $keyI != $this->key)
                continue;

            $fpath = $this->dataPath."/".$fname;
            $jsonPath = $this->dataPath."/".$fname."/".$this->key.".json";
            if (is_dir($fpath) && is_file($jsonPath)) {
                $this->filename = $fname;
                $this->type = self::TYPE_FULL;
                return;
            }
            if (is_file($fpath) && preg_match("/\.txt$/i", $fname)) {
                $this->filename = $fname;
                $this->type = self::TYPE_SHORT;
                return;
            }
        }
    }

    /**
     * Check if the paper already exists
     * @return boolean
     */
    public function exists () {
        return $this->filename !== null;
    }

    /**
     * Get the key of the paper
     * @return string
     */
    public function getKey () {
        return $this->key;
    }

    /**
     * Set the key of the paper
     * @param string $key
     */
    public function setKey ($key) {
        $this->key = $key;
        $this->detectPaperFilename($key);
    }

    /**
     * Return the type of the paper
     * @return string
     */
    public function getType() {
        return $this->type;
    }

    /**
     * Get the path of the paper (directory or file path)
     * @return string
     */
    private function getPath() {
        if (!$this->exists())
            throw new \Exception("Paper does not exist");
        return $this->dataPath . "/" . $this->filename;
    }

    /**
     * Get the bibtex from arXiv ID
     * @param  string $id arXiv ID
     * @return string     bibtex
     */
    private static function bibTexFromArXiv ($id) {
        $xml = @file_get_contents("http://export.arxiv.org/api/query?id_list=".$id);
        if (!$xml)
            throw new \Exception("Error while loading, the ID probably do not exist");

        $xml = json_decode(json_encode(simplexml_load_string($xml)), true);

        if ($xml["entry"]["title"] == "Error")
            throw new \Exception("No paper found for arXiv id ".$id);

        if (isset($xml["entry"]["author"]["name"]))
            $author = $xml["entry"]["author"]["name"];
        else
            $author = implode(" and ", array_map(function ($x) { return $x["name"]; }, $xml["entry"]["author"]));

        $time = strtotime($xml["entry"]["published"]);
        $bib = array(
            "cite" => $xml["entry"]["author"][0]["name"].date("Y", $time),
            "entryType" => "article",
            "title" => trim($xml["entry"]["title"]),
            "author" => $author,
            "booktitle" => "arXiv",
            "year" => date("Y", $time),
            "month" => date("M", $time),
            "archivePrefix" => "arXiv",
            "arxivId" => $id,
            "eprint" => $id,
            "url" => call_user_func(function($links) {foreach ($links as $y) { if($y["@attributes"]["title"] == "pdf") { return $y["@attributes"]["href"]; } }}, $xml["entry"]["link"]),
            "abstract" => trim($xml["entry"]["summary"])
            );

        $bibtex = new \models\BibTex(array("removeCurlyBraces" => false, "extractAuthors" => false));
        $bibtex->addEntry($bib);
        return $bibtex->toBibTex();
    }

    /**
     * Get the bibtex from DOI
     * @param  string $id DOI
     * @return string     bibtex
     */
    private static function bibTexFromDOI ($id) {
        $context = stream_context_create(array(
            "http" => array(
                "method" => "GET",
                "header" => "Accept: text/bibliography; style=bibtex\r\n"
            )
        ));

        $data = @file_get_contents("http://dx.doi.org/".$id, false, $context);

        if (!$data)
            throw new \Exception("Error while loading, the ID probably do not exist");

        return $data;
    }

    /**
     * Create a paper record from a standard ID (DOI, arXiv)
     * @param  string $rawId
     * @param  string $citationKey citation key
     */
    public static function createFromId ($rawId, $citationKey = "") {

        $rawId = trim($rawId);
        if (preg_match("/^(?:doi:)?(10\..+)$/", $rawId, $match)) {
            // DOI
            $rawId = $match[1];
            $bibtex = self::bibTexFromDOI($rawId);
        } elseif (preg_match("/^(?:ar[Xx]iv:)?(?:.*arxiv\.org\/[a-z]{3}\/)?(.+)$/", $rawId, $match)) {
            // arXiv
            $rawId = $match[1];
            $bibtex = self::bibTexFromArXiv($rawId);
        } else {
            throw new \Exception("ID not supported");
        }

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
        $bibtex = new \models\BibTex(array('removeCurlyBraces' => false, 'extractAuthors' => false));
        $bibtex->content = $bibtexData;
        $bibtex->parse();

        // init
        $successes = array();
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
            $key = preg_replace("/[^a-zA-Z0-9]/", "", \lib\Utils::remove_accents($key));
            $key = \models\Papers::instance()->getNextAvailableKey($key);

            // try to create paper, if not catch and record error
            try {
                $paper = new \models\Paper($key);
                $paper->createFromBibTexData($data);
                $successes[] = $paper;
            }
            catch (\Exception $e) {
                $errors[] = "Error for paper with key ".$key.": ".$e->getMessage();
            }
        }

        // return
        if (count($successes) == 0)
            throw new \Exception(implode("\n", $errors));
        else
            return array("sucesses" => $successes, "errors" => $errors);
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
        $this->type = self::TYPE_FULL;

        // check key, title, author
        if (!preg_match("/^[a-zA-Z0-9]+$/", $this->key))
            throw new \Exception("Key can only contain letters and numbers");
        if ($this->exists()) // paper already exists
            throw new \Exception("A paper with this key already exists");
        if (empty($data["title"]) || empty($data["author"]))
            throw new \Exception("Empty title or author");

        // create folder
        $folderTitle = preg_replace("/[^a-zA-Z]/", " ", $data["title"]);
        $folderTitle = preg_replace("/ +/", "_", $folderTitle);
        $folderTitle = substr($folderTitle, 0, 50);

        mkdir($this->dataPath."/".$this->key."_".$folderTitle, 0755);
        $this->filename = $this->key."_".$folderTitle;

        // create bibtex
        $bibtex = new \models\BibTex(array('removeCurlyBraces' => false, 'extractAuthors' => false));
        $bibtex->addEntry($data);

        // create JSON data
        $json = array(
            "title" => $bibtex->removeCurlyBraces($data["title"]),
            "authors" => explode(" and ", $bibtex->removeCurlyBraces($data["author"])),
            "year" => $data["year"] ? $bibtex->removeCurlyBraces($data["year"]) : "",
            "date_added" => date("Y-m-d H:i"),
            "in" => $data["booktitle"] ? $bibtex->removeCurlyBraces($data["booktitle"]) : "",
            "tags_reading" => array("new"),
            "url" => $data["url"] ? $bibtex->removeCurlyBraces($data["url"]) : "");

        if ($this->writeJSON($json) === false)
            throw new \Exception("Failed to write JSON file");

        // save bibtex
        if (file_put_contents($this->getPath()."/".$this->key.".bib", $bibtex->toBibTex()) === false)
            throw new \Exception("Failed to write bibtex file");
    }

    /**
     * Add a raw text reference
     * @param  string $raw         Raw text
     * @param  string $citationKey
     * @return array              success keys and error messages
     */
    public static function createShort ($raw, $citationKey = "") {
        if (empty(trim($raw)))
            throw new \Exception("No string reference received");

        // citation key
        if (!is_string($citationKey))
            $citationKey = "";
        $citationKey = preg_replace("/[^a-zA-Z0-9]/", "", \lib\Utils::remove_accents($citationKey));
        if (empty($citationKey)) {
            $words = explode(" ", preg_replace("#[-_;:,./?! ]+#", " ", $raw));
            preg_match("/(^|[^0-9-])((17|18|19|20|21)[0-9]{2})([^0-9-]|$)/", $raw, $matches);
            for ($i = 0; $i < min(count($words), 2); $i++)
                $citationKey .= $words[$i];
            if (count($matches) > 3)
                $citationKey .= $matches[2];
            $citationKey = preg_replace("/[^a-zA-Z0-9]/", "", \lib\Utils::remove_accents($citationKey));
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

        return array("sucesses" => array(new Paper($citationKey)), "errors" => array());
    }

    /**
     * Edit a field in the JSON of the paper
     * @param  string $field
     * @param  string $value
     */
    private function editJSON ($field, $value) {
        if (!$this->exists() || $this->type == self::TYPE_SHORT)
            throw new \Exception("Impossible to edit");
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
            $value = array_values(array_unique(array_filter(array_map("trim", $value))));
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
        if (!$this->exists() || $this->type == self::TYPE_SHORT)
            throw new \Exception("Impossible to edit");

        $content = trim($content);
        $mdPath = $this->getPath()."/".$this->key.".md";

        if (empty($content) && is_file($mdPath))
            if (!unlink($mdPath))
                throw new \Exception("Failed to remove notes");

        if (!empty($content))
            if (file_put_contents($mdPath, $content."\n") === false)
                throw new \Exception("Failed to save file");
        unset($this->filesCache["md"]); // clean cache
    }

    /**
     * Edit a field about the paper
     * @param  string $file
     * @param  string $field
     * @param  string $value
     * @return boolean        success
     */
    public function edit ($file, $field, $value) {
        if ($this->type == self::TYPE_FULL && $file == "json")
            return $this->editJSON($field, $value);
        if ($this->type == self::TYPE_FULL && $file == "md")
            return $this->editMD($value);
        throw new \Exception("File not editable");
    }

    /**
     * Return a file
     * @param  string $str file identifier (md, json, bib)
     * @return mixed
     */
    public function getFile($str) {
        $out = $this->getFiles(array($str));
        return isset($out[$str]) ? $out[$str] : null;
    }

    /**
     * Return different elements related to a given paper
     * @param  array  $els elements to get
     * @return array       list of requested elements (if exists)
     */
    public function getFiles ($els = array("json", "bib", "md")) {

        if (!$this->exists())
            return array();

        if ($this->type == self::TYPE_SHORT)
            return array("md" => file_get_contents($this->getPath()));

        foreach ($els as $ext) {
            $ext = preg_replace("/[^a-z0-9]/i", "", $ext);
            $fpath = $this->getPath()."/".$this->key.".".$ext;
            if (is_file($fpath)) {
                if ($ext == "json") {
                    if (!isset($this->filesCache["json"]))
                        $this->filesCache["json"] = json_decode(file_get_contents($fpath), true);
                    $out["json"] = $this->filesCache["json"];
                }
                elseif ($ext == "bib") {
                    if (!isset($this->filesCache["bibRaw"]))
                        $this->filesCache["bibRaw"] = file_get_contents($fpath);
                    if (!isset($this->filesCache["bib"])) {
                        $bibtex = new \models\BibTex(array('removeCurlyBraces' => false, 'extractAuthors' => false));
                        $bibtex->content = $this->filesCache["bibRaw"];
                        $bibtex->parse();
                        if (is_array($bibtex->data) && count($bibtex->data) > 0) {
                            $this->filesCache["bib"] = $bibtex->data[0];
                            $this->filesCache["bib"]["html"] = $bibtex->html();
                        }
                    }
                    $out["bibRaw"] = $this->filesCache["bibRaw"];
                    $out["bib"] = $this->filesCache["bib"];
                }
                else {
                    if (!isset($this->filesCache[$ext]))
                        $this->filesCache[$ext] = file_get_contents($fpath);
                    $out[$ext] = $this->filesCache[$ext];
                }
            }
        }

        return $out;
    }

    /**
     * Get the JSON associated with the paper
     * @return array
     */
    public function getJSON () {
        $json = $this->getFile("json");
        return $json != null ? $json : array();
    }

    /**
     * Return the value of a field from the JSON
     * @param  string $field field name
     * @return string
     */
    public function jsonField($field) {
        $json = $this->getJSON();
        if (isset($json[$field]))
            return $json[$field];
        else
            return null;
    }

    /**
     * Return the creation date of the paper
     * @return string YYYY-MM-DD HH:MM
     */
    public function getDateAdded () {
        if (!$this->exists())
            return "";
        elseif ($this->type == self::TYPE_FULL)
            return $this->jsonField("date_added");
        else
            return date("Y-m-d H:i", filemtime($this->getPath()));
    }

    /**
     * Return the MD notes about the paper as HTML
     * @return string
     */
    public function getNotesHTML ($options = array()) {
        $md = $this->getFile("md");
        if (!$md)
            return;
        $md = preg_replace('/(^|[^\\\])\$\$/', '\1`eq2', $md);
        $md = preg_replace('/(^|[^\\\])\$/',   '\1`eq',  $md);

        $parsedown = new \lib\Parsedown();
        if (isset($options["nl2br"]))
            $md = str_replace("\n", "<br>", trim($md));
        $html = $parsedown->text($md);

        $html = preg_replace('/\<\\/?code\>eq2/', '$$', $html);
        $html = preg_replace('/\<\\/?code\>eq/', '$', $html);
        $html = html_entity_decode($html, ENT_QUOTES);

        return $html;
    }

    /**
     * Save the input JSON as the info for the paper
     * @param  array $json
     * @return int number of bytes written or FALSE if error
     */
    public function writeJSON ($json) {
        if ($this->type != self::TYPE_FULL || !$this->exists())
            return;
        unset($this->filesCache["json"]); // clean cache
        return file_put_contents($this->getPath()."/".$this->key.".json", json_encode($json, JSON_PRETTY_PRINT));
    }

    /**
     * Remove the paper from the library
     */
    public function delete () {
        if (!$this->exists())
            return true;
        return \lib\Utils::rrmdir($this->getPath());
    }
}
