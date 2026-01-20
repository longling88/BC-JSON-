<?php
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');

$configFile = dirname(__FILE__) . '/admin/config.json';
if (!file_exists($configFile)) { echo json_encode([]); exit; }

$config = json_decode(file_get_contents($configFile), true) ?? [];

// 关键修复：确保路径正确
function fixPath($path) {
    if (empty($path)) return '';
    if (strpos($path, 'http') === 0) return $path;
    // 如果后台存的是 img/xxx，这里保持原样即可
    return $path; 
}

$config['logo'] = fixPath($config['logo'] ?? '');
$config['bg_pc'] = fixPath($config['bg_pc'] ?? '');
$config['bg_mobile'] = fixPath($config['bg_mobile'] ?? '');

echo json_encode($config, JSON_UNESCAPED_UNICODE);
?>