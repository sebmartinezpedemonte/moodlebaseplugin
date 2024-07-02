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
 * Display information about all the mod_moodlegoogle modules in the requested course.
 *
 * @package     mod_moodlegoogle
 * @copyright   2024 Your Name <you@example.com>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__.'/../../config.php');
require_once(__DIR__.'/lib.php');

$id = required_param('id', PARAM_INT);

$course = $DB->get_record('course', array('id' => $id), '*', MUST_EXIST);
require_course_login($course);

$coursecontext = context_course::instance($course->id);

$PAGE->set_url('/mod/moodlegoogle/index.php', array('id' => $id));
$PAGE->set_title(format_string($course->fullname));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->set_context($coursecontext);

echo $OUTPUT->header();

$modulenameplural = get_string('modulenameplural', 'mod_moodlegoogle');
echo $OUTPUT->heading($modulenameplural);

$moodlegoogles = get_all_instances_in_course('moodlegoogle', $course);

if (empty($moodlegoogles)) {
    notice(get_string('no$moodlegoogleinstances', 'mod_moodlegoogle'), new moodle_url('/course/view.php', array('id' => $course->id)));
}

$table = new html_table();
$table->attributes['class'] = 'generaltable mod_index';

if ($course->format == 'weeks') {
    $table->head  = array(get_string('week'), get_string('name'));
    $table->align = array('center', 'left');
} else if ($course->format == 'topics') {
    $table->head  = array(get_string('topic'), get_string('name'));
    $table->align = array('center', 'left', 'left', 'left');
} else {
    $table->head  = array(get_string('name'));
    $table->align = array('left', 'left', 'left');
}

foreach ($moodlegoogles as $moodlegoogle) {
    if (!$moodlegoogle->visible) {
        $link = html_writer::link(
            new moodle_url('/mod/moodlegoogle/view.php', array('id' => $moodlegoogle->coursemodule)),
            format_string($moodlegoogle->name, true),
            array('class' => 'dimmed'));
    } else {
        $link = html_writer::link(
            new moodle_url('/mod/moodlegoogle/view.php', array('id' => $moodlegoogle->coursemodule)),
            format_string($moodlegoogle->name, true));
    }

    if ($course->format == 'weeks' || $course->format == 'topics') {
        $table->data[] = array($moodlegoogle->section, $link);
    } else {
        $table->data[] = array($link);
    }
}
echo html_writer::table($table);
echo $OUTPUT->footer();