<?php

namespace lib;

/**
 * This is a utility class to do "advanced" (in the sense that it would be cumbersome to write only
 * in template syntax) formating for various inputs.
 */
class Formatting {

    public static $MONTHS = array("Jan", "Feb", "Mar", "Apr", "May", "Jun", "Jul", "Aug", "Sep", "Oct", "Nov", "Dec");

    /**
     * Format an author's name.
     *
     * @param  string $author string to format (hopefully as "LastName, FirstName" or "FirstName LastName")
     * @return string         formatted string
     */
    public static function formatAuthor($author, $firstName = true) {
        $author = trim($author);

        if (strpos($author, ",") !== false) {
            $parts = explode(",", $author);
            if (!$firstName)
                return trim(array_shift($parts));
            else
                return trim(array_shift($parts))." ".implode(array_map(function($s){ return trim($s)[0]; }, $parts)).".";
        }
        elseif (strpos($author, " ") !== false) {
            $parts = explode(" ", $author);
            if (!$firstName)
                return trim(array_pop($parts));
            else
                return trim(array_pop($parts))." ".implode(array_map(function($s){ return trim($s)[0]; }, $parts)).".";
        }
        else
            return $author;
    }

    /**
     * Format a list of authors.
     *
     * @param  string[] $authors array of strings of authors
     * @param  string $format  format to use
     * @return string          formated string
     */
    public static function formatAuthors($authors, $format = "short") {

        // full format
        if ($format == "full")
            return implode(", ", array_map(function ($author) { return self::formatAuthor($author); }, $authors));

        // short format (X | X & Y | X et al.)
        else {
            if (count($authors) == 0)
                return "";
            elseif (count($authors) == 1)
                return self::formatAuthor($authors[0], false);
            elseif (count($authors) == 2)
                return self::formatAuthor($authors[0], false)." & ".self::formatAuthor($authors[1], false);
            else
                return self::formatAuthor($authors[0], false)." <em>et al.</em>";
        }
    }

    /**
     * Format rating to display a list of stars
     * @param  numeric  $rating
     * @param  boolean $showEmpty show a span even if no rating is given
     * @return string  formated string
     */
    public static function formatRating($rating, $showEmpty = false) {
        if (is_numeric($rating)) {
            $out = '<span class="rating" title="'.$rating.' / 5">';
            for ($i = 0; $i < floor(min($rating, 5)); $i++)
                $out .= '<i class="fa fa-star"></i>';
            if ($rating - floor($rating) > 0.25) {
                $out .= '<i class="fa fa-star-half-o"></i>';
                $i++;
            }
            for (; $i < 5; $i++)
                $out .= '<i class="fa fa-star-o"></i>';
            return $out.'</span>';
        }
        elseif ($showEmpty)
            return '<span class="rating rating-empty"></span>';

    }

    /**
     * Format a date to be compact
     * @param  string $date YYYY-MM-DD HH:MM date string
     * @return string       DD monthName HHh string
     */
    public static function formatDate($date) {
        return '<span class="date" title="'.$date.'" data-toggle="tooltip" data-placement="top">'.
            preg_replace_callback('#^[0-9]+-([0-9]+)-([0-9]+)[^0-9]+([0-9]+)\:[0-9]+$#', function ($els) {
                return $els[2].'&nbsp;'.self::$MONTHS[(int) $els[1]].'&nbsp;'.$els[3].'h';
            }, $date).'</span>';
    }

    /**
     * Format a list of tags, possibly using an array of colors
     * @param  array $tags   list of tags
     * @param  integer $key_index   index of the key (to match the javascript search)
     * @param  array  $tagsDB associative array (key = tag, value = hex color)
     * @return string         formated string
     */
    public static function formatTags($tags, $tagsGroup, $tagsDB = array()) {

        $out = "";

        if (is_array($tags)) {
            foreach ($tags as $tag) {
                $bg = isset($tagsDB[$tag]) ? $tagsDB[$tag] : "777";
                $fg = isset($tagsDB[$tag]) ? self::textColorFromBgColor($tagsDB[$tag]) : "FFF";
                $out .= '<span class="label label-default" data-tag="'.$tag.'" data-tag-group="'.$tagsGroup.'" style="background: #'.$bg.'; color: #'.$fg.'">'.$tag.'</span> ';
            }
        }

        return $out;
    }

    /**
     * Format username to insert possession's "s" at the end.
     * @param  string $username   username
     */
    public static function formatUsername($username) {

        function endsWith($text, $ending) {
            return $ending === "" || (($temp = strlen($text) - strlen($ending)) >= 0 && strpos($text, $ending, $temp) !== false);
        }

        $add = "'";
        if(!(endsWith($username, 's') || endsWith($username, 'S'))) {
            $add .= "s";
        }

        return $username . $add;
    }

    /**
     * Given an hex value of background color, which color should we use for
     * the text?
     *
     * This computation is based on W3C recommandations [1], computation of
     * luminance L is based on W3C [2] and conversion from sRGB to RGB based on
     * ITU-R recommendation BT.709 [3].
     *
     * Overall process based on [4].
     *
     * Actual luminance threshold is supposed to be 0.179 based on W3C recommandations,
     * but it was actually set to 0.33 based on trials and probably personnal taste.
     *
     * [1] http://www.w3.org/TR/WCAG20/
     * [2] http://www.w3.org/TR/WCAG20/#relativeluminancedef
     * [3] http://en.wikipedia.org/wiki/Luma_(video)#Rec._601_luma_versus_Rec._709_luma_coefficients
     * [4] http://stackoverflow.com/a/3943023
     *
     * @param  string $hexBgColor background hex color
     * @return string             foreground gex color
     */
    public static function textColorFromBgColor ($hexBgColor) {

        // extract components
        list($r, $g, $b) = sscanf($hexBgColor, "%02x%02x%02x");

        // fix sRGB to RGB
        $r /= 255;
        $g /= 255;
        $b /= 255;
        $r = ($r <= 0.03928) ? $r / 12.92 : pow(($r + 0.055) / 1.055, 2.4);
        $g = ($g <= 0.03928) ? $g / 12.92 : pow(($g + 0.055) / 1.055, 2.4);
        $b = ($b <= 0.03928) ? $b / 12.92 : pow(($b + 0.055) / 1.055, 2.4);

        // compute luminance
        $L = 0.2126 * $r + 0.7152 * $g + 0.0722 * $b;

        // decide
        return ($L > 0.33) ? "000000" : "FFFFFF";
    }

}
