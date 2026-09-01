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

# Paso 0: la base nace vacía. Se instala Moodle, no se actualiza nada.
docker compose exec -u www-data app php /var/www/html/admin/cli/install_database.php \
  --lang=es --agree-license --adminuser=... --adminpass='...' --adminemail=...

docker compose exec -u www-data app php /opt/academia/cli/99_verificar.php
```

El orden de ejecución y qué hace cada script están en [academia/README.md](academia/README.md).

## Dos cosas que no se negocian

- **Siempre `-u www-data`.** Como root, todo lo que Moodle escriba en `moodledata` queda con
  dueño root y el sitio deja de poder escribir. El síntoma aparece días después.
- **`MOODLE_NOEMAILEVER=true`.** Acá ya no están los 2.869 buzones del campus, pero las cuentas
  que construyen la Academia son direcciones institucionales reales y un sitio a medio armar
  manda avisos a destajo.

Las reglas generales del proyecto —higiene, secretos, verificación— están en
[CLAUDE.md](CLAUDE.md).
