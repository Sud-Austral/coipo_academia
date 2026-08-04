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

docker compose exec -u www-data app php /opt/academia/cli/99_verificar.php
```

El orden de ejecución y qué hace cada script están en [academia/README.md](academia/README.md).

## Dos cosas que no se negocian

- **Siempre `-u www-data`.** Como root, todo lo que Moodle escriba en `moodledata` queda con
  dueño root y el sitio deja de poder escribir. El síntoma aparece días después.
- **`MOODLE_NOEMAILEVER=true`.** v2 es un clon con 2.869 correos institucionales reales.

Las reglas generales del proyecto —higiene, secretos, verificación— están en
[CLAUDE.md](CLAUDE.md).
