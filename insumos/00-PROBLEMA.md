# Que estaba roto, deducido de lo que se construyo

Este documento se escribe hacia atras, desde lo que existe. La cadena de
inferencia es debil y va escrita como tal.

## Que se construyo, y que problema sugiere

Lo que se construyo no es una plataforma sino la estructura que va dentro de
una. La pieza central es una secuencia numerada de guiones que se aplican en
orden y que van poniendo, uno por uno, los elementos de organizacion:
categorias [academia/cli/10_categorias.php], campos de clasificacion de curso
[academia/cli/20_campos_curso.php], cohortes
[academia/cli/30_cohortes.php], competencias
[academia/cli/40_competencias.php], un rol de gestion delegado
[academia/cli/50_rol_gestor.php], la clasificacion de los cursos existentes
[academia/cli/60_clasificar_cursos.php], informes
[academia/cli/70_informes.php], una plantilla maestra de curso
[academia/cli/80_plantilla_maestra.php], cursos vacios a partir de ella
[academia/cli/90_cursos_esqueleto.php] y los ajustes generales del sitio
[academia/cli/95_ajustes_sitio.php], con una comprobacion final
[academia/cli/99_verificar.php].

[INFERIDO] El problema no era que faltara la plataforma: era que la plataforma
estaba sin ordenar. Que se escriban guiones para crear categorias, campos,
cohortes, competencias y un rol delegado indica que esas cuatro cosas no
existian o estaban mal puestas. Ese diagnostico aparece ademas escrito por el
propio equipo [README.md], donde se afirma que el problema es la arquitectura
de informacion y no la plataforma. Las cifras concretas de ese diagnostico son
afirmaciones del propio repositorio y quedan [PENDIENTE] hasta que alguien las
confirme.

[INFERIDO] El problema tenia tambien una parte de infraestructura, y esa parte
ya estaba resuelta cuando empezo esta: hay un juego completo de piezas de
despliegue propio [Dockerfile] [docker-compose.yml] [docker/config.php]
[docker/apache-moodle.conf] [docker/php.ini] [docker/moodle-crontab], y un
segundo juego dedicado exclusivamente a una migracion
[docker-compose.migracion.yml] [.env.migracion.example], con su documento
[docs/MIGRACION.md] y otro sobre el traslado [docs/TRASLADO.md].

[INFERIDO] Y hubo una decision de partir de cero en vez de arrastrar: existen
dos guiones de creacion de base, uno original y uno de segunda version
[db/setup_bd.sql] [db/setup_bd_v2.sql], y el segundo comprueba explicitamente
que existan las tablas de usuarios, cursos y categorias antes de continuar
[db/setup_bd_v2.sql:168] [db/setup_bd_v2.sql:169]
[db/setup_bd_v2.sql:170]. Que la segunda version verifique en vez de copiar
sugiere que no se heredo el contenido de la primera.

## Quien sufre el problema

Esta es, por una vez, la parte solida del documento, porque los roles estan
nombrados en el repositorio.

[INFERIDO] Hay siete posiciones distintas frente a la plataforma, cada una con
su manual propio: quien administra [manuales/01-manager.md], quien crea cursos
[manuales/02-course-creator.md], quien ensena
[manuales/03-teacher.md], quien ensena sin editar
[manuales/04-non-editing-teacher.md], quien estudia
[manuales/05-student.md], quien entra como invitado
[manuales/06-guest.md] y quien esta autenticado sin mas rol
[manuales/07-authenticated-user.md], con un caso aparte para la portada
[manuales/08-authenticated-user-frontpage.md]. Que se haya escrito un manual
por rol es evidencia de que la distincion importa; que la lista este completa
es [PENDIENTE].

[INFERIDO] A esas posiciones se agrega una nueva creada por este proyecto: un
rol de gestion delegado que antes no existia
[academia/cli/50_rol_gestor.php].

[PENDIENTE] Cuantas personas hay en cada rol. El repositorio contiene guiones
que crean estructura, no dotacion, y ninguna cifra de usuarios se puede deducir
de aqui.

## Como lo resolvian antes

[INFERIDO] Las decisiones de clasificacion se venian tomando fuera del sistema,
en planillas, y este proyecto las trae adentro: las categorias, los campos de
curso, las cohortes y las competencias entran desde archivos separados por
comas [academia/datos/categorias.csv] [academia/datos/campos-curso.csv]
[academia/datos/cohortes.csv] [academia/datos/competencias.csv], que los
guiones leen. Un archivo de ese formato es, casi siempre, una hoja de calculo
exportada.

[INFERIDO] El banco de preguntas tambien venia de otro formato y hubo que
convertirlo: existe una herramienta escrita para eso
[academia/herramientas/convertir-banco-IF-151.py] y el resultado en el formato
que la plataforma entiende [academia/datos/banco-IF-151.xml].

[PENDIENTE] Quien mantenia esas planillas, cada cuanto y cuanto tardaba en
actualizarlas.

## Que pasa si no se hace nada

[PENDIENTE], sin excepcion. El repositorio contiene un diagnostico propio
[README.md] y documentos de propuesta [INSUMO_MEJORA/Academia-CONAF_Propuesta-Institucional.docx],
pero ninguno de los dos es respuesta a esta pregunta y ninguno se pudo leer:
el analizador no accede al contenido de esos documentos.

## Volumen

[INFERIDO] El orden de magnitud de las decisiones de estructura es pequeno: son
cuatro archivos de configuracion en formato de planilla
[academia/datos/categorias.csv] [academia/datos/campos-curso.csv]
[academia/datos/cohortes.csv] [academia/datos/competencias.csv], no un volcado
masivo.

[INFERIDO] El volumen de contenido pedagogico se concentra en un unico curso
por ahora: el banco de preguntas y la herramienta que lo convierte llevan el
mismo identificador de curso en su nombre
[academia/datos/banco-IF-151.xml]
[academia/herramientas/convertir-banco-IF-151.py], y hay un documento de diseno
del mismo curso [INSUMO_MEJORA/IF-151_Diseno-de-curso_Academia-CONAF.docx].

[PENDIENTE] Cuantos cursos, cuantos usuarios y cuantas inscripciones hay o se
esperan. La comprobacion de existencia de tablas
[db/setup_bd_v2.sql:168] no dice cuantas filas contienen.

## Quien decide que esta terminado

[PENDIENTE], sin excepcion. Hay un guion de verificacion
[academia/cli/99_verificar.php] y una lista de comprobacion previa al
despliegue [INSUMO/guia-8-prompt-checklist-pre-deploy.md], pero eso comprueba
estado tecnico, no cierra un acuerdo.

## Marco normativo

[VERIFICAR] La plataforma administra usuarios y su avance formativo
[db/setup_bd_v2.sql:168]. Los registros de aprendizaje de personas
identificadas son datos personales. Que tratamiento tienen, con que base y por
cuanto tiempo se conservan, no se resuelve leyendo codigo.

[VERIFICAR] El repositorio incluye un complemento de terceros dedicado a emitir
certificados [plugins/mod/customcert/composer.json]. Un certificado emitido a
nombre de una persona tiene efectos, y quien responde por su validez es una
pregunta que no contesta el codigo.

[VERIFICAR] Hay documentacion sobre el cifrado del trafico
[docs/TLS-HTTPS.md] y configuraciones de servidor asociadas
[docs/nginx-academia.conf] [docs/nginx-academia2.conf]. Si el nivel de
proteccion es el exigible para el tipo de dato que circula, lo tiene que cerrar
quien corresponda.
