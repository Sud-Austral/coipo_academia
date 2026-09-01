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

## La Academia corre en paralelo con el campus actual, y no hereda nada de él

No es la evolución del campus: es un sitio distinto que empieza vacío. Convive con él en el
mismo servidor y va por delante en versión, pero **no trae ni un usuario, ni un curso, ni un
archivo**.

```
172.31.2.41
├── coipo_moodle    8115   academia_prod   Moodle 4.5.10 · PHP 8.3
│   el campus actual: 2.869 usuarios, 37 cursos · archivo histórico, NO SE TOCA
└── coipo_academia  8116   academia_v2     Moodle 5.2.1  · PHP 8.4
    esta · instalación limpia, sin un solo usuario ni curso heredado
```

**Decidido el 31-08-2026.** Hasta ese día la Academia iba a ser un clon de `academia_prod`
—`pg_dump` completo y `filedir` por hardlink— y este documento estaba escrito entero sobre ese
supuesto. Ya no: instalación limpia con `install_database.php`, un puñado de cuentas nominadas
de CONAF para construir y probar, y contenido nuevo sobre la plantilla maestra GC-000. Qué se
hace con los 2.869 usuarios y los 37 cursos del campus es una decisión aparte, y está abierta.

**Por qué 5.2, y por qué ahora.** Decidido el 01-09-2026: se instala **5.2.1 ya**, sin esperar
a 5.3 LTS. 5.2 es la rama estable y soportada del día que se instala —seguridad hasta el 4 de
octubre de 2027—, y PHP 8.4 está en soporte activo hasta diciembre de 2026; **8.3 salió de
soporte activo el 31 de diciembre de 2025** y solo recibe parches de seguridad. Los dos
números de PHP están comprobados contra el `public/admin/environment.xml` del propio tag
v5.2.1: `<PHP version="8.3.0" level="required">` y ni un `<RESTRICT>` en el bloque de 5.2.

**La razón que este documento daba antes ya no existe:** «el salto 4.5 → 5.x se ensaya en un
clon desechable antes de tocar producción». No hay clon, no hay salto, y la Academia se
instala limpia con `install_database.php` — no actualiza nada, así que no hay nada que
ensayar. El campus actual actualizará cuando le toque o no actualizará nunca, según lo que se
decida hacer con él, y esa es otra conversación.

La razón que queda es más simple, y es la que vale: esperar a octubre dejaba cinco semanas de
construcción detenida a cambio de nada, porque de todos modos hay que armar el sitio antes de
que la versión importe.

**Y en octubre de 2026 se sube a 5.3 LTS.** Ya no es una pregunta abierta: es trabajo
planificado, y está en «Al poner la Academia en servicio», ítem 0. Para entonces será un
upgrade de verdad —con cursos, cohortes y competencias adentro—, no un `install_database.php`
sobre una base vacía. Sale barato justamente porque el sitio está armado con los scripts
idempotentes de `academia/cli`: lo que el upgrade descoloque se vuelve a correr.

**Por qué no PHP 8.5.** Moodle 5.2 no tiene ningún bloqueo superior de PHP —verificado en su
`environment.xml`: mínimo 8.3.0 y ni un `<RESTRICT>`—, así que 8.5 pasaría la comprobación de
entorno. Pero Moodle solo documenta 8.4.x como soportada. «No da error» no es «está
soportado», y este sitio va a terminar con datos de funcionarios adentro aunque hoy esté
vacío.

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

**Y hay un paso 0 antes de los scripts:** la base `academia_v2` nace vacía, así que hay que
instalar Moodle con `admin/cli/install_database.php` y comprobar `check_database_schema.php`.
No es un upgrade: no hay nada que actualizar. Está en
[academia/README.md](../academia/README.md), paso 0.

Tres cosas más que conviene no descubrir en caliente:

- **`MOODLE_NOEMAILEVER=true` sigue siendo obligatorio, por otra razón.** Ya no protege los
  2.869 buzones del campus —acá no están—, pero un sitio en construcción manda avisos de
  matrícula, de foro y de cambio de contraseña a las cuentas nominadas que lo están armando, y
  cada una de esas cuentas es una dirección institucional real. Se apaga cuando la Academia
  tenga usuarios de verdad y SMTP decidido, no antes.
- **El `CONNECTION LIMIT` es del rol, no de la base**, así que las dos instancias comparten un
  solo techo de 60 que el 31-08-2026 se decidió no volver a ampliar. Por eso `MaxRequestWorkers`
  está en **6** acá y en 50 en el campus: 50 + 6 + 2 crones + 2 de margen = 60. El 6 dejó de ser
  provisorio, y ahí está lo que hay que entender: como el campus no se apaga nunca, subir este
  número exige que alguien baje antes el del OTRO repositorio, y en ese orden. El reparto de
  destino y por qué el orden importa están en `docker/apache-moodle.conf`.
- **El `moodledata` de la Academia nace vacío.** No se clona `filedir` ni nada más: esos 11 GB
  son el contenido de los 37 cursos que se descartaron, y traerlos sería arrastrar justamente
  lo que la decisión del 31-08-2026 dejó fuera. La carpeta se crea con dueño UID 33 y Moodle la
  puebla solo, empezando por el primer archivo que alguien suba a GC-000.

  Con eso desaparece el `cp -al` y desaparece su riesgo: ya no hay un solo inodo compartido
  entre la Academia y el campus, así que nada de lo que se haga acá puede tocar los archivos
  del 8115. Y desaparece el orden `pg_dump` antes que `cp -al`, que existía para que no
  quedaran archivos referenciados en la base y ausentes del disco.

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

En el campus actual hay un caso: «Curso C-111 Nivel 2» existe dos veces, como 1.ª edición en *Cursos CONAF*
y como 2.ª en *Cursos MBZ*, porque la edición se modeló como curso y el financiador como
categoría. El campo `financiamiento` y la familia de cohortes `TEMP-` existen exactamente para
que ese patrón no se multiplique por siete áreas.

### Las 7 áreas

Se crean **vacías pero VISIBLES**. El método de árbol paralelo —que las quería ocultas para no
competir con las 9 categorías viejas— murió con la decisión del 31-08-2026: la Academia se
instala de cero y estas 7 áreas son la única estructura que existe.

Y hay una razón más dura para no ocultarlas, escrita en `academia/datos/categorias.csv`: **una
categoría oculta oculta también los cursos que viven dentro**. Con `visible=0`, GC-000, IF-151
y TR-104 —lo único que este repositorio construye— quedarían invisibles para cualquiera que no
sea administrador, y el error es silencioso: nadie ve un mensaje, sencillamente no hay nada.
Lo que se publica curso por curso es el CURSO, no el árbol.

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

El tablero no es para todo el mundo: muestra la brecha de formación de personas concretas. Hoy
la Academia tiene un puñado de cuentas de construcción y eso no se nota; el día que tenga la
dotación detrás es información de gestión, y la audiencia acotada al rol Gestor de Área es lo
que impide que deje de serlo.

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
5. **Qué se hace con los 2.869 usuarios y los 37 cursos del campus actual.** La decisión del
   31-08-2026 los dejó fuera de la Academia y no dijo qué pasa después: si alguna vez se migran
   las personas y con qué criterio, qué ocurre con el historial de certificaciones ya emitidas,
   y hasta cuándo sigue encendido el 8115. Es la decisión más grande que queda abierta y no la
   resuelve este repositorio.
6. **TR-104 va con auto-matriculación restringida, no con sincronización de cohorte**, porque
   es catálogo abierto. Falta decidir qué cohorte la habilita.

---

## Verificación

`99_verificar.php` cuenta todo y lo compara con lo esperado, línea por línea. Devuelve 0 solo
si todo cuadra.

Pero contar es la mitad. Lo que ninguna automatización reemplaza, y que el propio script
imprime al final:

1. Subir un archivo a GC-000 y encontrarlo en el `filedir` del host → confirma que
   `moodledata` se **escribe** fuera del contenedor, y con el dueño correcto.
2. Volver a abrirlo desde el navegador → confirma que se **lee**. El orden se dio vuelta a
   propósito: en un sitio recién instalado no hay ningún PDF heredado que abrir, así que
   primero hay que poner uno.
3. Abrir el catálogo, aplicar un filtro de área y **ver que la lista cambia**.
4. Entrar con una cuenta que tenga rol Gestor de Área **solo en la categoría 01**, y comprobar
   que puede crear un curso en 01 y que **no ve** los de 04. Si ve los de 04, la delegación no
   está implementada por muy creado que esté el rol.
5. Entrar a IF-151 como estudiante: la lección 2 tiene que aparecer **bloqueada**.
6. Recorrer el catálogo y una lección en un **teléfono real**, no en el emulador.
7. Comprobar que `coipo_moodle` en el 8115 sigue respondiendo 200 y su `mdl_course` no cambió.

---

## Al poner la Academia en servicio

Ya no es «promover un clon»: no hay nada que reemplazar. El campus actual sigue vivo en el
8115 con sus 37 cursos, y la Academia entra en servicio cuando tenga contenido, cuentas y un
nombre por el que llegar. Lo que sí está decidido:

0. **Subir a 5.3 LTS — octubre de 2026, ya decidido.** No es «decidir contra qué versión se
   entra en servicio»: eso se resolvió el 01-09-2026 instalando 5.2.1 sin esperar. Lo que
   queda es el upgrade, y conviene hacerlo **antes** de entrar en servicio: 5.3 tiene soporte
   de seguridad hasta el 1 de octubre de 2029 y 5.2 solo hasta el 4 de octubre de 2027. El
   paso es corto, pero para entonces ya habrá cursos adentro y por eso el orden importa:
   respaldo de `academia_v2` y del `moodledata` primero, después `admin/cli/upgrade.php`,
   después `check_database_schema.php` → `Database structure is ok.` Es el primer upgrade de
   verdad de este sitio; el paso 0 de instalación no lo fue.
1. `MaxRequestWorkers` a **16**, no a 50, y solo DESPUÉS de que alguien haya bajado a **36** el
   del repositorio `coipo_moodle`. El campus del 8115 no deja de competir por el
   `CONNECTION LIMIT` de 60 del rol: convive de forma permanente, y el 31-08-2026 se decidió no
   pedir más conexiones ni crear un rol aparte. Por eso el orden no es negociable: si se sube
   este primero, la ventana entre los dos despliegues suma 68 sobre 60 y el error 500 le toca al
   campus, que es el que tiene gente adentro. El reparto completo, la regla que lo recalcula y
   la consulta que avisa antes están en `docker/apache-moodle.conf`.
2. `MOODLE_WWWROOT` con el dominio que se decida y **`MOODLE_SSLPROXY=true`** — las dos siempre
   juntas. Con `https://` y `sslproxy=false`, Moodle genera las URLs de sus recursos con
   `http://` dentro de una página servida por `https://`, el navegador las bloquea por
   contenido mixto y el sitio aparece sin CSS ni JS, sin ningún error visible.
3. Purgar cachés y recompilar el CSS del tema: las URLs quedan incrustadas en el CSS cacheado.
4. **Respaldos de esta base y de este `moodledata`.** Antes esto decía que era problema de
   producción, porque un clon desechable no vale la pena respaldar. Dejó de serlo el
   31-08-2026: acá va a estar el único ejemplar del contenido nuevo y nadie más lo tiene.
5. Publicar las áreas (`visible = 1`) a medida que cada una tenga cursos.

Y lo que **no** está decidido, que es de fondo:

- **Por qué nombre se llega a la Academia.** `academia.conaf.cl` apunta hoy al campus actual. O
  la Academia toma ese nombre y el campus pasa a otro, o la Academia estrena uno propio. De eso
  depende `MOODLE_WWWROOT` y no lo resuelve este documento.
- **Qué pasa con el campus actual y cuándo.** Apagarlo se lleva por delante el historial de
  certificaciones de 2.869 personas si antes nadie decidió qué hacer con él.
- **Si `academia_v2` sigue llamándose así** cuando ya no es la versión 2 de nada, sino el sitio
  definitivo. Renombrar la base cuesta menos ahora que con contenido dentro.
