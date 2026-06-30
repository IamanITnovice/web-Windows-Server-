<?php
defined('ABSPATH') || exit;
include WD_THEME_DIR . '/templates/partials/layout-open.php';
?>

<div class="wd-page">
  <div class="wd-page-header">
    <h1 class="wd-page-title">主机管理</h1>
    <div class="wd-header-right">
      <select id="filter-status" class="wd-select" title="状态筛选" aria-label="状态筛选" onchange="loadHosts(1)">
        <option value="">全部状态</option>
        <option value="online">在线</option>
        <option value="offline">离线</option>
      </select>
      <button class="wd-btn wd-btn--ghost" onclick="loadHosts(1)">刷新</button>
    </div>
  </div>

  <!-- 心跳探测设置 -->
  <div class="wd-card" style="margin-bottom:14px">
    <div class="wd-card-body" style="display:flex;align-items:center;gap:16px;flex-wrap:wrap;padding:10px 16px">
      <span style="font-weight:600;font-size:13px">🔍 在线检测</span>
      <label class="wd-toggle-label" style="gap:6px">
        <input type="checkbox" id="probe-enabled" checked onchange="toggleProbe(this.checked)">
        <span class="wd-sm">启用</span>
      </label>
      <div style="display:flex;align-items:center;gap:6px">
        <span class="wd-sm wd-muted">刷新间隔：</span>
        <select id="probe-interval" class="wd-select wd-select--sm" onchange="saveProbeInterval(this.value)" style="min-width:90px">
          <option value="3">3 秒</option>
          <option value="5">5 秒</option>
          <option value="10" selected>10 秒</option>
          <option value="30">30 秒</option>
          <option value="60">60 秒</option>
        </select>
      </div>
      <span class="wd-sm wd-muted">（主机 35s 无心跳即标为下线，下线/上线时自动通知）</span>
      <span id="probe-status" class="wd-sm" style="margin-left:auto;color:#8be9fd"></span>
    </div>
  </div>

  <div class="wd-card">
    <div class="wd-card-body wd-p0">
      <table class="wd-table wd-table--hover">
        <thead><tr><th>主机名</th><th>Machine ID</th><th>IP 地址</th><th>系统</th><th>状态</th><th>最后心跳</th><th>操作</th></tr></thead>
        <tbody id="hosts-tbody"><tr><td colspan="7" class="wd-center wd-muted">加载中...</td></tr></tbody>
      </table>
    </div>
    <div class="wd-card-footer">
      <div id="hosts-pagination" class="wd-pagination"></div>
      <span id="hosts-total" class="wd-total"></span>
    </div>
  </div>
</div>

<!-- 重命名弹窗 -->
<div class="wd-modal" id="rename-modal" style="display:none">
  <div class="wd-modal-backdrop" onclick="closeModal('rename-modal')"></div>
  <div class="wd-modal-dialog">
    <div class="wd-modal-header"><h3>重命名主机</h3><button class="wd-modal-close" onclick="closeModal('rename-modal')">✕</button></div>
    <div class="wd-modal-body">
      <input type="hidden" id="rename-id">
      <div class="wd-form-group">
        <label class="wd-label">主机名称</label>
        <input type="text" class="wd-input" id="rename-name">
      </div>
    </div>
    <div class="wd-modal-footer">
      <button class="wd-btn wd-btn--ghost" onclick="closeModal('rename-modal')">取消</button>
      <button class="wd-btn wd-btn--primary" onclick="submitRename()">保存</button>
    </div>
  </div>
</div>

<script>
document.getElementById('wd-topbar-title').textContent = '主机管理';
let hostPage = 1;

// ── 心跳探测（基于心跳时间戳，无命令队列污染）────────────────────────
// 原理：客户端每 30s 上报一次心跳；服务端在每次 wd_get_hosts 时顺便执行
// mark_offline_stale()（超过 35s 无心跳 → offline）。
// 前端只需以自定义间隔轮询 loadHosts，即可立即感知下线。
const PROBE_KEY = 'wd_probe_sec';
let probeIntervalSec = parseInt(localStorage.getItem(PROBE_KEY) || '10');
let probeEnabled     = true;
let probeTimer       = null;
let prevStatusMap    = {};   // hostId → 'online'|'offline'（用于变化通知）

// 从 localStorage 恢复之前保存的探测间隔
(function(){
    const sel = document.getElementById('probe-interval');
    const saved = localStorage.getItem(PROBE_KEY);
    if (saved) {
        const opt = [...sel.options].find(o => o.value === saved);
        if (opt) opt.selected = true;
    }
})();

function saveProbeInterval(v) {
    probeIntervalSec = parseInt(v);
    localStorage.setItem(PROBE_KEY, v);
    restartProbeTimer();
    document.getElementById('probe-status').textContent = `间隔已更新为 ${v}s`;
}

function toggleProbe(on) {
    probeEnabled = on;
    if (on) {
        restartProbeTimer();
        document.getElementById('probe-status').textContent = '探测已开启';
    } else {
        if (probeTimer) { clearInterval(probeTimer); probeTimer = null; }
        document.getElementById('probe-status').textContent = '探测已暂停';
    }
}

function restartProbeTimer() {
    if (probeTimer) clearInterval(probeTimer);
    if (!probeEnabled) return;
    probeTimer = setInterval(() => loadHosts(hostPage, false), probeIntervalSec * 1000);
}

// 主机列表加载和渲染，带着心跳状态变化检测
async function loadHosts(page, resetTimer = true) {
    hostPage = page || 1;
    const status = document.getElementById('filter-status').value;
    const res = await WD.ajax('wd_get_hosts', { page: hostPage, status });
    if (!res.success) return;

    const { items, total } = res.data;

    // 对比上次的状态，在线变下线或者下线变在线都弹通知
    const newMap = {};
    items.forEach(h => { newMap[h.id] = h.status; });
    if (Object.keys(prevStatusMap).length) {
        items.forEach(h => {
            if (prevStatusMap[h.id] === 'online' && h.status === 'offline') {
                WD.toast(`⚠ 主机「${h.name}」已下线`, 'error', 6000);
            } else if (prevStatusMap[h.id] === 'offline' && h.status === 'online') {
                WD.toast(`✓ 主机「${h.name}」已上线`, 'success', 4000);
            }
        });
    }
    prevStatusMap = newMap;

    const onlineCnt = items.filter(h => h.status === 'online').length;
    document.getElementById('probe-status').textContent =
        probeEnabled ? `探测中（每 ${probeIntervalSec}s 刷新 · ${onlineCnt} 台在线）` : '探测已暂停';

    const tbody = document.getElementById('hosts-tbody');
    tbody.innerHTML = items.length ? items.map(h => `
        <tr>
          <td><span class="wd-dot wd-dot--${h.status==='online'?'green':'gray'}"></span><strong>${escHtml(h.name)}</strong></td>
          <td class="wd-mono wd-sm">${escHtml(h.machine_id.slice(0,16))}…</td>
          <td class="wd-mono">${escHtml(h.ip_last)}</td>
          <td class="wd-sm wd-muted">${escHtml(h.os_info)}</td>
          <td><span class="wd-badge wd-badge--${h.status==='online'?'green':'gray'}">${h.status==='online'?'在线':'离线'}</span></td>
          <td class="wd-sm wd-muted">${h.last_seen||'—'}</td>
          <td>
            <div class="wd-actions">
              <a href="${WD.pages['wd-screen']}?host_id=${h.id}"  class="wd-btn wd-btn--xs wd-btn--primary">屏幕</a>
              <a href="${WD.pages['wd-console']}?host_id=${h.id}" class="wd-btn wd-btn--xs wd-btn--blue">终端</a>
              <a href="${WD.pages['wd-logs']}?host_id=${h.id}"    class="wd-btn wd-btn--xs wd-btn--ghost">日志</a>
              <button class="wd-btn wd-btn--xs wd-btn--ghost" onclick="openRename(${h.id},'${escHtml(h.name).replace(/'/g,"\\'")}')">改名</button>
              <button class="wd-btn wd-btn--xs wd-btn--danger" onclick="WD.confirm('删除主机 &quot;${escHtml(h.name)}&quot; 及所有日志？',()=>deleteHost(${h.id}))">删除</button>
            </div>
          </td>
        </tr>`).join('')
      : '<tr><td colspan="7" class="wd-center wd-muted">暂无主机</td></tr>';

    document.getElementById('hosts-total').textContent = '共 ' + total + ' 台';
    renderPagination(total, hostPage, 20, p => loadHosts(p), 'hosts-pagination');

    // 手动翻页或刷新时重置轮询计时器，省得刚发完请求又来一遍
    if (resetTimer) restartProbeTimer();
}

function openRename(id, name) {
    document.getElementById('rename-id').value  = id;
    document.getElementById('rename-name').value = name;
    openModal('rename-modal');
}
async function submitRename() {
    const id   = document.getElementById('rename-id').value;
    const name = document.getElementById('rename-name').value.trim();
    if (!name) return WD.toast('名称不能为空','error');
    const res = await WD.ajax('wd_rename_host', { id, name }, 'POST');
    if (res.success) { closeModal('rename-modal'); loadHosts(hostPage); WD.toast('已重命名'); }
}
async function deleteHost(id) {
    const res = await WD.ajax('wd_delete_host', { id }, 'POST');
    if (res.success) { loadHosts(hostPage); WD.toast('已删除'); }
}

loadHosts(1);
</script>

<?php include WD_THEME_DIR . '/templates/partials/layout-close.php'; ?>
