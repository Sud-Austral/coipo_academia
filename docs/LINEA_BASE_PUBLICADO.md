# Línea base de lo publicado — coipo_academia

> Generado el 2026-10-26 con la metodología «Mejorar una app ya publicada» (objetivo 527, versión con banda de ruido
> y hallazgos preexistentes). Solo lectura sobre lo publicado (GET). **Archivo nuevo, sin commit.**

## 1. Identificación
| | |
|---|---|
| Repositorio | coipo_academia |
| Rama local / commit local | main / 0f67cd0 |
| Árbol de trabajo | limpio |
| Sembrada (`.semilla`) | no |
| Qué es | Migración de la plataforma Moodle de CONAF a infraestructura propia (CLAUDE.md:3-4). |

## 2. Entorno y evidencia del dominio
| Entorno | Dominio | Evidencia (archivo:línea) |
|---|---|---|
| producción (destino definitivo) | academia.conaf.cl | CLAUDE.md:41, .env.example:76 |
| descontinuado (origen) | campus.conaf.cl | CLAUDE.md:40 (no medido) |
| sin backend | academia2.conaf.cl | docs/GUIA-OPERATIVA.md:347 (no medido) |

## 3. CI y despliegue (gh run list)
README Maintainer | push | main | success | 0f67cd0
deploy-prod.yml | push | main | **failure** | 0f67cd0 (también falló en e1f5f31). Motivo: `gh run view --log-failed` respondió «log not found»; no lo comprobé.

Commit publicado (/health): el /health (117 B) no trae hash de commit (docker/apache-moodle.conf:20-22 lo sirve desde health.php) · coincide con la rama: no lo comprobé

## 4. Funcionalidad medida (2 pasadas × 5 repeticiones, solo GET)
Base https://academia.conaf.cl · /openapi.json: 404
```
salud                  200   117 B  med 0.05s  ruido 0.003s estable=True
salud_detalle          200   117 B  med 0.049s ruido 0.003s estable=True
raiz                   200 42135 B  med 0.16s  ruido 0.007s estable=False
INVALIDA_ruta          404   513 B  med 0.039s ruido 0.001s estable=True
INVALIDA_api           404   513 B  med 0.039s ruido 0.003s estable=True
extra_login_index.php  200 42135 B  med 0.093s ruido 0.003s estable=False
```

Hallazgos de la medición:
- /health y /health/detalle devuelven los mismos 117 B: no hay detalle propio.
- `raiz` y `/login/index.php` pesan igual (42135 B) y el hash no es estable entre repeticiones (contenido dinámico probable; no lo comprobé).

## 5. Hallazgos preexistentes (lo raro que ya existía) y si es por diseño
| Hallazgo | ¿Por diseño? | Fuente |
|---|---|---|
| /health/detalle responde igual que /health | sí: `Alias /health` en Apache es por prefijo | docker/apache-moodle.conf:22 |
| deploy-prod en rojo en main | no lo comprobé | sin log disponible |

## 6. Interfaz
No medida en esta pasada.

## 7. Defectos simples corregidos (sin commit)
Ninguno aplicado: no encontré defecto simple verificable.

Defectos vistos y NO corregidos (con motivo): enlace `../Moodle/Moodle/moodle/reportbuilder/classes/local/entities/course.php` en docs/V2-ACADEMIA.md:240 apunta fuera del repo (checkout externo de Moodle); no hay destino evidente.

## 8. No lo comprobé
- Motivo del fallo de deploy-prod.
- Si el commit publicado coincide con 0f67cd0.
- Rutas autenticadas de Moodle.
- Dominios academia2 y campus.
