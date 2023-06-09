<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * @package   block_tildeva
 * @copyright 2023, Evita Korņējeva <evita.kornejeva@tilde.lv>
 * @copyright 2023, SIA Tilde
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('AJAX_SCRIPT', true);

require(__DIR__ . '/../../config.php');

$action = optional_param('action', '', PARAM_ALPHANUM);
$userid = optional_param('chat_usid', 0, PARAM_INT);
$courseid = optional_param('chat_couid', 0, PARAM_INT);
$conversationid = optional_param('chat_convid', '', PARAM_RAW);
$input = optional_param('chat_msg', '', PARAM_RAW);


if (!isloggedin()) {
    throw new moodle_exception('notlogged', 'chat');
}

if (!$chatsession = $DB->get_record('chat_sessions', array('userid' => $userid, 'courseid' => $courseid))) {
    $chatsession = new stdClass();
    $chatsession->conversationid = 0;
}


// Set up $PAGE so that format_text will work properly.
// $PAGE->set_cm($cm, $course, $chat);
$PAGE->set_url('/blocks/tildeva/chat_ajax.php', array('chat_usid' => $userid));



ob_start();
header('Expires: Sun, 28 Dec 1997 09:32:45 GMT');
header('Last-Modified: ' . gmdate('D, d M Y H:i:s') . ' GMT');
header('Cache-Control: no-cache, must-revalidate');
header('Pragma: no-cache');
header('Content-Type: text/html; charset=utf-8');

switch ($action) {
    case 'init':
        $response['firstname'] = $USER->firstname;
        $response['lastname'] = $USER->lastname;
        $response['email'] = $USER->email;
        $response['conversationid'] = $chatsession->conversationid;
        echo json_encode($response);
        break;
    case 'sid':
        $chatsession->conversationid = $conversationid;
        $chatsession->userid = $userid;
        $chatsession->courseid = $courseid;

        if ($chatsession->id) {
            $DB->update_record('chat_sessions', $chatsession);
        } else {
            $DB->insert_record('chat_sessions', $chatsession);
        }
        echo json_encode($chatsession);
        break;
    case 'msg':
        if ($input) {

            $matches = array();
            $matchCount = preg_match('/:(\w+)$/', $input, $matches);

            if ($matchCount > 0) {
                // Match found
                $command = $matches[1];
                switch ($command) {
                    case 'getuserinfo':
                        $response['firstname'] = $USER->firstname;
                        $response['lastname'] = $USER->lastname;
                        $response['email'] = $USER->email;
                        $response['username'] = $USER->username;
                        // Get the course context
                        $context = context_course::instance($courseid);

                        // Get the user's roles in the course
                        $userRoles = get_user_roles($context, $userid, false);

                        // Iterate through the roles and extract role names
                        $roleNames = array();
                        foreach ($userRoles as $role) {
                            $roleNames[] = $role->shortname;
                        }

                        $response['userroles'] = implode(", ", $roleNames);
                        echo json_encode($response);
                        break;
                    default:
                        echo json_encode($command);
                        break;
                }

            } else {
                echo json_encode($input);
            }

        } else {
            echo json_encode($input);
        }
        break;
    case 'deleteconversation':  
            
            $DB->delete_records("chat_sessions", array('userid' => $userid, 'courseid' => $courseid));
            echo json_encode(true);
            break;
    default:
        break;
}