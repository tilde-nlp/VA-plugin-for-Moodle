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

defined('MOODLE_INTERNAL') || die();

if ($ADMIN->fulltree) {
    // Add block settings

    // Add course ID setting
    $settings->add(new admin_setting_configtext(
        'block_tildeva/course_id',
        get_string('courseid', 'block_tildeva'),
        get_string('courseid_desc', 'block_tildeva'),
        '0',
        PARAM_TEXT  
    ));

    // Add default style settings 
    $settings->add(new admin_setting_configtextarea(
        'block_tildeva/bot_style_default',
        get_string('bot_style', 'block_tildeva'),
        get_string('bot_style_desc', 'block_tildeva'),
        "{
            fontSizeSmall: '70%',
            botAvatarImage: 'https://va.tilde.com/api/prodk8sbotcava0/media/staging/avatar.jpg',
            botAvatarBackgroundColor: 'transparent',
            botAvatarInitials: 'VA',
            hideUploadButton: true,
            backgroundColor: '#fff',
            sendBoxBackground: '#C9DC50',
            sendBoxBorderTop: '1px solid #CCC',
            sendBoxPlaceholderColor: '#605e5c',
            sendBoxTextColor: '#606060',
            sendBoxButtonColorOnActive: '#C9DC50',
            sendBoxButtonColorOnFocus: '#C9DC50',
            sendBoxButtonColorOnHover: '#C9DC50',
            sendBoxButtonShadeColor: 'transparent',
            sendBoxButtonShadeColorOnActive: 'transparent',
            sendBoxButtonShadeColorOnDisabled: 'transparent',
            sendBoxButtonShadeColorOnFocus: 'transparent',
            sendBoxButtonShadeColorOnHover: 'transparent',
            transcriptActivityVisualKeyboardIndicatorColor: 'transparent',
            bubbleBackground: '#eef2f8',
            bubbleTextColor: '#606060',
            markdownRespectCRLF: true,
            bubbleBorderWidth: 0,
            bubbleFromUserBorderWidth: 0,
            bubbleFromUserBackground: '#C9DC50',
            bubbleFromUserTextColor: '#ffffff',
            paddingRegular: '15px',
            subtle: '#606060',
            paddingRegular: 10,
            paddingWide: 15,
            sendBoxHeight: 46,
            typingAnimationBackgroundImage: 'url(https://va.tilde.com/api/prodk8sbotcava0/media/staging/typing.gif)',
            typingAnimationWidth: 180,
            bubbleMinHeight: 30,
            suggestedActionBackground: 'transparent',
            suggestedActionBorder: 'undefined', 
            suggestedActionBorderColor: '#606060', 
            suggestedActionBorderStyle: 'solid',
            suggestedActionBorderWidth: 1,
            suggestedActionBorderRadius: 0,
            suggestedActionImageHeight: 20,
            suggestedActionTextColor: '#606060',
            suggestedActionDisabledBackground: 'undefined', 
            suggestedActionHeight: 40,
            bubbleMaxWidth: '80%',
            bubbleBorderRadius: '0px',
            bubbleFromUserBorderRadius: '0px'
        }",
        PARAM_TEXT  
    ));
     
}