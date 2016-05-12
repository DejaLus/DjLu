<?php

namespace models;

/**
 * Model to handle the library of papers
 */
class Papers extends \Prefab {

    private $f3;
    private $user;
    private $username;
    public static $TAGS_GROUPS = array("content", "reading");
    public static $TAGS_GROUPS_LABELS = array("Content", "Reading status");

    function __construct() {
        $this->f3 = \Base::instance();
        $this->user = \models\User::instance();
        $this->username = $this->user->getUsername();
    }

    /**
     * Return the list of papers as an array of arrays
     * TODO refactor this to return array of Paper
     * @return array
     */
    public function getPapers () {

        $folderPath = $this->f3->get("DATA_PATH").$this->username;

        $papers = array();

        foreach (scandir($folderPath) as $fname) {
            $paper = new Paper($fname);
            if ($paper->exists())
                $papers[] = $paper;
        }

        return $papers;
    }

    /**
     * Return the keys of all the papers in the library
     */
    public function getKeys () {

        $folderPath = $this->f3->get("DATA_PATH").$this->username;
        $keys = array();

        foreach (scandir($folderPath) as $fname) {
            $paper = new Paper($fname);
            if ($paper->exists())
                $keys[] = $paper->getKey();
        }

        return $keys;
    }

    /**
     * Return the next available key that starts with a prefix
     * @param  string $prefix
     * @return string
     */
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

    /**
     * Save the tags in the user preferences
     * @param  array $tags
     */
    public function saveTags ($tags) {
        $preferences = $this->user->getPreferences();
        $preferences["tags"] = $tags;
        $this->user->setPreferences($preferences);
    }

    /**
     * Get all the tags saved in the user preferences
     * @return array
     */
    public function getDeclaredTags () {
        $preferences = $this->user->getPreferences();

        $tags = array();
        $userTags = is_array($preferences["tags"]) ? $preferences["tags"] : array();

        // validate user data
        foreach (self::$TAGS_GROUPS as $group) {
            $tags[$group] = array();
            if (is_array($userTags[$group])) {
                foreach ($userTags[$group] as $tag => $tagVals) {
                    if (isset($tagVals["color"]) && preg_match("/^[0-9a-f]{6}$/i", $tagVals["color"])) {
                        $tags[$group][$tag] = array(
                            "pinned" => is_bool($tagVals["pinned"]) ? $tagVals["pinned"] : false,
                            "color" => $tagVals["color"],
                            "count" => is_int($tagVals["count"]) ? $tagVals["count"] : 0);
                    }
                }
            }
        }
        return $tags;
    }

    /**
     * Consolidate the existing list of tags with those used in the given list of papers
     * @param  array   $papers
     * @param  boolean $count      count the number of occurences of tags in the given papers
     * @param  boolean $resetCount reset the cached count when counting
     * @return array               consolidated tags
     */
    public function consolidateTags ($papers, $count = true, $resetCount = true) {

        $palette = new PaletteCounter();
        $tags = $this->getDeclaredTags();
        $palette->init($tags);

        // reset counts if requested
        if ($count && $resetCount)
            foreach ($tags as &$groupPtr)
                foreach ($groupPtr as &$tagPtr)
                    $tagPtr["count"] = 0;

        // loop over tag groups and papers to add tags
        foreach (self::$TAGS_GROUPS as $group) {
            foreach($papers as $paper) {
                if (is_array($paper->jsonField("tags_".$group))) {
                    foreach ($paper->jsonField("tags_".$group) as $tag) {
                        // assign color if needed
                        if (!isset($tags[$group][$tag]))
                            $tags[$group][$tag] = array("pinned" => false, "color" => $palette->getNextColor(), "count" => 0);

                        // count
                        if ($count)
                            $tags[$group][$tag]["count"]++;
                    }
                }
            }
        }

        // remove unused tag when not pinned
        if ($count && $resetCount)
            foreach ($tags as &$group)
                foreach ($group as $tag => $data)
                    if ($data["count"] == 0 && !$data["pinned"])
                        unset($group[$tag]);

        $this->saveTags($tags);

        return $tags;
    }

    /**
     * Returns tags array for a list of paper for an invite user (not logged in),
     * so basically simply assign colors to the tags of a list of papers
     *
     * @param  array   $papers
     * @return array
     */
    public function getInviteTags ($papers) {

        $palette = new PaletteCounter();
        $tags = array();
        foreach (self::$TAGS_GROUPS as $group)
            $tags[$group] = array();

        // loop over tag groups and papers to add tags
        foreach (self::$TAGS_GROUPS as $group)
            foreach($papers as $paper)
                if (is_array($paper->jsonField("tags_".$group)))
                    foreach ($paper->jsonField("tags_".$group) as $tag)
                        $tags[$group][$tag] = array("pinned" => false, "color" => $palette->getNextColor(), "count" => 1);

        return $tags;
    }
}
