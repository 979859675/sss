<?php
/**
 * 高级 PHP 空间探针 - 兼容 InfinityFree 严格环境
 */
error_reporting(0); // 屏蔽可能因权限不足产生的警告

// 获取执行耗时测试
$time_start = microtime(true);

// 1. 核心信息获取
$server_ip = @gethostbyname($_SERVER['SERVER_NAME']) ?: 'Unknown';
$disk_total = @function_exists('disk_total_space') ? @disk_total_space('.') : false;
$disk_free = @function_exists('disk_free_space') ? @disk_free_space('.') : false;

// 2. 格式化文件大小
function format_size($size) {
    if ($size === false || $size < 0) return '未知/被禁用';
    $units = array(' B', ' KB', ' MB', ' GB', ' TB');
    for ($i = 0; $size >= 1024 && $i < 4; $i++) $size /= 1024;
    return round($size, 2) . $units[$i];
}

// 3. 检测组件支持状态
function is_support($ext) {
    return extension_loaded($ext) ? '<span class="status-on">支持</span>' : '<span class="status-off">不支持</span>';
}
function check_func($func) {
    $disabled = explode(',', ini_get('disable_functions'));
    $disabled = array_map('trim', $disabled);
    if (function_exists($func) && !in_array($func, $disabled)) {
        return '<span class="status-on">可用</span>';
    }
    return '<span class="status-off">被禁用</span>';
}

// 4. 简单的 CPU 跑分测试 (计算 300万次整数运算)
function test_math() {
    $start = microtime(true);
    for ($i = 0; $i < 3000000; $i++) { $a = $i * $i; }
    return round((microtime(true) - $start), 4) . ' 秒';
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>高级 PHP 空间探针</title>
    <style>
        :root { --primary: #4361ee; --success: #2ecc71; --danger: #e74c3c; --bg: #f8f9fa; --text: #333; --border: #e9ecef; }
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; background: var(--bg); color: var(--text); margin: 0; padding: 20px; line-height: 1.6; }
        .container { max-width: 1000px; margin: 0 auto; }
        h1 { text-align: center; color: #2b2d42; margin-bottom: 30px; }
        .grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(48%, 1fr)); gap: 20px; }
        .card { background: #fff; border-radius: 10px; box-shadow: 0 4px 15px rgba(0,0,0,0.05); overflow: hidden; margin-bottom: 20px; }
        .card-title { background: var(--primary); color: #fff; padding: 15px 20px; margin: 0; font-size: 1.1em; }
        .card-title.dark { background: #2b2d42; }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 12px 20px; border-bottom: 1px solid var(--border); text-align: left; }
        th { width: 45%; background: #fdfdfd; color: #6c757d; font-weight: 500; }
        tr:last-child th, tr:last-child td { border-bottom: none; }
        tr:hover { background: #f8f9fa; }
        .status-on { color: var(--success); font-weight: bold; }
        .status-off { color: var(--danger); font-weight: bold; }
        .code-box { background: #f1f3f5; padding: 15px; margin: 15px; border-radius: 6px; font-family: monospace; word-break: break-all; color: #d63031; font-size: 0.9em; max-height: 150px; overflow-y: auto; }
    </style>
</head>
<body>
    <div class="container">
        <h1>📊 深度 PHP 空间探针</h1>

        <div class="grid">
            <!-- 模块 1：服务器基础参数 -->
            <div class="card">
                <h3 class="card-title">🖥️ 服务器基本参数</h3>
                <table>
                    <tr><th>服务器域名</th><td><?php echo $_SERVER['SERVER_NAME']; ?></td></tr>
                    <tr><th>服务器 IP 地址</th><td><?php echo $server_ip; ?></td></tr>
                    <tr><th>服务器操作系统</th><td><?php echo PHP_OS; ?> / <?php echo php_uname('r'); ?></td></tr>
                    <tr><th>Web 服务引擎</th><td><?php echo $_SERVER['SERVER_SOFTWARE']; ?></td></tr>
                    <tr><th>物理绝对路径</th><td><?php echo $_SERVER['DOCUMENT_ROOT']; ?></td></tr>
                    <tr><th>空间总容量</th><td><?php echo format_size($disk_total); ?></td></tr>
                    <tr><th>空间可用容量</th><td><?php echo format_size($disk_free); ?></td></tr>
                </table>
            </div>

            <!-- 模块 2：PHP 核心限制 -->
            <div class="card">
                <h3 class="card-title">⚙️ PHP 限制及环境</h3>
                <table>
                    <tr><th>PHP 运行版本</th><td><strong><?php echo PHP_VERSION; ?></strong></td></tr>
                    <tr><th>运行方式 (SAPI)</th><td><?php echo php_sapi_name(); ?></td></tr>
                    <tr><th>脚本内存限制 (memory_limit)</th><td class="status-on"><?php echo ini_get('memory_limit'); ?></td></tr>
                    <tr><th>最大上传大小 (upload_max_filesize)</th><td class="status-on"><?php echo ini_get('upload_max_filesize'); ?></td></tr>
                    <tr><th>表单提交限制 (post_max_size)</th><td class="status-on"><?php echo ini_get('post_max_size'); ?></td></tr>
                    <tr><th>最大执行时间 (max_execution_time)</th><td class="status-on"><?php echo ini_get('max_execution_time'); ?> 秒</td></tr>
                    <tr><th>时区设置 (timezone)</th><td><?php echo date_default_timezone_get(); ?></td></tr>
                </table>
            </div>
        </div>

        <div class="grid">
            <!-- 模块 3：关键组件支持检测 -->
            <div class="card">
                <h3 class="card-title dark">🧩 常见建站组件支持</h3>
                <table>
                    <tr><th>MySQL 数据库 (mysqli)</th><td><?php echo is_support('mysqli'); ?></td></tr>
                    <tr><th>PDO 数据库抽象层 (PDO)</th><td><?php echo is_support('PDO'); ?></td></tr>
                    <tr><th>cURL 远程通信组件 (curl)</th><td><?php echo is_support('curl'); ?></td></tr>
                    <tr><th>GD 图像处理库 (gd)</th><td><?php echo is_support('gd'); ?></td></tr>
                    <tr><th>OpenSSL 加密组件 (openssl)</th><td><?php echo is_support('openssl'); ?></td></tr>
                    <tr><th>MBString 多字节字符串</th><td><?php echo is_support('mbstring'); ?></td></tr>
                    <tr><th>ZIP 压缩文件支持 (zip)</th><td><?php echo is_support('zip'); ?></td></tr>
                    <tr><th>Zlib 压缩扩展 (zlib)</th><td><?php echo is_support('zlib'); ?></td></tr>
                </table>
            </div>

            <!-- 模块 4：网络与安全函数检测 -->
            <div class="card">
                <h3 class="card-title dark">🛡️ 高危与网络函数可用性</h3>
                <table>
                    <tr><th>发送邮件 (mail)</th><td><?php echo check_func('mail'); ?></td></tr>
                    <tr><th>读取网络文件 (file_get_contents)</th><td><?php echo (ini_get('allow_url_fopen')) ? '<span class="status-on">可用</span>' : '<span class="status-off">被禁用 (allow_url_fopen=Off)</span>'; ?></td></tr>
                    <tr><th>Socket 端口通信 (fsockopen)</th><td><?php echo check_func('fsockopen'); ?></td></tr>
                    <tr><th>执行系统命令 (exec/system)</th><td><?php echo check_func('exec'); ?></td></tr>
                    <tr><th>目录写入权限 (根目录)</th><td><?php echo is_writable('.') ? '<span class="status-on">可写</span>' : '<span class="status-off">不可写</span>'; ?></td></tr>
                    <tr><th>性能测试 (300万次计算)</th><td style="color: #e67e22; font-weight: bold;"><?php echo test_math(); ?></td></tr>
                </table>
            </div>
        </div>

        <!-- 模块 5：被禁用的函数列表 -->
        <div class="card">
            <h3 class="card-title" style="background: #e74c3c;">🚫 主机已禁用的 PHP 函数 (disable_functions)</h3>
            <div class="code-box">
                <?php 
                $disable_funcs = ini_get('disable_functions');
                echo $disable_funcs ? str_replace(',', ', ', $disable_funcs) : '<span style="color:var(--success)">未禁用任何函数（极其罕见）</span>'; 
                ?>
            </div>
        </div>

        <div style="text-align: center; color: #888; margin-top: 20px; font-size: 0.9em;">
            页面生成耗时: <?php echo round(microtime(true) - $time_start, 4); ?> 秒
        </div>
    </div>
</body>
</html>