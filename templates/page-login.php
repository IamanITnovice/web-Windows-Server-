<?php
defined('ABSPATH') || exit;
// 已登录直接跳转
if (wd_is_logged_in()) {
    $dashboard = get_page_by_path('wd-dashboard');
    wp_redirect($dashboard ? get_permalink($dashboard->ID) : home_url('/wd-dashboard/'));
    exit;
}
?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
<meta charset="<?php bloginfo('charset'); ?>">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>WatchDog — 登录</title>
<?php wp_head(); ?>
</head>
<body class="wd-body wd-body--login">

<div class="wd-login-page">
  <div class="wd-login-left">
    <div class="wd-login-brand">
      <div class="wd-login-logo">◉</div>
      <h1 class="wd-login-product">WatchDog</h1>
      <p class="wd-login-tagline">企业级 Windows 远程监控平台</p>
    </div>
    <div class="wd-login-features">
      <div class="wd-login-feature"><span class="wd-feature-icon">⌨</span><span>键盘记录 & 剪贴板监控</span></div>
      <div class="wd-login-feature"><span class="wd-feature-icon">◉</span><span>实时屏幕查看</span></div>
      <div class="wd-login-feature"><span class="wd-feature-icon">⚡</span><span>PowerShell 远程控制台</span></div>
      <div class="wd-login-feature"><span class="wd-feature-icon">🔔</span><span>KOOK 机器人实时推送</span></div>
    </div>
  </div>

  <div class="wd-login-right">
    <div class="wd-login-card">
      <div class="wd-login-card-header">
        <h2>登录管理平台</h2>
        <p class="wd-text-muted">使用 WordPress 管理员账号登录</p>
      </div>

      <div id="wd-login-error" class="wd-login-error" style="display:none"></div>

      <form class="wd-login-form" id="wd-login-form" onsubmit="return false">
        <div class="wd-form-group">
          <label class="wd-label">用户名</label>
          <div class="wd-input-with-icon">
            <span class="wd-input-icon">👤</span>
            <input type="text" class="wd-input wd-input--icon" id="login-username"
                   placeholder="输入管理员用户名" autocomplete="username" required>
          </div>
        </div>

        <div class="wd-form-group">
          <label class="wd-label">密码</label>
          <div class="wd-input-with-icon">
            <span class="wd-input-icon">🔒</span>
            <input type="password" class="wd-input wd-input--icon" id="login-password"
                   placeholder="输入密码" autocomplete="current-password" required>
            <button type="button" class="wd-input-toggle-vis" onclick="togglePasswordVis(this)">👁</button>
          </div>
        </div>

        <button type="submit" class="wd-btn wd-btn--primary wd-btn--full" id="login-btn" onclick="doLogin()">
          <span id="login-btn-text">登 录</span>
          <span id="login-btn-spin" style="display:none">登录中...</span>
        </button>
      </form>

      <div class="wd-login-card-footer">
        <span class="wd-text-muted wd-text-sm">WatchDog Monitor v1.0 — 主题版</span>
      </div>
    </div>
  </div>
</div>

<script>
<?php
$_wd_dash  = get_page_by_path('wd-dashboard');
$_wd_dash_url = $_wd_dash ? get_permalink($_wd_dash->ID) : home_url('/wd-dashboard/');
?>
const WD = {
    ajax_url: '<?= esc_js(admin_url('admin-ajax.php')) ?>',
    nonce:    '<?= esc_js(wp_create_nonce('wd_nonce')) ?>',
    pages:    { 'wd-dashboard': '<?= esc_js($_wd_dash_url) ?>' }
};

function togglePasswordVis(btn) {
    const input = btn.previousElementSibling;
    input.type = input.type === 'password' ? 'text' : 'password';
    btn.textContent = input.type === 'password' ? '👁' : '🙈';
}

async function doLogin() {
    const username = document.getElementById('login-username').value.trim();
    const password = document.getElementById('login-password').value;
    const errEl    = document.getElementById('wd-login-error');
    const btnText  = document.getElementById('login-btn-text');
    const btnSpin  = document.getElementById('login-btn-spin');

    if (!username || !password) {
        errEl.textContent = '请输入用户名和密码';
        errEl.style.display = 'block';
        return;
    }
    btnText.style.display = 'none';
    btnSpin.style.display = '';
    errEl.style.display   = 'none';

    try {
        const body = new URLSearchParams({ action: 'wd_login', nonce: WD.nonce, username, password });
        const r    = await fetch(WD.ajax_url, { method: 'POST', body });

        let data;
        try {
            data = await r.json();
        } catch (_) {
            // 服务器返回了非 JSON（PHP 致命错误页面）
            throw new Error('服务器内部错误（HTTP ' + r.status + '），请检查服务器日志');
        }

        btnText.style.display = '';
        btnSpin.style.display = 'none';

        if (data.success) {
            location.href = data.data.redirect || WD.pages['wd-dashboard'];
        } else {
            errEl.textContent = data.data?.message || '登录失败，请检查账号密码';
            errEl.style.display = 'block';
        }
    } catch (err) {
        btnText.style.display = '';
        btnSpin.style.display = 'none';
        errEl.textContent = err.message || '网络请求失败，请刷新重试';
        errEl.style.display = 'block';
    }
}

document.getElementById('login-password').addEventListener('keydown', e => {
    if (e.key === 'Enter') doLogin();
});
</script>

<?php wp_footer(); ?>
</body>
</html>
