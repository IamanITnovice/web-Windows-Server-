<?php
defined('ABSPATH') || exit;
include WD_THEME_DIR . '/templates/partials/layout-open.php';
$host_id = (int)($_GET['host_id'] ?? 0);
?>

<style>
.wd-page--console { display: flex; flex-direction: column; height: calc(100vh - 60px); }
.wd-terminal-wrap {
    flex: 1; display: flex; flex-direction: column;
    background: #0d0d0d; border-radius: 8px; overflow: hidden;
    border: 1px solid #333; margin: 0 16px 8px; min-height: 0;
}
.wd-terminal-chrome {
    display: flex; align-items: center; gap: 8px;
    padding: 8px 14px; background: #1c1c2a; border-bottom: 1px solid #333; flex-shrink: 0;
}
.wd-chrome-dots { display: flex; gap: 6px; }
.wd-chrome-dot  { width: 12px; height: 12px; border-radius: 50%; display: inline-block; }
.wd-chrome-dot--red    { background: #ff5f57; }
.wd-chrome-dot--yellow { background: #febc2e; }
.wd-chrome-dot--green  { background: #28c840; }
.wd-chrome-title { flex: 1; text-align: center; font-size: 12px; color: #888; }
#term-output {
    flex: 1; overflow-y: auto; padding: 10px 14px;
    background: #0d0d0d; color: #c8ffc8;
    font-family: 'Cascadia Code','Consolas','Courier New','NSimSun','SimSun','Microsoft YaHei',monospace;
    font-size: 13px; line-height: 1.6; white-space: pre-wrap; word-break: break-all;
}
.term-input-bar {
    display: flex; align-items: center; gap: 8px;
    padding: 8px 14px; background: #111; border-top: 1px solid #333; flex-shrink: 0;
}
.term-prompt { color: #28c840; font-family: monospace; font-size: 13px; white-space: nowrap; }
#term-input {
    flex: 1; background: transparent; border: none; color: #c8ffc8;
    font-family: 'Cascadia Code','Consolas','Courier New','NSimSun','SimSun',monospace;
    font-size: 13px; outline: none; caret-color: #c8ffc8;
}
#term-input::placeholder { color: #555; }
.wd-quick-cmds { padding: 6px 16px 10px; display: flex; align-items: center; gap: 6px; flex-wrap: wrap; flex-shrink: 0; }
</style>

<div class="wd-page wd-page--console">
  <div class="wd-page-header">
    <h1 class="wd-page-title">终端</h1>
    <div class="wd-header-right">
      <select id="console-host" class="wd-select" title="选择主机" aria-label="选择主机" onchange="document.getElementById('btn-console-connect').disabled=!this.value">
        <option value="">选择主机...</option>
      </select>
      <select id="console-shell" class="wd-select" title="选择终端类型" aria-label="选择终端类型" style="width:140px">
        <option value="powershell">PowerShell</option>
        <option value="cmd">CMD</option>
      </select>
      <button class="wd-btn wd-btn--primary" id="btn-console-connect" disabled onclick="connectConsole()">▶ 连接</button>
      <button class="wd-btn wd-btn--danger"  id="btn-console-stop"    style="display:none" onclick="disconnectConsole()">■ 断开</button>
      <button class="wd-btn wd-btn--ghost wd-btn--sm" onclick="clearOutput()">清屏</button>
      <span class="wd-badge" id="console-status">未连接</span>
    </div>
  </div>

  <div class="wd-terminal-wrap">
    <div class="wd-terminal-chrome">
      <div class="wd-chrome-dots">
        <span class="wd-chrome-dot wd-chrome-dot--red"></span>
        <span class="wd-chrome-dot wd-chrome-dot--yellow"></span>
        <span class="wd-chrome-dot wd-chrome-dot--green"></span>
      </div>
      <span class="wd-chrome-title" id="console-title">终端 — 未连接</span>
    </div>
    <div id="term-output"></div>
    <div class="term-input-bar">
      <span class="term-prompt" id="term-prompt-sym">$</span>
      <input id="term-input" type="text" placeholder="输入命令，按 Enter 发送..." autocomplete="off" spellcheck="false">
      <button class="wd-btn wd-btn--ghost wd-btn--xs" onclick="termSendInput()">发送</button>
    </div>
  </div>

  <div class="wd-quick-cmds">
    <span class="wd-sm wd-muted">快捷命令：</span>
    <button class="wd-btn wd-btn--ghost wd-btn--xs wd-btn--cmd" onclick="termQuick('whoami')">whoami</button>
    <button class="wd-btn wd-btn--ghost wd-btn--xs wd-btn--cmd" onclick="termQuick('ipconfig')">ipconfig</button>
    <button class="wd-btn wd-btn--ghost wd-btn--xs wd-btn--cmd" onclick="termQuick('systeminfo')">systeminfo</button>
    <button class="wd-btn wd-btn--ghost wd-btn--xs wd-btn--cmd ps-only" onclick="termQuick('Get-Process | Sort-Object CPU -Descending | Select-Object -First 20 | Format-Table -AutoSize')">进程列表</button>
    <button class="wd-btn wd-btn--ghost wd-btn--xs wd-btn--cmd ps-only" onclick="termQuick('Get-Date')">Get-Date</button>
    <button class="wd-btn wd-btn--ghost wd-btn--xs wd-btn--cmd ps-only" onclick="termQuick('Get-EventLog -LogName Security -Newest 5')">安全日志</button>
  </div>
</div>

<script>
document.getElementById('wd-topbar-title').textContent = '终端';
const INIT_HOST = <?= $host_id ?>;
let consoleWs = null;

// ── 纯 HTML 内置终端，无 xterm.js 依赖 ──────────────────────────────
const termOut   = document.getElementById('term-output');
const termInEl  = document.getElementById('term-input');
let   cmdHistory = [], histIdx = -1;

// 剥除 ANSI/VT100 控制序列，保留可读文字与换行
function processOutput(s) {
    // 1. \r\n → \n（Windows 换行）
    let r = s.replace(/\r\n/g, '\n');
    // 2. 去掉 ANSI 转义序列
    r = r.replace(/\x1b(?:[@-Z\\-_]|\[[0-?]*[ -/]*[@-~])/g, '');
    // 3. 去掉其余控制字符（保留 \n=0x0a \t=0x09）
    r = r.replace(/[\x00-\x08\x0b-\x0c\x0e-\x1f\x7f]/g, '');
    // 4. 单独的 \r 视为换行（部分 shell 只发 \r 不发 \n）
    r = r.replace(/\r/g, '\n');
    return r;
}

const term = {
    write(s) {
        const clean = processOutput(s);
        if (clean === '') return;
        termOut.appendChild(document.createTextNode(clean));
        termOut.scrollTop = termOut.scrollHeight;
    },
    writeln(s) { this.write(processOutput(s) + '\n'); },
    clear()    { termOut.innerHTML = ''; },
    focus()    { termInEl.focus(); },
    onData()   {},
    onResize() {},
    cols: 220, rows: 50,
};

// 回车发命令，箭头上下翻历史记录
termInEl.addEventListener('keydown', (e) => {
    if (e.key === 'Enter') {
        const cmd = termInEl.value;
        if (!cmd.trim()) return;
        if (!consoleWs || consoleWs.readyState !== WebSocket.OPEN)
            return WD.toast('请先连接主机', 'error');
        cmdHistory.unshift(cmd); if (cmdHistory.length > 50) cmdHistory.pop();
        histIdx = -1;
        consoleWs.send(JSON.stringify({ type: 'input', data: cmd + '\r\n' }));
        termInEl.value = '';
    } else if (e.key === 'ArrowUp') {
        histIdx = Math.min(histIdx + 1, cmdHistory.length - 1);
        termInEl.value = cmdHistory[histIdx] || '';
        e.preventDefault();
    } else if (e.key === 'ArrowDown') {
        histIdx = Math.max(histIdx - 1, -1);
        termInEl.value = histIdx < 0 ? '' : (cmdHistory[histIdx] || '');
        e.preventDefault();
    }
});

// 切换 Shell 类型的时候，快捷按钮按需显示隐藏
document.getElementById('console-shell').addEventListener('change', function() {
    document.querySelectorAll('.ps-only').forEach(el => {
        el.style.display = this.value === 'powershell' ? '' : 'none';
    });
    document.getElementById('term-prompt-sym').textContent = this.value === 'powershell' ? 'PS>' : 'CMD>';
});

// 主机列表加载，不限在线状态，以防心跳抖动把主机刷没了
async function initHosts() {
    // 加载全部主机，不限制在线，避免心跳短暂断开时主机从列表消失
    const res = await WD.ajax('wd_get_hosts', { page: 1 });
    if (!res.success) return;
    const sel = document.getElementById('console-host');
    res.data.items.forEach(h => {
        const o = document.createElement('option');
        const dot = h.status === 'online' ? '● ' : '○ ';
        o.value = h.id; o.textContent = dot + h.name + ' (' + h.ip_last + ')';
        o.dataset.name = h.name;
        o.dataset.status = h.status;
        if (h.id == INIT_HOST) o.selected = true;
        sel.appendChild(o);
    });
    if (sel.value) {
        document.getElementById('btn-console-connect').disabled = false;
        if (INIT_HOST) connectConsole();
    }
}

function clearOutput() { term.clear(); }

// WebSocket 连接逻辑，连上之后就能收发命令了
async function connectConsole() {
    const sel      = document.getElementById('console-host');
    const hostId   = sel.value;
    if (!hostId) return;
    const hostName  = sel.selectedOptions[0]?.dataset.name || '';
    const shellType = document.getElementById('console-shell').value;

    const tr = await WD.ajax('wd_get_ws_token', { host_id: hostId }, 'POST');
    if (!tr.success) return WD.toast('获取 Token 失败', 'error');

    const wsProto = location.protocol === 'https:' ? 'wss://' : 'ws://';
    const wsBase  = (WD.ws_host || location.hostname + ':8765').replace(/^wss?:\/\//i, '');
    const sid     = 'sess_' + Date.now();
    consoleWs = new WebSocket(
        `${wsProto}${wsBase}/shell?role=browser&host_id=${hostId}&token=${encodeURIComponent(tr.data.token)}&session=${sid}&shell=${shellType}`
    );
    setConStatus('连接中...', 'yellow');

    consoleWs.onopen = () => {
        setConStatus('已连接', 'green');
        document.getElementById('btn-console-connect').style.display = 'none';
        document.getElementById('btn-console-stop').style.display    = '';
        const label = shellType === 'cmd' ? 'CMD' : 'PowerShell';
        document.getElementById('console-title').textContent = label + ' — ' + hostName;
        term.writeln('[已连接到 ' + hostName + ' - ' + label + ']');
        consoleWs.send(JSON.stringify({ type: 'resize', rows: term.rows, cols: term.cols }));
        termInEl.focus();
    };
    consoleWs.onmessage = (e) => {
        try {
            const msg = JSON.parse(e.data);
            if (msg.type === 'output') term.write(msg.data);
            else if (msg.type === 'exit') {
                term.writeln('[进程退出 code=' + msg.code + ']');
                disconnectConsole();
            }
        } catch { term.write(e.data); }
    };
    consoleWs.onerror  = () => { setConStatus('错误', 'red');   term.writeln('[连接出错]'); };
    consoleWs.onclose  = () => {
        setConStatus('已断开', 'gray');
        document.getElementById('btn-console-connect').style.display = '';
        document.getElementById('btn-console-stop').style.display    = 'none';
        term.writeln('[连接已关闭]');
    };
}

function disconnectConsole() {
    if (consoleWs) { consoleWs.close(); consoleWs = null; }
}
function setConStatus(t, c) {
    const el = document.getElementById('console-status');
    el.textContent = t; el.className = 'wd-badge wd-badge--' + c;
}
function termQuick(cmd) {
    if (!consoleWs || consoleWs.readyState !== WebSocket.OPEN)
        return WD.toast('请先连接主机', 'error');
    consoleWs.send(JSON.stringify({ type: 'input', data: cmd + '\r\n' }));
    termInEl.focus();
}
function termSendInput() {
    const cmd = termInEl.value.trim();
    if (!cmd) return;
    if (!consoleWs || consoleWs.readyState !== WebSocket.OPEN)
        return WD.toast('请先连接主机', 'error');
    consoleWs.send(JSON.stringify({ type: 'input', data: cmd + '\r\n' }));
    termInEl.value = '';
}

initHosts();

// AI 助手调这个接口把命令填入终端输入框
window.wdTermPaste = function(cmd) {
    if (consoleWs && consoleWs.readyState === WebSocket.OPEN) {
        termInEl.value = cmd;
        termInEl.focus();
        WD.toast('命令已填入输入框，按 Enter 发送', 'success');
    } else {
        WD.toast('请先连接终端', 'error');
    }
};
</script>

<?php include WD_THEME_DIR . '/templates/partials/layout-close.php'; ?>
