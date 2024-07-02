<?php
// This file is part of Moodle - https://moodle.org/
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
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

/**
 * The main mod_moodlegoogle configuration form.
 *
 * @package     mod_moodlegoogle
 * @copyright   2024 Your Name <you@example.com>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot.'/course/moodleform_mod.php');

/**
 * Module instance settings form.
 *
 * @package     mod_moodlegoogle
 * @copyright   2024 Your Name <you@example.com>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class mod_moodlegoogle_mod_form extends moodleform_mod {

    /**
     * Defines forms elements
     */
    public function definition() {
        global $CFG;

        $mform = $this->_form;

        $mform->addElement('header', 'general', get_string('general', 'form'));

        if (!empty($CFG->formatstringstriptags)) {
            $mform->setType('name', PARAM_TEXT);
        } else {
            $mform->setType('name', PARAM_CLEANHTML);
        }

        $mform->addRule('name', null, 'required', null, 'client');
        $mform->addRule('name', get_string('maximumchars', '', 255), 'maxlength', 255, 'client');
        
        $mform -> addElement('text', 'gdrive_folder_id', get_string('googlekey','mod_moodlegoogle'), array('size' => '64'));
        $mform-> addHelpButton('gdrive_folder_id', 'gdrive_folder_id', 'mod_moodlegoogle');       
        
        $mform -> addElement('text', 'group_filter_prefix', get_string('group_filters','mod_moodlegoogle'), array('size' => '64'));
        $mform->addHelpButton('group_filter_prefix', 'group_filters', 'mod_moodlegoogle');

        $mform-> addElement('advcheckbox', 'enable', get_string('enable_export','mod_moodlegoogle'), null, array('group' => 1), array(0, 1));      
        $mform->addElement('submit', 'submit_button', 'Guardar Cambios');
        // Add standard elements.
        $this->standard_coursemodule_elements();
    }
}