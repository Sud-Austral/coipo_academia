# Plan de mejora — Academia CONAF v2 (2026-11-15)

Propone; no modifica código. Esfuerzos son estimaciones, no mediciones.

## P-004 (media) — automatizar la prueba de ámbito del gestor
- Causa raíz: 99_verificar.php:108-118 solo comprueba que gestorarea exista y sea asignable en categoría; la restricción real (no ver cursos de otra área) se deja a una prueba manual (50_rol_gestor.php:192-201).
- Solución: script de prueba en academia/pruebas/ que cree un curso oculto en 04, una cuenta temporal con el rol en 01, y compruebe con has_capability('moodle/course:viewhiddencourses', contexto del curso de 04) que da falso; borra lo creado al final. Alternativa descartada: agregarlo a 99_verificar.php, porque ese script solo cuenta y no debería escribir.
- Prueba de mutación: asignar el rol en CONTEXT_SYSTEM y ver la prueba en rojo.
- Criterio: la prueba devuelve 0 con el rol bien configurado y 1 con la mutación.
- Riesgos: escribe en la base; correr solo en academia_v2 (el freno de lib.php:175 lo cubre).
- Quién decide: TI de CONAF, si se aceptan escrituras temporales en la instancia. Esfuerzo: 0,5 día (estimación).

## P-003 (media) — ejecutar la provisión en un entorno local
- Causa raíz: el entorno de documentación no tenía Docker activo ni imágenes.
- Pasos: levantar el compose contra un PostgreSQL local de prueba, correr 10→99 y adjuntar la salida al paquete; tomar capturas del tema.
- Criterio: 99_verificar.php sale con código 0 y la salida queda guardada. Esfuerzo: 1 día (estimación). Quién decide: si se autoriza descargar las imágenes.

## P-001 (media) — decidir si el gestor necesita manual propio
- Causa raíz: el rol existe pero sin personas (50_rol_gestor.php:190-192).
- Pregunta abierta para la Dirección: ¿cuándo se designan gestores? Al designarlos, generar manual «Gestor de Área Temática» con capturas. Esfuerzo: 1 día (estimación).

## P-002 (baja) — actualizar el comentario de 70_informes.php
- Pasos: verificar la línea equivalente en reportbuilder de Moodle 5.2.1 y corregir el comentario. Criterio: el comentario cita la versión real. Esfuerzo: 0,5 h (estimación).

## P-005 (baja) — madurez del tema
- Pregunta abierta: ¿cuándo pasa a BETA/STABLE y con qué criterio? Esfuerzo: no estimable sin la decisión.

## Hoja de ruta
| Problema | Fase | Esfuerzo (estimado) | Responsable |
|---|---|---|---|
| P-003 | inmediato | 1 día | [POR VALIDAR] |
| P-004 | corto | 0,5 día | [POR VALIDAR] |
| P-002 | corto | 0,5 h | [POR VALIDAR] |
| P-001 | mediano | 1 día | Dirección decide |
| P-005 | mediano | - | [POR VALIDAR] |
