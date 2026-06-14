"use strict";

// ── Constants ──────────────────────────────────────────────────────────────
const OPTS = ['ก','ข','ค','ง','จ','ฉ'];
const ROLE_LABELS   = { admin:'Admin', deputy:'Academic Deputy', manager:'Exam Manager', teacher:'Teacher', supervisor:'Exam Supervisor', student:'Student' };
const ROLE_COLORS   = { 'Admin':['var(--st-red-bg)','var(--st-red-c)'], 'Academic Deputy':['var(--st-purple-bg)','var(--st-purple-c)'], 'Exam Manager':['var(--st-blue-bg)','var(--st-blue-c)'], 'Teacher':['var(--st-green-bg)','var(--st-green-c)'], 'Supervisor':['var(--st-amber-bg)','var(--st-amber-c)'], 'Exam Supervisor':['var(--st-amber-bg)','var(--st-amber-c)'], 'Student':['var(--st-gray-bg)','var(--st-gray-c)'] };
const ST_MAP        = { active:['var(--st-green-bg)','var(--st-green-c)','กำลังสอบ'], upcoming:['var(--st-blue-bg)','var(--st-blue-c)','รอสอบ'], done:['var(--st-gray-bg)','var(--st-gray-c)','เสร็จสิ้น'] };
const Q_TYPE_LABELS = { mcq:'เลือกตอบ (MCQ)', truefalse:'ถูก/ผิด', fill:'เติมคำ', matching:'จับคู่', short:'อัตนัย', drag:'ลากวาง' };
const Q_TYPES = [
  { id:'mcq',       label:'เลือกตอบ (MCQ)',     icon:'radio_button_checked' },
  { id:'truefalse', label:'ถูก / ผิด',          icon:'toggle_on' },
  { id:'fill',      label:'เติมคำในช่องว่าง',   icon:'short_text' },
  { id:'matching',  label:'จับคู่',             icon:'compare_arrows' },
  { id:'short',     label:'อัตนัย / เรียงความ', icon:'notes' },
  { id:'drag',      label:'ลากวาง (Drag&Drop)', icon:'open_with' },
];
const PAGE_TITLES = { admin:'จัดการผู้ใช้งาน', deputy:'ภาพรวมการสอบ', manager:'จัดการการสอบ', rooms:'จัดการห้องสอบ', settings:'ตั้งค่าระบบ', teacher:'ข้อสอบของฉัน', supervisor:'ควบคุมห้องสอบ' };
const NAV = {
  admin:      [{ label:'ผู้ใช้งาน',    screen:'admin',      icon:'manage_accounts' }],
  deputy:     [{ label:'ภาพรวม',       screen:'deputy',     icon:'dashboard' }],
  manager:    [{ label:'ตารางสอบ', screen:'manager', icon:'calendar_month' }, { label:'ห้องสอบ', screen:'rooms', icon:'meeting_room' }, { label:'ตั้งค่า', screen:'settings', icon:'settings' }],
  teacher:    [{ label:'ข้อสอบของฉัน', tab:'exams',         icon:'description' }, { label:'สร้างข้อสอบ', tab:'builder', icon:'edit_note' }, { label:'นำเข้า (Text)', tab:'import', icon:'upload_file' }],
  supervisor: [{ label:'ห้องสอบ',      screen:'supervisor', icon:'visibility' }],
};

// ── Theme ──────────────────────────────────────────────────────────────────
const THEME_CYCLE = { system:'light', light:'dark', dark:'system' };
const THEME_ICON  = { system:'brightness_auto', light:'light_mode', dark:'dark_mode' };
const THEME_LABEL = { system:'ตามระบบ', light:'โหมดสว่าง', dark:'โหมดมืด' };

function getTheme(){ return localStorage.getItem('examis-theme') || 'system'; }
function isDark(){
  const t = getTheme();
  if (t === 'dark')  return true;
  if (t === 'light') return false;
  return window.matchMedia('(prefers-color-scheme: dark)').matches;
}
function applyTheme(){
  document.documentElement.setAttribute('data-theme', isDark() ? 'dark' : 'light');
}
function cycleTheme(){
  localStorage.setItem('examis-theme', THEME_CYCLE[getTheme()] || 'light');
  applyTheme();
  render();
}
// listen for OS-level preference change when in system mode
window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', ()=>{ if(getTheme()==='system'){ applyTheme(); render(); } });
applyTheme();

// ── Theme button ───────────────────────────────────────────────────────────
function themeBtn(extraStyle=''){
  const t = getTheme(), icon = THEME_ICON[t], label = THEME_LABEL[t];
  return `<button data-act="cycleTheme" title="${label}" style="display:flex;align-items:center;gap:6px;padding:6px 12px;background:var(--bg-brand-soft);border:1px solid var(--bdr);border-radius:8px;font-size:12px;color:var(--txt-brand);cursor:pointer;font-weight:600;${extraStyle}" data-hover="background:var(--bdr-s)"><span class="msi" style="font-size:18px;">${icon}</span><span>${label}</span></button>`;
}

// ── API helper ─────────────────────────────────────────────────────────────
async function api(url, opts = {}) {
  const res = await fetch(url, { headers:{'Content-Type':'application/json','Accept':'application/json'}, credentials:'same-origin', ...opts, body:opts.body?JSON.stringify(opts.body):undefined });
  const data = await res.json();
  if (!res.ok) throw new Error(data.error || `HTTP ${res.status}`);
  return data;
}

// ── Toast ──────────────────────────────────────────────────────────────────
function toast(msg, type='ok'){
  const el = document.createElement('div');
  el.className = `toast toast-${type}`;
  el.textContent = msg;
  document.getElementById('toasts').appendChild(el);
  setTimeout(()=>el.remove(), 3200);
}

// ── Confirm Modal ──────────────────────────────────────────────────────────
function confirmModal(title, message=''){
  return new Promise(resolve => {
    const overlay = document.createElement('div');
    overlay.style.cssText = 'position:fixed;inset:0;background:rgba(0,0,0,0.5);backdrop-filter:blur(4px);z-index:9998;display:flex;align-items:center;justify-content:center;padding:20px;animation:fadeIn .15s ease;';
    overlay.innerHTML = `
      <div style="background:var(--bg-card);border-radius:16px;box-shadow:var(--shadow-l);width:100%;max-width:380px;overflow:hidden;animation:scaleIn .15s ease;">
        <div style="padding:24px 24px 0;">
          <div style="width:52px;height:52px;border-radius:14px;background:var(--st-red-bg);display:flex;align-items:center;justify-content:center;margin-bottom:16px;">
            <span class="msi" style="font-size:28px;color:var(--st-red-c);">delete_forever</span>
          </div>
          <div style="font-size:17px;font-weight:700;color:var(--txt-1);margin-bottom:8px;">${title}</div>
          ${message ? `<div style="font-size:14px;color:var(--txt-3);line-height:1.6;">${message}</div>` : ''}
        </div>
        <div style="padding:20px 24px 24px;display:flex;gap:10px;justify-content:flex-end;">
          <button id="cm-cancel" style="padding:10px 20px;background:var(--bg-card2);border:1px solid var(--bdr);border-radius:10px;font-size:14px;font-weight:600;color:var(--txt-2);cursor:pointer;">ยกเลิก</button>
          <button id="cm-ok" style="padding:10px 20px;background:var(--st-red-c);border:none;border-radius:10px;font-size:14px;font-weight:600;color:#fff;cursor:pointer;display:flex;align-items:center;gap:6px;"><span class="msi" style="font-size:16px;">delete</span>ลบ</button>
        </div>
      </div>`;
    document.body.appendChild(overlay);
    const done = ok => { overlay.remove(); document.removeEventListener('keydown', onKey); resolve(ok); };
    const onKey = e => { if(e.key==='Escape') done(false); if(e.key==='Enter') done(true); };
    overlay.querySelector('#cm-cancel').onclick = () => done(false);
    overlay.querySelector('#cm-ok').onclick    = () => done(true);
    overlay.addEventListener('click', e => { if(e.target===overlay) done(false); });
    document.addEventListener('keydown', onKey);
    overlay.querySelector('#cm-ok').focus();
  });
}

// ── Loading Modal ──────────────────────────────────────────────────────────
function loadingModal(message='กำลังบันทึกข้อมูล...'){
  const overlay = document.createElement('div');
  overlay.style.cssText = 'position:fixed;inset:0;background:rgba(0,0,0,0.55);backdrop-filter:blur(4px);z-index:9999;display:flex;align-items:center;justify-content:center;padding:20px;animation:fadeIn .15s ease;';
  overlay.innerHTML = `
    <div style="background:var(--bg-card);border-radius:16px;box-shadow:var(--shadow-l);padding:28px 32px;display:flex;align-items:center;gap:16px;animation:scaleIn .15s ease;min-width:240px;">
      <span style="width:22px;height:22px;border:3px solid var(--bdr);border-top-color:var(--txt-brand);border-radius:50%;animation:spin 0.7s linear infinite;flex-shrink:0;display:inline-block;"></span>
      <span style="font-size:15px;font-weight:600;color:var(--txt-1);">${message}</span>
    </div>`;
  overlay.addEventListener('click', e=>e.stopPropagation());
  document.body.appendChild(overlay);
  return ()=>overlay.remove();
}

// ── App state ──────────────────────────────────────────────────────────────
let _timer = null, _svPoll = null, _sessPoll = null;
const state = {
  screen:'login', role:null, userId:null, userFullName:'',
  loginErr:'', loginLoading:false, sbCollapsed:false,
  users:[], showAddUser:false,
  addForm:{ full_name:'', username:'', role:'student', department:'', password:'' },
  dashStats:null, dashSessions:[],
  settings:{ summer_term_enabled:'0' },
  managerTab:'schedule', sessions:[], showNewSess:false, editingSessId:null,
  sessForm:{ exam_paper_id:'', room:'', room_id:'', semester:'', exam_date:'', start_time:'', end_time:'', time_limit_minutes:90 },
  examPapers:[],
  buildings:[], rooms:[], roomReport:[],
  roomsTab:'buildings',
  buildingForm:{name:'', description:''}, editingBuildingId:null,
  roomForm:{building_id:'', room_code:'', capacity:30, description:''}, editingRoomId:null,
  teacherTab:'exams', myExams:[], currentExam:null, questions:[],
  qType:'mcq', builderCorrect:0, editingQId:null,
  qForm:{ question_text:'', score:2, options:['','','',''], correct_answer:null, correct_tf:true, fill_answer:'', match_left:['','','',''], match_right:['','','',''], short_guide:'' },
  importText:'', importParsed:[],
  svSessions:[], svSession:null, svStudents:[], codeVisible:false,
  examCode:'',
  studentExam:null, sessionInfo:null, questions2:[], savedAnswers:{},
  examStarted:false, timeLeft:5400,
  currentQ:0, answers:{}, matchLeft:null, matchPairs:{},
  _loginU:'', _loginP:'',
};
function setState(patch){ Object.assign(state, patch); render(); }

// ── Auth ───────────────────────────────────────────────────────────────────
async function checkSession(){
  try { const d = await api('api/auth.php'); if(d.user) applyUser(d.user); else render(); }
  catch(e){ render(); }
}
function applyUser(u){
  const map = { admin:'admin', deputy:'deputy', manager:'manager', teacher:'teacher', supervisor:'supervisor', student:'student-enter' };
  setState({ screen:map[u.role]||'login', role:u.role, userId:u.id, userFullName:u.full_name, loginErr:'', loginLoading:false });
  if(u.role==='admin')      loadUsers();
  if(u.role==='deputy')     loadDashboard();
  if(u.role==='manager')    { loadSessions(); loadExamPapers(); loadBuildings(); loadRooms(); loadSettings(); startSessPoll(); }
  if(u.role==='teacher')    loadMyExams();
  if(u.role==='supervisor') loadSvSessions();
}
async function login(){
  setState({ loginLoading:true, loginErr:'' });
  try { const d = await api('api/auth.php',{method:'POST',body:{username:state._loginU,password:state._loginP}}); applyUser(d.user); }
  catch(e){ setState({ loginLoading:false, loginErr:e.message||'ชื่อผู้ใช้หรือรหัสผ่านไม่ถูกต้อง' }); }
}
async function logout(){
  clearTimers();
  try { await api('api/auth.php',{method:'DELETE'}); } catch(e){}
  setState({ screen:'login', role:null, userId:null, userFullName:'', _loginU:'', _loginP:'', loginErr:'', examStarted:false, timeLeft:5400, answers:{}, currentQ:0, matchPairs:{}, matchLeft:null, studentExam:null, questions2:[], savedAnswers:{}, svSession:null, svStudents:[] });
}
function clearTimers(){ if(_timer){clearInterval(_timer);_timer=null;} if(_svPoll){clearInterval(_svPoll);_svPoll=null;} if(_sessPoll){clearInterval(_sessPoll);_sessPoll=null;} }
function startSessPoll(){ clearTimers(); _sessPoll=setInterval(()=>{ if(state.screen==='manager'||state.screen==='rooms'||state.role==='manager')loadSessions(); },60000); }

// ── Admin data ─────────────────────────────────────────────────────────────
async function loadUsers(){ try{ const d=await api('api/users.php'); setState({users:d}); }catch(e){toast(e.message,'err');} }
async function saveUser(){
  const f=state.addForm;
  if(!f.full_name||!f.username||!f.password){toast('กรุณากรอกข้อมูลให้ครบ','err');return;}
  try{ await api('api/users.php',{method:'POST',body:f}); toast('เพิ่มผู้ใช้เรียบร้อย'); setState({showAddUser:false,addForm:{full_name:'',username:'',role:'student',department:'',password:''}}); loadUsers(); }catch(e){toast(e.message,'err');}
}
async function deleteUser(id){ if(!await confirmModal('ลบผู้ใช้งาน','ต้องการลบผู้ใช้รายนี้ออกจากระบบ? การกระทำนี้ไม่สามารถย้อนกลับได้'))return; try{await api('api/users.php?id='+id,{method:'DELETE'});toast('ลบผู้ใช้เรียบร้อย');loadUsers();}catch(e){toast(e.message,'err');} }
async function toggleUserStatus(id,current){ const s=current==='active'?'inactive':'active'; try{await api('api/users.php?id='+id,{method:'PUT',body:{status:s}});loadUsers();}catch(e){toast(e.message,'err');} }

// ── Deputy data ────────────────────────────────────────────────────────────
async function loadDashboard(){ try{const d=await api('api/dashboard.php');setState({dashStats:d.stats,dashSessions:d.sessions});}catch(e){toast(e.message,'err');} }

// ── Manager data ───────────────────────────────────────────────────────────
async function loadSessions(){ try{const d=await api('api/sessions.php');setState({sessions:d});}catch(e){toast(e.message,'err');} }
async function loadExamPapers(){ try{const d=await api('api/exams.php');setState({examPapers:d});}catch(e){} }
async function loadSettings(){ try{const d=await api('api/settings.php');setState({settings:d});}catch(e){} }
async function toggleSummerTerm(){
  const next=state.settings.summer_term_enabled==='1'?'0':'1';
  try{
    const d=await api('api/settings.php',{method:'PUT',body:{key:'summer_term_enabled',value:next}});
    setState({settings:d});
    toast(next==='1'?'เปิดใช้ภาคฤดูร้อนแล้ว':'ปิดใช้ภาคฤดูร้อนแล้ว');
  }catch(e){toast(e.message,'err');}
}
const BLANK_SESS = { exam_paper_id:'', room:'', room_id:'', semester:'', exam_date:'', start_time:'', end_time:'', time_limit_minutes:90 };
function semLabel(sem){ if(!sem)return '—'; const[t,y]=sem.split('/'); return t==='S'?'ภาคฤดูร้อน/'+y:'ภาค '+t+'/'+y; }
function currentSemester(summerEnabled){ const now=new Date(),m=now.getMonth()+1,be=now.getFullYear()+543; if(m>=5&&m<=10)return '1/'+be; if(m>=11)return '2/'+be; if(m<=2)return '2/'+(be-1); return summerEnabled?'S/'+(be-1):'2/'+(be-1); }
function generateSemesters(summerEnabled){ const be=new Date().getFullYear()+543,res=[]; for(let y=be;y>=be-3;y--){ if(summerEnabled)res.push('S/'+y); res.push('2/'+y); res.push('1/'+y); } return res; }
async function saveSession(){
  const f=state.sessForm;
  if(!f.exam_paper_id||!f.room_id||!f.semester||!f.exam_date||!f.start_time||!f.end_time){toast('กรุณากรอกข้อมูลให้ครบ','err');return;}
  try{
    if(state.editingSessId){
      await api('api/sessions.php?id='+state.editingSessId,{method:'PUT',body:f});
      toast('แก้ไขรายการสอบแล้ว');
    } else {
      await api('api/sessions.php',{method:'POST',body:f});
      toast('สร้างการสอบเรียบร้อย');
    }
    setState({showNewSess:false,editingSessId:null,sessForm:{...BLANK_SESS}});
    loadSessions();
  }catch(e){toast(e.message,'err');}
}
function editSession(id){
  const s=state.sessions.find(x=>x.id===+id); if(!s)return;
  setState({
    showNewSess:true, editingSessId:s.id,
    sessForm:{ exam_paper_id:s.exam_paper_id, room:s.room||'', room_id:s.room_id||'', semester:s.semester||'', exam_date:s.exam_date||'', start_time:s.start_time||'', end_time:s.end_time||'', time_limit_minutes:s.time_limit_minutes||90 }
  });
}
function cancelEditSession(){
  setState({showNewSess:false, editingSessId:null, sessForm:{...BLANK_SESS}});
}
async function updateSessionStatus(id,status){ try{await api('api/sessions.php?id='+id,{method:'PUT',body:{status}});loadSessions();toast('อัปเดตสถานะแล้ว');}catch(e){toast(e.message,'err');} }

// ── Buildings & Rooms data ─────────────────────────────────────────────────
const BLANK_BUILDING = {name:'', description:''};
const BLANK_ROOM = {building_id:'', room_code:'', capacity:30, description:''};
async function loadBuildings(){ try{const d=await api('api/buildings.php');setState({buildings:d});}catch(e){toast(e.message,'err');} }
async function loadRooms(){ try{const d=await api('api/rooms.php');setState({rooms:d});}catch(e){toast(e.message,'err');} }
async function loadRoomReport(){ try{const d=await api('api/rooms.php?report=1');setState({roomReport:d});}catch(e){toast(e.message,'err');} }

async function saveBuilding(){
  const f=state.buildingForm;
  if(!f.name.trim()){toast('กรุณากรอกชื่ออาคาร','err');return;}
  const close=loadingModal('กำลังบันทึกข้อมูลอาคาร...');
  try{
    if(state.editingBuildingId){
      await api('api/buildings.php?id='+state.editingBuildingId,{method:'PUT',body:f});
      toast('แก้ไขข้อมูลอาคารแล้ว');
    } else {
      await api('api/buildings.php',{method:'POST',body:f});
      toast('เพิ่มอาคารแล้ว');
    }
    setState({buildingForm:{...BLANK_BUILDING},editingBuildingId:null});
    loadBuildings();
  }catch(e){toast(e.message,'err');}finally{close();}
}
async function deleteBuilding(id){
  if(!await confirmModal('ลบอาคาร','อาคารนี้และห้องทั้งหมดในอาคารจะถูกลบ'))return;
  try{await api('api/buildings.php?id='+id,{method:'DELETE'});toast('ลบอาคารแล้ว');loadBuildings();loadRooms();}catch(e){toast(e.message,'err');}
}
function editBuilding(id){
  const b=state.buildings.find(x=>x.id===+id); if(!b)return;
  setState({editingBuildingId:b.id,buildingForm:{name:b.name,description:b.description||''}});
}

async function saveRoom(){
  const f=state.roomForm;
  if(!f.building_id||!f.room_code.trim()){toast('กรุณาเลือกอาคารและกรอกรหัสห้อง','err');return;}
  const close=loadingModal('กำลังบันทึกข้อมูลห้องสอบ...');
  try{
    if(state.editingRoomId){
      await api('api/rooms.php?id='+state.editingRoomId,{method:'PUT',body:f});
      toast('แก้ไขข้อมูลห้องแล้ว');
    } else {
      await api('api/rooms.php',{method:'POST',body:f});
      toast('เพิ่มห้องสอบแล้ว');
    }
    setState({roomForm:{...BLANK_ROOM},editingRoomId:null});
    loadRooms();
  }catch(e){toast(e.message,'err');}finally{close();}
}
async function deleteRoom(id){
  if(!await confirmModal('ลบห้องสอบ','ต้องการลบห้องนี้? การสอบที่ผูกกับห้องนี้จะไม่ถูกลบ'))return;
  try{await api('api/rooms.php?id='+id,{method:'DELETE'});toast('ลบห้องสอบแล้ว');loadRooms();}catch(e){toast(e.message,'err');}
}
function editRoom(id){
  const r=state.rooms.find(x=>x.id===+id); if(!r)return;
  setState({editingRoomId:r.id,roomForm:{building_id:r.building_id,room_code:r.room_code,capacity:r.capacity,description:r.description||''}});
}

// ── Teacher data ───────────────────────────────────────────────────────────
async function loadMyExams(){ try{const d=await api('api/exams.php');setState({myExams:d});}catch(e){toast(e.message,'err');} }
async function createExam(){
  const title=(document.querySelector('[data-field="newExamTitle"]')||{}).value||'';
  if(!title.trim()){toast('กรุณากรอกชื่อชุดข้อสอบ','err');return;}
  try{ await api('api/exams.php',{method:'POST',body:{title}}); toast('สร้างชุดข้อสอบแล้ว'); await loadMyExams(); const ex=state.myExams.find(e=>e.title===title)||state.myExams[state.myExams.length-1]; if(ex)openBuilder(ex); }catch(e){toast(e.message,'err');}
}
async function openBuilder(exam){
  setState({currentExam:exam,teacherTab:'builder',questions:[]});
  try{const d=await api('api/questions.php?exam_id='+exam.id);setState({questions:d});}catch(e){toast(e.message,'err');}
}
async function publishExam(id,current){
  const s=current==='published'?'draft':'published';
  try{await api('api/exams.php?id='+id,{method:'PUT',body:{status:s}});toast(s==='published'?'เผยแพร่แล้ว':'เปลี่ยนเป็นร่าง');loadMyExams();}catch(e){toast(e.message,'err');}
}
async function deleteExam(id){ if(!await confirmModal('ลบชุดข้อสอบ','ต้องการลบชุดข้อสอบนี้? คำถามทั้งหมดในชุดนี้จะถูกลบด้วย'))return; try{await api('api/exams.php?id='+id,{method:'DELETE'});toast('ลบแล้ว');loadMyExams();setState({currentExam:null});}catch(e){toast(e.message,'err');} }
const BLANK_QFORM = { question_text:'', score:2, options:['','','',''], correct_answer:null, correct_tf:true, fill_answer:'', match_left:['','','',''], match_right:['','','',''], short_guide:'' };
function _buildQPayload(s){
  const f=s.qForm; let options=null, correct_answer=null;
  switch(s.qType){
    case 'mcq': options=f.options.filter(o=>o.trim()); if(options.length<2)throw new Error('กรุณากรอกตัวเลือกอย่างน้อย 2 ข้อ'); correct_answer=f.options[s.builderCorrect]||options[0]; break;
    case 'truefalse': options=['ถูกต้อง','ไม่ถูกต้อง']; correct_answer=f.correct_tf?'ถูกต้อง':'ไม่ถูกต้อง'; break;
    case 'fill': correct_answer=f.fill_answer; break;
    case 'matching': options={left:f.match_left.filter(x=>x),right:f.match_right.filter(x=>x)}; correct_answer={}; f.match_left.forEach((l,i)=>{if(l&&f.match_right[i])correct_answer[i]=String(i);}); break;
    case 'short': correct_answer=f.short_guide||null; break;
  }
  return { question_text:f.question_text, options, correct_answer, score:parseInt(f.score)||2 };
}
async function saveQuestion(){
  const s=state; if(!s.currentExam){toast('กรุณาเลือกชุดข้อสอบก่อน','err');return;}
  const f=s.qForm; if(!f.question_text.trim()){toast('กรุณากรอกคำถาม','err');return;}
  try{
    const payload=_buildQPayload(s);
    if(s.editingQId){
      await api('api/questions.php?id='+s.editingQId,{method:'PUT',body:payload});
      toast('แก้ไขคำถามแล้ว');
    } else {
      await api('api/questions.php',{method:'POST',body:{exam_paper_id:s.currentExam.id,type:s.qType,...payload}});
      toast('บันทึกคำถามแล้ว');
    }
    setState({editingQId:null,qForm:{...BLANK_QFORM}});
    const d=await api('api/questions.php?exam_id='+s.currentExam.id); setState({questions:d});
  }catch(e){toast(e.message,'err');}
}
function editQuestion(id){
  const q=state.questions.find(x=>x.id===+id); if(!q)return;
  const opts=Array.isArray(q.options)?[...q.options]:[];
  while(opts.length<4) opts.push('');
  let correctIdx=0, correctTf=true, fillAns='', matchLeft=['','','',''], matchRight=['','','',''], shortGuide='';
  if(q.type==='mcq'){ correctIdx=Math.max(0,opts.indexOf(q.correct_answer)); }
  else if(q.type==='truefalse'){ correctTf=q.correct_answer==='ถูกต้อง'; }
  else if(q.type==='fill'){ fillAns=q.correct_answer||''; }
  else if(q.type==='matching'){
    if(q.options&&q.options.left){ matchLeft=[...q.options.left]; while(matchLeft.length<4)matchLeft.push(''); }
    if(q.options&&q.options.right){ matchRight=[...q.options.right]; while(matchRight.length<4)matchRight.push(''); }
  } else if(q.type==='short'){ shortGuide=q.correct_answer||''; }
  setState({ editingQId:q.id, qType:q.type, builderCorrect:correctIdx,
    qForm:{ question_text:q.question_text||'', score:q.score||2, options:opts,
      correct_answer:q.correct_answer, correct_tf:correctTf, fill_answer:fillAns,
      match_left:matchLeft, match_right:matchRight, short_guide:shortGuide } });
}
function cancelEditQ(){
  setState({ editingQId:null, qForm:{...BLANK_QFORM} });
}
async function deleteQuestion(id){ if(!await confirmModal('ลบคำถาม','ต้องการลบคำถามข้อนี้? การกระทำนี้ไม่สามารถย้อนกลับได้'))return; try{await api('api/questions.php?id='+id,{method:'DELETE'});toast('ลบคำถามแล้ว');const d=await api('api/questions.php?exam_id='+state.currentExam.id);setState({questions:d,editingQId:state.editingQId===+id?null:state.editingQId,qForm:state.editingQId===+id?{...BLANK_QFORM}:state.qForm});}catch(e){toast(e.message,'err');} }
async function importText(){
  const s=state; if(!s.importParsed.length){toast('ไม่พบข้อสอบที่จะนำเข้า','err');return;} if(!s.currentExam){toast('กรุณาเลือกชุดข้อสอบก่อน','err');return;}
  try{ for(const q of s.importParsed){ await api('api/questions.php',{method:'POST',body:{exam_paper_id:s.currentExam.id,type:'mcq',question_text:q.question,options:q.options,correct_answer:q.answer,score:1}}); } toast(`นำเข้า ${s.importParsed.length} ข้อเรียบร้อย`); setState({importParsed:[]}); }catch(e){toast(e.message,'err');}
}
function parseImport(){
  const blocks=(state.importText||'').trim().split(/\n\s*\n/); const result=[];
  for(const block of blocks){
    const lines=block.trim().split('\n'); const qm=lines[0].match(/^\d+\.\s*(.+)/); if(!qm)continue;
    const question=qm[1].trim(); const options=[]; let answer='';
    for(let i=1;i<lines.length;i++){
      const om=lines[i].match(/^[กขคงจฉ]\.\s*(.+)/); const am=lines[i].match(/^เฉลย\s*[:：]\s*(.+)/iu);
      if(om)options.push(om[1].trim()); if(am)answer=am[1].trim();
    }
    if(options.length>0)result.push({question,options,answer,idx:result.length+1});
  }
  setState({importParsed:result});
}

// ── Supervisor data ────────────────────────────────────────────────────────
async function loadSvSessions(){ try{const d=await api('api/supervisor.php?action=my_sessions');setState({svSessions:d});}catch(e){toast(e.message,'err');} }
async function watchSession(sessionId){
  clearTimers();
  try{
    const d=await api('api/supervisor.php?action=students&session_id='+sessionId);
    setState({svSession:d.session,svStudents:d.students,codeVisible:false});
    _svPoll=setInterval(async()=>{ try{const d2=await api('api/supervisor.php?action=students&session_id='+sessionId);setState({svStudents:d2.students});}catch(e){} },5000);
  }catch(e){toast(e.message,'err');}
}

// ── Student exam ───────────────────────────────────────────────────────────
async function enterExam(){
  const code=(state.examCode||'').trim().toUpperCase(); if(code.length<4)return;
  try{
    const d=await api('api/student.php?action=enter',{method:'POST',body:{access_code:code}});
    setState({sessionInfo:d.session,studentExam:d.student_exam,questions2:d.questions,savedAnswers:d.saved_answers||{},answers:d.saved_answers||{},timeLeft:d.time_left??(d.session.time_limit_minutes*60),examStarted:d.student_exam.status==='in_progress',screen:'student-exam',currentQ:0,matchPairs:{},matchLeft:null});
    if(d.student_exam.status==='in_progress')startTimer(d.time_left);
  }catch(e){toast(e.message,'err');}
}
async function startExam(){
  try{const d=await api('api/student.php?action=start',{method:'POST',body:{student_exam_id:state.studentExam.id}});setState({studentExam:d.student_exam,timeLeft:d.time_left,examStarted:true});startTimer(d.time_left);}catch(e){toast(e.message,'err');}
}
function startTimer(seconds){
  clearTimers(); setState({timeLeft:seconds});
  _timer=setInterval(()=>{ if(state.timeLeft<=1){clearInterval(_timer);_timer=null;setState({timeLeft:0});submitExam();return;} setState({timeLeft:state.timeLeft-1}); },1000);
}
async function saveAnswer(qId,answer){
  const se=state.studentExam; if(!se)return;
  const a={...state.answers}; a[qId]=answer; setState({answers:a});
  try{await api('api/student.php?action=answer',{method:'POST',body:{student_exam_id:se.id,question_id:qId,answer}});}catch(e){}
}
async function submitExam(){
  clearTimers(); const se=state.studentExam; if(!se)return;
  try{
    const d=await api('api/student.php?action=submit',{method:'POST',body:{student_exam_id:se.id}});
    alert(`ส่งข้อสอบเรียบร้อย!\nคะแนน: ${d.total_score} / ${d.max_score}\nตอบแล้ว ${d.answered}/${d.total} ข้อ`);
    logout();
  }catch(e){toast(e.message,'err');}
}
function selOpt(qId,val){ const a={...state.answers}; a[qId]=val; setState({answers:a}); saveAnswer(qId,val); }
function matchRight(qId,rightIdx){
  if(state.matchLeft===null)return;
  const mp={...state.matchPairs}; mp[state.matchLeft]=rightIdx;
  const a={...state.answers}; a[qId]={...mp};
  setState({matchPairs:mp,matchLeft:null,answers:a}); saveAnswer(qId,{...mp});
}

// ── Click / input dispatch ─────────────────────────────────────────────────
const ACTIONS = {
  login, logout, cycleTheme,
  toggleSb:    ()=>setState({sbCollapsed:!state.sbCollapsed}),
  navScreen:   a=>{ setState({screen:a}); if(a==='admin')loadUsers(); if(a==='deputy')loadDashboard(); if(a==='manager'){loadSessions();loadExamPapers();loadBuildings();loadRooms();} if(a==='rooms'){loadBuildings();loadRooms();} if(a==='settings')loadSettings(); if(a==='supervisor')loadSvSessions(); },
  navTab:      a=>setState({teacherTab:a}),
  tTab:        a=>setState({teacherTab:a}),
  mTab:        a=>setState({managerTab:a}),
  qType:       a=>setState({qType:a}),
  builderCorrect: a=>setState({builderCorrect:+a}),
  openAddUser: ()=>setState({showAddUser:true}),
  closeAddUser:()=>setState({showAddUser:false}),
  saveUser,
  deleteUser:  a=>deleteUser(+a),
  toggleStatus:a=>{ const u=state.users.find(x=>x.id===+a); if(u)toggleUserStatus(+a,u.status); },
  openNewSess:      ()=>setState({showNewSess:true, editingSessId:null, sessForm:{...BLANK_SESS, semester:currentSemester(state.settings.summer_term_enabled==='1')}}),
  toggleSummerTerm: ()=>toggleSummerTerm(),
  closeNewSess:     ()=>cancelEditSession(),
  saveSession,
  editSession:      a=>editSession(+a),
  cancelEditSession,
  updateSessStatus: a=>{ const[id,status]=a.split(':'); updateSessionStatus(+id,status); },
  saveBuilding, deleteBuilding:a=>deleteBuilding(+a), editBuilding:a=>editBuilding(+a),
  cancelEditBuilding: ()=>setState({editingBuildingId:null,buildingForm:{...BLANK_BUILDING}}),
  saveRoom, deleteRoom:a=>deleteRoom(+a), editRoom:a=>editRoom(+a),
  cancelEditRoom: ()=>setState({editingRoomId:null,roomForm:{...BLANK_ROOM}}),
  roomsTab: a=>{ setState({roomsTab:a}); if(a==='report')loadRoomReport(); },
  openBuilder: a=>{ const ex=state.myExams.find(e=>e.id===+a); if(ex)openBuilder(ex); },
  createExam,
  publishExam: a=>{ const ex=state.myExams.find(e=>e.id===+a); if(ex)publishExam(+a,ex.status); },
  deleteExam:  a=>deleteExam(+a),
  saveQuestion,
  editQuestion:  a=>editQuestion(+a),
  cancelEditQ,
  deleteQuestion: a=>deleteQuestion(+a),
  parseImport,
  importText,
  toggleCode:  ()=>setState({codeVisible:!state.codeVisible}),
  watchSession:a=>watchSession(+a),
  backSvList:  ()=>{ clearTimers(); setState({svSession:null,svStudents:[]}); loadSvSessions(); },
  toExamEnter: ()=>enterExam(),
  noop: ()=>{},
  startExam,
  navQ:        a=>setState({currentQ:+a}),
  prevQ:       ()=>{ if(state.currentQ>0)setState({currentQ:state.currentQ-1}); },
  nextQ:       ()=>{ if(state.currentQ<state.questions2.length-1)setState({currentQ:state.currentQ+1}); },
  submitExam,
  matchLeft:   a=>setState({matchLeft:+a}),
  matchRight:  a=>{ const q=state.questions2[state.currentQ]; if(q)matchRight(q.id,+a); },
};
document.getElementById('app').addEventListener('click', e=>{
  const t=e.target.closest('[data-act]'); if(!t)return;
  const fn=ACTIONS[t.dataset.act]; if(fn)fn(t.dataset.arg);
});
document.getElementById('app').addEventListener('input', e=>{
  const f=e.target.dataset.field; if(!f)return; const v=e.target.value;
  if(f==='loginU'){state._loginU=v;if(state.loginErr)setState({loginErr:''});return;}
  if(f==='loginP'){state._loginP=v;if(state.loginErr)setState({loginErr:''});return;}
  if(f==='examCode'){e.target.value=v.toUpperCase();setState({examCode:v.toUpperCase()});return;}
  if(f==='importText'){state.importText=v;return;}
  if(f.startsWith('add_')){const k=f.slice(4);state.addForm={...state.addForm,[k]:v};return;}
  if(f.startsWith('sess_')){const k=f.slice(5);state.sessForm={...state.sessForm,[k]:v};return;}
  if(f.startsWith('bld_')){const k=f.slice(4);state.buildingForm={...state.buildingForm,[k]:v};return;}
  if(f.startsWith('rm_')){const k=f.slice(3);state.roomForm={...state.roomForm,[k]:v};return;}
  if(f==='q_text'){state.qForm={...state.qForm,question_text:v};return;}
  if(f==='q_score'){state.qForm={...state.qForm,score:v};return;}
  if(f==='q_fill_answer'){state.qForm={...state.qForm,fill_answer:v};return;}
  if(f==='q_short_guide'){state.qForm={...state.qForm,short_guide:v};return;}
  if(f.startsWith('q_opt_')){const i=+f.slice(6);const opts=[...state.qForm.options];opts[i]=v;state.qForm={...state.qForm,options:opts};return;}
  if(f.startsWith('q_ml_')){const i=+f.slice(5);const ml=[...state.qForm.match_left];ml[i]=v;state.qForm={...state.qForm,match_left:ml};return;}
  if(f.startsWith('q_mr_')){const i=+f.slice(5);const mr=[...state.qForm.match_right];mr[i]=v;state.qForm={...state.qForm,match_right:mr};return;}
  if(f==='fillAnswer'||f==='shortAnswer'){ const q=state.questions2[state.currentQ];if(!q)return; const a={...state.answers};a[q.id]=v;state.answers=a; saveAnswer(q.id,v); }
});
document.getElementById('app').addEventListener('change', e=>{
  const f=e.target.dataset.field; if(!f)return; const v=e.target.value;
  if(f==='q_tf'){state.qForm={...state.qForm,correct_tf:v==='true'};render();return;}
  if(f.startsWith('add_')){const k=f.slice(4);state.addForm={...state.addForm,[k]:v};render();return;}
  if(f.startsWith('sess_')){const k=f.slice(5);state.sessForm={...state.sessForm,[k]:v};render();return;}
  if(f.startsWith('bld_')){const k=f.slice(4);state.buildingForm={...state.buildingForm,[k]:v};render();return;}
  if(f.startsWith('rm_')){const k=f.slice(3);state.roomForm={...state.roomForm,[k]:v};render();return;}
});
document.getElementById('app').addEventListener('keydown', e=>{
  if((e.target.dataset.field==='loginU'||e.target.dataset.field==='loginP')&&e.key==='Enter')login();
  if(e.target.dataset.field==='examCode'&&e.key==='Enter')enterExam();
  if(e.key==='Enter'&&e.target.dataset.field?.startsWith('bld_'))saveBuilding();
  if(e.key==='Enter'&&e.target.dataset.field?.startsWith('rm_'))saveRoom();
  if(e.key==='Enter'&&e.target.dataset.field?.startsWith('sess_'))saveSession();
});

// ── Icon repaint fix ───────────────────────────────────────────────────────
function repaintIcons(){
  document.fonts.ready.then(()=>{
    document.querySelectorAll('.msi').forEach(el=>{
      const v=el.textContent; el.textContent=''; el.textContent=v;
    });
  });
}

// ── HTML escape ────────────────────────────────────────────────────────────
function esc(s){ return String(s==null?'':s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;'); }

// ── Hover / focus wiring ───────────────────────────────────────────────────
function wireInteractions(root){
  root.querySelectorAll('[data-hover]').forEach(el=>{
    const orig=el.style.background;
    el.addEventListener('mouseenter',()=>el.style.background=el.dataset.hover);
    el.addEventListener('mouseleave',()=>el.style.background=orig);
  });
  root.querySelectorAll('[data-focus]').forEach(el=>{
    el.addEventListener('focus',()=>el.style.outline=el.dataset.focus);
    el.addEventListener('blur', ()=>el.style.outline='');
  });
}

// ── Main render ────────────────────────────────────────────────────────────
function render(){
  const app=document.getElementById('app');
  const active=document.activeElement;
  const fid=active&&active.dataset&&active.dataset.fid?active.dataset.fid:null;
  const scrolls={};
  app.querySelectorAll('[data-scroll-id]').forEach(el=>scrolls[el.dataset.scrollId]=el.scrollTop);

  let html='';
  const s=state.screen;
  if(s==='login')              html=renderLogin();
  else if(s==='student-enter') html=renderStudentEnter();
  else if(s==='student-exam')  html=renderStudentExam();
  else                         html=renderShell();

  app.innerHTML=html;
  wireInteractions(app);

  if(fid){
    const el=app.querySelector('[data-fid="'+fid+'"]');
    if(el){ el.focus(); if(el.tagName==='INPUT'||el.tagName==='TEXTAREA'){const len=el.value.length;try{el.setSelectionRange(len,len);}catch(e){}} }
  }
  Object.entries(scrolls).forEach(([id,top])=>{ const el=app.querySelector('[data-scroll-id="'+id+'"]'); if(el)el.scrollTop=top; });
  repaintIcons();
}

// ── Login page ─────────────────────────────────────────────────────────────
function renderLogin(){
  return '<div style="min-height:100vh;background:var(--bg-page);display:flex;flex-direction:column;align-items:center;justify-content:center;padding:24px;">'
    +'<div style="position:absolute;top:16px;right:16px;">'+themeBtn()+'</div>'
    +'<div style="background:var(--bg-card);border-radius:20px;box-shadow:var(--shadow-l);padding:48px 40px;width:100%;max-width:420px;border:1px solid var(--bdr);">'
    +'<div style="text-align:center;margin-bottom:36px;">'
    +'<div style="display:inline-flex;align-items:center;justify-content:center;margin-bottom:16px;">'
    +'<img src="assets/ovec-logo.svg" style="width:72px;height:72px;">'
    +'</div>'
    +'<h1 style="font-size:22px;font-weight:700;color:var(--txt-brand);letter-spacing:-0.5px;">EXAMIS</h1>'
    +'<p style="font-size:13px;color:var(--txt-3);margin-top:4px;">วิทยาลัยอาชีวศึกษาร้อยเอ็ด</p>'
    +'</div>'
    +(state.loginErr?'<div style="background:var(--st-red-bg);color:var(--st-red-c);border:1px solid var(--st-red-bdr);border-radius:10px;padding:12px 16px;font-size:13px;font-weight:600;text-align:center;margin-bottom:20px;">'+esc(state.loginErr)+'</div>':'')
    +'<div style="margin-bottom:16px;">'
    +'<label style="display:block;font-size:13px;font-weight:600;color:var(--txt-2);margin-bottom:6px;">ชื่อผู้ใช้งาน</label>'
    +'<input data-fid="loginU" data-field="loginU" value="'+esc(state._loginU)+'" placeholder="กรอกชื่อผู้ใช้" style="width:100%;padding:12px 14px;border:1.5px solid var(--bdr);border-radius:10px;font-size:15px;background:var(--bg-input);color:var(--txt-1);outline:none;" data-focus="2px solid var(--txt-brand)">'
    +'</div>'
    +'<div style="margin-bottom:28px;">'
    +'<label style="display:block;font-size:13px;font-weight:600;color:var(--txt-2);margin-bottom:6px;">รหัสผ่าน</label>'
    +'<input type="password" data-fid="loginP" data-field="loginP" value="'+esc(state._loginP)+'" placeholder="กรอกรหัสผ่าน" style="width:100%;padding:12px 14px;border:1.5px solid var(--bdr);border-radius:10px;font-size:15px;background:var(--bg-input);color:var(--txt-1);outline:none;" data-focus="2px solid var(--txt-brand)">'
    +'</div>'
    +'<button data-act="login" '+(state.loginLoading?'disabled':'')+' style="width:100%;padding:14px;background:linear-gradient(135deg,#7B1C1C,#B91C1C);color:#fff;border:none;border-radius:12px;font-size:16px;font-weight:700;cursor:pointer;box-shadow:0 4px 16px rgba(185,28,28,0.35);">'
    +(state.loginLoading?'<span class="msi" style="animation:spin 1s linear infinite;font-size:20px;">refresh</span>':'เข้าสู่ระบบ')
    +'</button>'
    +'<div style="text-align:center;margin-top:16px;font-size:11px;color:var(--txt-4);letter-spacing:0.5px;">'+(window.APP_VERSION||'v0.dev')+'</div>'
    +'</div>'
    +'</div>';
}

// ── Sidebar theme toggle (full width, for uncollapsed sidebar) ─────────────
function renderThemeToggleSb(){
  const t=getTheme(),icon=THEME_ICON[t],label=THEME_LABEL[t];
  return '<button data-act="cycleTheme" title="'+label+'" style="display:flex;align-items:center;gap:8px;padding:10px 14px;background:rgba(255,255,255,0.08);border:1px solid rgba(255,255,255,0.15);border-radius:10px;color:rgba(255,255,255,0.8);font-size:13px;font-weight:600;cursor:pointer;width:100%;" data-hover="rgba(255,255,255,0.2)">'
    +'<span class="msi" style="font-size:18px;">'+icon+'</span>'
    +'<span>'+label+'</span>'
    +'</button>';
}

// ── App shell ──────────────────────────────────────────────────────────────
function renderShell(){
  const s=state, r=s.role;
  const navItems=NAV[r]||[];
  const collapsed=s.sbCollapsed;
  const sbW=collapsed?68:240;
  let navHtml='';
  navItems.forEach(item=>{
    const isActive=item.screen?s.screen===item.screen:s.teacherTab===item.tab;
    const act=item.screen?'navScreen':'navTab';
    const arg=item.screen||item.tab;
    navHtml+='<button data-act="'+act+'" data-arg="'+arg+'" title="'+item.label+'" style="width:100%;display:flex;align-items:center;gap:12px;padding:'+(collapsed?'12px 0':'12px 20px')+';justify-content:'+(collapsed?'center':'flex-start')+';background:'+(isActive?'rgba(255,255,255,0.18)':'transparent')+';border:none;color:rgba(255,255,255,'+(isActive?'1':'0.7')+');font-size:14px;font-weight:'+(isActive?'700':'500')+';cursor:pointer;border-radius:0;transition:background .15s;white-space:nowrap;" data-hover="rgba(255,255,255,0.1)">'
      +'<span class="msi" style="font-size:22px;flex-shrink:0;">'+item.icon+'</span>'
      +(!collapsed?'<span>'+item.label+'</span>':'')
      +'</button>';
  });
  return '<div style="display:flex;height:100vh;overflow:hidden;">'
    +'<aside style="width:'+sbW+'px;min-width:'+sbW+'px;background:linear-gradient(180deg,var(--sb-from) 0%,var(--sb-to) 100%);display:flex;flex-direction:column;transition:width .25s;overflow:hidden;">'
    +'<div style="padding:'+(collapsed?'20px 0':'20px 20px')+';display:flex;align-items:center;gap:10px;border-bottom:1px solid rgba(255,255,255,0.1);justify-content:'+(collapsed?'center':'flex-start')+'">'
    +(collapsed?'<img src="assets/ovec-logo.svg" style="width:36px;height:36px;flex-shrink:0;">':'<img src="assets/ovec-logo.svg" style="width:36px;height:36px;flex-shrink:0;"><span style="font-weight:700;font-size:17px;color:#fff;letter-spacing:-0.5px;white-space:nowrap;">EXAMIS</span>')
    +'</div>'
    +'<nav style="flex:1;padding:16px 0;overflow-y:auto;">'+navHtml+'</nav>'
    +'<div style="padding:'+(collapsed?'16px 0':'16px 20px')+';border-top:1px solid rgba(255,255,255,0.1);display:flex;flex-direction:column;gap:8px;align-items:'+(collapsed?'center':'flex-start')+'">'
    +'<button data-act="logout" style="display:flex;align-items:center;gap:8px;padding:'+(collapsed?'10px':'10px 14px')+';background:rgba(255,255,255,0.12);border:none;border-radius:10px;color:rgba(255,255,255,0.8);font-size:13px;font-weight:600;cursor:pointer;justify-content:'+(collapsed?'center':'flex-start')+';width:'+(collapsed?'44px':'100%')+';" data-hover="rgba(255,255,255,0.2)" title="ออกจากระบบ">'
    +'<span class="msi" style="font-size:20px;">logout</span>'
    +(!collapsed?'<span>ออกจากระบบ</span>':'')
    +'</button>'
    +(!collapsed?'<div style="text-align:center;width:100%;font-size:10px;color:rgba(255,255,255,0.25);letter-spacing:0.5px;padding-top:4px;">'+(window.APP_VERSION||'v0.dev')+'</div>':'')
    +'</div>'
    +'</aside>'
    +'<div style="flex:1;display:flex;flex-direction:column;min-width:0;overflow:hidden;">'
    +'<header style="background:var(--topbar-bg);border-bottom:1px solid var(--topbar-bdr);padding:0 24px;height:60px;display:flex;align-items:center;gap:12px;box-shadow:var(--shadow-s);flex-shrink:0;">'
    +'<button data-act="toggleSb" style="width:36px;height:36px;border:none;background:transparent;border-radius:8px;cursor:pointer;display:flex;align-items:center;justify-content:center;color:var(--txt-3);" data-hover="var(--bg-hover)">'
    +'<span class="msi" style="font-size:22px;">'+(collapsed?'menu_open':'menu')+'</span>'
    +'</button>'
    +'<span style="font-weight:700;font-size:16px;color:var(--txt-1);">'+esc(PAGE_TITLES[state.screen||r]||'')+'</span>'
    +'<div style="flex:1;"></div>'
    +themeBtn('margin-right:8px;')
    +'<div style="display:flex;align-items:center;gap:8px;">'
    +'<div style="width:34px;height:34px;border-radius:50%;background:var(--st-red-bg);display:flex;align-items:center;justify-content:center;">'
    +'<span class="msi" style="font-size:18px;color:var(--txt-brand);">person</span>'
    +'</div>'
    +'<div>'
    +'<div style="font-size:13px;font-weight:600;color:var(--txt-1);">'+esc(s.userFullName)+'</div>'
    +'<div style="font-size:11px;color:var(--txt-3);">'+esc(ROLE_LABELS[r]||r)+'</div>'
    +'</div>'
    +'</div>'
    +'</header>'
    +'<main style="flex:1;overflow-y:auto;padding:24px;" data-scroll-id="main">'+renderContent()+'</main>'
    +'</div>'
    +'</div>';
}

function renderContent(){
  const r=state.screen||state.role;
  if(r==='admin')      return renderAdmin();
  if(r==='deputy')     return renderDeputy();
  if(r==='manager')    return renderManager();
  if(r==='rooms')      return renderRooms();
  if(r==='settings')   return renderSettings();
  if(r==='teacher')    return renderTeacher();
  if(r==='supervisor') return renderSupervisor();
  return '';
}

// ── Shared helpers ─────────────────────────────────────────────────────────
function statBadge(label, bg, color){
  return '<span style="display:inline-flex;align-items:center;padding:3px 10px;border-radius:99px;font-size:12px;font-weight:600;background:'+bg+';color:'+color+';">'+label+'</span>';
}
function card(content, extraStyle){
  return '<div style="background:var(--bg-card);border-radius:16px;border:1px solid var(--bdr);box-shadow:var(--shadow-s);padding:24px;'+(extraStyle||'')+'">'+content+'</div>';
}
function statCard(icon, label, value, color){
  return '<div style="background:var(--bg-card);border-radius:16px;border:1px solid var(--bdr);box-shadow:var(--shadow-s);padding:24px;display:flex;align-items:center;gap:16px;">'
    +'<div style="width:52px;height:52px;border-radius:14px;background:'+color+'22;display:flex;align-items:center;justify-content:center;flex-shrink:0;">'
    +'<span class="msi" style="font-size:26px;color:'+color+';">'+icon+'</span>'
    +'</div>'
    +'<div>'
    +'<div style="font-size:28px;font-weight:700;color:var(--txt-1);">'+value+'</div>'
    +'<div style="font-size:13px;color:var(--txt-3);">'+label+'</div>'
    +'</div>'
    +'</div>';
}
function sessStatusBadge(status){
  const m=ST_MAP[status]||ST_MAP.done;
  return statBadge(m[2],m[0],m[1]);
}
function pageTitle(title, sub){
  return '<div style="margin-bottom:24px;">'
    +'<h2 style="font-size:20px;font-weight:700;color:var(--txt-1);">'+title+'</h2>'
    +(sub?'<p style="font-size:13px;color:var(--txt-3);margin-top:4px;">'+sub+'</p>':'')
    +'</div>';
}
function inputStyle(extra){ return 'width:100%;padding:10px 12px;border:1.5px solid var(--bdr);border-radius:10px;font-size:14px;background:var(--bg-input);color:var(--txt-1);outline:none;'+(extra||''); }
function btnPrimary(act, arg, label, icon, extra){
  return '<button data-act="'+act+'" '+(arg!==undefined?'data-arg="'+arg+'"':'')+' style="display:inline-flex;align-items:center;gap:8px;padding:10px 18px;background:var(--txt-brand);color:#fff;border:none;border-radius:10px;font-size:14px;font-weight:600;cursor:pointer;'+(extra||'')+'"><span class="msi" style="font-size:18px;">'+icon+'</span>'+label+'</button>';
}
function btnDanger(act, arg, label, extra){
  return '<button data-act="'+act+'" data-arg="'+arg+'" style="display:inline-flex;align-items:center;gap:6px;padding:7px 12px;background:var(--st-red-bg);color:var(--st-red-c);border:1px solid var(--st-red-bdr);border-radius:8px;font-size:13px;font-weight:600;cursor:pointer;'+(extra||'')+'"><span class="msi" style="font-size:16px;">delete</span>'+label+'</button>';
}
function btnGhost(act, arg, label, icon, extra){
  return '<button data-act="'+act+'" data-arg="'+arg+'" style="display:inline-flex;align-items:center;gap:6px;padding:7px 12px;background:var(--bg-card2);color:var(--txt-2);border:1px solid var(--bdr);border-radius:8px;font-size:13px;font-weight:600;cursor:pointer;'+(extra||'')+'" data-hover="var(--bg-hover)"><span class="msi" style="font-size:16px;">'+icon+'</span>'+label+'</button>';
}
function tabs(items, activeVal, actName){
  return '<div style="display:flex;gap:4px;border-bottom:2px solid var(--bdr);margin-bottom:20px;">'
    +items.map(([val,label])=>'<button data-act="'+actName+'" data-arg="'+val+'" style="padding:10px 18px;border:none;background:transparent;font-size:14px;font-weight:600;cursor:pointer;color:'+(activeVal===val?'var(--txt-brand)':'var(--txt-3)')+';border-bottom:2px solid '+(activeVal===val?'var(--txt-brand)':'transparent')+';margin-bottom:-2px;border-radius:0;" data-hover="var(--bg-hover)">'+label+'</button>').join('')
    +'</div>';
}

// ── Admin ──────────────────────────────────────────────────────────────────
function renderAdmin(){
  const users=state.users||[];
  const statsHtml='<div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(200px,1fr));gap:16px;margin-bottom:24px;">'
    +statCard('group','ผู้ใช้ทั้งหมด',users.length,'#6366F1')
    +statCard('person_check','ใช้งานได้',users.filter(u=>u.status==='active').length,'#10B981')
    +statCard('person_off','ถูกระงับ',users.filter(u=>u.status==='inactive').length,'#EF4444')
    +'</div>';

  let tableRows='';
  users.forEach(u=>{
    const rc=ROLE_COLORS[ROLE_LABELS[u.role]]||['var(--st-gray-bg)','var(--st-gray-c)'];
    tableRows+='<tr style="border-bottom:1px solid var(--bdr-s);" data-hover="var(--bg-hover)">'
      +'<td style="padding:14px 16px;font-weight:600;color:var(--txt-1);">'+esc(u.full_name)+'</td>'
      +'<td style="padding:14px 16px;color:var(--txt-3);font-size:13px;font-family:\'IBM Plex Mono\',monospace;">'+esc(u.username)+'</td>'
      +'<td style="padding:14px 16px;">'+statBadge(ROLE_LABELS[u.role]||u.role,rc[0],rc[1])+'</td>'
      +'<td style="padding:14px 16px;font-size:13px;color:var(--txt-3);">'+(u.department||'—')+'</td>'
      +'<td style="padding:14px 16px;">'
      +'<button data-act="toggleStatus" data-arg="'+u.id+'" style="display:inline-flex;align-items:center;gap:5px;padding:5px 10px;border-radius:8px;border:1px solid var(--bdr);font-size:12px;font-weight:600;cursor:pointer;background:'+(u.status==='active'?'var(--st-green-bg)':'var(--st-red-bg)')+';color:'+(u.status==='active'?'var(--st-green-c)':'var(--st-red-c)')+';">'
      +'<span class="msi" style="font-size:14px;">'+(u.status==='active'?'check_circle':'block')+'</span>'+(u.status==='active'?'ใช้งาน':'ระงับ')+'</button>'
      +'</td>'
      +'<td style="padding:14px 16px;">'+btnDanger('deleteUser',u.id,'')+'</td>'
      +'</tr>';
  });

  let addForm='';
  if(state.showAddUser){
    addForm='<div style="background:var(--bg-form-add);border:1px solid var(--bdr);border-radius:14px;padding:24px;margin-bottom:24px;">'
      +'<h3 style="font-size:15px;font-weight:700;color:var(--txt-brand);margin-bottom:16px;">เพิ่มผู้ใช้ใหม่</h3>'
      +'<div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">'
      +fld('ชื่อ-นามสกุล','text','add_full_name',state.addForm.full_name,'',true)
      +fld('ชื่อผู้ใช้ (username)','text','add_username',state.addForm.username,'font-family:\'IBM Plex Mono\',monospace;',true)
      +'<div>'
      +'<label style="display:block;font-size:13px;font-weight:600;color:var(--txt-2);margin-bottom:6px;">บทบาท</label>'
      +'<select data-field="add_role" style="'+inputStyle()+'">'
      +Object.entries(ROLE_LABELS).filter(([k])=>k!=='student').map(([k,v])=>'<option value="'+k+'"'+(state.addForm.role===k?' selected':'')+'>'+v+'</option>').join('')
      +'</select></div>'
      +fld('แผนก / ห้อง','text','add_department',state.addForm.department||'','')
      +fld('รหัสผ่าน','password','add_password',state.addForm.password,'',true)
      +'</div>'
      +'<div style="display:flex;gap:10px;margin-top:16px;">'
      +btnPrimary('saveUser',undefined,'บันทึก','save')
      +'<button data-act="closeAddUser" style="padding:10px 18px;background:var(--bg-card2);color:var(--txt-2);border:1px solid var(--bdr);border-radius:10px;font-size:14px;font-weight:600;cursor:pointer;">ยกเลิก</button>'
      +'</div>'
      +'</div>';
  }

  return pageTitle('จัดการผู้ใช้งาน')
    +statsHtml
    +'<div style="display:flex;justify-content:flex-end;margin-bottom:16px;">'
    +(!state.showAddUser?btnPrimary('openAddUser',undefined,'เพิ่มผู้ใช้ใหม่','person_add'):'')
    +'</div>'
    +addForm
    +card('<table style="width:100%;border-collapse:collapse;">'
      +'<thead><tr style="border-bottom:2px solid var(--bdr);">'
      +'<th style="padding:12px 16px;text-align:left;font-size:13px;color:var(--txt-3);font-weight:600;">ชื่อ-นามสกุล</th>'
      +'<th style="padding:12px 16px;text-align:left;font-size:13px;color:var(--txt-3);font-weight:600;">ชื่อผู้ใช้</th>'
      +'<th style="padding:12px 16px;text-align:left;font-size:13px;color:var(--txt-3);font-weight:600;">บทบาท</th>'
      +'<th style="padding:12px 16px;text-align:left;font-size:13px;color:var(--txt-3);font-weight:600;">แผนก</th>'
      +'<th style="padding:12px 16px;text-align:left;font-size:13px;color:var(--txt-3);font-weight:600;">สถานะ</th>'
      +'<th style="padding:12px 16px;text-align:left;font-size:13px;color:var(--txt-3);font-weight:600;"></th>'
      +'</tr></thead>'
      +'<tbody>'+tableRows+'</tbody>'
      +'</table>');
}

function fld(label, type, field, value, extraInputStyle, required){
  return '<div>'
    +'<label style="display:block;font-size:13px;font-weight:600;color:var(--txt-2);margin-bottom:6px;">'+label+(required?'<span style="color:var(--st-red-c);margin-left:2px;">*</span>':'')+'</label>'
    +'<input type="'+type+'" data-fid="'+field+'" data-field="'+field+'" value="'+esc(value)+'" style="'+inputStyle(extraInputStyle)+'" data-focus="2px solid var(--txt-brand)">'
    +'</div>';
}

// ── Deputy ─────────────────────────────────────────────────────────────────
function renderDeputy(){
  const st=state.dashStats||{};
  const sessions=state.dashSessions||[];
  let sessRows='';
  sessions.slice(0,10).forEach(s=>{
    sessRows+='<tr style="border-bottom:1px solid var(--bdr-s);">'
      +'<td style="padding:12px 16px;font-weight:600;color:var(--txt-1);">'+esc(s.title||s.exam_title||'—')+'</td>'
      +'<td style="padding:12px 16px;font-size:13px;color:var(--txt-3);">'+(s.room_code?esc((s.building_name?s.building_name+' ':'')+s.room_code):esc(s.room||'—'))+'</td>'
      +'<td style="padding:12px 16px;font-size:13px;color:var(--txt-3);">'+(s.exam_date||'—')+'</td>'
      +'<td style="padding:12px 16px;">'+sessStatusBadge(s.status)+'</td>'
      +'<td style="padding:12px 16px;font-size:13px;color:var(--txt-2);">'+(s.student_count||0)+' คน</td>'
      +'</tr>';
  });
  return pageTitle('ภาพรวมการสอบ','สรุปข้อมูลการสอบปลายภาคทั้งหมด')
    +'<div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(200px,1fr));gap:16px;margin-bottom:28px;">'
    +statCard('calendar_month','การสอบทั้งหมด',st.total_sessions||0,'#6366F1')
    +statCard('play_circle','กำลังสอบ',st.active_sessions||0,'#10B981')
    +statCard('pending','รอสอบ',st.upcoming_sessions||0,'#3B82F6')
    +statCard('task_alt','เสร็จสิ้น',st.done_sessions||0,'#6B7280')
    +statCard('group','นักศึกษาลงทะเบียน',st.total_students||0,'#8B5CF6')
    +statCard('description','ชุดข้อสอบ',st.total_papers||0,'#F59E0B')
    +'</div>'
    +card('<div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:16px;">'
      +'<h3 style="font-size:15px;font-weight:700;color:var(--txt-1);">รายการสอบล่าสุด</h3>'
      +'</div>'
      +'<table style="width:100%;border-collapse:collapse;">'
      +'<thead><tr style="border-bottom:2px solid var(--bdr);">'
      +'<th style="padding:10px 16px;text-align:left;font-size:13px;color:var(--txt-3);font-weight:600;">ชุดข้อสอบ</th>'
      +'<th style="padding:10px 16px;text-align:left;font-size:13px;color:var(--txt-3);font-weight:600;">ห้อง</th>'
      +'<th style="padding:10px 16px;text-align:left;font-size:13px;color:var(--txt-3);font-weight:600;">วันที่</th>'
      +'<th style="padding:10px 16px;text-align:left;font-size:13px;color:var(--txt-3);font-weight:600;">สถานะ</th>'
      +'<th style="padding:10px 16px;text-align:left;font-size:13px;color:var(--txt-3);font-weight:600;">ผู้เข้าสอบ</th>'
      +'</tr></thead>'
      +'<tbody>'+sessRows+'</tbody>'
      +'</table>');
}

// ── Manager ────────────────────────────────────────────────────────────────
function renderManager(){
  const editId=state.editingSessId;
  let sessRows='';
  (state.sessions||[]).forEach(s=>{
    const isEditing=editId===s.id;
    const statusActions=s.status==='upcoming'
      ?'<button data-act="updateSessStatus" data-arg="'+s.id+':active" style="padding:5px 10px;font-size:12px;font-weight:600;cursor:pointer;border-radius:7px;border:1px solid var(--st-green-bdr);background:var(--st-green-bg);color:var(--st-green-c);">เริ่มสอบ</button>'
      :s.status==='active'
      ?'<button data-act="updateSessStatus" data-arg="'+s.id+':done" style="padding:5px 10px;font-size:12px;font-weight:600;cursor:pointer;border-radius:7px;border:1px solid var(--bdr);background:var(--st-gray-bg);color:var(--st-gray-c);">สิ้นสุด</button>'
      :'';
    sessRows+='<tr style="border-bottom:1px solid var(--bdr-s);background:'+(isEditing?'var(--bg-brand-soft)':'transparent')+';outline:'+(isEditing?'2px solid var(--txt-brand)':'')+'">'
      +'<td style="padding:13px 16px;">'
      +'<div style="font-weight:600;color:var(--txt-1);font-size:14px;">'+esc(s.paper_title||s.exam_title||'—')+'</div>'
      +(s.teacher_name?'<div style="font-size:11px;color:var(--txt-4);margin-top:2px;">ครู: '+esc(s.teacher_name)+'</div>':'')
      +'</td>'
      +'<td style="padding:13px 16px;font-size:12px;"><span style="background:var(--bg-brand-soft);color:var(--txt-brand);border-radius:6px;padding:3px 8px;font-weight:600;white-space:nowrap;">'+semLabel(s.semester||'')+'</span></td>'
      +'<td style="padding:13px 16px;font-size:13px;color:var(--txt-3);">'+(s.room_code?esc((s.building_name?s.building_name+' ':'')+s.room_code):esc(s.room||'—'))+'</td>'
      +'<td style="padding:13px 16px;font-size:13px;color:var(--txt-3);">'+(s.exam_date||'—')+'</td>'
      +'<td style="padding:13px 16px;font-size:13px;color:var(--txt-3);">'+(s.start_time||'—')+' – '+(s.end_time||'—')+'</td>'
      +'<td style="padding:13px 16px;">'+sessStatusBadge(s.status)+'</td>'
      +'<td style="padding:13px 16px;font-family:\'IBM Plex Mono\',monospace;font-size:14px;font-weight:700;color:var(--txt-brand);">'+(s.access_code||'—')+'</td>'
      +'<td style="padding:13px 16px;">'
      +'<div style="display:flex;align-items:center;gap:6px;">'
      +statusActions
      +'<button data-act="editSession" data-arg="'+s.id+'" title="แก้ไข" style="width:30px;height:30px;border-radius:7px;border:1px solid var(--bdr);background:'+(isEditing?'var(--txt-brand)':'var(--bg-card)')+';cursor:pointer;display:flex;align-items:center;justify-content:center;color:'+(isEditing?'#fff':'var(--txt-2)')+';" data-hover="var(--bg-hover)"><span class="msi" style="font-size:15px;">edit</span></button>'
      +'</div>'
      +'</td>'
      +'</tr>';
  });

  let sessForm='';
  if(state.showNewSess){
    const papers=state.examPapers||[];
    const isEdit=!!editId;
    sessForm='<div style="background:var(--bg-form-add);border:1.5px solid '+(isEdit?'var(--txt-brand)':'var(--bdr)')+';border-radius:14px;padding:24px;margin-bottom:24px;">'
      +'<div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:16px;">'
      +'<h3 style="font-size:15px;font-weight:700;color:var(--txt-brand);">'+(isEdit?'แก้ไขรายการสอบ':'สร้างรายการสอบใหม่')+'</h3>'
      +(isEdit?'<span style="font-size:12px;color:var(--txt-3);">รหัสเดิม: <b style="font-family:\'IBM Plex Mono\',monospace;color:var(--txt-brand);">'+(state.sessions.find(x=>x.id===editId)||{}).access_code+'</b></span>':'')
      +'</div>'
      +(()=>{
        const summerOn=state.settings.summer_term_enabled==='1';
        const sems=generateSemesters(summerOn);
        const semOpts='<option value="">-- เลือกภาคการศึกษา --</option>'
          +sems.map(s=>'<option value="'+s+'"'+(state.sessForm.semester===s?' selected':'')+'>'+semLabel(s)+'</option>').join('');
        return '<div style="margin-bottom:12px;"><label style="display:block;font-size:13px;font-weight:600;color:var(--txt-2);margin-bottom:6px;">ภาคการศึกษา <span style="color:var(--st-red-c);">*</span></label>'
          +'<select data-field="sess_semester" style="'+inputStyle()+'">'+semOpts+'</select></div>';
      })()
      +'<div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:16px;">'
      +'<div><label style="display:block;font-size:13px;font-weight:600;color:var(--txt-2);margin-bottom:6px;">ชุดข้อสอบ <span style="color:var(--st-red-c);">*</span></label>'
      +(papers.length===0
        ?'<div style="padding:10px 12px;border:1.5px solid var(--bdr);border-radius:10px;font-size:13px;color:var(--txt-4);background:var(--bg-input);">ยังไม่มีชุดข้อสอบที่เผยแพร่</div>'
        :'<select data-field="sess_exam_paper_id" style="'+inputStyle()+'">'
        +'<option value="">-- เลือกชุดข้อสอบ --</option>'
        +papers.map(p=>'<option value="'+p.id+'"'+(state.sessForm.exam_paper_id==p.id?' selected':'')+'>'+esc(p.title)+(p.teacher_name?' ('+esc(p.teacher_name)+')':'')+'  · '+p.q_count+' ข้อ</option>').join('')
        +'</select>')
      +'</div>'
      +(()=>{
        const blds=state.buildings||[], rms=state.rooms||[];
        let opts='<option value="">-- เลือกห้องสอบ --</option>';
        blds.forEach(b=>{
          const br=rms.filter(r=>r.building_id==b.id);
          if(!br.length)return;
          opts+='<optgroup label="'+esc(b.name)+'">';
          br.forEach(r=>{ opts+='<option value="'+r.id+'"'+(state.sessForm.room_id==r.id?' selected':'')+'>'+esc(r.room_code)+(r.capacity?' ('+r.capacity+' ที่นั่ง)':'')+(r.description?' – '+esc(r.description):'')+'</option>'; });
          opts+='</optgroup>';
        });
        const noRooms=!rms.length;
        return '<div><label style="display:block;font-size:13px;font-weight:600;color:var(--txt-2);margin-bottom:6px;">ห้องสอบ <span style="color:var(--st-red-c);">*</span></label>'
          +(noRooms
            ?'<div style="padding:10px 12px;border:1.5px solid var(--bdr);border-radius:10px;font-size:13px;color:var(--txt-4);background:var(--bg-input);">ยังไม่มีห้องสอบ — <a data-act="navScreen" data-arg="rooms" href="#" style="color:var(--txt-brand);">เพิ่มห้องสอบ</a></div>'
            :'<select data-field="sess_room_id" style="'+inputStyle()+'">'+opts+'</select>')
          +'</div>';
      })()
      +fld('วันที่สอบ','date','sess_exam_date',state.sessForm.exam_date||'')
      +fld('เวลาเริ่ม','time','sess_start_time',state.sessForm.start_time||'')
      +fld('เวลาสิ้นสุด','time','sess_end_time',state.sessForm.end_time||'')
      +fld('เวลาสอบ (นาที)','number','sess_time_limit_minutes',state.sessForm.time_limit_minutes||90)
      +'</div>'
      +'<div style="display:flex;gap:10px;">'
      +btnPrimary('saveSession',undefined,isEdit?'บันทึกการแก้ไข':'บันทึก',isEdit?'check':'save')
      +'<button data-act="closeNewSess" style="padding:10px 18px;background:var(--bg-card2);color:var(--txt-2);border:1px solid var(--bdr);border-radius:10px;font-size:14px;font-weight:600;cursor:pointer;">ยกเลิก</button>'
      +'</div>'
      +'</div>';
  }

  return pageTitle('จัดการตารางสอบ')
    +'<div style="display:flex;justify-content:flex-end;margin-bottom:16px;">'
    +(!state.showNewSess?btnPrimary('openNewSess',undefined,'สร้างรายการสอบ','add'):'')
    +'</div>'
    +sessForm
    +card('<table style="width:100%;border-collapse:collapse;">'
      +'<thead><tr style="border-bottom:2px solid var(--bdr);">'
      +'<th style="padding:10px 16px;text-align:left;font-size:13px;color:var(--txt-3);font-weight:600;">ชุดข้อสอบ</th>'
      +'<th style="padding:10px 16px;text-align:left;font-size:13px;color:var(--txt-3);font-weight:600;">ภาค</th>'
      +'<th style="padding:10px 16px;text-align:left;font-size:13px;color:var(--txt-3);font-weight:600;">ห้อง</th>'
      +'<th style="padding:10px 16px;text-align:left;font-size:13px;color:var(--txt-3);font-weight:600;">วันที่</th>'
      +'<th style="padding:10px 16px;text-align:left;font-size:13px;color:var(--txt-3);font-weight:600;">เวลา</th>'
      +'<th style="padding:10px 16px;text-align:left;font-size:13px;color:var(--txt-3);font-weight:600;">สถานะ</th>'
      +'<th style="padding:10px 16px;text-align:left;font-size:13px;color:var(--txt-3);font-weight:600;">รหัสเข้าห้อง</th>'
      +'<th style="padding:10px 16px;text-align:left;font-size:13px;color:var(--txt-3);font-weight:600;"></th>'
      +'</tr></thead>'
      +'<tbody>'+sessRows+'</tbody>'
      +'</table>');
}

// ── Settings ───────────────────────────────────────────────────────────────
function renderSettings(){
  const summerOn=state.settings.summer_term_enabled==='1';
  const toggleBtn='<button data-act="toggleSummerTerm" style="display:inline-flex;align-items:center;gap:8px;padding:10px 20px;border-radius:10px;border:1.5px solid '+(summerOn?'var(--st-green-bdr)':'var(--bdr)')+';background:'+(summerOn?'var(--st-green-bg)':'var(--bg-card2)')+';color:'+(summerOn?'var(--st-green-c)':'var(--txt-3)')+';font-size:14px;font-weight:700;cursor:pointer;transition:all .15s;">'
    +'<span class="msi" style="font-size:20px;">'+(summerOn?'toggle_on':'toggle_off')+'</span>'
    +(summerOn?'เปิดใช้งาน':'ปิดใช้งาน')
    +'</button>';
  const semPreview=(()=>{
    const sems=generateSemesters(summerOn);
    return sems.slice(0,6).map(s=>'<span style="display:inline-flex;align-items:center;padding:4px 12px;border-radius:99px;font-size:12px;font-weight:600;background:var(--bg-brand-soft);color:var(--txt-brand);border:1px solid var(--bdr);margin:3px;">'+semLabel(s)+'</span>').join('')+'<span style="font-size:12px;color:var(--txt-4);margin-left:6px;">+ ย้อนหลัง 3 ปี</span>';
  })();
  return pageTitle('ตั้งค่าระบบ','ปรับการทำงานของระบบจัดการสอบ')
    +card('<h3 style="font-size:15px;font-weight:700;color:var(--txt-1);margin-bottom:20px;display:flex;align-items:center;gap:8px;"><span class="msi" style="font-size:20px;color:var(--txt-brand);">school</span>ภาคการศึกษา</h3>'
      +'<div style="background:var(--bg-card2);border:1px solid var(--bdr);border-radius:12px;padding:20px;margin-bottom:16px;">'
      +'<div style="display:flex;align-items:flex-start;justify-content:space-between;gap:16px;">'
      +'<div>'
      +'<div style="font-size:14px;font-weight:700;color:var(--txt-1);margin-bottom:6px;">ภาคฤดูร้อน (S)</div>'
      +'<div style="font-size:13px;color:var(--txt-3);line-height:1.6;">เปิดใช้งานเพื่อให้มีภาค S/ปี พ.ศ. ในตัวเลือกการสร้างรายการสอบ<br>และในรายงานการใช้ห้องสอบ</div>'
      +'</div>'
      +toggleBtn
      +'</div>'
      +'</div>'
      +'<div style="margin-bottom:8px;font-size:13px;font-weight:600;color:var(--txt-3);">ตัวเลือกภาคที่จะแสดงในแบบฟอร์ม:</div>'
      +'<div style="display:flex;flex-wrap:wrap;gap:0;">'+semPreview+'</div>'
    );
}

// ── Rooms Management ───────────────────────────────────────────────────────
function renderRooms(){
  const activeTab=state.roomsTab||'buildings';
  const tabBtn=(id,label,icon)=>'<button data-act="roomsTab" data-arg="'+id+'" style="display:inline-flex;align-items:center;gap:6px;padding:8px 16px;border-radius:8px;font-size:13px;font-weight:600;cursor:pointer;border:none;background:'+(activeTab===id?'var(--txt-brand)':'transparent')+';color:'+(activeTab===id?'#fff':'var(--txt-3)')+';"><span class="msi" style="font-size:16px;">'+icon+'</span>'+label+'</button>';

  const tabBar='<div style="display:flex;gap:4px;background:var(--bg-card2);padding:6px;border-radius:12px;margin-bottom:20px;width:fit-content;">'
    +tabBtn('buildings','อาคาร','apartment')
    +tabBtn('rooms','ห้องสอบ','meeting_room')
    +tabBtn('report','รายงานการใช้ห้อง','table_chart')
    +'</div>';

  // ── Buildings tab ──────────────────────────────────────────────────────
  if(activeTab==='buildings'){
    const isEdit=!!state.editingBuildingId;
    const blds=state.buildings||[];
    const form='<div style="background:var(--bg-form-add);border:1.5px solid '+(isEdit?'var(--txt-brand)':'var(--bdr)')+';border-radius:14px;padding:20px;margin-bottom:20px;">'
      +'<h3 style="font-size:14px;font-weight:700;color:var(--txt-brand);margin-bottom:14px;">'+(isEdit?'แก้ไขอาคาร':'เพิ่มอาคารใหม่')+'</h3>'
      +'<div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:14px;">'
      +fld('ชื่ออาคาร *','text','bld_name',state.buildingForm.name||'','placeholder="เช่น อาคาร 1 หรือ อาคารเรียน A"')
      +fld('หมายเหตุ','text','bld_description',state.buildingForm.description||'','placeholder="ข้อมูลเพิ่มเติม (ไม่จำเป็น)"')
      +'</div>'
      +'<div style="display:flex;gap:10px;">'
      +btnPrimary('saveBuilding',undefined,isEdit?'บันทึกการแก้ไข':'เพิ่มอาคาร',isEdit?'check':'add')
      +(isEdit?'<button data-act="cancelEditBuilding" style="padding:10px 18px;background:var(--bg-card2);color:var(--txt-2);border:1px solid var(--bdr);border-radius:10px;font-size:14px;font-weight:600;cursor:pointer;">ยกเลิก</button>':'')
      +'</div>'
      +'</div>';

    let rows='';
    blds.forEach(b=>{
      const isEditing=state.editingBuildingId===b.id;
      rows+='<tr style="border-bottom:1px solid var(--bdr-s);background:'+(isEditing?'var(--bg-brand-soft)':'transparent')+'">'
        +'<td style="padding:13px 16px;font-weight:600;color:var(--txt-1);">'+esc(b.name)+'</td>'
        +'<td style="padding:13px 16px;font-size:13px;color:var(--txt-3);">'+(b.description?esc(b.description):'—')+'</td>'
        +'<td style="padding:13px 16px;text-align:center;"><span style="background:var(--st-blue-bg);color:var(--st-blue-c);border-radius:6px;padding:3px 10px;font-size:12px;font-weight:700;">'+b.room_count+' ห้อง</span></td>'
        +'<td style="padding:13px 16px;"><div style="display:flex;gap:6px;justify-content:flex-end;">'
        +'<button data-act="editBuilding" data-arg="'+b.id+'" style="width:30px;height:30px;border-radius:7px;border:1px solid var(--bdr);background:var(--bg-card);cursor:pointer;display:flex;align-items:center;justify-content:center;color:var(--txt-2);" data-hover="var(--bg-hover)"><span class="msi" style="font-size:15px;">edit</span></button>'
        +btnDanger('deleteBuilding',b.id,'')
        +'</div></td>'
        +'</tr>';
    });
    const table=card('<table style="width:100%;border-collapse:collapse;">'
      +'<thead><tr style="border-bottom:2px solid var(--bdr);">'
      +'<th style="padding:10px 16px;text-align:left;font-size:13px;color:var(--txt-3);font-weight:600;">ชื่ออาคาร</th>'
      +'<th style="padding:10px 16px;text-align:left;font-size:13px;color:var(--txt-3);font-weight:600;">หมายเหตุ</th>'
      +'<th style="padding:10px 16px;text-align:center;font-size:13px;color:var(--txt-3);font-weight:600;">จำนวนห้อง</th>'
      +'<th style="padding:10px 16px;"></th>'
      +'</tr></thead>'
      +'<tbody>'+(rows||'<tr><td colspan="4" style="padding:24px;text-align:center;color:var(--txt-4);font-size:13px;">ยังไม่มีข้อมูลอาคาร</td></tr>')+'</tbody>'
      +'</table>');
    return pageTitle('จัดการห้องสอบ')+tabBar+form+table;
  }

  // ── Rooms tab ─────────────────────────────────────────────────────────
  if(activeTab==='rooms'){
    const blds=state.buildings||[], rms=state.rooms||[];
    const isEdit=!!state.editingRoomId;
    const form='<div style="background:var(--bg-form-add);border:1.5px solid '+(isEdit?'var(--txt-brand)':'var(--bdr)')+';border-radius:14px;padding:20px;margin-bottom:20px;">'
      +'<h3 style="font-size:14px;font-weight:700;color:var(--txt-brand);margin-bottom:14px;">'+(isEdit?'แก้ไขห้องสอบ':'เพิ่มห้องสอบใหม่')+'</h3>'
      +'<div style="display:grid;grid-template-columns:1fr 1fr 1fr 1fr;gap:12px;margin-bottom:14px;">'
      +'<div><label style="display:block;font-size:13px;font-weight:600;color:var(--txt-2);margin-bottom:6px;">อาคาร *</label>'
      +'<select data-field="rm_building_id" style="'+inputStyle()+'">'
      +'<option value="">-- เลือกอาคาร --</option>'
      +blds.map(b=>'<option value="'+b.id+'"'+(state.roomForm.building_id==b.id?' selected':'')+'>'+esc(b.name)+'</option>').join('')
      +'</select></div>'
      +fld('รหัสห้อง *','text','rm_room_code',state.roomForm.room_code||'','placeholder="เช่น C201"')
      +fld('ความจุ (ที่นั่ง)','number','rm_capacity',state.roomForm.capacity||30)
      +fld('หมายเหตุ','text','rm_description',state.roomForm.description||'','placeholder="ชั้น/ปีก (ไม่จำเป็น)"')
      +'</div>'
      +'<div style="display:flex;gap:10px;">'
      +btnPrimary('saveRoom',undefined,isEdit?'บันทึกการแก้ไข':'เพิ่มห้องสอบ',isEdit?'check':'add')
      +(isEdit?'<button data-act="cancelEditRoom" style="padding:10px 18px;background:var(--bg-card2);color:var(--txt-2);border:1px solid var(--bdr);border-radius:10px;font-size:14px;font-weight:600;cursor:pointer;">ยกเลิก</button>':'')
      +'</div>'
      +'</div>';

    let rows='';
    rms.forEach(r=>{
      const isEditing=state.editingRoomId===r.id;
      rows+='<tr style="border-bottom:1px solid var(--bdr-s);background:'+(isEditing?'var(--bg-brand-soft)':'transparent')+'">'
        +'<td style="padding:13px 16px;font-weight:600;color:var(--txt-1);">'+esc(r.room_code)+'</td>'
        +'<td style="padding:13px 16px;font-size:13px;color:var(--txt-3);">'+esc(r.building_name||'—')+'</td>'
        +'<td style="padding:13px 16px;font-size:13px;color:var(--txt-3);text-align:center;">'+r.capacity+'</td>'
        +'<td style="padding:13px 16px;font-size:13px;color:var(--txt-3);">'+(r.description?esc(r.description):'—')+'</td>'
        +'<td style="padding:13px 16px;"><div style="display:flex;gap:6px;justify-content:flex-end;">'
        +'<button data-act="editRoom" data-arg="'+r.id+'" style="width:30px;height:30px;border-radius:7px;border:1px solid var(--bdr);background:var(--bg-card);cursor:pointer;display:flex;align-items:center;justify-content:center;color:var(--txt-2);" data-hover="var(--bg-hover)"><span class="msi" style="font-size:15px;">edit</span></button>'
        +btnDanger('deleteRoom',r.id,'')
        +'</div></td>'
        +'</tr>';
    });
    const table=card('<table style="width:100%;border-collapse:collapse;">'
      +'<thead><tr style="border-bottom:2px solid var(--bdr);">'
      +'<th style="padding:10px 16px;text-align:left;font-size:13px;color:var(--txt-3);font-weight:600;">รหัสห้อง</th>'
      +'<th style="padding:10px 16px;text-align:left;font-size:13px;color:var(--txt-3);font-weight:600;">อาคาร</th>'
      +'<th style="padding:10px 16px;text-align:center;font-size:13px;color:var(--txt-3);font-weight:600;">ความจุ</th>'
      +'<th style="padding:10px 16px;text-align:left;font-size:13px;color:var(--txt-3);font-weight:600;">หมายเหตุ</th>'
      +'<th style="padding:10px 16px;"></th>'
      +'</tr></thead>'
      +'<tbody>'+(rows||'<tr><td colspan="5" style="padding:24px;text-align:center;color:var(--txt-4);font-size:13px;">ยังไม่มีห้องสอบ</td></tr>')+'</tbody>'
      +'</table>');
    return pageTitle('จัดการห้องสอบ')+tabBar+form+table;
  }

  // ── Report tab ────────────────────────────────────────────────────────
  const report=state.roomReport||[];
  // Group by room
  const roomMap={};
  report.forEach(row=>{
    const key=row.room_id;
    if(!roomMap[key]) roomMap[key]={building_name:row.building_name, room_code:row.room_code, capacity:row.capacity, sessions:[]};
    if(row.session_id) roomMap[key].sessions.push(row);
  });
  const statusBadge=s=>s==='active'?'<span style="background:var(--st-green-bg);color:var(--st-green-c);border-radius:5px;padding:2px 8px;font-size:11px;font-weight:700;">กำลังสอบ</span>'
    :s==='upcoming'?'<span style="background:var(--st-blue-bg);color:var(--st-blue-c);border-radius:5px;padding:2px 8px;font-size:11px;font-weight:700;">รอสอบ</span>'
    :'<span style="background:var(--st-gray-bg);color:var(--st-gray-c);border-radius:5px;padding:2px 8px;font-size:11px;font-weight:700;">เสร็จสิ้น</span>';
  let tRows='';
  Object.values(roomMap).sort((a,b)=>a.building_name.localeCompare(b.building_name)||a.room_code.localeCompare(b.room_code)).forEach(rm=>{
    const sessCount=rm.sessions.length;
    if(!sessCount){
      tRows+='<tr style="border-bottom:1px solid var(--bdr-s);">'
        +'<td style="padding:12px 16px;font-weight:600;color:var(--txt-1);">'+esc(rm.room_code)+'</td>'
        +'<td style="padding:12px 16px;font-size:12px;color:var(--txt-3);">'+esc(rm.building_name)+'</td>'
        +'<td colspan="5" style="padding:12px 16px;font-size:13px;color:var(--txt-4);">ยังไม่มีการสอบ</td></tr>';
    } else {
      rm.sessions.forEach((s,i)=>{
        tRows+='<tr style="border-bottom:1px solid var(--bdr-s);">'
          +(i===0?'<td style="padding:12px 16px;font-weight:600;color:var(--txt-1);" rowspan="'+sessCount+'">'+esc(rm.room_code)+'</td>'
                 +'<td style="padding:12px 16px;font-size:12px;color:var(--txt-3);" rowspan="'+sessCount+'">'+esc(rm.building_name)+'</td>':'')
          +'<td style="padding:12px 16px;font-size:13px;color:var(--txt-2);">'+(s.exam_date||'—')+'</td>'
          +'<td style="padding:12px 16px;font-size:12px;color:var(--txt-3);">'+(s.start_time||'')+'–'+(s.end_time||'')+'</td>'
          +'<td style="padding:12px 16px;font-size:13px;color:var(--txt-1);font-weight:500;">'+esc(s.exam_title||'—')+'</td>'
          +'<td style="padding:12px 16px;font-size:13px;color:var(--txt-3);">'+(s.supervisors?esc(s.supervisors):'—')+'</td>'
          +'<td style="padding:12px 16px;">'+statusBadge(s.status)+'</td>'
          +'</tr>';
      });
    }
  });
  if(!Object.keys(roomMap).length) tRows='<tr><td colspan="7" style="padding:32px;text-align:center;color:var(--txt-4);">ยังไม่มีห้องสอบในระบบ</td></tr>';
  const table=card('<table style="width:100%;border-collapse:collapse;">'
    +'<thead><tr style="border-bottom:2px solid var(--bdr);background:var(--bg-card2);">'
    +'<th style="padding:10px 16px;text-align:left;font-size:13px;color:var(--txt-3);font-weight:600;">ห้อง</th>'
    +'<th style="padding:10px 16px;text-align:left;font-size:13px;color:var(--txt-3);font-weight:600;">อาคาร</th>'
    +'<th style="padding:10px 16px;text-align:left;font-size:13px;color:var(--txt-3);font-weight:600;">วันที่สอบ</th>'
    +'<th style="padding:10px 16px;text-align:left;font-size:13px;color:var(--txt-3);font-weight:600;">เวลา</th>'
    +'<th style="padding:10px 16px;text-align:left;font-size:13px;color:var(--txt-3);font-weight:600;">ชุดข้อสอบ</th>'
    +'<th style="padding:10px 16px;text-align:left;font-size:13px;color:var(--txt-3);font-weight:600;">ผู้คุมสอบ</th>'
    +'<th style="padding:10px 16px;text-align:left;font-size:13px;color:var(--txt-3);font-weight:600;">สถานะ</th>'
    +'</tr></thead>'
    +'<tbody>'+tRows+'</tbody>'
    +'</table>');
  return pageTitle('จัดการห้องสอบ')+tabBar+table;
}

// ── Teacher ────────────────────────────────────────────────────────────────
function renderTeacherTabs(activeTab){
  const hasExam=!!state.currentExam;
  const items=[
    { val:'exams',   label:'ข้อสอบของฉัน', icon:'description', disabled:false },
    { val:'builder', label:'สร้าง/แก้ไข',  icon:'edit_note',   disabled:!hasExam },
    { val:'import',  label:'นำเข้า (Text)', icon:'upload_file', disabled:!hasExam },
  ];
  let html='<div style="display:flex;gap:4px;border-bottom:2px solid var(--bdr);margin-bottom:20px;">';
  items.forEach(item=>{
    const isActive=activeTab===item.val;
    if(item.disabled){
      html+='<div title="กรุณาเลือกชุดข้อสอบก่อน" style="display:flex;align-items:center;gap:6px;padding:10px 18px;border:none;background:transparent;font-size:14px;font-weight:500;color:var(--txt-4);border-bottom:2px solid transparent;margin-bottom:-2px;border-radius:0;cursor:not-allowed;opacity:0.5;user-select:none;">'
        +'<span class="msi" style="font-size:16px;">'+item.icon+'</span>'
        +item.label
        +'<span class="msi" style="font-size:14px;margin-left:2px;">lock</span>'
        +'</div>';
    } else {
      html+='<button data-act="tTab" data-arg="'+item.val+'" style="display:flex;align-items:center;gap:6px;padding:10px 18px;border:none;background:transparent;font-size:14px;font-weight:'+(isActive?'700':'500')+';cursor:pointer;color:'+(isActive?'var(--txt-brand)':'var(--txt-3)')+';border-bottom:2px solid '+(isActive?'var(--txt-brand)':'transparent')+';margin-bottom:-2px;border-radius:0;" data-hover="var(--bg-hover)">'
        +'<span class="msi" style="font-size:16px;">'+item.icon+'</span>'
        +item.label
        +'</button>';
    }
  });
  html+='</div>';
  if(!hasExam&&activeTab!=='exams'){
    html+='<div style="background:var(--st-amber-bg);border:1px solid var(--st-amber-c);border-radius:10px;padding:12px 16px;display:flex;align-items:center;gap:10px;margin-bottom:16px;font-size:13px;color:var(--st-amber-c);font-weight:600;">'
      +'<span class="msi" style="font-size:20px;">info</span>'
      +'กรุณาไปที่แท็บ "ข้อสอบของฉัน" แล้วกด "แก้ไขข้อสอบ" เพื่อเลือกชุดข้อสอบก่อน'
      +'</div>';
  }
  return html;
}
function renderTeacher(){
  const t=state.teacherTab;
  const tabNav=renderTeacherTabs(t);
  if(t==='exams')   return pageTitle('ข้อสอบของฉัน')+tabNav+renderExamList();
  if(t==='builder') return pageTitle('สร้าง/แก้ไขข้อสอบ')+tabNav+renderBuilder();
  if(t==='import')  return pageTitle('นำเข้าข้อสอบ (Text)')+tabNav+renderImportText();
  return '';
}

function renderExamList(){
  const exams=state.myExams||[];
  let cards='';
  exams.forEach(ex=>{
    const isPub=ex.status==='published';
    cards+='<div style="background:var(--bg-card);border:1px solid var(--bdr);border-radius:14px;padding:20px;box-shadow:var(--shadow-s);display:flex;flex-direction:column;gap:12px;">'
      +'<div style="display:flex;align-items:flex-start;justify-content:space-between;gap:8px;">'
      +'<div>'
      +'<div style="font-size:15px;font-weight:700;color:var(--txt-1);">'+esc(ex.title)+'</div>'
      +'<div style="font-size:12px;color:var(--txt-3);margin-top:3px;">'+esc(ex.subject||'')+(ex.question_count?' · '+ex.question_count+' ข้อ':'')+'</div>'
      +'</div>'
      +statBadge(isPub?'เผยแพร่':'ร่าง',isPub?'var(--st-green-bg)':'var(--st-amber-bg)',isPub?'var(--st-green-c)':'var(--st-amber-c)')
      +'</div>'
      +'<div style="display:flex;gap:8px;flex-wrap:wrap;">'
      +btnGhost('openBuilder',ex.id,'แก้ไขข้อสอบ','edit_note')
      +'<button data-act="publishExam" data-arg="'+ex.id+'" style="display:inline-flex;align-items:center;gap:6px;padding:7px 12px;background:'+(isPub?'var(--st-amber-bg)':'var(--st-green-bg)')+';color:'+(isPub?'var(--st-amber-c)':'var(--st-green-c)')+';border:1px solid '+(isPub?'var(--bdr)':'var(--st-green-bdr)')+';border-radius:8px;font-size:13px;font-weight:600;cursor:pointer;">'
      +'<span class="msi" style="font-size:16px;">'+(isPub?'unpublished':'publish')+'</span>'
      +(isPub?'เปลี่ยนเป็นร่าง':'เผยแพร่')+'</button>'
      +btnDanger('deleteExam',ex.id,'')
      +'</div>'
      +'</div>';
  });
  return '<div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(300px,1fr));gap:16px;margin-bottom:24px;">'
    +cards
    +'</div>'
    +card('<div style="display:flex;gap:12px;align-items:flex-end;">'
      +'<div style="flex:1;">'
      +'<label style="display:block;font-size:13px;font-weight:600;color:var(--txt-2);margin-bottom:6px;">สร้างชุดข้อสอบใหม่</label>'
      +'<input data-field="newExamTitle" placeholder="ชื่อชุดข้อสอบ" style="'+inputStyle()+'" data-focus="2px solid var(--txt-brand)">'
      +'</div>'
      +btnPrimary('createExam',undefined,'สร้าง','add')
      +'</div>');
}

function renderBuilder(){
  const exam=state.currentExam;
  const qs=state.questions||[];
  if(!exam){
    return '<div style="background:var(--bg-card);border-radius:14px;border:1px solid var(--bdr);padding:40px;text-align:center;color:var(--txt-3);">'
      +'<span class="msi" style="font-size:48px;display:block;margin-bottom:12px;">description</span>'
      +'<p style="font-size:15px;font-weight:600;">เลือกหรือสร้างชุดข้อสอบก่อน</p>'
      +'<p style="font-size:13px;margin-top:6px;">ไปที่แท็บ "ข้อสอบของฉัน" แล้วกด "แก้ไขข้อสอบ"</p>'
      +'</div>';
  }
  const isPub=exam.status==='published';
  let qListHtml='';
  qs.forEach((q,i)=>{
    const tl=Q_TYPE_LABELS[q.type]||q.type;
    const isEditing=state.editingQId===q.id;
    qListHtml+='<div style="display:flex;align-items:center;gap:8px;padding:10px;border-radius:10px;background:'+(isEditing?'var(--bg-brand-soft)':'var(--bg-card2)')+';border:1.5px solid '+(isEditing?'var(--txt-brand)':'transparent')+';margin-bottom:8px;">'
      +'<div style="width:28px;height:28px;border-radius:8px;background:'+(isEditing?'var(--txt-brand)':'var(--st-blue-bg)')+';display:flex;align-items:center;justify-content:center;flex-shrink:0;font-size:12px;font-weight:700;color:'+(isEditing?'#fff':'var(--st-blue-c)')+';">'+(i+1)+'</div>'
      +'<div style="flex:1;min-width:0;">'
      +'<div style="font-size:13px;font-weight:600;color:var(--txt-1);white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">'+esc(q.question_text||'')+'</div>'
      +'<div style="font-size:11px;color:var(--txt-3);">'+tl+' · '+q.score+' คะแนน</div>'
      +'</div>'
      +'<button data-act="editQuestion" data-arg="'+q.id+'" title="แก้ไข" style="width:28px;height:28px;border-radius:7px;border:1px solid var(--bdr);background:var(--bg-card);cursor:pointer;display:flex;align-items:center;justify-content:center;flex-shrink:0;color:var(--txt-2);" data-hover="var(--bg-hover)"><span class="msi" style="font-size:15px;">edit</span></button>'
      +btnDanger('deleteQuestion',q.id,'','padding:5px 8px;flex-shrink:0;')
      +'</div>';
  });

  let qForm='';
  const f=state.qForm;
  const qTypeNav='<div style="display:flex;flex-wrap:wrap;gap:6px;margin-bottom:16px;">'
    +Q_TYPES.map(qt=>'<button data-act="qType" data-arg="'+qt.id+'" style="display:inline-flex;align-items:center;gap:6px;padding:7px 12px;border-radius:8px;border:1px solid var(--bdr);font-size:13px;font-weight:600;cursor:pointer;background:'+(state.qType===qt.id?'var(--txt-brand)':'var(--bg-card2)')+';color:'+(state.qType===qt.id?'#fff':'var(--txt-2)')+';">'
      +'<span class="msi" style="font-size:16px;">'+qt.icon+'</span>'+qt.label+'</button>').join('')
    +'</div>';

  const commonFields='<div style="margin-bottom:12px;">'
    +'<label style="display:block;font-size:13px;font-weight:600;color:var(--txt-2);margin-bottom:6px;">คำถาม <span style="color:var(--st-red-c);">*</span></label>'
    +'<textarea data-fid="q_text" data-field="q_text" rows="3" style="'+inputStyle('resize:vertical;')+'" data-focus="2px solid var(--txt-brand)">'+esc(f.question_text)+'</textarea>'
    +'</div>'
    +'<div style="margin-bottom:12px;width:120px;">'
    +'<label style="display:block;font-size:13px;font-weight:600;color:var(--txt-2);margin-bottom:6px;">คะแนน</label>'
    +'<input type="number" data-fid="q_score" data-field="q_score" value="'+f.score+'" min="0" style="'+inputStyle()+'" data-focus="2px solid var(--txt-brand)">'
    +'</div>';

  const qt=state.qType;
  if(qt==='mcq'){
    const opts=f.options.slice(0,6);
    let optsHtml='';
    opts.forEach((o,i)=>{
      const isCorrect=i===state.builderCorrect;
      optsHtml+='<div style="display:flex;align-items:center;gap:8px;margin-bottom:8px;">'
        +'<button data-act="builderCorrect" data-arg="'+i+'" title="ตั้งเป็นคำตอบที่ถูกต้อง" style="width:28px;height:28px;border-radius:50%;border:2px solid '+(isCorrect?'var(--st-green-c)':'var(--bdr)')+';background:'+(isCorrect?'var(--st-green-bg)':'transparent')+';cursor:pointer;display:flex;align-items:center;justify-content:center;flex-shrink:0;">'
        +(isCorrect?'<span class="msi" style="font-size:16px;color:var(--st-green-c);">check</span>':'')+'</button>'
        +'<span style="font-size:13px;font-weight:600;color:var(--txt-3);width:20px;flex-shrink:0;">'+OPTS[i]+'.</span>'
        +'<input data-fid="q_opt_'+i+'" data-field="q_opt_'+i+'" value="'+esc(o)+'" placeholder="ตัวเลือก '+(i+1)+'" style="'+inputStyle()+'flex:1;" data-focus="2px solid var(--txt-brand)">'
        +'</div>';
    });
    qForm='<div style="margin-bottom:12px;"><label style="display:block;font-size:13px;font-weight:600;color:var(--txt-2);margin-bottom:8px;">ตัวเลือก (คลิกวงกลมเพื่อเลือกคำตอบที่ถูกต้อง)</label>'+optsHtml+'</div>';
  } else if(qt==='truefalse'){
    qForm='<div style="margin-bottom:12px;"><label style="display:block;font-size:13px;font-weight:600;color:var(--txt-2);margin-bottom:8px;">คำตอบที่ถูกต้อง</label>'
      +'<select data-field="q_tf" style="'+inputStyle()+'">'
      +'<option value="true"'+(f.correct_tf?' selected':'')+'>ถูกต้อง</option>'
      +'<option value="false"'+(!f.correct_tf?' selected':'')+'>ไม่ถูกต้อง</option>'
      +'</select></div>';
  } else if(qt==='fill'){
    qForm='<div style="margin-bottom:12px;"><label style="display:block;font-size:13px;font-weight:600;color:var(--txt-2);margin-bottom:6px;">คำตอบที่ถูกต้อง</label>'
      +'<input data-fid="q_fill_answer" data-field="q_fill_answer" value="'+esc(f.fill_answer)+'" style="'+inputStyle()+'" data-focus="2px solid var(--txt-brand)"></div>';
  } else if(qt==='matching'){
    let pairHtml='';
    for(let i=0;i<4;i++){
      pairHtml+='<div style="display:grid;grid-template-columns:1fr 1fr;gap:8px;margin-bottom:8px;">'
        +'<input data-fid="q_ml_'+i+'" data-field="q_ml_'+i+'" value="'+esc(f.match_left[i]||'')+'" placeholder="คอลัมน์ซ้าย '+(i+1)+'" style="'+inputStyle()+'" data-focus="2px solid var(--txt-brand)">'
        +'<input data-fid="q_mr_'+i+'" data-field="q_mr_'+i+'" value="'+esc(f.match_right[i]||'')+'" placeholder="คอลัมน์ขวา '+(i+1)+'" style="'+inputStyle()+'" data-focus="2px solid var(--txt-brand)">'
        +'</div>';
    }
    qForm='<div style="margin-bottom:12px;"><label style="display:block;font-size:13px;font-weight:600;color:var(--txt-2);margin-bottom:8px;">คู่จับคู่ (ซ้าย → ขวา)</label>'+pairHtml+'</div>';
  } else if(qt==='short'){
    qForm='<div style="margin-bottom:12px;"><label style="display:block;font-size:13px;font-weight:600;color:var(--txt-2);margin-bottom:6px;">แนวทางการให้คะแนน (ไม่บังคับ)</label>'
      +'<textarea data-fid="q_short_guide" data-field="q_short_guide" rows="3" style="'+inputStyle('resize:vertical;')+'" data-focus="2px solid var(--txt-brand)">'+esc(f.short_guide)+'</textarea></div>';
  }

  const isEditMode=!!state.editingQId;
  const qPanel='<div style="background:var(--bg-form-add);border:1.5px solid '+(isEditMode?'var(--txt-brand)':'var(--bdr)')+';border-radius:14px;padding:20px;">'
    +'<div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:14px;">'
    +'<h3 style="font-size:14px;font-weight:700;color:var(--txt-brand);">'+(isEditMode?'แก้ไขคำถาม':'เพิ่มคำถามใหม่')+'</h3>'
    +(isEditMode?'<button data-act="cancelEditQ" style="display:inline-flex;align-items:center;gap:5px;padding:5px 10px;background:var(--bg-card2);color:var(--txt-3);border:1px solid var(--bdr);border-radius:7px;font-size:12px;font-weight:600;cursor:pointer;"><span class="msi" style="font-size:14px;">close</span>ยกเลิกการแก้ไข</button>':'')
    +'</div>'
    +qTypeNav+commonFields+qForm
    +'<div style="display:flex;gap:8px;align-items:center;">'
    +btnPrimary('saveQuestion',undefined,isEditMode?'บันทึกการแก้ไข':'บันทึกคำถาม',isEditMode?'check':'save')
    +(isEditMode?'<button data-act="cancelEditQ" style="padding:10px 16px;background:var(--bg-card2);color:var(--txt-2);border:1px solid var(--bdr);border-radius:10px;font-size:14px;font-weight:600;cursor:pointer;">ยกเลิก</button>':'')
    +'</div>'
    +'</div>';

  return '<div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:16px;">'
    +'<div>'
    +'<div style="font-size:16px;font-weight:700;color:var(--txt-1);">'+esc(exam.title)+'</div>'
    +'<div style="font-size:13px;color:var(--txt-3);">'+qs.length+' ข้อ · '+(isPub?'เผยแพร่แล้ว':'ร่าง')+'</div>'
    +'</div>'
    +'<button data-act="publishExam" data-arg="'+exam.id+'" style="padding:8px 16px;border-radius:10px;border:1px solid '+(isPub?'var(--bdr)':'var(--st-green-bdr)')+';background:'+(isPub?'var(--st-amber-bg)':'var(--st-green-bg)')+';color:'+(isPub?'var(--st-amber-c)':'var(--st-green-c)')+';font-size:13px;font-weight:600;cursor:pointer;">'
    +(isPub?'เปลี่ยนเป็นร่าง':'เผยแพร่ข้อสอบ')+'</button>'
    +'</div>'
    +'<div style="display:grid;grid-template-columns:300px 1fr;gap:20px;">'
    +card('<h3 style="font-size:14px;font-weight:700;color:var(--txt-1);margin-bottom:12px;">รายการคำถาม ('+qs.length+')</h3>'+(qListHtml||'<p style="font-size:13px;color:var(--txt-3);margin-bottom:12px;">ยังไม่มีคำถาม</p>')+'<button data-act="cancelEditQ" style="width:100%;display:flex;align-items:center;justify-content:center;gap:8px;padding:10px;border-radius:10px;border:1.5px dashed var(--bdr);background:transparent;color:var(--txt-3);font-size:13px;font-weight:600;cursor:pointer;margin-top:4px;" data-hover="var(--bg-hover)"><span class="msi" style="font-size:18px;">add</span>เพิ่มคำถามใหม่</button>','overflow-y:auto;max-height:calc(100vh - 260px);')
    +qPanel
    +'</div>';
}

function renderImportText(){
  const exam=state.currentExam;
  const parsed=state.importParsed||[];
  let previewHtml='';
  parsed.slice(0,5).forEach((q,i)=>{
    previewHtml+='<div style="background:var(--bg-card2);border:1px solid var(--bdr);border-radius:10px;padding:14px;margin-bottom:10px;">'
      +'<div style="font-size:13px;font-weight:700;color:var(--txt-1);margin-bottom:8px;">'+(i+1)+'. '+esc(q.question)+'</div>'
      +q.options.map((o,j)=>'<div style="font-size:12px;color:'+(q.answer===o?'var(--st-green-c)':'var(--txt-3)')+';margin-left:12px;">'+(q.answer===o?'✓ ':'')+''+OPTS[j]+'. '+esc(o)+'</div>').join('')
      +(parsed.length>5&&i===4?'<div style="font-size:12px;color:var(--txt-3);margin-top:8px;">... และอีก '+(parsed.length-5)+' ข้อ</div>':'')
      +'</div>';
  });
  return '<div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;">'
    +card('<h3 style="font-size:15px;font-weight:700;color:var(--txt-1);margin-bottom:12px;">วางข้อสอบ (รูปแบบ Text)</h3>'
      +'<p style="font-size:12px;color:var(--txt-3);margin-bottom:12px;">รูปแบบ: ข้อ 1. คำถาม → ก. ตัวเลือก → เฉลย: ตัวเลือก</p>'
      +'<textarea data-field="importText" rows="16" placeholder="1. คำถาม&#10;ก. ตัวเลือก 1&#10;ข. ตัวเลือก 2&#10;ค. ตัวเลือก 3&#10;ง. ตัวเลือก 4&#10;เฉลย: ตัวเลือก 1" style="'+inputStyle('resize:none;font-size:13px;font-family:\'IBM Plex Mono\',monospace;')+'" data-focus="2px solid var(--txt-brand)">'+esc(state.importText)+'</textarea>'
      +'<div style="display:flex;gap:10px;margin-top:12px;">'
      +btnPrimary('parseImport',undefined,'วิเคราะห์ข้อสอบ','preview')
      +'</div>')
    +card('<h3 style="font-size:15px;font-weight:700;color:var(--txt-1);margin-bottom:12px;">ตัวอย่าง ('+(parsed.length)+' ข้อ)</h3>'
      +(parsed.length?previewHtml+'<div style="margin-top:16px;">'
        +(exam?btnPrimary('importText',undefined,'นำเข้าทั้งหมด '+parsed.length+' ข้อ','upload_file')
             :'<p style="font-size:13px;color:var(--txt-3);">กรุณาเลือกชุดข้อสอบก่อน (ไปที่แท็บ "ข้อสอบของฉัน")</p>')
        +'</div>'
        :'<p style="font-size:13px;color:var(--txt-3);">วางข้อสอบแล้วกด "วิเคราะห์ข้อสอบ"</p>'));
}

// ── Supervisor ─────────────────────────────────────────────────────────────
function renderSupervisor(){
  if(!state.svSession) return renderSvSessionList();
  return renderSvRoom();
}

function renderSvSessionList(){
  const sessions=state.svSessions||[];
  if(!sessions.length){
    return card('<div style="text-align:center;padding:32px;color:var(--txt-3);">'
      +'<span class="msi" style="font-size:48px;display:block;margin-bottom:12px;">event_busy</span>'
      +'<p style="font-weight:600;">ไม่มีรายการสอบที่กำหนด</p>'
      +'</div>');
  }
  let cards='';
  sessions.forEach(s=>{
    cards+='<div data-act="watchSession" data-arg="'+s.id+'" style="background:var(--bg-card);border:1px solid var(--bdr);border-radius:14px;padding:20px;cursor:pointer;transition:box-shadow .15s;" data-hover="var(--bg-hover)">'
      +'<div style="display:flex;align-items:flex-start;justify-content:space-between;gap:12px;margin-bottom:10px;">'
      +'<div style="font-size:15px;font-weight:700;color:var(--txt-1);">'+esc(s.exam_title||s.title||'—')+'</div>'
      +sessStatusBadge(s.status)
      +'</div>'
      +'<div style="display:flex;gap:16px;flex-wrap:wrap;">'
      +'<span style="font-size:13px;color:var(--txt-3);display:flex;align-items:center;gap:4px;"><span class="msi" style="font-size:16px;">meeting_room</span>'+(s.room_code?esc((s.building_name?s.building_name+' ':'')+s.room_code):esc(s.room||'—'))+'</span>'
      +'<span style="font-size:13px;color:var(--txt-3);display:flex;align-items:center;gap:4px;"><span class="msi" style="font-size:16px;">schedule</span>'+(s.start_time||'—')+'–'+(s.end_time||'—')+'</span>'
      +'<span style="font-size:13px;font-weight:700;color:var(--txt-brand);display:flex;align-items:center;gap:4px;font-family:\'IBM Plex Mono\',monospace;">'+(s.access_code||'—')+'</span>'
      +'</div>'
      +'</div>';
  });
  return pageTitle('ห้องสอบที่รับผิดชอบ')+'<div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(320px,1fr));gap:16px;">'+cards+'</div>';
}

function renderSvRoom(){
  const sv=state.svSession||{};
  const students=state.svStudents||[];
  const statusIcon={in_progress:'radio_button_checked',submitted:'task_alt',not_started:'schedule',absent:'person_off'};
  const statusColor={in_progress:'var(--st-green-c)',submitted:'var(--st-blue-c)',not_started:'var(--st-amber-c)',absent:'var(--st-gray-c)'};
  const statusLabel={in_progress:'กำลังสอบ',submitted:'ส่งแล้ว',not_started:'ยังไม่เริ่ม',absent:'ไม่มาสอบ'};
  let grid='';
  students.forEach(st=>{
    const sti=st.status||'not_started';
    grid+='<div style="background:var(--bg-card);border:1px solid var(--bdr);border-radius:12px;padding:16px;text-align:center;box-shadow:var(--shadow-s);">'
      +'<span class="msi" style="font-size:32px;color:'+statusColor[sti]+';display:block;margin-bottom:8px;">'+statusIcon[sti]+'</span>'
      +'<div style="font-size:13px;font-weight:700;color:var(--txt-1);">'+esc(st.full_name||'—')+'</div>'
      +'<div style="font-size:11px;color:var(--txt-3);margin-bottom:6px;">'+esc(st.username||'')+'</div>'
      +statBadge(statusLabel[sti]||sti,'var(--st-gray-bg)','var(--st-gray-c)')
      +(st.score!=null?'<div style="font-size:12px;color:var(--txt-3);margin-top:6px;">'+st.score+'/'+st.max_score+' คะแนน</div>':'')
      +'</div>';
  });
  const code=state.codeVisible?sv.access_code:'●●●●●●';
  return '<div style="display:flex;align-items:center;gap:12px;margin-bottom:20px;">'
    +'<button data-act="backSvList" style="width:36px;height:36px;border:none;background:var(--bg-card);border-radius:10px;border:1px solid var(--bdr);cursor:pointer;display:flex;align-items:center;justify-content:center;color:var(--txt-2);">'
    +'<span class="msi" style="font-size:20px;">arrow_back</span></button>'
    +'<div style="flex:1;">'
    +'<div style="font-size:16px;font-weight:700;color:var(--txt-1);">'+esc(sv.exam_title||sv.title||'—')+'</div>'
    +'<div style="font-size:13px;color:var(--txt-3);">ห้อง '+esc(sv.room||'—')+' · '+students.length+' คน</div>'
    +'</div>'
    +'<div style="display:flex;align-items:center;gap:8px;">'
    +'<span style="font-family:\'IBM Plex Mono\',monospace;font-size:18px;font-weight:700;color:var(--txt-brand);letter-spacing:2px;">'+code+'</span>'
    +'<button data-act="toggleCode" style="width:32px;height:32px;border:none;background:transparent;cursor:pointer;display:flex;align-items:center;justify-content:center;color:var(--txt-3);">'
    +'<span class="msi" style="font-size:20px;">'+(state.codeVisible?'visibility_off':'visibility')+'</span></button>'
    +'</div>'
    +sessStatusBadge(sv.status||'upcoming')
    +'</div>'
    +'<div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(160px,1fr));gap:12px;">'+grid+'</div>';
}

// ── Student enter screen ───────────────────────────────────────────────────
function renderStudentEnter(){
  return '<div style="min-height:100vh;background:var(--bg-page);display:flex;flex-direction:column;">'
    +'<div style="background:linear-gradient(135deg,var(--sb-from),var(--sb-to));padding:20px 24px;display:flex;align-items:center;justify-content:space-between;">'
    +'<div style="display:flex;align-items:center;gap:12px;">'
    +'<img src="assets/ovec-logo.svg" style="width:36px;height:36px;flex-shrink:0;">'
    +'<span style="font-size:18px;font-weight:700;color:#fff;">EXAMIS</span>'
    +'</div>'
    +'<div style="display:flex;align-items:center;gap:8px;">'
    +themeBtn('border-color:rgba(255,255,255,0.3);background:rgba(255,255,255,0.15);color:#fff;')
    +'<button data-act="logout" style="padding:8px 16px;background:rgba(255,255,255,0.15);border:1px solid rgba(255,255,255,0.3);border-radius:10px;color:#fff;font-size:13px;font-weight:600;cursor:pointer;" data-hover="rgba(255,255,255,0.25)">ออกจากระบบ</button>'
    +'</div>'
    +'</div>'
    +'<div style="flex:1;display:flex;align-items:center;justify-content:center;padding:40px 24px;">'
    +'<div style="background:var(--bg-card);border-radius:20px;box-shadow:var(--shadow-l);padding:48px 40px;width:100%;max-width:440px;border:1px solid var(--bdr);">'
    +'<div style="text-align:center;margin-bottom:32px;">'
    +'<span class="msi" style="font-size:48px;color:var(--txt-brand);display:block;margin-bottom:12px;">key</span>'
    +'<h2 style="font-size:20px;font-weight:700;color:var(--txt-1);">เข้าห้องสอบ</h2>'
    +'<p style="font-size:13px;color:var(--txt-3);margin-top:6px;">กรอกรหัสเข้าห้องที่ได้รับจากผู้คุมสอบ</p>'
    +'</div>'
    +'<div style="margin-bottom:24px;">'
    +'<label style="display:block;font-size:13px;font-weight:600;color:var(--txt-2);margin-bottom:8px;">รหัสเข้าห้องสอบ</label>'
    +'<input data-fid="examCode" data-field="examCode" value="'+esc(state.examCode)+'" maxlength="8" placeholder="ABCD1234" style="width:100%;padding:16px;border:2px solid var(--bdr);border-radius:12px;font-size:24px;font-weight:700;text-align:center;letter-spacing:4px;background:var(--bg-input);color:var(--txt-1);outline:none;font-family:\'IBM Plex Mono\',monospace;" data-focus="2px solid var(--txt-brand)">'
    +'</div>'
    +'<button data-act="toExamEnter" style="width:100%;padding:14px;background:linear-gradient(135deg,#7B1C1C,#B91C1C);color:#fff;border:none;border-radius:12px;font-size:16px;font-weight:700;cursor:pointer;box-shadow:0 4px 16px rgba(185,28,28,0.35);">เข้าห้องสอบ</button>'
    +'</div>'
    +'</div>'
    +'</div>';
}

// ── Student exam screen ────────────────────────────────────────────────────
function renderStudentExam(){
  const si=state.sessionInfo||{};
  const qs=state.questions2||[];
  const cq=state.currentQ;
  const q=qs[cq];
  const mins=Math.floor(state.timeLeft/60);
  const secs=state.timeLeft%60;
  const timeStr=String(mins).padStart(2,'0')+':'+String(secs).padStart(2,'0');
  const timeLow=state.timeLeft<300;
  const answered=Object.keys(state.answers).length;

  const topbar='<div style="background:linear-gradient(135deg,var(--sb-from),var(--sb-to));padding:0 24px;height:60px;display:flex;align-items:center;gap:12px;flex-shrink:0;">'
    +'<img src="assets/ovec-logo.svg" style="width:32px;height:32px;flex-shrink:0;">'
    +'<span style="font-size:15px;font-weight:700;color:#fff;flex:1;">'+esc(si.exam_title||si.title||'ข้อสอบ')+'</span>'
    +themeBtn('border-color:rgba(255,255,255,0.3);background:rgba(255,255,255,0.15);color:#fff;')
    +(!state.examStarted?''
      :'<div style="display:flex;align-items:center;gap:8px;padding:8px 16px;border-radius:10px;background:'+(timeLow?'var(--st-red-bg)':'rgba(255,255,255,0.15)')+';border:1px solid '+(timeLow?'var(--st-red-bdr)':'rgba(255,255,255,0.2)')+';">'
      +'<span class="msi" style="font-size:20px;color:'+(timeLow?'var(--st-red-c)':'#fff')+';">timer</span>'
      +'<span style="font-family:\'IBM Plex Mono\',monospace;font-size:18px;font-weight:700;color:'+(timeLow?'var(--st-red-c)':'#fff')+';">'+timeStr+'</span>'
      +'</div>')
    +'</div>';

  if(!state.examStarted){
    return '<div style="display:flex;flex-direction:column;height:100vh;overflow:hidden;">'
      +topbar
      +'<div style="flex:1;overflow-y:auto;padding:40px 24px;background:var(--bg-page);">'
      +'<div style="max-width:640px;margin:0 auto;">'
      +card('<div style="text-align:center;padding:8px;">'
        +'<span class="msi" style="font-size:48px;color:var(--txt-brand);display:block;margin-bottom:12px;">quiz</span>'
        +'<h2 style="font-size:20px;font-weight:700;color:var(--txt-1);">'+esc(si.exam_title||si.title||'ข้อสอบ')+'</h2>'
        +'<div style="display:flex;justify-content:center;gap:24px;margin:16px 0;flex-wrap:wrap;">'
        +'<span style="font-size:14px;color:var(--txt-3);display:flex;align-items:center;gap:6px;"><span class="msi" style="font-size:18px;">quiz</span>'+qs.length+' ข้อ</span>'
        +'<span style="font-size:14px;color:var(--txt-3);display:flex;align-items:center;gap:6px;"><span class="msi" style="font-size:18px;">timer</span>'+Math.floor(state.timeLeft/60)+' นาที</span>'
        +'<span style="font-size:14px;color:var(--txt-3);display:flex;align-items:center;gap:6px;"><span class="msi" style="font-size:18px;">meeting_room</span>'+esc(si.room||'—')+'</span>'
        +'</div>'
        +'<p style="font-size:13px;color:var(--txt-3);margin-bottom:24px;">เมื่อกดเริ่มสอบ จะเริ่มนับเวลา กรุณาทำข้อสอบให้ครบทุกข้อ</p>'
        +'<button data-act="startExam" style="padding:14px 32px;background:linear-gradient(135deg,#7B1C1C,#B91C1C);color:#fff;border:none;border-radius:12px;font-size:16px;font-weight:700;cursor:pointer;box-shadow:0 4px 16px rgba(185,28,28,0.35);">เริ่มสอบเลย</button>'
        +'</div>')
      +'</div>'
      +'</div>'
      +'</div>';
  }

  // Question navigator
  let qNav='';
  qs.forEach((q2,i)=>{
    const isAns=state.answers[q2.id]!==undefined&&state.answers[q2.id]!==null&&state.answers[q2.id]!=='';
    const isCur=i===cq;
    qNav+='<button data-act="navQ" data-arg="'+i+'" style="width:36px;height:36px;border-radius:8px;border:1.5px solid '+(isCur?'var(--txt-brand)':(isAns?'var(--st-green-c)':'var(--bdr)'))+';background:'+(isCur?'var(--txt-brand)':(isAns?'var(--st-green-bg)':'var(--bg-card)'))+';color:'+(isCur?'#fff':(isAns?'var(--st-green-c)':'var(--txt-3)'))+';font-size:13px;font-weight:700;cursor:pointer;">'+  (i+1)+'</button>';
  });

  // Question content
  let qContent='<div style="font-size:13px;color:var(--txt-3);margin-bottom:6px;">ข้อ '+(cq+1)+' / '+qs.length+' · '+esc((q&&Q_TYPE_LABELS[q.type])||'')+'</div>';
  if(!q){
    qContent+='<p style="color:var(--txt-3);">ไม่พบข้อสอบ</p>';
  } else {
    const ans=state.answers[q.id];
    qContent+='<div style="font-size:16px;font-weight:600;color:var(--txt-1);line-height:1.6;margin-bottom:20px;">'+esc(q.question_text)+'</div>';
    if(q.type==='mcq'||q.type==='truefalse'){
      let opts=[];
      try{opts=Array.isArray(q.options)?q.options:JSON.parse(q.options||'[]');}catch(e){}
      opts.forEach((o,i)=>{
        const sel=ans===o;
        qContent+='<button data-act="noop" onclick="selOpt('+q.id+',\''+o.replace(/'/g,"\\'")+'\')" style="display:flex;align-items:center;gap:12px;width:100%;padding:14px;margin-bottom:10px;border-radius:12px;border:2px solid '+(sel?'var(--txt-brand)':'var(--bdr)')+';background:'+(sel?'var(--bg-brand-soft)':'var(--bg-card)')+';text-align:left;cursor:pointer;color:var(--txt-1);font-size:14px;font-weight:'+(sel?'700':'500')+';">'
          +'<span style="width:28px;height:28px;border-radius:50%;border:2px solid '+(sel?'var(--txt-brand)':'var(--bdr)')+';background:'+(sel?'var(--txt-brand)':'transparent')+';display:flex;align-items:center;justify-content:center;flex-shrink:0;font-size:12px;font-weight:700;color:'+(sel?'#fff':'var(--txt-3)')+';">'+(q.type==='mcq'?OPTS[i]:''+(i===0?'ถ':'ผ'))+'</span>'
          +'<span>'+esc(o)+'</span>'
          +'</button>';
      });
    } else if(q.type==='fill'){
      qContent+='<input data-field="fillAnswer" value="'+esc(ans||'')+'" placeholder="พิมพ์คำตอบที่นี่..." style="width:100%;padding:14px;border:2px solid var(--bdr);border-radius:12px;font-size:15px;background:var(--bg-input);color:var(--txt-1);outline:none;" data-focus="2px solid var(--txt-brand)">';
    } else if(q.type==='matching'){
      let left=[],right=[];
      try{const op=typeof q.options==='string'?JSON.parse(q.options):q.options; left=op.left||[]; right=op.right||[];}catch(e){}
      const mp=state.matchPairs||{};
      const ml=state.matchLeft;
      qContent+='<div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;">'
        +'<div><div style="font-size:12px;font-weight:600;color:var(--txt-3);margin-bottom:8px;">คอลัมน์ซ้าย (คลิกเพื่อเลือก)</div>'
        +left.map((l,i)=>'<button data-act="matchLeft" data-arg="'+i+'" style="display:block;width:100%;padding:12px;margin-bottom:8px;border-radius:10px;border:2px solid '+(ml===i?'var(--txt-brand)':'var(--bdr)')+';background:'+(ml===i?'var(--bg-brand-soft)':'var(--bg-card)')+';text-align:left;cursor:pointer;font-size:14px;color:var(--txt-1);">'+(i+1)+'. '+esc(l)+'</button>').join('')
        +'</div>'
        +'<div><div style="font-size:12px;font-weight:600;color:var(--txt-3);margin-bottom:8px;">คอลัมน์ขวา (คลิกเพื่อจับคู่)</div>'
        +right.map((r,i)=>{
          const pairedLeft=Object.entries(mp).find(([li,ri])=>+ri===i);
          return '<button data-act="matchRight" data-arg="'+i+'" style="display:block;width:100%;padding:12px;margin-bottom:8px;border-radius:10px;border:2px solid '+(pairedLeft?'var(--st-green-c)':'var(--bdr)')+';background:'+(pairedLeft?'var(--st-green-bg)':'var(--bg-card)')+';text-align:left;cursor:pointer;font-size:14px;color:var(--txt-1);">'
            +(pairedLeft?'<span style="font-size:11px;color:var(--st-green-c);font-weight:600;">('+(+pairedLeft[0]+1)+'→'+(i+1)+') </span>':'')
            +esc(r)+'</button>';
        }).join('')
        +'</div>'
        +'</div>';
    } else if(q.type==='short'){
      qContent+='<textarea data-field="shortAnswer" rows="6" placeholder="พิมพ์คำตอบ..." style="width:100%;padding:14px;border:2px solid var(--bdr);border-radius:12px;font-size:15px;background:var(--bg-input);color:var(--txt-1);outline:none;resize:vertical;" data-focus="2px solid var(--txt-brand)">'+esc(ans||'')+'</textarea>';
    }
  }

  return '<div style="display:flex;flex-direction:column;height:100vh;overflow:hidden;">'
    +topbar
    +'<div style="flex:1;display:flex;overflow:hidden;background:var(--bg-page);">'
    // Left nav panel
    +'<div style="width:240px;min-width:240px;background:var(--bg-card);border-right:1px solid var(--bdr);overflow-y:auto;padding:16px;">'
    +'<div style="font-size:12px;font-weight:600;color:var(--txt-3);margin-bottom:10px;text-transform:uppercase;letter-spacing:0.5px;">ตอบแล้ว '+answered+'/'+qs.length+'</div>'
    +'<div style="display:flex;flex-wrap:wrap;gap:6px;margin-bottom:16px;">'+qNav+'</div>'
    +'<div style="border-top:1px solid var(--bdr);padding-top:16px;">'
    +'<button data-act="submitExam" style="width:100%;padding:12px;background:linear-gradient(135deg,#7B1C1C,#B91C1C);color:#fff;border:none;border-radius:10px;font-size:14px;font-weight:700;cursor:pointer;box-shadow:0 4px 14px rgba(185,28,28,0.3);">ส่งข้อสอบ</button>'
    +'</div>'
    +'</div>'
    // Question area
    +'<div style="flex:1;overflow-y:auto;padding:32px;">'
    +card(qContent,'max-width:680px;margin:0 auto;')
    +'<div style="max-width:680px;margin:16px auto 0;display:flex;justify-content:space-between;">'
    +(cq>0?btnGhost('prevQ',undefined,'ข้อก่อนหน้า','chevron_left'):'<div></div>')
    +(cq<qs.length-1?btnGhost('nextQ',undefined,'ข้อถัดไป','chevron_right','flex-direction:row-reverse;'):'')
    +'</div>'
    +'</div>'
    +'</div>'
    +'</div>';
}

// ── Boot ───────────────────────────────────────────────────────────────────
checkSession();
