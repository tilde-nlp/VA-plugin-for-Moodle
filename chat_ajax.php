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




if (!$chatsession = $DB->get_record('chat_sessions', array('userid' => $userid, 'courseid' => $courseid))) {
    $chatsession = new stdClass();
    $chatsession->conversationid = 0;
}

if (!isloggedin() && $conversationid != $chatsession->conversationid) {
    throw new moodle_exception('notlogged', 'chat');
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
        $response['userid'] = $userid;
        $response['courseid'] = $courseid;
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
            $matchCount = preg_match('/:(.+)$/', $input, $matches);

            if ($matchCount > 0) {
                // Match found
                $command = $matches[1];
                if (strncmp($command, "db|", 3) === 0) {
                    $params = explode("|", $command);
                    if (count($params) == 3) {
                        $sql = $params[1];
                        $response[$params[2]] = $DB->get_records_sql($sql);
                        echo json_encode($response);
                    } else {
                        echo json_encode($command);
                    }
                } else if (strncmp($command, "sendnotification|", 17) === 0) {
                    $params = explode("|", $command);
                    if (count($params) == 4) {
                        $teacherid = $params[1];
                        $subject = $params[2];
                        $message = $params[3];
                        // Load the messaging API
                        require_once($CFG->dirroot . '/message/lib.php');

                        // Create the message data
                        $messageData = new \core\message\message();
                        $messageData->component = 'moodle';
                        $messageData->name = 'instantmessage';
                        $messageData->userfrom = $USER;
                        $messageData->userto = \core_user::get_user($teacherid);
                        $messageData->subject = $subject;
                        $messageData->fullmessage =  $message;
                        $messageData->fullmessageformat = FORMAT_HTML;

                        // Send the message
                        $messageId = message_send($messageData);

                        // Check if the message was sent successfully
                        if ($messageId) {
                            echo json_encode('Notification sent');
                        } else {
                            echo json_encode('Failed to send the notification.');
                        }


                    } else {
                        echo json_encode($command);
                    }
                } else {
                    switch ($command) {
                        case 'getuserinfo':
                            $response['userinfo']['firstname'] = $USER->firstname;
                            $response['userinfo']['lastname'] = $USER->lastname;
                            $response['userinfo']['email'] = $USER->email;
                            $response['userinfo']['username'] = $USER->username;
                            // Get the course context
                            $context = context_course::instance($courseid);

                            // Get the user's roles in the course
                            $userRoles = get_user_roles($context, $userid, false);

                            // Iterate through the roles and extract role names
                            $roleNames = array();
                            foreach ($userRoles as $role) {
                                $roleNames[] = $role->shortname;
                            }

                            $response['userinfo']['userroles'] = implode(", ", $roleNames);
                            echo json_encode($response);
                            break;
                        case 'getcourseinfo':
                            $currentCourse = get_course($courseid);
                            $response['courseinfo']['courseid'] = $courseid;
                            $response['courseinfo']['coursefullname'] = $currentCourse->fullname;
                            $response['courseinfo']['courseShortName'] = $currentCourse->shortname;
                            $response['courseinfo']['coursecategory'] = $currentCourse->category;
                            // Define the database tables and columns
                            $table = 'quiz';
                            $columns = 'q.id, q.name, q.intro, q.timeclose';

                            // Build the SQL query
                            $sql = "SELECT $columns
                                FROM {course_modules} cm
                                JOIN {quiz} q ON q.id = cm.instance
                                LEFT JOIN {quiz_attempts} qa ON qa.quiz = q.id
                                WHERE cm.course = :courseid
                                AND cm.module = (SELECT id FROM {modules} WHERE name = 'quiz')
                                GROUP BY q.id";

                            // Execute the query
                            $params = ['courseid' => $courseid];
                            $tests = $DB->get_records_sql($sql, $params);

                            // Display the test information
                            $i = 0;
                            foreach ($tests as $test) {
                                $testId = $test->id;
                                $testName = $test->name;
                                $testIntro = $test->intro;
                                $testDeadline = $test->timeclose;
                                $response['courseinfo']['tests'][$i]['attemptid'] = $attemptId;
                                $response['courseinfo']['tests'][$i]['testid'] = $testId;
                                $response['courseinfo']['tests'][$i]['testname'] = $testName;
                                $response['courseinfo']['tests'][$i]['testintro'] = $testIntro;
                                $response['courseinfo']['tests'][$i]['testdeadline'] = date('Y-m-d H:i:s', $testDeadline);
                                $i++;
                            }
                            echo json_encode($response);
                            break;
                        case 'gettestinfo':
                            // Define the database tables and columns
                            $table = 'quiz_attempts';
                            $columns = 'qa.id, qa.uniqueid, qa.timefinish, qa.sumgrades, q.name';

                            // Build the SQL query
                            $sql = "SELECT $columns
                                FROM {user} u
                                JOIN {user_enrolments} ue ON ue.userid = u.id
                                JOIN {enrol} e ON e.id = ue.enrolid
                                JOIN {course} c ON c.id = e.courseid
                                JOIN {quiz} q ON q.course = c.id
                                JOIN {quiz_attempts} qa ON qa.userid = u.id AND qa.quiz = q.id
                                WHERE u.id = :userid
                                ORDER BY qa.timefinish DESC";

                            // Execute the query
                            $params = ['userid' => $userid];
                            $testResults = $DB->get_records_sql($sql, $params);
                            $i = 0;
                            // Display the test results
                            foreach ($testResults as $testResult) {
                                $attemptId = $testResult->id;
                                $attemptUniqueId = $testResult->uniqueid;
                                $attemptTimeFinish = $testResult->timefinish;
                                $attemptSumGrades = $testResult->sumgrades;
                                $quizName = $testResult->name;
                                $response['testresults'][$i]['attemptid'] = $attemptId;
                                $response['testresults'][$i]['attemptuniqueid'] = $attemptUniqueId;
                                $response['testresults'][$i]['attempttimefinish'] = date('Y-m-d H:i:s', $attemptTimeFinish);
                                $response['testresults'][$i]['attemptsumgrades'] = $attemptSumGrades;
                                $response['testresults'][$i]['quizname'] = $quizName;
                                $i++;
                            }

                            echo json_encode($testResults);
                            break;
                        default:
                            echo json_encode($command);
                            break;
                    }
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