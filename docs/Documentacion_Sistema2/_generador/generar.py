# Genera el paquete Documentacion_Sistema2. Uso: python generar.py (desde cualquier carpeta).
import os, sys
from docx import Document
from docx.shared import Pt
from docx.oxml.ns import qn
from docx.oxml import OxmlElement
from openpyxl import Workbook
sys.path.insert(0, os.path.dirname(os.path.abspath(__file__)))
from datos import *

RAIZ_DOC = os.path.normpath(os.path.join(os.path.dirname(os.path.abspath(__file__)), ".."))


def doc_base(titulo):
    d = Document()
    d.styles["Normal"].font.name = "Calibri"
    d.styles["Normal"].font.size = Pt(11)
    d.add_heading(titulo, 0)
    d.add_paragraph(f"Academia CONAF v2 (coipo_academia) · Versión {VERSION} · {FECHA}")
    d.add_heading("Índice", 1)
    p = d.add_paragraph()
    r = p.add_run()
    for tag, txt in (("begin", None), (None, 'TOC \\o "1-2" \\h \\z \\u'), ("separate", None), (None, "Actualizar índice"), ("end", None)):
        if tag:
            e = OxmlElement("w:fldChar"); e.set(qn("w:fldCharType"), tag); r._r.append(e)
        elif txt.startswith("TOC"):
            e = OxmlElement("w:instrText"); e.set(qn("xml:space"), "preserve"); e.text = txt; r._r.append(e)
        else:
            e = OxmlElement("w:t"); e.text = txt; r._r.append(e)
    return d


def tabla(d, cab, filas):
    t = d.add_table(rows=1, cols=len(cab)); t.style = "Table Grid"
    for i, c in enumerate(cab):
        t.rows[0].cells[i].text = c
        for run in t.rows[0].cells[i].paragraphs[0].runs: run.bold = True
    for f in filas:
        cs = t.add_row().cells
        for i, v in enumerate(f): cs[i].text = str(v)


def control(d):
    d.add_heading("Control de cambios", 1)
    tabla(d, ["Versión", "Fecha", "Cambio"], [["1", "-", "No existía paquete Documentacion_Sistema previo."],
                                              [VERSION, FECHA, "Primera versión generada desde código (_generador)."]])


def pasos(d, lista):
    for i, s in enumerate(lista, 1): d.add_paragraph(f"{i}. {s}")


def manual():
    d = doc_base("Manual de Usuario — Provisión de la Academia CONAF v2")
    control(d)
    d.add_heading("Novedades de la versión 2", 1)
    tabla(d, ["Qué cambió", "Qué significa para usted"], [["Primer manual generado", "Antes no había manual del operador de provisión; los de manuales/ describen el campus actual."]])
    d.add_heading("1. Qué es y para quién", 1)
    d.add_paragraph(ROL["resumen"])
    d.add_heading("2. Acceso", 1); d.add_paragraph(ROL["acceso"])
    d.add_heading("3. Interfaz", 1)
    d.add_paragraph("El repositorio no tiene pantallas propias. Sus piezas visibles en el navegador son el tema "
                    "theme_academia (colores y SCSS sobre Boost) y el plugin de terceros mod/customcert; ambas se ven "
                    "solo dentro de Moodle. Este manual no trae capturas: no se levantó Moodle (Docker no estaba "
                    "corriendo y levantarlo exige descargar imágenes grandes).")
    d.add_heading("4. Inicio de sesión", 1)
    f0 = FUNC[0]
    d.add_paragraph("No hay inicio de sesión propio del repositorio. El primer paso de todo es instalar Moodle:")
    pasos(d, f0[3])
    d.add_heading("5. Lo que usted puede hacer", 1)
    d.add_paragraph("Todos los scripts aceptan --dry-run (-n) y --help (-h) y se ejecutan así: "
                    "docker compose exec -u www-data app php /opt/academia/cli/<script>.php")
    for n, obj, scr, ps, res, val in FUNC:
        d.add_heading(n, 2)
        d.add_paragraph(f"Objetivo: {obj}"); d.add_paragraph(f"Dónde vive: {scr}")
        d.add_paragraph("Pasos:"); pasos(d, ps)
        d.add_paragraph(f"Resultado esperado: {res}")
        for v in val: d.add_paragraph(v, style="List Bullet")
    d.add_heading("6. Errores frecuentes", 1)
    for c in ("CU-14", "CU-15"): d.add_paragraph(CASO_EXTRA[c][3], style="List Bullet")
    d.add_heading("7. Buenas prácticas", 1)
    for s in ["Correr siempre primero con --dry-run.", "Respetar el orden numérico de los scripts.",
              "Leer la salida, no solo el código de retorno.", "Terminar con 99_verificar.php y su lista manual."]:
        d.add_paragraph(s, style="List Bullet")
    d.save(os.path.join(RAIZ_DOC, "01_Manuales", "Usuario.docx"))


def casos_filas():
    filas = []
    for cid, nom, fi, cat in CASOS:
        if fi is None:
            scr, rn, ps, res = CASO_EXTRA[cid]
        else:
            f = FUNC[fi]; scr, ps, res = f[2], f[3], f[4]; rn = ""
        filas.append((cid, nom, cat, scr, ps, res, rn))
    return filas


def casos():
    d = doc_base("Casos de uso — Usuario"); control(d)
    for cid, nom, cat, scr, ps, res, rn in casos_filas():
        d.add_heading(f"{cid} {nom}", 1)
        tabla(d, ["Campo", "Valor"], [["Categoría", cat], ["Actor", "Usuario (operador de provisión)"],
                                      ["Componente", scr], ["Resultado", res], ["Evidencia", rn or scr]])
        d.add_paragraph("Flujo:"); pasos(d, ps)
    d.save(os.path.join(RAIZ_DOC, "02_Casos_de_Uso", "Usuario_Casos_de_Uso.docx"))


def qa():
    d = doc_base("Checklist QA — Usuario"); control(d)
    d.add_paragraph("Todas las fichas parten en NOT TESTED: nada de esto se ejecutó al generar el paquete.")
    for cid, nom, cat, scr, ps, res, rn in casos_filas():
        d.add_heading(f"Ficha {cid} {nom}", 1)
        pasos(d, ps)
        tabla(d, ["Esperado", "Estado", "Probador", "Fecha", "Observación"], [[res, "NOT TESTED", "", "", ""]])
    d.save(os.path.join(RAIZ_DOC, "03_QA", "Checklist_QA.docx"))


def analisis():
    d = doc_base("Análisis del sistema — Academia CONAF v2"); control(d)
    d.add_heading("Qué cambió desde la versión anterior", 1)
    tabla(d, ["Fecha", "Cambio", "Detalle"], [[FECHA, "Primera versión del paquete", "README.md"]])
    d.add_heading("Qué es el sistema", 1)
    d.add_paragraph("Repositorio de construcción y despliegue de un Moodle 5.2.1 instalado de cero (base academia_v2, "
                    "PostgreSQL 17). El código de Moodle no vive en el repositorio: lo clona el Dockerfile. El repositorio "
                    "aporta: imagen Docker y compose, config.php por variables, scripts de provisión idempotentes "
                    "(academia/cli, 12 scripts numerados), datos CSV, el tema theme_academia y el plugin mod/customcert.")
    d.add_heading("Matriz de roles", 1); tabla(d, ["Rol", "Manual", "Motivo"], ROLES)
    d.add_heading("Permisos del rol gestorarea definidos por el repositorio", 1)
    d.add_paragraph("Leídos en academia/cli/50_rol_gestor.php (no comprobados ejecutando): prohíbe configurar el sitio, "
                    "gestionar categorías, cohortes, marcos de competencias, roles y cuentas de usuario; permite crear y "
                    "editar cursos de su área, respaldar y restaurar, asignar roles de curso, matricular y ver informes.")
    d.add_heading("Reglas de negocio", 1); tabla(d, ["Id", "Nombre", "Descripción", "Dónde vive"], REGLAS)
    d.add_heading("Hallazgos", 1); tabla(d, ["Id", "Título", "Gravedad", "Evidencia", "Certeza"], HALLAZGOS)
    d.add_heading("Cobertura", 1)
    cub = sum(1 for c in CASOS if c[2] is not None)
    d.add_paragraph(f"Funcionalidades: {len(FUNC)}. Casos: {len(CASOS)} ({len(CASOS)-cub} negativos). "
                    f"Funcionalidades con al menos un caso: {len({c[2] for c in CASOS if c[2] is not None})} de {len(FUNC)}.")
    d.save(os.path.join(RAIZ_DOC, "00_Analisis", "Analisis_Sistema.docx"))


def planilla():
    wb = Workbook(); ws = wb.active; ws.title = "Trazabilidad"
    ws.append(["Caso", "Nombre", "Categoría", "Componente", "Resultado", "Evidencia", "Estado QA"])
    for cid, nom, cat, scr, ps, res, rn in casos_filas(): ws.append([cid, nom, cat, scr, res, rn, "NOT TESTED"])
    for nombre, cab, filas in (("Reglas", ["Id", "Nombre", "Descripción", "Dónde"], REGLAS),
                               ("Roles", ["Rol", "Manual", "Motivo"], ROLES),
                               ("Hallazgos", ["Id", "Título", "Gravedad", "Evidencia", "Certeza"], HALLAZGOS)):
        s = wb.create_sheet(nombre); s.append(cab); [s.append(list(f)) for f in filas]
    for s in wb.worksheets: s.freeze_panes = "A2"; s.auto_filter.ref = s.dimensions
    wb.save(os.path.join(RAIZ_DOC, "04_Trazabilidad", "Trazabilidad.xlsx"))


if __name__ == "__main__":
    for c in ("00_Analisis", "01_Manuales", "02_Casos_de_Uso", "03_QA", "04_Trazabilidad", "05_Problema_PlanMejora", "_pdf"):
        os.makedirs(os.path.join(RAIZ_DOC, c), exist_ok=True)
    ids = [c[0] for c in CASOS] + [r[0] for r in REGLAS]
    assert len(ids) == len(set(ids)), "ids duplicados"
    analisis(); manual(); casos(); qa(); planilla()
    print("ok", RAIZ_DOC)
