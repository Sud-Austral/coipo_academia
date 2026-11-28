# Problemas abiertos — Academia CONAF v2 (2026-11-15)

Gravedad: crítica (pérdida de datos o producción caída), alta (función principal rota), media (riesgo o hueco de verificación), baja (forma o documentación).

| Id | Título | Gravedad | Afecta | Evidencia | Certeza | Estado |
|---|---|---|---|---|---|---|
| P-001 | El triaje decía «sin roles propios», pero el repositorio crea el rol gestorarea | media | Documentación / rol gestor | academia/cli/50_rol_gestor.php:24, 57 | verificado en el código | abierto |
| P-002 | Comentario cita Moodle 4.5 y una línea de reportbuilder de esa versión; la Academia es 5.2.1 | baja | 70_informes.php | academia/cli/70_informes.php:5 | verificado en el código | abierto |
| P-003 | Ningún script se ejecutó al documentar (sin PHP local, Docker detenido) | media | Todo el paquete | «docker images»: no conecta al daemon | observado | abierto |
| P-004 | La prueba de ámbito del gestor es manual; 99_verificar.php solo comprueba existencia y contexto del rol | media | Rol gestorarea | 50_rol_gestor.php:192-201; 99_verificar.php:108-118 | verificado en el código | abierto |
| P-005 | theme_academia en madurez ALPHA | baja | Tema | plugins/theme/academia/version.php | verificado en el código | abierto |

## Cerrados

Ninguno.
