<?php
// admin/auth.php
// 登录凭据设置
// 默认用户名: admin
// 默认密码: admin123
// 改这里就改密码，改完记得用新密码登录

if (session_status() == PHP_SESSION_NONE) {
    ini_set('session.cookie_httponly', 1);
    ini_set('session.use_only_cookies', 1);
    ini_set('session.cookie_secure', 0); // HTTPS网站改成1
    session_name('admin_session');
}

define('ADMIN_USER', 'admin');      // 用户名
define('ADMIN_PASS', 'admin123');   // 密码
?>