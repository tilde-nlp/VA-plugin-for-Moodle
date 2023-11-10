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

$courseContext = \context_course::instance($courseid);

// Create a new page instance
$PAGE->set_context($courseContext);

ob_start();
header('Expires: Sun, 28 Dec 1997 09:32:45 GMT');
header('Last-Modified: ' . gmdate('D, d M Y H:i:s') . ' GMT');
header('Cache-Control: no-cache, must-revalidate');
header('Pragma: no-cache');
header('Content-Type: text/html; charset=utf-8');



// Get the user's roles in the course
$userRoles = get_user_roles($courseContext, $userId, false);

// Check if the user has the 'teacher' or 'editingteacher' role
$hasTeacherRole = false;

foreach ($userRoles as $role) {
    $roleShortname = $role->shortname;
    
    if ($roleShortname === 'teacher') {
        $hasTeacherRole = true;
    } elseif ($roleShortname === 'editingteacher') {
        $hasTeacherRole = true;
    }
}

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
                } else if (strncmp($command, "feedback", 8) === 0) {
                    $params = explode("|", $command);
                    if (count($params) == 3) {
                        // Load the feedback activity
                        $feedback = $DB->get_record('feedback', array('name' => $params[1], 'course' => $courseid));

                        if ($feedback) {
                            $feedbackItem = $DB->get_record('feedback_item', array('feedback' => $feedback->id));

                            $time = time();

                            // Insert new submission
                            $submissionData = array(
                                'feedback' => $feedback->id,
                                'anonymous_response' => 1,
                                'anonymous' => 1,
                                'course' => $feedback->course,
                                'timecompleted' => $time,
                                'timemodified' => $time,
                                'userid' => $userid
                            );
                            $submissionId = $DB->insert_record('feedback_completed', (object) $submissionData);

                            if ($submissionId) {
                                // Insert feedback response
                                $responseData = array(
                                    'item' => $feedbackItem->id,
                                    'completed' => $submissionId,
                                    'course_id' => $feedback->course,
                                    'anonymous_response' => 1,
                                    'anonymous' => 1,
                                    'timecreated' => $time,
                                    'timemodified' => $time,
                                    'value' => $params[2]
                                );
                                $responseId = $DB->insert_record('feedback_value', (object) $responseData);

                                if ($responseId) {
                                    echo json_encode("Feedback posted successfully.");
                                } else {
                                    echo json_encode("Error adding feedback response.");
                                }
                            } else {
                                echo json_encode("Error creating feedback submission.");
                            }
                        } else {
                            echo json_encode("Feedback activity not found.");
                        }
                    }
                } else if (strncmp($command, "getpostbyname", 13) === 0) {
                    $params = explode("|", $command);
                    if (count($params) == 2) {
                        $sql = "SELECT p.message AS post_body, d.id AS discussion_id, p.id AS post_id
                                FROM {forum_discussions} d
                                LEFT JOIN {forum_posts} p ON d.id = p.discussion
                                WHERE (d.name = :dname OR p.subject = :pname) AND d.course = :course
                                LIMIT 1";

                        $params = array('dname' => $params[1], 'pname' => $params[1], 'course' => $courseid);

                        $result = $DB->get_records_sql($sql, $params);

                        if ($result) {
                            echo json_encode($result);
                        } else {
                            echo json_encode("Post not found.");
                        }
                    } else {
                        echo json_encode("Post not found.");
                    }
                } else if (strncmp($command, "sendmsg", 7) === 0) {
                    $params = explode("|", $command);
                    if (count($params) == 3) {
                        $userfrom = \core_user::get_user($userid);
                        $userto = \core_user::get_user($params[1]);
                        $message = $params[2];
                        $format = FORMAT_HTML;

                        $eventdata = new \core\message\message();
                        $eventdata->courseid = $courseid;
                        $eventdata->component = 'moodle';
                        $eventdata->name = 'instantmessage';
                        $eventdata->userfrom = $userfrom;
                        $eventdata->userto = $userto;

                        //using string manager directly so that strings in the message will be in the message recipients language rather than the senders
                        $eventdata->subject = get_string_manager()->get_string('unreadnewmessage', 'message', fullname($userfrom), $userto->lang);

                        if ($format == FORMAT_HTML) {
                            $eventdata->fullmessagehtml = $message;
                            //some message processors may revert to sending plain text even if html is supplied
                            //so we keep both plain and html versions if we're intending to send html
                            $eventdata->fullmessage = html_to_text($eventdata->fullmessagehtml);
                        } else {
                            $eventdata->fullmessage = $message;
                            $eventdata->fullmessagehtml = '';
                        }

                        $eventdata->fullmessageformat = $format;
                        $eventdata->smallmessage = $message; //store the message unfiltered. Clean up on output.
                        $eventdata->timecreated = time();
                        $eventdata->notification = 0;
                        // User image.
                        $userpicture = new user_picture($userfrom);
                        $userpicture->size = 1; // Use f1 size.
                        $userpicture->includetoken = $userto->id; // Generate an out-of-session token for the user receiving the message.
                        $eventdata->customdata = [
                            'notificationiconurl' => $userpicture->get_url($PAGE)->out(false),
                            'actionbuttons' => [
                                'send' => get_string_manager()->get_string('send', 'message', null, $eventdata->userto->lang),
                            ],
                            'placeholders' => [
                                    'send' => get_string_manager()->get_string('writeamessage', 'message', null, $eventdata->userto->lang),
                                ],
                        ];
                        $messageId = message_send($eventdata);


                        // // Check if the message was sent successfully
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
                           
                            // Iterate through the roles and extract role names
                            $roleNames = array();
                            foreach ($userRoles as $role) {
                                $roleNames[] = $role->shortname;
                            }

                            $response['userinfo']['userroles'] = implode(", ", $roleNames);
                            echo json_encode($response);
                            break;
                        case 'getteacherinfo':
                            $teacherRoleId = $DB->get_field('role', 'id', array('shortname' => 'teacher'));
                            $editingTeacherRoleId = $DB->get_field('role', 'id', array('shortname' => 'editingteacher'));

                            // Get the list of users with both teacher and editingteacher roles in the current course
                            $teachers = array_merge(
                                get_role_users($teacherRoleId, $courseContext),
                                get_role_users($editingTeacherRoleId, $courseContext)
                            );
                            echo json_encode($teachers);
                            break;
                        case 'getcoursesections':
                            $params = array('course' => $courseid);

                            $sql = "SELECT name, section FROM {course_sections} WHERE course = :course ORDER BY id ASC";
                            $sections = $DB->get_records_sql($sql, $params);
                            echo json_encode($sections);
                            break;
                        case 'getquizzes':
                            $currentCourse = get_course($courseid);
                            $quizzes = get_all_instances_in_course('quiz', $currentCourse, $userid);
                            echo json_encode($quizzes);
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
                        case 'getgroups':
                            if($hasTeacherRole) {
                                $groups = groups_get_all_groups($courseid);                                
                                echo json_encode($groups);
                            }
                            else{
                                header('HTTP/1.1 405 Method Not Allowed');
                                exit('Method Not Allowed');
                            }
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
                        case 'getversion':
                           

                        
                            $response['moodleversion'] = $CFG->version;
                            $response['pluginversion'] = get_config('block_tildeva')->version;
                            echo json_encode($response);
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