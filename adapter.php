<?php
/* adapter.php
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

/*************************************************************************
 * START of Adapter Configuration 
 *************************************************************************/

// To disable authentication, change this to false.
//$secret = 'YOUR PASSWORD GOES HERE';
$secret = false;

// Path to your music library.
$local_libs = [ '/home/goodspeed/Music' ];

// Allowed extensions, anything without this will be ignored.
$allowed_exts = [ '.mp3', '.flac', '.ogg', '.wav' ];

// Allow all requests, change this if you want a strict policy.
header('Access-Control-Allow-Origin: *');

$fmap_fs = dirname(__LINE__) . '/fmap';
$db_fs = dirname(__LINE__) . '/rhythmdb.xml';
$db_exist = file_exists($db_fs);

// for production, some of the functions may throw warnings and mess the output up.
error_reporting(E_ERROR | E_PARSE);

/*************************************************************************
 * END of Adapter Configuration 
 *************************************************************************/

require_once 'util.php';
require 'rhythmdb.php';

/* Make sure we can trust this bro. :-) */
if ($secret
    && (array_key_exists('k', $_COOKIE) ? $_COOKIE['k'] != md5($secret) : true)
    && (array_key_exists('k', $_GET) ? $_GET['k'] != md5($secret) : true))
{
    http_response_code(403);
    die;
}

$libm = new LibraryManager($db_fs, $fmap_fs, $allowed_exts);

if (!$db_exist)
{
    foreach ($local_libs as $ldir)
        $libm->add_music_dir($ldir);
}

header('Content-Type: application/json; charset=UTF-8');
switch ($_SERVER['PATH_INFO']) {
    case '/desc':
        echo json_encode([
            'name' => 'RhythmDB local adapter',
            'author' => 'William Goodspeed <goodspeed@anche.no>',
            'website' => 'https://www.fsfans.club',
            'license' => 'AGPL-3.0-or-later'
        ]);
        break;

    case '/capabilities':
        echo json_encode([ 'desc', 'list', 'fetch', 'metadata', 'multiple_metadata' ]);
        break;

    case '/list':
        $libm->update_map();
        echo json_encode(array_keys($libm->fmap));
        break;

    case '/multiple_metadata':
        $req = json_decode(file_get_contents("php://input"));
        if ($req === null)
        {
            http_response_code(400);
            echo 'bad reuqest body, should be a list of ids';
            die();
        }

        $ret_arr = array();
        $xml = simplexml_import_dom($libm->db->xml);

        foreach ($req as $song_id) {
            foreach ($xml as $entry)
            {
                $metadata = (array) $entry;
                if (md5($metadata['location']) != $song_id)
                    continue;
                unset($metadata['@attributes']);
                $metadata['location'] = md5($metadata['location']);
                $ret_arr[$song_id] = $metadata;
                break;
            }

            if (!array_key_exists($song_id, $ret_arr))
                $ret_arr[$song_id] = null;
        }

        echo json_encode($ret_arr);
        break;

    case '/metadata':
        $xml = simplexml_import_dom($libm->db->xml);

        if (!isset($_GET['id']))
        {
            http_response_code(400);
            echo 'the ID is missing, check your request';
            die();
        }

        foreach ($xml as $entry)
        {
            $metadata = (array) $entry;
            if (md5($metadata['location']) != $_GET['id'])
                continue;
            unset($metadata['@attributes']);
            $metadata['location'] = md5($metadata['location']);
            echo json_encode($metadata);
            exit;
        }

        http_response_code(404);
        echo 'can\'t find the target ID in the database';
        die();

        break;

    case '/fetch':
        header_remove('Content-Type');

        if (!isset($_GET['id']))
        {
            http_response_code(400);
            echo 'the ID is missing, check your request';
            die();
        }

        $libm->update_map();

        if (!array_key_exists($_GET['id'], $libm->fmap)) {
            http_response_code(404);
            echo 'can\'t find the target ID in the database';
            die();
        }

        $fp = url_to_fspath($libm->fmap[$_GET['id']]);

        if (file_exists($fp)) {
            header('Content-Type: ' . mime_content_type($fp));
            header('Content-Length: ' . filesize($fp));
            readfile($fp);
            exit;
        } else {
            http_response_code(404);
            die();
        }
        break;

    default:
        http_response_code(400);
        header_remove('Content-Type');
        echo 'Invalid endpoint, what are you looking for?';
        break;
}
?>