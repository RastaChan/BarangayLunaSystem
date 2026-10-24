from pathlib import Path
from PIL import Image,ImageDraw,ImageFont
from docx import Document
from docx.shared import Inches
from docx.oxml import OxmlElement
from docx.oxml.ns import qn
from copy import deepcopy
import json,html
R=Path(r'D:/laragon/www/BARANGAY LUNA SYSTEM/research-output'); W=R/'work'
svg=[]
im=Image.new('RGB',(2800,1900),'white');d=ImageDraw.Draw(im)
fontpath='C:/Windows/Fonts/arial.ttf';boldpath='C:/Windows/Fonts/arialbd.ttf'
def txt(x,y,s,size=30,bold=False,anchor='mm'):
 f=ImageFont.truetype(boldpath if bold else fontpath,size)
 lines=s.split('\n'); lh=size+9
 for i,line in enumerate(lines):
  yy=y+(i-(len(lines)-1)/2)*lh
  bbox=d.textbbox((x,yy),line,font=f,anchor=anchor); d.rectangle((bbox[0]-4,bbox[1]-2,bbox[2]+4,bbox[3]+2),fill='white') if size<=27 and not bold else None
  d.text((x,yy),line,font=f,fill='#222222',anchor=anchor)
  svg.append(f'<text x="{x}" y="{yy}" font-family="Arial" font-size="{size}" font-weight="{700 if bold else 400}" text-anchor="middle" dominant-baseline="central" style="paint-order:stroke;stroke:white;stroke-width:5px;stroke-linejoin:round">{html.escape(line)}</text>')
def rect(r,fill='#fff2a8',radius=0):
 if radius:d.rounded_rectangle(r,radius,fill,outline='#333333',width=3)
 else:d.rectangle(r,fill,outline='#333333',width=3)
 x,y,x2,y2=r;svg.append(f'<rect x="{x}" y="{y}" width="{x2-x}" height="{y2-y}" rx="{radius}" fill="{fill}" stroke="#333" stroke-width="3"/>')
def line(points,arrow=True):
 d.line(points,fill='#333333',width=3,joint='curve')
 svg.append('<polyline points="'+' '.join(f'{x},{y}' for x,y in points)+'" fill="none" stroke="#333" stroke-width="3"'+(' marker-end="url(#arrow)"' if arrow else '')+'/>')
 if arrow:
  x,y=points[-1];a,b=points[-2];dx=x-a;dy=y-b
  if abs(dx)>abs(dy):sgn=1 if dx>0 else -1;tri=[(x,y),(x-sgn*15,y-8),(x-sgn*15,y+8)]
  else:sgn=1 if dy>0 else -1;tri=[(x,y),(x-8,y-sgn*15),(x+8,y-sgn*15)]
  d.polygon(tri,fill='#333333')
def proc(n,y,label):
 rect((820,y,1320,y+145),radius=25);line([(820,y+43),(1320,y+43)],False);txt(1070,y+22,n,28,True);txt(1070,y+95,label,32)
def store(n,y,label):
 rect((1850,y+26,2390,y+118));rect((1850,y+26,1920,y+118),'#d3d3d3');txt(1885,y+72,n,25,True);txt(2155,y+72,label,28)
def pair(y,outlabel,inlabel):
 line([(1320,y+53),(1850,y+53)]);txt(1585,y+32,outlabel,25)
 line([(1850,y+101),(1320,y+101)]);txt(1585,y+126,inlabel,25)
ys=[160,420,680,940,1200,1460]
txt(1400,45,'PROPOSED DATA FLOW DIAGRAM',44,True)
txt(1400,94,'Barangay Luna Smart Portal • Level 1 • Current implementation',30)
labels=['Account registration,\nprofile and QR management','Document request submission\nand staff review','Document preparation\n(online or walk-in)','Cash payment recording\nand confirmed release','Community services\nand SMS notifications','Reports and system\nadministration']
for i,(y,label) in enumerate(zip(ys,labels),1):proc(f'{i}.0',y,label)
for n,y,label in [('D1',160,'Accounts, resident records\nand registration requirements'),('D2',420,'Document requests'),('D3',680,'Document templates and fees'),('D4',940,'Prepared / released documents\nand cash payment records'),('D5',1200,'Announcements, complaints,\nfeedback and SMS logs'),('D6',1460,'Settings, audit records\nand backup records')]:store(n,y,label)
pair(160,'Account / profile / QR record','Saved account and resident data')
pair(420,'Request / reviewed status','Request details and status')
# Templates are administered separately; preparation reads them.
line([(1850,752),(1320,752)]);txt(1585,720,'Active template and configured fee',25)
line([(1320,788),(1785,788),(1785,966),(1850,966)]);txt(1565,820,'Prepared document / payment due',25)
pair(940,'Paid / Released record','Prepared document / amount due')
pair(1200,'Community records / SMS result','Posts, complaints, feedback, logs')
pair(1460,'Settings / audit / backup record','Configuration and audit data')
# External entities and their explicit input/output flows.
rect((50,160,360,285));txt(205,222,'Resident',34,True)
line([(360,192),(820,192)]);txt(590,172,'Login / profile details',25)
line([(820,265),(360,265)]);txt(590,296,'Account access / QR profile',25)
line([(205,285),(205,465),(820,465)]);txt(515,441,'Reviewed request, purpose, copies',25)
line([(820,540),(300,540),(300,285)]);txt(550,568,'Request status / service response',25)
line([(100,285),(100,1245),(820,1245)]);txt(485,1224,'Complaint / feedback',25)
rect((360,725,700,850));txt(530,787,'Barangay Staff',33,True)
line([(700,750),(755,750),(755,210),(820,210)]);txt(530,355,'Resident identity, location\nand requirements',26)
line([(700,770),(780,770),(780,510),(820,510)]);txt(530,624,'Request review / rejection reason',25)
line([(700,800),(820,800)]);txt(550,886,'Applicant details, requirements,\npreparation / print request',25)
line([(700,830),(755,830),(755,985),(820,985)]);txt(525,1028,'Cash received / Confirm Release',25)
line([(530,850),(530,1300),(820,1300)]);txt(525,1158,'Announcements / complaint action',25)
# Sequential process-to-process data.
for a,b,label in [(305,420,'Resident data'),(565,680,'Reviewed online request'),(825,940,'Prepared issuance / amount due'),(1085,1200,'Request / release notification')]:
 line([(1070,a),(1070,b)]);txt(1070,(a+b)/2,label,23)
line([(1320,1005),(1695,1005),(1695,585),(1275,585),(1275,565)]);txt(1500,902,'Released request status',24)
# Preparation supplies Ready status as well.
line([(1320,706),(1730,706),(1730,510),(1320,510)]);txt(1540,637,'Processing / Ready status',24)
rect((2460,1200,2750,1345));txt(2605,1272,'SMS Gateway',31,True)
line([(1320,1315),(1800,1315),(1800,1380),(2605,1380),(2605,1345)]);txt(2100,1412,'Recipient mobile number / SMS message',25)
line([(2460,1245),(2425,1245),(2425,1150),(1380,1150),(1380,1245),(1320,1245)]);txt(1990,1126,'Gateway response → SMS log',25)
# Feedback to residents is a portal output; delivery is gateway-dependent.
line([(820,1330),(155,1330),(155,300)]);txt(485,1360,'Published posts / service updates',25)
rect((50,1460,360,1585));txt(205,1522,'Administrator',32,True)
line([(360,1495),(820,1495)]);txt(585,1470,'Report / settings / template request',24)
line([(820,1560),(360,1560)]);txt(590,1607,'Service, collection and audit reports',24)
line([(700,825),(725,825),(725,1515),(820,1515)]);txt(525,1430,'Staff report request',24)
line([(1320,1540),(1770,1540),(1770,870),(1970,870),(1970,752)]);txt(2080,850,'Admin template / fee updates',24)

for y in ys[:5]:
 line([(2390,y+100),(2410,y+100)],False)
line([(2410,260),(2410,1705),(1070,1705),(1070,1605)])
txt(1670,1734,'D1–D5 operational data for reports',25)
# Add a reporting read annotation instead of crossing every individual store.
txt(2120,1660,'Process 6.0 reads D1–D5 for\nservice and cash collection reports.',27)
txt(1400,1780,'Usual online path: Pending → Processing → Ready for Pickup → Released',30,True)
txt(1400,1828,'Staff also handles walk-in applicants. Payment is office cash; printing does not confirm release.',27)
# Raster and editable vector from the same drawing.
out=R/'PROPOSED_DATA_FLOW_DIAGRAM_CURRENT_SYSTEM.png';im.save(out)
head='<svg xmlns="http://www.w3.org/2000/svg" width="2800" height="1900" viewBox="0 0 2800 1900"><defs><marker id="arrow" markerWidth="8" markerHeight="8" refX="7" refY="4" orient="auto"><path d="M0,0 L8,4 L0,8" fill="#333"/></marker></defs><rect width="2800" height="1900" fill="white"/>'
(R/'PROPOSED_DATA_FLOW_DIAGRAM_CURRENT_SYSTEM.svg').write_text(head+''.join(svg)+'</svg>',encoding='utf-8')
# Replace the proposed diagram only. Preserve all existing manuscript text.
src=R/'BARANGAY_LUNA_CHAPTERS_1_TO_5_ORIGINAL_WORDING.docx';doc=Document(src)
cap=next(p for p in doc.paragraphs if p.text=='Figure 7  Original proposed data flow diagram')
pnode=cap._p.getprevious()
for child in list(pnode):
 if child.tag!=qn('w:pPr'):pnode.remove(child)
from docx.text.paragraph import Paragraph
p=Paragraph(pnode,doc);p.add_run().add_picture(str(out),width=Inches(9.25))
# Isolate the diagram in a landscape section and retain the current portrait body.
base=deepcopy(doc.element.body.sectPr)
def section(landscape=False):
 s=deepcopy(base)
 if landscape:
  for x in s.findall(qn('w:pgNumType')):x.attrib.pop(qn('w:start'),None)
 typ=s.find(qn('w:type'))
 if typ is None:typ=OxmlElement('w:type');s.insert(0,typ)
 typ.set(qn('w:val'),'nextPage')
 if landscape:
  pg=s.find(qn('w:pgSz'));pg.set(qn('w:w'),'16838');pg.set(qn('w:h'),'11906');pg.set(qn('w:orient'),'landscape')
  mg=s.find(qn('w:pgMar'))
  for k in ['top','bottom','left','right']:mg.set(qn('w:'+k),'720')
 return s
prev=pnode.getprevious();pp=prev.find(qn('w:pPr'))
if pp is None:pp=OxmlElement('w:pPr');prev.insert(0,pp)
pp.append(section())
cap._p.get_or_add_pPr().append(section(True))
for x in doc.element.body.sectPr.findall(qn('w:pgNumType')):x.attrib.pop(qn('w:start'),None)
# Avoid a pre-existing page break creating an empty diagram page.
p.paragraph_format.page_break_before=False;p.paragraph_format.keep_with_next=True
cap.paragraph_format.keep_with_next=False
for i,x in enumerate(doc.element.findall('.//'+qn('wp:docPr')),1):x.set('id',str(i))
original=[p.text for p in Document(src).paragraphs];updated=[p.text for p in doc.paragraphs]
assert original==updated,'Text was changed'
for para in doc.paragraphs:
 for run in para.runs:
  if 'Figure 7  Original proposed data flow diagram' in run.text:run.text=run.text.replace('Figure 7  Original proposed data flow diagram','Figure 7  Proposed data flow diagram based on the implemented system')
result=R/'BARANGAY_LUNA_CHAPTERS_1_TO_5_UPDATED_DFD.docx';doc.save(result)
print(json.dumps({'diagram':str(out),'manuscript':str(result),'all_existing_paragraphs_unchanged':True}))
