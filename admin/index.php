<?php
/**
 * admin/index.php 
 * 终极完整增强版：支持排序、字号控制、轮播图管理、资源预览防缓存
 */
session_start();
include 'auth.php'; 

// 使用绝对路径，避免权限问题
$configFile = __DIR__ . '/config.json';
$imgDir = dirname(__DIR__) . '/img/';

// 创建img目录并设置权限
if (!file_exists($imgDir)) { 
    if (@mkdir($imgDir, 0755, true)) {
        // 设置目录权限
        @chmod($imgDir, 0755);
    } else {
        // 记录错误但不中断执行
        error_log("无法创建img目录: " . $imgDir);
    }
} else {
    // 确保目录有正确权限
    @chmod($imgDir, 0755);
}

// 初始默认配置
$defaultConfig = [
    "titles" => [["text" => "欢迎使用", "size" => "32", "weight" => "bold", "color" => "#d4af37", "stroke_color" => "#000000", "stroke_width" => "1"]],
    "subs" => [["text" => "请在后台配置内容", "size" => "14", "weight" => "normal", "color" => "#ffffff", "stroke_color" => "#000000", "stroke_width" => "1"]],
    "links" => [],
    "carousel" => [],
    "border_width" => "2.0",
    "border_color" => "rgba(212, 175, 55, 0.6)",
    "border_radius" => "20",
    "card_opacity" => "0.5",
    "kefu" => "",
    "kefu_img" => "",
    "bg_pc" => "",
    "bg_mobile" => "",
    "logo" => "",
    "btnText" => "进入",
    "routeTitle" => "极速线路选择",
    "route_title_size" => "18",
    "route_title_color" => "#d4af37",
    "route_title_stroke_color" => "#000000",
    "route_title_stroke_width" => "0",
    "copyright_text" => "",
    "copyright_size" => "12",
    "copyright_color" => "#666666",
    "display_order" => "title_first"
];

// 检查配置文件是否存在和可写
$config = $defaultConfig;
$configExists = file_exists($configFile);

if (!$configExists) { 
    // 尝试创建配置文件
    $result = @file_put_contents($configFile, json_encode($defaultConfig, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)); 
    if ($result !== false) {
        // 设置配置文件权限
        @chmod($configFile, 0644);
        $configExists = true;
    } else {
        // 如果创建失败，显示错误信息但继续运行
        error_log("无法创建配置文件: " . $configFile);
    }
}

// 读取配置文件
if ($configExists) {
    $configContent = @file_get_contents($configFile);
    if ($configContent !== false) {
        $decodedConfig = json_decode($configContent, true);
        if ($decodedConfig !== null) {
            $config = $decodedConfig;
        } else {
            error_log("配置文件解析错误: " . $configFile);
        }
    } else {
        error_log("无法读取配置文件: " . $configFile);
    }
}

// 登录验证逻辑
if (!isset($_SESSION['logged_in']) && isset($_POST['login'])) {
    if (($_POST['user'] ?? '') === ADMIN_USER && ($_POST['pass'] ?? '') === ADMIN_PASS) {
        $_SESSION['logged_in'] = true; $_SESSION['login_time'] = time();
        header("Location: index.php"); exit;
    } else { $login_error = "账号或密码错误"; }
}
if (isset($_GET['logout'])) { session_destroy(); header("Location: index.php"); exit; }
$isLoggedIn = isset($_SESSION['logged_in']);

// 添加一个保存配置的辅助函数，处理权限问题
function saveConfigFile($configFile, $config) {
    // 先尝试直接保存
    $result = @file_put_contents($configFile, json_encode($config, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
    
    if ($result !== false) {
        // 保存成功，设置权限
        @chmod($configFile, 0644);
        return true;
    } else {
        // 保存失败，尝试临时解决方案
        // 1. 尝试修改文件权限
        if (file_exists($configFile)) {
            @chmod($configFile, 0666);
            $result = @file_put_contents($configFile, json_encode($config, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
            if ($result !== false) {
                @chmod($configFile, 0644);
                return true;
            }
        }
        
        // 2. 尝试创建备份文件
        $backupFile = $configFile . '.backup';
        $result = @file_put_contents($backupFile, json_encode($config, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
        if ($result !== false) {
            @chmod($backupFile, 0644);
            error_log("主配置文件保存失败，已保存到备份文件: " . $backupFile);
            return false; // 返回false但已保存备份
        }
        
        return false;
    }
}

if ($isLoggedIn) {
    if (isset($_POST['save_all'])) {
        // 1. 保存标题 (含排序与字号)
        $titles = [];
        if (isset($_POST['title_texts'])) {
            foreach ($_POST['title_texts'] as $i => $text) {
                if (!empty(trim($text))) {
                    $titles[] = [
                        "text" => trim($text),
                        "size" => $_POST['title_sizes'][$i] ?? "32",
                        "weight" => $_POST['title_weights'][$i] ?? "bold",
                        "color" => $_POST['title_colors'][$i] ?? "#d4af37",
                        "stroke_color" => $_POST['title_stroke_colors'][$i] ?? "#000000",
                        "stroke_width" => $_POST['title_stroke_widths'][$i] ?? "0"
                    ];
                }
            }
        }
        $config['titles'] = $titles;

        // 2. 保存副标题 (含排序与字号)
        $subs = [];
        if (isset($_POST['sub_texts'])) {
            foreach ($_POST['sub_texts'] as $i => $text) {
                if (!empty(trim($text))) {
                    $subs[] = [
                        "text" => trim($text),
                        "size" => $_POST['sub_sizes'][$i] ?? "14",
                        "weight" => $_POST['sub_weights'][$i] ?? "normal",
                        "color" => $_POST['sub_colors'][$i] ?? "#ffffff",
                        "stroke_color" => $_POST['sub_stroke_colors'][$i] ?? "#000000",
                        "stroke_width" => $_POST['sub_stroke_widths'][$i] ?? "0"
                    ];
                }
            }
        }
        $config['subs'] = $subs;

        // 3. 保存轮播图 (含排序与上传)
        $carousel = [];
        if (isset($_POST['carousel_exists'])) {
            foreach ($_POST['carousel_exists'] as $i => $val) {
                $currentImg = $_POST['carousel_old_img'][$i] ?? "";
                if (isset($_FILES['carousel_file']['name'][$i]) && $_FILES['carousel_file']['error'][$i] === 0) {
                    $ext = strtolower(pathinfo($_FILES['carousel_file']['name'][$i], PATHINFO_EXTENSION));
                    $newName = 'slide_' . time() . '_' . $i . '.' . $ext;
                    if (move_uploaded_file($_FILES['carousel_file']['tmp_name'][$i], $imgDir . $newName)) {
                        $currentImg = 'img/' . $newName;
                        // 设置上传文件权限
                        @chmod($imgDir . $newName, 0644);
                    }
                }
                if (!empty($currentImg)) {
                    $carousel[] = [
                        "img" => $currentImg,
                        "title" => $_POST['carousel_title'][$i] ?? "",
                        "sub" => $_POST['carousel_sub'][$i] ?? "",
                        "link" => $_POST['carousel_link'][$i] ?? ""
                    ];
                }
            }
        }
        $config['carousel'] = $carousel;

        // 4. 线路保存
        $links = [];
        if (isset($_POST['link_names'])) {
            foreach ($_POST['link_names'] as $i => $name) {
                if (!empty(trim($_POST['link_urls'][$i]))) {
                    $links[] = [
                        "name" => $name, 
                        "url" => trim($_POST['link_urls'][$i]),
                        "size" => $_POST['link_sizes'][$i] ?? "16",
                        "color" => $_POST['link_colors'][$i] ?? "#ffffff",
                        "stroke_color" => $_POST['link_stroke_colors'][$i] ?? "#000000",
                        "stroke_width" => $_POST['link_stroke_widths'][$i] ?? "0"
                    ];
                }
            }
        }
        $config['links'] = $links;

        // 5. 全局基础配置
        $config['border_width'] = $_POST['border_width'];
        $config['border_color'] = $_POST['border_color'];
        $config['border_radius'] = $_POST['border_radius'];
        $config['card_opacity'] = $_POST['card_opacity'];
        $config['routeTitle'] = $_POST['routeTitle'];
        $config['route_title_size'] = $_POST['route_title_size'] ?? "18";
        $config['route_title_color'] = $_POST['route_title_color'] ?? "#d4af37";
        $config['route_title_stroke_color'] = $_POST['route_title_stroke_color'] ?? "#000000";
        $config['route_title_stroke_width'] = $_POST['route_title_stroke_width'] ?? "0";
        $config['btnText'] = $_POST['btnText'];
        $config['kefu'] = $_POST['kefu'];
        $config['copyright_text'] = $_POST['copyright_text'];
        $config['copyright_size'] = $_POST['copyright_size'];
        $config['copyright_color'] = $_POST['copyright_color'];
        $config['display_order'] = $_POST['display_order'];

        // 使用新的保存函数
        if (saveConfigFile($configFile, $config)) {
            $msg = "全部改动已保存！";
        } else {
            $msg = "保存失败！请检查config.json文件权限。可能需要手动设置权限：chmod 666 " . basename($configFile);
        }
    }

    // 单项资源上传
    if (isset($_POST['upload_sys'])) {
        $type = $_POST['upload_type'];
        if ($_FILES['file']['error'] === 0) {
            $ext = strtolower(pathinfo($_FILES['file']['name'], PATHINFO_EXTENSION));
            $newName = $type . '_' . time() . '.' . $ext;
            if (move_uploaded_file($_FILES['file']['tmp_name'], $imgDir . $newName)) {
                $config[$type] = 'img/' . $newName;
                // 设置上传文件权限
                @chmod($imgDir . $newName, 0644);
                
                // 使用新的保存函数
                if (saveConfigFile($configFile, $config)) {
                    $msg = "资源上传成功！";
                } else {
                    $msg = "资源上传成功但配置保存失败！请检查文件权限。";
                }
            }
        }
    }
}

// 预览函数 (解决缓存)
function renderRes($path) {
    if (empty($path)) return '<div class="text-[10px] text-gray-600 bg-black/20 rounded h-12 flex items-center justify-center">未设置</div>';
    $url = (strpos($path, 'http') === 0) ? $path : '../' . $path . '?v=' . time();
    $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
    if (in_array($ext, ['mp4','webm','mov'])) return '<video src="'.$url.'" class="w-full h-12 object-cover rounded shadow"></video>';
    return '<img src="'.$url.'" class="w-full h-12 object-contain rounded shadow bg-black/20">';
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>后台管理中心</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        body { background: #0f172a; color: #e2e8f0; font-size: 14px; }
        .glass { background: rgba(30, 41, 59, 0.7); backdrop-filter: blur(10px); border: 1px solid rgba(255,255,255,0.1); }
        input, select, textarea { background: #1e293b !important; border: 1px solid #334155 !important; color: white !important; }
        .sort-btn { cursor: pointer; color: #64748b; transition: all 0.2s; }
        .sort-btn:hover { color: #fbbf24; transform: scale(1.2); }
        .color-input-wrapper { position: relative; width: 2rem; height: 2rem; }
        .color-input-wrapper input[type="color"] { width: 100%; height: 100%; border: none; cursor: pointer; border-radius: 0.375rem; }
        .tooltip { position: relative; display: inline-block; }
        .tooltip .tooltip-text { visibility: hidden; width: 80px; background-color: #1e293b; color: #fff; text-align: center; border-radius: 6px; padding: 5px 0; position: absolute; z-index: 1; bottom: 125%; left: 50%; margin-left: -40px; opacity: 0; transition: opacity 0.3s; font-size: 10px; }
        .tooltip:hover .tooltip-text { visibility: visible; opacity: 1; }
        
        /* 权限错误提示样式 */
        .permission-warning {
            background: rgba(239, 68, 68, 0.2);
            border: 1px solid rgba(239, 68, 68, 0.4);
            border-left: 4px solid #ef4444;
        }
    </style>
</head>
<body class="p-4 md:p-10">

<?php if(!$isLoggedIn): ?>
    <div class="min-h-screen flex items-center justify-center">
        <form method="post" class="glass p-10 rounded-3xl w-full max-w-sm">
            <h2 class="text-2xl font-bold mb-6 text-center">管理员登录</h2>
            <input type="text" name="user" placeholder="账号" class="w-full p-3 rounded-xl mb-4" required>
            <input type="password" name="pass" placeholder="密码" class="w-full p-3 rounded-xl mb-6" required>
            <button type="submit" name="login" class="w-full bg-yellow-500 text-black font-bold py-3 rounded-xl">登 录</button>
            <?php if(isset($login_error)) echo "<p class='text-red-500 mt-4 text-center'>$login_error</p>"; ?>
        </form>
    </div>
<?php else: ?>

    <div class="max-w-5xl mx-auto">
        <?php if(isset($msg)): ?>
        <div class="mb-4 p-4 bg-green-500/20 text-green-300 rounded-xl border border-green-500/30">
            <i class="fas fa-check-circle mr-2"></i><?= $msg ?>
        </div>
        <?php endif; ?>
        
        <!-- 检查权限并显示警告 -->
        <?php 
        $configWritable = is_writable($configFile);
        $imgDirWritable = is_writable($imgDir);
        
        if (!$configWritable || !$imgDirWritable): ?>
        <div class="mb-4 p-4 permission-warning rounded-xl">
            <div class="flex items-start">
                <i class="fas fa-exclamation-triangle text-red-400 mr-3 mt-1"></i>
                <div class="flex-1">
                    <h3 class="font-bold text-red-300 mb-1">权限警告</h3>
                    <p class="text-sm text-gray-300 mb-2">
                        检测到文件权限问题，可能会影响正常保存。请确保以下文件/目录有写入权限：
                    </p>
                    <ul class="text-xs text-gray-400 space-y-1">
                        <?php if (!$configWritable): ?>
                        <li class="flex items-center">
                            <i class="fas fa-file text-gray-500 mr-2 w-4"></i>
                            <code class="bg-black/30 px-2 py-1 rounded"><?= basename($configFile) ?></code>
                            <span class="ml-2">- 需要写入权限</span>
                        </li>
                        <?php endif; ?>
                        <?php if (!$imgDirWritable): ?>
                        <li class="flex items-center">
                            <i class="fas fa-folder text-gray-500 mr-2 w-4"></i>
                            <code class="bg-black/30 px-2 py-1 rounded">../img/</code>
                            <span class="ml-2">- 需要写入权限</span>
                        </li>
                        <?php endif; ?>
                    </ul>
                    <p class="text-xs text-gray-400 mt-2">
                        解决方法：通过FTP或SSH设置文件权限为666，目录权限为755
                    </p>
                </div>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- 修复：将 form 标签上移到这里，开始包裹按钮和配置 -->
        <form method="post" enctype="multipart/form-data" id="config-form">
        
            <div class="flex flex-col md:flex-row justify-between items-center mb-10 gap-4">
                <div class="flex items-center gap-3">
                    <h1 class="text-3xl font-black italic text-yellow-500 tracking-tighter">LONGLING</h1>
                    <span class="text-xs text-gray-400 px-2 py-1 bg-black/30 rounded-full">导航管理系统</span>
                </div>
                <div class="flex gap-4">
                    <!-- 修复保存按钮：改为表单内的提交按钮 -->
                    <button type="submit" name="save_all" class="bg-gradient-to-r from-yellow-500 to-amber-500 text-black font-bold px-6 py-2 rounded-full hover:brightness-110 active:scale-95 transition flex items-center justify-center gap-2 shadow-xl shadow-yellow-500/20">
                        <i class="fas fa-save"></i>保存所有配置
                    </button>
                    <a href="../" target="_blank" class="bg-white/10 px-6 py-2 rounded-full hover:bg-white/20 transition text-sm flex items-center gap-2">
                        <i class="fas fa-external-link-alt text-xs"></i>查看前台
                    </a>
                    <a href="?logout=1" class="bg-red-500/20 text-red-400 px-6 py-2 rounded-full text-sm flex items-center gap-2">
                        <i class="fas fa-sign-out-alt text-xs"></i>安全退出
                    </a>
                </div>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
                
                <div class="lg:col-span-2 space-y-8">
                    <section class="glass p-6 rounded-[2rem]">
                        <div class="flex justify-between items-center mb-6">
                            <h2 class="font-bold text-lg flex items-center"><i class="fa-solid fa-heading mr-2 text-yellow-500"></i>主标题组</h2>
                            <button type="button" onclick="addTitle()" class="text-xs bg-yellow-500/20 text-yellow-500 px-3 py-1 rounded-lg hover:bg-yellow-500/30 transition">+ 添加标题</button>
                        </div>
                        <p class="text-xs text-gray-400 mb-4">每个标题可单独设置颜色、描边、大小和字重</p>
                        <div id="titles-container" class="space-y-4">
                            <?php foreach($config['titles'] as $t): ?>
                            <div class="p-4 bg-black/20 rounded-2xl border border-white/5 relative group hover:border-white/10 transition-colors">
                                <div class="flex gap-3 mb-3">
                                    <input type="text" name="title_texts[]" value="<?= htmlspecialchars($t['text']) ?>" class="flex-1 p-2 rounded-lg text-sm" placeholder="文字内容" required>
                                    <input type="number" name="title_sizes[]" value="<?= $t['size']??'32' ?>" class="w-20 p-2 rounded-lg text-sm" placeholder="大小" min="12" max="72">
                                </div>
                                <div class="flex items-center gap-3 text-[11px] flex-wrap">
                                    <div class="tooltip">
                                        <div class="color-input-wrapper">
                                            <input type="color" name="title_colors[]" value="<?= $t['color'] ?>" class="rounded bg-transparent" title="文字颜色">
                                        </div>
                                        <span class="tooltip-text">文字颜色</span>
                                    </div>
                                    
                                    <select name="title_weights[]" class="p-2 rounded bg-slate-800 text-white">
                                        <option value="bold" <?= ($t['weight']??'')=='bold'?'selected':'' ?>>加粗</option>
                                        <option value="normal" <?= ($t['weight']??'')=='normal'?'selected':'' ?>>常规</option>
                                        <option value="lighter" <?= ($t['weight']??'')=='lighter'?'selected':'' ?>>细体</option>
                                    </select>
                                    
                                    <div class="tooltip">
                                        <div class="color-input-wrapper">
                                            <input type="color" name="title_stroke_colors[]" value="<?= $t['stroke_color'] ?? '#000000' ?>" class="rounded bg-transparent" title="描边颜色">
                                        </div>
                                        <span class="tooltip-text">描边颜色</span>
                                    </div>
                                    
                                    <div class="flex items-center gap-1">
                                        <span class="whitespace-nowrap text-gray-400">描边宽:</span>
                                        <input type="number" name="title_stroke_widths[]" value="<?= $t['stroke_width'] ?? '0' ?>" class="w-12 p-1 rounded" min="0" max="10" step="0.5" title="描边宽度（像素）">
                                    </div>
                                    
                                    <div class="ml-auto flex gap-3">
                                        <i class="fa-solid fa-arrow-up sort-btn hover:text-yellow-500" onclick="moveUp(this)" title="上移"></i>
                                        <i class="fa-solid fa-arrow-down sort-btn hover:text-yellow-500" onclick="moveDown(this)" title="下移"></i>
                                        <i class="fa-solid fa-trash-can text-red-500/50 hover:text-red-500 cursor-pointer" onclick="this.closest('.p-4').remove()" title="删除"></i>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </section>

                    <section class="glass p-6 rounded-[2rem]">
                        <div class="flex justify-between items-center mb-6">
                            <h2 class="font-bold text-lg flex items-center"><i class="fa-solid fa-paragraph mr-2 text-blue-400"></i>副标题组</h2>
                            <button type="button" onclick="addSub()" class="text-xs bg-blue-500/20 text-blue-500 px-3 py-1 rounded-lg hover:bg-blue-500/30 transition">+ 添加副标题</button>
                        </div>
                        <p class="text-xs text-gray-400 mb-4">支持多行文本，可设置颜色、描边和字体大小</p>
                        <div id="subs-container" class="space-y-4">
                            <?php foreach($config['subs'] as $s): ?>
                            <div class="p-4 bg-black/20 rounded-2xl border border-white/5 relative hover:border-white/10 transition-colors">
                                <textarea name="sub_texts[]" class="w-full p-3 rounded-lg text-sm mb-3 h-20" placeholder="副标题内容" required><?= htmlspecialchars($s['text']) ?></textarea>
                                <div class="flex items-center gap-3 text-[11px] flex-wrap">
                                    <div class="flex items-center gap-1">
                                        <span class="whitespace-nowrap text-gray-400">字号:</span>
                                        <input type="number" name="sub_sizes[]" value="<?= $s['size'] ?>" class="w-14 p-1 rounded" min="8" max="36">
                                    </div>
                                    
                                    <div class="tooltip">
                                        <div class="color-input-wrapper">
                                            <input type="color" name="sub_colors[]" value="<?= $s['color'] ?>" class="rounded bg-transparent" title="文字颜色">
                                        </div>
                                        <span class="tooltip-text">文字颜色</span>
                                    </div>
                                    
                                    <div class="tooltip">
                                        <div class="color-input-wrapper">
                                            <input type="color" name="sub_stroke_colors[]" value="<?= $s['stroke_color'] ?? '#000000' ?>" class="rounded bg-transparent" title="描边颜色">
                                        </div>
                                        <span class="tooltip-text">描边颜色</span>
                                    </div>
                                    
                                    <div class="flex items-center gap-1">
                                        <span class="whitespace-nowrap text-gray-400">描边宽:</span>
                                        <input type="number" name="sub_stroke_widths[]" value="<?= $s['stroke_width'] ?? '0' ?>" class="w-12 p-1 rounded" min="0" max="10" step="0.5" title="描边宽度（像素）">
                                    </div>
                                    
                                    <div class="ml-auto flex gap-3">
                                        <i class="fa-solid fa-arrow-up sort-btn hover:text-blue-500" onclick="moveUp(this)" title="上移"></i>
                                        <i class="fa-solid fa-arrow-down sort-btn hover:text-blue-500" onclick="moveDown(this)" title="下移"></i>
                                        <i class="fa-solid fa-trash-can text-red-500/50 hover:text-red-500 cursor-pointer" onclick="this.closest('.p-4').remove()" title="删除"></i>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </section>

                    <section class="glass p-6 rounded-[2rem]">
                        <div class="flex justify-between items-center mb-6">
                            <h2 class="font-bold text-lg flex items-center"><i class="fa-solid fa-images mr-2 text-purple-400"></i>轮播图管理</h2>
                            <button type="button" onclick="addSlide()" class="text-xs bg-purple-500/20 text-purple-500 px-3 py-1 rounded-lg hover:bg-purple-500/30 transition">+ 添加轮播图</button>
                        </div>
                        <p class="text-xs text-gray-400 mb-4">支持图片和视频，可设置标题、描述和链接</p>
                        <div id="carousel-container" class="space-y-5">
                            <?php foreach($config['carousel'] as $i => $item): ?>
                            <div class="p-4 bg-black/20 rounded-2xl border border-white/5 flex flex-col md:flex-row gap-6 relative hover:border-white/10 transition-colors">
                                <input type="hidden" name="carousel_exists[]" value="1">
                                <input type="hidden" name="carousel_old_img[]" value="<?= $item['img'] ?>">
                                <div class="w-full md:w-48">
                                    <div class="relative">
                                        <?= renderRes($item['img']) ?>
                                        <div class="absolute top-1 right-1 bg-black/50 text-white text-[8px] px-1 py-0.5 rounded">
                                            <?= strtoupper(pathinfo($item['img'], PATHINFO_EXTENSION)) ?>
                                        </div>
                                    </div>
                                </div>
                                <div class="flex-1 space-y-3">
                                    <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                                        <input type="text" name="carousel_title[]" value="<?= htmlspecialchars($item['title']) ?>" placeholder="主标题（可选）" class="p-2 rounded-lg text-xs">
                                        <input type="text" name="carousel_sub[]" value="<?= htmlspecialchars($item['sub']) ?>" placeholder="副标题（可选）" class="p-2 rounded-lg text-xs">
                                    </div>
                                    <input type="text" name="carousel_link[]" value="<?= htmlspecialchars($item['link']) ?>" placeholder="点击跳转地址（可选，留空则不可点击）" class="w-full p-2 rounded-lg text-xs">
                                    <div class="flex items-center justify-between pt-2">
                                        <div class="flex-1">
                                            <input type="file" name="carousel_file[]" class="text-[10px] text-gray-500 w-full" accept="image/*,video/*">
                                            <p class="text-[9px] text-gray-500 mt-1">支持 JPG, PNG, GIF, MP4, WEBM 等格式</p>
                                        </div>
                                        <div class="flex gap-3 ml-4">
                                            <i class="fa-solid fa-arrow-up sort-btn hover:text-purple-500" onclick="moveUp(this)" title="上移"></i>
                                            <i class="fa-solid fa-arrow-down sort-btn hover:text-purple-500" onclick="moveDown(this)" title="下移"></i>
                                            <i class="fa-solid fa-trash-can text-red-500 cursor-pointer hover:text-red-400" onclick="this.closest('.p-4').remove()" title="删除"></i>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </section>

                    <section class="glass p-6 rounded-[2rem]">
                        <div class="flex justify-between items-center mb-6">
                            <h2 class="font-bold text-lg flex items-center"><i class="fa-solid fa-link mr-2 text-emerald-400"></i>线路入口</h2>
                            <button type="button" onclick="addLink()" class="text-xs bg-emerald-500/20 text-emerald-500 px-3 py-1 rounded-lg hover:bg-emerald-500/30 transition">+ 添加线路</button>
                        </div>
                        <p class="text-xs text-gray-400 mb-4">设置导航链接，支持 http:// 和 https:// 协议。每个线路可单独设置字体样式。</p>
                        <div id="links-container" class="space-y-4">
                            <?php foreach($config['links'] as $link): ?>
                            <div class="p-4 bg-black/20 rounded-2xl border border-white/5 relative hover:border-white/10 transition-colors">
                                <div class="flex gap-3 mb-3">
                                    <input type="text" name="link_names[]" value="<?= htmlspecialchars($link['name']) ?>" class="flex-1 p-2 rounded-lg text-sm" placeholder="线路名称" required>
                                    <input type="number" name="link_sizes[]" value="<?= $link['size'] ?? '16' ?>" class="w-20 p-2 rounded-lg text-sm" placeholder="大小" min="12" max="72">
                                </div>
                                <div class="flex items-center gap-3 text-[11px] flex-wrap">
                                    <div class="tooltip">
                                        <div class="color-input-wrapper">
                                            <input type="color" name="link_colors[]" value="<?= $link['color'] ?? '#ffffff' ?>" class="rounded bg-transparent" title="文字颜色">
                                        </div>
                                        <span class="tooltip-text">文字颜色</span>
                                    </div>
                                    
                                    <div class="tooltip">
                                        <div class="color-input-wrapper">
                                            <input type="color" name="link_stroke_colors[]" value="<?= $link['stroke_color'] ?? '#000000' ?>" class="rounded bg-transparent" title="描边颜色">
                                        </div>
                                        <span class="tooltip-text">描边颜色</span>
                                    </div>
                                    
                                    <div class="flex items-center gap-1">
                                        <span class="whitespace-nowrap text-gray-400">描边宽:</span>
                                        <input type="number" name="link_stroke_widths[]" value="<?= $link['stroke_width'] ?? '0' ?>" class="w-12 p-1 rounded" min="0" max="10" step="0.5" title="描边宽度（像素）">
                                    </div>
                                    
                                    <div class="ml-auto flex gap-3">
                                        <i class="fa-solid fa-arrow-up sort-btn hover:text-emerald-500" onclick="moveUp(this)" title="上移"></i>
                                        <i class="fa-solid fa-arrow-down sort-btn hover:text-emerald-500" onclick="moveDown(this)" title="下移"></i>
                                        <i class="fa-solid fa-trash-can text-red-500 cursor-pointer hover:text-red-400" onclick="this.closest('.p-4').remove()" title="删除"></i>
                                    </div>
                                </div>
                                <div class="mt-3">
                                    <input type="text" name="link_urls[]" value="<?= htmlspecialchars($link['url']) ?>" class="w-full p-2 rounded-lg text-sm" placeholder="https://example.com" required>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        <div class="mt-6 p-4 bg-black/10 rounded-xl border border-dashed border-white/10">
                            <p class="text-xs text-gray-400 text-center mb-2">提示：线路将按照当前顺序在前台显示</p>
                            <button type="button" onclick="addLink()" class="w-full py-3 border border-dashed border-white/20 rounded-2xl text-gray-400 hover:text-white hover:border-white/30 transition flex items-center justify-center gap-2">
                                <i class="fas fa-plus text-xs"></i>增加新线路
                            </button>
                        </div>
                    </section>

                    <!-- 将资源管理和系统状态移动到线路入口下面 -->
                    <section class="glass p-6 rounded-[2rem]">
                        <div class="space-y-6">
                            <!-- 资源管理部分 -->
                            <div>
                                <div class="flex items-center justify-between mb-4">
                                    <h2 class="font-bold text-lg flex items-center">
                                        <i class="fas fa-images mr-2 text-purple-400"></i>资源管理
                                    </h2>
                                    <span class="text-xs bg-purple-500/20 text-purple-400 px-2 py-1 rounded-full"><?= count($config['carousel']) ?>个轮播</span>
                                </div>
                                
                                <div class="grid grid-cols-2 gap-3">
                                    <div class="bg-black/20 p-3 rounded-xl text-center hover:bg-black/30 transition-colors cursor-pointer" onclick="openUpload('logo')">
                                        <div class="h-12 flex items-center justify-center mb-2"><?= renderRes($config['logo']) ?></div>
                                        <div class="text-xs text-yellow-500 uppercase tracking-wide font-medium">Logo</div>
                                    </div>
                                    <div class="bg-black/20 p-3 rounded-xl text-center hover:bg-black/30 transition-colors cursor-pointer" onclick="openUpload('bg_pc')">
                                        <div class="h-12 flex items-center justify-center mb-2"><?= renderRes($config['bg_pc']) ?></div>
                                        <div class="text-xs text-yellow-500 uppercase tracking-wide font-medium">PC背景</div>
                                    </div>
                                    <div class="bg-black/20 p-3 rounded-xl text-center hover:bg-black/30 transition-colors cursor-pointer" onclick="openUpload('bg_mobile')">
                                        <div class="h-12 flex items-center justify-center mb-2"><?= renderRes($config['bg_mobile']) ?></div>
                                        <div class="text-xs text-yellow-500 uppercase tracking-wide font-medium">移动背景</div>
                                    </div>
                                    <div class="bg-black/20 p-3 rounded-xl text-center hover:bg-black/30 transition-colors cursor-pointer" onclick="openUpload('kefu_img')">
                                        <div class="h-12 flex items-center justify-center mb-2"><?= renderRes($config['kefu_img']) ?></div>
                                        <div class="text-xs text-yellow-500 uppercase tracking-wide font-medium">客服图标</div>
                                    </div>
                                </div>
                                <p class="text-[10px] text-gray-600 text-center mt-3">点击项目上传或替换资源文件</p>
                            </div>
                            
                            <!-- 系统状态部分 -->
                            <div class="pt-6 border-t border-white/5">
                                <div class="flex items-center justify-between mb-4">
                                    <h2 class="font-bold text-lg flex items-center">
                                        <i class="fa-solid fa-circle-info mr-2 text-cyan-400"></i>系统状态
                                    </h2>
                                    <span class="text-xs bg-cyan-500/20 text-cyan-400 px-2 py-1 rounded-full">v2.0</span>
                                </div>
                                
                                <div class="space-y-3">
                                    <div class="flex justify-between items-center text-sm">
                                        <span class="text-gray-400 flex items-center gap-2">
                                            <i class="fas fa-sign-in-alt text-[10px]"></i>
                                            <span>登录时间</span>
                                        </span>
                                        <span class="text-white font-medium"><?= date('H:i', $_SESSION['login_time'] ?? time()) ?></span>
                                    </div>
                                    <div class="flex justify-between items-center text-sm">
                                        <span class="text-gray-400 flex items-center gap-2">
                                            <i class="fas fa-image text-[10px]"></i>
                                            <span>轮播图</span>
                                        </span>
                                        <span class="text-white font-medium"><?= count($config['carousel']) ?> 个</span>
                                    </div>
                                    <div class="flex justify-between items-center text-sm">
                                        <span class="text-gray-400 flex items-center gap-2">
                                            <i class="fas fa-link text-[10px]"></i>
                                            <span>线路数</span>
                                        </span>
                                        <span class="text-white font-medium"><?= count($config['links']) ?> 条</span>
                                    </div>
                                    <div class="flex justify-between items-center text-sm">
                                        <span class="text-gray-400 flex items-center gap-2">
                                            <i class="fas fa-text-height text-[10px]"></i>
                                            <span>标题组</span>
                                        </span>
                                        <span class="text-white font-medium"><?= count($config['titles']) ?> 个</span>
                                    </div>
                                    <div class="flex justify-between items-center text-sm">
                                        <span class="text-gray-400 flex items-center gap-2">
                                            <i class="fas fa-sort text-[10px]"></i>
                                            <span>显示顺序</span>
                                        </span>
                                        <span class="text-white font-medium"><?= ($config['display_order'] ?? 'title_first') == 'title_first' ? '标题在上' : '轮播在上' ?></span>
                                    </div>
                                    
                                    <div class="pt-3 border-t border-white/5">
                                        <div class="flex gap-2 mt-2">
                                            <a href="../" target="_blank" class="flex-1 text-center bg-white/10 px-4 py-2 rounded-xl hover:bg-white/20 transition text-xs flex items-center justify-center gap-2">
                                                <i class="fas fa-external-link-alt text-xs"></i>
                                                <span>查看前台</span>
                                            </a>
                                            <a href="../config.json" target="_blank" class="flex-1 text-center bg-blue-500/10 text-blue-400 px-4 py-2 rounded-xl hover:bg-blue-500/20 transition text-xs flex items-center justify-center gap-2">
                                                <i class="fas fa-code text-xs"></i>
                                                <span>配置文件</span>
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </section>
                </div>

                <div class="lg:col-span-1 space-y-8">
                    <section class="glass p-6 rounded-[2rem] sticky top-8">
                        <h2 class="font-bold text-lg mb-6 flex items-center">
                            <i class="fa-solid fa-sliders-h mr-2 text-amber-400"></i>全局控制
                        </h2>
                        
                        <div class="space-y-5">
                            <div>
                                <label class="text-xs text-gray-500 block mb-1">线路组标题</label>
                                <input type="text" name="routeTitle" value="<?= htmlspecialchars($config['routeTitle']) ?>" class="w-full p-2 rounded-lg" placeholder="例如：选择线路">
                            </div>
                            <!-- 新增：线路组标题样式配置 -->
                            <div class="pt-2 border-t border-white/5">
                                <label class="text-xs text-gray-500 block mb-1">线路组标题样式</label>
                                <div class="grid grid-cols-2 gap-3">
                                    <div>
                                        <span class="text-[10px] text-gray-500">字号</span>
                                        <input type="number" name="route_title_size" value="<?= $config['route_title_size'] ?>" class="w-full p-1 rounded text-xs" min="8" max="36">
                                    </div>
                                    <div>
                                        <span class="text-[10px] text-gray-500">颜色</span>
                                        <input type="text" name="route_title_color" value="<?= $config['route_title_color'] ?>" class="w-full p-1 rounded text-xs" placeholder="#d4af37">
                                    </div>
                                    <div>
                                        <span class="text-[10px] text-gray-500">描边颜色</span>
                                        <input type="text" name="route_title_stroke_color" value="<?= $config['route_title_stroke_color'] ?>" class="w-full p-1 rounded text-xs" placeholder="#000000">
                                    </div>
                                    <div>
                                        <span class="text-[10px] text-gray-500">描边宽度</span>
                                        <input type="number" name="route_title_stroke_width" value="<?= $config['route_title_stroke_width'] ?>" class="w-full p-1 rounded text-xs" min="0" max="10" step="0.5">
                                    </div>
                                </div>
                            </div>
                            
                            <div>
                                <label class="text-xs text-gray-500 block mb-1">按钮文字</label>
                                <input type="text" name="btnText" value="<?= htmlspecialchars($config['btnText']) ?>" class="w-full p-2 rounded-lg" placeholder="例如：立即进入">
                            </div>
                            
                            <div class="grid grid-cols-2 gap-4">
                                <div>
                                    <label class="text-xs text-gray-500 block mb-1">圆角 (px)</label>
                                    <input type="number" name="border_radius" value="<?= $config['border_radius'] ?>" class="w-full p-2 rounded-lg" min="0" max="50">
                                </div>
                                <div>
                                    <label class="text-xs text-gray-500 block mb-1">边框 (px)</label>
                                    <input type="number" name="border_width" value="<?= $config['border_width'] ?>" step="0.1" class="w-full p-2 rounded-lg" min="0" max="10">
                                </div>
                            </div>
                            
                            <div>
                                <label class="text-xs text-gray-500 block mb-1">边框颜色</label>
                                <div class="flex gap-2">
                                    <input type="text" name="border_color" value="<?= $config['border_color'] ?>" class="flex-1 p-2 rounded-lg" placeholder="例如: #d4af37 或 rgba(212, 175, 55, 0.6)">
                                    <input type="color" id="border_color_picker" value="<?= preg_match('/^#([A-Fa-f0-9]{6}|[A-Fa-f0-9]{3})$/', $config['border_color']) ? $config['border_color'] : '#d4af37' ?>" class="w-10 h-10 rounded bg-transparent">
                                </div>
                            </div>
                            
                            <div>
                                <label class="text-xs text-gray-500 block mb-1">卡片透明度 (0-1)</label>
                                <input type="number" name="card_opacity" value="<?= $config['card_opacity'] ?>" step="0.05" min="0" max="1" class="w-full p-2 rounded-lg">
                                <div class="flex items-center gap-2 mt-1">
                                    <span class="text-[10px] text-gray-500">0</span>
                                    <input type="range" min="0" max="1" step="0.05" value="<?= $config['card_opacity'] ?>" oninput="this.previousElementSibling.previousElementSibling.value=this.value" class="flex-1">
                                    <span class="text-[10px] text-gray-500">1</span>
                                </div>
                            </div>
                            
                            <div>
                                <label class="text-xs text-gray-500 block mb-1">客服地址</label>
                                <input type="text" name="kefu" value="<?= htmlspecialchars($config['kefu']) ?>" class="w-full p-2 rounded-lg" placeholder="例如：https://t.me/username">
                            </div>
                            
                            <!-- 添加显示顺序控制 -->
                            <div>
                                <label class="text-xs text-gray-500 block mb-1">显示顺序</label>
                                <select name="display_order" class="w-full p-2 rounded-lg">
                                    <option value="title_first" <?= ($config['display_order'] ?? 'title_first') == 'title_first' ? 'selected' : '' ?>>标题在上，轮播在下</option>
                                    <option value="carousel_first" <?= ($config['display_order'] ?? 'title_first') == 'carousel_first' ? 'selected' : '' ?>>轮播在上，标题在下</option>
                                </select>
                            </div>
                            
                            <div class="pt-4 border-t border-white/5">
                                <label class="text-xs text-gray-500 block mb-1">版权信息</label>
                                <textarea name="copyright_text" class="w-full p-2 rounded-lg text-xs h-16" placeholder="例如：© 2024 我的网站 All Rights Reserved"><?= htmlspecialchars($config['copyright_text']) ?></textarea>
                                <div class="grid grid-cols-2 gap-3 mt-2">
                                    <div>
                                        <span class="text-[10px] text-gray-500">字号</span>
                                        <input type="number" name="copyright_size" value="<?= $config['copyright_size'] ?>" class="w-full p-1 rounded text-xs" min="8" max="24">
                                    </div>
                                    <div>
                                        <span class="text-[10px] text-gray-500">颜色</span>
                                        <input type="text" name="copyright_color" value="<?= $config['copyright_color'] ?>" class="w-full p-1 rounded text-xs" placeholder="#666666">
                                    </div>
                                </div>
                            </div>
                            
                            <!-- 这里移除了保存按钮，现在它在顶部导航栏 -->
                            <p class="text-[10px] text-gray-500 text-center mt-6">保存后请刷新前台页面查看效果</p>
                        </div>
                    </section>
                    
                    <!-- 右侧边栏现在只有全局控制，资源管理和系统状态已移到左侧 -->
                </div>
            </div>
        </form>
    </div>

    <div id="upModal" class="fixed inset-0 bg-black/90 hidden z-[100] flex items-center justify-center p-4">
        <form method="post" enctype="multipart/form-data" id="upload-form" class="glass p-8 rounded-[2.5rem] w-full max-w-sm border border-white/10">
            <h3 id="upTitle" class="text-white font-bold mb-4 text-lg flex items-center gap-2">
                <i class="fas fa-upload"></i>
                <span>上传素材</span>
            </h3>
            <input type="hidden" name="upload_type" id="upType">
            <div class="mb-6">
                <input type="file" name="file" class="text-sm mb-2 w-full p-2 rounded-xl bg-black/30 border border-white/10" required>
                <p class="text-[10px] text-gray-400">支持图片和视频文件，最大 10MB</p>
            </div>
            <div class="flex gap-3">
                <button type="submit" name="upload_sys" class="flex-1 bg-yellow-500 text-black py-3 rounded-xl font-bold hover:bg-yellow-600 transition flex items-center justify-center gap-2">
                    <i class="fas fa-upload"></i>立即上传
                </button>
                <button type="button" onclick="closeUpload()" class="flex-1 bg-white/10 py-3 rounded-xl text-sm hover:bg-white/20 transition">取消</button>
            </div>
        </form>
    </div>

    <script>
        // 修复：确保页面加载完成后执行
        document.addEventListener('DOMContentLoaded', function() {
            console.log('页面加载完成，初始化JavaScript...');
            
            // 排序逻辑：直接在 DOM 中交换位置，保存时 PHP 会按当前顺序处理
            window.moveUp = function(btn) {
                const item = btn.closest('.p-4, .flex-row');
                if (item.previousElementSibling) {
                    item.parentNode.insertBefore(item, item.previousElementSibling);
                    showToast('已上移', 'success');
                }
            };
            
            window.moveDown = function(btn) {
                const item = btn.closest('.p-4, .flex-row');
                if (item.nextElementSibling) {
                    item.parentNode.insertBefore(item.nextElementSibling, item);
                    showToast('已下移', 'success');
                }
            };

            // 文件上传模态框
            let currentUploadType = '';
            window.openUpload = function(type) {
                currentUploadType = type;
                document.getElementById('upType').value = type;
                const titles = {
                    'logo': 'Logo图标',
                    'bg_pc': 'PC背景图',
                    'bg_mobile': '移动端背景图',
                    'kefu_img': '客服图标'
                };
                document.getElementById('upTitle').innerHTML = `<i class="fas fa-upload"></i><span>上传 ${titles[type] || type}</span>`;
                document.getElementById('upModal').classList.remove('hidden');
            };
            
            window.closeUpload = function() { 
                document.getElementById('upModal').classList.add('hidden');
                document.getElementById('upModal').querySelector('input[type="file"]').value = '';
            };

            // 添加标题
            window.addTitle = function() {
                const html = `<div class="p-4 bg-black/20 rounded-2xl border border-white/5 relative hover:border-white/10 transition-colors">
                    <div class="flex gap-3 mb-3">
                        <input type="text" name="title_texts[]" class="flex-1 p-2 rounded-lg text-sm" placeholder="文字内容" required>
                        <input type="number" name="title_sizes[]" value="32" class="w-20 p-2 rounded-lg text-sm" placeholder="大小" min="12" max="72">
                    </div>
                    <div class="flex items-center gap-3 text-[11px] flex-wrap">
                        <div class="tooltip">
                            <div class="color-input-wrapper">
                                <input type="color" name="title_colors[]" value="#d4af37" title="文字颜色">
                            </div>
                            <span class="tooltip-text">文字颜色</span>
                        </div>
                        <select name="title_weights[]" class="p-2 rounded bg-slate-800">
                            <option value="bold">加粗</option>
                            <option value="normal">常规</option>
                            <option value="lighter">细体</option>
                        </select>
                        <div class="tooltip">
                            <div class="color-input-wrapper">
                                <input type="color" name="title_stroke_colors[]" value="#000000" title="描边颜色">
                            </div>
                            <span class="tooltip-text">描边颜色</span>
                        </div>
                        <div class="flex items-center gap-1">
                            <span class="whitespace-nowrap text-gray-400">描边宽:</span>
                            <input type="number" name="title_stroke_widths[]" value="0" class="w-12 p-1 rounded" min="0" max="10" step="0.5">
                        </div>
                        <div class="ml-auto flex gap-3">
                            <i class="fa-solid fa-arrow-up sort-btn hover:text-yellow-500" onclick="moveUp(this)" title="上移"></i>
                            <i class="fa-solid fa-arrow-down sort-btn hover:text-yellow-500" onclick="moveDown(this)" title="下移"></i>
                            <i class="fa-solid fa-trash-can text-red-500 cursor-pointer hover:text-red-400" onclick="this.closest('.p-4').remove()" title="删除"></i>
                        </div>
                    </div>
                </div>`;
                document.getElementById('titles-container').insertAdjacentHTML('beforeend', html);
                showToast('已添加标题', 'success');
            };

            // 添加副标题
            window.addSub = function() {
                const html = `<div class="p-4 bg-black/20 rounded-2xl border border-white/5 relative hover:border-white/10 transition-colors">
                    <textarea name="sub_texts[]" class="w-full p-3 rounded-lg text-sm mb-3 h-20" placeholder="副标题内容" required></textarea>
                    <div class="flex items-center gap-3 text-[11px] flex-wrap">
                        <div class="flex items-center gap-1">
                            <span class="whitespace-nowrap text-gray-400">字号:</span>
                            <input type="number" name="sub_sizes[]" value="14" class="w-14 p-1 rounded" min="8" max="36">
                        </div>
                        <div class="tooltip">
                            <div class="color-input-wrapper">
                                <input type="color" name="sub_colors[]" value="#ffffff" title="文字颜色">
                            </div>
                            <span class="tooltip-text">文字颜色</span>
                        </div>
                        <div class="tooltip">
                            <div class="color-input-wrapper">
                                <input type="color" name="sub_stroke_colors[]" value="#000000" title="描边颜色">
                            </div>
                            <span class="tooltip-text">描边颜色</span>
                        </div>
                        <div class="flex items-center gap-1">
                            <span class="whitespace-nowrap text-gray-400">描边宽:</span>
                            <input type="number" name="sub_stroke_widths[]" value="0" class="w-12 p-1 rounded" min="0" max="10" step="0.5">
                        </div>
                        <div class="ml-auto flex gap-3">
                            <i class="fa-solid fa-arrow-up sort-btn hover:text-blue-500" onclick="moveUp(this)" title="上移"></i>
                            <i class="fa-solid fa-arrow-down sort-btn hover:text-blue-500" onclick="moveDown(this)" title="下移"></i>
                            <i class="fa-solid fa-trash-can text-red-500 cursor-pointer hover:text-red-400" onclick="this.closest('.p-4').remove()" title="删除"></i>
                        </div>
                    </div>
                </div>`;
                document.getElementById('subs-container').insertAdjacentHTML('beforeend', html);
                showToast('已添加副标题', 'success');
            };

            // 添加轮播图
            window.addSlide = function() {
                const html = `<div class="p-4 bg-black/20 rounded-2xl border border-white/5 flex flex-col md:flex-row gap-6 relative hover:border-white/10 transition-colors">
                    <input type="hidden" name="carousel_exists[]" value="1">
                    <input type="hidden" name="carousel_old_img[]" value="">
                    <div class="w-full md:w-48 bg-black/40 h-32 rounded flex items-center justify-center text-xs text-gray-500">
                        <div class="text-center">
                            <i class="fas fa-image text-2xl mb-2"></i>
                            <p>新轮播图</p>
                        </div>
                    </div>
                    <div class="flex-1 space-y-3">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                            <input type="text" name="carousel_title[]" placeholder="主标题（可选）" class="p-2 rounded-lg text-xs">
                            <input type="text" name="carousel_sub[]" placeholder="副标题（可选）" class="p-2 rounded-lg text-xs">
                        </div>
                        <input type="text" name="carousel_link[]" placeholder="点击跳转地址（可选）" class="w-full p-2 rounded-lg text-xs">
                        <div class="flex items-center justify-between pt-2">
                            <div class="flex-1">
                                <input type="file" name="carousel_file[]" class="text-[10px] w-full" accept="image/*,video/*">
                            </div>
                            <div class="flex gap-3 ml-4">
                                <i class="fa-solid fa-arrow-up sort-btn hover:text-purple-500" onclick="moveUp(this)" title="上移"></i>
                                <i class="fa-solid fa-arrow-down sort-btn hover:text-purple-500" onclick="moveDown(this)" title="下移"></i>
                                <i class="fa-solid fa-trash-can text-red-500 cursor-pointer hover:text-red-400" onclick="this.closest('.p-4').remove()" title="删除"></i>
                            </div>
                        </div>
                    </div>
                </div>`;
                document.getElementById('carousel-container').insertAdjacentHTML('beforeend', html);
                showToast('已添加轮播图', 'success');
            };

            // 添加线路
            window.addLink = function() {
                const html = `<div class="p-4 bg-black/20 rounded-2xl border border-white/5 relative hover:border-white/10 transition-colors">
                    <div class="flex gap-3 mb-3">
                        <input type="text" name="link_names[]" class="flex-1 p-2 rounded-lg text-sm" placeholder="线路名称" required>
                        <input type="number" name="link_sizes[]" value="16" class="w-20 p-2 rounded-lg text-sm" placeholder="大小" min="12" max="72">
                    </div>
                    <div class="flex items-center gap-3 text-[11px] flex-wrap">
                        <div class="tooltip">
                            <div class="color-input-wrapper">
                                <input type="color" name="link_colors[]" value="#ffffff" title="文字颜色">
                            </div>
                            <span class="tooltip-text">文字颜色</span>
                        </div>
                        <div class="tooltip">
                            <div class="color-input-wrapper">
                                <input type="color" name="link_stroke_colors[]" value="#000000" title="描边颜色">
                            </div>
                            <span class="tooltip-text">描边颜色</span>
                        </div>
                        <div class="flex items-center gap-1">
                            <span class="whitespace-nowrap text-gray-400">描边宽:</span>
                            <input type="number" name="link_stroke_widths[]" value="0" class="w-12 p-1 rounded" min="0" max="10" step="0.5">
                        </div>
                        <div class="ml-auto flex gap-3">
                            <i class="fa-solid fa-arrow-up sort-btn hover:text-emerald-500" onclick="moveUp(this)" title="上移"></i>
                            <i class="fa-solid fa-arrow-down sort-btn hover:text-emerald-500" onclick="moveDown(this)" title="下移"></i>
                            <i class="fa-solid fa-trash-can text-red-500 cursor-pointer hover:text-red-400" onclick="this.closest('.p-4').remove()" title="删除"></i>
                        </div>
                    </div>
                    <div class="mt-3">
                        <input type="text" name="link_urls[]" class="w-full p-2 rounded-lg text-sm" placeholder="https://example.com" required>
                    </div>
                </div>`;
                document.getElementById('links-container').insertAdjacentHTML('beforeend', html);
                showToast('已添加线路', 'success');
            };

            // 边框颜色选择器同步
            document.getElementById('border_color_picker').addEventListener('input', function(e) {
                document.querySelector('input[name="border_color"]').value = e.target.value;
            });

            // 显示提示消息
            window.showToast = function(message, type = 'info') {
                const colors = {
                    'success': 'bg-green-500/20 text-green-300 border-green-500/30',
                    'error': 'bg-red-500/20 text-red-300 border-red-500/30',
                    'info': 'bg-blue-500/20 text-blue-300 border-blue-500/30'
                };
                
                const toast = document.createElement('div');
                toast.className = `fixed top-6 right-6 p-4 rounded-xl border ${colors[type]} z-50 flex items-center gap-2 shadow-lg animate-slideIn`;
                toast.innerHTML = `
                    <i class="fas fa-${type === 'success' ? 'check-circle' : type === 'error' ? 'exclamation-circle' : 'info-circle'}"></i>
                    <span>${message}</span>
                `;
                
                document.body.appendChild(toast);
                
                setTimeout(() => {
                    toast.classList.add('animate-slideOut');
                    setTimeout(() => toast.remove(), 300);
                }, 2000);
            };

            // 添加 CSS 动画
            const style = document.createElement('style');
            style.textContent = `
                @keyframes slideIn {
                    from { transform: translateX(100%); opacity: 0; }
                    to { transform: translateX(0); opacity: 1; }
                }
                @keyframes slideOut {
                    from { transform: translateX(0); opacity: 1; }
                    to { transform: translateX(100%); opacity: 0; }
                }
                .animate-slideIn { animation: slideIn 0.3s ease-out; }
                .animate-slideOut { animation: slideOut 0.3s ease-out; }
            `;
            document.head.appendChild(style);

            // 修复：表单提交确认 - 为主表单添加事件监听
            const mainForm = document.getElementById('config-form');
            if (mainForm) {
                mainForm.addEventListener('submit', function(e) {
                    // 检查是否是保存按钮触发的提交
                    const submitter = e.submitter;
                    if (submitter && submitter.name === 'save_all') {
                        console.log('保存按钮被点击，正在提交表单...');
                        showToast('正在保存配置...', 'info');
                        
                        // 验证表单
                        const requiredFields = mainForm.querySelectorAll('[required]');
                        let isValid = true;
                        let firstInvalidField = null;
                        
                        requiredFields.forEach(field => {
                            if (!field.value.trim()) {
                                isValid = false;
                                if (!firstInvalidField) {
                                    firstInvalidField = field;
                                }
                                field.style.borderColor = '#ef4444';
                            } else {
                                field.style.borderColor = '';
                            }
                        });
                        
                        if (!isValid) {
                            e.preventDefault();
                            showToast('请填写所有必填字段！', 'error');
                            if (firstInvalidField) {
                                firstInvalidField.focus();
                            }
                            return;
                        }
                        
                        // 如果一切正常，继续提交
                        console.log('表单验证通过，正在提交...');
                    }
                });
            }
            
            // 修复：为上传表单添加事件监听
            const uploadForm = document.getElementById('upload-form');
            if (uploadForm) {
                uploadForm.addEventListener('submit', function(e) {
                    console.log('上传表单提交...');
                    showToast('正在上传文件...', 'info');
                });
            }
            
            console.log('JavaScript初始化完成！');
        });
        
        // 修复：确保页面加载失败时的错误处理
        window.addEventListener('error', function(e) {
            console.error('JavaScript错误:', e.message, e.filename, e.lineno);
        });
    </script>
<?php endif; ?>
</body>
</html>
