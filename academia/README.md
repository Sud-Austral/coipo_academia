# Provisión de la Academia CONAF v2

Sobre **Moodle 5.2.1 y PHP 8.4**. Todo lo que convierte el campus de incendios en la Academia
se aplica desde acá, con scripts, y no por la interfaz de administración.

**Por qué scripts y no la web.** Es la misma razón que `CLAUDE.md` ya tiene escrita para los
plugins: lo que se hace por la interfaz vive solo en la base de datos de una instancia. No
queda en el repositorio, no se puede revisar, no se puede repetir en otra instancia y nadie
sabe después por qué un valor es el que es. Los ocho campos personalizados, las 28 cohortes y
el marco de competencias son decisiones institucionales: tienen que estar escritas.

Todos los scripts son **idempotentes**. Correrlos dos veces no duplica nada y la segunda
corrida no imprime ni un solo `creado`. Todos aceptan `--dry-run` y `--help`.

---

## Paso 0 — el upgrade de Moodle, antes que todo lo demás

Esto es nuevo y no se puede saltar. La base `academia_v2` se clona de `academia_prod`, que
está en **Moodle 4.5.10**; la imagen de este repositorio trae **Moodle 5.2.1**. El contenedor
arranca igual, pero el sitio responde «actualización pendiente» hasta que alguien la ejecute:

```bash
cd /opt/apps/coipo_academia
docker compose exec -u www-data app php /var/www/html/admin/cli/upgrade.php --non-interactive
docker compose exec -u www-data app php /var/www/html/admin/cli/check_database_schema.php
```

Son cuatro versiones de salto — 4.5 → 5.0 → 5.1 → 5.2 — y Moodle las encadena solo. Dos cosas
que conviene mirar mientras corre:

- **`admin/cli/` está en la raíz, sin `public/`.** No es un descuido: en Moodle 5.2 la raíz web
  se movió a `public/`, pero los scripts de línea de comandos se quedaron arriba.
- El segundo comando tiene que decir `Database structure is ok.` Si no, no seguir.

Y contar antes y después, que es la única forma de saber que el upgrade no se comió nada:

```sql
SELECT (SELECT count(*) FROM mdl_user)   AS usuarios,   -- 2873
       (SELECT count(*) FROM mdl_course) AS cursos,     -- 37
       (SELECT count(*) FROM mdl_files)  AS archivos;   -- 179147
```

El número de **tablas** sí va a subir: la rama 5.x agrega varias. Los usuarios, cursos y
archivos, no.

---

## Antes de empezar con los scripts

Los scripts se niegan a correr si el `config.php` apunta a `academia_prod`. Es deliberado:
esta es la instancia v2 y existe justamente para no tocar producción. Si de verdad hiciera
falta, hay que pasar `--permitir-produccion` a mano, por script.

```bash
docker compose exec -u www-data app php /opt/academia/cli/99_verificar.php
```

Siempre `-u www-data`. Como root, todo lo que Moodle escriba en `moodledata` queda con dueño
root y el sitio deja de poder escribir. Es el error más común de este proyecto y el más
confuso, porque el síntoma aparece días después.

---

## Orden de ejecución

El orden importa. Cada paso construye sobre el anterior, y saltárselo no da error: da un
resultado a medias que parece correcto.

| # | Script | Qué hace | Depende de |
|---|---|---|---|
| 95 | `95_ajustes_sitio.php` | Enciende finalización, restricciones, competencias, app móvil, accesibilidad | — |
| 10 | `10_categorias.php` | Las 7 áreas temáticas y 2 subcategorías, **vacías y ocultas** | — |
| 20 | `20_campos_curso.php` | Los 8 campos de clasificación | — |
| 30 | `30_cohortes.php` | Las 28 cohortes, vacías | — |
| 40 | `40_competencias.php` | La escala de logro y los 2 marcos | 95 |
| 50 | `50_rol_gestor.php` | El rol Gestor de Área Temática | 10 |
| 60 | `60_clasificar_cursos.php` | Completa los campos de los cursos existentes | 20 |
| 70 | `70_informes.php` | Catálogo filtrable y tablero | 20, 50, 60 |
| 80 | `80_plantilla_maestra.php` | El curso GC-000 | 10, 20, 95 |
| 90 | `90_cursos_esqueleto.php` | IF-151 y TR-104 | 10, 20, 30, 40, 95 |
| 99 | `99_verificar.php` | Cuenta todo y lo compara con lo esperado | — |

`95` va primero aunque su número sea el más alto: sin `enablecompletion` y
`enableavailability`, los cursos se crean sin finalización y **eso no se puede rellenar hacia
atrás** — hay que borrarlos y empezar de nuevo.

De corrido, con simulación primero:

```bash
E="docker compose exec -u www-data app php /opt/academia/cli"
for s in 95_ajustes_sitio 10_categorias 20_campos_curso 30_cohortes \
         40_competencias 50_rol_gestor; do
  $E/$s.php --dry-run || break
done
```

### El paso 60 es distinto: son dos

La clasificación de los 36 cursos reales no se puede escribir desde un documento — el Anexo A
trae los nombres, no los nombres cortos con que están en la base. Por eso:

```bash
# 1. Exportar lo que hay, con una propuesta ya rellenada
$E/60_clasificar_cursos.php --exportar

# 2. Revisar academia/datos/cursos-clasificacion.csv a mano.
#    La columna REVISAR es un freno: mientras tenga texto, ese curso NO se toca.
#    Vaciarla es la forma de decir «esta clasificación está decidida».

# 3. Aplicar
$E/60_clasificar_cursos.php --dry-run
$E/60_clasificar_cursos.php
```

La propuesta automática deduce área, nivel y financiamiento del nombre del curso y de su
categoría actual. Lo que ningún dato del sistema puede responder —perfil, duración y
vigencia— lo deja en blanco. Las reglas están probadas contra los 35 cursos del Anexo A:
`academia/pruebas/propuesta-clasificacion.php`.

---

## Datos

Los CSV de `datos/` son la fuente de verdad, y llevan las decisiones escritas al lado de la
fila que afectan. Se editan sin tocar PHP.

| Archivo | Qué contiene |
|---|---|
| `categorias.csv` | Las 7 áreas y sus subcategorías |
| `campos-curso.csv` | Los 8 campos, con sus listas cerradas de valores |
| `cohortes.csv` | 16 territoriales + 7 ocupacionales + 3 de temporada + 2 funcionales |
| `competencias.csv` | 2 marcos, 5 unidades y 11 competencias |
| `cursos-clasificacion.csv` | *Lo genera el paso 60. No está versionado.* |
| `banco-IF-151.xml` | Los 60 ítems del Anexo A, en Moodle Question XML |

`banco-IF-151.xml` está generado, no escrito a mano. Para regenerarlo si cambia el documento
de diseño: `academia/herramientas/convertir-banco-IF-151.py`. El conversor comprueba las
cuentas contra la tabla de especificaciones —35 formativos, 21 de certificación, 4
integradoras, exactamente una opción correcta por ítem— y se niega a escribir nada si alguna
no cuadra.

---

## Pruebas

Corren sin base de datos. Las dos verifican cosas que fallan en silencio.

```bash
# Las reglas de propuesta de clasificación, contra los 35 cursos del Anexo A
php -d extension=mbstring academia/pruebas/propuesta-clasificacion.php

# Que el SCSS del tema compila Y que las reglas del estándar llegan al CSV
docker compose exec app php /opt/academia/pruebas/compilar-tema.php
```

Un error de SCSS no da un error: da un sitio sin estilos, sin ningún mensaje. Y una regla con
un selector mal anidado compila perfecto y no produce nada — por eso la prueba, además de
compilar, busca cada regla dentro del CSS de salida.

---

## Decisiones abiertas

Están comentadas en los CSV, en la fila que afectan. Las tres primeras hay que cerrarlas antes
de poblar las cohortes.

1. **Las cohortes ocupacionales no coinciden con el campo `perfil`.** El campo trae
   `Todo el personal`; las cohortes traen `PERF-ADM · Administrativo y apoyo`. Son siete y
   siete, pero no las mismas siete. Mientras no se alineen, comparar «quién debía tomar el
   curso» contra «quién lo tomó» —la razón declarada de esa familia— no funciona para esos
   dos valores.
2. **`PERF-AUTOR` y `GER-IF-PROF` no están en el modelo de datos**, pero TR-104 e IF-151 las
   exigen. Están creadas como una cuarta familia, `funcional`. Hay que decidir si eso es lo
   correcto: de ahí depende que el total sea 26 —lo que dice el Anexo C.2— o 28.
3. **Las bandas numéricas de código de curso.** IF-151 propone reservar 101–149 para Malla
   Estándar, 150–179 para Fundamentos Técnicos Comunes, 201–249 para Intermedia y 301–349 para
   Avanzada. «Es una decisión de dos minutos hoy y un problema serio en tres años si no se
   toma.»
4. **Las correspondencias NFPA/NWCG de `competencias.csv` son una propuesta**, no doctrina.
   Ningún documento de INSUMO_MEJORA trae el mapeo hecho. Las tiene que validar el
   Departamento de Protección contra Incendios Forestales antes de que aparezcan en un
   certificado.

---

## Lo que estos scripts NO hacen, a propósito

- **No mueven ni un curso** al árbol nuevo. Es el método de árbol paralelo: la estructura
  nueva convive vacía con las 9 categorías actuales, y el traslado es la Etapa 4, en octubre,
  junto con el salto a la próxima versión de largo soporte. Las categorías antiguas se
  ocultan, no se borran, hasta cerrar la trazabilidad de las certificaciones ya emitidas.
- **No producen contenido H5P.** IF-151 y TR-104 quedan con la estructura, las evaluaciones,
  el banco y el certificado; el contenido lo escribe el autor y, en IF-151, lo firma un
  especialista. En un dominio con riesgo vital, el contenido técnico no se genera.
- **No pueblan las cohortes.** Se crean vacías. La pertenencia se carga por CSV mientras la
  identidad sea manual, y se sincroniza con el directorio institucional cuando la UIA
  construya esa integración.
- **No activan respaldos ni correo saliente.** Son los dos hallazgos críticos del Anexo B,
  pero son de producción: los respaldos de un clon desechable no protegen nada, y el correo
  está frenado por `MOODLE_NOEMAILEVER` porque detrás hay 2.869 direcciones institucionales
  reales. Van en `coipo_moodle`.
- **No encienden el antivirus.** ClamAV no está en la imagen. Encenderlo sin el demonio hace
  que *toda* subida de archivo falle: se pasa de «archivos sin revisar» a «nadie puede subir
  nada». `95_ajustes_sitio.php` lo comprueba y lo informa en vez de encenderlo.
- **No designan a nadie.** El rol de Gestor de Área se crea vacío de personas: quién lo ocupa
  es una decisión institucional (Propuesta, Parte 4.1).
