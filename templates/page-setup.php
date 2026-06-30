<?php
/**
 * WatchDog 安装向导 — 仅在 wd_setup_complete 未设置时可访问
 * 完成后自动跳转仪表盘，之后本页不再可见
 */
defined('ABSPATH') || exit;

// 已完成安装的用户直接跳走
if (get_option('wd_setup_complete') && wd_is_logged_in()) {
    $dash     = get_page_by_path('wd-dashboard');
    $dash_url = $dash ? get_permalink($dash->ID) : home_url('/wd-dashboard/');
    if (!headers_sent()) {
        wp_redirect($dash_url);
        exit;
    }
    echo '<script>window.location.replace(' . wp_json_encode($dash_url) . ');</script>';
    exit;
}
?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
<meta charset="<?php bloginfo('charset'); ?>">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>WatchDog — 安装向导</title>
<?php wp_head(); ?>
<style>
*, *::before, *::after { box-sizing: border-box; }
body {
    margin: 0;
    background: #0d1117;
    color: #c9d1d9;
    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
    min-height: 100vh;
    display: flex; align-items: center; justify-content: center;
}

/* ── 外框 ── */
.wds-wrap {
    width: 100%; max-width: 680px;
    padding: 24px 16px;
}
.wds-logo {
    display: flex; align-items: center; gap: 12px;
    margin-bottom: 32px; justify-content: center;
}
.wds-logo-icon {
    width: 48px; height: 48px; border-radius: 50%;
    background: linear-gradient(135deg, #1a56db, #58a6ff);
    display: flex; align-items: center; justify-content: center;
    font-size: 1.5rem; color: #fff; font-weight: 800;
}
.wds-logo-text { font-size: 1.5rem; font-weight: 700; color: #e6edf3; }
.wds-logo-sub  { font-size: .8rem; color: #8b949e; }

/* ── 进度条 ── */
.wds-progress {
    display: flex; align-items: center; gap: 0;
    margin-bottom: 32px;
    background: #161b22; border: 1px solid #30363d;
    border-radius: 12px; padding: 4px;
}
.wds-step-dot {
    flex: 1; display: flex; flex-direction: column; align-items: center;
    gap: 4px; padding: 12px 8px; border-radius: 8px;
    font-size: .72rem; color: #8b949e; transition: all .25s; cursor: default;
    position: relative;
}
.wds-step-dot::after {
    content: ''; position: absolute; right: -1px; top: 50%; transform: translateY(-50%);
    width: 1px; height: 60%; background: #30363d;
}
.wds-step-dot:last-child::after { display: none; }
.wds-step-num {
    width: 26px; height: 26px; border-radius: 50%; border: 2px solid #30363d;
    display: flex; align-items: center; justify-content: center;
    font-size: .75rem; font-weight: 700; color: #8b949e; transition: all .25s;
}
.wds-step-dot--active .wds-step-num  { border-color: #58a6ff; color: #58a6ff; background: rgba(88,166,255,.1); }
.wds-step-dot--active               { color: #e6edf3; }
.wds-step-dot--done .wds-step-num   { border-color: #3fb950; background: #3fb950; color: #fff; }
.wds-step-dot--done                 { color: #3fb950; }

/* ── 卡片 ── */
.wds-card {
    background: #161b22; border: 1px solid #30363d;
    border-radius: 12px; padding: 32px;
    animation: wds-in .3s ease;
}
@keyframes wds-in { from { opacity:0; transform:translateY(12px); } to { opacity:1; transform:none; } }
.wds-card-title {
    font-size: 1.15rem; font-weight: 700; color: #e6edf3;
    margin: 0 0 6px;
}
.wds-card-desc { font-size: .875rem; color: #8b949e; margin: 0 0 24px; }

/* ── 检测列表 ── */
.wds-checklist { list-style: none; padding: 0; margin: 0 0 24px; display: flex; flex-direction: column; gap: 8px; }
.wds-check-item {
    display: flex; align-items: center; gap: 12px;
    padding: 12px 16px; background: #0d1117; border: 1px solid #30363d;
    border-radius: 8px; font-size: .875rem;
}
.wds-check-item--ok     { border-color: rgba(63,185,80,.3); }
.wds-check-item--fail   { border-color: rgba(248,81,73,.3); }
.wds-check-icon { font-size: 1rem; flex-shrink: 0; }
.wds-check-label { flex: 1; color: #c9d1d9; }
.wds-check-status { font-size: .8rem; }
.wds-check-status--ok   { color: #3fb950; }
.wds-check-status--fail { color: #f85149; }
.wds-check-status--wait { color: #8b949e; }

/* ── 秘钥显示框 ── */
.wds-secret-box {
    background: #0d1117; border: 1px solid #30363d; border-radius: 8px;
    padding: 14px 16px; font-family: 'Cascadia Code', Consolas, monospace;
    font-size: .8rem; color: #58a6ff; word-break: break-all;
    margin: 16px 0; position: relative;
}
.wds-copy-btn {
    position: absolute; right: 10px; top: 50%; transform: translateY(-50%);
    background: rgba(88,166,255,.1); border: 1px solid rgba(88,166,255,.2);
    color: #58a6ff; border-radius: 6px; padding: 4px 10px;
    font-size: .72rem; cursor: pointer; transition: all .2s;
}
.wds-copy-btn:hover { background: rgba(88,166,255,.2); }

/* ── 输入组 ── */
.wds-field { margin-bottom: 18px; }
.wds-label { display: block; font-size: .8rem; color: #8b949e; margin-bottom: 6px; font-weight: 500; }
.wds-input {
    width: 100%; padding: 10px 14px;
    background: #0d1117; border: 1px solid #30363d;
    border-radius: 8px; color: #e6edf3; font-size: .9rem;
    transition: border-color .2s; outline: none;
}
.wds-input:focus { border-color: #58a6ff; }
.wds-input-help { font-size: .75rem; color: #8b949e; margin-top: 5px; }

/* ── 按钮 ── */
.wds-actions { display: flex; align-items: center; gap: 12px; flex-wrap: wrap; }
.wds-btn {
    display: inline-flex; align-items: center; gap: 7px;
    padding: 10px 22px; border-radius: 8px; font-size: .875rem;
    font-weight: 600; border: none; cursor: pointer; transition: all .2s;
    text-decoration: none;
}
.wds-btn--primary { background: #1f6feb; color: #fff; }
.wds-btn--primary:hover { background: #388bfd; }
.wds-btn--secondary { background: rgba(255,255,255,.05); border: 1px solid #30363d; color: #c9d1d9; }
.wds-btn--secondary:hover { background: rgba(255,255,255,.1); }
.wds-btn--success { background: #238636; color: #fff; }
.wds-btn--success:hover { background: #2ea043; }
.wds-btn:disabled { opacity: .5; cursor: not-allowed; }

/* ── 消息提示 ── */
.wds-msg {
    padding: 10px 14px; border-radius: 8px; font-size: .85rem; margin: 12px 0; display: none;
}
.wds-msg--ok   { background: rgba(63,185,80,.1);  border: 1px solid rgba(63,185,80,.3);  color: #3fb950; }
.wds-msg--err  { background: rgba(248,81,73,.1);  border: 1px solid rgba(248,81,73,.3);  color: #f85149; }
.wds-msg--warn { background: rgba(210,153,34,.1); border: 1px solid rgba(210,153,34,.3); color: #e3b341; }

/* ── 完成页 ── */
.wds-done-icon {
    width: 72px; height: 72px; border-radius: 50%; background: rgba(63,185,80,.15);
    display: flex; align-items: center; justify-content: center;
    font-size: 2rem; margin: 0 auto 20px;
    animation: wds-pop .4s cubic-bezier(.175,.885,.32,1.275);
}
@keyframes wds-pop { from { transform: scale(0); } to { transform: scale(1); } }
.wds-done-title { text-align: center; font-size: 1.3rem; font-weight: 700; color: #e6edf3; margin: 0 0 8px; }
.wds-done-desc  { text-align: center; font-size: .875rem; color: #8b949e; margin: 0 0 28px; }

/* ── spinner ── */
.wds-spinner {
    display: inline-block; width: 14px; height: 14px; border: 2px solid rgba(255,255,255,.3);
    border-top-color: #fff; border-radius: 50%; animation: wds-spin .7s linear infinite;
}
@keyframes wds-spin { to { transform: rotate(360deg); } }
</style>
</head>
<body>
<?php wp_footer(); ?>

<div class="wds-wrap">

  <!-- Logo -->
  <div class="wds-logo">
    <div class="wds-logo-icon">◉</div>
    <div>
      <div class="wds-logo-text">WatchDog</div>
      <div class="wds-logo-sub">安装向导</div>
    </div>
  </div>

  <!-- 进度步骤 -->
  <div class="wds-progress" id="wds-progress">
    <div class="wds-step-dot wds-step-dot--active" data-step="1">
      <div class="wds-step-num">1</div>
      <span>环境检测</span>
    </div>
    <div class="wds-step-dot" data-step="2">
      <div class="wds-step-num">2</div>
      <span>接口配置</span>
    </div>
    <div class="wds-step-dot" data-step="3">
      <div class="wds-step-num">3</div>
      <span>完成</span>
    </div>
  </div>

  <!-- Step 1 — 环境检测 -->
  <div class="wds-card" id="wds-step-1">
    <h2 class="wds-card-title">环境检测</h2>
    <p class="wds-card-desc">检查数据库表和必要依赖，确保 WatchDog 可以正常运行。</p>

    <ul class="wds-checklist" id="wds-check-list">
      <li class="wds-check-item" id="chk-db">
        <span class="wds-check-icon">🗄️</span>
        <span class="wds-check-label">数据库表</span>
        <span class="wds-check-status wds-check-status--wait" id="chk-db-status">检测中…</span>
      </li>
      <li class="wds-check-item" id="chk-secret">
        <span class="wds-check-icon">🔑</span>
        <span class="wds-check-label">WS 鉴权密钥</span>
        <span class="wds-check-status wds-check-status--wait" id="chk-secret-status">检测中…</span>
      </li>
      <li class="wds-check-item" id="chk-ws">
        <span class="wds-check-icon">🔌</span>
        <span class="wds-check-label">WebSocket 中继地址</span>
        <span class="wds-check-status wds-check-status--wait" id="chk-ws-status">检测中…</span>
      </li>
    </ul>

    <!-- 显示密钥供用户复制到 .env -->
    <div id="wds-secret-section" style="display:none">
      <p style="font-size:.85rem;color:#8b949e;margin:0 0 6px">
        将此密钥填入 ws-relay 的 <code style="color:#58a6ff">.env</code> 文件的 <code style="color:#58a6ff">WS_SECRET</code>：
      </p>
      <div class="wds-secret-box" id="wds-secret-val">
        <button class="wds-copy-btn" onclick="copySecret()">复制</button>
      </div>
    </div>

    <div class="wds-msg" id="chk-msg"></div>

    <div class="wds-actions" style="margin-top:8px">
      <button class="wds-btn wds-btn--primary" id="btn-next-1" onclick="goStep2()" disabled>
        下一步：配置接口
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="9 18 15 12 9 6"/></svg>
      </button>
    </div>
  </div>

  <!-- Step 2 — 接口配置 -->
  <div class="wds-card" id="wds-step-2" style="display:none">
    <h2 class="wds-card-title">配置 WebSocket 中继</h2>
    <p class="wds-card-desc">
      填写你部署的 ws-relay 服务地址。客户端和实时屏幕功能依赖此连接。
    </p>

    <div class="wds-field">
      <label class="wds-label" for="wds-ws-input">WebSocket 中继地址</label>
      <input class="wds-input" id="wds-ws-input" type="text"
             placeholder="ws://your-server-ip:8765 或 wss://your-domain.com/ws">
      <p class="wds-input-help">
        若配置了 Nginx 反代：填 <code>wss://your-domain.com/ws</code><br>
        若直接暴露端口：填 <code>ws://your-server-ip:8765</code>
      </p>
    </div>

    <div class="wds-msg" id="ws-msg"></div>

    <div class="wds-actions">
      <button class="wds-btn wds-btn--secondary" onclick="goStep1Back()">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="15 18 9 12 15 6"/></svg>
        返回
      </button>
      <button class="wds-btn wds-btn--primary" id="btn-test-ws" onclick="testAndSaveWS()">
        <span id="btn-test-ws-inner">测试并保存</span>
      </button>
      <button class="wds-btn wds-btn--secondary" id="btn-skip-ws" onclick="skipWS()" style="margin-left:auto;font-size:.8rem">
        跳过（稍后配置）
      </button>
    </div>
  </div>

  <!-- Step 3 — 完成 -->
  <div class="wds-card" id="wds-step-3" style="display:none">
    <div class="wds-done-icon">✓</div>
    <h2 class="wds-done-title">安装完成！</h2>
    <p class="wds-done-desc">WatchDog 已就绪，正在跳转到仪表盘…</p>

    <div id="wds-summary" style="margin-bottom:24px"></div>

    <div class="wds-actions" style="justify-content:center">
      <button class="wds-btn wds-btn--success" id="btn-go-dash" onclick="finishSetup()">
        <span id="btn-go-dash-inner">进入仪表盘</span>
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="9 18 15 12 9 6"/></svg>
      </button>
    </div>
  </div>

</div><!-- .wds-wrap -->

<script>
const WD_AJAX = <?= json_encode(admin_url('admin-ajax.php')) ?>;
const WD_NONCE = <?= json_encode(wp_create_nonce('wd_nonce')) ?>;
let setupData = {};

// ── 工具 ────────────────────────────────────────────────────────
async function wdAjax(action, data = {}) {
    const fd = new FormData();
    fd.append('action', action);
    fd.append('nonce', WD_NONCE);
    for (const [k, v] of Object.entries(data)) fd.append(k, v);
    const r = await fetch(WD_AJAX, { method: 'POST', body: fd });
    return r.json();
}

function showMsg(id, msg, type) {
    const el = document.getElementById(id);
    el.textContent = msg; el.className = 'wds-msg wds-msg--' + type; el.style.display = 'block';
}
function hideMsg(id) { document.getElementById(id).style.display = 'none'; }

function setStepDot(n, state) {
    const dot = document.querySelector(`[data-step="${n}"]`);
    dot.className = 'wds-step-dot' + (state ? ' wds-step-dot--' + state : '');
    if (state === 'done') dot.querySelector('.wds-step-num').textContent = '✓';
}

function setCheckItem(id, status, text) {
    const el  = document.getElementById(id);
    const cls = status === 'ok' ? 'wds-check-item--ok' : (status === 'fail' ? 'wds-check-item--fail' : '');
    el.className = 'wds-check-item ' + cls;
    const st  = el.querySelector('.wds-check-status');
    st.className = 'wds-check-status wds-check-status--' + status;
    st.textContent = text;
}

// ── Step 1：自动运行环境检测 ─────────────────────────────────────
(async function runCheck() {
    const res = await wdAjax('wd_setup_check');
    if (!res.success) {
        setCheckItem('chk-db', 'fail', '创建失败');
        showMsg('chk-msg', res.data?.message || '数据表创建失败，请检查数据库权限', 'err');
        return;
    }
    const d = res.data;
    setupData = d;

    // DB
    setCheckItem('chk-db', 'ok', '✓ 已就绪');

    // WS Secret
    if (d.ws_secret && d.ws_secret.length > 10) {
        setCheckItem('chk-secret', 'ok', '✓ 已生成');
        const sec = document.getElementById('wds-secret-section');
        sec.style.display = 'block';
        document.getElementById('wds-secret-val').childNodes[0].textContent = d.ws_secret;
        document.getElementById('wds-secret-val').insertBefore(
            Object.assign(document.createTextNode(d.ws_secret), {}), 
            document.getElementById('wds-secret-val').firstChild
        );
        // 重写：直接替换文字节点
        const box = document.getElementById('wds-secret-val');
        const btn = box.querySelector('.wds-copy-btn');
        box.textContent = d.ws_secret;
        box.appendChild(btn);
    } else {
        setCheckItem('chk-secret', 'warn', '⚠ 尚未生成（保存配置后会自动生成）');
    }

    // WS host
    if (d.ws_host) {
        setCheckItem('chk-ws', 'ok', '✓ ' + d.ws_host);
        document.getElementById('wds-ws-input').value = d.ws_host;
    } else {
        setCheckItem('chk-ws', 'warn', '⚠ 未配置');
    }

    document.getElementById('btn-next-1').disabled = false;
})();

function copySecret() {
    const box = document.getElementById('wds-secret-val');
    const text = box.textContent.replace('复制', '').trim();
    navigator.clipboard.writeText(text).then(() => {
        const btn = box.querySelector('.wds-copy-btn');
        btn.textContent = '已复制!';
        setTimeout(() => btn.textContent = '复制', 2000);
    });
}

// ── 步骤切换 ─────────────────────────────────────────────────────
function goStep2() {
    document.getElementById('wds-step-1').style.display = 'none';
    document.getElementById('wds-step-2').style.display = '';
    setStepDot(1, 'done'); setStepDot(2, 'active'); setStepDot(3, '');
    // 回填已有地址
    if (setupData.ws_host) document.getElementById('wds-ws-input').value = setupData.ws_host;
}
function goStep1Back() {
    document.getElementById('wds-step-2').style.display = 'none';
    document.getElementById('wds-step-1').style.display = '';
    setStepDot(1, 'active'); setStepDot(2, ''); setStepDot(3, '');
}

async function testAndSaveWS() {
    const ws = document.getElementById('wds-ws-input').value.trim();
    if (!ws) return showMsg('ws-msg', '请填写 WebSocket 地址', 'err');
    const btn = document.getElementById('btn-test-ws-inner');
    btn.innerHTML = '<span class="wds-spinner"></span> 测试中…';
    document.getElementById('btn-test-ws').disabled = true;
    hideMsg('ws-msg');

    const res = await wdAjax('wd_setup_save_ws', { ws_host: ws });
    btn.textContent = '测试并保存';
    document.getElementById('btn-test-ws').disabled = false;

    if (!res.success) return showMsg('ws-msg', res.data?.message || '保存失败', 'err');
    const d = res.data;
    showMsg('ws-msg', d.message, d.ping_ok ? 'ok' : 'warn');
    setTimeout(goFinish, d.ping_ok ? 1200 : 2500);
}

function skipWS() {
    showMsg('ws-msg', '已跳过，可在 KOOK 机器人页面中随时配置 WebSocket 地址', 'warn');
    setTimeout(goFinish, 1500);
}

// ── Step 3：完成 ─────────────────────────────────────────────────
function goFinish() {
    document.getElementById('wds-step-2').style.display = 'none';
    document.getElementById('wds-step-3').style.display = '';
    setStepDot(2, 'done'); setStepDot(3, 'active');

    // 生成摘要
    const ws = document.getElementById('wds-ws-input').value.trim();
    document.getElementById('wds-summary').innerHTML = `
        <div style="background:#0d1117;border:1px solid #30363d;border-radius:8px;padding:16px;font-size:.82rem">
          <div style="display:flex;justify-content:space-between;padding:6px 0;border-bottom:1px solid #21262d">
            <span style="color:#8b949e">数据库</span><span style="color:#3fb950">✓ 就绪</span>
          </div>
          <div style="display:flex;justify-content:space-between;padding:6px 0">
            <span style="color:#8b949e">WebSocket 地址</span>
            <span style="color:${ws?'#c9d1d9':'#8b949e'}">${ws || '未配置（跳过）'}</span>
          </div>
        </div>`;
}

async function finishSetup() {
    const inner = document.getElementById('btn-go-dash-inner');
    inner.innerHTML = '<span class="wds-spinner"></span> 跳转中…';
    document.getElementById('btn-go-dash').disabled = true;
    const res = await wdAjax('wd_setup_finish');
    if (res.success) {
        location.href = res.data.redirect;
    } else {
        inner.textContent = '进入仪表盘';
        document.getElementById('btn-go-dash').disabled = false;
    }
}
</script>
<?php wp_footer(); ?>
</body>
</html>
