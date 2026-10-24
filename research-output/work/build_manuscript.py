from pathlib import Path
from copy import deepcopy
import json, csv, hashlib, math, textwrap
from collections import Counter
from docx import Document
from docx.shared import Inches, Pt, RGBColor
from docx.enum.text import WD_ALIGN_PARAGRAPH
from docx.enum.section import WD_SECTION_START
from docx.enum.style import WD_STYLE_TYPE
from docx.oxml import OxmlElement
from docx.oxml.ns import qn
from PIL import Image, ImageDraw, ImageFont

ROOT=Path(__file__).resolve().parents[2]
OUT=ROOT/'research-output'
WORK=OUT/'work'
SOURCE=Path(r'C:\Users\Christian Deloy\Downloads\FINAL.docx')
PHOTO=Path(r'C:\Users\Christian Deloy\Downloads\Survey Results')
original=Document(SOURCE)
doc=Document(SOURCE)
for child in list(doc.element.body):
    if child.tag!=qn('w:sectPr'): doc.element.body.remove(child)
for s in doc.sections:
    s.page_width=Inches(8.268); s.page_height=Inches(11.693)
    s.top_margin=Inches(1.25); s.bottom_margin=Inches(1)
    s.left_margin=Inches(1.5); s.right_margin=Inches(.8)
    s.header_distance=Inches(.5);s.footer_distance=Inches(.5)
    for part in [s.header,s.footer,s.first_page_header,s.first_page_footer,s.even_page_header,s.even_page_footer]:
        for p in part.paragraphs: p.clear()
    borders=s._sectPr.find(qn('w:pgBorders'))
    if borders is not None:s._sectPr.remove(borders)
for name in ['Normal','Title','Heading 1','Heading 2','Heading 3','Caption']:
    if name not in doc.styles:doc.styles.add_style(name,WD_STYLE_TYPE.PARAGRAPH)
    st=doc.styles[name];st.font.name='Arial';st.font.color.rgb=RGBColor(0,0,0);st.font.size=Pt(12)
    st.paragraph_format.space_after=Pt(6)
    st.paragraph_format.line_spacing=2 if name=='Normal' else 1
    if name.startswith('Heading'):
        st.font.bold=True;st.paragraph_format.keep_with_next=True
        st.paragraph_format.space_before=Pt(10)
doc.styles['Title'].font.size=Pt(16);doc.styles['Title'].font.bold=True
doc.styles['Heading 1'].font.size=Pt(14)
doc.styles['Caption'].font.size=Pt(10);doc.styles['Caption'].font.italic=False
doc.styles['Normal'].paragraph_format.widow_control=True

ratings={
'R01':[[5,5,4,3,5],[5,4,5,3,5],[5,4,3,5,5],[4,5,3,4,5],[5,4,4,5,4],[5,5,4,3,4]],
'R02':[[4,4,5,5,4],[4,4,5,5,4],[4,4,5,5,4],[4,4,5,5,4],[5,5,5,5,5],[4,5,5,4,5]],
'R03':[[4,4,4,4,5],[5,5,5,5,4],[5,4,4,5,4],[4,4,4,5,5],[5,5,4,4,4],[5,5,4,5,5]],
'R04':[[4,5,4,4,4],[4,4,4,4,4],[3,4,4,4,4],[4,4,4,4,4],[4,4,4,4,4],[5,5,4,4,4]],
'R05':[[4,4,5,4,4],[5,5,4,4,5],[4,4,4,5,4],[4,4,5,4,5],[5,4,5,4,4],[4,5,5,4,5]],
'R06':[[3,4,5,5,5],[3,4,4,4,3],[5,3,4,5,3],[3,4,4,4,4],[5,5,5,5,5],[4,5,5,4,4]],
'R07':[[5,3,4,5,3],[5,5,5,5,5],[4,5,4,5,5],[5,5,5,5,4],[5,5,4,5,4],[5,5,4,5,5]],
'R08':[[5,4,4,5,4],[5,4,5,5,4],[4,4,5,4,5],[5,4,5,4,4],[4,4,4,5,5],[5,5,5,5,4]],
'R09':[[4,4,5,4,4],[4,5,4,5,4],[4,5,5,3,4],[3,5,5,5,4],[4,5,5,4,5],[4,5,5,4,4]],
'R10':[[5,4,5,5,5],[5,5,5,4,3],['NA',5,5,5,5],[3,5,4,5,5],[5,5,5,4,'NA'],[5,5,5,4,5]],
'TS1':[[4,5,4,4,4],[4,4,4,4,4],[4,5,4,4,5],[4,5,5,4,4],[5,5,5,5,5],[4,5,5,3,4]],
'TS2':[[4,5,4,4,4],[5,5,5,4,4],[4,5,4,5,4],[4,5,5,5,4],[5,5,5,5,5],[4,5,5,3,5]],
'TA1':[[4,4,5,5,5],[5,4,5,5,5],[4,4,5,4,4],[3,4,5,5,5],[5,5,5,5,5],[4,4,4,3,4]]}
pagefiles={
'R01':['838983607_2168358170743088_8775477051827181185_n.jpg','838327829_1566256351927129_3132549158109952971_n.jpg','842784941_988232550973604_7786896282574296696_n.jpg'],
'R02':['843410128_1111925888370924_3876357838011915548_n.jpg','846049817_1603506390679549_4805960671093787277_n.jpg','840263446_1658910409144375_5808230774727576242_n.jpg'],
'R03':['841338961_29869468782642333_7911800190156771830_n.jpg','846319696_1139332311946949_1877730862726785539_n.jpg','840951173_1515429757313855_6979476684122222298_n.jpg'],
'R04':['840922619_2143811039679481_7731470946586881149_n.jpg','838983607_1594739845737035_8103875275365846763_n.jpg','843907673_1064785643222308_5632644806142044930_n.jpg'],
'R05':['845115278_1838063270518134_3797715012364622644_n.jpg','844616222_3178772245646615_4600264718419462246_n.jpg','847549543_1391060419767463_6097338764791975831_n.jpg'],
'R06':['840697313_1132811312622398_6944210156104760603_n.jpg','844358799_980422754395750_7027955403511497935_n.jpg','843907673_1100129219476173_4319505033831440117_n.jpg'],
'R07':['837395891_1847918582869930_9110439192408142183_n.jpg','846528001_2277224209792760_1406597296073774990_n.jpg','847569531_3052383911773863_7697185115402552437_n.jpg'],
'R08':['837798095_1081287054779048_5182424533399623657_n.jpg','844301047_1903327324410143_4386711948320398950_n.jpg','841338961_1129510159607942_1282505525219881702_n.jpg'],
'R09':['843047937_3586242018192060_8618087881830277916_n.jpg','845011945_1788029179098510_6190325096569031781_n.jpg','844460473_1327772842665742_413457631360614642_n.jpg'],
'R10':['843223836_1122173074107702_1902172560250880410_n.jpg','842574275_1110775971676534_2115104418439695908_n.jpg','841165612_2518409131999212_5133690513716325668_n.jpg'],
'TS1':['839930897_1107651104994413_7554935902720300694_n.jpg','843457096_2020338985346499_2465633741465194480_n.jpg','839360719_4725329201065712_5587629752721451249_n.jpg'],
'TS2':['838658556_1060157477052089_33390820064688177_n.jpg','839842215_1027634313667932_8795676474421725501_n.jpg','845730375_2406438133514364_4218663030568657668_n.jpg'],
'TA1':['845115198_1467567575435707_4702976667316920643_n.jpg','846769969_1142083761592511_4544180939055425522_n.jpg','840051600_1411382457861690_3881603659085732566_n.jpg']}
items=[
['The features available to my user role work as expected.','I can complete the tasks I need to perform using the system.','The system displays the correct information after I complete an action.','The system provides useful information about the status of my transactions.','The system provides the features I need for my barangay transactions or assigned duties.'],
['The system is easy to learn.','Menus, buttons, and labels are easy to understand.','Forms and instructions are clear.','The layout is easy to use on the device I used during the evaluation.','I can navigate between sections without becoming confused.'],
['The system operates without unexpected errors during my tasks.','Information I save remains available when I return to it.','Repeating a task produces consistent results.','When I enter invalid information, the system gives a clear message and lets me correct it.','The system completes my transactions without losing the information I submitted.'],
['Pages load within an acceptable amount of time.','The system responds promptly when I click buttons or open menus.','Searches and filters display results promptly.','Saving or submitting information completes within an acceptable amount of time.','The system remains responsive while I complete several tasks in succession.'],
['I am required to log in before accessing account-related features.','Passwords are hidden while I type them unless I choose to display them.','The features available to my account are appropriate for my user role.','After logging out, I cannot access protected account pages without logging in again.','The system asks me to enter my current password before changing my password.'],
['Overall, I am satisfied with the system.','The system is useful for the barangay services or tasks I need.','I would be willing to use the system for future transactions or work.','I would recommend the system to other intended users.','The system meets my expectations for an online barangay service portal.']]
dims=['Functionality','Usability','Reliability','Performance','Security','Overall satisfaction']
resids=[r for r in ratings if r.startswith('R')]
teamids=[r for r in ratings if not r.startswith('R')]
def vals(ids,dimension=None,item=None):
    a=[]
    for r in ids:
        for d,row in enumerate(ratings[r]):
            if dimension is not None and d!=dimension:continue
            for j,v in enumerate(row):
                if item is not None and j!=item:continue
                if isinstance(v,int):a.append(v)
    return a
def mean(a):return sum(a)/len(a)
def interpret(v):
    return 'Strongly agree' if v>=4.5 else 'Agree' if v>=3.5 else 'Neutral' if v>=2.5 else 'Disagree' if v>=1.5 else 'Strongly disagree'
survey={'ratings':ratings,'source_pages':pagefiles,'dimension_names':dims,'items':items,
        'resident_valid_n':len(vals(resids)),'team_valid_n':len(vals(teamids)),
        'resident_overall':mean(vals(resids)),'team_overall':mean(vals(teamids)),
        'dimensions':[{ 'dimension':name,'resident_n':len(vals(resids,d)),'resident_mean':mean(vals(resids,d)),'team_n':len(vals(teamids,d)),'team_mean':mean(vals(teamids,d))} for d,name in enumerate(dims)]}
(WORK/'survey-audit.json').write_text(json.dumps(survey,ensure_ascii=False,indent=2),encoding='utf8')
with (WORK/'survey-audit.csv').open('w',newline='',encoding='utf-8-sig') as f:
    wr=csv.writer(f);wr.writerow(['Evaluator','Participation','Device']+[f'{c}{j}' for c in 'ABCDEF' for j in range(1,6)])
    for r,rows in ratings.items():wr.writerow([r,'Intended resident' if r.startswith('R') else 'Project team role test','Computer' if r in ['R09','TS1','TS2','TA1'] else 'Cellphone']+[v for row in rows for v in row])

TABLES=[];FIGURES=[];TOC=[];bookmarkid=0
def bookmark(p,name):
    global bookmarkid
    bookmarkid+=1;s=OxmlElement('w:bookmarkStart');s.set(qn('w:id'),str(bookmarkid));s.set(qn('w:name'),name)
    e=OxmlElement('w:bookmarkEnd');e.set(qn('w:id'),str(bookmarkid));p._p.insert(0,s);p._p.append(e)
def field(p,instr,result=''):
    r=p.add_run();b=OxmlElement('w:fldChar');b.set(qn('w:fldCharType'),'begin');r._r.append(b)
    r=p.add_run();t=OxmlElement('w:instrText');t.set(qn('xml:space'),'preserve');t.text=' '+instr+' ';r._r.append(t)
    r=p.add_run();s=OxmlElement('w:fldChar');s.set(qn('w:fldCharType'),'separate');r._r.append(s)
    p.add_run(result);r=p.add_run();e=OxmlElement('w:fldChar');e.set(qn('w:fldCharType'),'end');r._r.append(e)
def p(text='',bold=False,small=False):
    a=doc.add_paragraph(text);a.alignment=WD_ALIGN_PARAGRAPH.JUSTIFY
    if small:
        a.paragraph_format.line_spacing=1;a.paragraph_format.space_after=Pt(6)
        for r in a.runs:r.font.size=Pt(10)
    if bold:
        for r in a.runs:r.bold=True
    return a
def heading(text,level=2):
    a=doc.add_paragraph(text,style=f'Heading {level}');name=f'h{len(TOC)+1}';bookmark(a,name);TOC.append((text,level,name));return a
def chapter(num,title):
    doc.add_page_break();a=heading(f'CHAPTER {num}',1);a.alignment=WD_ALIGN_PARAGRAPH.CENTER
    a=doc.add_paragraph(title.upper(),style='Heading 1');a.alignment=WD_ALIGN_PARAGRAPH.CENTER
def caption(text,kind):
    a=doc.add_paragraph(text,style='Caption');a.paragraph_format.keep_with_next=True
    name=f'{kind}{len(TABLES if kind=="t" else FIGURES)+1}';bookmark(a,name)
    (TABLES if kind=='t' else FIGURES).append((text,name));return a
def table(title,headers,rows,widths=None):
    caption(f'Table {len(TABLES)+1}  {title}','t')
    t=doc.add_table(rows=1, cols=len(headers));t.style='Table Grid';t.autofit=False
    for i,h in enumerate(headers):t.rows[0].cells[i].text=str(h)
    for row in rows:
        cells=t.add_row().cells
        for i,v in enumerate(row):cells[i].text=str(v)
    for ri,row in enumerate(t.rows):
        trpr=row._tr.get_or_add_trPr();n=OxmlElement('w:cantSplit');trpr.append(n)
        if ri==0:
            n=OxmlElement('w:tblHeader');trpr.append(n)
        for i,c in enumerate(row.cells):
            if widths:c.width=Inches(widths[i])
            mar=OxmlElement('w:tcMar')
            for side in ['left','right']:
                margin=OxmlElement('w:'+side);margin.set(qn('w:w'),'60');margin.set(qn('w:type'),'dxa');mar.append(margin)
            c._tc.get_or_add_tcPr().append(mar)
            for a in c.paragraphs:
                a.alignment=WD_ALIGN_PARAGRAPH.LEFT
                a.paragraph_format.left_indent=Inches(0);a.paragraph_format.right_indent=Inches(0);a.paragraph_format.first_line_indent=Inches(0)
                a.paragraph_format.line_spacing=1;a.paragraph_format.space_after=Pt(5);a.paragraph_format.space_before=Pt(5)
                if len(rows)<=6:a.paragraph_format.keep_with_next=ri<len(t.rows)-1
                for r in a.runs:r.font.name='Arial';r.font.size=Pt(10);r.bold=(ri==0)
            if ri==0:
                sh=OxmlElement('w:shd');sh.set(qn('w:fill'),'E7E7E7');c._tc.get_or_add_tcPr().append(sh)
    if widths:
        for c,w in zip(t.columns,widths):c.width=Inches(w)
    gap=p('',small=True);gap.paragraph_format.space_after=Pt(2);gap.paragraph_format.line_spacing=Pt(2)
    return t

# Monochrome diagrams designed at 200 pixels per inch.
fontpath=Path('C:/Windows/Fonts/arial.ttf')
boldpath=Path('C:/Windows/Fonts/arialbd.ttf')
def diagram(name,size):
    im=Image.new('RGB',size,'white');return im,ImageDraw.Draw(im)
def box(d,rect,text,font=25,fill='white',rounded=False):
    if rounded:d.rounded_rectangle(rect,radius=18,outline='black',width=3,fill=fill)
    else:d.rectangle(rect,outline='black',width=3,fill=fill)
    f=ImageFont.truetype(str(fontpath),font)
    lines=[]
    for line in text.split('\n'):lines+=textwrap.wrap(line,width=max(12,int((rect[2]-rect[0]-30)/(font*.52)))) or ['']
    height=len(lines)*(font+8);y=(rect[1]+rect[3]-height)/2
    for line in lines:
        bb=d.textbbox((0,0),line,font=f);w=bb[2]-bb[0];d.text(((rect[0]+rect[2]-w)/2,y),line,font=f,fill='black');y+=font+8
def arrow(d,start,end,label=None):
    d.line([start,end],fill='black',width=3);a=math.atan2(end[1]-start[1],end[0]-start[0]);l=14
    d.polygon([end,(end[0]-l*math.cos(a-.4),end[1]-l*math.sin(a-.4)),(end[0]-l*math.cos(a+.4),end[1]-l*math.sin(a+.4))],fill='black')
    if label:
        f=ImageFont.truetype(str(fontpath),21);bb=d.textbbox((0,0),label,font=f);x=(start[0]+end[0]-bb[2])/2;y=(start[1]+end[1])/2-30
        d.rectangle((x-3,y-3,x+bb[2]+3,y+25),fill='white');d.text((x,y),label,font=f,fill='black')
def figure(title,im,filename):
    path=WORK/filename;im.save(path,dpi=(200,200))
    a=doc.add_paragraph();a.paragraph_format.keep_with_next=True;a.alignment=WD_ALIGN_PARAGRAPH.CENTER;a.paragraph_format.line_spacing=1
    a.add_run().add_picture(str(path),width=Inches(5.9))
    cap=caption(f'Figure {len(FIGURES)+1}  {title}','f');cap.paragraph_format.keep_with_next=False

# Front matter placeholders receive generated lists after authoring.
logo=Image.open(WORK/'image20.jpeg').crop((350,20,1510,255));logo.save(WORK/'institution-banner.png')
banner=doc.add_paragraph();banner.alignment=WD_ALIGN_PARAGRAPH.CENTER;banner.paragraph_format.line_spacing=1;banner.paragraph_format.space_after=Pt(20);banner.add_run().add_picture(str(WORK/'institution-banner.png'),width=Inches(5.9))
title=doc.add_paragraph('Smart Barangay Portal with QR Profiling and SMS Alerts for Barangay Luna',style='Title');title.alignment=WD_ALIGN_PARAGRAPH.CENTER
title.paragraph_format.space_after=Pt(24)
for text in ['A Capstone Project Manuscript\nPresented to the\nFaculty of the College of Information Technology\nNortheastern Mindanao Colleges','In Partial Fulfillment\nof the Requirements for Capstone Project and Research','By\nCalang, April John B.\nDeloy, Christian\nDeloy, Lorlie D.','October 2026']:
    a=doc.add_paragraph(text);a.alignment=WD_ALIGN_PARAGRAPH.CENTER;a.paragraph_format.line_spacing=2;a.paragraph_format.space_after=Pt(24)
doc.sections[0].different_first_page_header_footer=True
foot=doc.sections[0].footer.paragraphs[0];foot.alignment=WD_ALIGN_PARAGRAPH.CENTER;field(foot,'PAGE')
num=OxmlElement('w:pgNumType');num.set(qn('w:fmt'),'lowerRoman');num.set(qn('w:start'),'1');doc.sections[0]._sectPr.append(num)
doc.add_page_break();a=doc.add_paragraph('TABLE OF CONTENTS',style='Heading 1');a.alignment=WD_ALIGN_PARAGRAPH.CENTER
toc_anchor=doc.add_paragraph('CONTENTS_PLACEHOLDER')
doc.add_page_break();a=heading('ABSTRACT',1);a.alignment=WD_ALIGN_PARAGRAPH.CENTER
rm=mean(vals(resids));tm=mean(vals(teamids))
p(f'The Smart Barangay Portal with QR Profiling and SMS Alerts for Barangay Luna was developed to organize resident records, document requests, document preparation, cash payments, complaints, announcements, and service reports in one web application. Requirements were informed by the April 2026 interviews and preliminary survey documented in the original proposal. The system uses PHP, MySQL, HTML, CSS, and JavaScript in a Laragon development environment. Authorized personnel create resident accounts and maintain profiles; residents can preview and submit requests, monitor their status, and provide feedback. Staff can process online or walk-in applications, generate documents from DOCX templates, record cash payments, and confirm release. QR lookup and configured SMS notifications supplement these processes.')
p(f'A descriptive evaluation was conducted using photographed questionnaires from 10 intended resident users. Three additional questionnaires were completed by project team members testing two staff roles and one administrator role; these were analyzed separately from resident feedback. The resident assessment included 298 valid ratings across 30 items and obtained a pooled mean of {rm:.2f}, interpreted as {interpret(rm).lower()}. The team role assessment obtained a mean of {tm:.2f} from 90 ratings. Automated regression checks supported the implemented request, issuance, payment, template, and interface behavior. The findings support continued pilot refinement, while official staff and administrator evaluation, live gateway verification, and production deployment remain pending. The study does not establish a measured reduction in processing time, security certification, or formal barangay acceptance.')
doc.add_page_break();a=doc.add_paragraph('LIST OF TABLES',style='Heading 1');a.alignment=WD_ALIGN_PARAGRAPH.CENTER;tables_anchor=doc.add_paragraph('TABLES_PLACEHOLDER')
doc.add_page_break();a=doc.add_paragraph('LIST OF FIGURES',style='Heading 1');a.alignment=WD_ALIGN_PARAGRAPH.CENTER;figs_anchor=doc.add_paragraph('FIGURES_PLACEHOLDER')
sec=doc.add_section(WD_SECTION_START.NEW_PAGE);sec.header.is_linked_to_previous=False;sec.footer.is_linked_to_previous=False;sec.different_first_page_header_footer=False
num=sec._sectPr.find(qn('w:pgNumType'))
if num is None:num=OxmlElement('w:pgNumType');sec._sectPr.append(num)
num.set(qn('w:fmt'),'decimal');num.set(qn('w:start'),'1')
foot=sec.footer.paragraphs[0];foot.clear();foot.alignment=WD_ALIGN_PARAGRAPH.CENTER;field(foot,'PAGE')

a=heading('CHAPTER 1',1);a.alignment=WD_ALIGN_PARAGRAPH.CENTER;a=doc.add_paragraph('INTRODUCTION',style='Heading 1');a.alignment=WD_ALIGN_PARAGRAPH.CENTER
p('Barangay services depend on accurate resident information, clear processing steps, and accessible communication. A web portal can support these needs by connecting requests and records without removing the responsibility of authorized personnel to verify applicants, approve documents, collect fees, and release completed certificates. This project develops a portal for Barangay Luna and evaluates its current prototype through resident feedback and documented functional checks.')
heading('1.1 Project Context')
p('The April 15, 2026 interviews provide the local basis for the project. Barangay staff reported preparing certificates through ready-made Microsoft Word documents and saving each completed file. Identity and location details were checked with purok chairpersons and barangay health workers when necessary. Reported difficulties included misspelled information, applicants who did not know their purok or district, and uncertainty about the type of document needed. The staff respondent explicitly reported no record-loss problem at the time of the interview; the study therefore does not treat missing records as an established condition in Barangay Luna.')
p('The barangay captain emphasized resident identification, access to updated information, revenue monitoring, and reports that support planning. The staff interview also raised concerns about privacy and the need for proper authority before implementing QR profiling and digital complaints. These requirements led to a controlled account model, staff-managed resident records, optional QR retrieval, and an integrated document and payment workflow. Existing personal verification remains necessary, particularly for new residents and sensitive documents.')
p('The project addresses fragmentation between individual document files, applicant verification, request follow-up, and reporting. It provides a common record of requests and their statuses rather than assuming that all existing work is paper-based. The portal is a locally developed prototype; operational use in the barangay requires an approved deployment arrangement, designated operators, and further official evaluation.')
heading('1.2 Purpose and Description')
p('The purpose of the system is to support barangay administrative services through a web interface for residents, staff, and an administrator. Authorized staff or the administrator create resident accounts and maintain their verified profile information. A resident uses the assigned account to request a configured document, review a preview, confirm the number of copies, and track the request. The portal also provides announcements, complaint submission, feedback, and access to the resident’s SMS update history.')
p('Staff can receive online requests or encode walk-in applicants who do not have an account. Registered residents may be located by name or by an optional QR scan. Staff review the applicant information and purpose, prepare a document from an active DOCX template, print it, record any required cash payment, and confirm release. Preparation and release are distinct events. A prepared document is not counted as a released certificate, and unpaid fee-bearing documents cannot be released through the normal workflow.')
p('The administrator manages staff access, system settings, document fees, templates, backups, and audit information. Service and collection reports summarize stored transactions. SMS updates depend on the configured gateway, connectivity, and recipient details. Resident QR codes support record lookup, while document QR verification links identify stored document records; neither replaces official identity checks or establishes a government-issued identity credential.')
heading('1.3 Objectives of the Study')
heading('1.3.1 General Objective',3)
p('To develop and evaluate a web-based Smart Barangay Portal that organizes resident profiling, document services, cash payment records, and communication for Barangay Luna.')
heading('1.3.2 Specific Objectives',3)
for text in [
'To implement controlled accounts and resident profiles with optional QR retrieval and role-based access to authorized functions.',
'To provide resident document previews, confirmed request submission, request tracking, and staff processing of online and walk-in applications.',
'To generate printable documents from managed DOCX templates, keep preparation separate from release, and maintain cash payment records linked to document transactions.',
'To provide announcements, complaints, feedback, and configurable SMS notifications with a recorded communication history.',
'To provide service and collection reports, audit records, and administrative backup and account management functions.',
'To verify core workflows through functional regression checks and describe resident perceptions of functionality, usability, reliability, performance, security behavior, and overall satisfaction.']:
    p(text)
heading('1.4 Scope and Limitations')
heading('1.4.1 Scope',3)
p('The implemented scope covers three account roles: administrator, staff, and resident. The administrator and staff maintain the operational records available to their roles. Residents access their own account services after login. The core document service supports configured document types and fees, active templates, requested copies, request statuses, prepared documents, cash payments, and confirmed release. Walk-in issuance supports applicants without requiring the creation of a resident account.')
p('Other implemented modules include resident directory and location records, QR lookup, announcements, complaint tracking, feedback, SMS logs, service reports, collection exports, staff account management, settings, audit logs, and backup functions. QR scanning can use supported browser camera or image input. The availability of cameras, printing, PDF conversion, and SMS delivery depends on the workstation, browser permissions, installed conversion tools, and gateway configuration.')
heading('1.4.2 Limitations',3)
p('The system records cash payments made at the barangay office. Online payment processing and resident payment-proof uploads are outside the current workflow. A request may contain multiple copies of one document type; a single transaction containing several different document types is a future enhancement. QR retrieval does not eliminate the need to verify resident identity, eligibility, or supporting information.')
p('Complaints are recorded and monitored as administrative submissions. The portal is not an emergency response service, a court case management system, or a substitute for barangay dispute procedures. SMS delivery cannot be guaranteed solely from a successful application action. Internet or local network availability, gateway service, browser support, and correct mobile numbers affect access and communication.')
p('The primary user evaluation contains 10 intended resident users. The staff and administrator questionnaires were completed by the project team acting in those roles because an official barangay visit required a letter and a scheduled appointment. Those results describe team role testing rather than independent official acceptance. The small descriptive sample does not represent all residents. No controlled before-and-after timing study, production-scale load test, penetration test, or formal accessibility certification was conducted for this manuscript.')

chapter(2,'Review of Related Literature')
p('This chapter reviews verified sources on digital government, neighborhood administration, barangay portals, and web accessibility. The sources inform the project’s design choices; their reported findings are not treated as results of the Barangay Luna system. The review emphasizes services, identity-related records, communication, and the conditions needed for practical local adoption.')
heading('2.1 Related Literature')
heading('2.1.1 Digital Government and Local Service Delivery',3)
p('The United Nations Department of Economic and Social Affairs (2024) assesses digital government at national and local levels. Its discussion of local portals identifies continuing differences between national and municipal online services. This supports treating local service design as a distinct problem that requires attention to the community being served. For Barangay Luna, the relevant design implication is to organize common resident services and keep information accessible through a practical local portal, rather than assume that national digital progress automatically resolves barangay workflows.')
p('The World Bank’s 2022 GovTech Maturity Index update distinguishes core government systems, online public service delivery, digital citizen engagement, and enabling conditions (World Bank, 2022). These areas suggest that a portal should be evaluated beyond the presence of web pages. In this project, document requests and cash records support service delivery, while feedback and complaints provide channels for resident participation. Training, account administration, connectivity, and maintenance are considered implementation requirements. The global index is contextual literature and is not used to score Barangay Luna.')
heading('2.1.2 QR Supported Neighborhood Administration',3)
p('Hanafiah and Gunadi (2021) describe assistance in using G-Suite and QR codes for neighborhood administrative services in Serang City, Indonesia. Their account links digital tools with consultation, introduction, mentoring, and implementation. The study is relevant because it places QR use within an administrative process rather than treating a code as an independent solution. In the present project, a QR scan retrieves an existing resident record, after which an authorized operator still checks the applicant’s information. Its use is optional so that residents without a readable code can be served through name search or walk-in encoding.')
heading('2.1.3 Usability and Accessibility',3)
p('The World Wide Web Consortium’s Web Content Accessibility Guidelines 2.2 organize accessibility around perceivable, operable, understandable, and robust content (W3C, 2024). Their principles are relevant to readable labels, clear form instructions, keyboard operation, focus behavior, and error identification. The project uses these principles as a design reference for mobile navigation, confirmations, and modal dialogs. However, the questionnaire measures user observations; it does not establish conformance with a WCAG level or replace an accessibility audit.')
heading('2.2 Related Studies')
heading('2.2.1 Foreign Study on Resident Administration',3)
p('Ayuningtyas, Gunawan, Zulfitra, Pratama, and Juniawan (2022) developed a website for resident administration in Gubeng Kertajaya, Surabaya. Their published abstract describes online resident submissions and review by neighborhood officers, with black-box testing and user trials. The study provides a relevant example of linking resident requests to authorized review. Barangay Luna follows this separation through resident submission and staff processing, while adding a document preparation and cash release workflow suited to the current project.')
heading('2.2.2 Local Study on Document Requests',3)
p('Taruc, Martin, Olipas, and Alegado (2023) developed and assessed Docu-Go, a web-based barangay document requesting system in Cabanatuan City. They used a developmental research design and included residents and IT experts in the evaluation. Their abstract reports generally acceptable assessments and recommendations for improving functionality and user experience. The study supports the value of evaluating a document portal through more than one perspective. The present study separates feedback from intended residents and project team role testers because actual barangay staff and administrator evaluation had not been completed.')
heading('2.2.3 Local Study on Service Analytics',3)
p('Pastoril (2023) developed the Unified Barangay E-Services System with Augmented Analytics for Barangay Batasan Hills. The university repository describes a platform that combines online services with analysis of the data generated by those services. This provides a basis for considering reports alongside transaction functions. The Barangay Luna portal similarly summarizes document activity and collections. Its reports are descriptive views of stored records; the project does not claim to implement the personalized recommendations described in the related work.')
heading('2.3 Related Systems')
p('Docu-Go, the Gubeng Kertajaya administration website, and the Unified Barangay E-Services System provide documented reference systems for request handling, resident submissions, and reporting. They are compared here only on capabilities stated in their published descriptions. Absence of a feature from an abstract does not establish that the other system lacks it. The Barangay Luna column is based on the current implementation reviewed for this manuscript.')
table('Relationship of documented systems to the present project',['System','Documented emphasis','Relevance to Barangay Luna'],[
['Docu-Go','Web-based barangay document requests and assessment by residents and IT experts.','Request submission and separate user and technical evaluation.'],
['Gubeng Kertajaya website','Resident administration with neighborhood officer review.','Applicant information and authorized request processing.'],
['Unified Barangay E-Services','Online barangay services and service-data analytics.','Integrated service records and descriptive reports.'],
['Smart Barangay Portal','Staff-managed accounts, online and walk-in issuance, templates, cash ledger, QR lookup and configured SMS.','A workflow tailored to the documented local requirements.']],[1.25,2.15,2.55])
heading('2.4 Synthesis of the Study')
p('The reviewed literature supports bringing resident transactions, authorized administrative actions, and service information into a common digital process. It also shows that adoption depends on organizational support and user experience. The April interviews supply the local requirements that broader literature cannot determine, including the use of Word templates, verification through local personnel, and concerns about new resident information and privacy.')
p('The project’s contribution is the implementation of these local requirements in a single prototype. Resident request previews, explicit confirmation of copies, optional QR retrieval, template management, payment controls, and release-based reports work together as parts of the document service. This is a practical contribution to the case being studied, not a claim that no other barangay portal has similar features.')
p('The evaluation approach reflects the same distinction between a working application and operational adoption. Regression checks provide evidence about selected behaviors of the current code. Resident questionnaires describe perceived use of the prototype. Project team role questionnaires provide supplementary internal feedback. Independent official evaluation, gateway checks, and sustained use are still needed before concluding that the portal has improved barangay-wide operations.')

chapter(3,'Technical Background and Methodology')
p('This chapter describes the current implementation, its development approach, and the procedures used to evaluate it. The workflow has been updated to reflect staff-created accounts, online and walk-in applications, document preview and preparation, cash payment, and confirmed release. The diagrams describe implemented data movement at a logical level and identify where human verification remains part of the process.')
heading('3.1 Conceptual Framework')
p('The input-process-output framework connects the requirements and data used by the portal to its administrative functions and resulting records. Inputs include interview requirements, resident information, applicant details, requests, document templates, fee settings, and user actions. Processes apply validation, authorization, review, document generation, payment recording, release confirmation, and communication. Outputs include searchable profiles, request statuses, printable certificates, cash records, notices, and reports.')
im,d=diagram('ipo',(1200,620))
box(d,(15,80,355,560),'INPUT\nInterview requirements\nResident and applicant details\nRequests and complaints\nDOCX templates and fees\nAuthorized user actions',24)
box(d,(430,80,770,560),'PROCESS\nValidate and authorize\nReview and prepare\nRecord cash payment\nConfirm document release\nNotify and summarize',24,fill='#eeeeee')
box(d,(845,80,1185,560),'OUTPUT\nResident profiles and QR\nRequest status history\nPrintable documents\nCollection and service reports\nSMS and audit records',24)
arrow(d,(355,320),(430,320));arrow(d,(770,320),(845,320));figure('Input process output framework',im,'fig-ipo.png')
p('Feedback from evaluation leads to revisions of the forms, confirmations, templates, and interface behavior. The framework describes how the system is intended to support organized service delivery; it does not by itself prove faster processing or greater security.')
heading('3.2 Research and Development Methodology')
p('The project combines developmental work with a descriptive user evaluation. Rapid Application Development (RAD) is used as the organizing approach because screens and workflows were revised through repeated implementation and checking. The completed work covers requirements planning, user design, construction, and prototype testing. Cutover is treated as a future controlled deployment stage because official operational acceptance is not yet documented.')
heading('3.2.1 Requirements Planning',3)
p('Requirements were derived from the captain and staff interviews dated April 15, 2026 and the preliminary resident questionnaire retained in the appendices. The interviews identified template-based document work, local verification of identity and location, and a need for accessible reports. The planning stage translated these concerns into controlled accounts, searchable resident records, online request tracking, document templates, cash records, communication, and role restrictions. Interview expectations were treated as requirements rather than proof that the finished portal would achieve a particular improvement.')
heading('3.2.2 User Design',3)
p('The user design stage organized screens around the three roles. Resident screens support viewing profile-related information, requesting documents, checking progress, and submitting complaints or feedback. Staff screens support profile encoding, request review, document preparation, payments, and reports. Administrator screens add account, configuration, and system oversight functions. Design revisions addressed mobile layout, clear labels, modal visibility, document previews, and confirmation of quantities before submission.')
heading('3.2.3 Construction',3)
p('Construction used PHP for server processing, MySQL for persistent records, and HTML, CSS, and JavaScript for the browser interface. The code separates role views, shared functions, action endpoints, and client scripts. Server-side checks govern access, validation, request ownership, cash collection, and release. JavaScript supports form behavior, modals, previews, and periodic updates. Templates remain DOCX files so authorized personnel can maintain certificate wording using familiar document tools.')
heading('3.2.4 Testing and Cutover Planning',3)
p('The current build was checked with automated PHP and JavaScript regression scripts and assessed through the supplied questionnaires. Development fixes were rechecked in the current implementation. The evaluation is described in Chapter 4. Production cutover remains a plan: obtain official authorization, configure a reachable deployment address and gateway, review the templates and fees with designated operators, validate data and backups, train personnel, and complete official staff and administrator task testing before expanding use.')
heading('3.3 System Requirements')
p('The hardware specifications in the proposal are retained as a practical development baseline, not as benchmarked minimum requirements. Production requirements depend on the number of concurrent users, document conversion workload, storage growth, and deployment arrangement. A supported operating system and regular maintenance are required for the operational setup.')
table('Hardware and operational requirements',['Component','Development baseline or purpose'],[
['Processor','Intel Core i3 or higher as specified in the proposal; confirm capacity during deployment testing.'],['Memory','8 GB development baseline.'],['Storage','256 GB SSD baseline; reserve capacity for templates, attachments and backups.'],['Resident device','Browser-capable cellphone or computer with network access.'],['Office peripherals','Printer for certificates; camera or image input when QR scanning is used.'],['Network and SMS','Reachable server and reliable connection; configured gateway and valid mobile numbers for SMS.']],[1.35,4.6])
table('Software used by the current system',['Software or technology','Purpose'],[
['Laragon and web server','Local development and application hosting.'],['PHP','Authentication, validation, database actions, document workflow and reports.'],['MySQL','Accounts, requests, documents, payments and other persistent records.'],['HTML and CSS','Role-based pages, forms, tables and responsive presentation.'],['JavaScript','Form interactions, previews, modals, QR input and periodic refresh.'],['Web browser','Resident and staff access; camera features depend on permissions and secure context.'],['DOCX and PDF tools','Managed document templates and printable previews; conversion depends on a working configured converter.']],[1.35,4.6])
heading('3.4 System Architecture')
p('The system follows a browser-server-database arrangement. The browser displays the role interface and sends authenticated requests. PHP checks session access and input, applies the relevant workflow, and reads or updates MySQL. Uploaded templates and generated files are stored through the application’s file facilities. The SMS gateway is an external communication component, so the database records the application’s sending outcome independently of document preparation or release.')
im,d=diagram('architecture',(1200,800))
box(d,(160,25,1040,175),'PRESENTATION\nResident cellphone or computer  |  Staff and administrator browsers',27)
box(d,(160,260,1040,470),'APPLICATION\nPHP authentication and role checks\nRequest review, templates, payment, release, reports\nJavaScript interface and periodic data refresh',27,fill='#eeeeee')
box(d,(35,575,590,755),'PERSISTENCE\nMySQL records\nTemplates, attachments and backups',25)
box(d,(650,575,1165,755),'EXTERNAL SERVICES\nConfigured SMS gateway\nDocument converter and printer',25)
arrow(d,(600,175),(600,260),'Requests and responses');arrow(d,(355,470),(355,575),'Read and write');arrow(d,(890,470),(890,575),'Send or generate');figure('Architecture of the implemented portal',im,'fig-architecture.png')
p('A localhost installation is suitable for development but does not make QR verification links or resident pages reachable from arbitrary phones. Operational use requires an approved network or hosted address, appropriate access configuration, and a working document conversion and SMS setup. Browser updates do not guarantee immediate SMS delivery or continuous availability.')
heading('3.5 Current Barangay Workflow')
p('The existing workflow is reconstructed from the April staff interview rather than assumed from a generic manual system. A resident approaches the barangay, states the needed certificate, and supplies information. Staff clarify the document type and confirm identity or location through local personnel when needed. Staff fill a ready-made Word document, print the certificate, and save the completed document. Complaints are handled through personal discussion with notes by the responsible personnel. The interview does not establish a specific existing cash ledger or a measured average service time.')
im,d=diagram('existing',(1200,1080))
steps=['Resident states the required service and supplies details','Staff clarify the certificate type and check the information','When needed, consult purok chairperson or barangay health worker','Fill the ready-made Microsoft Word document','Print the certificate and save the completed file']
for i,text in enumerate(steps):
    y=30+i*195;box(d,(130,y,1070,y+140),text,29)
    if i<4:arrow(d,(600,y+140),(600,y+195))
figure('Existing certificate workflow from the staff interview',im,'fig-existing.png')
heading('3.6 Implemented System Workflow')
heading('3.6.1 Account and Resident Profiling',3)
p('An authorized staff member or administrator creates the resident account and encodes the information required for the profile. The operator checks identity and location information according to barangay procedures. The system stores the account and profile fields in the users record and associated location and requirement records. Successful staff-assisted registration marks the account Active, the profile Verified, and the QR Active. A resident cannot create a new account through a public self-registration workflow.')
p('QR information is linked to the existing resident record. Staff can retrieve a profile through name search or supported QR input, but they must still confirm that the displayed record belongs to the applicant. New or unregistered walk-in applicants can be served through manual applicant encoding. The system therefore supports both registered resident access and office-assisted service.')
heading('3.6.2 Resident Online Request',3)
p('The resident logs in, chooses a configured document type, supplies the purpose and any document-specific information, and reviews a preview. Profile-derived information is filled from the account. The preview is for checking content and does not create a released certificate. Before submission, the resident reviews the request and confirms the number of copies. Large quantities receive an additional warning and acknowledgement. The server rejects a submission whose reviewed quantity has been changed without renewed confirmation.')
p('A submitted request is stored as Pending. The resident can inspect its current status and available remarks. An owner may remove a request only while it remains Pending and has no prepared issuance protecting it. A complaint may similarly be removed only while it is the resident’s own Open submission. These removals preserve audit information. Requests or complaints already handled by staff are protected from this resident action.')
heading('3.6.3 Staff Processing and Document Preparation',3)
p('Authenticated staff and administrator request views retrieve new or changed requests through periodic polling, ordinarily every five seconds while the page is active. The list is refreshed without discarding text being entered in an open rejection form. This is periodic synchronization rather than a guarantee of instantaneous updates. Network and page state affect the time at which a new request becomes visible.')
p('Staff open the application, check the applicant and required details, and move the request through the appropriate processing states. Rejection requires a reason. For an online application, the issuance form can load the selected request; for a walk-in applicant, staff can select a registered resident or enter unregistered applicant details. Staff choose an active template, check the filled document and fee, and prepare the printable certificate. Preparation creates a Ready for Pickup record and a pending payment entry when a fee is due. Printing or previewing does not confirm release.')
p('The supported online request statuses are Pending, Processing, Approved, Ready for Pickup, Released, and Rejected. The current successful sequence is Pending → Processing → Ready for Pickup → Released. The staff preparation action moves an eligible request to Processing; preparing its printable document moves it to Ready for Pickup; Confirm Release records completed handover. Approved is also supported as a state from which preparation can restart, including after removal of the only valid unreleased preparation. Rejected records a refused application and its reason.')
heading('3.6.4 Cash Payment and Confirmed Release',3)
p('The payable amount is based on the configured unit fee and the number of copies. Cash is collected at the office. Staff record the amount and payment details, then use Confirm Release. A fee-bearing document cannot be released with an incomplete payment. For a free document, the workflow does not require a cash payment entry. The release action records the actual release and synchronizes the linked online request. Repeated release does not create another collection.')
p('Prepared documents remain in a queue so staff can reopen them without generating duplicate records. An unreleased preparation may be removed when it is no longer needed; its unpaid pending payment is voided, while an already collected payment is retained. A released document cannot be removed through that preparation action. When a linked online request has no other valid preparation, it becomes available for preparation again. Reports count released documents and recorded paid collections according to their relevant dates.')
im,d=diagram('issue',(1200,1360))
box(d,(25,20,565,190),'ONLINE\nResident logs in, previews and confirms request\nPending request reviewed by staff',25)
box(d,(635,20,1175,190),'WALK IN\nStaff search a resident or enter applicant details\nNo account required for an unregistered applicant',25)
arrow(d,(300,190),(600,260));arrow(d,(900,190),(600,260))
flow=['Validate applicant and details; choose active DOCX template','Preview and prepare printable document\nReady for Pickup; pending fee when applicable','Print and arrange applicant pickup\nPrepared record can be resumed','Fee due? Record complete cash payment\nFree document: no payment entry required','Confirm Release\nRecord release date and synchronize online request','Update released-document reports, paid collections and audit records\nSend configured notification if enabled']
for i,text in enumerate(flow):
    y=260+i*180;box(d,(120,y,1080,y+125),text,26,fill='#eeeeee' if i==4 else 'white')
    if i<5:arrow(d,(600,y+125),(600,y+180))
figure('Online and walk-in document preparation payment and release',im,'fig-issuance.png')
p('If validation or payment fails, the action returns an error and the document is not released. The displayed sequence represents the successful path; rejected requests and removed preparations are retained as non-released outcomes. SMS failure does not reverse a valid document transaction.')
heading('3.6.5 Templates and Document Verification',3)
p('Authorized operators upload and maintain DOCX templates for a document type and year. Saving a replacement for the same type and year replaces the active file rather than presenting another duplicate in the saved list. The list displays uploaded templates for inspection. A new document year remains a separate template entry. Template-based values include the resident’s information, purpose, control number, date, and document-specific details.')
p('Where a certificate identifies a parent and a child, the parent’s pronoun is based on the parent’s stored gender. The beneficiary relationship, such as son or daughter, is kept separate. This prevents a child’s relationship selection from changing the parent’s pronoun. A document verification QR links to the configured verification page for the stored issuance record. The verification view must be interpreted with the record’s status and configured access rules; it is not a security certificate or a substitute for an authorized signature.')
heading('3.6.6 Communication Complaints and Reporting',3)
p('The announcement module publishes community information. Residents can submit a complaint and staff can track it through Open, In Progress, Resolved, or Closed as appropriate. Feedback stores resident service observations. SMS functions use configured message and gateway settings; the system records Pending, Sent, or Failed application outcomes. Residents view their own update history. Gateway responses and message logs are useful operational evidence but do not prove that every recipient read a message.')
p('The reporting functions summarize document activity, elapsed request periods, and cash collections. A request-to-release duration can include waiting for payment and pickup, overnight periods, or other delays, so it should not be interpreted solely as staff working time. Revenue exports are based on recorded paid collections. Audit logs support traceability of administrative actions, and backups support planned recovery.')
heading('3.7 Process and Data Design')
heading('3.7.1 Context Diagram',3)
p('The context diagram treats the portal as one process. Residents provide account credentials, requests, complaints, and feedback and receive statuses and service information. Staff supply checked applicant details, templates, document actions, and cash records and receive work queues and reports. The administrator provides account and configuration actions and receives oversight information. The gateway receives outbound SMS requests and returns application-level sending responses.')
im,d=diagram('context',(1200,890))
box(d,(360,285,840,565),'0\nSmart Barangay Portal\nwith QR Profiling\nand SMS Alerts',30,fill='#eeeeee',rounded=True)
box(d,(25,20,570,185),'RESIDENT\nIN: Credentials, requests, complaints, feedback\nOUT: Profile, previews, status, announcements',23)
box(d,(630,20,1175,185),'STAFF\nIN: Applicant checks, templates, cash, actions\nOUT: Queue, printable documents, reports',23)
box(d,(25,695,570,860),'ADMINISTRATOR\nIN: Accounts, fees, settings, backup actions\nOUT: Audit, collection and service reports',23)
box(d,(630,695,1175,860),'SMS GATEWAY\nOUT from portal: Message and recipient\nIN to portal: Sending response',23)
arrow(d,(300,185),(480,285));arrow(d,(720,285),(900,185));arrow(d,(900,185),(730,285));arrow(d,(480,565),(300,695));arrow(d,(300,695),(490,565));arrow(d,(740,565),(900,695));arrow(d,(900,695),(730,565));arrow(d,(480,285),(300,185))
figure('Logical context diagram with input and output descriptions',im,'fig-context.png')
heading('3.7.2 Level One Data Flow',3)
p('The level one model divides the portal into five logical processes. The grouped stores in the diagram correspond to actual database tables and file facilities; the grouping is for readability, not an assertion that each group is a single physical table. Each process receives authorized data, applies checks, and produces stored records or a response. A double-direction connection denotes reads and writes whose individual flows are detailed in Table 4.')
im,d=diagram('dfd',(1200,1090))
flows=[('Resident / staff / administrator','1.0\nAccount and profile management','D1\nusers and resident location / requirement records'),('Resident / staff / administrator','2.0\nRequest review and document preparation','D2\ndocument_requests, document_templates, issued_documents'),('Staff / administrator','3.0\nCash recording and confirmed release','D3\ndocument_payments and issued_documents'),('Resident / staff / administrator / gateway','4.0\nCommunity communication','D4\nannouncements, complaints, feedbacks, sms_logs'),('Staff / administrator','5.0\nReports and system administration','D1–D4 read\nD5 audit_logs, backups, system_settings')]
for i,(entity,process,store) in enumerate(flows):
    y=20+i*210;box(d,(10,y+35,330,y+155),entity,24);box(d,(405,y,795,y+190),process,28,fill='#eeeeee',rounded=True);box(d,(870,y,1190,y+190),store,23)
    arrow(d,(330,y+75),(405,y+75));arrow(d,(405,y+125),(330,y+125));arrow(d,(795,y+75),(870,y+75));arrow(d,(870,y+125),(795,y+125))
figure('Level one logical data flow with grouped data stores',im,'fig-dfd.png')
table('Detailed flows supporting the level one model',['Process','Incoming or read data','Outgoing or written data'],[
['1.0 Accounts and profiles','Credentials; operator-checked identity and location; existing user record.','Account and profile changes; QR-related record; authorized profile view.'],
['2.0 Requests and preparation','Resident request and confirmation; template and fee; staff review or rejection reason.','Request status and remarks; filled preview; prepared issuance and verification reference.'],
['3.0 Payment and release','Prepared issuance; due amount and copies; cash details; release action.','Paid or voided payment outcome; released issuance and request; audit entry.'],
['4.0 Communication','Complaint or feedback; operator update; announcement; notification event; gateway response.','Community records; visible status; outbound SMS and stored sending result.'],
['5.0 Reporting and administration','Operational records; date filters; account and setting actions; backup request.','Service and collection summaries; export; settings and backup records; audit information.']],[1.35,2.3,2.3])
heading('3.7.3 Data Storage and Relationships',3)
p('The users table contains account and resident profile fields. A document request belongs to a resident user; issued documents may reference a resident or retain manually encoded applicant details for a walk-in transaction. Issuance links to request and document identifiers where applicable. Payment records carry the collection status and relevant transaction reference. This arrangement allows registered online requests and unregistered walk-in services to share the issuance and reporting workflow.')
table('Core stored records in the current schema',['Record group','Main tables or files','Purpose'],[
['Access and profile','users, roles, resident_districts, resident_puroks, resident_requirements','Account access, resident information, location and requirement records.'],['Document service','document_requests, document_templates, document_fees, issued_documents; DOCX files','Requests, template references, configured fees, preparation and release.'],['Cash collections','document_payments','Pending, paid and voided payment records linked to services.'],['Communication','announcements, complaints, feedbacks, sms_logs','Community information, concerns, feedback and sending outcomes.'],['Traceability and administration','qr_scan_logs, audit_logs, backups, system_settings, notification_seen_state','Lookup history, actions, configuration, recovery records and notification state.']],[1.2,2.55,2.2])
heading('3.8 Service Fees and Deployment Resources')
p('The current revenue function records service collections from document transactions; it is not a commercial subscription billing module. Authorized personnel configure document fees, and staff collect cash at the office. Only recorded paid collections belong in collection totals. Removed unpaid preparations are excluded from paid revenue, while previously collected payments remain traceable.')
p('Hosting, connectivity, SMS charges, printer supplies, training, and maintenance are potential deployment expenses. Their actual costs require an approved arrangement and quotations. The proposal’s illustrative subscription, customization, and training prices are not treated as implemented barangay income or committed project charges.')
heading('3.9 Evaluation Procedure and Data Analysis')
p('The supplied evaluation questionnaire contains 30 statements in six dimensions, with five items per dimension. Participants choose one response from 1 to 5, or N/A for a feature not used or observed. The dimensions are functionality, usability, reliability, performance, observed security behavior, and overall satisfaction. English and Cebuano versions use the same item codes and scale. The instrument is a project questionnaire and is not claimed to be an independently validated standardized test.')
p('Ten intended resident users completed resident questionnaires. Two staff-role questionnaires and one administrator-role questionnaire were completed by project team members acting in those roles. Because an official visit required a letter and scheduling, the role testing was conducted by the team before the manuscript deadline. Resident results form the primary user evaluation; team results are reported separately. The historical April interviews with actual barangay personnel remain requirements evidence and are not relabelled as current acceptance testing.')
p('The photographs were grouped by the numbered resident cover pages and adjoining rating pages. The 46 supplied images contain 39 rating pages, six comment pages, and one duplicate image. The duplicate reliability/performance photograph was counted once. R10 marked C1 and E5 as N/A. Enlarged inspection confirmed the faint response marks for R04 E1–E3 and R08 E1 in column 4. All remaining rating items were assigned a visible selected response. N/A values were excluded rather than converted to zero.')
p('For each item, the weighted mean is the sum of the rating multiplied by its frequency, divided by the number of valid responses: WM = (5f5 + 4f4 + 3f3 + 2f2 + f1) / n. Dimension and overall means pool all valid item responses within the stated group. They are not averages of already rounded item means. Counts and means are presented descriptively; no significance test or population-level estimate is made. Classifications use the unrounded mean and the bands in Table 6.')
table('Interpretation of rating means',['Mean interval','Interpretation'],[['4.50–5.00','Strongly agree'],['3.50–less than 4.50','Agree'],['2.50–less than 3.50','Neutral'],['1.50–less than 2.50','Disagree'],['1.00–less than 1.50','Strongly disagree']],[2.3,3.65])
p('Security ratings describe behavior observed by users, such as login restrictions and hidden passwords. They do not measure encryption strength, attack resistance, or complete technical security. Comments were transcribed or translated by meaning and grouped into interface, document wording, and transaction suggestions. These observations guide improvements but do not establish that each reported issue remains in the current build.')

chapter(4,'Results and Discussion')
p('This chapter presents the implemented system, verified regression checks, and the descriptive questionnaire results. Resident feedback is the primary user evidence. The three project team role questionnaires are supplementary internal assessment and are shown separately. Development outcomes, test cases, user perceptions, operational readiness, implementation steps, and risks are discussed in relation to the current prototype.')
heading('4.1 Development and Testing')
p('The resulting prototype integrates resident information, document requests, template-based preparation, cash payment, and confirmed release. Development revisions focused on matching the office workflow and reducing avoidable interface mistakes. The current code preserves the separation between a preview, a prepared printable document, and a released document. The request list updates periodically so authorized operators can see new submissions while preserving an open rejection reason being entered.')
p('Regression verification for this manuscript was completed on October 13, 2026. The PHP database tests used temporary isolated databases and removed their fixtures after completion. The JavaScript tests simulated the relevant interface behavior. These checks validate selected scenarios in the current build; they are distinct from a full office deployment, live SMS delivery, or a production capacity test.')
heading('4.2 System Modules and Features')
table('Implemented modules and resulting behavior',['Module','Current behavior'],[
['Accounts and resident profiles','Authorized account creation; resident directory, location information and role-controlled views.'],['QR lookup and verification','Optional resident retrieval and document verification references; human verification remains necessary.'],['Resident requests','Autofilled profile information, document preview, reviewed copies and Pending request tracking.'],['Staff document service','Online request selection and walk-in encoding; managed DOCX templates and printable preparation.'],['Cash payment and release','Fee based on copies; payment record; explicit release; protected released records.'],['Communication','Announcements, complaints, feedback and configured SMS history.'],['Reports and oversight','Released-document counts, paid collections, exports, audit information, settings and backups.']],[1.4,4.55])
p('These modules operate as connected services. For example, a request generates a work item, preparation creates an issuance record, payment establishes the collection state, and release changes the record counted by reports. Combining the modules is useful only if these transitions remain consistent. The regression tests therefore emphasize state changes and duplicate prevention as well as the presence of screens.')
heading('4.3 System Testing Procedures')
heading('4.3.1 Function and Integration Checks',3)
p('Function-level checks examined template values and the separate handling of parent gender and beneficiary relationship. Database integration checks examined request ownership, review confirmation, template replacement, payment and release, request feeds, and collection exports. Rejection paths were included, such as incomplete payment, missing confirmation, changing copies after review, and resident access to another user’s data.')
heading('4.3.2 Interface Regression Checks',3)
p('JavaScript regression checks examined new request rows during editing, safe status refresh, network recovery, account isolation, explicit acknowledgement of a high copy count, dashboard synchronization, and reopening a prepared document without duplicate generation. Modal layering was checked separately to ensure the dialog is attached at the body level, receives focus, and retains entered values. These are simulated regression checks and should be complemented by task observation with office users.')
heading('4.3.3 Performance and Communication Checks',3)
p('The questionnaire records whether users perceived loading and responses as acceptable. It does not supply elapsed response measurements. The default request refresh interval is approximately five seconds while active, but this interval is not a measured page load time. No concurrency benchmark or production network test is reported. The issuance test confirmed that disabled SMS does not undo a completed document transaction; no external messages were sent during the isolated test. Live gateway delivery and failure recovery require further operational testing.')
heading('4.4 Test Cases and Corrective Actions')
table('Verified regression scenarios in the current build',['Test area','Verified scenario and outcome','Result'],[
['Issuance and payment','Unpaid fee-bearing release rejected; paid and free releases recorded correctly; repeated release did not duplicate collections.','Passed'],['Prepared records','Unreleased preparation can be removed; unpaid entry voided; collected payment retained; released record protected.','Passed'],['Template replacement','Repeated saves replaced the same type/year; new years stayed separate; empty upload retained the file; saved list was read-only.','Passed'],['Submission and ownership','Own Pending request or Open complaint removal allowed; handled records and other users’ records protected; CSRF and review checks enforced.','Passed'],['Request feed','Staff/admin received the authenticated feed; new, changed and removed requests appeared; resident feed access denied.','Passed'],['Live request list','New rows and status changes refreshed safely; editing text remained; account isolation and connection recovery verified.','Passed'],['Quantity review','20-copy request required a warning acknowledgement and submitted the reviewed quantity once.','Passed'],['Dashboard and popup','Overview refresh and prepared-popup resumption preserved state and avoided duplicate generation.','Passed'],['Revenue export','Date ranges, totals, amounts, receipt identifiers, Unicode, formula safety and empty periods verified.','Passed'],['Parent pronouns','Female and male parent pronouns stayed tied to parent gender for son and daughter selections.','Passed'],['Modal positioning','Dialog opened at body level, received focus and retained input values.','Passed']],[1.05,4.25,.65])
p('Passed means that the named regression script completed without an assertion failure in the verification run. It does not mean every possible input, browser, network condition, or security scenario has been tested. The test filenames and their scope are retained in Appendix F so that the checks can be reproduced.')
table('Reported development issues and current disposition',['Reported issue','Current correction or disposition','Evidence or next step'],[
['Saved templates and dialogs behind header/sidebar','Template replacement and body-level modal positioning were implemented.','Replacement and modal regression checks passed; repeat in official user tasks.'],['Child relationship affected parent pronoun','Parent gender and beneficiary relationship now use separate values.','All four parent/relationship combinations passed.'],['Forms cover work or some actions are confusing','Prepared-popup state and interface behavior were revised.','Popup regression passed; overall clarity still needs independent task observation.'],['Repeated requests for different documents','Multiple copies of one type are supported; different document types in one transaction remain a proposed enhancement.','Gather requirements and design a grouped transaction before implementation.']],[1.6,2.35,2])
p('The issue table records observations and their disposition, not a numerical failure rate. The supplied photographs do not provide dated pre-fix and post-fix questionnaires or controlled retest results. It would therefore be inaccurate to present a percentage reduction in defects or a measured increase in satisfaction after the corrections.')
heading('4.5 Results and Discussion of the Implemented Workflow')
p('The tests support the consistency of the main document lifecycle. Preparation does not add to released-document counts; Confirm Release records the actual release and synchronizes linked requests. The payment checks prevent a fee-bearing release without sufficient recorded payment. Removing an unpaid preparation excludes it from collections while retaining paid records. These outcomes support the correctness of selected accounting and state transitions in the prototype.')
p('The request synchronization checks show that updated work items can be displayed while staff continue editing a rejection reason. Template checks reduce duplicate active template entries for the same type and year. Pronoun tests confirm that beneficiary choices do not overwrite a parent’s profile-derived gender. These verified behaviors respond to practical feedback, but they cannot establish the frequency of such errors in normal barangay operations.')
heading('4.6 User Evaluation Results')
heading('4.6.1 Evaluator Composition and Data Quality',3)
table('Composition of the current evaluation',['Group','Questionnaires','Actual participation','Use in analysis'],[['Resident','10','Intended resident users','Primary descriptive user assessment'],['Staff role','2','Project team members acting as staff','Supplementary internal role assessment'],['Administrator role','1','Project team member acting as administrator','Supplementary internal role assessment'],['Total','13','10 intended users and 3 team role assessments','Groups kept separate']],[1.15,.9,2,1.9])
p('Nine resident questionnaires list a cellphone and one lists a computer. All three team role questionnaires list a computer. Age and gender statistics are not reported for this evaluation because the uploaded forms do not provide a complete demographic dataset. The earlier proposal’s gender counts belong to a separate preliminary survey and are not transferred to the current sample.')
p('The resident dataset contains 300 possible item responses, of which 298 are valid numeric ratings and two are N/A. C1 and E5 therefore have nine resident ratings each; all other resident items have ten. The team dataset contains 90 valid ratings. The duplicate photograph was excluded before tallying. The item and evaluator audit tables are retained in Appendix E.')
heading('4.6.2 Resident Dimension Results',3)
table('Primary resident assessment by dimension',['Dimension','Valid ratings','Mean','Interpretation'],[[name,len(vals(resids,d)),f'{mean(vals(resids,d)):.2f}',interpret(mean(vals(resids,d)))] for d,name in enumerate(dims)]+[['Overall pooled assessment',len(vals(resids)),f'{rm:.2f}',interpret(rm)]],[2.05,.9,.65,2.35])
for d,name in enumerate(dims):
    v=mean(vals(resids,d))
    sentences=[
        'Residents generally agreed that available features supported their required tasks. The strongest functionality responses concerned correct information and available services, while completion and status information still merit observation in real transactions.',
        'Usability responses were favorable, but individual neutral ratings show that navigation and device layout were not equally clear to everyone. This is consistent with comments about forms and confusing functions and supports further task-based review.',
        'Reliability responses describe resident experience with saved information, repeated tasks, and correction messages. One resident marked unexpected-error behavior as N/A. These observations do not prove uninterrupted operation or replace recovery testing.',
        'Performance reflects perceived response speed. Page-loading acceptability received lower ratings than several other timing items. The result identifies a useful focus for measured testing on real phones and the planned network.',
        'Observed security behavior received favorable ratings for access and password-related interactions. One current-password item was marked N/A. This dimension cannot establish encryption quality, resistance to attacks, or complete privacy compliance.',
        'Overall satisfaction was favorable and included willingness to use the portal again. Recommendation ratings were lower than several other satisfaction items, indicating that willingness to recommend should be discussed separately from general satisfaction.'
    ]
    p(f'{name} obtained a mean of {v:.2f} ({interpret(v).lower()}). {sentences[d]}')
heading('4.6.3 Resident Item Results',3)
for d,name in enumerate(dims):
    rows=[]
    for j,text in enumerate(items[d]):
        a=vals(resids,d,j);c=Counter(a)
        rows.append([f'{"ABCDEF"[d]}{j+1}',text,len(a),f'{mean(a):.2f}',interpret(mean(a))])
    table(f'Resident item means for {name.lower()}',['Item','Statement','n','Mean','Interpretation'],rows,[.45,3.3,.3,.5,1.4])
lowest=min([(mean(vals(resids,d,j)),f'{"ABCDEF"[d]}{j+1}',items[d][j]) for d in range(6) for j in range(5)])
p(f'The lowest resident item mean was {lowest[1]} at {lowest[0]:.2f}. This identifies a priority for further examination, not evidence of a measured operational failure. Item means should be interpreted alongside the small sample, the actual features used, and the comments. A favorable pooled mean does not remove the need to address specific neutral responses.')
heading('4.6.4 Supplementary Team Role Assessment',3)
table('Team role assessment reported separately',['Dimension','Staff role mean n 2','Admin role mean n 1','Team pooled mean'],[[name,f'{mean(vals(["TS1","TS2"],d)):.2f}',f'{mean(vals(["TA1"],d)):.2f}',f'{mean(vals(teamids,d)):.2f}'] for d,name in enumerate(dims)]+[['Overall',f'{mean(vals(["TS1","TS2"])):.2f}',f'{mean(vals(["TA1"])):.2f}',f'{tm:.2f}']],[2.05,1.3,1.3,1.3])
p(f'The team role assessment obtained a pooled mean of {tm:.2f}, interpreted as {interpret(tm).lower()}, from 90 ratings. This indicates favorable internal perceptions during staff and administrator role testing. Because these questionnaires were completed by project team members, the values are not independent confirmation by barangay personnel. They are not merged with the primary resident mean and cannot be used as official acceptance or as a comparison of occupational groups.')
heading('4.6.5 Comments and Suggestions',3)
table('Comments transcribed or translated from the supplied pages',['Source','Observation','Suggested action'],[['TS1 team staff role','Saved template or popup placement affected visibility around the sidebar and header.','Correct errors and check dialog positioning.'],['TS2 team staff role','No problem reported on the supplied comment page.','No suggestion reported.'],['TA1 team administrator role','Indigency document wording associated with a child affected parent gender or pronoun.','Correct the document wording behavior.'],['R04 resident','None reported.','None reported.'],['R03 resident','Some forms covered the work and some functions were confusing.','Fix errors and improve clarity.'],['R02 resident','“Wala” meaning none.','Allow several different documents in one transaction to avoid repeated requests.']],[1.05,2.75,2.15])
p('Only six comment pages were supplied. The absence of a comment page for another questionnaire is not counted as “no problem.” Cebuano comments are translated by meaning rather than presented as verbatim English statements. The source images remain the basis for the transcription. The different-document transaction suggestion is retained as future work because the current copy selector applies to one document type.')
heading('4.7 User Acceptance and Operational Readiness')
p('The resident ratings support a favorable initial assessment of the prototype within the participating group. They do not constitute formal user acceptance testing by the barangay. Actual staff and administrator acceptance remains pending the required letter and scheduled visit. The historical April interviews demonstrate requirements consultation, not acceptance of the present build. No signed acceptance form or official approval is inferred from either dataset.')
p('The next official evaluation should use concrete tasks: create and verify a resident account, find a profile, review a request, reject it with a reason, prepare a certificate, check parent and child wording, collect cash, confirm release, inspect reports, and restore a backup in a test environment. Record task completion, errors, assistance needed, and acceptance decisions. Resident tests should also cover phone access, status comprehension, error correction, and the chosen network conditions.')
heading('4.8 Implementation Plan')
table('Proposed controlled implementation sequence',['Stage','Work to complete','Evidence before proceeding'],[['Authorization','Submit the required letter and obtain a scheduled official evaluation.','Agreed scope, responsible personnel and evaluation schedule.'],['Configuration','Confirm reachable server address, supported software, fees, templates and gateway settings.','Working role access, reviewed templates and test notifications.'],['Data and recovery','Validate resident records, access permissions and backup handling.','Checked sample data and successful test restoration.'],['Training and official testing','Train staff and administrator; observe complete document and payment tasks.','Recorded task results and issues with responsible operators.'],['Limited pilot','Use an approved small operational scope and monitor logs, delays and user feedback.','Resolved blocking issues and documented readiness decision.'],['Maintenance','Assign ownership for updates, template review, accounts and backups.','Regular review schedule and recovery responsibility.']],[1.15,2.45,2.35])
p('No launch date or operational commitment is assigned in this plan. Each stage depends on the barangay’s approval and the completion of its preceding checks. Continued manual or Word-based service should remain available during a limited pilot so that a portal outage does not prevent essential service.')
heading('4.9 Risk Management')
table('Operational risks and proposed controls',['Risk','Effect','Control or verification'],[['Unverified applicant details','Incorrect resident or certificate information.','Keep staff identity checks and resolve location discrepancies before issuance.'],['Network or gateway failure','Unavailable pages or delayed notices.','Monitor failures, retry appropriately and retain office follow-up procedures.'],['Incorrect fees or templates','Wrong certificate wording or payable amount.','Review settings with authorized personnel and test sample documents.'],['Unauthorized access or data exposure','Inappropriate access to personal information.','Review permissions, sessions, hosting configuration and minimal verification output; conduct security testing.'],['Loss of files or database','Interrupted service and missing transaction history.','Regular backups with tested restoration and defined retention.'],['User confusion or limited access','Incorrect submissions or inability to use the portal.','Clear instructions, office assistance, accessible layout and task observation.'],['Misinterpreted reports','Counts or elapsed durations used incorrectly.','Distinguish preparation from release, paid collections from pending amounts, and total elapsed time from staff working time.']],[1.45,1.8,2.7])
p('The risk controls identify work required for responsible deployment. Favorable questionnaire ratings do not close these risks automatically. In particular, the access and password items reflect observed behavior rather than a technical security audit.')

chapter(5,'Summary Conclusions and Recommendations')
heading('5.1 Summary of the Project')
p('The project developed a Smart Barangay Portal with QR Profiling and SMS Alerts for Barangay Luna. The requirements were informed by interviews with the barangay captain and a staff member and by a preliminary resident survey. The documented existing service used ready-made Word certificates, local verification of resident identity and location, and saved document files. The portal organizes these requirements into connected account, request, document, cash, and communication functions.')
p('The implementation uses PHP, MySQL, HTML, CSS, and JavaScript in a Laragon development environment. Authorized operators create resident accounts. Residents can review and submit requests and view progress. Staff support online and walk-in applicants, maintain templates, prepare printable documents, record cash payments, and confirm release. The administrator manages accounts, settings, backups, and oversight records. Optional QR lookup and configured SMS updates complement the administrative workflow.')
p(f'The current descriptive evaluation used 10 intended resident questionnaires, with 298 valid numeric responses across 30 items and two N/A responses. The overall resident mean was {rm:.2f}, interpreted as {interpret(rm).lower()}. Three additional questionnaires were completed by project team members acting as staff and administrator and were reported separately. Selected functional and interface regression checks passed in the current build. Official staff and administrator acceptance and production deployment have not yet been completed.')
heading('5.2 Conclusions')
p('The project achieved an implemented prototype that links resident profiling, document requests, preparation, cash records, release, and reporting. The verified regression scenarios support selected workflow requirements, including preventing unpaid fee-bearing release, avoiding duplicate release collections, protecting handled requests from resident deletion, replacing duplicate template saves, and keeping parent pronouns separate from beneficiary relationships.')
p(f'The participating residents gave a favorable assessment, with a pooled mean of {rm:.2f}. This supports the conclusion that the prototype was perceived positively by the 10 intended users who answered the questionnaire. The result is descriptive and should not be generalized to all Barangay Luna residents. Specific ratings and comments still identify needs for clearer interactions and measured page-load testing.')
p('The project team’s staff and administrator role feedback helps identify issues in office functions but does not establish independent acceptance by actual barangay personnel. The system should therefore be presented as a developed and initially evaluated prototype awaiting official operator evaluation. Its practical value depends on the approved workflow, accurate data, training, connectivity, functioning document conversion, and gateway configuration.')
p('The available evidence does not establish a quantified reduction in processing time or office visits, elimination of errors, production-scale reliability, or comprehensive security. QR and SMS features provide useful lookup and communication mechanisms, but their effectiveness must be tested within actual deployment conditions. The overall conclusion is that the prototype is suitable for further controlled evaluation and refinement before operational adoption.')
heading('5.3 Recommendations')
heading('5.3.1 For Barangay Luna and Intended Users',3)
p('Complete the required letter and scheduled visit, then involve actual staff and the administrator in task-based evaluation. Review resident registration rules, identity verification, document fees, template wording, approval responsibilities, and release procedures. Obtain a documented readiness decision before introducing live resident and transaction data into an operational deployment.')
p('Provide orientation and office-assisted access for residents who have difficulty using a phone, reading forms, or identifying their location. Keep clear explanations of document purpose, copies, payment at the office, rejection remarks, and pickup status. Maintain a service alternative during connection or system outages. Verify that public QR links disclose only the information needed for their purpose.')
heading('5.3.2 For the Project Team and System Maintainers',3)
p('Retest corrected popup, template, and pronoun behaviors with actual office personnel. Observe complete tasks rather than relying only on ratings. Measure page-load and submission response times on representative phones and the intended network, including poor connection conditions. Test end-to-end SMS delivery, unsuccessful sends, and follow-up handling with an approved gateway.')
p('Review access controls, session behavior, deployment settings, backup restoration, and document verification output before launch. Establish regular maintenance of accounts, fees, templates, logs, and backups. Record defects and their retest evidence so that later manuscripts or deployment reports can distinguish implemented fixes from suggestions.')
p('Assess the resident suggestion for requesting several different document types in one transaction. A future design should link each document to its own template, fee, copy count, status, and release event while presenting a clear combined transaction summary. Maintain the existing separation between pending preparation and completed cash collection when extending the service.')
heading('5.3.3 For Future Researchers',3)
p('Conduct an independent evaluation with actual barangay staff and administrators, a larger resident group, and clearly documented sampling and task procedures. If operational improvement is studied, collect comparable baseline and portal measurements of processing time, follow-up visits, errors, and completion rates. Do not compare the preliminary feature-preference survey directly with the later usability questionnaire as though they were the same measure.')
p('Investigate accessibility, offline or assisted service, load handling, privacy-related data exposure, and maintenance over a sustained pilot. Use separate technical tests for security and availability rather than treating security perception ratings as technical proof. Validate the questionnaire and preserve anonymized response and task records so that future findings can be audited and reproduced.')

doc.add_page_break();heading('REFERENCES',1)
refs=[
'Ayuningtyas, Gunawan, M. C., Zulfitra, S. R., Pratama, I. B., & Juniawan, A. A. (2022). Rancang Bangun Website untuk Administrasi Warga Gubeng Kertajaya Surabaya untuk mendukung Penerapan Smart City. Journal of Technology and Informatics, 4(1), 1–6. https://doi.org/10.37802/joti.v4i1.238',
'Hanafiah, H., & Gunadi, D. (2021). Assistance and utilization of G-Suite with QR-code in improving administrative services for RT residents 002/027 at Taman Graha Asri Housing Graha Minimalist Block Serang City. International Journal of Engagement and Empowerment, 1(3), 155–162. https://doi.org/10.53067/ije2.v1i3.35',
'Pastoril, J. G. (2023). Unified Barangay E-Services System with Augmented Analytics [Capstone/Special Project, University of the Philippines Open University]. UPOU Repository. https://hdl.handle.net/20.500.13073/1571',
'Taruc, F. S., Martin, T. A. S., Olipas, C. N. P., & Alegado, R. T. (2023). Docu-Go: The development and assessment of a web-based barangay document requesting system. International Journal of Information Technology & Computer Engineering, 3(4), 40–49. https://doi.org/10.55529/ijitc.34.40.49',
'United Nations Department of Economic and Social Affairs. (2024). UN E-Government Survey 2024. https://desapublications.un.org/publications/un-e-government-survey-2024',
'World Bank. (2022). 2022 GovTech Maturity Index update. https://www.worldbank.org/en/programs/govtech/2022-gtmi',
'World Wide Web Consortium. (2024). Web Content Accessibility Guidelines (WCAG) 2.2. https://www.w3.org/TR/WCAG22/']
for text in refs:
    a=p(text);a.paragraph_format.line_spacing=1.5;a.paragraph_format.left_indent=Inches(.35);a.paragraph_format.first_line_indent=Inches(-.35);a.paragraph_format.space_after=Pt(12)

doc.add_page_break();heading('APPENDIX A',1);heading('Preliminary Requirements Questionnaire',2)
p('This appendix retains the predevelopment questionnaire from the original proposal. It asks about the existing process and the perceived usefulness of proposed features. It is distinct from the current system evaluation questionnaire in Appendix D. Its wording is retained as historical instrument content and is not used to characterize all existing barangay records as paper-only.')
for i in range(430,442):
    text=original.paragraphs[i].text.strip()
    if text:
        a=p(text);a.paragraph_format.line_spacing=1.25
for part,rows in [('Current manual process assessment',original.tables[3].rows[2:7]),('Proposed system features',original.tables[3].rows[8:13])]:
    table(part,['Statement','5','4','3','2','1'],[[row.cells[0].text]+['□']*5 for row in rows],[3.7,.45,.45,.45,.45,.45])
for i in range(442,447):
    text=original.paragraphs[i].text.strip()
    if text:
        if text.count('_')>60:text='________________________________________________\n________________________________________________'
        a=p(text);a.paragraph_format.line_spacing=1.25

doc.add_page_break();heading('APPENDIX B',1);heading('Historical Preliminary Survey Results',2)
p('These results are retained from the original proposal as preliminary requirements evidence. The original proposal reports 10 respondents, four male and six female, with all ten reporting smartphone access. These are not the demographic characteristics of the October evaluation. The individual preliminary forms were not supplied for this revision, so the frequency tables are reproduced as source-reported historical data.')
table('Historical preliminary respondent profile',['Variable','Frequency','Percentage'],[['Male',4,'40%'],['Female',6,'60%'],['Smartphone access yes',10,'100%']],[3.2,1.1,1.65])
manual=[[4,0,0,0,0],[3,4,2,1,0],[4,3,1,2,0],[3,2,1,4,0],[2,2,3,2,1]]
features=[[4,3,2,1,0],[3,5,1,1,0],[4,5,1,0,0],[2,3,4,1,0],[5,2,1,1,1]]
for label,rows in [('Historical manual process responses',manual),('Historical proposed feature responses',features)]:
    rr=[]
    for j,a in enumerate(rows):
        n=sum(a);m=sum(v*c for v,c in zip([5,4,3,2,1],a))/n
        rr.append([j+1]+a+[n,f'{m:.2f}'])
    table(label,['Item','5','4','3','2','1','n','Mean'],rr,[.65,.6,.6,.6,.6,.6,.8,1.5])
p('The first manual-process item has four recorded ratings rather than ten. The proposal’s overall manual value of 3.88 is the unweighted average of the five item means. Pooling its 44 recorded responses instead gives 3.73; neither value is a measured processing-time baseline. The proposed-feature table has 50 responses and a pooled mean of 3.96. These historical measures are not comparable before-and-after scores against the different current evaluation instrument.')

doc.add_page_break();heading('APPENDIX C',1);heading('Original Requirements Interview Transcripts',2)
p('The following transcripts are retained from the supplied proposal. They document April 2026 requirements consultation with actual barangay personnel. They do not constitute staff or administrator acceptance of the current implementation. Answers are reproduced in their original wording.')
for i in range(484,575):
    text=original.paragraphs[i].text.strip()
    if not text:continue
    if i==532:doc.add_page_break()
    a=p(text,small=not text.startswith('Question'))
    a.paragraph_format.line_spacing=1.15;a.paragraph_format.space_after=Pt(5)
    for r in a.runs:r.font.size=Pt(11)
    if text.startswith('Question'):
        a.paragraph_format.keep_with_next=True
        for r in a.runs:r.bold=True

doc.add_page_break();heading('APPENDIX D',1);heading('Current User Evaluation Questionnaire',2)
p('System: Barangay Luna Smart Portal\nRole: Resident / Barangay Staff / Administrator\nDate: __________________  Device used: __________________')
p('Select one response per statement based on your actual experience. Use N/A for features not used or not observed. The supplied English and Cebuano forms use matching item codes. The English wording is reproduced below for the manuscript.')
p('5 Strongly Agree; 4 Agree; 3 Neither Agree nor Disagree; 2 Disagree; 1 Strongly Disagree; N/A Not applicable or not observed.',small=True)
for d,name in enumerate(dims):
    table(f'Evaluation instrument {name.lower()}',['Item','Statement','1','2','3','4','5','N/A'],[[f'{"ABCDEF"[d]}{j+1}',text]+['□']*6 for j,text in enumerate(items[d])],[.4,3.35,.32,.32,.32,.32,.32,.6])
p('Security ratings describe behavior observed by users. Encryption, attack resistance, and other technical protections require separate technical testing.',small=True)
p('Comments and suggestions\nWhat problems did you encounter?\n________________________________________________\nWhat improvements would you suggest?\n________________________________________________')

doc.add_page_break();heading('APPENDIX E',1);heading('Current Evaluation Tally and Source Audit',2)
p('R01–R10 identify the handwritten resident numbers 1–10. TS1 and TS2 identify the two team-completed staff-role sets; TA1 identifies the team-completed administrator-role set. The continuation pages are grouped with their adjoining cover pages in the supplied image sequence. Each row below lists the five item responses in item order. NA is excluded from numeric means. The duplicated photograph labelled Image 23 and Image 24 is counted only once.')
table('Raw responses by evaluator and dimension',['ID','A1–A5','B1–B5','C1–C5','D1–D5','E1–E5','F1–F5'],[[r]+[' '.join(map(str,row)) for row in rows] for r,rows in ratings.items()],[.6,.89,.89,.89,.89,.89,.89])
for d,name in enumerate(dims):
    rr=[]
    for j in range(5):
        a=vals(resids,d,j);c=Counter(a);rr.append([f'{"ABCDEF"[d]}{j+1}']+[c[v] for v in [5,4,3,2,1]]+[10-len(a),len(a),sum(a),f'{mean(a):.2f}'])
    table(f'Resident frequency audit for {name.lower()}',['Item','5','4','3','2','1','NA','n','Sum','Mean'],rr,[.65,.5,.5,.5,.5,.5,.5,.5,.65,1.15])
p('The R08 E1 mark and R04 E1–E3 marks were inspected at enlarged scale and assigned 4. R10 C1 and E5 are the only N/A responses. The date on the R07 cover appears inconsistent with the other October 2026 forms; it was not used to infer a separate evaluation date. Respondent ages and gender were not inferred from handwriting, device use, or the earlier survey.')
table('Rating photograph identifiers by questionnaire',['ID','Source photograph IDs for A B C D E F'],[[r,'\n'.join([f'AB: {fs[0][:-4]}',f'CD: {fs[1][:-4]}',f'EF: {fs[2][:-4]}'])] for r,fs in pagefiles.items()],[.55,5.4])
p('Duplicate source: the photograph for R07 C–D appears both with and without “(1)” in the filename. Matching SHA-256 hashes confirm that it is one image. Six comment photographs are represented in the Chapter 4 comment table. The tally is based on the supplied images and the authors’ clarification of evaluator participation.',small=True)

doc.add_page_break();heading('APPENDIX F',1);heading('Regression Verification Record',2)
p('Verification date: October 13, 2026. PHP tests with database fixtures used isolated temporary databases. The JavaScript scripts used simulated browser objects. A completed script without an assertion failure is recorded as passed. These records do not establish official acceptance, live SMS delivery, or production readiness.')
table('Regression scripts and verified scope',['Script','Scope','Result'],[
['short-issuance-workflow-test.php','Prepare/release distinction, cash/free release, duplicate prevention, removal and disabled SMS behavior.','Passed'],['template-replacement-test.php','Same type/year replacement, preserved file, separate years and saved-list behavior.','Passed'],['resident-submission-safety-test.php','Ownership, protected states, review, CSRF and removal audit retention.','Passed'],['document-request-feed-test.php','Authenticated staff/admin feed and request changes.','Passed'],['revenue-export-test.php','Collection export fields, totals, dates and output safety.','Passed'],['beneficiary-gender-test.php','Parent pronouns and beneficiary relationships.','Passed'],['document-request-live-test.cjs','Periodic request refresh, editing, account isolation and network recovery.','Passed'],['resident-submission-review-test.cjs','Large quantity acknowledgement and single reviewed submission.','Passed'],['overview-sync-test.cjs','Overview synchronization and page/account state.','Passed'],['issuance-popup-state-test.cjs','Prepared popup resume and duplicate prevention.','Passed'],['modal-layering-test.cjs','Modal placement, focus and retained values.','Passed']],[2.15,3.15,.65])

def replace_list(anchor,entries):
    # Native PAGEREF fields keep page references editable and updateable in Word.
    for text,level,name in entries:
        a=doc.add_paragraph();a.paragraph_format.line_spacing=1;a.paragraph_format.space_after=Pt(3)
        a.paragraph_format.left_indent=Inches(.18*max(0,level-1));a.paragraph_format.tab_stops.add_tab_stop(Inches(5.75-.18*max(0,level-1)),WD_ALIGN_PARAGRAPH.RIGHT)
        a.add_run(text+'\t');field(a,'PAGEREF '+name+' \\h','')
        for r in a.runs:r.font.size=Pt(10)
        anchor._p.addprevious(a._p)
    anchor._p.getparent().remove(anchor._p)
contents=[]
for text,level,name in TOC:
    if level>2:continue
    if level==2 and contents and contents[-1][0].startswith('APPENDIX '):
        prior=contents[-1];contents[-1]=(prior[0]+': '+text,prior[1],prior[2])
    else:contents.append((text,level,name))
replace_list(toc_anchor,contents)
replace_list(tables_anchor,[(t,1,n) for t,n in TABLES])
replace_list(figs_anchor,[(t,1,n) for t,n in FIGURES])
settings=doc.settings.element
up=OxmlElement('w:updateFields');up.set(qn('w:val'),'true');settings.append(up)
doc.core_properties.title='Smart Barangay Portal with QR Profiling and SMS Alerts for Barangay Luna'
doc.core_properties.subject='Revised Chapters 1 to 5 with current workflow and descriptive evaluation'
doc.core_properties.author='Calang, April John B.; Deloy, Christian; Deloy, Lorlie D.'
doc.core_properties.comments='Resident evaluation separated from project team staff and administrator role assessment.'
# Page-break-before avoids a blank page when a break paragraph would overflow.
for node in list(doc.element.body):
    if node.tag!=qn('w:p'):continue
    breaks=node.findall('.//'+qn('w:br'))
    if not any(b.get(qn('w:type'))=='page' for b in breaks):continue
    following=node.getnext()
    if following is not None and following.tag==qn('w:p'):
        pr=following.find(qn('w:pPr'))
        if pr is None:pr=OxmlElement('w:pPr');following.insert(0,pr)
        pb=OxmlElement('w:pageBreakBefore');pr.append(pb);node.getparent().remove(node)
destination=OUT/'BARANGAY_LUNA_REVISED_CHAPTERS_1_TO_5.docx'
doc.save(destination)
print(json.dumps({'output':str(destination),'tables':len(TABLES),'figures':len(FIGURES),'resident_mean':rm,'team_mean':tm,'resident_n':len(vals(resids)),'dimensions':survey['dimensions']},indent=2))
