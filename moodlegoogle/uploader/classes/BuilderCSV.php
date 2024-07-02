<?php
namespace mod_moodlegoogle\uploader;
ini_set("display_errors", 1);
ini_set("display_startup_errors", 1);
error_reporting(E_ALL);
use Exception;
// La clase Builder es la encargada de generar ultimo export csv para una materia.

class BuilderCSV {
	private static $extension = ".csv"; // Extension de archivo
	private $directorioExport; // Directorio interno local de exportacion
	private static $studentId = 5; // ID del rol de estudiante en Moodle. select id from mdl_role where mdl_role.shortname like "student";
	private $idMateria = 0; // ID de la materia
	private $nombreMateria; // Nombre de la materia
	private $gruposPrefijo = []; // Grupos prefijo de la materia
	private $filtroGrupos = ""; // Base de filtro de grupos
	private $selectInfoData = ""; // Select de campos personalizados
	private $fromInfoData = ""; // From de campos personalizados
	private $selectEvaluables = ""; // Select de evaluables
	private $fromEvaluables = ""; // From de evaluables
	private $DB;

	public function __construct($carpetaExportable, $DB) {
		$this->directorioExport = $carpetaExportable;
		$this->DB = $DB;
	}

	private function setMateria($id, $nombre) {
		// Setear materia
		if (!is_numeric($id) || $id < 0 || empty($nombre) || !is_string($nombre)){ 
			throw new Exception("El ID de la materia debe ser un numero entero y debe tener nombre. Se recibio id:$id nombre:$nombre \n");
		} else {
			echo "ID de la materia: $id \n";
			$this->idMateria = $id;
			$this->nombreMateria = $nombre;
		}
	}

	public function construirCSV($id, $nombre) {
		// Encargado de obtener los datos e iniciar la construccion del CSV.
		// Instnaciamos la nueva materia
		echo "Construyendo CSV para la materia: $id \n";
		echo "Seteando materia... \n";
		$this->setMateria($id, $nombre);
		echo "Obteniendo prefijos... \n";
		$this->setPrefijos();
		echo "Generando filtro de grupos... \n";
		$this->filtroGrupos = $this->generarFiltroGrupos();
		echo "Generando query de campos personalizados... \n";
		$this->generarQueryInfoData();
		echo "Generando query de evaluables... \n";
		$this->generarQueryEvaluables();
		echo "Obteniendo alumnos por materia... \n";
		$alumnosPorMateria = $this->getAlumnosPorMateria();
		echo "Obteniendo nombre de la materia... \n";
		echo "Creando archivo... \n";
		return $this->crearArchivo($alumnosPorMateria);
	}
	private function crearArchivo($matrizNotas) {
		// Crear archivo CSV
		$nombreArchivoCSV = $this->idMateria ."-". $this->nombreMateria . self::$extension;
		echo "Nombre del archivo: $nombreArchivoCSV \n";
		$archivoCSV = $this->directorioExport . $nombreArchivoCSV;
		echo "Ruta del archivo: $archivoCSV \n";

		// Creamos el archivo con su nombre y permisos.
		$file = fopen($archivoCSV, "w");

		// Carga los datos de los alumnos en el archivo en forma de tabla
		$primeraFila = true;
		foreach ($matrizNotas as $fila) {
			if ($primeraFila) {
				$primeraFila = false;
				$columnas = array_keys((array) $fila);
				fputcsv($file, $columnas);
			}
			fputcsv($file, (array) $fila);
		}
		fclose($file);
		echo "Archivo creado \n";
		return $nombreArchivoCSV;
	}

	private function setPrefijos() {
		//Consulta de la DB el valor STRING de los prefijos separados por , y los devuelve en un array
		$prefijosRaw = $this->DB->get_record_sql("SELECT group_filter_prefix FROM mdl_moodlegoogle WHERE course = $this->idMateria AND timemodified =( SELECT MAX(timemodified) FROM mdl_moodlegoogle WHERE course = $this->idMateria)")->group_filter_prefix;
		$prefijosRaw = $this->eliminarEspacios($prefijosRaw);
		if (strpos($prefijosRaw, "*") === false) {
			// Si tiene * verifica mas nada y en el else lo cambia a vacio para que busque todo.
			if (empty($prefijosRaw)) {
				echo "Prefijos de materia vacio \n";
				// Si esta vacio busca en la configuracion global los prefijos que se desean exportar
				$prefijosRaw = $this->DB->get_record_sql("SELECT value FROM mdl_config_plugins WHERE plugin = 'mod_moodlegoogle' AND name = 'group_filter_prefix'")->value;
				$prefijosRaw = $this->eliminarEspacios($prefijosRaw);
				if (!empty($prefijosRaw)) { // Si la global contiene prefijos, agarra sus datos y lo setea 
					echo "Prefijos de plugin: $prefijosRaw \n";
					$this->gruposPrefijo = explode(",", $prefijosRaw); //Convierte el string en un array
				} else {
					echo "Prefijos de plugin vacio \n";
				}
			} else { // Si los prefijos de la materia NO estan vacios, se setean para la busqueda   
				$this->gruposPrefijo = explode(",", $prefijosRaw); //Convierte el string en un array
			}
		} else {
			echo "Prefijos de materia con * \n";
		}
	}

	private function eliminarEspacios($texto){
		// Elimina espacios en blanco de un texto
		return preg_replace('/\s+/', '', $texto);
	}

	private function getAlumnosPorMateria() {
		// Obtiene los alumnos por materia y los sus datos de cantidad de columnas fijas.
		echo "Obteniendo matriz exportable de alumnos por materia... \n";
		$idEstudiante = self::$studentId;
		$filtroGrupos = $this->filtroGrupos;
		$selectInfoData = $this->selectInfoData;
		$fromInfoData = $this->fromInfoData;
		$selectEvaluables = $this->selectEvaluables;
		$fromEvaluables = $this->fromEvaluables;
		$respuesta = $this->DB->get_records_sql("SELECT user.id AS 'UserID', MAX(user.lastname) AS 'Apellido(s)', MAX(user.firstname) AS 'Nombre', MAX(user.username) AS 'Nombre de usuario', MAX(user.institution) AS 'Institucion', MAX(user.department) AS 'Departamento', $selectInfoData GROUP_CONCAT(DISTINCT g.name) AS 'groupid' $selectEvaluables FROM mdl_user user LEFT JOIN mdl_groups_members gm ON gm.userid = user.id LEFT JOIN mdl_groups g ON g.id = gm.groupid AND g.courseid = $this->idMateria INNER JOIN mdl_role_assignments role_assignments ON role_assignments.userid = user.id INNER JOIN mdl_context context ON context.id = role_assignments.contextid INNER JOIN mdl_course course ON course.id = context.instanceid $fromInfoData $fromEvaluables WHERE course.id = $this->idMateria $filtroGrupos AND role_assignments.roleid = $idEstudiante GROUP BY user.id ORDER BY user.id");
		if (empty($respuesta)) {
			throw new Exception("No hay alumnos en la materia! \n");
		}
		echo "Matriz exportable de alumnos por materia obtenida \n";
		return $respuesta;
	}

	// Busca los prefijos de exportacion solicitados obtenidos previamente y los desestructura en una sentencia SQL
	private function generarFiltroGrupos() { 
		// Generar filtro para WHERE SQL de grupos prefijo
		if (count($this->gruposPrefijo) > 0) {
			echo "Grupos prefijo: " . implode(", ", $this->gruposPrefijo) . "\n";
			$contenidoAux = "";
			foreach ($this->gruposPrefijo as $prefijo) {
				if (is_string($prefijo) && $prefijo !== "") {
					// Verificar que sea un string y no este vacio
					$contenidoAux .= "g.name LIKE '$prefijo%' OR "; // Agregar prefijo
				}
			}
			if ($contenidoAux !== "") {
				echo "Filtro de grupos: $contenidoAux \n";
				$filtroGrupos = "AND ("; // Inicializar filtro
				$filtroGrupos .= $contenidoAux; // Agregar contenido auxiliar
				$filtroGrupos = substr($filtroGrupos, 0, -4); // Eliminar los ultimos 4 caracteres por ser ' OR '.
				$filtroGrupos .= ")"; // Cerrar filtro
			}
		} else {
			echo "No hay grupos prefijo \n";
			$filtroGrupos = ""; // Dejar filtro vacio
		}
		return $filtroGrupos;
	}

	private function getCamposPersonalizados() {
		//Devuelve los campos personalizados de los usuarios.
		$respuesta = $this->DB->get_records_sql("SELECT id, name FROM mdl_user_info_field ORDER BY id ASC");
		if (empty($respuesta)) {
			echo "No hay campos personalizados \n";
		}
		return $respuesta;
	}
	
	private function generarQueryInfoData() {
		// Generar SELECT y FROM de campos personalizados
		$selectInfoData = "";
		$fromInfoData = "";
		$camposCustom = $this->getCamposPersonalizados(); // Obtiene campos personalizados
		if ($camposCustom) {
			// Si hay campos personalizados
			foreach ($camposCustom as $rowCampo) {
				$campoID = $rowCampo->id;
				$campoName = $rowCampo->name;
				if (empty($campoName)) {
					// Si no tiene nombre
					$campoName = "Campo personalizado: " . $campoID; // Se asigna un nombre generico con el ID
				}
				$selectInfoData .= "MAX(info_data$campoID.data) AS '$campoName', ";
				$fromInfoData .= "LEFT JOIN mdl_user_info_data info_data$campoID ON info_data$campoID.userid = user.id AND info_data$campoID.fieldid = $campoID ";
			}
			echo "Se generó query de campos personalizados \n";
		}
		$this->selectInfoData = $selectInfoData;
		$this->fromInfoData = $fromInfoData;
	}

	private function getEvaluablesPorMateria() {
		// Lista los evaluables de una materia por ID, no así su contenido.
		echo "Obteniendo evaluables por materia... \n";
		$respuesta = $this->DB->get_records_sql("SELECT grade_items.id AS evaluableid, grade_items.itemname AS nombredelevaluable FROM mdl_grade_items grade_items WHERE grade_items.courseid = $this->idMateria AND grade_items.itemmodule IS NOT NULL ORDER BY evaluableid ASC");
		echo "Evaluables por materia obtenidos \n";
		return $respuesta;
	}

	private function generarQueryEvaluables() {
		// Generar SELECT y FROM de evaluables
		$selectEvaluables = "";
		$fromEvaluables = "";
		$evaluablesPorMateria = $this->getEvaluablesPorMateria(); // Obtiene evaluables por materia
		if ($evaluablesPorMateria) {
			// Si hay evaluables
			foreach ($evaluablesPorMateria as $rowEvaluable) {
				// Genera SELECT y FROM de evaluables por cada evaluable.
				$evaluableID = $rowEvaluable->evaluableid;
				$evaluableName = $rowEvaluable->nombredelevaluable;
				if (empty($evaluableName)) {
					// Si no tiene nombre
					$evaluableName = "Evaluable: " . $evaluableID; // Se asigna un nombre generico con el ID
				}
				$selectEvaluables .= ", MAX(grades$evaluableID.finalgrade) AS '$evaluableName'";
				$fromEvaluables .= "LEFT JOIN mdl_grade_grades grades$evaluableID ON grades$evaluableID.userid = user.id AND grades$evaluableID.itemid = $evaluableID ";
			}
			echo "Se generó query de evaluables. \n";
		}
		$this->selectEvaluables = $selectEvaluables;
		$this->fromEvaluables = $fromEvaluables;
	}
}