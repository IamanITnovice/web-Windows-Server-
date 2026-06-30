<?php
defined('ABSPATH') || exit;
try {
    include WD_THEME_DIR . '/templates/partials/layout-open.php';
} catch (Throwable $e) {
    die('布局加载失败: ' . esc_html($e->getMessage()));
}
$host_id = (int)($_GET['host_id'] ?? 0);
?>
<style>
.wd-cam-page { display:flex;flex-direction:column;height:calc(100vh - 60px);background:#0d0d0d;color:#eee;overflow:hidden; }
/* 顶部操作栏，选择主机和开关摄像头 */
.wd-cam-header { display:flex;justify-content:space-between;align-items:center;padding:8px 16px;background:#12121f;border-bottom:1px solid #252540;flex-shrink:0;flex-wrap:wrap;gap:8px; }
.wd-cam-title  { display:flex;align-items:center;gap:10px;font-size:15px;font-weight:700; }
.wd-cam-controls { display:flex;align-items:center;gap:6px;flex-wrap:wrap; }
/* 设备扫描栏 */
.wd-cam-device-bar { display:none;background:#0f0f1f;border-bottom:1px solid #252540;padding:8px 16px;flex-shrink:0;flex-wrap:wrap;gap:10px;align-items:center; }
.wd-cam-device-bar.visible { display:flex; }
/* 主体 */
.wd-cam-body { display:flex;flex:1;overflow:hidden;gap:10px;padding:10px; }
/* 摄像头区 */
.wd-cam-video-panel { flex:1;display:flex;flex-direction:column;background:#111;border:1px solid #252540;border-radius:8px;overflow:hidden; }
.wd-cam-video-header { display:flex;justify-content:space-between;align-items:center;padding:6px 12px;background:#1a1a2e;border-bottom:1px solid #252540;font-size:13px;font-weight:600; }
.wd-cam-canvas-wrap { flex:1;position:relative;display:flex;align-items:center;justify-content:center;background:#000;overflow:hidden; }
#cam-canvas { max-width:100%;max-height:100%;display:block;border-radius:2px; }
.wd-cam-overlay { position:absolute;inset:0;display:flex;align-items:center;justify-content:center;background:rgba(0,0,0,.85);flex-direction:column;gap:12px; }
.wd-cam-placeholder { text-align:center;color:#888; }
.wd-spin-cam { width:40px;height:40px;border:4px solid #222;border-top-color:#c8b8ff;border-radius:50%;animation:spincam .7s linear infinite; }
@keyframes spincam { to { transform:rotate(360deg); } }
/* 音频区域，显示麦克风音量和波形图 */
.wd-cam-audio-panel { width:260px;display:flex;flex-direction:column;background:#111;border:1px solid #252540;border-radius:8px;overflow:hidden;flex-shrink:0; }
.wd-cam-audio-header { display:flex;justify-content:space-between;align-items:center;padding:6px 12px;background:#1a1a2e;border-bottom:1px solid #252540;font-size:13px;font-weight:600; }
.wd-cam-audio-body { flex:1;display:flex;flex-direction:column;align-items:center;justify-content:center;gap:14px;padding:16px 14px; }
.wd-vol-meter { width:100%;height:12px;background:#1e1e2e;border-radius:6px;overflow:hidden;border:1px solid #333; }
.wd-vol-fill  { height:100%;width:0%;transition:width .05s ease;background:linear-gradient(90deg,#3fb950,#e3b341,#f85149);border-radius:6px; }
#audio-wave { width:100%;height:70px;border-radius:6px;background:#0d0d1a;display:block; }
.wd-cam-audio-state { text-align:center;color:#888;font-size:12px; }
.wd-cam-audio-state strong { color:#eee;font-size:14px;display:block;margin-bottom:4px; }
.wd-cam-stats { display:flex;gap:6px;flex-wrap:wrap;justify-content:center; }
.wd-cam-stat { background:#1a1a2e;border:1px solid #333;border-radius:4px;padding:3px 8px;font-size:11px;color:#aaa; }
.wd-cam-stat span { color:#c8b8ff;font-weight:700; }
/* 摄像头页面的按钮统一样式 */
.cam-btn { cursor:pointer;border:none;border-radius:4px;padding:5px 11px;font-size:12px;font-weight:600;transition:opacity .15s; }
.cam-btn:disabled { opacity:.4;cursor:default; }
.cam-primary { background:#6e40c9;color:#fff; }
.cam-danger  { background:#c0392b;color:#fff; }
.cam-success { background:#1abc9c;color:#fff; }
.cam-warning { background:#e3b341;color:#000; }
.cam-ghost   { background:transparent;color:#aaa;border:1px solid #555; }
.cam-select  { background:#1e1e2e;color:#eee;border:1px solid #555;border-radius:4px;padding:4px 8px;font-size:12px;max-width:180px; }
/* 状态栏 */
.wd-cam-footer { display:flex;gap:16px;padding:5px 14px;background:#12121f;border-top:1px solid #252540;font-size:11px;color:#666;flex-shrink:0;flex-wrap:wrap; }
/* 指示点 */
.dot-live { width:8px;height:8px;border-radius:50%;display:inline-block;background:#f85149;animation:pulse-dot 1s ease-in-out infinite; }
@keyframes pulse-dot { 0%,100%{opacity:1;transform:scale(1)} 50%{opacity:.5;transform:scale(.7)} }
/* 设备状态标签：有设备还是没设备一目了然 */
.dev-badge { font-size:10px;padding:2px 6px;border-radius:3px;border:1px solid; }
.dev-ok   { background:rgba(63,185,80,.12);color:#3fb950;border-color:rgba(63,185,80,.3); }
.dev-none { background:rgba(248,81,73,.12);color:#f85149;border-color:rgba(248,81,73,.3); }
</style>

<div class="wd-cam-page">
  <!-- 顶栏 -->
  <div class="wd-cam-header">
    <div class="wd-cam-title">
      <span>📷 摄像头 &amp; 麦克风监控</span>
      <span id="cam-host-badge" style="background:#2a2a4a;padding:2px 8px;border-radius:4px;font-size:12px;display:none"></span>
      <span id="cam-status" class="wd-badge">未连接</span>
    </div>
    <div class="wd-cam-controls">
      <select id="cam-host" class="cam-select" title="选择主机" onchange="onHostChange()">
        <option value="">选择主机...</option>
      </select>
      <button class="cam-btn cam-warning" id="btn-scan" disabled onclick="scanDevices()">🔍 扫描设备</button>
      <button class="cam-btn cam-primary"  id="btn-cam-start"  disabled onclick="startCamera()">▶ 摄像头</button>
      <button class="cam-btn cam-danger"   id="btn-cam-stop"   style="display:none" onclick="stopCamera()">■ 停止</button>
      <button class="cam-btn cam-success"  id="btn-mic-start"  disabled onclick="startMic()">🎙 麦克风</button>
      <button class="cam-btn cam-danger"   id="btn-mic-stop"   style="display:none" onclick="stopMic()">■ 停止麦克</button>
      <button class="cam-btn cam-ghost" onclick="toggleFullscreen()">⛶ 全屏</button>
    </div>
  </div>

  <!-- 设备选择栏（扫描后显示） -->
  <div class="wd-cam-device-bar" id="device-bar">
    <div style="display:flex;align-items:center;gap:8px">
      <span style="font-size:12px;color:#aaa">📹 摄像头设备:</span>
      <select class="cam-select" id="cam-device-select">
        <option value="0">摄像头 0（默认）</option>
        <option value="1">摄像头 1</option>
        <option value="2">摄像头 2</option>
        <option value="3">摄像头 3</option>
      </select>
      <span id="cam-dev-badge" class="dev-badge" style="display:none"></span>
    </div>
    <div style="display:flex;align-items:center;gap:8px">
      <span style="font-size:12px;color:#aaa">🎙 麦克风设备:</span>
      <select class="cam-select" id="mic-device-select">
        <option value="">默认麦克风</option>
      </select>
      <span id="mic-dev-badge" class="dev-badge" style="display:none"></span>
    </div>
    <span id="scan-status" style="font-size:11px;color:#666;margin-left:auto"></span>
  </div>

  <!-- 主体 -->
  <div class="wd-cam-body">
    <!-- 摄像头视频区 -->
    <div class="wd-cam-video-panel">
      <div class="wd-cam-video-header">
        <span>📹 实时摄像头</span>
        <div style="display:flex;align-items:center;gap:10px">
          <span id="cam-fps"  style="color:#888;font-size:11px"></span>
          <span id="cam-res"  style="color:#888;font-size:11px"></span>
          <span id="cam-live-dot" style="display:none"><span class="dot-live"></span> 直播中</span>
        </div>
      </div>
      <div class="wd-cam-canvas-wrap">
        <canvas id="cam-canvas"></canvas>
        <div class="wd-cam-overlay" id="cam-overlay">
          <div class="wd-spin-cam" id="cam-spin" style="display:none"></div>
          <div class="wd-cam-placeholder">
            <div style="font-size:48px;margin-bottom:12px">📷</div>
            <p id="cam-overlay-msg">选择主机后点击「扫描设备」再开始预览</p>
            <p style="font-size:11px;color:#555">需目标主机安装摄像头且已运行最新 Agent</p>
          </div>
        </div>
      </div>
    </div>

    <!-- 音频区 -->
    <div class="wd-cam-audio-panel">
      <div class="wd-cam-audio-header">
        <span>🎙 实时麦克风</span>
        <span id="mic-live-dot" style="display:none"><span class="dot-live"></span></span>
      </div>
      <div class="wd-cam-audio-body">
        <div class="wd-cam-audio-state">
          <strong id="mic-status-text">未连接</strong>
          <span id="mic-hint">点击「麦克风」开始监听</span>
        </div>
        <canvas id="audio-wave" width="220" height="70"></canvas>
        <div style="width:100%">
          <div style="font-size:11px;color:#666;margin-bottom:4px">音量</div>
          <div class="wd-vol-meter"><div class="wd-vol-fill" id="vol-fill"></div></div>
        </div>
        <div class="wd-cam-stats">
          <div class="wd-cam-stat">采样率 <span>16kHz</span></div>
          <div class="wd-cam-stat">格式 <span>S16LE</span></div>
          <div class="wd-cam-stat">包 <span id="audio-chunks">0</span></div>
        </div>
        <div style="font-size:10px;color:#555;text-align:center;line-height:1.6">
          音频在浏览器实时播放<br>请允许网页自动播放音频
        </div>
      </div>
    </div>
  </div>

  <!-- 状态栏 -->
  <div class="wd-cam-footer">
    <span id="footer-host">主机: 未选择</span>
    <span id="footer-cam">摄像头: 未启动</span>
    <span id="footer-mic">麦克风: 未启动</span>
    <span id="footer-fps"></span>
    <span id="footer-dev" style="margin-left:auto"></span>
  </div>
</div>

<script>
document.getElementById('wd-topbar-title').textContent = '摄像头 & 麦克风监控';
const INIT_HOST = <?= (int)$host_id ?>;

let camWs = null, micWs = null;
let camFrames = 0, lastFpsTs = Date.now();
let audioCtx = null, audioNextTime = 0, audioChunks = 0;
let _camOpenedAt = 0, _micOpenedAt = 0;

const camCanvas  = document.getElementById('cam-canvas');
const camCtx     = camCanvas.getContext('2d');
const waveCanvas = document.getElementById('audio-wave');
const waveCtx    = waveCanvas.getContext('2d');

// ── 主机加载 ──────────────────────────────────────────────────────────
async function initHosts() {
    const res = await WD.ajax('wd_get_hosts', { page: 1 });
    if (!res.success) return;
    const sel = document.getElementById('cam-host');
    (res.data.items || []).forEach(h => {
        const o = document.createElement('option');
        o.value = h.id;
        o.textContent = (h.status === 'online' ? '● ' : '○ ') + (h.name || 'Host#' + h.id);
        o.dataset.name   = h.name || '';
        o.dataset.status = h.status;
        if (h.id == INIT_HOST) o.selected = true;
        sel.appendChild(o);
    });
    if (sel.value) onHostChange();
}

function onHostChange() {
    const sel = document.getElementById('cam-host');
    const ok  = !!sel.value;
    document.getElementById('btn-cam-start').disabled  = !ok;
    document.getElementById('btn-mic-start').disabled  = !ok;
    document.getElementById('btn-scan').disabled       = !ok;
    const name = sel.selectedOptions[0]?.dataset.name || '';
    document.getElementById('footer-host').textContent = '主机: ' + (name || '未选择');
}

// ── 设备扫描 ──────────────────────────────────────────────────────────
let _scanPending = false;
async function scanDevices() {
    if (_scanPending) return;
    const hostId = document.getElementById('cam-host').value;
    if (!hostId) return;

    _scanPending = true;
    const btn = document.getElementById('btn-scan');
    const statusEl = document.getElementById('scan-status');
    btn.disabled = true;
    btn.textContent = '扫描中...';
    statusEl.textContent = '正在查询设备...';
    document.getElementById('device-bar').classList.add('visible');

    try {
        // 用 PowerShell 的 PnpDevice 查一下有哪些摄像头
        const camCmd = `Get-PnpDevice -Class Camera -ErrorAction SilentlyContinue | Where-Object Status -eq 'OK' | Select-Object -ExpandProperty FriendlyName | ConvertTo-Json -Compress`;
        const camRes = await WD.ajax('wd_push_cmd', { host_id: hostId, cmd: camCmd }, 'POST');
        if (camRes.success) {
            await pollAndFillCamDevices(hostId, camCmd);
        }

        // ── 扫描麦克风（sounddevice via Python） ──
        const micCmd = String.raw`python -c "import sounddevice as sd,json; devs=[{'i':i,'n':d['name']} for i,d in enumerate(sd.query_devices()) if d['max_input_channels']>0]; print(json.dumps(devs))"`;
        await pollAndFillMicDevices(hostId, micCmd);

    } catch (e) {
        statusEl.textContent = '扫描失败: ' + e.message;
    } finally {
        _scanPending = false;
        btn.disabled = false;
        btn.textContent = '🔍 扫描设备';
    }
}

async function _pushAndPoll(hostId, cmd, timeoutMs = 10000) {
    // 使用 powershell 类型推送 shell 命令
    const pushRes = await WD.ajax('wd_send_command', {
        host_id: hostId,
        cmd_type: 'powershell',
        payload: cmd,
    }, 'POST');
    if (!pushRes.success) return null;
    const cmdId = pushRes.data?.cmd_id;
    if (!cmdId) return null;
    // 轮询等结果出来，超时了就拉倒
    const deadline = Date.now() + timeoutMs;
    while (Date.now() < deadline) {
        await new Promise(r => setTimeout(r, 900));
        const r = await WD.ajax('wd_get_cmd_result', { cmd_id: cmdId });
        if (r.success && r.data?.result !== undefined && r.data.result !== null) {
            return r.data.result;
        }
    }
    return null;
}

async function pollAndFillCamDevices(hostId, cmd) {
    const statusEl = document.getElementById('scan-status');
    const result   = await _pushAndPoll(hostId, cmd);
    const sel      = document.getElementById('cam-device-select');
    const badge    = document.getElementById('cam-dev-badge');

    // 备用：始终保留数字索引选项（0-3）
    const base = [{index:0,name:'摄像头 0（默认）'},{index:1,name:'摄像头 1'},{index:2,name:'摄像头 2'},{index:3,name:'摄像头 3'}];

    if (result) {
        try {
            const names = JSON.parse(result);
            const arr = Array.isArray(names) ? names : [names];
            sel.innerHTML = '';
            arr.forEach((n, i) => {
                const o = document.createElement('option');
                o.value = i;
                o.textContent = `摄像头 ${i}: ${n}`;
                sel.appendChild(o);
            });
            // 一个设备都没扫到就显示默认的数字索引选项
            if (arr.length === 0) {
                base.forEach(d => {
                    const o = document.createElement('option');
                    o.value = d.index; o.textContent = d.name; sel.appendChild(o);
                });
            }
            badge.textContent = arr.length > 0 ? `${arr.length} 个设备` : '无设备';
            badge.className   = 'dev-badge ' + (arr.length > 0 ? 'dev-ok' : 'dev-none');
            badge.style.display = '';
            statusEl.textContent = `找到 ${arr.length} 个摄像头`;
            document.getElementById('footer-dev').textContent = `摄像头: ${arr.length} 个`;
        } catch {
            statusEl.textContent = '摄像头扫描完成（解析数据异常，使用默认索引）';
        }
    } else {
        statusEl.textContent = '摄像头扫描超时，使用默认索引';
    }
}

async function pollAndFillMicDevices(hostId, cmd) {
    const result = await _pushAndPoll(hostId, cmd);
    const sel    = document.getElementById('mic-device-select');
    const badge  = document.getElementById('mic-dev-badge');

    if (result) {
        try {
            const devs = JSON.parse(result);
            sel.innerHTML = '<option value="">默认麦克风</option>';
            if (Array.isArray(devs)) {
                devs.forEach(d => {
                    const o = document.createElement('option');
                    o.value = d.i ?? d.index ?? '';
                    o.textContent = `[${o.value}] ${d.n || d.name}`;
                    sel.appendChild(o);
                });
                badge.textContent   = `${devs.length} 个设备`;
                badge.className     = 'dev-badge ' + (devs.length > 0 ? 'dev-ok' : 'dev-none');
                badge.style.display = '';
            }
        } catch { /* 保留默认 */ }
    }
}

// ── WebSocket 帮助 ────────────────────────────────────────────────────
function _wsUrl(channel, hostId, token, extraParams = '') {
    const proto  = location.protocol === 'https:' ? 'wss://' : 'ws://';
    // 去掉协议前缀，保留 host 和路径，末尾不要有多余斜杠再拼频道名
    const wsBase = (WD.ws_host || location.hostname + ':8765')
        .replace(/^wss?:\/\//i, '')
        .replace(/\/$/, '');
    return `${proto}${wsBase}/${channel}?role=viewer&host_id=${hostId}&token=${encodeURIComponent(token)}${extraParams}`;
}

// WebSocket 关闭码转成人话，方便排查问题
function _wsCloseHint(code) {
    if (code === 1006) return 'Agent 未响应或 WS 服务器不支持此频道（请确认 ws_server.py 已升级并重启，且 nginx 代理端口正确）';
    if (code === 4001) return '令牌验证失败（刷新页面重试）';
    if (code === 4003) return '主机不在线或 Agent 无此模块（请重新构建含摄像头模块的 Agent）';
    if (code === 4004) return '频道不存在';
    if (code === 1001) return '服务器主动关闭连接';
    if (code === 1011) return '服务器内部错误';
    return `连接被服务器关闭（code ${code}）`;
}

// ── 摄像头 ───────────────────────────────────────────────────────────
async function startCamera() {
    const sel    = document.getElementById('cam-host');
    const hostId = sel.value;
    if (!hostId) return;
    const hostName = sel.selectedOptions[0]?.dataset.name || hostId;
    const devIdx   = document.getElementById('cam-device-select').value || '0';

    const tr = await WD.ajax('wd_get_ws_token', { host_id: hostId }, 'POST');
    if (!tr.success) return WD.toast('获取 Token 失败', 'error');

    const url = _wsUrl('camera', hostId, tr.data.token, `&device_idx=${devIdx}`);
    camWs = new WebSocket(url);
    camWs.binaryType = 'arraybuffer';

    setCamStatus('连接中…', 'yellow');
    document.getElementById('cam-spin').style.display = '';
    document.getElementById('cam-overlay-msg').textContent = `连接 ${hostName} 摄像头${devIdx}…`;

    camWs.onopen = () => {
        _camOpenedAt = Date.now();
        setCamStatus('等待画面…', 'yellow');
        document.getElementById('cam-overlay').style.display    = 'flex';
        document.getElementById('cam-spin').style.display       = '';
        document.getElementById('cam-overlay-msg').textContent  = `等待 ${hostName} 摄像头${devIdx} 推流…`;
        document.getElementById('cam-live-dot').style.display   = '';
        document.getElementById('cam-host-badge').textContent   = hostName;
        document.getElementById('cam-host-badge').style.display = '';
        document.getElementById('btn-cam-start').style.display  = 'none';
        document.getElementById('btn-cam-stop').style.display   = '';
        document.getElementById('footer-cam').textContent = `摄像头 ${devIdx}: 连接中`;
    };
    camWs.onmessage = (e) => {
        if (_camOpenedAt) {
            _camOpenedAt = 0;
            setCamStatus('直播中', 'green');
            document.getElementById('cam-overlay').style.display = 'none';
            document.getElementById('cam-spin').style.display    = 'none';
            document.getElementById('footer-cam').textContent = `摄像头 ${devIdx}: 直播中`;
        }
        const blob = new Blob([e.data], { type: 'image/jpeg' });
        const burl = URL.createObjectURL(blob);
        const img  = new Image();
        img.onload = () => {
            if (camCanvas.width  !== img.width)  camCanvas.width  = img.width;
            if (camCanvas.height !== img.height) camCanvas.height = img.height;
            camCtx.drawImage(img, 0, 0);
            URL.revokeObjectURL(burl);
            document.getElementById('cam-res').textContent = img.width + '×' + img.height;
            camFrames++;
            const now = Date.now();
            if (now - lastFpsTs >= 1000) {
                document.getElementById('cam-fps').textContent    = camFrames + ' fps';
                document.getElementById('footer-fps').textContent = '摄像头 FPS: ' + camFrames;
                camFrames = 0; lastFpsTs = now;
            }
        };
        img.src = burl;
    };
    camWs.onerror = (ev) => { setCamStatus('连接错误', 'red'); };
    camWs.onclose = (ev) => {
        const wasInstant = _camOpenedAt && (Date.now() - _camOpenedAt < 3000);
        _camOpenedAt = 0;
        setCamStatus('已断开', 'gray');
        document.getElementById('cam-overlay').style.display    = 'flex';
        document.getElementById('cam-live-dot').style.display   = 'none';
        document.getElementById('cam-host-badge').style.display = 'none';
        document.getElementById('cam-spin').style.display       = 'none';
        document.getElementById('btn-cam-start').style.display  = '';
        document.getElementById('btn-cam-stop').style.display   = 'none';
        document.getElementById('footer-cam').textContent = '摄像头: 已停止';
        if (wasInstant) {
            const hint = _wsCloseHint(ev.code);
            document.getElementById('cam-overlay-msg').textContent = hint;
            WD.toast('摄像头: ' + hint, 'error');
        } else {
            document.getElementById('cam-overlay-msg').textContent = '摄像头已断开（code ' + ev.code + '）';
        }
        camWs = null;
    };
}

function stopCamera() { if (camWs) { camWs.close(); camWs = null; } }

function setCamStatus(t, c) {
    const el = document.getElementById('cam-status');
    el.textContent = t;
    el.className   = 'wd-badge wd-badge--' + c;
}

// ── 麦克风 ───────────────────────────────────────────────────────────
async function startMic() {
    const sel    = document.getElementById('cam-host');
    const hostId = sel.value;
    if (!hostId) return;
    const hostName = sel.selectedOptions[0]?.dataset.name || hostId;
    const micDev   = document.getElementById('mic-device-select').value;

    const tr = await WD.ajax('wd_get_ws_token', { host_id: hostId }, 'POST');
    if (!tr.success) return WD.toast('获取 Token 失败', 'error');

    const extraParam = micDev !== '' ? `&device_idx=${micDev}` : '';
    const url = _wsUrl('audio', hostId, tr.data.token, extraParam);

    if (!audioCtx) {
        audioCtx = new (window.AudioContext || window.webkitAudioContext)({ sampleRate: 16000 });
    }
    if (audioCtx.state === 'suspended') await audioCtx.resume();
    audioNextTime = audioCtx.currentTime + 0.2;

    micWs = new WebSocket(url);
    micWs.binaryType = 'arraybuffer';

    document.getElementById('mic-status-text').textContent = '连接中…';
    document.getElementById('mic-hint').textContent        = '等待音频流…';

    micWs.onopen = () => {
        _micOpenedAt = Date.now();
        const devLabel = micDev !== '' ? `麦克风 ${micDev}` : '默认麦克风';
        document.getElementById('mic-status-text').textContent = '等待音频…';
        document.getElementById('mic-hint').textContent        = `等待 ${hostName} 的${devLabel} 推流…`;
        document.getElementById('mic-live-dot').style.display  = '';
        document.getElementById('btn-mic-start').style.display = 'none';
        document.getElementById('btn-mic-stop').style.display  = '';
        document.getElementById('footer-mic').textContent = devLabel + ': 连接中';
    };
    micWs.onmessage = (e) => {
        if (_micOpenedAt) {
            _micOpenedAt = 0;
            const devLabel = micDev !== '' ? `麦克风 ${micDev}` : '默认麦克风';
            document.getElementById('mic-status-text').textContent = '监听中';
            document.getElementById('mic-hint').textContent        = `正在播放 ${hostName} 的${devLabel}`;
            document.getElementById('footer-mic').textContent = devLabel + ': 监听中';
        }
        audioChunks++;
        document.getElementById('audio-chunks').textContent = audioChunks;
        const pcm = new Int16Array(e.data);
        const f32 = new Float32Array(pcm.length);
        let sum = 0;
        for (let i = 0; i < pcm.length; i++) { f32[i] = pcm[i] / 32768; sum += f32[i] * f32[i]; }
        updateVolMeter(Math.sqrt(sum / pcm.length));
        drawWave(f32);
        if (!audioCtx) return;
        const buf = audioCtx.createBuffer(1, f32.length, 16000);
        buf.copyToChannel(f32, 0);
        const src = audioCtx.createBufferSource();
        src.buffer = buf;
        src.connect(audioCtx.destination);
        const now = audioCtx.currentTime;
        const t   = Math.max(now + 0.01, audioNextTime);
        src.start(t);
        audioNextTime = t + buf.duration;
    };
    micWs.onerror = () => { document.getElementById('mic-status-text').textContent = '连接错误'; };
    micWs.onclose = (ev) => {
        const wasInstant = _micOpenedAt && (Date.now() - _micOpenedAt < 3000);
        _micOpenedAt = 0;
        document.getElementById('mic-live-dot').style.display  = 'none';
        document.getElementById('btn-mic-start').style.display = '';
        document.getElementById('btn-mic-stop').style.display  = 'none';
        document.getElementById('footer-mic').textContent = '麦克风: 已停止';
        clearVolMeter(); micWs = null;
        if (wasInstant) {
            const hint = _wsCloseHint(ev.code);
            document.getElementById('mic-status-text').textContent = '连接失败';
            document.getElementById('mic-hint').textContent        = hint;
            WD.toast('麦克风: ' + hint, 'error');
        } else {
            document.getElementById('mic-status-text').textContent = '未连接';
            document.getElementById('mic-hint').textContent        = '点击「麦克风」重新开始';
        }
    };
}

function stopMic() { if (micWs) { micWs.close(); micWs = null; } }

// 音量条和波形图画起来，看着舒服
function updateVolMeter(rms) { document.getElementById('vol-fill').style.width = Math.min(rms * 400, 100) + '%'; }
function clearVolMeter()     { document.getElementById('vol-fill').style.width = '0%'; }
function drawWave(f32) {
    const W = waveCanvas.width, H = waveCanvas.height;
    waveCtx.clearRect(0, 0, W, H);
    waveCtx.fillStyle = '#0d0d1a'; waveCtx.fillRect(0, 0, W, H);
    waveCtx.beginPath(); waveCtx.strokeStyle = '#6e40c9'; waveCtx.lineWidth = 1.5;
    const step = Math.max(1, Math.floor(f32.length / W));
    for (let x = 0; x < W; x++) {
        const y = (H / 2) + (f32[x * step] || 0) * (H / 2) * 0.9;
        x === 0 ? waveCtx.moveTo(x, y) : waveCtx.lineTo(x, y);
    }
    waveCtx.stroke();
}

// 全屏切换，把摄像头面板放大看
function toggleFullscreen() {
    const el = document.querySelector('.wd-cam-video-panel');
    if (!document.fullscreenElement) el.requestFullscreen?.();
    else document.exitFullscreen?.();
}

initHosts();
window.addEventListener('beforeunload', () => { if (camWs) camWs.close(); if (micWs) micWs.close(); });
</script>

<?php include WD_THEME_DIR . '/templates/partials/layout-close.php'; ?>
