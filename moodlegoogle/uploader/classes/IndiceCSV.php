<?php
namespace mod_moodlegoogle\uploader;
ini_set("display_errors", 1);
ini_set("display_startup_errors", 1);
error_reporting(E_ALL);
use Exception;

global $CFG;
require $CFG->dirroot . "/mod/moodlegoogle/uploader/api-google/vendor/autoload.php";
use Google_Client;
use Google_Service_Drive; // Import the Google_Service_Drive class
use Google_Service_Drive_DriveFile; // Import the Google_Service_Drive_DriveFile class
// La clase IndiceCSV es la encargada de generar un listado de materias con sus nombres y categorias.

class IndiceCSV {
    private static $GOOGLE_SCOPE = "https://www.googleapis.com/auth/drive.file"; //A donde apunta nuestro servicio
	private static $RUTA_KEY = __DIR__ . DIRECTORY_SEPARATOR .  "key.json";
	private static $CLIENTE_GOOGLE; // Quien consume la api del proveedor
	private static $extension = ".csv"; // Extension de archivo
    private static $indiceName = "Indice de Materias"; // Nombre del archivo
    private $nombreArchivo; // Nombre del archivo con extension
    private $pathArchivoLocal; // Path del archivo local
	private $directorioExport; // Directorio interno local de exportacion
    private $idDirectorioGlobal; // Directorio global de exportacion
    private static $SERVICIO_GOOGLE; // El servicio que brinda el proveedor

	private $DB;
	public function __construct($carpetaExportable, $DB) {
        global $CFG;
        $this->DB = $DB;
        $this->directorioExport = $carpetaExportable;
        $this->nombreArchivo = self::$indiceName . self::$extension;
		self::$CLIENTE_GOOGLE = new Google_Client();
		putenv("GOOGLE_APPLICATION_CREDENTIALS=" . self::$RUTA_KEY);
		self::$CLIENTE_GOOGLE->useApplicationDefaultCredentials();
		self::$CLIENTE_GOOGLE->SetScopes([self::$GOOGLE_SCOPE]);
		self::$SERVICIO_GOOGLE = new Google_Service_Drive(self::$CLIENTE_GOOGLE);
	}
	public function construirIndiceCSV($categoriesData) {
        $this->pathArchivoLocal = $this->directorioExport . $this->nombreArchivo;
		// Encargado de obtener los datos e iniciar la construccion del CSV.
        $this->idDirectorioGlobal = $this->getDirectorioGlobal();
        echo "Construyendo Indice \n";
        $matriz = $this->generarMatriz($categoriesData);
        echo "Completando encabezado de Indice \n";
        $matriz = $this->completarEncabezado($matriz);
        echo "Generando CSV de Indice \n";
		$this->generarCSV($matriz);
		echo "Validando archivos vigentes \n";
		$this->chequearArchivosVigentes();
        echo "Subiendo CSV de Indice \n";
        $this->subirArchivo($this->crearArchivoDrive(), $this->pathArchivoLocal);
        echo "Indice Subido \n";
	}
	private function getCourses() {
		// Obtener materias
		$query = "SELECT c.id, c.fullname, c.shortname, c.category FROM mdl_course c";
		$result = $this->DB->get_records_sql($query);
		return $result;
	}
    private function generarMatriz($categoriesData) {
        $materias = $this->getCourses();
        $categorias = $categoriesData;
        $matriz = [];
        foreach ($materias as $materia) {
            //Agrega en la matriz el id, nombre, nombre corto y desestructura el path de las categorias en columnas.
            if (isset($categorias[$materia->category])) {
                $categoria = $categorias[$materia->category];
                if (is_object($categoria) && property_exists($categoria, 'path')) {
                    $path = explode("/", $categoria->path);
                    $matriz[] = [$materia->id, $materia->fullname, $materia->shortname]; //se cargan los valores basicos al array.
                    foreach ($path as $idCategoria) {
                        if ($idCategoria != null && isset($categorias[$idCategoria])) {
                            $categoria = $categorias[$idCategoria];
                            $matriz[count($matriz) - 1][] = $categoria->name;
                        }
                    }
                }
            }
        }
        return $matriz;
    }
	private function generarCSV($matriz) {
		$file = fopen($this->pathArchivoLocal, "w");
		//desestructura la matriz y escribe en el archivo csv
		foreach ($matriz as $linea) {
			fputcsv($file, $linea);
		}
		fclose($file);
	}
    private function completarEncabezado($matriz) {// Busca en la matriz recibida las columnas con contenido en alguna fila cuyo encabezado este vacio y le asigna en el encabezado "Categoria N" donde N es el numero de la columna.
        $encabezado = ["ID", "Nombre", "Nombre Corto"];
        $max = 0;
        foreach ($matriz as $linea) {
            $max = max($max, count($linea));
        }
        for ($i = 0; $i < $max - 3; $i++) {
            $encabezado[] = "Categoria " . ($i + 1);
        }
        foreach ($matriz as &$linea) {
            $linea = array_pad($linea, $max, "");
        }
        array_unshift($matriz, $encabezado);
        return $matriz;
    }
    private function getDirectorioGlobal() {
		//Devuelve el directorio global
		echo "Obteniendo directorio global... \n";
		$directorio = $this->DB->get_record_sql("SELECT value FROM mdl_config_plugins WHERE plugin = 'mod_moodlegoogle' AND name = 'gdrive_folder_id'")->value;
		if ($directorio == "") {
			echo "No se encontro directorio global \n";
			throw new Exception("No se encontro directorio global");
		}
		echo "Directorio global obtenido \n";
		return $directorio;
	}
    private function crearArchivoDrive() {
		// Creamos el archivo drive con los metadatos
		$archivo = new Google_Service_Drive_DriveFile();
		$archivo->setName($this->nombreArchivo);
		$archivo->setParents([$this->idDirectorioGlobal]);
		$archivo->setDescription("Subido automaticamente por MoodleGoogle: " . date("Y-m-d-H-i-s"));
		$archivo->setMimeType("text/csv");
		return $archivo;
	}
    private function chequearArchivosVigentes() {
		echo "Chequeando archivos INDICE vigentes... \n";
		$archivosExistentes = $this->buscarArchivo($this->nombreArchivo, $this->idDirectorioGlobal);
		if (count($archivosExistentes->getFiles()) > 0) {
			echo "Archivo INDICE existente encontrado \n";
			$archivoVigente = $archivosExistentes->getFiles()[0];
			$idArchivoExistente = $archivoVigente->getId();
			$this->eliminarArchivo($idArchivoExistente);
			echo "Archivo INDICE existente eliminado \n";
		}
	}

    private function buscarArchivo($nombreArchivo, $ruta) {
		echo "Buscando archivo... \n";
		$archivosExistentes = self::$SERVICIO_GOOGLE->files->listFiles([
			"q" => "name='$nombreArchivo' and '$ruta' in parents"
		]);
		echo "Archivo encontrado \n";
		return $archivosExistentes;
	}

    private function eliminarArchivo($idArchivo) {
		echo "Eliminando archivo existente... \n";
		self::$SERVICIO_GOOGLE->files->delete($idArchivo);
		echo "Archivo existente eliminado.\n";
	}
	private function subirArchivo($archivo, $pathArchivoLocal) {
		echo "Subiendo archivo... \n";
		$resultado = self::$SERVICIO_GOOGLE->files->create($archivo, [
			"data" => file_get_contents($pathArchivoLocal),
			"uploadType" => "text"
		]);
		echo "Archivo subido \n";
	}
}