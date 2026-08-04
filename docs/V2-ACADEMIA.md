# Academia CONAF v2 — modelo de datos, vistas y estándar

Qué se construyó en este repositorio, por qué, y qué queda abierto.

`coipo_moodle` resolvió el problema de **infraestructura**: sacar el Moodle de CONAF del
proveedor externo y dejarlo corriendo en servidor propio sobre PostgreSQL 17. Eso está hecho
y verificado.

Este repositorio resuelve otro problema. El diagnóstico de `INSUMO_MEJORA` lo dice en una
línea: *la plataforma no es el problema, la arquitectura de información sí lo es*.

| | Hoy | Academia |
|---|---|---|
| Categorías | 9 planas, con cinco criterios mezclados | 7 áreas, máximo 3 niveles |
| Campos de clasificación | 1 | 8 |
| Cohortes | 0 | 28 |
| Roles delegados | 0 | Gestor de Área Temática, por categoría |
| Competencias | apagadas | 2 marcos, 11 competencias |
| Certificado | documento de participación | credencial con vigencia y código |

---

## La instancia v2 corre en paralelo, y en otra versión

No reemplaza a producción: convive con ella en el mismo servidor, y además va por delante
en versión.

```
172.31.2.41
├── coipo_moodle    8115   academia_prod   Moodle 4.5.10 · PHP 8.3
│   producción, 2.869 usuarios · NO SE TOCA hasta octubre
└── coipo_academia  8116   academia_v2     Moodle 5.2.1  · PHP 8.4
    esta · clon de academia_prod, actualizado a 5.2
```

**Por qué 5.2 y no seguir en 4.5.** El destino es Moodle 5.3 LTS + PHP 8.4 en octubre de 2026,
que es lo que la Propuesta ya planifica —*«intervenir la plataforma una sola vez»*—. Pero
construir v2 sobre 5.2 ahora consigue tres cosas que valen más que esperar:

1. El salto 4.5 → 5.x se ensaya en un **clon desechable** antes de tocar producción.
   `DROP DATABASE academia_v2` lo revierte entero.
2. En octubre queda 5.2 → 5.3, que es un paso corto, en vez de 4.5 → 5.3, que son cuatro
   versiones de golpe y con fecha encima.
3. PHP 8.4 está en soporte activo hasta diciembre de 2026; **8.3 salió de soporte activo el
   31 de diciembre de 2025** y solo recibe parches de seguridad.

**Por qué no PHP 8.5.** Moodle 5.2 no tiene ningún bloqueo superior de PHP —verificado en su
`environment.xml`: mínimo 8.3.0 y ni un `<RESTRICT>`—, así que 8.5 pasaría la comprobación de
entorno. Pero Moodle solo documenta 8.4.x como soportada. «No da error» no es «está
soportado», y detrás hay datos personales de 2.869 funcionarios.

### El cambio que más rompe: la raíz web se movió a `public/`

Es el cambio estructural de Moodle 5.1/5.2, y no perdona:

```
/var/www/html/           raíz del repositorio. Acá va config.php
/var/www/html/public/    raíz web. DocumentRoot de Apache y valor de $CFG->dirroot
/var/www/html/admin/cli/ los scripts CLI, que NO se movieron
```

El `index.php` de la raíz **lanza una excepción a propósito** (`rootdirpublic`) si alguien
llega ahí: es la trampa para el DocumentRoot mal apuntado. Lo que eso obligó a cambiar en este
repositorio:

| Archivo | Cambio |
|---|---|
| `Dockerfile` | `sed` sobre el vhost por defecto para mover el DocumentRoot a `public/`; todos los `COPY` de plugins van a `public/<tipo>/` |
| `docker/apache-moodle.conf` | `<Directory /var/www/html/public>`, y la raíz pasa a `Require all denied` — ahí vive `config.php` con la clave de la base |
| `docker/config.php` | sigue en `/var/www/html/config.php`, la raíz. **No** dentro de `public/` |
| `docker/moodle-crontab` | **sin cambios**: `admin/cli/cron.php` se quedó en la raíz |
| `academia/cli/bootstrap.php` | **sin cambios**: carga `/var/www/html/config.php`, que no se movió |

Esa tabla es también el trabajo que le espera a `coipo_moodle` en octubre.

Se levanta con [db/setup_bd_v2.sql](../db/setup_bd_v2.sql) y
[.env.v2.example](../.env.v2.example), que traen el procedimiento completo.

**Y hay un paso nuevo antes de los scripts:** el clon sale con el esquema de 4.5.10 y la imagen
trae 5.2.1, así que hay que ejecutar `admin/cli/upgrade.php` y comprobar
`check_database_schema.php`. Está en [academia/README.md](../academia/README.md), paso 0.

Tres cosas más que conviene no descubrir en caliente:

- **`MOODLE_NOEMAILEVER=true` es obligatorio.** El clon trae los 2.869 correos institucionales
  reales. Sin ese freno, el cron de un entorno de pruebas le manda resúmenes de foro y avisos
  de contraseña a funcionarios de verdad — y llegarían dos veces, una por instancia.
- **El `CONNECTION LIMIT` es del rol, no de la base**, así que las dos instancias comparten un
  solo techo de 60. Por eso `MaxRequestWorkers` está en **6** en este repositorio y en 50 en
  producción: 50 + 6 + 2 crones = 58. Al promover v2, hay que devolverlo a 50.
- **Del `moodledata` se clona SOLO `filedir`, y por hardlink.** Es la única parte
  irremplazable: 11 de los 12 GB, y es el contenido de los PDF, los SCORM, las imágenes
  incrustadas y las entregas. Al ser contenido direccionado por SHA1 —Moodle nunca reescribe
  un archivo existente, solo crea o borra enlaces— compartir inodos es seguro y el clon cuesta
  casi nada en disco.

  Todo lo demás (`cache/`, `temp/`, `sessions/`, `trashdir/`, `lang/`) Moodle lo recrea solo, y
  copiarlo es peor que no hacerlo: caché rancia, sesiones ajenas, y sobre todo
  `climaintenance.html`, que si viaja en la copia deja a v2 respondiendo «under maintenance» a
  todo, healthcheck incluido.

  **Y el orden importa: primero el `pg_dump`, después el `cp -al`.** Al revés, un archivo
  subido en el intervalo queda referenciado en la base y sin contenido en disco. El
  procedimiento completo, con la comprobación de que no falta ningún `contenthash`, está en
  [.env.v2.example](../.env.v2.example).

  Lo que **no** hay que confundir: los cursos no están en `moodledata`. La estructura, las
  actividades, las matrículas, las calificaciones y el banco de preguntas están en la base.
  `filedir` solo guarda el contenido de los archivos a los que esas actividades apuntan.

---

## El modelo de datos

Se aplica con los scripts de [academia/](../academia/README.md). El detalle de cada elemento
está en los CSV de `academia/datos/`, con las decisiones comentadas al lado de la fila que
afectan.

### El principio que ordena el árbol

> Las categorías de una plataforma de formación no son un menú de navegación: son fronteras de
> propiedad y de permisos. La navegación se resuelve con atributos y filtros, no con carpetas.

De ahí se sigue todo lo demás. **No hay categorías por región, por año ni por financiador**:
eso son cohortes y campos. Meter la orgánica o el calendario en el árbol de carpetas es la
causa número uno de campus con cuatrocientas categorías y ningún dato consultable.

Y su corolario, que es la regla que evita que 35 cursos se conviertan en 300:

> **Una formación = un curso.** Las ediciones son cohortes y grupos dentro del mismo curso,
> nunca cursos nuevos.

Hoy ya hay un caso: «Curso C-111 Nivel 2» existe dos veces, como 1.ª edición en *Cursos CONAF*
y como 2.ª en *Cursos MBZ*, porque la edición se modeló como curso y el financiador como
categoría. El campo `financiamiento` y la familia de cohortes `TEMP-` existen exactamente para
que ese patrón no se multiplique por siete áreas.

### Las 7 áreas

Se crean **vacías y ocultas**. Es el método de árbol paralelo: la estructura nueva convive con
las 9 categorías actuales, no se mueve ni un curso, y el traslado es la Etapa 4 —octubre,
junto con el salto a la próxima versión de largo soporte, para intervenir la plataforma una
sola vez.

`01` Incendios Forestales · `02` Fiscalización y Legislación Forestal ·
`03` Fomento, Bosque Nativo y Restauración · `04` Desarrollo Institucional y Personas ·
`05` Áreas Silvestres Protegidas *(transitoria hasta marzo de 2027)* · `90` Transversal ·
`99` Gestión del Campus

### Los 8 campos

`area` · `nivel` · `perfil` · `modalidad` · `duracion` · `vigencia` · `financiamiento` ·
`estado`

Siete de los ocho son **listas cerradas**, nunca texto libre. Un campo libre produce
«Incendios», «incendios forestales», «IF» y «Prot. Incendios» en el mismo sistema, y con eso
ningún informe agrupa bien.

Y son obligatorios. Un curso con los campos vacíos queda invisible en el catálogo filtrable y
ausente de todo informe institucional: es el olvido más frecuente del estándar.

> Un detalle que muerde: `customfield_select` guarda un **índice entero**, no el texto de la
> opción. Pasarle la etiqueta la convierte en 0 y el campo queda vacío, sin error y sin aviso.
> Por eso los scripts hablan en etiquetas y convierten a índice al escribir.

### Las 28 cohortes

Tres familias independientes: 16 territoriales `REG-NN`, 7 ocupacionales `PERF-XXXX` y 3 de
temporada `TEMP-AAAA-AA`. Cada persona pertenece a una de cada familia, y **al cruzarlas se
obtiene cualquier segmento sin crear cohortes nuevas** — por eso no existe «brigadistas del
Biobío 2026»: eso es el cruce de tres, no una cuarta.

Más 2 funcionales que los cursos de este repositorio exigen y que el modelo original no
contempla: `GER-IF-PROF` y `PERF-AUTOR`. Ver las decisiones abiertas.

### Los 2 marcos de competencias

`MARCO-IF` con 4 unidades (las tres mallas más Fundamentos Técnicos Comunes) y 10
competencias; `MARCO-TR` con la de producción de cursos. Escala de logro de tres valores: *No
demostrada · En desarrollo · Demostrada*, con la última marcada como competente.

> Sin competencias, la plataforma responde qué cursos hizo una persona. Con competencias,
> responde **qué está habilitada para hacer, y desde cuándo hasta cuándo**. La segunda es la
> pregunta que importa cuando ocurre algo.

La correspondencia con NFPA 1051 / NWCG va dentro de la descripción de cada competencia:
Moodle no tiene campos personalizados en competencias. Es una solución de segunda —no se puede
filtrar ni agrupar por ella— y está anotada como tal.

### El rol Gestor de Área Temática

Es la pieza que desbloquea la expansión. Hoy no existe un permiso intermedio: o se es
administrador de todo el sitio, o no se puede publicar nada. Por eso ninguna otra gerencia ha
podido usar la plataforma.

Lo que hace que funcione **no son las capacidades**: es que el rol solo se puede asignar a
nivel de **categoría**. El alcance del permiso ES la frontera del área.

```
set_role_contextlevels($idrol, [CONTEXT_COURSECAT]);
```

Si en esa lista aparece `CONTEXT_SYSTEM`, alguien puede asignarlo globalmente y el gestor pasa
a ver las siete áreas. Es la única línea del script cuya alteración desarma el diseño entero,
y `99_verificar.php` la comprueba explícitamente.

Las capacidades recortadas van en `CAP_PROHIBIT` y no en `CAP_PREVENT`, a propósito: *prohibit*
no se puede revertir con una anulación local. Para un rol cuyo sentido es delimitar un área,
que el límite sea negociable caso a caso lo vacía.

---

## Las vistas

### El catálogo y el tablero son informes nativos

El generador de informes de Moodle 4.5 expone los campos personalizados de curso como columnas
**y como filtros**, automáticamente
([reportbuilder/classes/local/entities/course.php:84](../Moodle/Moodle/moodle/reportbuilder/classes/local/entities/course.php#L84)
usa el helper `custom_fields`). Verificado en el código, no supuesto: el catálogo filtrable de
la pantalla 02 no necesita ningún plugin.

`70_informes.php` crea tres: *Catálogo de cursos* (audiencia: todo el sitio), *Gestión — cursos
por área y estado* y *Gestión — dotación por cohorte* (audiencia: rol Gestor de Área).

El tablero no es para todo el mundo: muestra la brecha de formación de personas concretas, y
con 2.869 funcionarios reales detrás eso es información de gestión.

**Un límite honesto.** El generador da tabla, filtros, ordenamiento y descarga. **No** da las
tarjetas ni los gráficos de barras del prototipo: esas pantallas son ilustrativas. Para un
tablero con esa factura, el propio prototipo señala el camino correcto —réplica de solo lectura
de PostgreSQL conectada a Power BI, por la UIA— y queda fuera de este repositorio.

Y una advertencia que vale más que el informe: **si el catálogo sale vacío, el problema no es
el informe: son cursos sin clasificar.** La reportería es 20 % herramienta y 80 % modelo de
datos.

### El tema

`theme_academia` es un **hijo de Boost**: hereda plantillas, disposiciones y renderers, y solo
aporta variables de SCSS y unas pocas reglas. No copia el preset ni las layouts — un preset
copiado se queda en la versión de Bootstrap del día que se copió, y en la siguiente
actualización de Moodle el sitio sale con la mitad de los estilos rotos.

Eso ya se cobró su primer dividendo: pasar de Boost 4.5 a Boost 5.2 no requirió tocar una sola
línea del tema. Compila igual, y el CSS creció de 750 KB a 988 KB porque creció Bootstrap.

Lleva la paleta institucional (`#143A73`, `#1E5FA8`, `#2C8A3C` y los tres de estado) y las
reglas del estándar que son responsabilidad del tema y no del autor:

- cuerpo de 16 px mínimo e interlineado 1,6 — el contenido actual está medido en 1,2, por
  debajo del mínimo que pide la norma;
- alineación siempre a la izquierda: el contenido heredado viene justificado **y** centrado,
  las dos cosas que la pantalla 04 identifica como perjudiciales para la lectura;
- largo de línea entre 45 y 75 caracteres;
- foco de teclado visible, que algunos componentes de Bootstrap apagan;
- enlaces subrayados, no solo de color;
- tablas de informe con su propio scroll, para que la página nunca se desplace de lado.

Los tres colores de estado se usan **siempre con ícono y palabra, nunca color solo**. No es
preferencia estética: es el criterio 1.4.1 de WCAG 2.2, exigible por el Decreto N.º 1, la Ley
21.180 y la Ley 20.422.

**Lo que el tema no puede arreglar:** no alcanza dentro de un SCORM ni de un H5P, que viajan
con su propio CSS. El paquete actual —6.314 reglas, cero *media queries*, cero de ocho imágenes
con texto alternativo— se corrige en el paquete.

---

## Los dos cursos

Quedan como **esqueletos**: ficha con los 8 campos, secciones, cuestionarios con su
configuración exacta, banco de preguntas cargado, cadena de restricciones, certificado con su
vigencia, competencia vinculada y matrícula por cohorte. **Sin el contenido H5P**, que lo
produce el autor y, en IF-151, lo firma un especialista del Departamento de Protección contra
Incendios Forestales.

### Los dos instrumentos de evaluación no se configuran igual

Es la parte del diseño de IF-151 que más cuesta y la que más se equivoca.

| | Evaluación de lección | Evaluación final |
|---|---|---|
| Para qué es | **Enseñar** | **Decidir** si se emite el certificado |
| Ítems | 5 fijos | 16 al azar de un banco reservado de 25 |
| Umbral | 4 de 5 | 13 de 16 |
| Intentos | ilimitados | 3, con 24 h de espera |
| Tiempo | sin límite | 40 minutos |
| Revisión | inmediata y por opción | **solo el puntaje, hasta cerrar** |

La última fila es la crítica. Si la revisión completa queda habilitada «inmediatamente después
del intento», **el primer intento le enseña al participante las respuestas del segundo** y el
examen deja de medir. El examen sigue funcionando; solo deja de servir para nada.

Moodle guarda las cuatro ventanas de revisión en un solo entero con un bit por ventana, y
escribir ese número a mano es la forma más rápida de cometer ese error sin notarlo. Por eso
`lib_quiz.php` lo arma con una función que recibe cuatro booleanos con nombre.

Y por qué 16 ítems y no 3, que era la configuración inicial: con 3 ítems las notas posibles son
0, 33, 67 y 100, así que un umbral de «80 %» era en realidad 100 % —una sola respuesta mala
reprobaba—. Además, tres ítems no miden con fiabilidad, y acá se habilita un certificado de
competencia con 60 meses de vigencia.

### El banco de 60 preguntas

Está generado desde el Anexo A del documento de diseño, no escrito a mano:
`academia/herramientas/convertir-banco-IF-151.py`. Son 60 ítems con 180 opciones y 180
retroalimentaciones; copiados a mano, los errores no se ven —una opción correcta marcada mal
produce un examen que reprueba a quien sabe—. El conversor comprueba las cuentas contra la
tabla de especificaciones del propio documento y se niega a escribir nada si alguna no cuadra.

Las categorías `Lección N / Formativa` y `Lección N / Certificación` están separadas por una
razón: **los ítems C no se agregan nunca a un cuestionario de lección.** Si aparecen antes,
dejan de medir.

---

## Decisiones abiertas

1. **Las cohortes ocupacionales no coinciden con el campo `perfil`.** El campo trae
   `Todo el personal`; las cohortes traen `PERF-ADM · Administrativo y apoyo`. La pantalla 13
   afirma que «coinciden exactamente»: no coinciden. Son siete y siete, pero no las mismas
   siete. Mientras no se alineen, comparar «quién debía tomar el curso» contra «quién lo tomó»
   no funciona para esos dos valores.
2. **`GER-IF-PROF` y `PERF-AUTOR` no están en el modelo de datos** y los dos cursos las exigen.
   Están creadas como una cuarta familia, `funcional`. De la decisión depende que el total sea
   26 —lo que dice el Anexo C.2— o 28.
3. **Las bandas numéricas de código de curso.** «Es una decisión de dos minutos hoy y un
   problema serio en tres años si no se toma.»
4. **Las correspondencias NFPA/NWCG son una propuesta**, no doctrina. Ningún documento de
   INSUMO_MEJORA trae el mapeo hecho.
5. **Los cuatro cursos de prueba publicados en producción.** La propuesta de clasificación los
   detecta y los marca; qué se hace con ellos —archivar o borrar— es decisión del Gestor de
   Área.
6. **TR-104 va con auto-matriculación restringida, no con sincronización de cohorte**, porque
   es catálogo abierto. Falta decidir qué cohorte la habilita.

---

## Verificación

`99_verificar.php` cuenta todo y lo compara con lo esperado, línea por línea. Devuelve 0 solo
si todo cuadra.

Pero contar es la mitad. Lo que ninguna automatización reemplaza, y que el propio script
imprime al final:

1. Entrar por navegador y abrir un PDF de un curso → confirma que `moodledata` se **lee**.
2. Subir un archivo y encontrarlo en el `filedir` del host → confirma que se **escribe** fuera
   del contenedor, y con el dueño correcto.
3. Abrir el catálogo, aplicar un filtro de área y **ver que la lista cambia**.
4. Entrar con una cuenta que tenga rol Gestor de Área **solo en la categoría 01**, y comprobar
   que puede crear un curso en 01 y que **no ve** los de 04. Si ve los de 04, la delegación no
   está implementada por muy creado que esté el rol.
5. Entrar a IF-151 como estudiante: la lección 2 tiene que aparecer **bloqueada**.
6. Recorrer el catálogo y una lección en un **teléfono real**, no en el emulador.
7. Comprobar que `coipo_moodle` en el 8115 sigue respondiendo 200 y su `mdl_course` no cambió.

---

## Al promover v2 a producción

0. **Decidir contra qué versión se promueve.** Si es octubre o después, conviene subir primero
   v2 de 5.2 a **5.3 LTS** y promover eso: 5.3 tiene soporte de seguridad hasta el 1 de
   octubre de 2029, y 5.2 hasta el 4 de octubre de 2027. El paso 5.2 → 5.3 es corto.
1. `MaxRequestWorkers` de vuelta a **50** en `docker/apache-moodle.conf`.
2. `MOODLE_WWWROOT=https://academia.conaf.cl` y **`MOODLE_SSLPROXY=true`** — las dos siempre
   juntas. Con `https://` y `sslproxy=false`, Moodle genera las URLs de sus recursos con
   `http://` dentro de una página servida por `https://`, el navegador las bloquea por
   contenido mixto y el sitio aparece sin CSS ni JS, sin ningún error visible.
3. Purgar cachés y recompilar el CSS del tema: las URLs quedan incrustadas en el CSS cacheado.
4. Cerrar en producción los dos hallazgos críticos que v2 no toca: respaldos automáticos y
   correo saliente.
5. Publicar las áreas (`visible = 1`) recién cuando empiece la Etapa 4.
6. Apagar la instancia vieja y liberar `academia_v2`.
