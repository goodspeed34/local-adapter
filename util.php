<?php
/* util.php
 * Copyright (C) 2022  PortaStream Team
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <https://www.gnu.org/licenses/>.
 */


/* Polyfill for PHP8 features. */
if (!function_exists('str_ends_with')) {
    function str_ends_with($haystack, $needle)
    {
        return $needle !== '' ? substr($haystack, -strlen($needle)) === $needle : true;
    }
}

/**
 * Convert a file system path to `file://' prefixed URL.
 */
function fspath_to_url($path) {
    return 'file://' . str_replace("%2F", "/", rawurlencode(realpath($path)));
}

/**
 * Convert a `file://' prefixed URL back to the file system path.
 */
function url_to_fspath($url) {
    /* ltrim() does not work somehow, so replace it. :-( */
    return urldecode(str_replace('file://', '', $url));
}

/**
 * Convert a time to the julian_day since January 1, Year 1.
 * 
 * @param integer $time a UNIX timestamp.
 * @return integer days since January 1, Year 1.
 */
function time_to_julian($time) {
    return floor(abs((-62135596800 - $time) / 86400));
}

/**
 * Convert the julian_day since January 1, Year 1 to a time.
 * 
 * @param integer $julian_day days since January 1, Year 1.
 * @return integer the time of that day.
 */
function julian_to_time($julian_day) {
    return -62135596800 + (60*60*24 * $julian_day);
}

?>
