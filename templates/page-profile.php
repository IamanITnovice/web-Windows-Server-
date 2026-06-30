<?php
defined('ABSPATH') || exit;
include WD_THEME_DIR . '/templates/partials/layout-open.php';

$user      = wp_get_current_user();
$avatar_ch = strtoupper(substr($user->user_login ?? 'A', 0, 1));
$reg_date  = date('Y年m月d日', strtotime($user->user_registered));
$is_admin  = current_user_can('manage_options');
?>

<div class="wd-page wdp-page">

  <!-- ── Hero 横幅 ── -->
  <div class="wdp-hero">
    <div class="wdp-hero-bg"></div>
    <div class="wdp-hero-content">
      <div class="wdp-hero-avatar">
        <span><?= esc_html($avatar_ch) ?></span>
        <div class="wdp-avatar-glow"></div>
      </div>
      <div class="wdp-hero-info">
        <h1 class="wdp-hero-name"><?= esc_html($user->display_name ?: $user->user_login) ?></h1>
        <p class="wdp-hero-meta">
          <span class="wdp-badge"><?= $is_admin ? '管理员' : '子账号' ?></span>
          <span class="wdp-hero-sep">·</span>
          <span><?= esc_html($user->user_email) ?></span>
          <span class="wdp-hero-sep">·</span>
          <span>注册于 <?= $reg_date ?></span>
        </p>
      </div>
      <button type="button" class="wdp-logout-btn" title="退出登录" id="wdp-logout-trigger">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/>
          <polyline points="16 17 21 12 16 7"/>
          <line x1="21" y1="12" x2="9" y2="12"/>
        </svg>
        退出登录
      </button>
    </div>
  </div>

  <!-- ── Tab 导航 ── -->
  <div class="wdp-tabs">
    <button class="wdp-tab wdp-tab--active" data-tab="info">账号信息</button>
    <button class="wdp-tab" data-tab="password">修改密码</button>
  </div>

  <!-- ── Tab: 账号信息 ── -->
  <div class="wdp-tab-panel" id="wdp-panel-info">
    <div class="wdp-section">
      <h3 class="wdp-section-title">基本资料</h3>
      <div class="wdp-field-grid">
        <div class="wdp-field">
          <label class="wdp-field-label">显示名称</label>
          <input type="text" class="wd-input" id="p-nickname"
                 value="<?= esc_attr($user->display_name) ?>" placeholder="侧边栏展示的名称">
        </div>
        <div class="wdp-field">
          <label class="wdp-field-label">邮箱地址</label>
          <input type="email" class="wd-input" id="p-email"
                 value="<?= esc_attr($user->user_email) ?>">
        </div>
      </div>
      <div class="wdp-actions">
        <button class="wd-btn wd-btn--primary wdp-save-btn" onclick="saveProfile()">
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>
          保存更改
        </button>
        <div id="p-info-msg" class="wdp-toast" style="display:none"></div>
      </div>
    </div>

    <div class="wdp-section">
      <h3 class="wdp-section-title">账号详情</h3>
      <div class="wdp-detail-list">
        <div class="wdp-detail-item">
          <span class="wdp-detail-key">用户名</span>
          <span class="wdp-detail-val wd-mono"><?= esc_html($user->user_login) ?></span>
        </div>
        <div class="wdp-detail-item">
          <span class="wdp-detail-key">账号 ID</span>
          <span class="wdp-detail-val wd-mono">#<?= $user->ID ?></span>
        </div>
        <div class="wdp-detail-item">
          <span class="wdp-detail-key">角色</span>
          <span class="wdp-detail-val"><span class="wdp-badge">管理员</span></span>
        </div>
        <div class="wdp-detail-item">
          <span class="wdp-detail-key">注册时间</span>
          <span class="wdp-detail-val"><?= $reg_date ?></span>
        </div>
      </div>
    </div>
  </div>

  <!-- ── Tab: 修改密码 ── -->
  <div class="wdp-tab-panel" id="wdp-panel-password" style="display:none">
    <div class="wdp-section wdp-section--narrow">
      <h3 class="wdp-section-title">修改登录密码</h3>
      <p class="wdp-section-desc">密码修改后需重新登录。建议使用 8 位以上包含大小写、数字和特殊符号的强密码。</p>

      <div class="wdp-pass-form">
        <div class="wdp-field">
          <label class="wdp-field-label">当前密码</label>
          <div class="wdp-pass-wrap">
            <input type="password" class="wd-input" id="p-old" autocomplete="current-password">
            <button type="button" class="wdp-eye" onclick="wdpToggle('p-old',this)" aria-label="显示密码">
              <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
            </button>
          </div>
        </div>

        <div class="wdp-field">
          <label class="wdp-field-label">新密码</label>
          <div class="wdp-pass-wrap">
            <input type="password" class="wd-input" id="p-new" autocomplete="new-password" oninput="wdpStrength(this.value)">
            <button type="button" class="wdp-eye" onclick="wdpToggle('p-new',this)" aria-label="显示密码">
              <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
            </button>
          </div>
          <div id="wdp-strength-wrap" class="wdp-strength" style="display:none">
            <div class="wdp-strength-bar"><div id="wdp-strength-fill"></div></div>
            <span id="wdp-strength-label"></span>
          </div>
        </div>

        <div class="wdp-field">
          <label class="wdp-field-label">确认新密码</label>
          <div class="wdp-pass-wrap">
            <input type="password" class="wd-input" id="p-confirm" autocomplete="new-password">
            <button type="button" class="wdp-eye" onclick="wdpToggle('p-confirm',this)" aria-label="显示密码">
              <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
            </button>
          </div>
        </div>

        <div class="wdp-actions">
          <button class="wd-btn wd-btn--primary wdp-save-btn" onclick="changePassword()">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
            更新密码
          </button>
          <div id="p-pass-msg" class="wdp-toast" style="display:none"></div>
        </div>
      </div>
    </div>
  </div>

</div>

<style>
/* ── 个人中心专属样式 ─────────────────────────────── */
.wdp-page { padding: 0; }

/* Hero */
.wdp-hero {
    position: relative; overflow: hidden;
    padding: 36px 32px 32px;
    background: var(--c-surface);
    border-bottom: 1px solid var(--c-border);
}
.wdp-hero-bg {
    position: absolute; inset: 0; pointer-events: none;
    background: radial-gradient(ellipse 80% 60% at 10% 50%, rgba(88,166,255,.08) 0%, transparent 70%),
                radial-gradient(ellipse 40% 80% at 90% 20%, rgba(88,166,255,.05) 0%, transparent 60%);
}
.wdp-hero-content {
    position: relative; display: flex; align-items: center; gap: 22px;
}
.wdp-hero-avatar {
    position: relative; flex-shrink: 0;
    width: 72px; height: 72px;
}
.wdp-hero-avatar span {
    width: 72px; height: 72px; border-radius: 50%;
    background: linear-gradient(135deg, #1a56db, #58a6ff);
    color: #fff; font-size: 1.9rem; font-weight: 800;
    display: flex; align-items: center; justify-content: center;
    position: relative; z-index: 1;
}
.wdp-avatar-glow {
    position: absolute; inset: -3px; border-radius: 50%;
    background: linear-gradient(135deg, #1a56db44, #58a6ff44);
    animation: wd-pulse 3s infinite;
}
.wdp-hero-info { flex: 1; min-width: 0; }
.wdp-hero-name { font-size: 1.4rem; font-weight: 700; color: var(--c-text); margin: 0 0 8px; }
.wdp-hero-meta {
    display: flex; align-items: center; flex-wrap: wrap; gap: 6px;
    font-size: .82rem; color: var(--c-muted);
}
.wdp-hero-sep { opacity: .4; }
.wdp-badge {
    font-size: .68rem; background: rgba(88,166,255,.15); color: var(--c-blue);
    padding: 2px 9px; border-radius: 20px; font-weight: 600;
}
.wdp-logout-btn {
    display: flex; align-items: center; gap: 7px;
    padding: 9px 18px; border-radius: var(--c-radius-s);
    background: rgba(248,81,73,.1); color: var(--c-red);
    border: 1px solid rgba(248,81,73,.2); font-size: .85rem; font-weight: 500;
    text-decoration: none; transition: all var(--c-transition); flex-shrink: 0;
}
.wdp-logout-btn:hover { background: rgba(248,81,73,.2); border-color: var(--c-red); }

/* Tabs */
.wdp-tabs {
    display: flex; gap: 0;
    padding: 0 32px;
    background: var(--c-surface);
    border-bottom: 1px solid var(--c-border);
}
.wdp-tab {
    padding: 14px 20px; background: none; border: none; cursor: pointer;
    font-size: .875rem; color: var(--c-muted); font-weight: 500;
    border-bottom: 2px solid transparent; margin-bottom: -1px;
    transition: all var(--c-transition);
}
.wdp-tab:hover { color: var(--c-text); }
.wdp-tab--active { color: var(--c-blue); border-bottom-color: var(--c-blue); }

/* Panels */
.wdp-tab-panel { padding: 28px 32px; }
.wdp-section { margin-bottom: 32px; }
.wdp-section--narrow { max-width: 520px; }
.wdp-section-title {
    font-size: .9rem; font-weight: 700; color: var(--c-text);
    margin: 0 0 4px; text-transform: uppercase; letter-spacing: .04em;
}
.wdp-section-desc { font-size: .82rem; color: var(--c-muted); margin: 0 0 20px; }

/* Field grid */
.wdp-field-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; margin-bottom: 20px; }
@media (max-width: 640px) { .wdp-field-grid { grid-template-columns: 1fr; } }
.wdp-field { display: flex; flex-direction: column; gap: 6px; }
.wdp-field-label { font-size: .78rem; color: var(--c-muted); font-weight: 500; }

/* Password input */
.wdp-pass-form { display: flex; flex-direction: column; gap: 16px; }
.wdp-pass-wrap { position: relative; }
.wdp-pass-wrap .wd-input { padding-right: 42px; }
.wdp-eye {
    position: absolute; right: 10px; top: 50%; transform: translateY(-50%);
    background: none; border: none; cursor: pointer; padding: 4px;
    color: var(--c-muted); transition: color var(--c-transition); line-height: 1;
}
.wdp-eye:hover { color: var(--c-text); }

/* Strength bar */
.wdp-strength { display: flex; align-items: center; gap: 10px; margin-top: 6px; }
.wdp-strength-bar {
    flex: 1; height: 3px; background: var(--c-border); border-radius: 2px; overflow: hidden;
}
.wdp-strength-bar > div { height: 100%; border-radius: 2px; transition: all .35s; }
#wdp-strength-label { font-size: .72rem; min-width: 30px; font-weight: 600; }

/* Actions */
.wdp-actions { display: flex; align-items: center; gap: 14px; margin-top: 4px; }
.wdp-save-btn { display: flex; align-items: center; gap: 7px; }

/* Detail list */
.wdp-detail-list {
    display: grid; grid-template-columns: 1fr 1fr; gap: 0;
    border: 1px solid var(--c-border); border-radius: var(--c-radius-s); overflow: hidden;
}
@media (max-width: 640px) { .wdp-detail-list { grid-template-columns: 1fr; } }
.wdp-detail-item {
    display: flex; justify-content: space-between; align-items: center;
    padding: 13px 18px; font-size: .85rem;
    border-bottom: 1px solid var(--c-border-s);
    border-right: 1px solid var(--c-border-s);
}
.wdp-detail-item:nth-child(even) { border-right: none; }
.wdp-detail-item:nth-last-child(-n+2) { border-bottom: none; }
.wdp-detail-key { color: var(--c-muted); }
.wdp-detail-val { color: var(--c-text); font-weight: 500; }

/* Toast 行内提示 */
.wdp-toast {
    padding: 8px 16px; border-radius: var(--c-radius-s);
    font-size: .82rem; animation: wd-modal-in .2s ease;
}
.wdp-toast--ok  { background: rgba(63,185,80,.12); color: var(--c-green); border:1px solid rgba(63,185,80,.25); }
.wdp-toast--err { background: rgba(248,81,73,.12); color: var(--c-red);   border:1px solid rgba(248,81,73,.25); }
</style>

<script>
document.getElementById('wd-topbar-title').textContent = '个人中心';

// Tab 切换
document.querySelectorAll('.wdp-tab').forEach(tab => {
    tab.addEventListener('click', () => {
        document.querySelectorAll('.wdp-tab').forEach(t => t.classList.remove('wdp-tab--active'));
        document.querySelectorAll('.wdp-tab-panel').forEach(p => p.style.display = 'none');
        tab.classList.add('wdp-tab--active');
        document.getElementById('wdp-panel-' + tab.dataset.tab).style.display = '';
    });
});

// 保存资料
async function saveProfile() {
    const nickname = document.getElementById('p-nickname').value.trim();
    const email    = document.getElementById('p-email').value.trim();
    if (!email) return showToast('p-info-msg', '邮箱不能为空', 'err');
    const res = await WD.ajax('wd_update_profile', { nickname, email }, 'POST');
    if (res.success) {
        showToast('p-info-msg', '已保存', 'ok');
        // 同步更新侧边栏头像首字
        if (nickname) {
            const ab = document.querySelector('.wd-avatar-btn');
            if (ab) ab.textContent = nickname[0].toUpperCase();
        }
    } else {
        showToast('p-info-msg', res.data?.message || '保存失败', 'err');
    }
}

// 修改密码
async function changePassword() {
    const old = document.getElementById('p-old').value;
    const nw  = document.getElementById('p-new').value;
    const cf  = document.getElementById('p-confirm').value;
    if (!old || !nw || !cf) return showToast('p-pass-msg', '请填写所有字段', 'err');
    if (nw !== cf)           return showToast('p-pass-msg', '两次密码不一致', 'err');
    if (nw.length < 8)       return showToast('p-pass-msg', '新密码至少 8 位', 'err');
    const res = await WD.ajax('wd_change_password', { old_password: old, new_password: nw }, 'POST');
    if (res.success) {
        showToast('p-pass-msg', res.data.message || '密码已更新，2 秒后跳转登录页', 'ok');
        ['p-old','p-new','p-confirm'].forEach(id => document.getElementById(id).value = '');
        setTimeout(() => location.href = <?= json_encode(get_permalink(get_page_by_path('wd-login')) ?: home_url('/wd-login/')) ?>, 2200);
    } else {
        showToast('p-pass-msg', res.data?.message || '修改失败', 'err');
    }
}

// 密码强度
function wdpStrength(v) {
    const wrap = document.getElementById('wdp-strength-wrap');
    const fill = document.getElementById('wdp-strength-fill');
    const lbl  = document.getElementById('wdp-strength-label');
    if (!v) { wrap.style.display='none'; return; }
    wrap.style.display='flex';
    let s = 0;
    if (v.length >= 8)  s++;
    if (v.length >= 12) s++;
    if (/[A-Z]/.test(v)) s++;
    if (/[0-9]/.test(v)) s++;
    if (/[^A-Za-z0-9]/.test(v)) s++;
    const L = [{w:'20%',c:'#f85149',t:'极弱'},{w:'40%',c:'#f97316',t:'弱'},{w:'60%',c:'#e3b341',t:'一般'},{w:'80%',c:'#84cc16',t:'强'},{w:'100%',c:'#3fb950',t:'极强'}];
    const l = L[Math.min(s-1,4)] || L[0];
    fill.style.width=l.w; fill.style.background=l.c;
    lbl.textContent=l.t; lbl.style.color=l.c;
}

// 显示/隐藏密码（切换 SVG 图标）
function wdpToggle(id, btn) {
    const inp = document.getElementById(id);
    const isText = inp.type === 'text';
    inp.type = isText ? 'password' : 'text';
    btn.innerHTML = isText
        ? '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>'
        : '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94"/><path d="M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19"/><line x1="1" y1="1" x2="23" y2="23"/></svg>';
}

// Toast 提示
function showToast(id, msg, type) {
    const el = document.getElementById(id);
    el.textContent = msg;
    el.className = 'wdp-toast wdp-toast--' + type;
    el.style.display = 'block';
    setTimeout(() => { el.style.display = 'none'; }, 4000);
}

// 锚点直跳密码 tab
if (location.hash === '#change-password') {
    document.querySelector('[data-tab="password"]')?.click();
}

// ── 退出登录（AJAX，自定义确认弹窗）────────────────────────────────
document.addEventListener('DOMContentLoaded', function () {
    var trigger = document.getElementById('wdp-logout-trigger');
    var modal   = document.getElementById('wdp-logout-modal');
    var cancel  = document.getElementById('wdp-logout-cancel');
    var confirm = document.getElementById('wdp-logout-confirm');
    if (!trigger || !modal || !cancel || !confirm) return;

    trigger.addEventListener('click', () => { modal.style.display = 'flex'; });
    cancel.addEventListener('click',  () => { modal.style.display = 'none'; });
    confirm.addEventListener('click', async () => {
        confirm.disabled    = true;
        confirm.textContent = '退出中...';
        try {
            const body = new URLSearchParams({ action: 'wd_logout', nonce: WD.nonce });
            const r    = await fetch(WD.ajax_url, { method: 'POST', body });
            const data = await r.json();
            if (data.success) {
                location.href = data.data.redirect;
            } else {
                alert('退出失败，请刷新页面重试');
                confirm.disabled    = false;
                confirm.textContent = '确认退出';
            }
        } catch (e) {
            alert('网络错误，请刷新页面重试');
            confirm.disabled    = false;
            confirm.textContent = '确认退出';
        }
    });
});
</script>

<!-- 退出登录确认弹窗 -->
<div id="wdp-logout-modal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.6);z-index:9999;align-items:center;justify-content:center;">
  <div style="background:#1c2030;border:1px solid #30363d;border-radius:12px;padding:28px 32px;max-width:360px;width:90%;text-align:center;">
    <div style="font-size:32px;margin-bottom:12px;">🚪</div>
    <h3 style="color:#e6edf3;margin:0 0 8px;font-size:16px;">确认退出登录？</h3>
    <p style="color:#8b949e;font-size:14px;margin:0 0 24px;">退出后需重新登录才能访问管理平台。</p>
    <div style="display:flex;gap:12px;justify-content:center;">
      <button id="wdp-logout-cancel"
              style="padding:8px 20px;border-radius:6px;border:1px solid #30363d;background:transparent;color:#e6edf3;cursor:pointer;font-size:14px;">
        取消
      </button>
      <button id="wdp-logout-confirm"
              style="padding:8px 20px;border-radius:6px;border:none;background:#f85149;color:#fff;cursor:pointer;font-size:14px;font-weight:600;">
        确认退出
      </button>
    </div>
  </div>
</div>

<?php include WD_THEME_DIR . '/templates/partials/layout-close.php'; ?>
