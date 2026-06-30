<?php
// 默认模板：重定向到仪表盘（已登录）或登录页
$login_page     = get_page_by_path('wd-login');
$dashboard_page = get_page_by_path('wd-dashboard');

if (wd_is_logged_in() && $dashboard_page) {
    wp_redirect(get_permalink($dashboard_page->ID));
} elseif ($login_page) {
    wp_redirect(get_permalink($login_page->ID));
} else {
    wp_redirect(home_url('/wd-login'));
}
exit;
