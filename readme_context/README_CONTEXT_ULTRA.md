# PROJECT EVIDENCE CONTEXT
PROJECT=target
FILES=1587
GENERATED=2026-08-31T19:37:50.014212

## EVIDENCE_POLICY

This context contains repository evidence.
Signals are not guaranteed business features.
Do not infer unsupported functionality.
Prefer explicit files, dependencies and source evidence.
If evidence is insufficient, omit the claim.

## STACK
LANG=PHP,SCSS,HTML,CSS,JSON,JavaScript,Markdown,YAML,Text,SQL,INI,Python
TECH=Docker[high],Express[medium],FastAPI[medium],Flask[low],MongoDB[low],MySQL[medium],Node.js[high],PostgreSQL[high],React[medium],Redis[low],SQLAlchemy[low],SQLite[low]

## STRUCTURE
ROOTS=plugins(1521),academia(24),manuales(9),docs(6),docker(5),INSUMO(4),INSUMO_MEJORA(3),.github(2),db(2),CLAUDE.md(1),.gitignore(1),.gitattributes(1),docker-compose.yml(1),.env.migracion.example(1),docker-compose.migracion.yml(1),README.md(1),.env.v2.example(1),.env.example(1),.dockerignore(1),Dockerfile(1)

## KEY_FILES
Dockerfile,docker-compose.yml,plugins/blocks/configurable_reports/README.txt,plugins/blocks/configurable_reports/lib/pChart2/README.md,.env.example,README.md,academia/README.md,manuales/README.md,plugins/admin/tool/mergeusers/Makefile,plugins/admin/tool/mergeusers/README.md,plugins/mod/customcert/README.md,plugins/theme/academi/README.md,plugins/theme/boost_magnific/README.md,plugins/blocks/configurable_reports/db/services.php,plugins/theme/boost_magnific/settings/login_config.php,.github/workflows/deploy-prod.yml,docker-compose.migracion.yml,docker/config.php,plugins/admin/tool/mergeusers/classes/local/db_config.php,plugins/admin/tool/mergeusers/classes/local/default_db_config.php,plugins/admin/tool/mergeusers/classes/local/settable_db_config.php,plugins/admin/tool/mergeusers/tests/db_config_test.php,plugins/admin/tool/mergeusers/tests/settable_db_config_test.php,plugins/blocks/configurable_reports/.github/workflows/ci.yml,plugins/blocks/configurable_reports/db/access.php,plugins/blocks/configurable_reports/db/install.xml,plugins/blocks/configurable_reports/db/upgrade.php,plugins/blocks/configurable_reports/settings.php,plugins/mod/customcert/db/services.php,plugins/theme/boost_magnific/templates/settings/login.mustache,.dockerignore,.github/workflows/readme.yml,INSUMO/DOCKER.md,INSUMO/fastapi-postgresql-conexion.md,INSUMO/guia-8-prompt-checklist-pre-deploy.md,db/setup_bd.sql,db/setup_bd_v2.sql,docker/apache-moodle.conf,docker/health.php,docker/moodle-crontab,docker/php.ini,manuales/07-authenticated-user.md,manuales/08-authenticated-user-frontpage.md,plugins/admin/tool/mergeusers/.github/workflows/moodle-ci.yml,plugins/admin/tool/mergeusers/.github/workflows/moodle-release.yml,plugins/admin/tool/mergeusers/classes/hook/add_settings_before_merging.php,plugins/admin/tool/mergeusers/classes/local/config.php,plugins/admin/tool/mergeusers/classes/local/database_transactions.php,plugins/admin/tool/mergeusers/classes/local/merger/finder/assign_submission_db_finder.php,plugins/admin/tool/mergeusers/classes/local/settings/json_setting.php,plugins/admin/tool/mergeusers/db/access.php,plugins/admin/tool/mergeusers/db/events.php,plugins/admin/tool/mergeusers/db/hooks.php,plugins/admin/tool/mergeusers/db/install.xml,plugins/admin/tool/mergeusers/db/upgrade.php,plugins/admin/tool/mergeusers/settings.php,plugins/admin/tool/mergeusers/settingslib.php,plugins/admin/tool/mergeusers/tests/config_test.php,plugins/admin/tool/mergeusers/tests/fixtures/add_empty_settings_before_merging_callbacks.php,plugins/admin/tool/mergeusers/tests/fixtures/add_empty_settings_before_merging_hooks.php,plugins/admin/tool/mergeusers/tests/fixtures/add_settings_before_merging_callbacks.php,plugins/admin/tool/mergeusers/tests/fixtures/add_settings_before_merging_hooks.php,plugins/blocks/configurable_reports/.gitignore,plugins/blocks/configurable_reports/amd/src/codemirror.js,plugins/blocks/configurable_reports/amd/src/jquery.dataTables.js,plugins/blocks/configurable_reports/amd/src/jquery.tablesorter.js,plugins/blocks/configurable_reports/amd/src/main.js,plugins/blocks/configurable_reports/amd/src/sql.js,plugins/blocks/configurable_reports/block_configurable_reports.php,plugins/blocks/configurable_reports/classes/check/sql_execution.php,plugins/blocks/configurable_reports/classes/external.php,plugins/blocks/configurable_reports/classes/github.php,plugins/blocks/configurable_reports/classes/privacy/provider.php,plugins/blocks/configurable_reports/component.class.php,plugins/blocks/configurable_reports/components/calcs/average/form.php,plugins/blocks/configurable_reports/components/calcs/average/plugin.class.php,plugins/blocks/configurable_reports/components/calcs/component.class.php,plugins/blocks/configurable_reports/components/calcs/max/form.php,plugins/blocks/configurable_reports/components/calcs/max/plugin.class.php,plugins/blocks/configurable_reports/components/calcs/min/form.php

## API_EVIDENCE
FETCH ${M.cfg.wwwroot}/theme/boost_magnific/_editor/model/?lang=${frontpage.lang} [plugins/theme/boost_magnific/amd/src/frontpage.js:55]

## DATABASE_EVIDENCE
pg_roles [db/setup_bd_v2.sql:11]
PUBLIC [db/setup_bd_v2.sql:36]
information_schema.tables [db/setup_bd_v2.sql:59]
mdl_user [db/setup_bd_v2.sql:61]
mdl_course [db/setup_bd_v2.sql:62]
mdl_files [db/setup_bd_v2.sql:63]
pg_sequences [db/setup_bd_v2.sql:69]
PUBLIC [db/setup_bd.sql:35]

## CAPABILITY_SIGNALS
Autenticación [confidence=medium]
  login [CLAUDE.md:247]
  auth [CLAUDE.md:312]
  authenticate [CLAUDE.md:312]
  login [plugins/theme/boost_magnific/settings.php:57]
  login [plugins/theme/boost_magnific/course-image-default.php:25]
  login [plugins/theme/boost_magnific/config.php:67]
  login [plugins/theme/boost_magnific/report.php:29]
  login [plugins/theme/boost_magnific/accessibility-ajax.php:18]
Mapas / cartografía [confidence=low]
  mapa [CLAUDE.md:41]
  mapa [INSUMO_MEJORA/prototipo-academia-conaf.html:2579]
Exportación [confidence=medium]
  csv [CLAUDE.md:339]
  csv [README.md:16]
  excel [plugins/theme/boost_magnific/README.md:33]
  csv [plugins/theme/boost_magnific/_editor/css/bootstrap.css:2280]
  export [plugins/theme/boost_magnific/_editor/css/grapes.css:2630]
  export [plugins/theme/boost_magnific/_editor/model/popular-default/create-block.php:3]
  export [plugins/theme/boost_magnific/_editor/model/banner-eadflix-op1/create-block.php:3]
  export [plugins/theme/boost_magnific/_editor/model/banner-eadflix-op2/create-block.php:3]
Carga de archivos [confidence=medium]
  archivo [CLAUDE.md:19]
  file [CLAUDE.md:47]
  document [CLAUDE.md:12]
  archivo [docker-compose.yml:6]
  file [docker-compose.yml:15]
  archivo [docker-compose.migracion.yml:37]
  file [docker-compose.migracion.yml:10]
  document [README.md:35]
Reportes / analítica [confidence=medium]
  report [CLAUDE.md:122]
  report [plugins/theme/boost_magnific/settings.php:39]
  report [plugins/theme/boost_magnific/config.php:175]
  dashboard [plugins/theme/boost_magnific/config.php:97]
  report [plugins/theme/boost_magnific/report.php:18]
  dashboard [plugins/theme/boost_magnific/report.php:83]
  report [plugins/theme/boost_magnific/lang/en/theme_boost_magnific.php:31]
  dashboard [plugins/theme/boost_magnific/_editor/editor-lib.php:317]
Procesamiento de datos [confidence=medium]
  etl [plugins/theme/boost_magnific/scss/colors.css:23]
  etl [plugins/theme/boost_magnific/scss/moodle/_minicolors.scss:5]
  etl [plugins/theme/academi/amd/src/slick.js:257]
  etl [plugins/theme/academi/amd/src/jquery.sudoSlider.js:1047]
  etl [plugins/blocks/configurable_reports/amd/src/codemirror.js:907]
  etl [plugins/blocks/configurable_reports/components/plot/bar/graph.php:147]
  etl [plugins/blocks/configurable_reports/lib/pChart/pChart.class.php:58]
  etl [plugins/blocks/configurable_reports/lib/pChart2/class/pSpring.php:73]
IA / Machine Learning [confidence=low]
  torch [plugins/blocks/configurable_reports/amd/src/sql.js:20]

## PYTHON
academia/herramientas/convertir-banco-IF-151.py|F=esc,bloque_categoria,bloque_pregunta|I=re,sys,html,pathlib

## COMPONENTS
plugins/theme/boost_magnific/_editor/model/_assets/jquery-3.7.1.js:DOMEval,Ctor,Identity,Thrower,Data,Tween,Animation
plugins/theme/boost_magnific/_editor/model/_assets/owl.carousel.js:Owl
plugins/theme/boost_magnific/amd/src/frontpage.js:Modal
plugins/theme/academi/amd/src/slick.js:Slick
plugins/theme/academi/amd/src/jquery.sudoSlider.js:URLChange
plugins/blocks/configurable_reports/amd/src/codemirror.js:BidiSpan,Pos,MarkedSpan,LineView,PosWithInfo,Display,History,LeafChunk,BranchChunk,CodeMirror
plugins/blocks/configurable_reports/amd/src/jquery.dataTables.js:DataTables

## EXISTING_README
# coipo_academia — Academia CONAF v2
Segunda versión del Moodle de CONAF. `coipo_moodle` resolvió la **infraestructura** —sacar la
plataforma del proveedor externo y dejarla en servidor propio sobre PostgreSQL 17—. Este
repositorio resuelve la **arquitectura de información**: convertir un campus de incendios en la
plataforma de formación de toda la institución.
El diagnóstico de `INSUMO_MEJORA` lo resume en una línea: *la plataforma no es el problema, la
arquitectura de información sí lo es.* Hoy hay 9 categorías planas que mezclan cinco criterios,
**1** campo de clasificación, **0** cohortes, **0** roles delegados y las competencias apagadas.
## Qué hay acá
| | |
|---|---|
| [academia/](academia/README.md) | Los scripts que aplican el modelo de datos, los CSV con las decisiones, el banco de preguntas y las pruebas |
| [plugins/theme/academia/](plugins/theme/academia/) | El tema: hijo de Boost, con la identidad institucional y las reglas del estándar |
| [docs/V2-ACADEMIA.md](docs/V2-ACADEMIA.md) | **Empezar por acá.** Qué se construyó, por qué, y qué queda abierto |
| [db/setup_bd_v2.sql](db/setup_bd_v2.sql) · [.env.v2.example](.env.v2.example) | Cómo se levanta la instancia v2 |
| `INSUMO_MEJORA/` | Los tres insumos: propuesta institucional, diseño del curso IF-151 y el prototipo de 22 pantallas |
## Corre en paralelo con producción, y en otra versión
```
172.31.2.41
├── coipo_moodle    8115   academia_prod   Moodle 4.5.10 · PHP 8.3   NO SE TOCA
└── coipo_academia  8116   academia_v2     Moodle 5.2.1  · PHP 8.4   esta
```
v2 va por delante a propósito: ensaya el salto a 5.x en un clon desechable antes de que lo
tenga que hacer producción, y deja el camino a **5.3 LTS en octubre** convertido en un paso
corto en vez de un salto de cuatro versiones. El detalle está en
[docs/V2-ACADEMIA.md](docs/V2-ACADEMIA.md).
**Moodle 5.2 movió la raíz web a `public/`.** El `DocumentRoot` apunta a
`/var/www/html/public`, `config.php` se queda en la raíz y los scripts CLI también. Si el
sitio responde `rootdirpublic`, es eso.
Los scripts se **niegan a correr** si el `config.php` apunta a `academia_prod`.
## Lo mínimo para empezar
```bash
cd /opt/apps/coipo_academia
# Paso 0: el clon viene con esquema 4.5.10 y la imagen trae 5.2.1
docker compose exec -u www-data app php /var/www/html/admin/cli/upgrade.php --non-interactive

## DEPLOYMENT_FILES
.dockerignore,.github/workflows/deploy-prod.yml,.github/workflows/readme.yml,Dockerfile,INSUMO/DOCKER.md,INSUMO/guia-8-prompt-checklist-pre-deploy.md,docker-compose.migracion.yml,docker-compose.yml,docker/apache-moodle.conf,docker/config.php,docker/health.php,docker/moodle-crontab,docker/php.ini,plugins/admin/tool/mergeusers/.github/workflows/moodle-ci.yml,plugins/admin/tool/mergeusers/.github/workflows/moodle-release.yml,plugins/admin/tool/mergeusers/classes/output/renderer.php,plugins/admin/tool/mergeusers/composer.json,plugins/admin/tool/mergeusers/tests/renderer_test.php,plugins/blocks/configurable_reports/.github/workflows/ci.yml,plugins/blocks/configurable_reports/composer.json,plugins/blocks/configurable_reports/lib/pChart2/composer.json,plugins/mod/customcert/.github/workflows/moodle-ci.yml,plugins/mod/customcert/classes/element/renderable_element_interface.php,plugins/mod/customcert/classes/local/preview_renderer.php,plugins/mod/customcert/classes/output/email/renderer.php,plugins/mod/customcert/classes/output/email/renderer_textemail.php,plugins/mod/customcert/classes/output/renderer.php,plugins/mod/customcert/classes/service/element_renderer.php,plugins/mod/customcert/classes/service/html_renderer.php,plugins/mod/customcert/classes/service/pdf_renderer.php,plugins/mod/customcert/composer.json,plugins/mod/customcert/tests/fixtures/spy_element_renderer.php,plugins/mod/customcert/tests/preview_renderer_test.php,plugins/mod/customcert/tests/preview_renderer_with_text_test.php,plugins/theme/academi/.github/workflows/moodle-ci.yml,plugins/theme/academi/classes/output/core/course_renderer.php,plugins/theme/academi/classes/output/core_renderer.php,plugins/theme/boost_magnific/.github/workflows/ci.yml,plugins/theme/boost_magnific/.github/workflows/release.yml,plugins/theme/boost_magnific/classes/output/core/admin_renderer.php,plugins/theme/boost_magnific/classes/output/core/course_renderer.php,plugins/theme/boost_magnific/classes/output/core_renderer.php,plugins/theme/boost_magnific/classes/output/footer_renderer.php,plugins/theme/boost_magnific/renderers.php

## README_RULES

Generate README.md only from repository evidence.
Do not invent features.
Do not invent technologies.
Do not invent endpoints.
Do not invent database tables.
Do not invent environment variables.
Do not invent commands.
Do not infer production architecture from filenames alone.
Treat capability signals as signals, not confirmed features.
Prefer explicit source evidence.
Omit unsupported sections.