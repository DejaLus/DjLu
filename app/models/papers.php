<?php

namespace models;

/**
 * Model to handle the library of papers
 */
class Papers extends \Prefab {

    private $f3;
    private $username;
    public static $TAGS_GROUPS = array("content", "reading");
    public static $TAGS_GROUPS_LABELS = array("Content", "Reading status");

    function __construct() {
        $this->f3 = \Base::instance();
        $this->username = \models\User::instance()->getUsername();
    }

    public function getPapers () {

        $folderPath = $this->f3->get("DATA_PATH").$this->username;

        $papers = array();

        // iterate of the folders of the user
        foreach (scandir($folderPath) as $fname) {

            $key = preg_replace('/^([^_]+)_.+$/', '\1', $fname); // before _ is the "key" of the paper
            $dirPath = $folderPath."/".$fname;
            $jsonPath = $folderPath."/".$fname."/".$key.".json";
            $mdPath = $folderPath."/".$fname."/".$key.".md";

            if ($fname != "." && $fname != ".." && is_dir($dirPath) && is_file($jsonPath)) {
                $paper = json_decode(file_get_contents($jsonPath), true);
                $paper["type"] = "full";
                $paper["key"] = $key;
                $paper["folder"] = $fname;
                $paper["hasNotes"] = is_file($mdPath);
                $papers[$key] = $paper;
            }
            elseif (is_file($dirPath) && preg_match("/^([a-zA-Z0-9]+)\.txt$/", $fname, $matches)) {
                $papers["short_".$matches[1]] = array(
                    "type" => "short",
                    "key" => "short_".$matches[1],
                    "str" => file_get_contents($dirPath),
                    "date_added" => date("Y-m-d H:i", filemtime($dirPath)));
            }
        }

        return $papers;
    }

    public function getKeys () {

        $folderPath = $this->f3->get("DATA_PATH").$this->username;
        $keys = array();

        foreach (scandir($folderPath) as $fname) {

            $key = preg_replace('/^([^_]+)_.+$/', '\1', $fname);
            $dirPath = $folderPath."/".$fname;
            $jsonPath = $folderPath."/".$fname."/".$key.".json";

            if ($fname != "." && $fname != ".." && is_dir($dirPath) && is_file($jsonPath))
                $keys[] = $key;
        }

        return $keys;
    }

    public function getNextAvailableKey ($prefix) {
        $sufixes = array();
        foreach ($this->getKeys() as $key)
            if (strpos($key, $prefix) === 0)
                $sufixes[] = substr($key, strlen($prefix));

        if (count($sufixes) == 0)
            return $prefix;

        $sufixCandidate = "a";
        while (in_array($sufixCandidate, $sufixes))
            $sufixCandidate++;

        return $prefix.$sufixCandidate;
    }

    public function getDeclaredTags () {
        $preferences = \models\User::instance()->getPreferences();
        $tags = array("content" => array(), "reading" => array());
        foreach (self::$TAGS_GROUPS as $group)
            if (is_array($preferences["tags_".$group]))
                $tags[$group] = $preferences["tags_".$group];
        return $tags;
    }

    public function getTags ($papers) {
        $preferences = \models\User::instance()->getPreferences();
        if (is_array($preferences["palette"]))
            $palette = $preferences["palette"];
        else
            $palette = explode("-", $this->f3->get("TAGS_PALETTE"));
        $i = 0;
        $n = count($palette);

        $tags = $this->getDeclaredTags();
        foreach (self::$TAGS_GROUPS as $group)
            foreach($papers as $paper)
                if (is_array($paper["tags_".$group]))
                    foreach ($paper["tags_".$group] as $tag)
                        if (!isset($tags[$group][$tag]))
                            $tags[$group][$tag] = $palette[$i++ % $n];

        return $tags;
    }
}
