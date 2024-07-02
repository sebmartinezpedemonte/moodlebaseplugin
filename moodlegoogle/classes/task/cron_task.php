<?php
namespace mod_moodlegoogle\task;

use MoodleGoogleExe;

global $CFG;

require_once $CFG->dirroot . "/mod/moodlegoogle/lib.php";
require_once $CFG->dirroot . "/mod/moodlegoogle/uploader/MoodleGoogleExe.php";

class cron_task extends \core\task\scheduled_task {
	public function get_name() {
		return get_string("moodlegooglecron_task", "mod_moodlegoogle");
	}

	public function execute() {
		echo "\nλλλλλλλλλλλλλλλλλλλBCLλλλλλλλλλλλλλλλλλλλ\nAl final de este log puede encontrar un resumen de errores en caso de existir.\nλλλλλλλλλλλλλλλλλλλBCLλλλλλλλλλλλλλλλλλλλ\n";
		echo "\n****************************\nSe inicia la ejecución de moodlegoogle.\n****************************\n";
		global $DB;
		$habilitacionGlobal = $DB->get_record_sql(
			"SELECT value FROM mdl_config_plugins WHERE plugin = 'mod_moodlegoogle' AND name = 'enable'"
		)->value;
		if ($habilitacionGlobal == "custom") {
			// custom = Habilitado || normal = deshabilitado.
			$moodleGoogleExe = new MoodleGoogleExe($DB);
			$moodleGoogleExe->exportarMateriasExportables();
		}
		echo "\n****************************\nSe finaliza la ejecución de moodlegoogle.\n****************************\n";
	}	
}