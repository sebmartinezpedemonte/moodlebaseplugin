<?php
namespace mod_moodlegoogle\uploader;

// La clase Exporter es la encargada de exportar csv a Gdrive y generar su archivo historico correspondiente en la carpeta correspondiente.

global $CFG;
require $CFG->dirroot . "/mod/moodlegoogle/uploader/api-google/vendor/autoload.php";
use Exception;
use Google_Client;
use Google_Service_Drive; // Import the Google_Service_Drive class
use Google_Service_Drive_DriveFile; // Import the Google_Service_Drive_DriveFile class

class UploaderCSV
{
	private static $NOMBRE_CARPETA_HISTORICO = "Historico";
	private static $CLIENTE_GOOGLE; // Quien consume la api del proveedor
	private static $SERVICIO_GOOGLE; // El servicio que brinda el proveedor
	private static $RUTA_KEY = __DIR__ . DIRECTORY_SEPARATOR . "key.json";
	private static $GOOGLE_SCOPE = "https://www.googleapis.com/auth/drive.file"; //A donde apunta nuestro servicio
	private $directorioExport;
	private $extension;
	private $DB;
	public function __construct($carpetaExportable, $DB)
	{
		global $CFG;
		$this->DB = $DB;
		$this->directorioExport = $carpetaExportable;
		self::$CLIENTE_GOOGLE = new Google_Client();
		putenv("GOOGLE_APPLICATION_CREDENTIALS=" . self::$RUTA_KEY);
		self::$CLIENTE_GOOGLE->useApplicationDefaultCredentials();
		self::$CLIENTE_GOOGLE->SetScopes([self::$GOOGLE_SCOPE]);
		self::$SERVICIO_GOOGLE = new Google_Service_Drive(self::$CLIENTE_GOOGLE);
	}
	public function uploadCSV($nombreArchivo, $rutaMateria, $idMateria, $exportTime)
	{
		//Ruta archivo local
		echo "Subiendo archivo a Google Drive \n";
		$pathArchivoLocal = $this->directorioExport . $nombreArchivo;
		$idDirectorioPadreMateria = $this->getIdDirectorioPadreMateria($idMateria); // Ruta para archivo unico
		$idCarpetaFinal = $this->getDirectorioFinalID($rutaMateria, $idDirectorioPadreMateria); // Ruta con categoria para historico

		$archivoDrive = $this->crearArchivoDrive($nombreArchivo, $idDirectorioPadreMateria);
		$this->chequearArchivosVigentes($nombreArchivo, $idDirectorioPadreMateria);
		$this->subirArchivo($archivoDrive, $pathArchivoLocal);
		$this->subirArchivoHistorico($archivoDrive, $nombreArchivo, $pathArchivoLocal, $idCarpetaFinal);
		$this->actualizarFechaExportacion($idMateria, $exportTime);
		echo "Archivo subido a Google Drive \n";
	}
	public function subirArchivoHistorico($archivo, $nombreArchivo, $pathArchivoLocal, $idCarpetaFinal)
	{
		//crea el archivo historico en la carpeta correspondiente
		echo "Subiendo archivo historico... \n";
		$this->extension = substr($nombreArchivo, -4); //Obtenemos la extension del archivo que recibimos.
		$parents = $idCarpetaFinal;
		$idCarpetaHistorico = $this->buscarDirectorioId(self::$NOMBRE_CARPETA_HISTORICO, $parents);
		$nombreArchivo = substr($nombreArchivo, 0, -4);
		// Generamos el nombre completo del archivo que exportamos
		$archivo->setName($nombreArchivo . "-" . date("Y-m-d-H-i-s") . $this->extension);
		$archivo->setParents([$idCarpetaHistorico]);
		$this->subirArchivo($archivo, $pathArchivoLocal);
		echo "Archivo historico subido \n";
	}
	private function crearArchivoDrive($nombre, $ruta)
	{
		// Creamos el archivo drive con los metadatos
		$archivo = new Google_Service_Drive_DriveFile();
		$archivo->setName($nombre);
		$archivo->setParents([$ruta]);
		$archivo->setDescription("Subido automaticamente por MoodleGoogle: " . date("Y-m-d-H-i-s"));
		$archivo->setMimeType("text/csv");
		return $archivo;
	}
	private function subirArchivo($archivo, $pathArchivoLocal)
	{
		echo "Subiendo archivo... \n";
		$resultado = self::$SERVICIO_GOOGLE->files->create($archivo, [
			"data" => file_get_contents($pathArchivoLocal),
			"uploadType" => "text"
		]);

		echo "Archivo subido \n";
	}
	private function chequearArchivosVigentes($nombreArchivo, $ruta)
	{
		echo "Chequeando archivos vigentes... \n";
		$archivosExistentes = $this->buscarArchivo($nombreArchivo, $ruta);
		if (count($archivosExistentes->getFiles()) > 0) {
			echo "Archivo existente encontrado \n";
			$archivoVigente = $archivosExistentes->getFiles()[0];
			$idArchivoExistente = $archivoVigente->getId();
			$this->eliminarArchivo($idArchivoExistente);
			echo "Archivo existente eliminado \n";
		}
	}
	private function eliminarArchivo($idArchivo)
	{
		echo "Eliminando archivo existente... \n";
		self::$SERVICIO_GOOGLE->files->delete($idArchivo);
		echo "Archivo existente eliminado.\n";
	}
	private function buscarArchivo($nombreArchivo, $ruta)
	{
		echo "Buscando archivo... \n";
		$archivosExistentes = self::$SERVICIO_GOOGLE->files->listFiles([
			"q" => "name='$nombreArchivo' and '$ruta' in parents"
		]);
		echo "Archivo encontrado \n";
		return $archivosExistentes;
	}
	private function getDirectorioFinalID($rutaMateria, $idCarpetaPadre)
	{
		//Recorre los directorios buscando el id de cada uno hasta encontrar el fina. Si un directorio no existe, lo crea junto a sus hijos.
		echo "Obteniendo directorio final... \n";
		foreach ($rutaMateria as $nombreCarpeta) {
			try {
				$carpetaId = $this->buscarDirectorioId($nombreCarpeta, $idCarpetaPadre);
			} catch (Exception $e) {
				throw new Exception("Error al buscar directorio id:$idCarpetaPadre " . $e->getMessage());
			}

			if ($carpetaId == 0) {
				echo "Directorio no encontrado, creando... \n";
				$carpetaId = $this->crearDirectorio($nombreCarpeta, $idCarpetaPadre);
			}

			$idCarpetaPadre = $carpetaId;
		}
		$this->crearCarpetaHistorica($idCarpetaPadre);
		echo "Directorio final obtenido \n";
		return $idCarpetaPadre;
	}
	private function buscarDirectorioId($nombreCarpeta, $idCarpetaPadre)
	{
		//Busca id de directorio en otro directorio. Devuelve 0 si no existe
		echo "Buscando directorio... \n";
		$id = 0;
		$directorio = self::$SERVICIO_GOOGLE->files->listFiles([
			"q" => "mimeType='application/vnd.google-apps.folder' and name='$nombreCarpeta' and '$idCarpetaPadre' in parents"
		]);
		$archivos = $directorio->getFiles();

		if (!empty($archivos)) {
			$carpeta = $archivos[0];
			$id = $carpeta->getId();
		}
		echo "Directorio encontrado \n";
		return $id;
	}
	private function crearDirectorio($nombreCarpeta, $idCarpetaPadre)
	{
		//Crea un directorio en otro directorio y devuelve el id del directorio creado
		echo "Creando directorio... \n";
		$carpeta = new Google_Service_Drive_DriveFile();
		$carpeta->setName($nombreCarpeta);
		$carpeta->setMimeType("application/vnd.google-apps.folder");
		$carpeta->setParents([$idCarpetaPadre]);
		$nuevaCarpeta = self::$SERVICIO_GOOGLE->files->create($carpeta);
		echo "Directorio creado \n";
		usleep(1500000); // Esperamos un segundo y medio para que se cree la carpeta antes de seguir. Evita duplicado de carpetas por lentitud de Google.
		return $nuevaCarpeta->getId();
	}
	private function crearCarpetaHistorica($idCarpetaPadre)
	{
		echo "Creando carpeta historico... \n";
		if ($this->hayCarpetaHistorico($idCarpetaPadre)) {
			echo "Carpeta historico no existe, creando... \n";
			$carpeta = new Google_Service_Drive_DriveFile();
			$carpeta->setName(self::$NOMBRE_CARPETA_HISTORICO);
			$carpeta->setMimeType("application/vnd.google-apps.folder");
			$carpeta->setParents([$idCarpetaPadre]);
			self::$SERVICIO_GOOGLE->files->create($carpeta); // Creamos carpeta
			echo "Carpeta historico creada \n";
		}
		echo "Carpeta historico ya existe \n";
	}
	private function hayCarpetaHistorico($idCarpetaPadre)
	{
		echo "Verificando si existe carpeta historico... \n";
		$ncarpeta = self::$NOMBRE_CARPETA_HISTORICO;
		$result = self::$SERVICIO_GOOGLE->files->listFiles([
			"q" => "mimeType='application/vnd.google-apps.folder' and name='$ncarpeta' and '$idCarpetaPadre' in parents"
		]);

		$carpetas = $result->getFiles();
		echo "Carpeta historico encontrada. \n";
		return empty($carpetas);
	}
	private function getIdDirectorioPadreMateria($idMateria)
	{
		//Devuelve el directorio raiz donde se exportara el archivo. Si no encuentra el directorio de la materia, devuelve el directorio global
		echo "Obteniendo directorio padre de materia... \n";
		$directorio = $this->DB->get_record_sql("SELECT gdrive_folder_id FROM mdl_moodlegoogle WHERE course = $idMateria AND timemodified =( SELECT MAX(timemodified) FROM mdl_moodlegoogle WHERE course = $idMateria)")->gdrive_folder_id;
		if ($directorio == "") {
			echo "No se encontro directorio de materia \n";
			$directorio = $this->getDirectorioGlobal();
		}
		echo "Directorio padre de materia obtenido \n";
		return $directorio;
	}
	private function getDirectorioGlobal()
	{
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
	private function actualizarFechaExportacion($idMateria, $exportTime)
	{
		echo "Actualizando fecha de exportacion... \n";
		$this->DB->execute("UPDATE mdl_moodlegoogle SET time_last_export = $exportTime WHERE course = $idMateria");
		echo "Fecha de exportacion actualizada \n";
	}
}