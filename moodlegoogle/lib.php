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
 * Library of interface functions and constants.
 *
 * @package     mod_moodlegoogle
 * @copyright   2024 Your Name <you@example.com>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Return if the plugin supports $feature.
 *
 * @param string $feature Constant representing the feature.
 * @return true | null True if the feature is supported, null otherwise.
 */
function moodlegoogle_supports($feature) {
    switch ($feature) {
        case FEATURE_MOD_INTRO:
            return true;
        default:
            return null;
    }
}   
/**
 * Saves a new instance of the mod_moodlegoogle into the database.
 *
 * Given an object containing all the necessary data, (defined by the form
 * in mod_form.php) this function will create a new instance and return the id
 * number of the instance.
 *
 * @param object $moduleinstance An object from the form.
 * @param mod_moodlegoogle_mod_form $mform The form.
 * @return int The id of the newly inserted record.
 */
function moodlegoogle_add_instance($moduleinstance, $mform = null) {
    global $DB;

    $moduleinstance->timecreated = time();

    $id = $DB->insert_record('moodlegoogle', $moduleinstance);

    return $id;
}

/**
 * Updates an instance of the mod_moodlegoogle in the database.
 *
 * Given an object containing all the necessary data (defined in mod_form.php),
 * this function will update an existing instance with new data.
 *
 * @param object $moduleinstance An object from the form in mod_form.php.
 * @param mod_moodlegoogle_mod_form $mform The form.
 * @return bool True if successful, false otherwise.
 */
function moodlegoogle_update_instance($moduleinstance, $mform = null) {
    global $DB;

    $moduleinstance->timemodified = time();
    $moduleinstance->id = $moduleinstance->instance;

    return $DB->update_record('moodlegoogle', $moduleinstance);
}
 
/**
 * Removes an instance of the mod_moodlegoogle from the database.
 *
 * @param int $id Id of the module instance.
 * @return bool True if successful, false on failure.
 */
function moodlegoogle_delete_instance($id) {
    global $DB;

    $exists = $DB->get_record('moodlegoogle', array('id' => $id));
    if (!$exists) {
        return false;
    }
    $DB->delete_records('moodlegoogle', array('id' => $id));

    return true;
}

function mod_moodlegoogle_cron() {
    $cron_frequency = get_config('mod_moodlegoogle', 'cron_frequency');
    $custom_frequency = get_config('mod_moodlegoogle', 'custom_frequency');

    // Use $cron_frequency and $custom_frequency to control the execution of your cron task.
    // Code to perform your task

    // Create an instance of your cron task class.
    $task = new \mod_moodlegoogle\task\cron_task();
    // Execute the cron task.
    $task->execute();   
}

function moodlegooglemod_get_scheduled_tasks() {
    return [
        new mod_moodlegoogle\task\cron_task,
    ];
}