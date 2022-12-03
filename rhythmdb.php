<?php
/* rhythmdb.php
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

require 'getID3/getid3.php';
require_once 'util.php';

/**
 * Get a tag from parsed id3v1 or id3v2.
 *
 * @param array $result A parsed result from getID3->analyze().
 * @param string $name The name of the field your're requesting.
 */
function tag_from_id3v1or2($result, $name) {
	if (array_key_exists('id3v2', $result['tags']))
		if (array_key_exists($name, $result['tags']['id3v2']))
			return implode('/', $result['tags']['id3v2'][$name]);
	if (array_key_exists('id3v1', $result['tags']))
		if (array_key_exists($name, $result['tags']['id3v1']))
			return implode('/', $result['tags']['id3v1'][$name]);
	return 'Unknown';
}

/**
 * Get a TPE2 or TPE1 from parsed id3v1 or id3v2.
 *
 * @param array $result A parsed result from getID3->analyze().
 */
function tpe_from_id3v2($result) {
	if (array_key_exists('id3v2', $result))
		if (array_key_exists('TPE2', $result['id3v2']))
			return $result['id3v2']['TPE2'];
		else if (array_key_exists('TPE1', $result['id3v2']))
			return $result['id3v2']['TPE1'];
	return 'Unknown';
}

/**
 * An actuall song stored in RhythmDBEntry.
 */
class RhythmDBEntry
{
	/* The type of entry: iradio, ignore, song. */
	public $type = 'ignore';

	/* Initialize the entry with must fileds. */
	public $info = array(
		'title'		=> '',
		'genre'		=> '',
		'artist'	=> '',
		'album'		=> '',
		'location'	=> '',
		'date'		=> '',
		'media-type'=> '',
	);

	public $elem;

	function __construct(DOMElement $elem) {
		$this->elem = $elem;

		/* Oops, bad type! Regard this entry as a new one. */
		if (!in_array($elem->getAttribute('type'), [ 'iradio', 'ignore', 'song' ])) {
			$this->save();
		}
	}

	/**
	 * Set a field in the entry.
	 */
	function set(string $k, string $v) {
		$this->info[$k] = $v;
	}

	/**
	 * Load the metadata from the file and complete the info.
	 * 
	 * @param string $file The targeted file to be added.
	 */
	function load_from_file(string $file) {
		$id3_parser = new getID3;
		$metadata = $id3_parser->analyze($file);

		$this->type = 'song';

		$this->set('media-type', $metadata['mime_type']);
		$this->set('file-size', $metadata['filesize']);
		$this->set('location', fspath_to_url($file));
		$this->set('mtime', filemtime($file));

		$this->set('bitrate', intval($metadata['audio']['bitrate']));
		$this->set('duration', intval($metadata['playtime_seconds']));

		$this->set('title', tag_from_id3v1or2($metadata, 'title'));
		$this->set('artist',tag_from_id3v1or2($metadata, 'artist'));
		$this->set('album', tag_from_id3v1or2($metadata, 'album'));
		$this->set('genre', tag_from_id3v1or2($metadata, 'genre'));

		if (($track_number = tag_from_id3v1or2($metadata, 'track_number')) !== 'Unknown')
			$this->set('track-number', intval($track_number));

		if (($comment = tag_from_id3v1or2($metadata, 'comment')) !== 'Unknown')
			$this->set('comment', $comment);

		// Need to be fixed, causing tons of blank tags...
		// if (($tpe = tpe_from_id3v2($metadata)) !== 'Unknown')
		// 	$this->set('album-artist', $tpe[0]['data']);

		/* Fallback to the filename if we don't know the title. */
		if ($this->info['title'] === 'Unknown')
			$this->set('title', basename($file));

		$this->set('composer', tag_from_id3v1or2($metadata, 'composer'));

		$this->set('date', time_to_julian(time()));
	}

	/**
	 * Save the metadata to the XML node.
	 */
	function save() {
		$this->elem->setAttributeNode(new DOMAttr('type', $this->type));

		foreach ($this->info as $k => $v) {
			$node_list = $this->elem->getElementsByTagName($k);
			if (!$node_list->length) {
				$this->elem->appendChild($this->elem->ownerDocument->createElement($k, htmlspecialchars($v)));
			} else {
				$node_list->item(0)->nodeValue = htmlspecialchars($v);
			}
		}
	}
}

/**
 * Rhythmbox database for PortaStream.
 * 
 * @author William Goodspeed <goodspeed@anche.no>
 */
class RhythmDB
{
	public $xml, $xml_f, $root;

	/* Just initialize it... */
    function __construct() {
        $this->xml = new DOMDocument();
    }

	/**
	 * Load the database from disk.
	 */
	function load(string $xml_f) {
		$this->xml_f = $xml_f;

        // TODO: suppress this warning.
        $this->xml->load($xml_f, LIBXML_NOBLANKS);

        if (!file_exists($xml_f)) {
            // Initialize the basic XML conf.
            $this->xml->xmlVersion = '1.0';
            $this->xml->standalone = true;
            // Initialize the main node.
            $this->root = $this->xml->createElement('rhythmdb');
            $this->root = $this->xml->appendChild($this->root);
            $this->root->setAttributeNode(new DOMAttr('version', '2.0'));
            // Save back to the database.
            $this->xml->save($xml_f);
        }

        $this->xml->formatOutput = true;
        $this->root = $this->xml->documentElement;
	}

	/**
	 * Save the database to disk.
	 */
	function save() {
	    $this->xml->save($this->xml_f);
	}


	function new_entry() {
		$entry_elem = $this->xml->createElement('entry');
		$this->root->appendChild($entry_elem);
		return new RhythmDBEntry($entry_elem);
	}
}

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
        $this->update_map();
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