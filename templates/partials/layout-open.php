<?php
defined('ABSPATH') || exit;
$current_slug = get_post_field('post_name', get_queried_object_id());

$nav_items = [
    ['wd-dashboard', '仪表盘',      'icon-grid'],
    ['wd-hosts',     '主机管理',     'icon-server'],
    ['wd-logs',      '日志中心',     'icon-list'],
    ['wd-screen',    '实时屏幕',     'icon-monitor'],
    ['wd-camera',    '摄像头监控',   'icon-camera'],
    ['wd-console',   'PowerShell',  'icon-terminal'],
    ['wd-processes', '进程管理',     'icon-proc'],
    ['wd-files',     '文件管理',     'icon-folder'],
    ['wd-registry',  '注册表',       'icon-reg'],
    ['wd-winusers',  'Win 用户',     'icon-users'],
    ['wd-delivery',  '远程投递',     'icon-delivery'],
    ['wd-api-keys',  'API 管理',    'icon-key'],
    ['wd-accounts',  '子账号',       'icon-user'],
    ['wd-kook',      'KOOK 机器人',  'icon-bot'],
];
?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
<meta charset="<?php bloginfo('charset'); ?>">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title><?php wp_title('—'); ?> WatchDog</title>
<?php wp_head(); ?>
</head>
<body class="wd-body">

<div class="wd-app">

  <!-- ── 侧边栏 ── -->
  <aside class="wd-sidebar" id="wd-sidebar">
    <div class="wd-sidebar-brand">
      <span class="wd-brand-icon">◉</span>
      <div class="wd-brand-text">
        <span class="wd-brand-name">WatchDog</span>
        <span class="wd-brand-sub">监控平台</span>
      </div>
    </div>

    <nav class="wd-nav">
      <?php foreach ($nav_items as [$slug, $label, $icon]):
          // 管理员专属模块对子账号隐藏
          if (!wd_can_access($slug)) continue;
          $page = get_page_by_path($slug);
          if (!$page) {
              $new_id = wp_insert_post(['post_title'=>$label,'post_name'=>$slug,'post_status'=>'publish','post_type'=>'page']);
              $url = $new_id ? get_permalink($new_id) : home_url('/' . $slug . '/');
          } else {
              $url = get_permalink($page->ID);
          }
      ?>
        <a href="<?= esc_url($url) ?>"
           class="wd-nav-item <?= $current_slug === $slug ? 'wd-nav-item--active' : '' ?>">
          <span class="wd-nav-icon wd-<?= $icon ?>"></span>
          <span class="wd-nav-label"><?= esc_html($label) ?></span>
        </a>
      <?php endforeach; ?>
    </nav>

    <?php
      $cur_user    = wp_get_current_user();
      $avatar_ch   = strtoupper(substr($cur_user->user_login ?? 'A', 0, 1));
      $profile_page = get_page_by_path('wd-profile');
      $profile_url  = $profile_page ? get_permalink($profile_page->ID) : home_url('/wd-profile/');
    ?>
    <div class="wd-sidebar-footer">
      <a href="<?= esc_url($profile_url) ?>" class="wd-avatar-btn" title="个人中心">
        <?= esc_html($avatar_ch) ?>
      </a>
    </div>
  </aside>

  <!-- ── 主内容区 ── -->
  <main class="wd-main">
    <!-- 顶部栏 -->
    <header class="wd-topbar">
      <button class="wd-menu-toggle" onclick="document.getElementById('wd-sidebar').classList.toggle('wd-sidebar--open')" aria-label="菜单">☰</button>
      <div class="wd-topbar-center" id="wd-topbar-title"></div>
      <div class="wd-topbar-right">
        <span class="wd-online-indicator" id="wd-topbar-online">
          <span class="wd-status-dot wd-status-dot--green"></span>
          <span id="wd-online-count">—</span> 在线
        </span>
        <span class="wd-topbar-time" id="wd-topbar-time"></span>
      </div>
    </header>

    <div class="wd-content">
