# -*- coding: utf-8 -*-
"""Convierte el Anexo A de IF-151 a Moodle Question XML.

GENERADOR DE UN SOLO USO. No forma parte del despliegue: produce
academia/datos/banco-IF-151.xml, que sí está versionado. Se vuelve a correr solo
si cambia el documento de diseño.

    python academia/herramientas/convertir-banco-IF-151.py <texto.txt> [salida.xml]

El primer argumento es el texto plano extraído del .docx. Para obtenerlo:

    python -c "import zipfile,re,html;       x=zipfile.ZipFile('IF-151_Diseno-de-curso_Academia-CONAF.docx')         .read('word/document.xml').decode('utf8');       x=x.replace('</w:p>',chr(10)).replace('</w:tr>',chr(10)).replace('</w:tc>',' | ');       print(html.unescape(re.sub('<[^>]+>','',x)))" > if-151.txt

POR QUÉ UN CONVERSOR Y NO COPIAR A MANO. Son 60 ítems con 180 opciones y 180
retroalimentaciones. Copiadas a mano, los errores no se ven: una opción correcta
marcada mal produce un examen que reprueba a quien sabe, y nadie lo descubre
hasta que alguien reclama. El conversor, en cambio, comprueba las cuentas contra
la tabla de especificaciones del propio documento —35 formativos, 21 de
certificación, 4 integradoras, 5 y 3 por lección, exactamente una correcta por
ítem— y se niega a escribir nada si alguna no cuadra.

El .docx trae los ítems con un formato totalmente regular:

    F1.1
     | <enunciado>
     |
    ✓
     | <texto de la opción>
    <retroalimentación de esa opción>
     |
    ✕
     | ...

Se apoya en eso. Cualquier irregularidad la denuncia en vez de adivinarla.
"""
import re
import sys
import html
from pathlib import Path

if len(sys.argv) < 2:
    sys.exit(__doc__)

FUENTE = Path(sys.argv[1])
DESTINO = Path(sys.argv[2]) if len(sys.argv) > 2 else (
    Path(__file__).resolve().parents[1] / 'datos' / 'banco-IF-151.xml')

texto = FUENTE.read_text(encoding='utf8')
lineas = texto.split('\n')

# El anexo empieza en «A.1  Lección 1».
inicio = next(i for i, l in enumerate(lineas) if l.startswith('A.1  Lección 1'))
lineas = lineas[inicio:]

RE_LECCION = re.compile(r'^A\.(\d)\s+(Lección \d|Integradoras)(?:\s+·\s+(.*))?$')
RE_FORMATIVAS = re.compile(r'^Formativas —')
RE_CERTIF = re.compile(r'^Reservadas para la certificación —')
# Las integradoras se numeran I.1 .. I.4, sin número de lección; las de
# lección son F1.1 / C1.1. El grupo del medio queda vacío en las primeras.
RE_ITEM = re.compile(r'^([FCI])(\d*)\.(\d+)$')

items = []
categoria = None
titulos = {}
i = 0

while i < len(lineas):
    linea = lineas[i].rstrip()

    m = RE_LECCION.match(linea)
    if m:
        num, clase, titulo = m.groups()
        if clase == 'Integradoras':
            categoria = 'Integradoras'
        else:
            titulos[num] = titulo or ''
        i += 1
        continue

    if RE_FORMATIVAS.match(linea):
        categoria = ('formativa', linea)
        i += 1
        continue
    if RE_CERTIF.match(linea):
        categoria = ('certificacion', linea)
        i += 1
        continue

    m = RE_ITEM.match(linea)
    if m:
        codigo = linea
        tipo, leccion, _ = m.groups()

        # Enunciado: viene en la línea siguiente, precedida de ' | '.
        j = i + 1
        if not lineas[j].startswith(' | '):
            sys.exit(f'FORMATO INESPERADO en {codigo}: se esperaba el enunciado en la línea {j}')
        enunciado = lineas[j][3:].strip()
        j += 1
        # Puede continuar en más líneas hasta el separador ' | ' solo.
        while j < len(lineas) and lineas[j].strip() != '|':
            enunciado += ' ' + lineas[j].strip()
            j += 1
        j += 1  # saltar el separador

        # Tres opciones.
        opciones = []
        for _ in range(3):
            marca = lineas[j].strip()
            if marca not in ('✓', '✕'):
                sys.exit(f'FORMATO INESPERADO en {codigo}: se esperaba ✓ o ✕ y vino "{marca}"'
                         f' (línea {j})')
            correcta = (marca == '✓')
            j += 1
            if not lineas[j].startswith(' | '):
                sys.exit(f'FORMATO INESPERADO en {codigo}: opción sin " | " (línea {j})')
            opcion = lineas[j][3:].strip()
            j += 1
            # La retroalimentación son las líneas hasta el separador.
            retro = []
            while j < len(lineas) and lineas[j].strip() != '|':
                if lineas[j].strip():
                    retro.append(lineas[j].strip())
                j += 1
            j += 1
            opciones.append((correcta, opcion, ' '.join(retro)))

        correctas = sum(1 for c, _, _ in opciones if c)
        if correctas != 1:
            sys.exit(f'{codigo}: tiene {correctas} opciones correctas, debe tener exactamente 1')

        items.append({
            'codigo': codigo,
            'tipo': tipo,
            'leccion': leccion,
            'categoria': categoria,
            'enunciado': enunciado,
            'opciones': opciones,
        })
        i = j
        continue

    i += 1

# ─── Comprobaciones contra lo que declara la tabla de especificaciones ───────
f = [x for x in items if x['tipo'] == 'F']
c = [x for x in items if x['tipo'] == 'C']
ii = [x for x in items if x['tipo'] == 'I']

print(f'formativos:    {len(f):3}  (esperado 35)')
print(f'certificación: {len(c):3}  (esperado 21)')
print(f'integradoras:  {len(ii):3}  (esperado  4)')
print(f'TOTAL:         {len(items):3}  (esperado 60)')

errores = []
if len(f) != 35: errores.append('formativos != 35')
if len(c) != 21: errores.append('certificación != 21')
if len(ii) != 4: errores.append('integradoras != 4')

for n in '1234567':
    nf = len([x for x in f if x['leccion'] == n])
    nc = len([x for x in c if x['leccion'] == n])
    if nf != 5: errores.append(f'lección {n}: {nf} formativos, esperado 5')
    if nc != 3: errores.append(f'lección {n}: {nc} de certificación, esperado 3')

for x in items:
    for correcta, opcion, retro in x['opciones']:
        if not opcion:
            errores.append(f"{x['codigo']}: una opción quedó vacía")
        if not retro:
            errores.append(f"{x['codigo']}: la opción «{opcion[:40]}» quedó sin retroalimentación")

if errores:
    print('\nERRORES:')
    for e in errores:
        print('  ·', e)
    sys.exit(1)

print('\nTodas las cuentas coinciden con la tabla de especificaciones.\n')


# ─── Generar el XML ─────────────────────────────────────────────────────────
def esc(s):
    return html.escape(s, quote=False)


def bloque_categoria(ruta):
    return f'''
  <question type="category">
    <category><text>{esc(ruta)}</text></category>
    <info format="html"><text></text></info>
  </question>
'''


def bloque_pregunta(item):
    partes = [f'''
  <question type="multichoice">
    <name><text>{esc(item['codigo'])}</text></name>
    <questiontext format="html">
      <text><![CDATA[<p>{item['enunciado']}</p>]]></text>
    </questiontext>
    <generalfeedback format="html"><text></text></generalfeedback>
    <defaultgrade>1.0000000</defaultgrade>
    <penalty>0.0000000</penalty>
    <hidden>0</hidden>
    <idnumber>{esc(item['codigo'])}</idnumber>
    <single>true</single>
    <shuffleanswers>true</shuffleanswers>
    <answernumbering>abc</answernumbering>
    <showstandardinstruction>0</showstandardinstruction>
    <correctfeedback format="html"><text></text></correctfeedback>
    <partiallycorrectfeedback format="html"><text></text></partiallycorrectfeedback>
    <incorrectfeedback format="html"><text></text></incorrectfeedback>
    <shownumcorrect/>''']

    for correcta, opcion, retro in item['opciones']:
        fraccion = '100' if correcta else '0'
        partes.append(f'''
    <answer fraction="{fraccion}" format="html">
      <text><![CDATA[<p>{opcion}</p>]]></text>
      <feedback format="html">
        <text><![CDATA[<p>{retro}</p>]]></text>
      </feedback>
    </answer>''')

    partes.append('\n  </question>\n')
    return ''.join(partes)


RAIZ = '$course$/top/IF-151'

salida = ['<?xml version="1.0" encoding="UTF-8"?>\n',
          '<!--\n'
          '  Banco de preguntas de IF-151 · Física y comportamiento del fuego forestal.\n'
          '  GENERADO desde el Anexo A de IF-151_Diseno-de-curso_Academia-CONAF.docx.\n'
          '\n'
          '  60 ítems: 35 formativos (5 por lección), 21 de certificación (3 por lección)\n'
          '  y 4 integradoras. Opción múltiple, respuesta única, tres opciones, barajado.\n'
          '\n'
          '  LOS ÍTEMS C NO SE AGREGAN NUNCA A UN CUESTIONARIO DE LECCIÓN. Son la reserva\n'
          '  de la evaluación final: si aparecen antes, dejan de medir.\n'
          '\n'
          '  Todo el contenido técnico es PROPUESTA y requiere validación del Departamento\n'
          '  de Protección contra Incendios Forestales antes de su uso formativo.\n'
          '-->\n',
          '<quiz>\n']

vistas = set()
for item in items:
    if item['tipo'] == 'I':
        ruta = f'{RAIZ}/Integradoras'
    elif item['tipo'] == 'F':
        ruta = f'{RAIZ}/Lección {item["leccion"]}/Formativa'
    else:
        ruta = f'{RAIZ}/Lección {item["leccion"]}/Certificación'

    if ruta not in vistas:
        salida.append(bloque_categoria(ruta))
        vistas.add(ruta)
    salida.append(bloque_pregunta(item))

salida.append('</quiz>\n')

DESTINO.parent.mkdir(parents=True, exist_ok=True)
DESTINO.write_text(''.join(salida), encoding='utf8')
print(f'Escrito: {DESTINO}')
print(f'  {len(vistas)} categorías, {len(items)} preguntas, '
      f'{DESTINO.stat().st_size / 1024:.1f} KB')
