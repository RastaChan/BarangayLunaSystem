from pathlib import Path
from copy import deepcopy
from io import BytesIO
import re,json
from docx import Document
from docx.shared import Inches,Pt
from docx.oxml import OxmlElement
from docx.oxml.ns import qn
from docx.enum.text import WD_ALIGN_PARAGRAPH,WD_BREAK
from docx.enum.style import WD_STYLE_TYPE
from docx.text.paragraph import Paragraph

ROOT=Path(__file__).resolve().parents[2]
OUT=ROOT/'research-output';WORK=OUT/'work'
source=Document(r'C:\Users\Christian Deloy\Downloads\FINAL.docx')
current=Document(OUT/'BARANGAY_LUNA_REVISED_CHAPTERS_1_TO_5.docx')
doc=Document(OUT/'BARANGAY_LUNA_REVISED_CHAPTERS_1_TO_5.docx')
if 'Caption' not in doc.styles:
 st=doc.styles.add_style('Caption',WD_STYLE_TYPE.PARAGRAPH);st.font.name='Arial';st.font.size=Pt(10);st.paragraph_format.line_spacing=1
body=doc.element.body
def text(n):return ''.join(n.itertext()) if False else (Paragraph(n,doc).text if n.tag==qn('w:p') else '')
nodes=list(body);start=next(i for i,n in enumerate(nodes) if text(n)=='CHAPTER 1');end=next(i for i,n in enumerate(nodes) if text(n)=='CHAPTER 4')
anchor=nodes[end]
for n in nodes[start:end]:body.remove(n)
original_headings={118:1,122:2,124:2,126:2,127:3,129:3,136:2,137:3,147:3,163:1,166:2,169:2,172:2,174:2,198:1,202:2,207:2,218:3,228:3,234:3,240:3,247:2,256:2,269:2,283:3,295:3,328:3,421:2}
copied=[];original_figures=[];original_tables=[]
def addp(value,style=None):
 p=doc.add_paragraph(value,style=style);body.remove(p._p);anchor.addprevious(p._p);return p
def remap_source_links(n):
 for link in n.findall('.//'+qn('w:hyperlink')):
  rid=link.get(qn('r:id'))
  if rid:
   rel=source.part.rels[rid]
   link.set(qn('r:id'),doc.part.relate_to(rel.target_ref,rel.reltype,is_external=rel.is_external))
def original_range(a,b):
 first=source.paragraphs[a]._p;last=source.paragraphs[b]._p
 n=first
 while n is not last:
  nxt=n.getnext();new=deepcopy(n);remap_source_links(new)
  if n.tag==qn('w:p'):
   idx=next(i for i,p in enumerate(source.paragraphs) if p._p is n)
   value=source.paragraphs[idx].text
   embeds=[source.part.rels[x.get(qn('r:embed'))].target_part.blob for x in n.findall('.//'+qn('a:blip')) if x.get(qn('r:embed'))]
   # Convert original floating figures to inline images; remove decorative shapes only.
   for tag in ['w:drawing','w:pict']:
    for x in new.findall('.//'+qn(tag)):x.getparent().remove(x)
   for x in new.findall('.//'+qn('w:sectPr')):x.getparent().remove(x)
   if value.strip():
    anchor.addprevious(new);p=Paragraph(new,doc);copied.append(value)
    if idx in original_headings:p.style=f'Heading {original_headings[idx]}'
    if idx in [118,163,198]:p.paragraph_format.page_break_before=idx!=118;p.alignment=WD_ALIGN_PARAGRAPH.CENTER
    if idx in [119,164,200]:p.alignment=WD_ALIGN_PARAGRAPH.CENTER
    # Existing captions are retained word for word.
    if value in ['Table 1','Table 2']:original_tables.append((new,value))
   for imageblob in embeds:
    p=addp('');p.alignment=WD_ALIGN_PARAGRAPH.CENTER;p.paragraph_format.line_spacing=1
    p.add_run().add_picture(BytesIO(imageblob),width=Inches(5.75))
    shape=doc.inline_shapes[-1]
    if shape.height>Inches(7.3):shape.width=int(shape.width*Inches(7.3)/shape.height);shape.height=Inches(7.3)
    original_figures.append(p._p)
    labels={204:'Original conceptual framework',208:'Original RAD methodology',257:'Original system architecture',272:'Original existing context diagram',286:'Original existing data flow diagram',311:'Original proposed context diagram',356:'Original proposed data flow diagram',380:'Original development schedule March to May',404:'Original development schedule August to October'}
    cap=addp(f'Figure {len(original_figures)}  {labels[idx]}','Caption');cap.paragraph_format.keep_with_next=False
  elif n.tag==qn('w:tbl'):
   if source.tables[2]._tbl is n:addp('Table 3  Proposed Revenue Streams','Caption')
   anchor.addprevious(new)
   for row in new.findall('.//'+qn('w:tr')):
    for pnode in row.findall('.//'+qn('w:p')):
     p=Paragraph(pnode,doc);p.paragraph_format.left_indent=Inches(0);p.paragraph_format.right_indent=Inches(0);p.paragraph_format.first_line_indent=Inches(0)
     p.paragraph_format.line_spacing=1
     for r in p.runs:r.font.size=Pt(10)
  n=nxt

original_range(118,163)
addp('1.5 Additional Implemented System Features','Heading 2')
addp('The following implementation details supplement the proposed features described in this chapter. They describe the functions available in the current system.')
addp('Authorized barangay staff or the administrator create resident accounts and encode identity, location, and registration requirements. Successful registration activates the account and QR profile and marks the resident record Verified. Residents use the assigned account to request services; public self-registration is not part of the current implementation.')
addp('Residents can preview a document request, confirm its details and number of copies, monitor its status, and view their own service updates. Multiple copies of one document type are supported. Requesting several different document types in one transaction remains a proposed enhancement. Staff can also assist registered or unregistered walk-in applicants through resident search, optional QR lookup, or manual applicant encoding.')
addp('Staff prepare printable documents from managed DOCX templates, record cash payments at the barangay office, and use Confirm Release to record the completed handover. A prepared document is not yet a released certificate. The administrator manages fees, staff access, templates, settings, audit records, backups, and service and collection reports. SMS notifications depend on the configured gateway and network availability.')
original_range(163,198)
original_range(198,429)
addp('3.8 Additional Details of the Implemented System','Heading 2')
addp('The following sections describe the current implementation and supplement the proposal-stage architecture and process diagrams presented above. The current request, account, cash payment, document preparation, and release behavior is represented in the additional diagrams. The subscription and service prices previously presented under Revenue Streams remain proposal estimates; the implemented collection reports record actual document-service cash payments.')
addp('The April requirements interview describes staff preparing certificates through ready-made Microsoft Word documents and saving completed files, with identity and location checked through local personnel when necessary. This existing workflow is represented below. The current portal retains personnel verification while connecting resident records, requests, document preparation, and payments.')
cn=list(current.element.body)
def ci(value):return next(i for i,n in enumerate(cn) if text(n)==value)
addnodes=cn[ci('3.1 Conceptual Framework'):ci('3.2 Research and Development Methodology')]+cn[ci('3.4 System Architecture'):ci('CHAPTER 4')]
sec=0;sub=0;additional_table=3;additional_figure=9
for n in addnodes:
 new=deepcopy(n)
 if n.tag==qn('w:p'):
  p=Paragraph(new,doc);value=p.text
  if re.match(r'^3\.\d+ ',value):
   sec+=1;sub=0;newvalue=re.sub(r'^3\.\d+ ',f'3.8.{sec} ',value);p.text=newvalue;p.style='Heading 3'
  elif re.match(r'^3\.\d+\.\d+ ',value):
   sub+=1;p.text=re.sub(r'^3\.\d+\.\d+ ',f'3.8.{sec}.{sub} ',value);p.style='Heading 3'
  if value.startswith('Figure '):
   additional_figure+=1;p.text=re.sub(r'^Figure \d+  ',f'Figure {additional_figure}  Updated ',value)
  if value.startswith('Table '):
   additional_table+=1;p.text=re.sub(r'^Table \d+  ',f'Table {additional_table}  ',value)
  if value.startswith('The supported online request statuses'):pass
 anchor.addprevious(new)

# Retain the adviser-approved literature reference entries as well.
ref=next(n for n in list(body) if text(n)=='REFERENCES')
after=ref.getnext()
label=doc.add_paragraph('References from the Original Proposal',style='Heading 2');body.remove(label._p);after.addprevious(label._p)
for p in source.paragraphs[595:]:
 if p.text.strip():
  n=deepcopy(p._p);remap_source_links(n)
  for x in n.findall('.//'+qn('w:drawing')):x.getparent().remove(x)
  after.addprevious(n)
label=doc.add_paragraph('Additional References',style='Heading 2');body.remove(label._p);after.addprevious(label._p)

# Create fresh contents and figure/table lists, with native page-reference fields.
bookmark_id=1000
def bookmark(n):
 global bookmark_id
 bookmark_id+=1;name=f'approved{bookmark_id}'
 begin=OxmlElement('w:bookmarkStart');begin.set(qn('w:id'),str(bookmark_id));begin.set(qn('w:name'),name)
 finish=OxmlElement('w:bookmarkEnd');finish.set(qn('w:id'),str(bookmark_id));n.insert(1 if n.find(qn('w:pPr')) is not None else 0,begin);n.append(finish);return name
def field(p,name):
 p.add_run('\t');f=OxmlElement('w:fldSimple');f.set(qn('w:instr'),f'PAGEREF {name} \\h');p._p.append(f)
def regenerate(title,stop,entries):
 ns=list(body)
 def title_node(n,value):return n.tag==qn('w:p') and text(n)==value and Paragraph(n,doc).style.name=='Heading 1'
 a=next(i for i,n in enumerate(ns) if title_node(n,title));b=next(i for i in range(a+1,len(ns)) if title_node(ns[i],stop))
 pivot=ns[b]
 for n in ns[a+1:b]:
  if n.tag==qn('w:p') and n.find('.//'+qn('w:sectPr')) is not None:pivot=n;continue
  body.remove(n)
 for value,level,n in entries:
  p=doc.add_paragraph();body.remove(p._p);p.paragraph_format.line_spacing=1;p.paragraph_format.space_after=Pt(2);p.paragraph_format.space_before=Pt(0)
  p.paragraph_format.first_line_indent=Inches(0);p.paragraph_format.right_indent=Inches(0)
  p.paragraph_format.left_indent=Inches(.18*max(0,level-1));p.paragraph_format.tab_stops.add_tab_stop(Inches(5.75-.18*max(0,level-1)),WD_ALIGN_PARAGRAPH.RIGHT)
  p.add_run(value);field(p,bookmark(n))
  for r in p.runs:r.font.size=Pt(9.5)
  pivot.addprevious(p._p)
entries=[];newtables=[];newfigs=[]
chapter=0;in_body=False
for n in list(body):
 if n.tag!=qn('w:p'):continue
 p=Paragraph(n,doc);value=p.text.strip()
 if value.startswith('CHAPTER ') and p.style.name=='Heading 1':chapter=int(value[-1]);in_body=True
 if value=='ABSTRACT' and p.style.name=='Heading 1':entries.append((value,1,n))
 if in_body and p.style.name in ['Heading 1','Heading 2'] and value:
  if value in ['RESULTS AND DISCUSSION','SUMMARY CONCLUSIONS AND RECOMMENDATIONS','References from the Original Proposal','Additional References']:continue
  level=int(p.style.name[-1])
  if level==2 and entries and entries[-1][0].startswith('APPENDIX '):
   prev=entries[-1];entries[-1]=(prev[0]+': '+value,prev[1],prev[2])
  else:entries.append((value,level,n))
 if in_body and (value.startswith('Table ') or value.startswith('Additional Table')):newtables.append((value,1,n))
 if in_body and (value.startswith('Figure ') or value.startswith('Additional Figure')):newfigs.append((value,1,n))
regenerate('TABLE OF CONTENTS','ABSTRACT',entries)
regenerate('LIST OF TABLES','LIST OF FIGURES',newtables)
regenerate('LIST OF FIGURES','CHAPTER 1',newfigs)

# Confirm that every nonempty approved chapter paragraph survives verbatim and in order.
approved=[p.text for p in source.paragraphs[118:429] if p.text.strip()]
alltext=[p.text for p in doc.paragraphs]
cursor=0
for value in approved:
 try:cursor=alltext.index(value,cursor)+1
 except ValueError:raise AssertionError('Missing approved wording: '+value[:100])
out=OUT/'BARANGAY_LUNA_CHAPTERS_1_TO_5_ORIGINAL_WORDING.docx'
for i,n in enumerate(doc.element.findall('.//'+qn('wp:docPr')),1):n.set('id',str(i))
doc.save(out)
audit={'output':str(out),'verbatim_approved_paragraphs':len(approved),'original_diagrams_retained':len(original_figures),'original_chapter_tables_retained':3,'new_diagrams':6,'original_wording_preserved':True}
(WORK/'approved-wording-audit.json').write_text(json.dumps(audit,indent=2))
print(json.dumps(audit,indent=2))
