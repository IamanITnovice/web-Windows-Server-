<?php
defined('ABSPATH') || exit;
include WD_THEME_DIR . '/templates/partials/layout-open.php';
$host_id = (int)($_GET['host_id'] ?? 0);
?>

<div class="wd-page">
  <div class="wd-page-header">
    <h1 class="wd-page-title">Windows 用户管理</h1>
    <div class="wd-header-right">
      <select id="wu-host" class="wd-select" title="选择主机" aria-label="选择主机" onchange="selectWuHost(this.value)">
        <option value="">选择主机...</option>
      </select>
      <button class="wd-btn wd-btn--primary" id="btn-wu-create" style="display:none" onclick="openModal('wu-create-modal')">+ 新建用户</button>
      <button class="wd-btn wd-btn--ghost" onclick="refreshUsers()">刷新</button>
    </div>
  </div>

  <div class="wd-card">
    <div class="wd-card-body wd-p0">
      <table class="wd-table wd-table--hover">
        <thead><tr><th>用户名</th><th>全名</th><th>描述</th><th>状态</th><th>所属组</th><th>上次登录</th><th>操作</th></tr></thead>
        <tbody id="wu-tbody"><tr><td colspan="7" class="wd-center wd-muted">请选择主机</td></tr></tbody>
      </table>
    </div>
  </div>
</div>

<!-- 新建用户弹窗 -->
<div class="wd-modal" id="wu-create-modal" style="display:none">
  <div class="wd-modal-backdrop" onclick="closeModal('wu-create-modal')"></div>
  <div class="wd-modal-dialog">
    <div class="wd-modal-header"><h3>新建 Windows 用户</h3><button class="wd-modal-close" onclick="closeModal('wu-create-modal')">✕</button></div>
    <div class="wd-modal-body">
      <div class="wd-form-group"><label class="wd-label">用户名 <span class="wd-required">*</span></label><input type="text" class="wd-input" id="wu-new-name"></div>
      <div class="wd-form-group"><label class="wd-label">全名</label><input type="text" class="wd-input" id="wu-new-fullname"></div>
      <div class="wd-form-group"><label class="wd-label">密码 <span class="wd-required">*</span></label><input type="password" class="wd-input" id="wu-new-pass"></div>
      <div class="wd-form-group"><label class="wd-label">描述</label><input type="text" class="wd-input" id="wu-new-desc"></div>
      <div class="wd-form-group">
        <label class="wd-label">所属组</label>
        <select class="wd-select" id="wu-new-group" style="width:100%">
          <option value="Users">Users（标准用户）</option>
          <option value="Administrators">Administrators（管理员）</option>
          <option value="Guests">Guests（来宾）</option>
        </select>
      </div>
    </div>
    <div class="wd-modal-footer">
      <button class="wd-btn wd-btn--ghost" onclick="closeModal('wu-create-modal')">取消</button>
      <button class="wd-btn wd-btn--primary" onclick="createWinUser()">创建</button>
    </div>
  </div>
</div>

<!-- 修改密码弹窗 -->
<div class="wd-modal" id="wu-pass-modal" style="display:none">
  <div class="wd-modal-backdrop" onclick="closeModal('wu-pass-modal')"></div>
  <div class="wd-modal-dialog wd-modal-dialog--sm">
    <div class="wd-modal-header"><h3>修改密码</h3><button class="wd-modal-close" onclick="closeModal('wu-pass-modal')">✕</button></div>
    <div class="wd-modal-body">
      <input type="hidden" id="wu-pass-username">
      <p class="wd-sm wd-muted">用户：<strong id="wu-pass-display"></strong></p>
      <div class="wd-form-group" style="margin-top:12px"><label class="wd-label">新密码</label><input type="password" class="wd-input" id="wu-new-pass-val"></div>
    </div>
    <div class="wd-modal-footer">
      <button class="wd-btn wd-btn--ghost" onclick="closeModal('wu-pass-modal')">取消</button>
      <button class="wd-btn wd-btn--primary" onclick="changePassword()">修改</button>
    </div>
  </div>
</div>

<script>
document.getElementById('wd-topbar-title').textContent = 'Windows 用户管理';
const INIT_HOST = <?= $host_id ?>;
let wuHostId = INIT_HOST;

async function initHosts() {
    const res = await WD.ajax('wd_get_hosts', { page: 1 });
    if (!res.success) return;
    const sel = document.getElementById('wu-host');
    res.data.items.forEach(h => {
        const o = document.createElement('option');
        o.value = h.id; o.textContent = h.name;
        if (h.id == INIT_HOST) o.selected = true;
        sel.appendChild(o);
    });
    if (INIT_HOST) { wuHostId = INIT_HOST; showWuControls(); refreshUsers(); }
}

function selectWuHost(id) { wuHostId = parseInt(id)||0; if (wuHostId) { showWuControls(); refreshUsers(); } }
function showWuControls() { document.getElementById('btn-wu-create').style.display = ''; }

async function refreshUsers() {
    if (!wuHostId) return;
    document.getElementById('wu-tbody').innerHTML = '<tr><td colspan="7" class="wd-center wd-muted">加载中...</td></tr>';
    const res = await WD.ajax('wd_send_command', { host_id: wuHostId, cmd_type: 'list_win_users', payload: '{}' }, 'POST');
    if (!res.success) return WD.toast('失败','error');
    pollCmdResult(res.data.cmd_id, result => {
        try { renderUsers(JSON.parse(result||'[]')); } catch(e) { WD.toast('解析失败','error'); }
    });
}

function renderUsers(users) {
    const tbody = document.getElementById('wu-tbody');
    tbody.innerHTML = users.length ? users.map(u => `
        <tr>
          <td><strong>${escHtml(u.name)}</strong></td>
          <td>${escHtml(u.full_name||'—')}</td>
          <td class="wd-sm wd-muted">${escHtml(u.description||'')}</td>
          <td><span class="wd-badge wd-badge--${u.enabled?'green':'gray'}">${u.enabled?'启用':'禁用'}</span></td>
          <td class="wd-sm">${escHtml((u.groups||[]).join(', '))}</td>
          <td class="wd-sm wd-muted">${escHtml(u.last_logon||'—')}</td>
          <td>
            <div class="wd-actions">
              <button class="wd-btn wd-btn--xs wd-btn--ghost" onclick="openPassModal('${escHtml(u.name).replace(/'/g,"\\'")}')">改密码</button>
              <button class="wd-btn wd-btn--xs wd-btn--ghost" onclick="toggleUser('${escHtml(u.name).replace(/'/g,"\\'")}',${u.enabled})">${u.enabled?'禁用':'启用'}</button>
              <button class="wd-btn wd-btn--xs wd-btn--danger" onclick="WD.confirm('确定删除用户 &quot;${escHtml(u.name)}&quot;？',()=>deleteUser('${escHtml(u.name).replace(/'/g,"\\'")}'))">删除</button>
            </div>
          </td>
        </tr>`) .join('')
      : '<tr><td colspan="7" class="wd-center wd-muted">无用户</td></tr>';
}

async function createWinUser() {
    const name = document.getElementById('wu-new-name').value.trim();
    const pass = document.getElementById('wu-new-pass-val')?.value || document.getElementById('wu-new-pass').value;
    if (!name || !pass) return WD.toast('用户名和密码必填','error');
    const res = await WD.ajax('wd_send_command', {
        host_id: wuHostId, cmd_type: 'create_win_user',
        payload: JSON.stringify({ name, password: pass, full_name: document.getElementById('wu-new-fullname').value, description: document.getElementById('wu-new-desc').value, group: document.getElementById('wu-new-group').value }),
    }, 'POST');
    closeModal('wu-create-modal');
    if (res.success) { WD.toast('创建指令已发送'); setTimeout(refreshUsers, 3000); }
    else WD.toast('失败','error');
}

function openPassModal(name) {
    document.getElementById('wu-pass-username').value     = name;
    document.getElementById('wu-pass-display').textContent = name;
    document.getElementById('wu-new-pass-val').value      = '';
    openModal('wu-pass-modal');
}
async function changePassword() {
    const name = document.getElementById('wu-pass-username').value;
    const pass = document.getElementById('wu-new-pass-val').value;
    if (!pass) return WD.toast('密码不能为空','error');
    const res = await WD.ajax('wd_send_command', { host_id: wuHostId, cmd_type: 'set_win_user_password', payload: JSON.stringify({ name, password: pass }) }, 'POST');
    closeModal('wu-pass-modal');
    res.success ? WD.toast('密码修改指令已发送') : WD.toast('失败','error');
}
async function toggleUser(name, enabled) {
    const cmd = enabled ? 'disable_win_user' : 'enable_win_user';
    const res = await WD.ajax('wd_send_command', { host_id: wuHostId, cmd_type: cmd, payload: JSON.stringify({ name }) }, 'POST');
    if (res.success) { WD.toast('指令已发送'); setTimeout(refreshUsers, 2000); }
}
async function deleteUser(name) {
    const res = await WD.ajax('wd_send_command', { host_id: wuHostId, cmd_type: 'delete_win_user', payload: JSON.stringify({ name }) }, 'POST');
    if (res.success) { WD.toast('删除指令已发送'); setTimeout(refreshUsers, 3000); }
    else WD.toast('失败','error');
}

function pollCmdResult(cmdId, cb, n=0) {
    if(n>20) return WD.toast('超时','error');
    setTimeout(async () => {
        const r = await WD.ajax('wd_get_cmd_result',{cmd_id:cmdId});
        if(r.success && r.data.status==='ack') cb(r.data.result);
        else if(r.success && ['pending','sent'].includes(r.data.status)) pollCmdResult(cmdId,cb,n+1);
        else WD.toast('指令失败','error');
    }, 1500);
}

initHosts();
</script>

<?php include WD_THEME_DIR . '/templates/partials/layout-close.php'; ?>

