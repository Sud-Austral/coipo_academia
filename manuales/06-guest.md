# Manual de Guest (Invitado) — CAMPUS CONAF

> Este manual es para quien quiere mirar un curso sin tener cuenta, y para el profesor o encargado que debe decidir si abre su curso a visitantes. Al terminar de leerlo usted sabrá qué es el modo invitado, qué alcanza a ver, qué no puede hacer nunca, y cómo se activa o se desactiva en un curso.

---

## 1. Antes de empezar

### 1.1 Qué es Moodle

Moodle es un sitio de internet donde CONAF publica sus cursos.

Se entra con el navegador, igual que se entra al correo. No hay que instalar nada en el computador.

Adentro hay **cursos**: por ejemplo, cursos de prevención de incendios forestales o del Sistema de Comando de Incidentes (SCI). Cada curso tiene materiales para leer y actividades para hacer.

Hoy el sitio de CONAF tiene 2.869 personas registradas y 37 cursos.

### 1.2 Qué es un "rol"

Un **rol** es el conjunto de cosas que una persona tiene permitido hacer dentro de la plataforma.

El rol no cambia lo que usted es en CONAF. Solo cambia qué botones le aparecen en la pantalla.

Por ejemplo: un **estudiante** puede entregar tareas. Un **profesor** puede poner notas. Son dos roles distintos.

### 1.3 Qué es el rol "Invitado"

Aquí viene lo más importante de este manual, y conviene leerlo despacio.

**El invitado no es una persona. Es una manera de entrar.**

Los demás roles se le asignan a alguien: usted es estudiante *del curso tal*. El invitado no funciona así. Es un modo de visita, sin nombre y sin dueño.

Moodle trae de fábrica una cuenta llamada `guest`, que en español aparece como **Invitado**. Todo el que entra en modo invitado usa esa misma cuenta compartida. No es "su" cuenta: es la de todos y la de nadie.

Piénselo como la sala de espera de una oficina. Usted puede entrar, sentarse y leer los folletos del mostrador. No puede entrar a las oficinas, firmar documentos ni dejar nada escrito.

### 1.4 Qué puede y qué no puede un invitado, en una línea

- **Puede**: mirar los contenidos de un curso que esté abierto a visitantes.
- **No puede**: entregar tareas, responder cuestionarios, escribir en foros, recibir notas ni dejar registro de que estuvo ahí.

### 1.5 Adelanto de la recomendación

CAMPUS CONAF guarda datos personales de 2.869 funcionarios. Por eso, la recomendación de este manual es **mantener el acceso de invitados desactivado**, salvo que haya una razón concreta y por escrito para abrirlo.

La explicación completa está en el punto 5.8.

---

## 2. Sus datos de acceso

### 2.1 Este manual no trae usuario ni contraseña, y es a propósito

Los otros manuales de esta serie traen una cuenta de práctica: un nombre de usuario y una contraseña para probar.

**Este no. Y no es un olvido.** Hay tres razones, y las tres son de cómo está hecho Moodle:

**Razón 1: la cuenta de invitado no tiene contraseña.**
La cuenta `guest` viene sin clave por diseño. No es que la clave sea secreta o se haya perdido: simplemente no existe ninguna. Por eso no se puede escribir en el formulario de entrada como se hace con las demás cuentas.

**Razón 2: el rol Invitado no se le puede asignar a nadie.**
Se revisó la base de datos del sitio. El rol Invitado está definido para no poder darse a ninguna persona, en ningún curso. Aunque un administrador quisiera, la pantalla no se lo permitiría. Es el único rol del sitio con esa característica.

**Razón 3: no haría falta.**
Si el modo invitado está activado en un curso, cualquiera entra sin escribir usuario ni clave. Y si está desactivado, no hay usuario ni clave que sirva.

### 2.2 Comparación con los demás roles

| | Los otros roles | El rol Invitado |
|---|---|---|
| ¿Tiene nombre de usuario? | Sí, uno por persona | No. Es una cuenta compartida |
| ¿Tiene contraseña? | Sí | No, y nunca la tuvo |
| ¿Se le asigna a alguien? | Sí | No. No se puede |
| ¿Queda registro de lo que hizo? | Sí | No |
| ¿Recibe calificaciones? | Sí | No |

### 2.3 Cuántos permisos tiene

Se contaron los permisos de cada rol directamente en la base de datos del sitio. Estos son los números:

| Rol | Cantidad de permisos |
|---|---|
| Manager | 593 |
| Profesor (Teacher) | 486 |
| Non-editing teacher | 224 |
| Usuario autenticado | 116 |
| Estudiante | 84 |
| **Invitado (Guest)** | **30** |

Treinta permisos, y casi todos son de mirar.

Además, el rol Invitado tiene una particularidad que ningún otro rol del sitio tiene: **un** permiso suyo está marcado como **prohibido**. Es `block/online_users:viewlist`, el que permite ver la lista de quiénes están conectados en ese momento. Dato comprobado en la base de datos del sitio: los otros siete roles no tienen ninguna prohibición.

"Prohibido" es el candado más fuerte que existe en Moodle. Un permiso simplemente "no concedido" se puede devolver dándole a la persona otro rol encima. Un permiso **prohibido** no: queda cerrado con llave y ya no se abre por ningún camino.

Que el único permiso prohibido sea justo ese no es casualidad: un invitado no está identificado, así que no debe poder ver quién más anda navegando el sitio.

### 2.4 Si a usted le dieron un usuario y una clave, este no es su manual

Si alguien de CONAF le entregó un nombre de usuario personal, usted **no** es invitado: tiene cuenta propia.

Use el manual del rol que le corresponda (estudiante, profesor, etc.). Entrar como invitado le mostraría menos de lo que usted tiene derecho a ver.

### 2.5 Aviso importante sobre las contraseñas del sitio

Esto no afecta al invitado, porque el invitado no tiene contraseña. Pero conviene que lo sepa igual.

**En CAMPUS CONAF el envío de correos está desactivado.** La opción "¿Olvidó su contraseña?" **no funciona**: el sitio no puede mandarle ningún correo.

Si usted tiene cuenta propia y olvida su clave, la única salida es **pedírsela al administrador de la plataforma**. No espere un correo, porque no va a llegar.

Y si alguna vez cambia su contraseña, recuerde la regla del sitio: debe tener **al menos una letra mayúscula, al menos un número y al menos un símbolo** (por ejemplo un punto, un guion o un signo de exclamación).

---

## 3. Entrar por primera vez

Esta sección explica cómo se entra en modo invitado. Está escrita desde cero.

**Ojo, léalo antes de intentarlo:** el modo invitado solo funciona si alguien lo activó primero. Puede estar apagado en todo el sitio, o estar encendido solo en algunos cursos. Si sigue los pasos y no ve la opción, no es error suyo. Está apagado, y desde su computador no hay nada que hacer.

### Bloque A — Abrir el sitio

**Paso 1.** Encienda el computador y espere a que aparezca el escritorio.

**Paso 2.** Busque el **navegador**. El navegador es el programa con que se entra a internet. Los más comunes son:
- Google Chrome: un círculo de colores (rojo, amarillo, verde) con azul al centro.
- Microsoft Edge: una letra "e" azul y verde en forma de ola.
- Mozilla Firefox: un zorro naranja rodeando un círculo azul.

**Paso 3.** Haga doble clic sobre ese icono. Es decir: apunte con el mouse y presione dos veces seguidas, rápido, el botón izquierdo.

*Lo que debería ver:* se abre una ventana grande y blanca que ocupa casi toda la pantalla.

**Paso 4.** Mire la parte de arriba de esa ventana. Hay una franja alargada donde se escribe. Esa franja se llama **barra de direcciones**. Ahí se escribe a qué sitio quiere ir. No la confunda con el recuadro de búsqueda que aparece al centro de la página.

**Paso 5.** Haga un clic dentro de la barra de direcciones.

*Lo que debería ver:* el texto que había adentro queda pintado de azul, o desaparece, y aparece una rayita que parpadea.

**Paso 6.** Escriba exactamente esto, sin espacios:

```
academia.conaf.cl
```

**Paso 7.** Presione la tecla **Enter** (la tecla grande del lado derecho del teclado, con una flecha que dobla hacia la izquierda).

*Lo que debería ver:* la pantalla cambia y aparece la portada de CAMPUS CONAF, con el logo de CONAF.

**Si no aparece nada** y sale un aviso de "no se puede acceder a este sitio": revise que escribió bien la dirección, sin espacios y sin punto al final. Si sigue igual, puede que su computador no tenga conexión, o que el sitio solo se vea desde la red interna de CONAF. Consúltelo con el encargado informático de su unidad.

### Bloque B — Buscar la entrada de invitado

Hay dos caminos. Pruebe el primero; si no resulta, el segundo.

#### Camino 1: el botón en la pantalla de acceso

**Paso 8.** En la portada, busque arriba a la derecha la palabra **Acceder** o **Entrar**. Haga un clic sobre ella.

*Lo que debería ver:* aparece una pantalla con dos recuadros para escribir: uno dice "Nombre de usuario" y el otro "Contraseña".

**Paso 9.** No escriba nada en esos recuadros. Mire **debajo** de ellos.

**Paso 10.** Si el modo invitado está activado en todo el sitio, ahí aparece un botón que dice **Entrar como invitado**. Haga un clic sobre él.

*Lo que debería ver:* la pantalla cambia y vuelve a la portada, pero ahora arriba a la derecha, en vez de "Acceder", dice **Invitado**.

**Si NO ve ese botón:** el administrador tiene apagada esa opción para todo el sitio. Pase al Camino 2.

#### Camino 2: entrar directo a un curso abierto

Este camino sirve cuando el administrador apagó el botón general, pero un profesor abrió su curso a visitantes.

**Paso 11.** Necesita la dirección exacta del curso. Se la tiene que dar el profesor. Tiene esta forma:

```
https://academia.conaf.cl/course/view.php?id=41
```

Ese número del final (41) cambia según el curso. El 41 es el curso de práctica de estos manuales.

**Paso 12.** Haga clic en la barra de direcciones, escriba la dirección completa y presione **Enter**.

**Paso 13.** Puede pasar una de estas cuatro cosas. Lea las cuatro y vea cuál le tocó:

- **Entra directo al curso.** Perfecto, ya está adentro.
- **Aparece un aviso que dice que puede entrar como invitado, con un botón para confirmarlo.** Haga clic en ese botón.
- **Aparece un recuadro pidiendo una contraseña de acceso de invitados.** Vaya al Bloque C.
- **Aparece "No se puede matricular" o le pide iniciar sesión.** Ese curso está cerrado a visitantes. No hay nada que hacer desde su lado: pídale al profesor que lo abra, o que le cree una cuenta.

### Bloque C — Si el curso pide una contraseña de invitados

Algunos profesores abren el curso a visitantes, pero le ponen una palabra clave para que no entre cualquiera.

**Paso 14.** Esa palabra se la da el profesor. No es su contraseña personal y no la puede adivinar.

**Paso 15.** Haga clic dentro del recuadro que dice **Contraseña** y escríbala tal cual se la pasaron.

**Ojo:** distingue mayúsculas de minúsculas. `Bosque26` no es lo mismo que `bosque26`. Si tiene dudas, pida que se la escriban.

**Paso 16.** Haga clic en el botón **Enviar** o **Continuar**, abajo del recuadro.

*Lo que debería ver:* se abre la portada del curso, con su nombre arriba y los materiales en el centro.

*Si la rechaza:* aparece un aviso en rojo diciendo que la contraseña es incorrecta. Vuelva a escribirla con cuidado. Si no resulta a la tercera, pídala de nuevo: probablemente el profesor la cambió.

---

## 4. Reconocer la pantalla

Ya está adentro. Ahora hay que entender qué es cada cosa. Se describe de arriba hacia abajo.

### 4.1 La franja de arriba

Cruza la pantalla de lado a lado. A la izquierda está el logo de CONAF; si le hace clic, vuelve a la portada.

A la derecha aparece la palabra **Invitado**. Esa es la señal de que usted entró en modo visita y no como una persona con cuenta.

**Compruébelo ahora.** Si arriba a la derecha dice su nombre y apellido, usted **no** es invitado: entró con una cuenta propia y este manual no le corresponde.

### 4.2 El menú de arriba a la derecha

Al hacer clic sobre la palabra **Invitado** se abre hacia abajo una lista corta de opciones. A eso se le llama **menú desplegable**: una lista que aparece al hacer clic y se cierra sola.

Como invitado, esa lista es muy breve. Lo único que le va a interesar es **Cerrar sesión**, que sirve para salir.

### 4.3 La zona del centro

Es la parte más grande y es donde está el contenido real del curso.

Si entró a un curso, verá el nombre del curso arriba y, más abajo, el material dividido en bloques.

En el curso de práctica de estos manuales el material está dividido en **3 secciones**. Una **sección** es simplemente un bloque de contenidos agrupados, como los capítulos de un cuadernillo.

### 4.4 Los enlaces del contenido

Dentro de cada sección hay líneas de texto con un pequeño dibujo al lado. Cada una es un material o una actividad.

- Un dibujo de hoja de papel suele ser un archivo para leer o descargar (por ejemplo un PDF).
- Un dibujo de carpeta es un grupo de archivos.
- Un dibujo de globo de conversación es un **foro**, que es un tablero de mensajes.
- Un dibujo de marca de verificación suele ser un **cuestionario**, que es una prueba con preguntas.

Al pasar el mouse por encima, el texto se subraya y la flecha se convierte en una manito. Eso significa que se puede hacer clic.

### 4.5 Lo que NO va a ver (y es normal)

Como invitado, hay zonas que sencillamente no aparecen:

- **No verá "Calificaciones".** Los invitados no tienen notas.
- **No verá "Participantes"** o lo verá vacío. Es la lista de quiénes están en el curso; usted no está en ella.
- **No verá botones de "Editar".**
- **No verá "Mis cursos"** con contenido, porque un invitado no está inscrito en ningún curso.

Si algo de esto le falta, no es una falla. Es exactamente como debe ser.

---

## 5. Lo que usted puede hacer

### 5.1 Leer los materiales del curso

**Paso 1.** Dentro del curso, busque en el centro de la pantalla la línea del material que quiere abrir.

**Paso 2.** Haga un clic sobre el texto azul de esa línea.

*Lo que debería ver:* se abre el documento en la pantalla, o empieza a descargarse a su computador.

**Paso 3.** Si se descargó, el archivo queda normalmente en la carpeta **Descargas** de su computador. En la mayoría de los navegadores aparece además un aviso pequeño abajo a la izquierda o arriba a la derecha con el nombre del archivo.

**Paso 4.** Para volver al curso después de leer, use la **flecha hacia la izquierda** que está arriba a la izquierda de la ventana del navegador. Esa flecha significa "volver a la pantalla anterior".

### 5.2 Leer un foro (pero solo leer)

**Paso 1.** Haga clic en el nombre del foro.

*Lo que debería ver:* aparece una lista de conversaciones, con el título de cada una y quién la escribió.

**Paso 2.** Haga clic en el título de una conversación para leerla completa.

**Ojo:** no verá el botón para escribir una respuesta. Y si intenta llegar a él de otra forma, Moodle le mostrará un aviso diciendo que los invitados no pueden participar. Eso no se arregla; es el diseño del modo invitado.

### 5.3 Salir del modo invitado

Conviene hacerlo siempre, sobre todo en un computador compartido.

**Paso 1.** Haga clic arriba a la derecha, donde dice **Invitado**.

**Paso 2.** Se abre la lista de opciones. Haga clic en **Cerrar sesión**.

*Lo que debería ver:* vuelve a la portada y arriba a la derecha dice otra vez **Acceder**.

**Paso 3.** Cierre la ventana del navegador con la **X** de arriba a la derecha del todo.

### 5.4 Volver a entrar más adelante

No hay nada que recordar: no hay usuario ni clave. Solo repita los pasos de la sección 3.

Eso sí, sepa esto: **nada de lo que hizo antes se guardó**. Si leyó tres documentos ayer, hoy la plataforma no tiene idea de que usted estuvo. No hay historial, no hay avance, no hay "continuar donde lo dejó".

---

### 5.5 Para profesores y encargados: activar el acceso de invitados en un curso

Esta parte **no** es para el visitante. Es para quien tiene rol de **Profesor (Teacher)** o de **Manager** en el curso. Solo esos dos roles pueden hacerlo.

Antes de empezar, léase el punto 5.8: hay una recomendación institucional al respecto.

**Paso 1.** Entre a la plataforma con **su** cuenta de profesor, no como invitado.

**Paso 2.** Mire la franja de arriba de la página y haga clic en **Mis cursos**.

*Lo que debería ver:* aparecen recuadros, uno por cada curso donde usted es profesor.

**Paso 3.** Haga clic sobre el nombre del curso que quiere modificar.

**Paso 4.** Mire debajo del nombre del curso. Hay una fila de palabras: **Curso**, **Configuración**, **Participantes**, **Calificaciones**, **Informes**, **Más**.

**Paso 5.** Haga clic en **Participantes**.

*Lo que debería ver:* aparece el listado de las personas inscritas en el curso.

**Paso 6.** Arriba a la izquierda de ese listado hay un recuadro que dice **Usuarios matriculados** y tiene una flechita hacia abajo. Ese recuadro es un **menú desplegable**: al hacerle clic se abre una lista de opciones. Haga clic sobre él.

*Lo que debería ver:* se despliega una lista de opciones hacia abajo.

**Paso 7.** En esa lista, haga clic en **Métodos de matriculación**.

("Matricular" en Moodle significa inscribir a alguien en un curso. Un "método de matriculación" es una de las formas en que la gente puede entrar a ese curso.)

*Lo que debería ver:* la pantalla cambia y aparece una tabla con varias filas. Una de ellas dice **Acceso de invitados**.

**Ojo:** si esa fila no aparece por ninguna parte, el administrador tiene desactivada esa función en todo el sitio. Usted no puede activarla desde el curso: hay que pedírselo al administrador.

**Paso 8.** Mire cómo se ve el texto **Acceso de invitados**:
- Si está en color gris claro o pálido, está **desactivado**.
- Si está en negro normal, está **activado**.

**Paso 9.** A la derecha de esa fila hay unos iconos pequeños. Uno tiene forma de **ojo**. Ese ojo enciende y apaga el método. Para saber cuál es, ponga el puntero del mouse encima de cada icono y espere un segundo: aparece un letrero con su nombre.

**Paso 10.** Para activarlo, haga un clic en el ojo.

*Lo que debería ver:* la fila deja de estar gris y el ojo cambia de aspecto. El cambio se guarda solo, no hay botón de confirmar.

### 5.6 Ponerle una contraseña al acceso de invitados

Sirve para que no entre cualquiera: solo quien tenga la palabra clave.

**Paso 1.** En la misma tabla de **Métodos de matriculación**, busque la fila **Acceso de invitados**.

**Paso 2.** A la derecha hay un icono con forma de **engranaje** (una rueda dentada). Haga clic en él.

*Lo que debería ver:* se abre una pantalla de ajustes con pocos campos.

**Paso 3.** El primer campo dice **Permitir acceso de invitados** y a su lado hay un recuadro con una flechita hacia abajo. Haga clic sobre ese recuadro.

*Lo que debería ver:* se abre una lista con dos opciones, **Sí** y **No**.

**Paso 4.** Haga clic en **Sí**.

**Paso 5.** Más abajo hay un campo llamado **Contraseña**. Haga clic dentro y escriba la palabra clave que quiere usar.

**Ojo, tres advertencias sobre esa palabra:**
- No es la contraseña de nadie. Es del curso, y todos los visitantes usan la misma.
- Distingue mayúsculas de minúsculas.
- No use jamás su propia contraseña de CONAF aquí. La va a tener que repartir por escrito.

**Paso 6.** Si quiere confirmar que la escribió bien, busque el icono de **ojo** al lado del campo. Al hacerle clic muestra las letras en vez de puntitos.

**Paso 7.** Baje hasta el final de la pantalla girando la rueda del mouse hacia usted.

**Paso 8.** Haga clic en el botón **Guardar cambios**.

*Lo que debería ver:* vuelve a la tabla de métodos de matriculación, ahora con el acceso de invitados activo.

**Paso 9.** Compruébelo de verdad, con una **ventana privada**. Una ventana privada es una ventana del navegador que no recuerda quién había entrado antes: sirve para ver el sitio como lo vería un desconocido.

1. Sin cerrar lo que tiene abierto, apriete al mismo tiempo las teclas **Ctrl**, **Shift** y **N** (en Firefox es **Ctrl**, **Shift** y **P**). **Shift** es la tecla con una flecha hacia arriba, a la izquierda del teclado.
2. Se abre una ventana nueva, de fondo oscuro, que avisa que está en modo privado.
3. Haga clic en la barra de direcciones de esa ventana nueva.
4. Escriba la dirección del curso y apriete **Enter**.

*Lo que debería ver:* el recuadro pidiendo la contraseña de invitados. Si eso aparece, quedó bien configurado. Cierre esa ventana con la **X** de arriba a la derecha.

### 5.7 Quitar el acceso de invitados

**Paso 1.** Entre al curso y haga clic en **Participantes**, en la fila de palabras bajo el nombre del curso.

**Paso 2.** Arriba a la izquierda del listado, haga clic en el recuadro que dice **Usuarios matriculados**.

**Paso 3.** En la lista que se abre, haga clic en **Métodos de matriculación**.

**Paso 4.** Busque la fila **Acceso de invitados**.

**Paso 5.** Haga clic en el icono de **ojo** de esa fila para apagarlo.

*Lo que debería ver:* la fila queda en gris claro. Desde ese momento, quien entre a la dirección del curso sin cuenta ya no verá el contenido.

**Paso 6.** Compruébelo abriendo el curso en una ventana privada, como se explicó en el punto 5.6. Debe negarle la entrada.

### 5.8 Antes de activarlo: recomendación para CONAF

Esta es la parte que conviene leer antes de tocar nada.

CAMPUS CONAF **no es un sitio de folletos**. Contiene datos personales de **2.869 funcionarios**: nombres, correos institucionales, avances y calificaciones. En Chile eso está protegido por la Ley 19.628 sobre datos personales.

Abrir un curso a invitados significa que **cualquiera que tenga la dirección entra sin identificarse**. Nadie sabe quién fue. No queda registro de la visita, porque el invitado no deja rastro por diseño.

Súmele esto: todos los invitados comparten una sola cuenta. Si mañana hay que averiguar quién descargó un documento, la respuesta será "Invitado", y ahí se acaba la investigación.

**La recomendación es esta:** mantener el acceso de invitados **desactivado** en todo el sitio y en todos los cursos, salvo que exista una justificación concreta y por escrito.

Si aun así hay que abrir un curso, aplique estas cuatro condiciones:

| Condición | Por qué |
|---|---|
| Abrir solo el curso puntual, nunca el sitio entero | Limita lo que se expone |
| Ponerle siempre contraseña de invitados | Deja fuera al que llega por casualidad |
| Que ese curso no tenga datos de personas ni listas de participantes | Es lo que protege la ley |
| Ponerle fecha de cierre y apagarlo ese día | Lo que se abre "por un rato" se queda abierto años |

Para difundir material público hacia afuera de CONAF, casi siempre es mejor otro camino: publicarlo en el sitio web institucional, o crear cuentas nominadas de corta duración. Cuestan un poco más de trabajo y dejan trazabilidad.

---

## 6. Lo que usted no puede hacer

Como invitado, esta es la lista de lo que la plataforma no le va a permitir. No es una falla ni un permiso que se pueda pedir "por esta vez": está cerrado en el diseño de Moodle.

| Qué no puede hacer | Por qué | A quién pedirlo |
|---|---|---|
| Entregar una tarea | El invitado no está inscrito, así que no hay dónde guardar su entrega | Pida una cuenta de estudiante al administrador |
| Responder un cuestionario | Igual: no hay a quién anotarle el intento | Pida una cuenta de estudiante |
| Escribir en un foro | Los mensajes tienen que tener autor, y "Invitado" no identifica a nadie | Pida una cuenta de estudiante |
| Recibir calificaciones | No hay libreta de notas para un visitante | Pida una cuenta de estudiante |
| Que le quede guardado el avance | El modo invitado no registra nada, a propósito | Pida una cuenta de estudiante |
| Obtener certificado o constancia | Los certificados salen del avance registrado, y no hay | Al profesor del curso |
| Subir un archivo | Subir archivos exige tener cuenta propia. (El límite del sitio es de 200 MB por archivo, pero eso no le aplica: usted no puede subir) | Pida una cuenta de estudiante |
| Ver la lista de participantes | Son datos personales de funcionarios | Al profesor del curso |
| Entrar a un curso que no abrió el invitado | Está cerrado a visitantes | Al profesor de ese curso |
| Ver cursos ocultos | Solo lo pueden Manager, Creador de cursos, Profesor y Profesor sin edición | Al profesor del curso |
| Cambiar cualquier configuración | El invitado no configura nada | Al administrador |
| Configurar el servidor | Eso no lo puede ningún rol de este manual, ni siquiera el Manager. Es solo del administrador del sitio | Al administrador del sitio |

**Y algo que no puede hacer nadie:** convertir a una persona en "Invitado". Ese rol no se asigna. Si necesita que alguien tenga acceso de verdad, hay que crearle una cuenta.

---

## 7. Si algo sale mal

| Qué ve en la pantalla | Qué pasó | Qué hacer |
|---|---|---|
| No aparece el botón "Entrar como invitado" en la pantalla de acceso | El administrador tiene esa opción apagada para todo el sitio | Pida al profesor la dirección directa del curso, o pida una cuenta propia |
| Al abrir la dirección del curso dice que no se puede entrar | Ese curso no está abierto a visitantes | Pídale al profesor que lo abra, o que le cree una cuenta |
| Le pide una contraseña que usted no tiene | El curso está abierto a invitados, pero con palabra clave | Pídasela al profesor del curso. No se puede adivinar |
| Escribe la contraseña y dice en rojo que es incorrecta | Está mal escrita, o el profesor la cambió | Revise mayúsculas y minúsculas. Si falla otra vez, pídala de nuevo |
| Abre un cuestionario y dice que los invitados no pueden hacer intentos | Es normal: el invitado no rinde pruebas | Si necesita rendirla, pida una cuenta de estudiante |
| En el foro no aparece el botón para responder | Es normal: el invitado solo lee | Pida una cuenta de estudiante |
| Volvió al día siguiente y perdió todo lo que había hecho | Es normal: el modo invitado no guarda nada | Si necesita que su avance quede, pida una cuenta |
| Arriba a la derecha aparece su nombre y no "Invitado" | Usted entró con una cuenta propia, no como invitado | Use el manual de su rol. Verá más cosas |
| La página se ve sin colores, desordenada, con el texto amontonado | Casi siempre es el navegador mostrando una copia vieja | Presione la tecla **F5** para recargar. Si sigue igual, presione **Ctrl** y **F5** juntas |
| Sale "¿Olvidó su contraseña?" y usted la usa, pero no llega ningún correo | El envío de correos está **desactivado** en este sitio. No va a llegar nunca | Pídale la contraseña directamente al administrador de la plataforma |
| Un curso muestra imágenes rotas o cuadros vacíos | Contenido antiguo que apunta al sitio anterior | Avísele al profesor del curso. No es problema de su computador |
| La página queda cargando y no termina | Conexión lenta o caída | Espere un minuto y recargue con **F5**. Si sigue, consulte con informática de su unidad |

**Regla general:** si algo no le deja hacer nada y usted es invitado, lo más probable es que sea el comportamiento correcto y no una falla. El invitado está hecho para mirar.

---

## 8. Palabras que aparecen en la plataforma

**Actividad.** Algo que se hace dentro del curso: una tarea, un cuestionario, un foro. El invitado las ve pero no las hace.

**Acceso de invitados.** La opción que un profesor enciende en su curso para que entren visitantes sin cuenta.

**Barra de direcciones.** La franja alargada de arriba del navegador donde se escribe la dirección de un sitio.

**Cerrar sesión.** Salir de la plataforma. Está en el menú de arriba a la derecha.

**Contraseña de acceso de invitados.** Palabra clave del curso, no de una persona. Todos los visitantes usan la misma.

**Cuenta.** Un usuario y una contraseña propios, con su nombre. El invitado no tiene cuenta propia.

**Curso.** Un espacio con materiales y actividades sobre un tema. CAMPUS CONAF tiene 37.

**Cuestionario.** Una prueba con preguntas dentro del curso. El invitado no puede responderla.

**Descargar.** Traer un archivo desde el sitio hasta su computador. Suele quedar en la carpeta Descargas.

**Enlace.** Texto o imagen sobre el que se hace clic y lleva a otra pantalla. Suele estar en azul o subrayado.

**Foro.** Tablero de mensajes del curso. El invitado los lee pero no escribe.

**Hacer clic.** Presionar una vez el botón izquierdo del mouse sobre algo de la pantalla.

**Hacer doble clic.** Presionar dos veces seguidas y rápido el botón izquierdo del mouse.

**Invitado (Guest).** El modo de entrar sin cuenta. No es una persona: es una cuenta compartida y sin contraseña.

**Matricular.** Inscribir a una persona en un curso. Al invitado no se le matricula nunca.

**Menú desplegable.** Lista de opciones que se abre hacia abajo al hacer clic y se cierra sola.

**Moodle.** El programa con el que está hecha esta plataforma de cursos.

**Navegador.** El programa con que se entra a internet: Chrome, Edge, Firefox.

**Participantes.** La lista de personas inscritas en un curso. El invitado no aparece ahí.

**PDF.** Formato de documento que se ve igual en cualquier computador. Se abre con un clic.

**Permiso.** Autorización para hacer algo concreto dentro de la plataforma. El invitado tiene 30.

**Prohibido.** El cierre más fuerte de Moodle. Un permiso prohibido no se puede devolver por ningún camino, ni dándole otro rol a la persona.

**Recargar.** Volver a pedir la página al sitio, por si lo que se ve quedó viejo. Se hace con la tecla F5.

**Rol.** El conjunto de cosas que alguien tiene permitido hacer. Invitado, Estudiante y Profesor son roles distintos.

**Sección.** Un bloque de contenidos dentro del curso, como un capítulo. El curso de práctica tiene 3.

**Sesión.** El rato que usted permanece dentro de la plataforma, desde que entra hasta que sale.

**Tarea.** Actividad en la que se sube un archivo para que el profesor lo revise. El invitado no puede.

**Tema (theme).** El aspecto visual del sitio: colores, tipos de letra. Aquí se llama "boost_magnific".

**Ventana privada (o de incógnito).** Una ventana del navegador que no recuerda quién había entrado antes. Sirve para comprobar cómo se ve el curso desde afuera.

---

*Manual del rol Guest (Invitado) — CAMPUS CONAF · Moodle 4.5.10 · https://academia.conaf.cl*
