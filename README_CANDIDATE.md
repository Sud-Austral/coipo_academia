# coipo_academia — Academia CONAF v2

## Descripción
La Academia CONAF. `coipo_moodle` resolvió la infraestructura —mover la plataforma del proveedor externo a servidor propio con PostgreSQL 17—. Este repositorio resuelve la arquitectura de información, funcionando desde el 31-08-2026 en Moodle 5.2.1 instalado de cero. No hereda usuarios ni cursos del campus de incendios (que sigue respondiendo en el 8115 como archivo histórico). El diagnóstico indica que la plataforma no es el problema, sino la arquitectura de información actual, que presenta 9 categorías planas que mezclan cinco criterios, 1 campo de clasificación, 0 cohortes, 0 roles delegadas y competencias apagadas.

## Estructura del proyecto
| | |
|---|---|
| [academia/](academia/README.md) | Scripts que aplican el modelo de datos, CSV con decisiones, banco de preguntas y pruebas |
| [plugins/theme/academia/](plugins/theme/academia/) | Tema hijo de Boost con identidad institucional y reglas del estándar |
| [docs/V2-ACADEMIA.md](docs/V2-ACADEMIA.md) | Documento que explica qué se construyó, por qué y qué queda abierto |
| [db/setup_bd_v2.sql](db/setup_bd_v2.sql) · [.env.v2.example](.env.v2.example) | Configuración para levantar la instancia v2 |
| `INSUMO_MEJORA/` | Tres insumos: propuesta institucional, diseño del curso IF-151 y prototipo de 22 pantallas |

## Arquitectura
Corre en paralelo con el campus actual:
```
172.31.2.41
├── coipo_moodle    8115   academia_prod   Moodle 4.5.10 · PHP 8.3   archivo, NO SE TOCA
└── coipo_academia  8116   academia_v2     Moodle 5.2.1  · PHP 8.4   esta, instalada de cero
```
No es un clon ni evolución del campus: instalación limpia de Moodle 5.2.1. Moodle 5.2 movió la raíz web a `public/`, donde apunta el `DocumentRoot`. El `config.php` permanece en la raíz y los scripts CLI también. Los scripts se niegan a ejecutarse si el `config.php` apunta a `academia_prod`.

## Stack técnico
- Lenguajes: PHP, Markdown, YAML, JSON, JavaScript, SCSS, CSS, SQL, INI, HTML, Python
- Tecnologías: Docker (alto), PostgreSQL (alto), FastAPI (medio), MySQL (medio), Node.js (medio), Express (bajo), React (bajo), Redis (bajo), SQLAlchemy (bajo)

## Instalación
La base nace vacía. Se instala Moodle, no se actualiza nada:
```bash
cd /opt/apps/coipo_academia

docker compose exec -u www-data app php /var/www/html/admin/cli/install_database.php \
  --lang=es --agree-license --adminuser=... --adminpass='...' --adminemail=...

docker compose exec -u www-data app php /opt/academia/cli/99_verificar.php
```

## Configuración
- Siempre ejecutar con `-u www-data` para evitar problemas de permisos en `moodledata`
- `MOODLE_NOEMAILEVER=true` para evitar enviar correos a destajo

## Base de datos
Tablas explícitas:
- `mdl_user`
- `mdl_course`
- `mdl_course_categories`

## Desarrollo
- Plugin `customcert` con servicios para generación de certificados, manejo de plantillas, exportación e importación
- Tema `academia` personalizado basado en Boost

## Pruebas
El plugin `customcert` incluye pruebas unitarias para:
- Servicio de descarga de certificados
- Servicio de emisión de certificados
- Servicio de tiempo de certificados
- Servicio de formularios
- Servicio de generación PDF
- Servicios de plantillas

## Despliegue
Archivos de despliegue en `.github/workflows/deploy-prod.yml`
