# Guía operativa — cómo se hace un curso en la Academia

Este documento existe porque no existía. Tres scripts del repositorio remiten a «la guía
operativa de la Academia» —`80_plantilla_maestra.php`, `50_rol_gestor.php` y
`90_cursos_esqueleto.php`— y esa guía sólo vivía como la pantalla 14 de un prototipo HTML de
presentación. `academia/README.md` son 240 líneas sobre **cómo se instala** la Academia y
cero sobre **cómo se usa**.

Si no te quedaba claro cómo hacer un curso, no era cosa tuya: no estaba escrito.

---

## La regla, y es una sola

> «La regla que ahorra el 80 % del trabajo: **nunca crees un curso desde cero**. Siempre
> duplica la plantilla maestra, que ya trae la estructura, la configuración de finalización,
> el certificado y los textos de ayuda. **Tu trabajo es reemplazar contenido, no construir
> andamios.**»
>
> — `academia/cli/80_plantilla_maestra.php:4-8`, citando el prototipo, pantalla 14

Todo lo demás de este documento es el desarrollo de esa frase.

**GC-000 no es un curso: es un molde.** Vive oculto y con estado «Archivado» en la categoría
`99 Gestión del Campus`. Nadie lo cursa. Sólo se duplica.

---

## Antes de empezar: qué tiene que estar corrido

El orden no es capricho. `95_ajustes_sitio.php` enciende `enablecompletion` y
`enableavailability`, y los scripts de curso **se niegan a correr sin eso**
(`lib_cursos.php:35-49`) porque los campos de finalización **no se pueden rellenar hacia
atrás**: hay que borrar el curso y empezar de nuevo.

| # | Script | Sin él |
|---|---|---|
| 95 | `95_ajustes_sitio.php` | 80 y 90 abortan antes de escribir nada |
| 10 | `10_categorias.php` | no existe la categoría `99` y crear el curso lanza excepción |
| 20 | `20_campos_curso.php` | **el curso nace sin clasificar y nadie lo nota** |
| 30 | `30_cohortes.php` | no hay cohortes con que matricular |
| 50 | `50_rol_gestor.php` | no existe el rol Gestor de Área |
| 80 | `80_plantilla_maestra.php` | no hay plantilla, y la regla central es letra muerta |

`20` tiene que correr **antes de que exista el curso**. Un curso creado antes que sus campos
nace sin clasificar en silencio (`20_campos_curso.php:10-15`).

Comprobar dónde estás:

```bash
cd /opt/apps/coipo_academia
docker compose exec -u www-data app php /opt/academia/cli/99_verificar.php
```

Tiene que decir: 7 áreas, 2 subcategorías, 8 campos, rol `gestorarea` existente y asignable
**sólo en categoría**, 3 informes, y GC-000 con sus actividades.

> **Siempre `-u www-data`.** Como root, todo lo que Moodle escriba en `moodledata` queda con
> dueño root y el sitio deja de poder escribir. Es el error más común del proyecto y el más
> confuso, porque el síntoma aparece días después.

---

## Qué trae GC-000, exactamente

Conviene saberlo, porque es lo que **no** vas a tener que hacer en cada curso.

### 4 secciones

| | Sección | Para qué |
|---|---|---|
| 0 | Antes de empezar | Instrucciones para el autor. **Se borra al terminar el curso** |
| 1 | Presentación | |
| 2 | Lección 1 · (reemplazar por el título) | **Ésta es la que se duplica**, una vez por lección |
| 3 | Certificación | El certificado se condiciona a la evaluación final, no a la finalización del curso |

### 7 actividades, con su finalización ya configurada

**Sección 1** — Página de presentación (`GC000-PRESENTACION`, finalización automática por
ver) · Foro de consultas al instructor (`GC000-FORO`, suscripción opcional, finalización
manual) · Carpeta «Documentos de base» (`GC000-MATERIAL`, automática por ver).

**Sección 2** — Página «Contenido interactivo de la lección 1» (`GC000-L1-CONTENIDO`) — es un
**marcador**, existe sólo porque un H5P vacío no se puede crear por API sin un archivo
`.h5p` · Cuestionario formativo (`GC000-L1-EVALUACION`).

**Sección 3** — Cuestionario de certificación (`GC000-FINAL`) · Certificado
(`GC000-CERTIFICADO`, finalización manual).

### Los dos cuestionarios

| | Nota máxima | Aprueba con | Intentos | Tiempo | Espera |
|---|---|---|---|---|---|
| Formativo de lección | 5,0 | 4,0 | ilimitados | — | — |
| Certificación | 16,0 | 13,0 | 3 | 40 min | 24 h |

**Nacen vacíos** (`sumgrades=0`). Las preguntas se meten curso por curso.

### 3 restricciones encadenadas

1. La evaluación de la lección exige haber **completado** el contenido (basta marcarlo).
2. La evaluación final exige **80 %** en la evaluación de la lección.
3. El certificado exige **80 %** en la evaluación final.

Las tres se guardan con `showc=true`, así que la actividad bloqueada aparece **en gris con el
motivo** en vez de desaparecer: es lo que hace que el alumno entienda el itinerario.

> **El valor es PORCENTAJE (0-100), no la nota bruta.** Poner 4 pensando en «4 de 5» produce
> una restricción que se cumple con el 4 %, y el curso deja pasar a todo el mundo sin que
> nadie lo note.

---

## El ciclo de un curso, paso a paso

### 1 · Quién lo crea

El **Gestor de Área Temática** sí puede crear cursos: `moodle/course:create` está explícito en
`50_rol_gestor.php:120`. Lo que lo delimita **no son sus capacidades sino su contexto**:

```php
set_role_contextlevels($idrol, [CONTEXT_COURSECAT]);   // 50_rol_gestor.php:69
```

Sólo se puede asignar **sobre una categoría**, y esa es la frontera del área. Si ahí
apareciera `CONTEXT_SYSTEM`, el gestor vería las siete áreas y el diseño entero se desarma.

**No puede** (y va en `CAP_PROHIBIT`, no `CAP_PREVENT`, para que no se pueda revertir):
crear o borrar categorías · crear o editar cohortes · cambiar quién pertenece a una cohorte ·
crear, editar o borrar usuarios · cargar usuarios masivamente · tocar roles, competencias o
la configuración del sitio.

> ### Hoy no hay ningún gestor nombrado
>
> `50_rol_gestor.php:190-192` lo dice explícito: el rol **se crea vacío de personas** y
> designarlo es una decisión institucional. O sea: **hoy el único que puede crear el primer
> curso eres tú, como administrador.** No esperes a que haya gestores.

Asignar el rol: categoría del área → **Más** → Permisos → Asignar roles → Gestor de Área
Temática.

### 2 · Dónde

**La categoría es el área, y nada más.** La regla está escrita en la descripción del propio
campo: el área «debe coincidir con la categoría en que vive el curso»
(`campos-curso.csv:32`).

`01` Incendios Forestales · `02` Fiscalización y Legislación Forestal · `03` Fomento, Bosque
Nativo y Restauración · `04` Desarrollo Institucional y Personas · `05` Áreas Silvestres
Protegidas (transitoria hasta marzo 2027) · `90` Transversal · `99` Gestión del Campus.

Sólo se baja a subcategoría si un documento de diseño la exige. Hoy existen dos: `01-FTC` y
`90-AUT`. **No se inventan más**: cada subcategoría es una frontera de permisos, y las que no
tienen dueño sobran.

No hay categorías por región, por año ni por financiador. **Eso son cohortes y campos.**

### 3 · Averiguar el id de GC-000

Volver a correr el script que la creó. Es idempotente: si ya existe, no toca nada e imprime
el id.

```bash
docker compose exec -u www-data app php /opt/academia/cli/80_plantilla_maestra.php
```

```
= ya estaba GC-000   id 41
La plantilla ya existía y NO se toca: puede tener trabajo hecho encima.
```

**No le pongas `--dry-run`**: esa rama imprime `= ya estaba GC-000` **sin** el id.

### 4 · Duplicar

```
https://academia2.conaf.cl/backup/copy.php?id=<id de GC-000>
```

Por menú: entrar a GC-000 → **Más** → Reutilización de curso → **Copiar curso**.

> La redacción «Configuración → Duplicar curso» que trae `80_plantilla_maestra.php:14` es de
> Moodle 3.x/4.0 y **ya no corresponde**. La URL directa es lo único garantizado; confirma la
> ruta de menú la primera vez que la uses y corrige este documento.

En el formulario: nombre completo, **nombre corto (el código)**, categoría del área,
**visibilidad = Ocultar**, fecha de inicio y número ID.

> **La copia no aparece cuando aprietas el botón.** Es asíncrona: encola una tarea adhoc que
> ejecuta el cron, que acá corre cada minuto. Si no la ves, espera; no la vuelvas a lanzar.

### 5 · RECLASIFICAR — primer paso después de duplicar, no el último

```
https://academia2.conaf.cl/course/edit.php?id=<id del curso nuevo>
```
→ sección **Clasificación institucional**.

> ### La trampa que domina todo lo demás
>
> Al duplicar **se copian los 8 campos de la plantilla**. El curso nuevo nace con
> `área = 99 Gestión del Campus`, `estado = Archivado`, `duración = 1 hora`,
> `vigencia = 0`.
>
> No queda **sin** clasificar: queda **mal** clasificado, que es peor, porque el catálogo lo
> muestra como si alguien lo hubiera decidido. Y `99_verificar.php` **no lo detecta** — su
> comprobación mira que el campo `area` esté completo, y lo está: con el valor equivocado
> (`99_verificar.php:150-151`).

Los 8 campos y sus valores:

| Campo | Obligatorio | Valores |
|---|---|---|
| **área** | sí | las 7 categorías de arriba |
| **nivel** | sí | Básico · Intermedio · Avanzado · Especialización |
| **perfil** | sí | Brigadista · Jefe de cuadrilla · Guardaparque · Fiscalizador · Profesional · Jefatura · Todo el personal |
| **modalidad** | sí | e-learning autoinstruccional · Blended · Presencial con soporte |
| **duración** | sí | 1 a 200 **horas cronológicas**, no minutos de contenido |
| **vigencia** | sí | 0 a 60 meses. `0` = no vence. Previstos: 0, 12, 24, 36, 60 |
| **financiamiento** | **no** | CONAF · MBZ · KIZUNA-JICA · Otra cooperación internacional |
| **estado** | sí | Archivado · Piloto · Vigente |

### 6 · El código

`<PREFIJO>-<NNN>`, por ejemplo `IF-151`, `TR-104`, `GC-000`. **El primer dígito repite el
nivel**: 1xx básico, 2xx intermedio, 3xx avanzado.

**Dos cosas siguen sin decidir** y bloquean el nombre del primer curso nuevo:

- **Qué prefijo de letras le toca a cada área.** `IF`, `TR` y `GC` existen como hechos
  consumados, no como regla. No hay ninguna tabla.
- **Las bandas de tres dígitos.** El diseño de IF-151 propone 101-149 Malla Estándar,
  150-179 Fundamentos Técnicos Comunes, 201-249 Intermedia, 301-349 Avanzada — pero son
  bandas **dentro del área 01** y siguen abiertas en tres archivos a la vez, con la misma
  frase: *«es una decisión de dos minutos hoy y un problema serio en tres años si no se
  toma»*.

### 7 · Matricular

**Primero se llena la cohorte, después se sincroniza al curso.** Hoy las 28 están vacías.

Para llenarla hay dos caminos, y **ninguno es «Subir cohortes»** — esa pantalla *crea*
cohortes, no agrega miembros:

- **A mano**: Usuarios → Cohortes → icono **Asignar** (`/cohort/assign.php?id=<id>`)
- **Masivo**: Administración del sitio → Usuarios → **Subir usuarios**
  (`/admin/tool/uploaduser/index.php`), con una columna `cohort1` cuyo valor es el
  **IDNUMBER** de la cohorte (`REG-08`, `PERF-FISC`…). Este camino es **sólo del
  administrador**: `moodle/site:uploadusers` está prohibida al gestor.

> **Un typo en `cohort1` crea una cohorte nueva en vez de fallar.** Revisa el informe de la
> carga antes de darla por buena.

Después, en el curso: Participantes → Métodos de matriculación → Añadir método →
**Sincronización de cohorte**.

### 8 · Asignar al autor

El gestor asigna al autor como **Profesor (editingteacher)**: Participantes → Inscribir
usuarios.

El gestor sólo puede ofrecer **editingteacher, teacher y student**. No incluye `manager` ni
`gestorarea`, y la razón está escrita: *un gestor que puede nombrar a otro gestor puede darse
a sí mismo otra área, y la frontera deja de existir*.

### 9 · El autor reemplaza contenido

Reemplazar la Página «Contenido interactivo de la lección 1» por una actividad H5P. Duplicar
la sección de lección tantas veces como lecciones tenga el curso.

Los textos de ayuda que trae la plantilla —y que el autor **borra al terminar**— están en
`80_plantilla_maestra.php:290-366`:

- **Los cinco momentos de la lección**: gancho · idea en menos de 25 palabras · desarrollo ·
  práctica con consecuencia · algo para llevar.
- **Una lección enseña una sola cosa**, de 5 a 12 minutos.
- **La escala de interactividad**: ninguna lección puede quedarse en nivel 0 o 1; toda
  lección de seguridad operacional llega a nivel 3 al menos una vez.
- **Forma**: 16 px, interlineado 1,5, alineación a la izquierda **siempre**, texto
  alternativo en toda imagen, video de máximo 3 minutos, revisar en un teléfono real.

**Dos cosas que la plantilla ya trae y no hay que rehacer**: la finalización de actividad y
la cadena de restricciones.

### 10 · Publicar

Son **dos cosas distintas** y ninguna reemplaza a la otra:

- `visible` controla si la gente puede **entrar** al curso.
- el campo `estado` controla cómo aparece en el **catálogo**.

Y el catálogo **no es el listado de cursos de Moodle**: es un informe del generador nativo
—«Academia · Catálogo de cursos»— cuyas columnas y filtros **son** los 8 campos
personalizados. Por eso clasificar no es opcional:

> «Si el catálogo sale vacío, el problema no es el informe: son cursos sin clasificar. La
> reportería es 20 % herramienta y 80 % modelo de datos.»
> — `70_informes.php:222-226`

Flujo: terminado el curso → `estado = Piloto` → avisar al Gestor de Área → si pasa la
revisión → `estado = Vigente` **y** Configuración → Visibilidad del curso → **Mostrar**.

### 11 · La auditoría en bloque

`60_clasificar_cursos.php` cambió de propósito y por eso confunde. Nació para clasificar los
36 cursos heredados del campus viejo; ésos se descartaron. **Hoy sirve para atrapar al que se
olvidó del paso 5.**

```bash
E="docker compose exec -u www-data app php /opt/academia/cli"
$E/60_clasificar_cursos.php --exportar     # escribe una propuesta deducida
#   ... revisar el CSV a mano — es una decisión institucional, no técnica
$E/60_clasificar_cursos.php --dry-run
$E/60_clasificar_cursos.php
```

La columna `REVISAR` es un freno: mientras tenga texto, ese curso **no se toca**.

> El CSV se escribe **dentro del contenedor** y se pierde en el siguiente despliegue. Bájalo
> antes de tocarlo.

### 12 · Verificar con los ojos

Abrir el catálogo, filtrar por Área y **ver que el filtro cambia la lista**. Después
`99_verificar.php`.

«Funciona» significa haber visto el resultado. El propio verificador lista al final lo que no
reemplaza nada: entrar por navegador, subir un archivo y encontrarlo en el `filedir` del
host, y entrar con una cuenta que tenga `gestorarea` **sólo** en la categoría 01 y comprobar
que **no** ve los cursos de 04.

> Esa última prueba necesita un curso oculto puesto a mano en la 04: en un sitio vacío, «no ve
> los cursos de 04» se cumple solo y no prueba nada.

---

## Lo que todavía NO está resuelto

Honestidad sobre el estado real. Nada de esto es opinión: sale de leer el repositorio.

### Bloqueos duros, aguas abajo

| | Qué pasa |
|---|---|
| **No hay librerías H5P** | En una instalación limpia, `h5p_libraries` está **vacía** y el Banco de contenido **no ofrece ningún tipo que crear**, sin decir por qué. Alguien con permisos de manager tiene que subir a mano un `.h5p` que las traiga dentro. Y todo el estándar pedagógico exige H5P |
| **No hay plantilla de certificado** | No hay ningún `.json` de plantilla `customcert` versionado, ni script que la cree. **El certificado sale en blanco** |
| **`academia2.conaf.cl` no tiene backend** | El DNS resuelve y el certificado comodín sirve, pero el Alteon acepta el TLS y cierra sin reenviar. Falta su servicio virtual |
| **Nada conecta `vigencia` con el certificado** | `campos-curso.csv:37` dice que «alimenta el elemento Expiry», pero no hay nada que lo haga |

### Vacíos de procedimiento

- **No existe script ni procedimiento para duplicar GC-000.** El paso central del método es
  manual y no estaba escrito. Este documento es el primer intento.
- **La lista de verificación** que el Gestor aplica para autorizar el paso de Piloto a
  Vigente está citada en tres lugares del repositorio y **no existe**.
- **No hay procedimiento de solicitud de curso.**
- **No hay banco de preguntas para TR-104**: sus tres cuestionarios quedan vacíos.
- **No hay procedimiento para traer un `.mbz`** del campus del 8115.
- **No hay política de respaldos** ni de `academia_v2` ni de su `moodledata`. Es el pendiente
  más grande, y ahora pesa más que antes: **esto ya no es un clon**, y los cursos que se
  escriban acá no existen en ninguna otra parte.

### Contradicciones vivas en la documentación

- ~~`academia/README.md` y `docs/V2-ACADEMIA.md` decían que las 7 áreas nacen «ocultas»~~
  **CORREGIDO el 01-09-2026.** Nacen **visibles** (`categorias.csv`, `visible=1`), y la razón
  es dura: una categoría oculta oculta también los cursos que viven dentro, así que GC-000,
  IF-151 y TR-104 habrían quedado invisibles para todos salvo el administrador.
- Los 8 manuales de `manuales/` describen el **campus antiguo** (Moodle 4.5.10, otro tema, 9
  categorías). Entregarle `03-teacher.md` a un autor de la Academia creyendo que le sirve es
  un error.

---

## El siguiente paso concreto

**Crea tú el primer curso, como administrador, y escribe lo que te vaya faltando en este
documento mientras lo haces.** No esperes a que haya gestores nombrados: hoy no los hay, y
nombrarlos es una decisión institucional que no bloquea la construcción.

Antes de prometerle a nadie contenido interactivo, **comprueba si H5P puede funcionar**: entra
al Banco de contenido y mira si ofrece algún tipo. Si no ofrece ninguno, ése es el primer
problema a resolver, y es anterior a cualquier curso.
