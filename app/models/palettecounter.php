<?php

namespace models;

/**
 * Model to handle the library of papers
 */
class PaletteCounter {

    private $counter;

    public function __construct () {

        // get colors
        if (\models\User::instance()->isLoggedIn())
            $preferences = \models\User::instance()->getPreferences();
        if (is_array($preferences["palette"]))
            $palette = $preferences["palette"];
        else
            $palette = explode("-", \Base::instance()->get("TAGS_PALETTE"));

        // cleanup palette and create counter
        $this->counter = array();
        foreach ($palette as $color)
            if (preg_match("/^[0-9a-f]{6}$/i", $color))
                $this->counter[$color] = 0;

        // if palette is empty, add grey at least... (shouldn't happen)
        if (count($this->counter) == 0)
            $this->counter["CCCCCC"] = 0;
    }

    public function init ($tags) {
        foreach ($tags as $tagsGroup)
            foreach ($tagsGroup as $tag)
                if (isset($this->counter[$tag["color"]]))
                    $this->counter[$tag["color"]]++;
    }

    public function getNextColor () {
        $argmin = "";
        $min = INF;
        foreach ($this->counter as $color => $nb) {
            if ($nb < $min) {
                $argmin = $color;
                $min = $nb;
            }
        }
        $this->counter[$argmin]++;
        return $argmin;
    }
}
