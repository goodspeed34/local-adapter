<?php
/* library.php
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

require 'rhythmdb.php';
require_once 'util.php';

class LibraryManager
{
    public $allowed_exts;
    public $fmap_f;
    public $fmap;
    public $db;

    /**
     * Scan a music directory and try to write them to the database.
     */
    function add_music_dir(string $dir) {
        $this->update_map();
        $this->load_fmap();

        $dir_e = new RecursiveDirectoryIterator($dir);
        $iter = new RecursiveIteratorIterator($dir_e);

        foreach ($iter as $finfo) {
            if($finfo->isDir()) continue;

            if (in_array($finfo->getRealPath(), array_values($this->fmap)))
                continue;

            try {
                $this->add_music_file($finfo->getRealPath());
            } catch (Exception $ex) {
                print($ex);
            }
        }

        $this->db->save();
        update_file_map($this->db->xml, $this->fmap_f);
    }

    /**
     * Add a single song to the music library.
     * 
     * @param string $music_file A path to the targeted song.
     */
    function add_music_file(string $music_file) {
        /* Verify this is a file with an acceptable extension. */
        $ext_ok = false;
        foreach ($this->allowed_exts as &$ext)
            str_ends_with($music_file, $ext) ? ($ext_ok = true) : '';
        if (!$ext_ok) return;

        $entry = $this->db->new_entry();
        $entry->load_from_file($music_file);
        $entry->save();
    }

    /**
     * Load the current file map.
     */
    function load_fmap() {
        if (!file_exists($this->fmap_f))
            file_put_contents($this->fmap_f, serialize([]));
        $this->fmap = unserialize(file_get_contents($this->fmap_f));
    }

    /**
     * Write the current data to the file map.
     */
    function update_map() {
        $xml = simplexml_import_dom($this->db->xml);
        $this->fmap = array();
        foreach ($xml as $entry)
            $this->fmap[md5($entry->location)] = strval($entry->location);
        file_put_contents($this->fmap_f, serialize($this->fmap));
    }

    /**
     * Construct a local music library manager.
     * 
     * @param string $db_file A path to the music database. (foobar.xml)
     */
    function __construct($db_file, $map_file, $allowed_exts) {
        $this->allowed_exts = $allowed_exts;
        $this->db = new RhythmDB;
        $this->db->load($db_file);
        $this->fmap_f = $map_file;
        $this->load_fmap();
    }
}
?>
