# Avance de la entrevista de requisitos — coipo_moodle

Última actualización: 2026-07-29. Este archivo se borra cuando exista `docs/REQUISITOS.md`.

## Reencuadre del proyecto

No es una app nueva: es la **migración de la instancia Moodle de https://cursos.conaf.cl**
(hoy alojada por la empresa externa **Lazzos**) hacia servidores propios de CONAF.

## Área 1 — Contexto del negocio (CERRADA)

- **Problema**: la plataforma de cursos de CONAF depende de un proveedor externo (Lazzos);
  se quiere en infraestructura propia.
- **Sistema actual**: Moodle en producción en cursos.conaf.cl. **Versión desconocida**,
  motor de BD desconocido, plugins desconocidos. Sin acceso administrativo confirmado.
- **Escala actual**: aprox. 1 usuario y 1 curso. **Meta: escalar a todo CONAF.**
- **Quién lo pidió**: **Dirección Ejecutiva de CONAF (nivel nacional)**. Es quien valida
  el resultado final.
- **Plazos**: no hay plazo formal impuesto. Metas propias de Luis:
  - **1 semana** → instancia Moodle propia funcionando (aprendizaje/pruebas).
  - **~1 mes** → migración resuelta, sujeto a cómo avancen las conversaciones con Lazzos.
  - El riesgo es bajo porque hoy hay 1 curso y 1 usuario: poco que perder si algo falla.
- Luis contempla, como alternativa válida, instancia Moodle nueva con Docker + restaurar
  el curso, en vez de migrar byte a byte (decisión abierta, depende de lo que responda
  Lazzos).

## Plan acordado con el usuario

1. **Instancia Moodle local de prueba (Docker)** para conocer el sistema mientras avanzan
   las gestiones con Lazzos. (Pedida por Luis; aún no construida.)
2. **Primer correo a Lazzos** en `comunicaciones/` (markdown) solicitando inventario
   técnico + respaldo completo + coordinación. (En redacción 2026-07-29.)

## Infraestructura CONAF destino (de INSUMO/)

- Servidor de apps **172.31.2.41** (Docker, carpeta `/opt/apps/<repo>/`) + **PostgreSQL 17
  compartido en 172.31.2.40** (fuera de Docker).
- Deploy: push a `main` → workflow reusable `Sud-Austral/infra-docker-base`.
- Nginx del servidor como reverse proxy; solo el servicio `app` publica puerto (`APP_PORT`).
- **OJO**: servidor aún sirve HTTP plano (sin cert `*.conaf.cl`) según Guía 8.
- **OJO Moodle vs patrón coipo**: Moodle es PHP monolítico (no FastAPI+React); habrá que
  adaptar el patrón. Además, si el Moodle actual usa MySQL/MariaDB, migrar a PostgreSQL
  no es trivial — el motor de BD actual es dato crítico que debe responder Lazzos.
- **⚠ Seguridad**: `INSUMO/setup_bd.sql` contiene una contraseña real del rol `iam`.
  Avisado al usuario 2026-07-29; pendiente decisión (no commitear / rotar).

## Áreas pendientes de la entrevista

- Área 1: quién pidió la migración (jefatura/área) y plazos.
- Áreas 2–8 completas (usuarios futuros, funcionalidades del Moodle a escalar, alcance,
  integraciones — ej. ¿autenticación institucional?, datos personales/normativa chilena,
  restricciones del servidor, criterios de éxito del corte).

## Pregunta abierta al usuario (sin responder aún)

- ¿Qué acceso tiene hoy Luis a cursos.conaf.cl? (admin del sitio, contacto en Lazzos, o
  solo usuario normal). Respuesta implícita: sin acceso; las gestiones recién comienzan
  con el primer correo.
