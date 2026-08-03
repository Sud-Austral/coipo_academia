# Manuales de usuario — CAMPUS CONAF

Esta carpeta contiene **ocho manuales** para usar la plataforma de cursos de CONAF,
**https://academia.conaf.cl**. Ahí están los cursos de prevención y combate de incendios
forestales y del Sistema de Comando de Incidentes (SCI).

Hay un manual por cada **rol**. Un rol es el permiso que tiene una persona dentro de la
plataforma: define qué puede hacer y qué no. Dos personas pueden mirar la misma pantalla y ver
botones distintos, porque tienen roles distintos. Eso es normal y es a propósito.

Los manuales están escritos para gente que no trabaja con computadores todos los días. Empiezan
desde abrir el navegador y no dan nada por sabido. **Usted no necesita leer los ocho: lea el
suyo.**

Los ocho tienen la misma estructura, así que si alguna vez le cambian el rol, va a encontrar
las cosas en el mismo lugar:

| Sección | Qué trae |
|---|---|
| 1. Antes de empezar | Qué es la plataforma y qué es un rol, en lenguaje llano |
| 2. Sus datos de acceso | Usuario, contraseña y el aviso de cambiarla |
| 3. Entrar por primera vez | Paso a paso, desde encender el computador |
| 4. Reconocer la pantalla | Qué es cada zona de lo que aparece |
| 5. Lo que usted puede hacer | Las tareas del rol, una por una |
| 6. Lo que usted no puede hacer | Qué, por qué, y a quién pedírselo |
| 7. Si algo sale mal | Qué ve, qué pasó, qué hacer |
| 8. Palabras que aparecen en la plataforma | Glosario simple |

---

## ¿Cuál manual me toca a mí?

Búsquese por lo que hace en su trabajo, no por el nombre del rol:

- **Voy a tomar un curso: leer materiales, entregar tareas, dar pruebas y ver mis notas.**
  → [Manual de Estudiante](05-student.md)

- **Voy a corregir y poner notas a los trabajos de los alumnos, y a responder sus dudas en los
  foros, pero el curso ya está armado y yo no lo modifico.**
  → [Manual de Profesor sin permisos de edición](04-non-editing-teacher.md)

- **Voy a armar y dictar un curso: subir documentos, crear tareas y cuestionarios, matricular
  alumnos y calificarlos.**
  → [Manual de Profesor](03-teacher.md)

- **Mi única pega es abrir cursos nuevos en la plataforma; el contenido lo pone otra persona.**
  → [Manual de Creador de cursos](02-course-creator.md)

- **Yo administro la plataforma sin ser el informático: creo usuarios y cursos, matriculo gente,
  reviso notas de cualquier curso, saco respaldos y recupero lo borrado.**
  → [Manual de Manager (Gestor)](01-manager.md)

- **Ya tengo cuenta, pero todavía no me inscriben en ningún curso, y quiero dejar mi perfil
  listo: foto, contraseña, idioma, hora.**
  → [Manual de Usuario autenticado](07-authenticated-user.md)

- **Entro y quedo parado en la página de inicio del sitio, y no entiendo qué es esa pantalla ni
  en qué se diferencia de "Mis cursos".**
  → [Manual de Usuario autenticado en la portada](08-authenticated-user-frontpage.md)

- **Quiero mirar un curso sin tener cuenta, o soy encargado de un curso y tengo que decidir si
  lo abro a visitantes.**
  → [Manual de Invitado](06-guest.md)

Si no se reconoce en ninguna, pregúntele a quien le entregó su cuenta cuál es su rol.

---

## Los 8 manuales

| # | Rol (español) | Rol (Moodle, en inglés) | Manual | Para quién es | Permisos |
|---|---|---|---|---|---|
| 1 | Manager (Gestor) | `manager` | [01-manager.md](01-manager.md) | Quien administra la plataforma sin ser el informático del sitio | 593 |
| 2 | Creador de cursos | `coursecreator` | [02-course-creator.md](02-course-creator.md) | Quien solo abre cursos nuevos; queda como profesor de los que él mismo crea | 26 |
| 3 | Profesor (con permisos de edición) | `editingteacher` | [03-teacher.md](03-teacher.md) | Quien arma y dicta un curso de principio a fin | 486 |
| 4 | Profesor sin permisos de edición | `teacher` | [04-non-editing-teacher.md](04-non-editing-teacher.md) | Tutor o relator que corrige y califica, pero no modifica el curso | 224 |
| 5 | Estudiante | `student` | [05-student.md](05-student.md) | Funcionario o funcionaria que toma el curso | 84 |
| 6 | Invitado | `guest` | [06-guest.md](06-guest.md) | Quien mira un curso sin cuenta, y el encargado que decide si lo abre | 30 |
| 7 | Usuario autenticado | `user` | [07-authenticated-user.md](07-authenticated-user.md) | Toda persona con cuenta, tenga o no cursos asignados | 116 |
| 8 | Usuario autenticado en la portada | `frontpage` | [08-authenticated-user-frontpage.md](08-authenticated-user-frontpage.md) | Toda persona con cuenta, mientras está en la página de inicio | 10 |

Los números de permisos se contaron directamente en la base de datos del sitio.

---

## Tres roles que no son como los otros cinco

Esto explica por qué los manuales 6, 7 y 8 están escritos distinto. **Guest, Authenticated user
y Authenticated user on site home NO son roles asignables**: nadie se los puede dar ni quitar a
una persona.

| Rol | Por qué no es asignable | Consecuencia |
|---|---|---|
| **Invitado** (`guest`) | No es una persona, es una **manera de entrar**. Moodle trae una cuenta `guest` compartida, **sin contraseña**, y el rol está definido para no poder asignarse a nadie | **No tiene cuenta de práctica y no puede tenerla.** No es un olvido. El manual explica el modo invitado y cómo se activa o desactiva en un curso |
| **Usuario autenticado** (`user`) | La plataforma se lo da **sola** a toda persona en el instante en que inicia sesión | Es el rol **base**: todos los demás se apoyan encima. Si usted es estudiante, es estudiante **y además** usuario autenticado |
| **Usuario autenticado en la portada** (`frontpage`) | La plataforma se lo aplica sola, y **solo mientras la persona está en la página de inicio**. Al entrar a un curso deja de tener efecto | Es el rol con menos permisos de todo el sitio: 10. Por eso su manual es el más corto |

Los roles que **sí** se asignan a una persona son cinco: Manager, Creador de cursos, Profesor,
Profesor sin permisos de edición y Estudiante.

---

## Cuentas de práctica

> ### ⚠️ ESTAS SON CUENTAS DE DEMOSTRACIÓN — HAY QUE BORRARLAS
>
> Las seis cuentas de abajo existen **solo para aprender con estos manuales**. Sus contraseñas
> están escritas aquí, en un archivo del repositorio, así que **cualquiera que lea este texto
> puede entrar con ellas**.
>
> **Antes de abrir la plataforma a los funcionarios, hay que borrar las seis cuentas, el curso
> de práctica y la categoría que los contiene** (ver la sección siguiente). La plataforma tiene
> datos personales de 2.869 personas y eso está protegido por la Ley 19.628.

Las seis fueron creadas y probadas: **las seis inician sesión correctamente**.

| Rol | Nombre de usuario | Contraseña | Dónde tiene el rol |
|---|---|---|---|
| Manager (Gestor) | `manual.manager` | `Manager-campus26.` | En todo el sitio |
| Creador de cursos | `manual.creador` | `Coursecreator-campus26.` | En todo el sitio |
| Profesor | `manual.profesor` | `Editingteacher-campus26.` | Solo en el curso MANUALES |
| Profesor sin permisos de edición | `manual.profesorne` | `Teacher-campus26.` | Solo en el curso MANUALES |
| Estudiante | `manual.estudiante` | `Student-campus26.` | Solo en el curso MANUALES |
| Usuario autenticado | `manual.autenticado` | `User-campus26.` | Sin ningún rol asignado |

Escriba usuario y contraseña **exactamente como salen en la tabla**. La plataforma distingue
mayúsculas de minúsculas, y **el punto del final es parte de la contraseña**.

**No hay cuenta de Invitado**, y no puede haberla: la cuenta `guest` de Moodle no tiene
contraseña. Vea el manual 6.

### Regla de las contraseñas del sitio

Toda contraseña nueva debe tener al menos **una letra mayúscula**, **un número** y **un
símbolo** (por ejemplo `.` `-` `#`). Si le falta alguna, la plataforma la rechaza con un aviso
en rojo. Por eso las contraseñas de práctica empiezan con mayúscula y terminan en punto.

### ⚠️ El correo del sitio está apagado

**En esta plataforma el envío de correos está desactivado.** El enlace "¿Olvidó su nombre de
usuario o contraseña?" **no funciona**: no llega ningún correo, nunca.

Quien olvide su contraseña debe **pedírsela al administrador de la plataforma**. No hay otra
forma. Cada manual repite este aviso en su sección 2.

---

## Comparación de los 5 roles asignables

Medido en la base de datos del sitio. **Sí** = el rol tiene esa capacidad; **no** = no la tiene.

| Capacidad | Manager | Creador de cursos | Profesor | Profesor sin edición | Estudiante |
|---|:---:|:---:|:---:|:---:|:---:|
| Configurar el servidor | no | no | no | no | no |
| Crear usuarios | **sí** | no | no | no | no |
| Crear cursos | **sí** | **sí** | no | no | no |
| Editar un curso | **sí** | no | **sí** | no | no |
| Agregar o quitar actividades | **sí** | no | **sí** | no | no |
| Gestionar grupos | **sí** | no | **sí** | no | no |
| Matricular y dar de baja | **sí** | no | **sí** | no | no |
| Ver cursos ocultos | **sí** | **sí** | **sí** | **sí** | no |
| Editar el libro de calificaciones | **sí** | no | **sí** | no | no |
| Ver todas las calificaciones | **sí** | no | **sí** | **sí** | no |
| Calificar tareas | **sí** | no | **sí** | **sí** | no |
| Respaldar y restaurar el curso | **sí** | no | **sí** | no | no |
| Asignar roles | **sí** | no | **sí** | no | no |
| **Total de permisos** | **593** | **26** | **486** | **224** | **84** |

Dos cosas que conviene leer despacio:

- **Ninguno de estos cinco roles puede configurar el servidor.** Eso es exclusivo del
  **administrador del sitio**, que no aparece en esta tabla y no tiene manual en esta carpeta.
- **El Creador de cursos tiene solo 26 permisos, y eso es a propósito**, no un error. Su rol
  crea cursos nuevos; no le permite entrar a modificar cursos ajenos. Cuando crea un curso, la
  plataforma lo deja dentro **como profesor de ese curso**, y ahí sí puede hacer de todo.

Para referencia, los tres roles no asignables: Usuario autenticado **116**, Invitado **30**,
Usuario autenticado en la portada **10**.

---

## Qué se creó para estos manuales

Esto es lo que hay que **borrar** cuando los manuales dejen de usarse para capacitar. Está
detallado para que quien venga después no tenga que adivinar.

### 1. Curso de práctica

| Dato | Valor |
|---|---|
| Nombre completo | Curso de practica - Manuales de usuario |
| Nombre corto | `MANUALES` |
| Dirección directa | https://academia.conaf.cl/course/view.php?id=41 |
| Categoría | Manuales de usuario |
| Formato | Temas, 3 secciones |
| Visibilidad | Visible |

### 2. Categoría

**"Manuales de usuario"**, creada solo para contener el curso MANUALES. Bórrela después del
curso, cuando quede vacía.

### 3. Las seis cuentas de práctica

`manual.manager`, `manual.creador`, `manual.profesor`, `manual.profesorne`,
`manual.estudiante`, `manual.autenticado`.

### Orden sugerido para limpiar

1. Dar de baja del curso MANUALES a las cuentas matriculadas.
2. Borrar el curso MANUALES.
3. Borrar la categoría "Manuales de usuario", ya vacía.
4. Borrar las seis cuentas `manual.*`.
5. Si se quiere conservar la documentación, borrar de este README y de la sección 2 de cada
   manual las contraseñas escritas, o reemplazarlas por un texto del tipo "pídaselas al
   administrador".

Nada de esto toca los **37 cursos** ni las **2.869 personas** reales de la plataforma: el curso
de práctica y las cuentas `manual.*` son independientes.

---

## Datos del sitio, para ubicarse

| Dato | Valor |
|---|---|
| Dirección | https://academia.conaf.cl |
| Plataforma | Moodle 4.5.10 |
| Tema visual | `boost_magnific` |
| Idioma de la interfaz | Español |
| Personas con cuenta | 2.869 |
| Cursos | 37 |
| Tamaño máximo de archivo que se puede subir | 200 MB |
| Envío de correos | **Desactivado** |
| Organización | CONAF — Corporación Nacional Forestal, Chile |
