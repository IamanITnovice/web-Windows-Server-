<?php
defined('ABSPATH') || exit;
include WD_THEME_DIR . '/templates/partials/layout-open.php';
$host_id = (int)($_GET['host_id'] ?? 0);
?>

<div class="wd-page">
  <div class="wd-page-header">
    <h1 class="wd-page-title">磁盘文件管理</h1>
    <div class="wd-header-right">
      <select id="file-host" class="wd-select" title="选择主机" aria-label="选择主机" onchange="selectFileHost(this.value)">
        <option value="">选择主机...</option>
      </select>
      <label class="wd-btn wd-btn--ghost" id="btn-upload" style="display:none;cursor:pointer" for="upload-input">↑ 上传文件</label>
      <input type="file" id="upload-input" style="display:none" multiple onchange="uploadFiles(this.files)">
      <button class="wd-btn wd-btn--ghost" id="btn-scan-drives" style="display:none" onclick="scanDrives()">💿 扫描磁盘</button>
      <button class="wd-btn wd-btn--ghost" onclick="refreshDir()">刷新</button>
    </div>
  </div>

  <!-- 驱动器选择 -->
  <div id="drive-panel" style="display:none;margin-bottom:16px">
    <div class="wd-muted wd-sm" style="margin-bottom:8px">选择磁盘驱动器：</div>
    <div id="drive-list" style="display:flex;flex-wrap:wrap;gap:10px"></div>
  </div>

  <!-- 路径地址栏，可以手动输入路径跳转 -->
  <div class="wd-path-bar" id="file-path-bar" style="display:none">
    <div style="display:flex;align-items:center;gap:8px;flex:1">
      <button class="wd-btn wd-btn--ghost wd-btn--sm" onclick="showDrives()" title="返回磁盘列表">💿</button>
      <div class="wd-breadcrumb" id="file-breadcrumb"></div>
    </div>
    <div class="wd-path-input-wrap">
      <input type="text" class="wd-input wd-input--sm wd-mono" id="file-path-input" placeholder="输入路径..." style="width:280px">
      <button class="wd-btn wd-btn--ghost wd-btn--sm" onclick="navigateTo(document.getElementById('file-path-input').value)">跳转</button>
    </div>
  </div>

  <div class="wd-card" id="file-card" style="display:none">
    <div class="wd-card-body wd-p0">
      <table class="wd-table wd-table--hover" id="file-table">
        <thead><tr><th style="width:32px"></th><th>名称</th><th>大小</th><th>修改时间</th><th>权限</th><th>操作</th></tr></thead>
        <tbody id="file-tbody"><tr><td colspan="6" class="wd-center wd-muted">请选择主机</td></tr></tbody>
      </table>
    </div>
    <div class="wd-card-footer">
      <span id="file-summary" class="wd-sm wd-muted"></span>
      <div id="file-upload-progress" style="display:none">
        <div class="wd-progress-bar"><div class="wd-progress-fill" id="file-upload-fill" style="width:0%"></div></div>
        <span class="wd-sm wd-muted" id="file-upload-label">上传中...</span>
      </div>
    </div>
  </div>
</div>

<script>
document.getElementById('wd-topbar-title').textContent = '磁盘文件管理';
const INIT_HOST = <?= $host_id ?>;
let fileHostId = INIT_HOST, currentPath = 'C:\\';

// 给 Windows 路径做 JS 转义：反斜杠翻倍（\→\\），单引号转义（\'→\'）
function _jsesc(s) {
    return String(s).replace(/\\/g, '\\\\').replace(/'/g, "\\'");
}

async function initHosts() {
    const res = await WD.ajax('wd_get_hosts', { page: 1 });
    if (!res.success) return;
    const sel = document.getElementById('file-host');
    res.data.items.forEach(h => {
        const o = document.createElement('option');
        o.value = h.id; o.textContent = h.name;
        if (h.id == INIT_HOST) o.selected = true;
        sel.appendChild(o);
    });
    if (INIT_HOST) { fileHostId = INIT_HOST; showControls(); scanDrives(); }
}

function selectFileHost(id) { fileHostId = parseInt(id)||0; if (fileHostId) { showControls(); scanDrives(); } }
function showControls() {
    document.getElementById('btn-upload').style.display='';
    document.getElementById('btn-scan-drives').style.display='';
}
function showDrives() {
    document.getElementById('drive-panel').style.display='';
    document.getElementById('file-path-bar').style.display='none';
    document.getElementById('file-card').style.display='none';
}
async function scanDrives() {
    showControls();
    document.getElementById('drive-panel').style.display='';
    document.getElementById('file-path-bar').style.display='none';
    document.getElementById('file-card').style.display='none';
    document.getElementById('drive-list').innerHTML = '<span class="wd-muted wd-sm">扫描中...</span>';

    const psCmd = `@(Get-PSDrive -PSProvider FileSystem | Select-Object `
        + `@{N='letter';E={$_.Name}},`
        + `@{N='root';E={$_.Root}},`
        + `@{N='type';E={'本地磁盘'}},`
        + `@{N='total';E={if($null -ne $_.Used -and $null -ne $_.Free){[long]($_.Used+$_.Free)}else{0}}},`
        + `@{N='free';E={if($null -ne $_.Free){[long]$_.Free}else{0}}},`
        + `@{N='used';E={if($null -ne $_.Used){[long]$_.Used}else{0}}}`
        + `) | ConvertTo-Json -Compress -Depth 2`;

    const res = await WD.ajax('wd_send_command', { host_id: fileHostId, cmd_type: 'powershell', payload: psCmd }, 'POST');
    if (!res.success) { document.getElementById('drive-list').innerHTML = '<span style="color:#f85149">扫描失败</span>'; return; }
    pollResult(res.data.cmd_id, result => {
        try {
            let drives = JSON.parse(result || '[]');
            if (!Array.isArray(drives)) drives = drives ? [drives] : [];
            renderDrives(drives);
        } catch(e) { document.getElementById('drive-list').innerHTML = '<span style="color:#f85149">解析失败: ' + e.message + '</span>'; }
    });
}
function renderDrives(drives) {
    const fmtB = b => { if(!b) return '—'; if(b<1073741824) return (b/1048576).toFixed(0)+'MB'; return (b/1073741824).toFixed(1)+'GB'; };
    document.getElementById('drive-list').innerHTML = drives.map(d => {
        const root = d.root.replace(/\\/g, '\\\\');
        return `
        <div onclick="enterDrive('${root}')" class="wd-drive-card">
            <div style="font-size:22px;margin-bottom:4px">💽</div>
            <div style="font-weight:700;font-size:14px">${d.letter}: <span class="wd-muted wd-sm">${d.type||''}</span></div>
            <div class="wd-muted wd-sm" style="margin-top:4px">${d.free != null ? '可用 '+fmtB(d.free)+' / '+fmtB(d.total) : (d.error||'无法读取')}</div>
        </div>`;
    }).join('') || '<span class="wd-muted">未检测到磁盘</span>';
}
function enterDrive(root) {
    currentPath = root;
    document.getElementById('drive-panel').style.display='none';
    document.getElementById('file-path-bar').style.display='flex';
    document.getElementById('file-card').style.display='';
    refreshDir(root);
}

async function refreshDir(path) {
    if (path) currentPath = path;
    if (!fileHostId) return WD.toast('请先选择主机','error');

    document.getElementById('file-path-bar').style.display='flex';
    document.getElementById('file-card').style.display='';
    document.getElementById('drive-panel').style.display='none';
    document.getElementById('file-path-input').value = currentPath;
    document.getElementById('file-tbody').innerHTML = '<tr><td colspan="6" class="wd-center wd-muted">加载中...</td></tr>';
    updateBreadcrumb(currentPath);

    // PowerShell 单引号字符串里单引号要双写转义，别搞错了
    const psPath = currentPath.replace(/'/g, "''");
    const psCmd = `@(Get-ChildItem -LiteralPath '${psPath}' -Force -EA SilentlyContinue | Select-Object `
        + `@{N='name';E={$_.Name}},`
        + `@{N='type';E={if($_.PSIsContainer){'dir'}else{'file'}}},`
        + `@{N='size';E={if($_.PSIsContainer){0}else{[long]$_.Length}}},`
        + `@{N='mtime';E={$_.LastWriteTime.ToString('yyyy-MM-dd HH:mm:ss')}},`
        + `@{N='mode';E={$_.Mode}}`
        + `) | ConvertTo-Json -Compress -Depth 2`;

    const res = await WD.ajax('wd_send_command', { host_id: fileHostId, cmd_type: 'powershell', payload: psCmd }, 'POST');
    if (!res.success) return WD.toast(res.data?.message||'失败','error');

    const cmdId = res.data.cmd_id;
    pollResult(cmdId, result => {
        try {
            let items = JSON.parse(result || '[]');
            if (!Array.isArray(items)) items = items ? [items] : [];
            renderFiles(items);
        } catch(e) { WD.toast('解析失败: ' + e.message,'error'); }
    });
}

function pollResult(cmdId, onSuccess, retries = 0) {
    if (retries > 30) return WD.toast('响应超时（客户端可能离线）','error');
    setTimeout(async () => {
        const pr = await WD.ajax('wd_get_cmd_result', { cmd_id: cmdId });
        if (pr.success && pr.data.status === 'ack') onSuccess(pr.data.result);
        else if (pr.success && ['pending','sent'].includes(pr.data.status)) pollResult(cmdId, onSuccess, retries + 1);
        else if (pr.success && pr.data.status === 'failed') WD.toast('客户端执行失败: ' + (pr.data.result||''), 'error');
        else WD.toast('指令失败', 'error');
    }, 1500);
}

function renderFiles(items) {
    document.getElementById('file-summary').textContent = items.length + ' 个项目';
    const tbody = document.getElementById('file-tbody');
    if (!items.length) { tbody.innerHTML = '<tr><td colspan="6" class="wd-center wd-muted">（空目录）</td></tr>'; return; }

    // 文件夹排前面，文件排后面，跟文件管理器一样
    items.sort((a,b) => { if(a.type===b.type) return a.name.localeCompare(b.name); return a.type==='dir'?-1:1; });
    tbody.innerHTML = items.map(f => {
        const icon = f.type === 'dir' ? '📁' : getFileIcon(f.name);
        const size = f.type === 'dir' ? '—' : formatBytes(f.size||0);
        const fpath = currentPath.replace(/\\$/, '') + '\\' + f.name;
        const fpathEsc = _jsesc(fpath);
        const fnameEsc = _jsesc(f.name);
        const aiOp  = `<button class="wd-btn wd-btn--xs" style="color:#c8b8ff;border-color:#6e40c9;background:transparent" title="AI 生成命令" onclick="aiFileOp('${fnameEsc}','${fpathEsc}','${f.type}')">AI</button>`;
        const isExe = /\.exe$/i.test(f.name);
        const runBtn = isExe
            ? `<button class="wd-btn wd-btn--xs" style="background:#e3b341;border-color:#e3b341;color:#000" onclick="runExe('${fpathEsc}')" title="在目标主机上运行此程序">▶ 运行</button>`
            : '';
        const ops  = f.type === 'dir'
            ? `<button class="wd-btn wd-btn--xs wd-btn--ghost" onclick="refreshDir('${fpathEsc}')">进入</button>${aiOp}`
            : `<button class="wd-btn wd-btn--xs wd-btn--primary" onclick="downloadFile('${fnameEsc}')">下载</button>
               <button class="wd-btn wd-btn--xs wd-btn--danger"  onclick="deleteFile('${fnameEsc}')">删除</button>${runBtn}${aiOp}`;
        return `<tr>
            <td class="wd-center">${icon}</td>
            <td><span class="${f.type==='dir'?'wd-link':'wd-text'}" ${f.type==='dir'?`style="cursor:pointer" onclick="refreshDir('${fpathEsc}')"`:''}>
                ${escHtml(f.name)}</span></td>
            <td class="wd-mono wd-sm">${size}</td>
            <td class="wd-sm wd-muted">${escHtml(f.mtime||'')}</td>
            <td class="wd-mono wd-sm">${escHtml(f.mode||'')}</td>
            <td><div class="wd-actions">${ops}</div></td>
        </tr>`;
    }).join('');
}

function updateBreadcrumb(path) {
    const parts = path.replace(/\\/g,'/').split('/').filter(Boolean);
    const el = document.getElementById('file-breadcrumb');
    let built = '';
    el.innerHTML = parts.map((p, i) => {
        built += p + '\\';
        const bp = built;  // keep trailing backslash for proper drive-root navigation
        return `<span class="wd-crumb-item ${i===parts.length-1?'wd-crumb-active':''}"
            ${i<parts.length-1?`onclick="refreshDir('${_jsesc(bp)}')"`:''}>${escHtml(p)}</span>`;
    }).join('<span class="wd-crumb-sep">›</span>');
}

function navigateTo(p) { if(p) refreshDir(p); }

async function downloadFile(name) {
    const path = currentPath.replace(/\\$/, '') + '\\' + name;
    const res  = await WD.ajax('wd_send_command', { host_id: fileHostId, cmd_type: 'read_file', payload: JSON.stringify({ path }) }, 'POST');
    if (!res.success) return WD.toast('下载指令失败','error');
    pollResult(res.data.cmd_id, result => {
        try {
            const data = JSON.parse(result);
            const bytes = Uint8Array.from(atob(data.content_b64), c => c.charCodeAt(0));
            const blob  = new Blob([bytes]);
            const url   = URL.createObjectURL(blob);
            const a     = document.createElement('a');
            a.href = url; a.download = name; a.click();
            URL.revokeObjectURL(url);
            WD.toast('下载完成');
        } catch(e) { WD.toast('下载失败: ' + e.message,'error'); }
    });
    WD.toast('等待客户端响应...','success',2000);
}

async function deleteFile(name) {
    if (!confirm('确定删除 "' + name + '"？此操作不可撤销')) return;
    const path = currentPath.replace(/\\$/, '') + '\\' + name;
    const res  = await WD.ajax('wd_send_command', { host_id: fileHostId, cmd_type: 'delete_file', payload: JSON.stringify({ path }) }, 'POST');
    if (res.success) { WD.toast('删除指令已发送'); setTimeout(() => refreshDir(currentPath), 2500); }
    else WD.toast('失败: ' + (res.data?.message || ''),'error');
}

async function runExe(name) {
    const path = currentPath.replace(/\\$/, '') + '\\' + name;
    if (!confirm('确定在目标主机上运行 "' + name + '"？')) return;
    const psPath = path.replace(/'/g, "''");
    const psCmd  = `Start-Process -FilePath '${psPath}' -ErrorAction Stop; 'OK'`;
    const res = await WD.ajax('wd_send_command', { host_id: fileHostId, cmd_type: 'powershell', payload: psCmd }, 'POST');
    if (!res.success) return WD.toast('指令发送失败','error');
    WD.toast('已下发运行指令，等待客户端执行…','success');
    pollResult(res.data.cmd_id, result => {
        if ((result||'').startsWith('ERROR') || (result||'').startsWith('error'))
            WD.toast('运行失败: ' + result, 'error');
        else
            WD.toast('"' + name + '" 已成功启动', 'success');
    });
}

function aiFileOp(name, fullPath, type) {
    const prompt = `"${fullPath}" 是一个${type === 'dir' ? '目录' : '文件'}，帮我给出 PowerShell 常用操作命令（删除/复制/移动）`;
    if (typeof wdAI !== 'undefined') {
        if (!wdAI._open) wdAI.toggle();
        document.getElementById('wd-ai-input').value = prompt;
        setTimeout(() => wdAI.send(), 100);
    } else { alert('AI 助手未加载，请检查 DeepSeek Key 配置'); }
}

// 文件页没有终端，AI 生成的命令就复制到剪贴板吧
window.wdTermPaste = function(cmd) {
    navigator.clipboard?.writeText(cmd).then(() => {
        WD.toast('命令已复制到剪贴板');
    }).catch(() => { alert('命令：\n' + cmd); });
};

async function uploadFiles(files) {
    if (!files.length || !fileHostId) return;
    const progress = document.getElementById('file-upload-progress');
    const fill     = document.getElementById('file-upload-fill');
    const label    = document.getElementById('file-upload-label');
    progress.style.display = 'flex';

    for (let i = 0; i < files.length; i++) {
        const file = files[i];
        label.textContent = `上传 ${file.name} (${i+1}/${files.length})`;
        fill.style.width  = Math.round((i / files.length) * 100) + '%';
        const b64 = await fileToBase64(file);
        const res = await WD.ajax('wd_send_command', {
            host_id: fileHostId, cmd_type: 'write_file',
            payload: JSON.stringify({ path: currentPath + '\\' + file.name, content_b64: b64 }),
        }, 'POST');
        if (!res.success) { WD.toast('上传 ' + file.name + ' 失败','error'); }
    }
    fill.style.width = '100%';
    label.textContent = '上传完成';
    setTimeout(() => { progress.style.display='none'; refreshDir(); }, 1500);
}

function fileToBase64(file) {
    return new Promise(r => { const fr = new FileReader(); fr.onload = () => r(fr.result.split(',')[1]); fr.readAsDataURL(file); });
}
function formatBytes(b) { if(b<1024)return b+'B'; if(b<1048576)return (b/1024).toFixed(1)+'KB'; return (b/1048576).toFixed(1)+'MB'; }
function getFileIcon(n) { const e=n.split('.').pop().toLowerCase(); const m={exe:'⚙',dll:'⚙',bat:'⚡',ps1:'⚡',txt:'📄',pdf:'📕',zip:'📦',rar:'📦',jpg:'🖼',png:'🖼',mp4:'🎬',mp3:'🎵'}; return m[e]||'📄'; }

initHosts();
</script>

<?php include WD_THEME_DIR . '/templates/partials/layout-close.php'; ?>

