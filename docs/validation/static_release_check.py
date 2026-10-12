"""Structural checks and isolated SQLite SQL regressions. Not PHP/Moodle tests.
Usage: python docs/validation/static_release_check.py [plugin_directory]
"""
from pathlib import Path
import json, re, sqlite3, sys, xml.etree.ElementTree as ET

root = Path(sys.argv[1]) if len(sys.argv)>1 else Path(__file__).resolve().parents[2]
tables = ET.parse(root/'db/install.xml').getroot().findall('./TABLES/TABLE')
names={t.get('NAME'):{f.get('NAME') for f in t.findall('./FIELDS/FIELD')} for t in tables}
for t in tables:
    fields=names[t.get('NAME')]
    assert 'id' in fields
    for item in t.findall('./KEYS/KEY')+t.findall('./INDEXES/INDEX'):
        assert {f.strip() for f in item.get('FIELDS').split(',')} <= fields
        if item.get('REFTABLE') in names:
            assert {f.strip() for f in item.get('REFFIELDS').split(',')} <= names[item.get('REFTABLE')]
lang={}
for l in ['en','ru']:
    s=(root/f'lang/{l}/tool_coursecompliance.php').read_text()
    keys=re.findall(r"^\$string\['([^']+)'\]",s,re.M)
    assert keys==sorted(keys) and len(set(keys))==len(keys)
    lang[l]=keys
assert lang['en']==lang['ru']
newfields=['configsnapshot','confighash','finaldurationms','finaldbqueries']
upgrade=(root/'db/upgrade.php').read_text().split('if ($oldversion < 2026092500)')[1]
for f in newfields:
    assert f in names['tool_coursecompliance_scan'] and f"new xmldb_field('{f}'" in upgrade
assert "upgrade_plugin_savepoint(true, 2026092500, 'tool', 'coursecompliance')" in upgrade
# The current plugin version may advance with subsequent releases. Do not pin it to an old release.
versiontext = (root / 'version.php').read_text()
versionmatch = re.search(r'\$plugin->version\s*=\s*(\d+)\s*;', versiontext)
assert versionmatch, 'Missing numeric plugin version'
assert int(versionmatch.group(1)) >= 2026092500, 'Plugin version predates the recorded DB upgrade'
# Lightweight lexical sanity, intentionally not described as a PHP syntax check.
for f in root.rglob('*.php'):
    s=f.read_text()
    clean=re.sub(r"/\*.*?\*/|//[^\n]*|'(?:\\.|[^'\\])*'|\"(?:\\.|[^\"\\])*\"",'',s,flags=re.S)
    stack=[]
    for c in clean:
        if c in '([{':stack.append(c)
        elif c in ')]}':
            assert stack and stack.pop()==dict(zip(')]}','([{'))[c],(f,'delimiter')
    assert not stack,(f,'unclosed delimiter')

def dbnew():
    db=sqlite3.connect(':memory:')
    for t in tables:
        columns=[]
        for f in t.findall('./FIELDS/FIELD'):
            d='"'+f.get('NAME')+'" '+('INTEGER' if f.get('TYPE')=='int' else 'REAL' if f.get('TYPE')=='number' else 'TEXT')
            if f.get('NAME')=='id':d+=' PRIMARY KEY'
            if f.get('DEFAULT') is not None:d+=' DEFAULT '+repr(f.get('DEFAULT'))
            columns.append(d)
        db.execute('CREATE TABLE '+t.get('NAME')+' ('+','.join(columns)+')')
        for ix in t.findall('./INDEXES/INDEX'):
            db.execute('CREATE '+('UNIQUE ' if ix.get('UNIQUE')=='true' else '')+'INDEX '+t.get('NAME')+'_'+ix.get('NAME')+' ON '+t.get('NAME')+' ('+ix.get('FIELDS')+')')
    db.executescript('CREATE TABLE course(id INTEGER PRIMARY KEY, fullname TEXT, shortname TEXT); CREATE TABLE modules(id INTEGER PRIMARY KEY, name TEXT); CREATE TABLE course_modules(id INTEGER PRIMARY KEY, course INTEGER, module INTEGER, instance INTEGER); CREATE TABLE quiz(id INTEGER PRIMARY KEY, name TEXT);')
    return db

def unbrace(s):return re.sub(r'\{(\w+)\}',r'\1',s)

def insert(db,table,**kw):
    return db.execute('INSERT INTO '+table+' ('+','.join(kw)+') VALUES('+','.join('?' for _ in kw)+')',list(kw.values())).lastrowid

checks=[];db=dbnew();scan='tool_coursecompliance_scan';snap='tool_coursecompliance_course';res='tool_coursecompliance_result';qi='tool_coursecompliance_qissue'
insert(db,'course',id=10,fullname='Course',shortname='C')
insert(db,'course',id=20,fullname='Unaudited',shortname='U')
insert(db,'tool_coursecompliance_profile',id=1,name='P',enabled=1)
insert(db,scan,id=1,profileid=1,parentid=0,scantype='audit',status='complete')
insert(db,scan,id=2,profileid=1,parentid=0,scantype='audit',status='running')
insert(db,scan,id=3,profileid=1,parentid=2,scantype='batch',status='complete')
for sid in [1,3]:insert(db,snap,scanid=sid,courseid=10)
s=(root/'classes/course_indicator.php').read_text()
sql=unbrace(re.search(r'\$latestsql = "(.*?)";',s,re.S).group(1))
assert db.execute(sql,{'courseid':10,'scanstatus':'complete','profileenabled':1}).fetchall()==[(1,1)]
checks.append('indicator excludes private worker')
pred=re.search(r'return "(.*?)";', (root/'classes/current_scan.php').read_text(),re.S).group(1)
pred=pred.replace('$alias','s').replace('$course','qi.courseid')
src=(root/'classes/question_issue_repository.php').read_text()
rulemap=re.search(r'private const RULE_ISSUES = \[(.*?)\n    \];',src,re.S).group(1)
pairs=[];params={}
for i,(rule,raw) in enumerate(re.findall(r"'([^']+)' => \[([^\]]+)\]",rulemap)):
    codes=re.findall(r"'([^']+)'",raw);marks=[]
    for j,code in enumerate(codes):
        k=f'qicode{i}_{j}';params[k]=code;marks.append(':'+k)
    params[f'qirule{i}']=rule
    pairs.append(f"(r.rulecode = :qirule{i} AND qi.issuecode IN ({','.join(marks)}))")
scope=unbrace(re.search(r'\$sql = "(FROM .*?)";',src,re.S).group(1).replace('$current',pred).replace('$mapping',' OR '.join(pairs)))
insert(db,res,scanid=1,courseid=10,component='quiz',assessmenttype='exam',rulecode='QUIZ_QUESTION_CORRECT_ANSWER_REQUIRED',severity='critical',message='issue')
for i in range(105):insert(db,qi,scanid=1,courseid=10,assessmenttype='exam',issuecode='no_correct_answer',severity='critical',questionname='Needle' if i==0 else 'Other')
assert db.execute('SELECT COUNT(1) '+scope,params).fetchone()[0]==105
assert len(db.execute('SELECT qi.id '+scope+' ORDER BY qi.id DESC LIMIT 100 OFFSET 100',params).fetchall())==5
assert db.execute('SELECT COUNT(1) '+scope+" AND qi.questionname LIKE :search",params|{'search':'%Needle%'}).fetchone()[0]==1
checks.append('question filters before page and full count')
summary=unbrace(re.search(r'"(SELECT COUNT\(DISTINCT CASE.*?\$scope)"',src,re.S).group(1).replace('$scope',scope))
assert db.execute(summary,params).fetchone()[1:3]==(105,105)
checks.append('question summary aggregates execute')
insert(db,scan,id=4,profileid=1,parentid=0,scantype='recheck',status='complete');insert(db,snap,scanid=4,courseid=10)
assert db.execute('SELECT COUNT(1) '+scope,params).fetchone()[0]==0
checks.append('clean recheck suppresses old question issues')
db.execute('UPDATE tool_coursecompliance_profile SET enabled=0 WHERE id=1')
assert db.execute('SELECT COUNT(1) '+scope,params).fetchone()[0]==0
checks.append('disabled profile excluded')
# Actual ready-course scope cannot include an unscanned course from the category.
ready=unbrace(re.search(r'\$readywhere = "(.*?)";', (root/'report.php').read_text(),re.S).group(1))
assert db.execute('SELECT id FROM course WHERE '+ready,{'readyscanid':4,'issuescanid':4}).fetchall()==[(10,)]
checks.append('ready courses restricted to scan snapshots')
# Nonempty details in every source table exercise actual copying, not just empty SELECTs.
insert(db,res,scanid=3,courseid=10,component='quiz',assessmenttype='exam',rulecode='QUIZ_TEST',severity='warning',message='Child payload')
insert(db,qi,scanid=3,courseid=10,assessmenttype='exam',issuecode='no_correct_answer',severity='critical',questionname='Child question')
insert(db,'tool_coursecompliance_assessment',scanid=3,courseid=10,assessmenttype='exam')
# Grouped copy across all four detail tables, using the exact declared column list.
for table in [res,snap,qi,'tool_coursecompliance_assessment']:
    columns=[r[1] for r in db.execute('PRAGMA table_info('+table+')') if r[1]!='id']
    fields=', '.join(columns);select=', '.join(':parentid' if c=='scanid' else c for c in columns)
    expected=db.execute('SELECT COUNT(1) FROM '+table+' WHERE scanid=3').fetchone()[0]
    assert expected > 0
    db.execute(f'INSERT INTO {table} ({fields}) SELECT {select} FROM {table} WHERE scanid=:childid',{'parentid':2,'childid':3})
    assert db.execute('SELECT COUNT(1) FROM '+table+' WHERE scanid=2').fetchone()[0]==expected
checks.append('bulk finalizer SQL for all detail tables')
print(json.dumps({'schema_tables':len(tables),'language_keys_each':len(lang['en']),'php_files_delimiter_checked':len(list(root.rglob('*.php'))),'new_upgrade_fields':newfields,'sql_regressions_passed':checks,'limitations':'SQLite and structural checks only. PHP lint, PHPUnit, PHPCS, clean install, upgrade and Moodle runtime not executed.'},ensure_ascii=False,indent=2))
