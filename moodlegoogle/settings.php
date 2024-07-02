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
 * Plugin administration pages are defined here.
 *
 * @package     mod_moodlegoogle
 * @category    admin
 * @copyright   2024 Your Name <you@example.com>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined("MOODLE_INTERNAL") || die();

if ($ADMIN->fulltree) {
	$settings->add(
		new admin_setting_heading(
			"mod_moodlegoogle/frequency",
			get_string("frequency", "mod_moodlegoogle"),
			get_string("frequency_desc", "mod_moodlegoogle")
		)
	);

	$settings->add(
		new admin_setting_heading(
			"chat_method_heading",
			get_string("generalconfig", "mod_moodlegoogle"),
			get_string("explaingeneralconfig", "mod_moodlegoogle")
		)
	);

	$name = new lang_string("plugin_available_name", "mod_moodlegoogle");
	$description = new lang_string(
		"plugin_available_description",
		"mod_moodlegoogle"
	);

	$options = [
		"custom" => get_string("Habilitado_si", "mod_moodlegoogle"),
		"normal" => get_string("Habilitado_no", "mod_moodlegoogle")
	];

	$settings->add(
		new admin_setting_configselect(
			"mod_moodlegoogle/enable",
			get_string("Habilitado_titulo", "mod_moodlegoogle"),
			get_string("Habilitado_desc", "mod_moodlegoogle"),
			"custom",
			$options
		)
	);

	$settings->add(
		new admin_setting_configtext(
			"mod_moodlegoogle/gdrive_folder_id",
			get_string("googlekey", "mod_moodlegoogle"),
			get_string("googletext", "mod_moodlegoogle"),
			null
		)
	);

	$settings->add(
		new admin_setting_configtext(
			"mod_moodlegoogle/google_json_key",
			get_string("upload_file", "mod_moodlegoogle"),
			get_string("upload_file_desc", "mod_moodlegoogle"),
			"",
			PARAM_TEXT
		)
	);

	$settings->add(
		new admin_setting_configtext(
			"mod_moodlegoogle/group_filter_prefix",
			get_string("group", "mod_moodlegoogle"),
			get_string("group_desc", "mod_moodlegoogle"),
			"",
			PARAM_TEXT
		)
	);

	$settings->add(
		new admin_setting_configtext(
			"mod_moodlegoogle/export_delay",
			get_string("export_delay_name", "mod_moodlegoogle"),
			get_string("export_delay_desc", "mod_moodlegoogle"),
			10,
			PARAM_INT
		)
	);

	defined("MOODLE_INTERNAL") || die();
}