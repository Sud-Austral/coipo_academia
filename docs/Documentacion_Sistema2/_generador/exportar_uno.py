# Exporta un DOCX a PDF: Word 2007 por COM (DispatchEx nuevo), actualiza índice, PrintOut a Microsoft Print to PDF.
import sys, os, time, pythoncom, win32com.client
src, dst = os.path.abspath(sys.argv[1]), os.path.abspath(sys.argv[2])
if os.path.exists(dst): os.remove(dst)
pythoncom.CoInitialize()
w = win32com.client.DispatchEx("Word.Application"); w.Visible = False; w.DisplayAlerts = 0
d = w.Documents.Open(src)
for i in range(1, d.TablesOfContents.Count + 1): d.TablesOfContents(i).Update()
d.Save()
# PrintOut(Background, Append, Range, OutputFileName, From, To, Item, Copies, Pages, PageType, PrintToFile)
d.PrintOut(False, False, 0, dst, "", "", 0, 1, "", 0, True)
for _ in range(120):
    if os.path.exists(dst) and open(dst, "rb").read()[-1024:].find(b"%%EOF") >= 0: break
    time.sleep(1)
d.Close(0); w.Quit()
print("pdf", dst, os.path.getsize(dst))
