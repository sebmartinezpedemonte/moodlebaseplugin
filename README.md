# Exportación de Notas MoodleGoogle
<img src="https://github.com/ORTCALIFOWNER/CAL-202401-AL-C2/blob/78c2a7c8e281756f9e8a11e570f34afc1c841231/moodlegoogle/pix/icon.png?raw=true" alt="MoodleGoogleLogo" width="30%">

## Descripción

El plugin **Exportación de Notas MoodleGoogle** (MoodleGoogle Grades Export) permite la exportación automática de notas a archivos CSV. Estos archivos se almacenan en Google Drive, proporcionando un método eficiente y seguro para manejar y archivar las notas de los estudiantes.

## Tecnologías Utilizadas

- **PHP**: Versión 7.4.33
- **Moodle**: Librerías de Moodle 4.1.8, incluyendo:
  - Tareas programadas (Scheduled tasks)
  - Logs de tareas (Task Logs)
  - API de manipulación de datos (Data manipulation API)
- **Plugin Skeleton Generator**: Herramienta para la generación de la estructura del plugin
- **Google API Client para PHP**: Versión 2.16.0

## Características Principales

- **Automatización de Exportación**: Configura la exportación de notas de cursos específicos a intervalos regulares.
- **Integración con Google Drive**: Los archivos CSV generados se suben automáticamente a una carpeta designada en Google Drive.
- **Flexiblilidad**: Permite configuraciones a nivel global y por curso, adaptándose a diversas necesidades y preferencias.
- **Mayor seguridad**: Mejora la eficiencia y seguridad al eliminar la dependencia de servicios externos y proporcionando un acceso controlado a la base de datos evitand la apertura de apis a internet.

Para más información sobre la instalación y configuración, consulta la documentación.

## Instalación

Remitirse a los manuales:
- [80-2 - Manual de usuario - Configuración Google Cloud y obtención de Credenciales](https://github.com/ORTCALIFOWNER/CAL-202401-AL-C2/blob/3eff293f516f3e62741cf36012b12a2c0f45749d/Docs/80-2%20-%20Manual%20de%20usuario%20-%20Configuracion%20Google%20Cloud%20y%20obtencion%20de%20Credenciales.docx)
- [80-1 - Manual de usuario - MoodleGoogle](https://github.com/ORTCALIFOWNER/CAL-202401-AL-C2/blob/3eff293f516f3e62741cf36012b12a2c0f45749d/Docs/80-1%20-%20Manual%20de%20usuario%20-%20MoodleGoogle.docx)

## Documentación útil

- [81-1 - Especificación Funcional MoodleGoogle](https://github.com/ORTCALIFOWNER/CAL-202401-AL-C2/blob/3eff293f516f3e62741cf36012b12a2c0f45749d/Docs/81-1%20-%20Especificacion%20Funcional%20MoodleGoogle.docx)
- [81-2 - MoodleGoogle Esquema](https://github.com/ORTCALIFOWNER/CAL-202401-AL-C2/blob/3eff293f516f3e62741cf36012b12a2c0f45749d/Docs/81-2%20-%20MoodleGoogle%20Esquema.ddb) en formato [DrawDB](https://drawdb.vercel.app/editor).



## Contribuciones

Si deseas contribuir al desarrollo de este plugin, por favor abre un issue o envía un pull request en el repositorio.

## Licencia

TBD

---

¡Gracias por usar **Exportación de Notas MoodleGoogle**!
