<?php
//Busca materias Exportables y al encontrar corre su consturccion (BuilderCSV) y al finalizar la misma, sube el archivo (UploaderCSV)

require "classes/BuilderCSV.php";
require "classes/UploaderCSV.php";
require "classes/IndiceCSV.php";

use mod_moodlegoogle\uploader\UploaderCSV;
use mod_moodlegoogle\uploader\BuilderCSV;
use mod_moodlegoogle\uploader\IndiceCSV;

class MoodleGoogleExe {
	private static $CARPETA_EXPORT = __DIR__ . DIRECTORY_SEPARATOR . "exports" . DIRECTORY_SEPARATOR; 
	private static $builder;
	private static $uploader;
	private static $indice;
	private $DB;
	private $categoriesData;
	private $falloMensajes = [];
	private $inicio;
	public function __construct($DB) {
		$this->DB = $DB;
		self::$builder = new BuilderCSV(self::$CARPETA_EXPORT, $this->DB);
		self::$uploader = new UploaderCSV(self::$CARPETA_EXPORT, $this->DB);
		self::$indice = new IndiceCSV(self::$CARPETA_EXPORT, $this->DB);
	}
	public function exportarMateriasExportables() {		
		$this->inicio = time();
		echo "Obteniendo categorias \n";
		$this->getSetCategories();
		echo "Iniciando exportacion de materias exportables \n";
		$materiasExportables = $this->getMateriasExportables();
		$this->verificarCarpetaExport();
		try {
			$this->validarKey();
		} catch (Exception $e) {
			$this->informarError("Error en la validacion de la key \n". $e->getMessage());
			echo "Error en la validacion de la key \n";
			echo $e->getMessage();
		}
		//cantidad de materias
		if ($materiasExportables) {
			// Si hay materias para exportar...
			foreach ($materiasExportables as $materia) {
				$courseIdActual = $materia->courseid;
				$courseNameActual = $this->limpiarString($materia->fullname);
				$courseCategoryIdActual = $materia->category;
				$contador = 1;
				$cantidad = count($materiasExportables);

				echo "Exportando materia id:" .$courseIdActual ."-". $courseNameActual . "\n";
				echo "$contador de $cantidad \n";
				try { // Intenta exportar la materia
				$this->exportarNotasDeMateria($courseIdActual, $courseNameActual, $courseCategoryIdActual);
				} catch (Exception $e) { // Si falla, muestra el error
					$this->informarError("Error en la exportacion de la materia: " .$courseIdActual ."-". $courseNameActual . "\n". $e->getMessage());
					echo "Error en la exportacion de la materia: " .$courseIdActual ."-". $courseNameActual . "\n";
					echo $e->getMessage();
				}
				$contador++;
			}
		} else {
			echo "No hay materias exportables \n";
		}
		echo "Iniciando generacion de indice \n";
		try {
			self::$indice->construirIndiceCSV($this->categoriesData);
		} catch (Exception $e) {
			$this->informarError("Error en la generacion de indice \n". $e->getMessage());			
			echo "Error en la generacion de indice \n";
			echo $e->getMessage();
		}
		$this->errorDetector();
	}

	private function limpiarString($string) {
		// Limpia el string de caracteres especiales
		$string = preg_replace('/\s+/', ' ', $string);
		$string = str_replace(['á', 'é', 'í', 'ó', 'ú', 'Á', 'É', 'Í', 'Ó', 'Ú'], ['a', 'e', 'i', 'o', 'u', 'A', 'E', 'I', 'O', 'U'], $string);
		//$string = str_replace(' ', '_', $string);
		$string = preg_replace('/[^A-Za-z0-9_\s]/', '-', $string);
        $string = preg_replace('/-+/', '-', $string);
		return $string;
	}

	private function exportarNotasDeMateria($idMateria, $nombreMateria, $idCategoria) {
		$exportTime = time(); 
		echo "Epoch Unix Timestamp de Exportacion = $exportTime \n";
		echo "Inicio Construccion csv para materia: $idMateria \n";
		$nombreArchivo = self::$builder->construirCSV($idMateria, $nombreMateria);
		echo "Fin Construccion csv \n";
		echo "Obtener ruta Export \n";
		$rutaExport = $this->obtenerRutaExport($idCategoria, $nombreMateria);
		echo "Fin obtener ruta Export \n";
		echo "Subir csv \n";
		$inicioSubida = time();
		self::$uploader->uploadCSV($nombreArchivo, $rutaExport, $idMateria, $exportTime);
		echo "Fin subir csv para materia $idMateria \n";
		echo "La subida del archivo se demoró: " . (time() - $inicioSubida) . " segundos.\n";
		echo "Fin Exportacion de notas de materia: $idMateria \n";
		echo "El export completo de la materia se demoró: " . (time() - $exportTime) . " segundos. \n";
	}

	private function getUnixTime() {
		// Devuelve la fecha en formato UnixTime
		return time();
	}
	private function verificarCarpetaExport() {
		echo "Verificando carpeta export... \n";
		if (!is_dir(self::$CARPETA_EXPORT)) {
			echo "Carpeta export no existe, creando... \n";
			$this->crearCarpetaExport();
			echo "Carpeta export creada \n";
		} else {
			echo "Carpeta export existe \n";
		}
	}
	private function crearCarpetaExport() {
		echo "Creando carpeta export... \n";
		mkdir(self::$CARPETA_EXPORT, 0777, true); // Necesario para subir  0777 es el permiso de lectura y escritura, true es para que cree los directorios padres si no existen.
		echo "Carpeta export creada \n";
	}

	private function getMateriasExportables() {
		echo "Obteniendo materias exportables... \n";
		$maxCantidadMateriasExportables = $this->calcularMaximasMateriasExportables();
		$maxTime = time() - $this->getExportDelay();
		echo "Maximo momento de ultima carga de nota (segun delay) Epoch Unix Timestamp = $maxTime \n";
		$this->inicializarMateriasNuevas();
		return $this->DB->get_records_sql("SELECT gi.courseid as courseid, c.fullname as fullname, c.category as category, MAX(gg.timemodified) as max_timemodified FROM mdl_grade_items gi INNER JOIN mdl_grade_grades gg ON gi.id = gg.itemid INNER JOIN mdl_course c ON gi.courseid = c.id INNER JOIN( SELECT * FROM mdl_moodlegoogle m1 WHERE m1.timemodified = ( SELECT MAX(m2.timemodified) FROM mdl_moodlegoogle m2 WHERE m1.course = m2.course)) mg ON gi.courseid = mg.course WHERE gi.itemmodule IS NOT NULL AND gg.timemodified < $maxTime AND gg.timemodified > mg.time_last_export AND mg.enable = 1 GROUP BY gi.courseid ORDER BY max_timemodified ASC LIMIT $maxCantidadMateriasExportables");
	}

	private function calcularMaximasMateriasExportables() {
		$cantidadMaximaMateriasExportables = 100;
		$maximaDemoraSegundos = 20;
		$cantidad = 1;
		echo "Calculando cantidad de materias exportables... \n";
		$cronData = $this->DB->get_record_sql("SELECT lastruntime, nextruntime FROM mdl_task_scheduled WHERE component = 'mod_moodlegoogle' and disabled = 0");
		if ($this->inicio - $cronData->nextruntime < 30) {
			// Si pasaron menos de 30 segundos desde el ultimo momento en el que en teoria se debia ejecutar este proceso. Se permite esta diferencia por si algun otro proceso de la lista de cron demora la ejecucion de este proceso.
			echo "Cron ejecutado en tiempo esperado \n";
			$cantidad = round(($cronData->nextruntime - $cronData->lastruntime) / $maximaDemoraSegundos);
			echo "Cantidad de materias exportables calculadas: $cantidad \n";
			if ($cantidad > $cantidadMaximaMateriasExportables) {
				echo "Cantidad de materias exportables excede el maximo permitido, se exportaran $cantidadMaximaMateriasExportables materias \n";
				$cantidad = $cantidadMaximaMateriasExportables;
			} elseif ($cantidad < 1) {
				echo "Cantidad de materias exportables menor a 1 \n";
				echo "Posible primer exportacion o error en la ultima exportacion automatica. Se exportara 1 materia \n";
				$cantidad = 1;
			}
		} else {
			echo "No se ejecuto el cron en el tiempo esperado, se exportara 1 materia \n";
		}
		echo "Cantidad de materias exportables: $cantidad \n";
		return $cantidad;
	}

	private function getExportDelay() {
		echo "Obteniendo export delay... \n";
		$delay = $this->DB->get_record_sql("SELECT value FROM mdl_config_plugins WHERE plugin = 'mod_moodlegoogle' AND name = 'export_delay'")->value * 60; //devuelve en segundos
		echo "Export delay = $delay \n";
		return $delay;
	}

	private function inicializarMateriasNuevas() {
		echo "Inicializando materias nuevas... \n";
		$materiasConEvaluablesNuevas = $this->DB->get_records_sql("SELECT courseid as course, 'moodlegoogle' as name, 1 as enable FROM mdl_grade_items WHERE itemmodule IS NOT NULL AND courseid NOT IN (SELECT course FROM mdl_moodlegoogle GROUP BY course) GROUP BY course");
		if ($materiasConEvaluablesNuevas) {
			// Si hay materias nuevas...
			echo "Se encontraron " . count($materiasConEvaluablesNuevas) . " materias sin inicializar \n";
			$this->DB->insert_records("moodlegoogle", $materiasConEvaluablesNuevas); // Inserta las materias nuevas en la tabla mdl_moodlegoogle (el prefijo lo toma automaticamente moodle)
			$this->insertarEnSeccion0($materiasConEvaluablesNuevas);
		}
		echo "Actualizando timemodified de materias nuevas... \n";
		$this->DB->execute("UPDATE mdl_moodlegoogle SET timemodified = timecreated WHERE timecreated != 0 AND (timemodified IS NULL OR timemodified = 0)"); // Actualiza el campo timemodified de todas las materias nuevas que no tengan valor en timemodified para utilizar esa fecha como referencia para la exportacion de notas.
		echo "Fin actualizacion de timemodified de materias nuevas \n";
		echo "Fin inicializacion de materias nuevas \n";
	}
	private function insertarEnSeccion0($materiasConEvaluablesNuevas) {
		echo "Iniciando insercion en seccion 0... \n";
		$module = $this->DB->get_record_sql("SELECT id FROM mdl_modules WHERE name = 'moodlegoogle'")->id;
		$section = 0;
		$added = time();
		$visible = 0;
		$visibleold = 0;
		$completion = 0;
		$arrayCourseModules = [];
		echo "Creando objetos CourseModules... \n";
		foreach ($materiasConEvaluablesNuevas as $materia) {
			$courseId = $materia->course;
			echo "Creando objeto de la materia: $courseId \n";
			$objetosCourseModules = [
				"course" => $courseId,
				"module" => $module,
				"instance" => $this->DB->get_record_sql("SELECT id FROM mdl_moodlegoogle WHERE course = $courseId AND timemodified = (SELECT MAX(timemodified) FROM mdl_moodlegoogle WHERE course = $courseId)")->id,
				"section" => $this->DB->get_record_sql("SELECT id FROM mdl_course_sections WHERE course = $courseId AND section = $section")->id,
				"idnumber" => "",
				"added" => $added,
				"visible" => $visible,
				"visibleold" => $visibleold,
				"completion" => $completion,
				"lang" => ""
			];
			array_push($arrayCourseModules, $objetosCourseModules);
		}
		echo "Objetos CourseModules creados \n";
		echo "Insertando en seccion 0... \n";
		$this->DB->insert_records("course_modules", $arrayCourseModules);
		echo "Fin insertar en seccion 0 \n";
	}

	private function getSetCategories() { // Obtiene toda la data de las categorias de moodle
		echo "Obteniendo categorias... \n";
		$this->categoriesData = $this->DB->get_records_sql("SELECT id, name, path FROM mdl_course_categories");
		if (empty($this->categoriesData)) {
			throw new Exception("No se pudieron obtener las categorias de moodle \n");
		}
		echo "Categorias obtenidas \n";
	}

	// Funcion para obtener un array ordenado con las categorias de la materia para luego armar la ruta de exportacion
	// Ej: Instituto Ort / Analista en Sistemas / Programacion / TP3
	private function obtenerRutaExport($idCategory, $nombreMateria){
		echo "Obteniendo ruta export... \n";
		$dir = null;
		$categoryPath = $this->getCategoryPath($idCategory);
		$categories = explode('/', $categoryPath);
		$dir = [];
		foreach ($categories as $categoryId) {
			if (!empty($categoryId)) {// Normalemnte la primera es vacia ya que el path suele iniciar con "/"
				$categoryName = $this->getCategoryName($categoryId);
				array_push($dir, $categoryName);
			}			
		}
		array_push($dir,$nombreMateria);
		echo "Ruta export: " . self::$CARPETA_EXPORT . implode("\\", $dir) . "\n";
		return $dir; // Devuelve la ruta absoluta
	}

	private function getCategoryPath($idCategory){//Recibe un id de la categoria y busca su respectivo path en categoriesData.
		echo "Obteniendo path de categoria... \n";
		// Buscar Categoria y retornar path
		return $this->buscarCategoria($idCategory)->path;
	}

	private function getCategoryName($idCategory) { // Busca el nombre de la categoria en $categoriesData
		echo "Obteniendo nombre de categoria con id: $idCategory... \n";
		// Buscar Categoria y retornar nombre
		return $this->limpiarString($this->buscarCategoria($idCategory)->name);
	}

	private function buscarCategoria($idCategory){
		foreach ($this->categoriesData as $category) {
			if ($idCategory == $category->id){
				return $category;
			}
		}
		throw new Exception ("No se encontro la categoria con id: $idCategory \n");
	}

	private function errorDetector(){
		if (!empty($this->falloMensajes)) {
			$errorN = 1;
			echo "\nλλλλλλλλλλλλλλλλλλλBCLλλλλλλλλλλλλλλλλλλλ \n INICIO DE RESUMEN DE ERRORES \n Se detectaron ". count($this->falloMensajes) ." errores en la exportacion de materias. \nλλλλλλλλλλλλλλλλλλλBCLλλλλλλλλλλλλλλλλλλλ \n\n";
			foreach ($this->falloMensajes as $falloMensaje){
				echo "Error $errorN: \n";
				echo $falloMensaje . "\n\n";
				$errorN = $errorN + 1;
			}
			echo "\nλλλλλλλλλλλλλλλλλλλBCLλλλλλλλλλλλλλλλλλλλ \n FIN DE RESUMEN DE ERRORES \nλλλλλλλλλλλλλλλλλλλBCLλλλλλλλλλλλλλλλλλλλ \n\n";
			throw new Exception("Se detectaron errores en la exportacion de materias \n");
		}
		echo"Exportacion de materias finalizada sin errores \n";
	}

	private function informarError($mensaje){
		array_push( $this->falloMensajes, $mensaje);
	}
	private function validarKey() {
		echo "Validando key... \n";
		$keyVigente = false;
		if(file_exists(__DIR__ . DIRECTORY_SEPARATOR . "classes" . DIRECTORY_SEPARATOR . "key.json")) {
			echo "Archivo Key Encontrado \n";
			$keyVigente = $this->DB->get_record_sql("SELECT CASE WHEN( SELECT MAX(timeModified) FROM mdl_config_log WHERE plugin = 'mod_moodlegoogle' AND name = 'google_json_key') < ( SELECT MAX(timeModified) FROM mdl_config_log WHERE plugin = 'mod_moodlegoogle' AND name = 'last_google_json_key' ) THEN true ELSE false END AS valid_key")->valid_key;
			echo "Key vigente: " . ($keyVigente ? "SI" : "NO")  . "\n";
		} else {
			echo "Archivo Key no encontrado \n";
		}
		
		if (!$keyVigente){
			$this->createKey();
		}
	}
	private function createKey() {
		//Crea la key para la conexion con google // TODO consultar primero si se actualizó, sino no hacer nada.
		echo "Creando nueva key... \n";
		global $CFG;

		$key = $this->DB->get_record_sql("SELECT value FROM mdl_config_plugins WHERE plugin = 'mod_moodlegoogle' AND name = 'google_json_key'")->value;
		if ($key == "") {
			echo "No se encontro key \n";
			throw new Exception("No se encontro key");
		}
		$fp = fopen(__DIR__ . DIRECTORY_SEPARATOR . "classes" . DIRECTORY_SEPARATOR . "key.json", "w");
		fwrite($fp, $key);
		fclose($fp);
		echo "Key nueva creada \n";
		echo "Actualizando fecha de ultima key... \n";
		$this->DB->insert_record("config_log", [
			"userid" => 2,
			"timemodified" => time(),
			"plugin" => "mod_moodlegoogle",
			"name" => "last_google_json_key",
			"value" => time(),
			"oldvalue" => ""
		]);
		echo "Fecha de ultima key actualizada \n";
	}
}