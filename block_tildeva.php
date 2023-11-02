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


class block_tildeva extends block_base
{


    /**
     * init function
     * @return void
     */
    public function init()
    {
        $this->title = get_string('pluginname', 'block_tildeva');
    }

    /**
     * applicable_formats function
     * @return array
     */
    public function applicable_formats()
    {
        return array('course-view' => true, 'site' => true);
    }

    /**
     * has_config function
     * @return bool
     */
    public function has_config()
    {
        return true;
    }
    public function instance_allow_config() {
        return true;
    }
   

    /**
     * instance_allow_multiple function
     * @return bool
     */
    public function instance_allow_multiple()
    {
        return false;
    }

    /**
     * instance_can_be_hidden function
     * @return bool
     */
    public function instance_can_be_hidden()
    {
        // By default, instances can be hidden by the user.
        $hideblock = true;
        // If config 'hideblock' is disabled.
        if ((get_config('block_tildeva', 'hideblock')) == '0') {
            // Set value to false, so instance cannot be hidden.
            $hideblock = false;
        }
        return $hideblock;
    }



    /**
     * get_content function
     * @return string
     */
    public function get_content()
    {
        global $CFG, $PAGE, $DB, $COURSE, $USER;

        $course_ids = get_config('block_tildeva', 'course_id');
        $course_ids_array = explode(',', $course_ids);

        // Check if the block is enabled and should be displayed in the current course
        if (!in_array($COURSE->id, $course_ids_array)) {
            return null;
        }
        if (!isloggedin() ) {
           return null;
        }
        if ($this->content !== null) {
            return $this->content;
        }

        if (empty($this->config)) {
            $this->config = new stdClass();
        }

        if (!empty($this->config->bot_style)) {
            $bot_style =$this->config->bot_style;
        }
        else{
            $bot_style =  get_config('block_tildeva', 'bot_style_default');
        }

     

        $PAGE->requires->js(new moodle_url($CFG->wwwroot . '/blocks/tildeva/assets/webchat.js?v=2023110202'), true);
        $module = array(
            'name' => 'block_tildeva_ajax',
            // Chat gui's are not real plugins, we have to break the naming standards for JS modules here.
            'fullpath' => '/blocks/tildeva/assets/module.js',
            'requires' => array(
                'base',
                'dom',
                'event',
                'event-mouseenter',
                'event-key',
                'json-parse',
                'io',
                'overlay',
                'yui2-resize',
                'yui2-layout',
                'yui2-menu'
            )
        );
        $modulecfg = array(
            'chaturl' => $CFG->wwwroot . '/blocks/tildeva/gui_ajax/index.php?',
            'boturl' => 'https://va.tilde.com/dl/directline/aHR0cDovL3Byb2RrOHNib3RjYXZhMA==',
            'userid' => $USER->id,
            'courseid' => $COURSE->id,
            'bot_style' => $bot_style
        );


        $PAGE->requires->js_init_call('M.block_tildeva_ajax.init', array($modulecfg), false, $module);

        // Render the Web Chat container
        $html = html_writer::start_tag('div', ['class' => 'block_tildeva']);

        $html .= html_writer::end_tag('div');


        // Add a script to initialize the Web Chat
        $js = "
         const chat = document.createElement('div');
chat.innerHTML = `<div class=\"wchat__maximize-wrapper\" id=\"wchat__maximize-wrapper\" >
<div class=\"wchat__maximize-header\">
    <div class=\"wchat__title\">" . get_string('chattitle', 'block_tildeva') . "</div>
    <div class=\"wchat__avatar\"></div>
</div>
</div>
<div class=\"wchat__container\" id=\"wchat__container\" hidden>
<div class=\"wchat__header\">
    <div class=\"wchat__title\">" . get_string('chattitle', 'block_tildeva') . "</div>
    <div class=\"wchat__minimize\" id=\"wchat__minimize\">
        <svg>
            <path style=\"fill: #FFF\" d=\"M20,14H4V10H20\" />
        </svg>
    </div>
    <div class=\"wchat__close\" id=\"wchat__close\">
    <svg><path d=\"M13.46,12L19,17.54V19H17.54L12,13.46L6.46,19H5V17.54L10.54,12L5,6.46V5H6.46L12,10.54L17.54,5H19V6.46L13.46,12Z\" style=\"fill: #fff;\"></path></svg>
    </div>


</div>
<div id=\"webchat\" role=\"main\"></div>
<div class=\"wchat__footer\">
    <p class=\"powered-by\"><span>Veidots platformā </span><a href=\"https://tilde.ai\" target=\"_blank\">
            tilde.ai</a></p>
    </div>
</div>`;

document.body.appendChild(chat);
         ";
        $html = html_writer::script($js);


        // Return the HTML content for the block
        $this->content = new stdClass;
        $this->content->text = $html;
        return $this->content;
    }


    /**
     * Return the plugin config settings for external functions.
     *
     * @return stdClass the configs for both the block instance and plugin
     * @since Moodle 3.8
     */
    public function get_config_for_external()
    {

        // Return all settings for all users since it is safe (no private keys, etc..).
        $instanceconfigs = !empty($this->config) ? $this->config : new stdClass();
        $pluginconfigs = get_config('block_tildeva');

        return (object) [
            'instance' => $instanceconfigs,
            'plugin' => $pluginconfigs,
        ];
    }

    public function instance_config_save($data, $nolongerused = false) {
        

        // Call parent instance_config_save to handle the rest
        parent::instance_config_save($data);
    }


}