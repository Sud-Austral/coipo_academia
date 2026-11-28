# Datos del paquete Documentacion_Sistema2 de coipo_academia.
# Todo sale de leer el repositorio; lo que no se pudo determinar va como [POR VALIDAR].
VERSION = "2"
FECHA = "2026-11-15"

ROL = {
    "clave": "usuario",
    "nombre": "Usuario",
    "resumen": ("Persona que provisiona y verifica la Academia CONAF v2 (Moodle 5.2.1) ejecutando los "
                "scripts de academia/cli dentro del contenedor. El repositorio no tiene pantallas propias: "
                "todo lo que aporta se usa por línea de comandos o a través de las pantallas nativas de Moodle."),
    "acceso": ("Acceso por consola al servidor donde corre el contenedor «app» del docker-compose.yml y permiso "
               "para ejecutar «docker compose exec -u www-data app php ...». Quién tiene ese acceso en el "
               "servidor real: [POR VALIDAR], no está en el repositorio."),
}

# (nombre, objetivo, script, pasos, resultado, validaciones/errores)
FUNC = [
    ("Ingreso: instalar Moodle en una base vacía",
     "Crear las tablas de Moodle sobre academia_v2 vacía antes de cualquier script.",
     "admin/cli/install_database.php (Moodle) — descrito en academia/README.md, «Paso 0»",
     ["Entrar al directorio de despliegue del servidor.",
      "Ejecutar install_database.php con --lang=es --agree-license y los datos del sitio.",
      "Ejecutar check_database_schema.php y leer que diga «Database structure is ok.».",
      "Contar usuarios y cursos con la consulta del README: deben salir 2 y 1."],
     "Moodle instalado y vacío: 2 usuarios (invitado y administrador) y 1 curso (la portada).",
     ["Si la base ya tiene una tabla, el script aborta con clitablesexist y no escribe nada.",
      "Sin salida a internet, --lang=es no falla: avisa remotedownloaderror y el sitio queda en inglés. Hay que leer la salida, no el código de retorno.",
      "La contraseña del administrador queda en el historial del shell: limpiarlo."]),
    ("Crear el árbol de áreas temáticas", "Crear las categorías de academia/datos/categorias.csv, vacías y ocultas.",
     "academia/cli/10_categorias.php",
     ["Ejecutar primero con --dry-run y leer la simulación.", "Ejecutar sin --dry-run.", "Leer el resumen final de creados y existentes."],
     "9 categorías creadas (filas de datos del CSV).", ["Una segunda corrida no imprime ningún «creado» (idempotencia)."]),
    ("Crear los campos personalizados de curso", "Crear los 8 campos de «Clasificación institucional».",
     "academia/cli/20_campos_curso.php", ["Ejecutar con --dry-run.", "Ejecutar sin --dry-run."],
     "8 campos creados; 7 quedan obligatorios en el formulario de curso.",
     ["Desde que corre, quien cree un curso debe completar los campos obligatorios."]),
    ("Crear las cohortes", "Crear las cohortes de sistema, sin miembros.",
     "academia/cli/30_cohortes.php", ["Ejecutar con --dry-run.", "Ejecutar sin --dry-run."],
     "28 cohortes vacías. La pertenencia se carga después, a mano o por CSV.", ["El script no puebla cohortes."]),
    ("Cargar los marcos de competencias", "Encender el subsistema de competencias y cargar los marcos.",
     "academia/cli/40_competencias.php", ["Ejecutar con --dry-run.", "Ejecutar sin --dry-run."],
     "Subsistema encendido y 18 filas de competencias cargadas por la API core_competency.",
     ["tool_lpimportcsv no tiene interfaz de línea de comandos; por eso el script usa la API directamente."]),
    ("Crear el rol Gestor de Área Temática", "Crear el rol gestorarea, asignable solo en categoría.",
     "academia/cli/50_rol_gestor.php",
     ["Ejecutar con --dry-run.", "Ejecutar sin --dry-run.",
      "Seguir la prueba que imprime el script: curso oculto en 04, rol en 01, comprobar que no ve el curso de 04."],
     "Rol gestorarea (arquetipo manager), contexto solo CONTEXT_COURSECAT, puede asignar editingteacher, teacher y student.",
     ["El rol queda sin personas: designar gestores es una decisión institucional.",
      "No puede asignar manager ni gestorarea (50_rol_gestor.php:161)."]),
    ("Revisar y corregir la clasificación de cursos", "Corregir en bloque los 8 campos de los cursos duplicados desde GC-000.",
     "academia/cli/60_clasificar_cursos.php", ["Ejecutar con --dry-run.", "Ejecutar sin --dry-run."],
     "Cursos con clasificación propia y no la heredada de la plantilla («99 Gestión del Campus», «Archivado»).", []),
    ("Crear los informes del catálogo y del tablero", "Crear informes personalizados nativos del generador de informes.",
     "academia/cli/70_informes.php", ["Correr antes 20 y 60.", "Ejecutar con --dry-run.", "Ejecutar sin --dry-run."],
     "Informes personalizados creados.", ["Un informe no filtra por una columna que no existe: el orden 20 → 60 → 70 es obligatorio."]),
    ("Crear la plantilla maestra GC-000", "Construir el curso molde, oculto y archivado.",
     "academia/cli/80_plantilla_maestra.php", ["Ejecutar con --dry-run.", "Ejecutar sin --dry-run."],
     "Curso GC-000 en la categoría 99, oculto.", []),
    ("Construir los cursos esqueleto IF-151 y TR-104", "Crear la estructura, cuestionarios, banco y certificado, sin el contenido H5P.",
     "academia/cli/90_cursos_esqueleto.php", ["Ejecutar con --dry-run.", "Ejecutar sin --dry-run."],
     "IF-151 y TR-104 como esqueletos; el contenido H5P lo produce el autor.", []),
    ("Aplicar la línea base de ajustes del sitio", "Aplicar los ajustes de subsistemas necesarios.",
     "academia/cli/95_ajustes_sitio.php", ["Ejecutar con --dry-run.", "Ejecutar sin --dry-run."],
     "Ajustes aplicados; sobre una instalación limpia la mitad dice «ya está».",
     ["No toca el correo saliente: soltar MOODLE_NOEMAILEVER es una decisión, no un ajuste."]),
    ("Verificar la instalación", "Contar lo que hay y compararlo con lo esperado.",
     "academia/cli/99_verificar.php", ["Ejecutar el script.", "Leer cada línea y el código de salida.", "Hacer a mano la lista de comprobaciones que imprime al final."],
     "Código de salida 0 solo si todo cuadra; 1 si hay fallos (99_verificar.php:324).",
     ["Comprueba que la base no sea academia_prod (99_verificar.php:48)."]),
]

CASOS = [
    ("CU-01", "Instalar Moodle en base vacía", 0, "Positivo"),
    ("CU-02", "Crear categorías en simulación (--dry-run)", 1, "Positivo"),
    ("CU-03", "Crear categorías y repetir sin duplicar", 1, "Idempotencia"),
    ("CU-04", "Crear campos personalizados de curso", 2, "Positivo"),
    ("CU-05", "Crear cohortes vacías", 3, "Positivo"),
    ("CU-06", "Cargar marcos de competencias", 4, "Positivo"),
    ("CU-07", "Crear rol gestorarea y comprobar el límite por categoría", 5, "Ámbito"),
    ("CU-08", "Corregir clasificación de cursos", 6, "Positivo"),
    ("CU-09", "Crear informes respetando el orden 20 → 60 → 70", 7, "Positivo"),
    ("CU-10", "Crear plantilla maestra GC-000", 8, "Positivo"),
    ("CU-11", "Construir esqueletos IF-151 y TR-104", 9, "Positivo"),
    ("CU-12", "Aplicar ajustes del sitio", 10, "Positivo"),
    ("CU-13", "Verificar instalación con 99_verificar.php", 11, "Positivo"),
    ("CU-14", "Intentar correr un script contra academia_prod", None, "Negativo"),
    ("CU-15", "Pasar un parámetro no reconocido", None, "Negativo"),
]
CASO_EXTRA = {
    "CU-14": ("Cualquier script de academia/cli", "academia/cli/lib.php:175",
              ["Con un config.php que apunte a academia_prod, ejecutar un script sin --permitir-produccion."],
              "El script se detiene ANTES de escribir, con código 2 y el mensaje «Este script apunta a la base de PRODUCCIÓN (academia_prod) y se detuvo sin escribir nada.»"),
    "CU-15": ("Cualquier script de academia/cli", "academia/cli/lib.php:155",
              ["Ejecutar un script con un parámetro que no existe."],
              "Error «Parámetro no reconocido: ... . Usar --help.»"),
}

REGLAS = [
    ("RN-01", "Freno de producción", "Los scripts se niegan a escribir si la base es academia_prod, salvo --permitir-produccion.", "academia/cli/lib.php:175"),
    ("RN-02", "Idempotencia", "Se busca por idnumber o shortname antes de crear; una segunda corrida no crea nada.", "academia/cli/lib.php (cabecera)"),
    ("RN-03", "Simulación", "--dry-run (o -n) no escribe nada.", "academia/cli/lib.php:148"),
    ("RN-04", "Ámbito del gestor", "gestorarea solo se asigna en contexto de categoría.", "academia/cli/50_rol_gestor.php:69"),
    ("RN-05", "Gestor no nombra gestores", "gestorarea solo puede asignar editingteacher, teacher y student.", "academia/cli/50_rol_gestor.php:161"),
    ("RN-06", "Orden de provisión", "70_informes requiere que 20 y 60 hayan corrido.", "academia/cli/70_informes.php (cabecera)"),
]

ROLES = [
    ("Usuario (operador de provisión)", "Sí", "Único que usa directamente lo que aporta el repositorio (scripts CLI)."),
    ("Gestor de Área Temática (gestorarea)", "No (sin manual propio)",
     "Lo DEFINE el repositorio (50_rol_gestor.php:24), pero se usa en pantallas nativas de Moodle y el rol queda sin personas asignadas. No se pudo levantar Moodle para capturarlo. [POR VALIDAR] si merece manual cuando haya gestores designados."),
    ("Roles nativos de Moodle (manager, editingteacher, teacher, student, guest, user)", "No",
     "Son de Moodle, no del repositorio. Los manuales de manuales/ describen el campus actual (Moodle 4.5.10), no la Academia."),
]

HALLAZGOS = [
    ("P-001", "El triaje decía que el repositorio no tiene roles propios, pero 50_rol_gestor.php crea el rol gestorarea",
     "media", "academia/cli/50_rol_gestor.php:24, 57", "verificado en el código"),
    ("P-002", "Comentario de 70_informes.php cita Moodle 4.5 y una línea de reportbuilder de esa versión, aunque la Academia es 5.2.1",
     "baja", "academia/cli/70_informes.php:5", "verificado en el código"),
    ("P-003", "Ningún script se ejecutó en esta documentación: no hay PHP local y Docker no estaba corriendo",
     "media", "comando «docker images»: no conecta al daemon", "observado"),
    ("P-004", "La prueba de ámbito del gestor (no ver cursos de otra categoría) es manual y no está automatizada en 99_verificar.php",
     "media", "academia/cli/50_rol_gestor.php:192-201; 99_verificar.php:108-118 solo cuenta el rol y su contexto", "verificado en el código"),
    ("P-005", "El tema theme_academia está en madurez ALPHA",
     "baja", "plugins/theme/academia/version.php", "verificado en el código"),
]
