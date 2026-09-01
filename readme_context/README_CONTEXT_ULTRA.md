# PROJECT EVIDENCE CONTEXT
PROJECT=target
FILES=471
GENERATED=2026-09-01T18:40:41.010183

## EVIDENCE_POLICY

This context contains repository evidence.
Signals are not guaranteed business features.
Do not infer unsupported functionality.
Prefer explicit files, dependencies and source evidence.
If evidence is insufficient, omit the claim.

## STACK
LANG=PHP,Markdown,YAML,JSON,JavaScript,SCSS,CSS,SQL,INI,HTML,Python
TECH=Docker[high],Express[low],FastAPI[medium],MySQL[medium],Node.js[medium],PostgreSQL[high],React[low],Redis[low],SQLAlchemy[low]

## STRUCTURE
ROOTS=plugins(403),academia(24),manuales(9),docs(8),docker(5),INSUMO(4),INSUMO_MEJORA(3),.github(2),db(2),CLAUDE.md(1),.gitignore(1),.gitattributes(1),docker-compose.yml(1),.env.migracion.example(1),docker-compose.migracion.yml(1),README.md(1),.env.v2.example(1),.env.example(1),.dockerignore(1),Dockerfile(1)

## KEY_FILES
Dockerfile,docker-compose.yml,.env.example,README.md,academia/README.md,manuales/README.md,plugins/mod/customcert/README.md,.github/workflows/deploy-prod.yml,docker-compose.migracion.yml,docker/config.php,plugins/mod/customcert/db/services.php,.dockerignore,.github/workflows/readme.yml,INSUMO/DOCKER.md,INSUMO/fastapi-postgresql-conexion.md,INSUMO/guia-8-prompt-checklist-pre-deploy.md,db/setup_bd.sql,db/setup_bd_v2.sql,docker/apache-moodle.conf,docker/health.php,docker/moodle-crontab,docker/php.ini,manuales/07-authenticated-user.md,manuales/08-authenticated-user-frontpage.md,plugins/mod/customcert/.github/workflows/moodle-ci.yml,plugins/mod/customcert/classes/service/certificate_download_service.php,plugins/mod/customcert/classes/service/certificate_email_service.php,plugins/mod/customcert/classes/service/certificate_issue_service.php,plugins/mod/customcert/classes/service/certificate_issuer_service.php,plugins/mod/customcert/classes/service/certificate_repository.php,plugins/mod/customcert/classes/service/certificate_time_service.php,plugins/mod/customcert/classes/service/element_factory.php,plugins/mod/customcert/classes/service/element_layout.php,plugins/mod/customcert/classes/service/element_registry.php,plugins/mod/customcert/classes/service/element_renderer.php,plugins/mod/customcert/classes/service/element_repository.php,plugins/mod/customcert/classes/service/form_service.php,plugins/mod/customcert/classes/service/html_renderer.php,plugins/mod/customcert/classes/service/issue_email_repository.php,plugins/mod/customcert/classes/service/issue_repository.php,plugins/mod/customcert/classes/service/item_move_service.php,plugins/mod/customcert/classes/service/page_repository.php,plugins/mod/customcert/classes/service/page_update.php,plugins/mod/customcert/classes/service/pdf_generation_service.php,plugins/mod/customcert/classes/service/pdf_renderer.php,plugins/mod/customcert/classes/service/persistence_helper.php,plugins/mod/customcert/classes/service/template_duplication_service.php,plugins/mod/customcert/classes/service/template_load_service.php,plugins/mod/customcert/classes/service/template_repository.php,plugins/mod/customcert/classes/service/template_service.php,plugins/mod/customcert/classes/service/validation_service.php,plugins/mod/customcert/db/access.php,plugins/mod/customcert/db/hooks.php,plugins/mod/customcert/db/install.xml,plugins/mod/customcert/db/log.php,plugins/mod/customcert/db/mobile.php,plugins/mod/customcert/db/subplugins.json,plugins/mod/customcert/db/tasks.php,plugins/mod/customcert/db/upgrade.php,plugins/mod/customcert/element/bgimage/db/upgrade.php,plugins/mod/customcert/element/image/db/upgrade.php,plugins/mod/customcert/settings.php,plugins/mod/customcert/tests/certificate_download_service_test.php,plugins/mod/customcert/tests/certificate_issue_service_test.php,plugins/mod/customcert/tests/certificate_time_service_test.php,plugins/mod/customcert/tests/form_service_test.php,plugins/mod/customcert/tests/pdf_generation_service_test.php,plugins/mod/customcert/tests/template_authorisation_test.php,plugins/mod/customcert/tests/template_duplication_service_test.php,plugins/mod/customcert/tests/template_load_service_test.php,plugins/mod/customcert/tests/template_service_test.php,plugins/mod/customcert/tests/validation_service_test.php,plugins/theme/academia/config.php,plugins/theme/academia/settings.php

## DATABASE_EVIDENCE
pg_roles [db/setup_bd_v2.sql:39]
PUBLIC [db/setup_bd_v2.sql:81]
information_schema.tables [db/setup_bd_v2.sql:166]
mdl_user [db/setup_bd_v2.sql:168]
mdl_course [db/setup_bd_v2.sql:169]
mdl_course_categories [db/setup_bd_v2.sql:170]
pg_sequences [db/setup_bd_v2.sql:194]
PUBLIC [db/setup_bd.sql:42]

## CAPABILITY_SIGNALS
Autenticación [confidence=medium]
  login [CLAUDE.md:270]
  auth [CLAUDE.md:335]
  authenticate [CLAUDE.md:335]
  login [plugins/mod/customcert/verify_certificate.php:25]
  login [plugins/mod/customcert/edit_element.php:55]
  login [plugins/mod/customcert/export_template.php:37]
  auth [plugins/mod/customcert/export_template.php:26]
  login [plugins/mod/customcert/upload_image.php:31]
Mapas / cartografía [confidence=low]
  mapa [CLAUDE.md:51]
  mapa [INSUMO_MEJORA/prototipo-academia-conaf.html:2579]
Exportación [confidence=medium]
  csv [CLAUDE.md:363]
  csv [README.md:17]
  export [plugins/mod/customcert/export_template.php:18]
  export [plugins/mod/customcert/CHANGES.md:85]
  export [plugins/mod/customcert/import_template.php:34]
  export [plugins/mod/customcert/lang/en/customcert.php:131]
  export [plugins/mod/customcert/element/coursefield/classes/exporter.php:22]
  export [plugins/mod/customcert/element/coursefield/classes/element.php:190]
Carga de archivos [confidence=medium]
  archivo [CLAUDE.md:7]
  file [CLAUDE.md:57]
  document [CLAUDE.md:22]
  archivo [docker-compose.yml:6]
  file [docker-compose.yml:14]
  document [docker-compose.yml:17]
  archivo [docker-compose.migracion.yml:37]
  file [docker-compose.migracion.yml:10]
Reportes / analítica [confidence=medium]
  report [CLAUDE.md:135]
  report [plugins/theme/academia/scss/post.scss:141]
  report [plugins/mod/customcert/report.php:18]
  report [plugins/mod/customcert/styles.css:14]
  report [plugins/mod/customcert/lib.php:181]
  report [plugins/mod/customcert/CHANGES.md:449]
  report [plugins/mod/customcert/view.php:28]
  report [plugins/mod/customcert/lang/en/customcert.php:64]
Procesamiento de datos [confidence=low]
  etl [plugins/mod/customcert/element/border/classes/element.php:93]
  etl [plugins/mod/customcert/includes/colourpicker.php:62]

## PYTHON
academia/herramientas/convertir-banco-IF-151.py|F=esc,bloque_categoria,bloque_pregunta|I=re,sys,html,pathlib

## EXISTING_README
# coipo_academia — Academia CONAF v2
La Academia CONAF. `coipo_moodle` resolvió la **infraestructura** —sacar la plataforma del
proveedor externo y dejarla en servidor propio sobre PostgreSQL 17—. Este repositorio resuelve
la **arquitectura de información**, y desde el 31-08-2026 lo hace en un sitio nuevo: Moodle
5.2.1 instalado de cero, sin heredar los usuarios ni los cursos del campus de incendios, que
sigue respondiendo en el 8115 como archivo histórico.
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
## Corre en paralelo con el campus actual, y no hereda nada de él
```
172.31.2.41
├── coipo_moodle    8115   academia_prod   Moodle 4.5.10 · PHP 8.3   archivo, NO SE TOCA
└── coipo_academia  8116   academia_v2     Moodle 5.2.1  · PHP 8.4   esta, instalada de cero
```
No es un clon del campus ni su evolución: es una instalación limpia de 5.2.1, sin usuarios ni
cursos heredados. Va en 5.2 porque es la rama estable y soportada de hoy, y porque esperar a
5.3 LTS habría congelado dos meses de construcción a cambio de nada. En **octubre de 2026 se
sube a 5.3 LTS**, y ese sí será un upgrade de verdad. El detalle, y qué se decidió el
31-08-2026 y el 01-09-2026, está en [docs/V2-ACADEMIA.md](docs/V2-ACADEMIA.md).
**Moodle 5.2 movió la raíz web a `public/`.** El `DocumentRoot` apunta a
`/var/www/html/public`, `config.php` se queda en la raíz y los scripts CLI también. Si el
sitio responde `rootdirpublic`, es eso.
Los scripts se **niegan a correr** si el `config.php` apunta a `academia_prod`.
## Lo mínimo para empezar
```bash
cd /opt/apps/coipo_academia

## DEPLOYMENT_FILES
.dockerignore,.github/workflows/deploy-prod.yml,.github/workflows/readme.yml,Dockerfile,INSUMO/DOCKER.md,INSUMO/guia-8-prompt-checklist-pre-deploy.md,docker-compose.migracion.yml,docker-compose.yml,docker/apache-moodle.conf,docker/config.php,docker/health.php,docker/moodle-crontab,docker/php.ini,plugins/mod/customcert/.github/workflows/moodle-ci.yml,plugins/mod/customcert/classes/element/renderable_element_interface.php,plugins/mod/customcert/classes/local/preview_renderer.php,plugins/mod/customcert/classes/output/email/renderer.php,plugins/mod/customcert/classes/output/email/renderer_textemail.php,plugins/mod/customcert/classes/output/renderer.php,plugins/mod/customcert/classes/service/element_renderer.php,plugins/mod/customcert/classes/service/html_renderer.php,plugins/mod/customcert/classes/service/pdf_renderer.php,plugins/mod/customcert/composer.json,plugins/mod/customcert/tests/fixtures/spy_element_renderer.php,plugins/mod/customcert/tests/preview_renderer_test.php,plugins/mod/customcert/tests/preview_renderer_with_text_test.php

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