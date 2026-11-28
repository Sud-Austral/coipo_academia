# Documentacion_Sistema2 — Academia CONAF v2 (coipo_academia)

Versión 2 · 2026-11-15. No existía un paquete anterior: esta es la primera versión generada.

## Qué hay
| Carpeta | Contenido |
|---|---|
| 00_Analisis | Analisis_Sistema.docx (roles, reglas, hallazgos, cobertura) e INFORME_EJECUCION_2026-11-15.md |
| 01_Manuales | Usuario.docx (operador de provisión) y Usuario_guion.txt. Sin capturas, por eso no hay Usuario_capturas.txt |
| 02_Casos_de_Uso | Usuario_Casos_de_Uso.docx (15 casos) |
| 03_QA | Checklist_QA.docx (15 fichas, todas NOT TESTED) |
| 04_Trazabilidad | Trazabilidad.xlsx (panel inmovilizado y autofiltro) |
| 05_Problema_PlanMejora | PROBLEMAS.md y PLAN_MEJORA.md |
| _pdf | PDF de los cuatro DOCX con índice calculado (Analisis 4 págs., Usuario 7, Casos 7, QA 6) |

No hay carpeta _imagenes: el repositorio no tiene pantallas propias y no se levantó Moodle.

## Regenerar
```
python docs/Documentacion_Sistema2/_generador/generar.py
python docs/Documentacion_Sistema2/_generador/exportar_uno.py <docx> <pdf>   # uno por proceso
```
