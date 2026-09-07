# Que se construyo

## Que hace

Aplica sobre una plataforma de formacion una estructura de organizacion que
antes no estaba, y lo hace de manera repetible: una secuencia numerada de
guiones que se ejecutan en orden y dejan puesto cada elemento. Permite crear
el arbol de categorias [academia/cli/10_categorias.php], agregar campos de
clasificacion a los cursos [academia/cli/20_campos_curso.php], crear cohortes
[academia/cli/30_cohortes.php], cargar un marco de competencias
[academia/cli/40_competencias.php], crear un rol de gestion delegado
[academia/cli/50_rol_gestor.php], clasificar los cursos ya existentes segun ese
esquema [academia/cli/60_clasificar_cursos.php], dejar informes disponibles
[academia/cli/70_informes.php], construir una plantilla maestra de curso
[academia/cli/80_plantilla_maestra.php], generar cursos vacios a partir de ella
[academia/cli/90_cursos_esqueleto.php], fijar los ajustes generales del sitio
[academia/cli/95_ajustes_sitio.php] y comprobar al final que todo quedo como
correspondia [academia/cli/99_verificar.php].

Esos guiones comparten una base comun [academia/cli/bootstrap.php]
[academia/cli/lib.php], con utilidades separadas para lo que toca cursos
[academia/cli/lib_cursos.php] y para lo que toca evaluaciones
[academia/cli/lib_quiz.php]. Hay ademas pruebas propias: una que compila el
tema [academia/pruebas/compilar-tema.php] y otra que ensaya la propuesta de
clasificacion antes de aplicarla
[academia/pruebas/propuesta-clasificacion.php].

Aporta tambien la identidad visual, como tema propio de la plataforma
[plugins/theme/academia/config.php] [plugins/theme/academia/settings.php], y
un contenido pedagogico concreto: un banco de preguntas
[academia/datos/banco-IF-151.xml] producido por una herramienta de conversion
escrita para eso [academia/herramientas/convertir-banco-IF-151.py].

Y aporta la operacion: como se levanta el servicio [Dockerfile]
[docker-compose.yml], con que configuracion de la plataforma
[docker/config.php], de servidor web [docker/apache-moodle.conf], de motor
[docker/php.ini], de tareas periodicas [docker/moodle-crontab] y de
comprobacion de estado [docker/health.php]; y como se despliega en produccion
[.github/workflows/deploy-prod.yml].

## Roles: quien ve que

Esta es la parte con mejor respaldo del documento.

[INFERIDO] La plataforma distingue siete posiciones, y el repositorio escribio
un manual para cada una: administracion [manuales/01-manager.md], creacion de
cursos [manuales/02-course-creator.md], docencia con edicion
[manuales/03-teacher.md], docencia sin edicion
[manuales/04-non-editing-teacher.md], estudio [manuales/05-student.md],
visita sin cuenta [manuales/06-guest.md] y usuario autenticado sin rol
adicional [manuales/07-authenticated-user.md], mas un manual especifico para lo
que ese ultimo ve en la portada
[manuales/08-authenticated-user-frontpage.md]. Hay un indice que los reune
[manuales/README.md].

[INFERIDO] A esas siete se suma una octava creada por este proyecto: un rol de
gestion delegado [academia/cli/50_rol_gestor.php]. Que atribuciones concretas
le da, y a quien se le asigna, es [PENDIENTE].

[INFERIDO] Los permisos no los define este repositorio sino la plataforma que
lo aloja: la unica declaracion de capacidades que aparece en la evidencia
pertenece al complemento de terceros
[plugins/mod/customcert/db/access.php], no al codigo propio.

[PENDIENTE] Que puede hacer cada rol en concreto sobre esta instalacion. Los
manuales lo describen, pero el analizador no accede a su contenido: solo consta
que existen.

## De donde salen los datos

[INFERIDO] Las decisiones de estructura entran desde archivos separados por
comas, uno por tipo de decision: categorias [academia/datos/categorias.csv],
campos de curso [academia/datos/campos-curso.csv], cohortes
[academia/datos/cohortes.csv] y competencias
[academia/datos/competencias.csv]. Los guiones numerados los leen y los
aplican.

[INFERIDO] El contenido evaluativo entra desde un archivo en el formato de
intercambio de la plataforma [academia/datos/banco-IF-151.xml], generado a
partir de otro formato por una herramienta propia
[academia/herramientas/convertir-banco-IF-151.py].

[INFERIDO] La base de datos se crea con guiones propios, en dos versiones
[db/setup_bd.sql] [db/setup_bd_v2.sql]. La segunda comprueba la existencia de
las tablas de usuarios, cursos y categorias de la plataforma
[db/setup_bd_v2.sql:168] [db/setup_bd_v2.sql:169] [db/setup_bd_v2.sql:170] y
revisa los permisos concedidos [db/setup_bd_v2.sql:39]
[db/setup_bd_v2.sql:81].

[INFERIDO] La configuracion sensible vive fuera del repositorio: hay tres
plantillas de variables de entorno, una por escenario [.env.example]
[.env.v2.example] [.env.migracion.example].

[PENDIENTE] Quien es dueno del contenido de esos archivos de decisiones, quien
los aprueba y quien los mantiene al dia. Los archivos estan; el acuerdo detras
de ellos no consta.

[PENDIENTE] De donde salio el banco de preguntas original y quien responde por
su contenido.

## Que NO hace

Ausencias afirmables porque el analizador recorrio esas categorias de forma
exhaustiva. Van marcadas igual.

[INFERIDO] No expone ninguna interfaz de programacion propia: la extraccion no
registro ningun endpoint en el codigo propio. La unica declaracion de servicios
web de toda la evidencia pertenece al complemento de terceros
[plugins/mod/customcert/db/services.php].

[INFERIDO] No declara ninguna variable de entorno en su codigo: la extraccion
no registro ninguna. Lo que hay son plantillas que enumeran las que espera el
despliegue [.env.example].

[INFERIDO] No es una aplicacion con pantallas propias: no hay componentes de
interfaz ni manifiesto de aplicacion cliente en la evidencia. Lo que se ve en
pantalla lo pone la plataforma, y este repositorio solo cambia su apariencia
[plugins/theme/academia/config.php].

[INFERIDO] Los guiones no son idempotentes por declaracion: nada en la
evidencia dice que se puedan volver a correr sin efecto. Lo que si existe es
una comprobacion posterior [academia/cli/99_verificar.php].

## Codigo de terceros

[INFERIDO] El repositorio incorpora un complemento de emision de certificados
que no es codigo propio: su manifiesto viene marcado como de terceros
[plugins/mod/customcert/composer.json] y trae su propia integracion continua
[plugins/mod/customcert/.github/workflows/moodle-ci.yml]. Nada de lo que ese
complemento hace debe leerse como capacidad construida por este proyecto.

[INFERIDO] Ese complemento llega con una cantidad inusual de clases de servicio
y de pruebas unitarias
[plugins/mod/customcert/classes/service/certificate_issue_service.php]
[plugins/mod/customcert/tests/certificate_issue_service_test.php], lo que
podria indicar que se modifico localmente. Si se modifico, y con que criterio,
es [PENDIENTE]: la evidencia no permite compararlo con el original.

## Iteraciones

[INFERIDO] Hay al menos dos generaciones documentadas y conviven: dos guiones
de creacion de base [db/setup_bd.sql] [db/setup_bd_v2.sql], dos plantillas de
entorno correspondientes [.env.example] [.env.v2.example], y un documento
dedicado a explicar la segunda [docs/V2-ACADEMIA.md].

[INFERIDO] Hubo un traslado de infraestructura entre medio, con su propio juego
de piezas [docker-compose.migracion.yml] [.env.migracion.example] y sus
documentos [docs/MIGRACION.md] [docs/TRASLADO.md].

[INFERIDO] Existe ademas documentacion de operacion [docs/GUIA-OPERATIVA.md],
una guia de despliegue por repositorio
[INSUMO/guia-6-github-actions-por-repo.md], una lista de comprobacion previa al
despliegue [INSUMO/guia-8-prompt-checklist-pre-deploy.md], notas de contenedores
[INSUMO/DOCKER.md] y un registro de entrevista de avance
[docs/entrevista-avance.md]. No hay CHANGELOG ni etiquetas de version en la
evidencia.

[INFERIDO] El diagnostico y la propuesta que originaron este trabajo estan
guardados como documentos ofimaticos y como un prototipo de pantallas
[INSUMO_MEJORA/Academia-CONAF_Propuesta-Institucional.docx]
[INSUMO_MEJORA/IF-151_Diseno-de-curso_Academia-CONAF.docx]
[INSUMO_MEJORA/prototipo-academia-conaf.html]. El analizador no accede a su
contenido, de modo que lo que dicen es [PENDIENTE].
